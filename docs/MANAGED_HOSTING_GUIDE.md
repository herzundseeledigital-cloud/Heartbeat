# Heartbeat CMS - Managed Hosting Deployment Guide

## 1. Supported Hosting Environments
Heartbeat CMS runs out-of-the-box on standard Managed Hosting servers without root access and without SSH requirements:
- **Webgo** (Apache + PHP-FPM 8.2 / 8.3)
- **IONOS / 1&1** (Managed Webhosting, PHP 8.2 / 8.3)
- **Mittwald** (Space Server & Agentur Hosting)
- **HostEurope** (cPanel / Plesk Managed)
- **Hetzner** (KonsoleH Webhosting)

---

## 2. Deployment in 3 Easy Steps

### Step 1: Upload the Release ZIP
1. Run `npm run build:release` locally to create `dist/heartbeat-cms-install.zip`.
2. Connect to the client's server via FTP (FileZilla / Cyberduck) or the hoster's Web-FTP file manager.
3. Upload `heartbeat-cms-install.zip` directly into the domain's root directory (`/html`, `/htdocs`, or `/httpdocs`).
4. Extract the ZIP archive directly on the server.

### Step 2: DocumentRoot Configuration
- **Optimal Setup (Recommended):** Set the domain's Document Root in the hosting control panel to `/public`.
- **Alternative (Shared Root):** If the hosting provider does not allow changing the Document Root, Heartbeat's included root `.htaccess` automatically rewrites traffic to `/public/index.php` while strictly blocking direct HTTP access to `/core`, `/config`, `/storage`, and `/plugins`.

### Step 3: Run the Web Installer
1. Navigate to the client domain in your browser:
   ```
   https://client-domain.de/install
   ```
2. Enter the MySQL database credentials provided by the hoster (Database name, user, password, host).
3. Test the connection, set the agency white-label details and admin credentials, and complete the installation.
4. Heartbeat CMS automatically creates `config/installed.lock` to secure the system.

---

## 3. Recommended Server Settings (`php.ini` / Web Host Config)

| Setting | Recommended Value | Note |
| :--- | :--- | :--- |
| `memory_limit` | `256M` (or `512M`) | For native image conversions (WebP/AVIF) |
| `upload_max_filesize` | `64M` | For media library asset uploads |
| `post_max_size` | `64M` | Match upload limit |
| `max_execution_time`| `60` | For bulk migrations or backups |
| `opcache.enable` | `1` | High-performance opcode caching |

---

## 4. Cronjob Configuration (Optional for Automated Backups)
Add a standard server cronjob in the hosting control panel to trigger automated maintenance:
```bash
# Run daily at 03:00 AM
0 3 * * * /usr/bin/php /var/www/vhosts/client-domain.de/core/cron.php >/dev/null 2>&1
```

