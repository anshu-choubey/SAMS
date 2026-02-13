# SAMS Backend - Azure Deployment Guide

This guide will help you deploy the SAMS (Student Attendance Management System) backend to Microsoft Azure.

## 🚀 Quick Deployment

### Prerequisites
- Azure subscription
- Azure CLI installed and authenticated (`az login`)
- Git repository

### One-Command Deployment
```bash
# Make script executable and run
chmod +x deploy-azure.sh
./deploy-azure.sh
```

This will automatically:
- Create Azure resource group
- Set up Azure Database for MySQL
- Create Azure App Service
- Configure environment variables
- Provide deployment URL

## 📋 Manual Deployment Steps

### 1. Azure CLI Setup
```bash
# Install Azure CLI (macOS)
brew install azure-cli

# Login to Azure
az login

# Set subscription (if you have multiple)
az account set --subscription "your-subscription-name"
```

### 2. Create Resources
```bash
# Run the deployment script
./deploy-azure.sh
```

Or create manually:
```bash
# Create resource group
az group create --name sams-rg --location eastus

# Create MySQL database
az mysql server create \
  --resource-group sams-rg \
  --name sams-mysql-server \
  --location eastus \
  --admin-user samsadmin \
  --admin-password "YourSecurePassword123!" \
  --sku-name B_Gen5_1

# Create database
az mysql db create \
  --resource-group sams-rg \
  --server-name sams-mysql-server \
  --name sams_db

# Create App Service
az appservice plan create \
  --name sams-plan \
  --resource-group sams-rg \
  --location eastus \
  --sku B1 \
  --is-linux

az webapp create \
  --resource-group sams-rg \
  --plan sams-plan \
  --name sams-backend \
  --runtime "PHP|8.1"
```

### 3. Configure Environment
```bash
# Set environment variables
az webapp config appsettings set \
  --name sams-backend \
  --resource-group sams-rg \
  --setting MYSQL_HOST="sams-mysql-server.mysql.database.azure.com" \
  --setting MYSQL_USER="samsadmin@sams-mysql-server" \
  --setting MYSQL_PASSWORD="YourSecurePassword123!" \
  --setting MYSQL_DATABASE="sams_db" \
  --setting JWT_SECRET="your-super-secret-jwt-key"
```

### 4. Deploy Code
```bash
# Get deployment URL
DEPLOY_URL=$(az webapp deployment source config-local-git-get-url \
  --name sams-backend \
  --resource-group sams-rg \
  --query url -o tsv)

# Add Azure remote
git remote add azure $DEPLOY_URL

# Deploy
git push azure main
```

### 5. Initialize Database
```bash
# Import schema
az mysql db import \
  --resource-group sams-rg \
  --server-name sams-mysql-server \
  --name sams_db \
  --file config/schema.sql
```

## 🐳 Docker Deployment (Alternative)

### Local Development
```bash
# Start with Docker Compose
docker-compose up -d

# Access at http://localhost:8080
```

### Production Container
```bash
# Build and push to Azure Container Registry
az acr create --resource-group sams-rg --name samsacr --sku Basic
az acr build --registry samsacr --image sams-backend:latest .

# Deploy to Azure Container Instances
az container create \
  --resource-group sams-rg \
  --name sams-backend \
  --image samsacr.azurecr.io/sams-backend:latest \
  --cpu 1 --memory 1.5 \
  --registry-login-server samsacr.azurecr.io \
  --registry-username $(az acr credential show -n samsacr --query username -o tsv) \
  --registry-password $(az acr credential show -n samsacr --query passwords[0].value -o tsv) \
  --dns-name-label sams-backend \
  --ports 80
```

## 🔍 Verification

### Automated Test Script (Recommended)
```bash
# Make executable and run
chmod +x test-deployment.sh
./test-deployment.sh https://sams-backend.azurewebsites.net
```

This will automatically test:
- Azure CLI authentication
- HTTP connectivity
- Database connection
- API endpoints
- Basic functionality

### Legacy Verification Script
```bash
./verify-deployment.sh https://sams-backend.azurewebsites.net
```

### Manual Checks
```bash
# Test API endpoints
curl https://sams-backend.azurewebsites.net/api/public/login.php

# Check admin panel
curl https://sams-backend.azurewebsites.net/admin/

# Test database connection
curl https://sams-backend.azurewebsites.net/api/test-db.php
```

## 🔒 Security Configuration

### Database Security
```bash
# Enable SSL enforcement
az mysql server update \
  --resource-group sams-rg \
  --name sams-mysql-server \
  --ssl-enforcement Enabled

# Restrict firewall to App Service only
az mysql server firewall-rule create \
  --resource-group sams-rg \
  --server-name sams-mysql-server \
  --name AllowAppService \
  --start-ip-address <APP_SERVICE_IP> \
  --end-ip-address <APP_SERVICE_IP>
```

