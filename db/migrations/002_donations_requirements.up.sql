-- ShareSphere Migration: 002_donations_requirements.up.sql
-- Donations, Requirements, Images, NGO Documents, and PostGIS Spatial Indexes

-- 1. Donations Table
CREATE TABLE IF NOT EXISTS donations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    donor_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    condition VARCHAR(32) NOT NULL CHECK (condition IN ('new', 'like_new', 'good', 'fair')),
    total_quantity INT NOT NULL CHECK (total_quantity > 0),
    available_quantity INT NOT NULL CHECK (available_quantity >= 0 AND available_quantity <= total_quantity),
    status VARCHAR(32) NOT NULL DEFAULT 'active' CHECK (status IN ('draft', 'active', 'partially_allocated', 'fully_allocated', 'completed', 'closed', 'removed')),
    address_text TEXT NOT NULL,
    location geography(Point, 4326) NOT NULL,
    location_public geography(Point, 4326) NOT NULL,
    pickup_notes TEXT NULL,
    expires_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Spatial and Filtering Indexes on Donations
CREATE INDEX IF NOT EXISTS idx_donations_location_public ON donations USING GIST(location_public);
CREATE INDEX IF NOT EXISTS idx_donations_status_category ON donations (status, category_id);
CREATE INDEX IF NOT EXISTS idx_donations_donor_id ON donations (donor_id);

CREATE TRIGGER set_donations_updated_at
BEFORE UPDATE ON donations
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();

-- 2. Donation Images Table
CREATE TABLE IF NOT EXISTS donation_images (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    donation_id BIGINT NOT NULL REFERENCES donations(id) ON DELETE CASCADE,
    storage_name VARCHAR(255) UNIQUE NOT NULL,
    original_mime VARCHAR(64) NOT NULL,
    size_bytes BIGINT NOT NULL,
    sha256 VARCHAR(64) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_donation_images_donation_id ON donation_images (donation_id, sort_order);

-- 3. NGO Documents Table
CREATE TABLE IF NOT EXISTS ngo_documents (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ngo_id BIGINT NOT NULL REFERENCES ngos(id) ON DELETE CASCADE,
    storage_name VARCHAR(255) UNIQUE NOT NULL,
    mime VARCHAR(64) NOT NULL,
    size_bytes BIGINT NOT NULL,
    sha256 VARCHAR(64) NOT NULL,
    doc_type VARCHAR(64) NOT NULL DEFAULT 'registration_proof',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_ngo_documents_ngo_id ON ngo_documents (ngo_id);

-- 4. NGO Requirements Table
CREATE TABLE IF NOT EXISTS ngo_requirements (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ngo_id BIGINT NOT NULL REFERENCES ngos(id) ON DELETE RESTRICT,
    category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    quantity_needed INT NOT NULL CHECK (quantity_needed > 0),
    quantity_allocated INT NOT NULL DEFAULT 0 CHECK (quantity_allocated >= 0 AND quantity_allocated <= quantity_needed),
    quantity_fulfilled INT NOT NULL DEFAULT 0 CHECK (quantity_fulfilled >= 0 AND quantity_fulfilled <= quantity_allocated),
    urgency VARCHAR(32) NOT NULL DEFAULT 'medium' CHECK (urgency IN ('low', 'medium', 'high', 'critical')),
    min_condition VARCHAR(32) NULL CHECK (min_condition IS NULL OR min_condition IN ('new', 'like_new', 'good', 'fair')),
    location geography(Point, 4326) NOT NULL,
    radius_km NUMERIC NOT NULL DEFAULT 25 CHECK (radius_km >= 1 AND radius_km <= 500),
    needed_by DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active' CHECK (status IN ('draft', 'active', 'partially_fulfilled', 'fulfilled', 'closed')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Spatial and Filtering Indexes on Requirements
CREATE INDEX IF NOT EXISTS idx_ngo_requirements_location ON ngo_requirements USING GIST(location);
CREATE INDEX IF NOT EXISTS idx_ngo_requirements_status_category ON ngo_requirements (status, category_id);
CREATE INDEX IF NOT EXISTS idx_ngo_requirements_ngo_id ON ngo_requirements (ngo_id);

CREATE TRIGGER set_ngo_requirements_updated_at
BEFORE UPDATE ON ngo_requirements
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();
