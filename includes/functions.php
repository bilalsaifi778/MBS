<?php
// General functions for the blog website

// Helper function to properly handle emojis in content
if (!function_exists('decodeEmojis')) {
    function decodeEmojis($content) {
        // Decode HTML entities to get the actual emoji characters
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $content;
    }
}

// Ensure category display configuration columns exist
if (!function_exists('ensureCategoryDisplayColumns')) {
function ensureCategoryDisplayColumns() {
    global $conn;
    $needed = [
        'show_in_menu' => "ALTER TABLE categories ADD COLUMN show_in_menu TINYINT(1) NOT NULL DEFAULT 0",
        'menu_location' => "ALTER TABLE categories ADD COLUMN menu_location VARCHAR(50) NOT NULL DEFAULT 'primary_header'",
        'display_type' => "ALTER TABLE categories ADD COLUMN display_type VARCHAR(50) NOT NULL DEFAULT 'dropdown'",
        'menu_order' => "ALTER TABLE categories ADD COLUMN menu_order INT NOT NULL DEFAULT 0",
        'secondary_dropdown_name' => "ALTER TABLE categories ADD COLUMN secondary_dropdown_name VARCHAR(100) NOT NULL DEFAULT 'More'"
    ];
    $existing = [];
    $res = $conn->query("SHOW COLUMNS FROM categories");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
        foreach ($needed as $col => $ddl) {
            if (!isset($existing[$col])) {
                $conn->query($ddl);
            }
        }
    }
}
}

// Run column check at include time (safe no-ops if already present)
ensureCategoryDisplayColumns();

// ----- Site settings (key/value) helpers -----
if (!function_exists('ensureSiteSettingsTable')) {
function ensureSiteSettingsTable() {
    global $conn;
    $conn->query("CREATE TABLE IF NOT EXISTS site_settings (\n        `key` VARCHAR(100) PRIMARY KEY,\n        `value` LONGTEXT NULL,\n        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}

if (!function_exists('getSetting')) {
function getSetting($key, $default = '') {
    global $conn;
    ensureSiteSettingsTable();
    $stmt = $conn->prepare("SELECT `value` FROM site_settings WHERE `key` = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return (string)$row['value'];
    }
    return $default;
}
}

if (!function_exists('setSetting')) {
function setSetting($key, $value) {
    global $conn;
    ensureSiteSettingsTable();
    $stmt = $conn->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?, ?)\n                             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}
}

if (!function_exists('getSettings')) {
function getSettings(array $keys) {
    global $conn;
    ensureSiteSettingsTable();
    if (empty($keys)) return [];
    // Build placeholders safely
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $types = str_repeat('s', count($keys));
    $stmt = $conn->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ($placeholders)");
    $stmt->bind_param($types, ...$keys);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = array_fill_keys($keys, '');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[$row['key']] = (string)$row['value'];
        }
    }
    return $out;
}
}

// Ensure table exists at include time
ensureSiteSettingsTable();

// Get categories
function getCategories() {
    global $conn;
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM post_categories pc 
             JOIN posts p ON pc.post_id = p.id 
             WHERE pc.category_id = c.id AND p.status = 'published') as post_count
            FROM categories c 
            ORDER BY c.name ASC";
    $result = $conn->query($sql);
    $categories = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    return $categories;
}

// Check if categories table has a 'type' column (cached per-request)
function categoriesHasTypeColumn() {
    static $has = null; global $conn;
    if ($has !== null) { return $has; }
    $res = $conn->query("SHOW COLUMNS FROM categories LIKE 'type'");
    $has = ($res && $res->num_rows > 0);
    return $has;
}

// Get categories intended for posts (exclude type = 'Page' when column exists)
function getPostableCategories() {
    global $conn;
    if (categoriesHasTypeColumn()) {
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM post_categories pc 
                 JOIN posts p ON pc.post_id = p.id 
                 WHERE pc.category_id = c.id AND p.status = 'published') as post_count
                FROM categories c 
                WHERE (c.type IS NULL OR LOWER(c.type) <> 'page')
                ORDER BY c.name ASC";
    } else {
        // Fallback when no 'type' column exists
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM post_categories pc 
                 JOIN posts p ON pc.post_id = p.id 
                 WHERE pc.category_id = c.id AND p.status = 'published') as post_count
                FROM categories c 
                ORDER BY c.name ASC";
    }
    $result = $conn->query($sql);
    $categories = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    return $categories;
}

