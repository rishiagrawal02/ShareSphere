-- ShareSphere Migration: 001_identity.down.sql
-- Rollback Identity, NGOs, Categories, and Compatibility Schema

DROP TABLE IF EXISTS category_compatibility CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS ngos CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP FUNCTION IF EXISTS trigger_set_updated_at CASCADE;
