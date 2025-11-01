#!/bin/bash

# Quiz Game Database Setup Script
# This script creates the database and user for the quiz game

echo "🎮 Quiz Game Database Setup"
echo "============================"
echo ""

# MAMP MySQL path
MYSQL_PATH="/Applications/MAMP/Library/bin/mysql"

# Database credentials
DB_NAME="worshipteam"
DB_USER="worshipteam"
DB_PASS="worshipteam"
ROOT_PASS="root"

echo "This script will:"
echo "1. Create database: $DB_NAME"
echo "2. Create user: $DB_USER"
echo "3. Import schema and sample questions"
echo ""

# Check if MySQL is accessible
if [ ! -f "$MYSQL_PATH" ]; then
    echo "❌ MAMP MySQL not found at $MYSQL_PATH"
    echo "Please make sure MAMP is installed and update the path in this script"
    exit 1
fi

echo "✓ MySQL found"
echo ""

# Create database and user
echo "📊 Creating database and user..."
$MYSQL_PATH -u root -p$ROOT_PASS << EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo "✅ Database and user created successfully"
else
    echo "❌ Failed to create database and user"
    echo "Please check your MySQL root password (default is 'root')"
    exit 1
fi

# Import schema
echo ""
echo "📋 Importing database schema..."
$MYSQL_PATH -u $DB_USER -p$DB_PASS $DB_NAME < database/schema.sql

if [ $? -eq 0 ]; then
    echo "✅ Schema imported successfully"
else
    echo "❌ Failed to import schema"
    exit 1
fi

# Import sample questions
echo ""
echo "📝 Importing sample questions..."
$MYSQL_PATH -u $DB_USER -p$DB_PASS $DB_NAME < database/sample_questions.sql

if [ $? -eq 0 ]; then
    echo "✅ Sample questions imported successfully"
else
    echo "❌ Failed to import sample questions"
    exit 1
fi

echo ""
echo "🎉 Database setup complete!"
echo ""
echo "Database: $DB_NAME"
echo "Username: $DB_USER"
echo "Password: $DB_PASS"
echo ""
echo "Next steps:"
echo "1. Run: composer install"
echo "2. Run: php server.php"
echo "3. Open: http://localhost:8888/worshipteam/host.html"
echo ""
