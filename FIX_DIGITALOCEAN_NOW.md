# 🚨 URGENT: Fix DigitalOcean Build Error

## The Problem:
Your DigitalOcean build command is trying to run `composer`, but composer is not available because you're deploying from the root directory (not `backend-laravel`).

## ✅ THE FIX (Do This Now):

### Step 1: Go to DigitalOcean Dashboard
1. Open https://cloud.digitalocean.com
2. Click **Apps** in the left sidebar
3. Click on your **irrigtrack** app

### Step 2: Go to Settings
1. Click the **Settings** tab at the top
2. Scroll down to **"Build & Deploy Settings"** section

### Step 3: Find "Build Command" Field
Look for a field labeled **"Build Command"** or **"Buildpack Build Command"**

### Step 4: DELETE and REPLACE
1. **DELETE everything** in the Build Command field
2. **PASTE this exact command** (choose based on what you're deploying):

---

## Option A: Frontend Only (Vue.js)
```bash
cd frontend-vue && npm install && npm run build
```

**Run Command:**
```bash
cd frontend-vue && npx serve -s dist -l $PORT
```

---

## Option B: Python API Only
```bash
pip install -r requirements.txt
```

**Run Command:**
```bash
python ml_api_server.py
```

---

## Option C: Use npm build script (Recommended)
```bash
npm run build
```

**Run Command:**
```bash
npm start
```

---

### Step 5: Save and Redeploy
1. Click **Save** button
2. Go to **Deployments** tab
3. Click **Create Deployment** or **Redeploy**
4. Wait for build to complete

---

## ❌ What NOT to Use:

**DO NOT use any of these (they cause the error):**
```bash
composer install                    # ❌ WRONG
composer install && npm run build  # ❌ WRONG
npm run build && composer install  # ❌ WRONG
```

---

## 🔍 How to Verify:

After updating, check the build logs. You should see:
- ✅ `npm install` running
- ✅ `npm run build` running
- ✅ `✓ built in X.XXs`
- ❌ NO `composer` commands
- ❌ NO `composer: command not found` errors

---

## 📝 If You Still See the Error:

1. Double-check the Build Command field - make sure there's NO `composer` in it
2. Make sure you clicked **Save**
3. Try clearing the field completely, then paste the command again
4. Check if there are multiple components - each needs its own build command

---

## 🎯 Quick Copy-Paste Commands:

**For Frontend:**
- Build: `cd frontend-vue && npm install && npm run build`
- Run: `cd frontend-vue && npx serve -s dist -l $PORT`

**For Python:**
- Build: `pip install -r requirements.txt`
- Run: `python ml_api_server.py`

**For Both (using npm):**
- Build: `npm run build`
- Run: `npm start`

---

**The error will stop once you remove `composer` from the Build Command field in DigitalOcean!**

