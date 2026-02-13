#!/bin/bash

# SAMS Backend - Complete Azure Deployment (East US)
# This script handles the full deployment process for Azure for Students

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 SAMS Backend - Complete Azure Deployment (East US)${NC}"
echo -e "${BLUE}=====================================================${NC}"

# Configuration - East US Region
RESOURCE_GROUP="sams-rg-f4296e92"
LOCATION="eastus"  # East US region
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

# Step 2: Create Resource Group
echo -e "${YELLOW}Step 2: Creating Resource Group...${NC}"
az group create --name $RESOURCE_GROUP --location $LOCATION
check_error "Failed to create Resource Group"
echo -e "${GREEN}✅ Resource Group created${NC}"

# Step 3: Create MySQL Flexible Server
echo -e "${YELLOW}Step 3: Creating MySQL Flexible Server...${NC}"
az mysql flexible-server create \
  --resource-group $RESOURCE_GROUP \
  --name $MYSQL_SERVER \
  --location $LOCATION \
  --admin-user samsadmin \
  --admin-password $MYSQL_PASSWORD \
  --sku-name Standard_B1ms \
  --tier Burstable \
  --version 8.0 \
  --storage-size 20 \
  --public-access 0.0.0.0-255.255.255.255 \
  --yes
check_error "Failed to create MySQL Flexible Server"
echo -e "${GREEN}✅ MySQL Flexible Server created${NC}"

# Step 4: Create database
echo -e "${YELLOW}Step 4: Creating database...${NC}"
az mysql flexible-server db create \
  --resource-group $RESOURCE_GROUP \
  --server-name $MYSQL_SERVER \
  --database-name $MYSQL_DB
check_error "Failed to create database"
echo -e "${GREEN}✅ Database created${NC}"

# Step 5: Create App Service Plan
echo -e "${YELLOW}Step 5: Creating App Service Plan...${NC}"
az appservice plan create \
  --name $PLAN_NAME \
  --resource-group $RESOURCE_GROUP \
  --location $LOCATION \
  --sku B1 \
  --is-linux
check_error "Failed to create App Service Plan"
echo -e "${GREEN}✅ App Service Plan created${NC}"

# Step 6: Create Web App
echo -e "${YELLOW}Step 6: Creating Web App...${NC}"
az webapp create \
  --resource-group $RESOURCE_GROUP \
  --plan $PLAN_NAME \
  --name $APP_NAME \
  --runtime "PHP|8.1"
check_error "Failed to create Web App"
echo -e "${GREEN}✅ Web App created${NC}"

# Step 7: Configure environment variables
echo -e "${YELLOW}Step 7: Configuring environment variables...${NC}"

# Database connection settings
MYSQL_HOST="$MYSQL_SERVER.mysql.database.azure.com"
MYSQL_USER="samsadmin@$MYSQL_SERVER"

az webapp config appsettings set \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --settings \
  DB_HOST="$MYSQL_HOST" \
  DB_NAME="$MYSQL_DB" \
  DB_USER="$MYSQL_USER" \
  DB_PASS="$MYSQL_PASSWORD" \
  JWT_SECRET="your_super_secret_jwt_key_here_change_this_in_production_$(openssl rand -hex 32)"
check_error "Failed to configure environment variables"
echo -e "${GREEN}✅ Environment variables configured${NC}"

# Step 8: Set up Git deployment
echo -e "${YELLOW}Step 8: Setting up Git deployment...${NC}"

# Get Git URL
GIT_URL=$(az webapp deployment source config-local-git \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --output tsv)

check_error "Failed to set up Git deployment"
echo -e "${GREEN}✅ Git deployment configured${NC}"
echo -e "${BLUE}Git URL: $GIT_URL${NC}"

# Step 9: Initialize database
echo -e "${YELLOW}Step 9: Initializing database with sample data...${NC}"

# Database initialization command
MYSQL_COMMAND="mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DB < config/schema.sql"

echo "Running: $MYSQL_COMMAND"
eval $MYSQL_COMMAND
check_error "Failed to initialize database"
echo -e "${GREEN}✅ Database initialized with sample data${NC}"

echo ""
echo -e "${GREEN}🎉 DEPLOYMENT COMPLETE!${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "${BLUE}Your application is now deployed at:${NC}"
echo -e "${GREEN}https://$APP_NAME.azurewebsites.net${NC}"
echo ""
echo -e "${BLUE}Sample login credentials:${NC}"
echo "Admin: admin@sams.edu / password"
echo "Teacher: rajesh.kumar@sams.edu / password"
echo "Student: amit.kumar@student.sams.edu / password"
echo ""
echo -e "${BLUE}Database Details:${NC}"
echo "Host: $MYSQL_HOST"
echo "Database: $MYSQL_DB"
echo "Username: $MYSQL_USER"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Test the application at the URL above"
echo "2. Update the Android app with the new API URL"
echo "3. Change default passwords in production"