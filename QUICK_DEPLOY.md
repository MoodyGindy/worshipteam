# 🚀 Quick Start: Running WebSocket Server on kdsc.fun

## Your Configuration
- **Domain:** `https://kdsc.fun`
- **API URL:** `https://kdsc.fun/worshipteam/worshipteam/api`
- **WebSocket URL:** `wss://kdsc.fun:8080`

---

## Step 1: SSH into Your Server

```bash
ssh user@kdsc.fun
# or
ssh user@your-server-ip
```

---

## Step 2: Navigate to Your Project Directory

```bash
cd /path/to/worshipteam
# This might be: /var/www/html/worshipteam or /home/user/public_html/worshipteam
# Check with your hosting provider
```

---

## Step 3: Install Dependencies (if not done)

```bash
composer install --no-dev
```

---

## Step 4: Choose How to Run the Server

You have **3 options**. Pick ONE:

### Option A: Using PM2 (Recommended - Best for Production)

**Install PM2:**
```bash
npm install -g pm2
```

**Start the server:**
```bash
cd /path/to/worshipteam
pm2 start ecosystem.config.js
pm2 save
pm2 startup  # Follow instructions to auto-start on server reboot
```

**Check status:**
```bash
pm2 status
pm2 logs
```

**Stop server:**
```bash
pm2 stop ecosystem.config.js
```

---

### Option B: Using screen (Simple - Good for Testing)

```bash
cd /path/to/worshipteam
screen -S websocket
php server.php
# Press Ctrl+A then D to detach
```

**Reconnect later:**
```bash
screen -r websocket
```

---

### Option C: Using nohup (Simple Background Process)

```bash
cd /path/to/worshipteam
mkdir -p logs
nohup php server.php > logs/websocket.log 2>&1 &
```

**Check if running:**
```bash
ps aux | grep server.php
```

**Stop it:**
```bash
# Find the process ID (PID) from above command
kill [PID]
```

---

## Step 5: Open Firewall Port 8080

**If using UFW (Ubuntu/Debian):**
```bash
sudo ufw allow 8080/tcp
sudo ufw reload
```

**If using iptables:**
```bash
sudo iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
```

**If using cPanel/Shared Hosting:**
- Go to cPanel → Security → Configure Firewall
- Add port 8080 to allowed ports

**Check if port is open:**
```bash
netstat -tuln | grep 8080
# or
ss -tuln | grep 8080
```

---

## Step 6: Test the Server

1. **Check if server is running:**
   ```bash
   # If using PM2
   pm2 status
   
   # If using screen
   screen -r websocket
   
   # If using nohup
   ps aux | grep server.php
   ```

2. **Test from browser:**
   - Open: `https://kdsc.fun/worshipteam/worshipteam/host.html`
   - Open browser console (F12)
   - Should see: "Connected to WebSocket server"

3. **Test WebSocket connection directly:**
   ```bash
   # On server or local machine
   curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Key: test" -H "Sec-WebSocket-Version: 13" https://kdsc.fun:8080
   ```

---

## Important Notes

1. **SSL Certificate for WebSocket:**
   - Since your site uses HTTPS, you need WSS (secure WebSocket)
   - Port 8080 must have SSL certificate OR use reverse proxy (see below)

2. **Alternative: Use Nginx Reverse Proxy (Recommended for Production)**
   
   If you want to use standard HTTPS port (443) instead of 8080:
   
   Add to your Nginx config (`/etc/nginx/sites-available/default` or similar):
   ```nginx
   location /ws {
       proxy_pass http://127.0.0.1:8080;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```
   
   Then update `js/host.js` and `js/player.js`:
   ```javascript
   const WS_URL = 'wss://kdsc.fun/ws';  // No port needed!
   ```
   
   Reload Nginx:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

---

## Troubleshooting

**Server won't start:**
```bash
# Check PHP version (need 7.4+)
php -v

# Check if port 8080 is already in use
lsof -i :8080
# or
netstat -tuln | grep 8080

# Kill process using port 8080
kill -9 [PID]
```

**Connection refused:**
- Check firewall allows port 8080
- Check server is running: `pm2 status` or `ps aux | grep server.php`
- Check server logs: `pm2 logs` or `tail -f logs/websocket.log`

**SSL/Certificate errors:**
- Use reverse proxy method (Option above) OR
- Get SSL certificate for port 8080 OR
- Use `ws://` instead of `wss://` (not recommended for HTTPS sites)

---

## Auto-Start on Server Reboot

**With PM2 (already configured):**
```bash
pm2 startup
# Follow the instructions shown
pm2 save
```

**With systemd:**
See `DEPLOYMENT.md` for systemd service configuration.

---

## Quick Commands Reference

```bash
# Start (PM2)
pm2 start ecosystem.config.js

# Stop (PM2)
pm2 stop ecosystem.config.js

# Restart (PM2)
pm2 restart ecosystem.config.js

# View logs (PM2)
pm2 logs

# Check status (PM2)
pm2 status

# Start (screen)
screen -S websocket
php server.php
# Ctrl+A, then D to detach

# Start (nohup)
nohup php server.php > logs/websocket.log 2>&1 &
```

---

## Need Help?

Check the server logs:
- PM2: `pm2 logs`
- Screen: `screen -r websocket`
- Nohup: `tail -f logs/websocket.log`

Check browser console (F12) for client-side errors.

