# 🔧 Fix 404 Error - Deployment Configuration

## The Problem:
The 404 error suggests the frontend isn't being served correctly. This could be because:
1. The `dist` folder isn't being created
2. The `serve` command isn't finding the dist folder
3. The working directory is wrong

## ✅ Solution: Deploy from `frontend-vue` Directory

**The best solution is to change your DigitalOcean source directory to `frontend-vue` instead of root.**

### Step 1: Update DigitalOcean Settings

1. Go to DigitalOcean → Your App → Settings
2. Find **"Source Directory"** or **"Root Directory"**
3. Change it from `.` (root) to `frontend-vue`
4. Update **Build Command** to:
   ```bash
   npm install && npm run build
   ```
5. Update **Run Command** to:
   ```bash
   npx serve -s dist -l $PORT
   ```

### Step 2: Update Environment Variables

Make sure all `VITE_*` variables are set in DigitalOcean (Build Time scope).

### Step 3: Redeploy

After changing the source directory, trigger a new deployment.

---

## Alternative: Keep Root Directory

If you want to keep deploying from root, use these commands:

### Build Command:
```bash
npm run build
```

### Run Command:
```bash
npm start
```

But make sure the build actually creates `frontend-vue/dist` folder.

---

## Debugging Steps:

### Check Build Logs:
1. Go to DigitalOcean → Your App → Deployments
2. Click on the latest deployment
3. Check **Build Logs** for:
   - ✅ `npm install` completed
   - ✅ `npm run build` completed
   - ✅ `dist` folder was created
   - ❌ Any errors

### Check Deploy Logs:
1. Check **Deploy Logs** for:
   - ✅ `serve` command is running
   - ✅ Server is listening on port
   - ❌ Any errors about missing dist folder

### Verify dist Folder:
The build should create `frontend-vue/dist` with:
- `index.html`
- `assets/` folder with JS and CSS files

---

## Recommended Configuration:

**Source Directory:** `frontend-vue`

**Build Command:**
```bash
npm install && npm run build
```

**Run Command:**
```bash
npx serve -s dist -l $PORT
```

**Environment Variables (Build Time):**
```
VITE_API_BASE_URL=https://irrigtrack-pse6h.ondigitalocean.app/api
VITE_PY_API_BASE_URL=https://irrigtrack-pse6h.ondigitalocean.app
VITE_PUSHER_APP_KEY=n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow
VITE_PUSHER_HOST=irrigtrack-pse6h.ondigitalocean.app
VITE_PUSHER_PORT=443
VITE_PUSHER_APP_CLUSTER=mt1
```

---

## If Still Getting 404:

1. **Check if dist folder exists** in build logs
2. **Verify serve command** is running in deploy logs
3. **Check PORT variable** is set by DigitalOcean
4. **Try using absolute path** in serve command:
   ```bash
   npx serve -s ./dist -l $PORT
   ```

