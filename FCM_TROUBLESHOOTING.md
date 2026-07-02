# FCM Notification Troubleshooting - Step-by-Step

## Quick Health Check

First, run the diagnostic to see what's wrong:

```bash
curl -X GET https://www.arkdev.app/api/fcm/diagnose.php \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

This will show you:
- ✅ If FCM Server Key is configured
- ✅ How many devices are registered
- ✅ Any recent notifications sent
- ✅ Specific issues and how to fix them

## Issue 1: FCM Server Key Not Set

**Error**: `FCM Server Key is NOT SET`

### Solution:
1. For Firebase Cloud Messaging API v1, use a service account instead of a legacy server key:
   - Go to https://console.firebase.google.com
   - Select project: `careful-form-373115`
   - Settings → Project Settings → Service Accounts tab
   - Generate a new private key and download the JSON

2. Set it in the backend secret store or environment variables:
```bash
heroku config:set FIREBASE_SERVICE_ACCOUNT_JSON='{"type":"service_account",...}' --app sams-backend-73451
```

3. If the backend still has a legacy server-key path, it must be migrated before v1 can work.

Legacy example only:
```bash
curl -X PUT https://www.arkdev.app/api/fcm/configure.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{
    "fcm_server_key": "LEGACY_SERVER_KEY_ONLY"
  }'
```

4. Verify it was set:
```bash
curl -X GET https://www.arkdev.app/api/fcm/diagnose.php \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

---

## Issue 2: No FCM Tokens Registered

**Error**: `No FCM tokens registered - Users haven't logged in or app isn't registering tokens`

### This means:
- Users haven't logged in yet, OR
- The app isn't successfully registering tokens after login

### Debugging Steps:

**Step 1: Check if any tokens exist**
```sql
SELECT COUNT(*) FROM fcm_tokens;
SELECT * FROM fcm_tokens LIMIT 5;
```

**Step 2: Have users log in with the app**
- Open the SAMS app on Android device
- Log in with valid credentials
- Watch the app logs for FCM registration

**Step 3: Check app logs**
Open Android Studio or use adb:
```bash
adb logcat | grep "SAMSFirebaseMessaging"
```

You should see:
```
New FCM Token received: AAAA...
FCM Token registered successfully with backend
```

**Step 4: If token isn't registered, check:**
- Is the app authenticated? (Login successful?)
- Can the app reach the backend API?
- Check app's `/api/fcm/register.php` response in Android Studio network debugger
- Ensure `android.permission.POST_NOTIFICATIONS` is granted

---

## Issue 3: Unsent Notifications

**Error**: `notifications are marked as UNSENT`

This means notifications were created but never sent to FCM.

### Causes:
1. FCM Server Key is invalid/expired
2. No active FCM tokens at the time of send
3. cURL/networking issues

### Solution:
1. Verify FCM key is still valid (test it)
2. Ensure devices have tokens registered when sending
3. Try sending a test notification:

```bash
curl -X POST https://www.arkdev.app/api/fcm/test-send.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{}'
```

---

## Issue 4: Notifications Not Appearing on Device

**Setup appears correct, but no notifications on phone**

### Check 1: Notification Permissions
On Android device:
1. Go to Settings → Apps → SAMS
2. Permissions → Notifications
3. **Enable** notifications for SAMS

### Check 2: Notification Channel Disabled
1. Settings → Apps → SAMS → Notifications
2. Look for "SAMS Notifications" channel
3. Ensure it's enabled and not muted

### Check 3: App is Receiving Messages
Check if Firebase is receiving the messages:
```bash
adb logcat | grep -i "firebase\|messaging"
```

Should see:
```
Message received from: 73550218074@fcm.googleapis.com
Showing notification - Title: ...
```

### Check 4: Verify Notification is in Database
```sql
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5;
```

Should see entries with:
- `is_sent = 1` (true)
- `sent_at` = timestamp

---

## Complete Testing Flow

### 1. Verify Backend Setup
```bash
# Check if FCM is configured
curl -X GET https://www.arkdev.app/api/fcm/diagnose.php \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

### 2. Ensure User is Logged In
- Log in to SAMS app with valid credentials
- Wait 5 seconds for token registration

### 3. Check Tokens Are Registered
```bash
# Via API
curl -X GET https://www.arkdev.app/api/fcm/check-setup.php \
  -H "Authorization: Bearer ADMIN_TOKEN"

# Via Database
mysql> SELECT COUNT(*) FROM fcm_tokens WHERE is_active = TRUE;
```

### 4. Send Test Notification
```bash
curl -X POST https://www.arkdev.app/api/fcm/test-send.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

### 5. Check Phone
- Pull down notification shade
- Should see "FCM Test Notification"

### 6. Send Real Notification
```bash
curl -X POST https://www.arkdev.app/api/notifications/send.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -d '{
    "title": "My Notification",
    "message": "Test message",
    "type": "system",
    "target_role": "student"
  }'
```

---

## API Reference

| Endpoint | Method | Purpose | Auth |
|----------|--------|---------|------|
| `/api/fcm/check-setup.php` | GET | Check if FCM configured | Admin |
| `/api/fcm/configure.php` | PUT | Set FCM Server Key | Admin |
| `/api/fcm/diagnose.php` | GET | Full diagnostic report | Admin |
| `/api/fcm/test-send.php` | POST | Send test notification | Admin |
| `/api/fcm/register.php` | POST | Register device token | Authenticated User |
| `/api/notifications/send.php` | POST | Send notification | Admin |
| `/api/notifications/list.php` | GET | Get user's notifications | Authenticated User |

---

## Common Issues & Quick Fixes

| Issue | Quick Fix |
|-------|-----------|
| "Invalid FCM Server Key" | For legacy mode only; for v1 use a service account JSON |
| "No tokens registered" | Have user log in app, check logs for token registration errors |
| "Notifications not appearing" | Check notification permissions in Android settings |
| "HTTP 401 from FCM" | FCM key is invalid/expired, get new one |
| "HTTP 400 from FCM" | Bad payload, check token format |
| "Connection timeout" | Network issue, check Firebase API is accessible |

---

## Still Not Working?

1. **Check Server Logs:**
```bash
tail -f /var/log/apache2/error.log
tail -f /var/log/syslog
```

2. **Check Database for Errors:**
```sql
SELECT * FROM notifications WHERE is_sent = 0 ORDER BY created_at DESC;
SELECT * FROM fcm_tokens LIMIT 10;
SELECT setting_value FROM system_settings WHERE setting_key = 'fcm_server_key';
```

3. **Test Firebase Credentials:**
Install Firebase CLI and test:
```bash
firebase database:get / --project=careful-form-373115
```

4. **Enable Curl Debugging:**
Add to FCM API calls:
```php
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://stderr', 'w');
curl_setopt($ch, CURLOPT_STDERR, $verbose);
```

5. **Contact Firebase Support:**
If key is valid but still fails, Firebase might have issues with your project billing/quotas.
