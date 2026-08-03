# WMS-005: StockMovement Audit Trail

## Status

Draft

## Goal

Introduce an immutable stock movement history so the system can explain every change to warehouse stock.

## Problem

The current `stocks` table stores only the latest balance per warehouse and product.

The system cannot currently determine:

- which document changed the stock;
- whether the change came from a Receipt or an Issue;
- how much quantity changed;
- what the resulting balance was;
- when the operation occurred.

## Scope

This task introduces StockMovement records for posted Receipt and Issue documents.

Initial movement types:

- `receipt`
- `issue`

## Proposed Data Structure

```text
stock_movements
-------------------------
id
warehouse_id
product_id
type
quantity
balance_after
source_type
source_id
created_by
created_at
```
## Field Definitions

### warehouse_id

Identifies the warehouse where the stock change occurred.

### product_id

Identifies the product whose stock quantity changed.

### type

Identifies the business type of stock movement.

Initial values:

- `receipt`
- `issue`

The database stores the value as a string.

The application represents supported values using a PHP backed enum.

### quantity

Stores the stock quantity change with its direction.

Examples:

```text
Receipt: +10.000
Issue:   -4.000
```
### balance_after

Stores the physical stock quantity immediately after the movement.

It represents the value of `stocks.quantity`, not the available quantity.

Reserved stock does not create a StockMovement because reservation does not physically move goods.

Example:

```text
Previous balance: 100.000
Movement:         -20.000
Balance after:     80.000
```

### source_type and source_id

Store a Laravel polymorphic relation to the document that created the movement.

Initially supported source models:

- Receipt
- Issue

### created_by

Stores the user responsible for the operation.

The field is nullable until warehouse document posting is integrated with authentication.

### created_at

Stores when the movement record was created.

StockMovement records are immutable.

Therefore the table stores only `created_at`.

## Relationships

- StockMovement belongs to Warehouse.
- StockMovement belongs to Product.
- StockMovement morphs to its source document.
- Receipt has many StockMovements.
- Issue has many StockMovements.
