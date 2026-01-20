# DigitalOcean Deployment Checklist

## Pre-Deployment Setup

### 1. Generate Laravel Application Key
```bash
cd backend-laravel
php artisan key:generate
# Copy the generated APP_KEY value
```

### 2. Generate Laravel Reverb Keys
```bash
cd backend-laravel
php artisan reverb:install
# This will generate:
# - REVERB_APP_ID
# - REVERB_APP_KEY
# - REVERB_APP_SECRET
# Copy these values - you'll need them for app.yaml
```

### 3. Create Managed PostgreSQL Database

1. Go to **DigitalOcean Dashboard → Databases → Create Database**
2. Select **PostgreSQL** (version 15 or latest)
3. Choose region (same as your app)
4. Select plan:
   - **Development**: 1GB RAM, 1 vCPU, 10GB storage (~$15/month)
   - **Production**: 2GB+ RAM, 2+ vCPU (~$30+/month)
5. **Save connection details**:
   ```
   Host: xxx.db.ondigitalocean.com
   Port: 25060
   Database: defaultdb (or create new)
   Username: doadmin
   Password: [generated password]
   ```

### 4. Create DigitalOcean Spaces (Optional - for file storage)

1. Go to **DigitalOcean → Spaces → Create Space**
2. Choose region
3. Enable CDN (optional)
4. **Save credentials**:
   - Access Key
   - Secret Key
   - Endpoint URL
   - Bucket name

### 5. Update app.yaml

1. Copy `.do/app.yaml.production` to `.do/app.yaml`
2. Update these values:
   - `repo: your-username/irrigtrack` → Your actual GitHub repo
   - `APP_KEY` → Your generated Laravel key
   - `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` → Your Reverb keys
   - Database credentials (if not using database component)

---

## Deployment Steps

### Step 1: Push Code to GitHub
```bash
git add .
git commit -m "Prepare for DigitalOcean deployment"
git push origin main
```

### Step 2: Create App on DigitalOcean

1. Go to **DigitalOcean → App Platform → Create App**
2. Choose **GitHub** as source
3. Authorize DigitalOcean to access your GitHub
4. Select your repository: `irrigtrack`
5. Select branch: `main`
6. DigitalOcean will detect `.do/app.yaml` automatically
7. Review the configuration

### Step 3: Connect Managed PostgreSQL

1. In App Platform settings, go to **Components → Databases**
2. Click **Add Database**
3. Select your managed PostgreSQL database
4. DigitalOcean will automatically inject connection variables

**OR** manually set in environment variables:
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Step 4: Set Environment Variables

In App Platform, for each service, set:

**Backend Service:**
- `APP_KEY` (from step 1)
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (from step 2)
- Database credentials (if not auto-injected)

**Frontend Service:**
- Build-time variables are already in `app.yaml`
- No runtime variables needed

**Python API Service:**
- Database credentials (if not auto-injected)

**Reverb Service:**
- Same as backend (especially Reverb keys)

### Step 5: Run Database Migrations

After first deployment:

1. Go to **App Platform → Settings → Components → backend**
2. Click **Console** tab
3. Run:
   ```bash
   php artisan migrate
   php artisan db:seed  # If you have seeders
   ```

### Step 6: Verify Deployment

1. **Check all services are running:**
   - Frontend: Should show Vue.js app
   - Backend: Test API endpoint (e.g., `/api/v1/health`)
   - Python API: Test ML endpoint
   - Reverb: Check WebSocket connection

2. **Test WebSocket:**
   - Open browser console on frontend
   - Should see Echo connection established
   - Test real-time updates

3. **Check logs:**
   - App Platform → Runtime Logs
   - Check for errors

---

## Post-Deployment Configuration

### 1. Update Frontend URLs (if needed)

If frontend build-time variables didn't work:
1. Go to **Frontend service → Settings → Environment Variables**
2. Rebuild the app after updating

### 2. Configure CORS (if needed)

If you have CORS issues, update `backend-laravel/config/cors.php`:
```php
'allowed_origins' => [
    'https://your-frontend-url.ondigitalocean.app',
],
```

### 3. Set Up Custom Domain (Optional)

1. Go to **App Platform → Settings → Domains**
2. Add your custom domain
3. Update DNS records as instructed
4. SSL will be automatically configured

### 4. Set Up Monitoring (Recommended)

1. Enable **DigitalOcean Monitoring** in App Platform
2. Set up alerts for:
   - High CPU usage
   - High memory usage
   - Failed deployments
   - Database connection errors

### 5. Configure Backups

**Database Backups:**
- Managed PostgreSQL includes automatic daily backups
- Configure retention period in Database settings

**Application Backups:**
- App Platform automatically keeps deployment history
- Consider backing up `.env` files and important data

---

## Troubleshooting

### Issue: Database Connection Failed
- ✅ Check database credentials in environment variables
- ✅ Verify database firewall allows App Platform IPs
- ✅ Check database is running and accessible

### Issue: Reverb WebSocket Not Working
- ✅ Verify `REVERB_APP_KEY` matches in frontend and backend
- ✅ Check `REVERB_HOST` is set correctly (without https://)
- ✅ Ensure Reverb service is running
- ✅ Check browser console for WebSocket errors

### Issue: Frontend Can't Connect to Backend
- ✅ Verify `VITE_API_BASE_URL` is set correctly
- ✅ Check CORS configuration
- ✅ Verify backend URL is accessible

### Issue: Build Failures
- ✅ Check build logs in App Platform
- ✅ Verify all dependencies are in `package.json` / `composer.json`
- ✅ Check for missing environment variables at build time

### Issue: File Storage Not Working
- ✅ If using Spaces, verify credentials
- ✅ Check `storage` directory permissions
- ✅ Verify `storage:link` ran successfully

---

## Cost Optimization Tips

1. **Start Small**: Use `basic-xxs` for all services initially
2. **Monitor Usage**: Check App Platform metrics regularly
3. **Scale Down**: If services are idle, reduce instance sizes
4. **Use Database Connection Pooling**: Reduces database connections
5. **Enable CDN**: For static assets (included with Spaces)

---

## Next Steps After Deployment

- [ ] Set up CI/CD pipeline (automatic deployments on git push)
- [ ] Configure staging environment
- [ ] Set up error tracking (Sentry, Bugsnag, etc.)
- [ ] Configure logging aggregation
- [ ] Set up performance monitoring
- [ ] Create backup strategy
- [ ] Document API endpoints
- [ ] Set up API rate limiting

---

## Quick Reference: Important URLs

After deployment, you'll get URLs like:
- Frontend: `https://irrigtrack-frontend-xxx.ondigitalocean.app`
- Backend: `https://irrigtrack-backend-xxx.ondigitalocean.app`
- Python API: `https://irrigtrack-python-api-xxx.ondigitalocean.app`
- Reverb: `https://irrigtrack-reverb-xxx.ondigitalocean.app`

Save these URLs - you'll need them for:
- Frontend environment variables
- API documentation
- Testing
- Monitoring

---

## Support Resources

- **DigitalOcean Docs**: https://docs.digitalocean.com/products/app-platform/
- **Laravel Docs**: https://laravel.com/docs
- **Vue.js Docs**: https://vuejs.org/
- **DigitalOcean Community**: https://www.digitalocean.com/community
