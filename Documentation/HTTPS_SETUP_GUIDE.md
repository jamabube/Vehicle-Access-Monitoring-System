# HTTPS Setup Guide for VAMS
## Pre-Deployment Security Configuration

This guide walks you through enabling HTTPS/TLS on your XAMPP Apache server for the VAMS application, addressing **FINDING 2 (HIGH severity)** from the penetration test report.

---

## Why HTTPS is Critical

Without HTTPS:
- Session cookies transmitted in plaintext → session hijacking risk
- Login credentials sent unencrypted → credential theft
- RFID API secrets visible on the network → device forgery attacks

---

## Prerequisites

- XAMPP Control Panel access
- Administrator privileges on Windows
- OpenSSL (included with XAMPP)

---

## Step 1: Generate SSL Certificate

### Option A: Self-Signed Certificate (For Development/Thesis Defense)

Open PowerShell as Administrator in `C:\xampp\apache`:

```powershell
cd C:\xampp\apache

# Generate private key
.\bin\openssl.exe genrsa -out conf\ssl.key\vams.key 2048

# Generate certificate signing request
.\bin\openssl.exe req -new -key conf\ssl.key\vams.key -out conf\ssl.csr\vams.csr

# When prompted, enter:
# - Country: PH
# - State: (your province)
# - City: (your city)
# - Organization: Forest Lawn Memorial Park
# - Common Name: vams.local  ← IMPORTANT: Must match your hostname
# - Email: (your email)

# Generate self-signed certificate (valid for 1 year)
.\bin\openssl.exe x509 -req -days 365 -in conf\ssl.csr\vams.csr -signkey conf\ssl.key\vams.key -out conf\ssl.crt\vams.crt
```

### Option B: Let's Encrypt Certificate (For Production Deployment)

If deploying to a public server with a domain name:

```powershell
# Install Certbot for Windows: https://certbot.eff.org/
# Then run:
certbot certonly --standalone -d yourdomain.com
```


---

## Step 2: Configure Apache Virtual Host

Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`. Add at the end of the file:

```apache
# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName vams.local
    Redirect permanent / https://vams.local/
</VirtualHost>

# HTTPS Virtual Host
<VirtualHost *:443>
    ServerName vams.local
    DocumentRoot "C:/Users/jamabube/Downloads/Vehicle Access Monitoring System/vams-webapp/public"

    SSLEngine on
    SSLCertificateFile "C:/xampp/apache/conf/ssl.crt/vams.crt"
    SSLCertificateKeyFile "C:/xampp/apache/conf/ssl.key/vams.key"

    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"

    <Directory "C:/Users/jamabube/Downloads/Vehicle Access Monitoring System/vams-webapp/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/vams-error.log"
    CustomLog "logs/vams-access.log" common
</VirtualHost>
```

---

## Step 3: Enable Apache SSL Module

Edit `C:\xampp\apache\conf\httpd.conf` and uncomment these lines (remove the `#`):

```apache
LoadModule ssl_module modules/mod_ssl.so
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
Include conf/extra/httpd-ssl.conf
Include conf/extra/httpd-vhosts.conf
```

---

## Step 4: Update Windows Hosts File

Edit `C:\Windows\System32\drivers\etc\hosts` (requires Administrator):

```
127.0.0.1  vams.local
```

---

## Step 5: Update VAMS Configuration

Edit `vams-webapp/.env`:

```bash
APP_URL=https://vams.local
SESSION_SECURE_COOKIE=true
RFID_LISTENER_API_URL=https://vams.local/api/rfid/detections
RFID_LISTENER_VERIFY_TLS=true
```

For self-signed certificates during development, temporarily set:

```bash
RFID_LISTENER_VERIFY_TLS=false
```

---

## Step 6: Restart Apache

1. Open **XAMPP Control Panel**
2. Click **Stop** next to Apache
3. Wait 3 seconds
4. Click **Start** next to Apache
5. Verify port **443** appears next to Apache (alongside port **80**)


---

## Step 7: Verify HTTPS Works

1. Open browser: `https://vams.local`
2. Accept the self-signed certificate warning (click "Advanced" → "Proceed")
3. Verify:
   - URL shows `https://` with a padlock icon (may show "Not Secure" warning for self-signed cert)
   - Login page loads correctly
   - After login, check Developer Tools → Network tab → Headers:
     - `Strict-Transport-Security` header present
     - Cookie has `Secure` flag

---

## Step 8: Test RFID Listener with HTTPS

```powershell
cd "C:\Users\jamabube\Downloads\Vehicle Access Monitoring System\vams-webapp"
php artisan rfid:doctor
```

If using a self-signed cert and `RFID_LISTENER_VERIFY_TLS=true`, you will see SSL verification errors. That is expected — set `VERIFY_TLS=false` for development.

---

## Troubleshooting

### Issue: "This site can't be reached" after enabling HTTPS

1. Check Apache error log: `C:\xampp\apache\logs\error.log`
2. Common causes:
   - Port 443 blocked by firewall → add a Windows Firewall exception
   - Certificate path wrong → verify paths in `httpd-vhosts.conf`
   - Apache SSL module not loaded → check `httpd.conf` includes

### Issue: Browser shows "NET::ERR_CERT_AUTHORITY_INVALID"

This is expected for self-signed certificates. Click "Advanced" → "Proceed to vams.local (unsafe)" — the connection is still encrypted, just not verified by a trusted CA.

For production, use Let's Encrypt or purchase a commercial certificate.

### Issue: Mixed content warnings (some resources load over HTTP)

Ensure all asset URLs use the `asset()` helper (not hardcoded `http://`):

```php
<!-- Correct -->
<link rel="stylesheet" href="{{ asset('css/app.css') }}">

<!-- Wrong -->
<link rel="stylesheet" href="http://vams.local/css/app.css">
```

---

## Security Checklist

Before thesis defense / production deployment:

- [ ] HTTPS enabled and enforced (HTTP redirects to HTTPS)
- [ ] Self-signed certificate installed (or Let's Encrypt for production)
- [ ] `SESSION_SECURE_COOKIE=true` in `.env`
- [ ] `Strict-Transport-Security` header present in responses
- [ ] RFID listener connects via HTTPS
- [ ] Browser shows padlock icon (may have warning for self-signed cert)
- [ ] Session cookies have `Secure` flag in browser dev tools

---

## Estimated Time

- Self-signed certificate: 15–30 minutes
- Let's Encrypt certificate: 30–60 minutes
- Testing and verification: 15 minutes

**Total:** 30–90 minutes depending on experience level

---

## References

- [OWASP Transport Layer Protection Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Transport_Layer_Protection_Cheat_Sheet.html)
- [Let's Encrypt](https://letsencrypt.org/)
- [Apache SSL/TLS Documentation](https://httpd.apache.org/docs/2.4/ssl/)

---

**Document Version:** 1.0  
**Last Updated:** 2026-09-11  
**Status:** Ready for implementation
