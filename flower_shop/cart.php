<?php
session_start();
require_once 'includes/config.php';

// ---- Handle Add to Cart ----
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_to_cart'])
) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload and try again.');
    }
    $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
    if ($product_id === false || $product_id < 1 || $quantity === false || $quantity < 1) {
        http_response_code(400);
        exit('Invalid product or quantity.');
    }

    $stmt = $pdo->prepare("SELECT id, name, price, stock, is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product || (int)$product['stock'] < 1) {
        $_SESSION['cart_error'] = 'This bouquet is currently unavailable.';
    } else {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $existingItem = $_SESSION['cart'][$product_id] ?? [];
        $customization = normalize_cart_customization($existingItem['customization'] ?? [
            'extra_stems' => 0,
            'message' => '',
        ]);
        if ($customization === false) {
            $customization = normalize_cart_customization([]);
        }
        $maximumQuantity = intdiv((int)$product['stock'], 1 + $customization['extra_stems']);
        if ($maximumQuantity < 1) {
            $_SESSION['cart_error'] = 'There is not enough stock for the selected extra stems.';
            header('Location: cart.php');
            exit;
        }
        $existingQuantity = (int)($existingItem['quantity'] ?? 0);
        $_SESSION['cart'][$product_id] = [
            'name' => $product['name'],
            'price' => product_current_price($product),
            'quantity' => min($existingQuantity + $quantity, $maximumQuantity),
            'customization' => $customization,
        ];
    }
    header('Location: cart.php');
    exit;
}

// ---- Handle Remove Item ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['remove_item']) || isset($_POST['clear_cart']))) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload and try again.');
    }
    if (isset($_POST['clear_cart'])) {
        unset($_SESSION['cart']);
    } else {
        $productId = filter_var($_POST['remove_item'], FILTER_VALIDATE_INT);
        if ($productId !== false) {
            unset($_SESSION['cart'][$productId]);
        }
    }
    header('Location: cart.php');
    exit;
}

// ---- Handle Update Quantity ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form token. Please reload the cart and try again.');
    }
    if (!isset($_POST['quantities']) || !is_array($_POST['quantities'])) {
        http_response_code(400);
        exit('Invalid cart quantities.');
    }

    $customizations = $_POST['customizations'] ?? [];
    if (!is_array($customizations)) {
        http_response_code(400);
        exit('Invalid cart customizations.');
    }

    $updates = [];
    $cartError = '';
    $stockLookup = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    foreach ($_POST['quantities'] as $id => $rawQuantity) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
        if ($id === false || !isset($_SESSION['cart'][$id]) || $quantity === false) {
            $cartError = 'Your cart could not be updated. Please reload and try again.';
            break;
        }

        if ($quantity < 1) {
            $updates[$id] = null;
            continue;
        }

        $customization = normalize_cart_customization($customizations[$id] ?? []);
        if ($customization === false) {
            $cartError = 'Check your bouquet options and keep messages to 500 characters. A message needs wrapping or a greeting card.';
            break;
        }

        $stockLookup->execute([$id]);
        $stock = $stockLookup->fetchColumn();
        $maximumQuantity = $stock === false
            ? 0
            : intdiv((int)$stock, 1 + $customization['extra_stems']);
        if ($maximumQuantity < 1) {
            $cartError = 'There is not enough stock for the selected extra stems.';
            break;
        }

        $updates[$id] = [
            'quantity' => min($quantity, $maximumQuantity),
            'customization' => $customization,
        ];
    }

    if ($cartError !== '') {
        $_SESSION['cart_error'] = $cartError;
    } else {
        foreach ($updates as $id => $update) {
            if ($update === null) {
                unset($_SESSION['cart'][$id]);
                continue;
            }
            $_SESSION['cart'][$id]['quantity'] = $update['quantity'];
            $_SESSION['cart'][$id]['customization'] = $update['customization'];
        }
    }
    header('Location: cart.php');
    exit;
}

// ---- Clean up invalid cart entries ----
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $productStatement = $pdo->prepare("SELECT id, name, price, stock, is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at FROM products WHERE id = ?");
    foreach ($_SESSION['cart'] as $id => $item) {
        // If any required keys are missing, remove the item
        if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            unset($_SESSION['cart'][$id]);
        }
        // If quantity is 0 or negative, remove it
        if (isset($_SESSION['cart'][$id]) && $_SESSION['cart'][$id]['quantity'] <= 0) {
            unset($_SESSION['cart'][$id]);
        }
        if (isset($_SESSION['cart'][$id])) {
            $productStatement->execute([(int)$id]);
            $product = $productStatement->fetch();
            if (!$product || (int)$product['stock'] < 1) {
                unset($_SESSION['cart'][$id]);
            } else {
                $customization = normalize_cart_customization($item['customization'] ?? [
                    'extra_stems' => 0,
                    'message' => '',
                ]);
                if ($customization === false) {
                    $customization = normalize_cart_customization([]);
                }
                $maximumQuantity = intdiv((int)$product['stock'], 1 + $customization['extra_stems']);
                if ($maximumQuantity < 1) {
                    unset($_SESSION['cart'][$id]);
                    continue;
                }
                $_SESSION['cart'][$id]['name'] = $product['name'];
                $_SESSION['cart'][$id]['price'] = product_current_price($product);
                $_SESSION['cart'][$id]['quantity'] = min((int)$_SESSION['cart'][$id]['quantity'], $maximumQuantity);
                $_SESSION['cart'][$id]['customization'] = $customization;
                $_SESSION['cart'][$id]['stock'] = (int)$product['stock'];
            }
        }
    }
}

