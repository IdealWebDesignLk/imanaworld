# IMANAWORLD Pickup Network — Catalogue Import Specification

**For: Choppies (catalogue preparation)**
**From: Ideal Web Design / IMANAWORLD**
**Purpose:** the exact file format the IPN Catalogue Import tool expects. No sample file from Choppies existed when this was written, so the importer was built against this specification — please prepare your export to match it rather than sending your existing internal format as-is.

---

## 1. File format

- **CSV** (`.csv`) or **Excel** (`.xlsx`)
- First row must be the column headers exactly as listed below (case doesn't matter, but spelling does)
- One sheet only if using Excel — only the first sheet is read
- UTF-8 encoding recommended (handles currency symbols, accented characters, etc. correctly)

## 2. One row = one product at one branch

This is the most important rule. If a product (e.g. a 750ml bottle of dishwashing liquid) is stocked at 3 branches, that's **3 rows** in the file — one per branch — not one row with three stock numbers in three columns.

The product itself (name, price, description, category, image) only needs to be filled in on **one** of those rows; the importer creates the product the first time it sees that SKU and just adds branch stock on every subsequent row for the same SKU. In practice it's simplest to just repeat the product details on every row for that SKU — the importer ignores duplicates.

## 3. Columns

| Column | Required? | Format | Notes |
|---|---|---|---|
| `SKU` | **Always required** | Text, e.g. `CHP-00123` | The unique product code. This is how the importer matches a row to an existing product — if the SKU already exists, its other fields are updated; if not, a new product is created. **Must be consistent across all of a SKU's rows.** |
| `Product Name` | Required the first time a SKU appears | Text | The product title shown to customers. |
| `Description` | Optional | Text | Plain text or simple HTML. |
| `Category` | Optional | Text, comma-separated for multiple | e.g. `Groceries,Staples`. Categories that don't already exist on IMANAWORLD are created automatically. |
| `Regular Price` | Required the first time a SKU appears | Number, no currency symbol | e.g. `28.50`, not `BWP 28.50`. |
| `Sale Price` | Optional | Number, no currency symbol | Leave blank if not on sale. |
| `Branch Code` | **Always required** | Text | Must match a branch code already configured in IPN (IPN → Branches in wp-admin). We'll send Choppies the confirmed branch code list once your pilot store list is finalised — placeholders like `CHP-GBE-01` are used below until then. |
| `Stock Quantity` | **Always required** | Whole number, 0 or more | Stock for this specific SKU at this specific branch. |
| `Image URL` | Optional | A public URL to the product image | Only used the first time a SKU is created — not re-fetched on later updates. Leave blank to skip. |

## 4. Example

| SKU | Product Name | Description | Category | Regular Price | Sale Price | Branch Code | Stock Quantity | Image URL |
|---|---|---|---|---|---|---|---|---|
| CHP-00123 | Sunlight Dishwashing Liquid 750ml | Sunlight dishwashing liquid, 750ml bottle. | Household | 28.50 | | CHP-GBE-01 | 40 | https://example.com/images/sunlight-750ml.jpg |
| CHP-00123 | Sunlight Dishwashing Liquid 750ml | Sunlight dishwashing liquid, 750ml bottle. | Household | 28.50 | | CHP-FTW-01 | 15 | |
| CHP-00456 | White Star Maize Meal 2.5kg | White Star super maize meal, 2.5kg. | Groceries,Staples | 32.00 | 29.99 | CHP-GBE-01 | 60 | |

This example is also available as a downloadable template inside the IPN admin panel (**IPN → Catalogue Import → Download IPN import template**), formatted as a ready-to-edit CSV.

## 5. What happens on import

- **New SKU** → a new product is created on the Choppies vendor account, using the branch's assigned vendor, with the given name/price/description/category/image, and stock is set for that branch.
- **Existing SKU** → the product's name/price/description are updated (if provided — blank fields are left unchanged on updates, except stock, which is always set to whatever the file says), and stock is set for that branch.
- **Unknown Branch Code** → that row fails with a clear error; nothing else about the row is applied.
- **Missing SKU, Branch Code, or Stock Quantity** → that row fails; every other valid row in the file still processes normally.
- Every row's outcome (created / updated / failed, with a reason for failures) is recorded in the import log, visible in **IPN → Catalogue Import**.

## 6. Periodic updates

The same file format is used for both the initial catalogue load and later stock/price refreshes — just re-upload an updated file with the current stock numbers and it updates existing products rather than duplicating them, matched by SKU.

## 7. Known limitation (Excel files)

The importer reads standard `.xlsx` exports (Excel, Google Sheets "Download as .xlsx", etc.) but is not a full spreadsheet engine — it can't evaluate live formulas, multiple sheets, or unusual formatting. If your export tool produces anything non-standard, please save/export as **CSV** instead, which is read directly and has no such limitations.
