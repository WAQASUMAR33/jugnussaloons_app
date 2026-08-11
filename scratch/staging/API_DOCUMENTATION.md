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
      "created_at": "2026-08-07T11:44:52.000000Z",
      "updated_at": "2026-08-07T11:44:52.000000Z"
    }
  ]
}
```

---

### 4. Book Appointment
* **Method:** `POST`
* **Endpoint:** `/api/appointments`
* **Request Body (JSON):**
```json
{
  "customer_name": "Michael Scott",
  "customer_phone": "03001112233",
  "customer_email": "michael@example.com",
  "appointment_date": "2026-08-20",
  "start_time": "14:00",
  "service_ids": [1, 2],
  "notes": "Prefers senior stylist."
}
```
* **Response (201 Created):**
```json
{
  "success": true,
  "message": "Appointment booked successfully! Our team will contact you for confirmation.",
  "data": {
    "booking_no": "APT-202608-0006",
    "appointment_date": "2026-08-20",
    "start_time": "14:00",
    "net_amount": 66,
    "status": "pending"
  }
}
```

---

### 5. Submit Contact Form
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
