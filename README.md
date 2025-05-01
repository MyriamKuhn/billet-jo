# 🎟️ Ticketing System for Olympic Games Paris 2024 API

Welcome to the backend of the Olympic Games Ticketing Platform.  
This project provides a RESTful API to manage users, cart operations, ticket purchases, two-factor authentication (2FA), and more.

![Tests](https://img.shields.io/badge/tests-148_passed-4caf50.svg) ![Assertions](https://img.shields.io/badge/assertions-375_success-2196f3.svg) ![Test Coverage](https://img.shields.io/badge/coverage-79.3%25-brightgreen) ![Swagger Docs](https://img.shields.io/badge/Swagger%20Docs-Available-brightgreen)
![PHP version](https://img.shields.io/badge/php-8.3-blue) ![Laravel](https://img.shields.io/badge/laravel-12-red)

---

## 🚀 Project Features

- User registration with email verification
- Secure login with optional Two-Factor Authentication (2FA)
- Shopping cart and payment system
- Ticket generation with QR codes
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

✅ **148 tests passed** with **375 assertions**.  
📊 **Coverage: 79.3%**
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

## 🛠️ Tech Stack

- PHP 8.3.6 (cli)
- Laravel 12.9.2
- MySQL 8.x
- Laravel Sanctum for authentication
- Predis / Redis (caching)
- Google2FA for Two-Factor Authentication
- PHPUnit for automated testing
- AlwaysData (database hosting)
- VPS server hosting (Plesk)
- L5-Swagger for API documentation

---

## 📜 License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT)

*Developed with ❤️ by Myriam Kühn.*
