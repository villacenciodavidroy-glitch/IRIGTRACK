# Automatic Usage Tracking Setup

## ✅ What Was Implemented

### 1. **Automatic Usage Tracking on Fulfillment**
   - When a supply request is **fulfilled**, the system now **automatically creates/updates usage records** in the `supply_usages` table
   - This happens for both single-item and multi-item fulfillments
   - Usage is tracked for the **current quarter** automatically

### 2. **Backfill Script for Q1 2026**
   - Created a script to backfill usage data from existing fulfilled supply requests
   - Processes all fulfilled requests between Jan 1 - Mar 31, 2026
   - Creates or updates usage records in `supply_usages` table

## 🚀 How It Works

### Automatic Tracking Flow

1. **User requests supplies** → Creates record in `supply_requests` table
2. **Request is approved** → Status changes to "approved"
3. **Request is fulfilled** → **NEW: Automatically creates usage record!**
   - Gets current quarter (e.g., "Q1 2026")
   - Finds or creates usage record for that item + period
   - Adds fulfilled quantity to usage
   - Updates stock_start and stock_end values

### What Gets Tracked

- **Period**: Automatically determined (Q1, Q2, Q3, or Q4 + year)
- **Usage**: Quantity fulfilled from supply requests
- **Stock Start**: Stock level before fulfillment
- **Stock End**: Stock level after fulfillment

## 📋 How to Use

### For Future Requests (Automatic)

**No action needed!** When you fulfill supply requests going forward, usage data will be automatically created.

### For Existing Q1 2026 Data (Backfill)

If you have fulfilled supply requests in Q1 2026 that don't have usage records yet:

**Option 1: Windows Batch File**
```bash
backfill_q1_2026_usage.bat
```

**Option 2: PowerShell**
```powershell
.\backfill_q1_2026_usage.ps1
```

**Option 3: Direct PHP**
```bash
cd backend-laravel
php backfill_q1_2026_usage.php
```

## 🔍 Verification

After running the backfill script or fulfilling new requests:

1. **Check Usage Overview Page**
   - Navigate to Usage Overview
   - Q1 2026 should now show usage data instead of 0

2. **Check Database**
   ```sql
   SELECT item_id, period, usage, stock_start, stock_end 
   FROM supply_usages 
   WHERE period = 'Q1 2026'
   ORDER BY item_id;
   ```

3. **Check Browser Console**
   - Open browser DevTools (F12)
   - Look for logs: "Automatically tracked usage for item..."

## 📊 Example

**Before:**
- Supply Request #123: 50 units of Ballpens fulfilled on Jan 15, 2026
- Usage Overview Q1 2026: Shows **0** for Ballpens

**After Automatic Tracking:**
- Supply Request #123: 50 units of Ballpens fulfilled on Jan 15, 2026
- System automatically creates: `supply_usages` record
  - item_id: 71 (Ballpens)
  - period: "Q1 2026"
  - usage: 50
- Usage Overview Q1 2026: Shows **50** for Ballpens ✅

## ⚠️ Important Notes

1. **Only Fulfilled Requests**: Only supply requests with status "fulfilled" are tracked
2. **Current Quarter**: New fulfillments are tracked for the current quarter automatically
3. **Historical Data**: Use the backfill script for past quarters
4. **Multiple Fulfillments**: If the same item is fulfilled multiple times in the same quarter, usage is **added together**

## 🛠️ Troubleshooting

### Usage still shows 0 after fulfillment

1. **Check request status**: Must be "fulfilled", not just "approved"
2. **Check logs**: Look for "Automatically tracked usage..." in Laravel logs
3. **Check period**: Verify the fulfillment date matches the quarter you're viewing
4. **Run backfill**: If it's an old fulfillment, run the backfill script

### Backfill script shows 0 requests

- Make sure you have fulfilled requests in the date range (Jan 1 - Mar 31, 2026)
- Check that requests have status = "fulfilled"
- Verify `fulfilled_at` dates are within Q1 2026

## 📝 Code Changes Made

1. **SupplyRequestController.php**
   - Added `ItemUsage` model import
   - Added `trackUsageFromFulfillment()` method
   - Integrated automatic tracking in `fulfillRequest()` method (both single and multi-item)

2. **backfill_q1_2026_usage.php**
   - New script to backfill historical data
   - Processes all fulfilled requests in Q1 2026
   - Creates or updates usage records

## ✅ Summary

- **Automatic tracking**: ✅ Working for all new fulfillments
- **Backfill script**: ✅ Available for Q1 2026 historical data
- **Usage Overview**: ✅ Will now show data instead of 0

Your forecasting system will now have accurate usage data automatically! 🎉

