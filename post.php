<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Get post slug from URL
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

if (empty($slug)) {
    // Redirect to homepage if no slug provided
    redirect(SITE_URL);
}

// Get post by slug
$post = getPostBySlug($slug);

if (!$post) {
    // Post not found, redirect to homepage
    redirect(SITE_URL);
}

// Increment view count
incrementPostViews($post['id']);

// Get post categories
$post_categories = getPostCategories($post['id']);

// Set navigation context for tutorials posts
$current_category_slug = "";
$current_page = "post";
if (!empty($post_categories)) {
    foreach ($post_categories as $cat) {
        $slugLower = strtolower($cat["slug"]);
        if (in_array($slugLower, ["tutorials","computer-tutorials","ai-tools"])) {
            $current_category_slug = $cat["slug"]; 
            break;
        }
    }
}

// Get related posts
$related_posts = getRelatedPosts($post['id'], 3);

// Process comment submission
$comment_error = '';
$comment_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $comment_error = "Invalid form submission";
    } else {
        $name = clean($_POST['name']);
        $email = clean($_POST['email']);
        $comment = clean($_POST['comment']);
        
        // Simple validation
        if (empty($name) || empty($email) || empty($comment)) {
            $comment_error = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $comment_error = "Invalid email format";
        } else {
            // Insert comment
            $sql = "INSERT INTO comments (post_id, name, email, content, status) VALUES (?, ?, ?, ?, 'approved')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $post['id'], $name, $email, $comment);
            
            if ($stmt->execute()) {
                $comment_success = "Thank you for your comment!";
            } else {
                $comment_error = "Failed to submit comment: " . $stmt->error;
            }
        }
    }
}

// Process reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_submit'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $comment_error = "Invalid form submission";
    } else {
        $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $name = clean($_POST['reply_name']);
        $email = clean($_POST['reply_email']);
        $comment = clean($_POST['reply_comment']);
        
        // Simple validation
        if (empty($name) || empty($email) || empty($comment) || $parent_id === 0) {
            $comment_error = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $comment_error = "Invalid email format";
        } else {
            // Insert reply
            $sql = "INSERT INTO comments (post_id, parent_id, name, email, comment, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $post['id'], $parent_id, $name, $email, $comment);
            
            if ($stmt->execute()) {
                $comment_success = "Your reply has been submitted and is awaiting moderation.";
                // Redirect to avoid form resubmission
                redirect(SITE_URL . '/post.php?slug=' . $slug . '#comment-' . $parent_id);
            } else {
                $comment_error = "Failed to submit reply: " . $stmt->error;
            }
        }
    }
}

// Process like submission
if (isset($_POST['like_submit']) && isset($_POST['csrf_token']) && validateCSRFToken($_POST['csrf_token'])) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Check if already liked
    $sql = "SELECT id FROM likes WHERE post_id = ? AND ip_address = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $post['id'], $ip_address);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Insert like
        $sql = "INSERT INTO likes (post_id, ip_address) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $post['id'], $ip_address);
        $stmt->execute();
    }
    
    // Redirect to avoid form resubmission
    redirect(SITE_URL . '/post.php?slug=' . $slug);
}

// Get approved comments for this post
$comments = getComments($post['id'], 'approved');

// Get like count
$like_count = getLikeCount($post['id']);

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = $post['title'];

// Track page view for statistics
trackPageView('post', $post['id']);

// Get custom scripts for post content
$post_scripts = getCustomScripts('post', 'active');

// Include header
include 'includes/header.php';
?>

