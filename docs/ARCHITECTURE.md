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

## Principles

- SOLID
- Clean Architecture
- Thin Controllers
- Rich Domain
- Service Layer

## Business Logic

Business logic belongs inside service classes.

Controllers are responsible for receiving requests and returning responses,
but they must not directly implement stock-changing business operations.

## Domain Modules

### Catalog

- Category
- Unit
- Product
- Warehouse

### Inventory

- Stock
- StockService
- StockReconciliationService
- StockMovement

### Warehouse Documents

- Receipt
- ReceiptItem
- ReceiptService
- Issue
- IssueItem
- IssueService

## Stock Operations

StockService provides the basic inventory operations:

- increase stock quantity;
- decrease stock quantity;
- reserve available stock;
- release reserved stock.

Stock decreases are allowed only when sufficient available stock exists.

ReceiptService uses StockService to increase stock when a Receipt is posted.

IssueService uses StockService to decrease stock when an Issue is posted.

StockReconciliationService calculates expected stock from `stock_movements` and compares it with the current
`stocks` balance. It returns only discrepancies and does not modify stock balances, stock movements, or warehouse
documents.

When a Receipt is posted, ReceiptService creates a positive StockMovement for each document item.

When an Issue is posted, IssueService creates a negative StockMovement for each document item.

---

## Stock Movement Audit Trail

StockMovement stores the immutable history of physical stock changes.

Each movement:

- belongs to a warehouse and product;
- stores a positive quantity for a posted Receipt;
- stores a negative quantity for a posted Issue;
- references its source document through a polymorphic relationship;
- stores the resulting physical stock quantity in `balance_after`.

Stock movements are append-only audit records. Existing movements cannot be updated or deleted.

The `stocks` table stores the current inventory balance, while `stock_movements` stores the history explaining how that balance was reached.

---

## Transactions

All document operations that modify stock must use `DB::transaction()`.

The following actions must be atomic:

- updating stock for every document item;
- creating the corresponding stock movements;
- changing the warehouse document status to `posted`.

If processing any document item fails, all stock changes, stock movements, and the document status change must be rolled back.

---

## Warehouse Documents

Receipt and Issue are warehouse documents.

- Receipt represents goods entering a warehouse.
- Issue represents goods leaving a warehouse.
- Issue is not a commercial Sale document.
- Commercial Sale will be implemented separately.

## Planned Modules

- Transfer
- Inventory
- Roles and permissions
- Commercial Sale
- Real-time stock updates
