# LHDN E-Invoice API Proxy Server

A **stateless Laravel-based proxy server** for LHDN (Lembaga Hasil Dalam Negeri) Malaysia e-invoicing integration.

## 🚀 Features

- ✅ Complete LHDN Integration with OAuth2
- ✅ Digital Signatures & XML Generation
- ✅ QR Code Generation
- ✅ Document Status Tracking
- ✅ API Key Authentication
- ✅ Comprehensive Logging

## 📚 Documentation

- **[API Documentation](docs/API_DOCUMENTATION.md)** - Complete API reference
- **[Quick Reference](docs/QUICK_REFERENCE.md)** - Quick start guide

## 🚀 Quick Start

### 1. Install Dependencies
```bash
composer install
```

### 2. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

Update `.env`:
```env
DB_DATABASE=veeco_ifikr_invoice
EINVOICE_CLIENT_ID=your_client_id
EINVOICE_CLIENT_SECRET=your_client_secret
API_KEY=your_secure_key
```

### 3. Setup Database
```bash
php artisan migrate
php artisan db:seed --class=CompanySeeder
php artisan db:seed --class=SettingsSeeder
```

### 4. Test API
```bash
curl https://your-domain.com/api/health
```

## 📡 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | Health check |
| POST | `/api/documents` | Submit e-invoice |
| GET | `/api/documents/{uid}` | Get document |
| PUT | `/api/documents/{uid}/cancel` | Cancel document |
| GET | `/api/settings/{code}` | Get lookup codes |

See [API Documentation](docs/API_DOCUMENTATION.md) for complete reference.

## 🔧 Requirements

- PHP 8.2+
- MySQL 5.7+
- Composer 2.x
- LHDN Digital Certificate

## 🔒 Security

- API Key Authentication (X-API-Key header)
- Encrypted logging
- Rate limiting ready
- HTTPS enforcement for production

## 📝 License

Proprietary Software - VeecoTech Solutions

---

**Version:** 1.0.0 | **Status:** Production Ready ✅
