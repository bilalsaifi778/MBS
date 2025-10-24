<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Check if post ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid post ID";
    redirect(SITE_URL . '/admin/posts.php');
}

$post_id = intval($_GET['id']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
        redirect(SITE_URL . '/admin/edit-post.php?id=' . $post_id);
    }
    
    // Get form data
    $title = clean($_POST['title']);
    $content = $_POST['content']; // Don't clean HTML content
    $excerpt = clean($_POST['excerpt']);
    $youtube_url = clean($_POST['youtube_url']);
    $status = clean($_POST['status']);
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    // Validate required fields
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required";
        redirect(SITE_URL . '/admin/edit-post.php?id=' . $post_id);
    }
    
    // Get current post data
    $current_post = getPostById($post_id);
    if (!$current_post) {
        $_SESSION['error'] = "Post not found";
        redirect(SITE_URL . '/admin/posts.php');
    }
    
    // Handle featured image upload
    $featured_image = $current_post['featured_image'];
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $temp_name = $_FILES['featured_image']['tmp_name'];
        $file_name = time() . '_' . $_FILES['featured_image']['name'];
        $file_path = $upload_dir . $file_name;
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['featured_image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
            redirect(SITE_URL . '/admin/edit-post.php?id=' . $post_id);
        }
        
        // Move uploaded file
        if (move_uploaded_file($temp_name, $file_path)) {
            $featured_image = SITE_URL . '/uploads/' . $file_name;
        } else {
            $_SESSION['error'] = "Failed to upload image";
            redirect(SITE_URL . '/admin/edit-post.php?id=' . $post_id);
        }
    }
    
    // Update post
    $sql = "UPDATE posts SET 
            title = ?, 
            content = ?, 
            excerpt = ?, 
            featured_image = ?, 
            youtube_url = ?, 
            status = ?, 
            updated_at = NOW() 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $title, $content, $excerpt, $featured_image, $youtube_url, $status, $post_id);
    
    if ($stmt->execute()) {
        // Delete existing categories
        $sql = "DELETE FROM post_categories WHERE post_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        
        // Add new categories
        if (!empty($categories)) {
            $values = [];
            foreach ($categories as $category_id) {
                $values[] = "($post_id, " . intval($category_id) . ")";
            }
            
            $sql = "INSERT INTO post_categories (post_id, category_id) VALUES " . implode(', ', $values);
            $conn->query($sql);
        }
        
        $_SESSION['success'] = "Post updated successfully";
        redirect(SITE_URL . '/admin/posts.php');
    } else {
        $_SESSION['error'] = "Failed to update post: " . $stmt->error;
        redirect(SITE_URL . '/admin/edit-post.php?id=' . $post_id);
    }
}

// Get post data
$post = getPostById($post_id);
if (!$post) {
    $_SESSION['error'] = "Post not found";
    redirect(SITE_URL . '/admin/posts.php');
}

