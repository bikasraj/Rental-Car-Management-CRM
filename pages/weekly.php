<?php
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If no ID, show list of all reports
if (!$id) {
    $reports = getAllReports();
    ?>
    <!DOCTYPE html>
    <html lang="hi">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Weekly Reports — CarRent CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
    <?php pageHeader('weekly'); ?>
    <div class="app">
      <div class="page-title anim" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div><h2>📋 Weekly Reports</h2><p>All recorded weekly income reports</p></div>
        <a href="new-report.php" class="btn btn-primary">+ New Weekly Report</a>
      </div>
      <div class="card anim-2">
        <div class="t-head" style="display:grid;grid-template-columns:1.5fr 1fr 120px 120px 80px 90px">
          <span>Week Period</span><span>Driver / Car</span>
          <span class="txt-right">Net Earning</span><span class="txt-right">Cash</span>
          <span class="txt-center">Status</span><span class="txt-right">Action</span>
        </div>
        <?php if (empty($reports)): ?>
          <div class="empty"><div class="empty-icon">📋</div>No weekly reports yet.</div>
        <?php endif; ?>
        <?php foreach ($reports as $r): ?>
        <div class="t-row rr" style="grid-template-columns:1.5fr 1fr 120px 120px 80px 90px">
          <div>
            <div style="font-size:.87rem;font-weight:700"><?= fmtDate($r['week_start']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)">to <?= fmtDate($r['week_end']) ?></div>
          </div>
          <div>
            <div style="font-size:.85rem;font-weight:600"><?= htmlspecialchars($r['driver_name']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($r['car_name']) ?></div>
          </div>
          <div class="txt-right amt-gold"><?= inr($r['total_net'] ?? 0) ?></div>
          <div class="txt-right amt-pos"><?= inr($r['total_cash'] ?? 0) ?></div>
          <div class="txt-center"><span class="badge badge-active">DONE</span></div>
          <div class="txt-right" style="display:flex;gap:6px;justify-content:flex-end">
            <a href="weekly.php?id=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            <a href="delete-report.php?id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this report?')">🗑</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <footer>CarRent CRM</footer>
    </body></html>
    <?php
    exit;
}

// Single report view
$r = getWeeklyReport($id);
if (!$r) { echo "<p style='color:red;padding:30px'>Report not found.</p>"; exit; }

// Handle AJAX expense delete
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_expense'])) {
    $eid = (int)$_POST['delete_expense'];
    $st = $db->prepare("DELETE FROM expenses WHERE id=? AND weekly_report_id=?");
    $st->execute([$eid, $id]);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]); exit;
}

// Handle add expense
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_expense'])) {
    $label  = trim($_POST['label'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $icon   = $_POST['icon'] ?? '📌';
    if ($label && $amount > 0) {
        $st = $db->prepare("INSERT INTO expenses (weekly_report_id, label, amount, icon) VALUES (?,?,?,?)");
        $st->execute([$id, $label, $amount, $icon]);
    }
    header("Location: weekly.php?id=$id"); exit;
}

// Handle add driver payment
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_payment'])) {
    $date   = $_POST['pay_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['pay_amount'] ?? 0);
    $note   = trim($_POST['pay_note'] ?? '');
    if ($amount > 0) {
        $st = $db->prepare("INSERT INTO driver_payments (weekly_report_id, driver_id, payment_date, amount, note) VALUES (?,?,?,?,?)");
        $st->execute([$id, $r['driver_id'], $date, $amount, $note]);
    }
    header("Location: weekly.php?id=$id"); exit;
}

// Handle add home take received
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_htr'])) {
    $date   = $_POST['htr_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['htr_amount'] ?? 0);
    $note   = trim($_POST['htr_note'] ?? '');
    if ($amount > 0) {
        $st = $db->prepare("INSERT INTO home_take_received (weekly_report_id, received_date, amount, note) VALUES (?,?,?,?)");
        $st->execute([$id, $date, $amount, $note]);
    }
    header("Location: weekly.php?id=$id"); exit;
}

// Handle delete home take received
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_htr'])) {
    $hid = (int)$_POST['delete_htr'];
    $st = $db->prepare("DELETE FROM home_take_received WHERE id=? AND weekly_report_id=?");
    $st->execute([$hid, $id]);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]); exit;
}