// Ensure a category exists by slug; create if missing and return array{id,name,slug}
function ensureCategoryBySlug($slug, $name = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    if ($name === '') { $name = ucwords(str_replace(['-','_'],' ', $slug)); }
    $stmt2 = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
    $stmt2->bind_param('ss', $name, $slug);
    if ($stmt2->execute()) {
        $id = $conn->insert_id;
        return ['id' => $id, 'name' => $name, 'slug' => $slug];
    }
    return null;
}

// Save a tool bundle (HTML/CSS/JS) into uploads/tools/{slug}/ and return public URL to index.html
function saveToolBundle($slug, $html, $css = '', $js = '') {
    $baseDir = __DIR__ . '/../uploads/tools/' . $slug;
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }
    // If provided HTML looks like a full document, save as-is; otherwise wrap
    $isFullDoc = (bool)preg_match('/<!doctype|<html|<head|<body/i', $html);
    if ($isFullDoc) {
        file_put_contents($baseDir . '/index.html', $html);
    } else {
        $index = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<link rel=\"stylesheet\" href=\"style.css\">\n<title>" . htmlspecialchars($slug) . "</title>\n</head>\n<body>\n<div id=\"app\">\n" . $html . "\n</div>\n<script src=\"script.js\"></script>\n</body>\n</html>";
        file_put_contents($baseDir . '/index.html', $index);
    }
    file_put_contents($baseDir . '/style.css', $css);
    file_put_contents($baseDir . '/script.js', $js);
    $public = BASE_URL . '/uploads/tools/' . rawurlencode($slug) . '/index.html';
    return $public;
}

// Get categories configured to show in a specific menu location
function getCategoriesForMenu($location = 'primary_header') {
    global $conn;
    ensureCategoryDisplayColumns();
    $sql = "SELECT * FROM categories WHERE show_in_menu = 1 AND menu_location = ? ORDER BY menu_order ASC, name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $location);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    return $categories;
}

// Get posts by category
function getPostsByCategory($category_slug, $limit = 10) {
    global $conn;
    $sql = "SELECT p.*, u.username, 
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
            FROM posts p
            JOIN users u ON p.user_id = u.id
            JOIN post_categories pc ON p.id = pc.post_id
            JOIN categories c ON pc.category_id = c.id
            WHERE p.status = 'published' AND c.slug = ?
            ORDER BY p.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $category_slug, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
    return $posts;
}

// Get post by slug
function getPostBySlug($slug) {
    global $conn;
    $sql = "SELECT p.*, COALESCE(u.username, 'admin') as username 
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.slug = ? AND p.status = 'published'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Get post categories
function getPostCategories($post_id) {
    global $conn;
    $sql = "SELECT c.* FROM categories c
            JOIN post_categories pc ON c.id = pc.category_id
            WHERE pc.post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    return $categories;
}

// Get comments for post
function getComments($post_id, $status = 'approved') {
    global $conn;
    $sql = "SELECT * FROM comments 
            WHERE post_id = ? AND status = ? AND parent_id IS NULL
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $post_id, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get replies
            $row['replies'] = getReplies($row['id']);
            $comments[] = $row;
        }
    }
    return $comments;
}

// Get comment replies
function getReplies($comment_id) {
    global $conn;
    $sql = "SELECT * FROM comments 
            WHERE parent_id = ? AND status = 'approved'
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $replies = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $replies[] = $row;
        }
    }
    return $replies;
}

// Add comment
function addComment($post_id, $parent_id, $name, $email, $website, $content) {
    global $conn;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $sql = "INSERT INTO comments (post_id, parent_id, name, email, website, content, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssss", $post_id, $parent_id, $name, $email, $website, $content, $ip_address);
    return $stmt->execute();
}

// Add like
function addLike($post_id) {
    global $conn;
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
        return $stmt->execute() ? 'unliked' : false;
    } else {
        // Add like
        $sql = "INSERT INTO likes (post_id, ip_address) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $post_id, $ip_address);
        return $stmt->execute() ? 'liked' : false;
    }
}

