<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temporarily Unavailable - Your IT Expert Blog</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
            color: #5a5c69;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .error-container {
            max-width: 600px;
            text-align: center;
            background-color: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .error-icon {
            font-size: 60px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        h1 {
            color: var(--dark-color);
            margin-bottom: 15px;
        }
        
        p {
            color: var(--secondary-color);
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            font-weight: 500;
            color: white;
            background-color: var(--primary-color);
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2e59d9;
        }
        
        .contact-info {
            margin-top: 30px;
            font-size: 14px;
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class='bx bx-server'></i>
        </div>
        <h1>Website Temporarily Unavailable</h1>
        <p>We're experiencing some technical difficulties with our database connection. Our team has been notified and is working to resolve the issue as quickly as possible.</p>
        <p>Please try again in a few minutes. We apologize for any inconvenience.</p>
        <a href="javascript:location.reload()" class="btn">Try Again</a>
        <div class="contact-info">
            <p>If the problem persists, please contact the administrator at <?php echo ADMIN_EMAIL; ?></p>
        </div>
    </div>
</body>
</html>