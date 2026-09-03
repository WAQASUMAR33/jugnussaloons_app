# Jugnu Saloon App - Master API & Management Endpoints Documentation

Comprehensive technical specification for all REST API endpoints, POS management routes, and administrative endpoints in the Jugnu Saloon Application.

---

## 1. System Architecture & Base Configuration

### Base URL
* **Local Development Server:** `http://127.0.0.1:8000`
* **Production Deployment:** `https://your-domain.com`

### Authentication & Middleware
1. **Public REST APIs (`/api/*`):** No authentication token required for guest/public catalog endpoints.
2. **Customer APIs (`/api/customer/*`):** Uses JSON payload credentials for signup & authentication.
3. **Manager Back-Office & POS (`/manager/*`):** Requires session authentication + role check (`auth`, `role:manager,admin`).
4. **Admin System Settings (`/admin/*`):** Requires session authentication + admin role check (`auth`, `role:admin`).

### Request Headers
For all JSON requests, send:
```http
Accept: application/json
Content-Type: application/json
```
*(Use `multipart/form-data` when uploading image files such as service images, product photos, receipts, or gallery showcase photos).*

---

## 2. API Endpoints Master Index

| # | Group | Method | Endpoint Path | Description | Access Level |
|---|-------|--------|---------------|-------------|--------------|
| 1 | Public API | `GET` | `/api/products` | Fetch retail product catalog | Public |
| 2 | Public API | `GET` | `/api/services` | Fetch salon service catalog | Public |
| 3 | Public API | `GET` | `/api/service-categories` | Fetch service categories list | Public |
| 4 | Public API | `GET` | `/api/bank-accounts` | Fetch official bank account details | Public |
| 5 | Public API | `GET` | `/api/galleries` | Fetch active showcase gallery | Public |
| 5 | Public API | `POST` | `/api/appointments` | Online front-end appointment booking | Public |
| 6 | Public API | `POST` | `/api/contact` | Submit contact / inquiry form | Public |
| 7 | Public API | `POST` | `/api/customer/signup` | Register new customer account | Public |
| 8 | Public API | `POST` | `/api/customer/login` | Customer login authentication | Public |
| 9 | Manager | `GET` | `/manager/dashboard` | Back-office KPIs & revenue dashboard | Manager/Admin |
| 10 | Manager | `GET` | `/manager/service-categories` | List service categories | Manager/Admin |
| 11 | Manager | `POST` | `/manager/service-categories` | Create service category | Manager/Admin |
| 12 | Manager | `PUT` | `/manager/service-categories/{id}` | Update service category | Manager/Admin |
| 13 | Manager | `DELETE` | `/manager/service-categories/{id}` | Delete service category | Manager/Admin |
| 14 | Manager | `GET` | `/manager/services` | List salon services catalog | Manager/Admin |
| 15 | Manager | `POST` | `/manager/services` | Create new salon service | Manager/Admin |
| 16 | Manager | `PUT` | `/manager/services/{id}` | Update salon service details | Manager/Admin |
| 17 | Manager | `DELETE` | `/manager/services/{id}` | Delete salon service | Manager/Admin |
| 18 | Manager | `GET` | `/manager/products` | List product inventory stock | Manager/Admin |
| 19 | Manager | `POST` | `/manager/products` | Add new product to stock | Manager/Admin |
| 20 | Manager | `PUT` | `/manager/products/{id}` | Update product details & price | Manager/Admin |
| 21 | Manager | `DELETE` | `/manager/products/{id}` | Delete product | Manager/Admin |
| 22 | Manager | `GET` | `/manager/accounts` | List customer/supplier/staff accounts | Manager/Admin |
| 23 | Manager | `POST` | `/manager/accounts` | Create financial/customer account | Manager/Admin |
| 24 | Manager | `PUT` | `/manager/accounts/{id}` | Update account details | Manager/Admin |
| 25 | Manager | `DELETE` | `/manager/accounts/{id}` | Delete account | Manager/Admin |
| 26 | Manager | `POST` | `/manager/accounts/{id}/transaction` | Record manual debit/credit ledger | Manager/Admin |
| 27 | Manager | `GET` | `/manager/purchases` | List stock purchase invoices | Manager/Admin |
| 28 | Manager | `POST` | `/manager/purchases` | Record product purchase & increment stock | Manager/Admin |
| 29 | Manager | `GET` | `/manager/sales` | List POS sales invoices | Manager/Admin |
| 30 | Manager | `POST` | `/manager/sales` | Create POS product sale invoice | Manager/Admin |
| 31 | Manager | `GET` | `/manager/appointments` | List appointments & bookings | Manager/Admin |
| 32 | Manager | `POST` | `/manager/appointments` | Create appointment manually | Manager/Admin |
| 33 | Manager | `PUT` | `/manager/appointments/{id}` | Update appointment details | Manager/Admin |
| 34 | Manager | `PATCH` | `/manager/appointments/{id}/status` | Update appointment status | Manager/Admin |
| 35 | Manager | `GET` | `/manager/expenses` | List expense records & categories | Manager/Admin |
| 36 | Manager | `POST` | `/manager/expenses` | Record expense transaction | Manager/Admin |
| 37 | Manager | `GET` | `/manager/payroll` | Staff commission & salary payroll | Manager/Admin |
| 38 | Manager | `POST` | `/manager/payroll` | Generate payroll payment voucher | Manager/Admin |
| 39 | Manager | `GET` | `/manager/reports/{type}` | Financial reports (sales, stock, ledger) | Manager/Admin |
| 40 | Manager | `GET` | `/manager/settings` | Retrieve salon brand settings | Manager/Admin |
| 41 | Manager | `POST` | `/manager/settings` | Update brand logo, name & settings | Manager/Admin |
| 42 | Manager | `GET` | `/manager/galleries` | List gallery photos | Manager/Admin |
| 43 | Manager | `POST` | `/manager/galleries` | Upload new gallery photo | Manager/Admin |
| 44 | Manager | `DELETE` | `/manager/galleries/{id}` | Delete gallery photo | Manager/Admin |
| 45 | Admin | `GET` | `/admin/dashboard` | System admin dashboard overview | Admin |
| 46 | Admin | `GET` | `/admin/users` | List admin users & staff logins | Admin |
| 47 | Admin | `POST` | `/admin/users` | Create new system user | Admin |
| 48 | Admin | `PUT` | `/admin/users/{id}` | Update user details & roles | Admin |
| 49 | Admin | `DELETE` | `/admin/users/{id}` | Delete user account | Admin |
| 50 | Admin | `GET` | `/admin/roles` | List roles & permission matrix | Admin |
| 51 | Admin | `POST` | `/admin/roles` | Create user role | Admin |
| 52 | Admin | `PUT` | `/admin/roles/{id}` | Update role permissions | Admin |
| 53 | Admin | `DELETE` | `/admin/roles/{id}` | Delete role | Admin |

