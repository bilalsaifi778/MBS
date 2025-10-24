<?php
require_once 'config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Check if already liked
    $sql = "SELECT id FROM likes WHERE post_id = ? AND ip_address = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $post_id, $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        // Already liked, remove like
        $sql = "DELETE FROM likes WHERE post_id = ? AND ip_address = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $post_id, $ip_address);
        $stmt->execute();
        $action = 'unliked';
    } else {
        // Add like
        $sql = "INSERT INTO likes (post_id, ip_address) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $post_id, $ip_address);
        $stmt->execute();
        $action = 'liked';
    }
    
    // Get updated like count
    $sql = "SELECT COUNT(*) as count FROM likes WHERE post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $like_count = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'like_count' => $like_count
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
