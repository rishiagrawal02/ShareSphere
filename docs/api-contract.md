# ShareSphere – API Contract Specification

**Version:** 1.0  
**Base URL:** `/api`  
**Protocol:** HTTP/1.1 or HTTP/2 over TLS (Production), HTTP/1.1 (Development)  
**Content-Type:** `application/json; charset=UTF-8`

---

## 1. Global Standards & Protocols

### 1.1 JSON Response Envelopes

Every API response adheres to a strict JSON envelope structure.

#### Success Envelope
```json
{
  "success": true,
  "data": {},
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 100,
    "total_pages": 5
  }
}
```
*Note: `meta` is optional and provided primarily on paginated collections.*

#### Error Envelope
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "fields": {
      "quantity": "The quantity must be at least 1."
    }
  },
  "request_id": "req_01h8abc123456789"
}
```

---

### 1.2 Canonical Error Codes & HTTP Status Mapping

| HTTP Status | Error Code (`error.code`) | Description / Scenario |
|---|---|---|
| **400 Bad Request** | `BAD_REQUEST` | Malformed JSON body or syntactically invalid input |
| **401 Unauthorized** | `UNAUTHENTICATED` | Missing authentication session or token |
| | `SESSION_EXPIRED` | User session has timed out due to inactivity |
| | `INVALID_CREDENTIALS` | Incorrect email or password on login |
| **403 Forbidden** | `FORBIDDEN` | Access denied for current user role |
| | `CSRF_FAILED` | Missing or invalid CSRF token on state-changing request |
| | `ACCOUNT_SUSPENDED` | User account is currently suspended by administrator |
| | `NGO_NOT_VERIFIED` | NGO action attempted before admin approval |
| **404 Not Found** | `NOT_FOUND` | Target entity or route does not exist |
| **405 Method Not Allowed** | `METHOD_NOT_ALLOWED` | HTTP method not permitted on endpoint (`Allow` header returned) |
| **409 Conflict** | `EMAIL_TAKEN` | Registration email already exists |
| | `INVALID_TRANSITION` | Illegal entity state transition attempted |
| | `INSUFFICIENT_QUANTITY` | Requested quantity exceeds available item balance |
| | `CONFLICT` | Concurrent update conflict or optimistic lock clash |
| **413 Payload Too Large** | `PAYLOAD_TOO_LARGE` | Upload exceeds maximum size (e.g. 5 MB for images/docs) |
| **415 Unsupported Media** | `UNSUPPORTED_MEDIA_TYPE` | Upload MIME type not accepted (e.g. non-JPEG/PNG/PDF) |
| **422 Unprocessable Content**| `VALIDATION_FAILED` | Semantic field validation failure |
| | `OTP_INVALID` | Incorrect 6-digit OTP code provided |
| | `OTP_EXPIRED` | OTP has expired (lifetime > 30 minutes) |
| **423 Locked** | `OTP_LOCKED` | Pickup locked after 5 consecutive failed OTP attempts |
| **429 Too Many Requests** | `RATE_LIMITED` | Exceeded endpoint rate limit |
| | `OTP_REISSUE_LIMIT` | Exceeded daily OTP generation limit (max 3/24h) |
| **500 Internal Server Error**| `INTERNAL_ERROR` | Unhandled server error (details logged internally, masked to client) |
| **503 Service Unavailable** | `SERVICE_UNAVAILABLE` | Database or external subsystem unreachable |

---

## 2. API Endpoints Reference

### 2.1 System & Baseline
- `GET /api/health`
  - **Auth:** Public
  - **Response 200:** `{"success":true,"data":{"status":"ok","db":true,"time":"2026-10-09T18:00:00Z"}}`
  - **Response 503:** `{"success":false,"error":{"code":"SERVICE_UNAVAILABLE","message":"Database connection unavailable"}}`

---

### 2.2 Authentication & Session Management
- `GET /api/auth/csrf`
  - **Auth:** Public
  - **Response 200:** `{"success":true,"data":{"csrf_token":"<hex_token>"}}`

- `POST /api/auth/register`
  - **Auth:** Public
  - **Body:**
    ```json
    {
      "name": "Jane Donor",
      "email": "jane@example.com",
      "password": "Password123!",
      "password_confirmation": "Password123!",
      "role": "donor",
      "phone": "+919876543210",
      "organization_name": "Hope Foundation",
      "registration_number": "NGO-2026-1234",
      "address_text": "123 Community Lane",
      "latitude": 28.6139,
      "longitude": 77.2090,
      "service_radius_km": 25
    }
    ```
  - *Note: NGO registration fields are required only when `role = "ngo"`.*
  - **Response 201:** `{"success":true,"data":{"user":{"id":1,"name":"...","email":"...","role":"donor"}}}`
  - **Errors:** `409 EMAIL_TAKEN`, `422 VALIDATION_FAILED`

- `POST /api/auth/login`
  - **Auth:** Public
  - **Body:** `{"email":"jane@example.com","password":"Password123!"}`
  - **Response 200:** `{"success":true,"data":{"user":{"id":1,"name":"...","email":"...","role":"donor"}}}` (Sets HttpOnly session cookie)
  - **Errors:** `401 INVALID_CREDENTIALS`, `403 ACCOUNT_SUSPENDED`

- `POST /api/auth/logout`
  - **Auth:** Authenticated
  - **Response 200:** `{"success":true,"data":{"message":"Logged out successfully"}}`

- `GET /api/auth/me`
  - **Auth:** Authenticated
  - **Response 200:** `{"success":true,"data":{"user":{"id":1,"name":"...","email":"...","role":"donor","account_status":"active"}}}`
  - **Errors:** `401 UNAUTHENTICATED`

---

### 2.3 User Profile & Settings
- `GET /api/profile`
  - **Auth:** Authenticated
  - **Response 200:** `{"success":true,"data":{"user":{...},"ngo":{...}}}`

- `PATCH /api/profile`
  - **Auth:** Authenticated
  - **Body:** `{"name":"Jane Smith","phone":"+919876543211","address_text":"...","service_radius_km":30}`
  - **Response 200:** `{"success":true,"data":{"profile":{...}}}`

- `PATCH /api/profile/password`
  - **Auth:** Authenticated
  - **Body:** `{"current_password":"...","new_password":"...","new_password_confirmation":"..."}`
  - **Response 200:** `{"success":true,"data":{"message":"Password updated successfully"}}`

---

### 2.4 Categories
- `GET /api/categories`
  - **Auth:** Public
  - **Response 200:** `{"success":true,"data":[{"id":1,"name":"Books & Stationary","description":"...","is_active":true}]}`

---

### 2.5 Donations (Listings)
- `GET /api/donations`
  - **Auth:** Public / Authenticated (Filter by `status`, `category_id`, `donor_id`, `distance_km`, `lat`, `lng`)
  - **Response 200:** `{"success":true,"data":[{...}],"meta":{...}}`

- `POST /api/donations`
  - **Auth:** Donor only
  - **Body:**
    ```json
    {
      "category_id": 1,
      "title": "50 Notebooks and Pens",
      "description": "Unused spiral notebooks and ballpoint pens",
      "condition": "new",
      "total_quantity": 50,
      "address_text": "Sector 4, Building B",
      "latitude": 28.6139,
      "longitude": 77.2090,
      "pickup_notes": "Call upon arrival"
    }
    ```
  - **Response 201:** `{"success":true,"data":{"donation":{"id":10,...}}}`

- `GET /api/donations/{id}`
  - **Auth:** Public / Authenticated
  - **Response 200:** Returns donation details. *Public view obfuscates location to ~550m grid unless viewer is owner, admin, or matched NGO.*

- `PATCH /api/donations/{id}`
  - **Auth:** Owner (Donor) or Admin
  - **Response 200:** `{"success":true,"data":{"donation":{...}}}`

- `DELETE /api/donations/{id}`
  - **Auth:** Owner (Donor) or Admin
  - **Response 200:** `{"success":true,"data":{"message":"Donation removed"}}`

- `POST /api/donations/{id}/images`
  - **Auth:** Owner (Donor)
  - **Multipart:** `image` file (JPEG/PNG/WEBP <= 5MB)
  - **Response 201:** `{"success":true,"data":{"image":{"id":1,"url":"/api/media/donation-images/1"}}}`

- `DELETE /api/donations/{id}/images/{imageId}`
  - **Auth:** Owner (Donor)
  - **Response 200:** `{"success":true,"data":{"message":"Image deleted"}}`

---

### 2.6 NGO Requirements (Needs)
- `GET /api/requirements`
  - **Auth:** Public / Authenticated
  - **Response 200:** `{"success":true,"data":[{...}],"meta":{...}}`

- `POST /api/requirements`
  - **Auth:** Verified NGO only
  - **Body:**
    ```json
    {
      "category_id": 1,
      "title": "Need 100 School Books for Primary School",
      "description": "Grade 1-5 storybooks and learning material",
      "quantity_needed": 100,
      "urgency": "high",
      "min_condition": "good",
      "latitude": 28.6150,
      "longitude": 77.2100,
      "radius_km": 20,
      "needed_by": "2026-11-01"
    }
    ```
  - **Response 201:** `{"success":true,"data":{"requirement":{"id":5,...}}}`

- `GET /api/requirements/{id}`
  - **Auth:** Public / Authenticated
  - **Response 200:** `{"success":true,"data":{"requirement":{...}}}`

- `PATCH /api/requirements/{id}`
  - **Auth:** Owning NGO or Admin
  - **Response 200:** `{"success":true,"data":{"requirement":{...}}}`

- `POST /api/requirements/{id}/close`
  - **Auth:** Owning NGO or Admin
  - **Response 200:** `{"success":true,"data":{"message":"Requirement closed"}}`

---

### 2.7 Smart Matching & Map
- `GET /api/matches?requirement_id={id}`
  - **Auth:** Owning NGO only
  - **Response 200:** Returns scored donation matches (`score_breakdown`: Category 40%, Distance 35%, Condition 15%, Quantity 10%).

- `GET /api/matches?donation_id={id}`
  - **Auth:** Owning Donor only
  - **Response 200:** Returns scored NGO requirement matches.

- `GET /api/map/donations`
  - **Auth:** Verified NGO / Public
  - **Response 200:** Returns geojson / array of active donations with privacy-snapped coordinates.

---

### 2.8 Requests & Allocations
- `POST /api/requests`
  - **Auth:** Verified NGO only
  - **Body:** `{"donation_id":10,"requirement_id":5,"requested_quantity":25,"notes":"..."}`
  - **Response 201:** `{"success":true,"data":{"request":{"id":1,"status":"pending"},"allocation":{"id":1,"status":"reserved"}}}`
  - **Errors:** `409 INSUFFICIENT_QUANTITY`

- `GET /api/requests`
  - **Auth:** Authenticated (filtered by donor or NGO)
  - **Response 200:** `{"success":true,"data":[{...}]}`

- `GET /api/requests/{id}`
  - **Auth:** Donor owner, NGO requester, Admin
  - **Response 200:** `{"success":true,"data":{"request":{...},"allocation":{...}}}`

- `POST /api/requests/{id}/accept`
  - **Auth:** Donor owner only
  - **Response 200:** `{"success":true,"data":{"request":{"id":1,"status":"accepted"},"allocation":{"status":"confirmed"}}}`

- `POST /api/requests/{id}/reject`
  - **Auth:** Donor owner only
  - **Body:** `{"reason":"..."}`
  - **Response 200:** `{"success":true,"data":{"request":{"id":1,"status":"rejected"}}}`

- `POST /api/requests/{id}/cancel`
  - **Auth:** NGO requester only
  - **Body:** `{"reason":"..."}`
  - **Response 200:** `{"success":true,"data":{"request":{"id":1,"status":"cancelled"}}}`

---

### 2.9 Pickups, OTP Handover & Completion
- `POST /api/pickups`
  - **Auth:** NGO or Donor on accepted request
  - **Body:** `{"request_id":1,"scheduled_at":"2026-10-15T10:00:00Z","location_notes":"..."}`
  - **Response 201:** `{"success":true,"data":{"pickup":{"id":1,"state":"proposed"}}}`

- `POST /api/pickups/{id}/confirm`
  - **Auth:** Counterparty
  - **Response 200:** `{"success":true,"data":{"pickup":{"id":1,"state":"scheduled"}}}`

- `POST /api/pickups/{id}/otp` (Generate / Reissue OTP)
  - **Auth:** NGO or Donor
  - **Response 200:** Sends 6-digit OTP to NGO email; records hashed HMAC OTP.
  - **Errors:** `429 OTP_REISSUE_LIMIT` (Max 3/24h)

- `POST /api/pickups/{id}/verify-otp`
  - **Auth:** Donor enters code
  - **Body:** `{"otp":"123456"}`
  - **Response 200:** `{"success":true,"data":{"pickup":{"state":"collected"},"allocation":{"status":"collected"}}}`
  - **Errors:** `422 OTP_INVALID`, `422 OTP_EXPIRED`, `423 OTP_LOCKED` (after 5 failed attempts)

- `POST /api/pickups/{id}/confirm-receipt`
  - **Auth:** NGO confirms final receipt
  - **Response 200:** `{"success":true,"data":{"pickup":{"state":"completed"},"allocation":{"status":"completed"}}}`

---

### 2.10 Notifications
- `GET /api/notifications`
  - **Auth:** Authenticated
  - **Response 200:** `{"success":true,"data":[{"id":1,"type":"REQUEST_RECEIVED","data":{...},"is_read":false}]}`

- `POST /api/notifications/{id}/read`
  - **Auth:** Recipient
  - **Response 200:** `{"success":true,"data":{"id":1,"is_read":true}}`

- `POST /api/notifications/read-all`
  - **Auth:** Recipient
  - **Response 200:** `{"success":true,"data":{"message":"All notifications marked as read"}}`

---

### 2.11 Secure Media Delivery
- `GET /api/media/donation-images/{id}`
  - **Auth:** Public / Authenticated (Streams private storage image with cache control headers)
- `GET /api/media/ngo-documents/{id}`
  - **Auth:** Owning NGO or Admin only

---

### 2.12 Admin Operations
- `GET /api/admin/ngos` (Filter: `status=pending|verified|...`)
- `GET /api/admin/ngos/{id}`
- `POST /api/admin/ngos/{id}/verify` (`{"action":"verify"|"reject"|"request_correction","note":"..."}`)
- `GET /api/admin/users`
- `PATCH /api/admin/users/{id}/status` (`{"status":"active"|"suspended","reason":"..."}`)
- `GET/POST/PATCH /api/admin/categories`
- `POST /api/admin/donations/{id}/moderate` (`{"action":"remove","reason":"..."}`)
- `GET /api/admin/reports/summary`
- `GET /api/admin/audit-logs`
