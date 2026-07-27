# Architecture

## Layers

Presentation

↓

Controllers

↓

Form Requests

↓

Services

↓

Models

↓

Database

---

## Principles

- SOLID
- Clean Architecture
- Thin Controllers
- Rich Domain
- Service Layer

---

## Business Logic

Business logic belongs only inside Services.

---

## Transactions

All stock-changing operations must use DB::transaction().

---

## Future Modules

- Warehouse
- Receipt
- Sale
- Transfer
- Inventory
- Stock
