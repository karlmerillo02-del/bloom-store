<?php
session_start();
require_once 'includes/config.php';

if (empty($_SESSION['order_placed'])) {
    header('Location: index.php');
    exit;
}

$orderTotal = (float)($_SESSION['order_total'] ?? 0);
$orderCount = (int)($_SESSION['order_count'] ?? 0);
unset($_SESSION['order_placed'], $_SESSION['order_total'], $_SESSION['order_count']);

require_once 'includes/header.php';
?>

<section class="store-success-card" aria-labelledby="order-success-title">
    <span class="store-success-mark" aria-hidden="true">✓</span>
    <p class="store-eyebrow">Order received</p>
    <h1 id="order-success-title">Thank you for choosing Bloom.</h1>
    <p class="store-success-copy">Your order is in. We’re preparing your flowers with care.</p>
    <div class="store-success-summary">
        <div><span>Flower varieties</span><strong><?= $orderCount ?></strong></div>
        <div><span>Order total</span><strong>₱<?= number_format($orderTotal, 2) ?></strong></div>
    </div>
    <a class="store-button store-button-gold" href="shop.php">Continue shopping <span aria-hidden="true">&rarr;</span></a>
</section>

<?php require_once 'includes/footer.php'; ?>
