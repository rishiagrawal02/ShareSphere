-- ShareSphere Migration: 005_pickups_active_unique.up.sql
-- Allow multiple pickups per allocation across lifecycle, but only ONE active pickup at a time.

ALTER TABLE pickups DROP CONSTRAINT IF EXISTS pickups_allocation_id_key;

CREATE UNIQUE INDEX IF NOT EXISTS idx_pickups_allocation_active
    ON pickups (allocation_id)
    WHERE state != 'cancelled';

ALTER TABLE pickups ADD COLUMN IF NOT EXISTS contact_note TEXT NULL;
