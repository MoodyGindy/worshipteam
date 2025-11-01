# 🔌 WebSocket Server Setup Guide

## Quick Start

### Method 1: Using the Helper Script (Recommended)

1. Open **Terminal** (Applications → Utilities → Terminal)

2. Navigate to the project directory:
   ```bash
   cd /Applications/MAMP/htdocs/worshipteam
   ```

3. Run the startup script:
   ```bash
   ./start_server.sh
   ```

4. You should see:
   ```
   🎮 Starting Quiz Game WebSocket Server...
   WebSocket server started on port 8080
   ```

5. **Keep this terminal window open!** The server must run continuously.

---

### Method 2: Manual Start

1. Open **Terminal**

2. Navigate to the project:
   ```bash
   cd /Applications/MAMP/htdocs/worshipteam
   ```

3. Start the server:
   ```bash
   php server.php
   ```
   
   Or if using MAMP's PHP:
   ```bash
   /Applications/MAMP/bin/php/php8.2.0/bin/php server.php
   ```
   
   (Adjust version number if different: php8.1.0, php8.0.0, etc.)

4. You should see:
   ```
   WebSocket server started on port 8080
   ```

---

## Prerequisites

✅ **Before starting the WebSocket server, make sure:**

1. **MAMP is running** (Apache + MySQL should be green)
   - Check MAMP control panel
   - MySQL must be running for database connections

2. **Database is set up**
   - Database `team` exists
   - Tables are created (run `schema.sql` if not done)
   - Questions are loaded (run `sample_questions.sql`)

3. **Dependencies are installed**
   ```bash
   composer install
   ```

---

## Verifying It's Working

1. After starting the server, you should see:
   ```
   WebSocket server started on port 8080
   ```

2. **Test the connection:**
   - Open your browser to: `http://localhost:8888/worshipteam/host.html`
   - Open browser Developer Tools (F12 or Cmd+Option+I)
   - Go to Console tab
   - If you see "Connected to WebSocket server" → ✅ Success!
   - If you see connection errors → Check the steps below

---

## Troubleshooting

### ❌ "Port 8080 is already in use"

**Solution:**
1. Find what's using port 8080:
   ```bash
   lsof -i :8080
   ```

2. Kill the process (replace `PID` with the actual process ID):
   ```bash
   kill -9 PID
   ```

3. Or change the port in `server.php`:
   ```php
   $server = IoServer::factory(
       new HttpServer(
           new WsServer(
               new QuizGameServer()
           )
       ),
       8081  // Change to 8081 or another port
   );
   ```

4. Then update `js/host.js` and `js/player.js`:
   ```javascript
   const WS_URL = 'ws://localhost:8081';  // Match the new port
   ```

---

### ❌ "Class 'QuizGame\QuizGameServer' not found"

**Solution:**
Run composer install:
```bash
cd /Applications/MAMP/htdocs/worshipteam
composer install
```

---

### ❌ "Connection refused" or WebSocket fails to connect

**Check:**

1. **Is the server running?**
   - The terminal window must stay open
   - Look for "WebSocket server started on port 8080"

2. **Is port 8080 accessible?**
   ```bash
   lsof -i :8080
   ```
   Should show your PHP process

3. **Firewall blocking?**
   - System Preferences → Security & Privacy → Firewall
   - Allow Terminal/PHP if prompted

4. **Browser WebSocket support?**
   - Use Chrome, Firefox, Safari, or Edge (modern versions)
   - Old browsers may not support WebSockets

---

### ❌ Database connection errors

**Solution:**
1. Check `config/database.php` has correct settings:
   - Port: `8889` (MAMP default)
   - Host: `localhost`
   - Database: `team`
   - Username/password: match your MAMP MySQL settings

2. Verify MySQL is running in MAMP

3. Test connection:
   ```bash
   /Applications/MAMP/Library/bin/mysql -u team -p team
   # Enter password: team
   ```

---

## Running in Background (Optional)

If you want the server to run in the background:

### macOS/Linux:

```bash
# Start in background
nohup php server.php > server.log 2>&1 &

# Check if running
ps aux | grep server.php

# View logs
tail -f server.log

# Stop the server
pkill -f server.php
```

---

## Network Access (For Multiple Devices)

If players will join from other devices (phones, tablets):

1. **Find your computer's IP address:**
   ```bash
   ipconfig getifaddr en0
   # or
   ifconfig | grep "inet " | grep -v 127.0.0.1
   ```
   Example output: `192.168.1.100`

2. **Update JavaScript files:**

   **js/host.js** - Line 1-2:
   ```javascript
   const API_URL = 'http://192.168.1.100:8888/worshipteam/api';
   const WS_URL = 'ws://192.168.1.100:8080';
   ```

   **js/player.js** - Line 1-2:
   ```javascript
   const API_URL = 'http://192.168.1.100:8888/worshipteam/api';
   const WS_URL = 'ws://192.168.1.100:8080';
   ```

3. **Access from other devices:**
   - Host: `http://192.168.1.100:8888/worshipteam/host.html`
   - Players scan QR code or use: `http://192.168.1.100:8888/worshipteam/player.html?code=XXXXX`

4. **Make sure firewall allows connections:**
   - System Preferences → Security & Privacy → Firewall → Firewall Options
   - Allow incoming connections on ports 8080 and 8888

---

## Quick Commands Reference

```bash
# Start server
cd /Applications/MAMP/htdocs/worshipteam
./start_server.sh

# Or manually
php server.php

# Check if port 8080 is in use
lsof -i :8080

# Check if server is running
ps aux | grep server.php

# Stop server
# Press Ctrl+C in the terminal window
# Or: pkill -f server.php
```

---

## Next Steps

Once the WebSocket server is running:

1. ✅ Keep the terminal open
2. ✅ Open `http://localhost:8888/worshipteam/host.html` in your browser
3. ✅ The game should initialize without errors
4. ✅ Players can join via QR code

---

**Need help?** Check the main `README.md` or `QUICK_START.md` files.

