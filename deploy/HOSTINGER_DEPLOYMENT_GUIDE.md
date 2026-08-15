# Step-by-Step Hostinger Live Server Deployment Guide

This guide explains how to deploy `jugnussaloon` (`saloon_app`) to Hostinger live server (`https://app.jugnussaloon.com/`).

---

## 📦 Package Summary
The deployment script generates `hostinger_deploy.zip` in your project folder.
This zip contains:
- Pre-compiled Vite assets in `public/build/`
- Full PHP dependencies in `vendor/`
- Root `.htaccess` and root `index.php` entry fallback
- All Laravel source files, migrations, views, and controllers

---

## 🚀 Deployment Instructions

### Step 1: Create MySQL Database on Hostinger
1. Log into **Hostinger hPanel**.
2. Go to **Databases** -> **Management**.
3. Create a new MySQL database:
   - **Database Name**: e.g., `u312978252_jugnusaloon`
   - **Database User**: e.g., `u312978252_jugnusaloon`
   - **Password**: (Save this password securely)

---

### Step 2: Upload `hostinger_deploy.zip` to Hostinger File Manager
1. In **hPanel**, go to **Files** -> **File Manager**.
2. Navigate to your subdomain directory (usually `public_html/app/` or `domains/jugnussaloon.com/public_html/app/`).
3. Click **Upload** -> Select `hostinger_deploy.zip`.
4. Right-click `hostinger_deploy.zip` and click **Extract**.
   - Make sure all files are extracted **directly inside the subdomain folder** (`public_html/app/`).
   - If files extract into an extra folder like `saloon_app/`, open `saloon_app/`, select **ALL** files, click **Move**, and move them up to `public_html/app/`.

---

### Step 3: Configure `.env` File
1. Inside `public_html/app/`, create or edit `.env`.
2. Copy the contents of `.env.production` into `.env`.
3. Update the database credentials:
   ```env
   APP_NAME="Jugnu Saloon"
   APP_ENV=production
   APP_KEY=base64:H+gCmKAnENRVB9hQLGjfUabWffyz8CdLbvouMoNZ8WE=
   APP_DEBUG=false
   APP_URL=https://app.jugnussaloon.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=YOUR_HOSTINGER_DB_NAME
   DB_USERNAME=YOUR_HOSTINGER_DB_USER
   DB_PASSWORD=YOUR_HOSTINGER_DB_PASSWORD
   ```

---

### Step 4: Configure Document Root in Hostinger hPanel
1. In hPanel, go to **Domains** -> **Subdomains** (or **Websites** -> **Subdomains**).
2. Locate `app.jugnussaloon.com`.
3. Set the **Target Folder / Document Root** to:
   `public_html/app/public` (or `domains/jugnussaloon.com/public_html/app/public`)
4. Save changes.

---

### Step 5: Run Database Migrations & Seeders
If you have **SSH Access** on Hostinger:
```bash
cd public_html/app
php artisan migrate:fresh --seed --force
```

If you do NOT have SSH access:
- Use phpMyAdmin in Hostinger hPanel to import your MySQL database dump (`.sql` file) into `u312978252_jugnusaloon`.

---

### Step 6: Fix Permissions & Create Storage Symlink
1. In File Manager:
   - Ensure folders have `755` permissions.
   - Ensure files have `644` permissions.
   - Ensure `storage/` and `bootstrap/cache/` are writable (`775` or `755`).
2. If SSH is available, run:
   ```bash
   php artisan storage:link
   ```
   Or in File Manager, create a symbolic link from `storage/app/public` to `public/storage`.

---

## 🎯 Verification
Open `https://app.jugnussaloon.com/` in your browser. The application login page should load cleanly without 403 or 404 errors!
