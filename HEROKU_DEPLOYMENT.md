# SAMS Backend - Heroku Deployment Guide

Complete guide to deploy SAMS Backend to Heroku with MySQL database and full configuration.

## 📋 Prerequisites

Before deploying to Heroku, ensure you have:

### 1. Heroku Account
- Sign up at https://www.heroku.com/
- Free tier available (limitations apply)

### 2. Install Heroku CLI
```bash
# macOS
brew tap heroku/brew && brew install heroku

# Linux
curl https://cli-assets.heroku.com/install.sh | sh

# Windows
Download from https://cli-assets.heroku.com/heroku-x64.exe
```

### 3. Required Tools
```bash
# Verify installations
heroku --version
git --version
php --version
mysql --version  # For testing
```

---

## 🚀 Quick Deployment

### Automated Deployment (Recommended)

```bash
# Make script executable
chmod +x deploy-heroku.sh

# Run deployment
./deploy-heroku.sh
```

This will automatically:
- ✅ Check prerequisites
- ✅ Create Heroku app
- ✅ Provision MySQL database
- ✅ Set environment variables
- ✅ Deploy code via Git
- ✅ Initialize database schema
- ✅ Open app in browser

### Expected Output
```
✓ Heroku CLI installed
✓ Git installed
✓ Logged in to Heroku
✓ Heroku app created
✓ MySQL database attached
✓ Database URL retrieved
✓ Environment variables set
✓ Code deployed
✓ Database initialized

🎉 DEPLOYMENT SUCCESSFUL!

App URL: https://sams-backend-xxxxx.herokuapp.com
```

---

## 📖 Manual Deployment Steps

### Step 1: Login to Heroku

```bash
heroku login
```

This opens browser for authentication.

### Step 2: Create Heroku App

```bash
# Create app with unique name
heroku create sams-backend-prod

# Or let Heroku generate unique name
heroku create
```

**Output:**
```
Creating app... done, ⬢ sams-backend-prod
https://sams-backend-prod.herokuapp.com/ | https://git.heroku.com/sams-backend-prod.git
```

### Step 3: Provision MySQL Database

```bash
# Add ClearDB MySQL (free plan available)
heroku addons:create cleardb:ignite

# Verify addon
heroku config | grep CLEARDB_DATABASE_URL
```

**Available Plans:**
- **ignite** (free): Good for development
- **punch** ($9/month): For small production
- **kickstart** ($20/month): For larger apps

### Step 4: Configure Environment Variables

```bash
# Set database config (auto-configured by addon)
# But you can also manually set:

heroku config:set \
  MYSQL_HOST='hostname' \
  MYSQL_USER='username' \
  MYSQL_PASSWORD='password' \
  MYSQL_DATABASE='dbname' \
  MYSQL_PORT='3306'

# Set application config
heroku config:set \
  JWT_SECRET='your-super-secret-key-32-chars-minimum' \
  APP_ENV='production' \
  APP_DEBUG='false' \
  APP_URL='https://sams-backend-prod.herokuapp.com' \
  LOG_LEVEL='error'

# Set Firebase config (if using push notifications)
heroku config:set \
  FIREBASE_SERVER_KEY='your-firebase-key' \
  FIREBASE_PROJECT_ID='your-firebase-project'

# View all config
heroku config
```

### Step 5: Deploy Code

```bash
# Add git remote for Heroku (auto-done by create)
git remote -v

# Ensure you're on main branch
git checkout main

# Deploy to Heroku
git push heroku main
```

**Deploy from Different Branch:**
```bash
git push heroku dev:main  # Push dev branch as main
```

### Step 6: Initialize Database

```bash
# Run schema import
heroku run 'mysql -h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE < config/schema.sql'

# Or create database manually
heroku run 'php -r "
  require \"config/database.php\";
  \$db = new Database();
  \$conn = \$db->getConnection();
  echo \"Database connected successfully\";
"'
```

### Step 7: Verify Deployment

```bash
# Check app status
heroku ps

# View logs
heroku logs --tail

# Test health endpoint
curl https://sams-backend-prod.herokuapp.com/api/health-check.php

# Open in browser
heroku open
```

---

## 🔧 Configuration Guide

### Database Configuration

The Heroku MySQL addon provides `CLEARDB_DATABASE_URL` in format:
```
mysql://user:password@host/database
```

