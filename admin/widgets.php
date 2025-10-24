<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Process form submission for adding/editing widget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
        redirect(SITE_URL . '/admin/widgets.php');
    }
    
$action = clean($_POST['action']);
    
    if ($action === 'reorder' && isset($_POST['ids'])) {
        // Update ordering from drag-and-drop
        $ids = $_POST['ids'];
        if (is_array($ids)) {
            $order = 1; // Start from 1 for widgets
            foreach ($ids as $id) {
                $id = intval($id);
                $stmt = $conn->prepare("UPDATE widgets SET sort_order = ? WHERE id = ?");
                $stmt->bind_param("ii", $order, $id);
                $stmt->execute();
                $order++;
            }
        }
        // For AJAX response, avoid redirect
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    } else if ($action === 'add') {
        // Add new widget
        $title = clean($_POST['title']);
        $type = clean($_POST['type']);
        $content = $_POST['content']; // Don't clean HTML content
        
        // For advertisement and HTML widgets, ensure content isn't double-encoded
        if ($type === 'advertisement' || $type === 'html') {
            // Decode any HTML entities that might have been added by the form/browser
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        $position = clean($_POST['position']);
        // Allow advertisement widgets to be placed in any position
        // Removed forced sidebar position constraint
        $status = clean($_POST['status']);
        $is_active = ($status === 'active') ? 1 : 0;
        
        if (empty($title)) {
            $_SESSION['error'] = "Widget title is required";
            redirect(SITE_URL . '/admin/widgets.php');
        }
        
        // Add sort_order for proper ordering
        $sort_order = intval($_POST['sort_order'] ?? 1);
        
        // Add type column if it doesn't exist
        $conn->query("ALTER TABLE widgets ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'html'");
        
        // Store all fields (alignment removed)
        $sql = "INSERT INTO widgets (title, content, position, is_active, sort_order, type) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiis", $title, $content, $position, $is_active, $sort_order, $type);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Widget added successfully";
        } else {
            $_SESSION['error'] = "Failed to add widget: " . $stmt->error;
        }
        
        redirect(SITE_URL . '/admin/widgets.php');
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        // Edit existing widget
        $id = intval($_POST['id']);
        $title = clean($_POST['title']);
        $type = clean($_POST['type']);
        $content = $_POST['content']; // Don't clean HTML content
        
        // For advertisement and HTML widgets, ensure content isn't double-encoded
        if ($type === 'advertisement' || $type === 'html') {
            // Decode any HTML entities that might have been added by the form/browser
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        $position = clean($_POST['position']);
        // Allow advertisement widgets to be placed in any position
        // Removed forced sidebar position constraint
        $status = clean($_POST['status']);
        $is_active = ($status === 'active') ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 1);
        
        if (empty($title)) {
            $_SESSION['error'] = "Widget title is required";
            redirect(SITE_URL . '/admin/widgets.php');
        }
        
        // Add type column if it doesn't exist
        $conn->query("ALTER TABLE widgets ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'html'");
        
        $sql = "UPDATE widgets SET title = ?, content = ?, position = ?, is_active = ?, sort_order = ?, type = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiisi", $title, $content, $position, $is_active, $sort_order, $type, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Widget updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update widget: " . $stmt->error;
        }
        
        redirect(SITE_URL . '/admin/widgets.php');
    } elseif ($action === 'bulk_code_update') {
        // Bulk replace/append widget content for selected IDs
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $mode = isset($_POST['mode']) && $_POST['mode'] === 'append' ? 'append' : 'replace';
        $content = $_POST['content'] ?? '';
        // Decode entities to store raw ad/html code
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (empty($ids)) {
            $_SESSION['error'] = "No widgets selected for bulk update.";
            redirect(SITE_URL . '/admin/widgets.php');
        }
        $updated = 0;
        if ($mode === 'append') {
            $stmt = $conn->prepare("UPDATE widgets SET content = CONCAT(COALESCE(content,''), ?) WHERE id = ?");
            foreach ($ids as $wid) {
                $stmt->bind_param("si", $content, $wid);
                if ($stmt->execute()) { $updated++; }
            }
        } else { // replace
            $stmt = $conn->prepare("UPDATE widgets SET content = ? WHERE id = ?");
            foreach ($ids as $wid) {
                $stmt->bind_param("si", $content, $wid);
                if ($stmt->execute()) { $updated++; }
            }
        }
        $_SESSION['success'] = "Updated code for {$updated} widget(s).";
        redirect(SITE_URL . '/admin/widgets.php');
    }
}

