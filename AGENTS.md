# AI Guide: SchoolSupply Inventory System

Read this file fully before writing any code. It is the source of truth for this project.

## 1. What this project is
A web-based inventory management system for a school supplies distributor (single warehouse, currency PHP ₱). It tracks items, stock movements, suppliers, schools, and distributions of supplies to schools.

## 2. Tech stack (do not change)
- Plain PHP 8.x (XAMPP ships 8.4) with PDO. **No frameworks** (no Laravel, no Composer packages).
- MySQL / MariaDB, database name `inventory_db`, managed in phpMyAdmin.
- HTML, CSS, Bootstrap 5.3 via CDN, Bootstrap Icons via CDN.
- Runs on XAMPP (Apache) at `http://localhost/InventorySystem`.
- UI design lives in Figma: https://www.figma.com/design/utcPZgJfaLbh40oaRxdg0g (match its layout and colors).

## 3. Your working style
- Give **complete, copy-paste-ready files**, each labeled with its full path (e.g. `includes/auth.php`). Never give partial snippets unless editing one small part, and then say exactly where it goes.
- Work **one file or one feature at a time**, in the order in section 9.
- Explain simply, with step-by-step instructions. The user is a student and a hands-on learner.
- Do not redesign the database. Build around it.
- Do not add features outside the scope in section 10.
- When you finish a page, tell the user exactly how to test it (what to click, what should happen).

## 4. Folder structure
```
InventorySystem/
├── README.md            Project overview, features, stack
├── SETUP.md             Install + move-to-another-device guide
├── index.php            Dashboard
├── login.php            Sign in
├── logout.php           Sign out
├── setup_admin.php      One-time first admin (delete after use)
├── inventory.php        Item list (search, category/status filter, pagination)
├── item_form.php        Add / edit item, soft-delete, preferred suppliers (admin only)
├── categories.php       Item categories list + add / edit / delete-unused (admin only)
├── stock_in.php         Record stock-in
├── stock_out.php        Record stock-out
├── adjustment.php       Record a stock adjustment (+/-, reason; admin only)
├── suppliers.php        Supplier list (admin only)
├── supplier_form.php    Add / edit supplier + its items (admin only)
├── schools.php          School list (admin only)
├── school_form.php      Add / edit school + its distribution history (admin only)
├── distribution.php     Record a distribution (school, multi-line items)
├── reports.php          Reports (admin only): 3 tabs, period presets, filters, pagination
├── report_export.php    PDF / Excel export for reports (admin only)
├── admin.php            User management, own password, low-stock alerts (admin only)
├── config/config.php    Constants (DB, lockout, timeout)
├── config/database.php  db() returns the shared PDO
├── includes/functions.php  e(), url(), redirect(), flash(), csrf_*(), money(), fmt_date(), fmt_datetime()
├── includes/audit.php   audit_log()
├── includes/auth.php    attempt_login(), require_login(), require_admin(), is_admin(), current_user()
├── includes/header.php  Layout top (sidebar + top bar). Calls require_login()
├── includes/footer.php  Layout bottom + Bootstrap JS + password-toggle.js
├── includes/forbidden.php  403 page
├── includes/reports_lib.php  Shared report catalogue, filters, period presets, queries, totals and formatting
├── includes/pdf.php     SimplePdf - tiny dependency-free PDF writer for report exports
├── includes/report_foot.php  Totals strip + pagination partial for the report tables
├── includes/.htaccess   Deny direct web access
├── assets/css/style.css Custom styles (design tokens as CSS variables)
├── assets/js/password-toggle.js  Show / hide password fields
└── assets/js/clock.js            Live clock in the top bar
```
New pages go in the project root. Shared PHP logic goes in `includes/`. Use `__DIR__` in every include path.

## 5. Roles and access
| Role | Access |
|---|---|
| `admin` | Everything: users, categories, items, suppliers, schools, inventory, distribution, reports |
| `staff` | Stock-in, stock-out and distribution transactions only. Can view items. Cannot create, edit or delete categories, items, suppliers, schools or users |

- Every admin-only page must call `require_admin()` **before** including `header.php`. Hiding a menu link is not enough.
- Every other page includes `header.php`, which already enforces login and the 30-minute timeout.

## 6. Database (already built, do not change)
Tables: `users`, `categories`, `suppliers`, `items`, `item_suppliers` (many-to-many), `schools`, `distributions`, `distribution_items`, `stock_transactions`, `low_stock_alerts`, `audit_logs`.
Views: `v_stock_status`, `v_stock_transaction_report`, `v_distribution_report`, `v_dashboard_summary`.

