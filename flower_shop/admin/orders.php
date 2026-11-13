<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/config.php';

// Update order status
if (isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload orders and try again.');
    }
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $allowedStatuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
    if ($order_id === false || $order_id < 1 || !in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        exit('Invalid order status update.');
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE id = ?');
    $exists->execute([$order_id]);
    if ((int)$exists->fetchColumn() === 0) {
        header('Location: orders.php?msg=not_found');
        exit;
    }
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    header('Location: orders.php?msg=updated');
    exit;
}

// Fetch all orders with customer and product info
$orders = $pdo->query("SELECT o.*, u.name AS customer_name, p.name AS product_name, p.image 
                       FROM orders o 
                       JOIN users u ON o.user_id = u.id 
                       JOIN products p ON o.product_id = p.id 
                       ORDER BY o.order_date DESC, o.id DESC")->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders | Bloom Admin</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= (int)filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body class="admin-workspace">
    <div class="admin-wrap">
        <?php $adminActive = 'orders'; require '../includes/admin_sidebar.php'; ?>
        <main class="admin-main">
            <header class="admin-top">
                <div>
                    <p class="eyebrow">Admin workspace</p>
                    <h1>Orders</h1>
                    <p>Review purchases and update their fulfillment status.</p>
                </div>
            </header>

            <?php if ($msg === 'updated'): ?>
                <div class="admin-notice admin-notice-success" role="status">Order status updated.</div>
            <?php elseif ($msg === 'not_found'): ?>
                <div class="account-alert account-alert-error" role="alert">That order could not be found.</div>
            <?php endif; ?>

            <section class="admin-panel" aria-label="All customer orders">
                <div class="admin-panel-heading">
                    <div>
                        <h2>All orders</h2>
                        <p><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> in the store</p>
                    </div>
                </div>
                <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td class="admin-empty" colspan="8">No orders placed yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                            <?php $orderStatus = normalize_order_status($o['status'] ?? null); ?>
                            <tr>
                                <td><strong>#<?= (int)$o['id'] ?></strong></td>
                                <td><?= htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="order-product-cell">
                                    <div class="order-product-name">
                                        <img src="../<?= htmlspecialchars(local_product_image($o), ENT_QUOTES, 'UTF-8') ?>" alt="">
                                        <span><?= htmlspecialchars($o['product_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php $customizationSummary = customization_summary($o['customization_details'] ?? null); ?>
                                    <?php if ($customizationSummary !== ''): ?><span class="order-product-meta"><?= htmlspecialchars($customizationSummary, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                    <?php if (!empty($o['bouquet_message'])): ?><span class="order-product-note"><strong>Message:</strong> <?= nl2br(htmlspecialchars($o['bouquet_message'], ENT_QUOTES, 'UTF-8')) ?></span><?php endif; ?>
                                    <?php if (!empty($o['delivery_notes'])): ?><span class="order-product-note"><strong>Delivery:</strong> <?= nl2br(htmlspecialchars($o['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?></span><?php endif; ?>
                                </td>
                                <td><?= (int)$o['quantity'] ?></td>
                                <td><strong>₱<?= number_format((float)$o['total_price'], 2) ?></strong></td>
                                <td><?= htmlspecialchars(date('M d, Y', strtotime($o['order_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="admin-status-badge <?= htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(strtoupper($orderStatus), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="admin-status-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                        <label class="sr-only" for="order-status-<?= (int)$o['id'] ?>">Status for order #<?= (int)$o['id'] ?></label>
                                        <select class="admin-select" id="order-status-<?= (int)$o['id'] ?>" name="status">
                                            <option value="pending" <?= $orderStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="paid" <?= $orderStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                                            <option value="shipped" <?= $orderStatus === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered" <?= $orderStatus === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $orderStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="admin-button admin-button-primary">Save</button>
                                    </form>
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