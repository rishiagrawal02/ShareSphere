-- ShareSphere Database Initialization Script
-- Executed on container first boot by Postgres entrypoint

-- 1. Create owner and least-privilege app roles
DO $$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'sharesphere_owner') THEN
    CREATE ROLE sharesphere_owner WITH LOGIN PASSWORD 'change-me';
  END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'sharesphere_app') THEN
    CREATE ROLE sharesphere_app WITH LOGIN PASSWORD 'change-me' NOSUPERUSER NOCREATEDB NOCREATEROLE;
  END IF;
END
$$;

-- 2. Create dev and test databases owned by sharesphere_owner
SELECT 'CREATE DATABASE sharesphere_dev OWNER sharesphere_owner'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'sharesphere_dev')\gexec

SELECT 'CREATE DATABASE sharesphere_test OWNER sharesphere_owner'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'sharesphere_test')\gexec

-- 3. Configure sharesphere_dev
\c sharesphere_dev
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS citext;

-- Grant schema usage and default privileges to runtime app role
GRANT USAGE ON SCHEMA public TO sharesphere_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sharesphere_owner IN SCHEMA public
  GRANT SELECT,INSERT,UPDATE,DELETE ON TABLES TO sharesphere_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sharesphere_owner IN SCHEMA public
  GRANT USAGE,SELECT ON SEQUENCES TO sharesphere_app;

-- 4. Configure sharesphere_test
\c sharesphere_test
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS citext;

GRANT USAGE ON SCHEMA public TO sharesphere_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sharesphere_owner IN SCHEMA public
  GRANT SELECT,INSERT,UPDATE,DELETE ON TABLES TO sharesphere_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sharesphere_owner IN SCHEMA public
  GRANT USAGE,SELECT ON SEQUENCES TO sharesphere_app;
