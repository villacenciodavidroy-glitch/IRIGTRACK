# DigitalOcean Hosting Guide: Droplets vs App Platform

## Your Application Architecture

- **Frontend**: Vue.js (Static site)
- **Backend**: Laravel (PHP)
- **ML API**: Python (Flask/FastAPI)
- **Database**: PostgreSQL
- **Real-time**: Laravel Reverb (WebSocket server)

---

## Recommendation: **App Platform** (Best for Your Use Case)

### ✅ Why App Platform is Better for You:

1. **Multi-Service Architecture** - You already have `app.yaml` configured for 3 services
2. **Managed PostgreSQL** - DigitalOcean offers managed PostgreSQL databases
3. **Automatic Scaling** - Built-in load balancing and auto-scaling
4. **Zero-Downtime Deployments** - Automatic blue-green deployments
5. **SSL/HTTPS Included** - Free SSL certificates automatically configured
6. **GitHub Integration** - Automatic deployments on push
7. **Environment Variables** - Easy management across services
8. **Cost Effective** - Pay only for what you use (can start at ~$12/month for all 3 services)

### ⚠️ Considerations:

1. **Laravel Reverb (WebSocket)** - App Platform supports WebSocket, but you need to configure it properly
2. **File Storage** - Use DigitalOcean Spaces (S3-compatible) for Laravel storage
3. **Queue Workers** - You may need a separate worker component for Laravel queues

---

## Alternative: Droplets (If You Need More Control)

### ✅ When to Use Droplets:

- Need full server control (SSH access, custom configurations)
- Want to run everything on one server (cost savings)
- Need specific system-level packages or configurations
- Want to manage your own backups and updates

### ❌ Drawbacks:

