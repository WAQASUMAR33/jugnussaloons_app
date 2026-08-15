# Frontend Integration Guide - Direct USB Bill Printing

## 🎯 **Quick Answer**

**Yes, you need to update your frontend** to call the new `print_bill_direct.php` API instead of (or in addition to) the regular `print.php` API.

---

## 📝 **What You Need to Change**

### **Option 1: Replace Existing Print Function (Recommended)**

If you currently have a function that calls `/api/print.php`, replace it with this:

```javascript
/**
 * Print bill receipt directly to USB printer
 * @param {number} orderId - Order ID to print
 * @param {number|null} branchId - Branch ID (optional)
 * @param {number} terminal - Terminal number (default: 1)
 * @returns {Promise<boolean>} - Returns true if successful
 */
async function printBillDirect(orderId, branchId = null, terminal = 1) {
  try {
    // Show loading indicator (optional)
    const loadingMsg = document.getElementById('loading-message');
    if (loadingMsg) loadingMsg.textContent = 'Printing receipt...';

    const response = await fetch('/api/print_bill_direct.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId,
        branch_id: branchId,
        terminal: terminal
      })
    });

    const data = await response.json();

    if (data.success) {
      // Success - show success message
      console.log('✅ Receipt printed successfully!');
      console.log('Printer:', data.printer_used?.name || 'USB Printer');
      console.log('Method:', data.printer_info?.method || 'USB');
      
      // Show success notification
      alert('Receipt printed successfully!');
      // OR use your notification system:
      // showNotification('Receipt printed successfully!', 'success');
      
      return true;
    } else {
      // Error - show error message
      console.error('❌ Print failed:', data.message);
      
      // Show error notification
      alert('Failed to print receipt: ' + data.message);
      // OR use your notification system:
      // showNotification('Print failed: ' + data.message, 'error');
      
      // Show troubleshooting if available
      if (data.troubleshooting) {
        console.log('Troubleshooting tips:', data.troubleshooting);
      }
      
      return false;
    }
  } catch (error) {
    console.error('Error printing receipt:', error);
    alert('Error: ' + error.message);
    return false;
  } finally {
    // Hide loading indicator
    const loadingMsg = document.getElementById('loading-message');
    if (loadingMsg) loadingMsg.textContent = '';
  }
}
```

---

### **Option 2: Add New Print Button/Function**

If you want to keep the old print function and add a new "Print Direct" button:

```javascript
/**
 * Print bill receipt directly to USB printer (NEW)
 * Use this for direct USB printing without printer selection
 */
async function printBillDirectUSB(orderId, branchId = null) {
  try {
    const response = await fetch('/api/print_bill_direct.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order_id: orderId,
        branch_id: branchId
      })
    });

    const data = await response.json();

    if (data.success) {
      alert('✅ Receipt printed to USB printer!');
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

// Call this function when user clicks "Print Receipt" button
function onPrintReceiptClick(orderId) {
  printBillDirectUSB(orderId);
}
```

---

## 🔧 **Common Integration Scenarios**

### **Scenario 1: Print Button in Order List**

```html
<!-- HTML -->
<button onclick="printReceipt(123)" class="btn-print">
  Print Receipt
</button>
```

```javascript
// JavaScript
function printReceipt(orderId) {
  // Get branch_id and terminal from your current context
  const branchId = getCurrentBranchId(); // Your function to get branch ID
  const terminal = getCurrentTerminal(); // Your function to get terminal
  
  printBillDirect(orderId, branchId, terminal);
}
```

---

### **Scenario 2: Print Button in Bill/Invoice View**

```html
<!-- HTML -->
<div class="bill-actions">
  <button onclick="printBill(<?php echo $order_id; ?>)" class="btn-primary">
    <i class="icon-printer"></i> Print Receipt
  </button>
</div>
```

```javascript
// JavaScript
function printBill(orderId) {
  // Get branch_id from the order data or current context
  const branchId = document.getElementById('branch-id')?.value || null;
  
  printBillDirect(orderId, branchId)
    .then(success => {
      if (success) {
        // Optional: Update UI to show receipt was printed
        updatePrintStatus(orderId, 'printed');
      }
    });
}
```

---

### **Scenario 3: Print After Bill Generation**

