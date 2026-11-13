<?php
require_once 'includes/header.php';

$products = $pdo->query(
    "SELECT id, name, description, price, stock, category, image,
            is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at
     FROM products
     ORDER BY id DESC
     LIMIT 8"
)->fetchAll();
$flashSaleProducts = $pdo->query(
    "SELECT id, name, description, price, stock, category, image,
            is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at
     FROM products
     WHERE is_ready_bouquet = 1 AND sale_price > 0
       AND sale_price < price
       AND sale_starts_at <= NOW() AND sale_ends_at >= NOW()
     ORDER BY sale_ends_at ASC
     LIMIT 4"
)->fetchAll();

$categories = flower_categories();
?>

<section class="store-hero">
    <div class="store-hero-copy">
        <p class="store-eyebrow">A little joy, delivered</p>
        <h1>Flowers that say<br><span>what words can't.</span></h1>
        <p class="store-hero-text">Thoughtful blooms for birthdays, big moments, and all the ordinary days worth making special.</p>
        <a class="store-button store-button-gold" href="shop.php">Explore the collection <span aria-hidden="true">&rarr;</span></a>
        <p class="store-hero-note">Locally loved in Ilocos</p>
    </div>
    <div class="store-hero-art">
        <img src="images/home-flowers.svg" alt="" fetchpriority="high">
        <div class="store-hero-caption">A fresh way to make someone's day</div>
    </div>
</section>

<section class="store-categories" aria-labelledby="categories-title">
    <div class="store-section-heading">
        <div>
            <p class="store-eyebrow">Find your flowers</p>
            <h2 id="categories-title">Shop by occasion</h2>
        </div>
        <a class="store-text-link" href="shop.php">View all flowers <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="store-category-list">
        <?php foreach ($categories as $category): ?>
            <a class="store-category-chip" href="shop.php?category=<?= urlencode($category) ?>">
                <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!empty($flashSaleProducts)): ?>
<section class="store-featured store-flash-sale" aria-labelledby="flash-sale-title">
    <div class="store-section-heading">
        <div>
            <p class="store-eyebrow">Limited-time bouquets</p>
            <h2 id="flash-sale-title">Flash sale</h2>
        </div>
        <a class="store-text-link" href="shop.php">Shop all flowers <span aria-hidden="true">&rarr;</span></a>
    </div>
    <div class="store-product-grid">
        <?php foreach ($flashSaleProducts as $product): ?>
            <?php if (!is_active_sale($product)) { continue; } ?>
            <article class="store-product-card">
                <div class="store-product-image">
                    <span class="store-sale-badge">Flash sale</span>
                    <img src="<?= htmlspecialchars(local_product_image($product), ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                </div>
                <div class="store-product-info">
                    <span class="store-product-category"><?= htmlspecialchars(flower_category($product), ENT_QUOTES, 'UTF-8') ?> · Ready bouquet</span>
                    <h3><?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="store-product-bottom">
                        <div>
                            <span class="store-product-price">₱<?= number_format(product_current_price($product), 2) ?></span>
                            <del class="store-original-price">₱<?= number_format((float)$product['price'], 2) ?></del>
                        </div>
                        <?php if ((int)$product['stock'] > 0): ?>
                            <form class="store-add-form" method="POST" action="cart.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                <button class="store-add-button" type="submit" name="add_to_cart" value="1" aria-label="Add <?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?> to cart">+</button>
                            </form>
                        <?php else: ?>
                            <span class="store-sold-out">Sold out</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="store-featured" aria-labelledby="featured-title">
    <div class="store-section-heading">
        <div>
            <p class="store-eyebrow">Handpicked for you</p>
            <h2 id="featured-title">Flowers to fall for</h2>
        </div>
        <a class="store-text-link" href="shop.php">Shop the collection <span aria-hidden="true">&rarr;</span></a>
    </div>

    <?php if (empty($products)): ?>
        <div class="store-empty-state">
            <p>Our flower collection is being prepared. Please check back soon.</p>
        </div>
    <?php else: ?>
        <div class="store-product-grid">
            <?php foreach ($products as $product): ?>
                <article class="store-product-card">
                    <div class="store-product-image">
                        <img src="<?= htmlspecialchars(local_product_image($product), ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    </div>
                    <div class="store-product-info">
                        <span class="store-product-category"><?= htmlspecialchars(flower_category($product), ENT_QUOTES, 'UTF-8') ?><?= !empty($product['is_ready_bouquet']) ? ' · Ready bouquet' : '' ?></span>
                        <h3><?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="store-product-bottom">
                            <?php if (is_active_sale($product)): ?>
                                <span class="store-sale-badge store-sale-badge-inline">Flash sale</span>
                                <span class="store-product-price">₱<?= number_format(product_current_price($product), 2) ?></span>
                                <del class="store-original-price">₱<?= number_format((float)$product['price'], 2) ?></del>
                            <?php else: ?>
                                <span class="store-product-price">₱<?= number_format((float)$product['price'], 2) ?></span>
                            <?php endif; ?>
                            <?php if ((int)$product['stock'] > 0): ?>
                                <form class="store-add-form" method="POST" action="cart.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                                    <button class="store-add-button" type="submit" name="add_to_cart" value="1" aria-label="Add <?= htmlspecialchars(trim($product['name']), ENT_QUOTES, 'UTF-8') ?> to cart">+</button>
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

<section class="store-note">
    <div>
        <p class="store-eyebrow">Made to brighten your day</p>
        <h2>Every bouquet has a story.</h2>
        <p>Find the flowers that feel just right, then send a little happiness their way.</p>
    </div>
    <a class="store-button store-button-outline" href="shop.php">Find your bouquet</a>
</section>

<?php require_once 'includes/footer.php'; ?>
