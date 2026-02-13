#!/bin/bash

# SAMS Backend - Azure Account Switcher
# Helps switch between different Azure accounts/subscriptions

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration file
CONFIG_FILE="azure-accounts.ini"

# Function to show usage
show_usage() {
    echo -e "${BLUE}SAMS Backend - Azure Account Switcher${NC}"
    echo ""
    echo "Usage:"
    echo "  $0 [account-name]"
    echo ""
    echo "Available accounts in $CONFIG_FILE:"
    if [ -f "$CONFIG_FILE" ]; then
        grep '^\[' "$CONFIG_FILE" | sed 's/\[\(.*\)\]/  \1/'
    else
        echo "  dev"
        echo "  staging"
        echo "  prod"
        echo "  custom"
    fi
    echo ""
    echo "Examples:"
    echo "  $0 dev"
    echo "  $0 prod"
    echo ""
}

# Function to get config value
get_config_value() {
    local section=$1
    local key=$2
    if [ -f "$CONFIG_FILE" ]; then
        awk -F '=' -v section="[$section]" -v key="$key" '
            $0 == section { in_section=1; next }
            /^\[/ { in_section=0 }
            in_section && $1 == key { print $2; exit }
        ' "$CONFIG_FILE" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'
    fi
}

# Check if account is provided
if [ $# -eq 0 ]; then
    show_usage
    exit 1
fi

ACCOUNT=$1

# Get subscription ID from config
SUBSCRIPTION_ID=$(get_config_value "$ACCOUNT" "subscription_id")

if [ -z "$SUBSCRIPTION_ID" ]; then
    echo -e "${YELLOW}No subscription ID found for account '$ACCOUNT' in $CONFIG_FILE${NC}"
    echo -e "${YELLOW}Please add it to the config file or specify manually${NC}"
    echo ""
    read -p "Enter subscription ID manually: " SUBSCRIPTION_ID
fi

if [ -z "$SUBSCRIPTION_ID" ]; then
    echo -e "${RED}No subscription ID provided. Exiting.${NC}"
    exit 1
fi

echo -e "${YELLOW}Switching to account: $ACCOUNT${NC}"
echo -e "${YELLOW}Subscription ID: $SUBSCRIPTION_ID${NC}"

# Check if already logged in to this subscription
CURRENT_SUB=$(az account show --query id -o tsv 2>/dev/null || echo "")

if [ "$CURRENT_SUB" = "$SUBSCRIPTION_ID" ]; then
    echo -e "${GREEN}Already logged in to this subscription${NC}"
else
    # Try to set the subscription
    if az account set --subscription "$SUBSCRIPTION_ID" 2>/dev/null; then
        echo -e "${GREEN}Successfully switched to subscription${NC}"
    else
        echo -e "${RED}Failed to switch to subscription. You may need to login first.${NC}"
        echo -e "${YELLOW}Run: az login${NC}"
        exit 1
    fi
fi

# Show current account info
ACCOUNT_INFO=$(az account show --query "{name:name, user:name, subscriptionId:id}" -o json 2>/dev/null)
if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}Current Account:${NC}"
    echo "  User: $(echo $ACCOUNT_INFO | jq -r '.user')"
    echo "  Subscription: $(echo $ACCOUNT_INFO | jq -r '.name')"
    echo "  ID: $(echo $ACCOUNT_INFO | jq -r '.subscriptionId')"
    echo ""
    echo -e "${GREEN}Ready to deploy to $ACCOUNT environment!${NC}"
    echo -e "${YELLOW}Run: ./deploy-multi-account.sh $ACCOUNT${NC}"
else
    echo -e "${RED}Failed to get account information${NC}"
    exit 1
fi