// Check if this is an AI Tools post and redirect to proper editor
if (isAIToolsPost($post_id)) {
    // Extract slug from post content or use post slug
    $tool_slug = $post['slug'];
    
    if (strpos($post['content'], 'data-tool-slug=') !== false) {
        if (preg_match('/data-tool-slug="([^"]+)"/i', $post['content'], $matches)) {
            $tool_slug = $matches[1];
        }
    } else if (strpos($post['content'], '<iframe') !== false && strpos($post['content'], '/uploads/tools/') !== false) {
        if (preg_match('/src="[^"]*\/uploads\/tools\/([^\/"]+)\//i', $post['content'], $matches)) {
            $tool_slug = $matches[1];
        }
    }
    
    // Check if this tool exists in ai_tools table
    $tool_sql = "SELECT id FROM ai_tools WHERE slug = ?";
    $tool_stmt = $conn->prepare($tool_sql);
    $tool_stmt->bind_param("s", $tool_slug);
    $tool_stmt->execute();
    $tool_result = $tool_stmt->get_result();
    
    if ($tool_result && $tool_result->num_rows > 0) {
        $tool_data = $tool_result->fetch_assoc();
        // Redirect to AI Tools editor with message
        $_SESSION['info'] = 'This is an AI Tool post. Use this editor to modify the HTML, CSS, and JavaScript code.';
        redirect(SITE_URL . '/admin/edit-ai-tool.php?id=' . $tool_data['id']);
    }
    
    // If no tool found, automatically create it and redirect to AI tool editor
    try {
        // Auto-sync: Create missing ai_tools entry
        $description = !empty($post['excerpt']) ? $post['excerpt'] : '';
        $empty_code = '<div class="container mt-5"><h3>Tool Code Not Yet Added</h3><p>Please edit this tool to add the HTML, CSS, and JavaScript code.</p></div>';
        
        // Use PDO to insert into ai_tools
        require_once '../config/database.php';
        $insert_stmt = $pdo->prepare("INSERT INTO ai_tools (title, slug, description, tool_logo, full_code, html_code, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->execute([
            $post['title'],
            $tool_slug,
            $description,
            $post['featured_image'] ?? '',
            $empty_code,
            $empty_code,
            $post['status'],
            $post['created_at']
        ]);
        
        $new_tool_id = $pdo->lastInsertId();
        
        // Redirect to AI Tools editor with success message
        $_SESSION['success'] = 'AI Tool entry created automatically. Please add the HTML, CSS, and JavaScript code below.';
        redirect(SITE_URL . '/admin/edit-ai-tool.php?id=' . $new_tool_id);
    } catch (Exception $e) {
        // If auto-sync fails, show warning
        $_SESSION['warning'] = 'This appears to be an AI Tool post but the tool was not found in the AI Tools database. Auto-sync failed: ' . $e->getMessage();
    }
}

// Get all postable categories (exclude type='Page' if present)
$categories = function_exists('getPostableCategories') ? getPostableCategories() : getCategories();

