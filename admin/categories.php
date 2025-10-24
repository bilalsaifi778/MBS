<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Process form submission for adding/editing category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
        redirect(SITE_URL . '/admin/categories.php');
    }
    
    $action = clean($_POST['action']);
    
    if ($action === 'reorder' && isset($_POST['ids'])) {
        // Update ordering from drag-and-drop
        ensureCategoryDisplayColumns();
        $ids = $_POST['ids'];
        if (is_array($ids)) {
            $order = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                $stmt = $conn->prepare("UPDATE categories SET menu_order = ? WHERE id = ?");
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
        // Add new category
        $name = clean($_POST['name']);
        $slug = createUniqueSlug($name);
        $description = clean($_POST['description']);
        // Display configuration
        ensureCategoryDisplayColumns();
        $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
        $menu_location = isset($_POST['menu_location']) ? clean($_POST['menu_location']) : 'primary_header';
        $display_type = isset($_POST['display_type']) ? clean($_POST['display_type']) : 'dropdown';
        $menu_order = isset($_POST['menu_order']) ? intval($_POST['menu_order']) : 0;
        $secondary_dropdown_name = isset($_POST['secondary_dropdown_name']) ? clean($_POST['secondary_dropdown_name']) : 'More';
        
        if (empty($name)) {
            $_SESSION['error'] = "Category name is required";
            redirect(SITE_URL . '/admin/categories.php');
        }
        
        $sql = "INSERT INTO categories (name, slug, description, show_in_menu, menu_location, display_type, menu_order, secondary_dropdown_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissis", $name, $slug, $description, $show_in_menu, $menu_location, $display_type, $menu_order, $secondary_dropdown_name);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category added successfully";
        } else {
            $_SESSION['error'] = "Failed to add category: " . $stmt->error;
        }
        
        redirect(SITE_URL . '/admin/categories.php');
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        // Edit existing category
        $id = intval($_POST['id']);
        $name = clean($_POST['name']);
        $description = clean($_POST['description']);
        // Display configuration
        ensureCategoryDisplayColumns();
        $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
        $menu_location = isset($_POST['menu_location']) ? clean($_POST['menu_location']) : 'primary_header';
        $display_type = isset($_POST['display_type']) ? clean($_POST['display_type']) : 'dropdown';
        $menu_order = isset($_POST['menu_order']) ? intval($_POST['menu_order']) : 0;
        $secondary_dropdown_name = isset($_POST['secondary_dropdown_name']) ? clean($_POST['secondary_dropdown_name']) : 'More';
        
        if (empty($name)) {
            $_SESSION['error'] = "Category name is required";
            redirect(SITE_URL . '/admin/categories.php');
        }
        
        $sql = "UPDATE categories SET name = ?, description = ?, show_in_menu = ?, menu_location = ?, display_type = ?, menu_order = ?, secondary_dropdown_name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissisi", $name, $description, $show_in_menu, $menu_location, $display_type, $menu_order, $secondary_dropdown_name, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category updated successfully";
        } else {
            $_SESSION['error'] = "Failed to update category: " . $stmt->error;
        }
        
        redirect(SITE_URL . '/admin/categories.php');
    }
}

// Process reset categories ID
if (isset($_GET['action']) && $_GET['action'] === 'reset_ids' && isset($_GET['token'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET['token'])) {
        $_SESSION['error'] = "Invalid token";
        redirect(SITE_URL . '/admin/categories.php');
    }
    
    try {
        // Get all categories ordered by current ID
        $result = $conn->query("SELECT id, name FROM categories WHERE id NOT LIKE 'page_%' ORDER BY id ASC");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        if (empty($categories)) {
            $_SESSION['error'] = "No categories found to reset.";
            redirect(SITE_URL . '/admin/categories.php');
        }
        
        // Create a backup of current IDs
        $backup = [];
        foreach ($categories as $cat) {
            $backup[$cat['id']] = $cat['name'];
        }
        
        // Step 1: Update post_categories table with new IDs
        $new_id = 1;
        foreach ($categories as $cat) {
            if ($cat['id'] != $new_id) {
                // Update post_categories table
                $stmt = $conn->prepare("UPDATE post_categories SET category_id = ? WHERE category_id = ?");
                $stmt->bind_param("ii", $new_id, $cat['id']);
                $stmt->execute();
            }
            $new_id++;
        }
        
        // Step 2: Update categories table with new IDs
        $new_id = 1;
        foreach ($categories as $cat) {
            if ($cat['id'] != $new_id) {
                // First, update the category ID
                $stmt = $conn->prepare("UPDATE categories SET id = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_id, $cat['id']);
                $stmt->execute();
            }
            $new_id++;
        }
        
        // Step 3: Reset AUTO_INCREMENT
        $conn->query("ALTER TABLE categories AUTO_INCREMENT = " . $new_id);
        
        $_SESSION['success'] = "Categories ID reset successfully! All categories have been renumbered sequentially starting from 1.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to reset categories ID: " . $e->getMessage();
    }
    
    redirect(SITE_URL . '/admin/categories.php');
}

// Process category deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['token'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_GET['token'])) {
        $_SESSION['error'] = "Invalid token";
        redirect(SITE_URL . '/admin/categories.php');
    }
    
    $id = intval($_GET['id']);
    
    // If category is in use, detach it from posts first
    $sql = "SELECT COUNT(*) as count FROM post_categories WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row && (int)$row['count'] > 0) {
        $del = $conn->prepare("DELETE FROM post_categories WHERE category_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
    }
    
    // Delete category
    $sql = "DELETE FROM categories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Category deleted successfully";
    } else {
        $_SESSION['error'] = "Failed to delete category: " . $stmt->error;
    }
    
    redirect(SITE_URL . '/admin/categories.php');
}

