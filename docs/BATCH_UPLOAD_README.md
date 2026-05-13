# Batch Product Upload Feature - Implementation Guide

## Overview
This implementation adds batch product upload functionality via CSV/Excel files to your inventory management system.

## Files Created

### 1. **views/inventory/batch_upload.php**
- Main upload interface with instructions
- Shows available categories for reference
- File upload form with options:
  - Skip duplicates
  - Update existing products
- Real-time feedback on upload results

### 2. **api/inventory/download_template.php**
- Generates CSV template with:
  - Proper column headers
  - Example data rows
  - Instructions and category reference
  - Default values guidance

### 3. **api/inventory/process_batch_upload.php**
- Processes uploaded CSV/Excel files
- Features:
  - Validates required fields
  - Handles duplicate detection (by SKU/Barcode)
  - Supports update mode for existing products
  - Transaction-based processing (rollback on error)
  - Detailed error reporting and warnings
  - Creates stock movements for initial inventory

### 4. **Updated Existing Files**
- `views/inventory/create.php` - Added "Batch Upload" button
- `views/inventory/index.php` - Added "Batch Upload" button to toolbar

## CSV File Format

### Required Columns
- `product_name` - Product name
- `category_id` - Category ID (see template for available IDs)
- At least one price: `price_capital` OR `price_retail`

### Optional Columns
- `brand_name` - Brand/manufacturer
- `description` - Product description
- `variation_name` - Size/color/type (defaults to "Standard")
- `unit_type` - pc, set, box, pair, bottle, kit, roll (defaults to "pc")
- `sku` - Stock keeping unit
- `barcode` - Barcode number
- `price_wholesale` - Wholesale price
- `price_dealer` - Dealer price
- `initial_stock` - Starting quantity (defaults to 0)
- `low_stock_threshold` - Alert level (defaults to 5)

## Usage Instructions

### For Users:

1. **Navigate to Batch Upload**
   - From Inventory page, click "Batch Upload" button
   - Or from Create Product page, click "Batch Upload"

2. **Download Template**
   - Click "Download CSV Template" button
   - Opens pre-formatted CSV with examples and instructions

3. **Prepare Your Data**
   - Fill in the CSV template with your products
   - Delete example rows and instruction rows
   - Ensure category IDs are valid
   - Include at least one price per product

4. **Upload the File**
   - Choose your CSV or Excel file (.csv, .xlsx, .xls)
   - Select options:
     - "Skip duplicates" - Don't import if SKU/Barcode exists
     - "Update existing" - Update products with matching SKU/Barcode
   - Click "Upload & Process"

5. **Review Results**
   - Success message shows how many products were added
   - Warnings section lists any skipped rows with reasons
   - Products are immediately available in inventory

## Features

### Validation
- ✅ Checks for required fields
- ✅ Validates category IDs exist
- ✅ Ensures at least one price is provided
- ✅ Validates numeric values for prices and stock
- ✅ Skips empty rows and comment rows

### Duplicate Handling
- **Skip Mode**: Checks SKU/Barcode against existing products, skips if found
- **Update Mode**: Updates existing product prices and adds to stock if SKU/Barcode matches
- Creates new products if no match found

### Transaction Safety
- Each product insert uses database transactions
- Automatic rollback if any step fails
- Ensures data integrity

### Stock Management
- Creates initial inventory records
- Records stock movements with "Batch Upload" remarks
- Links to user who performed the upload

## Excel Support

### CSV Files (.csv)
- ✅ Fully supported out of the box
- ✅ No additional libraries needed

### Excel Files (.xlsx, .xls)
- ⚠️ Requires PhpSpreadsheet library
- To enable Excel support, install via Composer:
  ```bash
  composer require phpoffice/phpspreadsheet
  ```
- Current implementation shows error message if library not available
- Recommends converting to CSV as fallback

## Error Handling

The system provides comprehensive error messages:

- **File level**: Upload errors, format issues
- **Row level**: Validation errors with row numbers
- **Summary**: Total success, skipped, and error counts
- **Warnings**: Individual messages for each problematic row

## Database Schema Requirements

The batch upload expects these tables:
- `products` - Parent product information
- `product_variations` - SKU, prices, variation details
- `inventory` - Stock quantities
- `stock_movements` - Audit trail
- `categories` - Product categories

## Access Control

- Requires user authentication
- Role-based permissions: admin, manager, or stockman
- User ID recorded in stock movements

## Testing Recommendations

1. Test with template file (includes examples)
2. Test duplicate detection with same SKU
3. Test update mode vs skip mode
4. Test with invalid category IDs
5. Test with missing required fields
6. Test with large files (performance)

## Future Enhancements

Potential improvements:
- [ ] Add product image URL column support
- [ ] Support for multiple variations per product in one file
- [ ] Async processing for very large files
- [ ] Progress bar for uploads
- [ ] Download log file of warnings/errors
- [ ] Direct Excel support without external library
- [ ] Supplier assignment during import

## Notes

- Template includes 3 example products
- Instructions are embedded in template (delete before upload)
- Category reference table shown in upload page
- All monetary values use 2 decimal precision
- Stock movements automatically created for audit trail
