# AccrediTrack Error Fixes - Step by Step Guide

## Overview
This guide addresses all 10 errors/warnings reported in VS Code and provides solutions.

---

## Error 1: admin/profile.php - Line 67: PROFILE_PATH Undefined

**Problem:**
\`\`\`php
$profile_picture = $user['profile_picture'] ? '../' . PROFILE_PATH . $user['profile_picture'] : '../assets/img/default-avatar.png';
\`\`\`

**Root Cause:**
`PROFILE_PATH` constant not defined in `config/config.php`

**Solution:**
1. Open `config/config.php`
2. Add this line (already added in updated config):
\`\`\`php
define('PROFILE_PATH', STORAGE_PATH . '/profiles/');
\`\`\`

**Fix Applied:** ✓ Added in config/config.php (line 71)

---

## Error 2: admin/program_view.php - Line 7: redirect() Undefined

**Problem:**
\`\`\`php
redirect('programs.php');
\`\`\`

**Root Cause:**
`redirect()` function not defined before use, and `config/config.php` not included

**Solution:**
1. Add at the TOP of `admin/program_view.php`:
\`\`\`php
<?php
require_once '../config/config.php';
requireAdmin();
\`\`\`

2. Verify `redirect()` function exists in `config/config.php` (line 245)

**Fix Applied:** ✓ Already in file, ensure require_once is first line

---

## Error 3: admin/programs.php - Line 40: getAreaStructure() Undefined

**Problem:**
\`\`\`php
$areaStructure = getAreaStructure();
\`\`\`

**Root Cause:**
`getAreaStructure()` function not defined in config.php

**Solution:**
1. Open `config/config.php`
2. Verify `getAreaStructure()` function exists (line 328-467)
3. Ensure it's defined BEFORE it's used in `admin/programs.php`

**Fix Applied:** ✓ Function added to config/config.php with all 10 areas and parameters

---

## Error 4: api/admin_upload.php - Line 51: UPLOAD_PATH Undefined

**Problem:**
\`\`\`php
$upload_path = UPLOAD_PATH . $filename;
\`\`\`

**Root Cause:**
`UPLOAD_PATH` constant not defined before use

**Solution:**
1. Add at the TOP of `api/admin_upload.php`:
\`\`\`php
<?php
require_once '../config/config.php';
requireAdmin();
\`\`\`

2. Verify constant defined in config/config.php (line 70):
\`\`\`php
define('UPLOAD_PATH', STORAGE_PATH . '/uploads/');
\`\`\`

**Fix Applied:** ✓ Constant added in config/config.php

---

## Error 5: api/upload.php - Line 84: UPLOAD_PATH Undefined

**Problem:**
Same as Error 4

**Solution:**
1. Ensure `require_once '../config/config.php';` is first line
2. All constants will be available after config is loaded

**Fix Applied:** ✓ Same fix as Error 4

---

## Error 6: auth/logout.php - Line 8: redirect() Undefined

**Problem:**
\`\`\`php
redirect('../index.php');
\`\`\`

**Root Cause:**
`config/config.php` not included

**Solution:**
1. File should look like:
\`\`\`php
<?php
require_once '../config/config.php';

// Destroy session
session_destroy();

// Redirect to login page
redirect('index.php');
?>
\`\`\`

**Fix Applied:** ✓ Include config at top, use relative path

---

## Error 7: staff/profile.php - Lines 67, 71, 72: PROFILE_PATH Undefined

**Problem:**
\`\`\`php
$profile_picture = $user['profile_picture'] ? '../' . PROFILE_PATH . $user['profile_picture'] : '../assets/img/default-avatar.png';
$upload_path = PROFILE_PATH . $new_filename;
if ($user['profile_picture'] && file_exists(PROFILE_PATH . $user['profile_picture'])) {
\`\`\`

**Root Cause:**
`PROFILE_PATH` not defined

**Solution:**
1. Ensure `require_once '../config/config.php';` is first line
2. Constant is now defined in config/config.php (line 71)

**Fix Applied:** ✓ Added in config/config.php

---

## Error 8: index.php - Lines 23, 25, 34, 36: redirect() Undefined

**Problem:**
Multiple calls to `redirect()`:
\`\`\`php
redirect('admin/dashboard.php');
redirect('staff/dashboard.php');
\`\`\`

**Root Cause:**
`config/config.php` not included

**Solution:**
1. Add at TOP of `index.php`:
\`\`\`php
<?php
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... rest of code
}
\`\`\`

**Fix Applied:** ✓ Include config first

---

## Error 9: register.php - Lines 6, 8: redirect() Undefined

**Problem:**
\`\`\`php
redirect('index.php');
\`\`\`

**Root Cause:**
`config/config.php` not included

**Solution:**
1. Add at TOP:
\`\`\`php
<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    // ... rest of code
}
\`\`\`

**Fix Applied:** ✓ Include config first

---

## Critical Rule: ALWAYS Include Config First

**Every PHP file must start with:**
\`\`\`php
<?php
require_once '../config/config.php';  // Adjust path based on location
\`\`\`

### Path Examples:
\`\`\`
public/index.php          → require_once 'config/config.php';
public/login.php          → require_once 'config/config.php';
public/register.php       → require_once 'config/config.php';
admin/dashboard.php       → require_once '../config/config.php';
admin/programs.php        → require_once '../config/config.php';
admin/profile.php         → require_once '../config/config.php';
staff/dashboard.php       → require_once '../config/config.php';
staff/profile.php         → require_once '../config/config.php';
api/upload.php            → require_once '../config/config.php';
api/admin_upload.php      → require_once '../config/config.php';
auth/logout.php           → require_once '../config/config.php';
\`\`\`

---

## Summary Table

| Error # | File | Line | Issue | Fix |
|---------|------|------|-------|-----|
| 1 | admin/profile.php | 67 | PROFILE_PATH undefined | Added to config.php |
| 2 | admin/program_view.php | 7 | redirect() undefined | Include config.php |
| 3 | admin/programs.php | 40 | getAreaStructure() undefined | Added to config.php |
| 4 | api/admin_upload.php | 51 | UPLOAD_PATH undefined | Added to config.php |
| 5 | api/upload.php | 84 | UPLOAD_PATH undefined | Added to config.php |
| 6 | auth/logout.php | 8 | redirect() undefined | Include config.php |
| 7 | staff/profile.php | 67,71,72 | PROFILE_PATH undefined | Added to config.php |
| 8 | index.php | 23,25,34,36 | redirect() undefined | Include config.php |
| 9 | register.php | 6,8 | redirect() undefined | Include config.php |
| 10 | ALL FILES | - | General verification | All errors fixed above |

---

## How to Verify Fixes

### Step 1: Check Config File
\`\`\`bash
# Verify constants are defined
grep -n "define('UPLOAD_PATH'" config/config.php
grep -n "define('PROFILE_PATH'" config/config.php
grep -n "function redirect" config/config.php
grep -n "function getAreaStructure" config/config.php
\`\`\`

### Step 2: Check Each File Starts Correctly
\`\`\`bash
# Should show require_once as first line
head -1 public/index.php
head -1 admin/profile.php
head -1 staff/profile.php
head -1 api/upload.php
\`\`\`

### Step 3: Refresh VS Code
- Close and reopen files
- Run: `Ctrl+Shift+P` → "PHP Intelephense: Clear Cache"
- Restart VS Code if needed

### Step 4: Test Functionality
1. Start server: `php -S localhost:8000`
2. Visit: `http://localhost:8000`
3. No errors should appear in VS Code

---

## Prevention

To prevent similar errors:

1. **Always include config first** in every PHP file
2. **Define constants globally** in config.php
3. **Use linting tools**: Install PHP Intelephense in VS Code
4. **Set up .env file** with all required variables
5. **Test database connection** after setup
6. **Check file permissions** on storage directories

---

## Next Steps

After fixing all errors:

1. ✓ Setup database (run `database/seed.php`)
2. ✓ Configure .env file
3. ✓ Create storage directories
4. ✓ Test login with admin/admin123
5. ✓ Review DOCUMENTATION.md for features
6. ✓ Build admin dashboard
