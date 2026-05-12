<?php

require_once '../includes/config.php';
global $conn;
// Protect page
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

// Count Products
$product_query = mysqli_query($conn, "SELECT COUNT(*) AS total_products FROM products");
$product_data = mysqli_fetch_assoc($product_query);

// Count Orders
$order_query = mysqli_query($conn, "SELECT COUNT(*) AS total_orders FROM orders");
$order_data = mysqli_fetch_assoc($order_query);

// Pending Orders
$pending_query = mysqli_query($conn, "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = 'Pending'");
$pending_data = mysqli_fetch_assoc($pending_query);

// Ready for Pickup
$ready_query = mysqli_query($conn, "SELECT COUNT(*) AS ready_orders FROM orders WHERE status = 'Ready'");
$ready_data = mysqli_fetch_assoc($ready_query);

// Total Sales (Today)
$sales_query = mysqli_query($conn, "SELECT SUM(total) AS total_sales FROM orders WHERE DATE(created_at) = CURDATE()");
$sales_data = mysqli_fetch_assoc($sales_query);

// Recent Orders
$recent_query = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 10");

// Recent Products (no stock column exists)
$stock_query = mysqli_query($conn, "SELECT * FROM products ORDER BY created_at DESC LIMIT 5");

// Sales trend (last 7 days)
$trend_query = mysqli_query($conn, "
    SELECT DATE(created_at) as sale_date, SUM(total) as daily_total
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");
$trend_labels = [];
$trend_values = [];
while($row = mysqli_fetch_assoc($trend_query)){
    $trend_labels[] = date('D', strtotime($row['sale_date']));
    $trend_values[] = (float)$row['daily_total'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – King's Cup</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    

    <style>
        /* ── RESET & BASE ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-bg:   #3B1F0F;
            --sidebar-w:    220px;
            --accent:       #C8A96E;
            --accent-light: #E8D5B0;
            --body-bg:      #F0EDE8;
            --surface:      #FFFFFF;
            --text-dark:    #2A1A0A;
            --text-mid:     #6B5744;
            --text-light:   #A89282;
            --border:       #E2D9CF;
            --green:        #2ECC71;
            --blue:         #3B82F6;
            --red:          #E74C3C;
            --radius:       12px;
            --shadow:       0 2px 12px rgba(60,30,10,.08);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--body-bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 22px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }

        .sidebar-logo {
            width: 42px; height: 42px;
            background: var(--accent);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            color: var(--sidebar-bg);
            font-weight: 700;
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            line-height: 1.2;
        }

        .sidebar-brand-text strong {
            display: block;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: .3px;
        }

        .sidebar-brand-text span {
            font-size: 11px;
            color: var(--accent-light);
            opacity: .75;
        }

        .sidebar-nav {
            padding: 20px 12px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 8px;
            color: rgba(255,255,255,.65);
            font-size: 13.5px;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 2px;
            transition: background .18s, color .18s;
        }

        .nav-item:hover { background: rgba(255,255,255,.08); color: #fff; }
        .nav-item.active { background: var(--accent); color: var(--sidebar-bg); font-weight: 600; }

        .nav-item svg { flex-shrink: 0; }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,.1);
        }

        /* ── MAIN WRAP ── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: var(--body-bg);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
            border-bottom: 1px solid var(--border);
        }

        .topbar-title {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: var(--text-dark);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            color: var(--text-light);
            width: 240px;
        }

        .search-box input {
            border: none; outline: none;
            font-size: 13px; color: var(--text-dark);
            background: transparent; width: 100%;
        }

        .date-pill {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 13px;
            color: var(--text-mid);
            display: flex; align-items: center; gap: 6px;
        }

        .notif-btn {
            width: 36px; height: 36px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            position: relative;
        }

        .notif-dot {
            position: absolute;
            top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid var(--body-bg);
        }

        /* ── PAGE CONTENT ── */
        .content {
            padding: 28px;
            flex: 1;
        }

        .greeting { margin-bottom: 22px; }
        .greeting h2 { font-size: 24px; font-weight: 600; }
        .greeting p { font-size: 13.5px; color: var(--text-mid); margin-top: 3px; }

        /* ── STAT CARDS ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .stat-icon {
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-mid);
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        /* ── MIDDLE ROW ── */
        .mid-row {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            margin-bottom: 24px;
        }

        .card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-dark);
        }

        /* ── TREND TABS ── */
        .trend-tabs {
            display: flex; gap: 4px;
        }

        .trend-tab {
            padding: 4px 12px;
            font-size: 12px;
            border-radius: 20px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text-light);
            transition: background .15s, color .15s;
        }

        .trend-tab.active {
            background: var(--accent);
            color: var(--sidebar-bg);
        }

        #salesChart { width: 100% !important; height: 180px !important; }

        /* ── INVENTORY ALERTS ── */
        .inv-alerts { display: flex; flex-direction: column; gap: 10px; }

        .inv-item {
            background: var(--accent-light);
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            opacity: .85;
        }

        .inv-item-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .inv-item-stock {
            font-size: 12px;
            color: var(--red);
            font-weight: 600;
        }

        .inv-empty {
            font-size: 13px;
            color: var(--text-light);
            text-align: center;
            padding: 20px 0;
        }

        .view-all-link {
            font-size: 12px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        /* ── RECENT ORDERS TABLE ── */
        .orders-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .orders-card .card-header {
            padding: 18px 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text-light);
            padding: 10px 16px;
            background: var(--body-bg);
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #FAF8F5; }

        tbody td {
            padding: 12px 16px;
            font-size: 13.5px;
            color: var(--text-dark);
        }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .2px;
        }

        .badge-ready      { background: #D4F5E3; color: #17864B; }
        .badge-preparing  { background: #DBEAFE; color: #1D4ED8; }
        .badge-pending    { background: #FEF9C3; color: #854D0E; }
        .badge-completed  { background: #E5E7EB; color: #374151; }

        /* ── ORDER STATUS LEGEND ── */
        .legend {
            display: flex;
            gap: 14px;
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            background: var(--body-bg);
        }

        .legend-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; color: var(--text-light);
        }

        .legend-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .stat-row { grid-template-columns: repeat(2, 1fr); }
            .mid-row  { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">K</div>
        <div class="sidebar-brand-text">
            <strong>King's Cup</strong>
            <span>Admin Dashboard</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item active">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="instoreorders.php" class="nav-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            Instore Orders
        </a>
        <a href="processedorders.php" class="nav-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Processed Orders
        </a>
        <a href="stocks.php" class="nav-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            Stocks
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item" style="color:rgba(255,255,255,.55);">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<div class="main">

    <!-- TOPBAR -->
    <header class="topbar">
        <span class="topbar-title">Dashboard</span>
        <div class="topbar-right">
            <div class="search-box">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search orders, customers, menu items...">
            </div>
            <div class="date-pill">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Today
            </div>
            <div class="notif-btn">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <span class="notif-dot"></span>
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="content">

        <div class="greeting">
            <h2>Hi, Admin!</h2>
            <p>Here's what's happening at your coffee shop today.</p>
        </div>

        <!-- STAT CARDS -->
        <div class="stat-row">

            <!-- Today's Sales -->
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                </div>
                <div class="stat-value">₱<?php echo number_format($sales_data['total_sales'] ?? 0, 0); ?></div>
                <div class="stat-label">Today's Sales</div>
            </div>

            <!-- Total Orders -->
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                </div>
                <div class="stat-value"><?php echo $order_data['total_orders']; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>

            <!-- Pending Orders -->
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="stat-value"><?php echo $pending_data['pending_orders']; ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>

            <!-- Ready for Pickup -->
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1"/><path d="M12 6v6l4 2"/><path d="M5.2 8.4A9 9 0 1019 19"/></svg>
                </div>
                <div class="stat-value"><?php echo $ready_data['ready_orders']; ?></div>
                <div class="stat-label">Ready for Pickup</div>
            </div>

        </div>

        <!-- MIDDLE ROW: Chart + Inventory Alerts -->
        <div class="mid-row">

            <!-- Sales Trend -->
            <div class="card">

    <div class="card-header">

        <span class="card-title">Sales Trends</span>

        <div class="trend-tabs">
            <button class="trend-tab active" data-type="daily">Daily</button>
            <button class="trend-tab" data-type="weekly">Weekly</button>
            <button class="trend-tab" data-type="monthly">Monthly</button>
        </div>

    </div>

    <canvas id="salesChart"></canvas>

</div>

            <!-- Inventory Alerts -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Inventory Alerts</span>
                    <a href="stocks.php" class="view-all-link">View All</a>
                </div>
                <div class="inv-alerts">
                    <?php if(mysqli_num_rows($stock_query) > 0): ?>
                        <?php while($item = mysqli_fetch_assoc($stock_query)): ?>
                        <div class="inv-item">
                            <span class="inv-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="inv-item-stock">₱<?php echo number_format($item['price'], 2); ?></span>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <?php for($i=0;$i<5;$i++): ?>
                        <div class="inv-item" style="opacity:.35;">&nbsp;</div>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RECENT ORDERS -->
        <div class="orders-card">
            <div class="card-header">
                <span class="card-title">Recent Orders</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Order No.</th>
                        <th>Name</th>
                        <th>Mobile No.</th>
                        <th>Order</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($recent_query) > 0): ?>
                        <?php while($order = mysqli_fetch_assoc($recent_query)): 
                            $status = strtolower($order['status'] ?? 'pending');
                            $badge_class = match($status) {
                                'ready'     => 'badge-ready',
                                'preparing' => 'badge-preparing',
                                'completed' => 'badge-completed',
                                default     => 'badge-pending',
                            };
                        ?>
                        <tr>
                            <td><?php echo str_pad($order['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($order['mobile'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($order['items'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($order['payment_method'] ?? '—'); ?></td>
                            <td>₱<?php echo number_format($order['total'], 2); ?></td>
                            <td><?php echo htmlspecialchars($order['payment_status'] ?? 'Paid'); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:28px;color:var(--text-light);">No orders yet today.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="legend">
                <div class="legend-item"><span class="legend-dot" style="background:#F59E0B;"></span>Waiting</div>
                <div class="legend-item"><span class="legend-dot" style="background:#3B82F6;"></span>Preparing</div>
                <div class="legend-item"><span class="legend-dot" style="background:#2ECC71;"></span>Ready</div>
                <div class="legend-item"><span class="legend-dot" style="background:#9CA3AF;"></span>Completed</div>
            </div>
        </div>

    </main>
</div>

<!-- CHART.JS INIT -->
<script>
const labels = <?php echo json_encode(!empty($trend_labels) ? $trend_labels : ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']); ?>;
const values = <?php echo json_encode(!empty($trend_values) ? $trend_values : [1200, 2300, 1800, 3100, 2900, 3800, 5200]); ?>;

const ctx = document.getElementById('salesChart').getContext('2d');

const grad = ctx.createLinearGradient(0, 0, 0, 180);
grad.addColorStop(0, 'rgba(200,169,110,.35)');
grad.addColorStop(1, 'rgba(200,169,110,0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels,
        datasets: [{
            data: values,
            borderColor: '#C8A96E',
            backgroundColor: grad,
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#C8A96E',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.35,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: {
            backgroundColor: '#3B1F0F',
            titleColor: '#C8A96E',
            bodyColor: '#fff',
            padding: 10,
            callbacks: { label: ctx => '₱' + ctx.parsed.y.toLocaleString() }
        }},
        scales: {
            x: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { color: '#A89282', font: { size: 12 } } },
            y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { color: '#A89282', font: { size: 12 }, callback: v => '₱' + v.toLocaleString() } }
        }
    }
});

// Trend tab switching (cosmetic — wire to AJAX for real data)
document.querySelectorAll('.trend-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.trend-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});
</script>

</body>
</html>