// Handle update platform earnings
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_platform'])) {
    $pid   = (int)$_POST['platform_id'];
    $net   = (float)$_POST['net_earning'];
    $cash  = (float)$_POST['cash_received'];
    $st = $db->prepare("UPDATE platform_earnings SET net_earning=?, cash_received=? WHERE id=? AND weekly_report_id=?");
    $st->execute([$net, $cash, $pid, $id]);
    header("Location: weekly.php?id=$id"); exit;
}

$r = getWeeklyReport($id); // reload fresh
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= fmtDate($r['week_start']) ?> Report — CarRent CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php pageHeader('weekly'); ?>
<div class="app">

  <!-- Header -->
  <div class="page-title anim" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <h2>📋 Weekly Report</h2>
      <p><?= fmtDate($r['week_start']) ?> &nbsp;–&nbsp; <?= fmtDate($r['week_end']) ?>
        &nbsp;·&nbsp; <?= htmlspecialchars($r['car_name']) ?>
        &nbsp;·&nbsp; <?= htmlspecialchars($r['driver_name']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="weekly.php" class="btn btn-ghost btn-sm">← All Reports</a>
      <button class="btn btn-primary btn-sm" onclick="printPDF()">🖨️ PDF Report</button>
      <a href="delete-report.php?id=<?= $id ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this report?')">🗑 Delete</a>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="stat-row">
    <div class="stat-card gold">
      <div class="stat-label">Total Net Earning</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($r['total_net'], 0) ?></div>
      <div class="stat-sub">All platforms</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Cash Received</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($r['total_cash'], 0) ?></div>
      <div class="stat-sub">Driver handover</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Driver Salary (<?= $r['salary_pct'] ?>%)</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($r['driver_salary'], 0) ?></div>
      <div class="stat-sub">Auto calculated</div>
    </div>
    <div class="stat-card <?= $r['home_take'] >= 0 ? 'green' : 'red' ?>">
      <div class="stat-label">Home Take</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format(abs($r['home_take']), 0) ?></div>
      <div class="stat-sub"><?= $r['home_take'] >= 0 ? 'Net take home' : '⚠ Deficit' ?></div>
    </div>
  </div>

  <!-- Platform + Expenses row -->
  <div class="grid-2" style="margin-bottom:18px">

    <!-- Platform Earnings -->
    <div class="card anim-2">
      <div class="card-head">
        <h3>🚀 Platform Earnings</h3>
        <span class="amt-gold mono" style="font-size:.9rem"><?= inr($r['total_net']) ?></span>
      </div>
      <div class="t-head" style="display:grid;grid-template-columns:32px 1fr 110px 110px 70px">
        <span></span><span>Platform</span><span class="txt-right">Net Earning</span><span class="txt-right">Cash</span><span class="txt-center">Edit</span>
      </div>
      <?php foreach ($r['platforms'] as $p): ?>
      <div class="t-row" style="grid-template-columns:32px 1fr 110px 110px 70px" id="plat-row-<?= $p['id'] ?>">
        <span style="font-size:1.1rem"><?= $p['icon'] ?></span>
        <span style="font-size:.87rem;font-weight:600"><?= htmlspecialchars($p['name']) ?></span>
        <span class="amt-pos txt-right"><?= inr($p['net_earning']) ?></span>
        <span class="amt-gold txt-right"><?= $p['cash_received'] > 0 ? inr($p['cash_received']) : '<span style="color:var(--muted)">—</span>' ?></span>
        <div class="txt-center">
          <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditPlat(<?= $p['id'] ?>, <?= $p['net_earning'] ?>, <?= $p['cash_received'] ?>, '<?= htmlspecialchars($p['name']) ?>')">✏️</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Expenses -->
    <div class="card anim-2">
      <div class="card-head">
        <h3>💸 Expenses</h3>
        <span class="amt-neg mono" style="font-size:.9rem">-<?= inr($r['total_expenses']) ?></span>
      </div>
      <?php foreach ($r['expenses'] as $e): ?>
      <div class="t-row" style="display:flex;align-items:center;gap:12px" id="exp-<?= $e['id'] ?>">
        <div class="exp-icon"><?= $e['icon'] ?></div>
        <span style="flex:1;font-size:.87rem;font-weight:600"><?= htmlspecialchars($e['label']) ?>
          <?php if ($e['expense_type']==='auto'): ?><span class="badge badge-auto" style="margin-left:6px">AUTO</span><?php endif; ?></span>
        <span class="amt-neg"><?= inr($e['amount']) ?></span>
        <?php if ($e['expense_type']==='manual'): ?>
        <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteExpense(<?= $e['id'] ?>, <?= $id ?>)" title="Delete">🗑</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <!-- Add Expense -->
      <form method="POST" style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="add_expense" value="1">
        <input class="form-control" name="label"  placeholder="Expense label…" style="flex:2;min-width:120px" required>
        <input class="form-control" name="amount" type="number" min="1" placeholder="₹ Amount" style="flex:1;min-width:90px" required>
        <input class="form-control" name="icon"   placeholder="icon" style="width:60px" value="📌">
        <button class="btn btn-primary btn-sm" type="submit">+ Add</button>
      </form>
    </div>

  </div><!-- /grid-2 -->

  <!-- Saving + Driver Payments -->
  <div class="grid-2" style="margin-bottom:18px">

    <!-- Saving Breakdown -->
    <div class="saving-panel anim-3">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
        <div>
          <p class="sec-label" style="margin-bottom:6px">Home Take / Saving</p>
          <div class="saving-big">₹<?= number_format(abs($r['home_take']), 0) ?></div>
        </div>
        <span style="font-size:2.5rem">🏠</span>
      </div>
      <div>
        <div class="saving-row"><span>Total Cash</span><span class="amt-pos">+<?= inr($r['total_cash']) ?></span></div>
        <div class="saving-row"><span>Driver Salary</span><span class="amt-neg">-<?= inr($r['driver_salary']) ?></span></div>
        <div class="saving-row"><span>Other Expenses</span><span class="amt-neg">-<?= inr($r['total_expenses'] - $r['driver_salary']) ?></span></div>
        <div class="saving-div"></div>
        <div class="saving-row"><span>Saving (Cash–Exp)</span><span class="amt-pos"><?= inr($r['saving']) ?></span></div>
        <div class="saving-row"><span>Driver Paid (<?= count($r['driver_payments']) ?>×)</span><span class="amt-neg">-<?= inr($r['driver_paid']) ?></span></div>
        <div class="saving-div"></div>
        <div class="saving-row" style="font-weight:700;font-size:.95rem">
          <span>Home Take</span>
          <span style="color:var(--accent2)"><?= inr($r['home_take']) ?></span>
        </div>
        <div class="saving-div" style="margin:10px 0"></div>

        <!-- Received rows -->
        <?php if (!empty($r['home_take_received'])): ?>
        <div style="font-size:.75rem;font-weight:700;color:var(--muted);letter-spacing:.05em;margin-bottom:6px;text-transform:uppercase">Received</div>
        <?php foreach ($r['home_take_received'] as $htr): ?>
        <div class="saving-row" id="htr-<?= $htr['id'] ?>" style="font-size:.82rem">
          <span style="color:var(--text2)"><?= date('d M', strtotime($htr['received_date'])) ?><?= $htr['note'] ? ' · '.htmlspecialchars($htr['note']) : '' ?></span>
          <span style="display:flex;align-items:center;gap:6px">
            <span class="amt-pos">+<?= inr($htr['amount']) ?></span>
            <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteHtr(<?= $htr['id'] ?>, <?= $id ?>)" title="Delete">🗑</button>
          </span>
        </div>
        <?php endforeach; ?>
        <div class="saving-div" style="margin:8px 0"></div>
        <?php endif; ?>

        <!-- Due / Received Total -->
        <div class="saving-row" style="font-weight:700;font-size:.9rem">
          <span>Total Received</span>
          <span class="amt-pos">+<?= inr($r['home_take_received_total']) ?></span>
        </div>
        <div class="saving-row" style="font-weight:800;font-size:1rem;margin-top:4px">
          <span>Due (Pending)</span>
          <span style="color:<?= $r['home_take_due'] > 0 ? 'var(--accent4)' : 'var(--accent2)' ?>;font-size:1.05rem">
            <?= $r['home_take_due'] > 0 ? '⚠ ' : '✅ ' ?><?= inr($r['home_take_due']) ?>
          </span>
        </div>
      </div>

      <!-- Add Received Form -->
      <form method="POST" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="add_htr" value="1">
        <input type="text" id="htrDatePick" class="cal-trigger" placeholder="Date…" readonly style="flex:1;min-width:110px">
        <input type="hidden" name="htr_date" id="htrDateVal" value="<?= date('Y-m-d') ?>">
        <input class="form-control" name="htr_note" placeholder="Note…" style="flex:2;min-width:90px">
        <input class="form-control" name="htr_amount" type="number" min="1" placeholder="₹ Received" style="flex:1;min-width:80px" required>
        <button class="btn btn-primary btn-sm" type="submit" style="white-space:nowrap">+ Add</button>
      </form>
    </div>

    <!-- Driver Payments -->
    <div class="card anim-3">
      <div class="card-head">
        <h3>💵 Driver Payments</h3>
        <span class="amt-gold mono" style="font-size:.9rem"><?= inr($r['driver_paid']) ?></span>
      </div>
      <div class="t-head" style="display:grid;grid-template-columns:80px 1fr 110px">
        <span>Date</span><span>Note</span><span class="txt-right">Amount</span>
      </div>
      <?php if (empty($r['driver_payments'])): ?>
        <div class="empty" style="padding:20px">No payments recorded yet.</div>
      <?php endif; ?>
      <?php foreach ($r['driver_payments'] as $p): ?>
      <div class="t-row" style="grid-template-columns:80px 1fr 110px">
        <span class="mono" style="font-size:.75rem;color:var(--muted)"><?= date('d M', strtotime($p['payment_date'])) ?></span>
        <span style="font-size:.85rem"><?= htmlspecialchars($p['note'] ?? '') ?></span>
        <span class="amt-gold txt-right"><?= inr($p['amount']) ?></span>
      </div>
      <?php endforeach; ?>
      <!-- Add Payment -->
      <form method="POST" style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="add_payment" value="1">
        <input type="text" id="payDatePick" class="cal-trigger" placeholder="Date…" readonly style="flex:1;min-width:120px">
        <input type="hidden" name="pay_date" id="payDateVal" value="<?= date('Y-m-d') ?>">
        <input class="form-control" name="pay_note"   placeholder="Note…" style="flex:2;min-width:100px">
        <input class="form-control" name="pay_amount" type="number" min="1" placeholder="₹" style="flex:1;min-width:80px" required>
        <button class="btn btn-primary btn-sm" type="submit">+ Add</button>
      </form>
    </div>

  </div><!-- /grid-2 -->

</div><!-- /app -->
<footer>CarRent CRM · Weekly Report</footer>

<!-- Edit Platform Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:28px;width:90%;max-width:400px;animation:fadeUp .25s ease">
    <h3 style="margin-bottom:18px;font-size:1rem" id="editPlatTitle">Edit Platform</h3>
    <form method="POST">
      <input type="hidden" name="update_platform" value="1">
      <input type="hidden" name="platform_id" id="editPlatId">
      <div class="form-group">
        <label class="form-label">Net Earning (₹)</label>
        <input class="form-control" name="net_earning" id="editNet" type="number" step="0.01" min="0" required>
      </div>
      <div class="form-group">
        <label class="form-label">Cash Received (₹)</label>
        <input class="form-control" name="cash_received" id="editCash" type="number" step="0.01" min="0">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
        <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditPlat(id, net, cash, name) {
  document.getElementById('editPlatId').value = id;
  document.getElementById('editNet').value    = net;
  document.getElementById('editCash').value   = cash;
  document.getElementById('editPlatTitle').textContent = 'Edit: ' + name;
  document.getElementById('editModal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e){
  if(e.target===this) closeModal();
});

async function deleteExpense(expId, reportId) {
  if (!confirm('Delete this expense?')) return;
  const fd = new FormData();
  fd.append('delete_expense', expId);
  const resp = await fetch('weekly.php?id='+reportId, {method:'POST', body:fd});
  const data = await resp.json();
  if (data.ok) {
    const row = document.getElementById('exp-'+expId);
    if (row) { row.style.opacity='0'; setTimeout(()=>row.remove(), 300); }
  }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
if (document.getElementById('payDatePick')) {
  flatpickr("#payDatePick", {
    dateFormat: "Y-m-d", defaultDate: "today",
    locale: { firstDayOfWeek: 1 },
    onChange: function(sel, dateStr) { document.getElementById("payDateVal").value = dateStr; },
    onReady: function(s,d,fp) { fp.input.value = flatpickr.formatDate(new Date(),"d M Y"); }
  });
}
if (document.getElementById('htrDatePick')) {
  flatpickr("#htrDatePick", {
    dateFormat: "Y-m-d", defaultDate: "today",
    locale: { firstDayOfWeek: 1 },
    onChange: function(sel, dateStr) { document.getElementById("htrDateVal").value = dateStr; },
    onReady: function(s,d,fp) { fp.input.value = flatpickr.formatDate(new Date(),"d M Y"); }
  });
}

async function deleteHtr(htrId, reportId) {
  if (!confirm('Delete this received entry?')) return;
  const fd = new FormData();
  fd.append('delete_htr', htrId);
  const resp = await fetch('weekly.php?id='+reportId, {method:'POST', body:fd});
  const data = await resp.json();
  if (data.ok) {
    const row = document.getElementById('htr-'+htrId);
    if (row) { row.style.opacity='0'; row.style.transition='opacity .3s'; setTimeout(()=>location.reload(), 350); }
  }
}

function printPDF() {
  window.print();
}
</script>

<!-- PDF Print Styles -->
<style>
@media print {
  @page { size: A4; margin: 15mm 12mm; }
  body { background: #fff !important; color: #111 !important; font-family: Arial, sans-serif; }
  body::before { display: none !important; }
  header.top-bar, footer, .btn, form, #editModal { display: none !important; }
  .app { padding: 0 !important; max-width: 100% !important; }
  .page-title h2 { font-size: 1.2rem; color: #111; }
  .page-title p { font-size: .8rem; color: #555; }
  .stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; margin-bottom: 14px; }
  .stat-card { background: #f5f5f5 !important; border: 1px solid #ddd !important; border-radius: 8px; padding: 10px 12px; }
  .stat-label { font-size: .7rem; color: #666; }
  .stat-value { font-size: 1.2rem; font-weight: 800; color: #111; }
  .stat-sub { font-size: .65rem; color: #888; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .card, .saving-panel { background: #f9f9f9 !important; border: 1px solid #e0e0e0 !important; border-radius: 8px; padding: 12px; page-break-inside: avoid; }
  .card-head h3, .sec-label { font-size: .85rem; font-weight: 700; color: #333; }
  .t-head, .t-row { font-size: .75rem; color: #333; padding: 5px 8px !important; border-bottom: 1px solid #eee; }
  .t-head { background: #eee !important; font-weight: 700; }
  .saving-row { font-size: .8rem; padding: 4px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
  .saving-big { font-size: 1.4rem; font-weight: 900; color: #111; }
  .saving-div { border-top: 2px solid #ccc; margin: 6px 0; }
  .amt-pos { color: #1a7a3c !important; font-weight: 700; }
  .amt-neg { color: #c0392b !important; font-weight: 700; }
  .amt-gold { color: #b8860b !important; font-weight: 700; }
  .mono { font-family: monospace; }
  .badge { font-size: .65rem; padding: 2px 5px; border-radius: 4px; background: #e0e0e0; color: #333; }
  .badge-auto { background: #daf0e8; color: #1a7a3c; }
  .empty { color: #999; font-size: .78rem; padding: 10px; }
  .exp-icon { font-size: .9rem; }
  /* Print header */
  .print-header { display: block !important; text-align: center; margin-bottom: 14px; border-bottom: 2px solid #333; padding-bottom: 10px; }
  .print-header h1 { font-size: 1.3rem; font-weight: 900; }
  .print-header p { font-size: .8rem; color: #555; }
  /* hide anim classes issues */
  .anim, .anim-2, .anim-3 { opacity: 1 !important; transform: none !important; }
}
/* Print header hidden on screen */
.print-header { display: none; }
</style>

<!-- Hidden print header shown only in PDF -->
<div class="print-header">
  <h1>🚗 CarRent CRM — Weekly Report</h1>
  <p><?= fmtDate($r['week_start']) ?> &nbsp;–&nbsp; <?= fmtDate($r['week_end']) ?>
     &nbsp;·&nbsp; <?= htmlspecialchars($r['car_name']) ?>
     &nbsp;·&nbsp; Driver: <?= htmlspecialchars($r['driver_name']) ?>
     &nbsp;·&nbsp; Printed: <?= date('d M Y H:i') ?></p>
</div>
</body>
</html>
