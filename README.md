# MedLink Backend API

Laravel 11 REST API backend for the MedLink pharmacy platform.

---

## Tech Stack

- **Framework:** Laravel 11
- **Auth:** JWT (`tymon/jwt-auth`)
- **Database:** MySQL 8.0+
- **PHP:** 8.2+

---

## Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` with your database credentials:

```env
DB_DATABASE=medlink_db
DB_USERNAME=medlink_user
DB_PASSWORD=your_password
```

### 3. Database

```sql
CREATE DATABASE medlink_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'medlink_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON medlink_db.* TO 'medlink_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
php artisan migrate
php artisan db:seed
```

### 4. Run

```bash
php artisan serve
# API available at: http://localhost:8000/api/v1
```

### Seeded Test Accounts

| Role     | Email                    | Password       |
|----------|--------------------------|----------------|
| Admin    | admin@medlink.com        | Admin@1234     |
| Citizen  | ahmed@citizen.com        | Citizen@1234   |
| Citizen  | sara@citizen.com         | Citizen@1234   |
| Pharmacy | alshifa@pharmacy.com     | Pharmacy@1234  |
| Pharmacy | citymed@pharmacy.com     | Pharmacy@1234  |

---

## Architecture

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php               # register, login, logout, refresh
│   │   ├── UserController.php               # profile, password, avatar
│   │   ├── MedicineController.php           # medicine CRUD + search
│   │   ├── PharmacyController.php           # pharmacy list, details, areas
│   │   ├── InventoryController.php          # pharmacy inventory management
│   │   ├── OrderController.php              # order lifecycle
│   │   ├── BroadcastRequestController.php   # network medicine requests
│   │   ├── FavoriteController.php           # save medicines & pharmacies
│   │   ├── ReviewController.php             # pharmacy reviews
│   │   ├── ComplaintController.php          # citizen complaints
│   │   └── Admin/
│   │       └── AdminControllers.php         # user mgmt, verification, reports, stats
│   └── Middleware/
│       ├── RoleMiddleware.php               # role:citizen,pharmacy,admin
│       └── PharmacyVerifiedMiddleware.php   # pharmacy must be verified
├── Models/
│   ├── User.php             # citizen / pharmacy / admin (single table)
│   ├── Medicine.php
│   ├── Category.php
│   ├── InventoryItem.php    # pivot: pharmacy ↔ medicine
│   ├── Order.php
│   ├── BroadcastRequest.php
│   ├── Complaint.php
│   ├── Review.php           # auto-recalculates pharmacy rating
│   └── Favorite.php
database/
├── migrations/              # 7 migration files
└── seeders/
    └── DatabaseSeeder.php   # full seed with test data
routes/
└── api.php                  # all 50+ routes, grouped by role
```

---

## API Reference

**Base URL:** `http://localhost:8000/api/v1`  
**Auth:** `Authorization: Bearer <token>`  
**Format:** JSON

---

### Authentication

#### Register
```
POST /auth/register
```
```json
{
  "email": "user@example.com",
  "password": "secret123",
  "role": "citizen",
  "firstName": "Ahmed",
  "lastName": "Ali",
  "phone": "961712345678"
}
```
For `role: "pharmacy"` add: `pharmacyName`, `address`, `licenseNumber`, `deliveryAvailable`, `deliveryFee`

#### Login
```
POST /auth/login
```
```json
{ "email": "ahmed@citizen.com", "password": "Citizen@1234" }
```
Returns `token` — include it as `Authorization: Bearer <token>` on all authenticated routes.

#### Logout
```
POST /auth/logout             [auth]
```

#### Refresh Token
```
POST /auth/refresh            [auth]
```

---

### User Profile

```
GET    /users/me              [auth]           Get current user profile
PUT    /users/me              [auth]           Update profile
POST   /users/change-password [auth]           Change password
POST   /users/upload-avatar   [auth]           Upload profile image (multipart)
```

---

### Medicines

```
GET  /medicines                  [auth]        List medicines (paginated)
GET  /medicines/categories       [auth]        List all categories
GET  /medicines/:id              [auth]        Medicine details + pharmacy availability
POST /admin/medicines            [admin]       Create medicine
PUT  /admin/medicines/:id        [admin]       Update medicine
DELETE /admin/medicines/:id      [admin]       Deactivate medicine
```

**Query params for GET /medicines:**
- `search` — fuzzy search on name/generic/manufacturer
- `category` — filter by category name
- `sort` — `default | name_asc | name_desc | price_asc | price_desc | availability`
- `page`, `per_page` (max 50)
- `requires_prescription` — `true | false`

---

### Pharmacies

```
GET  /pharmacies                 [auth]        List pharmacies (paginated)
GET  /pharmacies/areas           [auth]        All available areas with counts
GET  /pharmacies/:id             [auth]        Pharmacy details + inventory + reviews
```

**Query params for GET /pharmacies:**
- `search`, `area`, `sort` (`rating_high | rating_low | name_asc`), `delivery_only`
- `page`, `per_page`

---

### Inventory (Pharmacy only)

```
GET    /inventory                [pharmacy]    My pharmacy's inventory
POST   /inventory                [pharmacy]    Add medicine to inventory
PUT    /inventory/:id            [pharmacy]    Update stock/price
DELETE /inventory/:id            [pharmacy]    Remove from inventory
```

**POST /inventory body:**
```json
{
  "medicineId": "uuid",
  "quantity": 100,
  "price": 5.99,
  "costPrice": 3.50,
  "minimumStock": 20,
  "maximumStock": 500,
  "expiryDate": "2027-12-31"
}
```

Status (`in_stock | low_stock | out_of_stock`) is auto-computed from quantity vs minimumStock.

