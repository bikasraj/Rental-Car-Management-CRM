<?php
require_once __DIR__ . '/../includes/helpers.php';

$db      = getDB();
$drivers = getDrivers();
$selId   = isset($_GET['driver']) ? (int)$_GET['driver'] : ($drivers[0]['id'] ?? 0);

// ── Handle: Add Transaction ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_txn'])) {
    $driver_id = (int)$_POST['driver_id'];
    $date      = $_POST['txn_date'];
    $type      = $_POST['txn_type'];
    $amount    = (float)$_POST['txn_amount'];
    $note      = trim($_POST['txn_note'] ?? '');
    $last      = $db->prepare("SELECT balance FROM driver_ledger WHERE driver_id=? ORDER BY id DESC LIMIT 1");
    $last->execute([$driver_id]);
    $prev    = (float)($last->fetchColumn() ?? 0);
    $new_bal = $type === 'credit' ? $prev + $amount : $prev - $amount;
    $db->prepare("INSERT INTO driver_ledger (driver_id, txn_date, txn_type, amount, note, balance) VALUES (?,?,?,?,?,?)")
       ->execute([$driver_id, $date, $type, $amount, $note, $new_bal]);
    header("Location: driver-ledger.php?driver=$driver_id&tab=ledger"); exit;
}

// ── Handle: Delete Transaction ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_txn'])) {
    $txn_id = (int)$_POST['delete_txn'];
    $db->prepare("DELETE FROM driver_ledger WHERE id=?")->execute([$txn_id]);
    $txns = $db->prepare("SELECT * FROM driver_ledger WHERE driver_id=? ORDER BY txn_date ASC, id ASC");
    $txns->execute([$selId]);
    $all = $txns->fetchAll();
    $bal = 0;
    foreach ($all as $t) {
        $bal = $t['txn_type']==='credit' ? $bal + $t['amount'] : $bal - $t['amount'];
        $db->prepare("UPDATE driver_ledger SET balance=? WHERE id=?")->execute([$bal, $t['id']]);
    }
    header("Location: driver-ledger.php?driver=$selId&tab=ledger"); exit;
}

// ── Handle: Add New Loan (Diya paisa) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_loan'])) {
    $driver_id = (int)$_POST['loan_driver_id'];
    $date      = $_POST['loan_date'];
    $amount    = (float)$_POST['loan_amount'];
    $note      = trim($_POST['loan_note'] ?? '');
    if ($driver_id && $date && $amount > 0) {
        $db->prepare("INSERT INTO driver_loans (driver_id, loan_date, amount, note, status) VALUES (?,?,?,?,'pending')")
           ->execute([$driver_id, $date, $amount, $note]);
    }
    header("Location: driver-ledger.php?driver=$driver_id&tab=loans"); exit;
}

// ── Handle: Add Return (Wapsi) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_return'])) {
    $loan_id     = (int)$_POST['loan_id'];
    $return_date = $_POST['return_date'];
    $ret_amount  = (float)$_POST['return_amount'];
    $ret_note    = trim($_POST['return_note'] ?? '');
    if ($loan_id && $return_date && $ret_amount > 0) {
        $db->prepare("INSERT INTO driver_loan_returns (loan_id, return_date, amount, note) VALUES (?,?,?,?)")
           ->execute([$loan_id, $return_date, $ret_amount, $ret_note]);
        updateLoanStatus($loan_id);
    }
    // get driver_id of that loan
    $lrow = $db->prepare("SELECT driver_id FROM driver_loans WHERE id=?");
    $lrow->execute([$loan_id]);
    $lr = $lrow->fetch();
    $drid = $lr ? $lr['driver_id'] : $selId;
    header("Location: driver-ledger.php?driver=$drid&tab=loans"); exit;
}

// ── Handle: Delete Return ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_return'])) {
    $rid = (int)$_POST['delete_return'];
    $row = $db->prepare("SELECT loan_id FROM driver_loan_returns WHERE id=?");
    $row->execute([$rid]);
    $r = $row->fetch();
    $lid = $r ? $r['loan_id'] : 0;
    $db->prepare("DELETE FROM driver_loan_returns WHERE id=?")->execute([$rid]);
    if ($lid) updateLoanStatus($lid);
    header("Location: driver-ledger.php?driver=$selId&tab=loans"); exit;
}

