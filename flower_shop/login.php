<?php
session_start();
require_once 'includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your sign-in form expired. Please reload and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Prepare and execute query
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Invalid email or password. Please try again.";
        } else {
            if (password_verify($password, $user['password'])) {
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $rehash = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = "Invalid email or password. Please try again.";
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
    <title>Login | Bloom Flower Shop</title>
    <link rel="stylesheet" href="css/style.css?v=<?= (int)filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body class="auth-page">
    <main class="auth-card">
        <a href="index.php" aria-label="Bloom Flower Shop home">
            <img class="auth-brand" src="images/logo.png" alt="Bloom Flower Shop">
        </a>
        <h1 class="auth-heading">Welcome back</h1>
        <p class="auth-subtitle">Sign in to continue to your Bloom account.</p>

        <?php if ($error): ?>
            <div class="auth-alert" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="auth-field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="auth-submit" type="submit" name="login">Sign in</button>
        </form>

        <nav class="auth-links" aria-label="Account navigation">
            <p>New to Bloom? <a href="register.php">Create an account</a></p>
            <a href="index.php">Back to home</a>
        </nav>
    </main>
        <?php require_once 'includes/store_footer.php'; ?>
        <script src="javascript/script.js?v=<?= (int)filemtime(__DIR__ . '/javascript/script.js') ?>" defer></script>
</body>
</html>