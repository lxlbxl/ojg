# OJG Herbal - Health Assessment System

## Overview

This is the main deployment package for OJG Herbal (Nigeria - NGN currency). It includes:

- **Backend**: PHP API, Admin Panel, Database, AI Orchestration
- **Member Area**: Authenticated member dashboard with personalized plans
- **Funnels**: PCOS, Acne, Weight, Egbon, Men's Vitality lead capture & sales funnels

## Quick Start

### System Requirements

- PHP 7.4 or higher
- SQLite 3 or MySQL 5.7+
- Apache/Nginx web server

### Installation

1. Upload all files to your web server
2. Navigate to `http://yourdomain.com/backend/admin/`
3. Login with default credentials: `admin` / `admin123`
4. The SQLite database will auto-initialize

### Default Credentials

- **Admin Panel**: `/backend/admin/`
  - Username: `admin`
  - Password: `admin123`

⚠️ **Change the default password immediately!**

## File Structure

```
├── backend/          # PHP Backend & Admin Panel
├── member/           # Member Area
├── pcos/             # PCOS Funnel
├── acne/             # Acne Funnel
├── weight/           # Weight Loss Funnel
├── egbon/            # Egbon Funnel
├── js/               # Shared JavaScript
└── images/           # Images
```

## Funnels

| Funnel | URL Path | Currency |
|--------|----------|----------|
| PCOS | `/pcos/` | NGN |
| Acne | `/acne/` | NGN |
| Weight Loss | `/weight/` | NGN |
| Egbon | `/egbon/` | NGN |
| Men's Vitality | `/mens/` | NGN |

## Configuration

### Payment Gateway (Flutterwave)

Edit `js/config.js`:
```javascript
payment: {
    flutterwave: {
        publicKey: "YOUR_LIVE_KEY",
        environment: "production",
    }
}
```

### Database (MySQL Option)

Create `backend/config/db_config.php`:
```php
<?php
define('DB_TYPE', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'ojg_herbal');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_pass');
```

## Support

Email: pcos@ojg.ng

## License

© 2025 OJG Herbal. All rights reserved.