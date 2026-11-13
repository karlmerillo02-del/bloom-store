<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/config.php';

// If cart is empty, redirect
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];
$error = '';
$rawDeliveryNotes = $_POST['delivery_notes'] ?? $_SESSION['checkout_delivery_notes'] ?? '';
$deliveryNotes = is_string($rawDeliveryNotes) ? trim($rawDeliveryNotes) : '';
$pricedCart = [];
$cartTotal = 0.0;
$productLookup = $pdo->prepare("SELECT id, name, price, stock, is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at FROM products WHERE id = ?");
foreach ($cart as $productId => $item) {
    $productLookup->execute([(int)$productId]);
    $product = $productLookup->fetch();
    if (!$product || (int)$product['stock'] < 1) {
        unset($cart[$productId]);
        continue;
    }

    $customization = normalize_cart_customization($item['customization'] ?? []);
    if ($customization === false) {
        $customization = normalize_cart_customization([]);
    }
    $maximumQuantity = intdiv((int)$product['stock'], 1 + $customization['extra_stems']);
    if ($maximumQuantity < 1) {
        unset($cart[$productId]);
        $error = 'There is not enough stock for the extra stems on one of your cart items. Update your cart and try again.';
        continue;
    }

    $item['name'] = $product['name'];
    $item['price'] = product_current_price($product);
    $item['customization'] = $customization;
    $item['quantity'] = min(max(1, (int)$item['quantity']), $maximumQuantity);
    $pricedCart[$productId] = $item;
    $cartTotal += cart_item_unit_price($product, $item) * $item['quantity'];
}
$cart = $pricedCart;
$_SESSION['cart'] = $cart;

// ---- Process Order ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload checkout and try again.');
    }
    if (utf8_length($deliveryNotes) > 1000) {
        $error = 'Delivery notes must be 1,000 characters or fewer.';
    } else {
        $_SESSION['checkout_delivery_notes'] = $deliveryNotes;
    }

    try {
        if ($error !== '') {
            throw new RuntimeException($error);
        }
        $pdo->beginTransaction();

        // Insert each item as an order record
        foreach ($cart as $product_id => $item) {
            $lock = $pdo->prepare("SELECT id, name, price, stock, is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at FROM products WHERE id = ? FOR UPDATE");
            $lock->execute([(int)$product_id]);
            $product = $lock->fetch();
            $customization = $item['customization'];
            $stockNeeded = (int)$item['quantity'] * (1 + (int)$customization['extra_stems']);
            if (!$product || (int)$product['stock'] < $stockNeeded) {
                throw new RuntimeException('Not enough stock for product: ' . $item['name']);
            }
            $item['price'] = cart_item_unit_price($product, $item);
            $customizationDetails = [
                'bouquet_wrap' => $customization['bouquet_wrap'],
                'greeting_card' => $customization['greeting_card'],
                'extra_stems' => (int)$customization['extra_stems'],
                'base_unit_price' => product_current_price($product),
                'customization_unit_price' => cart_customization_unit_price($customization),
            ];
            $customizationJson = json_encode($customizationDetails);
            if ($customizationJson === false) {
                throw new RuntimeException('Bouquet customization could not be saved.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO orders
                    (user_id, product_id, quantity, total_price, customization_details, bouquet_message, delivery_notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                $user_id,
                $product_id,
                $item['quantity'],
                $item['price'] * $item['quantity'],
                $customizationJson,
                $customization['message'] === '' ? null : $customization['message'],
                $deliveryNotes === '' ? null : $deliveryNotes,
            ]);

            $update = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $update->execute([$stockNeeded, $product_id, $stockNeeded]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Product stock changed while placing your order. Please try again.');
            }
            $cart[$product_id]['price'] = $item['price'];
        }

        $pdo->commit();

        // Clear cart
        unset($_SESSION['cart']);
        unset($_SESSION['checkout_delivery_notes']);
        
        // Store order info in session for the success page
        $_SESSION['order_placed'] = true;
        $_SESSION['order_total'] = 0;
        foreach ($cart as $item) {
            $_SESSION['order_total'] += $item['price'] * $item['quantity'];
        }
        $_SESSION['order_count'] = count($cart);

        // Redirect to success page
        header('Location: order_success.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Order failed: " . $e->getMessage();
    }
}

// Include header after processing
require_once 'includes/header.php';
?>

<section class="store-page-heading">
    <p class="store-eyebrow">One last look</p>
    <h1>Review your order</h1>
</section>

<?php if ($error): ?>
    <div class="store-checkout-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (empty($cart)): ?>
    <div class="store-empty-state">
        <p>Your bag is empty.</p>
        <a class="store-button store-button-gold" href="shop.php">Browse flowers</a>
    </div>
<?php else: ?>
    <div class="store-checkout-card">
        <div class="store-table-wrap">
            <table class="store-table">
                <thead>
                    <tr>
                        <th scope="col">Flower</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Price</th>
                        <th scope="col">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
            <?php
            $total = $cartTotal;
            foreach ($cart as $id => $item):
                $unitPrice = cart_item_unit_price($item, $item);
                $subtotal = $unitPrice * $item['quantity'];
            ?>
            <tr>
                <td>
                    <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($item['customization']['bouquet_wrap']): ?><br><small>Bouquet wrapping</small><?php endif; ?>
                    <?php if ($item['customization']['greeting_card']): ?><br><small>Greeting card</small><?php endif; ?>
                    <?php if ($item['customization']['extra_stems'] > 0): ?><br><small><?= (int)$item['customization']['extra_stems'] ?> extra stems</small><?php endif; ?>
                    <?php if ($item['customization']['message'] !== ''): ?><br><small>Message: <?= nl2br(htmlspecialchars($item['customization']['message'], ENT_QUOTES, 'UTF-8')) ?></small><?php endif; ?>
                </td>
                <td><?= (int)$item['quantity'] ?></td>
                <td>₱<?= number_format((float)$unitPrice, 2) ?></td>
                <td>₱<?= number_format((float)$subtotal, 2) ?></td>
            </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="store-checkout-bottom">
            <a class="store-text-link" href="cart.php">&larr; Back to your bag</a>
            <div class="store-checkout-total"><span>Order total</span><strong>₱<?= number_format((float)$total, 2) ?></strong></div>
            <form method="POST" action="checkout.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label class="checkout-notes-label" for="delivery-notes">Delivery or order notes <span>(optional, up to 1,000 characters)</span></label>
                <textarea id="delivery-notes" class="checkout-notes" name="delivery_notes" maxlength="1000" rows="3" placeholder="Add delivery instructions or anything else we should know"><?= htmlspecialchars($deliveryNotes, ENT_QUOTES, 'UTF-8') ?></textarea>
                <button class="store-button store-button-gold" type="submit" name="place_order">Place order <span aria-hidden="true">&rarr;</span></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
