# 🚗 CarRent CRM — Rental Car Income Manager

A complete PHP + MySQL CRM to manage your rental car fleet income.

---

## 📁 Project Structure

```
carrent-crm/
├── index.php                 ← Dashboard
├── config/
│   └── db.php                ← Database config
├── includes/
│   └── helpers.php           ← Functions & page header
├── assets/
│   └── css/style.css         ← Global styles
├── pages/
│   ├── weekly.php            ← View / list weekly reports
│   ├── new-report.php        ← Create new weekly report
│   ├── driver-ledger.php     ← Driver account & transactions
│   ├── balance.php           ← Online/cash balance sheet
│   ├── settings.php          ← Manage cars, drivers, platforms
│   └── delete-report.php     ← Delete utility
└── database.sql              ← MySQL schema + sample data
```

---

## ⚙️ Installation Steps

### 1. Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache / Nginx (or use PHP built-in server)

### 2. Database Setup
```sql
-- In phpMyAdmin or MySQL terminal:
SOURCE /path/to/carrent-crm/database.sql;
```
OR open phpMyAdmin → Import → Select `database.sql`

### 3. Configure DB Connection
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');
define('DB_NAME', 'carrent_crm');
```

### 4. Place Files
- Copy the `carrent-crm` folder to your web root:
  - XAMPP: `C:/xampp/htdocs/carrent-crm/`
  - LAMP:  `/var/www/html/carrent-crm/`

### 5. Open in Browser
```
http://localhost/carrent-crm/
```

---

## 🚀 Features

| Feature | Description |
|---|---|
| 📊 Dashboard | Overview of total earnings, cash, expenses, balance |
| 📋 Weekly Reports | Create & view weekly income per platform |
| 🚀 Platform Earnings | Ola, Uber, Rapido, Contract, Yatri Sathi etc. |
| 💸 Expenses | Auto driver salary (32%) + manual fuel/washing entries |
| 🏠 Home Take | Auto calculated: Cash − Expenses − Driver Paid |
| 👤 Driver A/C | Full credit/debit ledger per driver |
| 💰 Balance Sheet | Online/account balance with running ledger |
| ⚙️ Settings | Add/delete cars, drivers, platforms |

---

## 🧮 Calculations

```
Total Net Earning  = Sum of all platform net earnings
Driver Salary      = Total Net × salary% (default 32%)
Total Expenses     = Driver Salary + Oil + Washing + Others
Saving             = Total Cash − Total Expenses
Home Take          = Saving − Driver Paid this week
```

---

## 📊 Sample Data (11 Jan – 18 Jan)

| Platform | Net Earning | Cash |
|---|---|---|
| Ola | ₹3,333 | — |
| Uber | ₹888 | — |
| Rapido | ₹6,645 | — |
| Contract | ₹3,123 | ₹12,788 |
| Yatri Sathi | ₹937 | — |
| **Total** | **₹14,926** | **₹12,788** |

Driver Salary (32%) = ₹4,776  
Oil = ₹3,500 | Washing = ₹646  
**Home Take = ₹1,006**

Balance: ₹37,312 + ₹3,000 − ₹5,000 = **₹35,312**
