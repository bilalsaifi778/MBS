<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <h5 class="text-white">Admin Panel</h5>
            <p class="text-white-50"><?php echo $_SESSION['admin_username']; ?></p>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'posts.php' || basename($_SERVER['PHP_SELF']) == 'add-post.php' || basename($_SERVER['PHP_SELF']) == 'edit-post.php' ? 'active' : ''; ?>" href="posts.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Posts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' || basename($_SERVER['PHP_SELF']) == 'add-category.php' || basename($_SERVER['PHP_SELF']) == 'edit-category.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-folder me-2"></i>
                    Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'comments.php' || basename($_SERVER['PHP_SELF']) == 'edit-comment.php' ? 'active' : ''; ?>" href="comments.php">
                    <i class="fas fa-comments me-2"></i>
                    Comments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'widgets.php' || basename($_SERVER['PHP_SELF']) == 'add-widget.php' || basename($_SERVER['PHP_SELF']) == 'edit-widget.php' ? 'active' : ''; ?>" href="widgets.php">
                    <i class="fas fa-th-large me-2"></i>
                    Widgets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>" href="statistics.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</div>