// Get all categories ordered by menu_order, then name
ensureCategoryDisplayColumns();

// No auto-recreation of default categories; deleted items should stay deleted

$sql = "SELECT * FROM categories ORDER BY menu_order ASC, name ASC";
$result = $conn->query($sql);
$categories = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Categories';
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
                    <h1 class="h2">Categories</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus"></i> Add New Category
                            </button>
                            <a href="categories.php?action=reset_ids&token=<?php echo $csrf_token; ?>" 
                               class="btn btn-sm btn-warning" 
                               onclick="return confirm('Are you sure you want to reset categories ID? This will renumber ALL existing categories sequentially starting from 1. This action cannot be undone!')">
                                <i class="fas fa-redo"></i> Reset Categories ID
                            </a>
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
                        <i class="fas fa-folder me-1"></i>
                        Manage Categories
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="categoriesTable">
                                <thead>
                                    <tr>
                                        <th style="width:38px;"><i class="fas fa-grip-vertical"></i></th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Slug</th>
                                        <th>Description</th>
                                        <th>Posts</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr class="sortable-row" data-id="<?php echo $category['id']; ?>">
                                            <td class="handle"><i class="fas fa-grip-vertical"></i></td>
                                            <td><?php echo $category['id']; ?></td>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td><span class="badge bg-primary">Category</span></td>
                                            <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                            <td><?php echo htmlspecialchars($category['description']); ?></td>
                                            <td>
                                                <?php 
                                                $sql = "SELECT COUNT(*) as count FROM post_categories WHERE category_id = ?";
                                                $stmt = $conn->prepare($sql);
                                                $stmt->bind_param("i", $category['id']);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $row = $result->fetch_assoc();
                                                echo $row['count'];
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-category" 
                                                        data-id="<?php echo $category['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                        data-description="<?php echo htmlspecialchars($category['description']); ?>"
                                                        data-show_in_menu="<?php echo isset($category['show_in_menu']) ? (int)$category['show_in_menu'] : 0; ?>"
                                                        data-menu_location="<?php echo isset($category['menu_location']) ? htmlspecialchars($category['menu_location']) : 'primary_header'; ?>"
                                                        data-display_type="<?php echo isset($category['display_type']) ? htmlspecialchars($category['display_type']) : 'dropdown'; ?>"
                                                        data-menu_order="<?php echo isset($category['menu_order']) ? (int)$category['menu_order'] : 0; ?>"
                                                        data-secondary_dropdown_name="<?php echo isset($category['secondary_dropdown_name']) ? htmlspecialchars($category['secondary_dropdown_name']) : 'More'; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="categories.php?action=delete&id=<?php echo $category['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                                   class="btn btn-sm btn-danger delete-category"
                                                   onclick="return confirm('Are you sure you want to delete this category?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php
                                    // Append static pages so they are visible in the table as well
                                    $static_pages = [
                                        [
                                            'id' => 'page_contact',
                                            'name' => 'Contact Us',
                                            'slug' => 'contact',
                                            'description' => 'Static page',
                                            'url' => SITE_URL . '/contact.php',
                                            'type' => 'Page'
                                        ],
                                        [
                                            'id' => 'page_about',
                                            'name' => 'About Us',
                                            'slug' => 'about',
                                            'description' => 'Static page',
                                            'url' => SITE_URL . '/about.php',
                                            'type' => 'Page'
                                        ]
                                    ];
                                    foreach ($static_pages as $page): ?>
                                        <tr>
                                            <td></td>
                                            <td><?php echo htmlspecialchars($page['id']); ?></td>
                                            <td><?php echo htmlspecialchars($page['name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo 'Page'; ?></span></td>
                                            <td><?php echo htmlspecialchars($page['slug']); ?></td>
                                            <td><?php echo htmlspecialchars($page['description']); ?></td>
                                            <td>-</td>
                                            <td>
                                                <a href="<?php echo $page['url']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-external-link-alt"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Display Location</label>
                            <select class="form-select" name="menu_location">
                                <option value="primary_header" selected>Primary Header Navigation</option>
                                <option value="secondary_header">Secondary Dropdown</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Display Type</label>
                            <select class="form-select" name="display_type">
                                <option value="dropdown" selected>Dropdown Menu</option>
                                <option value="top_link">Top Level Link</option>
                            </select>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="show_in_menu" name="show_in_menu" checked>
                            <label class="form-check-label" for="show_in_menu">Show in Primary Header</label>
                        </div>

                        <div class="mb-3" id="secondary_dropdown_name_field" style="display: none;">
                            <label for="secondary_dropdown_name" class="form-label">Secondary Dropdown Name</label>
                            <input type="text" class="form-control" id="secondary_dropdown_name" name="secondary_dropdown_name" value="More" placeholder="Enter dropdown name">
                        </div>

                        <div class="mb-3">
                            <label for="menu_order" class="form-label">Menu Order</label>
                            <input type="number" class="form-control" id="menu_order" name="menu_order" value="0">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        
                        <div class="mb-3">
                            <label for="edit-name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit-name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit-description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit-description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Display Location</label>
                            <select class="form-select" name="menu_location" id="edit-menu_location">
                                <option value="primary_header">Primary Header Navigation</option>
                                <option value="secondary_header">Secondary Dropdown</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Display Type</label>
                            <select class="form-select" name="display_type" id="edit-display_type">
                                <option value="dropdown">Dropdown Menu</option>
                                <option value="top_link">Top Level Link</option>
                            </select>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="edit-show_in_menu" name="show_in_menu">
                            <label class="form-check-label" for="edit-show_in_menu">Show in Primary Header</label>
                        </div>

                        <div class="mb-3" id="edit-secondary_dropdown_name_field" style="display: none;">
                            <label for="edit-secondary_dropdown_name" class="form-label">Secondary Dropdown Name</label>
                            <input type="text" class="form-control" id="edit-secondary_dropdown_name" name="secondary_dropdown_name" value="More" placeholder="Enter dropdown name">
                        </div>

                        <div class="mb-3">
                            <label for="edit-menu_order" class="form-label">Menu Order</label>
                            <input type="number" class="form-control" id="edit-menu_order" name="menu_order" value="0">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Category</button>
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
    
    <!-- Admin JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            var table = $('#categoriesTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
            
            // Enable drag-and-drop ordering (requires jQuery UI)
            $('#categoriesTable tbody').sortable({
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
                    $('#categoriesTable tbody tr.sortable-row').each(function(index){
                        orderedIds.push($(this).data('id'));
                    });
                    $.post('categories.php', { action: 'reorder', csrf_token: '<?php echo $csrf_token; ?>', ids: orderedIds }, function(resp){
                        // optionally show a toast
                    });
                }
            }).disableSelection();
            
            // Show/hide secondary dropdown name field based on menu location
            $('select[name="menu_location"]').change(function() {
                if ($(this).val() === 'secondary_header') {
                    $('#secondary_dropdown_name_field').show();
                } else {
                    $('#secondary_dropdown_name_field').hide();
                }
            });
            
            $('select[name="menu_location"]').change(function() {
                if ($(this).val() === 'secondary_header') {
                    $('#edit-secondary_dropdown_name_field').show();
                } else {
                    $('#edit-secondary_dropdown_name_field').hide();
                }
            });
            
            // Edit category button click
            $('.edit-category').click(function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var description = $(this).data('description');
                var show_in_menu = $(this).data('show_in_menu') == 1;
                var menu_location = $(this).data('menu_location');
                var display_type = $(this).data('display_type');
                var menu_order = $(this).data('menu_order');
                var secondary_dropdown_name = $(this).data('secondary_dropdown_name');
                
                $('#edit-id').val(id);
                $('#edit-name').val(name);
                $('#edit-description').val(description);
                $('#edit-show_in_menu').prop('checked', show_in_menu);
                $('#edit-menu_location').val(menu_location);
                $('#edit-display_type').val(display_type);
                $('#edit-menu_order').val(menu_order);
                $('#edit-secondary_dropdown_name').val(secondary_dropdown_name);
                
                // Show/hide secondary dropdown name field
                if (menu_location === 'secondary_header') {
                    $('#edit-secondary_dropdown_name_field').show();
                } else {
                    $('#edit-secondary_dropdown_name_field').hide();
                }
                
                $('#editCategoryModal').modal('show');
            });
        });
    </script>
</body>
</html>