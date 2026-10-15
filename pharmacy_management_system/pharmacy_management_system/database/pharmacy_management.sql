-- PharmaCare database.
-- WARNING: importing this file wipes and recreates every table (fresh start).
CREATE DATABASE IF NOT EXISTS pharmacy_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacy_management;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS prescriptions, sale_items, sales, medicines, suppliers, customers, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','pharmacist','assistant','cashier','stock_manager') NOT NULL DEFAULT 'assistant',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  contact_person VARCHAR(100),
  phone VARCHAR(30),
  email VARCHAR(150),
  address VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medicines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(100) NOT NULL,
  supplier_id INT NULL,
  quantity INT NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL,
  expiry_date DATE NOT NULL,
  low_stock INT NOT NULL DEFAULT 10,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CHECK (quantity >= 0),
  CHECK (price > 0),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NULL,
  user_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  payment_method ENUM('cash','card','medical_aid') NOT NULL DEFAULT 'cash',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  medicine_id INT NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

CREATE TABLE prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  doctor_name VARCHAR(120) NOT NULL,
  medicine_id INT NOT NULL,
  quantity INT NOT NULL,
  instructions VARCHAR(255),
  status ENUM('pending','dispensed','cancelled') NOT NULL DEFAULT 'pending',
  sale_id INT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (medicine_id) REFERENCES medicines(id),
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Fictional demo data only. Staff logins are created by install.php.
INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('MediSupply Wholesale', 'Thandi Dube', '0115550101', 'orders@medisupply.example', '12 Demo Road, Pretoria'),
('HealthLine Distributors', 'Pieter Smit', '0125550102', 'sales@healthline.example', '8 Sample Street, Centurion');

INSERT INTO customers (name, phone, email) VALUES
('Sample Customer', '0710000000', 'customer@example.com'),
('Jane Demo', '0720000001', NULL);

INSERT INTO medicines (code, name, category, supplier_id, quantity, price, expiry_date, low_stock) VALUES
('MED001', 'Paracetamol 500mg', 'Pain Relief', 1, 50, 25.00, DATE_ADD(CURDATE(), INTERVAL 18 MONTH), 10),
('MED002', 'Vitamin C 500mg', 'Vitamins', 2, 8, 45.00, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 10),
('MED003', 'Amoxicillin 500mg', 'Antibiotic', 1, 30, 80.00, DATE_ADD(CURDATE(), INTERVAL 12 MONTH), 5),
('MED004', 'Ibuprofen 400mg', 'Pain Relief', 1, 0, 38.50, DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 10),
('MED005', 'Cough Syrup 100ml', 'Cold & Flu', 2, 25, 55.00, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 5),
('MED006', 'Antihistamine 10mg', 'Allergy', 2, 40, 62.00, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), 10);
