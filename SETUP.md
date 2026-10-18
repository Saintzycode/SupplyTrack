# Setup Guide

How to install the **SchoolSupply Inventory System** on a machine, and how to
move it to a different device.

- Stack: XAMPP (Apache + PHP 8.x + MySQL/MariaDB)
- URL: `http://localhost/InventorySystem`
- Database name: `inventory_db`

> There is **no** `inventory_db.sql` committed to this repository. You create the
> database by exporting it from a working copy and importing it on the new machine
> (or by generating a dump — see step 3). This is important: the app relies on
> database **triggers and views**, so a plain "tables and data" copy is not enough.

---

## A. Fresh install on a single machine

### 1. Install XAMPP
Install a recent XAMPP (PHP **8.x**). Start **Apache** and **MySQL** from the
XAMPP Control Panel.

### 2. Copy the project
Copy this whole folder into XAMPP's web root:

```
C:\xampp\htdocs\InventorySystem
```

Keep the folder name exactly `InventorySystem`, because `config/config.php` sets
`BASE_URL` to `/InventorySystem`. If you rename the folder, update `BASE_URL` too.

### 3. Create the database

Pick **one** of these:

**Option A — import a dump from a working copy (recommended)**

On a machine where the app already works:
1. Open phpMyAdmin → select `inventory_db` → **Export** → method **Quick**,
   format **SQL** → **Go** (save the `.sql` file).
2. On the new machine: phpMyAdmin → **Import** → choose that file → **Go**.

**Option B — generate a dump from the command line**

From a working copy, run (XAMPP's bundled tools):

```
C:\xampp\mysql\bin\mysqldump.exe -uroot --databases inventory_db --triggers --routines > inventory_db.sql
```

Then import it on the new machine:

```
C:\xampp\mysql\bin\mysql.exe -uroot < inventory_db.sql
```

(`--databases` includes `CREATE DATABASE` + `USE`; `--triggers` is essential.)

> The app's `config/config.php` expects MySQL user `root` with an **empty
> password** on port `3306` — the XAMPP defaults. Change `DB_USER` / `DB_PASS` /
> `DB_PORT` in `config/config.php` if your setup differs.

### 4. Open the app
Go to `http://localhost/InventorySystem`. You should see the login page.

### 5. Create the first administrator
Visit `http://localhost/InventorySystem/setup_admin.php` and fill in the form.
It only works while the `users` table is empty.

**After it succeeds, delete `setup_admin.php` from the server.**

Then sign in at `http://localhost/InventorySystem/login.php` and create Staff
accounts from **Admin → Users**.

---

## B. Move to a different device

1. **Install XAMPP** on the other device and start Apache + MySQL.
2. **Copy the project folder** `InventorySystem` into that device's `htdocs`.
3. **Move the database** with an export/import (see step 3 above). Always include
   triggers and views.
4. **Open** `http://localhost/InventorySystem` and sign in with an account that
   exists in the imported database.
5. **Only if needed**, edit `config/config.php`:
   - folder renamed → change `BASE_URL` (e.g. `/myapp`)
   - MySQL password or port different → change `DB_PASS` / `DB_PORT`

Notes:
- Don't run `setup_admin.php` again if you imported the `users` table — you already
  have accounts. (It refuses to run when a user exists, but keep it deleted anyway.)
- There is **no email/SMTP**: passwords are set/reset inside the app only.
- Files must be served by Apache (not opened as `file://`).

---

## C. Configuration reference

All settings live in `config/config.php`:

| Constant | Purpose | Default |
|---|---|---|
| `DB_HOST` / `DB_PORT` | MySQL host/port | `127.0.0.1` / `3306` |
| `DB_NAME` | Database name | `inventory_db` |
| `DB_USER` / `DB_PASS` | MySQL credentials | `root` / *(empty)* |
| `BASE_URL` | Folder under `htdocs` | `/InventorySystem` |
| `MAX_FAILED_ATTEMPTS` | Failed logins before lockout | `5` |
| `LOCKOUT_MINUTES` | Lockout duration | `15` |
| `SESSION_TIMEOUT_MINUTES` | Inactivity logout | `30` |
| `FORCE_HTTPS` | Force HTTPS in production | `false` |
| `ASSET_VER` | Cache-buster for CSS/JS (bump after CSS edits) | `10` |

---

## D. Going live — hardening checklist

1. Set `display_errors` to `0` in `config/config.php`.
2. Delete `setup_admin.php`.
3. Set `FORCE_HTTPS` to `true` and serve over HTTPS.
4. Give MySQL a strong password (and update `DB_PASS`).
5. Restrict direct web access to `includes/` and `config/` (the bundled
   `includes/.htaccess` denies it; add one for `config/` if your host allows).
6. Keep regular database backups **including triggers and views**.

---

## E. Troubleshooting

| Symptom | Fix |
|---|---|
| Blank page / 500 error | Check PHP is 8.x and `display_errors` is on while debugging; look at Apache's `error.log` |
| "Access denied" connecting to DB | `DB_USER` / `DB_PASS` in `config.php` don't match MySQL |
| Login redirects loop / flash messages lost | A `.php` file was saved with a UTF-8 **BOM**; re-save as "UTF-8 without BOM" |
| Stock never changes | The **triggers** were not imported — re-import a full dump |
| 403 for an admin page | You are signed in as Staff; that page needs an admin account |
| CSS/JS changes not showing | Bump `ASSET_VER` in `config/config.php` to bust the browser cache |
| Download (PDF/Excel) is corrupt | Something echoed output before the file was built; check for whitespace/BOM outside `<?php` |
