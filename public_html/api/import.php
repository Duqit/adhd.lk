<?php
// api/import.php — one-time import of guest (localStorage) data into the
// logged-in user's account. Called by js/store.js after signup/login.
require_once __DIR__ . '/../includes/boot.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../db.php';
$user_id = $_SESSION['user_id'];

// Server-side idempotency: a guest import may only ever run once per account.
// (The client also guards this, but that flag lives in localStorage and is
// trivially replayable — this is the authoritative check.)
try {
    $stmt = $pdo->prepare("SELECT data_imported_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $already = $stmt->fetchColumn();
    if (!empty($already)) {
        // Report success so the client clears its local copy and stops retrying.
        echo json_encode(['ok' => true, 'imported' => [], 'note' => 'already imported']);
        exit;
    }
} catch (PDOException $e) {
    // Column missing (pre-migration) — fall through and import as before.
}

// Reject oversized bodies before parsing.
$len = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
if ($len > 1000000) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Payload too large']);
    exit;
}
$raw = file_get_contents('php://input');
if (strlen($raw) > 1000000) { // 1 MB safety cap
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'Payload too large']);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$is_date = function ($s) {
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
};

$imported = ['tasks' => 0, 'moods' => 0, 'journal' => 0, 'routines' => 0, 'habits' => 0];

try {
    $pdo->beginTransaction();

    // ---- Tasks ----
    if (!empty($data['tasks']) && is_array($data['tasks'])) {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, estimated_minutes, is_completed, created_at) VALUES (?, ?, ?, ?, ?)");
        foreach (array_slice($data['tasks'], 0, 200) as $t) {
            if (!is_array($t) || empty($t['title']) || !is_string($t['title'])) continue;
            $title = mb_substr(trim($t['title']), 0, 255);
            $est = isset($t['est']) && is_numeric($t['est']) ? (int)$t['est'] : null;
            $done = !empty($t['done']) ? 1 : 0;
            $date = $is_date($t['date'] ?? '') ? $t['date'] : date('Y-m-d');
            $stmt->execute([$user_id, $title, $est, $done, $date]);
            $imported['tasks']++;
        }
    }

    // ---- Moods ----
    if (!empty($data['moods']) && is_array($data['moods'])) {
        $stmt = $pdo->prepare("INSERT INTO moods (user_id, mood_score, note, created_at) VALUES (?, ?, ?, ?)");
        foreach (array_slice($data['moods'], 0, 400) as $m) {
            if (!is_array($m) || !isset($m['score'])) continue;
            $score = max(1, min(5, (int)$m['score']));
            $note = isset($m['note']) && is_string($m['note']) ? mb_substr(trim($m['note']), 0, 500) : '';
            $date = $is_date($m['date'] ?? '') ? $m['date'] . ' 12:00:00' : date('Y-m-d H:i:s');
            $stmt->execute([$user_id, $score, $note, $date]);
            $imported['moods']++;
        }
    }

    // ---- Journal ----
    if (!empty($data['journal']) && is_array($data['journal'])) {
        $stmt = $pdo->prepare("INSERT INTO journal_entries (user_id, prompt, entry_text, created_at) VALUES (?, ?, ?, ?)");
        foreach (array_slice($data['journal'], 0, 400) as $j) {
            if (!is_array($j) || empty($j['text']) || !is_string($j['text'])) continue;
            $prompt = isset($j['prompt']) && is_string($j['prompt']) ? mb_substr(trim($j['prompt']), 0, 255) : 'Journal entry';
            $text = mb_substr(trim($j['text']), 0, 20000);
            $date = $is_date($j['date'] ?? '') ? $j['date'] . ' 12:00:00' : date('Y-m-d H:i:s');
            $stmt->execute([$user_id, $prompt, $text, $date]);
            $imported['journal']++;
        }
    }

    // ---- Routines ----
    if (!empty($data['routines']) && is_array($data['routines'])) {
        $rStmt = $pdo->prepare("INSERT INTO routines (user_id, name) VALUES (?, ?)");
        $sStmt = $pdo->prepare("INSERT INTO routine_steps (routine_id, title, duration_minutes, step_order) VALUES (?, ?, ?, ?)");
        foreach (array_slice($data['routines'], 0, 50) as $r) {
            if (!is_array($r) || empty($r['name']) || !is_string($r['name']) || empty($r['steps']) || !is_array($r['steps'])) continue;
            $rStmt->execute([$user_id, mb_substr(trim($r['name']), 0, 255)]);
            $routine_id = $pdo->lastInsertId();
            $order = 1;
            foreach (array_slice($r['steps'], 0, 50) as $s) {
                if (!is_array($s) || empty($s['title']) || !is_string($s['title'])) continue;
                $mins = isset($s['duration_minutes']) && is_numeric($s['duration_minutes']) ? max(1, (int)$s['duration_minutes']) : 1;
                $sStmt->execute([$routine_id, mb_substr(trim($s['title']), 0, 255), $mins, $order++]);
            }
            $imported['routines']++;
        }
    }

    // ---- Habits & logs ----
    if (!empty($data['habits']) && is_array($data['habits'])) {
        $hStmt = $pdo->prepare("INSERT INTO habits (user_id, name, emoji) VALUES (?, ?, ?)");
        $lStmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, user_id, log_date) VALUES (?, ?, ?)");
        $logs = (isset($data['habitLogs']) && is_array($data['habitLogs'])) ? $data['habitLogs'] : [];
        foreach (array_slice($data['habits'], 0, 50) as $h) {
            if (!is_array($h) || empty($h['name']) || !is_string($h['name'])) continue;
            $emoji = isset($h['emoji']) && is_string($h['emoji']) ? mb_substr(trim($h['emoji']), 0, 8) : '✅';
            $hStmt->execute([$user_id, mb_substr(trim($h['name']), 0, 120), $emoji]);
            $habit_id = $pdo->lastInsertId();
            $hLogs = (isset($h['id']) && isset($logs[$h['id']]) && is_array($logs[$h['id']])) ? $logs[$h['id']] : [];
            foreach (array_slice(array_unique($hLogs), 0, 400) as $d) {
                if ($is_date($d)) {
                    $lStmt->execute([$habit_id, $user_id, $d]);
                }
            }
            $imported['habits']++;
        }
    }

    // Stamp the account so this can never run twice.
    try {
        $pdo->prepare("UPDATE users SET data_imported_at = NOW() WHERE id = ?")->execute([$user_id]);
    } catch (PDOException $e) {
        // Column missing (pre-migration) — not fatal.
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'imported' => $imported]);
} catch (Exception $e) {
    // rollBack() throws if no transaction is active (e.g. beginTransaction failed).
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Import failed']);
}