// ── Handle: Delete Loan ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_loan'])) {
    $lid = (int)$_POST['delete_loan'];
    $db->prepare("DELETE FROM driver_loans WHERE id=?")->execute([$lid]);
    header("Location: driver-ledger.php?driver=$selId&tab=loans"); exit;
}

// ── Load Data ─────────────────────────────────────────────────
$selDriver  = null;
foreach ($drivers as $d) { if ($d['id'] == $selId) { $selDriver = $d; break; } }
$ledger     = $selId ? getDriverLedger($selId) : [];
$loans      = $selId ? getDriverLoans($selId)  : [];
$curBal     = $ledger ? (float)end($ledger)['balance'] : 0;
$totalCr    = array_sum(array_column(array_filter($ledger, fn($t)=>$t['txn_type']==='credit'), 'amount'));
$totalDr    = array_sum(array_column(array_filter($ledger, fn($t)=>$t['txn_type']==='debit'),  'amount'));
$outstanding = array_sum(array_column(
    array_filter($loans, fn($l)=>$l['status']!=='cleared'),
    'remaining'
));
$activeTab = $_GET['tab'] ?? 'ledger';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Driver Account — CarRent CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  /* ── Loan Card override for this page ─────────── */
  .loan-grid { display: flex; flex-direction: column; gap: 0; padding: 12px 0 4px; }
  .loan-summary-bar {
    display: flex; gap: 14px; flex-wrap: wrap;
    padding: 14px 20px; background: rgba(255,112,67,.04);
    border-bottom: 1px solid var(--border);
  }
  .lsb-item { display: flex; flex-direction: column; gap: 2px; }
  .lsb-label { font-size: .59rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 700; }
  .lsb-val   { font-family: 'JetBrains Mono', monospace; font-size: 1.05rem; font-weight: 700; }
  .lsb-divider { width: 1px; background: var(--border); align-self: stretch; margin: 2px 0; }
