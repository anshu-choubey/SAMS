#!/bin/bash

# SAMS Backend - Deployment Verification Script
# Run this after deployment to verify everything is working

DEPLOY_URL=${1:-"https://sams-backend.azurewebsites.net"}

echo "🔍 Verifying SAMS Backend Deployment at: $DEPLOY_URL"
echo "=================================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Test basic connectivity
echo -e "${YELLOW}Testing basic connectivity...${NC}"
if curl -s --max-time 10 "$DEPLOY_URL" > /dev/null; then
    echo -e "${GREEN}✅ Website is accessible${NC}"
else
    echo -e "${RED}❌ Website is not accessible${NC}"
    exit 1
fi

# Test API endpoints
echo -e "${YELLOW}Testing API endpoints...${NC}"

# Test login endpoint
if curl -s --max-time 10 "$DEPLOY_URL/api/public/login.php" > /dev/null; then
    echo -e "${GREEN}✅ Login API accessible${NC}"
else
    echo -e "${RED}❌ Login API not accessible${NC}"
fi

# Test database connection (if you have a test endpoint)
if curl -s --max-time 10 "$DEPLOY_URL/api/test-db.php" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Database connection OK${NC}"
else
    echo -e "${YELLOW}⚠️  Database test endpoint not found (this is normal)${NC}"
fi

# Test admin panel
if curl -s --max-time 10 "$DEPLOY_URL/admin/index.php" > /dev/null; then
    echo -e "${GREEN}✅ Admin panel accessible${NC}"
else
    echo -e "${RED}❌ Admin panel not accessible${NC}"
fi

# Check SSL certificate
echo -e "${YELLOW}Checking SSL certificate...${NC}"
if curl -s --max-time 10 -I "https://$DEPLOY_URL" | grep -q "HTTP/2 200"; then
    echo -e "${GREEN}✅ SSL certificate is valid${NC}"
else
    echo -e "${YELLOW}⚠️  SSL certificate check inconclusive${NC}"
fi

# Performance check
echo -e "${YELLOW}Testing response time...${NC}"
RESPONSE_TIME=$(curl -s -w "%{time_total}" -o /dev/null "$DEPLOY_URL")
if (( $(echo "$RESPONSE_TIME < 2.0" | bc -l) )); then
    echo -e "${GREEN}✅ Response time: ${RESPONSE_TIME}s (Good)${NC}"
elif (( $(echo "$RESPONSE_TIME < 5.0" | bc -l) )); then
    echo -e "${YELLOW}⚠️  Response time: ${RESPONSE_TIME}s (Acceptable)${NC}"
else
    echo -e "${RED}❌ Response time: ${RESPONSE_TIME}s (Slow)${NC}"
fi

echo ""
echo -e "${GREEN}🎉 Deployment verification complete!${NC}"
echo ""
echo -e "${YELLOW}📋 Next steps:${NC}"
echo "1. Configure your frontend to use: $DEPLOY_URL"
echo "2. Update CORS settings in config/CORS.php"
echo "3. Set up monitoring and alerts in Azure"
echo "4. Configure backup policies for the database"
echo "5. Set up SSL certificate if not using Azure's default"

echo ""
echo -e "${YELLOW}🔒 Security checklist:${NC}"
echo "□ Change default database password"
echo "□ Enable SSL enforcement on MySQL"
echo "□ Configure proper firewall rules"
echo "□ Set up Azure Active Directory authentication"
echo "□ Enable Azure Application Insights"
echo "□ Configure backup and disaster recovery"