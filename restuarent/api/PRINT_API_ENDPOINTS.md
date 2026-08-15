# Print API Endpoints - Complete Documentation

## Overview
This document lists all print-related API endpoints in the restaurant management system. All endpoints support both GET and POST requests, with JSON body support for POST requests.

---

## 1. Print Customer Bill/Receipt (Network/USB)

**Endpoint:** `POST/GET /api/print.php`

**Purpose:** Prints final customer receipts/bills to network or USB thermal printers.

**Parameters:**
- `order_id` (int/string, **required**) - Order ID (numeric or "ORD-123" format)
- `orderid` (string, optional) - Alternative to order_id ("ORD-123" format)
- `id` (int, optional) - Alternative to order_id (numeric only)
- `printer_id` (int, optional) - Printer ID from printers table
- `printer_ip` (string, optional) - Network printer IP address
- `printer_name` (string, optional) - USB printer name
- `usb_port` (string, optional) - USB COM port (e.g., COM1, USB002)
- `connection_type` (string, optional) - 'network' or 'usb' (default: 'network')

**Example Request:**
```json
POST /api/print.php
Content-Type: application/json

{
  "order_id": 123
}
```

**Example with Printer:**
```json
{
  "order_id": 123,
  "printer_id": 5
}
```

**Example with USB:**
```json
{
  "order_id": 123,
  "connection_type": "usb",
  "printer_name": "XP-80C"
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Receipt sent to printer successfully",
  "printer_info": {
    "connection_type": "network",
    "printer_ip": "192.168.1.100",
    "printer_port": 9100
  },
  "order_number": "ORD-123"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Order not found"
}
```

---

## 2. Print Customer Bill/Receipt (Direct USB)

**Endpoint:** `POST/GET /api/print_bill_direct.php`

**Purpose:** Automatically finds and prints to USB receipt printer without user interaction. Automatically selects the correct USB printer based on branch_id and terminal.

**Parameters:**
- `order_id` (int/string, **required**) - Order ID (numeric or "ORD-123" format)
- `orderid` (string, optional) - Alternative to order_id
- `id` (int, optional) - Alternative to order_id
- `branch_id` (int, optional) - Branch ID to find branch-specific printer
- `terminal` (int, optional) - Terminal number (default: 1)

**Example Request:**
```json
POST /api/print_bill_direct.php
Content-Type: application/json

{
  "order_id": 123,
  "branch_id": 1,
  "terminal": 1
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Receipt printed successfully to USB printer",
  "printer_info": {
    "connection_type": "usb",
    "method": "com_port_direct",
    "usb_port": "COM3",
    "bytes_written": 1024
  },
  "order_number": "ORD-123",
  "printer_used": {
    "printer_id": 5,
    "printer_name": "USB Receipt Printer",
    "connection_type": "usb"
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "No USB receipt printer configured. Please configure a USB printer in the printer management section.",
  "hint": "Add a printer with type='receipt' and connection_type='usb' in the printers table"
}
```

---

## 3. Print Kitchen Receipt (KOT) - Single Kitchen

**Endpoint:** `POST/GET /api/print_kitchen_receipt.php`

**Purpose:** Prints Kitchen Order Ticket (KOT) to a specific kitchen's printer. Automatically finds the correct printer based on kitchen_id.

**Parameters:**
- `order_id` (int, **required**) - Order ID
- `kitchen_id` (int, **required**) - Kitchen ID
- `branch_id` (int, optional) - Branch ID

**Example Request:**
```json
POST /api/print_kitchen_receipt.php
Content-Type: application/json

{
  "order_id": 123,
  "kitchen_id": 10,
  "branch_id": 1
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Receipt sent to printer successfully",
  "printer_ip": "192.168.1.101",
  "printer_port": 9100,
  "kitchen_name": "Fast Food Kitchen",
  "order_number": "ORD-123",
  "items_count": 5,
  "bytes_written": 512,
  "total_bytes": 512
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Printer IP not configured for kitchen: Fast Food Kitchen (ID: 10)"
}
```

---

## 4. Print Kitchen Receipt (KOT) - All Kitchens (Batch)

**Endpoint:** `POST/GET /pos/print.php`

**Purpose:** Automatically detects all kitchens that have items in an order and prints KOT to each kitchen's printer. Returns summary of all print results.

**Parameters:**
- `id` (int, **required**) - Order ID

**Example Request:**
```json
POST /pos/print.php
Content-Type: application/json

{
  "id": 123
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Printed to 3 kitchen(s), 0 error(s)",
  "results": [
    {
      "kitchen_id": 10,
      "success": true,
      "message": "KOT sent to printer successfully",
      "kitchen_name": "Fast Food Kitchen",
      "printer_ip": "192.168.1.101"
    },
    {
      "kitchen_id": 11,
      "success": true,
      "message": "KOT sent to printer successfully",
      "kitchen_name": "Beverage Kitchen",
      "printer_ip": "192.168.1.102"
    },
    {
      "kitchen_id": 12,
      "success": true,
      "message": "KOT sent to printer successfully",
      "kitchen_name": "Dessert Kitchen",
      "printer_ip": "192.168.1.103"
    }
  ],
  "order_id": 123
}
```

