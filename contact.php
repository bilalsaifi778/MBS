<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Initialize variables
$name = $email = $subject = $message = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission.';
    } else {
        // Validate inputs
        $name = clean($_POST['name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $subject = clean($_POST['subject'] ?? '');
        $message = clean($_POST['message'] ?? '');
        
        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (empty($subject)) {
            $errors[] = 'Subject is required.';
        }
        
        if (empty($message)) {
            $errors[] = 'Message is required.';
        }
        
        // If no errors, process the form
        if (empty($errors)) {
            // In a real-world scenario, you would send an email here
            // For InfinityFree hosting, you might use their mail function or a third-party service
            
            // For demonstration purposes, we'll just mark it as successful
            $success = true;
            
            // Clear form fields after successful submission
            $name = $email = $subject = $message = '';
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Page title
$page_title = 'Contact Us';

// Track page view for statistics
trackPageView('contact');

// Include header
include 'includes/header.php';
?>

<div class="container container-narrow mt-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <section class="post-hero mb-4" data-aos="fade-up">
                <h1 class="h3 mb-0 d-flex align-items-center gap-2"><i class='bx bx-envelope text-primary'></i> Contact Us</h1>
            </section>
            
            <?php if ($success): ?>
                <div class="alert alert-success" data-aos="fade-up">
                    <p>Thank you for your message! We will get back to you as soon as possible.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" data-aos="fade-up">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4 shadow-sm" data-aos="fade-up">
                <div class="card-body">
                    <form method="post" action="<?php echo SITE_URL; ?>/contact.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($subject); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4 shadow-sm" data-aos="fade-up">
                <div class="card-body">
                    <h2 class="card-title h4">Other Ways to Connect</h2>
                    <div class="row mt-4">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-envelope fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5>Email</h5>
                                    <p class="mb-0">contact@yourblog.com</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-phone fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5>Phone</h5>
                                    <p class="mb-0">+1 (555) 123-4567</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5>Address</h5>
                                    <p class="mb-0">123 Blog Street, Web City, 12345</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5>Business Hours</h5>
                                    <p class="mb-0">Monday - Friday: 9AM - 5PM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <?php include 'includes/sidebar.php'; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>