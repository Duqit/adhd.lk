<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/csrf.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    csrf_check();
    $user_id = $_SESSION['user_id'];
    
    try {
        // Delete the user. 
        // Note: The database uses "ON DELETE CASCADE" for tasks, moods, routines, journal_entries, and bookmarks.
        // So deleting the user automatically purges all their data.
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Destroy the session
        session_unset();
        session_destroy();
        
        // Redirect to homepage with a success message (optional, but a clean redirect is fine)
        header("Location: index.php?msg=account_deleted");
        exit;
        
    } catch(PDOException $e) {
        die("Error deleting account. Please contact support.");
    }
} else {
    // If someone tries to access this file directly via GET
    header("Location: dashboard");
    exit;
}
?>
