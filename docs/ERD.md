# Entity Relationship Diagram

## Entities

- Category
- Unit
- Product
- Warehouse
- Stock
- Receipt
- ReceiptItem
- StockMovement

## Relationships

Category 1 --- * Product

Unit 1 --- * Product

Warehouse 1 --- * Stock

Product 1 --- * Stock

Receipt 1 --- * ReceiptItem

Product 1 --- * ReceiptItem

Receipt 1 --- * StockMovement

Warehouse 1 --- * StockMovement

Product 1 --- * StockMovement
