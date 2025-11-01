#!/bin/bash

# Deployment Script for Quiz Game WebSocket Server
# Run this on your server after uploading files

echo "🚀 Quiz Game Deployment Script"
echo "=============================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root (not recommended, but sometimes needed)
if [ "$EUID" -eq 0 ]; then 
   echo -e "${YELLOW}Warning: Running as root. Consider using a regular user.${NC}"
fi

# Get current directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "📁 Working directory: $SCRIPT_DIR"
echo ""

# Step 1: Check PHP
echo "1️⃣  Checking PHP..."
if ! command -v php &> /dev/null; then
    echo -e "${RED}❌ PHP not found! Please install PHP 7.4+${NC}"
    exit 1
fi
PHP_VERSION=$(php -v | head -n 1)
echo -e "${GREEN}✓${NC} PHP found: $PHP_VERSION"
echo ""

# Step 2: Check Composer
echo "2️⃣  Checking Composer..."
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}⚠️  Composer not found. Installing dependencies manually...${NC}"
else
    echo -e "${GREEN}✓${NC} Composer found"
    echo "📦 Installing dependencies..."
    composer install --no-dev --optimize-autoloader
    echo -e "${GREEN}✓${NC} Dependencies installed"
fi
echo ""

# Step 3: Check database config
echo "3️⃣  Checking database configuration..."
if [ ! -f "config/database.php" ]; then
    echo -e "${RED}❌ config/database.php not found!${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} Database config found"
echo -e "${YELLOW}⚠️  Make sure database credentials are correct!${NC}"
echo ""

# Step 4: Create logs directory
echo "4️⃣  Setting up logs directory..."
mkdir -p logs
touch logs/error.log logs/out.log logs/websocket.log
chmod 755 logs
chmod 644 logs/*.log 2>/dev/null || true
echo -e "${GREEN}✓${NC} Logs directory ready"
echo ""

# Step 5: Test server.php syntax
echo "5️⃣  Testing server.php..."
php -l server.php
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} server.php syntax OK"
else
    echo -e "${RED}❌ server.php has syntax errors!${NC}"
    exit 1
fi
echo ""

# Step 6: Check port availability
echo "6️⃣  Checking port 8080..."
if command -v netstat &> /dev/null; then
    if netstat -tuln 2>/dev/null | grep -q ":8080 "; then
        echo -e "${YELLOW}⚠️  Port 8080 is already in use!${NC}"
        echo "   You may need to change the port in server.php"
    else
        echo -e "${GREEN}✓${NC} Port 8080 is available"
    fi
elif command -v ss &> /dev/null; then
    if ss -tuln 2>/dev/null | grep -q ":8080 "; then
        echo -e "${YELLOW}⚠️  Port 8080 is already in use!${NC}"
    else
        echo -e "${GREEN}✓${NC} Port 8080 is available"
    fi
else
    echo -e "${YELLOW}⚠️  Cannot check port (netstat/ss not available)${NC}"
fi
echo ""

# Step 7: Check PM2
echo "7️⃣  Checking PM2..."
if command -v pm2 &> /dev/null; then
    echo -e "${GREEN}✓${NC} PM2 found"
    echo ""
    echo "Would you like to start the server with PM2? (y/n)"
    read -r response
    if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
        # Update ecosystem.config.js with current path
        sed -i "s|/var/www/html/worshipteam|$SCRIPT_DIR|g" ecosystem.config.js 2>/dev/null || \
        sed -i '' "s|/var/www/html/worshipteam|$SCRIPT_DIR|g" ecosystem.config.js
        
        echo "Starting with PM2..."
        pm2 start ecosystem.config.js
        pm2 save
        echo ""
        echo -e "${GREEN}✅ Server started with PM2!${NC}"
        echo ""
        echo "Useful commands:"
        echo "  pm2 status              - Check status"
        echo "  pm2 logs quiz-websocket - View logs"
        echo "  pm2 restart quiz-websocket - Restart"
    else
        echo -e "${YELLOW}⚠️  Skipping PM2 setup${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  PM2 not found${NC}"
    echo ""
    echo "Options:"
    echo "1. Install PM2: npm install -g pm2"
    echo "2. Use systemd (see DEPLOYMENT.md)"
    echo "3. Use screen: screen -S websocket php server.php"
    echo "4. Use nohup: nohup php server.php > logs/websocket.log 2>&1 &"
fi
echo ""

# Step 8: Final checks
echo "8️⃣  Final checklist:"
echo "   ☐ Database credentials configured in config/database.php"
echo "   ☐ Database schema imported (schema.sql)"
echo "   ☐ Questions imported (sample_questions.sql)"
echo "   ☐ JavaScript files updated with server domain/IP"
echo "   ☐ Port 8080 open in firewall"
echo "   ☐ WebSocket server running (check with: pm2 status or ps aux | grep server.php)"
echo ""

echo -e "${GREEN}🎉 Deployment script complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Update js/host.js and js/player.js with your server URL"
echo "2. Test the connection from a browser"
echo "3. Check logs if there are issues"
echo ""
echo "For detailed instructions, see DEPLOYMENT.md"

