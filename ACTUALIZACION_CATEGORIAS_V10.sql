-- Ejecutar solamente si la actualización automática no puede modificar la tabla.
ALTER TABLE dresses
  ADD COLUMN category VARCHAR(60) NOT NULL DEFAULT 'Vestidos' AFTER name,
  ADD INDEX idx_category (category);

UPDATE dresses SET category='Pijamas' WHERE LOWER(name) LIKE '%pijama%' OR LOWER(name) LIKE '%bata%';
UPDATE dresses SET category='Conjuntos' WHERE LOWER(name) LIKE '%conjunto%';
UPDATE dresses SET category='Blusas' WHERE LOWER(name) LIKE '%blusa%' OR LOWER(name) LIKE '%camisa%';
UPDATE dresses SET category='Faldas' WHERE LOWER(name) LIKE '%falda%';
UPDATE dresses SET category='Pantalones' WHERE LOWER(name) LIKE '%pantalón%' OR LOWER(name) LIKE '%pantalon%' OR LOWER(name) LIKE '%jean%' OR LOWER(name) LIKE '%short%';
UPDATE dresses SET category='Accesorios' WHERE LOWER(name) LIKE '%bolsa%' OR LOWER(name) LIKE '%cartera%' OR LOWER(name) LIKE '%collar%' OR LOWER(name) LIKE '%pulsera%' OR LOWER(name) LIKE '%arete%';
