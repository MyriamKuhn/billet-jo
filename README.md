# 🎟️ Ticketing System for Olympic Games Paris 2024 API

Welcome to the backend of the Olympic Games Ticketing Platform.This project provides a RESTful API to manage users, cart operations, ticket purchases, payments, invoicing, two-factor authentication (2FA), and more.

![Tests](https://img.shields.io/badge/tests-350_passed-4caf50.svg) ![Assertions](https://img.shields.io/badge/assertions-1165_success-2196f3.svg) ![Test Coverage](https://img.shields.io/badge/coverage-100%25-darkgreen) ![Swagger Docs](https://img.shields.io/badge/Swagger%20Docs-Available-brightgreen)
![PHP version](https://img.shields.io/badge/php-8.3-blue) ![Laravel](https://img.shields.io/badge/laravel-12-red)

---

## 📋 Table of Contents

- [🎟️ Ticketing System for Olympic Games Paris 2024 API](#️-ticketing-system-for-olympic-games-paris-2024-api)
  - [📋 Table of Contents](#-table-of-contents)
  - [✨ Features](#-features)
  - [🔧 Requirements](#-requirements)
  - [📘 API Documentation](#-api-documentation)
  - [📥 Installation](#-installation)
  - [🏗️ Environment Setup](#️-environment-setup)
  - [🏃‍♂️ Running the Application](#️-running-the-application)
  - [🧪 Testing](#-testing)
  - [🔒 Security](#-security)
  - [🚀 Future Evolutions](#-future-evolutions)
  - [🛠️ Tech Stack](#️-tech-stack)
  - [📜 License](#-license)

---

## ✨ Features

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

## 🔧 Requirements

- PHP 8.3
- Laravel 12.x
- Composer 2.x
- MySQL 8.x (or compatible)
- Redis

> **Note:**  
> The project requires at least PHP 8.2 to install via Composer.

---

## 📘 API Documentation

The API is documented with Swagger and is always up-to-date.

Full Documentation is available here:
[https://api-jo2024.mkcodecreations.dev/](https://api-jo2024.mkcodecreations.dev/)

---

## 📥 Installation

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

## 🏗️ Environment Setup

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

## 🏃‍♂️ Running the Application

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

Full HTML report available under [Coverage HTML](https://myriamkuhn.github.io/billet-jo/).  

✅ **350 tests passed** with **1165 assertions**.  
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

## 🔒 Security

We follow industry best practices:
- **Authentication**: Laravel Sanctum + optional Google2FA
- **Authorization**: Role-based guards (admin, employee, user)
- **Input Validation**: FormRequests everywhere, strict OpenAPI schemas
- **Data protection**: HTTPS enforced, Redis access behind VPC
- **CORS Configuration**: Only allowed origins can call the API
- **Vulnerability Scanning**: GitHub CodeQL SAST on push, OWASP ZAP DAST weekly

_For more details → [SECURITY.md](./SECURITY.md)_

---

## 🚀 Future Evolutions

Planned improvements:
- Multi-currency support (USD, GBP, JPY…)  
- Dynamic discount rules engine  
- GraphQL gateway in front of REST  
- Event sourcing for audit trails  
- Self-service client portal (React)

_For more details → [FUTURE_ENHANCEMENTS.md](./FUTURE_ENHANCEMENTS.md)_

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
