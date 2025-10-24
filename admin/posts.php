<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();



// Handle bulk actions
if (isset($_POST['bulk_action']) && !empty($_POST['post_ids'])) {
    $action = clean($_POST['bulk_action']);
    $post_ids = $_POST['post_ids'];
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
    } else {
        $ids = implode(',', array_map('intval', $post_ids));
        
        if ($action === 'publish') {
            $sql = "UPDATE posts SET status = 'published' WHERE id IN ($ids)";
            $conn->query($sql);
            $_SESSION['success'] = "Selected posts have been published";
        } elseif ($action === 'draft') {
            $sql = "UPDATE posts SET status = 'draft' WHERE id IN ($ids)";
            $conn->query($sql);
            $_SESSION['success'] = "Selected posts have been moved to draft";
        } elseif ($action === 'delete') {
            $sql = "DELETE FROM posts WHERE id IN ($ids)";
            $conn->query($sql);
            $_SESSION['success'] = "Selected posts have been deleted";
        }
    }
    
    redirect(SITE_URL . '/admin/posts.php');
}

// Get search and filter parameters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status = isset($_GET['status']) ? clean($_GET['status']) : '';

// Build query
$sql = "SELECT p.*, 
        (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
         FROM categories c 
         JOIN post_categories pc ON c.id = pc.category_id 
         WHERE pc.post_id = p.id) as categories
        FROM posts p";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(p.title LIKE ? OR p.content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($category > 0) {
    $sql .= " JOIN post_categories pc ON p.id = pc.post_id";
    $where_clauses[] = "pc.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if (!empty($status)) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$posts = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
}

// Get all postable categories for filter (exclude Pages)
$categories = function_exists('getPostableCategories') ? getPostableCategories() : getCategories();

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Posts';
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
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Posts</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group">
                            <a href="add-post.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add New Post</a>
                        </div>
                    </div>
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
                
                <!-- Filter and Search -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-filter me-1"></i>
                        Filter Posts
                    </div>
                    <div class="card-body">
                        <form method="get" action="" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo $search; ?>" placeholder="Search by title or content">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo $cat['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Posts Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Posts
                    </div>
                    <div class="card-body">
                        <form method="post" action="" id="posts-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="mb-3">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <select class="form-select" name="bulk_action" id="bulk-action">
                                            <option value="">Bulk Actions</option>
                                            <option value="publish">Publish</option>
                                            <option value="draft">Move to Draft</option>
                                            <option value="delete">Delete</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary" id="apply-bulk-action">Apply</button>
                                    </div>
                                </div>
                            </div>
                            
                            <table class="table table-striped table-hover" id="posts-table">
                                <thead>
                                    <tr>
                                        <th width="30px">
                                            <input type="checkbox" id="select-all">
                                        </th>
                                        <th>Title</th>
                                        <th>Categories</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($posts)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No posts found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($posts as $post): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="post_ids[]" value="<?php echo $post['id']; ?>" class="post-checkbox">
                                                </td>
                                                <td><?php echo $post['title']; ?></td>
                                                <td><?php echo $post['categories']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $post['status'] == 'published' ? 'bg-success' : 'bg-warning'; ?>">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo SITE_URL; ?>/post.php?slug=<?php echo $post['slug']; ?>" class="btn btn-sm btn-info" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete-post.php?id=<?php echo $post['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this post?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>
                    </div>
                </div>
            </main>
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
            $('#posts-table').DataTable({
                "paging": true,
                "pageLength": 10,
                "searching": false,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true
            });
            
            // Select all checkbox
            $('#select-all').change(function() {
                $('.post-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Form submission validation
            $('#posts-form').submit(function(e) {
                var action = $('#bulk-action').val();
                var checked = $('.post-checkbox:checked').length;
                
                if (action === '' || checked === 0) {
                    e.preventDefault();
                    alert('Please select an action and at least one post');
                    return false;
                }
                
                if (action === 'delete') {
                    if (!confirm('Are you sure you want to delete the selected posts?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                return true;
            });
        });
    </script>
</body>
</html>