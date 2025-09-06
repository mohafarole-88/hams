-- MANUAL FIX: Run these commands one by one in phpMyAdmin or MySQL interface

-- Step 1: First, add new ENUM values (allows both old and new temporarily)
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'coordinator', 'field_worker', 'officer', 'assistant') DEFAULT 'assistant';

-- Step 2: Update existing user roles to new structure
UPDATE users SET role = 'coordinator' WHERE role = 'manager';
UPDATE users SET role = 'officer' WHERE role = 'field_worker';

-- Step 3: Remove old ENUM values, keep only new ones
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'coordinator', 'officer', 'assistant') DEFAULT 'assistant';

-- Verify the changes
SELECT username, role FROM users;