---

### Orders

```
POST   /orders                   [citizen]     Place an order
GET    /orders                   [auth]        My orders (filtered by role)
GET    /orders/:id               [auth]        Order details
PUT    /orders/:id/status        [pharmacy]    Update order status
DELETE /orders/:id               [citizen]     Cancel order
```

**Status flow:**
```
pending → approved → preparing → ready → delivered
pending → rejected
pending/approved → cancelled
```

**PUT /orders/:id/status body:**
```json
{ "status": "approved", "response": "Ready in 30 minutes" }
```

When an order is `rejected` or `cancelled`, inventory is automatically restored.

---

### Broadcast Requests

Citizens broadcast a medicine need to all pharmacies in the network.

```
POST   /requests                         [citizen]    Create broadcast request
GET    /requests                         [citizen]    My requests
DELETE /requests/:id                     [citizen]    Close request
POST   /requests/:id/accept/:pharmacyId  [citizen]    Accept a pharmacy's response → creates order

GET    /requests/network                 [pharmacy]   View open network requests
POST   /requests/:id/respond             [pharmacy]   Submit price/availability response
```

Requests auto-expire after 2 hours.

---

### Favorites

```
GET    /favorites                [citizen]     List favorites (type: medicine|pharmacy|all)
POST   /favorites                [citizen]     Add to favorites
POST   /favorites/toggle         [citizen]     Toggle favorite (add/remove)
DELETE /favorites/:id            [citizen]     Remove favorite
```

---

### Reviews

```
POST   /reviews                          [citizen]    Submit/update review for pharmacy
GET    /reviews/pharmacy/:pharmacyId     [auth]       Get pharmacy reviews + avg rating
DELETE /reviews/:id                      [citizen]    Delete own review
```

Reviews auto-update the pharmacy's `rating` and `review_count` on save/delete.

---

### Complaints

```
POST   /complaints               [citizen]     File a complaint against a pharmacy
GET    /complaints               [citizen]     My complaints

GET    /admin/complaints         [admin]       All complaints (filterable)
PUT    /admin/complaints/:id     [admin]       Resolve/reject complaint
POST   /admin/complaints/:id/assign [admin]   Assign to self
```

---

### Admin

#### User Management
```
GET    /admin/users              List all users (filter: role, status, search)
GET    /admin/users/:id          User details
PATCH  /admin/users/:id/toggle-active   Activate/deactivate user
DELETE /admin/users/:id          Delete user
```

#### Pharmacy Verification
```
GET    /admin/pharmacies/verification   Pharmacies by status (default: pending)
PUT    /admin/pharmacies/:id/verify     Verify, reject, or suspend pharmacy
```

**PUT body:** `{ "status": "verified", "notes": "License confirmed" }`

#### Statistics
```
GET    /admin/statistics         Platform-wide metrics
```
Returns: total citizens, pharmacies, medicines, orders, revenue, monthly growth, top medicines, open complaints.

#### Reports
```
GET    /admin/reports?type=shortage&startDate=2025-01-01&endDate=2025-01-31
```
Types: `shortage | complaints | orders | pharmacy_performance`

---

## Response Format

All responses follow this envelope:

```json
{
  "success": true,
  "message": "Optional message",
  "data": { ... }
}
```

Errors:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "email": ["The email field is required."] },
  "code": 422
}
```

---

## Error Codes

| Code | Meaning                          |
|------|----------------------------------|
| 200  | OK                               |
| 201  | Created                          |
| 401  | Unauthenticated / token invalid  |
| 403  | Forbidden (wrong role)           |
| 404  | Resource not found               |
| 409  | Conflict (duplicate)             |
| 422  | Validation error                 |
| 500  | Server error                     |

---

## Role Permissions Summary

| Endpoint                       | Citizen | Pharmacy | Admin |
|-------------------------------|---------|----------|-------|
| GET /medicines                | ✅      | ✅       | ✅    |
| POST /orders                  | ✅      | ❌       | ❌    |
| PUT /orders/:id/status        | ❌      | ✅       | ✅    |
| POST /inventory               | ❌      | ✅       | ❌    |
| GET /requests/network         | ❌      | ✅       | ❌    |
| POST /requests/:id/respond    | ❌      | ✅       | ❌    |
| POST /requests                | ✅      | ❌       | ❌    |
| POST /favorites               | ✅      | ❌       | ❌    |
| POST /reviews                 | ✅      | ❌       | ❌    |
| POST /complaints              | ✅      | ❌       | ❌    |
| GET /admin/*                  | ❌      | ❌       | ✅    |

---

## Frontend Integration

Replace localStorage calls with API calls using this pattern:

```javascript
// api-client.js
const API_BASE = 'http://localhost:8000/api/v1';

const APIClient = {
  async request(method, endpoint, body = null) {
    const token = localStorage.getItem('medlink_token');
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(`${API_BASE}${endpoint}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : null,
    });

    if (res.status === 401) {
      localStorage.removeItem('medlink_token');
      window.location.href = '/auth/login.html';
    }

    return res.json();
  },
  get:    (ep)       => APIClient.request('GET',    ep),
  post:   (ep, body) => APIClient.request('POST',   ep, body),
  put:    (ep, body) => APIClient.request('PUT',    ep, body),
  patch:  (ep, body) => APIClient.request('PATCH',  ep, body),
  delete: (ep)       => APIClient.request('DELETE', ep),
};
```

Login and store token:
```javascript
const res  = await APIClient.post('/auth/login', { email, password });
localStorage.setItem('medlink_token', res.data.token);
localStorage.setItem('medlink_role',  res.data.role);
```
