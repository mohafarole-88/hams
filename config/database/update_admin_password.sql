-- Fix admin password hash for 'admin123'
-- This hash was generated using PHP's password_hash() function
UPDATE users SET password_hash = '$2y$10$EIXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';

-- Alternative: Delete and recreate the admin user with correct hash
-- DELETE FROM users WHERE username = 'admin';
-- INSERT INTO users (username, password_hash, full_name, role, email) VALUES 
-- ('admin', '$2y$10$EIXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'admin@hams.local');