---

## 3. Detailed Specifications: Public REST APIs

### 1. Fetch Products Catalog (`GET /api/products`)
* **Endpoint:** `/api/products`
* **Method:** `GET`
* **Input (Query Params):**
  * `search` *(string, optional)* - Filter products by title.
* **Output Structure (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Matte Clay Styling Pomade 100g",
      "price": 22.5,
      "discount": 10.0,
      "discounted_price": 20.25,
      "stock": 37,
      "image_url": "http://127.0.0.1:8000/storage/products/1786361478_6a79b686e0463.jpg",
      "created_at": "2026-08-07T11:44:52.000000Z"
    }
  ]
}
```

---

### 2. Fetch Services Catalog (`GET /api/services`)
* **Endpoint:** `/api/services`
* **Method:** `GET`
* **Input (Query Params):**
  * `category_id` *(integer, optional)* - Filter by category ID.
  * `search` *(string, optional)* - Filter by title or description.
* **Output Structure (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Executive Haircut & Styling",
      "description": "Precision haircut, hair wash, scalp massage, and blowdry styling.",
      "price": 45.0,
      "discount": 10.0,
      "discounted_price": 40.5,
      "category": {
        "id": 1,
        "title": "Haircuts & Styling"
      },
      "image_url": "http://127.0.0.1:8000/storage/services/1786360684_6a79b36ccd2e8.jpg",
      "created_at": "2026-08-07T11:44:52.000000Z"
    }
  ]
}
```

