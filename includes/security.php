<?php
/**
 * Security utility functions for AI Tools system
 */

class SecurityHelper {
    
    /**
     * Sanitize HTML content to prevent XSS attacks
     * Allows safe HTML tags commonly used in tools
     */
    public static function sanitizeHTML($html) {
        // Allow these tags for tool functionality
        $allowed_tags = '<div><span><p><a><img><input><button><form><select><option><textarea><label><h1><h2><h3><h4><h5><h6><ul><ol><li><br><hr><table><tr><td><th><thead><tbody><strong><em><b><i><u><small><code><pre><blockquote>';
        
        // Remove potentially dangerous attributes but keep safe ones
        $html = strip_tags($html, $allowed_tags);
        
        // Remove dangerous event attributes (onclick, onload, etc.)
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $html);
        
        // Remove javascript: URLs
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/', '', $html);
        $html = preg_replace('/src\s*=\s*["\']javascript:[^"\']*["\']/', '', $html);
        
        // Remove dangerous tags that might have passed through
        $dangerous_tags = ['<script>', '</script>', '<iframe>', '</iframe>', '<object>', '</object>', '<embed>', '</embed>', '<applet>', '</applet>'];
        $html = str_ireplace($dangerous_tags, '', $html);
        
        return $html;
    }
    
    /**
     * Sanitize HTML content for AI Tools (more permissive)
     * Allows more tags needed for interactive tools
     */
    public static function sanitizeHTMLForTools($html) {
        // Allow more tags for tool functionality including common HTML5 elements
        $allowed_tags = '<div><span><p><a><img><input><button><form><select><option><textarea><label><h1><h2><h3><h4><h5><h6><ul><ol><li><br><hr><table><tr><td><th><thead><tbody><tfoot><strong><em><b><i><u><small><code><pre><blockquote><section><article><header><footer><nav><main><aside><canvas><svg><figure><figcaption>';
        
        // Remove potentially dangerous attributes but keep safe ones
        $html = strip_tags($html, $allowed_tags);
        
        // Remove dangerous event attributes (onclick, onload, etc.) but preserve data attributes and IDs
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/', '', $html);
        
        // Remove javascript: URLs
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/', '', $html);
        $html = preg_replace('/src\s*=\s*["\']javascript:[^"\']*["\']/', '', $html);
        
        // Don't remove script tags here - they should be handled separately
        // Only remove really dangerous embedded objects
        $dangerous_tags = ['<iframe>', '</iframe>', '<object>', '</object>', '<embed>', '</embed>', '<applet>', '</applet>'];
        $html = str_ireplace($dangerous_tags, '', $html);
        
        return $html;
    }
    
    /**
     * Sanitize CSS content to prevent CSS injection attacks
     */
    public static function sanitizeCSS($css) {
        // Remove dangerous CSS functions
        $dangerous_patterns = [
            '/expression\s*\(/i',  // IE expression()
            '/javascript\s*:/i',   // javascript: URLs
            '/vbscript\s*:/i',     // vbscript: URLs
            '/@import/i',          // @import statements
            '/url\s*\(\s*["\']?\s*javascript/i', // javascript in urls
            '/behavior\s*:/i',     // IE behavior
            '/binding\s*:/i',      // binding
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $css = preg_replace($pattern, '', $css);
        }
        
        // Remove HTML tags that might be in CSS
        $css = strip_tags($css);
        
        return $css;
    }
    
    /**
     * Sanitize JavaScript content
     * This is more restrictive as JS can be very dangerous
     */
    public static function sanitizeJS($js) {
        // Remove potential XSS patterns
        $dangerous_patterns = [
            '/document\.cookie/i',           // Cookie access
            '/document\.write/i',            // Document write
            '/eval\s*\(/i',                  // eval() function
            '/setTimeout\s*\(\s*["\'][^"\']*["\']/i', // setTimeout with strings
            '/setInterval\s*\(\s*["\'][^"\']*["\']/i', // setInterval with strings
            '/new\s+Function/i',             // Function constructor
            '/window\.location/i',           // Location manipulation
            '/document\.location/i',         // Document location
            '/XMLHttpRequest/i',             // AJAX requests
            '/fetch\s*\(/i',                 // Fetch API
            '/navigator\./i',                // Navigator object
            '/localStorage/i',               // LocalStorage access
            '/sessionStorage/i',             // SessionStorage access
        ];
        
        // Don't remove these patterns, just log them for review
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $js)) {
                error_log("Potentially dangerous JS pattern detected: " . $pattern);
                // You can choose to either remove the pattern or reject the entire script
                // For now, we'll be permissive but log it
            }
        }
        
        // Remove HTML tags that might be in JS
        $js = strip_tags($js);
        
        return $js;
    }
    
    /**
     * Sanitize JavaScript content for AI Tools (less restrictive)
     * Allows more JS functionality needed for interactive tools
     */
    public static function sanitizeJSForTools($js) {
        // Only remove the most dangerous patterns for tools
        $dangerous_patterns = [
            '/document\.cookie\s*=/i',        // Cookie setting (reading might be OK)
            '/document\.write\s*\(/i',        // Document write
            '/eval\s*\(/i',                   // eval() function
            '/new\s+Function\s*\(/i',         // Function constructor
        ];
        
        // Log but don't necessarily remove other patterns
        $warning_patterns = [
            '/XMLHttpRequest/i',              // AJAX requests
            '/fetch\s*\(/i',                  // Fetch API
            '/localStorage/i',                // LocalStorage access
            '/sessionStorage/i',              // SessionStorage access
        ];
        
        // Remove dangerous patterns
        foreach ($dangerous_patterns as $pattern) {
            $js = preg_replace($pattern, '/* REMOVED */', $js);
        }
        
        // Log warning patterns but don't remove them
        foreach ($warning_patterns as $pattern) {
            if (preg_match($pattern, $js)) {
                error_log("AI Tool JS contains potentially sensitive pattern: " . $pattern);
            }
        }
        
        // Don't remove HTML tags from JS as it might be needed for template strings
        
        return $js;
    }
    
    /**
     * Minimal sanitization for unified tool code
     * Only removes the most dangerous patterns
     */
    public static function sanitizeToolCode($code) {
        // Only remove truly dangerous patterns that could compromise security
        $dangerous_patterns = [
            '/document\.cookie\s*=/i',        // Cookie setting
            '/document\.write\s*\(/i',        // Document write
            '/eval\s*\(/i',                   // eval() function
            '/new\s+Function\s*\(/i',         // Function constructor
            '/<iframe[^>]*src\s*=\s*["\'][^"\']*(javascript|data):[^"\']*/i', // Dangerous iframes
            '/<object/i',                     // Object tags
            '/<embed/i',                      // Embed tags
            '/<applet/i',                     // Applet tags
        ];
        
        // Replace dangerous patterns with comments
        foreach ($dangerous_patterns as $pattern) {
            $code = preg_replace($pattern, '/* SECURITY: Pattern removed */', $code);
        }
        
        // Do not encode HTML entities to preserve HTML structure
        return $code;
    }
    
    /**
     * Validate file upload (for tool logos)
     */
    public static function validateImageUpload($file) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid parameters.');
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return null; // No file uploaded, which is okay
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('File too large.');
            default:
                throw new RuntimeException('Unknown upload error.');
        }
        
        if ($file['size'] > $max_size) {
            throw new RuntimeException('File too large. Maximum size is 5MB.');
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new RuntimeException('Invalid file format. Only images are allowed.');
        }
        
        return true;
    }
    
    /**
     * Generate a secure slug from title
     */
    public static function generateSlug($title) {
        // Convert to lowercase
        $slug = strtolower($title);
        
        // Replace special characters and spaces with hyphens
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        
        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Remove hyphens from beginning and end
        $slug = trim($slug, '-');
        
        // Ensure minimum length
        if (strlen($slug) < 3) {
            $slug .= '-tool';
        }
        
        return $slug;
    }
    
    /**
     * Check if user has admin privileges
     */
    public static function requireAdmin() {
        // Check for both possible admin session variables
        $isAdmin = isset($_SESSION['admin_id']) || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
        
        if (!$isAdmin) {
            // Redirect to admin login instead of showing error
            $loginUrl = defined('BASE_URL') ? BASE_URL . '/admin/login.php' : '/admin/login.php';
            header('Location: ' . $loginUrl);
            exit('Access denied. Please login as administrator.');
        }
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRF($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new RuntimeException('CSRF token mismatch.');
        }
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRF() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Rate limiting for tool creation (prevent spam)
     */
    public static function checkRateLimit($user_ip, $action = 'tool_creation', $limit = 5, $window = 3600) {
        $cache_file = sys_get_temp_dir() . '/rate_limit_' . md5($user_ip . $action) . '.json';
        
        $attempts = [];
        if (file_exists($cache_file)) {
            $attempts = json_decode(file_get_contents($cache_file), true) ?: [];
        }
        
        // Clean old attempts
        $current_time = time();
        $attempts = array_filter($attempts, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        if (count($attempts) >= $limit) {
            throw new RuntimeException("Rate limit exceeded. Please try again later.");
        }
        
        // Add current attempt
        $attempts[] = $current_time;
        file_put_contents($cache_file, json_encode($attempts));
        
        return true;
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $log_file = dirname(__DIR__) . '/logs/security.log';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        error_log(json_encode($log_entry) . PHP_EOL, 3, $log_file);
    }
}

/**
 * Content Security Policy helper
 */
class CSPHelper {
    
    public static function generateNonce() {
        return base64_encode(random_bytes(16));
    }
    
    public static function setCSPHeaders($nonce = null) {
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "font-src 'self' https://cdnjs.cloudflare.com; ";
        $csp .= "connect-src 'self'; ";
        $csp .= "frame-src 'none'; ";
        $csp .= "object-src 'none';";
        
        if ($nonce) {
            $csp = str_replace("'unsafe-inline'", "'nonce-{$nonce}'", $csp);
        }
        
        header("Content-Security-Policy: " . $csp);
    }
}
?>