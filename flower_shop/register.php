<?php
session_start();
require_once 'includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your registration form expired. Please reload and try again.';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || utf8_length($email) > 100) {
        $error = "Enter a valid email address with at most 100 characters.";
    } elseif (utf8_length($name) > 100) {
        $error = "Your name must be 100 characters or fewer.";
    } elseif (utf8_length($password) < 11) {
        $error = "Password must be at least 11 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match("/^[\p{L}\s\-']+$/u", $name)) {
        $error = "Name can only contain letters, spaces, hyphens, and apostrophes.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered. Please login.";
        } else {
            // Hash password and insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
            if ($stmt->execute([$name, $email, $hashed_password])) {
                $success = "Registration successful! You can now <a href='login.php'>login</a>.";
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
    <title>Create account | Bloom Flower Shop</title>
    <link rel="stylesheet" href="css/style.css?v=<?= (int)filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a href="index.php" aria-label="Bloom Flower Shop home"><img class="auth-brand" src="images/logo.png" alt="Bloom Flower Shop"></a>
        <h1 class="auth-heading">Create your account</h1>
        <p class="auth-subtitle">Save your details and keep track of your flower orders.</p>
        <?php if ($error): ?><div class="auth-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?><div class="auth-success" role="status"><?= $success ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="auth-field"><label for="name">Full name</label><input id="name" type="text" name="name" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="auth-field"><label for="email">Email address</label><input id="email" type="email" name="email" maxlength="100" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="auth-field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="new-password" minlength="11" required><small>Use at least 11 characters.</small></div>
            <div class="auth-field"><label for="confirm_password">Confirm password</label><input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required></div>
            <button class="auth-submit" type="submit">Create account</button>
        </form>
        <nav class="auth-links" aria-label="Account navigation"><p>Already have an account? <a href="login.php">Sign in</a></p><a href="index.php">Back to home</a></nav>
    </main>
    <?php require_once 'includes/store_footer.php'; ?>
    <script src="javascript/script.js?v=<?= (int)filemtime(__DIR__ . '/javascript/script.js') ?>" defer></script>
</body>
</html>