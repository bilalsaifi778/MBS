<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Process bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['comment_ids'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
        redirect(SITE_URL . '/admin/comments.php');
    }
    
    $bulk_action = clean($_POST['bulk_action']);
    $comment_ids = $_POST['comment_ids'];
    
    if (!empty($comment_ids)) {
        $ids = implode(',', array_map('intval', $comment_ids));
        
        if ($bulk_action === 'approve') {
            $sql = "UPDATE comments SET status = 'approved' WHERE id IN ($ids)";
            $conn->query($sql);
            $_SESSION['success'] = count($comment_ids) . " comment(s) approved successfully";
        } elseif ($bulk_action === 'unapprove') {
            $sql = "UPDATE comments SET status = 'pending' WHERE id IN ($ids)";
            $conn->query($sql);
            $_SESSION['success'] = count($comment_ids) . " comment(s) unapproved successfully";
        } elseif ($bulk_action === 'delete') {
            $sql = "DELETE FROM comments WHERE id IN ($ids)";
            $conn->query($sql);
            $_SESSION['success'] = count($comment_ids) . " comment(s) deleted successfully";
        }
    }
    
    redirect(SITE_URL . '/admin/comments.php');
}

// Process single comment actions
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['token'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET['token'])) {
        $_SESSION['error'] = "Invalid token";
        redirect(SITE_URL . '/admin/comments.php');
    }
    
    $action = clean($_GET['action']);
    $id = intval($_GET['id']);
    
    if ($action === 'approve') {
        $sql = "UPDATE comments SET status = 'approved' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['success'] = "Comment approved successfully";
    } elseif ($action === 'unapprove') {
        $sql = "UPDATE comments SET status = 'pending' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['success'] = "Comment unapproved successfully";
    } elseif ($action === 'delete') {
        $sql = "DELETE FROM comments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['success'] = "Comment deleted successfully";
    } elseif ($action === 'reply' && isset($_POST['reply_content'])) {
        // Get the comment to reply to
        $sql = "SELECT post_id, parent_id FROM comments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $comment = $result->fetch_assoc();
        
        // If it's already a reply, use its parent
        $parent_id = ($comment['parent_id'] > 0) ? $comment['parent_id'] : $id;
        $post_id = $comment['post_id'];
        $content = clean($_POST['reply_content']);
        $admin_name = 'Admin';
        $admin_email = ADMIN_EMAIL;
        
        // Insert reply (comments table has no user_id column)
        $sql = "INSERT INTO comments (post_id, parent_id, name, email, content, status) VALUES (?, ?, ?, ?, ?, 'approved')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisss", $post_id, $parent_id, $admin_name, $admin_email, $content);
        $stmt->execute();
        $_SESSION['success'] = "Reply added successfully";
    }
    
    redirect(SITE_URL . '/admin/comments.php');
}

// Get comments with filtering
$status_filter = isset($_GET['status']) ? clean($_GET['status']) : '';
$hide_admin = isset($_GET['hide_admin']) ? (int)$_GET['hide_admin'] : 0;
$search_term = isset($_GET['search']) ? clean($_GET['search']) : '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_clauses[] = "c.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search_term)) {
    $where_clauses[] = "(c.content LIKE ? OR p.title LIKE ? OR c.name LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
// Hide admin replies if requested
if ($hide_admin) {
    $where_sql .= (empty($where_sql) ? "WHERE " : " AND ") . "(c.name IS NULL OR c.name != 'Admin')";
}

$sql = "SELECT c.*, p.title as post_title 
        FROM comments c 
        LEFT JOIN posts p ON c.post_id = p.id 
        $where_sql 
        ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$comments = [];

