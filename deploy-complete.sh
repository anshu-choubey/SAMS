#!/bin/bash

# SAMS Backend - Complete Azure Deployment Script
# This script handles the full deployment process for Azure for Students

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 SAMS Backend - Complete Azure Deployment${NC}"
echo -e "${BLUE}=============================================${NC}"

# Configuration
RESOURCE_GROUP="sams-rg-f4296e92"
LOCATION="eastus"  # East US region for Azure for Students
APP_NAME="sams-backend-f4296e92"
MYSQL_SERVER="sams-mysql-f4296e92"
MYSQL_DB="sams_db"
PLAN_NAME="sams-plan-f4296e92"
MYSQL_PASSWORD="SecurePass123!"  # Change this!

echo -e "${YELLOW}Configuration:${NC}"
echo "  Resource Group: $RESOURCE_GROUP"
echo "  Location: $LOCATION (East US)"
echo "  App Name: $APP_NAME"
echo "  MySQL Server: $MYSQL_SERVER"
echo "  Database: $MYSQL_DB"
echo ""

# Function to check command success
check_error() {
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ Error: $1${NC}"
        exit 1
    fi
}

# Step 1: Clean up old resources
echo -e "${YELLOW}Step 1: Cleaning up old resources...${NC}"
az group delete --name $RESOURCE_GROUP --yes --no-wait 2>/dev/null
echo -e "${GREEN}✅ Cleanup initiated${NC}"

# Wait for cleanup
echo -e "${YELLOW}Waiting for cleanup to complete (2 minutes)...${NC}"
sleep 120

# Step 2: Create MySQL Flexible Server
echo -e "${YELLOW}Step 2: Creating MySQL Flexible Server...${NC}"
az mysql flexible-server create \
  --resource-group $RESOURCE_GROUP \
  --name $MYSQL_SERVER \
  --location $LOCATION \
  --admin-user samsadmin \
  --admin-password $MYSQL_PASSWORD \
  --sku-name Standard_B1ms \
  --tier Burstable \
  --version 8.0.21 \
  --storage-size 32 \
  --public-access 0.0.0.0-255.255.255.255 \
  --yes
check_error "Failed to create MySQL Flexible Server"
echo -e "${GREEN}✅ MySQL Flexible Server created${NC}"

# Step 3: Create database
echo -e "${YELLOW}Step 3: Creating database...${NC}"
az mysql flexible-server db create \
  --resource-group $RESOURCE_GROUP \
  --server-name $MYSQL_SERVER \
  --database-name $MYSQL_DB
check_error "Failed to create database"
echo -e "${GREEN}✅ Database created${NC}"

# Step 4: Create App Service Plan
echo -e "${YELLOW}Step 4: Creating App Service Plan...${NC}"
az appservice plan create \
  --name $PLAN_NAME \
  --resource-group $RESOURCE_GROUP \
  --location $LOCATION \
  --sku B1 \
  --is-linux
check_error "Failed to create App Service Plan"
echo -e "${GREEN}✅ App Service Plan created${NC}"

# Step 5: Create Web App
echo -e "${YELLOW}Step 5: Creating Web App...${NC}"
az webapp create \
  --resource-group $RESOURCE_GROUP \
  --plan $PLAN_NAME \
  --name $APP_NAME \
  --runtime "PHP|8.2"
check_error "Failed to create Web App"
echo -e "${GREEN}✅ Web App created${NC}"

# Step 6: Configure environment variables
echo -e "${YELLOW}Step 6: Configuring environment variables...${NC}"
az webapp config appsettings set \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --settings \
    MYSQL_HOST="$MYSQL_SERVER.mysql.database.azure.com" \
    MYSQL_USER="samsadmin" \
    MYSQL_PASSWORD="$MYSQL_PASSWORD" \
    MYSQL_DATABASE="$MYSQL_DB" \
    MYSQL_PORT="3306" \
    MYSQL_SSL="PREFERRED" \
    JWT_SECRET="your-super-secret-jwt-key-here-change-this"
check_error "Failed to configure environment variables"
echo -e "${GREEN}✅ Environment variables configured${NC}"

# Step 7: Setup deployment
echo -e "${YELLOW}Step 7: Setting up Git deployment...${NC}"
az webapp deployment source config-local-git \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP
check_error "Failed to setup Git deployment"
echo -e "${GREEN}✅ Git deployment configured${NC}"

# Step 8: Get deployment URL
echo -e "${YELLOW}Step 8: Getting deployment URL...${NC}"
DEPLOY_URL=$(az webapp deployment source config-local-git-get-url \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --query url -o tsv)
check_error "Failed to get deployment URL"
echo -e "${GREEN}✅ Deployment URL obtained: $DEPLOY_URL${NC}"

# Step 9: Deploy code
echo -e "${YELLOW}Step 9: Deploying code...${NC}"
git remote remove azure 2>/dev/null || true
git remote add azure $DEPLOY_URL
check_error "Failed to add Git remote"

echo -e "${YELLOW}Pushing code to Azure...${NC}"
git push azure main --force
check_error "Failed to push code to Azure"
echo -e "${GREEN}✅ Code deployed successfully${NC}"

# Step 10: Import database schema
echo -e "${YELLOW}Step 10: Importing database schema...${NC}"
if [ -f "config/schema.sql" ]; then
    az mysql flexible-server db import \
      --resource-group $RESOURCE_GROUP \
      --server-name $MYSQL_SERVER \
      --database-name $MYSQL_DB \
      --file config/schema.sql
    check_error "Failed to import database schema"
    echo -e "${GREEN}✅ Database schema imported${NC}"
else
    echo -e "${YELLOW}⚠️  Schema file not found, skipping database import${NC}"
fi

# Step 11: Get final app URL
echo -e "${YELLOW}Step 11: Getting final app URL...${NC}"
APP_URL=$(az webapp show --name $APP_NAME --resource-group $RESOURCE_GROUP --query defaultHostName -o tsv)
check_error "Failed to get app URL"

echo ""
echo -e "${GREEN}🎉 DEPLOYMENT COMPLETE!${NC}"
echo -e "${GREEN}========================${NC}"
echo ""
echo -e "${GREEN}🌐 Your SAMS Backend is live at:${NC}"
echo -e "${BLUE}   https://$APP_URL${NC}"
echo ""
echo -e "${GREEN}📊 Test your deployment:${NC}"
echo -e "${BLUE}   curl https://$APP_URL/api/public/login.php${NC}"
echo ""
echo -e "${GREEN}🔒 Security Notes:${NC}"
echo "   • Change the MySQL password from 'SecurePass123!'"
echo "   • Update the JWT secret in environment variables"
echo "   • Configure CORS for your frontend domain"
echo ""
echo -e "${GREEN}📋 Next Steps:${NC}"
echo "   1. Test the API endpoints"
echo "   2. Connect your Android app to this backend"
echo "   3. Set up monitoring and alerts"
echo ""
echo -e "${YELLOW}⚠️  Remember to update your frontend API base URL to:${NC}"
echo -e "${BLUE}   https://$APP_URL/api/${NC}"