# 🚀 Future Enhancements

This document collects the planned improvements and roadmap items for the Ticketing System API of the Paris 2024 Olympic Games.

---

## 1. Multi-Currency & Internationalization

- **Support for additional currencies**  
  - USD, GBP, JPY, AUD, etc.  
  - Automatic exchange-rate fetch via external API (e.g. OpenExchangeRates)  
- **Dynamic locale detection & formatting**  
  - Date, time, number and currency formatting per user locale  
  - Extendable translations for all invoice and ticket PDFs  

---

## 2. Dynamic Discount Engine

- **Rule-based discounts**  
  - Volume discounts (e.g. “buy 3 get 1 free”)  
  - Early-bird / late-purchase pricing tiers  
- **Coupon codes & promotions**  
  - One-time or multi-use codes, expiration dates  
  - Campaign management UI for marketing team  

---

## 3. GraphQL Gateway

- **GraphQL facade**  
  - Single entrypoint for client apps  
  - Schema stitching over existing REST endpoints  
- **Automatic persisted queries**  
  - Reduce payload size and improve performance  
- **Role-based field-level authorization**  

---

## 4. Event Sourcing & Audit Trails

- **Immutable event log**  
  - Record every cart, payment and ticket action  
- **Replayable streams**  
  - Rebuild read models (projections) for reporting or debugging  
- **Time-travel debugging**  

---

## 5. Self-Service Client Portal

- **React-based SPA**  
  - Order history, invoice downloads, ticket management  
- **Real-time notifications**  
  - WebSockets or Server-Sent Events (SSE) for payment status updates  
- **User profile & preferences**  
  - Newsletter opt-in, saved payment methods  

---

## 6. Scalability & High Availability

- **Microservice decomposition**  
  - Separate services for Users, Products, Cart, Payments, Tickets, Invoices  
- **Kubernetes orchestration**  
  - Auto-scaling, rolling updates, health checks  
- **Distributed cache & message bus**  
  - Redis Cluster, RabbitMQ / Kafka for event distribution  

---

## 7. Analytics & Reporting Dashboard

- **Admin UI with charts**  
  - Sales by event, time window, geography  
- **Exportable CSV / Excel reports**  
- **Real-time monitoring**  
  - Alerts on payment failures, abnormal traffic  

---

## 8. Offline & Mobile Support

- **Progressive Web App (PWA)**  
  - Offline cart caching, background sync  
- **Mobile SDK**  
  - Simplify integration for native iOS/Android apps  

---

## 9. Plugin & Extension Framework

- **Hook system**  
  - Allow custom code (e.g. send SMS, integrate new payment provider)  
- **Marketplace**  
  - Shareable extensions and integrations  
