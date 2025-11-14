# How to Start and Verify Reverb Server

## Step 1: Start Reverb Server

### Option A: Double-click the script
- **Windows:** Double-click `start_reverb.bat` in your project root
- **Mac/Linux:** Run `./start_reverb.sh` (if created)

### Option B: Manual Start (Recommended for troubleshooting)
1. Open a **NEW** PowerShell/Command Prompt window
2. Navigate to backend directory:
   ```powershell
   cd C:\Users\User\NIA_SystemProject\backend-laravel
   ```
3. Start Reverb:
   ```powershell
   php artisan reverb:start
   ```

4. **Keep this window open** - You should see:
   ```
   Starting Reverb server on 0.0.0.0:8080...
   Reverb server started successfully.
   ```

## Step 2: Verify Reverb is Running

### Check if port 8080 is listening:
```powershell
netstat -ano | findstr ":8080"
```
You should see a line with `:8080` and `LISTENING`

### Check browser console:
After refreshing your Inventory page, you should see:
- ✅ `Laravel Echo initialized`
- ✅ `Echo connection state: connected` (NOT "unavailable")
- ✅ `Real-time inventory listener active`

## Step 3: Test Real-Time Updates

1. **Open Inventory page** in browser
2. **Open browser console** (F12)
3. **Borrow an item** from your mobile app
4. **Watch console** - Should see:
   - `📦 ItemBorrowed event received`
   - `✅ Updated item [UUID] quantity...`
5. **Watch the page** - Quantity should update automatically!

## Troubleshooting

### If you see "Connection unavailable":
1. ✅ Make sure Reverb server window is still running
2. ✅ Check if port 8080 is listening (use netstat command above)
3. ✅ Verify `.env` files have matching keys:
   - `backend-laravel/.env`: `REVERB_APP_KEY=your-key`
   - `frontend-vue/.env`: `VITE_PUSHER_APP_KEY=your-key` (SAME VALUE)

### If port 8080 is in use:
1. Find what's using it:
   ```powershell
   netstat -ano | findstr ":8080"
   ```
2. Kill the process or change Reverb port in `.env`:
   ```
   REVERB_PORT=8081
   ```
   (Don't forget to update `VITE_PUSHER_PORT=8081` in frontend `.env`)

### If still not connecting:
1. Check firewall - Port 8080 might be blocked
2. Try accessing `http://localhost:8080` in browser (should fail, but confirms port is open)
3. Check Laravel logs: `backend-laravel/storage/logs/laravel.log`

## Important Notes

- **Reverb must run in a separate terminal window** - Keep it open!
- **Don't close Reverb** while testing real-time features
- **Refresh browser** after starting Reverb to reconnect
- **Reverb and your dev servers** can run simultaneously

