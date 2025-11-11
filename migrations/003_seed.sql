-- Sample seed data for quick start

INSERT INTO cities (slug, name) VALUES
  ('maceio', 'Maceió'),
  ('sao-paulo', 'São Paulo'),
  ('rio-de-janeiro', 'Rio de Janeiro');

INSERT INTO neighborhoods_en (city_id, slug, name) VALUES
  (2, 'moema', 'Moema'),
  (2, 'jardins', 'Jardins'),
  (2, 'pinheiros', 'Pinheiros');

INSERT INTO categories_en (slug, name) VALUES
  ('blonde', 'Blonde'),
  ('brunette', 'Brunette'),
  ('massage', 'Massage'),
  ('cam-girl', 'Cam Girl');

INSERT INTO models (slug, display_name, phone, description, city_id, neighborhood_id, is_active, price, price_period)
VALUES
  ('clara-meier', 'Clara Meier', '5551998989919', 'Verified model', 2, 1, 1, 1000.00, '1h');

INSERT INTO model_photos (model_id, url, is_primary, sort_order) VALUES
  ((SELECT id FROM models WHERE slug='clara-meier'), '/storage/img/ee043df9bfbd1b2a03e1ca638dfa4643.jpg', 1, 1);

INSERT INTO model_categories (model_id, category_id)
VALUES
  ((SELECT id FROM models WHERE slug='clara-meier'), (SELECT id FROM categories_en WHERE slug='blonde')),
  ((SELECT id FROM models WHERE slug='clara-meier'), (SELECT id FROM categories_en WHERE slug='massage'));