### SSL Certificate
```bash
# Add custom domain SSL
az webapp config ssl create \
  --resource-group sams-rg \
  --name sams-backend \
  --certificate-name sams-cert \
  --hostname your-domain.com
```

## 📊 Monitoring & Logging

### Application Insights
```bash
az monitor app-insights component create \
  --app sams-backend \
  --location eastus \
  --resource-group sams-rg \
  --application-type web
```

### Enable Logs
```bash
az webapp log config \
  --name sams-backend \
  --resource-group sams-rg \
  --application-logging true \
  --detailed-error-messages true \
  --failed-request-tracing true \
  --web-server-logging filesystem
```

## 🚀 CI/CD Pipeline

The repository includes GitHub Actions workflow (`.github/workflows/azure-deploy.yml`) for automatic deployment on code changes.

### Setup GitHub Secrets
Add these secrets to your GitHub repository for each environment:
- `AZURE_CREDENTIALS`: Azure service principal credentials
- `AZURE_SUBSCRIPTION_ID`: Azure subscription ID
- `AZURE_SUBSCRIPTION_SUFFIX`: Unique suffix for resource naming (first 8 chars of subscription ID)

### Manual Deployment Trigger
You can manually trigger deployments from GitHub Actions:
1. Go to your repository on GitHub
2. Click "Actions" tab
3. Select "Deploy to Azure" workflow
4. Click "Run workflow"
5. Choose environment (dev/staging/prod)

### Environment-Specific Secrets
For multiple accounts, create separate secrets:
- `AZURE_CREDENTIALS_DEV`
- `AZURE_CREDENTIALS_STAGING`
- `AZURE_CREDENTIALS_PROD`

## 🔐 Azure Account Management

### Switching Between Accounts
```bash
# List all your accounts
az account list --output table

# Set active account
az account set --subscription "your-subscription-id"

# Verify current account
az account show
```

### Service Principal Setup (Recommended for CI/CD)
```bash
# Create service principal for each account
az ad sp create-for-rbac --name "sams-backend-dev" --role contributor \
    --scopes /subscriptions/YOUR_DEV_SUBSCRIPTION_ID \
    --sdk-auth

az ad sp create-for-rbac --name "sams-backend-prod" --role contributor \
    --scopes /subscriptions/YOUR_PROD_SUBSCRIPTION_ID \
    --sdk-auth
```

### Account Configuration File
Edit `azure-accounts.ini` to configure different accounts:
```ini
[dev]
subscription_id = "dev-subscription-id"
resource_group = "sams-dev-rg"

[prod]
subscription_id = "prod-subscription-id"
resource_group = "sams-prod-rg"
```

## 🔐 Azure Account Management

## 💰 Cost Estimation

| Service | SKU | Monthly Cost |
|---------|-----|-------------|
| App Service | B1 | ~$13 |
| Azure Database for MySQL | Basic | ~$25 |
| Storage | LRS | ~$2 |
| **Total** | | **~$40** |

## 🆘 Troubleshooting

### Common Issues

**Database Connection Failed**
- Check environment variables in App Service
- Verify MySQL firewall rules
- Ensure SSL settings are correct

**Deployment Failed**
- Check Azure CLI authentication
- Verify resource group permissions
- Check deployment logs: `az webapp log tail --name sams-backend --resource-group sams-rg`

**Slow Performance**
- Upgrade App Service plan
- Enable Azure CDN
- Optimize database queries

### Logs and Debugging
```bash
# View application logs
az webapp log tail --name sams-backend --resource-group sams-rg

# View deployment logs
az webapp deployment list-publishing-profiles --name sams-backend --resource-group sams-rg

# Check database logs
az mysql server-logs list --resource-group sams-rg --server-name sams-mysql-server
```

## 📞 Support

For issues with this deployment:
1. Check Azure status: https://status.azure.com
2. Review Azure documentation: https://docs.microsoft.com/azure
3. Check application logs in Azure portal
4. Verify configuration files match your environment

## 🎯 Production Checklist

- [ ] Change default database password
- [ ] Enable SSL enforcement on MySQL
- [ ] Configure proper firewall rules
- [ ] Set up Azure Active Directory authentication
- [ ] Enable Azure Application Insights
- [ ] Configure backup and disaster recovery
- [ ] Set up monitoring alerts
- [ ] Configure CORS for frontend domain
- [ ] Update JWT secret key
- [ ] Enable Azure Defender for App Service