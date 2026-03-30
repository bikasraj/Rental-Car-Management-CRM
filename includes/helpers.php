<?php
require_once __DIR__ . '/../config/db.php';

// ── Format Indian Rupee ──────────────────────────────────────
function inr(float $n, bool $sign = false): string {
    $f = '₹' . number_format(abs($n), 2, '.', ',');
    if ($sign && $n > 0) $f = '+' . $f;
    if ($n < 0) $f = '-' . $f;
    return $f;
}

// ── Format date ──────────────────────────────────────────────
function fmtDate(string $d): string {
    return date('d M Y', strtotime($d));
}

// ── Redirect ─────────────────────────────────────────────────
function redirect(string $url): void {
    header("Location: $url"); exit;
}

// ── Flash Messages ───────────────────────────────────────────
session_start();
function flash(string $key, string $msg, string $type = 'success'): void {
    $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
}
function getFlash(string $key): ?array {
    if (isset($_SESSION['flash'][$key])) {
        $f = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $f;
    }
    return null;
}

// ── Get all active cars ──────────────────────────────────────
function getCars(): array {
    return getDB()->query("SELECT * FROM cars WHERE status='active' ORDER BY name")->fetchAll();
}

// ── Get all active drivers ───────────────────────────────────
function getDrivers(): array {
    return getDB()->query("SELECT d.*, c.name as car_name, c.number as car_number
        FROM drivers d LEFT JOIN cars c ON d.car_id=c.id
        WHERE d.status='active' ORDER BY d.name")->fetchAll();
}

// ── Get all platforms ────────────────────────────────────────
function getPlatforms(): array {
    return getDB()->query("SELECT * FROM platforms ORDER BY id")->fetchAll();
}

// ── Get weekly report with full data ────────────────────────
function getWeeklyReport(int $id): ?array {
    $db = getDB();
    $report = $db->prepare("SELECT wr.*, c.name as car_name, c.number as car_number,
        d.name as driver_name, d.salary_pct
        FROM weekly_reports wr
        JOIN cars c ON wr.car_id=c.id
        JOIN drivers d ON wr.driver_id=d.id
        WHERE wr.id=?");
    $report->execute([$id]);
    $r = $report->fetch();
    if (!$r) return null;

    // Platform earnings
    $pe = $db->prepare("SELECT pe.*, p.name, p.icon, p.color
        FROM platform_earnings pe JOIN platforms p ON pe.platform_id=p.id
        WHERE pe.weekly_report_id=? ORDER BY p.id");
    $pe->execute([$id]);
    $r['platforms'] = $pe->fetchAll();

    // Expenses
    $exp = $db->prepare("SELECT * FROM expenses WHERE weekly_report_id=? ORDER BY expense_type DESC, id");
    $exp->execute([$id]);
    $r['expenses'] = $exp->fetchAll();

    // Driver payments
    $dp = $db->prepare("SELECT * FROM driver_payments WHERE weekly_report_id=? ORDER BY payment_date");
    $dp->execute([$id]);
    $r['driver_payments'] = $dp->fetchAll();

    // Home Take Received
    $htr = $db->prepare("SELECT * FROM home_take_received WHERE weekly_report_id=? ORDER BY received_date ASC");
    $htr->execute([$id]);
    $r['home_take_received'] = $htr->fetchAll();

    // Calculations
    $r['total_net']           = array_sum(array_column($r['platforms'], 'net_earning'));
    $r['total_cash']          = array_sum(array_column($r['platforms'], 'cash_received'));
    $r['total_expenses']      = array_sum(array_column($r['expenses'], 'amount'));
    $r['driver_salary']       = round($r['total_net'] * ($r['salary_pct'] / 100), 2);
    $r['saving']              = $r['total_cash'] - $r['total_expenses'];
    $r['driver_paid']         = array_sum(array_column($r['driver_payments'], 'amount'));
    $r['home_take']           = $r['saving'] - $r['driver_paid'];
    $r['home_take_received_total'] = array_sum(array_column($r['home_take_received'], 'amount'));
    $r['home_take_due']       = $r['home_take'] - $r['home_take_received_total'];

    return $r;
}

// ── Get all weekly reports ───────────────────────────────────
function getAllReports(): array {
    return getDB()->query("SELECT wr.*, c.name as car_name, d.name as driver_name,
        (SELECT SUM(net_earning) FROM platform_earnings WHERE weekly_report_id=wr.id) as total_net,
        (SELECT SUM(cash_received) FROM platform_earnings WHERE weekly_report_id=wr.id) as total_cash
        FROM weekly_reports wr
        JOIN cars c ON wr.car_id=c.id
        JOIN drivers d ON wr.driver_id=d.id
        ORDER BY wr.week_start DESC")->fetchAll();
}

// ── Driver Ledger ────────────────────────────────────────────
function getDriverLedger(int $driver_id): array {
    $st = getDB()->prepare("SELECT * FROM driver_ledger WHERE driver_id=? ORDER BY txn_date ASC, id ASC");
    $st->execute([$driver_id]);
    return $st->fetchAll();
}

// ── Online Balance ───────────────────────────────────────────
function getBalanceLedger(): array {
    return getDB()->query("SELECT * FROM balance_ledger ORDER BY txn_date ASC, id ASC")->fetchAll();
}
function getCurrentBalance(): float {
    $r = getDB()->query("SELECT balance FROM balance_ledger ORDER BY id DESC LIMIT 1")->fetch();
    return $r ? (float)$r['balance'] : 0;
}

// ── Get Driver Loans ─────────────────────────────────────────
function getDriverLoans(int $driver_id): array {
    $db = getDB();
    $st = $db->prepare("SELECT l.*,
        COALESCE((SELECT SUM(amount) FROM driver_loan_returns WHERE loan_id=l.id),0) as returned,
        (l.amount - COALESCE((SELECT SUM(amount) FROM driver_loan_returns WHERE loan_id=l.id),0)) as remaining
        FROM driver_loans l WHERE l.driver_id=? ORDER BY l.loan_date DESC");
    $st->execute([$driver_id]);
    $loans = $st->fetchAll();
    foreach ($loans as &$loan) {
        $ret = $db->prepare("SELECT * FROM driver_loan_returns WHERE loan_id=? ORDER BY return_date ASC");
        $ret->execute([$loan['id']]);
        $loan['returns'] = $ret->fetchAll();
    }
    return $loans;
}

// ── Total Outstanding Loans for a Driver ─────────────────────
function getDriverOutstanding(int $driver_id): float {
    $db = getDB();
    $st = $db->prepare("SELECT COALESCE(SUM(l.amount) - COALESCE(SUM(r.total_ret),0), 0)
        FROM driver_loans l
        LEFT JOIN (SELECT loan_id, SUM(amount) as total_ret FROM driver_loan_returns GROUP BY loan_id) r
        ON r.loan_id = l.id
        WHERE l.driver_id=? AND l.status != 'cleared'");
    $st->execute([$driver_id]);
    return (float)($st->fetchColumn() ?? 0);
}

// ── Update loan status ────────────────────────────────────────
function updateLoanStatus(int $loan_id): void {
    $db = getDB();
    $loan = $db->prepare("SELECT amount FROM driver_loans WHERE id=?")->execute([$loan_id]);
    $row  = getDB()->query("SELECT amount FROM driver_loans WHERE id=$loan_id")->fetch();
    if (!$row) return;
    $returned = getDB()->query("SELECT COALESCE(SUM(amount),0) FROM driver_loan_returns WHERE loan_id=$loan_id")->fetchColumn();
    $status = 'pending';
    if ($returned >= $row['amount']) $status = 'cleared';
    elseif ($returned > 0)          $status = 'partial';
    getDB()->prepare("UPDATE driver_loans SET status=? WHERE id=?")->execute([$status, $loan_id]);
}

// ── Header HTML ─────────────────────────────────────────────
function pageHeader(string $active = ''): void {
    $pages = [
        'dashboard'    => ['Dashboard',    '📊', 'index.php'],
        'weekly'       => ['Weekly Report','📋', 'pages/weekly.php'],
        'new-report'   => ['New Report',   '➕', 'pages/new-report.php'],
        'driver-ledger'=> ['Driver A/C',   '👤', 'pages/driver-ledger.php'],
        'loans'        => ['Loans/Udhar',  '💸', 'pages/loans.php'],
        'balance'      => ['Balance Sheet','💰', 'pages/balance.php'],
        'settings'     => ['Settings',     '⚙️',  'pages/settings.php'],
    ];
    $inPages = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
    $prefix  = $inPages ? '../' : '';
    ?>
    <header class="top-bar">
      <div class="brand">
        <div class="brand-icon">🚗</div>
        <h1>Car<span>Rent</span> CRM</h1>
      </div>
      <nav class="top-nav">
        <?php foreach ($pages as $key => [$label, $icon, $path]): ?>
          <a href="<?= $prefix.$path ?>" class="<?= $active===$key ? 'active':'' ?>">
            <?= $icon ?> <?= $label ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="top-bar-right">
        <span class="today-badge"><?= date('d M Y') ?></span>
      </div>
    </header>
    <?php
}
