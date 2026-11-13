<?php
require_once 'includes/config.php';
if (!is_logged_in()) {
    redirect('login.php');
}

$statement = $pdo->prepare(
    'SELECT o.id, o.quantity, o.total_price, o.status, o.order_date,
            o.customization_details, o.bouquet_message, o.delivery_notes, p.name AS product_name
     FROM orders o
     INNER JOIN products p ON p.id = o.product_id
     WHERE o.user_id = ?
     ORDER BY o.order_date DESC, o.id DESC'
);
$statement->execute([(int)$_SESSION['user_id']]);
$orders = $statement->fetchAll();

require_once 'includes/header.php';
?>

<section class="account-page">
    <div class="store-page-heading">
        <p class="store-eyebrow">Your Bloom account</p>
        <h1>My orders</h1>
        <p>Keep up with the flowers you’ve sent and the status of each order.</p>
    </div>
    <?php if (empty($orders)): ?>
        <div class="store-empty-state">
            <p>You haven’t placed an order yet.</p>
            <a class="store-button store-button-gold" href="shop.php">Browse flowers</a>
        </div>
    <?php else: ?>
        <div class="store-table-wrap">
            <table class="store-table">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Flower</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Total</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php $orderStatus = normalize_order_status($order['status'] ?? null); ?>
                        <tr>
                            <td>#<?= (int)$order['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($order['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php $customizationSummary = customization_summary($order['customization_details']); ?>
                                <?php if ($customizationSummary !== ''): ?><br><small><?= htmlspecialchars($customizationSummary, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                <?php if (!empty($order['bouquet_message'])): ?><br><small>Message: <?= nl2br(htmlspecialchars($order['bouquet_message'], ENT_QUOTES, 'UTF-8')) ?></small><?php endif; ?>
                                <?php if (!empty($order['delivery_notes'])): ?><br><small>Delivery notes: <?= nl2br(htmlspecialchars($order['delivery_notes'], ENT_QUOTES, 'UTF-8')) ?></small><?php endif; ?>
                            </td>
                            <td><?= (int)$order['quantity'] ?></td>
                            <td>₱<?= number_format((float)$order['total_price'], 2) ?></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($order['order_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="order-status order-status-<?= htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($orderStatus), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
