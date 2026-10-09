-- ShareSphere Migration: 003_workflow_support.down.sql
-- Rollback Workflow, Audit, Notifications, Outbox, and Support Schema

DROP TABLE IF EXISTS rate_limits CASCADE;
DROP TABLE IF EXISTS session_logs CASCADE;
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS email_outbox CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS pickups CASCADE;
DROP TABLE IF EXISTS allocations CASCADE;
DROP TABLE IF EXISTS donation_requests CASCADE;
