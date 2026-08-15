# Print API Endpoints - Quick Reference

## All Print Endpoints

| # | Endpoint | Method | Purpose | Required Parameters |
|---|----------|--------|---------|---------------------|
| 1 | `/api/print.php` | POST/GET | Print customer bill/receipt (Network/USB) | `order_id` |
| 2 | `/api/print_bill_direct.php` | POST/GET | Print customer bill to USB printer (auto-detect) | `order_id` |
| 3 | `/api/print_kitchen_receipt.php` | POST/GET | Print KOT to specific kitchen | `order_id`, `kitchen_id` |
| 4 | `/pos/print.php` | POST/GET | Print KOT to all kitchens (batch) | `id` (order_id) |
| 5 | `/pos/print_cancel.php` | POST/GET | Print cancellation/addition receipt | `item_id`, `title`, `qnty` |

---

## Quick Examples

### 1. Print Customer Bill
```bash
POST /api/print.php
{"order_id": 123}
```

### 2. Print Customer Bill (USB)
```bash
POST /api/print_bill_direct.php
{"order_id": 123, "branch_id": 1}
```

### 3. Print Kitchen Receipt
```bash
POST /api/print_kitchen_receipt.php
{"order_id": 123, "kitchen_id": 10}
```

### 4. Print All Kitchens
```bash
POST /pos/print.php
{"id": 123}
```

### 5. Print Cancel Receipt
```bash
POST /pos/print_cancel.php
{"item_id": 456, "title": "Order Cancellation", "qnty": 2}
```

---

## Response Format

**Success:**
```json
{"success": true, "message": "...", ...}
```

**Error:**
```json
{"success": false, "message": "...", ...}
```

---

## Full Documentation
See `PRINT_API_ENDPOINTS.md` for complete documentation.