**Parse it in your code:**
```php
$url = parse_url(getenv('CLEARDB_DATABASE_URL'));
$db_host = $url['host'];
$db_user = $url['user'];
$db_pass = $url['pass'];
$db_name = trim($url['path'], '/');
```

### Environment Variables

**Required:**
```env
MYSQL_HOST=xxxxx.mysql.us-east-1.cleardb.com
MYSQL_USER=xxxxx
MYSQL_PASSWORD=xxxxx
MYSQL_DATABASE=xxxxx
JWT_SECRET=your-secret-key-32-chars-min
APP_ENV=production
APP_URL=https://your-app.herokuapp.com
```

**Optional:**
```env
FIREBASE_SERVER_KEY=your-firebase-key
CORS_ALLOWED_ORIGINS=https://frontend.com,https://app.com
LOG_LEVEL=error
MAX_FILE_SIZE=10485760
FACE_CONFIDENCE_THRESHOLD=75
```

### Procfile

Already configured in `/Procfile`:
```procfile
web: heroku-php-apache2 public/
```

**Other options:**
```procfile
# Run with custom PHP settings
web: heroku-php-apache2 -C nginx.conf public/

# Use Nginx
web: vendor/bin/heroku-php-nginx -C nginx.conf public/
```

---

## 📊 Deployment Verification

### API Health Check
```bash
curl https://your-app.herokuapp.com/api/health-check.php
```

Expected response:
```json
{
  "status": "healthy",
  "timestamp": "2026-03-01T12:00:00Z",
  "database": "connected"
}
```

### Test Database Connection
```bash
curl https://your-app.herokuapp.com/api/test-db.php
```

### View Recent Logs
```bash
heroku logs --tail --app your-app-name
```

### Access Remote Console (if enabled)
```bash
heroku ps:exec --app your-app-name
```

---

## 🚨 Common Issues & Solutions

### Issue: "Postgres connection failed"
**Solution:** You created PostgreSQL but selected MySQL in code
```bash
# Delete wrong addon
heroku addons:destroy heroku-postgresql

# Create correct MySQL addon
heroku addons:create cleardb:ignite
```

### Issue: "No such file or directory: config/schema.sql"
**Solution:** Ensure file exists in repository
```bash
git add config/schema.sql
git commit -m "Add database schema"
git push heroku main
```

### Issue: "PHP Fatal error: Call to undefined function..."
**Solution:** Missing dependencies
```bash
# Check composer.json includes all packages
composer install
git add composer.lock
git commit -m "Update dependencies"
git push heroku main
```

### Issue: "SQLSTATE[HY000]: General error"
**Solution:** Connection string issue
```bash
# Verify database is attached
heroku addons

# Check connection string
heroku config | grep CLEARDB
```

### Issue: "Request timeout (30s)"
**Solution:** Database query too slow
```bash
# Scale dyno to higher tier
heroku dyno:type web=hobby

# Add database indexes
heroku run 'mysql ... < config/schema-indexes.sql'
```

### Issue: Build fails - "PHP version"
**Solution:** Update composer.json
```json
{
  "require": {
    "php": ">=8.0"
  }
}
```

---

## 🔐 Security Best Practices

### 1. Environment Variables
```bash
# Never commit sensitive data
echo ".env" >> .gitignore

# Set all secrets in Heroku
heroku config:set SECRET_KEY=$(openssl rand -base64 32)
```

### 2. Database Security
```bash
# Use strong password
heroku config:set MYSQL_PASSWORD=$(openssl rand -base64 20)

# Restrict remote connections (ClearDB does this automatically)

# Regular backups
heroku pg:backups:capture --app your-app
```

### 3. API Security
```bash
# Enable CORS properly
heroku config:set CORS_ALLOWED_ORIGINS=https://yourdomain.com

# Set JWT expiration
heroku config:set JWT_EXPIRY=3600

# Limit request rate
# Configure in .htaccess or middleware
```

### 4. HTTPS Enforcement
```bash
# Heroku provides free HTTPS
# Enforce in code:

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

---

## 📈 Scaling & Performance

### Dyno Types & Pricing
```
Free     - $0/month     (sleeps after 30 min inactivity)
Eco      - $5/month     (always on)
Standard - $7/month     (better performance)
Premium  - $25-50/month (high performance)
```

### Scale Dynos
```bash
# View current dyno
heroku ps

