<?php

require_once '../includes/config.php';
global $conn;
$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) + 1 AS next_id FROM orders"));
$next_order = $row['next_id'] ?? 1;

$result = mysqli_query($conn, "SELECT * FROM products ORDER BY category, name");
$products = [];
while ($p = mysqli_fetch_assoc($result)) $products[] = $p;

$categories = array_unique(array_column($products, 'category'));
$by_cat = [];
foreach ($products as $p) $by_cat[$p['category'] ?? 'Other'][] = $p;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instore Orders – King's Cup</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --sidebar-bg:   #3B1F0F;
      --sidebar-w:    220px;
      --accent:       #C8A96E;
      --accent-dark:  #a8843e;
      --accent-light: #F0E0C0;
      --body-bg:      #F0EDE8;
      --surface:      #FFFFFF;
      --text-dark:    #2A1A0A;
      --text-mid:     #6B5744;
      --text-light:   #A89282;
      --border:       #E2D9CF;
      --red:          #E74C3C;
      --radius:       12px;
      --shadow:       0 2px 14px rgba(60,30,10,.09);
    }

    body { font-family: 'DM Sans', sans-serif; background: var(--body-bg); color: var(--text-dark); min-height: 100vh; display: flex; }

    /* SIDEBAR */
    .sidebar { width: var(--sidebar-w); background: var(--sidebar-bg); min-height: 100vh; display: flex; flex-direction: column; position: fixed; top:0; left:0; bottom:0; z-index:100; }
    .sidebar-brand { display:flex; align-items:center; gap:10px; padding:22px 20px 20px; border-bottom:1px solid rgba(200, 157, 157, 0.1); }
    .sidebar-logo { width:42px; height:42px; background:var(--accent); border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'Playfair Display',serif; font-size:18px; color:var(--sidebar-bg); font-weight:700; flex-shrink:0; }
    .sidebar-brand-text strong { display:block; color:#fff; font-size:14px; font-weight:600; }
    .sidebar-brand-text span { font-size:11px; color:var(--accent-light); opacity:.75; }
    .sidebar-nav { padding:20px 12px; flex:1; }
    .nav-item { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:8px; color:rgba(255,255,255,.65); font-size:13.5px; font-weight:500; text-decoration:none; margin-bottom:2px; transition:background .18s,color .18s; }
    .nav-item:hover { background:rgba(255,255,255,.08); color:#fff; }
    .nav-item.active { background:var(--accent); color:var(--sidebar-bg); font-weight:600; }
    .sidebar-footer { padding:16px 12px; border-top:1px solid rgba(255,255,255,.1); }

    /* MAIN */
    .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }

    /* TOPBAR */
    .topbar { background:var(--body-bg); padding:16px 28px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; border-bottom:1px solid var(--border); }
    .topbar-title { font-family:'Playfair Display',serif; font-size:22px; color:var(--text-dark); }
    .topbar-right { display:flex; align-items:center; gap:12px; }
    .search-box { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:7px 14px; width:260px; }
    .search-box input { border:none; outline:none; font-size:13px; color:var(--text-dark); background:transparent; width:100%; font-family:'DM Sans',sans-serif; }
    .date-pill { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:7px 14px; font-size:13px; color:var(--text-mid); display:flex; align-items:center; gap:6px; }
    .notif-btn { width:36px; height:36px; background:var(--surface); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; }
    .notif-dot { position:absolute; top:6px; right:6px; width:8px; height:8px; background:var(--red); border-radius:50%; border:2px solid var(--body-bg); }

    /* PAGE GRID */
    .page { display:grid; grid-template-columns:1fr 320px; flex:1; min-height:0; }

    /* MENU AREA */
    .menu-area { padding:22px 24px 40px; overflow-y:auto; }
    .menu-toprow { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .order-tag { display:flex; align-items:center; gap:8px; }
    .order-tag-label { font-size:13px; color:var(--text-light); font-weight:500; }
    .order-tag-num { font-family:'Playfair Display',serif; font-size:18px; color:var(--text-dark); background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px 14px; box-shadow:var(--shadow); }

    .cat-pills { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:20px; }
    .cat-pill { padding:5px 15px; border-radius:20px; font-size:12.5px; font-weight:500; border:1.5px solid var(--border); background:var(--surface); color:var(--text-mid); cursor:pointer; transition:all .15s; font-family:'DM Sans',sans-serif; }
    .cat-pill:hover { border-color:var(--accent); color:var(--accent-dark); }
    .cat-pill.active { background:var(--accent); border-color:var(--accent); color:var(--sidebar-bg); font-weight:700; }

    .menu-section { margin-bottom:28px; }
    .section-title { font-family:'Playfair Display',serif; font-size:16px; color:var(--text-dark); margin-bottom:14px; display:flex; align-items:center; gap:10px; }
    .section-title::after { content:''; flex:1; height:1px; background:var(--border); }

    .menu-row { display:grid; grid-template-columns:repeat(auto-fill,minmax(148px,1fr)); gap:12px; }

    .menu-card { background:var(--surface); border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); cursor:pointer; transition:transform .18s,box-shadow .18s,border-color .15s; border:2px solid transparent; position:relative; }
    .menu-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(60,30,10,.14); }
    .menu-card.selected { border-color:var(--accent); }
    .menu-card-badge { display:none; position:absolute; top:8px; right:8px; background:var(--accent); color:var(--sidebar-bg); font-size:11px; font-weight:700; border-radius:20px; padding:2px 8px; box-shadow:0 2px 6px rgba(0,0,0,.15); }
    .menu-card.selected .menu-card-badge { display:block; }
    .menu-card img { width:100%; height:110px; object-fit:cover; display:block; }
    .menu-card-info { padding:10px 11px 12px; }
    .menu-card-name { font-size:12.5px; font-weight:600; color:var(--text-dark); line-height:1.3; margin-bottom:3px; }
    .menu-card-price { font-size:13px; font-weight:700; color:var(--text-mid); }

    /* ORDER PANEL */
    .order-panel { background:var(--surface); border-left:1px solid var(--border); display:flex; flex-direction:column; position:sticky; top:69px; height:calc(100vh - 69px); overflow:hidden; }
    .order-panel-top { background:var(--sidebar-bg); padding:18px 20px; flex-shrink:0; }
    .order-panel-top h2 { font-family:'Playfair Display',serif; font-size:17px; color:#fff; }
    .order-panel-top p { font-size:12px; color:var(--accent-light); opacity:.8; margin-top:2px; }
    .order-items { flex:1; overflow-y:auto; padding:14px 18px; }
    .cart-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; gap:10px; color:var(--text-light); }
    .cart-empty svg { opacity:.3; }
    .cart-empty p { font-size:13px; }
    .cart-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); }
    .cart-row:last-child { border-bottom:none; }
    .cart-row-img { width:44px; height:44px; border-radius:8px; object-fit:cover; flex-shrink:0; background:var(--body-bg); }
    .cart-row-info { flex:1; min-width:0; }
    .cart-row-name { font-size:13px; font-weight:600; color:var(--text-dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cart-row-sub { font-size:11.5px; color:var(--text-light); margin-top:1px; }
    .cart-row-controls { display:flex; align-items:center; gap:6px; flex-shrink:0; }
    .qty-btn { width:24px; height:24px; border-radius:6px; border:1px solid var(--border); background:var(--body-bg); font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-dark); transition:background .12s; line-height:1; }
    .qty-btn:hover { background:var(--accent-light); }
    .qty-num { font-size:13px; font-weight:700; min-width:18px; text-align:center; }
    .order-footer { flex-shrink:0; padding:14px 18px 20px; border-top:1px solid var(--border); background:var(--surface); }
    .totals { margin-bottom:14px; }
    .total-row { display:flex; justify-content:space-between; margin-bottom:5px; }
    .total-row span:first-child { font-size:12.5px; color:var(--text-light); }
    .total-row span:last-child { font-size:12.5px; color:var(--text-dark); font-weight:500; }
    .grand-row { display:flex; justify-content:space-between; margin-top:10px; padding-top:10px; border-top:1px dashed var(--border); }
    .grand-row span:first-child { font-size:15px; font-weight:700; color:var(--text-dark); }
    .grand-row span:last-child { font-size:20px; font-weight:700; color:var(--sidebar-bg); }
    .btn-checkout { width:100%; padding:13px; background:var(--accent); border:none; border-radius:10px; font-size:14px; font-weight:700; font-family:'DM Sans',sans-serif; color:var(--sidebar-bg); cursor:pointer; transition:background .15s,transform .1s; margin-bottom:8px; letter-spacing:.2px; }
    .btn-checkout:hover { background:var(--accent-dark); transform:translateY(-1px); }
    .btn-clear { width:100%; padding:10px; background:transparent; border:1px solid #fcc; border-radius:10px; font-size:13px; font-weight:600; font-family:'DM Sans',sans-serif; color:var(--red); cursor:pointer; transition:background .15s; }
    .btn-clear:hover { background:#fff5f5; }

    /* MODAL */
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(30,10,0,.5); z-index:300; align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal { background:var(--surface); border-radius:16px; width:100%; max-width:460px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.25); animation:slideUp .22s ease; }
    @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    .modal-head { background:var(--sidebar-bg); padding:20px 24px; }
    .modal-head h3 { font-family:'Playfair Display',serif; font-size:19px; color:#fff; }
    .modal-head p { font-size:12px; color:var(--accent-light); opacity:.8; margin-top:3px; }
    .modal-body { padding:22px 24px; }
    .field { margin-bottom:14px; }
    .field label { display:block; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); margin-bottom:6px; }
    .field input, .field select { width:100%; padding:10px 14px; border:1.5px solid var(--border); border-radius:8px; font-size:14px; font-family:'DM Sans',sans-serif; color:var(--text-dark); background:var(--body-bg); outline:none; transition:border-color .15s,background .15s; }
    .field input:focus, .field select:focus { border-color:var(--accent); background:#fff; }
    .modal-summary { background:var(--body-bg); border-radius:10px; padding:14px 16px; margin-top:4px; }
    .modal-summary-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-light); margin-bottom:10px; }
    .sum-row { display:flex; justify-content:space-between; font-size:13px; color:var(--text-mid); margin-bottom:5px; }
    .sum-total { display:flex; justify-content:space-between; font-size:15px; font-weight:700; color:var(--sidebar-bg); padding-top:10px; margin-top:8px; border-top:1px solid var(--border); }
    .modal-error { display:none; background:#fff0f0; border:1px solid #fcc; color:var(--red); border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:14px; }
    .modal-error.show { display:block; }
    .modal-foot { display:flex; gap:10px; padding:0 24px 22px; }
    .btn-mcancel { flex:1; padding:11px; background:var(--body-bg); border:1px solid var(--border); border-radius:8px; font-size:13px; font-weight:600; font-family:'DM Sans',sans-serif; color:var(--text-mid); cursor:pointer; transition:background .15s; }
    .btn-mcancel:hover { background:var(--border); }
    .btn-mconfirm { flex:1; padding:11px; background:var(--accent); border:none; border-radius:8px; font-size:13px; font-weight:700; font-family:'DM Sans',sans-serif; color:var(--sidebar-bg); cursor:pointer; transition:background .15s; }
    .btn-mconfirm:hover { background:var(--accent-dark); }
    .btn-mconfirm:disabled { opacity:.6; cursor:not-allowed; }

    /* SUCCESS */
    .success-body { text-align:center; padding:36px 24px; }
    .success-circle { width:68px; height:68px; background:#D4F5E3; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
    .success-body h3 { font-family:'Playfair Display',serif; font-size:22px; color:var(--text-dark); margin-bottom:8px; }
    .success-body p { font-size:14px; color:var(--text-light); margin-bottom:24px; }
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
    <a href="instoreorders.php" class="nav-item active">
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

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <span class="topbar-title">Instore Orders</span>
    <div class="topbar-right">
      <div class="search-box">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="menuSearch" placeholder="Search orders, Customers, Menu Items..." oninput="filterMenu()">
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

  <div class="page">
    <!-- MENU -->
    <div class="menu-area">
      <div class="menu-toprow">
        <div class="order-tag">
          <span class="order-tag-label">Order no:</span>
          <span class="order-tag-num">#<?= str_pad($next_order, 3, '0', STR_PAD_LEFT) ?></span>
        </div>
      </div>

      <div class="cat-pills">
        <button class="cat-pill active" data-cat="all" onclick="setCategory(this)">All</button>
        <?php foreach ($categories as $cat): ?>
          <button class="cat-pill" data-cat="<?= htmlspecialchars($cat) ?>" onclick="setCategory(this)"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($by_cat as $cat => $items): ?>
      <div class="menu-section" data-section="<?= htmlspecialchars($cat) ?>">
        <div class="section-title"><?= htmlspecialchars($cat) ?></div>
        <div class="menu-row">
          <?php foreach ($items as $p):
            $img = htmlspecialchars($p['image_url'] ?? $p['image'] ?? '');
          ?>
          <div class="menu-card" id="card-<?= $p['id'] ?>"
            data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
            data-category="<?= htmlspecialchars($p['category']) ?>"
            data-img="<?= $img ?>"
            onclick="addToCart(<?= $p['id'] ?>,'<?= htmlspecialchars(addslashes($p['name'])) ?>',<?= $p['price'] ?>,'<?= $img ?>')">
            <span class="menu-card-badge" id="badge-<?= $p['id'] ?>">×1</span>
            <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['name']) ?>"
              onerror="this.src='https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&q=80'">
            <div class="menu-card-info">
              <div class="menu-card-name"><?= htmlspecialchars($p['name']) ?></div>
              <div class="menu-card-price">₱<?= number_format($p['price'], 2) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($products)): ?>
        <p style="color:var(--text-light);text-align:center;padding:60px 0;">No products found.</p>
      <?php endif; ?>
    </div>

    <!-- ORDER PANEL -->
    <div class="order-panel">
      <div class="order-panel-top">
        <h2>Current Order</h2>
        <p>#<?= str_pad($next_order, 3, '0', STR_PAD_LEFT) ?> &nbsp;·&nbsp; <span id="itemCount">0 items</span></p>
      </div>
      <div class="order-items" id="cartItems">
        <div class="cart-empty">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
          <p>Tap a menu item to add</p>
        </div>
      </div>
      <div class="order-footer">
        <div class="totals">
          <div class="total-row"><span>Subtotal</span><span id="subtotal">₱0</span></div>
          <div class="grand-row"><span>Total</span><span id="total">₱0</span></div>
        </div>
        <button class="btn-checkout" onclick="openPaymentModal()">Proceed to Payment &nbsp;→</button>
        <button class="btn-clear" onclick="clearCart()">Cancel Order</button>
      </div>
    </div>
  </div>
</div>

<!-- PAYMENT MODAL -->
<div id="paymentModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-head">
      <h3>Customer &amp; Payment</h3>
      <p>Complete the details to confirm the order</p>
    </div>
    <div class="modal-body">
      <div id="modalError" class="modal-error"></div>
      <div class="field"><label>Customer Name</label><input id="customerName" type="text" placeholder="Enter customer name"></div>
      <div class="field"><label>Mobile Number</label><input id="customerMobile" type="text" placeholder="e.g. 09123456789"></div>
      <div class="field">
        <label>Payment Method</label>
        <select id="paymentMethod">
          <option value="">-- Select --</option>
          <option value="Cash">Cash</option>
          <option value="GCash">GCash</option>
          <option value="InstaPay">InstaPay</option>
          <option value="Card">Card</option>
        </select>
      </div>
      <div class="modal-summary">
        <div class="modal-summary-label">Order Summary</div>
        <div id="modalSummary"></div>
        <div class="sum-total"><span>Total</span><span id="modalTotal">₱0</span></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-mcancel" onclick="closePaymentModal()">Cancel</button>
      <button id="confirmBtn" class="btn-mconfirm" onclick="submitOrder()">Confirm Order</button>
    </div>
  </div>
</div>

<!-- SUCCESS MODAL -->
<div id="successModal" class="modal-overlay">
  <div class="modal">
    <div class="success-body">
      <div class="success-circle">
        <svg width="30" height="30" fill="none" stroke="#17864B" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <h3>Order Placed!</h3>
      <p id="successMsg">Your order has been saved.</p>
      <button class="btn-checkout" style="max-width:200px;margin:0 auto;" onclick="closeSuccessModal()">New Order</button>
    </div>
  </div>
</div>

<script>
let cart = {}, cartImages = {};

function addToCart(id, name, price, img) {
  cart[id] ? cart[id].qty++ : (cart[id] = {name, price, qty:1}, cartImages[id] = img);
  document.getElementById('card-'+id)?.classList.add('selected');
  const b = document.getElementById('badge-'+id);
  if (b) b.textContent = '×' + cart[id].qty;
  renderCart();
}

function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) {
    delete cart[id];
    document.getElementById('card-'+id)?.classList.remove('selected');
  } else {
    const b = document.getElementById('badge-'+id);
    if (b) b.textContent = '×' + cart[id].qty;
  }
  renderCart();
}