- Manual setup and maintenance
- You manage security updates
- Manual SSL certificate setup (Let's Encrypt)
- Manual scaling (need to set up load balancers)
- More time-consuming deployments

---

## Recommended Setup: App Platform + Managed PostgreSQL

### Architecture:

```
┌─────────────────────────────────────────┐
│     DigitalOcean App Platform           │
├─────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌────────┐│
│  │ Frontend │  │ Backend  │  │ Python ││
│  │ (Vue.js) │  │(Laravel) │  │  ML API ││
│  └──────────┘  └──────────┘  └────────┘│
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│   Managed PostgreSQL Database            │
│   (DigitalOcean Database Cluster)       │
└─────────────────────────────────────────┘
```

### Cost Estimate (Starting):

- **Frontend Service**: $5/month (basic-xxs)
- **Backend Service**: $5/month (basic-xxs) 
- **Python ML API**: $5/month (basic-xxs)
- **Managed PostgreSQL**: $15/month (1GB RAM, 1 vCPU, 10GB storage)
- **DigitalOcean Spaces** (for file storage): $5/month
- **Total**: ~$35/month

*Note: Prices may vary. Check current DigitalOcean pricing.*

---

## Step-by-Step Deployment Guide

### 1. Create Managed PostgreSQL Database

1. Go to DigitalOcean → Databases → Create Database
2. Choose **PostgreSQL**
3. Select region (same as your app)
4. Choose plan (start with 1GB RAM, 1 vCPU)
5. **Save connection details**:
   - Host
   - Port (usually 25060)
   - Database name
   - Username
   - Password
   - **Connection String** (for easy setup)

### 2. Update Your `app.yaml` for Production

Your current `app.yaml` needs these additions:

```yaml
name: irrigtrack

# Add database component
databases:
  - name: nia-db
    engine: PG
    version: "15"  # or latest
    production: false  # Set to true for production
    cluster_name: your-db-cluster-name

services:
  - name: frontend
    source_dir: frontend-vue
    github:
      repo: your-username/irrigtrack
      branch: main
    build_command: npm install && npm run build
    run_command: npx serve -s dist -l $PORT
    environment_slug: node-js
    instance_count: 1
    instance_size_slug: basic-xxs
    envs:
      - key: VITE_API_BASE_URL
        scope: BUILD_TIME
        value: ${backend.URL}/api
      - key: VITE_PY_API_BASE_URL
        scope: BUILD_TIME
        value: ${python-api.URL}
      - key: VITE_PUSHER_APP_KEY
        scope: BUILD_TIME
        value: ${backend.REVERB_APP_KEY}
      - key: VITE_PUSHER_HOST
        scope: BUILD_TIME
        value: ${backend.URL}
      - key: VITE_PUSHER_PORT
        scope: BUILD_TIME
        value: "443"
      - key: VITE_PUSHER_SCHEME
        scope: BUILD_TIME
        value: "https"

  - name: backend
    source_dir: backend-laravel
    github:
      repo: your-username/irrigtrack
      branch: main
    build_command: composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link
    run_command: php artisan serve --host=0.0.0.0 --port=$PORT
    environment_slug: php
    instance_count: 1
    instance_size_slug: basic-xxs
    # Add database connection
    database: nia-db
    envs:
      # Database connection (auto-injected if using database component above)
      - key: DB_CONNECTION
        value: pgsql
      - key: DB_HOST
        value: ${nia-db.HOSTNAME}
      - key: DB_PORT
        value: ${nia-db.PORT}
      - key: DB_DATABASE
        value: ${nia-db.DATABASE}
      - key: DB_USERNAME
        value: ${nia-db.USERNAME}
      - key: DB_PASSWORD
        value: ${nia-db.PASSWORD}
      # App settings
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: "false"
      - key: APP_URL
        value: ${backend.URL}
      # Reverb settings (for WebSocket)
      - key: BROADCAST_DRIVER
        value: reverb
      - key: REVERB_APP_ID
        value: your-reverb-app-id  # Generate with: php artisan reverb:install
      - key: REVERB_APP_KEY
        value: your-reverb-app-key
      - key: REVERB_APP_SECRET
        value: your-reverb-app-secret
      - key: REVERB_HOST
        value: ${backend.URL}  # Your backend URL without https://
      - key: REVERB_PORT
        value: "443"
      - key: REVERB_SCHEME
        value: "https"
      # Queue settings (if using queues)
      - key: QUEUE_CONNECTION
        value: database  # or redis if you add Redis

  - name: python-api
    source_dir: .
    github:
      repo: your-username/irrigtrack
      branch: main
    build_command: pip install -r requirements.txt
    run_command: python ml_api_server.py
    environment_slug: python
    instance_count: 1
    instance_size_slug: basic-xxs
    # Add database connection for Python API
    database: nia-db
    envs:
      - key: DB_HOST
        value: ${nia-db.HOSTNAME}
      - key: DB_PORT
        value: ${nia-db.PORT}
      - key: DB_DATABASE
        value: ${nia-db.DATABASE}
      - key: DB_USERNAME
        value: ${nia-db.USERNAME}
      - key: DB_PASSWORD
        value: ${nia-db.PASSWORD}

  # Optional: Add queue worker for Laravel
  - name: queue-worker
    source_dir: backend-laravel
    github:
      repo: your-username/irrigtrack
      branch: main
    build_command: composer install --no-dev --optimize-autoloader
    run_command: php artisan queue:work --sleep=3 --tries=3
    environment_slug: php
    instance_count: 1
    instance_size_slug: basic-xxs
    database: nia-db
    envs:
      # Copy same env vars as backend
      - key: DB_CONNECTION
        value: pgsql
      - key: DB_HOST
        value: ${nia-db.HOSTNAME}
      # ... (same as backend)
```

### 3. Important Configuration Notes

#### Laravel Reverb on App Platform

App Platform supports WebSocket, but you need to:

1. **Run Reverb as a separate service** OR
2. **Use a process manager** to run both Laravel and Reverb

**Option A: Separate Reverb Service** (Recommended)

Add to `app.yaml`:

```yaml
  - name: reverb
    source_dir: backend-laravel
    github:
      repo: your-username/irrigtrack
      branch: main
    build_command: composer install --no-dev --optimize-autoloader
    run_command: php artisan reverb:start --host=0.0.0.0 --port=$PORT
    environment_slug: php
    instance_count: 1
    instance_size_slug: basic-xxs
    envs:
      # Same env vars as backend
      - key: REVERB_APP_ID
        value: ${backend.REVERB_APP_ID}
      # ... etc
```

**Option B: Use Supervisor/Process Manager**

Modify backend `run_command`:

```yaml
run_command: |
  php artisan reverb:start --host=0.0.0.0 --port=8080 &
  php artisan serve --host=0.0.0.0 --port=$PORT
```

#### File Storage (Laravel)

1. Create **DigitalOcean Spaces** bucket
2. Install `league/flysystem-aws-s3-v3`:
   ```bash
   composer require league/flysystem-aws-s3-v3
   ```
3. Update `config/filesystems.php` to use S3
4. Add Spaces credentials to backend env vars:
   ```yaml
   - key: AWS_ACCESS_KEY_ID
     value: your-spaces-key
   - key: AWS_SECRET_ACCESS_KEY
     value: your-spaces-secret
   - key: AWS_DEFAULT_REGION
     value: nyc3  # or your region
   - key: AWS_BUCKET
     value: your-bucket-name
   - key: AWS_ENDPOINT
     value: https://nyc3.digitaloceanspaces.com
   ```

### 4. Deployment Steps

1. **Push code to GitHub** (if not already)
2. **Go to DigitalOcean → App Platform → Create App**
3. **Connect GitHub repository**
4. **Choose "Deploy from GitHub"**
5. **Select your repository and branch**
6. **DigitalOcean will detect `app.yaml`** (or you can paste it)
7. **Review configuration** and adjust instance sizes
8. **Add managed PostgreSQL database** (or connect existing)
9. **Set environment variables** (especially Reverb keys)
10. **Deploy!**

### 5. Post-Deployment Checklist

- [ ] Run Laravel migrations: `php artisan migrate`
- [ ] Generate Reverb keys: `php artisan reverb:install` (if not done)
- [ ] Update frontend env vars with actual backend URLs
- [ ] Test WebSocket connection
- [ ] Set up Laravel storage link (if using local storage)
- [ ] Configure CORS if needed
- [ ] Set up monitoring/alerts

---

## If You Choose Droplets Instead

### Recommended Droplet Setup:

- **Size**: 4GB RAM, 2 vCPU ($24/month) or 8GB RAM, 4 vCPU ($48/month)
- **OS**: Ubuntu 22.04 LTS
- **Stack**: 
  - Nginx (reverse proxy)
  - PHP-FPM (for Laravel)
  - Node.js (for frontend build)
  - Python (for ML API)
  - PostgreSQL (self-managed or use managed DB)

### Setup Steps (Droplets):

1. Create droplet
2. Install Nginx, PHP, Node.js, Python, PostgreSQL
3. Set up SSL with Let's Encrypt
4. Configure Nginx virtual hosts
5. Set up systemd services for each app
6. Configure firewall
7. Set up automated backups
8. Configure monitoring

**Time Investment**: 4-8 hours for initial setup

---

## Final Recommendation

**Use App Platform** because:

1. ✅ You already have `app.yaml` configured
2. ✅ Multi-service architecture is easier on App Platform
3. ✅ Managed PostgreSQL = less maintenance
4. ✅ Automatic deployments from GitHub
5. ✅ Built-in SSL and scaling
6. ✅ Better for teams (less DevOps knowledge needed)

**Consider Droplets if**:
- You need very specific server configurations
- You want to minimize costs (single server)
- You have DevOps expertise and time

---

## Next Steps

1. Review and update your `app.yaml` with the configuration above
2. Create managed PostgreSQL database on DigitalOcean
3. Generate Reverb keys: `cd backend-laravel && php artisan reverb:install`
4. Set up DigitalOcean Spaces for file storage
5. Deploy via App Platform

Need help with any specific step? Let me know!
