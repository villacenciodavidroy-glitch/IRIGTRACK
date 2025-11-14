# Complete Laravel Reverb + Echo Setup Guide

This guide will help you configure real-time communication between Laravel (backend) and Vue 3 (frontend) using Laravel Reverb and Echo.

## Prerequisites

✅ Laravel Reverb installed: `composer require laravel/reverb`
✅ Frontend packages installed: `laravel-echo` and `pusher-js`

## Step 1: Backend Configuration

### 1.1 Install Reverb (if not already done)

```bash
cd backend-laravel
composer require laravel/reverb
php artisan reverb:install
```

### 1.2 Generate Reverb Keys

Generate unique app credentials for Reverb:

```bash
php artisan reverb:install
```

This will create the necessary configuration. If it doesn't automatically add to `.env`, you'll need to add them manually.

### 1.3 Configure `.env` File

Add these variables to `backend-laravel/.env`:

```env
# Broadcasting Driver (REQUIRED)
BROADCAST_DRIVER=reverb

# Reverb Configuration (REQUIRED)
REVERB_APP_ID=your-app-id-here
REVERB_APP_KEY=your-app-key-here
REVERB_APP_SECRET=your-app-secret-here
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Important:** After adding these, run:
```bash
php artisan config:clear
```

### 1.4 Verify Broadcasting Config

Ensure `backend-laravel/config/broadcasting.php` exists and has Reverb configured. If it doesn't exist, publish it:

```bash
php artisan config:publish broadcasting
```

### 1.5 Configure Broadcast Channels

The file `backend-laravel/routes/channels.php` should define your channels:

```php
<?php

use Illuminate\Support\Facades\Broadcast;

// Public channel for inventory updates
Broadcast::channel('inventory', function ($user = null) {
    return true; // Allow all users
});
```

## Step 2: Frontend Configuration

### 2.1 Install Frontend Packages

Ensure packages are installed in `frontend-vue`:

```bash
cd frontend-vue
npm install laravel-echo pusher-js
```

### 2.2 Configure Frontend `.env`

Create or update `frontend-vue/.env`:

```env
# These must match your backend REVERB_APP_KEY
VITE_PUSHER_APP_KEY=your-app-key-here
VITE_PUSHER_HOST=localhost
VITE_PUSHER_PORT=8080
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

**CRITICAL:** `VITE_PUSHER_APP_KEY` must **exactly match** `REVERB_APP_KEY` from backend!

### 2.3 Initialize Echo in Bootstrap

The file `frontend-vue/src/bootstrap.js` should initialize Echo. It should look like this:

```javascript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || 'your-pusher-app-key'
const pusherHost = import.meta.env.VITE_PUSHER_HOST || 'localhost'
const pusherPort = import.meta.env.VITE_PUSHER_PORT || 8080

const isLocalhost = pusherHost === 'localhost' || pusherHost === '127.0.0.1'

const echoConfig = {
  broadcaster: 'pusher',
  key: pusherKey,
  wsHost: pusherHost,
  wsPort: pusherPort,
  wssPort: pusherPort,
  forceTLS: false, // Set to false for localhost
  enabledTransports: isLocalhost ? ['ws'] : ['ws', 'wss'],
  disableStats: true,
  encrypted: false,
  cluster: 'mt1' // Dummy cluster, ignored when wsHost is set
}

window.Echo = new Echo(echoConfig)

export default window.Echo
```

### 2.4 Import Bootstrap in Main.js

Ensure `frontend-vue/src/main.js` imports bootstrap:

```javascript
import './bootstrap' // Initialize Laravel Echo
```

## Step 3: Create a Broadcastable Event

### 3.1 Event Example (Already exists: ItemBorrowed)

Your event should implement `ShouldBroadcast`:

```php
<?php

namespace App\Events;

use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemBorrowed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;
    public $borrowedQuantity;
    public $borrowedBy;

    public function __construct(Item $item, $borrowedQuantity, $borrowedBy)
    {
        $this->item = $item;
        $this->borrowedQuantity = $borrowedQuantity;
        $this->borrowedBy = $borrowedBy;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('inventory'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ItemBorrowed';
    }

    public function broadcastWith(): array
    {
        return [
            'item' => [
                'id' => $this->item->id,
                'uuid' => $this->item->uuid,
                'quantity' => $this->item->quantity,
                // Add other fields as needed
            ],
            'borrowed_quantity' => $this->borrowedQuantity,
            'borrowed_by' => $this->borrowedBy,
        ];
    }
}
```

### 3.2 Fire the Event

In your controller, fire the event:

```php
use App\Events\ItemBorrowed;

// After updating item quantity
event(new ItemBorrowed($item, $request->quantity, $request->borrowed_by));
```

