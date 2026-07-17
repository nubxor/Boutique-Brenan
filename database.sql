-- GALLETITAS | Base de datos MySQL
-- 1. Crea una base de datos en tu hosting, por ejemplo: galletitas_catalogo
-- 2. Selecciona esa base de datos en phpMyAdmin
-- 3. Importa este archivo database.sql

CREATE TABLE IF NOT EXISTS dresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  size VARCHAR(30) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('available','sold') NOT NULL DEFAULT 'available',
  sold_date DATE NULL,
  image VARCHAR(255) NULL,
  image_fit ENUM('cover','contain') NOT NULL DEFAULT 'cover',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_size (size),
  INDEX idx_status (status),
  INDEX idx_price (price),
  INDEX idx_sold_date (sold_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO dresses (name, size, price, status, sold_date, image, image_fit, created_at, updated_at) VALUES
('Vestido satín rosa', 'XS', 780.00, 'available', NULL, NULL, 'cover', NOW(), NOW()),
('Vestido floral beige', 'S', 890.00, 'available', NULL, NULL, 'cover', NOW(), NOW()),
('Vestido fiesta vino', 'M', 1150.00, 'available', NULL, NULL, 'cover', NOW(), NOW()),
('Vestido elegante marfil', 'M', 980.00, 'sold', CURDATE(), NULL, 'cover', NOW(), NOW()),
('Vestido noche cocoa', 'L', 1290.00, 'available', NULL, NULL, 'cover', NOW(), NOW()),
('Vestido premium champagne', 'XL', 1390.00, 'available', NULL, NULL, 'cover', NOW(), NOW());