---

### 3. Fetch Service Categories (`GET /api/service-categories`)
* **Endpoint:** `/api/service-categories`
* **Method:** `GET`
* **Input:** None
* **Output Structure (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Haircuts & Styling",
      "description": "Professional haircutting and styling services for all hair types.",
      "image": "storage/categories/1723465000_sample.jpg",
      "created_at": "2026-08-07T11:44:52.000000Z",
      "updated_at": "2026-08-07T11:44:52.000000Z"
    }
  ]
}
```

---

### 4. Fetch Gallery Showcase (`GET /api/galleries`)
* **Endpoint:** `/api/galleries`
* **Method:** `GET`
* **Input (Query Params):**
  * `category` *(string, optional)* - Category filter e.g. `Bridal`, `Haircut`.
  * `search` *(string, optional)* - Search title or category.
* **Output Structure (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Bridal Hair & Makeup Showcase",
      "category": "Bridal",
      "image_path": "storage/galleries/gallery_1723465000.jpg",
      "image_url": "http://127.0.0.1:8000/storage/galleries/gallery_1723465000.jpg",
      "file_name": "gallery_1723465000.jpg",
      "file_size": 345120,
      "formatted_size": "337.0 KB",
      "sort_order": 0,
      "created_at": "2026-08-08T18:00:00.000000Z"
    }
  ]
}
```

---

### 5. Book Online Appointment (`POST /api/appointments`)
* **Endpoint:** `/api/appointments`
* **Method:** `POST`
* **Input Structure (JSON Body / Form):**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `customer_name` | String | Required | Customer full name |
  | `customer_phone` | String | Required | Mobile number |
  | `customer_email` | String | Optional, Email | Email address |
  | `appointment_date` | Date | Required, `>=today` | Date format `YYYY-MM-DD` |
  | `start_time` | String | Required | Time string e.g. `"14:00"` |
  | `service_ids` | Array[Int] | Required, Min: 1 | Array of service IDs |
  | `notes` | String | Optional | Notes/special requests |
  | `receipt_image` | File | Optional, Max 5MB | Payment receipt upload |

* **Output Structure (`201 Created`):**
```json
{
  "success": true,
  "message": "Appointment created successfully",
  "data": {
    "booking_no": "APT-202608-0007",
    "customer_name": "Ayesha Khan",
    "customer_phone": "03194415757",
    "appointment_date": "2026-08-20",
    "start_time": "14:00",
    "net_amount": 85.5,
    "status": "pending"
  }
}
```

---

### 6. Submit Contact Inquiry (`POST /api/contact`)
* **Endpoint:** `/api/contact`
* **Method:** `POST`
* **Input Structure (JSON Body):**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `name` | String | Required | Customer name |
  | `email` | String | Required, Email | Contact email |
  | `phone` | String | Optional | Contact phone number |
  | `subject` | String | Optional | Message subject |
  | `message` | String | Required, Max 2000 | Message content |

* **Output Structure (`201 Created`):**
```json
{
  "success": true,
  "message": "Thank you for reaching out! Your message has been received.",
  "data": {
    "id": 1,
    "created_at": "2026-08-11T15:03:38.000000Z"
  }
}
```

---

### 7. Customer Sign Up (`POST /api/customer/signup`)
* **Endpoint:** `/api/customer/signup`
* **Method:** `POST`
* **Input Structure (JSON Body):**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `name` | String | Required | Full name |
  | `phone_no1` | String | Required | Primary phone |
  | `username` | String | Required, Unique | Login username |
  | `password` | String | Required, Min: 6 | Account password |
  | `father_name` | String | Optional | Guardian/father name |
  | `address` | String | Optional | Residential address |
  | `date_of_birth` | Date | Optional | `YYYY-MM-DD` |
  | `date_of_anniversary` | Date | Optional | `YYYY-MM-DD` |
  | `card_type` | String | Optional | `No Card`, `Silver`, `Gold`, `Platinum` |
  | `card_no` | String | Optional | Membership card number |
  | `phone_no2` | String | Optional | Secondary phone number |

