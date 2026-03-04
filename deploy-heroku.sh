#!/bin/bash

##############################################################
# SAMS Backend - Heroku Deployment Script
# Deploys SAMS Backend to Heroku with MySQL database
# Usage: ./deploy-heroku.sh
##############################################################

set -e  # Exit on error

# Colors for output
BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="sams-backend-$(date +%s | tail -c 6)"  # Unique app name
HEROKU_REGION="us"  # us or eu
DATABASE_ADDON="jawsdb:kitefin"  # MySQL addon plan (JawsDB)

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     SAMS Backend - Heroku Deployment                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}\n"

# Check prerequisites
echo -e "${YELLOW}📋 Checking Prerequisites...${NC}"

if ! command -v heroku &> /dev/null; then
    echo -e "${RED}❌ Heroku CLI not found. Please install it:${NC}"
    echo "   macOS: brew tap heroku/brew && brew install heroku"
    echo "   Linux: curl https://cli-assets.heroku.com/install.sh | sh"
    exit 1
fi
echo -e "${GREEN}✓ Heroku CLI installed${NC}"

if ! command -v git &> /dev/null; then
    echo -e "${RED}❌ Git not found. Please install Git.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Git installed${NC}"

# Check Heroku login
if ! heroku auth:whoami &> /dev/null; then
    echo -e "${YELLOW}Not logged in to Heroku. Logging in...${NC}"
    heroku login
fi
echo -e "${GREEN}✓ Logged in to Heroku${NC}\n"

# Create Heroku app
echo -e "${YELLOW}🚀 Creating Heroku App: ${APP_NAME}${NC}"
heroku create $APP_NAME --region $HEROKU_REGION
echo -e "${GREEN}✓ Heroku app created${NC}\n"

# Remove conflicting git remote if it exists
git remote remove heroku 2>/dev/null || true

# Add git remote for this specific app
git remote add heroku https://git.heroku.com/${APP_NAME}.git
echo -e "${GREEN}✓ Git remote added${NC}\n"

# Add MySQL database
echo -e "${YELLOW}📦 Attaching MySQL Database...${NC}"
heroku addons:create $DATABASE_ADDON --app $APP_NAME
echo -e "${GREEN}✓ MySQL database attached${NC}\n"

# Get database URL
echo -e "${YELLOW}🔗 Retrieving Database Configuration...${NC}"
DATABASE_URL=$(heroku config:get JAWSDB_URL --app $APP_NAME)

# Fallback to other possible environment variable names
if [ -z "$DATABASE_URL" ]; then
    DATABASE_URL=$(heroku config:get DATABASE_URL --app $APP_NAME)
fi

if [ -z "$DATABASE_URL" ]; then
    echo -e "${RED}❌ Could not retrieve database URL. Waiting 10 seconds and retrying...${NC}"
    sleep 10
    DATABASE_URL=$(heroku config:get JAWSDB_URL --app $APP_NAME)
fi

echo -e "${GREEN}✓ Database URL: ${DATABASE_URL:0:50}...${NC}\n"

