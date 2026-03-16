<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LHDN E-Invoice API - Documentation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            background: #4caf50;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 15px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .endpoint-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .endpoint-table th,
        .endpoint-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .endpoint-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #667eea;
        }

        .method {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .get { background: #4caf50; color: white; }
        .post { background: #2196f3; color: white; }
        .put { background: #ff9800; color: white; }

        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 15px 0;
        }

        .code-block pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .footer {
            text-align: center;
            padding: 40px 0;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🚀 LHDN E-Invoice API</h1>
            <p>Stateless Proxy Server for Malaysia E-Invoicing</p>
            <div class="status-badge">✅ Production Ready</div>
            <p style="margin-top: 15px; font-size: 0.9em;">Version 1.0.0 | Last Updated: March 16, 2025</p>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>🚀 Quick Start</h2>
            <div class="info">
                <strong>Base URL:</strong> <code>https://your-domain.com/api</code>
            </div>

            <h3>1. Health Check</h3>
            <div class="code-block">
                <pre>curl https://your-domain.com/api/health</pre>
            </div>

            <h3>2. Submit Invoice</h3>
            <div class="code-block">
                <pre>curl -X POST https://your-domain.com/api/documents \
  -H "X-API-Key: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "documents": [{
      "invoice_num": "INV001",
      "invoice_type": "01",
      "currency": "MYR",
      "supplier": { ... },
      "buyer": { ... },
      "items": [ ... ]
    }]
  }'</pre>
            </div>
        </div>

        <div class="card">
            <h2>🔐 Authentication</h2>
            <div class="warning">
                <strong>All endpoints (except /health) require authentication via the <code>X-API-Key</code> header.</strong>
            </div>

            <h3>Authentication Header</h3>
            <div class="code-block">
                <pre>X-API-Key: your_secure_64_char_api_key_here</pre>
            </div>
        </div>

        <div class="card">
            <h2>📡 API Endpoints</h2>

            <table class="endpoint-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Auth</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="method get">GET</span></td>
                        <td><code>/api/health</code></td>
                        <td>No</td>
                        <td>Health check</td>
                    </tr>
                    <tr>
                        <td><span class="method post">POST</span></td>
                        <td><code>/api/documents</code></td>
                        <td>Yes</td>
                        <td>Create & submit e-invoice</td>
                    </tr>
                    <tr>
                        <td><span class="method get">GET</span></td>
                        <td><code>/api/documents/{uid}</code></td>
                        <td>Yes</td>
                        <td>Get document details</td>
                    </tr>
                    <tr>
                        <td><span class="method put">PUT</span></td>
                        <td><code>/api/documents/{uid}/cancel</code></td>
                        <td>Yes</td>
                        <td>Cancel document</td>
                    </tr>
                    <tr>
                        <td><span class="method get">GET</span></td>
                        <td><code>/api/settings/{code}</code></td>
                        <td>Yes</td>
                        <td>Get lookup codes</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>📚 Documentation</h2>
            <p>For complete API documentation, see:</p>
            <ul>
                <li><a href="/docs/API_DOCUMENTATION.md">Full API Documentation</a></li>
                <li><a href="/docs/QUICK_REFERENCE.md">Quick Reference Guide</a></li>
                <li><a href="/README.md">README</a></li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <div class="container">
            <p><strong>LHDN E-Invoice API Proxy Server</strong></p>
            <p>Version 1.0.0 | Production Ready ✅</p>
            <p style="margin-top: 10px; font-size: 0.9em; color: #999;">
                © 2025 VeecoTech Solutions. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