function clearCart() {
  Object.keys(cart).forEach(id => document.getElementById('card-'+id)?.classList.remove('selected'));
  cart = {};
  renderCart();
}

function renderCart() {
  const container = document.getElementById('cartItems');
  const items = Object.entries(cart);
  document.getElementById('itemCount').textContent = items.reduce((s,[,i])=>s+i.qty,0) + ' items';

  if (!items.length) {
    container.innerHTML = `<div class="cart-empty"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg><p>Tap a menu item to add</p></div>`;
    document.getElementById('subtotal').textContent = '₱0';
    document.getElementById('total').textContent = '₱0';
    return;
  }

  let subtotal = 0;
  container.innerHTML = items.map(([id,item]) => {
    subtotal += item.price * item.qty;
    return `<div class="cart-row">
      <img class="cart-row-img" src="${escHtml(cartImages[id]||'')}" onerror="this.src='https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=100&q=60'" alt="">
      <div class="cart-row-info">
        <div class="cart-row-name">${escHtml(item.name)}</div>
        <div class="cart-row-sub">₱${item.price.toLocaleString()} × ${item.qty} = ₱${(item.price*item.qty).toLocaleString()}</div>
      </div>
      <div class="cart-row-controls">
        <button class="qty-btn" onclick="changeQty(${id},-1)">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn" onclick="changeQty(${id},1)">+</button>
      </div>
    </div>`;
  }).join('');

  document.getElementById('subtotal').textContent = '₱' + subtotal.toLocaleString();
  document.getElementById('total').textContent = '₱' + subtotal.toLocaleString();
}

