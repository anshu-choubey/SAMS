# FCM Notification Setup Guide

## Overview
Firebase Cloud Messaging (FCM) is used to send push notifications to students and teachers in the SAMS app. This project uses **Firebase Cloud Messaging API v1**, which requires a **service account** and OAuth access, not the legacy server key flow.

## Problem
The old server-key setup is not the right credential model for FCM API v1. Without a Firebase service account, the backend cannot mint access tokens and call the v1 send endpoint.

## Solution Steps

### Step 1: Get Your Firebase Service Account

1. Go to [Firebase Console](https://console.firebase.google.com)
2. Select the project: **careful-form-373115**
3. Navigate to **Project Settings** (gear icon) → **Service Accounts**
4. Generate a **new private key** for the service account
5. Download the JSON file and store it securely

### Step 2: Configure FCM API v1 Credentials in Backend

For FCM API v1, configure the backend with the service account JSON or environment variables.

Example environment variables:

```bash
FIREBASE_SERVICE_ACCOUNT_JSON='{"type":"service_account",...}'
FIREBASE_PROJECT_ID='careful-form-373115'
```

For FCM API v1, use the service account JSON or environment variables only.
There is no legacy server-key configuration step in the v1 flow.

### Step 3: Verify FCM Configuration

Check if FCM is properly configured:

```bash
curl -X GET https://www.arkdev.app/api/fcm/check-setup.php \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

Expected successful response:
```json
{
  "success": true,
  "data": {
    "fcm_configured": true,
    "service_account_configured": true,
    "registered_devices": 5,
    "registered_users": 3,
    "status": "READY"
  }
}
```

### Step 4: Test Sending a Notification

Use the admin send notification API:

```bash
curl -X POST https://www.arkdev.app/api/notifications/send.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{
    "title": "Test Notification",
    "message": "This is a test FCM notification",
    "type": "system",
    "target_role": "student"
  }'
```

## Android App Side

### What's Already Configured:
✅ Firebase dependency in build.gradle.kts  
✅ google-services.json properly configured  
✅ SAMSFirebaseMessagingService registered in AndroidManifest.xml  
✅ All required permissions set (POST_NOTIFICATIONS, INTERNET, etc.)  
✅ Notification channel configured for Android 8+  

### How It Works:
1. When the app starts, Firebase SDK initializes and generates/retrieves FCM token
2. Once user logs in, the token is registered with the backend via `/api/fcm/register.php`
3. When admin sends notification, backend sends it via FCM to all registered devices
4. SAMSFirebaseMessagingService receives the notification and displays it
5. User sees the notification in their system notification tray

## Troubleshooting

### Notifications Not Appearing?

**Check 1: Is FCM configured?**
```bash
curl -X GET https://www.arkdev.app/api/fcm/check-setup.php
```
If `fcm_configured` is false, follow Step 2 above.

**Check 2: Are users registered?**
```bash
SELECT COUNT(*) FROM fcm_tokens WHERE is_active = TRUE;
```
If count is 0, users haven't logged in yet or app isn't registering tokens.

**Check 3: Check app logs for token registration**
- Open Android Studio logcat
- Filter by tag: `SAMSFirebaseMessaging`
- Look for messages like "FCM Token registered successfully"

**Check 4: Verify notification permissions**
- Android 13+ requires POST_NOTIFICATIONS permission
- User must grant notification permission to SAMS app in device settings
- Go to Settings → Apps → SAMS → Notifications → Allow

**Check 5: Check backend notification logs**
```bash
SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5;
SELECT * FROM fcm_tokens LIMIT 5;
```

## API Endpoints Reference

### Check FCM Setup
```
GET /api/fcm/check-setup.php
```
Auth: Admin  
Returns: FCM configuration status and token statistics

### Configure FCM
```
PUT /api/fcm/configure.php
```
Auth: Admin  
Body: `N/A for FCM API v1`  
Returns: This endpoint is legacy-only and should not be used for v1.

### Register FCM Token
```
POST /api/fcm/register.php
```
Auth: Authenticated User  
Body: `{ "token": "...", "device_type": "android", "device_name": "..." }`  
Returns: Confirmation of token registration

### Send Notification
```
POST /api/notifications/send.php
```
Auth: Admin  
Body:
```json
{
  "title": "Notification Title",
  "message": "Notification Body",
  "type": "system|attendance_alert|schedule_change",
  "target_role": "all|student|teacher|admin",
  "target_user_id": null,
  "target_department_id": null
}
```

## Important Notes

⚠️ **Never share your Firebase service account JSON** - it is highly sensitive  
⚠️ **Keep it safe** in a backend secret store or environment variables  
⚠️ **Rotate it periodically** for security  

## Need Help?

If notifications still don't work after following these steps:
1. Check browser console for errors when sending notification
2. Check server logs for FCM API errors
3. Verify Firebase project has billing enabled (required for notifications)
4. Make sure your Firebase project security rules allow notifications
