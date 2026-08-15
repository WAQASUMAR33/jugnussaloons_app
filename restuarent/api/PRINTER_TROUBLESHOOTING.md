# USB Printer Troubleshooting Guide

## 🔍 **Step 1: Test Your Printer Configuration**

Use the test endpoint to diagnose issues:

```javascript
// Test your printer (replace 1 with your printer_id)
fetch('/api/test_printer.php?printer_id=1')
  .then(r => r.json())
  .then(d => console.log(d));
```

Or open in browser:
```
http://localhost/restuarent/api/test_printer.php?printer_id=1
```

This will show:
- ✅ Port format validation
- ✅ Port accessibility
- ✅ Windows printer detection
- ✅ Temp directory permissions
- ✅ Print command availability

---

## 🔧 **Common Issues and Solutions**

### **Issue 1: "Could not open port: USB002"**

**Possible Causes:**
1. Port name is incorrect
2. Printer not connected
3. Driver not installed
4. Port permissions issue

**Solutions:**

**A. Verify Port Name:**
1. Open **Device Manager** (Win + X → Device Manager)
2. Expand **Ports (COM & LPT)**
3. Find your printer - note the exact port name
4. Update database:
   ```sql
   UPDATE printers 
   SET usb_port = 'USB002'  -- Use exact port from Device Manager
   WHERE printer_id = 1;
   ```

**B. Use Windows Printer Name Instead:**
1. Open **Control Panel** → **Devices and Printers**
2. Find your printer
3. Right-click → **Printer Properties**
4. Note the exact name at the top
5. Update database:
   ```sql
   UPDATE printers 
   SET printer_name = 'Your Exact Printer Name',  -- Exact name from Windows
       usb_port = NULL  -- Remove USB port
   WHERE printer_id = 1;
   ```

---

### **Issue 2: "Failed to print to USB printer using Windows printer name"**

**Solutions:**

**A. Verify Printer Name:**
- Name must match **exactly** (case-sensitive)
- No extra spaces
- Use exact name from Control Panel

**B. Check Printer Status:**
1. Open **Control Panel** → **Devices and Printers**
2. Check if printer shows "Offline" or has error icon
3. Right-click → **See what's printing**
4. Clear any stuck jobs
5. Right-click → **Printer Properties** → **Ports** tab
6. Verify port is correct

**C. Set as Default Printer:**
- Right-click printer → **Set as default printer**

---

### **Issue 3: "Invalid port format"**

**Solution:**
- Port must be in format: `COM1`, `COM2`, `COM3`, etc. OR `USB001`, `USB002`, etc.
- No spaces or special characters
- Update database with correct format

---

### **Issue 4: Printer Connected But Nothing Prints**

**Checklist:**
- ✅ Printer is powered on
- ✅ Printer has paper
- ✅ Printer is not in error state (check printer display)
- ✅ USB cable is securely connected
- ✅ Printer drivers are installed
- ✅ Printer is not set to "Offline" in Windows
- ✅ No paper jam or other errors

**Test:**
1. Try printing a test page from Windows
2. Control Panel → Devices and Printers → Right-click printer → **Printer Properties** → **Print Test Page**
3. If test page works, the issue is with the API
4. If test page fails, fix Windows/printer issues first

---

## 🎯 **Recommended Setup**

### **Option 1: Use Windows Printer Name (Recommended)**

This is usually more reliable than USB port:

```sql
UPDATE printers 
SET 
    connection_type = 'usb',
    printer_name = 'Your Exact Printer Name',  -- From Control Panel
    usb_port = NULL,  -- Not needed
    ip_address = 'USB',
    port = 0
WHERE printer_id = 1;
```

**To find exact printer name:**
1. Control Panel → Devices and Printers
2. Find your printer
3. The name shown is what you need (copy exactly)

### **Option 2: Use USB Port**

```sql
UPDATE printers 
SET 
    connection_type = 'usb',
    usb_port = 'USB002',  -- From Device Manager
    printer_name = NULL,  -- Not needed
    ip_address = 'USB',
    port = 0
WHERE printer_id = 1;
```

---

## 🔍 **Debug Steps**

### **Step 1: Check Printer in Database**

```sql
SELECT 
    printer_id,
    name,
    connection_type,
    usb_port,
    printer_name,
    type,
    status
FROM printers
WHERE printer_id = 1;
```

**Verify:**
- `connection_type` = 'usb'
- `type` = 'receipt'
- `status` = 'active'
- Either `usb_port` OR `printer_name` is set

### **Step 2: Test Print API Directly**

```javascript
fetch('/api/print_bill_direct.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ order_id: 123 })
})
.then(r => r.json())
.then(d => {
  console.log('Response:', d);
  if (!d.success) {
    console.error('Error:', d.message);
    console.error('Debug Info:', d.debug_info);
    console.error('Troubleshooting:', d.troubleshooting);
  }
});
```

### **Step 3: Check Error Response**

The API now returns detailed error information:
- `debug_info` - Shows what was checked
- `troubleshooting` - Step-by-step solutions
- `suggested_fix` - Specific recommendation

---

## ✅ **Quick Fix Checklist**

1. ✅ Run test endpoint: `/api/test_printer.php?printer_id=1`
2. ✅ Verify printer in database has correct `usb_port` or `printer_name`
3. ✅ Check Device Manager for correct port name
4. ✅ Try using Windows printer name instead of USB port
5. ✅ Ensure printer is powered on and has paper
6. ✅ Check printer is not "Offline" in Windows
7. ✅ Try printing test page from Windows first
8. ✅ Check browser console for detailed error messages

---

## 📞 **Still Not Working?**

1. **Check API Response:**
   - Look at the `debug_info` in the error response
   - Check `troubleshooting` steps

2. **Check PHP Error Log:**
   - Look in WAMP error logs
   - Check for permission errors

3. **Try Alternative Method:**
   - If using `usb_port`, try `printer_name` instead
   - If using `printer_name`, try `usb_port` instead

4. **Verify Printer Works:**
   - Print test page from Windows
   - If Windows can't print, fix that first

---

**The improved API now tries multiple methods and provides detailed error information to help diagnose issues!**

