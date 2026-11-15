<?php
/**
 * XP bookkeeping shared by the logged-in tools.
 *
 * $amount may be negative: un-ticking a task or a habit has to take its XP back,
 * otherwise the same item pays out again every time it is toggled. Levels are
 * walked in both directions and XP never drops below zero.
 */
function adhd_award_xp(PDO $pdo, int $user_id, int $amount): void
{
    $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }

    $xp = (int)$user['xp'] + $amount;
    $level = (int)$user['level'];

    if ($xp >= $level * 100) {
        $xp -= $level * 100;
        $level++;
    }
    // Taking XP back can drop through one or more levels.
    while ($xp < 0 && $level > 1) {
        $level--;
        $xp += $level * 100;
    }
    if ($xp < 0) {
        $xp = 0;
    }

    $stmt = $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
    $stmt->execute([$xp, $level, $user_id]);
}
