<?php
require_once __DIR__ . '/includes/helpers.php';

$db       = getDB();
$reports  = getAllReports();
$cars     = getCars();
$drivers  = getDrivers();
$balance  = getCurrentBalance();

// Overall stats
$totalNet  = $db->query("SELECT SUM(net_earning) FROM platform_earnings")->fetchColumn() ?? 0;
$totalCash = $db->query("SELECT SUM(cash_received) FROM platform_earnings")->fetchColumn() ?? 0;
$totalExp  = $db->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?? 0;
$weekCount = $db->query("SELECT COUNT(*) FROM weekly_reports")->fetchColumn() ?? 0;

// Recent 5 reports
$recent = array_slice($reports, 0, 5);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — CarRent CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php pageHeader('dashboard'); ?>

<div class="app">

  <!-- Page Title -->
  <div class="page-title anim">
    <h2>📊 Dashboard</h2>
    <p>Your rental car fleet income at a glance</p>
  </div>

  <!-- Summary Stats -->
  <p class="sec-label">Overall Summary</p>
  <div class="stat-row">
    <div class="stat-card gold">
      <div class="stat-label">Total Net Earnings</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($totalNet, 0) ?></div>
      <div class="stat-sub">All time · <?= $weekCount ?> weeks recorded</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Total Cash Received</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($totalCash, 0) ?></div>
      <div class="stat-sub">Driver handover total</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Total Expenses Paid</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($totalExp, 0) ?></div>
      <div class="stat-sub">Salary + fuel + washing etc.</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Account Balance</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($balance, 0) ?></div>
      <div class="stat-sub"><a href="pages/balance.php" style="color:var(--accent4);text-decoration:none">View Ledger →</a></div>
    </div>
  </div>

  <div class="grid-2" style="gap:20px; margin-bottom:20px">

    <!-- Recent Weekly Reports -->
    <div class="card anim-2">
      <div class="card-head">
        <h3>📋 Recent Weekly Reports</h3>
        <a href="pages/new-report.php" class="btn btn-primary btn-sm">+ New</a>
      </div>
      <div class="card-body">
        <?php if (empty($recent)): ?>
          <div class="empty"><div class="empty-icon">📋</div>No reports yet.<br>Create your first weekly report!</div>
        <?php else: ?>
          <div class="t-head" style="display:grid;grid-template-columns:1fr 110px 110px 90px">
            <span>Week</span><span class="txt-right">Net Earning</span><span class="txt-right">Cash</span><span class="txt-right">Action</span>
          </div>
          <?php foreach ($recent as $r): ?>
          <div class="t-row rr" style="grid-template-columns:1fr 110px 110px 90px">
            <div>
              <div style="font-size:.85rem;font-weight:600"><?= fmtDate($r['week_start']) ?> – <?= date('d M', strtotime($r['week_end'])) ?></div>
              <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($r['car_name']) ?> · <?= htmlspecialchars($r['driver_name']) ?></div>
            </div>
            <div class="txt-right amt-gold"><?= inr($r['total_net'] ?? 0) ?></div>
            <div class="txt-right amt-pos"><?= inr($r['total_cash'] ?? 0) ?></div>
            <div class="txt-right">
              <a href="pages/weekly.php?id=<?= $r['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Fleet & Driver Quick View -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="card anim-2">
        <div class="card-head">
          <h3>🚗 Fleet</h3>
          <a href="pages/settings.php" class="btn btn-ghost btn-sm">Manage</a>
        </div>
        <div class="card-body">
          <?php foreach ($cars as $c): ?>
          <div class="t-row" style="grid-template-columns:auto 1fr auto">
            <span style="font-size:1.4rem">🚙</span>
            <div style="padding-left:12px">
              <div style="font-size:.87rem;font-weight:600"><?= htmlspecialchars($c['name']) ?></div>
              <div style="font-size:.72rem;color:var(--muted);font-family:'DM Mono',monospace"><?= $c['number'] ?></div>
            </div>
            <span class="badge badge-active"><?= strtoupper($c['status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card anim-3">
        <div class="card-head">
          <h3>👤 Drivers</h3>
          <a href="pages/driver-ledger.php" class="btn btn-ghost btn-sm">A/C</a>
        </div>
        <div class="card-body">
          <?php foreach ($drivers as $d): ?>
          <div class="t-row" style="grid-template-columns:auto 1fr auto">
            <span style="font-size:1.4rem">🧑‍✈️</span>
            <div style="padding-left:12px">
              <div style="font-size:.87rem;font-weight:600"><?= htmlspecialchars($d['name']) ?></div>
              <div style="font-size:.72rem;color:var(--muted)"><?= $d['salary_pct'] ?>% salary · <?= htmlspecialchars($d['car_name'] ?? '—') ?></div>
            </div>
            <span class="badge badge-active">ACTIVE</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /grid-2 -->

</div><!-- /app -->
<footer>CarRent CRM &nbsp;·&nbsp; Rental Fleet Income Manager</footer>
</body>
</html>