// Process reset auto-increment
if (isset($_GET['action']) && $_GET['action'] === 'reset_ids' && isset($_GET['token'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET['token'])) {
        $_SESSION['error'] = "Invalid token";
        redirect(SITE_URL . '/admin/widgets.php');
    }
    
    // Reset auto-increment to 1
    if ($conn->query("ALTER TABLE widgets AUTO_INCREMENT = 1")) {
        $_SESSION['success'] = "Widget IDs reset successfully";
    } else {
        $_SESSION['error'] = "Failed to reset widget IDs: " . $conn->error;
    }
    
    redirect(SITE_URL . '/admin/widgets.php');
}

// Process widget deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['token'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET['token'])) {
        $_SESSION['error'] = "Invalid token";
        redirect(SITE_URL . '/admin/widgets.php');
    }
    
    $id = intval($_GET['id']);
    
    // Delete widget
    $sql = "DELETE FROM widgets WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Reset auto-increment if this was the last widget
        $count_result = $conn->query("SELECT COUNT(*) as count FROM widgets");
        $count_row = $count_result->fetch_assoc();
        
        if ($count_row['count'] == 0) {
            // Reset auto-increment to 1 when no widgets remain
            $conn->query("ALTER TABLE widgets AUTO_INCREMENT = 1");
        }
        
        $_SESSION['success'] = "Widget deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete widget: " . $stmt->error;
    }
    
    redirect(SITE_URL . '/admin/widgets.php');
}

