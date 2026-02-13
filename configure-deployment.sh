#!/bin/bash

# SAMS Backend - Configure Environment Variables and Deployment
# This script handles Steps 4-6: Environment variables, Git deployment, and database setup

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 SAMS Backend - Steps 4-6 Configuration${NC}"
echo -e "${BLUE}==========================================${NC}"

# Configuration - UPDATE THESE WITH YOUR ACTUAL VALUES
RESOURCE_GROUP="sams-rg-f4296e92"
APP_NAME="sams-backend-f4296e92"  # Your actual App Service name
MYSQL_SERVER="sams-mysql-server-f4296e92"  # Your actual MySQL server name
MYSQL_DB="sams_db"
MYSQL_ADMIN="samsadmin"  # Your MySQL admin username
MYSQL_PASSWORD="SecurePass123!"  # Your actual MySQL password

echo -e "${YELLOW}Configuration:${NC}"
echo "  Resource Group: $RESOURCE_GROUP"
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

# Step 4: Configure environment variables
echo -e "${YELLOW}Step 4: Configuring environment variables...${NC}"

# Database connection settings
MYSQL_HOST="$MYSQL_SERVER.mysql.database.azure.com"
MYSQL_USER="$MYSQL_ADMIN@$MYSQL_SERVER"

echo "Setting database environment variables..."
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

# Step 5: Set up Git deployment
echo -e "${YELLOW}Step 5: Setting up Git deployment...${NC}"

# Get Git URL
GIT_URL=$(az webapp deployment source config-local-git \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --output tsv)

check_error "Failed to set up Git deployment"
echo -e "${GREEN}✅ Git deployment configured${NC}"
echo -e "${BLUE}Git URL: $GIT_URL${NC}"

# Step 6: Database initialization instructions
echo -e "${YELLOW}Step 6: Database initialization${NC}"
echo -e "${BLUE}Run this command to initialize the database:${NC}"
echo "mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DB < config/schema.sql"
echo ""

# Additional deployment instructions
echo -e "${GREEN}🎉 Configuration complete!${NC}"
echo -e "${BLUE}Next steps:${NC}"
echo "1. Push your code to the Git URL above:"
echo "   git remote add azure $GIT_URL"
echo "   git push azure main"
echo ""
echo "2. Initialize the database (run the mysql command above)"
echo ""
echo "3. Test your deployed application at:"
echo -e "${BLUE}https://$APP_NAME.azurewebsites.net${NC}"
echo ""

# Optional: Show current app settings
echo -e "${YELLOW}Current App Settings:${NC}"
az webapp config appsettings list \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --output table