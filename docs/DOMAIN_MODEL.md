# Domain Model

## Core Entities

### Category

Represents a product category.

---

### Unit

Represents a measurement unit.

Examples:

- pcs
- kg
- liter

---

### Product

Represents a product definition.

A product:

- belongs to Category
- belongs to Unit

---

### Warehouse

Represents a physical warehouse.

---

### Stock

Represents current quantity of a Product in a Warehouse.

Stock is calculated.

Negative quantity is forbidden.

---

### Receipt

Incoming goods document.

States:

- Draft
- Posted
- Cancelled

Only Posted document modifies stock.

---

### ReceiptItem

Receipt line.

Contains:

- Product
- Quantity
- Price

---

### StockMovement

Represents every stock movement.

Never edited.

Never deleted.
