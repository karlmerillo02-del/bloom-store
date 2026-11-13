<?php
require_once 'includes/header.php';

$allProducts = $pdo->query(
    "SELECT id, name, description, price, stock, category, image,
            is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at
     FROM products
     ORDER BY id DESC"
)->fetchAll();

$categories = flower_categories();
$categoryCounts = array_fill_keys($categories, 0);
foreach ($allProducts as $product) {
    $categoryCounts[flower_category($product)]++;
}

$requestedCategory = trim($_GET['category'] ?? '');
$categoryFilter = in_array($requestedCategory, $categories, true) ? $requestedCategory : '';
$products = $categoryFilter === ''
    ? $allProducts
    : array_values(array_filter($allProducts, function ($product) use ($categoryFilter) {
        return flower_category($product) === $categoryFilter;
    }));
?>

<section class="shop-intro">
    <p class="store-eyebrow">The Bloom collection</p>
    <h1>Find your kind of flowers.</h1>
    <p>Beautiful blooms for thoughtful gifts, celebrations, and just-because moments.</p>
</section>

<section class="shop-catalog" aria-labelledby="shop-heading">
    <div class="store-section-heading shop-heading">
        <div>
            <p class="store-eyebrow">Browse the collection</p>
            <h2 id="shop-heading"><?= $categoryFilter === '' ? 'All flowers' : htmlspecialchars($categoryFilter, ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <p class="shop-result-count"><?= count($products) ?> <?= count($products) === 1 ? 'flower' : 'flowers' ?></p>
    </div>

    <nav class="shop-category-list" id="categories" aria-label="Filter flowers by category">
        <a class="shop-category-chip<?= $categoryFilter === '' ? ' is-active' : '' ?>"
           href="shop.php"<?= $categoryFilter === '' ? ' aria-current="page"' : '' ?>>All flowers</a>
        <?php foreach ($categories as $category): ?>
            <?php if ($categoryCounts[$category] === 0) { continue; } ?>
            <a class="shop-category-chip<?= $categoryFilter === $category ? ' is-active' : '' ?>"
               href="shop.php?category=<?= urlencode($category) ?>"<?= $categoryFilter === $category ? ' aria-current="page"' : '' ?>>
                <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
                <span><?= (int)$categoryCounts[$category] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($products)): ?>
        <div class="store-empty-state">
            <p>No flowers in this category just yet.</p>
            <a class="store-button store-button-gold" href="shop.php">Browse all flowers</a>
        </div>
    <?php else: ?>
        <div class="store-product-grid shop-product-grid">
            <?php foreach ($products as $product): ?>
                <article class="store-product-card">
                    <div class="store-product-image">
                        <?php if (is_active_sale($product)): ?>
                            <span class="store-sale-badge">Flash sale</span>
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars(local_product_image($product), ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    </div>
                    <div class="store-product-info">
                        <span class="store-product-category"><?= htmlspecialchars(flower_category($product), ENT_QUOTES, 'UTF-8') ?><?= !empty($product['is_ready_bouquet']) ? ' · Ready bouquet' : '' ?></span>
                        <h3><?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?></h3>
                        <?php if (trim((string)$product['description']) !== ''): ?>
                            <p class="store-product-description"><?= htmlspecialchars(trim($product['description']), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <div class="store-product-bottom">
                            <div>
                                <span class="store-product-price">₱<?= number_format(product_current_price($product), 2) ?></span>
                                <?php if (is_active_sale($product)): ?>
                                    <del class="store-original-price">₱<?= number_format((float)$product['price'], 2) ?></del>
                                <?php endif; ?>
                                <span class="store-stock<?= (int)$product['stock'] > 0 ? '' : ' is-unavailable' ?>">
                                    <?= (int)$product['stock'] > 0 ? 'In stock' : 'Sold out' ?>
                                </span>
                            </div>
                            <?php if ((int)$product['stock'] > 0): ?>
                                <form class="store-add-form" method="POST" action="cart.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                    <button class="store-add-button" type="submit" name="add_to_cart" value="1"
                                            aria-label="Add <?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?> to cart">+</button>
                                </form>
                            <?php else: ?>
                                <span class="store-sold-out">Sold out</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
