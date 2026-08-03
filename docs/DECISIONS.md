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
