-- ZamboSur Crafts Database Setup
-- Run this SQL script in your MySQL database

-- Create database
CREATE DATABASE IF NOT EXISTS zambosur_db;
USE `zambosur_db`;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(500),
    category_id INT,
    is_best_seller TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact messages table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample data for categories
INSERT INTO categories (name) VALUES 
    ('Bags'),
    ('Wallets'),
    ('Belts'),
    ('Accessories'),
    ('Jewelry'),
    ('Malongs'),
    ('Pillowcases'),
    ('Baskets'),
    ('Weaving'),
    ('Modern Miniature');

-- Sample data for products
INSERT INTO products (name, description, price, image_url, category_id, is_best_seller,quantity, created_at) VALUES
    ('Handwoven Basket Bag', 'Beautiful handwoven basket bag made from natural materials', 450.00, 'images/images/images.jpg', 1, TRUE, 20, '2024-06-01 10:00:00'),
    ('Leather Wallet', 'Genuine leather wallet with intricate tribal design', 250.00, 'images/images/leather-phonewallet1_orig.jpg', 2, TRUE, 20, '2024-06-02 11:30:00'),
    ('Braided Belt', 'Traditional braided belt with brass buckle', 350.00, 'images/images/cdd08bac970af079a99f8ee15dd8a7fe.jpg', 3, FALSE, 20, '2024-06-03 14:00:00'),
    ('Tribal Necklace', 'Handcrafted necklace with alchemical symbols', 550.00, 'images/images/40ba97f811a933fac605623528be4888.jpg', 5, TRUE, 20, '2024-06-04 16:00:00'),
    ('Woven Earrings', 'Delicate woven earrings with当地 beads', 180.00, 'images/images/79664d7fd27781c4243a4a47c4af4b95.jpg_720x720q80.jpg', 5, FALSE, 20, '2024-06-05 10:30:00'),
    ('Traditional Malong', 'Authentic handwoven malong with intricate patterns', 800.00, 'images/images/de36ea0b3bedf1fc52ba0b8a17a97908.jpg', 6, TRUE, 20, '2024-06-06 13:00:00'),
    ('Malong Tinalak', 'Rare tinalak malong with traditional Yakan design', 1500.00, 'images/images/malong_tinalak.jpg', 6, TRUE, 20, '2024-06-07 15:00:00'),
    ('Silk Pillowcase', 'Handwoven silk pillowcase with traditional motifs', 300.00, 'images/images/silk_pillowcase.jpg', 7, FALSE, 20, '2024-06-08 11:00:00'),
    ('Embroidered Pillowcase', 'Colorful embroidered pillowcases from local artisans', 350.00, 'images/images/embroidered.jpg', 7, TRUE, 20, '2024-06-09 14:30:00'),
    ('Wicker Market Basket', 'Durable wicker basket perfect for shopping and storage', 600.00, 'images/images/market_basket.jpg', 8, TRUE, 20, '2024-06-10 10:00:00'),
    ('Decorative Fruit Basket', 'Decorative woven basket for fruits and decor', 500.00, 'images/images/fruit_basket.jpg', 8, FALSE, 20, '2024-06-11 14:30:00'),
    ('Handloom Weaving', 'Traditional handloom fabric piece', 1200.00, 'images/images/handloom.jpg', 9, FALSE, 20, '2024-06-12 16:00:00'),
    ('Decorative Weave', 'Colorful woven wall hanging', 800.00, 'images/images/decorative.jpg', 9, TRUE, 20, '2024-06-13 11:30:00'),
    ('Miniature House', 'Handcrafted wooden miniature house', 150.00, 'images/images/miniature_house.jpg', 10, FALSE, 20, '2024-06-14 10:00:00'),
    ('Miniature Boat', 'Carved boat miniature with intricate details', 200.00, 'images/mini2.jpg', 10, TRUE, 20, '2024-06-14 10:00:00'),
    ('Floral Pillowcase Set', 'Set of two floral-patterned pillowcases', 400.00, 'images/images/floral.jpg', 7, TRUE, 20, '2024-06-14 10:00:00'),
    ('Handwoven Storage Basket', 'Large handwoven basket for storage', 750.00, 'images/images/storage_basket.jpg', 8, FALSE, 20, '2024-06-14 10:00:00'),
    ('Picnic Basket', 'Traditional picnic basket with lid', 900.00, 'images/images/picnic.jpg', 8, TRUE, 20, '2024-06-14 10:00:00'),
    ('Banig Weave', 'Seagrass banig weaving sample', 1100.00, 'images/images/banig.jpg', 9, FALSE, 20, '2024-06-14 10:00:00'),
    ('Wooden Miniature Chair', 'Miniature wooden chair handcrafted in detail', 120.00, 'images/images/chair.jpg', 10, FALSE, 20, '2024-06-14 10:00:00'),
    ('Miniature Table', 'Small wooden table miniature', 180.00, 'images/images/table.jpg', 10, TRUE, 20, '2024-06-14 10:00:00');

-- Sample Malong products (category_id = 6 for Malongs)
INSERT INTO products (name, description, price, image_url, category_id, is_best_seller) VALUES
    ('Traditional Malong - Red', 'Authentic handwoven malong with traditional red patterns, perfect for ceremonies and daily wear', 850.00, 'images/malong1.jpg', 6, TRUE),
    ('Traditional Malong - Blue', 'Beautiful blue malong featuring intricate geometric patterns from Zamboanga del Sur', 850.00, 'images/malong2.jpg', 6, TRUE),
    ('Traditional Malong - Multi-color', 'Vibrant multi-colored malong showcasing the rich weaving tradition of local artisans', 950.00, 'images/malong3.jpg', 6, FALSE),
    ('Premium Malong - Yellow', 'Premium quality malong in sunny yellow, ideal for festive occasions', 1200.00, 'images/malong4.jpg', 6, FALSE),
    ('Premium Malong - Green', 'Elegant green malong with detailed traditional patterns', 1200.00, 'images/malong5.jpg', 6, FALSE),
    ('Classic Malong - Black', 'Timeless black malong with subtle white accents, versatile for any occasion', 780.00, 'images/malong6.jpg', 6, TRUE),
    ('Handwoven Malong - Purple', 'Luxurious purple malong handcrafted by skilled weavers from Kumalarang', 1100.00, 'images/malong7.jpg', 6, FALSE),
    ('Wedding Malong - White', 'Elegant white malong specially designed for weddings and formal events', 1500.00, 'images/malong8.jpg', 6, FALSE),
    ('Festival Malong - Orange', 'Bright orange malong perfect for Hermosa Festival celebrations', 900.00, 'images/malong9.jpg', 6, FALSE),
    ('Daily Wear Malong - Gray', 'Comfortable gray malong for everyday use, lightweight and durable', 650.00, 'images/malong10.jpg', 6, FALSE);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);;

-- Admins table for admin panel
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample admin account (username: admin, password: admin123)
INSERT INTO admins (username, password_hash) VALUES 
    ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password_hash for 'admin123' using password_hash('admin123', PASSWORD_DEFAULT)