# Upgrade dyno
heroku dyno:type web=standard-1x

# Scale multiple dynos
heroku ps:scale web=2

# View dyno status
heroku ps
```

### Database Optimization
```bash
# Check database size
heroku db:info

# View slow queries
heroku addons:open cleardb

# Add indexes (if custom)
heroku run 'mysql ... < optimize.sql'
```

---

## 🔄 Continuous Integration / Deployment

### Auto-Deploy from GitHub

1. **Connect GitHub:**
```bash
heroku apps:info

# Or through dashboard: Settings → Build packs → GitHub
```

2. **Enable Auto Deploy:**
- Go to Heroku Dashboard → App → Deploy
- Connect GitHub repository
- Enable "Automatic deploys from main branch"

3. **Run tests before deploy:**
- Configure GitHub Actions
- Set branch protection rules
- Require passing tests

### GitHub Actions Example
```yaml
name: Deploy to Heroku

on:
  push:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run tests
        run: |
          composer test
          php -l public/api/**/*.php

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
        with:
          fetch-depth: 0
      - name: Deploy to Heroku
        uses: akhileshns/heroku-deploy@v3.12.12
        with:
          heroku_api_key: ${{ secrets.HEROKU_API_KEY }}
          heroku_app_name: ${{ secrets.HEROKU_APP_NAME }}
          heroku_email: ${{ secrets.HEROKU_EMAIL }}
```

---

## 📝 Monitoring & Logs

### View Logs
```bash
# Real-time logs
heroku logs --tail

# Last 100 lines
heroku logs -n 100

# Filter by process type
heroku logs --dyno=web

# Show logs from 1 hour ago
heroku logs --since 1h
```

### Setup Log Draining (Optional)
```bash
# Send logs to external service
heroku drains:add https://your-log-service/endpoint --app your-app
```

### Error Tracking
```bash
# Use Heroku's built-in error monitoring
# Or integrate Sentry:
heroku config:set SENTRY_DSN=your-sentry-url
```

---

## 🧹 Cleanup & Destruction

### Delete App
```bash
# Destroy app (WARNING: irreversible)
heroku apps:destroy --app sams-backend-prod --confirm sams-backend-prod
```

### Remove Addons
```bash
# List addons
heroku addons

# Remove addon
heroku addons:destroy cleardb:ignite
```

---

## 📚 Useful Commands

```bash
# App Management
heroku apps                    # List all apps
heroku apps:info              # Get app details
heroku open                    # Open app in browser
heroku ps                      # View dynos

# Configuration
heroku config                  # View env vars
heroku config:set KEY=value    # Set env var
heroku config:unset KEY        # Remove env var

# Deployment
heroku git:remote --app name   # Add git remote
git push heroku main           # Deploy
heroku releases                # View release history
heroku releases:rollback       # Rollback deployment

# Database
heroku pg:info                 # Database info
heroku db:psql                 # Database shell
heroku db:reset                # Reset database

# Logs & Monitoring
heroku logs --tail             # View logs
heroku ps:exec                 # Interactive shell
heroku run php -v              # Run command

# Scaling
heroku dyno:type web=hobby     # Change dyno
heroku ps:scale web=3          # Scale dynos
```

---

## 🎯 Production Checklist

Before going live, ensure:

- [ ] Environment variables all set securely
- [ ] Database schema imported and tested
- [ ] API health checks passing
- [ ] CORS configured properly
- [ ] SSL/HTTPS working
- [ ] Firebase keys configured (if using notifications)
- [ ] Logs redirected to proper service
- [ ] Backups configured
- [ ] Monitoring/alerting set up
- [ ] Custom domain configured
- [ ] Load balanced (if needed)
- [ ] Error tracking integrated
- [ ] Rate limiting enabled
- [ ] Firewall rules configured

---

## 📞 Support & Resources

- **Heroku Docs:** https://devcenter.heroku.com/
- **PHP Support:** https://devcenter.heroku.com/articles/php-support
- **Add-ons:** https://elements.heroku.com/addons
- **Community:** https://help.heroku.com/
- **Status Page:** https://status.heroku.com/

---

**Last Updated:** March 1, 2026  
**Tested With:** Heroku, PHP 8.x, MySQL 8.0, ClearDB