Key columns:
- `categories`: category_id, category_name (UNIQUE), description (nullable). No `is_active` column, so a category is only deletable while no item uses it.
- `items`: item_id, sku (UNIQUE), item_name, description, category_id, unit_of_measure, reorder_point, unit_cost, quantity_on_hand, is_active
- `stock_transactions`: item_id, transaction_type (`stock_in`, `stock_out`, `adjustment`), quantity_change (signed), quantity_after, supplier_id (nullable), distribution_item_id (nullable), reason, remarks, transaction_date, created_by
- `distributions`: school_id, distribution_date, remarks, recorded_by. `distribution_items`: distribution_id, item_id, quantity
- `users`: username, email (both UNIQUE), password_hash, role, is_active, failed_attempts, locked_until

### What the database triggers already do (never duplicate this in PHP)
- Before insert on `stock_transactions`: rejects inactive items, rejects anything that would make stock negative, rejects wrong signs, and sets `quantity_after`. Errors use SQLSTATE `45000` with a readable message.
- After insert on `stock_transactions`: updates `items.quantity_on_hand`, opens a `low_stock_alerts` row when quantity is at or below the reorder point, and resolves open alerts when it goes back above.
- Update and delete on `stock_transactions` are blocked (the history is non-editable).
- After insert on `distribution_items`: automatically inserts a `stock_out` row into `stock_transactions`.

## 7. Coding rules (mandatory)
1. Use `db()` and **prepared statements** for every query. Never put user input in SQL strings.
2. **Never update `items.quantity_on_hand` directly.** Insert into `stock_transactions` instead.
3. Sign rules: `stock_in` is positive, `stock_out` is negative, `adjustment` can be either but never zero.
4. Recording a distribution: in **one PDO transaction**, insert the `distributions` row, then each `distribution_items` row. Roll back on any exception.
5. Catch `PDOException` and, when `$e->getCode() === '45000'`, show `$e->errorInfo[2]` to the user as a friendly red alert. Other errors: log with `error_log()` and show a generic message.
6. Escape all output with `e()`.
7. Every POST form needs `<?= csrf_field() ?>` and a `csrf_verify()` check before processing.
8. Show messages with `flash('success'|'danger'|'warning', '...')` then `redirect(...)` (post/redirect/get).
9. Call `audit_log(action, table, record_id, details)` after every create, update, delete (soft-delete), password reset, stock adjustment, and report export.
10. Hash passwords only with `password_hash($pw, PASSWORD_BCRYPT)` and verify with `password_verify()`.
11. Validate input on the server, and show errors inline next to the field (Bootstrap `is-invalid` and `invalid-feedback`). Keep the user's typed values after an error.
12. Soft-delete only (`is_active = 0`). Never `DELETE` items, suppliers, schools or users. Inactive items stay in history but cannot be used in new transactions.
13. Use Bootstrap 5 components (modals, tables, forms, alerts). Reuse the custom classes in `style.css` (`card-ui`, `table-ui`, `badge-soft`, `badge-low`, `badge-in`, `badge-out`, `badge-adj`, `stat-card`, `page-head`).
14. Use `money($x)` for prices and `fmt_date()` / `fmt_datetime()` for dates.
15. Target PHP 8.x (XAMPP ships 8.4): `match`, `never`, `str_contains()` and `?->` are allowed. Do not add Composer or a PHP PDF library — `includes/pdf.php` (the own `SimplePdf` class) draws the report PDF server-side, and the Excel export is an HTML table sent as `application/vnd.ms-excel`.
16. No closing `?>` tag at the end of pure-PHP files (prevents stray whitespace breaking headers, downloads and PDFs).

### Known gotchas (learned the hard way)
- MySQL `UNSIGNED` columns: `quantity_on_hand - reorder_point` throws "BIGINT UNSIGNED value is out of range". Cast both with `CAST(x AS SIGNED)` first.
- In a MySQL `UPDATE ... SET a = ..., b = ...`, assignments run left to right, so a later column sees the updated value of an earlier one.
- After `session_destroy()` PHP does not send a new session cookie, which loses flash messages. Use `destroy_session()` from `auth.php` (it regenerates the ID instead).
- Duplicate SKU or school name: catch SQLSTATE `23000` (duplicate entry) and show "SKU already exists" as an inline field error.
- PDO runs with `EMULATE_PREPARES` off, so a named placeholder can be used only once per query. Use `:q1` and `:q2` instead of `:q` twice.
- File downloads (PDF/Excel): clear output buffers before sending headers, and never echo anything else, or the file is corrupted.
- Never save a `.php` file with a UTF-8 BOM. Bytes written before `session_start()`/`header()` break sessions and corrupt downloads. Keep files saved as "UTF-8 without BOM".
- Low-stock alerts come only from the `stock_transactions` trigger, so an item created at 0 stock would not appear in the alerts list. `item_form.php` opens the alert itself when the new item has `reorder_point > 0`. To backfill existing items: insert into `low_stock_alerts` for active items where `quantity_on_hand <= reorder_point` and no open alert exists.

