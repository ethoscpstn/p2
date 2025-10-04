# 🚀 Quick Start Guide - HanapBahay Payment & Chat Integration

## ⚡ Quick Setup (5 Minutes)

### Step 1: Database Setup (2 minutes)
```bash
# Open phpMyAdmin at http://localhost/phpmyadmin
# Select database: dbhanapbahay
# Go to SQL tab and run:
```

```sql
-- Copy and paste this entire file: add_payment_fields.sql
-- OR run this command in terminal:
mysql -u root -p dbhanapbahay < add_payment_fields.sql
```

### Step 2: Create Upload Folders (1 minute)
```bash
# Windows (Command Prompt)
mkdir c:\xampp\htdocs\public_html\uploads\qr_codes
mkdir c:\xampp\htdocs\public_html\uploads\receipts
```

### Step 3: Test Clean URLs (1 minute)
Visit these URLs (without .php):
- ✅ `http://localhost/public_html/browse_listings`
- ✅ `http://localhost/public_html/setup_payment`

If they don't work, ensure Apache mod_rewrite is enabled:
```bash
# Check httpd.conf has this line uncommented:
LoadModule rewrite_module modules/mod_rewrite.so
# Restart Apache
```

### Step 4: Owner Setup (1 minute)
1. Login as owner
2. Click **"Payment Setup"** in navigation
3. Upload GCash QR code + enter details
4. Upload PayMaya QR code + enter details
5. Enter bank details
6. Save

### Step 5: Test Payment Flow (1 minute)
1. Logout, login as tenant
2. Go to any property
3. Click **"Apply / Reserve"**
4. Select GCash → See QR code ✅
5. Upload screenshot → Preview appears ✅
6. Submit → Success ✅

---

## ✅ What's New

### 1. Payment Methods (Owner Side)
- **New Page:** [setup_payment.php](setup_payment.php)
- Owners can upload QR codes for GCash & PayMaya
- Owners can set bank transfer details
- Real-time preview of uploaded QR codes

### 2. Payment Methods (Tenant Side)
- **GCash:** Scan owner's QR code, upload screenshot
- **PayMaya:** Scan owner's QR code, upload screenshot
- **Bank Transfer:** View bank details, upload proof

### 3. Clean URLs (SEO-Friendly)
- ❌ Old: `hanapbahay.online/LoginModule.php`
- ✅ New: `hanapbahay.online/LoginModule`
- Auto-redirect from old URLs

### 4. Fixed Chat Button
- **"Message Owner"** now works without page refresh
- Chat widget opens smoothly at bottom-right
- Real-time messaging via Pusher

---

## 📁 New Files

| File | Purpose |
|------|---------|
| `add_payment_fields.sql` | Database migration (RUN THIS FIRST!) |
| `setup_payment.php` | Owner payment setup page |
| `.htaccess` | Clean URLs configuration |
| `PAYMENT_SETUP_README.md` | Detailed documentation |
| `QUICK_START.md` | This file |

---

## 🔄 Modified Files

| File | Changes |
|------|---------|
| `property_details.php` | ✅ 3 payment methods<br>✅ Fixed chat button<br>✅ Receipt upload |
| `DashboardUO.php` | ✅ Payment Setup link<br>✅ Clean URLs |
| `DashboardT.php` | ✅ Clean URLs |
| `browse_listings.php` | ✅ Clean URLs |
| `start_chat.php` | ✅ AJAX support |

---

## 🎯 Testing Checklist

### Database ✅
- [ ] Run `add_payment_fields.sql`
- [ ] Verify tables updated: `DESCRIBE tbadmin;`

### Payment (Owner) ✅
- [ ] Login as owner
- [ ] Access `setup_payment` page
- [ ] Upload GCash QR → Preview shows
- [ ] Upload PayMaya QR → Preview shows
- [ ] Enter bank details → Save success

### Payment (Tenant) ✅
- [ ] Login as tenant
- [ ] View property details
- [ ] Click "Apply / Reserve"
- [ ] GCash: QR shows, upload works ✅
- [ ] PayMaya: QR shows, upload works ✅
- [ ] Bank: Details show, upload works ✅

### Clean URLs ✅
- [ ] Visit `/browse_listings` (no .php) → Works
- [ ] Visit `/setup_payment` → Works
- [ ] Old URL `/browse_listings.php` → Redirects

### Chat ✅
- [ ] Login as tenant
- [ ] Property details → Click "Message Owner"
- [ ] Chat widget appears (no page refresh) ✅
- [ ] Send message → Real-time delivery ✅

---

## 🐛 Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| "Column not found" error | Run `add_payment_fields.sql` |
| Clean URLs don't work | Enable mod_rewrite, check `.htaccess` |
| QR code not showing | Create `uploads/qr_codes/` folder |
| Receipt upload fails | Create `uploads/receipts/` folder |
| Chat button does nothing | Check browser console, verify tenant login |

---

## 📱 Mobile Testing

Test on mobile devices:
- [ ] QR codes display properly
- [ ] File upload works from camera
- [ ] Receipt preview shows
- [ ] Chat widget is responsive
- [ ] Clean URLs work

---

## 🔐 Security Features

- ✅ File upload validation (type, size)
- ✅ SQL injection protection (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Session-based authentication
- ✅ Receipt storage in secure folder
- ✅ Clean URLs for better security

---

## 📈 Next Steps (Optional)

Consider adding:
1. **Email notifications** when payment received
2. **Admin panel** to view all payments
3. **Payment status tracking** (pending/approved/rejected)
4. **Automatic receipt number generation**
5. **Download receipt** feature for tenants

---

## 💡 Quick Tips

### For Owners:
- Upload clear, high-quality QR codes
- Test QR codes before uploading
- Keep bank details accurate
- Update payment info regularly

### For Tenants:
- Always upload payment proof
- Take clear screenshots of transactions
- Include reference number in screenshot
- Keep copy of receipt for records

### For Developers:
- Check Apache error logs if issues occur
- Monitor `uploads/` folder size
- Backup database before major changes
- Test on different browsers

---

## 🎉 You're All Set!

Your HanapBahay platform now has:
- ✅ **3 Payment Methods** (GCash, PayMaya, Bank Transfer)
- ✅ **QR Code Payment** (Owner-Tenant flow)
- ✅ **Receipt Uploads** (Required validation)
- ✅ **Clean URLs** (Professional appearance)
- ✅ **Working Chat** (No page refresh)

Need help? Check [PAYMENT_SETUP_README.md](PAYMENT_SETUP_README.md) for detailed documentation.

---

**Ready to test?** Follow the testing checklist above! ✨
