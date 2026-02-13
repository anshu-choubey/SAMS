# SAMS Backend - Azure Deployment Script
#!/bin/bash

# Configuration - Update these for different accounts
RESOURCE_GROUP="${RESOURCE_GROUP:-sams-rg}"
LOCATION="${LOCATION:-centralindia}"  # Changed from eastus for Azure for Students
APP_NAME="${APP_NAME:-sams-backend}"
MYSQL_SERVER_NAME="${MYSQL_SERVER_NAME:-sams-mysql-server}"
MYSQL_DB_NAME="${MYSQL_DB_NAME:-sams_db}"
PLAN_NAME="${PLAN_NAME:-sams-plan}"
SUBSCRIPTION="${SUBSCRIPTION:-}"  # Set this for different subscriptions

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Starting SAMS Backend Deployment to Azure${NC}"

# Set subscription if specified
if [ -n "$SUBSCRIPTION" ]; then
    echo -e "${YELLOW}Setting subscription to: $SUBSCRIPTION${NC}"
    az account set --subscription "$SUBSCRIPTION"
fi

# Check if logged in
echo -e "${YELLOW}Checking Azure CLI login...${NC}"
az account show > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}Please login to Azure first:${NC}"
    echo "az login"
    echo "Or for different account: az login --use-device-code"
    exit 1
fi

# Show current account info
ACCOUNT_INFO=$(az account show --query "{name:name, user:name, subscriptionId:id}" -o json)
echo -e "${GREEN}✅ Logged in as:$(echo $ACCOUNT_INFO | jq -r '.user') ${NC}"
echo -e "${GREEN}📧 Subscription: $(echo $ACCOUNT_INFO | jq -r '.name') ${NC}"

# Generate unique suffix to avoid conflicts
UNIQUE_SUFFIX=$(echo $ACCOUNT_INFO | jq -r '.subscriptionId' | cut -c1-8)
if [ -z "$UNIQUE_SUFFIX" ]; then
    UNIQUE_SUFFIX=$(date +%s | tail -c 6)
fi

# Update resource names with unique suffix
RESOURCE_GROUP="${RESOURCE_GROUP}-${UNIQUE_SUFFIX}"
APP_NAME="${APP_NAME}-${UNIQUE_SUFFIX}"
MYSQL_SERVER_NAME="${MYSQL_SERVER_NAME}-${UNIQUE_SUFFIX}"
PLAN_NAME="${PLAN_NAME}-${UNIQUE_SUFFIX}"

echo -e "${YELLOW}Using unique resource names for this account:${NC}"
echo "Resource Group: $RESOURCE_GROUP"
echo "App Service: $APP_NAME"
echo "MySQL Server: $MYSQL_SERVER_NAME"
echo "App Service Plan: $PLAN_NAME"

# Create resource group
echo -e "${YELLOW}Creating resource group...${NC}"
az group create --name $RESOURCE_GROUP --location $LOCATION

# Create MySQL Flexible Server (new version)
echo -e "${YELLOW}Creating Azure Database for MySQL Flexible Server...${NC}"
MYSQL_PASSWORD=$(openssl rand -base64 12 | tr -d "=+/" | cut -c1-16)
az mysql flexible-server create \
  --resource-group $RESOURCE_GROUP \
  --name $MYSQL_SERVER_NAME \
  --location $LOCATION \
  --admin-user samsadmin \
  --admin-password $MYSQL_PASSWORD \
  --sku-name Standard_B1ms \
  --tier Burstable \
  --version 8.0 \
  --storage-size 32 \
  --public-access 0.0.0.0-255.255.255.255 \
  --yes

# Create database
echo -e "${YELLOW}Creating database...${NC}"
az mysql flexible-server db create \
  --resource-group $RESOURCE_GROUP \
  --server-name $MYSQL_SERVER_NAME \
  --database-name $MYSQL_DB_NAME

# Firewall is already configured with --public-access above
echo -e "${YELLOW}Firewall configured (public access enabled)...${NC}"

# Create App Service Plan
echo -e "${YELLOW}Creating App Service Plan...${NC}"
az appservice plan create \
  --name $PLAN_NAME \
  --resource-group $RESOURCE_GROUP \
  --location $LOCATION \
  --sku B1 \
  --is-linux

# Create Web App
echo -e "${YELLOW}Creating Web App...${NC}"
az webapp create \
  --resource-group $RESOURCE_GROUP \
  --plan $PLAN_NAME \
  --name $APP_NAME \
  --runtime "PHP|8.1"

# Configure PHP settings
echo -e "${YELLOW}Configuring PHP settings...${NC}"
az webapp config appsettings set \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --setting WEBSITES_ENABLE_APP_SERVICE_STORAGE=false

# Set environment variables
echo -e "${YELLOW}Setting environment variables...${NC}"
az webapp config appsettings set \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --setting MYSQL_HOST="$MYSQL_SERVER_NAME.mysql.database.azure.com" \
  --setting MYSQL_USER="samsadmin" \
  --setting MYSQL_PASSWORD="$MYSQL_PASSWORD" \
  --setting MYSQL_DATABASE="$MYSQL_DB_NAME" \
  --setting MYSQL_PORT="3306" \
  --setting MYSQL_SSL="PREFERRED" \
  --setting JWT_SECRET="$(openssl rand -hex 32)"

# Get deployment URL
echo -e "${YELLOW}Getting deployment URL...${NC}"
DEPLOY_URL=$(az webapp deployment source config-local-git-get-url \
  --name $APP_NAME \
  --resource-group $RESOURCE_GROUP \
  --query url -o tsv)

echo -e "${GREEN}✅ Deployment setup complete!${NC}"
echo ""
echo -e "${YELLOW}📋 Next steps:${NC}"
echo "1. Add Azure remote to your git repository:"
echo "   git remote add azure $DEPLOY_URL"
echo ""
echo "2. Push your code:"
echo "   git push azure main"
echo ""
echo "3. Import database schema:"
echo "   az mysql flexible-server db import --resource-group $RESOURCE_GROUP --server-name $MYSQL_SERVER_NAME --database-name $MYSQL_DB_NAME --file config/schema.sql"
echo ""
echo -e "${GREEN}🌐 Your app will be available at: https://$APP_NAME.azurewebsites.net${NC}"
echo ""
echo -e "${YELLOW}🔒 Security Note: Remember to:${NC}"
echo "- Update MySQL password in production"
echo "- Enable SSL enforcement on MySQL"
echo "- Configure CORS for your frontend domain"
echo "- Set up proper firewall rules"