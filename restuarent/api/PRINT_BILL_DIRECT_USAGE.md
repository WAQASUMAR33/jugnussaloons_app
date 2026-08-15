# Direct USB Bill Printing API - Usage Guide

## 🎯 **Purpose**
This API (`print_bill_direct.php`) prints bill receipts **directly to a USB-connected printer** without requiring any user interaction or printer selection dialog.

## 📋 **API Endpoint**
```
POST /api/print_bill_direct.php
```

**Note:** GET requests are also supported, but POST is recommended.

---

## 📝 **Request Format**

### **Option 1: POST with JSON (Recommended)**

```javascript
fetch('/api/print_bill_direct.php', {
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
    console.log('Receipt printed successfully!', data);
  } else {
    console.error('Error:', data.message);
  }
});
```

### **Option 2: GET with Query String**

```javascript
const orderId = 123;
fetch(`/api/print_bill_direct.php?order_id=${orderId}`)
  .then(response => response.json())
  .then(data => console.log(data));
```

---

## 📥 **Parameters**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `order_id` | int/string | ✅ Yes | Order ID (numeric or "ORD-123" format) |
| `branch_id` | int | ❌ Optional | Branch ID to find branch-specific printer |
| `terminal` | int | ❌ Optional | Terminal number (default: 1) |

---

## 📤 **Response Format**

### **Success Response:**
```json
{
  "success": true,
  "message": "Receipt printed successfully to USB printer",
  "printer_info": {
    "connection_type": "usb",
    "method": "com_port",
    "usb_port": "COM3",
    "bytes_written": 1234
  },
  "order_number": "ORD-123",
  "printer_used": {
    "printer_id": 1,
    "printer_name": "USB Receipt Printer",
    "connection_type": "usb"
  }
}
```

### **Error Response:**
```json
{
  "success": false,
  "message": "No USB receipt printer configured...",
  "hint": "Add a printer with type='receipt' and connection_type='usb'...",
  "required_fields": {
    "type": "receipt",
    "connection_type": "usb",
    "usb_port": "COM1, COM2, etc. (OR)",
    "printer_name": "Windows printer name"
  }
}
```

---

## ⚙️ **Setup Required on Your Side**

### **Step 1: Configure USB Printer in Database**

You need to add a USB printer to the `printers` table with the following configuration:

#### **Option A: Using COM Port (Recommended for Direct USB Connection)**

```sql
INSERT INTO printers (
    name, 
    connection_type, 
    usb_port, 
    type, 
    terminal, 
    branch_id, 
    status
) VALUES (
    'USB Receipt Printer',  -- Printer display name
    'usb',                  -- Connection type must be 'usb'
    'COM3',                 -- Your COM port (check Device Manager)
    'receipt',              -- Type must be 'receipt'
    1,                      -- Terminal number
    NULL,                   -- Branch ID (or specific branch_id)
    'active'                -- Status must be 'active'
);
```

#### **Option B: Using Windows Printer Name**

```sql
INSERT INTO printers (
    name, 
    connection_type, 
    printer_name, 
    type, 
    terminal, 
    branch_id, 
    status
) VALUES (
    'USB Receipt Printer',           -- Printer display name
    'usb',                           -- Connection type must be 'usb'
    'XP-80C Receipt Printer',        -- Exact Windows printer name
    'receipt',                       -- Type must be 'receipt'
    1,                               -- Terminal number
    NULL,                            -- Branch ID (or specific branch_id)
    'active'                         -- Status must be 'active'
);
```

### **Step 2: Find Your COM Port (For Option A)**

1. **Windows:**
   - Open **Device Manager** (Win + X → Device Manager)
   - Expand **Ports (COM & LPT)**
   - Look for your USB printer (e.g., "USB Serial Port (COM3)")
   - Note the COM port number (e.g., COM3)

2. **Alternative Method:**
   - Open **Control Panel** → **Devices and Printers**
   - Right-click your printer → **Printer Properties**
   - Check the **Ports** tab for COM port information

