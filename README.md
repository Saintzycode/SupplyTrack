# SchoolSupply — Inventory System

A web-based inventory management system for a school-supplies distributor (single
warehouse, currency **PHP ₱**). It tracks items, stock movements, suppliers,
schools, and the distribution of supplies to schools.

Built with plain PHP 8 + PDO on XAMPP — **no frameworks, no Composer**.

## Features

**Admin**
- Dashboard with stock summaries and a "Stock by category" chart
- Items: add / edit, soft-delete, preferred suppliers, live on-hand quantity
- Categories: add / rename / delete (only while unused)
- Suppliers and Schools: manage records, view linked items / distribution history
- Reports: Stock Status, Stock Transactions, Distribution — with period presets,
  filters, pagination, and **Export PDF / Export Excel**
- Accounts: create / edit / deactivate Staff, reset Staff passwords, change own password

**Staff**
- Record stock-in and stock-out
- Record distributions (a stock-out to a school, multi-line)
- View items (read-only for master data)

## Tech stack

| Layer | Choice |
|---|---|
| Language | PHP 8.x (XAMPP ships 8.4), PDO, prepared statements |
| Database | MySQL / MariaDB, database `inventory_db` |
| UI | HTML + CSS, Bootstrap 5.3 + Bootstrap Icons (CDN), Chart.js (CDN, dashboard) |
| Server | Apache (XAMPP), runs at `http://localhost/InventorySystem` |

No build step and no dependencies to install — just PHP and MySQL.

## Requirements

- XAMPP (or any Apache + PHP 8.x + MySQL/MariaDB stack) with the **PDO MySQL** extension (enabled by default in XAMPP)
- A modern browser (Chrome, Firefox, or Edge)

## Getting started

See **[SETUP.md](SETUP.md)** for full, step-by-step install and
"move to another device" instructions.

Quick version:
1. Copy this folder into `htdocs` (keep the folder name `InventorySystem`).
2. Create the `inventory_db` database and import the schema (tables, views, triggers).
3. Start Apache + MySQL, open `http://localhost/InventorySystem`.
4. Create the first administrator at `setup_admin.php`, then **delete that file**.

## Roles and access

| Role | Can do |
|---|---|
| `admin` | Everything: users, categories, items, suppliers, schools, inventory, distribution, reports |
| `staff` | Stock-in, stock-out and distribution; can view items only |

Admin-only pages call `require_admin()` on the server (hiding menu links is not enough).

## Project structure

```
InventorySystem/
├── index.php            Dashboard
├── login.php / logout.php
├── setup_admin.php      One-time first admin (delete after use)
├── inventory.php        Item list
├── item_form.php        Add / edit item
├── categories.php       Categories
├── stock_in.php         Record stock-in
├── stock_out.php        Record stock-out
├── suppliers.php / supplier_form.php
├── schools.php / school_form.php
├── distribution.php     Record a distribution
├── reports.php          Reports (admin only)
├── report_export.php    PDF / Excel export (admin only)
├── admin.php            Users, own password, low-stock alerts (admin only)
├── config/              config.php, database.php
├── includes/            functions, auth, audit, layout, reports_lib, pdf, ...
└── assets/              css/style.css, js/*
```

## Reports & exports

All three report tabs (Stock Status, Stock Transactions, Distribution) share
`includes/reports_lib.php`, which also drives the on-screen tables — so exports
always match what you see. Exports are generated **server-side** (no browser PDF
library): the Excel file is an HTML table sent as `application/vnd.ms-excel`, and
the PDF is drawn by the small built-in `SimplePdf` class in `includes/pdf.php`.
Every export is written to the audit log.

## Database notes

- Stock levels are **never** updated directly. The app inserts into
  `stock_transactions`; database **triggers** update `items.quantity_on_hand`,
  raise low-stock alerts, reject negative stock, and auto-create a `stock_out`
  when a `distribution_items` row is added.
- Because triggers and views do the heavy lifting, any database backup/restore
  must include **triggers and views** (see SETUP.md).

## Security

- Passwords hashed with `password_hash(..., PASSWORD_BCRYPT)`
- Login lockout after repeated failures; 30-minute inactivity timeout
- CSRF tokens on every POST form; all output escaped with `e()`
- All SQL uses prepared statements

Before going live, read the hardening checklist in **[SETUP.md](SETUP.md)**.
