<?php
require_once __DIR__ . '/../includes/helpers.php';
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    getDB()->prepare("DELETE FROM weekly_reports WHERE id=?")->execute([$id]);
}
header("Location: weekly.php"); exit;
