#!/bin/bash

# WebSocket Server Startup Script for MAMP

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎮 Quiz Game WebSocket Server"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "⚠️  IMPORTANT: This server MUST run from Terminal!"
echo "   DO NOT access server.php via browser!"
echo ""
echo "📍 Location: $(pwd)"
echo "🔌 WebSocket Port: 8080"
echo "🌐 Make sure MAMP (Apache + MySQL) is running!"
echo ""
echo "✅ Once started, open in browser:"
echo "   http://localhost:8888/worshipteam/host.html"
echo ""
echo "Press Ctrl+C to stop the server"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

cd "$(dirname "$0")"

# Check if MAMP PHP is available, otherwise use system PHP
if [ -f "/Applications/MAMP/bin/php/php8.2.0/bin/php" ]; then
    PHP_BIN="/Applications/MAMP/bin/php/php8.2.0/bin/php"
    echo "Using MAMP PHP 8.2.0"
elif [ -f "/Applications/MAMP/bin/php/php8.1.0/bin/php" ]; then
    PHP_BIN="/Applications/MAMP/bin/php/php8.1.0/bin/php"
    echo "Using MAMP PHP 8.1.0"
elif [ -f "/Applications/MAMP/bin/php/php8.0.0/bin/php" ]; then
    PHP_BIN="/Applications/MAMP/bin/php/php8.0.0/bin/php"
    echo "Using MAMP PHP 8.0.0"
else
    PHP_BIN="php"
    echo "⚠️  Using system PHP"
fi

echo "Starting server..."
echo ""

$PHP_BIN server.php

