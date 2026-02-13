#!/bin/bash

# SAMS Backend - Deployment Test Script
# Tests the deployed Azure resources and application

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DEPLOYMENT_URL=""
DB_CONNECTION_TEST=""

# Function to show usage
show_usage() {
    echo -e "${BLUE}SAMS Backend - Deployment Test${NC}"
    echo ""
    echo "Usage:"
    echo "  $0 [deployment-url]"
    echo ""
    echo "Examples:"
    echo "  $0 https://sams-backend-dev.azurewebsites.net"
    echo "  $0 https://sams-backend-prod.azurewebsites.net"
    echo ""
}

# Function to test HTTP endpoint
test_endpoint() {
    local url=$1
    local expected_status=${2:-200}

    echo -e "${YELLOW}Testing: $url${NC}"

    response=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
    if [ "$response" = "$expected_status" ]; then
        echo -e "${GREEN}✓ HTTP $response - OK${NC}"
        return 0
    else
        echo -e "${RED}✗ HTTP $response - Expected $expected_status${NC}"
        return 1
    fi
}

# Function to test database connection
test_database() {
    local url=$1

    echo -e "${YELLOW}Testing database connection: $url/api/test-db.php${NC}"

    response=$(curl -s "$url/api/test-db.php" 2>/dev/null)
    if echo "$response" | grep -q "Database connection successful"; then
        echo -e "${GREEN}✓ Database connection - OK${NC}"
        return 0
    else
        echo -e "${RED}✗ Database connection failed${NC}"
        echo "Response: $response"
        return 1
    fi
}

# Function to test API endpoints
test_api_endpoints() {
    local base_url=$1

    echo -e "${BLUE}Testing API endpoints...${NC}"

    # Test public endpoints
    test_endpoint "$base_url/api/public/login.php" 405  # Method not allowed (expects POST)

    # Test login endpoint with invalid data
    response=$(curl -s -X POST "$base_url/api/public/login.php" \
        -d "username=test&password=test" 2>/dev/null)
    if echo "$response" | grep -q "error"; then
        echo -e "${GREEN}✓ Login endpoint responding${NC}"
    else
        echo -e "${YELLOW}⚠ Login endpoint response unexpected${NC}"
    fi
}

# Function to check Azure resources
check_azure_resources() {
    echo -e "${BLUE}Checking Azure resources...${NC}"

    # Check if Azure CLI is logged in
    if ! az account show >/dev/null 2>&1; then
        echo -e "${RED}✗ Not logged in to Azure CLI${NC}"
        echo -e "${YELLOW}Run: az login${NC}"
        return 1
    fi

    echo -e "${GREEN}✓ Azure CLI authenticated${NC}"

    # Get current subscription
    subscription=$(az account show --query name -o tsv 2>/dev/null)
    echo -e "${GREEN}✓ Current subscription: $subscription${NC}"
}

# Main function
main() {
    if [ $# -eq 0 ]; then
        show_usage
        exit 1
    fi

    DEPLOYMENT_URL=$1

    echo -e "${BLUE}SAMS Backend Deployment Test${NC}"
    echo -e "${BLUE}================================${NC}"
    echo "Testing deployment at: $DEPLOYMENT_URL"
    echo ""

    # Check Azure resources
    check_azure_resources
    echo ""

    # Test basic connectivity
    echo -e "${BLUE}Testing basic connectivity...${NC}"
    test_endpoint "$DEPLOYMENT_URL" 200
    test_endpoint "$DEPLOYMENT_URL/index.php" 200
    echo ""

    # Test database connection
    test_database "$DEPLOYMENT_URL"
    echo ""

    # Test API endpoints
    test_api_endpoints "$DEPLOYMENT_URL"
    echo ""

    echo -e "${BLUE}Test completed!${NC}"
    echo ""
    echo -e "${YELLOW}If all tests passed, your deployment is working correctly.${NC}"
    echo -e "${YELLOW}If any tests failed, check the deployment logs and configuration.${NC}"
}

# Run main function with all arguments
main "$@"