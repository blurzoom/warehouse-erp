# Warehouse ERP

Warehouse management system built with Laravel as a learning project for designing and testing inventory business logic.

## Implemented Features

- Product categories and measurement units
- Products and warehouses
- Warehouse stock balances
- Stock receiving through Receipt documents
- Stock issuing through Issue documents
- Stock reservation and release
- Protection against negative available stock
- Transactional document posting
- Immutable stock movement audit trail
- Stock reconciliation through StockReconciliationService
- Positive movements for posted Receipt documents
- Negative movements for posted Issue documents
- Atomic stock updates and movement recording
- Database constraints and Eloquent relationships
- Feature and service tests

## Current Modules

- Category
- Unit
- Product
- Warehouse
- Stock
- Receipt and ReceiptItem
- Issue and IssueItem
- StockReconciliationService
- StockMovement

## Tech Stack

- PHP
- Laravel
- MySQL
- PHPUnit
- Laravel Pint

## Architecture

Business operations are implemented in service classes.

Posting a Receipt increases warehouse stock and creates a positive StockMovement. Posting an Issue decreases warehouse
stock and creates a negative StockMovement.

StockReconciliationService calculates expected stock from `stock_movements` and compares it with the current `stocks`
balance. It returns only discrepancies and does not modify stock data.

StockMovement provides an immutable audit trail of inventory changes. Each movement references its source document and
stores the resulting physical stock balance in `balance_after`.

Document posting, stock balance updates, and stock movement creation are executed atomically inside database transactions.
If any part of the operation fails, all related changes are rolled back.

Detailed project documentation:

- [Architecture](docs/ARCHITECTURE.md)
- [Business Rules](docs/BUSINESS_RULES.md)
- [Roadmap](docs/ROADMAP.md)
- [Architecture Decisions](docs/DECISIONS.md)

## Testing

Run the complete test suite:

```bash
php artisan test
```

Run code style checks:

```bash
vendor/bin/pint --test
```

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md) for the complete project roadmap.
