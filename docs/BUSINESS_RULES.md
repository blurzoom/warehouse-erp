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

## Receipt

- A new Receipt starts in draft status.
- Only a draft Receipt may be edited.
- A Receipt without items cannot be posted.
- Posting a Receipt increases warehouse stock.
- Posting all Receipt items and changing the document status must be atomic.
- A posted Receipt cannot be edited.
- Repeated posting is forbidden.
- Receipt cancellation and stock reversal are not implemented.

## Issue

- A new Issue starts in draft status.
- Only a draft Issue may be edited.
- An Issue without items cannot be posted.
- Posting an Issue decreases warehouse stock.
- An Issue cannot be posted when available stock is insufficient.
- Posting all Issue items and changing the document status must be atomic.
- A posted Issue cannot be edited.
- Repeated posting is forbidden.
- Issue cancellation and stock restoration are not implemented.

## Warehouse Documents

- Only posted warehouse documents modify stock.
- Receipt posting increases stock.
- Issue posting decreases stock.

## Transfer

- Transfer quantity cannot exceed available stock.
- Transfer is planned but not implemented.
