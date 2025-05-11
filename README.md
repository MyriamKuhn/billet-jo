# 🎟️ Ticketing System for Olympic Games Paris 2024 API

Welcome to the backend of the Olympic Games Ticketing Platform.This project provides a RESTful API to manage users, cart operations, ticket purchases, payments, invoicing, two-factor authentication (2FA), and more.

![Tests](https://img.shields.io/badge/tests-290_passed-4caf50.svg) ![Assertions](https://img.shields.io/badge/assertions-895_success-2196f3.svg) ![Test Coverage](https://img.shields.io/badge/coverage-100%25-darkgreen) ![Swagger Docs](https://img.shields.io/badge/Swagger%20Docs-Available-brightgreen)
![PHP version](https://img.shields.io/badge/php-8.3-blue) ![Laravel](https://img.shields.io/badge/laravel-12-red)

---

## 🚀 Project Features

- User registration with email verification
- Secure login with optional Two-Factor Authentication (2FA)
- Shopping cart and payment system
- Full cart-to-payment flow via Stripe (initiation, webhook, status, refunds)
- Partial and full refunds with dynamic invoice regeneration
- PDF invoice generation (DomPDF) and download endpoints (user & admin)
- Event-driven invoice creation (`InvoiceRequested` + `GenerateInvoicePdf` listener)
- Ticket generation with QR codes and on-the-fly PNG downloads (user & admin)
- PDF ticket generation and download endpoints (user & admin)
- Admin and employee user roles
- Full API with documentation (Swagger)

---

## Table of Contents

- [🎟️ Ticketing System for Olympic Games Paris 2024 API](#️-ticketing-system-for-olympic-games-paris-2024-api)
  - [🚀 Project Features](#-project-features)
  - [Table of Contents](#table-of-contents)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Environment Setup](#environment-setup)
  - [Running the Application](#running-the-application)
  - [🧪 Testing](#-testing)
  - [API Documentation](#api-documentation)
  - [Payment \& Invoicing](#payment--invoicing)
    - [Initiate a payment](#initiate-a-payment)
    - [Webhook endpoint](#webhook-endpoint)
    - [Check payment status](#check-payment-status)
    - [Refund a payment](#refund-a-payment)
    - [Invoice management](#invoice-management)
    - [QR Code Download](#qr-code-download)
    - [Ticket PDF Download](#ticket-pdf-download)
  - [🛠️ Tech Stack](#️-tech-stack)
  - [📜 License](#-license)

--- 

## Requirements

- PHP 8.3
- Laravel 12.x
- Composer 2.x
- MySQL 8.x (or compatible)
- Redis

> **Note:**  
> The project requires at least PHP 8.2 to install via Composer.

---

## Installation

1. Clone the repository:
    ```bash
    git clone https://github.com/MyriamKuhn/billet-jo.git
    cd your-repo
    ```
2. Install dependencies:
    ```bash
    composer install
    ```
3. Copy the environment configuration:
    ```bash
    cp .env.example .env
    ```
4. Generate application key:
    ```bash
    php artisan key:generate
    ```
5. Migrate the database
   ```
   php artisan migrate
   ```
6. Start the server
   ```
   php artisan serve
   ```

---

## Environment Setup

Configure your `.env` file according to your local environment.

Example for database settings:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```
Ensure Redis and Mail settings are configured if applicable.

---

## Running the Application

Start the local development server:

```bash
php artisan serve
```
Access the API at:
```
http://localhost:8000
```

---

## 🧪 Testing

The application has a **full feature test coverage**, including:

- Database schema and relationships
- Authentication and authorization
- Two-Factor Authentication
- Cart and payment logic
- Performance (N+1 queries detection)

✅ **290 tests passed** with **895 assertions**.  
📊 **Coverage: 100%**
(Last tested on: `php artisan test`)

To run all automated tests:
```
php artisan test
```
To run tests with coverage (Xdebug is needed):
```
php artisan test --coverage
```

---

## API Documentation

The API is documented with Swagger and is always up-to-date.

📄 Swagger Documentation is available here:
[https://api-jo2024.mkcodecreations.dev/](https://api-jo2024.mkcodecreations.dev/)

---

## Payment & Invoicing

### Initiate a payment

**POST** `/api/payments`  
Request body:  
```json
{ "cart_id": 42, "payment_method": "stripe" }
```
Response: 201 +  
```json
{ "data": { "uuid": "...", "status": "pending", "client_secret": "..." } }
```

### Webhook endpoint

**POST** `/api/payments/webhook`  
Handles `payment_intent.succeeded` / `payment_intent.payment_failed`, updates status and dispatches invoice generation.

### Check payment status

**GET** `/api/payments/{uuid}`  
Response:  
```json
{ "status": "paid", "paid_at": "2025-05-10T14:30:00+02:00" }
```

### Refund a payment

**POST** `/api/payments/{uuid}/refund`  _(admin only)_  
Request body:  
```json
{ "amount": 25.00 }
```  
Response: 200 +  
```json
{ "uuid": "...", "refunded_amount": 25.00, "status": "refunded", "refunded_at": "..." }
```

### Invoice management

**GET** `/api/invoices`  
Lists authenticated user’s invoices with download URLs.  

**GET** `/api/invoices/{filename}`  
Downloads the PDF invoice (user).  

**GET** `/api/invoices/admin/{filename}`  _(admin only)_  
Downloads any invoice PDF. 

### QR Code Download

**GET** `/api/tickets/qr/{filename}`  
  Download your ticket’s QR code image (PNG).  
  - Authenticated user only (200 + `image/png`, 404 if not found).

**GET** `/api/tickets/admin/qr/{filename}` _(admin only)_  
  Download any ticket’s QR code (PNG).  
  - Admin guard (403 if non-admin, 404 if missing).

### Ticket PDF Download

**GET** `/api/tickets/{filename}`  
  Download your ticket as a PDF.  
  - Authenticated user only  
  - Response: `200 OK` + `Content-Type: application/pdf`  
  - `404 Not Found` si le fichier n’existe pas.

**GET** `/api/tickets/admin/{filename}` _(admin only)_  
  Download any ticket PDF by filename.  
  - Admin guard (`403 Forbidden` si non-admin)  
  - Response: `200 OK` + `Content-Type: application/pdf`  
  - `404 Not Found` si le fichier n’existe pas.
---

## 🛠️ Tech Stack

- PHP 8.3.6 (cli)
- Laravel 12.9.2
- MySQL 8.x
- Laravel Sanctum for authentication
- Predis / Redis (caching)
- Google2FA for Two-Factor Authentication
- PHPUnit for automated testing
- AlwaysData (database hosting)
- Barryvdh/DomPDF for PDF generation
- endroid/qr-code for QR code generation  
- VPS server hosting (Plesk)
- L5-Swagger for API documentation
- Stripe (test mode)

---

## 📜 License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT)

*Developed with ❤️ by Myriam Kühn.*
