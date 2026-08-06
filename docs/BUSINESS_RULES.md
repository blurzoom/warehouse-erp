# Business Rules

## Product

- SKU is unique.
- Barcode is nullable and unique.
- Product belongs to Category.
- Product belongs to Unit.

## Warehouse

- Warehouse code is unique.

## Stock

- Stock is maintained per Warehouse and Product.
- Stock quantity cannot be decreased by more than the available quantity.
- Available stock is calculated as quantity minus reserved quantity.
- Reserved quantity cannot exceed available quantity.
- Reserved quantity cannot be released by more than the currently reserved amount.

## Stock Reconciliation

- Expected quantity is the sum of `quantity` in `stock_movements` for the warehouse and product pair.
- Actual quantity is the current `stocks.quantity` value, or 0 when no stock record exists.
- Discrepancy is calculated as actual quantity minus expected quantity.
- Reconciliation returns only discrepancies.
- Reconciliation is read-only and does not modify stock data, stock movements, or document status.

## Receipt

- A new Receipt starts in draft status.
- Only a draft Receipt may be edited.
- A Receipt without items cannot be posted.
- Posting a Receipt increases warehouse stock.
- Posting all Receipt items, updating stock, creating stock movements, and changing the document status must be atomic.
- A posted Receipt cannot be edited.
- Repeated posting is forbidden.
- Receipt cancellation and stock reversal are not implemented.

## Issue

- A new Issue starts in draft status.
- Only a draft Issue may be edited.
- An Issue without items cannot be posted.
- Posting an Issue decreases warehouse stock.
- An Issue cannot be posted when available stock is insufficient.
- Posting all Issue items, updating stock, creating stock movements, and changing the document status must be atomic.
- A posted Issue cannot be edited.
- Repeated posting is forbidden.
- Issue cancellation and stock restoration are not implemented.

## Warehouse Documents

- Only posted warehouse documents modify stock.
- Receipt posting increases stock.
- Issue posting decreases stock.

## Stock Movement

- StockMovement is an append-only audit record of a physical stock change.
- Posting a Receipt creates a positive StockMovement for each document item.
- Posting an Issue creates a negative StockMovement for each document item.
- Each movement belongs to a Warehouse and Product.
- Each movement references its source document through a polymorphic relationship.
- The `balance_after` value must match the resulting physical stock quantity.
- Existing stock movements cannot be updated or deleted.
- Stock movements must be created within the same database transaction as the related stock update and document posting.
- Failed document posting must not leave partial stock changes or stock movements.

## Transfer

- Transfer quantity cannot exceed available stock.
- Transfer is planned but not implemented.
