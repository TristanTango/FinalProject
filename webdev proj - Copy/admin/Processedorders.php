<?php
require_once '../includes/config.php';
global $conn;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle status update via POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_status') {
        $id     = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $allowed = ['Pending', 'Preparing', 'Ready', 'Completed'];

        if (!in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $status, $id);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Stats
$processed_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'Completed'"));
$completed_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE status = 'Completed' AND DATE(created_at) = CURDATE()"));
$sales_data     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total) AS total FROM orders WHERE status = 'Completed' AND DATE(created_at) = CURDATE()"));

// Filter
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'Pending', 'Preparing', 'Ready', 'Completed'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$where = $filter !== 'all' ? "WHERE status = '" . mysqli_real_escape_string($conn, $filter) . "'" : '';
$orders_query = mysqli_query($conn, "SELECT * FROM orders $where ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processed Orders – King's Cup</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --sidebar-bg: #3B1F0F; --sidebar-w: 220px;
            --accent: #C8A96E; --accent-light: #E8D5B0;
            --body-bg: #F0EDE8; --surface: #FFFFFF;
            --text-dark: #2A1A0A; --text-mid: #6B5744; --text-light: #A89282;
            --border: #E2D9CF; --red: #E74C3C;
            --radius: 12px; --shadow: 0 2px 12px rgba(60,30,10,.08);
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); min-height: 100vh; display: flex; }

        /* SIDEBAR */
        .sidebar { width: var(--sidebar-w); background: var(--sidebar-bg); min-height: 100vh; display: flex; flex-direction: column; position: fixed; top:0; left:0; bottom:0; z-index:100; }
        .sidebar-brand { display:flex; align-items:center; gap:10px; padding:22px 20px 20px; border-bottom:1px solid rgba(255,255,255,.1); }
        .sidebar-logo { width:42px; height:42px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'Playfair Display',serif; font-size:18px; color:var(--sidebar-bg); font-weight:700; flex-shrink:0; }
        .sidebar-brand-text strong { display:block; color:#fff; font-size:14px; font-weight:600; }
        .sidebar-brand-text span { font-size:11px; color:var(--accent-light); opacity:.75; }
        .sidebar-nav { padding:20px 12px; flex:1; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:8px; color:rgba(255,255,255,.65); font-size:13.5px; font-weight:500; text-decoration:none; margin-bottom:2px; transition:background .18s,color .18s; }
        .nav-item:hover { background:rgba(255,255,255,.08); color:#fff; }
        .nav-item.active { background:var(--accent); color:var(--sidebar-bg); font-weight:600; }
        .sidebar-footer { padding:16px 12px; border-top:1px solid rgba(255,255,255,.1); }

        /* MAIN */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { background:var(--body-bg); padding:18px 28px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; border-bottom:1px solid var(--border); }
        .topbar-title { font-family:'Playfair Display',serif; font-size:22px; color:var(--text-dark); }
        .topbar-right { display:flex; align-items:center; gap:14px; }
        .search-box { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:7px 14px; width:260px; }
        .search-box input { border:none; outline:none; font-size:13px; color:var(--text-dark); background:transparent; width:100%; font-family:'DM Sans',sans-serif; }
        .date-pill { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:7px 14px; font-size:13px; color:var(--text-mid); display:flex; align-items:center; gap:6px; }

        .content { padding:28px; flex:1; }

        /* STAT CARDS */
        .stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        .stat-card { background:var(--surface); border-radius:var(--radius); padding:20px 24px; box-shadow:var(--shadow); }
        .stat-label { font-size:12px; color:var(--text-light); font-weight:500; text-transform:uppercase; letter-spacing:.4px; margin-bottom:8px; }
        .stat-value { font-size:28px; font-weight:700; color:var(--text-dark); }
        .stat-value.green { color:#17864B; }
        .stat-value.gold  { color:var(--accent); }

        /* ORDERS TABLE CARD */
        .orders-card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .orders-card-header { display:flex; align-items:center; justify-content:space-between; padding:18px 20px; border-bottom:1px solid var(--border); }
        .orders-card-title { font-size:16px; font-weight:600; color:var(--text-dark); }
        .filter-row { display:flex; align-items:center; gap:8px; }
        .filter-select { padding:7px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; font-family:'DM Sans',sans-serif; color:var(--text-dark); background:var(--body-bg); outline:none; cursor:pointer; }
        .btn-refresh { display:flex; align-items:center; gap:6px; padding:7px 14px; background:var(--accent); border:none; border-radius:8px; font-size:13px; font-weight:600; font-family:'DM Sans',sans-serif; color:var(--sidebar-bg); cursor:pointer; transition:background .15s; }
        .btn-refresh:hover { background:#a8843e; }

        table { width:100%; border-collapse:collapse; }
        thead th { font-size:11.5px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); padding:10px 16px; background:var(--body-bg); text-align:left; border-bottom:1px solid var(--border); }
        tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#FAF8F5; }
        tbody td { padding:12px 16px; font-size:13.5px; color:var(--text-dark); }

        .badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-ready     { background:#D4F5E3; color:#17864B; }
        .badge-preparing { background:#DBEAFE; color:#1D4ED8; }
        .badge-pending   { background:#FEF9C3; color:#854D0E; }
        .badge-completed { background:#E5E7EB; color:#374151; }

        .status-select { padding:5px 10px; border:1px solid var(--border); border-radius:8px; font-size:12px; font-family:'DM Sans',sans-serif; color:var(--text-dark); background:var(--body-bg); cursor:pointer; outline:none; }

        .empty-row td { text-align:center; padding:40px; color:var(--text-light); font-size:14px; }

        .toast { position:fixed; bottom:24px; right:24px; background:var(--sidebar-bg); color:#fff; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:500; box-shadow:0 4px 20px rgba(0,0,0,.2); transform:translateY(80px); opacity:0; transition:all .3s; z-index:999; }
        .toast.show { transform:translateY(0); opacity:1; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">K</div>
        <div class="sidebar-brand-text">
            <strong>King's Cup</strong>
            <span>Admin Dashboard</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="instoreorders.php" class="nav-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            Instore Orders
        </a>
        <a href="processedorders.php" class="nav-item active">
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

<!-- MAIN -->
<div class="main">
    <header class="topbar">
        <span class="topbar-title">Processed Orders</span>
        <div class="topbar-right">
            <div class="search-box">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="Search customer, order, items..." oninput="filterTable()">
            </div>
            <div class="date-pill">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Today
            </div>
        </div>
    </header>

    <div class="content">

        <!-- STAT CARDS -->
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-label">Processed Orders</div>
                <div class="stat-value"><?php echo $processed_data['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed Orders</div>
                <div class="stat-value green"><?php echo $completed_data['total']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today's Sales</div>
                <div class="stat-value gold">₱<?php echo number_format($sales_data['total'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- ORDERS TABLE -->
        <div class="orders-card">
            <div class="orders-card-header">
                <span class="orders-card-title">Orders List</span>
                <div class="filter-row">
                    <select class="filter-select" onchange="location.href='processedorders.php?filter='+this.value">
                        <option value="all"       <?= $filter==='all'       ? 'selected':'' ?>>All Orders</option>
                        <option value="Pending"   <?= $filter==='Pending'   ? 'selected':'' ?>>Pending</option>
                        <option value="Preparing" <?= $filter==='Preparing' ? 'selected':'' ?>>Preparing</option>
                        <option value="Ready"     <?= $filter==='Ready'     ? 'selected':'' ?>>Ready</option>
                        <option value="Completed" <?= $filter==='Completed' ? 'selected':'' ?>>Completed</option>
                    </select>
                    <button class="btn-refresh" onclick="location.reload()">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                        Refresh
                    </button>
                </div>
            </div>

            <table id="ordersTable">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Items Ordered</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th>Payment Status</th>
                        <th>Order Status</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($orders_query) > 0): ?>
                        <?php while ($order = mysqli_fetch_assoc($orders_query)):
                            $status = strtolower($order['status'] ?? 'pending');
                            $badge_class = match($status) {
                                'ready'     => 'badge-ready',
                                'preparing' => 'badge-preparing',
                                'completed' => 'badge-completed',
                                default     => 'badge-pending',
                            };
                        ?>
                        <tr data-search="<?= strtolower(htmlspecialchars($order['customer_name'].$order['items'].$order['mobile'])) ?>">
                            <td><?= str_pad($order['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td><?= htmlspecialchars($order['customer_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($order['mobile'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($order['items'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($order['payment_method'] ?? '—') ?></td>
                            <td>₱<?= number_format($order['total'], 2) ?></td>
                            <td><?= htmlspecialchars($order['payment_status'] ?? 'Paid') ?></td>
                            <td>
                                <select class="status-select" onchange="updateStatus(<?= $order['id'] ?>, this.value, this)">
                                    <option value="Pending"   <?= $order['status']==='Pending'   ? 'selected':'' ?>>Pending</option>
                                    <option value="Preparing" <?= $order['status']==='Preparing' ? 'selected':'' ?>>Preparing</option>
                                    <option value="Ready"     <?= $order['status']==='Ready'     ? 'selected':'' ?>>Ready</option>
                                    <option value="Completed" <?= $order['status']==='Completed' ? 'selected':'' ?>>Completed</option>
                                </select>
                            </td>
                            <td><?= date('M d, g:i A', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="empty-row"><td colspan="9">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#ordersTable tbody tr:not(.empty-row)').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}

async function updateStatus(orderId, status, selectEl) {
    const res  = await fetch('processedorders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_status&order_id=${orderId}&status=${encodeURIComponent(status)}`
    });
    const data = await res.json();
    showToast(data.success ? `Order #${String(orderId).padStart(3,'0')} → ${status}` : 'Update failed.');
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}
</script>
</body>
</html>