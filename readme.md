# SilverNet Management

A web-based ISP (Internet Service Provider) management system built with PHP and MySQL. Designed for small to medium ISPs running MikroTik routers, it covers the full operational workflow from infrastructure management to customer billing.

---

## Features

**Infrastructure**
- Node and POP management with geographic coordinates and photo documentation
- Wireless and fiber connection mapping between nodes
- MikroTik router registration with online/offline status monitoring

**MikroTik Integration**
- Live PPPoE session monitoring
- IP Pool management (create, edit, delete directly on router)
- PPPoE Server configuration per router (service name, interface, authentication)
- PPP Profile synchronization
- Queue management
- Router proxy access — embedded router web UI with a navigation bar overlay

**Customer Management**
- Customer registration with PPPoE credentials, router details, and billing cycle configuration
- Automatic customer number generation
- Package assignment with MikroTik profile sync on change
- Customer detail page with live connection status and traffic data
- Import PPPoE users from MikroTik

**Billing**
- Monthly invoice generation (manual per customer or bulk for all active customers)
- Payment recording with method tracking (cash, transfer, QRIS, virtual account)
- Invoice and receipt printing in A4, A5, and 58mm thermal formats
- Discount support per customer
- Overdue detection

**Settings**
- ISP profile (name, logo, address, phone)
- Payment method configuration
- Billing cycle and isolation thresholds
- API key management for external integrations

**User Management**
- Four roles: Super Admin, Admin, Teknisi, Kasir
- Single active session enforcement per user
- Password management with old password verification
- User activation/deactivation

**External API**
- `GET /api/v1/customers.php` — customer data with optional live MikroTik stats
- `GET /api/v1/status.php` — online/offline status optimized for monitoring systems
- Authentication via Bearer token or `?api_key=` query parameter

---

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 / MariaDB 10.4 or higher
- PHP extensions: `pdo`, `pdo_mysql`, `openssl`, `mbstring`
- Web server: Apache (with mod_rewrite) or Nginx
- Composer (for MikroTik RouterOS API library)
- MikroTik RouterOS API enabled on routers (port 8728, or 8729 for SSL)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/yourusername/silver-network-management.git
cd silver-network-management
```

### 2. Install dependencies

```bash
composer install
```

### 3. Run the web installer

Place the project folder inside your web server's document root, then open:

```
http://localhost/silver-network-management/install/
```

The installer will guide you through:
1. System requirements check
2. Database connection configuration
3. Database and table creation (13 tables)
4. Superadmin account creation
5. Automatic writing of `config/database.php`

### 4. Remove the install directory

After installation is complete, delete the `/install/` directory from the server. Leaving it accessible is a security risk.

```bash
rm -rf install/
```

### 5. Log in

Navigate to the application root and log in with the superadmin credentials you created during installation.

---

## Manual Installation (without installer)

### 1. Configure the database

Copy and edit the database configuration:

```bash
cp config/database.php.example config/database.php
```

Edit `config/database.php` with your database credentials:

```php
$host = 'localhost';
$db   = 'silvernet-management';
$user = 'root';
$pass = 'your_password';
$port = 3306;
```

### 2. Import the database schema

```bash
mysql -u root -p silvernet-management < silvernet-management-structure.sql
```

### 3. Create the first superadmin

Run this SQL after importing the schema, replacing the placeholder values:

```sql
INSERT INTO users (name, username, password, role, is_active)
VALUES (
    'Administrator',
    'admin',
    '$2y$10$...', -- use password_hash() output
    'superadmin',
    1
);
```

Or use PHP to generate the hash:

```php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

---

## Project Structure

```
silver-network-management/
├── api/                    # Internal AJAX endpoints and external API
│   ├── v1/                 # External API (customers, status)
│   ├── proxy_token.php     # Router proxy token generation
│   ├── pools.php           # IP pool data for forms
│   ├── queues.php          # Queue data for forms
│   └── ppp_profiles.php    # PPP profile data for forms
├── auth/                   # Login/logout handlers
├── config/
│   ├── bootstrap.php       # Single entry point loaded by all pages
│   ├── database.php        # Database credentials (not committed)
│   └── settings.php        # Runtime settings loader from DB
├── helpers/
│   └── security.php        # requireLogin(), requireRole(), session enforcement
├── install/                # Web installer (delete after use)
├── layout/
│   ├── header.php          # HTML head, Tabler CSS & JS
│   └── sidebar.php         # Navigation sidebar
├── mikrotik/               # MikroTik API wrapper functions
│   ├── connection.php      # get_mikrotik_client(), mikrotik_query()
│   ├── ppp.php             # PPP secrets and profiles
│   ├── queue.php           # Simple queues
│   ├── ip_pool.php         # IP pools
│   └── system.php          # System resource info
├── uploads/                # User-uploaded files (logos, photos)
├── billing.php             # Billing management
├── customers.php           # Customer list
├── packages.php            # Package list
├── routers.php             # Router list and status
├── nodes.php               # Node management
├── pops.php                # POP management
├── connections.php         # Network connection mapping
├── ip_pool.php             # IP pool management
├── pppoe_server.php        # PPPoE server management
├── hotspot.php             # Hotspot management
├── import_pppoe.php        # Import PPPoE users from router
├── users.php               # User and role management
├── settings.php            # System settings and API keys
├── router_proxy.php        # Router web UI proxy with overlay bar
├── login.php               # Login page
└── index.php               # Dashboard
```

