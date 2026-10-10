<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';

$client_id = "776109473102-l9stq5md476nanlq6jf2jgho47aa96q0.apps.googleusercontent.com";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $credential = $_POST['credential'] ?? '';
    
    if (empty($credential)) {
        die("Missing Google credential");
    }

    // Verify token using Google's tokeninfo endpoint
    // urlencode: the credential is attacker-controlled and must not be able to
    // smuggle extra query parameters (e.g. &access_token=) into the lookup.
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    $payload = is_string($response) ? json_decode($response, true) : null;

    if (!is_array($payload) || isset($payload['error']) || empty($payload['sub'])) {
        error_log('google_auth: token rejected: ' . (is_array($payload) ? ($payload['error_description'] ?? $payload['error'] ?? 'no sub') : 'no response'));
        die("Google sign-in could not be verified. Please go back and try again.");
    }

    // Verify audience matches our Client ID and the token was issued by Google
    if (($payload['aud'] ?? '') !== $client_id
        || !in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
        die("Invalid audience");
    }
    
    // Google must have verified the email before we trust it to match an
    // existing account — otherwise an attacker could claim someone else's
    // address on their Google account and take over that account here.
    $email_verified = isset($payload['email_verified'])
        && ($payload['email_verified'] === true || $payload['email_verified'] === 'true');

    // Extract user info
    $google_id = $payload['sub'];
    $email = $payload['email'] ?? '';

    if (empty($email) || !$email_verified) {
        die("Your Google account does not have a verified email address, so we can't sign you in. Please verify your email with Google and try again.");
    }
    $name = $payload['name'] ?? ''; // Often Full Name (absent on some accounts)
    // Create a base username from the name
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    if (empty($base_username)) {
        $base_username = 'user';
    }

    try {
        // Check if user already exists by google_id or email
        $stmt = $pdo->prepare("SELECT id, username, role, google_id FROM users WHERE google_id = ? OR email = ?");
        $stmt->execute([$google_id, $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // User exists. Update google_id if it was null (e.g., they previously signed up with email)
            if (empty($user['google_id'])) {
                $update = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                $update->execute([$google_id, $user['id']]);
            }
            
            // Log them in
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_name'] = $payload['family_name'] ?? '';
            
            header("Location: dashboard");
            exit;
        } else {
            // New user, register them automatically
            
            // Ensure unique username
            $username = $base_username;
            $counter = 1;
            while (true) {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->rowCount() == 0) {
                    break;
                }
                $username = $base_username . $counter;
                $counter++;
            }
            
            // Random password for Google users to satisfy NOT NULL constraint
            $random_password = bin2hex(random_bytes(16));
            $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, google_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password, $google_id])) {
                $new_user_id = $pdo->lastInsertId();
                
                // Log them in
                session_regenerate_id(true);
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user'; // default role
                $_SESSION['last_name'] = $payload['family_name'] ?? '';
                
                header("Location: dashboard");
                exit;
            } else {
                die("Failed to create user account.");
            }
        }
    } catch(PDOException $e) {
        error_log('google_auth: ' . $e->getMessage());
        die("Sorry, something went wrong signing you in. Please try again.");
    }
} else {
    // Not a POST request
    header("Location: login");
    exit;
}
?>
