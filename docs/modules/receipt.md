# WMS-002 Receipt

## User Story

As a warehouse clerk,
I want to create a receipt document,
so that products can be received into a warehouse.

---

## Statuses

- Draft
- Posted
- Cancelled

---

## Header

- number
- warehouse_id
- document_date
- status
- comment
- created_by

---

## Items

- product_id
- quantity
- price

---

## Business Rules

- Draft can be edited.
- Posted cannot be edited.
- Only Posted changes stock.
- Negative stock is forbidden.
- Receipt always increases stock.

---

## Definition of Done

- Receipt migration
- ReceiptItem migration
- Models
- Factories
- Relationships
- Feature tests
