<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Get latest posts for homepage: show ONLY posts explicitly assigned to the "Home Page" category
// If the "Home Page" category doesn't exist or has no posts, fallback to recent posts (excluding Tutorials)
$home_slug = 'home-page';
$sql = "SELECT p.*, COALESCE(u.username, 'admin') as username,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
        FROM posts p
        LEFT JOIN users u ON p.user_id = u.id
        JOIN post_categories pc ON p.id = pc.post_id
        JOIN categories c ON c.id = pc.category_id
        WHERE p.status = 'published' AND c.slug = ?
        ORDER BY p.created_at DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $home_slug);
$stmt->execute();
$result = $stmt->get_result();
$posts = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { $posts[] = $row; }
} else {
    // Fallback: latest published posts excluding Tutorials
    $sql = "SELECT p.*, COALESCE(u.username, 'admin') as username,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = 'published'
              AND NOT EXISTS (
                SELECT 1 FROM post_categories pc
                JOIN categories c ON c.id = pc.category_id
                WHERE pc.post_id = p.id AND (c.slug = 'tutorials' OR c.slug = 'ai-tools')
              )
            ORDER BY p.created_at DESC
            LIMIT 10";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) { $posts[] = $row; }
    }
}

// Sidebar widgets are now handled by includes/sidebar.php

// Set current page for navigation highlighting
$current_page = 'home';

// Track page view
$page_url = $_SERVER['REQUEST_URI'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

$sql = "INSERT INTO statistics (page_url, ip_address, user_agent, referrer) 
        VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $page_url, $ip_address, $user_agent, $referrer);
$stmt->execute();

// Include header
include 'includes/header.php';
?>

<div class="container container-narrow">
    <!-- Top Posts Ad/Gadget area -->
    <?php $home_top_widgets = getWidgets('home_top'); ?>
    <?php if (!empty($home_top_widgets)): ?>
        <section class="mb-4" data-aos="fade-up" data-aos-once="true">
            <?php echo displayWidgets('home_top'); ?>
        </section>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 posts-grid">
            <?php if (empty($posts)): ?>
                <div class="alert alert-info">No posts found.</div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php $i = 0; foreach ($posts as $post): $i++; ?>
                    
                    <!-- Display first 4 posts -->
                    <?php if ($i <= 4): ?>
                        <div class="col">
                            <article class="post-card" data-aos="fade-up" data-aos-delay="<?php echo $i * 50; ?>" data-aos-once="true">
                                <div class="post-media">
                                    <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" class="post-thumbnail-link">
                                        <?php 
                                            // Resolve thumbnail using helper (supports YouTube and local uploads)
                                            $img = '';
                                            if (function_exists('getPostThumbnail')) {
                                                $img = getPostThumbnail($post);
                                            } else if (!empty($post['featured_image'])) {
                                                if (preg_match('/^https?:\/\//', $post['featured_image'])) {
                                                    $img = $post['featured_image'];
                                                } else {
                                                    $img = BASE_URL . '/uploads/' . $post['featured_image'];
                                                }
                                            }
                                        ?>
                                        <?php if (!empty($img)): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy" width="100%" height="auto">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="bx bx-image-alt fs-1"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="post-body">
                                    <h2 class="post-title">
                                        <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                    </h2>
                                    <div class="post-meta">
                                        <span><i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($post['username']); ?></span>
                                        <span class="counter"><i class="fas fa-comments"></i><?php echo (int)$post['comment_count']; ?></span>
                                        <span class="counter"><i class="fas fa-heart"></i><?php echo (int)$post['like_count']; ?></span>
                                    </div>
                                    <div class="post-excerpt">
                                        <?php echo $post['excerpt'] ? htmlspecialchars($post['excerpt']) : htmlspecialchars(substr(strip_tags($post['content']), 0, 160)) . '...'; ?>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-read">
                                        Read more <i class="bx bx-right-arrow-alt ms-1"></i>
                                    </a>
                                </div>
                            </article>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Insert home_middle ad as the 5th div -->
                    <?php if ($i == 4): ?>
                        <div class="col-12">
                            <?php $home_middle_widgets = getWidgets('home_middle'); ?>
                            <?php if (!empty($home_middle_widgets)): ?>
                                <!-- Full width ad container -->
                                <div class="home-middle-ad my-3 w-100 clearfix highlight" data-aos="fade-up" data-aos-once="true">
                                    <?php echo displayWidgets('home_middle'); ?>
                                </div>
                            <?php else: ?>
                                <!-- Fallback ad content -->
                                <div class="home-middle-ad my-3 w-100 clearfix highlight" data-aos="fade-up" data-aos-once="true">
                                    <div class="widget">
                                        <div class="widget-content">
                                            <div class="text-center">
                                                <p style="background-color: #fff3cd; color: #856404; padding: 20px; border: 1px dashed #ffc107; border-radius: 5px;">
                                                    Advertisement Space<br>
                                                    728x90 or 336x280 Ad Unit
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Display remaining posts (5th post onwards) -->
                    <?php if ($i > 4): ?>
                        <div class="col">
                            <article class="post-card" data-aos="fade-up" data-aos-delay="<?php echo $i * 50; ?>" data-aos-once="true">
                                <div class="post-media">
                                    <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" class="post-thumbnail-link">
                                        <?php 
                                            // Resolve thumbnail using helper (supports YouTube and local uploads)
                                            $img = '';
                                            if (function_exists('getPostThumbnail')) {
                                                $img = getPostThumbnail($post);
                                            } else if (!empty($post['featured_image'])) {
                                                if (preg_match('/^https?:\/\//', $post['featured_image'])) {
                                                    $img = $post['featured_image'];
                                                } else {
                                                    $img = BASE_URL . '/uploads/' . $post['featured_image'];
                                                }
                                            }
                                        ?>
                                        <?php if (!empty($img)): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy" width="100%" height="auto">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="bx bx-image-alt fs-1"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="post-body">
                                    <h2 class="post-title">
                                        <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                    </h2>
                                    <div class="post-meta">
                                        <span><i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                        <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($post['username']); ?></span>
                                        <span class="counter"><i class="fas fa-comments"></i><?php echo (int)$post['comment_count']; ?></span>
                                        <span class="counter"><i class="fas fa-heart"></i><?php echo (int)$post['like_count']; ?></span>
                                    </div>
                                    <div class="post-excerpt">
                                        <?php echo $post['excerpt'] ? htmlspecialchars($post['excerpt']) : htmlspecialchars(substr(strip_tags($post['content']), 0, 160)) . '...'; ?>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    <a href="post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-read">
                                        Read more <i class="bx bx-right-arrow-alt ms-1"></i>
                                    </a>
                                </div>
                            </article>
                        </div>
                    <?php endif; ?>
                    
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <?php include 'includes/sidebar.php'; ?>
        </div>
    </div>
</div>

<?php $home_bottom_widgets = getWidgets('home_bottom'); ?>
<?php if (!empty($home_bottom_widgets)): ?>
<div class="home-bottom-widgets bg-light py-3">
    <div class="container container-narrow">
        <?php echo displayWidgets('home_bottom'); ?>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include 'includes/footer.php';
?>