```javascript
// After generating a bill, automatically print it
async function generateBillAndPrint(orderId) {
  try {
    // Step 1: Generate bill
    const billResponse = await fetch('/api/bills_management.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        total_amount: calculateTotal(),
        // ... other bill data
      })
    });
    
    const billData = await billResponse.json();
    
    if (billData.success) {
      // Step 2: Print receipt directly
      await printBillDirect(orderId);
      
      // Show success message
      showMessage('Bill generated and receipt printed!');
    }
  } catch (error) {
    console.error('Error:', error);
    showError('Failed to generate bill or print receipt');
  }
}
```

---

### **Scenario 4: Using with jQuery (if you use jQuery)**

```javascript
function printBillDirect(orderId, branchId = null) {
  $.ajax({
    url: '/api/print_bill_direct.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
      order_id: orderId,
      branch_id: branchId
    }),
    success: function(data) {
      if (data.success) {
        alert('✅ Receipt printed successfully!');
      } else {
        alert('❌ Print failed: ' + data.message);
      }
    },
    error: function(xhr, status, error) {
      alert('Error: ' + error);
    }
  });
}
```

---

### **Scenario 5: Using with Axios (if you use Axios)**

```javascript
async function printBillDirect(orderId, branchId = null) {
  try {
    const response = await axios.post('/api/print_bill_direct.php', {
      order_id: orderId,
      branch_id: branchId
    });
    
    if (response.data.success) {
      alert('✅ Receipt printed successfully!');
      return true;
    } else {
      alert('❌ Print failed: ' + response.data.message);
      return false;
    }
  } catch (error) {
    alert('Error: ' + error.message);
    return false;
  }
}
```

---

## 📋 **Complete Example: Full Integration**

Here's a complete example you can copy and use:

```html
<!DOCTYPE html>
<html>
<head>
  <title>Print Receipt Example</title>
</head>
<body>
  <button onclick="printReceipt(123)">Print Receipt</button>
  <div id="print-status"></div>

  <script>
    /**
     * Print bill receipt directly to USB printer
     */
    async function printReceipt(orderId) {
      const statusDiv = document.getElementById('print-status');
      statusDiv.textContent = 'Printing...';
      statusDiv.style.color = 'blue';

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
          statusDiv.textContent = '✅ Receipt printed successfully!';
          statusDiv.style.color = 'green';
          console.log('Printer used:', data.printer_used?.name);
        } else {
          statusDiv.textContent = '❌ Error: ' + data.message;
          statusDiv.style.color = 'red';
        }
      } catch (error) {
        statusDiv.textContent = '❌ Error: ' + error.message;
        statusDiv.style.color = 'red';
      }
    }
  </script>
</body>
</html>
```

---

## 🔄 **Migration from Old Print API**

If you're currently using `/api/print.php`, here's how to migrate:

### **Before (Old Way):**
```javascript
// Old print function
async function printReceipt(orderId) {
  const response = await fetch('/api/print.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  });
  
  const data = await response.json();
  // Handle response...
}
```

### **After (New Way):**
```javascript
// New direct USB print function
async function printReceipt(orderId) {
  const response = await fetch('/api/print_bill_direct.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId })
  });
  
  const data = await response.json();
  // Handle response...
}
```

**That's it!** Just change the URL from `/api/print.php` to `/api/print_bill_direct.php`

---

## ✅ **Key Differences**

| Feature | Old `print.php` | New `print_bill_direct.php` |
|---------|----------------|---------------------------|
| **URL** | `/api/print.php` | `/api/print_bill_direct.php` |
| **Printer Selection** | Manual (printer_id required) | Automatic (finds USB printer) |
| **User Interaction** | May require selection | None (automatic) |
| **Parameters** | `order_id`, `printer_id`, etc. | `order_id`, `branch_id`, `terminal` |
| **Response** | Returns receipt data | Returns print status |

---

## 🎯 **Quick Integration Checklist**

- [ ] Copy the `printBillDirect()` function to your JavaScript file
- [ ] Update your "Print Receipt" button to call `printBillDirect(orderId)`
- [ ] Test with a valid order_id
- [ ] Handle success/error responses
- [ ] Add user notifications (alerts, toasts, etc.)

---

## 📞 **Need Help?**

1. **Check browser console** for any JavaScript errors
2. **Check network tab** to see the API request/response
3. **Verify printer is configured** in database (see `USB_PRINTER_SETUP_CHECKLIST.md`)
4. **Test API directly** using browser console or Postman

---

**That's all you need!** Just replace your print function call with the new API endpoint.

