# 🔧 DigitalOcean Build Error Fix

## ❌ Current Error:
```
bash: line 3: composer: command not found
```

## ✅ Solution: Update Build Command in DigitalOcean

### Step 1: Go to DigitalOcean App Platform
1. Log into DigitalOcean
2. Go to **Apps** → Select your app
3. Click **Settings** tab
4. Scroll to **Build & Deploy Settings**

### Step 2: Update Build Command

**Find the "Build Command" field and replace it with ONE of these:**

---

## Option A: Frontend Only (Recommended if deploying Vue.js)

**Build Command:**
```bash
cd frontend-vue && npm install && npm run build
```

**Run Command:**
```bash
cd frontend-vue && npx serve -s dist -l $PORT
```

**Source Directory:** `.` (root) OR `frontend-vue`

---

## Option B: Python API Only

**Build Command:**
```bash
pip install -r requirements.txt
```

**Run Command:**
```bash
python ml_api_server.py
```

**Source Directory:** `.` (root)

---

## Option C: Use Build Script

**Build Command:**
```bash
chmod +x build.sh && ./build.sh
```

**Run Command (choose one):**
```bash
# For frontend:
cd frontend-vue && npx serve -s dist -l $PORT

# OR for Python:
python ml_api_server.py
```

**Source Directory:** `.` (root)

---

## ❌ DO NOT USE (These cause the error):

```bash
# ❌ WRONG - composer not available
composer install && npm install && npm run build

# ❌ WRONG - composer not available  
npm run build && composer install

# ❌ WRONG - any command with "composer"
```

---

## 🔍 How to Check Your Current Build Command:

1. Go to DigitalOcean → Your App → Settings
2. Look for "Build Command" field
3. If it contains the word "composer", **DELETE IT**
4. Replace with one of the commands above

---

## ✅ After Updating:

1. Click **Save**
2. Go to **Deployments** tab
3. Click **Create Deployment** or **Redeploy**
4. The build should now succeed!

---

## 📝 Note:

If you need to deploy the Laravel backend, create a **separate app** in DigitalOcean with:
- **Source Directory:** `backend-laravel`
- **Build Command:** `composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link`
- **Run Command:** `php artisan serve --host=0.0.0.0 --port=$PORT`

