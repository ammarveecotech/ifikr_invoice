# API Quick Reference Guide

## Base URL
```
https://your-domain.com/api
```

## Authentication Header
```http
X-API-Key: your_api_key_here
```

---

## Endpoints Summary

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/health` | No | Health check |
| POST | `/documents` | Yes | Create & submit e-invoice |
| GET | `/documents/{uid}` | Yes | Get document details |
| GET | `/documents/{uid}/raw` | Yes | Get raw XML document |
| GET | `/documents/search` | Yes | Search documents |
| PUT | `/documents/{uid}/cancel` | Yes | Cancel document |
| PUT | `/documents/{uid}/reject` | Yes | Reject document |
| GET | `/submissions/{uid}` | Yes | Get submission status |
| POST | `/validate/tin` | Yes | Validate TIN |
| GET | `/documents/{uid}/qr` | Yes | Generate QR code |
| GET | `/settings/{code}` | Yes | Get lookup codes |

---

## Quick Start Examples

### 1. Health Check
```bash
curl https://your-domain.com/api/health
```

### 2. Submit Invoice
```bash
curl -X POST https://your-domain.com/api/documents \
  -H "X-API-Key: your_key" \
  -H "Content-Type: application/json" \
  -d '{
    "documents": [{
      "invoice_num": "INV001",
      "invoice_type": "01",
      "invoice_datetime": "2025-03-16T10:00:00Z",
      "currency": "MYR",
      "supplier": { ... },
      "buyer": { ... },
      "items": [ ... ],
      "tax_type": "06",
      "total_payable": 1320.00
    }]
  }'
```

### 3. Get Document
```bash
curl -H "X-API-Key: your_key" \
     https://your-domain.com/api/documents/DOC_UUID
```

### 4. Cancel Document
```bash
curl -X PUT https://your-domain.com/api/documents/DOC_UUID/cancel \
  -H "X-API-Key: your_key" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Goods returned"}'
```

### 5. Validate TIN
```bash
curl -X POST https://your-domain.com/api/validate/tin \
  -H "X-API-Key: your_key" \
  -H "Content-Type: application/json" \
  -d '{"tin": "123456789012"}'
```

### 6. Get States
```bash
curl -H "X-API-Key: your_key" \
     https://your-domain.com/api/settings/State
```

---

## Invoice Types

| Code | Type |
|------|------|
| 01 | Standard Invoice |
| 02 | Debit Note |
| 03 | Credit Note |
| 04 | Self-Billed Invoice |
| 07 | Consolidated Invoice |

---

## Tax Types

| Code | Type |
|------|------|
| 06 | GST Standard Rate (10%) |
| E | Exempt |

---

## Malaysian State Codes

| Code | State |
|------|-------|
| 14 | WP Kuala Lumpur |
| 10 | WP Putrajaya |
| 01 | Johor |
| 02 | Kedah |
| 08 | Penang |
| 11 | Selangor |

---

## Common Measurement Units

| Code | Unit |
|------|------|
| C62 | Piece |
| KGM | Kilogram |
| MTR | Meter |
| LTN | Liter |
| HUR | Hour |

---

## Response Format

**Success:**
```json
{
  "success": true,
  "message": "Success message",
  "data": { }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Error message",
  "data": { }
}
```

---

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 401 | Unauthorized |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## Required Fields for Document Submission

### Document Level
- `invoice_num` - Unique invoice number
- `invoice_type` - Invoice type code
- `invoice_datetime` - ISO 8601 datetime
- `currency` - Currency code (MYR)
- `tax_type` - Tax type code
- `total_payable` - Total amount payable

### Supplier
- `name` - Company name
- `tin` - Tax ID (12 digits)
- `registration_type` - BRN/NRIC/passport
- `registration_num` - Registration number
- `msic` - MSIC code
- `contact_num` - Phone number
- `email` - Email address
- `address1`, `city`, `postcode`, `state`, `country`

### Buyer
- Same as supplier fields

### Items (Minimum 1)
- `description` - Item description
- `unit_price` - Price per unit
- `qty` - Quantity
- `tax_type` - Tax type
- `tax_amount` - Tax amount
- `subtotal` - Subtotal amount

---

## Environment Variables

```env
# LHDN Credentials
EINVOICE_CLIENT_ID=your_client_id
EINVOICE_CLIENT_SECRET=your_client_secret

# API Key
API_KEY=your_64_char_key

# Environment
APP_ENV=local  # local = staging, production = live
```

---

## Testing Checklist

- [ ] Health check returns 200
- [ ] API key validation works
- [ ] Can submit test invoice
- [ ] Can retrieve document
- [ ] Can cancel document
- [ ] TIN validation works
- [ ] QR code generates

---

## Common Errors

| Error | Solution |
|-------|----------|
| 401 Unauthorized | Check API key |
| 422 Validation | Check required fields |
| 500 Server Error | Check logs, verify credentials |

---

## Need Help?

See full documentation: `docs/API_DOCUMENTATION.md`
