-- ShareSphere Migration: 001_identity.up.sql
-- Identity, NGOs, Categories, and Compatibility Schema

-- 1. Create shared updated_at trigger function
CREATE OR REPLACE FUNCTION trigger_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email citext UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL CHECK (role IN ('donor', 'ngo', 'admin')),
    account_status VARCHAR(32) NOT NULL DEFAULT 'active' CHECK (account_status IN ('active', 'suspended')),
    phone VARCHAR(64) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMPTZ NULL
);

CREATE TRIGGER set_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();

-- 3. NGOs Table
CREATE TABLE IF NOT EXISTS ngos (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    organization_name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(128) NOT NULL,
    address_text TEXT NOT NULL,
    location geography(Point, 4326) NOT NULL,
    service_radius_km NUMERIC NOT NULL DEFAULT 25 CHECK (service_radius_km > 0 AND service_radius_km <= 500),
    verification_status VARCHAR(32) NOT NULL DEFAULT 'pending' CHECK (verification_status IN ('pending', 'verified', 'rejected', 'correction_requested', 'suspended')),
    reviewed_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMPTZ NULL,
    review_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Spatial index on NGO location
CREATE INDEX IF NOT EXISTS idx_ngos_location ON ngos USING GIST(location);

CREATE TRIGGER set_ngos_updated_at
BEFORE UPDATE ON ngos
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();

-- 4. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(128) UNIQUE NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TRIGGER set_categories_updated_at
BEFORE UPDATE ON categories
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();

-- 5. Category Compatibility Table
CREATE TABLE IF NOT EXISTS category_compatibility (
    category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    compatible_category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    score_factor NUMERIC NOT NULL DEFAULT 60 CHECK (score_factor > 0 AND score_factor <= 100),
    PRIMARY KEY (category_id, compatible_category_id),
    CONSTRAINT check_different_categories CHECK (category_id <> compatible_category_id)
);