### **Step 3: Find Your Windows Printer Name (For Option B)**

1. Open **Control Panel** → **Devices and Printers**
2. Find your USB printer
3. The name shown is the **exact name** you need to use
4. **Important:** The name must match exactly (case-sensitive)

### **Step 4: Test the API**

```javascript
// Test with a valid order_id
fetch('/api/print_bill_direct.php', {
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
  console.log(data);
  if (data.success) {
    alert('Receipt printed successfully!');
  } else {
    alert('Error: ' + data.message);
  }
});
```

---

## 🔧 **Troubleshooting**

### **Error: "No USB receipt printer configured"**

**Solution:**
- Add a printer to the database using the SQL commands above
- Make sure `type = 'receipt'` and `connection_type = 'usb'`
- Make sure `status = 'active'`

### **Error: "Could not open COM port"**

**Solutions:**
1. **Check COM Port:**
   - Verify the COM port in Device Manager
   - Make sure the printer is connected
   - Try a different COM port if available

2. **Check Permissions:**
   - The web server (PHP) needs access to COM ports
   - On Windows, you may need to run the web server with appropriate permissions

3. **Check Printer Connection:**
   - Disconnect and reconnect the USB cable
   - Try a different USB port
   - Check if printer drivers are installed

### **Error: "Failed to print to USB printer using Windows printer name"**

**Solutions:**
1. **Verify Printer Name:**
   - Check the exact printer name in Control Panel
   - Make sure it matches exactly (case-sensitive)
   - Remove any special characters if possible

2. **Set as Default Printer:**
   - Right-click printer → **Set as default printer**

3. **Check Printer Sharing:**
   - Make sure printer is not set to "Offline"
   - Check printer queue for errors

### **Printer Not Printing**

**Checklist:**
- ✅ Printer is powered on
- ✅ USB cable is connected
- ✅ Printer has paper
- ✅ Printer is not in error state
- ✅ COM port or printer name is correct in database
- ✅ Printer status is 'active' in database

---

## 📊 **How It Works**

1. **API receives order_id**
2. **Finds USB receipt printer** from database (type='receipt', connection_type='usb')
3. **Fetches order details** and items
4. **Generates receipt** using ESC/POS commands
5. **Prints directly to USB** using:
   - **Method 1:** COM port (direct serial communication) - **Preferred**
   - **Method 2:** Windows printer name (if COM port fails)

---

## ✅ **Key Points**

1. ✅ **No user interaction required** - prints automatically
2. ✅ **Automatic printer detection** - finds USB receipt printer from database
3. ✅ **Two printing methods** - COM port (preferred) or Windows printer name
4. ✅ **Branch-specific printers** - can use different printers per branch
5. ✅ **Terminal-specific printers** - can use different printers per terminal

---

## 🔄 **Comparison with Regular Print API**

| Feature | `print.php` | `print_bill_direct.php` |
|---------|-------------|------------------------|
| Printer Selection | Manual (printer_id required) | Automatic (finds USB printer) |
| User Interaction | May require selection | None |
| USB Support | Yes | Yes (USB only) |
| Network Support | Yes | No |
| Use Case | Flexible printing | Direct USB printing only |

---

## 📝 **Example: Complete Integration**

```javascript
// Function to print bill receipt directly
async function printBillDirect(orderId, branchId = null, terminal = 1) {
  try {
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
      console.log('✅ Receipt printed successfully!');
      console.log('Printer:', data.printer_used.name);
      console.log('Method:', data.printer_info.method);
      return true;
    } else {
      console.error('❌ Print failed:', data.message);
      if (data.troubleshooting) {
        console.log('Troubleshooting:', data.troubleshooting);
      }
      return false;
    }
  } catch (error) {
    console.error('Error:', error);
    return false;
  }
}

// Usage
printBillDirect(123)
  .then(success => {
    if (success) {
      alert('Receipt printed!');
    } else {
      alert('Failed to print receipt. Check console for details.');
    }
  });
```

---

**Last Updated:** December 2024

