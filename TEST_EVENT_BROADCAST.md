# Testing Event Broadcasting

## Step 1: Verify Backend is Broadcasting

### Check Laravel Logs

After borrowing an item, check `backend-laravel/storage/logs/laravel.log`:

```bash
cd backend-laravel
Get-Content storage/logs/laravel.log -Tail 50 | Select-String "ItemBorrowed|Broadcasting|broadcast"
```

You should see:
- `"Broadcasting ItemBorrowed event for item: X"`
- `"ItemBorrowed event broadcasted successfully"`

### If you DON'T see these logs:
The event is NOT being broadcast. Check:
1. Is `BROADCAST_DRIVER=reverb` in `.env`?
2. Is Reverb server running?
3. Does the borrowItem method call `event(new ItemBorrowed(...))`?

## Step 2: Verify Frontend is Receiving

### Check Browser Console

When you borrow an item, look for:
- `📡 ANY event received on inventory channel:` ← This will show ALL events
- `📦📦📦 ItemBorrowed event received` ← This confirms the specific event

### If you see "ANY event" but NOT "ItemBorrowed":
The event name might be different. Check the event name in the console output.

### If you DON'T see ANY events:
The event is not reaching the frontend. Check:
1. Is Reverb server running?
2. Is Echo connected? (Should see "connected" state)
3. Are keys matching? (`REVERB_APP_KEY` = `VITE_PUSHER_APP_KEY`)

## Step 3: Verify Event Data

If events ARE being received, check the data structure:

```javascript
// In browser console after borrowing:
// Look for the event data logged
```

The event should have:
```javascript
{
  item: {
    uuid: "...",
    quantity: 123,
    category: "Supply"  // or whatever the category name is
  },
  borrowed_quantity: 1,
  borrowed_by: "User Name"
}
```

## Step 4: Check Item Matching

The handler looks for items by UUID. Verify:

1. **Does the UUID match?**
   - Check console: `🔍 Looking for item with UUID: ...`
   - Compare with: `items.value.map(i => i.uuid)`

2. **Is the item in the array?**
   - Console should show: `✅ Found item at index X`
   - If it shows: `⚠️ Item with UUID ... not found` → Item not in current list

3. **Is it a Supply item?**
   - Console should show: `📦 This is a Supply item`
   - Check category match: `Current category: ... vs "supply"`

## Quick Test Commands

### In Browser Console:
```javascript
// Check if Echo is connected
window.Echo?.connector?.pusher?.connection?.state

// Check current items
items.value.length

// Check Supply items
consumableItems.value.length

// Find a Supply item by UUID
consumableItems.value.find(i => i.uuid === 'your-uuid-here')

// Check all categories
[...new Set(items.value.map(i => i.category))]
```

### Test Manual Update:
```javascript
// Manually update an item to test reactivity
const item = items.value.find(i => i.category?.toLowerCase() === 'supply')
if (item) {
  item.quantity = item.quantity - 1
  console.log('Updated:', item.quantity)
  // Check if Supply table updates
}
```

