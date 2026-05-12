<?php
require_once '../includes/config.php';
global $conn;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle AJAX stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_stock') {
        $id     = (int)$_POST['product_id'];
        $stock  = (int)$_POST['stock'];
        $status = $_POST['status'];
        $allowed_status = ['In Stock', 'Low Stock', 'Out of Stock'];

        if (!in_array($status, $allowed_status)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "UPDATE products SET stock = ?, status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "isi", $stock, $status, $id);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'add_product') {
        $name     = trim($_POST['name']);
        $price    = (float)$_POST['price'];
        $category = trim($_POST['category']) ?: 'Other';
        $stock    = (int)$_POST['stock'];
        $status   = $stock > 0 ? ($stock <= 5 ? 'Low Stock' : 'In Stock') : 'Out of Stock';

        if (!$name || $price <= 0) {
            echo json_encode(['success' => false, 'error' => 'Name and price are required.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, price, category, stock, status) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sdsiss", $name, $price, $category, $stock, $status);
        // fix bind: price is double
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, price, category, stock, status) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sdsis", $name, $price, $category, $stock, $status);
        mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conn);
        echo json_encode(['success' => true, 'id' => $new_id]);
        exit;
    }

    if ($_POST['action'] === 'delete_product') {
        $id   = (int)$_POST['product_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Stats
$total_q    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products"));
$instock_q  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status = 'In Stock'"));
$low_q      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status = 'Low Stock'"));
$out_q      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE status = 'Out of Stock'"));

// Products
$filter   = $_GET['filter'] ?? 'all';
$search   = trim($_GET['search'] ?? '');
$where    = [];
if ($filter !== 'all') $where[] = "status = '" . mysqli_real_escape_string($conn, $filter) . "'";
if ($search)           $where[] = "name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$products_query = mysqli_query($conn, "SELECT * FROM products $where_sql ORDER BY category, name");
$products = [];
while ($p = mysqli_fetch_assoc($products_query)) $products[] = $p;
$by_cat = [];
foreach ($products as $p) $by_cat[$p['category'] ?? 'Other'][] = $p;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management – King's Cup</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --sidebar-bg: #3B1F0F; --sidebar-w: 220px;
            --accent: #C8A96E; --accent-dark: #a8843e; --accent-light: #F0E0C0;
            --body-bg: #F0EDE8; --surface: #FFFFFF;
            --text-dark: #2A1A0A; --text-mid: #6B5744; --text-light: #A89282;
            --border: #E2D9CF; --red: #E74C3C;
            --radius: 12px; --shadow: 0 2px 12px rgba(60,30,10,.08);
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); min-height: 100vh; display: flex; }

        /* SIDEBAR */
        .sidebar { width:var(--sidebar-w); background:var(--sidebar-bg); min-height:100vh; display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; }
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
        .topbar-title { font-family:'Playfair Display',serif; font-size:22px; }
        .topbar-right { display:flex; align-items:center; gap:12px; }
        .search-box { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:7px 14px; width:240px; }
        .search-box input { border:none; outline:none; font-size:13px; color:var(--text-dark); background:transparent; width:100%; font-family:'DM Sans',sans-serif; }
        .date-pill { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:7px 14px; font-size:13px; color:var(--text-mid); display:flex; align-items:center; gap:6px; }
        .btn-add { display:flex; align-items:center; gap:6px; padding:8px 16px; background:var(--accent); border:none; border-radius:8px; font-size:13px; font-weight:600; font-family:'DM Sans',sans-serif; color:var(--sidebar-bg); cursor:pointer; transition:background .15s; }
        .btn-add:hover { background:var(--accent-dark); }

        .content { padding:28px; flex:1; }

        /* STAT CARDS */
        .stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        .stat-card { background:var(--surface); border-radius:var(--radius); padding:18px 20px; box-shadow:var(--shadow); }
        .stat-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); margin-bottom:8px; }
        .stat-value { font-size:26px; font-weight:700; color:var(--text-dark); }
        .stat-value.green { color:#17864B; }
        .stat-value.amber { color:#D97706; }
        .stat-value.red   { color:var(--red); }

        /* FILTER PILLS */
        .filter-pills { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:22px; }
        .filter-pill { padding:6px 16px; border-radius:20px; font-size:12.5px; font-weight:500; border:1.5px solid var(--border); background:var(--surface); color:var(--text-mid); cursor:pointer; text-decoration:none; transition:all .15s; }
        .filter-pill:hover { border-color:var(--accent); color:var(--accent-dark); }
        .filter-pill.active { background:var(--accent); border-color:var(--accent); color:var(--sidebar-bg); font-weight:700; }

        /* PRODUCT CARDS */
        .section-title { font-family:'Playfair Display',serif; font-size:16px; color:var(--text-dark); margin-bottom:14px; display:flex; align-items:center; gap:10px; }
        .section-title::after { content:''; flex:1; height:1px; background:var(--border); }
        .products-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:28px; }

        .product-card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .product-card img { width:100%; height:130px; object-fit:cover; display:block; }
        .product-card-body { padding:14px; }
        .product-name { font-size:14px; font-weight:600; margin-bottom:3px; }
        .product-price { font-size:13px; color:var(--text-mid); font-weight:600; margin-bottom:10px; }

        .stock-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .qty-btn { width:26px; height:26px; border-radius:6px; border:1px solid var(--border); background:var(--body-bg); font-size:15px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .12s; }
        .qty-btn:hover { background:var(--accent-light); }
        .qty-input { width:48px; text-align:center; border:1px solid var(--border); border-radius:6px; padding:4px; font-size:13px; font-family:'DM Sans',sans-serif; }
        .status-select { width:100%; padding:5px 8px; border:1px solid var(--border); border-radius:6px; font-size:12px; font-family:'DM Sans',sans-serif; background:var(--body-bg); margin-bottom:8px; }

        .stock-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:600; margin-bottom:10px; }
        .stock-badge.in    { background:#D4F5E3; color:#17864B; }
        .stock-badge.low   { background:#FEF9C3; color:#854D0E; }
        .stock-badge.out   { background:#FEE2E2; color:#991B1B; }

        .btn-save { width:100%; padding:8px; background:var(--accent); border:none; border-radius:8px; font-size:13px; font-weight:700; font-family:'DM Sans',sans-serif; color:var(--sidebar-bg); cursor:pointer; transition:background .15s; }
        .btn-save:hover { background:var(--accent-dark); }
        .btn-delete { width:100%; padding:6px; background:transparent; border:1px solid #fcc; border-radius:8px; font-size:12px; font-weight:600; font-family:'DM Sans',sans-serif; color:var(--red); cursor:pointer; margin-top:5px; transition:background .15s; }
        .btn-delete:hover { background:#fff5f5; }

        .empty-msg { text-align:center; padding:60px 20px; color:var(--text-light); font-size:14px; }

        /* MODAL */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(30,10,0,.5); z-index:300; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.open { display:flex; }
        .modal { background:var(--surface); border-radius:16px; width:100%; max-width:420px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.25); }
        .modal-head { background:var(--sidebar-bg); padding:20px 24px; }
        .modal-head h3 { font-family:'Playfair Display',serif; font-size:18px; color:#fff; }
        .modal-body { padding:22px 24px; display:flex; flex-direction:column; gap:14px; }
        .field label { display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); margin-bottom:6px; }
        .field input, .field select { width:100%; padding:10px 14px; border:1.5px solid var(--border); border-radius:8px; font-size:14px; font-family:'DM Sans',sans-serif; color:var(--text-dark); background:var(--body-bg); outline:none; transition:border-color .15s; }
        .field input:focus, .field select:focus { border-color:var(--accent); background:#fff; }
        .modal-foot { display:flex; gap:10px; padding:0 24px 22px; }
        .btn-mcancel { flex:1; padding:11px; background:var(--body-bg); border:1px solid var(--border); border-radius:8px; font-size:13px; font-weight:600; font-family:'DM Sans',sans-serif; color:var(--text-mid); cursor:pointer; }
        .btn-mconfirm { flex:1; padding:11px; background:var(--accent); border:none; border-radius:8px; font-size:13px; font-weight:700; font-family:'DM Sans',sans-serif; color:var(--sidebar-bg); cursor:pointer; }
        .btn-mconfirm:hover { background:var(--accent-dark); }

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
        <a href="processedorders.php" class="nav-item">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Processed Orders
        </a>
        <a href="stocks.php" class="nav-item active">
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
        <span class="topbar-title">Stock Management</span>
        <div class="topbar-right">
            <div class="search-box">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" placeholder="Search products..."
                    value="<?= htmlspecialchars($search) ?>"
                    onchange="location.href='stocks.php?search='+encodeURIComponent(this.value)+'&filter=<?= $filter ?>'">
            </div>
            <div class="date-pill">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= date('M d, Y') ?>
            </div>
            <button class="btn-add" onclick="document.getElementById('addModal').classList.add('open')">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Product
            </button>
        </div>
    </header>

    <div class="content">

        <!-- STAT CARDS -->
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?= $total_q['total'] ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:4px;">Menu Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">In Stock</div>
                <div class="stat-value green"><?= $instock_q['total'] ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:4px;">Available now</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Low Stock</div>
                <div class="stat-value amber"><?= $low_q['total'] ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:4px;">Need restock soon</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Out of Stock</div>
                <div class="stat-value red"><?= $out_q['total'] ?></div>
                <div style="font-size:11px;color:var(--text-light);margin-top:4px;">Unavailable</div>
            </div>
        </div>

        <!-- FILTER PILLS -->
        <div class="filter-pills">
            <a href="stocks.php" class="filter-pill <?= $filter==='all' ? 'active':'' ?>">All Products</a>
            <a href="stocks.php?filter=In+Stock" class="filter-pill <?= $filter==='In Stock' ? 'active':'' ?>">In Stock</a>
            <a href="stocks.php?filter=Low+Stock" class="filter-pill <?= $filter==='Low Stock' ? 'active':'' ?>">Low Stock</a>
            <a href="stocks.php?filter=Out+of+Stock" class="filter-pill <?= $filter==='Out of Stock' ? 'active':'' ?>">Out of Stock</a>
        </div>

        <!-- PRODUCTS BY CATEGORY -->
        <?php if (empty($products)): ?>
            <div class="empty-msg">No products found.</div>
        <?php else: ?>
            <?php foreach ($by_cat as $cat => $items): ?>
            <div class="section-title"><?= htmlspecialchars($cat) ?></div>
            <div class="products-grid">
                <?php foreach ($items as $p):
                    $badge_class = match($p['status']) {
                        'In Stock'  => 'in',
                        'Low Stock' => 'low',
                        default     => 'out',
                    };
                    $img = htmlspecialchars($p['image'] ?? '');
                ?>
                <div class="product-card" id="pcard-<?= $p['id'] ?>">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['name']) ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&q=80'">
                    <div class="product-card-body">
                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="product-price">₱<?= number_format($p['price'], 2) ?></div>
                        <span class="stock-badge <?= $badge_class ?>" id="sbadge-<?= $p['id'] ?>"><?= $p['status'] ?></span>

                        <div class="stock-row">
                            <button class="qty-btn" onclick="adjustQty(<?= $p['id'] ?>,-1)">−</button>
                            <input class="qty-input" type="number" id="qty-<?= $p['id'] ?>"
                                   value="<?= $p['stock'] ?>" min="0" oninput="syncStatus(<?= $p['id'] ?>)">
                            <button class="qty-btn" onclick="adjustQty(<?= $p['id'] ?>,1)">+</button>
                        </div>

                        <select class="status-select" id="status-<?= $p['id'] ?>" onchange="syncBadge(<?= $p['id'] ?>)">
                            <option value="In Stock"    <?= $p['status']==='In Stock'    ? 'selected':'' ?>>In Stock</option>
                            <option value="Low Stock"   <?= $p['status']==='Low Stock'   ? 'selected':'' ?>>Low Stock</option>
                            <option value="Out of Stock"<?= $p['status']==='Out of Stock'? 'selected':'' ?>>Out of Stock</option>
                        </select>

                        <?php if ($p['stock'] == 0 && $p['status'] === 'Out of Stock'): ?>
                        <div style="font-size:11px;color:var(--red);margin-bottom:8px;">✕ Out of Stock (0 available)</div>
                        <?php endif; ?>

                        <button class="btn-save" onclick="saveStock(<?= $p['id'] ?>)">Save Changes</button>
                        <button class="btn-delete" onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<!-- ADD PRODUCT MODAL -->
<div id="addModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-head"><h3>Add New Product</h3></div>
        <div class="modal-body">
            <div class="field"><label>Product Name</label><input type="text" id="newName" placeholder="e.g. Matcha Latte"></div>
            <div class="field"><label>Price (₱)</label><input type="number" id="newPrice" placeholder="0.00" step="0.01" min="0"></div>
            <div class="field"><label>Category</label><input type="text" id="newCategory" placeholder="e.g. Coffee, Frappe" value="Other"></div>
            <div class="field"><label>Initial Stock</label><input type="number" id="newStock" placeholder="0" min="0" value="0"></div>
        </div>
        <div class="modal-foot">
            <button class="btn-mcancel" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
            <button class="btn-mconfirm" onclick="addProduct()">Add Product</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function adjustQty(id, delta) {
    const input = document.getElementById('qty-' + id);
    input.value = Math.max(0, parseInt(input.value || 0) + delta);
    syncStatus(id);
}

function syncStatus(id) {
    const qty = parseInt(document.getElementById('qty-' + id).value || 0);
    const sel = document.getElementById('status-' + id);
    if (qty === 0)      sel.value = 'Out of Stock';
    else if (qty <= 5)  sel.value = 'Low Stock';
    else                sel.value = 'In Stock';
    syncBadge(id);
}

function syncBadge(id) {
    const status = document.getElementById('status-' + id).value;
    const badge  = document.getElementById('sbadge-' + id);
    badge.className = 'stock-badge ' + (status === 'In Stock' ? 'in' : status === 'Low Stock' ? 'low' : 'out');
    badge.textContent = status;
}

async function saveStock(id) {
    const stock  = parseInt(document.getElementById('qty-' + id).value || 0);
    const status = document.getElementById('status-' + id).value;

    const res  = await fetch('stocks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_stock&product_id=${id}&stock=${stock}&status=${encodeURIComponent(status)}`
    });
    const data = await res.json();
    showToast(data.success ? 'Stock updated!' : 'Update failed.');
}

async function deleteProduct(id, name) {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    const res  = await fetch('stocks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_product&product_id=${id}`
    });
    const data = await res.json();
    if (data.success) {
        document.getElementById('pcard-' + id)?.remove();
        showToast(`"${name}" deleted.`);
    }
}

async function addProduct() {
    const name     = document.getElementById('newName').value.trim();
    const price    = parseFloat(document.getElementById('newPrice').value);
    const category = document.getElementById('newCategory').value.trim() || 'Other';
    const stock    = parseInt(document.getElementById('newStock').value || 0);

    if (!name || !price) { showToast('Name and price are required.'); return; }

    const res  = await fetch('stocks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add_product&name=${encodeURIComponent(name)}&price=${price}&category=${encodeURIComponent(category)}&stock=${stock}`
    });
    const data = await res.json();
    if (data.success) {
        showToast(`"${name}" added! Refreshing...`);
        document.getElementById('addModal').classList.remove('open');
        setTimeout(() => location.reload(), 1000);
    } else {
        showToast(data.error || 'Failed to add product.');
    }
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