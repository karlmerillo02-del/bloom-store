<?php
session_start();
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/config.php';

$error = '';
$success = '';
$settings = app_settings($pdo);
$allowedSettings = [
    'store_name' => ['label' => 'Store name', 'max' => 100],
    'store_tagline' => ['label' => 'Store tagline', 'max' => 180],
    'contact_email' => ['label' => 'Contact email', 'max' => 254],
    'contact_phone' => ['label' => 'Contact phone', 'max' => 40],
    'store_location' => ['label' => 'Store location', 'max' => 120],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Please reload and try again.';
    } else {
        $submitted = [];
        foreach ($allowedSettings as $key => $metadata) {
            $value = trim($_POST[$key] ?? '');
            if (utf8_length($value) > $metadata['max']) {
                $error = $metadata['label'] . ' must be ' . $metadata['max'] . ' characters or fewer.';
                break;
            }
            if ($key === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid contact email address.';
                break;
            }
            $submitted[$key] = $value;
        }

        if ($error === '') {
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare(
                    'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                foreach ($submitted as $key => $value) {
                    $update->execute([$key, $value]);
                }
                $pdo->commit();
                $settings = array_merge($settings, $submitted);
                $success = 'Store settings saved.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store settings | Bloom Admin</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= (int)filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body class="admin-workspace">
    <div class="admin-wrap">
        <?php $adminActive = 'settings'; require '../includes/admin_sidebar.php'; ?>
        <main class="admin-main">
            <header class="admin-top">
                <div>
                    <p class="eyebrow">Admin workspace</p>
                    <h1>Store settings</h1>
                    <p>Manage the business details and contact information customers see.</p>
                </div>
            </header>

            <?php if ($error !== ''): ?><div class="account-alert account-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($success !== ''): ?><div class="account-alert account-alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="admin-settings-grid">
                    <section class="admin-settings-panel" aria-labelledby="store-profile-heading">
                        <h2 id="store-profile-heading">Store profile</h2>
                        <p>Name and location displayed around the storefront.</p>
                        <?php foreach (['store_name', 'store_tagline', 'store_location'] as $key): ?>
                            <?php $metadata = $allowedSettings[$key]; ?>
                            <div class="admin-form-field">
                                <label for="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($metadata['label'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                       name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                       maxlength="<?= (int)$metadata['max'] ?>"
                                       type="text"
                                       value="<?= htmlspecialchars($_POST[$key] ?? ($settings[$key] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        <?php endforeach; ?>
                    </section>
                    <section class="admin-settings-panel" aria-labelledby="contact-heading">
                        <h2 id="contact-heading">Contact information</h2>
                        <p>Used for customer inquiries and store contact details.</p>
                        <?php foreach (['contact_email', 'contact_phone'] as $key): ?>
                            <?php $metadata = $allowedSettings[$key]; ?>
                            <div class="admin-form-field">
                                <label for="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($metadata['label'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                       name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                                       maxlength="<?= (int)$metadata['max'] ?>"
                                       <?= $key === 'contact_email' ? 'type="email"' : 'type="text"' ?>
                                       value="<?= htmlspecialchars($_POST[$key] ?? ($settings[$key] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
                <div class="admin-settings-actions">
                    <button class="admin-button admin-button-primary" type="submit">Save settings</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
