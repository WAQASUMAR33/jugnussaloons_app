# Simple Frontend Changes Guide

## 🎯 **What You Need to Change**

Replace your old print function with a call to the new **`print_bill_direct.php`** API.

---

## 📝 **Simple Example**

### **Before (Old Way):**
```javascript
// Old print function
function printReceipt(orderId) {
  fetch('/api/print.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Receipt printed!');
    }
  });
}
```

### **After (New Way - Direct USB Printing):**
```javascript
// New direct USB print function
function printReceipt(orderId) {
  fetch('/api/print_bill_direct.php', {  // ← Changed URL here
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Receipt printed!');
    } else {
      alert('Error: ' + data.message);
    }
  });
}
```

**That's it!** Just change `/api/print.php` to `/api/print_bill_direct.php`

---

## 🔧 **Complete Ready-to-Use Function**

Copy and paste this function into your JavaScript file:

```javascript
/**
 * Print bill receipt directly to USB printer
 * @param {number} orderId - Order ID to print
 */
async function printBillDirect(orderId) {
  try {
    const response = await fetch('/api/print_bill_direct.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId
      })
    });

    const data = await response.json();

    if (data.success) {
      alert('✅ Receipt printed successfully!');
      return true;
    } else {
      alert('❌ Print failed: ' + data.message);
      return false;
    }
  } catch (error) {
    alert('Error: ' + error.message);
    return false;
  }
}
```

---

## 📋 **How to Use It**

### **Example 1: Print Button**

```html
<button onclick="printBillDirect(123)">Print Receipt</button>
```

### **Example 2: Print After Bill Generation**

```javascript
// After generating a bill
function generateBill(orderId) {
  // ... your bill generation code ...
  
  // Then print the receipt
  printBillDirect(orderId);
}
```

### **Example 3: Print from Order List**

```javascript
function printOrderReceipt(orderId) {
  if (confirm('Print receipt for order #' + orderId + '?')) {
    printBillDirect(orderId);
  }
}
```

---

## 🎨 **With Loading Indicator (Better UX)**

```javascript
async function printBillDirect(orderId) {
  // Show loading
  const btn = event.target;
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Printing...';

  try {
    const response = await fetch('/api/print_bill_direct.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId })
    });

    const data = await response.json();

    if (data.success) {
      alert('✅ Receipt printed!');
    } else {
      alert('❌ Error: ' + data.message);
    }
  } catch (error) {
    alert('Error: ' + error.message);
  } finally {
    // Restore button
    btn.disabled = false;
    btn.textContent = originalText;
  }
}
```

---

## 📱 **jQuery Version (If You Use jQuery)**

```javascript
function printBillDirect(orderId) {
  $.ajax({
    url: '/api/print_bill_direct.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ order_id: orderId }),
    success: function(data) {
      if (data.success) {
        alert('✅ Receipt printed!');
      } else {
        alert('❌ Error: ' + data.message);
      }
    },
    error: function(xhr, status, error) {
      alert('Error: ' + error);
    }
  });
}
```

---

## 🔄 **Quick Comparison**

| What | Old API | New API |
|------|---------|---------|
| **URL** | `/api/print.php` | `/api/print_bill_direct.php` |
| **Parameters** | `order_id`, `printer_id` (optional) | `order_id` (required) |
| **Printer Selection** | Manual | Automatic (finds USB printer) |
| **User Interaction** | May need to select printer | None (automatic) |

---

## ✅ **Checklist**

- [ ] Replace `/api/print.php` with `/api/print_bill_direct.php` in your code
- [ ] Remove any `printer_id` parameter (not needed)
- [ ] Test with a valid `order_id`
- [ ] Make sure your USB printer is configured in database

---

## 🚀 **Quick Test**

Open browser console (F12) and run:

```javascript
fetch('/api/print_bill_direct.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ order_id: 123 })
})
.then(r => r.json())
.then(d => console.log(d));
```

**Expected Result:**
```json
{
  "success": true,
  "message": "Receipt printed successfully to USB printer",
  "order_number": "ORD-123"
}
```

---

## 📞 **Common Issues**

### **Error: "No USB receipt printer configured"**
- **Solution:** Add printer to database (see `USB_PRINTER_SQL_FIX.md`)

### **Error: "Invalid order ID"**
- **Solution:** Make sure you're passing a valid `order_id`

### **Nothing happens when clicking print**
- **Solution:** Check browser console (F12) for JavaScript errors

---

## 💡 **That's All!**

Just change the API URL from `/api/print.php` to `/api/print_bill_direct.php` and you're done!

**No other changes needed** - the API automatically finds and uses your USB printer.

