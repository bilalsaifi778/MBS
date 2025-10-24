</main>

    <?php 
    // Render any widgets specifically assigned to the "Above Footer" location
    if (function_exists('displayWidgets')) {
        $pre_widgets = getWidgets('pre_footer');
        if (!empty($pre_widgets)) {
            echo '<div class="container my-3"><div class="pre-footer-widgets">' . displayWidgets('pre_footer') . '</div></div>'; 
        }
    }

    // Render the single HTML ad set via Layout > Above Footer Ad
    $pre_footer_ad = function_exists('getSetting') ? getSetting('pre_footer_ad_html', '') : ''; 

    // Aggressive decode in case the HTML was stored entity-encoded
    if (!empty($pre_footer_ad)) {
        $decoded = $pre_footer_ad;
        $prev = '';
        $attempts = 0;
        while ($decoded !== $prev && $attempts < 5) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $attempts++;
        }
        $pre_footer_ad = $decoded;
    }
    ?>
    <?php if (!empty($pre_footer_ad)): ?>
    <div class="container my-3">
        <div class="pre-footer-ad">
            <?php echo $pre_footer_ad; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <footer class="site-footer bg-white py-4 border-top">
        <div class="container">
            <!-- Footer Widgets -->
            <?php
            $footer_widgets = getWidgets('footer');
            if (!empty($footer_widgets)):
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <?php echo displayWidgets('footer'); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-12 text-center">
                    <?php 
                        $footer_credit = function_exists('getSetting') ? getSetting('footer_credit_html', '') : '';
                        if (!empty($footer_credit)) {
                            // Aggressively decode in case it was stored as entities
                            $decoded = $footer_credit; $prev = ''; $attempts = 0;
                            while ($decoded !== $prev && $attempts < 5) { $prev = $decoded; $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'); $attempts++; }
                            echo $decoded;
                        } else {
                            echo '<p class="mb-0">&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>';
                        }
                    ?>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS with async loading -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" async></script>
    
    <!-- AOS Animation JS with async loading -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js" async></script>
    
    <!-- Critical CSS-only functionality - No JavaScript required -->
    
    <!-- Mobile Menu JavaScript - Optimized for Performance -->
    <script>
    // Critical mobile menu functionality inlined to avoid external request
    // Debounce function to limit how often a function can be called
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Optimized mobile menu toggle
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        const toggle = document.querySelector('.mobile-menu-toggle');
        
        if (!menu || !toggle) return;
        
        // Use classList.toggle for better performance
        menu.classList.toggle('show');
        toggle.classList.toggle('open');
    }
    
    // Close mobile menu when clicking outside - optimized version
    function closeMobileMenuOnClickOutside(event) {
        const menu = document.getElementById('mobileMenu');
        const toggle = document.querySelector('.mobile-menu-toggle');
        
        if (!toggle || !menu) return;
        
        // Check if click is outside menu and toggle
        if (!toggle.contains(event.target) && !menu.contains(event.target)) {
            menu.classList.remove('show');
            toggle.classList.remove('open');
        }
    }
    
    // Update mobile menu button text when selecting an option - optimized version
    function handleMobileMenuLinkClick(event) {
        event.preventDefault();
        const newText = this.textContent.trim();
        const toggle = document.querySelector('.mobile-menu-toggle');
        const menu = document.getElementById('mobileMenu');
        
        if (toggle && menu) {
            toggle.textContent = newText;
            menu.classList.remove('show');
            toggle.classList.remove('open');
            
            // Navigate to the link after a short delay
            const href = this.href;
            setTimeout(() => {
                window.location.href = href;
            }, 150);
        }
    }
    
    // Initialize mobile menu functionality
    function initMobileMenu() {
        const menuLinks = document.querySelectorAll('.mobile-menu .nav-link');
        const toggle = document.querySelector('.mobile-menu-toggle');
        
        if (toggle) {
            toggle.addEventListener('click', toggleMobileMenu);
        }
        
        // Add event listeners to menu links
        menuLinks.forEach(link => {
            link.addEventListener('click', handleMobileMenuLinkClick);
        });
        
        // Close menu when clicking outside - debounced for performance
        document.addEventListener('click', debounce(closeMobileMenuOnClickOutside, 150));
    }
    
    // Initialize when DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMenu);
    } else {
        initMobileMenu();
    }
    </script>
    
    <!-- Responsive ad scaler with async loading -->
    <script src="<?php echo BASE_URL; ?>/assets/js/responsive-ads.js" async></script>

    <!-- Initialize AOS - Optimized -->
    <script>
        // Inline AOS initialization to avoid external request
        function initAOS() {
            if (typeof AOS !== 'undefined') {
                AOS.init({
                    duration: 800,
                    easing: 'ease-in-out',
                    once: true,
                    offset: 100,
                    delay: 100
                });
            }
        }
        
        // Initialize AOS when library is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                // Check if AOS is available, if not, wait a bit more
                if (typeof AOS !== 'undefined') {
                    initAOS();
                } else {
                    setTimeout(initAOS, 500);
                }
            });
        } else {
            if (typeof AOS !== 'undefined') {
                initAOS();
            } else {
                setTimeout(initAOS, 500);
            }
        }
    </script>
    
    <!-- Smooth Scroll - Optimized -->
    <script>
        // Inline smooth scroll functionality
        function initSmoothScroll() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const target = document.querySelector(this.getAttribute('href'));
                    if (!target) return;
                    e.preventDefault();
                    target.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            });
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSmoothScroll);
        } else {
            initSmoothScroll();
        }
    </script>

    <!-- Back to Top button - Optimized -->
    <div id="backToTop">
        <button class="btn btn-primary"><i class='bx bx-up-arrow-alt'></i></button>
    </div>
    <script>
        // Inline back to top functionality
        (function(){
            const btnWrap = document.getElementById('backToTop');
            const btn = btnWrap.querySelector('button');
            
            // Throttle function to limit how often a function can be called
            function throttle(func, limit) {
                let inThrottle;
                return function() {
                    const args = arguments;
                    const context = this;
                    if (!inThrottle) {
                        func.apply(context, args);
                        inThrottle = true;
                        setTimeout(() => inThrottle = false, limit);
                    }
                }
            }
            
            const onScroll = throttle(() => {
                if (window.scrollY > 300) {
                    btnWrap.classList.add('show');
                } else {
                    btnWrap.classList.remove('show');
                }
            }, 100); // Limit to once every 100ms
            
            window.addEventListener('scroll', onScroll, { passive: true });
            
            btn.addEventListener('click', () => {
                window.scrollTo({ 
                    top: 0, 
                    behavior: 'smooth' 
                });
            });
            
            // Initial check
            onScroll();
        })();
    </script>
    
    <!-- Custom Scripts - Footer -->
    <?php
    $page = isset($current_page) ? $current_page : '';
    $footer_scripts = getCustomScripts('footer', $page);
    foreach ($footer_scripts as $script) {
        echo $script['content'];
    }
    ?>
    
    <!-- Service Worker Registration for Caching -->
    <script>
    // Inline service worker registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?php echo BASE_URL; ?>/sw.js')
                .then(function(registration) {
                    console.log('SW registered: ', registration);
                })
                .catch(function(registrationError) {
                    console.log('SW registration failed: ', registrationError);
                });
        });
    }
    </script>
</body>
</html>