function setCategory(btn) {
  document.querySelectorAll('.cat-pill').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  filterMenu();
}

function filterMenu() {
  const q = document.getElementById('menuSearch').value.toLowerCase();
  const cat = document.querySelector('.cat-pill.active')?.dataset.cat ?? 'all';
  document.querySelectorAll('.menu-card').forEach(el => {
    el.style.display = (el.dataset.name.includes(q) && (cat==='all'||el.dataset.category===cat)) ? '' : 'none';
  });
  document.querySelectorAll('.menu-section').forEach(sec => {
    sec.style.display = [...sec.querySelectorAll('.menu-card')].some(c=>c.style.display!=='none') ? '' : 'none';
  });
}

function openPaymentModal() {
  if (!Object.keys(cart).length) { alert('Add items first.'); return; }
  let total = 0;
  document.getElementById('modalSummary').innerHTML = Object.values(cart).map(i => {
    total += i.price * i.qty;
    return `<div class="sum-row"><span>${escHtml(i.name)} ×${i.qty}</span><span>₱${(i.price*i.qty).toLocaleString()}</span></div>`;
  }).join('');
  document.getElementById('modalTotal').textContent = '₱' + total.toLocaleString();
  document.getElementById('paymentModal').classList.add('open');
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.remove('open');
  document.getElementById('modalError').classList.remove('show');
}

