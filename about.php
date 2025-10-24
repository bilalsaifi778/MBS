<?php
require_once 'config.php';
require_once 'includes/functions.php';

$page_title = 'About Us';
$current_page = 'about';

include 'includes/header.php';
?>

<div class="container container-narrow mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8" data-aos="fade-up">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-2 d-flex align-items-center gap-2"><i class='bx bx-info-circle text-primary'></i> About Us</h1>
                    <p class="mb-0">Welcome to <?php echo SITE_NAME; ?>. This page is under construction. Check back soon for more details.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


