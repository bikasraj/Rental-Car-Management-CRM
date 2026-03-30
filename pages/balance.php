<?php
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();

// Handle add entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bal'])) {
    $date   = $_POST['bal_date'];
    $type   = $_POST['bal_type'];
    $amount = (float)$_POST['bal_amount'];
    $note   = trim($_POST['bal_note'] ?? '');

    $last = $db->query("SELECT balance FROM balance_ledger ORDER BY id DESC LIMIT 1")->fetchColumn() ?? 0;
    $new_bal = $type === 'credit' ? $last + $amount : $last - $amount;

    $db->prepare("INSERT INTO balance_ledger (txn_date, txn_type, amount, note, balance) VALUES (?,?,?,?,?)")
       ->execute([$date, $type, $amount, $note, $new_bal]);
    header("Location: balance.php"); exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bal'])) {
    $bid = (int)$_POST['delete_bal'];
    $db->prepare("DELETE FROM balance_ledger WHERE id=?")->execute([$bid]);
    // Recalculate balances
    $all = $db->query("SELECT * FROM balance_ledger ORDER BY txn_date ASC, id ASC")->fetchAll();
    $b   = 0;
    foreach ($all as $t) {
        $b = $t['txn_type']==='credit' ? $b + $t['amount'] : $b - $t['amount'];
        $db->prepare("UPDATE balance_ledger SET balance=? WHERE id=?")->execute([$b, $t['id']]);
    }
    header("Location: balance.php"); exit;
}

$ledger  = getBalanceLedger();
$curBal  = $ledger ? (float)end($ledger)['balance'] : 0;
$totalCr = array_sum(array_column(array_filter($ledger, fn($t)=>$t['txn_type']==='credit'), 'amount'));
$totalDr = array_sum(array_column(array_filter($ledger, fn($t)=>$t['txn_type']==='debit'),  'amount'));
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Balance Sheet — CarRent CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php pageHeader('balance'); ?>
<div class="app">

  <div class="page-title anim">
    <h2>💰 Balance Sheet</h2>
    <p>Online account / cash balance ledger</p>
  </div>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat-card green">
      <div class="stat-label">Total Credits</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($totalCr, 0) ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Total Debits</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($totalDr, 0) ?></div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Current Balance</div>
      <div class="stat-value"><span class="cur">₹</span><?= number_format($curBal, 0) ?></div>
      <div class="stat-sub"><?= count($ledger) ?> transactions</div>
    </div>
  </div>

  <!-- Add Entry -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="card-head"><h3>➕ Add Balance Entry</h3></div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="add_bal" value="1">
      <div style="display:grid;grid-template-columns:130px 1fr 1fr 130px auto;gap:10px;align-items:end">
        <div>
          <label class="form-label">Date</label>
          <input type="text" id="balDatePicker" class="cal-trigger" placeholder="Date chuniye…" readonly>
        <input type="hidden" name="bal_date" id="balDateVal" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div>
          <label class="form-label">Type</label>
          <select class="form-control" name="bal_type">
            <option value="credit">Credit (Add)</option>
            <option value="debit">Debit (Withdraw)</option>
          </select>
        </div>
        <div>
          <label class="form-label">Note</label>
          <input class="form-control" name="bal_note" placeholder="Description…">
        </div>
        <div>
          <label class="form-label">Amount (₹)</label>
          <input class="form-control" type="number" name="bal_amount" step="0.01" min="0.01" required placeholder="0.00">
        </div>
        <button class="btn btn-primary" type="submit">+ Add</button>
      </div>
    </form>
  </div>

  <!-- Ledger -->
  <div class="card anim-3">
    <div class="card-head">
      <h3>📒 Balance Ledger</h3>
      <span class="mono" style="font-size:.75rem;color:var(--muted)"><?= count($ledger) ?> entries</span>
    </div>
    <?php if (empty($ledger)): ?>
      <div class="empty"><div class="empty-icon">💰</div>No entries yet.</div>
    <?php else: ?>
    <div class="t-head" style="display:grid;grid-template-columns:100px 1fr 90px 120px 130px 40px">
      <span>Date</span><span>Note</span><span class="txt-center">Type</span>
      <span class="txt-right">Amount</span><span class="txt-right">Running Balance</span><span></span>
    </div>
    <?php foreach (array_reverse($ledger) as $t): ?>
    <div class="t-row rr" style="grid-template-columns:100px 1fr 90px 120px 130px 40px">
      <span class="mono" style="font-size:.75rem;color:var(--muted)"><?= fmtDate($t['txn_date']) ?></span>
      <span style="font-size:.85rem"><?= htmlspecialchars($t['note'] ?? '') ?></span>
      <span class="txt-center"><span class="badge badge-<?= $t['txn_type'] ?>"><?= strtoupper($t['txn_type']) ?></span></span>
      <span class="txt-right <?= $t['txn_type']==='credit' ? 'amt-pos' : 'amt-neg' ?>">
        <?= $t['txn_type']==='credit' ? '+' : '-' ?>₹<?= number_format($t['amount'], 2) ?>
      </span>
      <span class="txt-right amt-gold">₹<?= number_format($t['balance'], 2) ?></span>
      <form method="POST" style="display:inline">
        <input type="hidden" name="delete_bal" value="<?= $t['id'] ?>">
        <button class="btn btn-ghost btn-sm btn-icon" type="submit" onclick="return confirm('Delete?')" title="Delete">🗑</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
<footer>CarRent CRM · Balance Sheet</footer>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#balDatePicker", {
  dateFormat: "Y-m-d", defaultDate: "today",
  locale: { firstDayOfWeek: 1 },
  onChange: function(sel, dateStr) { document.getElementById("balDateVal").value = dateStr; },
  onReady: function(s,d,fp) { fp.input.value = flatpickr.formatDate(new Date(),"d M Y"); }
});
</script>
</body>
</html>
