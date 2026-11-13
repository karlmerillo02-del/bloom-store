<?php
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = filter_var($_POST['stock'] ?? '5', FILTER_VALIDATE_INT);
    $category = $_POST['category'] ?? '';
    $salePrice = trim($_POST['sale_price'] ?? '');
    $saleStartsAt = trim($_POST['sale_starts_at'] ?? '');
    $saleEndsAt = trim($_POST['sale_ends_at'] ?? '');
    $readyBouquet = isset($_POST['is_ready_bouquet']) ? 1 : 0;
    $storeTimezone = new DateTimeZone('Asia/Manila');
    $startDate = $saleStartsAt === '' ? false : DateTime::createFromFormat('Y-m-d\TH:i', $saleStartsAt, $storeTimezone);
    $endDate = $saleEndsAt === '' ? false : DateTime::createFromFormat('Y-m-d\TH:i', $saleEndsAt, $storeTimezone);

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Please reload and try again.';
    } elseif ($name === '' || !is_numeric($price) || (float)$price <= 0) {
        $error = 'Enter a product name and a valid price greater than zero.';
    } elseif ($stock === false || $stock < 0) {
        $error = 'Enter a whole-number stock amount of zero or more.';
    } elseif (!in_array($category, flower_categories(), true)) {
        $error = 'Choose a category from the list.';
    } elseif (($salePrice !== '' || $saleStartsAt !== '' || $saleEndsAt !== '')
        && (!$readyBouquet || !is_numeric($salePrice) || (float)$salePrice <= 0
            || (float)$salePrice >= (float)$price || !$startDate || !$endDate
            || $startDate->format('Y-m-d\TH:i') !== $saleStartsAt
            || $endDate->format('Y-m-d\TH:i') !== $saleEndsAt
            || $endDate <= $startDate)) {
        $error = 'For a flash sale, mark this as a ready bouquet and enter a lower sale price with valid start and end times.';
    } else {
        try {
            $image = save_product_image($_FILES['image_file'] ?? []);
            $image = $image ?? 'images/products/flower-placeholder.svg';
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category, image, is_ready_bouquet, sale_price, sale_starts_at, sale_ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, $desc, $price, $stock, $category, $image, $readyBouquet,
                $salePrice === '' ? null : $salePrice,
                $salePrice === '' ? null : $startDate->format('Y-m-d H:i:s'),
                $salePrice === '' ? null : $endDate->format('Y-m-d H:i:s')
            ]);
            header('Location: products.php?msg=added');
            exit;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Product</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= (int)filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body class="admin-workspace">
<div class="admin-wrap">
    <?php $adminActive = 'products'; require '../includes/admin_sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-top">
            <div>
                <p class="eyebrow">Product catalog</p>
                <h1>Add product</h1>
                <p>Add a flower to your catalog and set its inventory and pricing.</p>
            </div>
            <div class="admin-top-actions">
                <a class="admin-button admin-button-secondary" href="products.php">Back to products</a>
            </div>
        </header>
        <?php if ($error): ?>
            <div class="account-alert account-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="admin-form-layout">
            <form class="admin-form-card" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <fieldset class="admin-form-section">
                    <legend>Product details</legend>
                    <div class="admin-form-grid">
                        <div class="admin-form-field admin-form-field-full">
                            <label for="name">Product name</label>
                            <input id="name" type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="admin-form-field admin-form-field-full">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="admin-form-field">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <?php foreach (flower_categories() as $option): ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"<?= ($category ?? 'Just Because') === $option ? ' selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="admin-form-section">
                    <legend>Price and inventory</legend>
                    <div class="admin-form-grid">
                        <div class="admin-form-field">
                            <label for="price">Regular price (₱)</label>
                            <input id="price" type="number" min="0.01" step="0.01" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="admin-form-field">
                            <label for="stock">Stock quantity</label>
                            <input id="stock" type="number" min="0" step="1" name="stock" value="<?= htmlspecialchars((string)($_POST['stock'] ?? '5'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="admin-form-field admin-check-field">
                            <label><input type="checkbox" name="is_ready_bouquet" value="1"<?= isset($_POST['is_ready_bouquet']) ? ' checked' : '' ?>> Ready-to-send bouquet</label>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="admin-form-section">
                    <legend>Flash sale <span>Optional — requires a ready-to-send bouquet</span></legend>
                    <div class="admin-form-grid">
                        <div class="admin-form-field">
                            <label for="sale_price">Sale price (₱)</label>
                            <input id="sale_price" type="number" min="0.01" step="0.01" name="sale_price" value="<?= htmlspecialchars($_POST['sale_price'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="admin-form-field">
                            <label for="sale_starts_at">Starts</label>
                            <input id="sale_starts_at" type="datetime-local" name="sale_starts_at" value="<?= htmlspecialchars($_POST['sale_starts_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="admin-form-field">
                            <label for="sale_ends_at">Ends</label>
                            <input id="sale_ends_at" type="datetime-local" name="sale_ends_at" value="<?= htmlspecialchars($_POST['sale_ends_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </fieldset>
                <fieldset class="admin-form-section">
                    <legend>Product photo</legend>
                    <div class="admin-form-field">
                        <label for="image_file">Choose an image <span>(optional, max 5 MB)</span></label>
                        <input id="image_file" type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="image-preview-message">
                        <img class="admin-image-preview" data-image-preview alt="Product image preview" hidden>
                        <p id="image-preview-message" class="image-preview-message" data-image-preview-message aria-live="polite"></p>
                    </div>
                </fieldset>
                <div class="admin-form-actions">
                    <a class="admin-button admin-button-secondary" href="products.php">Cancel</a>
                    <button type="submit" class="admin-button admin-button-primary">Add product</button>
                </div>
            </form>
        </div>
    </main>
</div>
<script src="../javascript/script.js?v=<?= (int)filemtime(__DIR__ . '/../javascript/script.js') ?>" defer></script>
</body>
</html>