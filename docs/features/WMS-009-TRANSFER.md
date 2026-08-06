# WMS-009: Warehouse Transfer

## Status

Draft

## Goal

Introduce a warehouse Transfer document that moves physical stock of one or more products from one warehouse to
another while preserving the current stock balance model and the immutable StockMovement audit trail.

Posting a Transfer must decrease stock in the source warehouse, increase stock in the destination warehouse, and
record both sides of the operation atomically.

## Scope

WMS-009 defines the domain model and posting behavior for:

- `Transfer` and `TransferItem`;
- a `TransferService` following the existing `ReceiptService` and `IssueService` service-layer pattern;
- draft-only document editing;
- one-time posting through `TransferService::post(Transfer $transfer): void`;
- stock updates through the existing `StockService` operations;
- paired immutable StockMovement records linked to the Transfer through the existing polymorphic source relation.

The Transfer is a warehouse document. It is not a Receipt, Issue, Sale, shipment, or purchase document.

## Proposed Data Structure

### transfers

```text
transfers
-------------------------
id
number
transfer_date
from_warehouse_id
to_warehouse_id
status
comment
created_at
updated_at
```

### transfer_items

```text
transfer_items
-------------------------
id
transfer_id
product_id
quantity
comment
created_at
updated_at
UNIQUE (transfer_id, product_id)
```

### Required stocks constraint

WMS-009 must add a composite unique index on the existing `stocks` table:

```text
UNIQUE (warehouse_id, product_id)
```

This index enforces the existing domain rule that one Stock record represents one warehouse/product pair. It is the
final database guarantee against duplicate Stock rows and a mandatory technical prerequisite for concurrent
destination stock creation.

The unique index is not, by itself, the complete creation algorithm. A normal `lockForUpdate()` query cannot lock a
Stock row that does not yet exist. `StockService` must therefore use a database-compatible conflict-tolerant creation
strategy and then retrieve the resulting row with a row-level lock before changing its quantity.

If another transaction creates the same Stock row first, `StockService` must continue with that row after acquiring
its lock. It must not leave the Transfer partially posted. The implementation must not rely on catching a unique
constraint exception inside a transaction when the selected database engine treats that violation as transaction-
aborting. In that case, it must use a compatible upsert or insert-or-ignore strategy followed by a locked read.

The exact Laravel query implementation must be verified against every database engine used by the project runtime
and concurrency tests before it is selected. This specification defines the required behavior, not a
database-specific query sequence.

## Field Definitions

### Transfer

#### id

Primary key.

#### number

Unique document number, consistent with `Receipt.number` and `Issue.number`.

#### transfer_date

Transfer document date. It should be stored as a date, indexed, and cast to `date` by the `Transfer` model.

#### from_warehouse_id

Identifies the warehouse whose stock is decreased. The foreign key references `warehouses.id` and should restrict
warehouse deletion while a Transfer references it.

The corresponding proposed relationship name is `fromWarehouse()`.

#### to_warehouse_id

Identifies the warehouse whose stock is increased. The foreign key references `warehouses.id` and should restrict
warehouse deletion while a Transfer references it.

The corresponding proposed relationship name is `toWarehouse()`.

`from_warehouse_id` and `to_warehouse_id` must contain different values.

#### status

Stores the document state as a string with exactly two supported values: `draft` and `posted`. A new Transfer always
starts in `draft`, including when another status is supplied to `TransferService::create()`.

The `cancelled` status is not supported by Transfer and is outside WMS-009.

#### comment

Optional document-level text comment.

#### created_at and updated_at

Standard Eloquent timestamps.

### TransferItem

#### id

Primary key.

#### transfer_id

Identifies the parent Transfer. The foreign key references `transfers.id` and should restrict deletion while an item
references the document.

#### product_id

Identifies the transferred Product. The foreign key references `products.id` and should restrict product deletion
while a TransferItem references it.

A Product may occur only once within a Transfer. Application validation must reject a duplicate `product_id`, and a
composite unique index on `(transfer_id, product_id)` must enforce the rule in the database.

