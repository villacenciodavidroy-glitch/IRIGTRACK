# 🔍 How to Find Your DigitalOcean App URLs

## Finding Your Python ML API URL

### Step 1: Go to Your Python API App
1. Log into DigitalOcean: https://cloud.digitalocean.com
2. Click **Apps** in the left sidebar
3. Find your **Python ML API** app/service
4. Click on it to open

### Step 2: Find the URL
The URL is displayed in several places:

#### Option A: From the App Overview Page
- Look at the top of the app page
- You'll see a section like:
  ```
  Live App
  https://your-python-api-abc123.ondigitalocean.app
  ```
- Or click the **"Live App"** button/link

#### Option B: From Settings
1. Click the **Settings** tab
2. Scroll to **"App-Level Settings"** or **"Domains"**
3. Look for **"App URL"** or **"Live URL"**
4. It will show: `https://your-app-name-xyz.ondigitalocean.app`

#### Option C: From the Deployments Tab
1. Click the **Deployments** tab
2. Look at the latest successful deployment
3. The URL is usually shown there

---

## URL Format

DigitalOcean App Platform URLs follow this format:
```
https://[app-name]-[random-id].ondigitalocean.app
```

**Example:**
```
https://irrigtrack-ml-api-abc123.ondigitalocean.app
```

---

## If You Haven't Deployed the Python API Yet

### Step 1: Create a New App for Python API
1. Go to **Apps** → **Create App**
2. Connect your GitHub repository
3. Select the repository: `IrrigTrack`

### Step 2: Configure the Python API Service
1. Click **"Edit"** or **"Add Component"**
2. Select **"Service"** (not Static Site)
3. Set **Source Directory:** `.` (root)
4. Set **Build Command:** `pip install -r requirements.txt`
5. Set **Run Command:** `python ml_api_server.py`
6. DigitalOcean will auto-detect Python from `requirements.txt`

### Step 3: Deploy and Get URL
1. Click **"Create Resources"** or **"Deploy"**
2. Wait for deployment to complete
3. Once deployed, the URL will be shown on the app overview page

---

## Setting Up All Three Services

### Service 1: Python ML API
- **Source Directory:** `.` (root)
- **Build Command:** `pip install -r requirements.txt`
- **Run Command:** `python ml_api_server.py`
- **URL Example:** `https://irrigtrack-ml-abc123.ondigitalocean.app`

### Service 2: Vue.js Frontend
- **Source Directory:** `frontend-vue`
- **Build Command:** `npm install && npm run build`
- **Run Command:** `npx serve -s dist -l $PORT`
- **URL Example:** `https://irrigtrack-frontend-xyz789.ondigitalocean.app`

### Service 3: Laravel Backend
- **Source Directory:** `backend-laravel`
- **Build Command:** `composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link`
- **Run Command:** `php artisan serve --host=0.0.0.0 --port=$PORT`
- **URL Example:** `https://irrigtrack-api-def456.ondigitalocean.app`

---

## Environment Variables Setup

Once you have all URLs, set them in each service:

### Frontend Environment Variables (Build Time):
```
VITE_API_BASE_URL=https://irrigtrack-api-def456.ondigitalocean.app/api
VITE_PY_API_BASE_URL=https://irrigtrack-ml-abc123.ondigitalocean.app
VITE_PUSHER_APP_KEY=(your-reverb-key)
VITE_PUSHER_HOST=irrigtrack-api-def456.ondigitalocean.app
VITE_PUSHER_PORT=443
```

### Backend Environment Variables:
```
APP_URL=https://irrigtrack-api-def456.ondigitalocean.app
REVERB_HOST=irrigtrack-api-def456.ondigitalocean.app
REVERB_APP_KEY=(your-reverb-key)
REVERB_APP_SECRET=(your-reverb-secret)
REVERB_APP_ID=(your-reverb-id)
```

---

## Quick Checklist

- [ ] Deploy Python ML API service → Get URL
- [ ] Deploy Vue.js Frontend service → Get URL  
- [ ] Deploy Laravel Backend service → Get URL
- [ ] Set `VITE_PY_API_BASE_URL` in Frontend = Python API URL
- [ ] Set `VITE_API_BASE_URL` in Frontend = Backend URL + `/api`
- [ ] Set `APP_URL` in Backend = Backend URL
- [ ] Set `REVERB_HOST` in Backend = Backend URL (without https://)

---

## 💡 Pro Tip

You can also add custom domains later:
1. Go to **Settings** → **Domains**
2. Add your custom domain (e.g., `api.yourdomain.com`)
3. DigitalOcean will provide DNS records to configure

