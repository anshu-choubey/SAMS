#!/bin/bash

# Heroku Deployment Script for SAMS Backend
# This script deploys the PHP backend to Heroku

set -e

echo "🚀 Starting Heroku deployment for SAMS Backend..."

# Check if Heroku CLI is installed
if ! command -v heroku &> /dev/null; then
    echo "❌ Heroku CLI is not installed. Please install it first."
    echo "Visit: https://devcenter.heroku.com/articles/heroku-cli"
    exit 1
fi

# Check if logged in to Heroku
if ! heroku auth:whoami &> /dev/null; then
    echo "🔐 Please login to Heroku:"
    heroku login
fi

# App name (you can change this)
APP_NAME="sams-backend-$(date +%s)"

echo "📦 Creating Heroku app: $APP_NAME"
heroku create $APP_NAME --region us

echo "🗄️ Adding ClearDB MySQL add-on..."
heroku addons:create cleardb:ignite --app $APP_NAME

echo "🔧 Setting up environment variables..."
# Get the ClearDB connection URL
DATABASE_URL=$(heroku config:get DATABASE_URL --app $APP_NAME)
echo "Database URL: $DATABASE_URL"

# Set any additional config vars if needed
heroku config:set APP_ENV=production --app $APP_NAME

echo "📊 Initializing database..."
# Extract database credentials from DATABASE_URL
# DATABASE_URL format: mysql://user:pass@host/db?reconnect=true
DB_HOST=$(echo $DATABASE_URL | sed 's/mysql:\/\/.*@\(.*\)\/.*$/\1/')
DB_USER=$(echo $DATABASE_URL | sed 's/mysql:\/\/\(.*\):.*@.*$/\1/')
DB_PASS=$(echo $DATABASE_URL | sed 's/mysql:\/\/.*:\(.*\)@.*$/\1/')
DB_NAME=$(echo $DATABASE_URL | sed 's/mysql:\/\/.*@.*\/\(.*\)?reconnect=true$/\1/')

echo "Database Host: $DB_HOST"
echo "Database Name: $DB_NAME"

# Initialize database schema
echo "Creating database schema..."
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME < config/schema.sql

echo "🚀 Deploying code to Heroku..."
git push heroku main

echo "✅ Deployment completed!"
echo "🌐 Your app is available at: https://$APP_NAME.herokuapp.com"

echo "📋 Next steps:"
echo "1. Test the application"
echo "2. Configure domain if needed"
echo "3. Monitor logs with: heroku logs --tail --app $APP_NAME"