-- ShareSphere Migration: 002_donations_requirements.down.sql
-- Rollback Donations, Requirements, Images, and NGO Documents

DROP TABLE IF EXISTS ngo_requirements CASCADE;
DROP TABLE IF EXISTS ngo_documents CASCADE;
DROP TABLE IF EXISTS donation_images CASCADE;
DROP TABLE IF EXISTS donations CASCADE;
