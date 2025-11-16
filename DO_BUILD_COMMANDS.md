# DigitalOcean Build Commands

## ⚠️ IMPORTANT: Use these exact commands in DigitalOcean

### For Vue.js Frontend Deployment (from root):

**Source Directory:** `.` (root)

**Build Command:**
```bash
cd frontend-vue && npm install && npm run build
```

**Run Command:**
```bash
cd frontend-vue && npx serve -s dist -l $PORT
```

---

### For Python ML API Deployment (from root):

**Source Directory:** `.` (root)

**Build Command:**
```bash
pip install -r requirements.txt
```

**Run Command:**
```bash
python ml_api_server.py
```

---

### For Laravel Backend Deployment:

**Source Directory:** `backend-laravel`

**Build Command:**
```bash
composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link
```

**Run Command:**
```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

---

## ❌ DO NOT USE THESE (they cause the error):

```bash
# ❌ WRONG - tries to run composer when PHP buildpack isn't detected
composer install && npm install && npm run build

# ❌ WRONG - composer not available
npm run build && composer install
```

---

## ✅ CORRECT - Only build what's detected:

```bash
# ✅ For frontend only
cd frontend-vue && npm install && npm run build

# ✅ For Python only  
pip install -r requirements.txt
```

