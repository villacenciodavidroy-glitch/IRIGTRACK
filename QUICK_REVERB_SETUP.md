# Quick Reverb + Echo Setup Checklist

## ✅ Step-by-Step Configuration

### 1. Backend Environment Variables

Add to `backend-laravel/.env`:

```env
BROADCAST_DRIVER=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key  
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**To generate keys, run:**
```bash
cd backend-laravel
php artisan reverb:install
```

### 2. Frontend Environment Variables

Create/update `frontend-vue/.env`:

```env
VITE_PUSHER_APP_KEY=your-app-key
VITE_PUSHER_HOST=localhost
VITE_PUSHER_PORT=8080
```

**CRITICAL:** `VITE_PUSHER_APP_KEY` must match `REVERB_APP_KEY` exactly!

### 3. Verify Files

**Backend:**
- ✅ `config/reverb.php` exists
- ✅ `routes/channels.php` has channel definition
- ✅ Event class exists (`app/Events/ItemBorrowed.php`)

**Frontend:**
- ✅ `src/bootstrap.js` initializes Echo
- ✅ `src/main.js` imports bootstrap
- ✅ `package.json` has `laravel-echo` and `pusher-js`

### 4. Start Reverb Server

```bash
cd backend-laravel
php artisan reverb:start
```

Keep this terminal window open!

### 5. Test Connection

1. Start frontend: `npm run dev`
2. Open browser console (F12)
3. Look for: `✅ Laravel Echo connected successfully`

## 🔧 Troubleshooting

**"Connection unavailable"**
→ Reverb server not running

**"Keys don't match"**
→ Check `REVERB_APP_KEY` = `VITE_PUSHER_APP_KEY`

**Events not received**
→ Check browser console and Laravel logs