#### quantity

Physical quantity to move, stored with three decimal places as `decimal(15, 3)` and cast as `decimal:3`, matching
`ReceiptItem.quantity` and `IssueItem.quantity`.

The value must be greater than zero. The application must validate this rule, and the table should enforce it with a
database check constraint on supported database engines, following the existing item migrations.

#### comment

Optional item-level text comment.

#### created_at and updated_at

Standard Eloquent timestamps.

## Proposed Relationships

- Transfer belongs to its source Warehouse through `fromWarehouse()`.
- Transfer belongs to its destination Warehouse through `toWarehouse()`.
- Transfer has many TransferItems through `items()`.
- Transfer has many StockMovements through `stockMovements()`, implemented as `morphMany(StockMovement::class,
  'source')`.
- TransferItem belongs to Transfer through `transfer()`.
- TransferItem belongs to Product through `product()`.
- Each transfer StockMovement belongs to the affected Warehouse and Product and morphs to the Transfer through
  `source()`.

## Statuses and Transitions

### draft

- Initial status assigned by `TransferService::create()`.
- Header data and items may be changed only while the Transfer is in this status.
- The only transition implemented by WMS-009 is `draft -> posted`, performed by `TransferService::post()`.

### posted

- Terminal status within WMS-009.
- Indicates that all item quantities, both stock changes, and both StockMovement records per item were committed.
- A posted Transfer cannot be posted again, edited, or have items added, updated, or removed.

No direct status assignment is a valid business transition. Cancellation and reversal transitions are outside
WMS-009.

## Business Rules

- The source and destination warehouses must be different.
- Transfer supports only `draft` and `posted`; `cancelled` is not a valid Transfer status.
- A Transfer without items cannot be posted.
- Every TransferItem quantity must be greater than zero.
- A Product may occur only once in a Transfer. Application validation and the composite unique index on
  `transfer_items(transfer_id, product_id)` must both enforce this rule.
- A Transfer may be posted only from `draft` and only once.
- The source warehouse must have sufficient available stock for every item. Available stock is the existing
  `Stock::available()` result: `quantity - reserved`.
- A quantity equal to the available stock is allowed.
- Posting must not consume reserved stock.
- Only a posted Transfer changes stock. Creating or editing a draft does not reserve or move stock.
- Only a draft Transfer and its items may be edited.
- After posting, header data and items are immutable through the Transfer service.
- All stock changes, StockMovement records, and the status transition must succeed or fail as one database
  transaction.
- `stocks(warehouse_id, product_id)` must be unique so each warehouse/product pair has exactly one Stock record. The
  index is the final duplicate-prevention guarantee, not the complete concurrent creation algorithm.
- Concurrently safe Stock row locking belongs to `StockService` so Transfer, Issue, and other stock-changing
  operations can use the same mechanism.
- When destination Stock does not exist, `StockService` must create or obtain it using a conflict-tolerant strategy
  compatible with the active database engine, then retrieve it with a row-level lock before changing quantity.
- If another transaction creates the destination Stock first, `StockService` must lock the existing row and continue
  without leaving partial stock changes, movements, or a posted status.
- Missing-row creation, conflict handling, locked retrieval, and quantity changes must remain inside the same
  document-posting transaction.
- The implementation must not catch a uniqueness violation and continue inside a transaction that the active
  database engine has already marked as failed. It must use compatible upsert or insert-or-ignore semantics followed
  by `lockForUpdate()` when required by that engine.
- Stock rows must be locked in deterministic ascending order by `warehouse_id` and then `product_id` to reduce
  deadlock risk.
- Existing StockMovement records remain append-only and cannot be updated or deleted.

## Posting Algorithm

`TransferService::post(Transfer $transfer): void` must implement the operation as follows:

1. Start one `DB::transaction()` covering all checks that protect posting state and every database write.
2. Reload and lock the Transfer row, then verify that its current status is `draft`. This prevents two concurrent
   calls from posting the same document twice.
3. Verify that `from_warehouse_id` and `to_warehouse_id` are different.
4. Verify that the Transfer has at least one item, every item quantity is greater than zero, and no Product occurs
   more than once.
