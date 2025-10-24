<?php
// Get active widgets ordered by position for sidebar
$allWidgets = getWidgets();
// Filter widgets for sidebar position and active status
$widgets = array_filter($allWidgets, function($w) {
    return isset($w['position']) && $w['position'] === 'sidebar' && isset($w['is_active']) && $w['is_active'] == 1;
});

// Widgets found, process them

// Sort widgets by sort_order
usort($widgets, function($a, $b) {
    $ao = $a['sort_order'] ?? 0;
    $bo = $b['sort_order'] ?? 0;
    return $ao <=> $bo;
});
?>

<div class="sidebar">
    <!-- Search Widget -->
    <div class="card mb-4 shadow-sm" data-aos="fade-up">
        <div class="card-header bg-primary text-white">
            <i class='bx bx-search-alt me-2'></i>Search
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>/search.php" method="get">
                <div class="input-group">
                    <input class="form-control" type="text" name="q" placeholder="Search for..." required />
                    <button class="btn btn-primary" type="submit"><i class='bx bx-search'></i></button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($widgets)): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-0">No widgets configured for sidebar.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($widgets as $widget): ?>
        <div class="card mb-4 shadow-sm" data-aos="fade-up" data-aos-delay="<?php echo (isset($widget['sort_order']) ? $widget['sort_order'] : 1) * 100; ?>">
            <div class="card-header bg-primary text-white">
                <?php 
                // Add icon based on widget type
                $icon = 'bx-cube';
                $type = $widget['type'] ?? '';
                switch (strtolower($type)) {
                    case 'popular_posts': $icon = 'bx-trending-up'; break;
                    case 'latest_posts': $icon = 'bx-time'; break;
                    case 'categories': $icon = 'bx-category'; break;
                    case 'html': $icon = 'bx-code-alt'; break;
                    case 'advertisement': $icon = 'bx-ad'; break;
                    case 'search': $icon = 'bx-search'; break;
                }
                ?>
                <i class='bx <?php echo $icon; ?> me-2'></i><?php echo htmlspecialchars($widget['title']); ?>
            </div>
            <div class="card-body">
                <?php
                // Render widget and safely extract only its body content
                $widget_html = renderWidget($widget);
                
                // Preferred: capture widget-body content and ignore the outer wrappers
                if (preg_match('/<div class=\"widget-body\">(.*?)<\\/div>\s*<\\/div>\s*<\\/div>\s*$/s', $widget_html, $matches)) {
                    echo $matches[1];
                } elseif (preg_match('/<div class=\"widget-body\">(.*?)<\\/div>/s', $widget_html, $matches2)) {
                    // Fallback: capture body even if trailing wrappers differ
                    echo $matches2[1];
                } else {
                    // Final fallback for legacy HTML/Ad widgets
                    switch (strtolower($type)) {
                        case 'html':
                        case 'advertisement':
                            // Display custom HTML content without escaping
                            $content = $widget['content'];
                            
                            // Aggressive decoding for content that was stored encoded
                            $decode_attempts = 0;
                            $previous_content = '';
                            while ($content !== $previous_content && $decode_attempts < 10) {
                                $previous_content = $content;
                                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $decode_attempts++;
                            }
                            
                            $replacements = [
                                '&quot;' => '\"',
                                '&#34;' => '\"',
                                '&apos;' => "'",
                                '&#39;' => "'",
                                '&lt;' => '<',
                                '&gt;' => '>',
                                '&amp;' => '&'
                            ];
                            $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                            
                            if (strpos($content, '&') !== false) {
                                $content = htmlspecialchars_decode($content, ENT_QUOTES);
                            }
                            
                            if (strtolower($widget['type']) === 'advertisement') {
                                echo '<div class="advertisement-widget">';
                                echo $content;
                                echo '</div>';
                            } else {
                                echo $content;
                            }
                            break;
                        default:
                            // As a last resort, strip everything before/after widget-body
                            $stripped = preg_replace('/^.*?<div class=\"widget-body\">/s', '', $widget_html);
                            $stripped = preg_replace('/<\\/div>\s*<\\/div>\s*$/s', '', $stripped);
                            echo $stripped;
                            break;
                    }
                }
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom scripts for sidebar location -->
    <?php
    $sidebar_scripts = getCustomScripts('sidebar', 'active');
    foreach ($sidebar_scripts as $script) {
        echo $script['content'];
    }
    ?>
</div>

<style>
/* Custom styles for sidebar widgets */
.popular-posts .d-flex:last-child,
.latest-posts .d-flex:last-child {
    margin-bottom: 0 !important;
}

.popular-posts img,
.latest-posts img {
    transition: transform 0.2s ease;
}

.popular-posts img:hover,
.latest-posts img:hover {
    transform: scale(1.05);
}

.popular-posts h6 a,
.latest-posts h6 a {
    font-size: 0.9rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.popular-posts h6 a:hover,
.latest-posts h6 a:hover {
    color: #0d6efd !important;
}

.popular-posts .small,
.latest-posts .small {
    font-size: 0.75rem;
}

/* Alignment feature removed: keeping only general sidebar styles */
</style>