<?php
session_start();
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload products and try again.');
    }

    $productId = filter_var($_POST['delete_product'], FILTER_VALIDATE_INT);
    if ($productId === false || $productId < 1) {
        http_response_code(400);
        exit('Invalid product ID.');
    }

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
        $lock->execute([$productId]);
        if ($lock->fetchColumn() === false) {
            $pdo->commit();
            header('Location: products.php?msg=not_found');
            exit;
        }

        $ordersForProduct = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE product_id = ?');
        $ordersForProduct->execute([$productId]);
        if ((int)$ordersForProduct->fetchColumn() > 0) {
            $pdo->commit();
            header('Location: products.php?msg=in_use');
            exit;
        }

        $delete = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $delete->execute([$productId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    header('Location: products.php?msg=deleted');
    exit;
}

$products = $pdo->query(
    'SELECT p.*,
            EXISTS(SELECT 1 FROM orders o WHERE o.product_id = p.id) AS has_orders
     FROM products p
     ORDER BY p.id DESC'
)->fetchAll();
$message = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products | Bloom Admin</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= (int)filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body class="admin-workspace">
    <div class="admin-wrap">
        <?php $adminActive = 'products'; require '../includes/admin_sidebar.php'; ?>
        <main class="admin-main">
            <header class="admin-top">
                <div>
                    <p class="eyebrow">Admin workspace</p>
                    <h1>Products</h1>
                    <p>Manage your flower catalog, pricing, and inventory.</p>
                </div>
                <div class="admin-top-actions">
                    <a class="admin-button admin-button-primary" href="add_product.php">Add product</a>
                </div>
            </header>

            <?php if ($message === 'deleted'): ?>
                <div class="admin-notice admin-notice-success" role="status">Product deleted.</div>
            <?php elseif ($message === 'added'): ?>
                <div class="admin-notice admin-notice-success" role="status">Product added.</div>
            <?php elseif ($message === 'updated'): ?>
                <div class="admin-notice admin-notice-success" role="status">Product updated.</div>
            <?php elseif ($message === 'in_use'): ?>
                <div class="account-alert account-alert-error" role="alert">This product has order history and cannot be deleted.</div>
            <?php elseif ($message === 'not_found'): ?>
                <div class="account-alert account-alert-error" role="alert">That product could not be found.</div>
            <?php endif; ?>

            <section class="admin-panel" aria-label="Product catalog">
                <div class="admin-panel-heading">
                    <div>
                        <h2>Catalog</h2>
                        <p><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?></p>
                    </div>
                </div>
                <div class="admin-table-container">
                    <table class="admin-table admin-products-table">
                        <thead>
                            <tr>
                                <th scope="col">Product</th>
                                <th scope="col">Category</th>
                                <th scope="col">Price</th>
                                <th scope="col">Stock</th>
                                <th scope="col">Availability</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td class="admin-empty" colspan="6">Your catalog is empty. Add a product to get started.</td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="admin-product-cell">
                                                <img src="../<?= htmlspecialchars(local_product_image($product), ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                                <div>
                                                    <strong><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <span>#<?= (int)$product['id'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars(flower_category($product), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="admin-product-price">
                                            ₱<?= number_format(product_current_price($product), 2) ?>
                                            <?php if (is_active_sale($product)): ?><del>₱<?= number_format((float)$product['price'], 2) ?></del><?php endif; ?>
                                        </td>
                                        <td><span class="<?= (int)$product['stock'] <= 5 ? 'admin-stock-low' : '' ?>"><?= (int)$product['stock'] ?></span></td>
                                        <td>
                                            <div class="admin-product-tags">
                                                <?php if ((int)$product['stock'] < 1): ?>
                                                    <span class="admin-product-tag unavailable">Out of stock</span>
                                                <?php else: ?>
                                                    <span class="admin-product-tag available">In stock</span>
                                                <?php endif; ?>
                                                <?php if (!empty($product['is_ready_bouquet'])): ?><span class="admin-product-tag">Ready bouquet</span><?php endif; ?>
                                                <?php if (is_active_sale($product)): ?><span class="admin-product-tag sale">Flash sale</span><?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <a class="admin-button admin-button-secondary" href="edit_product.php?id=<?= (int)$product['id'] ?>">Edit</a>
                                                <form method="POST" class="delete-form">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="admin-button admin-button-danger" type="submit" name="delete_product" value="<?= (int)$product['id'] ?>" <?= !empty($product['has_orders']) ? 'disabled title="Products with order history cannot be deleted."' : 'data-confirm="Delete this product? This cannot be undone."' ?>>Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script src="../javascript/script.js?v=<?= (int)filemtime(__DIR__ . '/../javascript/script.js') ?>" defer></script>
</body>
</html>
