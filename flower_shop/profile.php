<?php
require_once 'includes/config.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$userId = (int)$_SESSION['user_id'];
$statement = $pdo->prepare('SELECT id, name, email, bio, password FROM users WHERE id = ?');
$statement->execute([$userId]);
$user = $statement->fetch();
if (!$user) {
    $_SESSION = [];
    session_destroy();
    redirect('login.php');
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Please reload and try again.';
    } elseif ($name === '' || utf8_length($name) > 100 || utf8_length($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a name and a valid email address.';
    } elseif (utf8_length($bio) > 500) {
        $error = 'Your bio must be 500 characters or fewer.';
    } elseif ($newPassword !== '' && (!password_verify($currentPassword, $user['password']) || utf8_length($newPassword) < 11 || $newPassword !== $confirmPassword)) {
        $error = 'To change your password, enter your current password and confirm a new password of at least 11 characters.';
    } elseif ($newPassword === '' && $confirmPassword !== '') {
        $error = 'Enter a new password before confirming it.';
    } else {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $emailCheck->execute([$email, $userId]);
        if ($emailCheck->fetch()) {
            $error = 'That email address is already associated with another account.';
        } else {
            if ($newPassword !== '') {
                $update = $pdo->prepare('UPDATE users SET name = ?, email = ?, bio = ?, password = ? WHERE id = ?');
                $update->execute([$name, $email, $bio, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $update = $pdo->prepare('UPDATE users SET name = ?, email = ?, bio = ? WHERE id = ?');
                $update->execute([$name, $email, $bio, $userId]);
            }
            $_SESSION['user_name'] = $name;
            $statement->execute([$userId]);
            $user = $statement->fetch();
            $success = 'Your profile has been updated.';
        }
    }
}

require_once 'includes/header.php';
?>

<section class="profile-page">
    <div class="profile-heading">
        <div>
            <p class="store-eyebrow">Your Bloom account</p>
            <h1>Your profile</h1>
            <p>Manage your personal details and keep your account up to date.</p>
        </div>
    </div>
    <?php if ($error !== ''): ?><div class="account-alert account-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="account-alert account-alert-success" role="status"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <div class="profile-layout">
        <aside class="profile-sidebar">
            <section class="profile-overview" aria-label="Account overview">
                <p class="profile-overview-label">Signed in as</p>
                <h2><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="profile-overview-email"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
                <span class="profile-account-badge">Bloom customer</span>
            </section>
            <nav class="profile-shortcuts" aria-label="Account shortcuts">
                <a href="orders.php">
                    <span class="profile-shortcut-icon" aria-hidden="true">↗</span>
                    <span><strong>My orders</strong><small>Check your order history</small></span>
                    <span class="profile-shortcut-arrow" aria-hidden="true">›</span>
                </a>
                <a href="shop.php">
                    <span><strong>Browse flowers</strong><small>Find something thoughtful</small></span>
                    <span class="profile-shortcut-arrow" aria-hidden="true">›</span>
                </a>
            </nav>
        </aside>

        <form class="profile-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <section class="profile-panel">
                <div class="profile-panel-heading">
                    <span class="profile-panel-icon" aria-hidden="true">01</span>
                    <div>
                        <h2>Personal information</h2>
                        <p>These details are used to identify your account.</p>
                    </div>
                </div>
                <div class="profile-fields">
                    <div class="profile-field">
                        <label for="profile-name">Full name</label>
                        <input id="profile-name" type="text" name="name" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars($_POST['name'] ?? $user['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="profile-field">
                        <label for="profile-email">Email address</label>
                        <input id="profile-email" type="email" name="email" maxlength="100" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user['email'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="profile-field profile-field-full">
                        <label for="profile-bio">A little about you <span>Optional · 500 characters max</span></label>
                        <textarea id="profile-bio" name="bio" maxlength="500" rows="4" aria-describedby="profile-bio-help"><?= htmlspecialchars($_POST['bio'] ?? ($user['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <small id="profile-bio-help">Share a little about yourself. You can leave this blank.</small>
                    </div>
                </div>
            </section>
            <fieldset class="profile-panel profile-password-panel">
                <legend class="profile-panel-heading">
                    <span class="profile-panel-icon" aria-hidden="true">02</span>
                    <span>
                        <strong>Change password</strong>
                        <small>Optional · leave blank to keep your current password.</small>
                    </span>
                </legend>
                <div class="profile-fields">
                    <div class="profile-field">
                        <label for="current-password">Current password</label>
                        <input id="current-password" type="password" name="current_password" autocomplete="current-password">
                    </div>
                    <div class="profile-field">
                        <label for="new-password">New password</label>
                        <input id="new-password" type="password" name="new_password" minlength="11" autocomplete="new-password" aria-describedby="new-password-help">
                        <small id="new-password-help">Use at least 11 characters.</small>
                    </div>
                    <div class="profile-field profile-field-full">
                        <label for="confirm-password">Confirm new password</label>
                        <input id="confirm-password" type="password" name="confirm_password" minlength="11" autocomplete="new-password">
                    </div>
                </div>
            </fieldset>
            <div class="profile-form-actions">
                <p>Your profile is only visible to you.</p>
                <button class="store-button store-button-gold" type="submit">Save changes</button>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
