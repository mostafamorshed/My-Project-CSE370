# EzHub Admin Panel — Setup Guide

## Folder Structure
```
ezhub-backend/
├── index.html          ← Frontend (open this in browser)
├── config/
│   └── db.php          ← Database config
└── api/
    ├── products.php    ← Products CRUD
    ├── categories.php  ← Categories CRUD
    ├── orders.php      ← Orders management
    ├── campaigns.php   ← Group Buy Campaigns (Unique Feature 1)
    └── stats.php       ← Dashboard stats
```

## Setup Steps

1. **Copy this folder** to your XAMPP/WAMP `htdocs/` directory:
   ```
   C:/xampp/htdocs/ezhub-backend/
   ```

2. **Import the database**:
   - Open phpMyAdmin → Create database `ezhub`
   - Import `ezhub (1).sql`

3. **Check DB credentials** in `config/db.php`:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_USER', 'root');
   define('DB_PASS', '');     // Change if you have a password
   define('DB_NAME', 'ezhub');
   ```

4. **Open in browser**:
   ```
   http://localhost/ezhub-backend/index.html
   ```

---

## Unique Features

### ⚡ Feature 1: Group Buy Campaign Tracker
- Go to **Group Buys** in the sidebar
- Shows live progress bars for each campaign
- Displays participants joined vs target needed
- Countdown timer showing hours remaining
- "Join Deal" button — updates participant count in real time
- Campaign auto-completes when target is reached

### 📦 Feature 2: Smart Stock Badge (on Products page)
- Every product card shows a dynamic inventory badge:
  - 🟢 **In Stock · [qty]** — quantity ≥ 30
  - 🟡 **Low · [qty]** — quantity between 1–29 (warning)
  - 🔴 **Out of Stock** — quantity = 0
- Stock is pulled live from the `inventory` table
- Dashboard shows a "Low Stock" counter

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `api/products.php` | All products with stock + ratings |
| GET | `api/products.php?id=1` | Single product |
| GET | `api/products.php?category=2` | Filter by category |
| GET | `api/products.php?search=keyboard` | Search products |
| POST | `api/products.php` | Add product |
| PUT | `api/products.php?id=1` | Update product |
| DELETE | `api/products.php?id=1` | Delete product |
| GET | `api/categories.php` | All categories with product count |
| POST | `api/categories.php` | Add category |
| DELETE | `api/categories.php?id=1` | Delete category |
| GET | `api/orders.php` | All orders |
| GET | `api/orders.php?status=pending` | Filter orders |
| GET | `api/orders.php?id=1` | Order with items |
| PUT | `api/orders.php?id=1` | Update order status |
| GET | `api/campaigns.php` | All campaigns with progress |
| POST | `api/campaigns.php` | Join a campaign |
| GET | `api/stats.php` | Dashboard statistics |