</style>
</head>
<body>
<?php pageHeader('driver-ledger'); ?>
<div class="app">

  <!-- Page Header -->
  <div class="page-title anim" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
    <div>
      <h2>👤 Driver Account</h2>
      <p>Transaction ledger, loans given & repayments</p>
    </div>
    <!-- Driver selector -->
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
      <label class="form-label" style="margin:0;white-space:nowrap">Driver:</label>
      <select class="form-control" name="driver" onchange="this.form.submit()" style="width:auto;min-width:160px">
        <?php foreach ($drivers as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $d['id']==$selId?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if ($selDriver): ?>

  <!-- Driver Profile Card -->
  <div class="card anim" style="margin-bottom:18px">
    <div style="padding:20px 24px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      <div style="width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:grid;place-items:center;font-size:1.5rem;flex-shrink:0;box-shadow:0 0 22px rgba(139,127,255,.3)">🧑‍✈️</div>
      <div style="flex:1;min-width:140px">
        <div style="font-size:1.1rem;font-weight:800"><?= htmlspecialchars($selDriver['name']) ?></div>
        <div style="color:var(--text2);font-size:.78rem;margin-top:2px;font-weight:500">
          <?= $selDriver['salary_pct'] ?>% salary &nbsp;·&nbsp;
          📞 <?= $selDriver['phone'] ?> &nbsp;·&nbsp;
          🚗 <?= htmlspecialchars($selDriver['car_name'] ?? '—') ?>
        </div>
      </div>
      <!-- Stats -->
      <div style="display:flex;gap:2px;flex-wrap:wrap">
        <?php
          $stats = [
            ['Total Given',   '₹'.number_format($totalCr,0), 'var(--accent2)'],
            ['Total Received','₹'.number_format($totalDr,0), 'var(--danger)'],
            ['Ledger Balance','₹'.number_format($curBal,0),  'var(--accent)'],
            ['Loan Due',      '₹'.number_format($outstanding,0), 'var(--accent4)'],
          ];
          foreach ($stats as [$lbl, $val, $clr]):
        ?>
        <div style="text-align:center;padding:10px 18px;border-left:1px solid var(--border)">
          <div class="sec-label" style="margin-bottom:4px"><?= $lbl ?></div>
          <div class="mono" style="font-size:1.05rem;font-weight:700;color:<?= $clr ?>"><?= $val ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Tab Navigation -->
  <div class="tab-nav anim-2">
    <a href="?driver=<?= $selId ?>&tab=ledger" class="<?= $activeTab==='ledger'?'active':'' ?>">📒 Transaction Ledger</a>
    <a href="?driver=<?= $selId ?>&tab=loans"  class="<?= $activeTab==='loans' ?'active':'' ?>">
      💸 Loans / Udhar
      <?php if ($outstanding > 0): ?><span style="background:var(--accent4);color:#fff;font-size:.58rem;padding:2px 7px;border-radius:20px;margin-left:4px;font-weight:700">DUE ₹<?= number_format($outstanding,0) ?></span><?php endif; ?>
    </a>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 1: LEDGER
  ═══════════════════════════════════════════════════════════ -->
  <?php if ($activeTab === 'ledger'): ?>

  <!-- Add Transaction -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="card-head"><h3>➕ Add Transaction</h3></div>
    <form method="POST" style="padding:18px 20px">
      <input type="hidden" name="add_txn" value="1">
      <input type="hidden" name="driver_id" value="<?= $selId ?>">
      <div style="display:grid;grid-template-columns:150px 140px 1fr 140px auto;gap:10px;align-items:end;flex-wrap:wrap">
        <div>
          <label class="form-label">📅 Date</label>
          <input class="cal-trigger" type="text" id="txnDatePicker" placeholder="Pick date…" readonly>
          <input type="hidden" name="txn_date" id="txnDateVal" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div>
          <label class="form-label">Type</label>
          <select class="form-control" name="txn_type" required>
            <option value="debit">💸 Diya (Debit)</option>
            <option value="credit">✅ Liya (Credit)</option>
          </select>
        </div>
        <div>
          <label class="form-label">Note / Description</label>
          <input class="form-control" name="txn_note" placeholder="e.g. Advance payment, weekly salary…">
        </div>
        <div>
          <label class="form-label">Amount (₹)</label>
          <input class="form-control" name="txn_amount" type="number" step="0.01" min="0.01" placeholder="0.00" required>
        </div>
        <div style="padding-bottom:1px">
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-primary" type="submit">+ Add</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Ledger Table -->
  <div class="card anim-3">
    <div class="card-head">
      <h3>📒 Transaction History</h3>
      <span class="mono" style="font-size:.73rem;color:var(--muted)"><?= count($ledger) ?> entries &nbsp;·&nbsp; Balance: <span style="color:var(--accent)">₹<?= number_format($curBal,2) ?></span></span>
    </div>
    <?php if (empty($ledger)): ?>
      <div class="empty"><div class="empty-icon">📒</div>No transactions yet.<br>Add your first entry above.</div>
    <?php else: ?>
    <div class="t-head" style="display:grid;grid-template-columns:100px 1fr 95px 130px 130px 44px">
      <span>Date</span><span>Note</span><span class="txt-center">Type</span>
      <span class="txt-right">Amount</span><span class="txt-right">Balance</span><span></span>
    </div>
    <?php $i=0; foreach (array_reverse($ledger) as $t): $i++; ?>
    <div class="t-row rr" style="grid-template-columns:100px 1fr 95px 130px 130px 44px">
      <span class="mono" style="font-size:.74rem;color:var(--muted)"><?= fmtDate($t['txn_date']) ?></span>
      <span style="font-size:.84rem;font-weight:500"><?= htmlspecialchars($t['note'] ?? '') ?></span>
      <span class="txt-center">
        <span class="badge badge-<?= $t['txn_type'] ?>"><?= $t['txn_type']==='credit'?'✅ LIYA':'💸 DIYA' ?></span>
      </span>
      <span class="txt-right <?= $t['txn_type']==='credit' ? 'amt-pos' : 'amt-neg' ?>">
        <?= $t['txn_type']==='credit' ? '+' : '-' ?>₹<?= number_format($t['amount'],2) ?>
      </span>
      <span class="txt-right amt-gold">₹<?= number_format($t['balance'],2) ?></span>
      <form method="POST" style="display:inline">
        <input type="hidden" name="delete_txn" value="<?= $t['id'] ?>">
        <button class="btn btn-ghost btn-sm btn-icon" type="submit" onclick="return confirm('Delete this entry?')" title="Delete">🗑</button>
      </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 2: LOANS / UDHAR
  ═══════════════════════════════════════════════════════════ -->
  <?php else: ?>

  <!-- Loan Summary Bar -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="loan-summary-bar">
      <?php
        $totalLoaned  = array_sum(array_column($loans, 'amount'));
        $totalReturned= array_sum(array_column($loans, 'returned'));
        $totalPending = count(array_filter($loans, fn($l)=>$l['status']==='pending'));
        $totalPartial = count(array_filter($loans, fn($l)=>$l['status']==='partial'));
        $totalCleared = count(array_filter($loans, fn($l)=>$l['status']==='cleared'));
      ?>
      <div class="lsb-item"><span class="lsb-label">Total Diya (Loaned)</span><span class="lsb-val" style="color:var(--accent4)">₹<?= number_format($totalLoaned,0) ?></span></div>
      <div class="lsb-divider"></div>
      <div class="lsb-item"><span class="lsb-label">Total Wapas (Returned)</span><span class="lsb-val" style="color:var(--accent2)">₹<?= number_format($totalReturned,0) ?></span></div>
      <div class="lsb-divider"></div>
      <div class="lsb-item"><span class="lsb-label">Still Due (Baaki)</span><span class="lsb-val" style="color:var(--danger)">₹<?= number_format($outstanding,0) ?></span></div>
      <div class="lsb-divider"></div>
      <div class="lsb-item"><span class="lsb-label">Pending</span><span class="lsb-val" style="color:var(--accent)"><?= $totalPending ?></span></div>
      <div class="lsb-item"><span class="lsb-label">Partial</span><span class="lsb-val" style="color:var(--accent5)"><?= $totalPartial ?></span></div>
      <div class="lsb-item"><span class="lsb-label">Cleared</span><span class="lsb-val" style="color:var(--accent2)"><?= $totalCleared ?></span></div>
    </div>
  </div>

  <!-- Add New Loan -->
  <div class="card anim-2" style="margin-bottom:18px">
    <div class="card-head">
      <h3>💸 Naya Udhar / Loan Diya</h3>
      <span style="font-size:.72rem;color:var(--muted)">Driver ko paisa diya</span>
    </div>
    <form method="POST" style="padding:18px 20px">
      <input type="hidden" name="add_loan" value="1">
      <input type="hidden" name="loan_driver_id" value="<?= $selId ?>">
      <div style="display:grid;grid-template-columns:170px 1fr 160px auto;gap:10px;align-items:end">
        <div>
          <label class="form-label">📅 Date Diya</label>
          <input type="text" id="loanDatePicker" class="cal-trigger" placeholder="Click — date chuniye…" readonly>
          <input type="hidden" name="loan_date" id="loanDateVal" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div>
          <label class="form-label">Note / Reason</label>
          <input class="form-control" name="loan_note" placeholder="e.g. Advance, Festival, Medical…">
        </div>
        <div>
          <label class="form-label">Amount Diya (₹)</label>
          <input class="form-control" name="loan_amount" type="number" step="0.01" min="1" placeholder="0.00" required>
        </div>
        <div>
          <label class="form-label">&nbsp;</label>
          <button class="btn btn-primary" type="submit">💸 Loan Add</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Loan List -->
  <div class="card anim-3">
    <div class="card-head">
      <h3>📋 Loan Records — <?= htmlspecialchars($selDriver['name']) ?></h3>
      <span class="mono" style="font-size:.73rem;color:var(--muted)"><?= count($loans) ?> loans</span>
    </div>

    <?php if (empty($loans)): ?>
      <div class="empty">
        <div class="empty-icon">💸</div>
        Koi loan record nahi hai.<br>
        Upar se naya loan add karo.
      </div>
    <?php else: ?>
    <div class="loan-grid">
      <?php foreach ($loans as $loan):
        $pct = $loan['amount'] > 0 ? min(100, round(($loan['returned'] / $loan['amount']) * 100)) : 0;
        $statusClass = 'st-'.$loan['status'];
      ?>
      <div class="loan-card <?= $statusClass ?>" id="loan-<?= $loan['id'] ?>">

        <!-- Loan Header (clickable to expand) -->
        <div class="loan-header" onclick="toggleLoan(<?= $loan['id'] ?>)">
          <div class="loan-info">
            <h4><?= htmlspecialchars($loan['note'] ?: 'Loan #'.$loan['id']) ?></h4>
            <div class="loan-meta">
              📅 <?= fmtDate($loan['loan_date']) ?> &nbsp;·&nbsp;
              <?= count($loan['returns']) ?> wapsi entries &nbsp;·&nbsp;
              <span class="badge badge-<?= $loan['status'] ?>"><?= strtoupper($loan['status']) ?></span>
            </div>
          </div>
          <div class="loan-amounts">
            <div class="loan-amt-item">
              <div class="loan-amt-label">Diya (Total)</div>
              <div class="loan-amt-val" style="color:var(--accent4)">₹<?= number_format($loan['amount'],0) ?></div>
            </div>
            <div class="loan-amt-item">
              <div class="loan-amt-label">Wapas Aaya</div>
              <div class="loan-amt-val" style="color:var(--accent2)">₹<?= number_format($loan['returned'],0) ?></div>
            </div>
            <div class="loan-amt-item">
              <div class="loan-amt-label">Baaki Hai</div>
              <div class="loan-amt-val" style="color:<?= $loan['remaining'] > 0 ? 'var(--danger)' : 'var(--accent2)' ?>">
                <?= $loan['remaining'] > 0 ? '₹'.number_format($loan['remaining'],0) : '✅ Clear' ?>
              </div>
            </div>
            <div class="loan-toggle" id="toggle-<?= $loan['id'] ?>">▼</div>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="loan-progress-wrap">
          <div class="loan-progress">
            <div class="loan-progress-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <div style="font-size:.62rem;color:var(--muted);margin-top:3px;font-family:'JetBrains Mono',monospace;padding-bottom:8px">
            <?= $pct ?>% returned
            <?php if ($loan['status'] === 'cleared'): ?>&nbsp;✅ Fully Cleared<?php endif; ?>
          </div>
        </div>

        <!-- Expandable Body: Returns List + Add Return Form -->
        <div class="loan-body" id="body-<?= $loan['id'] ?>">

          <!-- Existing Returns -->
          <?php if (!empty($loan['returns'])): ?>
            <p style="font-size:.68rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);font-weight:700;margin-bottom:8px">Wapsi History</p>
            <?php foreach ($loan['returns'] as $ret): ?>
            <div class="return-row">
              <span class="ret-date">📅 <?= date('d M Y', strtotime($ret['return_date'])) ?></span>
              <span class="ret-note"><?= htmlspecialchars($ret['note'] ?: '—') ?></span>
              <span class="ret-amt">+₹<?= number_format($ret['amount'],2) ?></span>
              <form method="POST" class="ret-del" style="display:inline">
                <input type="hidden" name="delete_return" value="<?= $ret['id'] ?>">
                <button class="btn btn-ghost btn-sm btn-icon" type="submit" onclick="return confirm('Delete this return entry?')" title="Delete">🗑</button>
              </form>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="font-size:.78rem;color:var(--muted);margin-bottom:10px">Abhi koi wapsi nahi hui.</p>
          <?php endif; ?>

          <!-- Add Return Form -->
          <?php if ($loan['status'] !== 'cleared'): ?>
          <form method="POST" class="add-return-form">
            <input type="hidden" name="add_return" value="1">
            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
            <div style="display:flex;gap:7px;flex-wrap:wrap;width:100%;align-items:flex-end">
              <div style="display:flex;flex-direction:column;gap:4px;min-width:150px">
                <label style="font-size:.62rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:700">📅 Wapsi Ki Date</label>
                <input type="text" class="ret-date-picker" id="retDate-<?= $loan['id'] ?>"
                  placeholder="Date chuniye…" readonly style="background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:7px;padding:7px 10px;font-size:.79rem;font-family:'Montserrat',sans-serif;font-weight:500;outline:none;cursor:pointer">
                <input type="hidden" name="return_date" class="ret-date-val" id="retDateVal-<?= $loan['id'] ?>" value="<?= date('Y-m-d') ?>">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:130px">
                <label style="font-size:.62rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:700">Note</label>
                <input name="return_note" placeholder="e.g. Pehli kisht, final payment…">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;min-width:120px">
                <label style="font-size:.62rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:700">Wapsi Amount (₹)</label>
                <input name="return_amount" type="number" step="0.01" min="0.01"
                  max="<?= $loan['remaining'] ?>" placeholder="Max: ₹<?= number_format($loan['remaining'],0) ?>" required>
              </div>
              <button class="btn btn-success btn-sm" type="submit" style="height:fit-content;align-self:flex-end">✅ Wapsi Add</button>
            </div>
          </form>
          <?php else: ?>
            <div style="text-align:center;padding:10px 0;font-size:.82rem;color:var(--accent2);font-weight:700">✅ Yeh loan poora clear ho gaya!</div>
          <?php endif; ?>

          <!-- Delete Loan -->
          <div style="text-align:right;margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.05)">
            <form method="POST" style="display:inline">
              <input type="hidden" name="delete_loan" value="<?= $loan['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('Yeh loan record delete karo?')">🗑 Delete Loan</button>
            </form>
          </div>
        </div><!-- /loan-body -->

      </div><!-- /loan-card -->
      <?php endforeach; ?>
    </div><!-- /loan-grid -->
    <?php endif; ?>
  </div><!-- /card -->
  <?php endif; // end tabs ?>

  <?php else: ?>
    <div class="empty card" style="padding:60px"><div class="empty-icon">👤</div>No drivers found. Add one in Settings.</div>
  <?php endif; ?>

</div><!-- /app -->
<footer>CarRent CRM &nbsp;·&nbsp; Driver Account — Udhar & Wapsi Tracker</footer>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// ── Flatpickr: Ledger date picker ──────────────────────────────
<?php if ($activeTab === 'ledger'): ?>
flatpickr("#txnDatePicker", {
  dateFormat: "Y-m-d",
  defaultDate: "today",
  locale: { firstDayOfWeek: 1 },
  onChange: function(sel, dateStr) {
    document.getElementById("txnDateVal").value = dateStr;
  },
  onReady: function(sel, dateStr, fp) {
    fp.input.value = flatpickr.formatDate(new Date(), "d M Y");
  }
});
<?php endif; ?>

// ── Flatpickr: Loan date picker ────────────────────────────────
<?php if ($activeTab === 'loans'): ?>
flatpickr("#loanDatePicker", {
  dateFormat: "Y-m-d",
  defaultDate: "today",
  locale: { firstDayOfWeek: 1 },
  onChange: function(sel, dateStr) {
    document.getElementById("loanDateVal").value = dateStr;
  },
  onReady: function(sel, dateStr, fp) {
    fp.input.value = flatpickr.formatDate(new Date(), "d M Y");
  }
});

// ── Flatpickr: Return date pickers (one per loan card) ─────────
document.querySelectorAll('.ret-date-picker').forEach(function(el) {
  var loanId = el.id.replace('retDate-', '');
  flatpickr(el, {
    dateFormat: "Y-m-d",
    defaultDate: "today",
    locale: { firstDayOfWeek: 1 },
    onChange: function(sel, dateStr) {
      document.getElementById('retDateVal-' + loanId).value = dateStr;
    },
    onReady: function(sel, dateStr, fp) {
      fp.input.value = flatpickr.formatDate(new Date(), "d M Y");
    }
  });
});

// ── Toggle Loan Body ───────────────────────────────────────────
function toggleLoan(id) {
  var body   = document.getElementById('body-' + id);
  var toggle = document.getElementById('toggle-' + id);
  var isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  toggle.textContent = isOpen ? '▼' : '▲';
}

// Auto-open first pending or partial loan
(function() {
  var cards = document.querySelectorAll('.loan-card.st-pending, .loan-card.st-partial');
  if (cards.length > 0) {
    var firstId = cards[0].id.replace('loan-', '');
    toggleLoan(firstId);
  }
})();
<?php endif; ?>
</script>
</body>
</html>
