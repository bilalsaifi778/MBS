-- Database schema for blog website

-- Users table for admin authentication
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories table
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Posts table
CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `excerpt` text,
  `featured_image` varchar(255) DEFAULT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `views` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Post categories relationship table
CREATE TABLE IF NOT EXISTS `post_categories` (
  `post_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`category_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `post_categories_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments table
CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','spam') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Likes table
CREATE TABLE IF NOT EXISTS `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_id_ip_address` (`post_id`,`ip_address`),
  CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Widgets table
CREATE TABLE IF NOT EXISTS `widgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `position` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Statistics table
CREATE TABLE IF NOT EXISTS `statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_url` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `referrer` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `page_url` (`page_url`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Custom scripts table
CREATE TABLE IF NOT EXISTS `custom_scripts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `location` enum('header','footer','sidebar','custom') NOT NULL,
  `page_specific` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Tools table
CREATE TABLE IF NOT EXISTS `ai_tools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `tool_logo` varchar(255) DEFAULT NULL,
  `html_code` longtext,
  `css_code` longtext,
  `js_code` longtext,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `category_id` int(11) DEFAULT '1',
  `views_count` int(11) NOT NULL DEFAULT '0',
  `likes_count` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `status` (`status`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `ai_tools_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123) - only if doesn't exist
INSERT IGNORE INTO `users` (`username`, `email`, `password`) VALUES
('admin', 'admin@example.com', '$2y$10$8zUlxQxkbIk7T3xP1o5YAOVNBHKsfRHK94iGO9s1E/THVgJvdHK4.');

-- Insert default categories - only if they don't exist
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`) VALUES
('Home Page', 'home', 'Posts for the home page'),
('Computer Tutorials', 'computer-tutorials', 'Computer tutorial posts'),
('News & Updates', 'news-updates', 'News and updates posts'),
('Downloads', 'downloads', 'Download resources'),
('Reel & Shows', 'reel-shows', 'Reel and shows content');

-- Insert AI Tools categories - only if they don't exist
INSERT IGNORE INTO `categories` (`name`, `slug`, `description`) VALUES
('AI Tools', 'ai-tools', 'Collection of AI-powered tools and utilities'),
('Generators', 'generators', 'Content and link generators'),
('Utilities', 'utilities', 'Useful utility tools');

-- Insert default widgets
INSERT INTO `widgets` (`title`, `content`, `position`, `sort_order`) VALUES
('Popular Posts', '<p>Popular posts widget content</p>', 'sidebar', 1),
('Latest Posts', '<p>Latest posts widget content</p>', 'sidebar', 2),
('Advertisement', '<p>Ad space</p>', 'sidebar', 3);

-- Insert default homepage middle ad widget
INSERT IGNORE INTO `widgets` (`title`, `content`, `position`, `sort_order`) VALUES
('Homepage Middle Ad', '<div class="text-center"><p style="background-color: #fff3cd; color: #856404; padding: 20px; border: 1px dashed #ffc107; border-radius: 5px;">Advertisement Space<br>728x90 or 336x280 Ad Unit</p></div>', 'home_middle', 1);

-- Sample AI Tool (YouTube Auto-Subscribe Link Generator)
-- First, get the category ID for AI Tools
SET @ai_tools_category_id = (SELECT id FROM categories WHERE slug = 'ai-tools' LIMIT 1);

-- Insert the AI tool with the category ID
INSERT INTO `ai_tools` (`title`, `slug`, `description`, `html_code`, `css_code`, `js_code`, `status`, `category_id`) VALUES 
(
    'YouTube Auto-Subscribe Link Generator',
    'youtube-auto-subscribe-link-generator',
    'Create links that automatically subscribe users to your YouTube channel',
    '<div class="tool-container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fab fa-youtube text-danger" style="font-size: 3rem;"></i>
                            <h4 class="mt-3 text-dark">YouTube Subscribe Link Generator</h4>
                            <p class="text-muted">Generate direct subscription links for your YouTube channel</p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="channelUrl" class="form-label fw-bold">Enter your YouTube Channel URL:</label>
                            <input type="url" id="channelUrl" placeholder="https://www.youtube.com/@YourChannel or https://www.youtube.com/channel/UC..." class="form-control form-control-lg" style="border: 2px solid #e9ecef;">
                            <div class="form-text">Supports both @username and /channel/ URL formats</div>
                        </div>
                        
                        <div class="d-grid">
                            <button onclick="generateLink()" class="btn btn-danger btn-lg">
                                <i class="fas fa-magic me-2"></i>Generate Subscribe Link
                            </button>
                        </div>
                        
                        <div id="result" class="mt-4"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>',
    '.tool-container {
        padding: 20px 0;
        min-height: 400px;
    }
    
    .result-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 15px;
        margin-top: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .result-box h5 {
        color: white;
        margin-bottom: 20px;
        font-weight: 600;
    }
    
    .link-display {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 10px;
        padding: 15px;
        word-break: break-all;
        font-family: monospace;
        font-size: 14px;
    }
    
    .copy-btn {
        background: #28a745;
        border: none;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .copy-btn:hover {
        background: #218838;
        transform: translateY(-2px);
    }
    
    .test-btn {
        background: #17a2b8;
        border: none;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }
    
    .test-btn:hover {
        background: #138496;
        color: white;
        transform: translateY(-2px);
    }
    
    .card {
        border-radius: 20px !important;
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #ff416c, #ff4b2b);
        border: none;
        border-radius: 10px;
        padding: 12px 30px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 65, 108, 0.3);
    }',
    'function generateLink() {
        const url = document.getElementById("channelUrl").value.trim();
        
        if (!url) {
            showError("Please enter a YouTube channel URL");
            return;
        }
        
        let channelIdentifier = "";
        let subscribeLink = "";
        
        // Handle @username format
        if (url.includes("/@")) {
            const username = url.split("/@")[1].split("/")[0].split("?")[0];
            subscribeLink = `https://www.youtube.com/@${username}?sub_confirmation=1`;
            channelIdentifier = `@${username}`;
        }
        // Handle /channel/ format
        else if (url.includes("/channel/")) {
            const channelId = url.split("/channel/")[1].split("/")[0].split("?")[0];
            subscribeLink = `https://www.youtube.com/channel/${channelId}?sub_confirmation=1`;
            channelIdentifier = channelId;
        }
        // Handle /c/ format (old custom URL format)
        else if (url.includes("/c/")) {
            const customName = url.split("/c/")[1].split("/")[0].split("?")[0];
            subscribeLink = `https://www.youtube.com/c/${customName}?sub_confirmation=1`;
            channelIdentifier = customName;
        }
        else {
            showError("Invalid YouTube URL format. Please use: https://www.youtube.com/@username or https://www.youtube.com/channel/UC...");
            return;
        }
        
        displayResult(subscribeLink, channelIdentifier);
    }
    
    function displayResult(subscribeLink, channelName) {
        document.getElementById("result").innerHTML = `
            <div class="result-box">
                <h5><i class="fas fa-check-circle me-2"></i>Subscribe Link Generated Successfully!</h5>
                <p class="mb-3"><strong>Channel:</strong> ${channelName}</p>
                <div class="link-display mb-3">${subscribeLink}</div>
                <div class="d-flex flex-wrap gap-2">
                    <button onclick="copyLink()" class="copy-btn">
                        <i class="fas fa-copy me-2"></i>Copy Link
                    </button>
                    <a href="${subscribeLink}" target="_blank" class="test-btn">
                        <i class="fas fa-external-link-alt me-2"></i>Test Link
                    </a>
                </div>
                <div class="mt-3">
                    <small><i class="fas fa-info-circle me-2"></i>This link will prompt visitors to subscribe to your channel when clicked.</small>
                </div>
            </div>
        `;
    }
    
    function copyLink() {
        const linkText = document.querySelector(".link-display").textContent;
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(linkText).then(() => {
                showCopySuccess();
            }).catch(() => {
                fallbackCopy(linkText);
            });
        } else {
            fallbackCopy(linkText);
        }
    }
    
    function fallbackCopy(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand("copy");
            showCopySuccess();
        } catch (err) {
            console.error("Failed to copy: ", err);
            alert("Failed to copy. Please select and copy the link manually.");
        }
        
        document.body.removeChild(textArea);
    }
    
    function showCopySuccess() {
        const copyBtn = document.querySelector(".copy-btn");
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = `<i class="fas fa-check me-2"></i>Copied!`;
        copyBtn.style.background = "#28a745";
        
        setTimeout(() => {
            copyBtn.innerHTML = originalText;
            copyBtn.style.background = "#28a745";
        }, 2000);
    }
    
    function showError(message) {
        document.getElementById("result").innerHTML = `
            <div class="alert alert-danger border-0 shadow-sm" style="border-radius: 15px;">
                <i class="fas fa-exclamation-triangle me-2"></i>${message}
            </div>
        `;
    }
    
    // Allow Enter key to trigger generation
    document.getElementById("channelUrl").addEventListener("keypress", function(e) {
        if (e.key === "Enter") {
            generateLink();
        }
    });',
    'published',
    @ai_tools_category_id
);

-- Add full_code column to ai_tools table if it doesn't exist
ALTER TABLE `ai_tools` 
ADD COLUMN IF NOT EXISTS `full_code` LONGTEXT AFTER `tool_logo`;

-- Migrate existing data from separate columns to full_code
-- Only update records where full_code is NULL or empty
UPDATE `ai_tools` 
SET `full_code` = CONCAT(
    IFNULL(`html_code`, ''),
    CASE WHEN `css_code` IS NOT NULL AND `css_code` != '' 
        THEN CONCAT('\n\n<style>\n', `css_code`, '\n</style>') 
        ELSE '' 
    END,
    CASE WHEN `js_code` IS NOT NULL AND `js_code` != '' 
        THEN CONCAT('\n\n<script>\n', `js_code`, '\n</script>') 
        ELSE '' 
    END
)
WHERE (`full_code` IS NULL OR `full_code` = '')
  AND (`html_code` IS NOT NULL OR `css_code` IS NOT NULL OR `js_code` IS NOT NULL);

SELECT 'Migration completed! full_code column added and data migrated.' as 'Status';