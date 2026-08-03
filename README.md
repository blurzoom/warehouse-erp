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

## Tech Stack

- PHP
- Laravel
- MySQL
- PHPUnit
- Laravel Pint

## Architecture

Business operations are implemented in service classes.

Receipt posting increases warehouse stock, while Issue posting decreases warehouse stock. Stock-changing document
operations are executed inside database transactions.

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

The next planned business module is **StockMovement**.

See [docs/ROADMAP.md](docs/ROADMAP.md) for the complete project roadmap.