// ── THE REAL submitOrder — saves to DB via save_order.php ──────────────
async function submitOrder() {
  const name    = document.getElementById('customerName').value.trim();
  const mobile  = document.getElementById('customerMobile').value.trim();
  const payment = document.getElementById('paymentMethod').value;
  const errEl   = document.getElementById('modalError');
  const btn     = document.getElementById('confirmBtn');

  if (!name || !mobile || !payment) {
    errEl.textContent = 'Please fill in all fields.';
    errEl.classList.add('show');
    return;
  }

  const items  = Object.entries(cart).map(([id, i]) => ({ id, name: i.name, price: i.price, qty: i.qty }));
  const amount = items.reduce((s, i) => s + i.price * i.qty, 0);

  btn.disabled    = true;
  btn.textContent = 'Processing...';
  errEl.classList.remove('show');

  try {
    const res  = await fetch('save_order.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ customer_name: name, mobile, payment_method: payment, cart: items, amount })
    });

    const data = await res.json();

    if (data.success) {
      closePaymentModal();
      document.getElementById('successMsg').textContent =
        `Order #${String(data.order_id).padStart(3,'0')} saved for ${name} — ₱${amount.toLocaleString()}`;
      document.getElementById('successModal').classList.add('open');
      clearCart();
      ['customerName', 'customerMobile'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('paymentMethod').value = '';
    } else {
      errEl.textContent = data.error || 'Failed to save order.';
      errEl.classList.add('show');
    }
  } catch (e) {
    errEl.textContent = 'Network error. Please try again.';
    errEl.classList.add('show');
  }

  btn.disabled    = false;
  btn.textContent = 'Confirm Order';
}

function closeSuccessModal() {
  document.getElementById('successModal').classList.remove('open');
  location.reload();
}

function escHtml(str) {
  return String(str??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>