// Get widgets by optional position (null returns all) - with caching
function getWidgets($position = null) {
    global $conn;
    
    // Use static cache to avoid multiple DB queries per request
    static $widgets_cache = [];
    static $index_created = false;
    
    // Create index on first call for better performance
    if (!$index_created) {
        $conn->query("CREATE INDEX IF NOT EXISTS idx_widgets_position ON widgets(position, is_active, sort_order)");
        $index_created = true;
    }
    
    // Create cache key
    $cache_key = $position === null ? 'all' : $position;
    
    // Return from cache if available
    if (isset($widgets_cache[$cache_key])) {
        return $widgets_cache[$cache_key];
    }
    
    // Query database with optimized query
    if ($position === null) {
        $sql = "SELECT id, title, type, content, position, is_active, sort_order FROM widgets ORDER BY sort_order ASC LIMIT 50";
        $stmt = $conn->prepare($sql);
    } else {
        $sql = "SELECT id, title, type, content, position, is_active, sort_order FROM widgets 
                WHERE position = ? AND is_active = 1 
                ORDER BY sort_order ASC LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $position);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $widgets = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $widgets[] = $row;
        }
    }
    
    // Store in cache
    $widgets_cache[$cache_key] = $widgets;
    
    return $widgets;
}

// Get custom scripts
function getCustomScripts($location, $page = null) {
    global $conn;
    $sql = "SELECT * FROM custom_scripts 
            WHERE location = ? AND is_active = 1 
            AND (page_specific IS NULL OR page_specific = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $location, $page);
    $stmt->execute();
    $result = $stmt->get_result();
    $scripts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $scripts[] = $row;
        }
    }
    return $scripts;
}

// Generate slug from title
function generateSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Check if slug exists
function slugExists($slug, $exclude_id = null) {
    global $conn;
    $sql = "SELECT id FROM posts WHERE slug = ?";
    $params = [$slug];
    $types = "s";
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result && $result->num_rows > 0;
}

// Create unique slug
function createUniqueSlug($title, $exclude_id = null) {
    $slug = generateSlug($title);
    $original_slug = $slug;
    $counter = 1;
    
    while (slugExists($slug, $exclude_id)) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// Format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Enhanced security functions

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            } else {
                $_SESSION['csrf_token'] = bin2hex(md5(uniqid((string)mt_rand(), true)));
            }
        }
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    $sessionToken = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
    $providedToken = (string)$token;
    if ($sessionToken === '' || $providedToken === '') {
        return false;
    }
    return hash_equals($sessionToken, $providedToken);
}

// Sanitize output to prevent XSS
function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Secure file upload validation
function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 2097152) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed with error code: " . ($file['error'] ?? 'unknown');
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = "File size exceeds the maximum limit of " . ($max_size / 1048576) . "MB";
    }
    
    // Check file type
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "File type not allowed. Allowed types: " . implode(', ', array_map(function($type) {
            return str_replace('image/', '', $type);
        }, $allowed_types));
    }
    
    // Verify the file is an actual image
    if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            $errors[] = "Invalid image file";
        }
    }
    
    return $errors;
}

