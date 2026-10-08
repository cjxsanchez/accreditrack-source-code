# AccrediTrack Setup Guide

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (recommended)
- Supabase account with a project and storage bucket

## Installation Steps

### Step 1: Clone or Download the Project

\`\`\`bash
git clone <repository-url>
cd accreditrack
\`\`\`

### Step 2: Copy Environment Configuration

\`\`\`bash
cp .env.example .env
\`\`\`

### Step 3: Configure .env File

Edit `.env` with your actual values:

\`\`\`env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=accreditrack
DB_USER=root
DB_PASSWORD=your_password

# Supabase
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_KEY=your-service-key
SUPABASE_BUCKET=accredidocs
\`\`\`

### Step 4: Create Database

\`\`\`bash
# Using MySQL command line
mysql -u root -p < database/schema.sql

# Or manually:
# 1. Open phpMyAdmin or MySQL client
# 2. Create database: CREATE DATABASE accreditrack;
# 3. Import schema: database/schema.sql
\`\`\`

### Step 5: Run Database Seeder

\`\`\`bash
php database/seed.php
\`\`\`

This creates:
- Default admin user (username: `admin`, password: `admin123`)

### Step 6: Set Proper File Permissions

\`\`\`bash
# Linux/Mac
chmod 755 storage
chmod 755 storage/logs
chmod 755 storage/uploads
chmod 755 storage/profiles
chmod 755 storage/references

# Windows: Ensure IIS/Apache has write permissions to storage directory
\`\`\`

### Step 7: Start Local Server

\`\`\`bash
# Using PHP built-in server
php -S localhost:8000

# Then visit: http://localhost:8000
\`\`\`

## Troubleshooting

### Issue 1: Cannot connect to database

**Solution:**
- Check DB credentials in `.env`
- Ensure MySQL server is running
- Verify database exists: `SHOW DATABASES;`

### Issue 2: "UPLOAD_PATH undefined constant" Error

**Solution:**
- Ensure `config/config.php` is loaded before any file operations
- Check that `require_once 'config/config.php';` is at the top of each PHP file
- Verify storage directories exist with proper permissions

### Issue 3: "redirect() - call to unknown function" Error

**Solution:**
- This happens when `config/config.php` is not included
- Make sure `redirect()` function is defined in config.php
- Add `require_once __DIR__ . '/config/config.php';` at the top of each file

### Issue 4: Supabase Connection Issues

**Solution:**
- Verify SUPABASE_URL and SUPABASE_ANON_KEY are correct
- Check that Supabase bucket exists
- Ensure CORS settings allow your domain
- Review Supabase logs for details

### Issue 5: Session-related Errors

**Solution:**
- Ensure `session_start()` is called before any headers are sent
- Check PHP session settings in `php.ini`
- Verify session storage path has write permissions

## Verification Checklist

- [ ] Database connected successfully
- [ ] All tables created
- [ ] Default admin user created
- [ ] File storage directories exist
- [ ] Supabase credentials configured
- [ ] Can login with admin/admin123
- [ ] Can upload files
- [ ] Can view files
