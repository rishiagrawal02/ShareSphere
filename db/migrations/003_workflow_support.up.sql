-- ShareSphere Migration: 003_workflow_support.up.sql
-- Requests, Allocations, Pickups, Notifications, Email Outbox, Audit & Support Schema

-- 1. Donation Requests Table
CREATE TABLE IF NOT EXISTS donation_requests (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    donation_id BIGINT NOT NULL REFERENCES donations(id) ON DELETE RESTRICT,
    ngo_id BIGINT NOT NULL REFERENCES ngos(id) ON DELETE RESTRICT,
    requirement_id BIGINT NULL REFERENCES ngo_requirements(id) ON DELETE SET NULL,
    requested_quantity INT NOT NULL CHECK (requested_quantity > 0),
    status VARCHAR(32) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected', 'cancelled', 'expired')),
    notes TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    reviewed_at TIMESTAMPTZ NULL,
    expires_at TIMESTAMPTZ NOT NULL DEFAULT (NOW() + INTERVAL '72 hours')
);

CREATE INDEX IF NOT EXISTS idx_donation_requests_donation_id ON donation_requests (donation_id);
CREATE INDEX IF NOT EXISTS idx_donation_requests_ngo_id ON donation_requests (ngo_id);
CREATE INDEX IF NOT EXISTS idx_donation_requests_status ON donation_requests (status);

-- 2. Allocations Table (Locked with Requests)
CREATE TABLE IF NOT EXISTS allocations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT UNIQUE NOT NULL REFERENCES donation_requests(id) ON DELETE RESTRICT,
    donation_id BIGINT NOT NULL REFERENCES donations(id) ON DELETE RESTRICT,
    requirement_id BIGINT NULL REFERENCES ngo_requirements(id) ON DELETE SET NULL,
    allocated_quantity INT NOT NULL CHECK (allocated_quantity > 0),
    status VARCHAR(32) NOT NULL DEFAULT 'reserved' CHECK (status IN ('reserved', 'confirmed', 'collected', 'completed', 'cancelled')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_allocations_donation_id ON allocations (donation_id);
CREATE INDEX IF NOT EXISTS idx_allocations_requirement_id ON allocations (requirement_id);
CREATE INDEX IF NOT EXISTS idx_allocations_status ON allocations (status);

CREATE TRIGGER set_allocations_updated_at
BEFORE UPDATE ON allocations
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();

-- 3. Pickups & OTP Handover Table
CREATE TABLE IF NOT EXISTS pickups (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    allocation_id BIGINT UNIQUE NOT NULL REFERENCES allocations(id) ON DELETE CASCADE,
    proposed_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    scheduled_at TIMESTAMPTZ NOT NULL,
    location_details TEXT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'proposed' CHECK (state IN ('proposed', 'scheduled', 'otp_issued', 'collected', 'completed', 'cancelled')),
    otp_hmac VARCHAR(64) NULL,
    otp_expires_at TIMESTAMPTZ NULL,
    otp_attempts INT NOT NULL DEFAULT 0,
    otp_locked BOOLEAN NOT NULL DEFAULT FALSE,
    otp_issue_count INT NOT NULL DEFAULT 0,
    last_otp_issued_at TIMESTAMPTZ NULL,
    collected_at TIMESTAMPTZ NULL,
    completed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pickups_state ON pickups (state);

CREATE TRIGGER set_pickups_updated_at
BEFORE UPDATE ON pickups
FOR EACH ROW
EXECUTE FUNCTION trigger_set_updated_at();

-- 4. In-App Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    ref_type VARCHAR(64) NULL,
    ref_id BIGINT NULL,
    read_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications (user_id, read_at);

-- 5. Transactional Email Outbox Table
CREATE TABLE IF NOT EXISTS email_outbox (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    to_email citext NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_text TEXT NOT NULL,
    body_html TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    attempts INT NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_error TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    sent_at TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS idx_email_outbox_status_next ON email_outbox (status, next_attempt_at);

-- 6. Audit Logs Table (Append-only)
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(128) NOT NULL,
    target_type VARCHAR(64) NOT NULL,
    target_id BIGINT NULL,
    result VARCHAR(32) NOT NULL DEFAULT 'success',
    metadata JSONB NULL,
    ip INET NULL,
    request_id VARCHAR(64) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_actor_target ON audit_logs (actor_user_id, target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs (created_at);

-- 7. User Session Logs Table
CREATE TABLE IF NOT EXISTS session_logs (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    event VARCHAR(64) NOT NULL,
    ip INET NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_session_logs_user_id ON session_logs (user_id);

-- 8. Persistent Rate Limits Table
CREATE TABLE IF NOT EXISTS rate_limits (
    key TEXT NOT NULL,
    window_start TIMESTAMPTZ NOT NULL,
    hits INT NOT NULL DEFAULT 1,
    PRIMARY KEY (key, window_start)
);
