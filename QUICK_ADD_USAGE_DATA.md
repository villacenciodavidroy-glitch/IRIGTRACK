# Quick Guide: Add Usage Data for Forecasting

## 🚀 Quick Start

**Easiest way (auto-detects items from your database):**
```bash
cd backend-laravel
php artisan usage:add-forecast-data --auto-detect --fill-missing
```

Or use the batch file:
```bash
add_2024_usage_data.bat
```

## What It Does

Based on your database, this will:
1. **Auto-detect** items 69, 71, and 72 (items with existing usage data)
2. **Add 2024 data** (Q1-Q4 2024) for all items
3. **Fill missing 2025 quarters** (Q1-Q3 for items 71 and 72)
4. **Analyze existing patterns** to generate realistic usage values

## Command Options

```bash
# Auto-detect items and add 2024 + missing 2025 data
php artisan usage:add-forecast-data --auto-detect --fill-missing

# Preview first (recommended)
php artisan usage:add-forecast-data --auto-detect --fill-missing --dry-run

# Specify specific item IDs
php artisan usage:add-forecast-data --item-ids=69,71,72 --fill-missing

# Add only 2024 data
php artisan usage:add-forecast-data --auto-detect --year=2024

# Add only 2025 missing quarters
php artisan usage:add-forecast-data --auto-detect --year=2025
```

## Expected Results

After running, you'll have:
- **Item 69**: Q1-Q4 2024 + Q1-Q4 2025 (complete)
- **Item 71**: Q1-Q4 2024 + Q1-Q4 2025 (complete)
- **Item 72**: Q1-Q4 2024 + Q1-Q4 2025 (complete)

**Total: 24 new records** (12 for 2024 + 12 for missing 2025 quarters)

## How It Works

1. **Analyzes existing data** - Looks at your current usage patterns
2. **Calculates averages** - Uses your actual usage values as baseline
3. **Applies seasonal patterns** - Q4 typically higher, Q1 typically lower
4. **Links stock values** - Each quarter's stock_start = previous quarter's stock_end
5. **Smart restocking** - Restocks when stock gets low

## Verify Results

```sql
-- Check 2024 data
SELECT * FROM supply_usages WHERE period LIKE 'Q% 2024' ORDER BY item_id, period;

-- Check all data
SELECT item_id, period, usage, stock_start, stock_end, restocked 
FROM supply_usages 
ORDER BY item_id, period;
```

## Notes

- Uses your existing usage patterns to generate realistic values
- Skips records that already exist (won't create duplicates)
- Stock values are calculated based on previous quarters
- Restocking happens when stock drops below 30% or randomly
