<?php
$adminActive = $adminActive ?? '';
$adminLinks = [
    'dashboard' => ['href' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'D'],
    'products' => ['href' => 'products.php', 'label' => 'Products', 'icon' => 'P'],
    'orders' => ['href' => 'orders.php', 'label' => 'Orders', 'icon' => 'O'],
    'settings' => ['href' => 'settings.php', 'label' => 'Settings', 'icon' => 'S'],
];
?>
<aside class="admin-sidebar">
    <a class="brand" href="dashboard.php" aria-label="Bloom admin home">
        <span class="brand-mark" aria-hidden="true">B</span>
        <span>Bloom<span>.</span></span>
    </a>
    <p class="nav-label">Workspace</p>
    <nav class="nav" aria-label="Admin navigation">
        <?php foreach ($adminLinks as $key => $link): ?>
            <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"
               <?= $adminActive === $key ? 'class="active" aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true"><?= htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8') ?></span>
                <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="../index.php" target="_blank" rel="noopener">View storefront</a>
        <a href="../logout.php">Log out</a>
    </div>
</aside>
