<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get statistics data
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Total posts
$query = "SELECT COUNT(*) as total FROM posts";
$result = $db->query($query);
$total_posts = $result->fetch_assoc()['total'];

// Total views
$query = "SELECT SUM(views) as total FROM posts";
$result = $db->query($query);
$total_views = $result->fetch_assoc()['total'] ?? 0;

// Total comments
$query = "SELECT COUNT(*) as total FROM comments";
$result = $db->query($query);
$total_comments = $result->fetch_assoc()['total'];

// Total likes
$query = "SELECT COUNT(*) as total FROM likes";
$result = $db->query($query);
$total_likes = $result->fetch_assoc()['total'];

// Total categories
$query = "SELECT COUNT(*) as total FROM categories";
$result = $db->query($query);
$total_categories = $result->fetch_assoc()['total'];

// Posts by category
$query = "SELECT c.name, COUNT(pc.post_id) as post_count 
          FROM categories c 
          LEFT JOIN post_categories pc ON c.id = pc.category_id 
          GROUP BY c.id 
          ORDER BY post_count DESC";
$result = $db->query($query);
$posts_by_category = [];
while ($row = $result->fetch_assoc()) {
    $posts_by_category[] = $row;
}

// Most viewed posts
$query = "SELECT title, views FROM posts ORDER BY views DESC LIMIT 5";
$result = $db->query($query);
$most_viewed_posts = [];
while ($row = $result->fetch_assoc()) {
    $most_viewed_posts[] = $row;
}

// Most liked posts
$query = "SELECT p.title, COUNT(l.id) as likes 
          FROM posts p 
          LEFT JOIN likes l ON p.id = l.post_id 
          GROUP BY p.id 
          ORDER BY likes DESC 
          LIMIT 5";
$result = $db->query($query);
$most_liked_posts = [];
while ($row = $result->fetch_assoc()) {
    $most_liked_posts[] = $row;
}

// Posts by month
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
          FROM posts 
          GROUP BY month 
          ORDER BY month DESC 
          LIMIT 12";
$result = $db->query($query);
$posts_by_month = [];
while ($row = $result->fetch_assoc()) {
    $posts_by_month[] = $row;
}

// Reverse the array to show oldest to newest for chart
$posts_by_month = array_reverse($posts_by_month);

// Get months and counts for chart
$months = [];
$counts = [];
foreach ($posts_by_month as $data) {
    $months[] = date('M Y', strtotime($data['month'] . '-01'));
    $counts[] = $data['count'];
}

$db->close();

// Page title
$page_title = "Statistics Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Box Icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
        }
        
        .stat-card {
            border-radius: 0.5rem;
            border-left: 0.25rem solid;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card-primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-card-success {
            border-left-color: var(--success-color);
        }
        
        .stat-card-info {
            border-left-color: var(--info-color);
        }
        
        .stat-card-warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .stat-card-primary .stat-icon {
            color: var(--primary-color);
        }
        
        .stat-card-success .stat-icon {
            color: var(--success-color);
        }
        
        .stat-card-info .stat-icon {
            color: var(--info-color);
        }
        
        .stat-card-warning .stat-icon {
            color: var(--warning-color);
        }
        
        .chart-container {
            height: 20rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class='bx bx-bar-chart-alt-2 me-2'></i>Statistics Dashboard</h1>
                </div>
                
                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card shadow h-100 py-2 stat-card stat-card-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Posts</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_posts; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class='bx bx-file stat-icon'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card shadow h-100 py-2 stat-card stat-card-success">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Views</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_views; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class='bx bx-show stat-icon'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card shadow h-100 py-2 stat-card stat-card-info">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Comments</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_comments; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class='bx bx-comment stat-icon'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card shadow h-100 py-2 stat-card stat-card-warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Likes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_likes; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class='bx bx-like stat-icon'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Posts by Month</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="postsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">Posts by Category</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Most Viewed Posts</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Views</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($most_viewed_posts as $post): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($post['title']); ?></td>
                                                <td><?php echo $post['views']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Most Liked Posts</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Likes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($most_liked_posts as $post): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($post['title']); ?></td>
                                                <td><?php echo $post['likes']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart Initialization -->
    <script>
        // Posts by Month Chart
        var ctx = document.getElementById('postsChart').getContext('2d');
        var postsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Number of Posts',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Category Chart
        var catCtx = document.getElementById('categoryChart').getContext('2d');
        var categoryChart = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($posts_by_category, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($posts_by_category, 'post_count')); ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                        '#5a5c69', '#6610f2', '#fd7e14', '#20c997', '#6f42c1'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617',
                        '#484a54', '#5d0ce1', '#e96b02', '#1aa67e', '#5d37a2'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>