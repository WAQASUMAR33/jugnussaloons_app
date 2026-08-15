# Quick Fix: 403 Error & Printer Status 0

## ✅ **Complete Solution**

### **Step 1: Fix Printer Status (Status = 0)**

Run this SQL to activate your printer:

```sql
UPDATE printers 
SET status = 'active' 
WHERE printer_id = YOUR_PRINTER_ID;
```

**Or activate all USB printers:**
```sql
UPDATE printers 
SET status = 'active' 
WHERE connection_type = 'usb' AND type = 'receipt';
```

---

### **Step 2: Use the Test Print Endpoint**

I've created a simple test endpoint: `/api/test_print_usb.php`

**JavaScript Code for Test Button:**

```javascript
async function testPrintUSB() {
  try {
    const response = await fetch('/api/test_print_usb.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        printer_id: 1  // Replace with your printer_id
      })
    });

    const data = await response.json();
    
    if (data.success) {
      alert('✅ Test print sent successfully!');
      console.log('Print method used:', data.method);
    } else {
      alert('❌ Error: ' + data.message);
      console.error('Error details:', data);
      
      // If status issue, show fix
      if (data.fix) {
        console.log('SQL Fix:', data.fix);
      }
    }
  } catch (error) {
    console.error('Request failed:', error);
    alert('❌ Request failed: ' + error.message);
  }
}
```

---

### **Step 3: Fix 403 Error**

The 403 error is usually caused by:

**A. CORS Issue:**
- Make sure `cors_headers.php` is included at the top
- Check browser console for CORS errors

**B. Server Configuration:**
- Check `.htaccess` file (if exists)
- Make sure PHP is running
- Check WAMP/Apache error logs

**C. File Permissions:**
- Make sure PHP files are readable
- Check folder permissions

---

### **Step 4: Complete Test Button HTML**

```html
<button onclick="testPrintUSB()" id="testPrintBtn">
  Test Print USB
</button>

<script>
async function testPrintUSB() {
  const btn = document.getElementById('testPrintBtn');
  btn.disabled = true;
  btn.textContent = 'Printing...';

  try {
    const response = await fetch('/api/test_print_usb.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        printer_id: 1  // Your printer ID
      })
    });

    if (!response.ok) {
      throw new Error('HTTP error! status: ' + response.status);
    }

    const data = await response.json();
    
    if (data.success) {
      alert('✅ Test print sent successfully!');
    } else {
      alert('❌ Error: ' + data.message);
      if (data.fix) {
        console.log('SQL Fix:', data.fix);
      }
    }
  } catch (error) {
    alert('❌ Error: ' + error.message);
    console.error('Full error:', error);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Test Print USB';
  }
}
</script>
```

---

## 🔍 **Check Printer Status**

```sql
SELECT 
    printer_id,
    name,
    connection_type,
    status,
    usb_port,
    printer_name
FROM printers
WHERE connection_type = 'usb';
```

**Status should be:**
- ✅ `'active'` (string)
- ❌ NOT `0` (number)
- ❌ NOT `'inactive'`
- ❌ NOT `NULL`

---

## 🚀 **Quick Test**

1. **Fix status:**
   ```sql
   UPDATE printers SET status = 'active' WHERE printer_id = 1;
   ```

2. **Test in browser console:**
   ```javascript
   fetch('/api/test_print_usb.php', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ printer_id: 1 })
   })
   .then(r => r.json())
   .then(d => console.log(d));
   ```

3. **Check response:**
   - If `success: true` → Printer is working! ✅
   - If `success: false` → Check error message

---

## ✅ **That's It!**

1. Update printer status to 'active'
2. Use `/api/test_print_usb.php` endpoint
3. Test print should work!

