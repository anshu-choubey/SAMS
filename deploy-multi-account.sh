#!/bin/bash

# SAMS Backend - Multi-Account Azure Deployment Script
# This script helps deploy to different Azure accounts/subscriptions

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to show usage
show_usage() {
    echo -e "${BLUE}SAMS Backend - Multi-Account Azure Deployment${NC}"
    echo ""
    echo "Usage:"
    echo "  $0 [account-name] [options]"
    echo ""
    echo "Account Names:"
    echo "  dev        - Development environment"
    echo "  staging    - Staging environment"
    echo "  prod       - Production environment"
    echo "  custom     - Custom configuration"
    echo ""
    echo "Options:"
    echo "  --subscription-id SUB_ID    - Azure subscription ID"
    echo "  --resource-group RG_NAME    - Custom resource group name"
    echo "  --location LOCATION         - Azure region (default: eastus)"
    echo "  --skip-login               - Skip Azure login (if already logged in)"
    echo "  --help                     - Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 dev"
    echo "  $0 prod --subscription-id xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
    echo "  $0 custom --resource-group my-sams-rg --location westus2"
    echo ""
    echo "Environment Variables:"
    echo "  AZURE_SUBSCRIPTION_ID      - Default subscription ID"
    echo "  AZURE_LOCATION            - Default location"
    echo "  SKIP_AZURE_LOGIN          - Set to 'true' to skip login"
}

# Function to setup account-specific configuration
setup_account_config() {
    local account=$1

    case $account in
        "dev")
            export RESOURCE_GROUP="sams-dev-rg"
            export LOCATION="eastus"
            export APP_NAME="sams-backend-dev"
            export MYSQL_SERVER_NAME="sams-mysql-dev"
            export PLAN_NAME="sams-plan-dev"
            export ENVIRONMENT="development"
            ;;
        "staging")
            export RESOURCE_GROUP="sams-staging-rg"
            export LOCATION="eastus"
            export APP_NAME="sams-backend-staging"
            export MYSQL_SERVER_NAME="sams-mysql-staging"
            export PLAN_NAME="sams-plan-staging"
            export ENVIRONMENT="staging"
            ;;
        "prod")
            export RESOURCE_GROUP="sams-prod-rg"
            export LOCATION="eastus"
            export APP_NAME="sams-backend-prod"
            export MYSQL_SERVER_NAME="sams-mysql-prod"
            export PLAN_NAME="sams-plan-prod"
            export ENVIRONMENT="production"
            ;;
        "custom")
            export RESOURCE_GROUP="${RESOURCE_GROUP:-sams-custom-rg}"
            export LOCATION="${LOCATION:-eastus}"
            export APP_NAME="${APP_NAME:-sams-backend-custom}"
            export MYSQL_SERVER_NAME="${MYSQL_SERVER_NAME:-sams-mysql-custom}"
            export PLAN_NAME="${PLAN_NAME:-sams-plan-custom}"
            export ENVIRONMENT="custom"
            ;;
        *)
            echo -e "${RED}Error: Unknown account '$account'${NC}"
            echo ""
            show_usage
            exit 1
            ;;
    esac

    echo -e "${GREEN}Configured for $ENVIRONMENT environment${NC}"
}

# Parse command line arguments
ACCOUNT=""
SUBSCRIPTION_ID=""
SKIP_LOGIN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --subscription-id)
            SUBSCRIPTION_ID="$2"
            shift 2
            ;;
        --resource-group)
            RESOURCE_GROUP="$2"
            shift 2
            ;;
        --location)
            LOCATION="$2"
            shift 2
            ;;
        --skip-login)
            SKIP_LOGIN=true
            shift
            ;;
        --help)
            show_usage
            exit 0
            ;;
        dev|staging|prod|custom)
            ACCOUNT="$1"
            shift
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            show_usage
            exit 1
            ;;
    esac
done

# Check if account is specified
if [ -z "$ACCOUNT" ]; then
    echo -e "${RED}Error: No account specified${NC}"
    echo ""
    show_usage
    exit 1
