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

Business logic belongs inside service classes.

Controllers are responsible for receiving requests and returning responses,
but they must not directly implement stock-changing business operations.

---

## Domain Modules

### Catalog

- Category
- Unit
- Product
- Warehouse

### Inventory

- Stock
- StockService

### Warehouse Documents

- Receipt
- ReceiptItem
- ReceiptService
- Issue
- IssueItem
- IssueService

---

## Stock Operations

StockService provides the basic inventory operations:

- increase stock quantity;
- decrease stock quantity;
- reserve available stock;
- release reserved stock.

Stock decreases are allowed only when sufficient available stock exists.

ReceiptService uses StockService to increase stock when a Receipt is posted.

IssueService uses StockService to decrease stock when an Issue is posted.

---

## Transactions

All document operations that modify stock must use `DB::transaction()`.

The following actions must be atomic:

- updating stock for every document item;
- changing the warehouse document status to `posted`.

If processing any document item fails, all stock changes and the document status change must be rolled back.

---

## Warehouse Documents

Receipt and Issue are warehouse documents.

- Receipt represents goods entering a warehouse.
- Issue represents goods leaving a warehouse.
- Issue is not a commercial Sale document.
- Commercial Sale will be implemented separately.

---

## Planned Modules

- StockMovement
- Stock recalculation
- Transfer
- Inventory
- Roles and permissions
- Commercial Sale
