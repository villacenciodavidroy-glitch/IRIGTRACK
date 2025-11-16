# ✅ Corrected Environment Variables for DigitalOcean

## Copy and Paste These Values

### Frontend Environment Variables (Build Time):

| Key | Value | Scope | Encrypt |
|-----|-------|-------|---------|
| `VITE_API_BASE_URL` | `https://irrigtrack-pse6h.ondigitalocean.app/api` | Build time | No |
| `VITE_PY_API_BASE_URL` | `https://irrigtrack-pse6h.ondigitalocean.app` | Build time | No |
| `VITE_PUSHER_APP_KEY` | `n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow` | Build time | No |
| `VITE_PUSHER_APP_CLUSTER` | `mt1` | Build time | No |
| `VITE_PUSHER_HOST` | `irrigtrack-pse6h.ondigitalocean.app` | Build time | No |
| `VITE_PUSHER_PORT` | `443` | Build time | No |

---

### Backend Environment Variables (Run Time):

| Key | Value | Scope | Encrypt |
|-----|-------|-------|---------|
| `APP_NAME` | `IrrigTrack` | Run and build time | No |
| `APP_ENV` | `production` | Run and build time | No |
| `APP_KEY` | `base64:vL5aWFDmojCJp3Czm7m8C8oQ9EdFkacA6dulMjeL6No=` | Run and build time | ✅ Yes |
| `APP_DEBUG` | `false` | Run and build time | No |
| `APP_URL` | `https://irrigtrack-pse6h.ondigitalocean.app` | Run and build time | No |
| `REVERB_APP_ID` | `ec317d18-4302-476f-a918-b356763ee514` | Run and build time | ✅ Yes |
| `REVERB_APP_KEY` | `n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow` | Run and build time | ✅ Yes |
| `REVERB_APP_SECRET` | `h9YEiVOZEHsge48GePeO-9M3xA02CCyw0Pyg8MVbmlRGTyVSHBKvJDvnjN2e6T8iLxeomSv2OY3RL8AsU3kLgQ` | Run and build time | ✅ Yes |
| `REVERB_HOST` | `irrigtrack-pse6h.ondigitalocean.app` | Run and build time | No |
| `REVERB_PORT` | `443` | Run and build time | No |
| `REVERB_SCHEME` | `https` | Run and build time | No |

---

## ⚠️ Critical Fixes:

1. **VITE_API_BASE_URL**: Remove trailing slash, add `/api`
   - ❌ `https://irrigtrack-pse6h.ondigitalocean.app/`
   - ✅ `https://irrigtrack-pse6h.ondigitalocean.app/api`

2. **VITE_PY_API_BASE_URL**: Remove trailing slash
   - ❌ `https://irrigtrack-pse6h.ondigitalocean.app/`
   - ✅ `https://irrigtrack-pse6h.ondigitalocean.app`

3. **VITE_PUSHER_HOST**: Remove `https://` and trailing slash
   - ❌ `https://irrigtrack-pse6h.ondigitalocean.app/`
   - ✅ `irrigtrack-pse6h.ondigitalocean.app`

4. **VITE_PUSHER_PORT**: Change from 8080 to 443
   - ❌ `8080`
   - ✅ `443`

5. **REVERB_HOST**: Remove the `REVERB_HOST=` prefix from value
   - ❌ Value: `REVERB_HOST=irrigtrack-api-abc123.ondigitalocean.app`
   - ✅ Value: `irrigtrack-pse6h.ondigitalocean.app`

6. **VITE_PUSHER_APP_KEY**: Use the Reverb key, not APP_KEY
   - ❌ `base64:vL5aWFDmojCJp3Czm7m8C8oQ9EdFkacA6dulMjeL6No=`
   - ✅ `n4ioFz9APjjEUgkY3Xa4WK_XU0LN3GlGX0_LobcBGow`

---

## Quick Action Items:

1. ✅ Go to DigitalOcean → Frontend App → Settings → Environment Variables
2. ✅ Update all VITE_* variables with corrected values above
3. ✅ Go to DigitalOcean → Backend App → Settings → Environment Variables  
4. ✅ Fix REVERB_HOST (remove prefix from value)
5. ✅ Make sure REVERB_APP_KEY matches VITE_PUSHER_APP_KEY
6. ✅ Redeploy both apps

