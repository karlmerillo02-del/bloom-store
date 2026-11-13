<?php
// Function to safely sanitize user input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function utf8_length($value) {
    $count = preg_match_all('/./us', (string)$value, $matches);
    return $count === false ? PHP_INT_MAX : $count;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to get cart item count
function cart_count() {
    if (isset($_SESSION['cart'])) {
        return array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    return 0;
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Function to display flash messages
function display_flash($key, $type = 'info') {
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return "<div class='alert alert-$type'>$msg</div>";
    }
    return '';
}

function flower_categories() {
    return [
        'Birthday',
        'Valentine\'s Day',
        'Sympathy & Funeral',
        'Anniversary',
        'Congratulations',
        'Get Well Soon',
        'Thank You',
        'Just Because',
    ];
}

function flower_category(array $product) {
    $category = trim($product['category'] ?? '');
    return in_array($category, flower_categories(), true) ? $category : 'Just Because';
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function is_active_sale(array $product) {
    if (
        empty($product['is_ready_bouquet'])
        || !isset($product['sale_price'])
        || $product['sale_price'] === null
        || (float)$product['sale_price'] <= 0
        || (float)$product['sale_price'] >= (float)$product['price']
        || empty($product['sale_starts_at'])
        || empty($product['sale_ends_at'])
    ) {
        return false;
    }

    $storeTimezone = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $storeTimezone);
    $startsAt = new DateTimeImmutable($product['sale_starts_at'], $storeTimezone);
    $endsAt = new DateTimeImmutable($product['sale_ends_at'], $storeTimezone);
    return $startsAt <= $now && $now <= $endsAt;
}

function product_current_price(array $product) {
    return is_active_sale($product) ? (float)$product['sale_price'] : (float)$product['price'];
}

function normalize_cart_customization($values) {
    if (!is_array($values)) {
        return false;
    }

    $rawExtraStems = $values['extra_stems'] ?? '0';
    $rawMessage = $values['message'] ?? '';
    if (!is_scalar($rawExtraStems) || !is_string($rawMessage)) {
        return false;
    }

    $extraStems = filter_var($rawExtraStems, FILTER_VALIDATE_INT);
    $message = trim($rawMessage);
    $bouquetWrap = isset($values['bouquet_wrap'])
        && ($values['bouquet_wrap'] === '1' || $values['bouquet_wrap'] === true);
    $greetingCard = isset($values['greeting_card'])
        && ($values['greeting_card'] === '1' || $values['greeting_card'] === true);

    if (
        $extraStems === false
        || $extraStems < 0
        || $extraStems > 30
        || !is_string($message)
        || utf8_length($message) > 500
        || ($message !== '' && !$bouquetWrap && !$greetingCard)
    ) {
        return false;
    }

    return [
        'bouquet_wrap' => $bouquetWrap,
        'greeting_card' => $greetingCard,
        'extra_stems' => $extraStems,
        'message' => $message,
    ];
}

function cart_customization_unit_price(array $customization) {
    return (!empty($customization['bouquet_wrap']) ? 20 : 0)
        + (!empty($customization['greeting_card']) ? 10 : 0)
        + ((int)($customization['extra_stems'] ?? 0) * 5);
}

function cart_item_unit_price(array $product, array $item) {
    return product_current_price($product)
        + cart_customization_unit_price($item['customization'] ?? []);
}

function customization_summary($details) {
    if (!is_string($details) || $details === '') {
        return '';
    }

    $customization = json_decode($details, true);
    if (!is_array($customization)) {
        return '';
    }

    $summary = [];
    if (!empty($customization['bouquet_wrap'])) {
        $summary[] = 'Gift wrapping';
    }
    if (!empty($customization['greeting_card'])) {
        $summary[] = 'Greeting card';
    }
    $extraStems = (int)($customization['extra_stems'] ?? 0);
    if ($extraStems > 0) {
        $summary[] = $extraStems . ' extra ' . ($extraStems === 1 ? 'stem' : 'stems');
    }

    return implode(', ', $summary);
}

function normalize_order_status($status) {
    $allowedStatuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
    return is_string($status) && in_array($status, $allowedStatuses, true)
        ? $status
        : 'pending';
}

function app_settings(PDO $pdo) {
    $settings = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $settings;
}

function local_product_image(array $product) {
    $image = trim($product['image'] ?? '');
    if (
        preg_match('/\Aimages\/products\/product-[a-zA-Z0-9-]+\.(?:jpe?g|png|webp|gif)\z/', $image)
        && is_file(__DIR__ . '/../' . $image)
    ) {
        return $image;
    }

    return 'images/products/flower-placeholder.svg';
}

function save_product_image(array $file) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image could not be uploaded. Please try again.');
    }
    if (!isset($file['tmp_name'], $file['size']) || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Choose an image smaller than 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The uploaded image could not be verified.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Upload a valid JPG, PNG, WEBP, or GIF image.');
    }

    $directory = __DIR__ . '/../images/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The product image folder could not be created.');
    }

    $filename = 'product-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The product image could not be saved.');
    }

    return 'images/products/' . $filename;
}
?>