// Get post categories
$post_categories = getPostCategories($post_id);
$selected_categories = [];
foreach ($post_categories as $cat) {
    $selected_categories[] = $cat['id'];
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Edit Post';
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
    
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    
    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/theme/monokai.min.css">
    
    <!-- Admin CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/admin.css" rel="stylesheet">
    
    <style>
        .editor-toggle-btn {
            margin-bottom: 10px;
        }
        #html-editor {
            display: none;
        }
        .CodeMirror {
            height: 400px;
        }
        .note-editor {
            margin-bottom: 0;
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
                    <h1 class="h2">Edit Post</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="posts.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Posts
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['warning']; 
                        unset($_SESSION['warning']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <div class="mt-2">
                            <a href="ai-tools.php" class="btn btn-sm btn-primary">Go to AI Tools</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-edit me-1"></i>
                        Post Details
                    </div>
                    <div class="card-body">
                        <form method="post" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                                    </div>
                                    
                                    <?php 
                                    // Only show excerpt field for non-YouTube posts
                                    $hasYouTubeUrl = !empty($post['youtube_url']);
                                    $hasYouTubeInContent = stripos($post['content'], 'youtube.com/embed') !== false || preg_match('/<iframe[^>]+youtube\.com/i', $post['content']);
                                    $isYouTubePost = $hasYouTubeUrl || $hasYouTubeInContent;
                                    ?>
                                    <?php if (!$isYouTubePost): ?>
                                    <div class="mb-3">
                                        <label for="excerpt" class="form-label">Excerpt</label>
                                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                                        <div class="form-text">A short summary of the post (optional)</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                                        <div class="btn-group editor-toggle-btn" role="group">
                                            <button type="button" class="btn btn-primary active" id="visual-btn">Visual Editor</button>
                                            <button type="button" class="btn btn-secondary" id="html-btn">HTML Editor</button>
                                        </div>
                                        <div id="visual-editor">
                                            <textarea class="form-control" id="summernote" name="content"><?php echo $post['content']; ?></textarea>
                                        </div>
                                        <div id="html-editor">
                                            <textarea class="form-control" id="codemirror" name="content-html"><?php echo $post['content']; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card mb-3">
                                        <div class="card-header">Publish</div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="draft" <?php echo ($post['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="published" <?php echo ($post['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                                </select>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary">Update Post</button>
                                                <button type="button" class="btn btn-outline-secondary" id="save-draft">Save as Draft</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-header">Categories</div>
                                        <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($categories as $category): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category-<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $selected_categories) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                                        <?php echo $category['name']; ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-header">Featured Image</div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <input class="form-control" type="file" id="featured_image" name="featured_image" accept="image/*">
                                                <div class="form-text">Recommended size: 1200x630 pixels</div>
                                            </div>
                                            <?php if (!empty($post['featured_image'])): ?>
                                                <div id="current-image" class="mt-2">
                                                    <p>Current image:</p>
                                                    <img src="<?php echo $post['featured_image']; ?>" alt="Featured Image" class="img-fluid">
                                                </div>
                                            <?php endif; ?>
                                            <div id="image-preview" class="mt-2 d-none">
                                                <p>New image:</p>
                                                <img src="" alt="Preview" class="img-fluid">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">YouTube Video</div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="youtube_url" class="form-label">YouTube URL</label>
                                                <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?php echo htmlspecialchars($post['youtube_url']); ?>">
                                                <div class="form-text">Enter YouTube video URL for autoplay embedding</div>
                                            </div>
                                            <?php if (!empty($post['youtube_url'])): ?>
                                                <div class="mt-2">
                                                    <p>Current video:</p>
                                                    <div class="ratio ratio-16x9">
                                                        <?php echo getYouTubeEmbedCode($post['youtube_url']); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
    
    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    
    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.63.0/mode/css/css.min.js"></script>
    
    <!-- Admin JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Summernote
            $('#summernote').summernote({
                height: 400,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
            
            // Initialize CodeMirror
            var editor = CodeMirror.fromTextArea(document.getElementById('codemirror'), {
                mode: 'htmlmixed',
                theme: 'monokai',
                lineNumbers: true,
                lineWrapping: true
            });
            
            // Toggle between visual and HTML editor
            $('#visual-btn').click(function() {
                $(this).addClass('active').removeClass('btn-secondary').addClass('btn-primary');
                $('#html-btn').removeClass('active').removeClass('btn-primary').addClass('btn-secondary');
                $('#visual-editor').show();
                $('#html-editor').hide();
                
                // Sync content from HTML to Visual
                var htmlContent = editor.getValue();
                $('#summernote').summernote('code', htmlContent);
            });
            
            $('#html-btn').click(function() {
                $(this).addClass('active').removeClass('btn-secondary').addClass('btn-primary');
                $('#visual-btn').removeClass('active').removeClass('btn-primary').addClass('btn-secondary');
                $('#visual-editor').hide();
                $('#html-editor').show();
                
                // Sync content from Visual to HTML
                var visualContent = $('#summernote').summernote('code');
                editor.setValue(visualContent);
                editor.refresh();
            });
            
            // Save as draft button
            $('#save-draft').click(function() {
                $('#status').val('draft');
                $(this).closest('form').submit();
            });
            
            // Image preview
            $('#featured_image').change(function() {
                var file = this.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#image-preview').removeClass('d-none');
                        $('#image-preview img').attr('src', e.target.result);
                    }
                    reader.readAsDataURL(file);
                } else {
                    $('#image-preview').addClass('d-none');
                }
            });
            
            // Form submission - sync editors
            $('form').submit(function() {
                if ($('#html-editor').is(':visible')) {
                    var htmlContent = editor.getValue();
                    $('#summernote').summernote('code', htmlContent);
                }
            });
        });
    </script>
</body>
</html>