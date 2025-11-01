# Quick Start Guide

## 1. Install Dependencies

```bash
cd /Applications/MAMP/htdocs/worshipteam
composer install
```

## 2. Setup Database

Option A - Using Terminal:
```bash
# Make sure MAMP MySQL is running first
/Applications/MAMP/Library/bin/mysql -u root -p -e "source database/schema.sql"
/Applications/MAMP/Library/bin/mysql -u root -p -e "source database/sample_questions.sql"
# Default password is usually: root
```

Option B - Using phpMyAdmin:
1. Go to http://localhost:8888/phpMyAdmin
2. Import `database/schema.sql`
3. Import `database/sample_questions.sql`

## 3. Start WebSocket Server

Open a new Terminal window:
```bash
cd /Applications/MAMP/htdocs/worshipteam
php server.php
```

**Keep this terminal running!**

## 4. Open the Game

**Host View (Big Screen):**
```
http://localhost:8888/worshipteam/host.html
```

**Player View (Mobile):**
Scan the QR code shown on the host screen, or go to:
```
http://localhost:8888/worshipteam/player.html?code=GAMECODE
```

## 5. Play!

1. Players join by scanning QR code
2. Host clicks "Start Game" (ابدأ اللعبة)
3. Questions appear automatically
4. Players select answers on their phones
5. After each question, leaderboard updates
6. Host clicks "Next Question" (السؤال التالي)
7. At the end, view the winners!

## Troubleshooting

### WebSocket connection fails?
- Make sure `php server.php` is running
- Check port 8080 is not in use: `lsof -i :8080`

### Can't connect from other devices?
1. Find your IP: `ipconfig getifaddr en0`
2. Update `js/host.js` and `js/player.js`:
   - Change `localhost` to your IP address (e.g., `192.168.1.100`)
3. Use: `http://YOUR_IP:8888/worshipteam/host.html`

### Database connection error?
- Verify MAMP MySQL is running
- Check credentials in `config/database.php`
- Default username: `root`, password: `root`

## System Requirements

- MAMP running (Apache + MySQL)
- PHP 7.4+
- Modern browser with WebSocket support
- Stable Wi-Fi for 200-300 players

## Configuration

To use on local network with multiple devices:

**js/host.js** - Update line 1:
```javascript
const API_URL = 'http://YOUR_IP:8888/worshipteam/api';
const WS_URL = 'ws://YOUR_IP:8080';
```

**js/player.js** - Update line 1:
```javascript
const API_URL = 'http://YOUR_IP:8888/worshipteam/api';
const WS_URL = 'ws://YOUR_IP:8080';
```

Replace `YOUR_IP` with your computer's local IP address.

---

**Ready to play? Have fun! 🎉**
