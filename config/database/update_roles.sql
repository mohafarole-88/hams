-- Update HAMS user roles to new hierarchy
-- Admin > Coordinator > Officer > Assistant

-- Step 1: Add new ENUM values to the role column (this allows both old and new values temporarily)
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'coordinator', 'field_worker', 'officer', 'assistant') DEFAULT 'assistant';

-- Step 2: Update existing roles to new structure
UPDATE users SET role = 'coordinator' WHERE role = 'manager';
UPDATE users SET role = 'officer' WHERE role = 'field_worker';

-- Step 3: Remove old ENUM values, keeping only the new ones
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'coordinator', 'officer', 'assistant') DEFAULT 'assistant';

-- Note: Run this SQL script in your MySQL database to update the schema
