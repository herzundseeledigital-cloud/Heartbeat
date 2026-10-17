# Heartbeat CMS - Local Development Setup

## 1. Prerequisites
- **Operating System:** macOS, Linux, or Windows (WSL recommended)
- **PHP:** PHP 8.2+ or 8.3+ (CLI with PDO SQLite or MySQL)
- **Node.js:** Node.js v18+ / v20+ / v22+ & npm (for local tooling only)

---

## 2. Quickstart (One Command)

1. Clone or open the repository in VS Code:
   ```bash
   cd Heartbeat
   ```

2. Start the local development server:
   ```bash
   npm run dev
   ```
   *Alternative:*
   ```bash
   php -S localhost:8000 -t public router.php
   ```

3. Open your browser and navigate to the Web Installer:
   ```
   http://localhost:8000/install
   ```

---

## 3. Recommended Local Development Workflow

1. In **Step 1 (System Check)**: Verify all checkmarks are green.
2. In **Step 2 (Database & Environment)**: Click **"1-Klick SQLite aktivieren"** for instant zero-config testing.
3. In **Step 3 (Website & Admin)**: Choose your starting theme (e.g. `One-Pager`) and set your Super-Admin credentials.
4. In **Step 4 (Abschluss)**: Click **"Installation jetzt starten"**. The database will be created at `storage/database.sqlite`, migrations applied, and starter content seeded.
5. Log into the Admin Dashboard at `http://localhost:8000/admin/login`.

---

## 4. Resetting the Local Environment

To wipe the local installation and restart the installer from scratch:
```bash
rm -f config/installed.lock config/env.php storage/database.sqlite*
```
Reloading `http://localhost:8000/` will immediately relaunch the 4-step Web Installer.

---

## 5. Building the Production Release ZIP

When ready to deliver or roll out to a client:
```bash
npm run build:release
```
This generates `dist/heartbeat-cms-install.zip` containing all production runtime files, fully configured for Managed Hosting.