# Parse database URL
if [[ $DATABASE_URL =~ mysql://([^:]+):([^@]+)@([^/]+)/(.+) ]]; then
    DB_USER="${BASH_REMATCH[1]}"
    DB_PASS="${BASH_REMATCH[2]}"
    DB_HOST="${BASH_REMATCH[3]}"
    DB_NAME="${BASH_REMATCH[4]}"
    
    echo -e "${YELLOW}Database Configuration:${NC}"
    echo "  Host: $DB_HOST"
    echo "  User: $DB_USER"
    echo "  Database: $DB_NAME\n"
fi

# Set environment variables
echo -e "${YELLOW}⚙️  Setting Environment Variables...${NC}"

# Generate secure keys
JWT_SECRET=$(openssl rand -base64 32)
APP_URL="https://${APP_NAME}.herokuapp.com"

heroku config:set \
  MYSQL_HOST="$DB_HOST" \
  MYSQL_USER="$DB_USER" \
  MYSQL_PASSWORD="$DB_PASS" \
  MYSQL_DATABASE="$DB_NAME" \
  MYSQL_PORT="3306" \
  JWT_SECRET="$JWT_SECRET" \
  APP_ENV="production" \
  APP_DEBUG="false" \
  APP_URL="$APP_URL" \
  LOG_LEVEL="error" \
  SESSION_LIFETIME="604800" \
  --app $APP_NAME

echo -e "${GREEN}✓ Environment variables set${NC}\n"

# Prepare git for deployment
echo -e "${YELLOW}📝 Preparing Git Repository...${NC}"
git add .
git commit -m "Deploy to Heroku: $APP_NAME" || true  # Allow no changes
echo -e "${GREEN}✓ Repository ready${NC}\n"

# Deploy to Heroku
echo -e "${YELLOW}🚀 Deploying to Heroku...${NC}"
git push heroku main || git push heroku master
echo -e "${GREEN}✓ Code deployed${NC}\n"

# Initialize database
echo -e "${YELLOW}🗄️  Initializing Database...${NC}"

# Create a temporary PHP script to initialize the database
cat > /tmp/init-db.php << 'EOF'
<?php
$url = parse_url(getenv('JAWSDB_URL') ?: getenv('DATABASE_URL'));
$db_host = $url['host'];
$db_user = $url['user'];
$db_pass = $url['pass'];
$db_name = ltrim($url['path'], '/');

echo "Connecting to {$db_host}/{$db_name}...\n";

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Connected successfully\n";
    
    // Read schema file
    $schema = file_get_contents('config/schema.sql');
    if ($schema) {
        echo "Executing schema...\n";
        // Split by ; and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        echo "✓ Database schema imported successfully\n";
    }
} catch (Exception $e) {
    echo "⚠ Database initialization: {$e->getMessage()}\n";
    echo "You may need to initialize the database manually:\n";
    echo "  heroku run 'php -r \"require config/database.php;\"' --app $APP_NAME\n";
}
EOF

# Try to run initialization via Heroku
echo "Attempting database initialization..."
if heroku run "php /tmp/init-db.php" --app $APP_NAME 2>/dev/null; then
    echo -e "${GREEN}✓ Database initialized${NC}\n"
else
    echo -e "${YELLOW}⚠ Database initialization deferred - you can initialize manually:${NC}"
    echo "  heroku run 'php apply-schema.php' --app $APP_NAME"
    echo -e "  or through phpMyAdmin at your database provider's dashboard\n"
fi

# Open app in browser
echo -e "${YELLOW}🌐 Opening Application in Browser...${NC}"
echo -e "${GREEN}✓ App URL: $APP_URL${NC}\n"
heroku open --app $APP_NAME

# Display deployment summary
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          🎉 DEPLOYMENT SUCCESSFUL! 🎉                    ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}\n"

echo -e "📊 Deployment Summary:"
echo -e "  ${BLUE}App Name:${NC}        $APP_NAME"
echo -e "  ${BLUE}App URL:${NC}         $APP_URL"
echo -e "  ${BLUE}Database:${NC}        $DB_HOST:$DB_NAME"
echo -e "  ${BLUE}Region:${NC}          $HEROKU_REGION"
echo -e "  ${BLUE}Plan:${NC}            Free/Eco (upgrade for production)\n"

echo -e "📝 Next Steps:"
echo -e "  1. Test the API: curl $APP_URL/api/health-check.php"
echo -e "  2. Create admin user: heroku run 'php setup-database.php' --app $APP_NAME"
echo -e "  3. Configure Firebase Server Key: heroku config:set FIREBASE_SERVER_KEY=your-key"
echo -e "  4. Monitor logs: heroku logs --tail --app $APP_NAME"
echo -e "  5. Set custom domain: heroku domains:add yourdomain.com --app $APP_NAME"
echo -e "  6. Set up auto-deployment from GitHub\n"

echo -e "📋 Updated Features:"
echo -e "  ✓ Session timeout: 7 days (604800 seconds)"
echo -e "  ✓ Improved error handling for schedule queries"
echo -e "  ✓ Better teacher authorization checks\n"

echo -e "🔧 Useful Commands:"
echo -e "  View logs:          heroku logs --tail --app $APP_NAME"
echo -e "  Run command:        heroku run 'php command.php' --app $APP_NAME"
echo -e "  Access database:    heroku db:psql --app $APP_NAME"
echo -e "  Scale dynos:        heroku dyno:scale web=1 --app $APP_NAME"
echo -e "  View env vars:      heroku config --app $APP_NAME"
echo -e "  Upgrade plan:       heroku dyno:type web=hobby --app $APP_NAME\n"

echo -e "✅ Deployment complete!\n"
