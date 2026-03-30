<?php
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();
$tab = $_GET['tab'] ?? 'cars';

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add Car
    if ($action === 'add_car') {
        $st = $db->prepare("INSERT INTO cars (name, number, model, color) VALUES (?,?,?,?)");
        $st->execute([trim($_POST['car_name']), trim($_POST['car_number']), trim($_POST['car_model']), trim($_POST['car_color'])]);
        header("Location: settings.php?tab=cars"); exit;
    }
    // Delete Car
    if ($action === 'del_car') {
        $db->prepare("DELETE FROM cars WHERE id=?")->execute([(int)$_POST['id']]);
        header("Location: settings.php?tab=cars"); exit;
    }
    // Toggle Car Status
    if ($action === 'toggle_car') {
        $c = $db->prepare("SELECT status FROM cars WHERE id=?")->execute([(int)$_POST['id']]);
        $cur = $db->prepare("SELECT status FROM cars WHERE id=?")->execute([(int)$_POST['id']]);
        $row = $db->query("SELECT status FROM cars WHERE id=".(int)$_POST['id'])->fetch();
        $new = $row['status']==='active' ? 'inactive' : 'active';
        $db->prepare("UPDATE cars SET status=? WHERE id=?")->execute([$new, (int)$_POST['id']]);
        header("Location: settings.php?tab=cars"); exit;
    }

    // Add Driver
    if ($action === 'add_driver') {
        $st = $db->prepare("INSERT INTO drivers (name, phone, license_no, salary_pct, car_id) VALUES (?,?,?,?,?)");
        $carId = (int)$_POST['driver_car'] ?: null;
        $st->execute([trim($_POST['driver_name']), trim($_POST['driver_phone']), trim($_POST['driver_license']), (float)$_POST['driver_pct'], $carId]);
        header("Location: settings.php?tab=drivers"); exit;
    }
    // Delete Driver
    if ($action === 'del_driver') {
        $db->prepare("DELETE FROM drivers WHERE id=?")->execute([(int)$_POST['id']]);
        header("Location: settings.php?tab=drivers"); exit;
    }

    // Add Platform
    if ($action === 'add_platform') {
        $st = $db->prepare("INSERT INTO platforms (name, icon, color) VALUES (?,?,?)");
        $st->execute([trim($_POST['plat_name']), trim($_POST['plat_icon']), trim($_POST['plat_color'])]);
        header("Location: settings.php?tab=platforms"); exit;
    }
    // Delete Platform
    if ($action === 'del_platform') {
        $db->prepare("DELETE FROM platforms WHERE id=?")->execute([(int)$_POST['id']]);
        header("Location: settings.php?tab=platforms"); exit;
    }
}

