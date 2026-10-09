-- ShareSphere Deterministic Seed Data
-- Seed baseline categories and compatibility mappings

INSERT INTO categories (name, description, is_active) VALUES
('Clothing', 'Clean garments, apparel, shoes and winter wear in wearable condition', TRUE),
('Blankets', 'Warm blankets, bedding sets, quilts, and sleeping mats', TRUE),
('Books', 'Educational textbooks, story books, reference material, and novels', TRUE),
('Stationery', 'Notebooks, pens, geometry boxes, backpacks, and art supplies', TRUE),
('Furniture', 'Desks, chairs, study tables, storage units, and bookshelves', TRUE),
('Kitchenware', 'Cooking utensils, plates, cutlery, bowls, and storage containers', TRUE),
('Toys', 'Safe children toys, board games, educational puzzles, and soft toys', TRUE),
('Electronics-small', 'Working calculators, small table fans, torches, and digital clocks', TRUE)
ON CONFLICT (name) DO UPDATE SET
    description = EXCLUDED.description,
    is_active = EXCLUDED.is_active;

-- Category compatibility mappings (Exact = 100%, Compatible = score_factor)
-- 1. Books <-> Stationery (score: 75)
INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
SELECT c1.id, c2.id, 75
FROM categories c1, categories c2
WHERE c1.name = 'Books' AND c2.name = 'Stationery'
ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor;

INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
SELECT c1.id, c2.id, 75
FROM categories c1, categories c2
WHERE c1.name = 'Stationery' AND c2.name = 'Books'
ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor;

-- 2. Clothing <-> Blankets (score: 65)
INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
SELECT c1.id, c2.id, 65
FROM categories c1, categories c2
WHERE c1.name = 'Clothing' AND c2.name = 'Blankets'
ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor;

INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
SELECT c1.id, c2.id, 65
FROM categories c1, categories c2
WHERE c1.name = 'Blankets' AND c2.name = 'Clothing'
ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor;

-- 3. Furniture <-> Kitchenware (score: 50)
INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
SELECT c1.id, c2.id, 50
FROM categories c1, categories c2
WHERE c1.name = 'Furniture' AND c2.name = 'Kitchenware'
ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor;

INSERT INTO category_compatibility (category_id, compatible_category_id, score_factor)
SELECT c1.id, c2.id, 50
FROM categories c1, categories c2
WHERE c1.name = 'Kitchenware' AND c2.name = 'Furniture'
ON CONFLICT (category_id, compatible_category_id) DO UPDATE SET score_factor = EXCLUDED.score_factor;
