# ShareSphere

> **Smart Community Donation & NGO Matching Platform** — connecting donors who have surplus goods with verified NGOs that have active community needs.

> ⚠️ **Setup in progress** — this project is being built phase-by-phase following the [Development Playbook](docs/ShareSphere_Development_Playbook.md).

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | React 18 + Vite + Tailwind CSS |
| Map | Leaflet + OpenStreetMap |
| Backend | PHP 8.2+ (custom REST API) |
| Database | PostgreSQL 16 + PostGIS 3.3 |
| Email | PHPMailer SMTP (Mailpit for dev) |
| Testing | PHPUnit · Vitest · Playwright · k6 |

---

## Quick Start

### Prerequisites
- Docker & Docker Compose (or local PostgreSQL 16 with PostGIS + Mailpit)
- PHP 8.2+ with `pdo_pgsql`, `mbstring`, `fileinfo`, `gd`, `openssl`, `sodium`
- Composer 2.x
- Node.js 18+ and npm

### Local Services Setup
1. Copy environment configuration:
   ```bash
   cp .env.example .env
   ```
2. Start PostgreSQL + PostGIS and Mailpit services:
   ```bash
   docker compose up -d
   ```
   - PostgreSQL is exposed on port `5432` (`sharesphere_dev` and `sharesphere_test` databases).
   - Mailpit UI is accessible at `http://localhost:8025` (SMTP on `1025`).


---

## Project Status

| Phase | Name | Status |
|---|---|---|
| 0 | Project Setup & Baseline | 🔧 In Progress |
| 1–22 | See [Development Playbook](docs/ShareSphere_Development_Playbook.md) | ⏳ Pending |

---

## License

All rights reserved © 2026 rishiagrawal02.
