# ⚠️ IMPORTANT: How to Run the WebSocket Server

## ❌ DO NOT Access server.php via Browser!

**WRONG:** `https://kdsc.fun/worshipteam/server.php` ❌  
This will cause a "500 Internal Server Error" because `server.php` is NOT a web page!

## ✅ CORRECT: Run from Terminal

`server.php` is a **command-line script** that must be run from Terminal.

---

## Step-by-Step Instructions:

### 1. Open Terminal
- **macOS:** Press `Cmd + Space`, type "Terminal", press Enter
- Or: Applications → Utilities → Terminal

### 2. Navigate to Project
```bash
cd /Applications/MAMP/htdocs/worshipteam
```

### 3. Start the Server
```bash
./start_server.sh
```

**OR manually:**
```bash
php server.php
```

### 4. You Should See:
```
WebSocket server started on port 8080
```

### 5. Keep Terminal Open!
The server must keep running. Don't close the terminal window.

---

## What Files to Access in Browser:

✅ **Host View:** `https://kdsc.fun/worshipteam/host.html`  
✅ **Player View:** `https://kdsc.fun/worshipteam/player.html?code=XXXXX`

❌ **NOT:** `https://kdsc.fun/worshipteam/server.php`

---

## Quick Test:

1. Start server in Terminal: `php server.php`
2. Open browser: `https://kdsc.fun/worshipteam/host.html`
3. Check browser console (F12) for WebSocket connection

---

## Troubleshooting

If you get errors when running `php server.php`:

1. **Check PHP is in PATH:**
   ```bash
   which php
   ```

2. **Use MAMP's PHP if needed:**
   ```bash
   /Applications/MAMP/bin/php/php8.2.0/bin/php server.php
   ```
   (Adjust version number: php8.1.0, php8.0.0, etc.)

3. **Check dependencies:**
   ```bash
   composer install
   ```

4. **Check port 8080 is available:**
   ```bash
   lsof -i :8080
   ```

