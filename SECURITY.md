# 🛡️ Security Policy

## Overview

This document describes the security practices and policies for the **Ticketing System for Olympic Games Paris 2024 API**.

---

## 🚪 Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| `v1.x`  | :white_check_mark: |
| `v0.x`  | :x:                |

---

## 🔐 Secure Development Practices

1. **Authentication & Authorization**  
   - Laravel Sanctum for stateless API tokens  
   - Optional Google2FA (Time-based One-Time Password) for high-privilege users  
   - Role-based guards (`admin`, `employee`, `user`) enforced at middleware level

2. **Input Validation**  
   - All endpoints validate payloads via FormRequest classes  
   - OpenAPI (Swagger) schema definitions kept in sync with controllers

3. **Data Protection**  
   - Enforce HTTPS in production (HSTS header)  
   - Sensitive configuration stored in environment variables only  
   - Redis access restricted via VPC / firewall rules  

4. **Dependency Management**  
   - Composer-managed with explicit version constraints  
   - Automated `composer audit` run in CI to detect known vulnerabilities

5. **Secrets & Keys**  
   - No secrets committed to Git; `.env.example` only  
   - Stripe webhook secret, API keys loaded via `.env`  
   - Rotate secrets quarterly or after any suspected leak

6. **CORS Configuration**  
   - Middleware in Laravel to validate the requests `Origin`   
   - Only the listed origins are allowed
   - No entry for unknown cross-origin calls (response HTTP 403)  

---

## 🕵️ Vulnerability Reporting

If you discover a security vulnerability, please contact us. 
