#!/bin/bash

# SAMS Backend - Manual Deployment Steps 3-6
# This script handles App Service creation and deployment (assuming MySQL is already created)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 SAMS Backend - Steps 3-6 Deployment${NC}"
echo -e "${BLUE}=========================================${NC}"

# Configuration - Update these with your actual MySQL details
RESOURCE_GROUP="sams-rg-f4296e92"
LOCATION="eastus"  # CHANGE THIS: Use same region as your MySQL server
APP_NAME="sams-backend-f4296e92"
PLAN_NAME="sams-plan-f4296e92"
MYSQL_SERVER="sams-mysql-server-f4296e92"  # Update with your actual server name
MYSQL_DB="sams_db"
MYSQL_ADMIN="samsadmin"  # Update with your admin username
MYSQL_PASSWORD="SecurePass123!"  # Update with your actual password

echo -e "${YELLOW}Configuration:${NC}"
echo "  Resource Group: $RESOURCE_GROUP"
echo "  Location: $LOCATION"
echo "  App Name: $APP_NAME"
echo "  MySQL Server: $MYSQL_SERVER"
echo ""

# Function to check command success
check_error() {
    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ Error: $1${NC}"
        exit 1
    fi
}

# Step 3: Create App Service Plan
echo -e "${YELLOW}Step 3: Creating App Service Plan...${NC}"
az appservice plan create \
  --name $PLAN_NAME \
  --resource-group $RESOURCE_GROUP \
  --location $LOCATION \
  --sku B1 \
  --is-linux
check_error "Failed to create App Service Plan"
echo -e "${GREEN}✅ App Service Plan created${NC}"

# Step 4: Create Web App
echo -e "${YELLOW}Step 4: Creating Web App...${NC}"
az webapp create \
  --resource-group $RESOURCE_GROUP \
  --plan $PLAN_NAME \
  --name $APP_NAME \
  --runtime "PHP|8.1"
check_error "Failed to create Web App"
echo -e "${GREEN}✅ Web App created${NC}"

# Step 5: Configure environment variables
echo -e "${YELLOW}Step 5: Configuring environment variables...${NC}"

# Database connection settings
MYSQL_HOST="$MYSQL_SERVER.mysql.database.azure.com"
MYSQL_USER="$MYSQL_ADMIN@$MYSQL_SERVER"

az webapp config appsettings set \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --settings \
  DB_HOST="$MYSQL_HOST" \
  DB_NAME="$MYSQL_DB" \
  DB_USER="$MYSQL_USER" \
  DB_PASS="$MYSQL_PASSWORD" \
  JWT_SECRET="your_super_secret_jwt_key_here_change_this_in_production"
check_error "Failed to configure environment variables"
echo -e "${GREEN}✅ Environment variables configured${NC}"

# Step 6: Set up Git deployment
echo -e "${YELLOW}Step 6: Setting up Git deployment...${NC}"

# Get Git URL
GIT_URL=$(az webapp deployment source config-local-git \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --output tsv)

check_error "Failed to set up Git deployment"
echo -e "${GREEN}✅ Git deployment configured${NC}"
echo -e "${BLUE}Git URL: $GIT_URL${NC}"

# Step 7: Initialize database (optional - run schema.sql)
echo -e "${YELLOW}Step 7: Database initialization instructions${NC}"
echo -e "${BLUE}To initialize the database, connect to your MySQL server and run:${NC}"
echo "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DB < config/schema.sql"
echo ""

echo -e "${GREEN}🎉 Deployment setup complete!${NC}"
echo -e "${BLUE}Next steps:${NC}"
echo "1. Push your code to the Git URL above"
echo "2. Run the database schema initialization command"
echo "3. Test your deployed application"
echo ""
echo -e "${BLUE}Your app will be available at: https://$APP_NAME.azurewebsites.net${NC}"