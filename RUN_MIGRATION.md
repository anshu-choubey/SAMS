# 🗄️ Database Migration Instructions

## ✅ Code Deployed Successfully!

Your code is now live on Heroku: https://sams-backend-73451-bca7cff1a531.herokuapp.com/

## Next Step: Run Database Migration

### Option 1: Direct MySQL Connection (Recommended)

1. **Get database credentials:**

```bash
heroku config:get CLEARDB_DATABASE_URL --app sams-backend-73451
```

This will return a URL like:
```
mysql://username:password@hostname/database_name
```

2. **Parse the credentials:**
   - Username: `username`
   - Password: `password`
   - Hostname: `hostname`
   - Database: `database_name`

3. **Connect and run migration:**

```bash
mysql -h hostname -u username -p database_name < migrations/add_multi_check_attendance.sql
```

When prompted, enter the password from step 1.

### Option 2: One-Line Command

```bash
# Get URL, parse it, and run migration in one command
URL=$(heroku config:get CLEARDB_DATABASE_URL --app sams-backend-73451)
HOST=$(echo $URL | sed 's/mysql:\/\/.*@\(.*\)\/.*/\1/')
USER=$(echo $URL | sed 's/mysql:\/\/\(.*\):.*@.*/\1/')
PASS=$(echo $URL | sed 's/mysql:\/\/.*:\(.*\)@.*/\1/')
DB=$(echo $URL | sed 's/.*\///')

mysql -h $HOST -u $USER -p$PASS $DB < migrations/add_multi_check_attendance.sql
```

### Option 3: Using Heroku MySQL Plugin (if installed)

```bash
heroku mysql:cli --app sams-backend-73451
# Then run:
source migrations/add_multi_check_attendance.sql;
```

### Option 4: Using MySQL Workbench or GUI Tool

1. Get credentials from: `heroku config:get CLEARDB_DATABASE_URL --app sams-backend-73451`
2. Open MySQL Workbench
3. Create new connection with parsed credentials
4. Open and execute: `migrations/add_multi_check_attendance.sql`

## Verify Migration

After running the migration, verify it was successful:

```bash
# Connect to database
mysql -h hostname -u username -p database_name

# Run verification queries
SHOW TABLES LIKE 'attendance_check%';
DESCRIBE teacher_locations;
DESCRIBE attendance;
```

Expected output:
- `attendance_check_points` table exists
- `attendance_check_responses` table exists
- `teacher_locations` has: `multi_check_enabled`, `total_checks_planned`, `checks_completed`
- `attendance` has: `session_id`, `total_checks_required`, `successful_checks`

## Test Deployment

After migration, test the API endpoints:

```bash
# Test public endpoint
curl https://sams-backend-73451-bca7cff1a531.herokuapp.com/api/public/settings.php

# Test with auth (replace YOUR_TOKEN)
curl https://sams-backend-73451-bca7cff1a531.herokuapp.com/api/student/active-attendance-checks.php \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Monitor Logs

```bash
# View real-time logs
heroku logs --tail --app sams-backend-73451

# Check app status
heroku ps --app sams-backend-73451

# Restart if needed
heroku restart --app sams-backend-73451
```

## Rollback (if needed)

If something goes wrong:

```bash
mysql -h hostname -u username -p database_name < migrations/rollback_multi_check_attendance.sql
```

## 📞 Need Help?

Check these files:
- `QUICK_DEPLOY.md` - Quick reference
- `DEPLOYMENT_GUIDE.md` - Detailed instructions
- `MULTI_CHECK_ATTENDANCE_GUIDE.md` - API documentation

---

**App URL**: https://sams-backend-73451-bca7cff1a531.herokuapp.com/
**Heroku App**: sams-backend-73451
**Version**: v121
**Status**: ✅ Code Deployed (Migration Pending)