$cars      = $db->query("SELECT * FROM cars ORDER BY name")->fetchAll();
$drivers   = $db->query("SELECT d.*, c.name as car_name FROM drivers d LEFT JOIN cars c ON d.car_id=c.id ORDER BY d.name")->fetchAll();
$platforms = $db->query("SELECT * FROM platforms ORDER BY id")->fetchAll();
$allCars   = getCars();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings — CarRent CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .tab-nav { display:flex; gap:4px; margin-bottom:20px; }
  .tab-nav a { padding:9px 20px; border-radius:8px; text-decoration:none; font-size:.82rem; font-weight:700; color:var(--text2); border:1px solid var(--border); transition:all .18s; }
  .tab-nav a.active, .tab-nav a:hover { background:var(--accent); color:#000; border-color:var(--accent); }
</style>
</head>
<body>
<?php pageHeader('settings'); ?>
<div class="app">

  <div class="page-title anim">
    <h2>⚙️ Settings</h2>
    <p>Manage your cars, drivers, and platforms</p>
  </div>

  <!-- Tab Nav -->
  <div class="tab-nav anim-2">
    <a href="?tab=cars"      class="<?= $tab==='cars'      ?'active':'' ?>">🚗 Cars</a>
    <a href="?tab=drivers"   class="<?= $tab==='drivers'   ?'active':'' ?>">👤 Drivers</a>
    <a href="?tab=platforms" class="<?= $tab==='platforms' ?'active':'' ?>">🚀 Platforms</a>
  </div>

  <?php if ($tab === 'cars'): ?>
  <!-- ── CARS ─────────────────────────────────────────────── -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="card-head"><h3>➕ Add New Car</h3></div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="add_car">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;align-items:end">
        <div><label class="form-label">Car Name</label><input class="form-control" name="car_name" placeholder="e.g. Swift Dzire" required></div>
        <div><label class="form-label">Number Plate</label><input class="form-control" name="car_number" placeholder="WB-01-AB-1234" required></div>
        <div><label class="form-label">Model</label><input class="form-control" name="car_model" placeholder="Model year…"></div>
        <div><label class="form-label">Color</label><input class="form-control" name="car_color" placeholder="White…"></div>
        <button class="btn btn-primary" type="submit">+ Add</button>
      </div>
    </form>
  </div>
  <div class="card anim-3">
    <div class="card-head"><h3>🚗 All Cars</h3><span class="mono" style="color:var(--muted);font-size:.75rem"><?= count($cars) ?> total</span></div>
    <div class="t-head" style="display:grid;grid-template-columns:1fr 140px 1fr 90px 80px 80px">
      <span>Name</span><span>Number</span><span>Model</span><span>Color</span><span class="txt-center">Status</span><span class="txt-right">Action</span>
    </div>
    <?php foreach ($cars as $c): ?>
    <div class="t-row rr" style="grid-template-columns:1fr 140px 1fr 90px 80px 80px">
      <span style="font-weight:600"><?= htmlspecialchars($c['name']) ?></span>
      <span class="mono" style="font-size:.78rem;color:var(--text2)"><?= $c['number'] ?></span>
      <span style="font-size:.83rem;color:var(--text2)"><?= htmlspecialchars($c['model'] ?? '—') ?></span>
      <span style="font-size:.83rem"><?= htmlspecialchars($c['color'] ?? '—') ?></span>
      <span class="txt-center">
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_car">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="badge <?= $c['status']==='active'?'badge-active':'badge-warning' ?>" type="submit" style="cursor:pointer;border:none;background:inherit;font-family:inherit;font-size:inherit">
            <?= strtoupper($c['status']) ?>
          </button>
        </form>
      </span>
      <div class="txt-right">
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="del_car">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn btn-danger btn-sm btn-icon" type="submit" onclick="return confirm('Delete car?')">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($tab === 'drivers'): ?>
  <!-- ── DRIVERS ───────────────────────────────────────────── -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="card-head"><h3>➕ Add New Driver</h3></div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="add_driver">
      <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 80px 1fr auto;gap:10px;align-items:end">
        <div><label class="form-label">Full Name</label><input class="form-control" name="driver_name" required placeholder="Driver name…"></div>
        <div><label class="form-label">Phone</label><input class="form-control" name="driver_phone" placeholder="98765…"></div>
        <div><label class="form-label">License No.</label><input class="form-control" name="driver_license" placeholder="WB-01-…"></div>
        <div><label class="form-label">Salary %</label><input class="form-control" name="driver_pct" type="number" value="32" min="0" max="100" step="0.5"></div>
        <div>
          <label class="form-label">Assigned Car</label>
          <select class="form-control" name="driver_car">
            <option value="">— None —</option>
            <?php foreach ($allCars as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit">+ Add</button>
      </div>
    </form>
  </div>
  <div class="card anim-3">
    <div class="card-head"><h3>👤 All Drivers</h3><span class="mono" style="color:var(--muted);font-size:.75rem"><?= count($drivers) ?> total</span></div>
    <div class="t-head" style="display:grid;grid-template-columns:1.2fr 110px 1fr 70px 1fr 60px">
      <span>Name</span><span>Phone</span><span>License</span><span class="txt-center">%</span><span>Car</span><span class="txt-right">Del</span>
    </div>
    <?php foreach ($drivers as $d): ?>
    <div class="t-row rr" style="grid-template-columns:1.2fr 110px 1fr 70px 1fr 60px">
      <span style="font-weight:600"><?= htmlspecialchars($d['name']) ?></span>
      <span class="mono" style="font-size:.78rem;color:var(--text2)"><?= $d['phone'] ?></span>
      <span style="font-size:.8rem;color:var(--text2)"><?= $d['license_no'] ?></span>
      <span class="txt-center" style="color:var(--accent);font-weight:700"><?= $d['salary_pct'] ?>%</span>
      <span style="font-size:.83rem"><?= htmlspecialchars($d['car_name'] ?? '—') ?></span>
      <div class="txt-right">
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="del_driver">
          <input type="hidden" name="id" value="<?= $d['id'] ?>">
          <button class="btn btn-danger btn-sm btn-icon" type="submit" onclick="return confirm('Delete driver?')">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <!-- ── PLATFORMS ─────────────────────────────────────────── -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="card-head"><h3>➕ Add Platform</h3></div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="add_platform">
      <div style="display:grid;grid-template-columns:1fr 60px 120px auto;gap:10px;align-items:end">
        <div><label class="form-label">Platform Name</label><input class="form-control" name="plat_name" required placeholder="e.g. InDrive"></div>
        <div><label class="form-label">Icon</label><input class="form-control" name="plat_icon" value="🚕"></div>
        <div><label class="form-label">Color (hex)</label><input class="form-control" name="plat_color" value="#f0b429" placeholder="#hex"></div>
        <button class="btn btn-primary" type="submit">+ Add</button>
      </div>
    </form>
  </div>
  <div class="card anim-3">
    <div class="card-head"><h3>🚀 All Platforms</h3></div>
    <div class="t-head" style="display:grid;grid-template-columns:40px 1fr 100px 40px">
      <span>Icon</span><span>Name</span><span>Color</span><span class="txt-right">Del</span>
    </div>
    <?php foreach ($platforms as $p): ?>
    <div class="t-row rr" style="grid-template-columns:40px 1fr 100px 40px">
      <span style="font-size:1.3rem"><?= $p['icon'] ?></span>
      <span style="font-weight:600"><?= htmlspecialchars($p['name']) ?></span>
      <span style="display:flex;align-items:center;gap:8px">
        <span style="width:14px;height:14px;border-radius:4px;background:<?= $p['color'] ?>;display:inline-block"></span>
        <span class="mono" style="font-size:.75rem;color:var(--muted)"><?= $p['color'] ?></span>
      </span>
      <div class="txt-right">
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="del_platform">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="btn btn-danger btn-sm btn-icon" type="submit" onclick="return confirm('Delete?')">🗑</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
<footer>CarRent CRM · Settings</footer>
</body>
</html>
