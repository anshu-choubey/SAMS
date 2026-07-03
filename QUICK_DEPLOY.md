# Quick Deployment Guide

## 🚀 Deploy to Heroku in 3 Steps

### Step 1: Run Database Migration

Connect to your Heroku database and run the migration:

```bash
# Get database URL
heroku config:get CLEARDB_DATABASE_URL --app your-app-name

# The URL format is: mysql://username:password@hostname/database_name
# Parse it and connect:
mysql -h hostname -u username -p database_name < migrations/add_multi_check_attendance.sql
```

**Or use this one-liner:**

```bash
heroku config:get CLEARDB_DATABASE_URL --app your-app-name | \
  sed 's|mysql://\([^:]*\):\([^@]*\)@\([^/]*\)/\(.*\)|mysql -h \3 -u \1 -p\2 \4|' | \
  bash -c "$(cat) < migrations/add_multi_check_attendance.sql"
```

### Step 2: Deploy Code

Use the deployment script:

```bash
./deploy.sh
```

Or manually:

```bash
git add .
git commit -m "feat: Add multi-check attendance system"
git push heroku main
```

### Step 3: Verify

Test the deployment:

```bash
# Check app status
heroku ps --app your-app-name

# View logs
heroku logs --tail --app your-app-name

# Test API
curl https://your-app-name.herokuapp.com/api/public/settings.php
```

## ✅ Verification Checklist

Run these queries to verify migration:

```sql
-- Check new tables
SHOW TABLES LIKE 'attendance_check%';

-- Should show:
-- attendance_check_points
-- attendance_check_responses

-- Check new columns in teacher_locations
DESCRIBE teacher_locations;

-- Should include:
-- multi_check_enabled
-- total_checks_planned
-- checks_completed

-- Check new columns in attendance
DESCRIBE attendance;

-- Should include:
-- session_id
-- total_checks_required
-- successful_checks
```

## 🔧 Quick Commands

```bash
# View Heroku logs
heroku logs --tail --app your-app-name

# Restart app
heroku restart --app your-app-name

# Open app in browser
heroku open --app your-app-name

# Check database
heroku mysql:cli --app your-app-name

# Rollback if needed
mysql -h hostname -u username -p database_name < migrations/rollback_multi_check_attendance.sql
```

## 📱 Test Endpoints

**Teacher - Trigger Check:**
```bash
curl -X POST "https://your-app.herokuapp.com/api/teacher/trigger-attendance-check.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"session_id": 1, "window_minutes": 5}'
```

**Student - Get Active Checks:**
```bash
curl "https://your-app.herokuapp.com/api/student/active-attendance-checks.php" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## ⚠️ Important Notes

- **Always backup** database before migration
- Migration is **backward compatible**
- Old single-check attendance **still works**
- Test on staging environment first if available

## 🆘 Troubleshooting

**Migration fails?**
- Check database credentials
- Verify tables don't already exist
- Review error messages in migration output

**Deployment fails?**
- Check git remote: `git remote -v`
- Verify Heroku app name
- Check logs: `heroku logs --tail`

**App not working?**
- Restart: `heroku restart --app your-app-name`
- Check config: `heroku config --app your-app-name`
- Verify database connection

---

**Need help?** Check `DEPLOYMENT_GUIDE.md` for detailed instructions.
