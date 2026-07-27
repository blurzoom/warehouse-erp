# Business Rules

## Product

- SKU is unique
- Barcode is nullable and unique
- Product belongs to Category
- Product belongs to Unit

## Warehouse

- Warehouse code is unique

## Receipt

Draft

↓

Posted

↓

Cancelled

Posted receipt cannot be edited.
Cancelled receipt does not rollback stock automatically.

Only Posted documents modify stock.

## Stock

Stock cannot become negative.
Stock is calculated per Warehouse.

## Transfer

Transfer quantity cannot exceed available stock.
