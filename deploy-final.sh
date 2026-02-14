#!/bin/bash

# SAMS Backend - Final Deployment Script
# Run this script to deploy the backend to Azure

echo "🚀 Deploying SAMS Backend to Azure..."
echo "====================================="

# Check if we're in the right directory
if [ ! -f ".gitignore" ]; then
    echo "❌ Error: Please run this script from the sams-backend directory"
    exit 1
fi

# Check git status
echo "📋 Checking git status..."
git status --porcelain
if [ $? -ne 0 ]; then
    echo "❌ Git status check failed"
    exit 1
fi

# Deploy to Azure
echo "🚀 Deploying to Azure App Service..."
git push azure main

if [ $? -eq 0 ]; then
    echo "✅ Deployment successful!"
    echo ""
    echo "🌐 Your app should be available at:"
    echo "   https://sams-backend-f4296e92.azurewebsites.net/"
    echo ""
    echo "🔐 Admin Login:"
    echo "   URL: https://sams-backend-f4296e92.azurewebsites.net/admin/"
    echo "   Email: admin@sams.edu"
    echo "   Password: admin123"
    echo ""
    echo "📚 API Endpoints:"
    echo "   Login: https://sams-backend-f4296e92.azurewebsites.net/api/login.php"
    echo "   Dashboard: https://sams-backend-f4296e92.azurewebsites.net/api/dashboard.php"
else
    echo "❌ Deployment failed!"
    exit 1
fi