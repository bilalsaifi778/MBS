<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Initialize variables
$search_query = '';
$posts = [];
$search_error = '';
$total_results = 0;

// Process search query
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $search_query = clean($_GET['q']);
    
    // Search in posts (title, content)
    $sql = "SELECT p.*, u.username, 
            (SELECT COUNT(*) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'published' 
            AND (p.title LIKE ? OR p.content LIKE ?)
            ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $search_term = "%{$search_query}%";
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
        $total_results = count($posts);
    } else {
        $search_error = "Error executing search query: " . $conn->error;
    }
}

// Get sidebar widgets
$sql = "SELECT * FROM widgets WHERE position = 'sidebar' AND is_active = 1 ORDER BY sort_order ASC";
$result = $conn->query($sql);
$widgets = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $widgets[] = $row;
    }
}

// Track search query
if (!empty($search_query)) {
    $sql = "INSERT INTO search_logs (query, results_count, ip_address) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("sis", $search_query, $total_results, $ip_address);
    $stmt->execute();
}

// Include header
include 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="search-container">
                <h1>Search Results</h1>
                <form action="search.php" method="GET" class="search-form mb-4">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Search for..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search_error)): ?>
                    <div class="alert alert-danger"><?php echo $search_error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($search_query)): ?>
                    <p>Found <?php echo $total_results; ?> result<?php echo $total_results != 1 ? 's' : ''; ?> for "<?php echo htmlspecialchars($search_query); ?>"</p>
                    
                    <?php if (empty($posts)): ?>
                        <div class="alert alert-info">No posts found matching your search criteria.</div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h2 class="card-title">
                                        <a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                    </h2>
                                    <p class="card-text">
                                        <?php 
                                        // Create a short excerpt from the content
                                        $excerpt = strip_tags($post['content']);
                                        $excerpt = substr($excerpt, 0, 200) . (strlen($excerpt) > 200 ? '...' : '');
                                        echo $excerpt;
                                        ?>
                                    </p>
                                    <div class="meta-info">
                                        <small class="text-muted">
                                            Posted by <?php echo htmlspecialchars($post['username']); ?> on 
                                            <?php echo date('F j, Y', strtotime($post['created_at'])); ?> | 
                                            <?php echo $post['comment_count']; ?> comment<?php echo $post['comment_count'] != 1 ? 's' : ''; ?> | 
                                            <?php echo $post['like_count']; ?> like<?php echo $post['like_count'] != 1 ? 's' : ''; ?>
                                        </small>
                                    </div>
                                    <a href="post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary mt-2">Read More</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">Enter a search term to find posts.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4">
            <?php include 'includes/sidebar.php'; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>