# Debugging Supply Item Borrow Real-Time Updates

## Current Status
- ✅ Echo is connected
- ✅ Listener is active
- ✅ Event changed to `ShouldBroadcastNow` (immediate broadcast)
- ❓ Events may not be received or processed correctly

## Step-by-Step Debugging

### 1. Verify Backend Broadcasts Event

After borrowing a Supply item, check Laravel logs:

```bash
cd backend-laravel
Get-Content storage/logs/laravel.log -Tail 100 | Select-String "ItemBorrowed|Broadcasting|broadcast"
```

**Look for:**
- `"Broadcasting ItemBorrowed event for item: X"`
- `"ItemBorrowed event broadcasted successfully"`

**If these logs are missing:**
- The event is NOT being broadcast
- Check if `event(new ItemBorrowed(...))` is being called
- Verify `BROADCAST_DRIVER=reverb` in `.env`

### 2. Check Browser Console

When you borrow a Supply item, look for these messages:

**Expected:**
```
📦📦📦 ItemBorrowed event received (with dot) 📦📦📦
📦 Full event data: { ... }
```

**If you don't see this:**
- Event is not reaching the frontend
- Check Reverb server is running
- Verify keys match: `REVERB_APP_KEY` = `VITE_PUSHER_APP_KEY`

### 3. Verify Event Data Structure

When you see the event received, check the data:

```javascript
{
  item: {
    uuid: "...",      // Must match item in your list
    quantity: 123,    // Should be the NEW quantity
    category: "Supply" // Check exact name
  },
  borrowed_quantity: 1,
  borrowed_by: "..."
}
```

### 4. Check Item Matching

The handler logs will show:

**If item found:**
```
✅ Found item at index X
Current category: Supply, Event category: Supply
Updating quantity from X to Y
```

**If item NOT found:**
```
⚠️ Item with UUID ... not found
Available UUIDs: [...]
```

### 5. Test Manual Update

To verify Vue reactivity works, test manually in browser console:

```javascript
// Find a Supply item
const supplyItem = items.value.find(i => 
  i.category?.toLowerCase() === 'supply'
)

if (supplyItem) {
  console.log('Found Supply item:', supplyItem.uuid, supplyItem.quantity)
  
  // Manually update quantity
  const index = items.value.findIndex(i => i.uuid === supplyItem.uuid)
  items.value[index].quantity = supplyItem.quantity - 1
  
  // Check if Supply table updates
  console.log('Updated quantity:', items.value[index].quantity)
  console.log('In Supply table:', consumableItems.value.find(i => i.uuid === supplyItem.uuid))
}
```

## Quick Fixes

### If events are not received:
1. Restart Reverb server
2. Refresh browser
3. Verify Reverb is running: `netstat -ano | findstr ":8080"`

### If events are received but UI doesn't update:
1. Check category name matches exactly
2. Verify UUID matches
3. Check console for "Found item at index" message

### If Supply items aren't in the list:
1. Check category filter: `consumableItems.value.length`
2. Verify category name: `[...new Set(items.value.map(i => i.category))]`
3. May need to refresh items list after page load

## Next Steps

After borrowing a Supply item, please share:
1. Do you see "📦 ItemBorrowed event received" in console?
2. What does the event data show?
3. Do you see "✅ Found item at index" or "⚠️ Item not found"?
4. What is the category name shown?