5. Build the complete set of source and destination warehouse/product pairs required by all TransferItems.
6. Pass the complete set to the transaction-aware locking mechanism implemented by `StockService`. `StockService`
   must sort the pairs by ascending `warehouse_id` and then ascending `product_id` before obtaining Stock rows or
   acquiring row-level locks. It must hold all acquired locks until the transaction ends.
7. For an existing Stock row, `StockService` must retrieve it with a row-level lock in the deterministic order.
8. A source Stock row is required. If it does not exist, posting fails and the complete transaction is rolled back.
9. If a destination Stock row does not exist, `StockService` must create or obtain it inside the same transaction
   using a conflict-tolerant strategy compatible with the active database engine. The
   `stocks(warehouse_id, product_id)` unique index is the final guarantee that only one row can exist.
10. If another transaction creates the destination Stock first, `StockService` must retrieve that existing row with
    a row-level lock and continue. It must not depend on catching a constraint violation inside a transaction that
    the active database engine considers failed. Where necessary, use compatible upsert or insert-or-ignore behavior
    followed by `lockForUpdate()`.
11. Do not prescribe the final Laravel query sequence until this behavior has been verified against the database
    engines used by the project runtime and concurrency tests.
12. After all required Stock rows are obtained and locked, process every TransferItem within the same transaction.
13. Use `StockService::decrease()` for the source Warehouse and Product. This enforces the existing available-stock
   rule and throws a DomainException when the stock row does not exist or available stock is insufficient.
14. Create the source movement through `$transfer->stockMovements()->create()` with the source warehouse, product,
   `StockMovementType::TransferOut`, negative quantity, and the source Stock quantity returned by `decrease()` as
   `balance_after`.
15. Use `StockService::increase()` for the locked destination Stock. A newly created destination row starts with
    `quantity = 0` and `reserved = 0` before the increase.
16. Create the destination movement through `$transfer->stockMovements()->create()` with the destination warehouse,
   product, `StockMovementType::TransferIn`, positive quantity, and the destination Stock quantity returned by
   `increase()` as `balance_after`.
17. After every item and both movements per item have succeeded, update the Transfer status to `posted`.
18. Commit the transaction and release the Stock row locks.

If any validation, stock decrease, stock increase, movement creation, or status update fails, the exception must
leave the transaction. Laravel then rolls back all changes, including changes made for earlier items. The Transfer
must remain `draft`, both warehouse balances must retain their pre-posting values, and no movement from the failed
posting attempt may remain.

The checks in steps 2-4 are repeated during posting even if equivalent application validation exists, because the
service is the business-logic boundary and may be called without an HTTP request.

The locking mechanism is a `StockService` responsibility rather than Transfer-specific logic. `IssueService` and
future warehouse operations must be able to use the same lock ordering and stock-row protection.

## Stock Changes

For each TransferItem with quantity `Q`:

```text
Source stock quantity after posting      = source quantity before posting - Q
Destination stock quantity after posting = destination quantity before posting + Q
```

The source decrease is based on available stock, but changes physical `stocks.quantity`. The destination increase
does not change `stocks.reserved`.

The net quantity across both warehouses is zero. `StockReconciliationService::reconcile()` continues to calculate
expected stock independently for each warehouse and product by summing signed movement quantities.

## Stock Movements

`StockMovementType` currently contains only `Receipt` and `Issue`. WMS-009 requires adding these backed enum cases
during implementation:

```php
case TransferOut = 'transfer_out';
case TransferIn = 'transfer_in';
```

Each posted TransferItem creates exactly two immutable StockMovement records:

| Field | Source movement | Destination movement |
| --- | --- | --- |
| `warehouse_id` | `from_warehouse_id` | `to_warehouse_id` |
| `product_id` | TransferItem `product_id` | TransferItem `product_id` |
| `type` | `StockMovementType::TransferOut` | `StockMovementType::TransferIn` |
| `quantity` | negative TransferItem quantity | positive TransferItem quantity |
| `balance_after` | resulting source `stocks.quantity` | resulting destination `stocks.quantity` |
| `source_type` | Transfer morph class | Transfer morph class |
| `source_id` | Transfer id | Transfer id |
| `created_by` | `null` until authentication is integrated | `null` until authentication is integrated |