// ---- Calculate Total ----
$cart_total = 0;
$cart_items = [];
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $id => $item) {
        // Only include valid items
        if (isset($item['name'], $item['price'], $item['quantity']) && $item['quantity'] > 0) {
            $item['customization'] = normalize_cart_customization($item['customization'] ?? []) ?: normalize_cart_customization([]);
            $item['unit_price'] = (float)$item['price'] + cart_customization_unit_price($item['customization']);
            $cart_items[$id] = $item;
            $cart_total += $item['unit_price'] * $item['quantity'];
        }
    }
}

// Include header after processing
require_once 'includes/header.php';
?>

<section class="store-page-heading">
    <p class="store-eyebrow">Almost yours</p>
    <h1>Your shopping bag</h1>
</section>

<?php if (!empty($_SESSION['cart_error'])): ?>
    <div class="account-alert account-alert-error" role="status"><?= htmlspecialchars($_SESSION['cart_error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php unset($_SESSION['cart_error']); ?>
<?php endif; ?>

<?php if (empty($cart_items)): ?>
    <div class="store-empty-state">
        <p>Your bag is waiting for something lovely.</p>
        <a class="store-button store-button-gold" href="shop.php">Browse flowers</a>
    </div>
<?php else: ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="store-cart-items">
                <?php foreach ($cart_items as $id => $item): ?>
                <?php
                    $customization = $item['customization'];
                    $maxQuantity = intdiv((int)$item['stock'], 1 + $customization['extra_stems']);
                ?>
                <article class="store-cart-line" data-cart-line>
                    <div class="store-cart-line-heading">
                        <div>
                            <h2><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <p>Base price <span>₱<?= number_format((float)$item['price'], 2) ?></span> per arrangement</p>
                        </div>
                        <button class="store-remove-link" type="submit" name="remove_item" value="<?= (int)$id ?>" data-confirm="Remove this flower from your bag?">Remove</button>
                    </div>

                    <fieldset class="cart-customization-panel"
                             data-base-price="<?= htmlspecialchars((string)$item['price'], ENT_QUOTES, 'UTF-8') ?>"
                             data-wrap-price="20"
                             data-card-price="10"
                             data-stem-price="5"
                             data-stock="<?= (int)$item['stock'] ?>">
                        <legend>Make it a bouquet</legend>
                        <div class="cart-customization-options">
                            <label><input type="checkbox" name="customizations[<?= (int)$id ?>][bouquet_wrap]" value="1"<?= $customization['bouquet_wrap'] ? ' checked' : '' ?>> Gift wrapping <span>+₱20</span></label>
                            <label><input type="checkbox" name="customizations[<?= (int)$id ?>][greeting_card]" value="1"<?= $customization['greeting_card'] ? ' checked' : '' ?>> Greeting card <span>+₱10</span></label>
                            <label class="cart-extra-stems">Extra stems <span>+₱5 each</span>
                                <input type="number" name="customizations[<?= (int)$id ?>][extra_stems]" value="<?= (int)$customization['extra_stems'] ?>" min="0" max="30" step="1" inputmode="numeric" aria-label="Extra stems for <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                            </label>
                        </div>
                        <label class="cart-message-label" for="bouquet-message-<?= (int)$id ?>">Bouquet or card message <span>(optional, up to 500 characters)</span></label>
                        <textarea id="bouquet-message-<?= (int)$id ?>" name="customizations[<?= (int)$id ?>][message]" maxlength="500" rows="2" placeholder="Write a short note for your recipient"><?= htmlspecialchars($customization['message'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </fieldset>

                    <div class="store-cart-line-bottom">
                        <label class="cart-quantity-label" for="quantity-<?= (int)$id ?>">Quantity</label>
                        <div class="quantity-control">
                            <button type="button" class="qty-decrement" aria-label="Decrease quantity of <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">−</button>
                            <input id="quantity-<?= (int)$id ?>" type="number" class="qty-input" name="quantities[<?= (int)$id ?>]" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= $maxQuantity ?>" step="1" inputmode="numeric" aria-label="Quantity of <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" class="qty-increment" aria-label="Increase quantity of <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">+</button>
                        </div>
                        <div class="cart-line-price">
                            <span><output data-unit-price>₱<?= number_format((float)$item['unit_price'], 2) ?></output> each</span>
                            <strong><output data-line-total>₱<?= number_format((float)$item['unit_price'] * (int)$item['quantity'], 2) ?></output></strong>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
        </div>

        <div class="store-cart-summary">
            <button class="store-remove-link" type="submit" name="clear_cart" value="1" data-confirm="Remove every flower from your bag?">Clear bag</button>
            <div class="store-cart-total">
                <span>Bag total</span>
                <strong><output data-cart-total>₱<?= number_format($cart_total, 2) ?></output></strong>
            </div>
            <div class="store-cart-actions">
                <button class="store-button store-button-outline" type="submit" name="update_cart">Update bag</button>
                <a class="store-button store-button-gold" href="checkout.php">Continue to checkout <span aria-hidden="true">&rarr;</span></a>
            </div>
        </div>
    </form>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
