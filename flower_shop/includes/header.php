<?php
require_once __DIR__ . '/config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$assetBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($assetBasePath === '.') {
    $assetBasePath = '';
}
$storeSettings = app_settings($pdo);
$storeName = $storeSettings['store_name'] ?? 'Bloom Flower Shop';
$pageTitles = [
    'index.php' => 'Fresh flowers, thoughtfully chosen',
    'shop.php' => 'Shop flowers',
    'cart.php' => 'Your cart',
    'checkout.php' => 'Review your order',
    'order_success.php' => 'Order received',
    'profile.php' => 'Your profile',
];
$pageTitle = $pageTitles[$currentPage] ?? $storeName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="description" content="<?= htmlspecialchars($storeSettings['store_tagline'] ?? 'Shop fresh flowers and thoughtful bouquets.', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBasePath . '/css/style.css', ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body class="storefront">
    <header class="store-header">
        <nav class="store-nav" aria-label="Main navigation">
            <a class="store-brand" href="index.php" aria-label="<?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?> home">
                <img src="images/logo.png" alt="<?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?>">
            </a>
            <div class="store-nav-links">
                <a href="index.php"<?= $currentPage === 'index.php' ? ' aria-current="page"' : '' ?>>Home</a>
                <a href="shop.php"<?= $currentPage === 'shop.php' ? ' aria-current="page"' : '' ?>>Shop flowers</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php"<?= $currentPage === 'profile.php' ? ' aria-current="page"' : '' ?>>Profile</a>
                    <a href="orders.php"<?= $currentPage === 'orders.php' ? ' aria-current="page"' : '' ?>>My orders</a>
                    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                        <a href="admin/dashboard.php">Admin</a>
                    <?php endif; ?>
                    <a href="logout.php">Log out</a>
                <?php else: ?>
                    <a href="login.php">Sign in</a>
                <?php endif; ?>
                <a class="store-cart-link" href="cart.php" aria-label="Shopping cart, <?= (int)cart_count() ?> items">
                    Cart <span><?= (int)cart_count() ?></span>
                </a>
            </div>
        </nav>
    </header>

    <main class="store-shell">
