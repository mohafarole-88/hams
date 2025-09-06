-- HAMS Database Schema for Somali Humanitarian Aid Management
-- Created for low-bandwidth, mobile-first use

CREATE DATABASE IF NOT EXISTS hams_db;
USE hams_db;

-- Users table for login and role-based access
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'coordinator', 'field_worker') DEFAULT 'field_worker',
    email VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Aid Recipients (People Supported)
CREATE TABLE aid_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id VARCHAR(20) UNIQUE NOT NULL, -- Local ID number
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    location VARCHAR(100) NOT NULL,
    district VARCHAR(50),
    household_size INT DEFAULT 1,
    displacement_status ENUM('resident', 'idp', 'refugee', 'returnee') DEFAULT 'resident',
    vulnerability_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    registration_date DATE NOT NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Warehouses for supply storage
CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    manager_name VARCHAR(100),
    capacity_description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Supply items (inventory)
CREATE TABLE supplies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category ENUM('food', 'water', 'shelter', 'hygiene', 'medical', 'clothing', 'other') DEFAULT 'other',
    unit_type VARCHAR(20) NOT NULL, -- kg, liters, pieces, boxes
    current_stock DECIMAL(10,2) DEFAULT 0,
    minimum_stock DECIMAL(10,2) DEFAULT 0,
    warehouse_id INT,
    expiry_date DATE NULL,
    cost_per_unit DECIMAL(10,2) DEFAULT 0,
    supplier VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

-- Projects (Relief Programs)
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(150) NOT NULL,
    project_code VARCHAR(20) UNIQUE,
    donor_name VARCHAR(100),
    target_location VARCHAR(100),
    target_beneficiaries INT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(12,2),
    status ENUM('planning', 'active', 'completed', 'suspended') DEFAULT 'planning',
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Aid Delivery (Distribution Records)
CREATE TABLE aid_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_date DATE NOT NULL,
    recipient_id INT NOT NULL,
    supply_id INT NOT NULL,
    quantity_delivered DECIMAL(10,2) NOT NULL,
    project_id INT,
    delivery_location VARCHAR(100),
    delivered_by INT NOT NULL,
    receipt_signature BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES aid_recipients(id),
    FOREIGN KEY (supply_id) REFERENCES supplies(id),
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (delivered_by) REFERENCES users(id)
);

-- Activity Records (Audit Log)
CREATE TABLE activity_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('login', 'logout', 'create', 'update', 'delete', 'delivery', 'report') NOT NULL,
    table_affected VARCHAR(50),
    record_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user (password: admin123)
-- Using a known working hash for 'admin123'
INSERT INTO users (username, password_hash, full_name, role, email) VALUES 
('admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'System Administrator', 'admin', 'admin@hams.local');

-- Insert sample warehouse
INSERT INTO warehouses (name, location, manager_name) VALUES 
('Main Warehouse', 'Mogadishu Central', 'Ahmed Hassan');

-- Insert sample supplies
INSERT INTO supplies (item_name, category, unit_type, current_stock, minimum_stock, warehouse_id) VALUES 
('Rice', 'food', 'kg', 1000, 100, 1),
('Cooking Oil', 'food', 'liters', 200, 50, 1),
('Soap Bars', 'hygiene', 'pieces', 500, 100, 1),
('Water Purification Tablets', 'water', 'boxes', 50, 10, 1);