<div class="container container-narrow mt-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Post content-->
            <article>
                <!-- Post header-->
                <header class="post-hero mb-4" data-aos="fade-up">
                    <!-- Post title-->
                    <h1 class="fw-bolder mb-2"><?php echo htmlspecialchars($post['title']); ?></h1>
                </header>
                
                <!-- Featured image hidden as requested -->
                
                <!-- YouTube video embed (avoid duplicate if already in content) -->
                <?php 
                    $has_inline_youtube = stripos($post['content'], 'youtube.com/embed') !== false || preg_match('/<iframe[^>]+youtube\.com/i', $post['content']);
                ?>
                <?php if (!empty($post['youtube_url']) && !$has_inline_youtube): ?>
                    <div class="ratio ratio-16x9 mb-4">
                        <iframe src="<?php echo getYouTubeEmbedUrl($post['youtube_url'], true); ?>" title="YouTube video" allowfullscreen></iframe>
                    </div>
                <?php endif; ?>

                <!-- Top of Post Ad/Gadget area -->
                <?php $post_top_widgets = getWidgets('post_top'); ?>
                <?php if (!empty($post_top_widgets)): ?>
                    <div class="my-4">
                        <?php echo displayWidgets('post_top'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Post content-->
                <section class="mb-5 post-content">
                    <?php
                    // Check if this is an AI Tools post and render accordingly
                    if (isAIToolsPost($post['id'])) {
                        $content = renderAIToolPost($post);
                        echo decodeEmojis($content);
                    } else {
                        // For regular posts, isolate risky HTML/CSS/JS to this post only
                        $raw = decodeEmojis($post['content']);
                        $needs_isolation = preg_match('/<(script|style|link)[^>]*>/i', $raw) || preg_match('/\son[a-z]+\s*=\s*["\']/i', $raw);
                        if ($needs_isolation) {
                            $frame_id = 'post-iframe-' . (int)$post['id'];
                            $inner = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head><body style=\"margin:0;padding:0\">" . $raw . "<script>(function(){function r(){var h=Math.max(document.body.scrollHeight,document.documentElement.scrollHeight);parent.postMessage({type:'post-iframe-resize',id:'" . $frame_id . "',height:h},'*');}window.addEventListener('load',r);new MutationObserver(r).observe(document.body,{childList:true,subtree:true,attributes:true,characterData:true});setInterval(r,500);}())<\/script></body></html>";
                            $src = 'data:text/html;base64,' . base64_encode($inner);
                            echo '<iframe id="' . $frame_id . '" src="' . $src . '" sandbox="allow-scripts allow-same-origin allow-forms allow-popups" style="width:100%;border:0;min-height:400px;border-radius:8px;background:#fff"></iframe>';
                            echo '<script>(function(){var id="' . $frame_id . '";window.addEventListener("message",function(e){try{if(!e.data||e.data.type!==' . '"post-iframe-resize"' . '||e.data.id!==id)return;var el=document.getElementById(id);if(el){var h=parseInt(e.data.height,10)||400;el.style.height=Math.max(400,h)+"px";}}catch(_){}},false);}())</script>';
                        } else {
                            // Safe content without scripts/styles can render inline
                            echo $raw;
                        }
                    }
                    ?>
                </section>

                <!-- Middle of Post Ad/Gadget area -->
                <?php $post_middle_widgets = getWidgets('post_middle'); ?>
                <?php if (!empty($post_middle_widgets)): ?>
                    <div class="my-4">
                        <?php echo displayWidgets('post_middle'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Like and Share Section -->
                <div class="like-share-section mb-5">
                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap">
                        <!-- Like Button -->
                        <button type="button" id="like-btn" class="btn-action like-btn" data-post-id="<?php echo $post['id']; ?>">
                            <i class="fas fa-heart"></i>
                            <span>Like (<span class="like-count"><?php echo $like_count; ?></span>)</span>
                        </button>
                        
                        <!-- Share Dropdown -->
                        <div class="share-dropdown">
                            <button type="button" class="btn-action share-btn" id="share-toggle">
                                <i class="fas fa-share-alt"></i>
                                <span>Share</span>
                            </button>
                            <div class="share-menu" id="share-menu">
                                <?php
                                $share_url = urlencode(SITE_URL . '/post.php?slug=' . $slug);
                                $share_title = urlencode($post['title']);
                                ?>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-facebook-f"></i> Facebook
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-twitter"></i> Twitter
                                </a>
                                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $share_url; ?>&title=<?php echo $share_title; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-linkedin-in"></i> LinkedIn
                                </a>
                                <a href="https://api.whatsapp.com/send?text=<?php echo $share_title; ?>%20<?php echo $share_url; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                </a>
                                <a href="https://pinterest.com/pin/create/button/?url=<?php echo $share_url; ?>&description=<?php echo $share_title; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-pinterest-p"></i> Pinterest
                                </a>
                                <a href="https://www.reddit.com/submit?url=<?php echo $share_url; ?>&title=<?php echo $share_title; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-reddit-alien"></i> Reddit
                                </a>
                                <a href="https://t.me/share/url?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" target="_blank" class="share-item">
                                    <i class="fab fa-telegram-plane"></i> Telegram
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Custom scripts for post content -->
                <?php foreach ($post_scripts as $script): ?>
                    <?php echo $script['content']; ?>
                <?php endforeach; ?>

                <!-- End of Post Ad/Gadget area -->
                <?php $post_bottom_widgets = getWidgets('post_bottom'); ?>
                <?php if (!empty($post_bottom_widgets)): ?>
                    <div class="my-4">
                        <?php echo displayWidgets('post_bottom'); ?>
                    </div>
                <?php endif; ?>
            </article>
            
            <!-- Related posts section-->
            <section class="mb-5">
                <div class="card bg-light">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>Related Posts</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (!empty($related_posts)): ?>
                                <?php foreach ($related_posts as $related_post): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <?php if (!empty($related_post['featured_image'])): ?>
                                                <img class="card-img-top" src="<?php echo SITE_URL; ?>/uploads/<?php echo $related_post['featured_image']; ?>" alt="<?php echo htmlspecialchars($related_post['title']); ?>" />
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <a href="<?php echo SITE_URL; ?>/post.php?slug=<?php echo $related_post['slug']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($related_post['title']); ?>
                                                    </a>
                                                </h5>
                                                <p class="card-text small"><?php echo substr(strip_tags($related_post['content']), 0, 100); ?>...</p>
                                            </div>
                                            <div class="card-footer">
                                                <small class="text-muted">Posted on <?php echo formatDate($related_post['created_at']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="mb-0">No related posts found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Comments section-->
            <section class="mb-5">
                <div class="card bg-light">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Comments (<?php echo count($comments); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <!-- Comment form-->
                        <form method="post" action="" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            
                            <?php if (!empty($comment_error)): ?>
                                <div class="alert alert-danger"><?php echo $comment_error; ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($comment_success)): ?>
                                <div class="alert alert-success"><?php echo $comment_success; ?></div>
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <div class="form-text">Your email will not be published.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comment" class="form-label">Comment <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                            </div>
                            
                            <button type="submit" name="comment_submit" class="btn btn-primary">Submit Comment</button>
                        </form>
                        
                        <!-- Comments list -->
                        <?php if (!empty($comments)): ?>
                            <?php foreach ($comments as $comment): ?>
                                <div class="d-flex mb-4" id="comment-<?php echo $comment['id']; ?>">
                                    <div class="flex-shrink-0">
                                        <img class="rounded-circle" src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($comment['email']))); ?>?s=50&d=mp" alt="<?php echo sanitizeOutput($comment['name']); ?>" />
                                    </div>
                                    <div class="ms-3 w-100">
                                        <div class="fw-bold"><?php echo sanitizeOutput($comment['name']); ?></div>
                                        <div class="small text-muted mb-2"><?php echo formatDate($comment['created_at']); ?></div>
                                        <div class="comment-content mb-2">
                                            <?php echo nl2br(sanitizeOutput($comment['content'])); ?>
                                        </div>
                                        
                                        <!-- Comment actions -->
                                        <div class="d-flex mb-3">
                                            <button class="btn btn-sm btn-outline-primary me-2 reply-btn" data-comment-id="<?php echo $comment['id']; ?>">
                                                <i class="fas fa-reply"></i> Reply
                                            </button>
                                        </div>
                                        
                                        <!-- Reply form (hidden by default) -->
                                        <div class="reply-form mb-3 d-none" id="reply-form-<?php echo $comment['id']; ?>">
                                            <form method="post" action="">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <input type="text" class="form-control form-control-sm" name="reply_name" placeholder="Your Name *" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <input type="email" class="form-control form-control-sm" name="reply_email" placeholder="Your Email *" required>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <textarea class="form-control form-control-sm" name="reply_comment" rows="2" placeholder="Your Reply *" required></textarea>
                                                </div>
                                                
                                                <div class="d-flex">
                                                    <button type="submit" name="reply_submit" class="btn btn-sm btn-primary me-2">Submit Reply</button>
                                                    <button type="button" class="btn btn-sm btn-secondary cancel-reply" data-comment-id="<?php echo $comment['id']; ?>">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <!-- Replies -->
                                        <?php if (!empty($comment['replies'])): ?>
                                            <div class="replies ms-4 mt-3">
                                                <?php foreach ($comment['replies'] as $reply): ?>
                                                    <div class="d-flex mb-3">
                                                        <div class="flex-shrink-0">
                                                            <img class="rounded-circle" src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($reply['email']))); ?>?s=35&d=mp" alt="<?php echo sanitizeOutput($reply['name']); ?>" />
                                                        </div>
                                                        <div class="ms-3">
                                                            <div class="fw-bold"><?php echo sanitizeOutput($reply['name']); ?></div>
                                                            <div class="small text-muted mb-2"><?php echo formatDate($reply['created_at']); ?></div>
                                                        <?php echo nl2br(sanitizeOutput($reply['content'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="mb-0">No comments yet. Be the first to comment!</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <?php include 'includes/sidebar.php'; ?>
        </div>
    </div>
</div>

<!-- Like and Share Styles -->
<style>
.like-share-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #333;
}

.btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.like-btn {
    color: #dc3545;
    border-color: #dc3545;
}

.like-btn:hover {
    background: #dc3545;
    color: white;
}

.like-btn.liked {
    background: #dc3545;
    color: white;
}

.like-btn i {
    font-size: 18px;
}

.share-btn {
    color: #0d6efd;
    border-color: #0d6efd;
}

.share-btn:hover {
    background: #0d6efd;
    color: white;
}

.share-dropdown {
    position: relative;
}

.share-menu {
    display: none;
    position: absolute;
    top: 110%;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    padding: 10px;
    min-width: 200px;
    z-index: 1000;
}

.share-menu.active {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

.share-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-weight: 500;
}

.share-item:hover {
    background: #f0f0f0;
    color: #000;
}

.share-item i {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.heart-animation {
    animation: heartBeat 0.6s ease-in-out;
}

@keyframes heartBeat {
    0%, 100% { transform: scale(1); }
    25% { transform: scale(1.3); }
    50% { transform: scale(1.1); }
    75% { transform: scale(1.2); }
}

.btn-action.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn-action.loading .fas {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 576px) {
    .btn-action {
        padding: 10px 20px;
        font-size: 14px;
    }
}
</style>

<!-- Comment Reply, Like and Share JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reply button click
    const replyButtons = document.querySelectorAll('.reply-btn');
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.getAttribute('data-comment-id');
            const replyForm = document.getElementById('reply-form-' + commentId);
            replyForm.classList.remove('d-none');
        });
    });
    
    // Cancel reply button click
    const cancelButtons = document.querySelectorAll('.cancel-reply');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.getAttribute('data-comment-id');
            const replyForm = document.getElementById('reply-form-' + commentId);
            replyForm.classList.add('d-none');
        });
    });
    
    // Share dropdown toggle
    const shareToggle = document.getElementById('share-toggle');
    const shareMenu = document.getElementById('share-menu');
    
    if (shareToggle && shareMenu) {
        shareToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            shareMenu.classList.toggle('active');
        });
        
        // Close share menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!shareMenu.contains(e.target) && e.target !== shareToggle) {
                shareMenu.classList.remove('active');
            }
        });
    }
    
    // Like button functionality
    const likeButton = document.getElementById('like-btn');
    if (likeButton) {
        likeButton.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const heartIcon = this.querySelector('.fas');
            const likeCount = this.querySelector('.like-count');
            
            // Add loading state
            this.classList.add('loading');
            
            // Make AJAX request
            fetch('like-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'post_id=' + postId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update like count
                    likeCount.textContent = data.like_count;
                    
                    // Update button state
                    if (data.action === 'liked') {
                        this.classList.add('liked');
                        heartIcon.classList.add('heart-animation');
                        
                        // Remove animation class after animation completes
                        setTimeout(() => {
                            heartIcon.classList.remove('heart-animation');
                        }, 600);
                    } else {
                        this.classList.remove('liked');
                    }
                } else {
                    alert('Error: ' + (data.message || 'Something went wrong'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Something went wrong. Please try again.');
            })
            .finally(() => {
                // Remove loading state
                this.classList.remove('loading');
            });
        });
    }
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>