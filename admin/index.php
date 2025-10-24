<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Get statistics
$stats = getStatistics();

// Page title
$page_title = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Admin CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Posts</h6>
                                        <h2 class="card-text"><?php echo $stats['total_posts']; ?></h2>
                                    </div>
                                    <i class="fas fa-file-alt fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="posts.php" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Comments</h6>
                                        <h2 class="card-text"><?php echo $stats['total_comments']; ?></h2>
                                    </div>
                                    <i class="fas fa-comments fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="comments.php" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Likes</h6>
                                        <h2 class="card-text"><?php echo $stats['total_likes']; ?></h2>
                                    </div>
                                    <i class="fas fa-heart fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="#" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Views</h6>
                                        <h2 class="card-text"><?php echo $stats['total_views']; ?></h2>
                                    </div>
                                    <i class="fas fa-eye fa-3x opacity-50"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a href="#" class="text-white text-decoration-none">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Posts -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-file-alt me-1"></i>
                        Recent Posts
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT p.*, 
                                        (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                                         FROM categories c 
                                         JOIN post_categories pc ON c.id = pc.category_id 
                                         WHERE pc.post_id = p.id) as categories
                                        FROM posts p
                                        ORDER BY p.created_at DESC
                                        LIMIT 5";
                                $result = $conn->query($sql);
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . $row['title'] . '</td>';
                                        echo '<td>' . $row['categories'] . '</td>';
                                        echo '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
                                        echo '<td><span class="badge ' . ($row['status'] == 'published' ? 'bg-success' : 'bg-warning') . '">' . ucfirst($row['status']) . '</span></td>';
                                        echo '<td>
                                                <a href="edit-post.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                                <a href="delete-post.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this post?\')"><i class="fas fa-trash"></i></a>
                                              </td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">No posts found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="posts.php" class="btn btn-primary">View All Posts</a>
                    </div>
                </div>
                
                <!-- Recent Comments -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-comments me-1"></i>
                        Recent Comments
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Author</th>
                                    <th>Comment</th>
                                    <th>Post</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT c.*, p.title as post_title 
                                        FROM comments c
                                        JOIN posts p ON c.post_id = p.id
                                        ORDER BY c.created_at DESC
                                        LIMIT 5";
                                $result = $conn->query($sql);
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . $row['name'] . '</td>';
                                        echo '<td>' . substr($row['content'], 0, 50) . (strlen($row['content']) > 50 ? '...' : '') . '</td>';
                                        echo '<td>' . $row['post_title'] . '</td>';
                                        echo '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
                                        echo '<td><span class="badge ' . ($row['status'] == 'approved' ? 'bg-success' : ($row['status'] == 'pending' ? 'bg-warning' : 'bg-danger')) . '">' . ucfirst($row['status']) . '</span></td>';
                                        echo '<td>
                                                <a href="edit-comment.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                                <a href="delete-comment.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this comment?\')"><i class="fas fa-trash"></i></a>
                                              </td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No comments found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="comments.php" class="btn btn-primary">View All Comments</a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Admin JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>