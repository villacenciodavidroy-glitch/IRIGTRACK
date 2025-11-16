# 🚀 DigitalOcean Frontend Setup - Fix 404 Error

## ✅ RECOMMENDED: Deploy from `frontend-vue` Directory

This is the **best and simplest solution** to fix the 404 error.

### Step 1: Update DigitalOcean App Settings

1. Go to **DigitalOcean** → Your App → **Settings**
2. Scroll to **"Source Directory"** or **"Root Directory"**
3. **Change from:** `.` (root)
4. **Change to:** `frontend-vue`

### Step 2: Update Build & Run Commands

**Build Command:**
```bash
npm install && npm run build
```

**Run Command:**
```bash
npx serve -s dist -l $PORT
```

### Step 3: Set Environment Variables (Build Time)

Go to **Settings** → **Environment Variables** and add:

| Key | Value | Scope |
|-----|-------|-------|
| `VITE_API_BASE_URL` | `https://irrigtrack-pse6h.ondigitalocean.app/api` | Build time |
| `VITE_PY_API_BASE_URL` | `https://irrigtrack-pse6h.ondigitalocean.app` | Build time |
| `VITE_PUSHER_APP_KEY` | `n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow` | Build time |
| `VITE_PUSHER_HOST` | `irrigtrack-pse6h.ondigitalocean.app` | Build time |
| `VITE_PUSHER_PORT` | `443` | Build time |
| `VITE_PUSHER_APP_CLUSTER` | `mt1` | Build time |

### Step 4: Save and Redeploy

1. Click **Save**
2. Go to **Deployments** tab
3. Click **Create Deployment** or **Redeploy**
4. Wait for deployment to complete

---

## Why This Works:

- ✅ Build runs in `frontend-vue` directory
- ✅ `dist` folder is created in `frontend-vue/dist`
- ✅ `serve` command finds `dist` folder easily
- ✅ No path issues with `cd` commands
- ✅ Simpler configuration

---

## Alternative: Keep Root Directory (If Needed)

If you must deploy from root, use:

**Source Directory:** `.` (root)

**Build Command:**
```bash
npm run build
```

**Run Command:**
```bash
npm start
```

But **deploying from `frontend-vue` is recommended** as it's simpler and more reliable.

---

## Verify It's Working:

After deployment, check:

1. **Build Logs** should show:
   - ✅ `npm install` completed
   - ✅ `npm run build` completed
   - ✅ `dist` folder created

2. **Deploy Logs** should show:
   - ✅ `serve` command running
   - ✅ Server listening on port

3. **Browser** should show:
   - ✅ Your Vue.js app (not 404)

---

## Troubleshooting:

### If still getting 404:

1. **Check Build Logs** - Did the build complete?
2. **Check Deploy Logs** - Is `serve` running?
3. **Verify dist folder** - Does it contain `index.html`?
4. **Check PORT** - Is `$PORT` environment variable set?

### Common Issues:

- **Build failed** → Check build logs for errors
- **dist folder missing** → Build didn't complete
- **serve not found** → Add `serve` to dependencies (already done)
- **Wrong port** → Make sure `$PORT` is used, not hardcoded

---

**The key fix: Change Source Directory to `frontend-vue` in DigitalOcean!**

