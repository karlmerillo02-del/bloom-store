<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once '../includes/config.php';

$total_products = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$total_orders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$recentOrders = $pdo->query(
    "SELECT o.id, o.quantity, o.total_price, o.order_date, o.status,
            u.name AS customer_name, p.name AS product_name
     FROM orders o
     JOIN users u ON o.user_id = u.id
     JOIN products p ON o.product_id = p.id
     ORDER BY o.order_date DESC, o.id DESC
     LIMIT 6"
)->fetchAll();

// Get stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$salesRows = $pdo->query(
    "SELECT DATE(order_date) AS sale_date, SUM(total_price) AS revenue
     FROM orders
     WHERE order_date >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(order_date)
     ORDER BY sale_date"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$salesChart = [];
$storeTimezone = new DateTimeZone('Asia/Manila');
for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $day = new DateTimeImmutable("-$dayOffset days", $storeTimezone);
    $date = $day->format('Y-m-d');
    $salesChart[] = [
        'label' => $day->format('D'),
        'date' => $day->format('M j'),
        'revenue' => (float)($salesRows[$date] ?? 0),
    ];
}
$maxRevenue = max(array_column($salesChart, 'revenue')) ?: 1;

$msg = $_GET['msg'] ?? '';
$adminActive = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Bloom Admin</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= (int)filemtime(__DIR__ . '/../css/style.css') ?>">
    <style>
        :root {
            --ink: #1a1a2e;
            --muted: #72798a;
            --brand: #2c3e50;
            --brand-dark: #1a1a2e;
            --brand-soft: #fff7d6;
            --canvas: #f5f6fa;
            --line: #e7e9f0;
            --white: #fff;
            --shadow: 0 8px 24px rgba(26, 26, 46, .06);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--canvas);
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        button, select { font: inherit; }

        .admin-wrap {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            display: flex;
            flex: 0 0 248px;
            flex-direction: column;
            width: 248px;
            padding: 28px 16px;
            background: var(--brand-dark);
            color: #fff;
            position: fixed;
            inset: 0 auto 0 0;
            overflow-y: auto;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 4px 10px 25px;
            border-bottom: 1px solid rgba(255,255,255,.13);
            color: #fff;
            font-size: 21px;
            font-weight: 750;
            letter-spacing: -.5px;
        }
        .brand-mark {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 12px;
            background: var(--brand-soft);
            color: var(--brand-dark);
            font-size: 18px;
        }
        .brand span { color: #f1c40f; }
        .nav-label {
            margin: 25px 10px 10px;
            color: #c6c8d2;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }
        .admin-sidebar .nav {
            display: grid;
            gap: 6px;
        }
        .admin-sidebar .nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid transparent;
            border-radius: 9px;
            color: #e0e1e8;
            font-size: 14px;
            transition: background .18s ease, color .18s ease;
        }
        .nav-icon {
            display: inline-grid;
            width: 23px;
            height: 23px;
            place-items: center;
            border-radius: 7px;
            background: rgba(255,255,255,.08);
            color: #f1c40f;
            font-size: 12px;
            font-weight: 700;
        }
        .admin-sidebar .nav a:hover,
        .admin-sidebar .nav a.active {
            background: rgba(255,255,255,.1);
            color: #fff;
        }
        .admin-sidebar .nav a.active {
            border-color: rgba(241,196,15,.24);
            box-shadow: inset 3px 0 #f1c40f;
        }
        .sidebar-footer {
            margin-top: auto;
            padding: 24px 10px 4px;
            color: #c6c8d2;
            font-size: 12px;
        }

        .admin-main {
            width: calc(100% - 248px);
            max-width: 1800px;
            margin-left: 248px;
            padding: 34px clamp(22px, 4vw, 58px) 48px;
        }
        .admin-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 18px;
        }
        .eyebrow {
            margin: 0 0 4px;
            color: var(--brand);
            font-size: 14px;
            font-weight: 650;
        }
        .admin-top h1 {
            margin: 0;
            color: var(--ink);
            font-size: clamp(26px, 3vw, 34px);
            letter-spacing: -1px;
            line-height: 1.2;
        }
        .admin-top p { margin: 8px 0 0; color: var(--muted); font-size: 14px; }
        .top-actions { display: flex; align-items: center; gap: 10px; }
        .top-actions a {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 9px 15px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--white);
            color: var(--ink);
            font-size: 13px;
            font-weight: 650;
            transition: border-color .18s ease, transform .18s ease;
        }
        .top-actions a:hover { border-color: #b9c9bf; transform: translateY(-1px); }
        .top-actions .logout { border-color: #e2e4eb; color: #2c3e50; }
        .top-actions .logout:hover { border-color: #aeb5c3; }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #fff8df; color: #725b13; border: 1px solid #efe0a5; }
        .alert-danger { background: #fff0ee; color: #9c3d32; border: 1px solid #f1d0ca; }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat {
            position: relative;
            min-height: 132px;
            padding: 20px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: var(--white);
            box-shadow: var(--shadow);
        }
        .stat > * { position: relative; z-index: 1; }
        .stat::after {
            position: absolute;
            right: -17px;
            bottom: -34px;
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: var(--brand-soft);
            content: "";
            pointer-events: none;
        }
        .stat .label { color: var(--muted); font-size: 13px; font-weight: 600; }
        .stat .num {
            margin-top: 8px;
            color: var(--ink);
            font-size: 31px;
            font-weight: 750;
            letter-spacing: -1px;
            line-height: 1;
        }
        .stat .detail { margin-top: 9px; color: #89968f; font-size: 12px; }
        .stat.pending .num { color: #9b7910; }
        .stat.pending::after { background: #fbf0d9; }
        .sales-chart {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            align-items: end;
            gap: 14px;
            min-height: 190px;
            padding: 22px;
        }
        .chart-day { display: grid; min-width: 0; height: 100%; grid-template-rows: 1fr auto auto; gap: 8px; text-align: center; }
        .chart-bar-track { display: flex; height: 118px; align-items: end; justify-content: center; border-bottom: 1px solid var(--line); }
        .chart-bar { width: min(34px, 70%); min-height: 3px; border-radius: 6px 6px 0 0; background: #d7ad1e; }
        .chart-value { color: var(--ink); font-size: 11px; font-weight: 650; }
        .chart-label { color: var(--muted); font-size: 11px; }
        .delete-form { display: inline-flex; }

        .section {
            margin-bottom: 22px;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: var(--white);
            box-shadow: var(--shadow);
        }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 22px;
            border-bottom: 1px solid var(--line);
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-head h2 { margin: 0; color: var(--ink); font-size: 17px; letter-spacing: -.2px; }
        .section-head p { margin: 3px 0 0; color: var(--muted); font-size: 12px; }
        .section-head .add {
            display: inline-flex;
            min-height: 40px;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            font-size: 13px;
            font-weight: 650;
            transition: background .18s ease, transform .18s ease;
        }
        .section-head .add:hover { background: var(--brand-dark); transform: translateY(-1px); }

        .table-wrap { width: 100%; overflow-x: auto; }
        table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }
        th {
            padding: 12px 16px;
            background: #f8faf8;
            border-bottom: 1px solid var(--line);
            color: #72798a;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: .75px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        td {
            padding: 13px 16px;
            border-bottom: 1px solid #eef0f4;
            color: #455066;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr { transition: background .15s ease; }
        tbody tr:hover { background: #fafaff; }
        .thumb {
            display: block;
            width: 48px;
            height: 48px;
            border: 1px solid #eef0f4;
            border-radius: 9px;
            background: #f7f8fb;
            object-fit: cover;
        }
        .name { color: var(--ink); font-weight: 650; }
        .price { color: var(--brand); font-weight: 700; white-space: nowrap; }
        .tag {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 20px;
            background: #f0f1f5;
            color: #4a5368;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .muted { color: #9aa59f; font-size: 12px; }
        .empty { padding: 38px 20px; color: var(--muted); text-align: center; }
        .empty a { color: var(--brand); font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-pending { background: #fff3d9; color: #94620d; }
        .status-paid { background: #e5efff; color: #315a9a; }
        .status-shipped { background: #eee8ff; color: #6546a2; }
        .status-delivered { background: #e5f3e9; color: #347147; }
        .status-cancelled { background: #ffebe8; color: #a34035; }
        .stock-low { color: #ae5e25; font-weight: 700; }

        .actions { display: flex; gap: 7px; flex-wrap: wrap; }
        .actions .btn,
        .status-form .btn {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            justify-content: center;
            padding: 6px 11px;
            border: 1px solid transparent;
            border-radius: 7px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 650;
            transition: background .15s ease, border-color .15s ease;
        }
        .actions .edit { background: #edf0f5; color: #2c3e50; }
        .actions .edit:hover { background: #dfe4ee; }
        .actions .delete { background: #fff0ee; color: #a84336; }
        .actions .delete:hover { background: #fbe0dc; }
        .actions .update,
        .status-form .update { background: var(--brand); color: #fff; }
        .status-form .update:hover { background: var(--brand-dark); }
        .admin-select {
            min-height: 36px;
            padding: 6px 28px 6px 10px;
            border: 1px solid #dfe6e1;
            border-radius: 7px;
            background: #fff;
            color: #405047;
            font-size: 12px;
        }
        .status-form { display: flex; align-items: center; gap: 7px; }
        :focus-visible { outline: 3px solid rgba(47,114,88,.35); outline-offset: 2px; }

        @media (max-width: 1050px) {
            .admin-sidebar { flex-basis: 220px; width: 220px; }
            .admin-main { width: calc(100% - 220px); margin-left: 220px; padding-right: 24px; padding-left: 24px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .admin-wrap { display: block; overflow-x: clip; }
            .admin-sidebar {
                position: static;
                width: 100%;
                padding: 12px 16px;
                overflow: visible;
            }
            .brand { padding: 2px 3px 12px; border-bottom: 0; }
            .brand-mark { width: 32px; height: 32px; }
            .nav-label, .sidebar-footer { display: none; }
            .admin-sidebar .nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                overflow: visible;
            }
            .admin-sidebar .nav a { flex: 1 1 auto; min-height: 40px; padding: 8px 10px; font-size: 12px; }
            .nav-icon { width: 20px; height: 20px; }
            .admin-main { width: 100%; margin: 0; padding: 24px 18px 36px; }
        }
        @media (max-width: 520px) {
            .admin-top { align-items: flex-start; }
            .top-actions { width: 100%; }
            .top-actions a { flex: 1; }
            .stats { gap: 10px; }
            .stat { min-height: 116px; padding: 15px; }
            .stat .num { font-size: 27px; }
            .section-head { padding: 16px; }
            .admin-main { padding-right: 12px; padding-left: 12px; }
        }
    </style>
</head>
<body class="admin-workspace admin-dashboard">

<div class="admin-wrap">
    <?php require '../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <header class="admin-top">
            <div>
                <p class="eyebrow">Admin overview</p>
                <h1>Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>.</p>
            </div>
            <div class="top-actions">
                <a href="orders.php">Manage orders</a>
                <a href="products.php">Manage products</a>
            </div>
        </header>

        <?php if ($msg === 'product_deleted'): ?>
            <div class="alert alert-danger" role="status">Product deleted successfully.</div>
        <?php elseif ($msg === 'order_updated'): ?>
            <div class="alert alert-success" role="status">Order status updated successfully.</div>
        <?php elseif ($msg === 'product_added'): ?>
            <div class="alert alert-success" role="status">Product added successfully.</div>
        <?php elseif ($msg === 'product_updated'): ?>
            <div class="alert alert-success" role="status">Product updated successfully.</div>
        <?php endif; ?>

        <section class="stats" aria-label="Shop summary">
            <a class="stat" href="products.php">
                <div class="label">Products</div>
                <div class="num"><?= number_format((int)$total_products) ?></div>
                <div class="detail">In your catalog</div>
            </a>
            <a class="stat" href="orders.php">
                <div class="label">Orders</div>
                <div class="num"><?= number_format((int)$total_orders) ?></div>
                <div class="detail">All-time orders</div>
            </a>
            <a class="stat pending" href="orders.php">
                <div class="label">Pending orders</div>
                <div class="num"><?= number_format((int)$pending_orders) ?></div>
                <div class="detail">Need your attention</div>
            </a>
            <article class="stat">
                <div class="label">Users</div>
                <div class="num"><?= number_format((int)$total_users) ?></div>
                <div class="detail">Registered accounts</div>
            </article>
        </section>

        <section class="section" aria-labelledby="sales-heading">
            <div class="section-head">
                <div>
                    <h2 id="sales-heading">Sales, last 7 days</h2>
                    <p>Daily order revenue in Philippine pesos.</p>
                </div>
            </div>
            <div class="sales-chart" role="img" aria-label="Daily order revenue for the last seven days">
                <?php foreach ($salesChart as $day): ?>
                    <?php $barHeight = $day['revenue'] > 0 ? max(5, (int)round($day['revenue'] / $maxRevenue * 100)) : 0; ?>
                    <div class="chart-day" title="<?= htmlspecialchars($day['date'], ENT_QUOTES, 'UTF-8') ?>: ₱<?= number_format($day['revenue'], 2) ?>">
                        <div class="chart-bar-track"><span class="chart-bar" style="height: <?= $barHeight ?>%"></span></div>
                        <span class="chart-value">₱<?= number_format($day['revenue'], 0) ?></span>
                        <span class="chart-label"><?= htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section" aria-labelledby="orders-heading">
            <div class="section-head">
                <div>
                    <h2 id="orders-heading">Recent orders</h2>
                    <p>Latest activity. Open orders to review details and update fulfillment.</p>
                </div>
                <a href="orders.php" class="add">View all orders</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Product</th>
                            <th scope="col">Date</th>
                            <th scope="col">Total</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="6" class="empty">No orders yet. New customer orders will appear here.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                            <?php $orderStatus = normalize_order_status($order['status'] ?? null); ?>
                            <tr>
                                <td><strong>#<?= (int)$order['id'] ?></strong></td>
                                <td><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($order['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="muted"><?= htmlspecialchars(date('M j, Y', strtotime($order['order_date'])), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="price">₱<?= number_format((float)$order['total_price'], 2) ?></td>
                                <td>
                                    <span class="status status-<?= htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(ucfirst($orderStatus), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
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