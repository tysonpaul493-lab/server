# LifeRoute Unified Server Deployment Guide

This directory (`server/`) is the **central master backend and WebSocket dispatch engine** for the entire LifeRoute Emergency Response ecosystem. It simultaneously powers:
1. **Emergency User Mobile & Web App** (`/api/...`)
2. **Ambulance Driver Mobile & Web App** (`/api/v1/driver/...`)
3. **Admin Command Center Website** (`/api/admin/...`, `/api/bookings`, etc.)
4. **Live GPS & Real-Time Telemetry Engine** (`ws://...:8080` / `wss://...`)

---

## 📁 What is Inside this Server Folder?

* **`app/`**: All backend business logic, emergency dispatch controllers, SIM carrier fraud verifier, and database models.
* **`routes/api.php`**: All API endpoints for User, Driver, and Admin.
* **`database/migrations/`**: Automated schema migrations creating all database tables.
* **`database/liferoute_database.sql`**: **Full database dump** with ready-to-use seed data (drivers, ambulances, hospitals, admin accounts, and users).
* **`start-server.bat`**: 1-Click launcher for Windows local/LAN deployment.
* **`start-server.sh`**: 1-Click launcher for Linux / Ubuntu VPS deployment.
* **`docker-compose.yml`**: 1-Command containerized deployment (MySQL + API + WebSocket).
* **`nginx.conf`**: Production reverse proxy with CORS headers and WebSocket upgrade rules.

---

## 🚀 Deployment Options

### Option 1: Standard Linux VPS (Ubuntu 22.04 / 24.04, AWS, DigitalOcean)

1. **Install PHP 8.2 and MySQL**:
   ```bash
   sudo apt update
   sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-bcmath php8.2-xml php8.2-curl unzip mariadb-server nginx composer
   ```

2. **Upload this `server` folder** to `/var/www/liferoute/server`.

3. **Set permissions**:
   ```bash
   cd /var/www/liferoute/server
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

4. **Configure MySQL Database**:
   ```bash
   sudo mysql -u root -e "CREATE DATABASE liferoute CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   sudo mysql -u root liferoute < database/liferoute_database.sql
   ```

5. **Configure `.env`**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Edit `.env` to set your MySQL credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=liferoute
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

6. **Start Services**:
   ```bash
   chmod +x start-server.sh
   ./start-server.sh
   ```

---

### Option 2: Docker / Container Deployment (1 Command)

If your server has Docker & Docker Compose installed:
```bash
docker compose up -d
```
This automatically boots:
* MariaDB on port `3306` (with `liferoute_database.sql` auto-imported).
* Laravel REST API on port `8000`.
* Laravel Reverb WebSocket daemon on port `8080`.

---

### Option 3: cPanel / Shared Hosting with MySQL

1. **Create Database in cPanel**:
   * Open **cPanel > MySQL Databases** and create `liferoute`.
   * Open **phpMyAdmin**, click `liferoute`, select **Import**, and upload `database/liferoute_database.sql`.
2. **Upload Server Files**:
   * Compress this `server` folder into a `.zip` and upload it via cPanel File Manager to your root or `public_html`.
   * Set Document Root to `server/public`.
3. **Configure `.env`**:
   * Update `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` with your cPanel database details.

---

## 🔗 Connecting the 2 Apps and Website to this Server

Once your server is running (e.g., at `https://api.yourdomain.com` or through a tunnel):

1. **Emergency User App**:
   * Set API URL: `https://api.yourdomain.com/api`
   * Set WebSocket Host: `api.yourdomain.com` (port `443` or `8080`)
2. **Ambulance Driver App**:
   * Set API URL: `https://api.yourdomain.com/api/v1`
   * Set WebSocket Host: `api.yourdomain.com` (port `443` or `8080`)
3. **Admin Website**:
   * In `client.ts`: set `DEFAULT_API_URL = 'https://api.yourdomain.com/api/v1'`
   * In `liveSocket.ts`: set `wsUrl = 'wss://api.yourdomain.com'`
