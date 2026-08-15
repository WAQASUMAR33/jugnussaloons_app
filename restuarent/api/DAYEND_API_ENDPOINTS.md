# Dayend and Closing Date Time API Endpoints - Complete Documentation

## Overview
This document lists all dayend-related API endpoints in the restaurant management system. These APIs handle day-end closing operations, tracking sales, expenses, and balances.

---

## 1. Create/Update Day End Record

**Endpoint:** `POST /api/dayend_management.php`

**Purpose:** Creates a new day-end record or updates an existing one. Automatically calculates sales data and closing balance if not provided.

**Parameters:**
- `id` (int, optional) - Day-end ID. If empty, creates new record. If provided, updates existing.
- `branch_id` (int, **required**) - Branch ID
- `opening_balance` (float, optional) - Opening balance for the day (default: 0)
- `expences` (float, optional) - Total expenses for the day (default: 0, auto-calculated if not provided)
- `total_cash` (float, optional) - Total cash sales (default: 0, auto-calculated if not provided)
- `total_easypaisa` (float, optional) - Total online/easypaisa sales (default: 0, auto-calculated if not provided)
- `total_bank` (float, optional) - Total bank transfers (default: 0, auto-calculated if not provided)
- `credit_sales` (float, optional) - Credit sales amount (default: 0, auto-calculated if not provided)
- `total_sales` (float, optional) - Total sales amount (default: 0, auto-calculated if not provided)
- `total_receivings` (float, optional) - Total receivings (default: 0)
- `drawings` (float, optional) - Drawings amount (default: 0)
- `closing_balance` (float, optional) - Closing balance (default: 0, auto-calculated if not provided)
- `closing_date_time` (string, optional) - Closing date and time in format `YYYY-MM-DD HH:MM:SS` or `YYYY-MM-DDTHH:MM:SS` (default: current date/time)
- `closing_by` (int, optional) - User ID who closed the day (default: 0)
- `note` (string, optional) - Optional note

**Auto-Calculation Features:**
- If sales values are not provided (all 0), the API automatically calculates:
  - `total_cash` - From orders/bills with payment method = 'cash'
  - `total_easypaisa` - From orders/bills with payment method = 'easypaisa', 'online', 'card', etc.
  - `total_bank` - From orders/bills with payment method = 'bank', 'transfer', etc.
  - `credit_sales` - From orders/bills with payment method = 'credit' or unpaid bills
  - `total_sales` - Sum of all sales
- If `expences` is not provided (0), automatically calculates from expenses table
- If `closing_balance` is not provided (0), automatically calculates: `opening_balance + total_sales + total_receivings - expences - drawings`
- Date filtering: Only includes orders created after the last dayend's `closing_date_time` (or today if no dayend exists)

**Example Request (Create):**
```json
POST /api/dayend_management.php
Content-Type: application/json

{
  "branch_id": 1,
  "opening_balance": 1000.00,
  "closing_by": 5,
  "note": "End of day closing"
}
```

**Example Request (Update):**
```json
POST /api/dayend_management.php
Content-Type: application/json

{
  "id": 10,
  "branch_id": 1,
  "opening_balance": 1000.00,
  "expences": 50.00,
  "total_cash": 500.00,
  "total_easypaisa": 200.00,
  "total_bank": 0.00,
  "credit_sales": 100.00,
  "total_sales": 800.00,
  "total_receivings": 0.00,
  "drawings": 0.00,
  "closing_balance": 1750.00,
  "closing_date_time": "2024-01-15 23:59:59",
  "closing_by": 5,
  "note": "Updated closing"
}
```

**Success Response (Create):**
```json
{
  "status": "success",
  "message": "Day-end record created successfully",
  "id": 10,
  "data": {
    "branch_id": 1,
    "opening_balance": 1000.00,
    "expences": 50.00,
    "total_cash": 500.00,
    "total_easypaisa": 200.00,
    "total_bank": 0.00,
    "credit_sales": 100.00,
    "total_sales": 800.00,
    "total_receivings": 0.00,
    "drawings": 0.00,
    "closing_balance": 1750.00,
    "closing_date_time": "2024-01-15 23:59:59",
    "orders_updated": 25
  }
}
```

**Success Response (Update):**
```json
{
  "status": "success",
  "message": "Day-end record updated successfully",
  "id": 10
}
```

