# LHDN E-Invoice API Proxy Server - Documentation

## Table of Contents
1. [Overview](#overview)
2. [Authentication](#authentication)
3. [API Endpoints](#api-endpoints)
4. [Request/Response Formats](#requestresponse-formats)
5. [Error Handling](#error-handling)
6. [Setup & Configuration](#setup--configuration)
7. [Usage Examples](#usage-examples)
8. [Security Best Practices](#security-best-practices)

---

## Overview

This is a **stateless proxy server** for LHDN e-invoicing that handles:
- Document submission to LHDN MyInvois platform
- Authentication with LHDN OAuth2
- Digital signatures and XML generation
- Document status tracking
- QR code generation

**Base URL:** `https://your-domain.com/api`

**Key Features:**
- Single shared certificate for all API clients
- One API key authentication via `X-API-Key` header
- MySQL database for data persistence
- No multi-tenancy - all clients share the same infrastructure

---

## Authentication

All API endpoints (except `/health`) require authentication using the `X-API-Key` header.

### Authentication Header
```http
X-API-Key: your_secure_64_char_api_key_here
```

### Example Request
```bash
curl -H "X-API-Key: your_api_key" \
     https://your-domain.com/api/health
```

### Unauthorized Response (401)
```json
{
  "success": false,
  "message": "Invalid API key",
  "data": []
}
```

---

## API Endpoints

### 1. Health Check
**Endpoint:** `GET /api/health`

**Authentication:** Not required

**Description:** Check if the API server is running.

**Response (200):**
```json
{
  "status": "ok",
  "service": "LHDN E-Invoice Proxy",
  "timestamp": "2025-03-16T10:30:00Z"
}
```

---

### 2. Create & Submit E-Invoice
**Endpoint:** `POST /api/documents`

**Authentication:** Required

**Description:** Create and submit one or more e-invoices to LHDN.

**Request Body:**
```json
{
  "documents": [
    {
      "invoice_num": "INV20250316001",
      "invoice_type": "01",
      "invoice_datetime": "2025-03-16T10:00:00Z",
      "currency": "MYR",
      "einvoice_ver": "1.0",

      "supplier": {
        "name": "Your Company Sdn Bhd",
        "tin": "123456789012",
        "registration_type": "BRN",
        "registration_num": "202501000001",
        "msic": "62010",
        "business_desc": "Software Development",
        "contact_num": "+60123456789",
        "email": "billing@yourcompany.com",
        "address1": "Level 1, Tower A",
        "address2": "Tech Park",
        "address3": "Cyberjaya",
        "city": "Sepang",
        "postcode": "63000",
        "state": "10",
        "country": "MY"
      },

      "buyer": {
        "name": "Customer Sdn Bhd",
        "tin": "987654321098",
        "registration_type": "BRN",
        "registration_num": "202501000002",
        "msic": "62010",
        "business_desc": "Software Development",
        "contact_num": "+60198765432",
        "email": "accounts@customer.com",
        "address1": "Level 5, Block B",
        "address2": "Business Center",
        "address3": "Kuala Lumpur",
        "city": "Kuala Lumpur",
        "postcode": "50000",
        "state": "14",
        "country": "MY"
      },

      "items": [
        {
          "classification": "A",
          "description": "Software License - Annual Subscription",
          "unit_price": 1200.00,
          "qty": 1,
          "measurement": "C62",
          "country": "MY",
          "product_tariff_code": null,
          "tax_type": "06",
          "tax_rate": 10.00,
          "tax_amount": 120.00,
          "tax_exemption_amt": 0.00,
          "tax_exemption_details": null,
          "discount_amt": 0.00,
          "discount_rate": 0.00,
          "discount_reason": null,
          "charge_amt": 0.00,
          "charge_rate": 0.00,
          "charge_reason": null,
          "subtotal": 1200.00,
          "total_excl_tax": 1200.00
        }
      ],

      "tax_type": "06",
      "total_tax_amt": 120.00,
      "total_taxable_amt": 1200.00,
      "total_tax_amt_per_tax_type": 120.00,
      "total_nett_amt": 1200.00,
      "total_excl_tax": 1200.00,
      "total_incl_tax": 1320.00,
      "total_dsc_val": 0.00,
      "total_charge_amt": 0.00,
      "rounding_amt": 0.00,
      "total_payable": 1320.00,
      "additional_disc": null,
      "additional_disc_reason": null,
      "additional_fee": null,
      "additional_fee_reason": null,
      "original_invoice_num": null,
      "billing_startdate": null,
      "billing_enddate": null,
      "frequency": null,
      "payment_mode": null,
      "payment_terms": null
    }
  ]
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Request sent to LHDN successfully",
  "data": {
    "submissionUid": "SUB202503160001",
    "acceptedDocuments": [
      {
        "uuid": "DOC202503160001",
        "invoiceCodeNumber": "INV20250316001",
        "submissionTimestamp": "2025-03-16T10:05:00Z"
      }
    ]
  }
}
```

**Error Response (422):**
```json
{
  "success": false,
  "message": "There is an error with one or more documents submitted",
  "data": {
    "error": "Validation failed",
    "details": "Invalid TIN format"
  }
}
```

---

### 3. Get Document Details
**Endpoint:** `GET /api/documents/{document_uid}`

**Authentication:** Required

**Description:** Retrieve submitted document details from LHDN.

**Parameters:**
- `document_uid` (path) - Document UUID from LHDN

**Success Response (200):**
```json
{
  "success": true,
  "message": "Document retrieved successfully",
  "data": {
    "uuid": "DOC202503160001",
    "invoiceCodeNumber": "INV20250316001",
    "submissionUid": "SUB202503160001",
    "status": "Valid",
    "invoiceDateTime": "2025-03-16T10:00:00Z",
    "supplier": { ... },
    "buyer": { ... },
    "items": [ ... ]
  }
}
```

---

### 4. Get Raw Document
**Endpoint:** `GET /api/documents/{document_uid}/raw`

**Authentication:** Required

**Description:** Get the original XML document as submitted to LHDN.

**Success Response (200):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
  ...
</Invoice>
```

---

### 5. Search Documents
**Endpoint:** `GET /api/documents/search`

**Authentication:** Required

**Description:** Search for documents by invoice number or other criteria.

**Query Parameters:**
- `invoice_num` (optional) - Search by invoice number
- `date_from` (optional) - Start date (YYYY-MM-DD)
- `date_to` (optional) - End date (YYYY-MM-DD)
- `status` (optional) - Document status (Draft, Submitted, Valid, Invalid)
- `tin` (optional) - Filter by supplier TIN

**Example Request:**
```bash
curl -H "X-API-Key: your_key" \
     "https://your-domain.com/api/documents/search?invoice_num=INV001&status=Valid"
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Documents found",
  "data": {
    "documents": [
      {
        "id": 1,
        "invoice_num": "INV001",
        "document_UID": "DOC202503160001",
        "status": "Valid",
        "total_payable": 1320.00
      }
    ],
    "total": 1,
    "page": 1,
    "per_page": 20
  }
}
```

---

### 6. Cancel Document
**Endpoint:** `PUT /api/documents/{document_uid}/cancel`

**Authentication:** Required

**Description:** Cancel a previously submitted document.

**Request Body:**
```json
{
  "reason": "Goods returned by customer"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Document cancelled successfully",
  "data": {
    "uuid": "DOC202503160001",
    "status": "Cancelled"
  }
}
```

---

### 7. Reject Document
**Endpoint:** `PUT /api/documents/{document_uid}/reject`

**Authentication:** Required

**Description:** Reject a document received from supplier.

**Request Body:**
```json
{
  "reason": "Incorrect product description"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Document rejected successfully",
  "data": {
    "uuid": "DOC202503160001",
    "status": "Rejected"
  }
}
```

---

### 8. Get Submission Status
**Endpoint:** `GET /api/submissions/{submission_uid}`

**Authentication:** Required

**Description:** Check the status of a document submission.

**Success Response (200):**
```json
{
  "success": true,
  "message": "Submission retrieved successfully",
  "data": {
    "submissionUid": "SUB202503160001",
    "status": "Completed",
    "submittedAt": "2025-03-16T10:05:00Z",
    "completedAt": "2025-03-16T10:07:00Z",
    "documents": [
      {
        "uuid": "DOC202503160001",
        "status": "Valid"
      }
    ]
  }
}
```

---

### 9. Validate TIN
**Endpoint:** `POST /api/validate/tin`

**Authentication:** Required

**Description:** Validate a Tax Identification Number with LHDN.

**Request Body:**
```json
{
  "tin": "123456789012",
  "idType": "BRN",
  "idValue": "202501000001"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "TIN validated successfully",
  "data": {
    "tin": "123456789012",
    "isValid": true,
    "name": "Your Company Sdn Bhd",
    "registrationType": "BRN"
  }
}
```

---

### 10. Generate QR Code
**Endpoint:** `GET /api/documents/{document_uid}/qr`

**Authentication:** Required

**Description:** Generate a QR code for the document.

**Success Response (200):**
```
[QR Code SVG/Image data]
```

---

### 11. Get Settings/Lookup Codes
**Endpoint:** `GET /api/settings/{code}`

**Authentication:** Required

**Description:** Get lookup codes for dropdowns (states, countries, MSIC codes, etc.).

**Parameters:**
- `code` (path) - Category name: `State`, `Country`, `InvoiceType`, `MSIC`, `Classification`, `Measurement`

**Example Request:**
```bash
curl -H "X-API-Key: your_key" \
     https://your-domain.com/api/settings/State
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Settings retrieved",
  "data": [
    {
      "name": "14",
      "value": "Wilayah Persekutuan Kuala Lumpur"
    },
    {
      "name": "10",
      "value": "Wilayah Persekutuan Putrajaya"
    },
    {
      "name": "01",
      "value": "Johor"
    }
  ]
}
```

---

## Request/Response Formats

### Standard Response Structure

All API responses follow this format:

```json
{
  "success": true|false,
  "message": "Human-readable message",
  "data": { }
}
```

### Invoice Types Reference

| Code | Description |
|------|-------------|
| 01 | Standard Invoice |
| 02 | Debit Note |
| 03 | Credit Note |
| 04 | Self-Billed Invoice |
| 05 | Self-Billed Debit Note |
| 06 | Self-Billed Credit Note |
| 07 | Consolidated Invoice |
| 08 | Consolidated Debit Note |
| 09 | Consolidated Credit Note |
| 10 | Self-Billed Consolidated Invoice |
| 11 | Self-Billed Consolidated Debit Note |
| 12 | Self-Billed Consolidated Credit Note |

### Tax Types Reference

| Code | Description |
|------|-------------|
| 01 | SST - Sales Tax |
| 02 | SST - Service Tax |
| 03 | SST - Sales Tax and Service Tax |
| 04 | GST - Free |
| 05 | GST - Zero Rated |
| 06 | GST - Standard Rate |
| 07 | GST - Exempt |
| 08 | GST - Out of Scope |
| E | Exempt |

### Measurement Units Reference

Common measurement codes (UN/ECE Rec. 20):
- `C62` - Piece
- `KGM` - Kilogram
- `MTR` - Meter
- `LTN` - Liter
- `HUR` - Hour
- `DAY` - Day
- `MON` - Month

---

## Error Handling

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request |
| 401 | Unauthorized (invalid/missing API key) |
| 404 | Resource Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests (rate limit exceeded) |
| 500 | Internal Server Error |

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "data": {
    "error": "Specific error type",
    "details": "Additional error details"
  }
}
```

### Common Errors

**1. Missing API Key (401)**
```json
{
  "success": false,
  "message": "API key is missing",
  "data": []
}
```

**2. Invalid API Key (401)**
```json
{
  "success": false,
  "message": "Invalid API key",
  "data": []
}
```

**3. Validation Error (422)**
```json
{
  "success": false,
  "message": "Validation failed",
  "data": {
    "error": "Invalid TIN format",
    "field": "supplier.tin"
  }
}
```

**4. LHDN API Error (500)**
```json
{
  "success": false,
  "message": "Request failed from LHDN",
  "data": {
    "error": "Authentication failed",
    "details": "Invalid client credentials"
  }
}
```

---

## Setup & Configuration

### 1. Environment Variables

Update your `.env` file with the following:

```env
# LHDN E-Invoice API Credentials
EINVOICE_CLIENT_ID=your_lhdn_client_id
EINVOICE_CLIENT_SECRET=your_lhdn_client_secret

# LHDN API URLs
EINVOICE_STAGING_URL=https://preprod-api.myinvois.hasil.gov.my
EINVOICE_STAGING_PORTAL_URL=https://preprod.myinvois.hasil.gov.my
EINVOICE_PROD_URL=https://api.myinvois.hasil.gov.my
EINVOICE_PROD_PORTAL_URL=https://myinvois.hasil.gov.my

# API Security
API_KEY=your_secure_64_char_api_key_here

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=veeco_ifikr_invoice
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Default TIN
DEFAULT_TIN=000000000000

# Environment
APP_ENV=production
APP_DEBUG=false
```

### 2. Generate API Key

```bash
php artisan tinker
>>> echo Str::random(64)
```

### 3. Run Migrations

```bash
php artisan migrate
php artisan db:seed --class=CompanySeeder
php artisan db:seed --class=SettingsSeeder
```

### 4. Certificate Files

Ensure these certificate files exist in `/certificate` directory:
- `private_key.pem` - Your private key
- `certificate.pem` - Your certificate (base64 encoded)
- `certificate_original.pem` - Original certificate file

Set secure permissions:
```bash
chmod 600 /certificate/*.pem
```

---

## Usage Examples

### Example 1: Submit a Simple Invoice

```bash
curl -X POST https://your-domain.com/api/documents \
  -H "X-API-Key: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "documents": [{
      "invoice_num": "INV20250316001",
      "invoice_type": "01",
      "invoice_datetime": "2025-03-16T10:00:00Z",
      "currency": "MYR",
      "supplier": {
        "name": "Your Company Sdn Bhd",
        "tin": "123456789012",
        "registration_type": "BRN",
        "registration_num": "202501000001",
        "msic": "62010",
        "business_desc": "Software Development",
        "contact_num": "+60123456789",
        "email": "billing@yourcompany.com",
        "address1": "Level 1, Tower A",
        "address2": "Tech Park",
        "address3": "Cyberjaya",
        "city": "Sepang",
        "postcode": "63000",
        "state": "10",
        "country": "MY"
      },
      "buyer": {
        "name": "Customer Sdn Bhd",
        "tin": "987654321098",
        "registration_type": "BRN",
        "registration_num": "202501000002",
        "msic": "62010",
        "business_desc": "Software Development",
        "contact_num": "+60198765432",
        "email": "accounts@customer.com",
        "address1": "Level 5, Block B",
        "address2": "Business Center",
        "address3": "Kuala Lumpur",
        "city": "Kuala Lumpur",
        "postcode": "50000",
        "state": "14",
        "country": "MY"
      },
      "items": [{
        "classification": "A",
        "description": "Software License",
        "unit_price": 1200.00,
        "qty": 1,
        "measurement": "C62",
        "tax_type": "06",
        "tax_rate": 10.00,
        "tax_amount": 120.00,
        "subtotal": 1200.00,
        "total_excl_tax": 1200.00
      }],
      "tax_type": "06",
      "total_tax_amt": 120.00,
      "total_taxable_amt": 1200.00,
      "total_tax_amt_per_tax_type": 120.00,
      "total_nett_amt": 1200.00,
      "total_excl_tax": 1200.00,
      "total_incl_tax": 1320.00,
      "total_dsc_val": 0.00,
      "total_charge_amt": 0.00,
      "rounding_amt": 0.00,
      "total_payable": 1320.00
    }]
  }'
```

### Example 2: Get Document Status

```bash
curl -H "X-API-Key: your_api_key" \
     https://your-domain.com/api/documents/DOC202503160001
```

### Example 3: Cancel Document

```bash
curl -X PUT https://your-domain.com/api/documents/DOC202503160001/cancel \
  -H "X-API-Key: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Goods returned by customer"}'
```

### Example 4: Search Documents

```bash
curl -H "X-API-Key: your_api_key" \
     "https://your-domain.com/api/documents/search?invoice_num=INV001&status=Valid"
```

### Example 5: Get Malaysian States

```bash
curl -H "X-API-Key: your_api_key" \
     https://your-domain.com/api/settings/State
```

---

## Security Best Practices

### 1. API Key Management
- Never expose API key in client-side code
- Rotate API keys regularly
- Use environment variables for storing keys
- Generate keys with minimum 64 characters

### 2. Rate Limiting
Configure rate limiting in `routes/api.php`:
```php
Route::middleware(['api.key', 'throttle:60,1'])->group(function () {
    // 60 requests per minute
});
```

### 3. CORS Configuration
Update `config/cors.php`:
```php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST', 'PUT'],
'allowed_origins' => ['https://your-frontend-domain.com'],
'allowed_headers' => ['X-API-Key', 'Content-Type', 'Accept'],
```

### 4. HTTPS Enforcement
Add to `app/Providers/AppServiceProvider.php`:
```php
if (app()->environment('production')) {
    URL::forceScheme('https');
}
```

### 5. Input Validation
Always validate and sanitize input data:
- Validate TIN format
- Validate email addresses
- Validate phone numbers
- Escape special characters

### 6. Database Security
- Use strong passwords
- Restrict database user permissions
- Enable SSL for database connections
- Regular backups

### 7. Certificate Security
- Set file permissions to 600
- Never commit certificates to version control
- Store certificates outside web root
- Rotate certificates before expiration

---

## Testing

### Test with Staging Environment

Set `APP_ENV=local` in `.env` to use staging URLs:
```env
APP_ENV=local
```

### Test Health Endpoint

```bash
curl https://your-domain.com/api/health
```

### Test Authentication

```bash
# Should return 401
curl https://your-domain.com/api/documents

# Should return 200
curl -H "X-API-Key: your_key" https://your-domain.com/api/health
```

---

## Troubleshooting

### Issue: 401 Unauthorized
**Solution:** Check API key in `.env` matches request header

### Issue: "Base table or view not found"
**Solution:** Run `php artisan migrate`

### Issue: "Class 'ApiResponse' not found"
**Solution:** Run `composer dump-autoload`

### Issue: LHDN API authentication failed
**Solution:** Verify `EINVOICE_CLIENT_ID` and `EINVOICE_CLIENT_SECRET`

### Issue: Certificate file not found
**Solution:** Ensure certificate files exist in `/certificate` directory

---

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode temporarily for detailed errors
3. Verify all environment variables are set correctly
4. Ensure certificate files have correct permissions

---

## Version History

- **v1.0** (2025-03-16) - Initial implementation
  - All core e-invoice operations
  - LHDN MyInvois integration
  - Digital signatures and QR codes
  - Complete API documentation