// Get popular posts
function getPopularPosts($limit = 5) {
    global $conn;
    
    // First check if views column exists
    $columns_check = $conn->query("SHOW COLUMNS FROM posts LIKE 'views'");
    $has_views = $columns_check && $columns_check->num_rows > 0;
    
    if ($has_views) {
        $sql = "SELECT p.*, COALESCE(u.username, 'admin') as username,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = 'published'
                ORDER BY p.views DESC, like_count DESC
                LIMIT ?";
    } else {
        // Fallback: order by likes and comments if no views column
        $sql = "SELECT p.*, COALESCE(u.username, 'admin') as username,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
                (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = 'published'
                ORDER BY like_count DESC, comment_count DESC, p.created_at DESC
                LIMIT ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
    return $posts;
}

// Get latest posts
function getLatestPosts($limit = 5) {
    global $conn;
    $sql = "SELECT p.*, COALESCE(u.username, 'admin') as username,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = 'published'
            ORDER BY p.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
    return $posts;
}

// Get related posts
function getRelatedPosts($post_id, $limit = 4) {
    global $conn;
    
    // Get categories of current post
    $categories = getPostCategories($post_id);
    if (empty($categories)) {
        return [];
    }
    
    $category_ids = [];
    foreach ($categories as $category) {
        $category_ids[] = $category['id'];
    }
    
    $category_ids_str = implode(',', $category_ids);
    
    $sql = "SELECT DISTINCT p.*, 
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
            FROM posts p
            JOIN post_categories pc ON p.id = pc.post_id
            WHERE p.status = 'published' 
            AND p.id != ? 
            AND pc.category_id IN ($category_ids_str)
            ORDER BY p.created_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $post_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
    return $posts;
}

// Update post views
function updatePostViews($post_id) {
    global $conn;
    $sql = "UPDATE posts SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    return $stmt->execute();
}

// Get statistics
function getStatistics() {
    global $conn;
    
    // Total posts
    $sql = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
    $result = $conn->query($sql);
    $stats['total_posts'] = $result->fetch_assoc()['total'];
    
    // Total comments
    $sql = "SELECT COUNT(*) as total FROM comments WHERE status = 'approved'";
    $result = $conn->query($sql);
    $stats['total_comments'] = $result->fetch_assoc()['total'];
    
    // Total likes
    $sql = "SELECT COUNT(*) as total FROM likes";
    $result = $conn->query($sql);
    $stats['total_likes'] = $result->fetch_assoc()['total'];
    
    // Total views
    $sql = "SELECT SUM(views) as total FROM posts";
    $result = $conn->query($sql);
    $stats['total_views'] = $result->fetch_assoc()['total'];
    
    return $stats;
}

// Sanitize YouTube URL and get embed code
function getYoutubeEmbed($url) {
    $youtube_id = '';
    
    // Extract YouTube video ID
    if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    } else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    } else if (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    }
    
    if (!empty($youtube_id)) {
        return '<div class="video-container"><iframe width="100%" height="400" src="https://www.youtube.com/embed/' . $youtube_id . '?autoplay=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    }
    
    return '';
}

// Get category by slug
function getCategoryBySlug($slug) {
    global $conn;
    $sql = "SELECT * FROM categories WHERE slug = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get post count by category
function getPostCountByCategory($category_id) {
    global $conn;
    $sql = "SELECT COUNT(*) as count 
            FROM post_categories pc 
            JOIN posts p ON pc.post_id = p.id 
            WHERE pc.category_id = ? AND p.status = 'published'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['count'];
    }
    
    return 0;
}

// Get post by ID
function getPostById($post_id) {
    global $conn;
    $sql = "SELECT * FROM posts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get YouTube embed code from URL
function getYouTubeEmbedCode($url) {
    $video_id = '';
    
    // Extract video ID from various YouTube URL formats
    if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $matches)) {
        $video_id = $matches[1];
    } else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $matches)) {
        $video_id = $matches[1];
    } else if (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $matches)) {
        $video_id = $matches[1];
    } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $matches)) {
        $video_id = $matches[1];
    }
    
    if ($video_id) {
        return '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }
    
    return '';
}

// Increment post views
function incrementPostViews($post_id) {
    global $conn;
    $sql = "UPDATE posts SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    return $stmt->execute();
}

// Get like count for a post
function getLikeCount($post_id) {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM likes WHERE post_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

// Track page view for analytics
function trackPageView($page, $post_id = null) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    $sql = "INSERT INTO statistics (page_url, ip_address, user_agent, referrer) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $page, $ip, $user_agent, $referrer);
    return $stmt->execute();
}

// Get YouTube embed URL
function getYoutubeEmbedUrl($url, $autoplay = false) {
    $youtube_id = '';
    
    // Extract YouTube video ID
    if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    } else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    } else if (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id)) {
        $youtube_id = $id[1];
    }
    
    if (!empty($youtube_id)) {
        $autoplay_param = $autoplay ? '1' : '0';
        return "https://www.youtube.com/embed/{$youtube_id}?autoplay={$autoplay_param}";
    }
    
    return '';
}

