# Direct Insert Usage Data - Instructions

## Option 1: Run SQL Script (Easiest)

1. Open your database tool (pgAdmin, DBeaver, etc.)
2. Open the file: `insert_usage_data.sql`
3. Execute the SQL script
4. Done! ✅

The SQL script will add:
- **Item 69**: Q1-Q4 2024 (4 records)
- **Item 71**: Q1-Q4 2024 + Q1-Q3 2025 (7 records)
- **Item 72**: Q1-Q4 2024 + Q1-Q3 2025 (7 records)

**Total: 18 new records**

## Option 2: Use PHP Script

Run this command:
```bash
cd backend-laravel
php add_usage_direct.php
```

Or double-click: `add_usage_now.bat`

## Option 3: Use Artisan Command

```bash
cd backend-laravel
php artisan usage:add-forecast-data --auto-detect --fill-missing --force
```

## Verify Data

After inserting, run this SQL to verify:
```sql
SELECT item_id, period, usage, stock_start, stock_end, restocked 
FROM supply_usages 
WHERE item_id IN (69, 71, 72)
ORDER BY item_id, period;
```

You should see:
- Item 69: 8 records (Q1-Q4 2024 + Q1-Q4 2025)
- Item 71: 8 records (Q1-Q4 2024 + Q1-Q4 2025)
- Item 72: 8 records (Q1-Q4 2024 + Q1-Q4 2025)

## Notes

- The SQL uses `ON CONFLICT DO NOTHING` to avoid duplicates
- If your database doesn't support this, remove those lines
- Usage values are based on your existing patterns
- Stock values are calculated to flow between quarters
