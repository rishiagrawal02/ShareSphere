-- ShareSphere Migration: 005_pickups_active_unique.down.sql
-- Revert active pickup unique index

DROP INDEX IF EXISTS idx_pickups_allocation_active;
ALTER TABLE pickups DROP COLUMN IF EXISTS contact_note;
ALTER TABLE pickups ADD CONSTRAINT pickups_allocation_id_key UNIQUE (allocation_id);