// Render widget with alignment
function renderWidget($widget) {
    // Alignment feature removed; render with default layout
    $html = '<div class="widget" id="widget-' . $widget['id'] . '">';
    $html .= '<div class="widget-content">';
    
    switch($widget['type']) {
        case 'popular_posts':
            $html .= renderPopularPostsWidget($widget);
            break;
        case 'latest_posts':
            $html .= renderLatestPostsWidget($widget);
            break;
        case 'categories':
            $html .= renderCategoriesWidget($widget);
            break;
        case 'search':
            $html .= renderSearchWidget($widget);
            break;
        case 'html':
        case 'advertisement':
            $html .= renderHTMLWidget($widget);
            break;
        default:
            $html .= renderHTMLWidget($widget);
            break;
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// Render popular posts widget
function renderPopularPostsWidget($widget) {
    $limit = !empty($widget['content']) && is_numeric($widget['content']) ? intval($widget['content']) : 3;
    $posts = getPopularPosts($limit);
    
    $html = '<div class="widget-header">';
    $html .= '<h5 class="widget-title">' . htmlspecialchars($widget['title']) . '</h5>';
    $html .= '</div>';
    $html .= '<div class="widget-body">';
    
    if (!empty($posts)) {
        $html .= '<ul class="list-unstyled">';
        foreach ($posts as $post) {
            $html .= '<li class="mb-2">';
            $html .= '<a href="' . SITE_URL . '/post.php?slug=' . $post['slug'] . '" class="text-decoration-none">';
            $html .= htmlspecialchars($post['title']);
            $html .= '</a>';
            $html .= '<small class="text-muted d-block">Views: ' . ($post['views'] ?? 0) . '</small>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<p class="text-muted">No popular posts found.</p>';
    }
    
    $html .= '</div>';
    return $html;
}

// Render latest posts widget
function renderLatestPostsWidget($widget) {
    $limit = !empty($widget['content']) && is_numeric($widget['content']) ? intval($widget['content']) : 3;
    $posts = getLatestPosts($limit);
    
    $html = '<div class="widget-header">';
    $html .= '<h5 class="widget-title">' . htmlspecialchars($widget['title']) . '</h5>';
    $html .= '</div>';
    $html .= '<div class="widget-body">';
    
    if (!empty($posts)) {
        $html .= '<ul class="list-unstyled">';
        foreach ($posts as $post) {
            $html .= '<li class="mb-2">';
            $html .= '<a href="' . SITE_URL . '/post.php?slug=' . $post['slug'] . '" class="text-decoration-none">';
            $html .= htmlspecialchars($post['title']);
            $html .= '</a>';
            $html .= '<small class="text-muted d-block">' . formatDate($post['created_at']) . '</small>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<p class="text-muted">No recent posts found.</p>';
    }
    
    $html .= '</div>';
    return $html;
}

// Render categories widget
function renderCategoriesWidget($widget) {
    $categories = getCategories();
    
    $html = '<div class="widget-header">';
    $html .= '<h5 class="widget-title">' . htmlspecialchars($widget['title']) . '</h5>';
    $html .= '</div>';
    $html .= '<div class="widget-body">';
    
    if (!empty($categories)) {
        $html .= '<ul class="list-unstyled">';
        foreach ($categories as $category) {
            $html .= '<li class="mb-1">';
            $html .= '<a href="' . SITE_URL . '/category.php?slug=' . $category['slug'] . '" class="text-decoration-none">';
            $html .= htmlspecialchars($category['name']);
            $html .= '</a>';
            $html .= ' <span class="badge bg-secondary">' . $category['post_count'] . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    } else {
        $html .= '<p class="text-muted">No categories found.</p>';
    }
    
    $html .= '</div>';
    return $html;
}

// Render search widget
function renderSearchWidget($widget) {
    $html = '<div class="widget-header">';
    $html .= '<h5 class="widget-title">' . htmlspecialchars($widget['title']) . '</h5>';
    $html .= '</div>';
    $html .= '<div class="widget-body">';
    $html .= '<form method="GET" action="' . SITE_URL . '/search.php">';
    $html .= '<div class="input-group">';
    $html .= '<input type="text" class="form-control" name="q" placeholder="Search posts..." required>';
    $html .= '<button class="btn btn-outline-secondary" type="submit">';
    $html .= '<i class="fas fa-search"></i>';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</form>';
    $html .= '</div>';
    return $html;
}

// Render HTML/Advertisement widget
function renderHTMLWidget($widget) {
    $html = '';
    if (!empty($widget['title'])) {
        $html .= '<div class="widget-header">';
        $html .= '<h5 class="widget-title">' . htmlspecialchars($widget['title']) . '</h5>';
        $html .= '</div>';
    }
    $html .= '<div class="widget-body">';
    $html .= $widget['content']; // Don't escape HTML content
    $html .= '</div>';
    return $html;
}

// Display widgets for a specific position
function displayWidgets($position) {
    $widgets = getWidgets($position);
    $html = '';
    
    foreach ($widgets as $widget) {
        $html .= '<div class="mb-4">';
        $html .= renderWidget($widget);
        $html .= '</div>';
    }
    
    return $html;
}
// --- Utility: HTTP GET with fallback ---
if (!function_exists('httpGet')) {
function httpGet($url, $timeout = 10) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: Mozilla/5.0 (compatible; AgentMode/1.0)\r\n"
        ]
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data !== false) return $data;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AgentMode/1.0)',
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $out = curl_exec($ch);
        curl_close($ch);
        return $out !== false ? $out : '';
    }
    return '';
}
}

