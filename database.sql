-- Lost&Found Hub Database Schema
-- Run this SQL to create the database structure

CREATE DATABASE IF NOT EXISTS lostfound_hub;
USE lostfound_hub;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items table for lost and found items
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('lost', 'found') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    location VARCHAR(200),
    date_occurred DATE,
    photo_url VARCHAR(255),
    status ENUM('active', 'resolved') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at)
);

-- Contact requests table
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    requester_id INT NOT NULL,
    message TEXT,
    contact_info VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample data for testing
INSERT INTO users (username, email, password_hash, full_name, phone) VALUES
('john_doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '555-0123'),
('jane_smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', '555-0456');

INSERT INTO items (user_id, type, title, description, category, location, date_occurred) VALUES
(1, 'lost', 'Black iPhone 13', 'Lost my black iPhone 13 with a blue case near the library', 'Electronics', 'Main Library', '2024-11-05'),
(2, 'found', 'Red Backpack', 'Found a red backpack in the cafeteria with textbooks inside', 'Bags', 'Student Cafeteria', '2024-11-04'),
(1, 'lost', 'Silver Watch', 'Lost my silver Casio watch during gym class', 'Jewelry', 'Gymnasium', '2024-11-03'),
(2, 'found', 'Blue Water Bottle', 'Found a blue water bottle with stickers on it', 'Personal Items', 'Parking Lot B', '2024-11-02');