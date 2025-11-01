# 🚀 Server Deployment Guide

This guide explains how to deploy the Quiz Game to a production server and keep the WebSocket server running.

---

## Prerequisites

1. **Server with SSH access**
2. **PHP 7.4+ installed** (check: `php -v`)
3. **Composer installed** (check: `composer -v`)
4. **MySQL/MariaDB database**
5. **Port 8080 available** (or choose another port)

---

## Step 1: Upload Files to Server

Upload all files to your server (except `vendor/` - we'll install that on the server):

```bash
# On your local machine, from the project directory
rsync -avz --exclude 'vendor' \
  /Applications/MAMP/htdocs/worshipteam/ \
  user@your-server.com:/var/www/html/worshipteam/
```

Or use FTP/SFTP to upload:
- All files EXCEPT `vendor/` folder
- `composer.json` and `composer.lock` (to install dependencies)

---

## Step 2: Install Dependencies on Server

SSH into your server:

```bash
ssh user@your-server.com
cd /var/www/html/worshipteam
composer install --no-dev
```

This installs all PHP dependencies needed for the WebSocket server.

---

## Step 3: Configure Database

1. **Update `config/database.php`** with your production database credentials:

```php
<?php
return [
    'host' => 'localhost', // or your DB host
    'port' => 3306, // Standard MySQL port (not 8889 for production)
    'database' => 'worshipteam',
    'username' => 'your_db_user',
    'password' => 'your_db_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

2. **Import database schema**:
```bash
mysql -u your_db_user -p worshipteam < database/schema.sql
mysql -u your_db_user -p worshipteam < database/sample_questions.sql
```

---

## Step 4: Update JavaScript Files for Production

Update the API and WebSocket URLs to use your server's domain/IP:

### `js/host.js` - Line 1-2:
```javascript
const API_URL = 'https://your-domain.com/worshipteam/api';
const WS_URL = 'wss://your-domain.com:8080';
```

### `js/player.js` - Line 1-2:
```javascript
const API_URL = 'https://your-domain.com/worshipteam/api';
const WS_URL = 'wss://your-domain.com:8080';
```

**Important Notes:**
- Use `https://` and `wss://` (secure) if you have SSL
- Use `http://` and `ws://` if no SSL
- Replace `your-domain.com` with your actual domain or IP
- Port 8080 must be open in your firewall

---

## Step 5: Running WebSocket Server on Server

### Option A: Using PM2 (Recommended - Best for Production)

**Install PM2:**
```bash
npm install -g pm2
```

**Create PM2 configuration file** `ecosystem.config.js`:
```javascript
module.exports = {
  apps: [{
    name: 'quiz-websocket',
    script: 'php',
    args: 'server.php',
    cwd: '/var/www/html/worshipteam',
    interpreter: 'php',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    error_file: './logs/error.log',
    out_file: './logs/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    merge_logs: true
  }]
};
```

**Start with PM2:**
```bash
cd /var/www/html/worshipteam
pm2 start ecosystem.config.js
pm2 save
pm2 startup  # Run this to auto-start on server boot
```

**PM2 Commands:**
```bash
pm2 status              # Check if running
pm2 logs quiz-websocket # View logs
pm2 restart quiz-websocket # Restart
pm2 stop quiz-websocket    # Stop
```

---

### Option B: Using systemd (Linux - Service)

**Create service file** `/etc/systemd/system/quiz-websocket.service`:

```ini
[Unit]
Description=Quiz Game WebSocket Server
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/worshipteam
ExecStart=/usr/bin/php /var/www/html/worshipteam/server.php
Restart=always
RestartSec=10
StandardOutput=append:/var/www/html/worshipteam/logs/websocket.log
StandardError=append:/var/www/html/worshipteam/logs/websocket-error.log

[Install]
WantedBy=multi-user.target
```

**Enable and start:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable quiz-websocket
sudo systemctl start quiz-websocket
sudo systemctl status quiz-websocket
```

---

### Option C: Using screen (Simple - Good for Testing)

```bash
# Install screen if needed
sudo apt-get install screen  # Debian/Ubuntu
# or
sudo yum install screen      # CentOS/RHEL

# Start server in screen
cd /var/www/html/worshipteam
screen -S websocket
php server.php

# Detach: Press Ctrl+A, then D
# Reattach: screen -r websocket
```

---

### Option D: Using nohup (Basic Background)

```bash
cd /var/www/html/worshipteam
nohup php server.php > logs/websocket.log 2>&1 &

# Check if running
ps aux | grep server.php

# Stop
pkill -f server.php
```

---

## Step 6: Configure Firewall

**Open port 8080** (or your chosen port):

```bash
# Ubuntu/Debian (UFW)
sudo ufw allow 8080/tcp

# CentOS/RHEL (firewalld)
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload

# Or edit iptables directly
sudo iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
```

---

## Step 7: Test the Setup

1. **Check WebSocket server is running:**
```bash
netstat -tuln | grep 8080
# Should show: tcp 0 0 0.0.0.0:8080 LISTEN
```

2. **Test from browser:**
   - Open: `https://your-domain.com/worshipteam/host.html`
   - Open browser console (F12)
   - Should see: "Connected to WebSocket server"

3. **Check server logs:**
```bash
# PM2
pm2 logs quiz-websocket

# systemd
sudo journalctl -u quiz-websocket -f

# screen/nohup
tail -f logs/websocket.log
```

---

## Step 8: Using Different Port (If 8080 is Unavailable)

### Change Port in Code:

**1. `server.php`** - Change port:
```php
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new QuizGameServer()
        )
    ),
    8443  // New port
);
```

**2. Update JavaScript files:**
```javascript
const WS_URL = 'wss://your-domain.com:8443';
```

**3. Open new port in firewall** (same as Step 6)

---

## Step 9: Using Nginx Reverse Proxy (Recommended for Production)

This allows using standard ports (80/443) and SSL:

**Nginx configuration** `/etc/nginx/sites-available/quiz-websocket`:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 80;
    server_name your-domain.com;

    # WebSocket upgrade
    location /ws {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }

    # Regular HTTP
    location / {
        root /var/www/html;
        index index.html index.php;
        try_files $uri $uri/ =404;
    }
}
```

Then update JavaScript:
```javascript
const WS_URL = 'wss://your-domain.com/ws';
```

---

## Troubleshooting

### WebSocket connection fails:

1. **Check server is running:**
   ```bash
   ps aux | grep server.php
   ```

2. **Check port is open:**
   ```bash
   netstat -tuln | grep 8080
   ```

3. **Check firewall:**
   ```bash
   sudo ufw status
   # or
   sudo firewall-cmd --list-ports
   ```

4. **Check server logs** for errors

### Connection closes immediately:

1. Check PHP error logs
2. Check database connection
3. Verify all dependencies installed (`composer install`)
4. Check file permissions

### Server stops after closing terminal:

- Use PM2 or systemd (they run as daemons)
- Don't use direct `php server.php` (will stop when terminal closes)

---

## Security Considerations

1. **Use HTTPS/WSS** if possible (SSL certificate)
2. **Limit database user permissions** (read/write only to quiz database)
3. **Use strong database passwords**
4. **Restrict port 8080** to specific IPs if possible
5. **Regular updates** of PHP and dependencies

---

## Maintenance Commands

```bash
# View logs
pm2 logs quiz-websocket

# Restart server (after code changes)
pm2 restart quiz-websocket

# Check server status
pm2 status

# Update dependencies
cd /var/www/html/worshipteam
composer update --no-dev

# Update database
mysql -u user -p database < database/new_migration.sql
```

---

## Quick Reference

**Start Server (PM2):**
```bash
cd /var/www/html/worshipteam
pm2 start ecosystem.config.js
```

**Check Status:**
```bash
pm2 status
netstat -tuln | grep 8080
```

**View Logs:**
```bash
pm2 logs quiz-websocket
```

**Restart:**
```bash
pm2 restart quiz-websocket
```

---

**Need help?** Check server logs and browser console for specific error messages.

