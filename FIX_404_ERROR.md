# 🔧 Fix 404 Error - Complete Guide

## Issues Found in Your .env File:

### ❌ Wrong Values (Fix These):

1. **VITE_API_BASE_URL** - Has trailing slash
   - ❌ Wrong: `https://irrigtrack-pse6h.ondigitalocean.app/`
   - ✅ Correct: `https://irrigtrack-pse6h.ondigitalocean.app/api`

2. **VITE_PY_API_BASE_URL** - Has trailing slash
   - ❌ Wrong: `https://irrigtrack-pse6h.ondigitalocean.app/`
   - ✅ Correct: `https://irrigtrack-pse6h.ondigitalocean.app` (no trailing slash)

3. **VITE_PUSHER_HOST** - Has https:// and trailing slash
   - ❌ Wrong: `https://irrigtrack-pse6h.ondigitalocean.app/`
   - ✅ Correct: `irrigtrack-pse6h.ondigitalocean.app` (hostname only, no protocol)

4. **VITE_PUSHER_PORT** - Wrong port for production
   - ❌ Wrong: `8080`
   - ✅ Correct: `443` (for HTTPS)

5. **REVERB_HOST** - Has prefix in value
   - ❌ Wrong: `REVERB_HOST=irrigtrack-api-abc123.ondigitalocean.app`
   - ✅ Correct: `irrigtrack-pse6h.ondigitalocean.app` (just the hostname)

6. **VITE_PUSHER_APP_KEY** - Looks like APP_KEY, not Reverb key
   - ❌ Wrong: `base64:vL5aWFDmojCJp3Czm7m8C8oQ9EdFkacA6dulMjeL6No=`
   - ✅ Correct: `n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow` (the Reverb key we generated)

---

## ✅ Corrected Environment Variables

### For Frontend (Build Time):
```
VITE_API_BASE_URL=https://irrigtrack-pse6h.ondigitalocean.app/api
VITE_PY_API_BASE_URL=https://irrigtrack-pse6h.ondigitalocean.app
VITE_PUSHER_APP_KEY=n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow
VITE_PUSHER_APP_CLUSTER=mt1
VITE_PUSHER_HOST=irrigtrack-pse6h.ondigitalocean.app
VITE_PUSHER_PORT=443
```

### For Backend (Run Time):
```
APP_NAME=IrrigTrack
APP_ENV=production
APP_KEY=base64:vL5aWFDmojCJp3Czm7m8C8oQ9EdFkacA6dulMjeL6No=
APP_DEBUG=false
APP_URL=https://irrigtrack-pse6h.ondigitalocean.app

REVERB_APP_ID=ec317d18-4302-476f-a918-b356763ee514
REVERB_APP_KEY=n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow
REVERB_APP_SECRET=h9YEiVOZEHsge48GePeO-9M3xA02CCyw0Pyg8MVbmlRGTyVSHBKvJDvnjN2e6T8iLxeomSv2OY3RL8AsU3kLgQ
REVERB_HOST=irrigtrack-pse6h.ondigitalocean.app
REVERB_PORT=443
REVERB_SCHEME=https
```

---

## Steps to Fix in DigitalOcean:

### Step 1: Update Frontend Environment Variables
1. Go to DigitalOcean → Your Frontend App → Settings → Environment Variables
2. Update each variable with the corrected values above
3. Make sure to remove trailing slashes and `https://` from `VITE_PUSHER_HOST`

### Step 2: Update Backend Environment Variables
1. Go to DigitalOcean → Your Backend App → Settings → Environment Variables
2. Fix `REVERB_HOST` - remove the `REVERB_HOST=` prefix from the value
3. Make sure `REVERB_APP_KEY` matches `VITE_PUSHER_APP_KEY` in frontend

### Step 3: Verify Build & Run Commands
**Frontend:**
- Source Directory: `.` (root) OR `frontend-vue`
- Build Command: `npm run build`
- Run Command: `npm start`

### Step 4: Redeploy
1. After updating environment variables, trigger a new deployment
2. Wait for build to complete
3. Check the app URL

---

## Additional Checks:

### Check if `serve` package is installed
The `npm start` command uses `npx serve`, which should work, but let's make sure the build creates the `dist` folder correctly.

### Verify Build Output
After deployment, check the build logs to ensure:
- ✅ `npm install` completed
- ✅ `npm run build` completed
- ✅ `dist` folder was created
- ✅ `npm start` is running

---

## If Still Getting 404:

1. **Check Build Logs** - Make sure the build completed successfully
2. **Check Deploy Logs** - Make sure `npm start` is running
3. **Verify Source Directory** - Should be `.` (root) if using root package.json
4. **Check Port** - Make sure `$PORT` environment variable is set by DigitalOcean