Both movements reference the same Transfer through the existing `source_type` and `source_id` polymorphic columns.
No Transfer-specific foreign key is added to `stock_movements`.

## Main Test Scenarios

- `TransferService::create()` creates a Transfer in `draft` and ignores a supplied non-draft status.
- Transfer numbers are unique.
- Transfer belongs to distinct source and destination Warehouses and has many TransferItems.
- TransferItem belongs to its Transfer and Product.
- Transfer accepts only `draft` and `posted`; `cancelled` is rejected.
- A Transfer with the same source and destination warehouse is rejected before any stock change.
- Zero and negative item quantities are rejected by the application and database constraint.
- Adding the same Product twice to one Transfer is rejected by application validation.
- The `transfer_items(transfer_id, product_id)` unique index rejects duplicate Product rows for one Transfer.
- The same Product may be used in different Transfers.
- The `stocks(warehouse_id, product_id)` unique index rejects duplicate Stock rows for one warehouse/product pair.
- Items can be added, updated, and removed from a draft Transfer.
- Header data and items cannot be changed after posting.
- An empty Transfer cannot be posted.
- Posting decreases source stock and increases destination stock by the same quantity.
- Posting creates destination Stock when no destination warehouse/product row exists.
- Posting succeeds when the item quantity equals source available stock.
- Posting fails when source stock does not exist or available stock is insufficient, including when physical stock
  exists but part of it is reserved.
- Each item creates one negative `transfer_out` movement and one positive `transfer_in` movement with correct
  warehouse, product, `balance_after`, and polymorphic source values.
- Posting multiple items creates exactly two movements per item.
- A Transfer cannot be posted twice and repeated posting creates no additional stock changes or movements.
- Failure on any later item rolls back earlier source decreases, destination increases, movements, and the status
  update.
- Failure while creating either movement rolls back both warehouse balances and all movements from the posting
  attempt.
- Concurrent posting attempts cannot both pass the `draft` check.
- `StockService` locks all required Stock rows before quantities are changed.
- `StockService` acquires locks in ascending `warehouse_id`, then ascending `product_id`, regardless of TransferItem
  insertion order.
- The unique index prevents duplicate destination Stock rows but is not treated as the complete concurrent creation
  mechanism.
- When two transactions concurrently require the same missing destination Stock, exactly one Stock row is created;
  the other transaction obtains that row with a lock and continues after the first transaction releases it.
- A concurrent destination creation conflict does not leave partial source decreases, destination increases,
  StockMovement records, or a posted Transfer status.
- The conflict-tolerant creation path keeps the document-posting transaction usable on the active database engine;
  it does not continue after a transaction-aborting constraint violation.
- The missing-destination concurrency scenario is covered by a database integration test using an engine with the
  same unique-constraint and row-locking semantics as the project runtime, rather than only by mocks.
- After a successful Transfer, `StockReconciliationService::reconcile()` reports no discrepancy when the starting
  balances were already explained by StockMovement history.

## Out of Scope

- HTTP controllers, routes, Form Requests, API resources, and user interface.
- Authentication, authorization, roles, and populating `created_by` from the authenticated user.
- Transfer approval workflows or statuses beyond `draft` and `posted`.
- The `cancelled` status, cancellation, reversal, deletion of posted documents, or compensating StockMovement
  records.
- Partial posting, partial fulfillment, backorders, or splitting one Transfer into shipments.
- Reservation of stock when a draft Transfer is created.
- In-transit stock, dispatch and receipt as separate steps, or transport tracking.
- Product costing, valuation, accounting entries, taxes, or prices on TransferItem.
- Lots, batches, serial numbers, expiry dates, and storage-bin locations.
- Changes to `StockReconciliationService`; paired signed movements are compatible with its existing reconciliation
  algorithm.
