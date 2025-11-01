-- Migration: Add email and password reset fields to admins table
-- Run this if you already have the admins table without email fields

USE worshipteam;

-- Add email column if it doesn't exist
ALTER TABLE admins 
ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER password_hash;

-- Add reset token columns if they don't exist
ALTER TABLE admins 
ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL AFTER email;

ALTER TABLE admins 
ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME NULL AFTER reset_token;

-- Update existing admin user with email
UPDATE admins 
SET email = 'moody.gindy@gmail.com' 
WHERE username = 'admin' AND (email IS NULL OR email = '');

-- Make email required (after updating existing records)
ALTER TABLE admins 
MODIFY COLUMN email VARCHAR(255) NOT NULL;

