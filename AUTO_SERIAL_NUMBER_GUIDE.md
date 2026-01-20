# Auto-Generated Serial Numbers Guide

## Overview

The system now automatically generates unique serial numbers for equipment items. Serial numbers follow the format:

**Format:** `NIA-EQ-{YEAR}-{SEQUENTIAL}`

**Example:**
- `NIA-EQ-2024-0001`
- `NIA-EQ-2024-0002`
- `NIA-EQ-2025-0001` (resets for new year)

## How It Works

### Backend (Automatic Generation)

1. **Model Auto-Generation**
   - When creating a new item, if `serial_number` is empty, it's automatically generated
   - The `Item` model's `booted()` method handles this during creation
   - Format: `NIA-EQ-{YEAR}-{SEQUENTIAL}`

2. **API Endpoint**
   - `GET /api/v1/items/generate-serial-number`
   - Returns a new unique serial number
   - Can be called to preview or regenerate serial numbers

3. **Validation**
   - `serial_number` is **optional** (nullable) in `StoreItemRequest`
   - If not provided, backend auto-generates it
   - If provided, must be unique
   - For updates, serial number can be changed but must remain unique

### Frontend (User Experience)

1. **Add Item Page**
   - Serial number is **auto-generated** when the page loads
   - Field is **editable** - users can modify if needed
   - **"Generate" button** - click to get a new serial number
   - Placeholder shows "Auto-generated serial number"

2. **Edit Item Page**
   - Existing serial numbers are displayed
   - Can be edited if needed
   - No auto-generation (item already has serial number)

## Features

✅ **Automatic Generation** - No manual input required
✅ **Editable** - Can be modified if needed
✅ **Unique** - System ensures no duplicates
✅ **Year-based** - Sequential numbers reset each year
✅ **Format Consistent** - Follows NIA naming convention

## Usage Examples

### Creating New Item (Auto-Generated)

1. Navigate to Add Item page
2. Serial number field is **pre-filled** automatically
3. Optionally click "Generate" button to get a new one
4. Or manually edit if you have a specific serial number
5. Submit form - serial number is saved

### Creating New Item (Manual Override)

1. Navigate to Add Item page
2. Serial number is auto-generated
3. **Edit the field** to enter your own serial number
4. Submit form - your custom serial number is saved

### API Usage

```javascript
// Generate a serial number via API
const response = await axios.get('/api/v1/items/generate-serial-number')
console.log(response.data.serial_number) // e.g., "NIA-EQ-2024-0001"
```

## Technical Details

### Database

- Column: `serial_number` (nullable string)
- Unique constraint enforced
- Auto-generated if NULL during creation

### Model Method

```php
Item::generateSerialNumber()
// Returns: "NIA-EQ-2024-0001"
```

### Sequential Logic

- Gets highest sequential number for current year
- Increments by 1
- Resets to 0001 each new year
- PostgreSQL compatible

## Benefits

1. **Reduces Errors** - No manual typing mistakes
2. **Ensures Uniqueness** - System guarantees unique serial numbers
3. **Consistent Format** - All serial numbers follow same pattern
4. **Time-saving** - No need to manually create serial numbers
5. **Flexible** - Can still override if needed

## Migration Notes

- Existing items without serial numbers will get one when edited
- Old items can remain without serial numbers (nullable)
- New items will always have serial numbers (auto-generated)

## Troubleshooting

### Issue: Serial number not generating
**Solution:**
- Check backend is running
- Check API endpoint is accessible
- Check browser console for errors
- Fallback: Manual entry still works

### Issue: Duplicate serial number error
**Solution:**
- System ensures uniqueness
- If error occurs, click "Generate" button to get a new one
- Or manually enter a different serial number

### Issue: Want to change format
**Solution:**
- Edit `Item::generateSerialNumber()` method in `Item.php`
- Modify the format string as needed
- Example: Change `NIA-EQ` to `NIA-EQP` for equipment

---

## Summary

Serial numbers are now **automatically generated** when creating new items, following the format `NIA-EQ-{YEAR}-{SEQUENTIAL}`. Users can still edit the serial number if needed, but the system ensures uniqueness and provides a consistent format for all equipment tracking.

