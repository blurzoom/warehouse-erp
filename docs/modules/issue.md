# WMS-003 Issue

## Purpose

- Represents issuing goods from one warehouse.
- Posting an Issue decreases warehouse stock.
- Issue is a warehouse operation, not a commercial Sale document.

---

## Entities

### Issue

- belongs to Warehouse
- has many IssueItems
- contains a unique document number
- contains document date
- contains status
- may contain a comment

### IssueItem

- belongs to Issue
- belongs to Product
- contains quantity
- may contain a comment

---

## Statuses

- draft
- posted
- cancelled

---

## Business Rules

- A new Issue starts in draft status.
- Only draft Issues may be edited.
- A posted Issue cannot be edited.
- An Issue without items cannot be posted.
- Only a draft Issue can be posted.
- Posting must verify sufficient available stock for every item.
- Posting must decrease stock for every item.
- Posting and status change must be atomic inside one database transaction.
- Repeated posting is forbidden.
- Cancellation and stock reversal are outside the current WMS-003 scope.

---

## Out of Scope

- customers
- prices
- payments
- taxes
- discounts
- invoices
- automatic cancellation or reversal