**Error Response:**
```json
{
  "status": "error",
  "message": "Branch ID is required"
}
```

**Notes:**
- When a new dayend is created, all orders with `sts = 0` for the branch are updated to `sts = dayend_id`
- This marks orders as processed in the dayend
- The `closing_date_time` is used by other APIs to filter "today's" sales (sales after the last dayend)

---

## 2. Get Day End Records

**Endpoint:** `GET/POST /api/get_dayend.php`

**Purpose:** Fetches day-end records from the database with optional date filtering.

**Parameters:**
- `branch_id` (int, **required**) - Branch ID to filter records
- `start_date` (string, optional) - Start date in YYYY-MM-DD format
- `end_date` (string, optional) - End date in YYYY-MM-DD format

**Example Request:**
```json
POST /api/get_dayend.php
Content-Type: application/json

{
  "branch_id": 1,
  "start_date": "2024-01-01",
  "end_date": "2024-01-31"
}
```

**Success Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "branch_id": 1,
      "branch_name": "Main Branch",
      "opening_balance": 1000.00,
      "expences": 50.00,
      "total_cash": 500.00,
      "total_easypaisa": 200.00,
      "total_bank": 0.00,
      "credit_sales": 100.00,
      "total_sales": 800.00,
      "total_receivings": 0.00,
      "drawings": 0.00,
      "closing_balance": 1750.00,
      "closing_date_time": "2024-01-15 23:59:59",
      "closing_by": 5,
      "closing_by_name": "John Doe",
      "closing_by_username": "john",
      "note": "End of day closing",
      "created_at": "2024-01-15 23:59:59",
      "updated_at": "2024-01-15 23:59:59"
    }
  ],
  "count": 1
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Branch ID is required and must be a positive integer"
}
```

---

## 3. Get Last Closing Balance

**Endpoint:** `GET/POST /pos/get_last_closing_balance.php`

**Purpose:** Fetches the most recent closing balance and closing date-time for a branch (or all branches if branch_id not provided).

**Parameters:**
- `branch_id` (int, optional) - Branch ID to filter records. If not provided, returns last record across all branches.

**Example Request:**
```json
POST /pos/get_last_closing_balance.php
Content-Type: application/json

{
  "branch_id": 1
}
```

**Success Response:**
```json
{
  "status": "success",
  "closing_balance": 1750.00,
  "closing_date_time": "2024-01-15 23:59:59",
  "branch_id": 1
}
```

**Error Response:**
```json
{
  "status": "error",
  "message": "No records found for branch ID: 1"
}
```

---

## 4. Save Day End (Legacy)

**Endpoint:** `POST /pos/save_dayend.php`

**Purpose:** Legacy endpoint for saving day-end records. Uses object-oriented mysqli style.

**Parameters:** Same as `/api/dayend_management.php`

**Note:** This endpoint is maintained for backward compatibility. New implementations should use `/api/dayend_management.php`.

---

## 5. Get Day End Data by Date Range (Legacy)

**Endpoint:** `POST /pos/get_dayend_data.php`

**Purpose:** Legacy endpoint for fetching day-end records between two dates.

**Parameters:**
- `date1` (string, **required**) - Start date in YYYY-MM-DD format
- `date2` (string, **required**) - End date in YYYY-MM-DD format

**Example Request:**
```json
POST /pos/get_dayend_data.php
Content-Type: application/json