## Step 4: Frontend Event Listener

### 4.1 Listen in Vue Component

In your Vue component (e.g., `Inventory.vue`):

```javascript
import { onMounted, onUnmounted } from 'vue'

onMounted(() => {
  if (window.Echo) {
    const channel = window.Echo.channel('inventory')
    
    // Listen for ItemBorrowed events
    channel.listen('.ItemBorrowed', (data) => {
      console.log('Item borrowed:', data)
      // Update your UI here
      updateItemQuantity(data.item.uuid, data.item.quantity)
    })
  }
})

onUnmounted(() => {
  if (window.Echo) {
    window.Echo.leave('inventory')
  }
})
```

## Step 5: Start Reverb Server

### 5.1 Start Reverb

**IMPORTANT:** Reverb must run in a separate terminal window:

```bash
cd backend-laravel
php artisan reverb:start
```

You should see:
```
Starting Reverb server on 0.0.0.0:8080...
Reverb server started successfully.
```

**Keep this window open** while using your application.

### 5.2 Verify Reverb is Running

Check if port 8080 is listening:

```powershell
# Windows PowerShell
netstat -ano | findstr ":8080"
```

You should see the port is `LISTENING`.

## Step 6: Testing

### 6.1 Test Connection

1. Start Reverb server
2. Start your frontend dev server (`npm run dev`)
3. Open browser console (F12)
4. Navigate to a page that uses Echo

You should see:
- ✅ `Laravel Echo initialized`
- ✅ `Echo connection state: connected`
- ✅ `Real-time inventory listener active`

### 6.2 Test Broadcasting

1. Trigger an event from your backend (e.g., borrow an item)
2. Check browser console for:
   - `📦 ItemBorrowed event received`
   - Event data should appear

### 6.3 Common Issues

**Issue: "Connection unavailable"**
- ✅ Reverb server not running → Start with `php artisan reverb:start`
- ✅ Keys don't match → Verify `REVERB_APP_KEY` = `VITE_PUSHER_APP_KEY`
- ✅ Port blocked → Check firewall settings

**Issue: Events not received**
- ✅ Check browser console for connection status
- ✅ Verify event is being fired (check Laravel logs)
- ✅ Check channel name matches (`inventory`)
- ✅ Verify event name matches (`.ItemBorrowed`)

**Issue: "WebSocket connection failed"**
- ✅ Reverb not running
- ✅ Wrong port (should be 8080)
- ✅ Using `wss://` instead of `ws://` for localhost

## Step 7: Verify Configuration

### 7.1 Quick Checklist

**Backend:**
- [ ] `BROADCAST_DRIVER=reverb` in `.env`
- [ ] `REVERB_APP_KEY` set in `.env`
- [ ] `REVERB_PORT=8080` in `.env`
- [ ] Event implements `ShouldBroadcast`
- [ ] Event fires using `event()` helper
- [ ] Channel defined in `routes/channels.php`

**Frontend:**
- [ ] `laravel-echo` and `pusher-js` installed
- [ ] `bootstrap.js` imports Echo
- [ ] `main.js` imports `bootstrap.js`
- [ ] `VITE_PUSHER_APP_KEY` matches `REVERB_APP_KEY`
- [ ] Component listens to events on mounted
- [ ] Component cleans up on unmounted

**Server:**
- [ ] Reverb server running (`php artisan reverb:start`)
- [ ] Port 8080 accessible
- [ ] No firewall blocking

## Step 8: Production Considerations

For production, you'll need to:

1. **Use Secure WebSocket (WSS):**
   - Set `REVERB_SCHEME=https` in backend
   - Set `VITE_PUSHER_FORCE_TLS=true` in frontend
   - Update `forceTLS: true` in bootstrap.js

2. **Run Reverb as Service:**
   - Use Supervisor or PM2 to keep Reverb running
   - Set up auto-restart on failure

3. **Use Private Channels:**
   - Update `routes/channels.php` to require authentication
   - Update frontend to use `Echo.private()` instead of `Echo.channel()`

## Quick Start Script

Create `start_reverb.bat` (Windows) in project root:

```bat
@echo off
echo Starting Laravel Reverb...
cd backend-laravel
php artisan reverb:start
pause
```

Double-click to start Reverb easily!

## Summary

1. ✅ Configure backend `.env` with Reverb settings
2. ✅ Configure frontend `.env` with matching keys
3. ✅ Initialize Echo in `bootstrap.js`
4. ✅ Create broadcastable events
5. ✅ Listen to events in Vue components
6. ✅ Start Reverb server
7. ✅ Test connection and events

**Remember:** Reverb must always be running for real-time features to work!

