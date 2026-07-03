# Multi-Check Attendance Deployment Guide

## Prerequisites

- Heroku CLI installed
- Git configured
- Database backup completed
- Heroku app name: (your-heroku-app-name)

## Deployment Steps

### 1. Database Backup (IMPORTANT!)

Before any migration, **always backup your database**:

```bash
# For local database
mysqldump -u root -p sams_db > backup_$(date +%Y%m%d_%H%M%S).sql

# For Heroku ClearDB
heroku mysql:dump --app your-heroku-app-name > heroku_backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Run Database Migration

#### Option A: Using Heroku MySQL CLI

```bash
# Get database credentials
heroku config:get CLEARDB_DATABASE_URL --app your-heroku-app-name

# Connect to database
# Parse the URL format: mysql://username:password@host/database_name
# Then connect:
mysql -h hostname -u username -p database_name

# Run migration
source /path/to/migrations/add_multi_check_attendance.sql;
```

#### Option B: Using Remote MySQL Client

```bash
# If you use MySQL Workbench or similar:
# 1. Get connection details from CLEARDB_DATABASE_URL
# 2. Connect to the database
# 3. Open and execute: migrations/add_multi_check_attendance.sql
```

#### Option C: Using PHP Script (Automated)

Create `run_migration.php`:

```php
<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read migration file
    $sql = file_get_contents(__DIR__ . '/migrations/add_multi_check_attendance.sql');
    
    // Execute migration
    $db->exec($sql);
    
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
```

Then run:
```bash
php run_migration.php
```

### 3. Verify Migration

Check if migration was successful:

```sql
-- Check new tables
SHOW TABLES LIKE 'attendance_check%';

-- Check new columns
DESCRIBE teacher_locations;
DESCRIBE attendance;

-- Verify data structure
SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME IN ('attendance_check_points', 'attendance_check_responses')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

### 4. Deploy Code to Heroku

```bash
# Navigate to project directory
cd /Users/anshu/sams-backend

# Check git status
git status

# Stage all changes
git add .

# Commit changes
git commit -m "feat: Add multi-check attendance system

- Added attendance_check_points and attendance_check_responses tables
- Updated teacher_locations with multi-check fields
- Enhanced attendance table with session tracking
- Added teacher APIs: trigger-attendance-check, finalize-attendance
- Added student APIs: active-attendance-checks, respond-attendance-check
- Updated Android app with full multi-check support
- Backward compatible with single-check system"

# Push to Heroku
git push heroku main

# Or if your main branch is named 'master':
# git push heroku master

# Or if pushing from a different branch:
# git push heroku your-branch:main
```

### 5. Verify Deployment

```bash
# Check deployment logs
heroku logs --tail --app your-heroku-app-name

# Check if app is running
heroku ps --app your-heroku-app-name

# Open app in browser
heroku open --app your-heroku-app-name

# Test API endpoints
curl https://your-heroku-app-name.herokuapp.com/api/public/settings.php
```

### 6. Test API Endpoints

Test the new endpoints to ensure they're working:

```bash
# Replace with your actual Heroku URL and auth token
HEROKU_URL="https://your-heroku-app-name.herokuapp.com"
TOKEN="your-auth-token"

# Test trigger attendance check (Teacher)
curl -X POST "${HEROKU_URL}/api/teacher/trigger-attendance-check.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "session_id": 1,
    "window_minutes": 5
  }'

# Test get active checks (Student)
curl -X GET "${HEROKU_URL}/api/student/active-attendance-checks.php" \
  -H "Authorization: Bearer ${TOKEN}"

# Test respond to check (Student)
curl -X POST "${HEROKU_URL}/api/student/respond-attendance-check.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d '{
    "check_point_id": 1,
    "latitude": 28.6139,
    "longitude": 77.2090,
    "face_confidence": 92.5
  }'
```

## Rollback Procedure

If something goes wrong, rollback using:

```bash
# Connect to database
mysql -h hostname -u username -p database_name

# Run rollback script
source /path/to/migrations/rollback_multi_check_attendance.sql;

# Restore from backup if needed
mysql -h hostname -u username -p database_name < backup_file.sql
```

## Post-Deployment Checklist

- [ ] Database migration completed successfully
- [ ] All new tables created (attendance_check_points, attendance_check_responses)
- [ ] New columns added to existing tables
- [ ] Code deployed to Heroku
- [ ] No deployment errors in logs
- [ ] API endpoints responding correctly
- [ ] Teacher can trigger attendance checks
- [ ] Students can view and respond to checks
- [ ] Old single-check attendance still works
- [ ] Android app can connect to new endpoints
- [ ] FCM notifications configured (if applicable)

## Monitoring

Monitor the application after deployment:

```bash
# Real-time logs
heroku logs --tail --app your-heroku-app-name

# Database metrics (if using ClearDB)
heroku addons:open cleardb --app your-heroku-app-name

# App metrics
heroku ps --app your-heroku-app-name
heroku releases --app your-heroku-app-name
```

## Common Issues

### Issue: Migration fails with "Table already exists"

**Solution**: The migration script uses `CREATE TABLE IF NOT EXISTS` and `ADD COLUMN IF NOT EXISTS`, so it's safe to re-run.

### Issue: Foreign key constraint fails

**Solution**: Check if referenced tables exist and have the correct structure. Run schema.sql first if needed.

### Issue: Git push rejected

**Solution**:
```bash
# Force push (use with caution)
git push heroku main --force

# Or pull and merge first
git pull heroku main
git push heroku main
```

### Issue: Heroku app crashes after deployment

**Solution**:
```bash
# Check logs
heroku logs --tail --app your-heroku-app-name

# Restart dynos
heroku restart --app your-heroku-app-name

# Check dyno status
heroku ps --app your-heroku-app-name
```

## Environment Variables

Ensure these are set in Heroku:

```bash
# Check current config
heroku config --app your-heroku-app-name

# Required variables
heroku config:set DB_HOST=your-db-host --app your-heroku-app-name
heroku config:set DB_NAME=your-db-name --app your-heroku-app-name
heroku config:set DB_USER=your-db-user --app your-heroku-app-name
heroku config:set DB_PASSWORD=your-db-password --app your-heroku-app-name
```

## Support

If you encounter issues:
1. Check Heroku logs: `heroku logs --tail`
2. Verify database connection
3. Test API endpoints locally first
4. Check migration verification queries
5. Review error messages in PHP error logs

---

**Deployment Date**: July 3, 2026
**Version**: Multi-Check Attendance v1.0
**Status**: Ready for Production