---

## User Roles

| Role | Access |
|------|--------|
| Super Admin | Full access including user management, all settings, and all data |
| Admin | Customers, billing, packages, routers, infrastructure. Cannot manage other users |
| Teknisi | Infrastructure, routers, nodes, connections. No financial access |
| Kasir | Billing and payments only |

---

## MikroTik API Setup

Enable the RouterOS API on each router before adding it to the system:

```
/ip service enable api
/ip service set api port=8728
```

For SSL API (recommended for production):

```
/ip service enable api-ssl
/ip service set api-ssl port=8729
```

The API user should have sufficient privileges. A minimal policy for read/write operations:

```
/user group add name=silvernet-api policy=read,write,api,!sensitive
/user add name=silvernet group=silvernet-api password=yourpassword
```

---

## External API

All external API endpoints require authentication via one of:

- HTTP header: `Authorization: Bearer YOUR_API_KEY`
- Query parameter: `?api_key=YOUR_API_KEY`

API keys are created and managed in Settings > API Keys.

### GET /api/v1/status.php

Returns online/offline status for all customers. Intended for monitoring systems and dashboards.

**Permissions required:** `read_live`

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `node_id` | int | Filter by node |
| `router_id` | int | Filter by router |
| `status` | string | `online` or `offline` |

**Response:**

```json
{
  "summary": { "total": 120, "online": 98, "offline": 22 },
  "fetched_at": "2025-03-10 14:32:01",
  "elapsed_ms": 412,
  "customers": [
    {
      "customer_number": "SN-0001",
      "name": "John Doe",
      "is_online": true,
      "wan_ip": "10.25.0.187",
      "uptime": "2d3h12m"
    }
  ]
}
```

### GET /api/v1/customers.php

Returns customer data from the database with optional live MikroTik stats.

**Permissions required:** `read_customers` (add `read_live` for live data)

**Query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | int | Single customer by ID |
| `search` | string | Search by name, phone, or PPPoE username |
| `status` | string | `active`, `isolated`, `terminated`, `free` |
| `node_id` | int | Filter by node |
| `live` | 0 or 1 | Include live traffic and session data from router |

---

## Configuration Reference

All settings are stored in the `settings` table and configurable from the Settings page.

| Key | Description |
|-----|-------------|
| `isp_name` | ISP display name used in invoices and the UI |
| `address` | ISP address shown on invoices |
| `admin_phone` | Contact number shown on invoices |
| `isp_logo` | Path to logo file |
| `billing_due_days` | Days after cycle date before invoice is overdue |
| `billing_isolation_days` | Days after due date before customer is isolated |
| `timezone` | PHP timezone string, e.g. `Asia/Jakarta` |
| `proxy_secret` | HMAC secret for router proxy token signing |
| `payment_methods` | JSON array of configured payment methods |

---

## Security Notes

- The `/install/` directory must be deleted after installation.
- `config/database.php` should not be committed to version control. Add it to `.gitignore`.
- The `uploads/` directory should not be web-accessible for non-image files. Consider restricting with `.htaccess`.
- Router proxy tokens are HMAC-signed and expire hourly.
- Single-session enforcement prevents concurrent logins for the same user account.
- External API keys are scoped by permission and can be revoked individually from the Settings page.

---

## .gitignore Recommendations

```gitignore
config/database.php
uploads/
vendor/
install/.installed
*.log
.env
```

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 7.4+ |
| Database | MySQL / MariaDB |
| Frontend | Tabler UI (Bootstrap 5) |
| MikroTik API | RouterOS PHP Client (via Composer) |
| Session | PHP native sessions with single-session enforcement |

---

## License

This project is proprietary software. All rights reserved.