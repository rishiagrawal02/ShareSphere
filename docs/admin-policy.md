# ShareSphere – Administrative Governance & Account Suspension Policy

## 1. Scope and Principles
This policy sets forth the grounds, procedural requirements, technical consequences, and appeal pathways governing account suspensions on the ShareSphere platform.
ShareSphere operates as a community trust marketplace connecting vulnerable recipients and NGOs with generous donors. Platform integrity, physical safety during item handovers, and compliance with anti-fraud safeguards require decisive administrative enforcement.

---

## 2. Grounds for Account Suspension
An administrator may suspend an account under the following documented circumstances:
1. **Safety and Conduct Violations**: Abuse, harassment, fraudulent representation, or improper conduct during communication or physical item pickup.
2. **Fraudulent Listings or Requirements**: Posting non-existent items, misrepresenting condition/value, submitting falsified NGO credentials or registration numbers.
3. **Repeated Handover Non-Compliance**: Chronic no-shows for scheduled pickups, attempted bypass of the secure two-party OTP protocol, or unauthorized quantity discrepancies.
4. **Security Breaches**: Compromised credentials, automated scraping, abusive rate-limit violations, or injection attempts.
5. **Organizational Inactivity / Regulatory Revocation**: Revocation of legal NGO status or government non-profit registration.

---

## 3. Suspension Procedure & Administrative Constraints
- **Reason Mandate**: Every suspension action requires a specific, non-empty text justification (`reason`) submitted via `PATCH /api/admin/users/{id}/status`.
- **Audit Logging**: All administrative actions are recorded immutably in `audit_logs` with actor ID, target user ID, timestamp, and justification.
- **Self-Suspension Protection**: Administrators cannot suspend their own account (`409 Conflict`).
- **Last Active Administrator Invariant**: The system prohibits the suspension of the sole remaining active administrator to prevent administrative lockout.
- **Role Immutability**: Role changes (`donor`, `ngo`, `admin`) cannot be executed via the API to prevent privilege-escalation vectors; role modifications require direct database maintenance by authorized system owners.

---

## 4. Technical Consequences of Suspension
- **Immediate Session Termination**: Authenticated endpoints check `users.account_status = 'active'` directly against the database on every HTTP request. Upon suspension, any active session is immediately rejected with HTTP `401 Unauthorized` (`ACCOUNT_SUSPENDED`).
- **Discovery Exclusion**: Donor listings owned by a suspended account are instantly filtered out from spatial matching algorithms (`MatchRepository`) and public map endpoints.
- **Cascade Behavior**: If `cascade=true` is specified, open active listings are transitioned to `closed` to prevent pending requests. Open allocations and pickups are preserved for administrative inspection or graceful cancellation.
- **Automated Notification**: A transactional email notification detailing the suspension decision and reason is dispatched to the user's registered address.

---

## 5. Reinstatement & Appeal Process
1. **Filing an Appeal**: A suspended user may submit an appeal via the official platform support channel (`support@sharesphere.local`) specifying their registered email, organization name (if NGO), and substantive explanation addressing the stated suspension reason.
2. **Administrative Review**: An administrator independently evaluates the appeal and relevant audit logs.
3. **Reinstatement**: Upon approval, the administrator executes `PATCH /api/admin/users/{id}/status` with `status: active`. The user receives an automated reinstatement email and immediate platform access is restored.
