# Migration Guide: SMF Quiz 2.0.2 → 2.1.0-Modernized

## Overview

This guide helps you upgrade from the original SMF Quiz 2.0.2 to the modernized 2.1.0 version.

## Before You Upgrade

**CRITICAL: Back up your database and files!**

```bash
# Backup database
mysqldump -u username -p forum_database > quiz_backup.sql

# Backup files
cp -r Sources/Quiz Sources/Quiz.backup
cp -r Themes/Quiz Themes/Quiz.backup
```

## Installation Steps

### Step 1: Disable the Old Mod (Optional but Recommended)
1. Log in to SMF Admin Panel
2. Go to **Administration → Packages and Plugins → Installed Packages**
3. Find "SMF Quiz" and click **Uninstall**
   - **Do NOT delete files** - just uninstall hooks
4. Note down your quiz settings (screenshotif needed)

### Step 2: Replace Files

```bash
# Remove old files
rm -rf Sources/Quiz
rm -rf Themes/Quiz

# Copy new files from the modernized version
cp -r modernize-branch/Sources/Quiz Sources/
cp -r modernize-branch/Themes/Quiz Themes/
```

### Step 3: Run Database Migration

1. SMF will automatically run the migration on first load
2. Navigate to your forum
3. If you see any errors, check SMF error log: **Admin → Maintenance → Error Log**

**Manual migration (if needed):**
```php
// In admin panel, go to: Admin → Packages and Plugins → Installed Packages
// Install the modernized SMF Quiz package
// database.php will run automatically
```

### Step 4: Verify Installation

1. Go to **Admin → Quiz** (new icon should appear)
2. Check that all your quizzes are still there
3. Create a test quiz
4. Play the test quiz as a user
5. Verify results are recorded

## What Changed (For You)

### Database Tables
No changes to table structure—your existing data is 100% compatible.

### Admin Panel
Identical to before. No learning curve needed.

### User Experience
Identical to before. Users won't notice any difference.

### Settings
All existing settings are preserved. You shouldn't need to reconfigure anything.

## What Changed (For Developers)

See `MODERNIZATION.md` for technical details.

## Troubleshooting

### "404 Quiz not found" errors
**Cause:** Migration didn't run properly
**Fix:**
1. Check error log: Admin → Maintenance → Error Log
2. If errors exist, note them
3. Contact support with error details

### Quizzes disappeared after upgrade
**Cause:** Unlikely, but possible data issue
**Fix:**
1. Restore from backup: `mysql forum_database < quiz_backup.sql`
2. Try again with Step 3
3. If still broken, check SMF forum for support

### "Class not found" errors
**Cause:** Autoloader not registered
**Fix:**
1. Clear SMF cache: Admin → Maintenance → Empty the cache
2. Ensure `Sources/Quiz/Integration.php` exists
3. Restart browser and try again

### Permission issues
**Cause:** File permissions incorrect
**Fix:**
```bash
chmod -R 755 Sources/Quiz
chmod -R 755 Themes/Quiz
```

## Rollback (If Something Goes Wrong)

If you need to revert to 2.0.2:

```bash
# Restore old files
rm -rf Sources/Quiz
rm -rf Themes/Quiz
cp -r Sources/Quiz.backup Sources/Quiz
cp -r Themes/Quiz.backup Themes/Quiz

# Restore database (if you modified schema)
mysql forum_database < quiz_backup.sql
```

Then reinstall the old package through SMF admin.

## Getting Help

1. **Check the error log:** Admin → Maintenance → Error Log
2. **Check MODERNIZATION.md** for technical details
3. **Check ARCHITECTURE.md** for design information
4. **SMF Forum:** Ask in SMF support forum with full error messages

## Support

This modernization maintains full SMF 2.1.x compatibility. All your data is safe.
