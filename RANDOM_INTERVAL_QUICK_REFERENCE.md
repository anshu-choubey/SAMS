# Random Interval Attendance - Quick Reference

## Current Settings (v123)

| Setting | Value | Description |
|---------|-------|-------------|
| **Total Checks** | 3 | Number of attendance checks per class |
| **First Check Delay** | 20 min | Time before first check |
| **Response Window** | 5 min | How long students have to respond |
| **Min Interval** | 15 min | Minimum time between checks |
| **Max Interval** | 30 min | Maximum time between checks |

---

## Quick Commands

### Get Current Settings
```bash
curl -X GET "https://sams-backend-73451.herokuapp.com/api/admin/attendance-settings.php" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### Update Settings
```bash
curl -X POST "https://sams-backend-73451.herokuapp.com/api/admin/attendance-settings.php" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "attendance_default_total_checks": "4",
    "attendance_first_check_delay": "15"
  }'
```

### Test Cron Job
```bash
curl "https://sams-backend-73451.herokuapp.com/api/cron/trigger-scheduled-checks.php"
```

---

## Example Schedules

### 30-minute class, 2 checks
- Check 1: 10 min (09:10)
- Check 2: 25 min (09:25)

### 60-minute class, 3 checks
- Check 1: 20 min (09:20)
- Check 2: 40 min (09:40)
- Check 3: 58 min (09:58)

### 90-minute class, 3 checks (default)
- Check 1: 20 min (09:20)
- Check 2: 55 min (09:55)
- Check 3: 88 min (10:28)

### 120-minute class, 4 checks
- Check 1: 20 min (09:20)
- Check 2: 53 min (09:53)
- Check 3: 86 min (10:26)
- Check 4: 117 min (10:57)

---

## Configuration Presets

### Strict (High Security)
```json
{
  "attendance_default_total_checks": "4",
  "attendance_first_check_delay": "10",
  "attendance_check_window_minutes": "3",
  "liveness_min_score": "75"
}
```

### Balanced (Recommended)
```json
{
  "attendance_default_total_checks": "3",
  "attendance_first_check_delay": "20",
  "attendance_check_window_minutes": "5",
  "liveness_min_score": "60"
}
```

### Relaxed (Student-Friendly)
```json
{
  "attendance_default_total_checks": "2",
  "attendance_first_check_delay": "30",
  "attendance_check_window_minutes": "10",
  "liveness_min_score": "50"
}
```

---

## API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/admin/attendance-settings.php` | GET/POST | Admin | Manage settings |
| `/api/student/continuous-monitoring-config.php` | GET | Student | Get session config |
| `/api/teacher/start-class.php` | POST | Teacher | Start with auto-schedule |
| `/api/cron/trigger-scheduled-checks.php` | GET | None | Activate scheduled checks |

---

## Troubleshooting

**Checks not triggering?**
- Verify cron job is configured
- Check scheduled times in `attendance_check_points` table
- Ensure session is still active

**Students not seeing checks?**
- Verify `is_active = TRUE` in database
- Check student polling (every 10 seconds)
- Confirm check window hasn't expired

**Want to change defaults?**
- Use admin settings API
- Changes apply to new classes immediately
- Existing sessions use their saved values

---

## Full Documentation

See `/ADMIN_SETTINGS_DOCUMENTATION.md` for complete reference.

---

**Deployed**: Version v123 on Heroku  
**Status**: ✅ All systems operational
