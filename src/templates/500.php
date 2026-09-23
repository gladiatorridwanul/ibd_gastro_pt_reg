<?php
/**
 * 500 Internal Server Error Template
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error</title>
    <style>
        :root {
            --white: #FFFFFF;
            --ash: #F2F4F8;
            --blue: #4A90E2;
            --black: #000000;
        }
        body {
            font-family: Cambria, serif;
            background-color: var(--ash);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .error-container {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            text-align: center;
            border-left: 4px solid var(--blue);
        }
        h1 {
            color: var(--blue);
            font-size: 72px;
            margin: 0 0 20px;
        }
        h2 {
            color: var(--black);
            margin-bottom: 20px;
        }
        p {
            color: var(--black);
            opacity: 0.8;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background-color: var(--blue);
            color: var(--white);
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #357ABD;
        }
        .error-details {
            background-color: var(--ash);
            padding: 15px;
            border-radius: 6px;
            margin-top: 30px;
            font-size: 14px;
            text-align: left;
            color: var(--black);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>500</h1>
        <h2>Internal Server Error</h2>
        <p>Something went wrong on our server. We're working to fix the issue. Please try again later.</p>
        <a href="<?= BASE_URL ?? '/' ?>" class="btn">Go to Dashboard</a>
        
        <?php if (isset($config['app']['debug']) && $config['app']['debug'] && isset($e)): ?>
        <div class="error-details">
            <strong>Error:</strong> <?= htmlspecialchars($e->getMessage()) ?><br>
            <strong>File:</strong> <?= htmlspecialchars($e->getFile()) ?>:<?= $e->getLine() ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>