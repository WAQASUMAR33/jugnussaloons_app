# Salon App - Front-End API Documentation

## Base URL
`http://127.0.0.1:8000` (Local) or `https://your-domain.com` (Production)

## Global Headers
For all `POST` requests or JSON requests, send:
```http
Accept: application/json
Content-Type: application/json
```

---

## Endpoints

### 1. Fetch Products Catalog
* **Method:** `GET`
* **Endpoint:** `/api/products`
* **Query Params:** `search` (optional) - e.g. `/api/products?search=Pomade`
* **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Matte Clay Styling Pomade 100g",
      "price": 22.5,
      "discount": 10,
      "discounted_price": 20.25,
      "stock": 37,
      "image_url": "http://127.0.0.1:8000/storage/products/1786361478_6a79b686e0463.jpg",
      "created_at": "2026-08-07T11:44:52.000000Z"
    }
  ]
}
```

---

### 2. Fetch Services Catalog
* **Method:** `GET`
* **Endpoint:** `/api/services`
* **Query Params:** 
  * `category_id` (optional) - e.g. `/api/services?category_id=1`
  * `search` (optional) - e.g. `/api/services?search=haircut`
* **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Executive Haircut & Styling",
      "description": "Precision haircut, hair wash, scalp massage, and professional blowdry styling.",
      "price": 45,
      "discount": 10,
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

### 3. Fetch Service Categories
* **Method:** `GET`
* **Endpoint:** `/api/service-categories`
* **Response (200 OK):**
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

### 4. Fetch Gallery Showcase
* **Method:** `GET`
* **Endpoint:** `/api/galleries`
* **Query Parameters (Optional):**
  * `category` (string, e.g. `Haircut`, `Facial Spa`, `Bridal`)
  * `search` (string, filter by title or category)
* **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Bridal Hair & Makeup Showcase",
      "category": "Bridal",
      "image_path": "storage/galleries/gallery_1723465000.jpg",
      "image_url": "http://localhost:8000/storage/galleries/gallery_1723465000.jpg",
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

### 5. Book Appointment
* **Method:** `POST`
* **Endpoint:** `/api/appointments`
* **Request Body (JSON):**
```json
{
  "customer_name": "Ayesha Khan",
  "customer_phone": "03194415757",
  "customer_email": "ayesha@example.com",
  "appointment_date": "2026-08-15",
  "start_time": "14:00",
  "service_ids": [1],
  "notes": "Service Requested: Royal HD Airbrush Bridal Glam"
}
```
* **Success Response (201 Created / 200 OK):**
```json
{
  "success": true,
  "message": "Appointment created successfully",
  "data": {
    "booking_no": "APT-202608-0007",
    "customer_name": "Ayesha Khan",
    "customer_phone": "03194415757",
    "appointment_date": "2026-08-15",
    "start_time": "14:00",
    "net_amount": 2700,
    "status": "pending"
  }
}
```
* **Validation Error Response (422 Unprocessable Content):**
```json
{
  "success": false,
  "message": "The selected time slot is no longer available.",
  "errors": {
    "start_time": [
      "Selected time slot is already reserved."
    ]
  }
}
```

---

### 6. Submit Contact Form
* **Method:** `POST`
* **Endpoint:** `/api/contact`
* **Request Body (JSON):**
```json
{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "03009876543",
  "subject": "General Inquiry",
  "message": "I would like to inquire about wedding packages."
}
```
* **Response (201 Created):**
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

### 7. Customer Sign Up
* **Method:** `POST`
* **Endpoint:** `/api/customer/signup`
* **Request Body (JSON):**
```json
{
  "name": "Sarah Johnson",
  "phone_no1": "03123456789",
  "username": "sarah_j",
  "password": "password123",
  "father_name": "Robert Johnson",
  "address": "123 Beauty Ave, Lahore",
  "card_type": "Gold",
  "date_of_birth": "1998-06-20",
  "date_of_anniversary": "2022-10-15"
}
```
* **Response (201 Created):**
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
    "balance": 0,
    "created_at": "2026-08-15T10:20:00.000000Z"
  }
}
```

---

### 8. Customer Login
* **Method:** `POST`
* **Endpoint:** `/api/customer/login`
* **Request Body (JSON):**
```json
{
  "username": "sarah_j",
  "password": "password123"
}
```
*(Note: `username` can also be passed as `login` or `phone_no1`)*

* **Response (200 OK):**
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
    "balance": 0
  }
}
```
* **Invalid Credentials Response (401 Unauthorized):**
```json
{
  "success": false,
  "message": "Invalid username/phone or password."
}
```