**Partial Success Response:**
```json
{
  "success": false,
  "message": "Printed to 2 kitchen(s), 1 error(s)",
  "results": [
    {
      "kitchen_id": 10,
      "success": true,
      "message": "KOT sent to printer successfully",
      "kitchen_name": "Fast Food Kitchen",
      "printer_ip": "192.168.1.101"
    },
    {
      "kitchen_id": 11,
      "success": false,
      "message": "Could not connect to printer 192.168.1.102:9100",
      "printer_ip": "192.168.1.102"
    }
  ],
  "order_id": 123
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "No kitchens found for this order"
}
```

---

## 5. Print Cancel/Addition Receipt

**Endpoint:** `POST/GET /pos/print_cancel.php`

**Purpose:** Prints cancellation or addition receipts to kitchen printers when items are cancelled or added to an order.

**Parameters:**
- `item_id` (int, **required**) - Order item ID
- `title` (string, **required**) - Title of the receipt (e.g., "Order Cancellation", "Order Addition")
- `qnty` (int, **required**) - Quantity to display

**Example Request:**
```json
POST /pos/print_cancel.php
Content-Type: application/json

{
  "item_id": 456,
  "title": "Order Cancellation",
  "qnty": 2
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Printed to 1 printer(s), 0 error(s)",
  "results": [
    {
      "kitchen": "Fast Food Kitchen",
      "success": true,
      "message": "Receipt sent to printer successfully",
      "printer_ip": "192.168.1.101"
    }
  ],
  "title": "Order Cancellation"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Item ID and title are required"
}
```

---
    
## 6. Print Kitchen Function (Internal/Reusable)

**File:** `api/print_kitchen_function.php`

**Purpose:** Reusable PHP function that can be called directly from other PHP files. This is NOT an HTTP endpoint, but a function used internally by other print APIs.

**Function Signature:**
```php
function print_kitchen_receipt_direct($connection, $order_id, $kitchen_id, $branch_id = 0)
```

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'string',
    'printer_ip' => 'string',
    'printer_port' => int,
    'kitchen_name' => 'string',
    'order_number' => 'string',
    'items_count' => int,
    'bytes_written' => int,
    'total_bytes' => int
]
```

**Usage:**
This function is automatically called by:
- `api/print_kitchen_receipt.php`
- `pos/print.php`
- `api/create_order.php`
- `api/create_order_with_kitchen.php`

---

## Common Features

### Error Handling
All endpoints include:
- Proper error handling with try-catch blocks
- JSON error responses
- HTTP status codes (400, 404, 500)
- Detailed error messages
- Troubleshooting hints

### CORS Support
All endpoints include CORS headers via `cors_headers.php` for cross-origin requests.

### Output Buffering
All endpoints use output buffering to prevent HTML/whitespace in JSON responses.

### Database Compatibility
All endpoints support both:
- `order_items` table (new structure)
- `orderdetails` table (legacy structure)

### Printer Connection Types
- **Network Printers:** IP-based printing via socket connection (default port 9100)
- **USB Printers:** COM port or Windows printer name

### ESC/POS Commands
All receipts use ESC/POS commands for thermal printer formatting:
- Font sizing (normal, double height/width)
- Bold text
- Alignment (left, center)
- Paper cutting
- Line spacing

---

## Response Format Standards

### Success Response Structure
```json
{
  "success": true,
  "message": "Descriptive success message",
  "printer_info": { /* printer details */ },
  "order_number": "ORD-123",
  /* additional fields as needed */
}
```

### Error Response Structure
```json
{
  "success": false,
  "message": "Descriptive error message",
  "hint": "Helpful hint for fixing the issue",
  "troubleshooting": { /* troubleshooting steps */ }
}
```

---

## Testing Endpoints

### Test Print Customer Bill
```bash
curl -X POST http://localhost/restuarent/api/print.php \
  -H "Content-Type: application/json" \
  -d '{"order_id": 123}'
```

### Test Print Kitchen Receipt
```bash
curl -X POST http://localhost/restuarent/api/print_kitchen_receipt.php \
  -H "Content-Type: application/json" \
  -d '{"order_id": 123, "kitchen_id": 10}'
```

### Test Batch Print All Kitchens
```bash
curl -X POST http://localhost/restuarent/pos/print.php \
  -H "Content-Type: application/json" \
  -d '{"id": 123}'
```

---

## Notes

1. **Order ID Formats:** All endpoints accept order_id in multiple formats:
   - Numeric: `123`
   - String with prefix: `"ORD-123"`
   - The API automatically extracts the numeric ID

2. **Printer Selection Priority:**
   - If `printer_id` is provided, uses that printer
   - If `printer_ip` is provided, uses that IP
   - For kitchen printing, automatically finds printer from kitchen configuration
   - For USB printing, automatically finds USB printer from database

3. **Network Printer Ports:** Default port is 9100, but the system tries alternative ports (515, 631, 9101, 9102) if the default fails.

4. **USB Printer Methods:**
   - COM port direct access (COM1, COM2, etc.)
   - Windows printer name
   - USB port name (USB001, USB002, etc.)

5. **Automatic Kitchen Detection:** The batch print endpoint (`pos/print.php`) automatically detects which kitchens have items for an order by querying the order_items or orderdetails table.

---

## Version Information

- **Document Version:** 1.0
- **Last Updated:** 2024
- **API Compatibility:** All endpoints are production-ready and tested

---

## Support

For printer troubleshooting, see:
- `api/PRINTER_TROUBLESHOOTING.md`
- `api/QUICK_FIX_403.md`

