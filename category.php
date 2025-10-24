<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Get category slug from URL
$slug = isset($_GET['slug']) ? clean($_GET['slug']) : '';

if (empty($slug)) {
    // Redirect to homepage if no slug provided
    redirect(SITE_URL);
}

// Get category by slug
$category = getCategoryBySlug($slug);

if (!$category) {
    // Category not found, redirect to homepage
    redirect(SITE_URL);
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page = max(1, (int)$page);
$per_page = 12; // 12 posts per page
$offset = ($page - 1) * $per_page;

// Get posts for this category with pagination
$posts = getPostsByCategoryId($category['id'], $per_page, $offset);
$total_posts = getPostCountByCategory($category['id']);
$total_pages = max(1, (int)ceil($total_posts / $per_page));

// Defensive fallback: if count query returns 0 but we clearly have a full page of items
// (or we are beyond page 1), estimate at least one more page so pagination renders.
if (($total_posts == 0) && (count($posts) >= $per_page || $page > 1)) {
    $total_pages = max($page + 1, 2);
}
// Clamp page into valid range
if ($page > $total_pages) { $page = $total_pages; }

// Page title
$page_title = $category['name'];

// Set current page for navigation highlighting
$current_page = 'category';
$current_category_slug = $category['slug'];

// Track page view for statistics
trackPageView('category', $category['id']);

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container container-narrow mt-4">
    <!-- Category Hero -->

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8 posts-grid">
            <?php if (!empty($posts)): ?>
                <?php if (strtolower($category['slug']) === 'ai-tools'): ?>
                    <!-- AI Tools Grid Layout -->
                    <div class="ai-tools-section">
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-2 g-4 ai-tools-grid">
                            <?php $i = 0; foreach ($posts as $post): $i++; ?>
                            <div class="col">
                                <a class="ai-tool-card aos-init aos-animate" href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
                                    <div class="tool-logo">
                                        <?php 
                                        $logo = '';
                                        if (!empty($post['featured_image'])) {
                                            $logo = preg_match('/^https?:\/\//', $post['featured_image']) ? $post['featured_image'] : BASE_URL . '/uploads/' . $post['featured_image'];
                                        }
                                        ?>
                                        <?php if ($logo): ?>
                                            <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="tool-logo-img" loading="lazy">
                                        <?php else: ?>
                                            <div class="logo-placeholder"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tool-content">
                                        <h3 class="tool-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                        <p class="tool-description">
                                            <?php 
                                            $excerpt = !empty($post['excerpt']) ? $post['excerpt'] : substr(strip_tags($post['content']), 0, 120);
                                            echo htmlspecialchars($excerpt) . '...';
                                            ?>
                                        </p>
                                        <div class="tool-footer">
                                            <span class="pricing-tag">FREE</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif (strtolower($category['slug']) === 'computer-tutorials' || strtolower($category['slug']) === 'tutorials' || strtolower($category['slug']) === 'videos-tutorial' || strtolower($category['slug']) === 'video-tutorials' || strtolower($category['slug']) === 'videos-tutorials'): ?>
                    <!-- Replicate Home style for Tutorials -->
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php $i = 0; foreach ($posts as $post): $i++; ?>
                        <?php $img = getPostThumbnail($post); ?>
                        <div class="col">
                            <article class="post-card" data-aos="fade-up" data-aos-delay="<?php echo $i * 50; ?>">
                                <div class="post-media">
                                    <a href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>" class="post-thumbnail-link">
                                        <?php if (!empty($img)): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="bx bx-image-alt fs-1"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="post-body">
                                    <h2 class="post-title"><a href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                                    <div class="post-meta">
                                        <span><i class="fas fa-calendar me-1"></i><?php echo formatDate($post['created_at']); ?></span>
                                        <span class="counter"><i class="fas fa-comment"></i><?php echo (int)($post['comment_count'] ?? 0); ?></span>
                                        <span class="counter"><i class="fas fa-heart"></i><?php echo (int)($post['like_count'] ?? 0); ?></span>
                                    </div>
                                    <div class="post-excerpt">
                                        <?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 160)) . '...'; ?>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    <a href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-read">Read more <i class="bx bx-right-arrow-alt ms-1"></i></a>
                                </div>
                            </article>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php $i = 0; foreach ($posts as $post): $i++; ?>
                        <?php 
                            $img = ''; 
                            if (!empty($post['featured_image'])) { 
                                if (preg_match('/^https?:\/\//', $post['featured_image'])) {
                                    $img = $post['featured_image'];
                                } else {
                                    $img = BASE_URL . '/uploads/' . $post['featured_image'];
                                }
                            } 
                        ?>
                        <div class="col">
                            <article class="post-card" data-aos="fade-up" data-aos-delay="<?php echo $i * 50; ?>">
                                <div class="post-media">
                                    <a href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>" class="post-thumbnail-link">
                                        <?php if ($img): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="bx bx-image-alt fs-1"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="post-body">
                                    <h2 class="post-title"><a href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h2>
                                    <div class="post-meta">
                                        <span><i class="fas fa-calendar me-1"></i><?php echo formatDate($post['created_at']); ?></span>
                                        <span class="counter"><i class="fas fa-eye"></i><?php echo (int)($post['views'] ?? 0); ?></span>
                                        <span class="counter"><i class="fas fa-comment"></i><?php echo (int)($post['comment_count'] ?? 0); ?></span>
                                        <span class="counter"><i class="fas fa-heart"></i><?php echo (int)($post['like_count'] ?? 0); ?></span>
                                    </div>
                                    <div class="post-excerpt">
                                        <?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 160)) . '...'; ?>
                                    </div>
                                </div>
                                <div class="post-actions">
                                    <a href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-read">Read more <i class="bx bx-right-arrow-alt ms-1"></i></a>
                                </div>
                            </article>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo urlencode($slug); ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo urlencode($slug); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo urlencode($slug); ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">No posts found in this category.</div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="col-lg-4">
            <?php include 'includes/sidebar.php'; ?>
        </aside>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>