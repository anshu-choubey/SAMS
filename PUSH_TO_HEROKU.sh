#!/bin/bash

# =====================================================
# Push to Heroku Script
# =====================================================

echo "========================================"
echo "Pushing to Heroku"
echo "========================================"
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Get Heroku app name
read -p "Enter your Heroku app name: " HEROKU_APP

if [ -z "$HEROKU_APP" ]; then
    echo -e "${RED}Error: Heroku app name is required${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Pushing to Heroku app: ${HEROKU_APP}${NC}"
echo ""

# Push to Heroku
echo "Executing: git push heroku main"
git push heroku main

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ Code deployed successfully!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Run database migration"
    echo "2. Verify deployment"
    echo ""
    echo "Database Migration Command:"
    echo "mysql -h hostname -u username -p database_name < migrations/add_multi_check_attendance.sql"
    echo ""
    echo "Get database credentials:"
    echo "heroku config:get CLEARDB_DATABASE_URL --app $HEROKU_APP"
else
    echo ""
    echo -e "${RED}❌ Deployment failed${NC}"
    echo ""
    echo "Common solutions:"
    echo "1. Check if 'heroku' remote exists: git remote -v"
    echo "2. Add heroku remote: heroku git:remote -a $HEROKU_APP"
    echo "3. Try: git push heroku master (if main branch is master)"
    exit 1
fi
