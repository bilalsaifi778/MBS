<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
checkLogin();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission";
        redirect(SITE_URL . '/admin/add-post.php');
    }
    
    // Get form data
    $title = clean($_POST['title']);
    $editor_type = clean($_POST['editor_type']); // Store which editor was used
    
    // Get content based on editor type
    if ($editor_type === 'html' && isset($_POST['content-html'])) {
        $content = $_POST['content-html']; // Content from HTML editor
    } else {
        $content = $_POST['content']; // Content from Visual editor
    }
    
    $excerpt = '';
    $youtube_url = clean($_POST['youtube_url']);
    $youtube_autoplay = isset($_POST['youtube_autoplay']) ? 1 : 0;
    $status = clean($_POST['status']);
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    // Validate required fields
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required";
        redirect(SITE_URL . '/admin/add-post.php');
    }
    
    // Create slug
    $slug = createUniqueSlug($title);
    
    // Handle featured image upload
    $featured_image = '';
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
            redirect(SITE_URL . '/admin/add-post.php');
        }
        
        // Move uploaded file
        if (move_uploaded_file($temp_name, $file_path)) {
            $featured_image = $file_name; // Store only filename, not full URL
        } else {
            $_SESSION['error'] = "Failed to upload image";
            redirect(SITE_URL . '/admin/add-post.php');
        }
    }
    
    // Insert post
    $sql = "INSERT INTO posts (title, slug, content, excerpt, featured_image, youtube_url, status, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $user_id = $_SESSION['admin_id'];
    $stmt->bind_param("sssssssi", $title, $slug, $content, $excerpt, $featured_image, $youtube_url, $status, $user_id);
    
    if ($stmt->execute()) {
        $post_id = $stmt->insert_id;
        
        // Add categories
        if (!empty($categories)) {
            $values = [];
            foreach ($categories as $category_id) {
                $values[] = "($post_id, " . intval($category_id) . ")";
            }
            
            $sql = "INSERT INTO post_categories (post_id, category_id) VALUES " . implode(', ', $values);
            $conn->query($sql);
        }
        
        $_SESSION['success'] = "Post created successfully";
        redirect(SITE_URL . '/admin/posts.php');
    } else {
        $_SESSION['error'] = "Failed to create post: " . $stmt->error;
        redirect(SITE_URL . '/admin/add-post.php');
    }
}

// Get all postable categories (exclude type='Page' if present)
$categories = function_exists('getPostableCategories') ? getPostableCategories() : getCategories();

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Add New Post';
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
                    <h1 class="h2">Add New Post</h1>
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
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                                        <div class="btn-group editor-toggle-btn" role="group">
                                            <button type="button" class="btn btn-primary active" id="visual-btn">Visual Composer</button>
                                            <button type="button" class="btn btn-secondary" id="html-btn">HTML Editor</button>
                                        </div>
                                        <input type="hidden" name="editor_type" id="editor_type" value="visual">
                                        <div id="visual-editor">
                                            <textarea class="form-control" id="summernote" name="content"></textarea>
                                        </div>
                                        <div id="html-editor">
                                            <textarea class="form-control" id="codemirror" name="content-html"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="youtube_url" class="form-label">YouTube Video URL</label>
                                        <input type="text" class="form-control" id="youtube_url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=...">
                                        <div class="form-text">Enter YouTube URL to embed video with autoplay</div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="youtube_autoplay" name="youtube_autoplay" value="1" checked>
                                            <label class="form-check-label" for="youtube_autoplay">
                                                Enable autoplay
                                            </label>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-info mt-2" id="preview-youtube">Preview Video</button>
                                        <div id="youtube-preview" class="mt-2"></div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card mb-3">
                                        <div class="card-header">Publish</div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="draft">Draft</option>
                                                    <option value="published">Published</option>
                                                </select>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary">Save Post</button>
                                                <button type="button" class="btn btn-outline-secondary" id="save-draft">Save as Draft</button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-header">Categories</div>
                                        <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($categories as $category): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category-<?php echo $category['id']; ?>">
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
                                            <div id="image-preview" class="mt-2 d-none">
                                                <img src="" alt="Preview" class="img-fluid">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card">
                                        <div class="card-header">YouTube Video</div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="youtube_url" class="form-label">YouTube URL</label>
                                                <input type="url" class="form-control" id="youtube_url" name="youtube_url">
                                                <div class="form-text">Enter YouTube video URL for autoplay embedding</div>
                                            </div>
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
                $('#editor_type').val('visual');
                
                // Sync content from HTML to Visual
                var htmlContent = editor.getValue();
                $('#summernote').summernote('code', htmlContent);
            });
            
            $('#html-btn').click(function() {
                $(this).addClass('active').removeClass('btn-secondary').addClass('btn-primary');
                $('#visual-btn').removeClass('active').removeClass('btn-primary').addClass('btn-secondary');
                $('#visual-editor').hide();
                $('#html-editor').show();
                $('#editor_type').val('html');
                
                // Sync content from Visual to HTML
                var visualContent = $('#summernote').summernote('code');
                editor.setValue(visualContent);
                editor.refresh();
            });
            
            // YouTube preview functionality
            $('#preview-youtube').click(function() {
                var youtubeUrl = $('#youtube_url').val();
                if (youtubeUrl) {
                    // Extract video ID from URL
                    var videoId = '';
                    if (youtubeUrl.indexOf('youtube.com/watch?v=') > -1) {
                        videoId = youtubeUrl.split('v=')[1];
                        var ampersandPosition = videoId.indexOf('&');
                        if (ampersandPosition != -1) {
                            videoId = videoId.substring(0, ampersandPosition);
                        }
                    } else if (youtubeUrl.indexOf('youtu.be/') > -1) {
                        videoId = youtubeUrl.split('youtu.be/')[1];
                    }
                    
                    if (videoId) {
                        var autoplay = $('#youtube_autoplay').is(':checked') ? 1 : 0;
                        var embedCode = '<div class="ratio ratio-16x9"><iframe src="https://www.youtube.com/embed/' + 
                            videoId + '?autoplay=' + autoplay + '&rel=0" allowfullscreen></iframe></div>';
                        $('#youtube-preview').html(embedCode);
                    } else {
                        $('#youtube-preview').html('<div class="alert alert-danger">Invalid YouTube URL</div>');
                    }
                } else {
                    $('#youtube-preview').html('<div class="alert alert-warning">Please enter a YouTube URL</div>');
                }
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