* **Output Structure (`201 Created`):**
```json
{
  "success": true,
  "message": "Customer registered successfully",
  "data": {
    "id": 12,
    "name": "Sarah Johnson",
    "username": "sarah_j",
    "phone_no1": "03123456789",
    "phone_no2": null,
    "father_name": "Robert Johnson",
    "address": "123 Beauty Ave, Lahore",
    "card_type": "Gold",
    "card_no": null,
    "date_of_birth": "1998-06-20",
    "date_of_anniversary": "2022-10-15",
    "balance": 0.0,
    "created_at": "2026-08-15T10:20:00.000000Z"
  }
}
```

---

### 8. Customer Login (`POST /api/customer/login`)
* **Endpoint:** `/api/customer/login`
* **Method:** `POST`
* **Input Structure (JSON Body):**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `username` *(or `login` / `phone_no1`)* | String | Required | Username or registered phone number |
  | `password` | String | Required | Plain text password |

* **Output Structure (`200 OK`):**
```json
{
  "success": true,
  "message": "Customer logged in successfully",
  "data": {
    "id": 12,
    "name": "Sarah Johnson",
    "username": "sarah_j",
    "phone_no1": "03123456789",
    "phone_no2": null,
    "father_name": "Robert Johnson",
    "address": "123 Beauty Ave, Lahore",
    "card_type": "Gold",
    "card_no": null,
    "date_of_birth": "1998-06-20",
    "date_of_anniversary": "2022-10-15",
    "balance": 0.0
  }
}
```

---

## 4. Detailed Specifications: Manager & POS Endpoints

### 9. Record POS Product Sale (`POST /manager/sales`)
* **Endpoint:** `/manager/sales`
* **Method:** `POST`
* **Access Level:** Manager / Admin
* **Input Structure (Form Data / JSON):**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `account_id` | Int | Required, Exists:accounts | Customer account ID |
  | `sale_date` | Date | Required | Invoice date `YYYY-MM-DD` |
  | `discount` | Numeric | Optional, Min: 0 | Bill discount amount |
  | `received_amount` | Numeric | Required, Min: 0 | Amount paid by customer |
  | `payment_mode` | String | Optional | `Cash` or `Bank` |
  | `extra_amount` | Numeric | Optional, Min: 0 | Bank processing fee or tax |
  | `notes` | String | Optional | Invoice notes |
  | `items` | Array | Required, Min: 1 | Array of items |
  | `items.*.product_id` | Int | Required, Exists:products | Product ID |
  | `items.*.quantity` | Int | Required, Min: 1 | Quantity sold |
  | `items.*.unit_price` | Numeric | Required, Min: 0 | Selling unit price |

* **System Action:** Generates `INV-YYYYMM-XXXX`, decrements product stock, writes customer ledger entry (Debit/Credit balance updates).

---

### 10. Record Product Stock Purchase (`POST /manager/purchases`)
* **Endpoint:** `/manager/purchases`
* **Method:** `POST`
* **Access Level:** Manager / Admin
* **Input Structure:**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `account_id` | Int | Required, Exists:accounts | Supplier / Vendor account ID |
  | `purchase_date` | Date | Required | Invoice date `YYYY-MM-DD` |
  | `paid_amount` | Numeric | Required, Min: 0 | Amount paid to supplier |
  | `items` | Array | Required, Min: 1 | Array of purchased products |
  | `items.*.product_id` | Int | Required | Product ID |
  | `items.*.quantity` | Int | Required, Min: 1 | Quantity purchased |
  | `items.*.unit_price` | Numeric | Required, Min: 0 | Unit purchase price |

* **System Action:** Generates `PUR-YYYYMM-XXXX`, increments inventory stock, updates supplier payable ledger.

---

