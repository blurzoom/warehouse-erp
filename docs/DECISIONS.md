# Architecture Decisions

## ADR-001: Separate warehouse Issue from commercial Sale

### Status

Accepted

### Context

The system requires a warehouse document for recording goods leaving a warehouse.

A commercial Sale represents a different business process and may require customers, prices, discounts, taxes, payments and invoices.

Using one document for both warehouse issuing and commercial selling would mix inventory operations with commercial operations.

### Decision

Issue is implemented as a warehouse document.

Posting an Issue decreases warehouse stock but does not represent a commercial Sale.

Commercial Sale will be implemented later as a separate module.

### Consequences

- Issue does not contain customer information.
- Issue does not contain prices, discounts, taxes or payments.
- Receipt and Issue form the basic warehouse document workflow.
- Commercial Sale can be developed independently.

## ADR-002: Store an immutable stock movement audit trail

### Status

Accepted

### Context

The `stocks` table stores only the current physical quantity of each product in a warehouse.

A current balance does not explain which warehouse operations produced that balance and cannot provide a complete history of inventory changes.

The system requires a reliable audit trail for posted Receipt and Issue documents. This history will also support future stock reconciliation and reporting.

### Decision

Store every physical stock change as a separate immutable StockMovement record.

Posting a Receipt creates a positive movement, while posting an Issue creates a negative movement.

Each movement belongs to a warehouse and product, stores the resulting physical stock quantity in `balance_after`, and references its source document through a polymorphic relationship.

Stock updates, movement creation, and document posting are performed atomically within the same database transaction.

Existing stock movements cannot be updated or deleted.

### Consequences

- The `stocks` table represents the current physical stock balance.
- The `stock_movements` table explains how that balance was reached.
- Posted Receipt and Issue operations produce an append-only inventory history.
- A polymorphic source relationship allows different warehouse document types to create movements without separate foreign-key columns.
- Failed document posting does not leave partial stock updates or movement records.
- Stock history requires additional database storage.
- Incorrect posted movements must be corrected through new compensating operations instead of modifying historical records.
- Movement history provides the foundation for future stock reconciliation and reporting.