// --- YouTube helpers ---
if (!function_exists('extractYouTubeId')) {
    function extractYouTubeId($url) {
        $videoId = '';
        
        // Match YouTube URL patterns - improved regex
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
            $videoId = $matches[1];
        }
        
        return $videoId;
    }
}

if (!function_exists('getYouTubeThumbnail')) {
    function getYouTubeThumbnail($videoId) {
        if (empty($videoId)) {
            return '';
        }
        
        // Always use the standard quality thumbnail which is most reliable
        return "https://img.youtube.com/vi/{$videoId}/0.jpg";
    }
}

if (!function_exists('fetchYouTubeDetails')) {
function fetchYouTubeDetails($url) {
    $video_id = extractYouTubeId($url);
    if ($video_id === '') return null;

    $title = '';
    $description = '';
    $thumb = '';

    // Try oEmbed for title and thumbnail (no API key required)
    $oembed = httpGet('https://www.youtube.com/oembed?format=json&url=' . urlencode('https://www.youtube.com/watch?v=' . $video_id));
    if ($oembed) {
        $data = json_decode($oembed, true);
        if (is_array($data)) {
            $title = $data['title'] ?? '';
            $thumb = $data['thumbnail_url'] ?? '';
        }
    }

    // Try scraping meta og:description for description if API key not set
    $html = httpGet('https://www.youtube.com/watch?v=' . $video_id);
    if ($html) {
        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $m)) {
            $description = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    // Always ensure we have a thumbnail - use most reliable format
    if (empty($thumb)) {
        $thumb = "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg";
    }

    return [
        'id' => $video_id,
        'title' => $title,
        'description' => $description,
        'thumbnail' => $thumb
    ];
}
}

if (!function_exists('youtubeEmbedHtml')) {
function youtubeEmbedHtml($video_id, $autoplay = 0) {
    $ap = $autoplay ? '1' : '0';
    $src = 'https://www.youtube.com/embed/' . $video_id . '?rel=0&autoplay=' . $ap;
    return '<div class="ratio ratio-16x9"><iframe src="' . $src . '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
}
}

// --- Category posts by ID with pagination ---
if (!function_exists('getPostsByCategoryId')) {
function getPostsByCategoryId($category_id, $limit = 10, $offset = 0) {
    global $conn;
    // Ensure numeric values to avoid SQL injection and placeholder issues in LIMIT/OFFSET
    $limit = max(1, (int)$limit);
    $offset = max(0, (int)$offset);

    $sql = "SELECT p.*, p.youtube_url, COALESCE(u.username, 'admin') as username,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            JOIN post_categories pc ON p.id = pc.post_id
            WHERE p.status = 'published' AND pc.category_id = ?
            ORDER BY p.created_at DESC
            LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
    return $posts;
}

/**
 * PHP-based form validation
 * Validates form data server-side
 */
function validateFormData($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = isset($data[$field]) ? trim($data[$field]) : '';
        
        // Required validation
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = $rule['message'] ?? ucfirst($field) . ' is required';
            continue;
        }
        
        // Skip other validations if field is empty and not required
        if (empty($value) && !isset($rule['required'])) {
            continue;
        }
        
        // Email validation
        if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = 'Please enter a valid email address';
        }
        
        // Min length validation
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters long';
        }
        
        // Max length validation
        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_length'] . ' characters';
        }
        
        // Pattern validation
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
            $errors[$field] = $rule['message'] ?? ucfirst($field) . ' format is invalid';
        }
    }
    
    return $errors;
}

/**
 * Display form validation errors
 */
function displayFormErrors($errors) {
    if (empty($errors)) {
        return '';
    }
    
    $html = '<div class="alert alert-danger">';
    $html .= '<h6>Please correct the following errors:</h6>';
    $html .= '<ul class="mb-0">';
    
    foreach ($errors as $error) {
        $html .= '<li>' . htmlspecialchars($error) . '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Sanitize form input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'info') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    return null;
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    
    if ($flash) {
        $class = 'alert-' . $flash['type'];
        return '<div class="alert ' . $class . '">' . htmlspecialchars($flash['message']) . '</div>';
    }
    
    return '';
}
}

