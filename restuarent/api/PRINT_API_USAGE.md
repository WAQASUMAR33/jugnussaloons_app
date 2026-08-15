# Print API Usage Guide

## ✅ **How to Use `api/print.php`**

### **Purpose:**
Generate receipt data or print customer receipts to thermal printers.

---

## 📋 **API Endpoint:**
```
POST /api/print.php
```

**Note:** GET requests are also supported, but POST is recommended.

---

## 📝 **Request Format**

### **Option 1: POST with JSON (Recommended)**

```javascript
fetch('/api/print.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    order_id: 123
  })
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    console.log('Receipt:', data);
    // data.items contains order items
    // data.receipt_data contains base64 receipt
  } else {
    console.error('Error:', data.message);
  }
});
```

### **Option 2: POST with Form Data**

```javascript
const formData = new FormData();
formData.append('order_id', '123');

fetch('/api/print.php', {
  method: 'POST',
  body: formData
})
```

### **Option 3: GET with Query String**

```javascript
const orderId = 123;
fetch(`/api/print.php?order_id=${orderId}`, {
  method: 'GET'
})
```

---

## 📥 **Required Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `order_id` | int/string | ✅ Yes | Order ID (numeric or "ORD-123" format) |
| `orderid` | string | ❌ Alternative | Alternative to order_id ("ORD-123" format) |
| `id` | int | ❌ Alternative | Alternative to order_id (numeric only) |

---

## 📤 **Response Format**

### **Success Response:**
```json
{
  "success": true,
  "message": "Receipt generated successfully",
  "receipt_data": "base64_encoded_receipt_string",
  "order": {
    "order_id": 123,
    "order_number": "ORD-123",
    "order_type": "Dine In",
    "branch_name": "Main Branch",
    "table_number": "5",
    "created_at": "2024-12-01 12:00:00"
  },
  "items": [
    {
      "item_id": 1,
      "dish_name": "Chicken Biryani",
      "quantity": 2,
      "price": 50.00,
      "total_amount": 100.00
    }
  ],
  "items_count": 1,
  "totals": {
    "subtotal": 100.00,
    "service_charge": 10.00,
    "discount": 5.00,
    "net_total": 105.00
  }
}
```

### **Error Response:**
```json
{
  "success": false,
  "message": "Error message here",
  "order_id": 123,
  "items": []
}
```

---

## ⚠️ **Common Errors**

### **1. "Invalid order ID"**
**Cause:** No order_id parameter provided

**Solution:**
```javascript
// Make sure you're passing order_id
fetch('/api/print.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ order_id: 123 }) // ← Must include this
})
```

### **2. "Cannot print receipt: No order items found"**
**Cause:** Order exists but has no items

**Solution:**
- Check if the order actually has items
- Regenerate the bill if items were deleted
- Verify the order_id is correct

---

## 🔧 **Full Example**

```javascript
async function printReceipt(orderId) {
  try {
    const response = await fetch('/api/print.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId
      })
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || 'Failed to generate receipt');
    }

    // Check if items exist
    if (!data.items || data.items.length === 0) {
      throw new Error('No items found in receipt');
    }

    // Use the receipt data
    console.log('Receipt generated:', data);
    console.log('Items:', data.items);
    console.log('Totals:', data.totals);

    return data;

  } catch (error) {
    console.error('Print Receipt Error:', error);
    throw error;
  }
}

// Usage
printReceipt(123)
  .then(receipt => {
    // Show receipt modal or print
  })
  .catch(error => {
    alert(error.message);
  });
```

---

## ✅ **Key Points**

1. ✅ **Always use POST** with JSON body for best compatibility
2. ✅ **Always check `data.success`** before using response
3. ✅ **Always validate items exist** before showing receipt
4. ✅ **Include order_id** in request body or query string

---

**Last Updated:** December 2024

