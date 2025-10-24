<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" crossorigin>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" as="style" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style" crossorigin>
    <link rel="preload" href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' as="style" crossorigin>
    
    <!-- Bootstrap CSS with preload -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Font Awesome with preload -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Google Fonts with font-display: swap -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Box Icons with preload -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet' crossorigin="anonymous">
    
    <!-- AOS Animation Library (deferred) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Critical CSS combined for better performance -->
    <style>
        /* Critical above-the-fold CSS */
        :root {
            --primary-color: #2563eb;
            --secondary-color: #f43f5e;
            --dark-color: #0f172a;
            --light-color: #f8fafc;
            --success-color: #16a34a;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        /* Critical layout styles */
        .container-narrow {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Critical header styles */
        .topbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.875rem;
            padding: 12px 0;
        }
        
        .brandbar {
            background: white;
            padding: 24px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .site-logo {
            height: 80px;
            width: auto;
        }
        
        .navstrip {
            background: white;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
        }
        
        .navbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        /* Critical card styles */
        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        /* Critical button styles */
        .btn {
            border-radius: 0.5rem;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2a75e8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(58, 134, 255, 0.3);
        }
        
        /* Critical grid styles */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-md-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
        
        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        
        /* Critical ad styles */
        .ad-slot, .header-ad-container, .advertisement-widget {
            contain: layout style;
            will-change: auto;
        }
        
        .ad-slot > *, .header-ad-container > *, .advertisement-widget > * {
            max-width: 100%;
            height: auto;
        }
        
        .header-ad-container {
            min-height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Critical responsive styles */
        @media (max-width: 768px) {
            .col-md-8, .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .brandbar {
                padding: 1rem 0;
            }
        }
        
        /* Hide AOS until loaded */
        [data-aos] {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        [data-aos].aos-animate {
            opacity: 1;
        }
        
        /* Critical post card styles */
        .post-card {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .post-media {
            position: relative;
            overflow: hidden;
            height: 200px;
        }
        
        .post-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .post-card:hover .post-media img {
            transform: scale(1.05);
        }
        
        .post-body {
            padding: 1.5rem;
        }
        
        .post-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }
        
        .post-title a {
            color: #0f172a;
            text-decoration: none;
        }
        
        .post-title a:hover {
            color: var(--primary-color);
        }
        
        .post-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .post-excerpt {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }
        
        .btn-read {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1d4ed8 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .btn-read:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3), 0 2px 4px -1px rgba(59, 130, 246, 0.1);
        }
        
        /* Critical widget styles */
        .widget {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            margin-bottom: 1.5rem;
        }
        
        .widget-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4f46e5 100%);
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 0.5rem 0.5rem 0 0;
            font-weight: 600;
        }
        
        .widget-body {
            padding: 1.25rem;
        }
        
        /* Critical form styles */
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            background: white;
            color: #0f172a;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Critical footer styles */
        .site-footer {
            background: white;
            padding: 2rem 0;
            border-top: 1px solid #e2e8f0;
            margin-top: 2rem;
        }
        
        /* Critical back to top button */
        #backToTop {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        #backToTop.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        #backToTop button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
    
    <!-- Non-critical CSS files loaded asynchronously -->
    <link href="<?php echo BASE_URL; ?>/assets/css/modern-theme.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="<?php echo BASE_URL; ?>/assets/css/mobile-menu-fix.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="<?php echo BASE_URL; ?>/assets/css/ad-performance.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="<?php echo BASE_URL; ?>/assets/css/performance-optimizations.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet" media="print" onload="this.media='all'">
    
    <!-- Prefetch important pages for better perceived performance -->
    <link rel="prefetch" href="<?php echo BASE_URL; ?>/category.php">
    <link rel="prefetch" href="<?php echo BASE_URL; ?>/about.php">
    <link rel="prefetch" href="<?php echo BASE_URL; ?>/contact.php">
    
    <!-- Custom Scripts - Header -->
    <?php
    $page = isset($current_page) ? $current_page : '';
    $header_scripts = getCustomScripts('header', $page);
    foreach ($header_scripts as $script) {
        echo $script['content'];
    }
    // Load categories configured for the primary and secondary header menus
    $header_categories = function_exists('getCategoriesForMenu') ? getCategoriesForMenu('primary_header') : [];
    // Only show categories that are explicitly configured for primary header
    // Remove the fallback to prevent showing all categories
    $secondary_categories = function_exists('getCategoriesForMenu') ? getCategoriesForMenu('secondary_header') : [];
    
    // Get secondary dropdown name from first secondary category
    $secondary_dropdown_name = 'More';
    if (!empty($secondary_categories) && isset($secondary_categories[0]['secondary_dropdown_name'])) {
        $secondary_dropdown_name = $secondary_categories[0]['secondary_dropdown_name'];
    }

    // Site settings: logo, socials, ads, topbar menu
    $site_settings = function_exists('getSettings') ? getSettings([
        'logo_text','logo_image_url',
        'facebook_url','instagram_url','telegram_url','twitter_url','youtube_url',
        'header_ad_html','topbar_menu_links'
    ]) : [];
    ?>
</head>
<body>
    <header class="site-header">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="container container-narrow d-flex align-items-center justify-content-between">
                <div class="top-links d-flex align-items-center gap-3">
                    <?php
                    $tb_items = [];
                    if (!empty($site_settings['topbar_menu_links'])) {
                        $decoded = json_decode($site_settings['topbar_menu_links'], true);
                        if (is_array($decoded)) { $tb_items = $decoded; }
                    }
                    if (empty($tb_items)) {
                        $tb_items = [
                            ['label' => 'Home', 'url' => BASE_URL],
                            ['label' => 'About', 'url' => BASE_URL . '/about.php'],
                            ['label' => 'Contact', 'url' => BASE_URL . '/contact.php'],
                        ];
                    }
                    foreach ($tb_items as $it):
                        $label = htmlspecialchars($it['label'] ?? '');
                        $url = htmlspecialchars($it['url'] ?? '#');
                    ?>
                        <a href="<?php echo $url; ?>" class="top-link"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="social-links d-flex align-items-center gap-2">
                    <a href="<?php echo !empty($site_settings['facebook_url']) ? htmlspecialchars($site_settings['facebook_url']) : '#'; ?>" class="social" aria-label="Facebook" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?php echo !empty($site_settings['instagram_url']) ? htmlspecialchars($site_settings['instagram_url']) : '#'; ?>" class="social" aria-label="Instagram" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                    <a href="<?php echo !empty($site_settings['telegram_url']) ? htmlspecialchars($site_settings['telegram_url']) : '#'; ?>" class="social" aria-label="Telegram" target="_blank" rel="noopener"><i class="fab fa-telegram-plane"></i></a>
                    <a href="<?php echo !empty($site_settings['twitter_url']) ? htmlspecialchars($site_settings['twitter_url']) : '#'; ?>" class="social" aria-label="Twitter" target="_blank" rel="noopener"><i class="fab fa-twitter"></i></a>
                    <a href="<?php echo !empty($site_settings['youtube_url']) ? htmlspecialchars($site_settings['youtube_url']) : '#'; ?>" class="social" aria-label="YouTube" target="_blank" rel="noopener"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>

        <!-- Branding Row -->
        <div class="brandbar py-3 bg-white">
            <div class="container container-narrow d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
                <a class="brand d-flex align-items-center text-decoration-none" href="<?php echo BASE_URL; ?>">
<?php $logo_img = trim($site_settings['logo_image_url'] ?? ''); $logo_text = trim($site_settings['logo_text'] ?? ''); ?>
<?php if (!empty($logo_img)): ?>
                    <img src="<?php echo htmlspecialchars($logo_img); ?>" alt="<?php echo htmlspecialchars(!empty($logo_text) ? $logo_text : SITE_NAME); ?>" class="me-2 site-logo" loading="eager"/>
<?php if ($logo_text !== ''): ?>
                    <span class="fs-4 fw-bold text-dark"><?php echo htmlspecialchars($logo_text); ?></span>
<?php endif; ?>
<?php else: ?>
                    <i class='bx bx-code-block me-2' style="font-size: 2rem; color: var(--primary);"></i>
                    <span class="fs-4 fw-bold text-dark"><?php echo !empty($logo_text) ? htmlspecialchars($logo_text) : SITE_NAME; ?></span>
<?php endif; ?>
                </a>
<?php
    // Decide header ad content first, then render container only if non-empty
    $ad_out = '';
    $header_ad_html = trim($site_settings['header_ad_html'] ?? '');
    if ($header_ad_html !== '') {
        $decoded = $header_ad_html; $prev=''; $attempts=0;
        while ($decoded !== $prev && $attempts < 5) { $prev=$decoded; $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'); $attempts++; }
        $ad_out = $decoded;
    } else {
        $header_widgets_for_bar = getWidgets('header');
        if (!empty($header_widgets_for_bar)) {
            $ad_out = displayWidgets('header');
        }
    }
    if ($ad_out !== ''):
?>
                <div class="ad-slot text-muted d-flex align-items-center justify-content-center mt-2 mt-md-0" style="min-height: 90px; max-width: 100%; overflow: hidden;">
                    <div class="header-ad-container" id="header-ad-lazy" style="max-width: 100%; text-align: center; opacity: 0; transition: opacity 0.3s ease;" data-ad-content="<?php echo htmlspecialchars($ad_out, ENT_QUOTES, 'UTF-8'); ?>">
                        <!-- Ad loads here -->
                    </div>
                </div>
                <script>
                    // Lazy load header ad after page loads to prevent blocking
                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(function() {
                            loadHeaderAd();
                        });
                    } else {
                        setTimeout(function() {
                            loadHeaderAd();
                        }, 100);
                    }
                    
                    function loadHeaderAd() {
                        var container = document.getElementById('header-ad-lazy');
                        if (container) {
                            var adContent = container.getAttribute('data-ad-content');
                            if (adContent) {
                                var temp = document.createElement('textarea');
                                temp.innerHTML = adContent;
                                container.innerHTML = temp.value;
                                container.style.opacity = '1';
                            }
                        }
                    }
                </script>
<?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light navstrip">
            <div class="container container-narrow">
                <!-- Mobile Menu Dropdown -->
                <div class="mobile-menu-container">
                    <button class="mobile-menu-toggle" type="button" onclick="toggleMobileMenu()">
                        <?php 
                        if (isset($current_page) && $current_page == 'category' && isset($page_title)) {
                            echo htmlspecialchars($page_title);
                        } else {
                            echo 'Homepage';
                        }
                        ?>
                    </button>
                    <div class="mobile-menu" id="mobileMenu">
                        <a href="<?php echo BASE_URL; ?>" class="nav-link <?php echo (!isset($current_page) || $current_page == 'home') ? 'active' : ''; ?>">Homepage</a>
                        <?php if (!empty($header_categories)):
                            foreach ($header_categories as $cat):
                                if (isset($cat['display_type']) && $cat['display_type'] === 'top_link') {
                                    $is_active = (isset($current_category_slug) && $current_category_slug == $cat['slug']) ? 'active' : '';
                                ?>
                        <a href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>" class="nav-link <?php echo $is_active; ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
                        <?php } endforeach; endif; ?>
                        <?php if (!empty($secondary_categories)): ?>
                        <?php foreach ($secondary_categories as $cat): ?>
                        <a href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>" class="nav-link"><?php echo htmlspecialchars($cat['name']); ?></a>
                        <?php endforeach; endif; ?>
                        <a href="<?php echo BASE_URL; ?>/about.php" class="nav-link">About</a>
                        <a href="<?php echo BASE_URL; ?>/contact.php" class="nav-link">Contact</a>
                    </div>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo (!isset($current_page) || $current_page == 'home') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>">Home</a>
                        </li>
                        <?php if (!empty($header_categories)):
                            foreach ($header_categories as $cat):
                                if (isset($cat['display_type']) && $cat['display_type'] === 'top_link') {
                                    $is_active = (isset($current_category_slug) && $current_category_slug == $cat['slug']) ? 'active' : '';
                                ?>
                        <li class="nav-item"><a class="nav-link <?php echo $is_active; ?>" href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                        <?php } endforeach; endif; ?>
                        <?php if (!empty($secondary_categories)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="secondaryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?php echo htmlspecialchars($secondary_dropdown_name); ?></a>
                            <ul class="dropdown-menu" aria-labelledby="secondaryDropdown">
                                <?php foreach ($secondary_categories as $cat):
                                    $is_active = (isset($current_category_slug) && $current_category_slug == $cat['slug']) ? 'active' : '';
                                ?>
                                    <li><a class="dropdown-item <?php echo $is_active; ?>" href="<?php echo BASE_URL; ?>/category.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <form class="d-flex ms-auto" action="<?php echo BASE_URL; ?>/search.php" method="get">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" name="q" placeholder="Search..." aria-label="Search">
                            <button class="btn btn-primary" type="submit"><i class='bx bx-search'></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </nav>

        <!-- Top News Ticker -->
        <?php $top_news = function_exists('getLatestPosts') ? getLatestPosts(6) : []; ?>
        <?php if (!empty($top_news)): ?>
        <div class="news-ticker">
            <div class="container container-narrow d-flex align-items-center">
                <span class="label">Top News</span>
                <div class="items">
                    <div class="track">
                        <?php foreach ($top_news as $n): ?>
                            <a class="item" href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($n['slug']); ?>"><?php echo htmlspecialchars($n['title']); ?></a>
                        <?php endforeach; ?>
                        <?php foreach ($top_news as $n): // duplicate for seamless loop ?>
                            <a class="item" href="<?php echo BASE_URL; ?>/post.php?slug=<?php echo urlencode($n['slug']); ?>"><?php echo htmlspecialchars($n['title']); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </header>
    
    <main class="site-main py-4">