fi

# Setup configuration for the account
setup_account_config "$ACCOUNT"

# Set subscription if provided
if [ -n "$SUBSCRIPTION_ID" ]; then
    export SUBSCRIPTION="$SUBSCRIPTION_ID"
fi

# Handle Azure login
if [ "$SKIP_LOGIN" = false ] && [ "${SKIP_AZURE_LOGIN:-false}" != "true" ]; then
    echo -e "${YELLOW}Azure Login Required${NC}"
    echo "Choose your login method:"
    echo "1. Interactive login (recommended)"
    echo "2. Device code login (for restricted environments)"
    echo "3. Service principal login"
    echo ""
    read -p "Enter choice (1-3): " login_choice

    case $login_choice in
        1)
            echo -e "${YELLOW}Logging in interactively...${NC}"
            az login
            ;;
        2)
            echo -e "${YELLOW}Logging in with device code...${NC}"
            az login --use-device-code
            ;;
        3)
            echo -e "${YELLOW}Service Principal Login${NC}"
            read -p "Enter client ID: " client_id
            read -p "Enter client secret: " client_secret
            read -p "Enter tenant ID: " tenant_id
            az login --service-principal -u "$client_id" -p "$client_secret" --tenant "$tenant_id"
            ;;
        *)
            echo -e "${RED}Invalid choice. Exiting.${NC}"
            exit 1
            ;;
    esac
fi

# Verify login
echo -e "${YELLOW}Verifying Azure login...${NC}"
if ! az account show > /dev/null 2>&1; then
    echo -e "${RED}Azure login failed. Please try again.${NC}"
    exit 1
fi

# Show account info
ACCOUNT_INFO=$(az account show --query "{name:name, user:name, subscriptionId:id}" -o json)
echo -e "${GREEN}✅ Logged in successfully!${NC}"
echo -e "${GREEN}📧 Account: $(echo $ACCOUNT_INFO | jq -r '.user')${NC}"
echo -e "${GREEN}📋 Subscription: $(echo $ACCOUNT_INFO | jq -r '.name')${NC}"
echo ""

# Set subscription if specified
if [ -n "$SUBSCRIPTION" ]; then
    echo -e "${YELLOW}Setting subscription...${NC}"
    az account set --subscription "$SUBSCRIPTION"
fi

# Generate unique suffix to avoid conflicts
UNIQUE_SUFFIX=$(echo $ACCOUNT_INFO | jq -r '.subscriptionId' | cut -c1-8)
if [ -z "$UNIQUE_SUFFIX" ]; then
    UNIQUE_SUFFIX=$(date +%s | tail -c 6)
fi

# Update resource names with unique suffix (only if not custom)
if [ "$ACCOUNT" != "custom" ]; then
    RESOURCE_GROUP="${RESOURCE_GROUP}-${UNIQUE_SUFFIX}"
    APP_NAME="${APP_NAME}-${UNIQUE_SUFFIX}"
    MYSQL_SERVER_NAME="${MYSQL_SERVER_NAME}-${UNIQUE_SUFFIX}"
    PLAN_NAME="${PLAN_NAME}-${UNIQUE_SUFFIX}"
fi

echo -e "${YELLOW}Resources ready for deployment${NC}"

# Confirm before proceeding
read -p "Continue with deployment? (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Deployment cancelled.${NC}"
    exit 0
fi

# Run the main deployment script
echo -e "${GREEN}🚀 Starting deployment...${NC}"
export RESOURCE_GROUP LOCATION APP_NAME MYSQL_SERVER_NAME MYSQL_DB_NAME PLAN_NAME SUBSCRIPTION
./deploy-azure.sh

echo ""
echo -e "${GREEN}🎉 Deployment to $ACCOUNT account complete!${NC}"
echo ""
echo -e "${YELLOW}📋 Next steps:${NC}"
echo "1. Note down your deployment URL"
echo "2. Update your DNS records if using custom domain"
echo "3. Configure your frontend application"
echo "4. Test the deployment with ./verify-deployment.sh"