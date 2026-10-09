# ShareSphere – State Model & Entity Lifecycle Specification

This document defines the authoritative state machines, transition rules, and validation invariants across all entities in ShareSphere.

---

## 1. Summary of Canonical Enums

| Entity Attribute | Allowed Values |
|---|---|
| `user.role` | `donor`, `ngo`, `admin` |
| `user.account_status` | `active`, `suspended` |
| `ngo.verification_status` | `pending`, `verified`, `rejected`, `correction_requested`, `suspended` |
| `donation.condition` | `new`, `like_new`, `good`, `fair` |
| `donation.status` | `draft`, `active`, `partially_allocated`, `fully_allocated`, `completed`, `closed`, `removed` |
| `requirement.urgency` | `low`, `medium`, `high`, `critical` |
| `requirement.status` | `draft`, `active`, `partially_fulfilled`, `fulfilled`, `closed` |
| `request.status` | `pending`, `accepted`, `rejected`, `cancelled`, `expired` |
| `allocation.status` | `reserved`, `confirmed`, `collected`, `completed`, `cancelled` |
| `pickup.state` | `proposed`, `scheduled`, `otp_issued`, `collected`, `completed`, `cancelled` |

---

## 2. Detailed State Machines & Transition Rules

### 2.1 User Account Status (`user.account_status`)

```
 [active] <==========> [suspended]
             (admin)
```

| Current State | Target State | Trigger / Role | Guard / Invariant |
|---|---|---|---|
| `active` | `suspended` | Admin | Account cannot log in; existing active donations hidden. |
| `suspended` | `active` | Admin | Account restored to normal operations. |

---

### 2.2 NGO Verification Status (`ngo.verification_status`)

```
                     ┌──────────────────┐
                     │     pending      │ <─────────────────┐
                     └───────┬──┬──┬────┘                   │
           Admin verifies    │  │  │ Admin requests         │ NGO resubmits
                   ┌─────────┘  │  │ correction             │ documents
                   ▼            │  └──────────────► ┌───────┴──────────────┐
           ┌──────────────┐     │                   │ correction_requested │
           │   verified   │     │ Admin rejects     └──────────────────────┘
           └───────┬──────┘     │
Admin suspends     │  ▲         └─────────────────► ┌──────────────────────┐
                   ▼  │ Admin restores              │       rejected       │
           ┌──────────┴───┐                         └──────────────────────┘
           │  suspended   │
           └──────────────┘
```

| Current State | Target State | Trigger / Role | Guard / Invariant |
|---|---|---|---|
| `pending` | `verified` | Admin | Mandatory registration documents approved. |
| `pending` | `rejected` | Admin | Final rejection note recorded. |
| `pending` | `correction_requested` | Admin | Feedback notes provided to NGO. |
| `correction_requested` | `pending` | NGO User | NGO uploads revised verification documents. |
| `verified` | `suspended` | Admin | NGO loses ability to browse donations & request items. |
| `suspended` | `verified` | Admin | NGO privileges reinstated. |

---

### 2.3 Donation Status (`donation.status`)

```
 [draft] ──► [active] <───► [partially_allocated] <───► [fully_allocated]
                 │                    │                        │
                 │ (donor closes)     │ (donor closes)         │ (all allocations done)
                 ▼                    ▼                        ▼
             [closed]             [closed]                [completed]
                 │                    │                        │
                 └────────────────────┼────────────────────────┘
                                      │ (admin moderate)
                                      ▼
                                  [removed]
```

| Current State | Target State | Trigger / Conditions | Quantity / Invariant |
|---|---|---|---|
| `draft` | `active` | Donor publishes listing | `available_quantity = total_quantity` |
| `active` | `partially_allocated` | NGO request creates reserved allocation | `0 < available_quantity < total_quantity` |
| `partially_allocated` | `active` | Allocation cancelled / rejected | `available_quantity = total_quantity` |
| `partially_allocated` | `fully_allocated` | NGO request claims remaining stock | `available_quantity = 0` |
| `fully_allocated` | `partially_allocated`| Allocation cancelled / rejected | `available_quantity > 0` |
| `fully_allocated` | `completed` | All linked allocations reach `completed` | Quantity fully disbursed and verified |
| `active` / `partially_allocated` | `closed` | Donor closes listing | Only allowed if NO active uncompleted allocations |
| Any state | `removed` | Admin moderation | Hidden from public; audit logged |

---

### 2.4 Requirement Status (`requirement.status`)

```
 [draft] ──► [active] <───► [partially_fulfilled] ───► [fulfilled]
                 │                    │
                 └────────► [closed] ◄┘
```

| Current State | Target State | Trigger / Conditions | Quantity / Invariant |
|---|---|---|---|
| `draft` | `active` | NGO publishes requirement | `quantity_allocated = 0` |
| `active` | `partially_fulfilled` | Allocations linked to need | `0 < quantity_allocated < quantity_needed` |
| `partially_fulfilled` | `fulfilled` | Completed disbursements reach goal | `quantity_fulfilled = quantity_needed` |
| `active` / `partially_fulfilled` | `closed` | NGO manual close / expired | Need closed |

---

### 2.5 Donation Request (`request.status`) & Allocation (`allocation.status`)

Requests and Allocations operate in lockstep to guarantee zero overselling.

```
 [pending / reserved] ───► [accepted / confirmed] ───► [collected] ───► [completed]
          │                          │
          ├──► [rejected]            └──► [cancelled] (returns stock)
          ├──► [cancelled] (returns stock)
          └──► [expired]   (auto 72h, returns stock)
```

| Request State | Allocation State | Action / Actor | Effect on Donation Stock |
|---|---|---|---|
| `pending` | `reserved` | NGO submits request | `available_quantity` decremented atomically |
| `accepted` | `confirmed` | Donor accepts request | Stock remains allocated; pickup scheduling unlocked |
| `rejected` | `cancelled` | Donor rejects request | Reserved quantity returned to `available_quantity` |
| `cancelled` | `cancelled` | NGO cancels request | Reserved/confirmed quantity returned to `available_quantity` |
| `expired` | `cancelled` | Auto-expired (72h cron) | Reserved quantity returned to `available_quantity` |
| `accepted` | `collected` | Handover OTP verified | Physical possession transferred to NGO |
| `accepted` | `completed` | NGO confirms final receipt | Allocation permanently closed |

---

### 2.6 Pickup Workflow (`pickup.state`)

```
 [proposed] ──► [scheduled] ──► [otp_issued] ──► [collected] ──► [completed]
      │               │              │
      └───────────────┴──────────────┴───────► [cancelled]
```

1. **`proposed`**: Initial date/time/location submitted by NGO or Donor.
2. **`scheduled`**: Counterparty accepts proposed schedule.
3. **`otp_issued`**: 6-digit cryptographic OTP generated (HMAC hashed) and emailed to NGO.
4. **`collected`**: Donor inputs 6-digit OTP provided in person by NGO. Validated and updated.
5. **`completed`**: NGO arrives at facility and confirms goods received in good order.
6. **`cancelled`**: Either party cancels pickup before OTP completion; returns allocation to scheduling.
