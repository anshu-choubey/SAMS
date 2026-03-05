#!/bin/bash

# SAMS FCM Notification Diagnostic & Fix Script
# Usage: bash fcm-diagnose.sh

set -e

BASE_URL="${1:-https://www.arkdev.app}"
ADMIN_TOKEN="${2}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       SAMS FCM Notification Diagnostic Tool              ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

if [ -z "$ADMIN_TOKEN" ]; then
    echo -e "${YELLOW}Usage: bash fcm-diagnose.sh <BASE_URL> <ADMIN_TOKEN>${NC}"
    echo ""
    echo -e "${YELLOW}Example:${NC}"
    echo "  bash fcm-diagnose.sh https://www.arkdev.app YOUR_ADMIN_TOKEN"
    echo ""
    echo -e "${YELLOW}To get your admin token:${NC}"
    echo "  1. Log in as admin in the app"
    echo "  2. Check app local storage or SharedPreferences for 'session_token'"
    echo ""
    exit 1
fi

echo -e "${BLUE}Configuration:${NC}"
echo "  Base URL: $BASE_URL"
echo "  Admin Token: ${ADMIN_TOKEN:0:20}..."
echo ""

# Function to make API calls
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    if [ -z "$data" ]; then
        curl -s -X "$method" "$BASE_URL$endpoint" \
            -H "Authorization: Bearer $ADMIN_TOKEN" \
            -H "Content-Type: application/json"
    else
        curl -s -X "$method" "$BASE_URL$endpoint" \
            -H "Authorization: Bearer $ADMIN_TOKEN" \
            -H "Content-Type: application/json" \
            -d "$data"
    fi
}

# Step 1: Check FCM Setup
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}STEP 1: Checking FCM Configuration${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

DIAGNOSE=$(make_request GET "/api/fcm/diagnose.php")
echo "$DIAGNOSE" | jq '.' 2>/dev/null || echo "$DIAGNOSE"

FCM_CONFIGURED=$(echo "$DIAGNOSE" | jq -r '.data.fcm_config.configured // false' 2>/dev/null)
echo ""

if [ "$FCM_CONFIGURED" = "true" ]; then
    echo -e "${GREEN}✓ FCM Server Key is configured${NC}"
else
    echo -e "${RED}✗ FCM Server Key is NOT configured${NC}"
    echo ""
    echo -e "${YELLOW}TO FIX: Set your FCM Server Key${NC}"
    echo "  1. Get your key from Firebase Console:"
    echo "     https://console.firebase.google.com → careful-form-373115"
    echo "     Project Settings → Service Accounts → Database Secrets/Cloud Messaging"
    echo ""
    echo "  2. Run this command:"
    echo "     curl -X PUT $BASE_URL/api/fcm/configure.php \\"
    echo "       -H 'Content-Type: application/json' \\"
    echo "       -H 'Authorization: Bearer $ADMIN_TOKEN' \\"
    echo "       -d '{\"fcm_server_key\": \"AAAA...\"}'"
    echo ""
    exit 1
fi

# Step 2: Check registered tokens
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}STEP 2: Checking Registered Devices${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

TOKEN_COUNT=$(echo "$DIAGNOSE" | jq '.data.token_stats.total_tokens // 0' 2>/dev/null)
ACTIVE_COUNT=$(echo "$DIAGNOSE" | jq '.data.token_stats.active_tokens // 0' 2>/dev/null)

echo "Total registered tokens: $TOKEN_COUNT"
echo "Active tokens: $ACTIVE_COUNT"
echo ""

if [ "$TOKEN_COUNT" = "0" ] || [ "$ACTIVE_COUNT" = "0" ]; then
    echo -e "${RED}✗ No active FCM tokens registered${NC}"
    echo ""
    echo -e "${YELLOW}TO FIX: Have users log in with the SAMS app${NC}"
    echo "  1. Open SAMS app on Android device"
    echo "  2. Log in with valid credentials"
    echo "  3. Watch the logs:"
    echo "     adb logcat | grep SAMSFirebaseMessaging"
    echo ""
    echo "  You should see:"
    echo "   'FCM Token registered successfully with backend'"
    echo ""
    echo "  Possible issues:"
    echo "   - User hasn't logged in yet"
    echo "   - App isn't registering tokens"
    echo "   - Device doesn't have notification permission"
    echo ""
    exit 1
else
    echo -e "${GREEN}✓ $ACTIVE_COUNT active tokens found${NC}"
fi

# Step 3: Send test notification
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}STEP 3: Sending Test Notification${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

TEST_RESULT=$(make_request POST "/api/fcm/test-send.php" "{}")
echo "$TEST_RESULT" | jq '.' 2>/dev/null || echo "$TEST_RESULT"
echo ""

TEST_SUCCESS=$(echo "$TEST_RESULT" | jq -r '.data.status // "FAILED"' 2>/dev/null)

if [ "$TEST_SUCCESS" = "SUCCESS" ]; then
    echo -e "${GREEN}✓ Test notification sent successfully!${NC}"
    echo ""
    echo -e "${YELLOW}CHECK YOUR PHONE:${NC}"
    echo "  1. Pull down notification shade"
    echo "  2. You should see 'FCM Test Notification'"
    echo "  3. If you see it, FCM is working!"
    echo ""
else
    echo -e "${RED}✗ Test notification failed${NC}"
    echo -e "${YELLOW}Error: $(echo "$TEST_RESULT" | jq -r '.message' 2>/dev/null)${NC}"
    echo ""
fi

# Summary
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}SUMMARY${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [ "$TEST_SUCCESS" = "SUCCESS" ]; then
    echo -e "${GREEN}✓ FCM appears to be working correctly!${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Ensure notification permissions are enabled on devices"
    echo "  2. Create notifications via the admin panel"
    echo "  3. Notifications will appear on user devices"
else
    echo -e "${RED}✗ FCM is not working properly yet${NC}"
    echo ""
    echo "Common issues:"
    echo "  1. FCM Server Key is invalid - get a new one from Firebase"
    echo "  2. No devices have registered tokens - have users log in"
    echo "  3. Network connectivity issue - check Firebase API is accessible"
fi

echo ""
echo -e "${BLUE}For more help, see: FCM_TROUBLESHOOTING.md${NC}"
