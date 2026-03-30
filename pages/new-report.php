<?php
require_once __DIR__ . '/../includes/helpers.php';

$db        = getDB();
$cars      = getCars();
$drivers   = getDrivers();
$platforms = getPlatforms();
$flash     = getFlash('report');
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_id    = (int)($_POST['car_id'] ?? 0);
    $driver_id = (int)($_POST['driver_id'] ?? 0);
    $w_start   = $_POST['week_start'] ?? '';
    $w_end     = $_POST['week_end']   ?? '';

    if (!$car_id)    $errors[] = 'Please select a car.';
    if (!$driver_id) $errors[] = 'Please select a driver.';
    if (!$w_start)   $errors[] = 'Please set week start date.';
    if (!$w_end)     $errors[] = 'Please set week end date.';

    // Check duplicate
    if (!$errors) {
        $chk = $db->prepare("SELECT id FROM weekly_reports WHERE car_id=? AND week_start=?");
        $chk->execute([$car_id, $w_start]);
        if ($chk->fetch()) $errors[] = 'A report for this car and week already exists.';
    }

    if (!$errors) {
        // Insert report
        $st = $db->prepare("INSERT INTO weekly_reports (car_id, driver_id, week_start, week_end) VALUES (?,?,?,?)");
        $st->execute([$car_id, $driver_id, $w_start, $w_end]);
        $report_id = (int)$db->lastInsertId();

        // Insert platform earnings
        foreach ($platforms as $p) {
            $net  = (float)($_POST['net_'.$p['id']]  ?? 0);
            $cash = (float)($_POST['cash_'.$p['id']] ?? 0);
            $st2 = $db->prepare("INSERT INTO platform_earnings (weekly_report_id, platform_id, net_earning, cash_received) VALUES (?,?,?,?)");
            $st2->execute([$report_id, $p['id'], $net, $cash]);
        }

        // Auto-calculate driver salary
        $driver = $db->prepare("SELECT salary_pct FROM drivers WHERE id=?");
        $driver->execute([$driver_id]);
        $drv = $driver->fetch();
        $pct = $drv ? $drv['salary_pct'] : 32;

        $totalNet = $db->prepare("SELECT SUM(net_earning) FROM platform_earnings WHERE weekly_report_id=?");
        $totalNet->execute([$report_id]);
        $net_sum = (float)($totalNet->fetchColumn() ?? 0);
        $salary  = round($net_sum * ($pct / 100), 2);

        $st3 = $db->prepare("INSERT INTO expenses (weekly_report_id, label, amount, expense_type, icon) VALUES (?,?,?,'auto','👤')");
        $st3->execute([$report_id, "Driver Salary ({$pct}%)", $salary]);

        // Other expenses
        $exp_labels  = $_POST['exp_label']  ?? [];
        $exp_amounts = $_POST['exp_amount'] ?? [];
        $exp_icons   = $_POST['exp_icon']   ?? [];
        foreach ($exp_labels as $i => $label) {
            $label  = trim($label);
            $amount = (float)($exp_amounts[$i] ?? 0);
            $icon   = $exp_icons[$i] ?? '📌';
            if ($label && $amount > 0) {
                $st4 = $db->prepare("INSERT INTO expenses (weekly_report_id, label, amount, icon) VALUES (?,?,?,?)");
                $st4->execute([$report_id, $label, $amount, $icon]);
            }
        }

        flash('report', 'Weekly report created successfully!', 'success');
        header("Location: weekly.php?id=$report_id"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Report — CarRent CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php pageHeader('new-report'); ?>
<div class="app">

  <div class="page-title anim">
    <h2>➕ New Weekly Report</h2>
    <p>Enter this week's income data for your car</p>
  </div>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error">⚠ <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="POST">
    <!-- Basic Info -->
    <div class="card anim-2" style="margin-bottom:18px">
      <div class="card-head"><h3>📌 Basic Info</h3></div>
      <div style="padding:22px">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Car</label>
            <select class="form-control" name="car_id" required>
              <option value="">— Select Car —</option>
              <?php foreach ($cars as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($_POST['car_id']??'')==$c['id']?'selected':'' ?>>
                  <?= htmlspecialchars($c['name']) ?> (<?= $c['number'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Driver</label>
            <select class="form-control" name="driver_id" required>
              <option value="">— Select Driver —</option>
              <?php foreach ($drivers as $d): ?>
                <option value="<?= $d['id'] ?>" <?= ($_POST['driver_id']??'')==$d['id']?'selected':'' ?>>
                  <?= htmlspecialchars($d['name']) ?> (<?= $d['salary_pct'] ?>%)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">📅 Week Range — Pick Monday to Sunday</label>
          <input type="hidden" name="week_start" id="week_start" value="<?= $_POST['week_start'] ?? '' ?>">
          <input type="hidden" name="week_end"   id="week_end"   value="<?= $_POST['week_end'] ?? '' ?>">
          <input type="text" id="weekRangePicker" class="cal-trigger"
            placeholder="📅 Click to choose week (Mon – Sun)…"
            value="<?php if (!empty($_POST['week_start']) && !empty($_POST['week_end'])) echo date('d M Y', strtotime($_POST['week_start'])). ' → ' .date('d M Y', strtotime($_POST['week_end'])); ?>"
            readonly>
          <div id="weekLabel" style="margin-top:6px;font-size:.73rem;color:var(--accent);font-weight:600;font-family:'JetBrains Mono',monospace;min-height:18px"></div>
        </div>
      </div>
    </div>

    <!-- Platform Earnings -->
    <div class="card anim-2" style="margin-bottom:18px">
      <div class="card-head"><h3>🚀 Platform Earnings</h3></div>
      <div style="padding:22px">
        <div class="t-head" style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;margin-bottom:10px">
          <span></span><span>Platform</span><span>Net Earning (₹)</span><span>Cash Received (₹)</span>
        </div>
        <?php foreach ($platforms as $p): ?>
        <div style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;gap:10px;align-items:center;margin-bottom:12px">
          <span style="font-size:1.3rem"><?= $p['icon'] ?></span>
          <span style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($p['name']) ?></span>
          <input class="form-control" type="number" name="net_<?= $p['id'] ?>"  step="0.01" min="0" placeholder="0.00" value="<?= $_POST['net_'.$p['id']] ?? '' ?>">
          <input class="form-control" type="number" name="cash_<?= $p['id'] ?>" step="0.01" min="0" placeholder="0.00" value="<?= $_POST['cash_'.$p['id']] ?? '' ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Expenses -->
    <div class="card anim-3" style="margin-bottom:24px">
      <div class="card-head">
        <h3>💸 Expenses (Optional)</h3>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addExpRow()">+ Add Row</button>
      </div>
      <div style="padding:22px" id="expContainer">
        <p style="color:var(--muted);font-size:.82rem;margin-bottom:14px">Driver salary will be auto-calculated. Add fuel, washing etc.</p>
        <!-- Default expense rows -->
        <div class="exp-row-wrap" style="display:grid;grid-template-columns:60px 1fr 140px 36px;gap:8px;align-items:center;margin-bottom:10px">
          <input class="form-control" name="exp_icon[]"   value="⛽" placeholder="icon">
          <input class="form-control" name="exp_label[]"  value="Oil / Petrol / CNG" placeholder="Label">
          <input class="form-control" name="exp_amount[]" type="number" step="0.01" min="0" placeholder="₹ Amount">
          <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.exp-row-wrap').remove()">✕</button>
        </div>
        <div class="exp-row-wrap" style="display:grid;grid-template-columns:60px 1fr 140px 36px;gap:8px;align-items:center;margin-bottom:10px">
          <input class="form-control" name="exp_icon[]"   value="🚿" placeholder="icon">
          <input class="form-control" name="exp_label[]"  value="Washing" placeholder="Label">
          <input class="form-control" name="exp_amount[]" type="number" step="0.01" min="0" placeholder="₹ Amount">
          <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.exp-row-wrap').remove()">✕</button>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:12px">
      <button type="submit" class="btn btn-primary" style="padding:12px 32px">💾 Create Report</button>
      <a href="weekly.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>

</div>
<footer>CarRent CRM</footer>
<script>
function addExpRow() {
  const c = document.getElementById('expContainer');
  const row = document.createElement('div');
  row.className = 'exp-row-wrap';
  row.style.cssText = 'display:grid;grid-template-columns:60px 1fr 140px 36px;gap:8px;align-items:center;margin-bottom:10px';
  row.innerHTML = `
    <input class="form-control" name="exp_icon[]"   value="📌" placeholder="icon">
    <input class="form-control" name="exp_label[]"  placeholder="Expense label…">
    <input class="form-control" name="exp_amount[]" type="number" step="0.01" min="0" placeholder="₹ Amount">
    <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.exp-row-wrap').remove()">✕</button>
  `;
  c.appendChild(row);
  row.querySelector('input[name="exp_label[]"]').focus();
}
</script>
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Week range picker (Mon-Sun)
flatpickr("#weekRangePicker", {
  mode: "range",
  dateFormat: "Y-m-d",
  locale: { firstDayOfWeek: 1 },
  onDayCreate: function(dObj, dStr, fp, dayElem) {
    var d = dayElem.dateObj.getDay();
    if (d === 1) dayElem.style.borderRadius = "8px 0 0 8px";
    if (d === 0) dayElem.style.borderRadius = "0 8px 8px 0";
  },
  onChange: function(selectedDates, dateStr, instance) {
    if (selectedDates.length === 1) {
      // Auto-snap to Monday of that week
      var d = new Date(selectedDates[0]);
      var day = d.getDay();
      var diff = (day === 0) ? -6 : 1 - day;
      var mon = new Date(d); mon.setDate(d.getDate() + diff);
      var sun = new Date(mon); sun.setDate(mon.getDate() + 6);
      instance.setDate([mon, sun], true);
    }
    if (selectedDates.length === 2) {
      var start = selectedDates[0];
      var end   = selectedDates[1];
      // Force Monday start
      if (start.getDay() !== 1) {
        var diff2 = (start.getDay() === 0) ? -6 : 1 - start.getDay();
        start = new Date(start); start.setDate(start.getDate() + diff2);
      }
      // Force Sunday end = start + 6
      end = new Date(start); end.setDate(start.getDate() + 6);

      var fmt = function(d) {
        return d.getFullYear() + "-" +
          String(d.getMonth()+1).padStart(2,"0") + "-" +
          String(d.getDate()).padStart(2,"0");
      };
      var fmtDisp = function(d) {
        var months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
        return String(d.getDate()).padStart(2,"0") + " " + months[d.getMonth()] + " " + d.getFullYear();
      };

      document.getElementById("week_start").value = fmt(start);
      document.getElementById("week_end").value   = fmt(end);
      instance.input.value = fmtDisp(start) + " → " + fmtDisp(end);

      var days = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
      document.getElementById("weekLabel").textContent =
        "✅ Week: " + fmtDisp(start) + " (" + days[start.getDay()] + ") to " + fmtDisp(end) + " (" + days[end.getDay()] + ")";
    }
  }
});
</script>
</body>
</html>