{
  "date1": "2024-01-01",
  "date2": "2024-01-31"
}
```

**Success Response:**
```json
[
  {
    "id": 10,
    "branch_id": 1,
    "opening_balance": 1000.00,
    "expences": 50.00,
    "total_cash": 500.00,
    "total_easypaisa": 200.00,
    "total_bank": 0.00,
    "credit_sales": 100.00,
    "total_sales": 800.00,
    "total_receivings": 0.00,
    "drawings": 0.00,
    "closing_balance": 1750.00,
    "closing_date_time": "2024-01-15 23:59:59",
    "closing_by": 5,
    "note": "End of day closing",
    "created_at": "2024-01-15 23:59:59",
    "updated_at": "2024-01-15 23:59:59"
  }
]
```

**Error Response:**
```json
{
  "status": "error",
  "message": "Start date and end date are required"
}
```

---

## How Closing Date Time Works

### Purpose
The `closing_date_time` field is crucial for filtering "today's" sales and orders. When a dayend is created:

1. **Orders Marking:** All orders with `sts = 0` for the branch are updated to `sts = dayend_id`, marking them as processed.

2. **Sales Filtering:** Other APIs (like `get_sales.php`, `get_dashboard_stats.php`, `get_menu_sales.php`) use the last dayend's `closing_date_time` to filter "today's" data:
   - If a dayend exists: Show only orders/sales created **after** the last `closing_date_time`
   - If no dayend exists: Show only orders/sales from **today** (CURDATE())

### Example Flow

1. **Day 1 (Jan 15):**
   - Opening Balance: 1000.00
   - Sales throughout the day
   - At 11:59 PM, create dayend with `closing_date_time = "2024-01-15 23:59:59"`
   - Closing Balance: 1750.00

2. **Day 2 (Jan 16):**
   - Opening Balance: 1750.00 (from previous dayend)
   - New orders created after "2024-01-15 23:59:59" are considered "today's" orders
   - Dashboard and sales APIs show only orders after the last dayend
   - At end of day, create new dayend with `closing_date_time = "2024-01-16 23:59:59"`

### Date Format

The `closing_date_time` accepts multiple formats:
- `YYYY-MM-DD HH:MM:SS` (e.g., "2024-01-15 23:59:59")
- `YYYY-MM-DDTHH:MM:SS` (e.g., "2024-01-15T23:59:59")
- Any format parseable by PHP's `strtotime()` function

The API automatically normalizes it to `YYYY-MM-DD HH:MM:SS` format for storage.

---

## Common Features

### Error Handling
All endpoints include:
- Proper error handling with try-catch blocks
- JSON error responses
- HTTP status codes (400, 404, 500)
- Detailed error messages
- Error logging

### CORS Support
All endpoints include CORS headers via `cors_headers.php` for cross-origin requests.

### Output Buffering
All endpoints use output buffering to prevent HTML/whitespace in JSON responses.

### Database Connection
- Main APIs (`api/`) use procedural mysqli (`$connection`)
- Legacy APIs (`pos/`) use object-oriented mysqli (`$conn`)
- Both are provided by `config.php`

---

## Response Format Standards

### Success Response Structure
```json
{
  "status": "success" or "success": true,
  "message": "Descriptive success message",
  "id": 10,
  "data": { /* record data */ }
}
```

### Error Response Structure
```json
{
  "status": "error" or "success": false,
  "message": "Descriptive error message"
}
```

---

## Testing Endpoints

### Test Create Dayend
```bash
curl -X POST http://localhost/restuarent/api/dayend_management.php \
  -H "Content-Type: application/json" \
  -d '{
    "branch_id": 1,
    "opening_balance": 1000.00,
    "closing_by": 5
  }'
```

### Test Get Dayend Records
```bash
curl -X POST http://localhost/restuarent/api/get_dayend.php \
  -H "Content-Type: application/json" \
  -d '{
    "branch_id": 1,
    "start_date": "2024-01-01",
    "end_date": "2024-01-31"
  }'
```

### Test Get Last Closing Balance
```bash
curl -X POST http://localhost/restuarent/pos/get_last_closing_balance.php \
  -H "Content-Type: application/json" \
  -d '{"branch_id": 1}'
```

---

## Notes

1. **Auto-Calculation:** The main dayend API automatically calculates sales and expenses if not provided. This ensures accurate dayend records even if some data is missing.

2. **Order Status Update:** When a dayend is created, orders with `sts = 0` are updated to `sts = dayend_id`. This prevents double-counting in future dayends.

3. **Date Filtering:** The `closing_date_time` is used by sales and dashboard APIs to determine what constitutes "today's" data. This ensures that after a dayend, only new orders are shown.

4. **Branch Filtering:** All APIs support branch_id filtering to support multi-branch operations.

5. **Backward Compatibility:** Legacy endpoints in `pos/` directory are maintained for backward compatibility but should be migrated to `api/` endpoints.

---

## Version Information

- **Document Version:** 1.0
- **Last Updated:** 2024
- **API Compatibility:** All endpoints are production-ready and tested

---

## Support

For issues or questions:
- Check error logs for detailed error messages
- Verify database connection in `config.php`
- Ensure branch_id is valid and exists in branches table
- Verify closing_date_time format is correct