### 11. Create / Manage Salon Appointment (`POST /manager/appointments`)
* **Endpoint:** `/manager/appointments`
* **Method:** `POST`
* **Access Level:** Manager / Admin
* **Input Structure:**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `account_id` | Int | Required | Customer Account ID |
  | `employee_id` | Int | Required | Assigned Staff/Employee Account ID |
  | `appointment_date` | Date | Required | Date `YYYY-MM-DD` |
  | `start_time` | String | Required | Time e.g. `"14:00"` |
  | `services` | Array | Required, Min: 1 | Service IDs |
  | `paid_amount` | Numeric | Optional | Advance paid amount |
  | `status` | String | Required | `pending`, `confirmed`, `completed`, `cancelled` |

---

### 12. Record Account Ledger Transaction (`POST /manager/accounts/{id}/transaction`)
* **Endpoint:** `/manager/accounts/{account}/transaction`
* **Method:** `POST`
* **Access Level:** Manager / Admin
* **Input Structure:**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `type` | String | Required | `debit` or `credit` |
  | `amount` | Numeric | Required, Min: 0.01 | Transaction amount |
  | `date` | Date | Required | Transaction date |
  | `description` | String | Required | Entry reference & narration |

---

### 13. Process Staff Payroll (`POST /manager/payroll`)
* **Endpoint:** `/manager/payroll`
* **Method:** `POST`
* **Access Level:** Manager / Admin
* **Input Structure:**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `employee_id` | Int | Required | Staff Account ID |
  | `month` | String | Required | Format `YYYY-MM` |
  | `basic_salary` | Numeric | Required | Base monthly salary |
  | `commission_amount` | Numeric | Optional | Accumulated service commissions |
  | `bonus` | Numeric | Optional | Bonus payout |
  | `deductions` | Numeric | Optional | Salary deductions/advances |
  | `net_salary` | Numeric | Required | Final payable salary |

---

### 14. Financial & Stock Reports (`GET /manager/reports/{type}`)
* **Endpoint Path:**
  * Sales Report: `/manager/reports/sales`
  * Stock Report: `/manager/reports/stock`
  * Services Analytics: `/manager/reports/services`
  * Account Ledger Report: `/manager/reports/ledger`
  * Purchases Report: `/manager/reports/purchases`
* **Method:** `GET`
* **Query Parameters:**
  * `start_date` *(date, optional)* - Filter range start
  * `end_date` *(date, optional)* - Filter range end
  * `account_id` *(integer, optional)* - Filter by customer/vendor/staff

---

## 5. Detailed Specifications: Admin User & Security Endpoints

### 15. Create System User Account (`POST /admin/users`)
* **Endpoint:** `/admin/users`
* **Method:** `POST`
* **Access Level:** Admin
* **Input Structure:**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `name` | String | Required, Max: 255 | User name |
  | `email` | String | Required, Unique:users | Login email address |
  | `password` | String | Required, Min: 8 | Account password |
  | `roles` | Array[Int] | Required, Min: 1 | Assigned Role IDs |

---

### 16. Create User Role & Permissions (`POST /admin/roles`)
* **Endpoint:** `/admin/roles`
* **Method:** `POST`
* **Access Level:** Admin
* **Input Structure:**
  | Field | Type | Rules | Description |
  |-------|------|-------|-------------|
  | `name` | String | Required, Unique:roles | Role display title |
  | `slug` | String | Required, Unique:roles | Role identifier key e.g. `cashier` |
  | `permissions` | Array[Int] | Required | Array of Permission IDs |

---

## 6. Global Response Status Codes Reference

| HTTP Code | Name | Scenario / Meaning |
|-----------|------|--------------------|
| `200 OK` | Success | GET requests succeeded or customer logged in |
| `201 Created` | Created | Resource successfully created (Sale, Appointment, Customer, Contact) |
| `302 Found` | Redirect | Web Form submission successful (Redirect with flash message) |
| `401 Unauthorized` | Unauthorized | Unauthenticated user or invalid customer login credentials |
| `403 Forbidden` | Forbidden | Authenticated user lacks mandatory role/permission |
| `422 Unprocessable` | Validation Failure | Invalid input payload, duplicate username, or reserved time slot |
| `500 Internal Error` | Server Failure | Uncaught exception or database error |



