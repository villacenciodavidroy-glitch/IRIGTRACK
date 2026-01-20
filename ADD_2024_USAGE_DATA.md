# Add 2024 Usage Data for Supply Items

This guide explains how to add usage data for supply items (Ballpens, Bondpaper A4(size), Flash Drive) for 2024 to enable better forecasting.

## Methods Available

### Method 1: Using Artisan Command (Recommended)

Run the Laravel artisan command to add 2024 usage data:

```bash
cd backend-laravel
php artisan usage:add-2024-data
```

**Options:**
- `--item-names="Item Name"`: Specify specific items (can be used multiple times)
  ```bash
  php artisan usage:add-2024-data --item-names="Ballpens" --item-names="Flash Drive"
  ```
- `--dry-run`: Preview what will be created without actually creating records
  ```bash
  php artisan usage:add-2024-data --dry-run
  ```

**Example:**
```bash
# Add data for default items (Ballpens, Bondpaper A4(size), Flash Drive)
php artisan usage:add-2024-data

# Preview first
php artisan usage:add-2024-data --dry-run

# Add data for specific items only
php artisan usage:add-2024-data --item-names="Ballpens"
```

### Method 2: Using PHP Script

Run the standalone PHP script:

```bash
cd backend-laravel
php add_2024_usage_data.php
```

This script will:
1. Find the supply items (Ballpens, Bondpaper A4(size), Flash Drive)
2. Generate sample usage data for Q1-Q4 2024
3. Ask for confirmation before creating records
4. Create the usage records in the database

### Method 3: Using API Endpoint

You can also add usage data programmatically via the API:

**Single Record:**
```bash
POST /api/usage
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "item_id": 1,
  "period": "Q1 2024",
  "usage": 75,
  "stock_start": 100,
  "stock_end": 25,
  "restocked": false,
  "restock_qty": 0
}
```

**Bulk Records:**
```bash
POST /api/usage/bulk
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "records": [
    {
      "item_id": 1,
      "period": "Q1 2024",
      "usage": 75,
      "stock_start": 100,
      "stock_end": 25,
      "restocked": false,
      "restock_qty": 0
    },
    {
      "item_id": 1,
      "period": "Q2 2024",
      "usage": 85,
      "stock_start": 25,
      "stock_end": 40,
      "restocked": true,
      "restock_qty": 100
    }
  ]
}
```

## What Data Will Be Created

For each item, the script will create usage records for:
- **Q1 2024** (Jan-Mar)
- **Q2 2024** (Apr-Jun)
- **Q3 2024** (Jul-Sep)
- **Q4 2024** (Oct-Dec)

Each record includes:
- `usage`: Number of units used in that quarter
- `stock_start`: Starting stock quantity
- `stock_end`: Ending stock quantity
- `restocked`: Whether the item was restocked
- `restock_qty`: Quantity restocked (if applicable)

## Customizing Usage Values

If you want to use actual usage values instead of sample data:

1. **Edit the Artisan Command** (`app/Console/Commands/Add2024UsageData.php`):
   - Modify the `$quarterlyUsage` array with your actual values
   
2. **Edit the PHP Script** (`add_2024_usage_data.php`):
   - Modify the `$quarterlyUsage` array with your actual values

3. **Use the API**:
   - Send your actual usage data via POST requests

## Verifying Data

After adding the data, you can verify it:

1. **Check in Database:**
   ```sql
   SELECT * FROM supply_usages WHERE period LIKE 'Q% 2024' ORDER BY item_id, period;
   ```

2. **Check in Usage Overview Page:**
   - Navigate to Usage Overview
   - Select year 2024 from the dropdown
   - You should see the quarterly usage charts

3. **Check Forecasting:**
   - The forecasting system will now use 2024 data (along with 2025 data)
   - More historical data = better forecast accuracy

## Notes

- The script will skip records that already exist (won't create duplicates)
- Usage values are randomly generated samples - replace with actual data if available
- Stock values are calculated based on usage and restocking
- All records are created with timestamps

## Troubleshooting

**Error: "No items found"**
- Check that the item names match exactly (case-sensitive)
- Use `--dry-run` to see available items
- Check the items table: `SELECT id, unit FROM items WHERE unit IN ('Ballpens', 'Bondpaper A4(size)', 'Flash Drive');`

**Error: "Table does not exist"**
- Run migrations: `php artisan migrate`
- Check that the `supply_usages` table exists

**Records not showing in UI**
- Clear cache: `php artisan cache:clear`
- Check that the period format is correct: "Q1 2024", "Q2 2024", etc.
- Verify the API endpoint `/api/usage/quarterly` returns the data