## 8. Requirements reference (condensed)
**Items:** admin adds and edits items (SKU, name, description, category, unit, reorder point, unit cost); soft-delete; duplicate SKU blocked; filter and group by category; show real-time on-hand quantity.
**Categories (admin):** `categories.php` lists categories with their item counts and lets an admin add, rename and delete a category. `category_name` is UNIQUE (duplicate shows an inline error). A category can only be deleted while no item references it; otherwise the delete is blocked with a friendly message. No deactivate (the table has no `is_active`), so deleting an unused category is the only removal. Every create, edit and delete is audit-logged.
**Stock:** staff record stock-in (item, quantity, supplier, date, remarks) and stock-out (item, quantity, reason, date); negative stock blocked; low-stock alert automatic; adjustments (increase/decrease with a reason, `adjustment.php`, admin only) logged with user and time.
**Suppliers (admin):** company, contact person, phone, email, address, payment terms; deactivate; items can have several preferred suppliers; supplier profile lists its items.
**Schools (admin):** name (unique), contact person, phone, email, address; deactivate; school profile lists its distributions.
**Distribution (staff and admin):** choose school, add one or more item lines with quantities, date, remarks; counts as stock-out; blocked if it exceeds on-hand; per-school history shows date, items, quantities, recording staff.
**Reports (admin):** Stock Status (item, on-hand, reorder point, Normal/Low), Stock Transactions (filter by period preset or date range, item, type), Distribution (filter by period preset or date range, school, item). Period presets: Custom, This week, Last week, Last 7 days, This month. Each tab has **Export PDF** and **Export Excel** buttons that export all rows matching the current filters (FR-7.5). Exports are written to the audit log.
**Accounts (admin):** create, edit, deactivate and delete Staff accounts; reset a Staff password in the system (no email); admin changes own password.
**Non-functional:** pages load within 3 seconds; works on current Chrome, Firefox and Edge; responsive on desktop, tablet and phone (off-canvas sidebar + compact top bar under 992px); supports 10,000 items and 50,000 transactions (so paginate every list, 10 to 25 rows per page).

## 9. Current status and what to build next
**Done:** Phase 1 (config, PDO connection, helpers, audit log, authentication with lockout and timeout, role checks, shared layout, login, logout, dashboard, one-time admin setup) and build steps 1–9: `inventory.php`, `item_form.php`, `stock_in.php`, `stock_out.php`, `suppliers.php` / `supplier_form.php`, `schools.php` / `school_form.php`, `distribution.php`, `reports.php` with PDF/Excel export (`report_export.php`, `includes/reports_lib.php`, `includes/pdf.php`), and `admin.php`. Also done: `categories.php` (step 11) - list with item counts, add/rename, delete-unused only (no `is_active` column); and `adjustment.php` - admin-only stock adjustment (increase/decrease with a required reason), reachable from an Inventory row action.

**Reports export:** `report_export.php` (admin only) streams an HTML-table Excel file or a server-drawn PDF via `includes/pdf.php` (`SimplePdf`). Both are built from `includes/reports_lib.php`, which also drives the on-screen tables, so exports always match the screen. Every export writes an `audit_log` row. No Composer, no browser PDF library.

**Build in this order (one at a time, wait for the user to confirm each works):**
10. Polish: empty states, input validation review, security review, responsive check at 1024px and 768px.

**Extras added beyond the original plan (kept in scope by the user):** dashboard "Stock by category" chart (`index.php` + `assets/js/dashboard-chart.js`, Chart.js via CDN, data from a MySQL query on each load — not auto-refreshing); a "Generate" button on the Admin → Users reset-password form that fills a random temporary password.

## 10. Out of scope (do not build)
Point of sale, accounting or general ledger, formal purchase orders, bid and quotation management, per-school order and delivery history, e-commerce integration, multi-warehouse, email or SMTP, self-service password change for Staff.

## 11. Definition of done for any page
- Matches the Figma layout and the existing look (sidebar layout, cards, badges).
- Role access enforced on the server.
- All SQL uses prepared statements. All output escaped. All POST forms use CSRF.
- Errors from triggers shown as friendly messages.
- Audit log written for changes.
- Tested with both an admin and a staff account.
- No PHP warnings or notices (`error_reporting(E_ALL)` is on).
- The user is told exactly how to test it.