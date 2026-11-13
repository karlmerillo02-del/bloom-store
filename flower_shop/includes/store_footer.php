<?php
$footerSettings = isset($storeSettings) && is_array($storeSettings) ? $storeSettings : app_settings($pdo);
$footerStoreName = $storeName ?? ($footerSettings['store_name'] ?? 'Bloom Flower Shop');
?>
<footer class="store-footer">
    <div class="store-footer-inner">
        <div>
            <a class="store-footer-brand" href="index.php"><?= htmlspecialchars($footerStoreName, ENT_QUOTES, 'UTF-8') ?></a>
            <?php if (!empty($footerSettings['store_tagline'])): ?>
                <p class="store-footer-tagline"><?= htmlspecialchars($footerSettings['store_tagline'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <nav class="store-footer-links" aria-label="Footer navigation">
            <a href="index.php">Home</a>
            <a href="shop.php">Shop</a>
            <a href="cart.php">Cart</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php">Profile</a>
                <a href="orders.php">My orders</a>
            <?php else: ?>
                <a href="login.php">Sign in</a>
            <?php endif; ?>
            <?php if (!empty($footerSettings['contact_email'])): ?>
                <a href="mailto:<?= htmlspecialchars($footerSettings['contact_email'], ENT_QUOTES, 'UTF-8') ?>">Contact</a>
            <?php endif; ?>
        </nav>
        <p class="store-footer-copy">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($footerStoreName, ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($footerSettings['store_location'])): ?> · <?= htmlspecialchars($footerSettings['store_location'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
            <?php if (!empty($footerSettings['contact_phone'])): ?> · <?= htmlspecialchars($footerSettings['contact_phone'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </p>
    </div>
</footer>