while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Comments';
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
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Admin CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
    
    <style>
        .comment-content {
            max-height: 100px;
            overflow-y: auto;
        }
        .status-approved {
            color: #198754;
        }
        .status-pending {
            color: #ffc107;
        }
        .status-spam {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Comments</h1>
                </div>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-comments me-1"></i>
                        Manage Comments
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <form method="get" action="" class="row g-3">
                                <div class="col-md-4">
                                    <select class="form-select" name="status" onchange="this.form.submit()">
                                        <option value="">All Comments</option>
                                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="spam" <?php echo $status_filter === 'spam' ? 'selected' : ''; ?>>Spam</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" placeholder="Search comments..." value="<?php echo htmlspecialchars($search_term); ?>">
                                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="hide_admin" name="hide_admin" value="1" <?php echo $hide_admin ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        <label class="form-check-label" for="hide_admin">Hide admin replies</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <a href="comments.php" class="btn btn-outline-secondary w-100">Reset</a>
                                </div>
                            </form>
                        </div>
                        
                        <form method="post" action="" id="comments-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <div class="row g-2 align-items-center">
                                    <div class="col-auto">
                                        <select class="form-select" name="bulk_action" id="bulk-action">
                                            <option value="">Bulk Actions</option>
                                            <option value="approve">Approve</option>
                                            <option value="unapprove">Unapprove</option>
                                            <option value="delete">Delete</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-primary" id="apply-bulk-action">Apply</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="commentsTable">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="select-all">
                                                </div>
                                            </th>
                                            <th>Author</th>
                                            <th>Comment</th>
                                            <th>Post</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($comments as $comment): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input comment-checkbox" type="checkbox" name="comment_ids[]" value="<?php echo $comment['id']; ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $authorName = $comment['name'] ?? ($comment['author_name'] ?? 'Anonymous');
                                                    $authorEmail = $comment['email'] ?? ($comment['author_email'] ?? '');
                                                    echo htmlspecialchars($authorName);
                                                    if (!empty($authorEmail)) {
                                                        echo '<br><small>' . htmlspecialchars($authorEmail) . '</small>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="comment-content">
                                                        <?php if ($comment['parent_id'] > 0): ?>
                                                            <span class="badge bg-secondary mb-1">Reply</span>
                                                        <?php endif; ?>
                                                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="<?php echo SITE_URL . '/post.php?id=' . $comment['post_id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($comment['post_title']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="status-<?php echo $comment['status']; ?>">
                                                        <?php echo ucfirst($comment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <?php if ($comment['status'] !== 'approved'): ?>
                                                            <a href="comments.php?action=approve&id=<?php echo $comment['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-success" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="comments.php?action=unapprove&id=<?php echo $comment['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-warning" title="Unapprove">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-primary reply-btn" 
                                                                data-id="<?php echo $comment['id']; ?>"
                                                                data-content="<?php echo htmlspecialchars($comment['content']); ?>"
                                                                title="Reply">
                                                            <i class="fas fa-reply"></i>
                                                        </button>
                                                        <a href="comments.php?action=delete&id=<?php echo $comment['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this comment?');"
                                                           title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="replyModalLabel">Reply to Comment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" id="reply-form">
                        <input type="hidden" name="token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Original Comment:</label>
                            <div class="card p-3 bg-light" id="original-comment"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reply_content" class="form-label">Your Reply:</label>
                            <textarea class="form-control" id="reply_content" name="reply_content" rows="4" required></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Submit Reply</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Admin JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#commentsTable').DataTable({
                order: [[5, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                columnDefs: [
                    { orderable: false, targets: [0, 6] }
                ]
            });
            
            // Select all checkbox
            $('#select-all').change(function() {
                $('.comment-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Apply bulk action
            $('#apply-bulk-action').click(function() {
                var action = $('#bulk-action').val();
                if (action === '') {
                    alert('Please select an action');
                    return;
                }
                
                var checked = $('.comment-checkbox:checked').length;
                if (checked === 0) {
                    alert('Please select at least one comment');
                    return;
                }
                
                if (action === 'delete' && !confirm('Are you sure you want to delete the selected comments?')) {
                    return;
                }
                
                $('#comments-form').submit();
            });
            
            // Reply button click
            $('.reply-btn').click(function() {
                var id = $(this).data('id');
                var content = $(this).data('content');
                
                $('#original-comment').text(content);
                $('#reply-form').attr('action', 'comments.php?action=reply&id=' + id + '&token=<?php echo $csrf_token; ?>');
                $('#replyModal').modal('show');
            });
        });
    </script>
</body>
</html>