// Enhanced function to get video thumbnail with fallbacks
// Check if post is an AI Tools post and render with iframe isolation
if (!function_exists('renderAIToolPost')) {
    function renderAIToolPost($post) {
        global $conn;
        
        $slug = '';
        
        // Method 1: Check if this is a new AI tool post with data-tool-slug
        if (strpos($post['content'], 'data-tool-slug=') !== false) {
            if (preg_match('/data-tool-slug="([^"]+)"/i', $post['content'], $matches)) {
                $slug = $matches[1];
            }
        }
        // Method 2: Check if this post contains an iframe pointing to AI tools (legacy)
        else if (strpos($post['content'], '<iframe') !== false && strpos($post['content'], '/uploads/tools/') !== false) {
            if (preg_match('/src="[^"]*\/uploads\/tools\/([^\/"]+)\//i', $post['content'], $matches)) {
                $slug = $matches[1];
            }
        }
        // Method 3: Use post slug if it matches an AI tool
        else {
            $slug = $post['slug'];
        }
        
        if (!empty($slug)) {
            // USE IFRAME FOR COMPLETE ISOLATION - prevents CSS/JS from affecting main site
            $tool_url = BASE_URL . '/tool/' . htmlspecialchars($slug);
            
            $html = '<div class="ai-tool-container" style="width: 100%; margin: 30px 0;">';
            $html .= '<iframe ';
            $html .= 'src="' . $tool_url . '" ';
            $html .= 'style="width: 100%; min-height: 600px; border: 1px solid #e0e0e0; border-radius: 8px; background: #fff;" ';
            $html .= 'frameborder="0" ';
            $html .= 'loading="lazy" ';
            $html .= 'sandbox="allow-scripts allow-same-origin allow-forms allow-popups" ';
            $html .= 'title="AI Tool: ' . htmlspecialchars($slug) . '"';
            $html .= '></iframe>';
            
            // Add auto-resize script
            $html .= '<script>';
            $html .= '(function() {';
            $html .= '  var iframe = document.querySelector(".ai-tool-container iframe");';
            $html .= '  if (iframe) {';
            $html .= '    iframe.addEventListener("load", function() {';
            $html .= '      try {';
            $html .= '        var height = iframe.contentWindow.document.body.scrollHeight;';
            $html .= '        if (height > 0) iframe.style.height = height + "px";';
            $html .= '      } catch(e) { console.log("Cannot access iframe height"); }';
            $html .= '    });';
            $html .= '  }';
            $html .= '})();';
            $html .= '</script>';
            $html .= '</div>';
            
            return $html;
        }
        
        // Return original content if not an AI tool or tool not found in database
        return $post['content'];
    }
}

// Check if a post is an AI Tools post based on its categories
if (!function_exists('isAIToolsPost')) {
    function isAIToolsPost($post_id) {
        $categories = getPostCategories($post_id);
        foreach ($categories as $category) {
            if (strtolower($category['slug']) === 'ai-tools') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('getPostThumbnail')) {
    function getPostThumbnail($post) {
        
        // 1. Check if it's a YouTube post and extract video ID
        $videoId = '';
        
        // First check youtube_url field
        if (!empty($post['youtube_url'])) {
            // Extract video ID from various YouTube URL formats
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $post['youtube_url'], $matches)) {
                $videoId = $matches[1];
            }
        }
        
        // If no video ID yet, check content
        if (empty($videoId) && !empty($post['content'])) {
            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $post['content'], $matches)) {
                $videoId = $matches[1];
            }
        }
        
        // If we have a YouTube video ID, return the thumbnail URL
        if (!empty($videoId)) {
            // Use hqdefault as it's the most reliable for existing videos
            return "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";
        }
        
        // 2. Use featured image if no YouTube thumbnail found
        if (!empty($post['featured_image'])) {
            // Check if it's already a full URL (starts with http or https)
            if (preg_match('/^https?:\/\//', $post['featured_image'])) {
                return $post['featured_image'];
            } else {
                // It's a relative path, add BASE_URL
                return BASE_URL . '/uploads/' . $post['featured_image'];
            }
        }
        
        // 3. Default to placeholder if no image found
        return BASE_URL . '/assets/images/default-thumbnail.svg';
    }
}

?>