// Get all widgets
$widgets = getWidgets();

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Widgets';
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
    
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    
    <!-- Admin CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
    
    <style>
    /* Widget drag and drop styles */
    .handle {
        cursor: move;
        cursor: grab;
        color: #999;
        text-align: center;
        padding: 8px !important;
    }
    
    .handle:hover {
        color: #007bff;
    }
    
    .handle:active,
    .handle:focus {
        cursor: grabbing;
    }
    
    .sortable-row.ui-sortable-helper {
        background-color: #f8f9fa;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .sortable-row:hover {
        background-color: #f8f9fa;
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
                    <h1 class="h2">Widgets</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addWidgetModal">
                                <i class="fas fa-plus"></i> Add New Widget
                            </button>
                            <a href="widgets.php?action=reset_ids&token=<?php echo $csrf_token; ?>" 
                               class="btn btn-sm btn-warning" 
                               onclick="return confirm('Are you sure you want to reset widget IDs? This will make new widgets start from ID 1.');">
                                <i class="fas fa-redo"></i> Reset IDs
                            </a>
                            <button type="button" id="openBulkCodeModal" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bulkCodeModal" disabled>
                                <i class="fas fa-exchange-alt"></i> Bulk Replace Code
                            </button>
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
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-puzzle-piece me-1"></i>
                        Manage Widgets
                        <small class="text-muted ms-2">
                            <i class="fas fa-grip-vertical me-1"></i>
                            Drag and drop rows to reorder widgets
                        </small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="widgetsTable">
                                <thead>
                                    <tr>
                                        <th style="width:38px;"><i class="fas fa-grip-vertical"></i></th>
                                        <th style="width:26px;"><input type="checkbox" id="selectAll"></th>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($widgets as $widget): ?>
                                        <tr class="sortable-row" data-id="<?php echo $widget['id']; ?>">
                                            <td class="handle"><i class="fas fa-grip-vertical"></i></td>
                                            <td><input type="checkbox" class="row-select" value="<?php echo $widget['id']; ?>"></td>
                                            <td><?php echo $widget['id']; ?></td>
                                            <td><?php echo htmlspecialchars($widget['title']); ?></td>
                                            <td><?php echo isset($widget['type']) ? ucfirst($widget['type']) : 'N/A'; ?></td>
                                            <td><?php echo $widget['position']; ?></td>
                                            <td>
                                                <?php if (!empty($widget['is_active'])): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-widget" 
                                                        data-id="<?php echo $widget['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($widget['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-type="<?php echo htmlspecialchars($widget['type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-content="<?php echo htmlspecialchars($widget['content'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-position="<?php echo htmlspecialchars($widget['position'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-status="<?php echo $widget['is_active'] ? 'active' : 'inactive'; ?>"
                                                        data-sort_order="<?php echo $widget['sort_order'] ?? 1; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="widgets.php?action=delete&id=<?php echo $widget['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                                   class="btn btn-sm btn-danger delete-widget"
                                                   onclick="return confirm('Are you sure you want to delete this widget?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Widget Types Guide
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        Popular Posts
                                    </div>
                                    <div class="card-body">
                                        <p>Displays a list of the most popular posts based on view count.</p>
                                        <p><strong>Type:</strong> popular_posts</p>
                                        <p><strong>Content:</strong> Leave empty or specify the number of posts to display (default: 3)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white">
                                        Latest Posts
                                    </div>
                                    <div class="card-body">
                                        <p>Displays a list of the most recent posts.</p>
                                        <p><strong>Type:</strong> latest_posts</p>
                                        <p><strong>Content:</strong> Leave empty or specify the number of posts to display (default: 3)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        Categories
                                    </div>
                                    <div class="card-body">
                                        <p>Displays a list of all categories with post counts.</p>
                                        <p><strong>Type:</strong> categories</p>
                                        <p><strong>Content:</strong> Leave empty</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header bg-warning text-dark">
                                                        Custom HTML
                                                    </div>
                                                    <div class="card-body">
                                                        <p>Displays custom HTML, CSS, and JavaScript content.</p>
                                                        <p><strong>Type:</strong> html</p>
                                                        <p><strong>Content:</strong> Your HTML/CSS/JS code</p>
                                                    </div>
                                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-danger text-white">
                                        Advertisement
                                    </div>
                                    <div class="card-body">
                                        <p>Displays advertisement code (AdSense, Adsterra, Propeller Ads).</p>
                                        <p><strong>Type:</strong> advertisement</p>
                                        <p><strong>Content:</strong> Your ad code (HTML/JavaScript)</p>
                                        <hr>
                                        <p class="mb-1"><strong>Recommended Ad Sizes:</strong></p>
                                        <ul class="small mb-0">
                                            <li><strong>Sidebar:</strong> 300x250, 300x600</li>
                                            <li><strong>Header/Footer:</strong> 728x90, 970x90</li>
                                            <li><strong>Post Content:</strong> 336x280, 728x90</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-secondary text-white">
                                        Search
                                    </div>
                                    <div class="card-body">
                                        <p>Displays a search form.</p>
                                        <p><strong>Type:</strong> search</p>
                                        <p><strong>Content:</strong> Leave empty</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Widget Modal -->
    <div class="modal fade" id="addWidgetModal" tabindex="-1" aria-labelledby="addWidgetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addWidgetModalLabel">Add New Widget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="title" class="form-label">Widget Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label for="type" class="form-label">Widget Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="popular_posts">Popular Posts</option>
                                    <option value="latest_posts">Latest Posts</option>
                                    <option value="categories">Categories</option>
                                    <option value="html">Custom HTML</option>
                                    <option value="advertisement">Advertisement</option>
                                    <option value="search">Search</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                <select class="form-select" id="position" name="position" required>
                                    <optgroup label="Site Wide">
                                        <option value="header">Header - Top of Site (728x90, 970x90)</option>
                                    </optgroup>
                                    <optgroup label="Homepage">
                                        <option value="home_top">Homepage - Top (728x90)</option>
                                        <option value="home_middle">Homepage - Middle (728x90, 336x280)</option>
                                        <option value="home_bottom">Homepage - Bottom (728x90)</option>
                                    </optgroup>
                                    <optgroup label="Post/Article Page">
                                        <option value="post_top">Post - Before Content (728x90)</option>
                                        <option value="post_middle">Post - Middle of Content (336x280)</option>
                                        <option value="post_bottom">Post - After Content (728x90)</option>
                                    </optgroup>
                                    <optgroup label="Sidebar & Other">
                                        <option value="sidebar" selected>Sidebar - Right Column (300x250, 300x600)</option>
                                    </optgroup>
                                </select>
                    <div class="form-text" id="position-help">Recommended ad sizes shown in parentheses. Sidebar is recommended for Advertisement widgets.</div>
                            </div>
                            <div class="col-md-3">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" min="1" value="1">
                                <div class="form-text">Lower numbers appear first</div>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5"></textarea>
                            <div class="form-text">For HTML and Advertisement widgets, enter your custom code. For other widget types, this field is optional.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Add Widget</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Replace Code Modal -->
    <div class="modal fade" id="bulkCodeModal" tabindex="-1" aria-labelledby="bulkCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkCodeModalLabel"><i class="fas fa-exchange-alt me-2"></i>Bulk Replace Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="bulk_code_update">
                    <div id="bulkIds"></div>
                    <div class="modal-body">
                        <div class="alert alert-info small">This will update the Content field for all selected widgets. For Advertisement/HTML widgets, code is stored as-is.</div>
                        <div class="mb-3">
                            <label class="form-label">Mode</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="bulkReplace" value="replace" checked>
                                    <label class="form-check-label" for="bulkReplace">Replace existing code</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="bulkAppend" value="append">
                                    <label class="form-check-label" for="bulkAppend">Append to existing code</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Code</label>
                            <textarea class="form-control" name="content" rows="8" placeholder="Paste HTML/JS or text to insert..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-save me-1"></i>Apply to Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Widget Modal -->
    <div class="modal fade" id="editWidgetModal" tabindex="-1" aria-labelledby="editWidgetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editWidgetModalLabel">Edit Widget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit-title" class="form-label">Widget Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit-title" name="title" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit-type" class="form-label">Widget Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-type" name="type" required>
                                    <option value="popular_posts">Popular Posts</option>
                                    <option value="latest_posts">Latest Posts</option>
                                    <option value="categories">Categories</option>
                                    <option value="html">Custom HTML</option>
                                    <option value="advertisement">Advertisement</option>
                                    <option value="search">Search</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="edit-position" class="form-label">Position <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-position" name="position" required>
                                    <optgroup label="Site Wide">
                                        <option value="header">Header - Top of Site (728x90, 970x90)</option>
                                        <option value="footer">Footer - Bottom of Site (728x90)</option>
                                    </optgroup>
                                    <optgroup label="Homepage">
                                        <option value="home_top">Homepage - Top (728x90)</option>
                                        <option value="home_middle">Homepage - Middle (728x90, 336x280)</option>
                                        <option value="home_bottom">Homepage - Bottom (728x90)</option>
                                    </optgroup>
                                    <optgroup label="Post/Article Page">
                                        <option value="post_top">Post - Before Content (728x90)</option>
                                        <option value="post_middle">Post - Middle of Content (336x280)</option>
                                        <option value="post_bottom">Post - After Content (728x90)</option>
                                    </optgroup>
                                    <optgroup label="Sidebar & Other">
                                        <option value="sidebar">Sidebar - Right Column (300x250, 300x600)</option>
                                        <option value="pre_footer">Before Footer (728x90)</option>
                                    </optgroup>
                                </select>
                                <div class="form-text" id="edit-position-help">Recommended ad sizes shown in parentheses. Sidebar is recommended for Advertisement widgets.</div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit-sort_order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="edit-sort_order" name="sort_order" min="1">
                                <div class="form-text">Lower numbers appear first</div>
                            </div>
                            <div class="col-md-3">
                                <label for="edit-status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit-content" class="form-label">Content</label>
                            <textarea class="form-control" id="edit-content" name="content" rows="5"></textarea>
                            <div class="form-text">For HTML and Advertisement widgets, enter your custom code. For other widget types, this field is optional.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Widget</button>
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
    
    <!-- jQuery UI for drag and drop -->
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    
    <!-- Admin JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#widgetsTable').DataTable({
                order: [[5, 'asc']], // Position column index after adding selection column
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
            
            // Enable drag-and-drop ordering (requires jQuery UI)
            $('#widgetsTable tbody').sortable({
                handle: '.handle',
                helper: function(e, tr){
                    var $originals = tr.children();
                    var $helper = tr.clone();
                    $helper.children().each(function(index){
                        $(this).width($originals.eq(index).width());
                    });
                    return $helper;
                },
                stop: function(){
                    var orderedIds = [];
                    $('#widgetsTable tbody tr.sortable-row').each(function(index){
                        orderedIds.push($(this).data('id'));
                    });
                    $.post('widgets.php', { action: 'reorder', csrf_token: '<?php echo $csrf_token; ?>', ids: orderedIds }, function(resp){
                        // Show a success message
                        if (resp.status === 'ok') {
                            // Create a temporary success message
                            var successMsg = $('<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                                '<i class="fas fa-check-circle me-1"></i> Widget order updated successfully!' +
                                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                                '</div>');
                            $('.card-header').after(successMsg);
                            // Auto-hide after 3 seconds
                            setTimeout(function() {
                                successMsg.alert('close');
                            }, 3000);
                        }
                    }).fail(function() {
                        // Show error message
                        var errorMsg = $('<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                            '<i class="fas fa-exclamation-circle me-1"></i> Failed to update widget order. Please try again.' +
                            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                            '</div>');
                        $('.card-header').after(errorMsg);
                        setTimeout(function() {
                            errorMsg.alert('close');
                        }, 5000);
                    });
                }
            }).disableSelection();
            
            // Initialize content editors based on initial widget type
            // Note: Summernote will be initialized dynamically based on widget type
            
            // Show/hide content editor based on widget type
            function toggleContentEditor(typeSelector, contentSelector) {
                var type = $(typeSelector).val();
                // Map to proper position select based on which form is active
                var positionSelector = (typeSelector === '#type') ? '#position' : '#edit-position';

                if (type === 'html' || type === 'advertisement') {
                    // For HTML and Advertisement, destroy Summernote and use plain textarea
                    var $textarea = $(contentSelector);
                    
                    // Check if Summernote is initialized on this element
                    if ($textarea.next('.note-editor').length > 0) {
                        $textarea.summernote('destroy');
                    }
                    
                    // Reset to plain textarea
                    $textarea.attr('placeholder', 'Enter your HTML/CSS/JS or advertisement code here...');
                    $textarea.css({
                        'font-family': 'monospace',
                        'font-size': '13px',
                        'background-color': '#f8f9fa',
                        'min-height': '200px'
                    });
                    $textarea.removeClass('note-editable');
                } else {
                    // For other types, initialize Summernote if not already initialized
                    var $textarea = $(contentSelector);
                    if ($textarea.next('.note-editor').length === 0) {
                        $textarea.summernote({
                            height: 200,
                            toolbar: [
                                ['style', ['style']],
                                ['font', ['bold', 'underline', 'clear']],
                                ['color', ['color']],
                                ['para', ['ul', 'ol', 'paragraph']],
                                ['table', ['table']],
                                ['insert', ['link', 'picture']],
                                ['view', ['fullscreen', 'codeview', 'help']]
                            ]
                        });
                    }
                }

                // Update help text based on widget type
                var helpSelector = (typeSelector === '#type') ? '#position-help' : '#edit-position-help';
                if (type === 'advertisement') {
                    // Show ad-specific guidance but don't disable the dropdown
                    $(helpSelector).html('Recommended ad sizes shown in parentheses. <strong>Sidebar (300x250)</strong> is most common for ads.');
                    $(positionSelector).prop('disabled', false); // Don't disable - let user choose
                } else {
                    // Reset to standard help text for other widget types
                    $(helpSelector).html('Recommended ad sizes shown in parentheses. Sidebar is recommended for Advertisement widgets.');
                    $(positionSelector).prop('disabled', false);
                }
            }
            
            $('#type').change(function() {
                toggleContentEditor('#type', '#content');
            });
            
            $('#edit-type').change(function() {
                toggleContentEditor('#edit-type', '#edit-content');
            });
            
            // Initialize proper editor on page load
            toggleContentEditor('#type', '#content');
            toggleContentEditor('#edit-type', '#edit-content');
            
            // Function to update ad size recommendation based on position
            function updateAdSizeRecommendation(positionSelector, helpSelector) {
                var position = $(positionSelector).val();
                var adSizes = {
                    'header': '728x90 (Leaderboard) or 970x90 (Large Leaderboard)',
                    'footer': '728x90 (Leaderboard)',
                    'home_top': '728x90 (Leaderboard)',
                    'home_middle': '728x90 (Leaderboard) or 336x280 (Large Rectangle)',
                    'home_bottom': '728x90 (Leaderboard)',
                    'post_top': '728x90 (Leaderboard)',
                    'post_middle': '336x280 (Large Rectangle) or 300x250 (Medium Rectangle)',
                    'post_bottom': '728x90 (Leaderboard)',
                    'sidebar': '300x250 (Medium Rectangle) or 300x600 (Half Page)',
                    'pre_footer': '728x90 (Leaderboard)'
                };
                
                var sizeText = adSizes[position] || 'Various sizes supported';
                $(helpSelector).html('<strong>Recommended ad size:</strong> ' + sizeText);
            }
            
            // Update ad size on position change for Add form
            $('#position').change(function() {
                updateAdSizeRecommendation('#position', '#position-help');
            });
            
            // Update ad size on position change for Edit form
            $('#edit-position').change(function() {
                updateAdSizeRecommendation('#edit-position', '#edit-position-help');
            });
            
            // Initialize ad size on page load
            updateAdSizeRecommendation('#position', '#position-help');
            updateAdSizeRecommendation('#edit-position', '#edit-position-help');

            // Row selection handling for bulk updates
            var selectedIds = new Set();
            function updateBulkButton(){
                var btn = document.getElementById('openBulkCodeModal');
                if (!btn) return;
                btn.disabled = selectedIds.size === 0;
            }
            $('#selectAll').on('change', function(){
                var checked = $(this).is(':checked');
                $('.row-select').prop('checked', checked).trigger('change.selectonly');
            });
            $(document).on('change.selectonly', '.row-select', function(){
                var id = parseInt($(this).val(), 10);
                if ($(this).is(':checked')) selectedIds.add(id); else selectedIds.delete(id);
                updateBulkButton();
            });
            $('#openBulkCodeModal').on('click', function(){
                var container = $('#bulkIds');
                container.empty();
                selectedIds.forEach(function(id){
                    container.append('<input type="hidden" name="ids[]" value="'+id+'">');
                });
            });
            
            // Edit widget button click
            $(document).on('click', '.edit-widget', function() {
                var $btn = $(this);
                var id = $btn.data('id');
                var title = $btn.data('title');
                var type = $btn.data('type');
                var content = $btn.data('content') || '';
                var position = $btn.data('position');
                var status = $btn.data('status');
                var sort_order = $btn.data('sort_order') || 1;
                
                // For HTML/Advertisement widgets, decode HTML entities to show raw code
                // For other widgets, also decode to show original content
                if (content) {
                    // Use textarea to decode HTML entities properly
                    var txt = document.createElement('textarea');
                    txt.innerHTML = content;
                    content = txt.value;
                }
                
                // Set basic fields first
                $('#edit-id').val(id);
                $('#edit-title').val(title);
                $('#edit-type').val(type);
                $('#edit-position').val(position);
                $('#edit-status').val(status);
                $('#edit-sort_order').val(sort_order);
                
                // Destroy any existing editor first
                var $editContent = $('#edit-content');
                if ($editContent.next('.note-editor').length > 0) {
                    $editContent.summernote('destroy');
                }
                
                // Configure editor based on widget type
                toggleContentEditor('#edit-type', '#edit-content');
                
                // Set content after a brief delay
                setTimeout(function() {
                    if (type === 'html' || type === 'advertisement') {
                        // For HTML/Advertisement, set content directly to textarea
                        $('#edit-content').val(content || '');
                    } else {
                        // For other types, use Summernote if it's initialized
                        if ($('#edit-content').next('.note-editor').length > 0) {
                            $('#edit-content').summernote('code', content || '');
                        } else {
                            $('#edit-content').val(content || '');
                        }
                    }
                }, 150);
                
                // Show modal
                $('#editWidgetModal').modal('show');
            });
        });
    </script>
</body>
</html>