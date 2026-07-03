#!/bin/bash

# =====================================================
# SAMS Backend Deployment Script
# Multi-Check Attendance System
# =====================================================

set -e  # Exit on error

echo "========================================"
echo "SAMS Backend Deployment Script"
echo "Multi-Check Attendance System"
echo "========================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get Heroku app name
read -p "Enter your Heroku app name: " HEROKU_APP

if [ -z "$HEROKU_APP" ]; then
    echo -e "${RED}Error: Heroku app name is required${NC}"
    exit 1
fi

# Confirm deployment
echo ""
echo -e "${YELLOW}Warning: This will deploy to Heroku app: ${HEROKU_APP}${NC}"
read -p "Do you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Deployment cancelled."
    exit 0
fi

echo ""
echo "Step 1: Checking git status..."
git status

echo ""
read -p "Do you want to stage all changes? (yes/no): " STAGE_ALL

if [ "$STAGE_ALL" = "yes" ]; then
    echo "Staging all changes..."
    git add .
fi

echo ""
echo "Step 2: Committing changes..."
read -p "Enter commit message (or press Enter for default): " COMMIT_MSG

if [ -z "$COMMIT_MSG" ]; then
    COMMIT_MSG="feat: Add multi-check attendance system

- Added attendance_check_points and attendance_check_responses tables
- Updated teacher_locations with multi-check fields
- Enhanced attendance table with session tracking
- Added teacher APIs: trigger-attendance-check, finalize-attendance
- Added student APIs: active-attendance-checks, respond-attendance-check
- Updated Android app with full multi-check support
- Backward compatible with single-check system"
fi

git commit -m "$COMMIT_MSG" || echo "No changes to commit"

echo ""
echo "Step 3: Pushing to Heroku..."
git push heroku main || git push heroku master || {
    echo -e "${RED}Failed to push to Heroku${NC}"
    echo "Trying alternative branch names..."
    read -p "Enter your local branch name: " BRANCH_NAME
    git push heroku $BRANCH_NAME:main
}

echo ""
echo -e "${GREEN}Step 4: Deployment successful!${NC}"

echo ""
echo "Step 5: Checking deployment status..."
heroku ps --app $HEROKU_APP

echo ""
echo "Step 6: Showing recent logs..."
heroku logs --tail --app $HEROKU_APP --num 50

echo ""
echo -e "${GREEN}========================================"
echo "Deployment Complete!"
echo "========================================${NC}"
echo ""
echo "Next steps:"
echo "1. Run database migration (see migrations/add_multi_check_attendance.sql)"
echo "2. Test API endpoints"
echo "3. Monitor logs: heroku logs --tail --app $HEROKU_APP"
echo "4. Open app: heroku open --app $HEROKU_APP"
echo ""
echo "Database Migration Command:"
echo "heroku mysql:execute --app $HEROKU_APP < migrations/add_multi_check_attendance.sql"
echo ""
