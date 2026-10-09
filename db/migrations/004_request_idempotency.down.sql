-- ShareSphere Migration: 004_request_idempotency.down.sql
-- Revert Request Idempotency Guard

DROP INDEX IF EXISTS idx_donation_requests_ngo_idempotency;
ALTER TABLE donation_requests DROP COLUMN IF EXISTS idempotency_key;
