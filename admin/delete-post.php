<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Process post deletion
if (isset($_GET['id']) && isset($_GET['csrf_token'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid token";
        redirect(SITE_URL . '/admin/posts.php');
    }
    
    $id = intval($_GET['id']);
    
    // Delete post categories first
    $sql = "DELETE FROM post_categories WHERE post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Delete post comments
    $sql = "DELETE FROM comments WHERE post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Delete post likes
    $sql = "DELETE FROM likes WHERE post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Delete the post
    $sql = "DELETE FROM posts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Post deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete post: " . $stmt->error;
    }
    
    redirect(SITE_URL . '/admin/posts.php');
} else {
    $_SESSION['error'] = "Invalid request";
    redirect(SITE_URL . '/admin/posts.php');
}
?>
