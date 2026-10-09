-- ShareSphere Migration: 004_request_idempotency.up.sql
-- Request Idempotency Guard

ALTER TABLE donation_requests ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(255) NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_donation_requests_ngo_idempotency 
    ON donation_requests (ngo_id, idempotency_key) 
    WHERE idempotency_key IS NOT NULL;
