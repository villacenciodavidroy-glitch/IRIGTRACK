# How to Check if CatBoost is Running (Browser Console/Inspect)

## Method 1: Browser Console (Easiest) ✅

1. **Open your application** (e.g., Analytics page)
2. **Open Developer Tools**: Press `F12` or `Right-click → Inspect`
3. **Go to Console tab**
4. **Look for these logs** when predictions load:

### ✅ CatBoost IS Running:
```
📊 Prediction method: catboost_model
✅ Received 266 predictions from Python API
📊 Model Metrics: {method: "catboost_model", accuracy: ..., confidence: ...}
```

### ❌ CatBoost NOT Running (Fallback):
```
📊 Prediction method: manual_calculation_fallback
⚠️ CatBoost model disabled - using manual calculation method
```

---

## Method 2: Network Tab (Most Reliable) 🔍

1. **Open Developer Tools** → **Network tab**
2. **Filter by**: `Fetch/XHR` or search for `lifespan`
3. **Find the request**:
   - `calculate-lifespan-predictions` (Laravel endpoint)
   - OR `predict/items/lifespan` (Python API)
4. **Click the request** → **Response tab**
5. **Check the JSON response**:

### ✅ CatBoost IS Running:
```json
{
  "success": true,
  "method": "catboost_model", ← This confirms CatBoost!
  "predictions": [...],
  "total_predictions": 266
}
```

### ❌ CatBoost NOT Running:
```json
{
  "success": true,
  "method": "manual_calculation_fallback", ← Fallback method
  "predictions": [...]
}
```

---

## Method 3: Console Command (Quick Check) 💻

In the browser console, run:

```javascript
// Check if predictions are loaded
console.log('Predictions:', window.lifespanPredictions || 'Not available');

// Or check the API response directly
fetch('/api/v1/items/calculate-lifespan-predictions', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({})
})
.then(r => r.json())
.then(data => {
  console.log('Method:', data.method);
  console.log('CatBoost running?', data.method === 'catboost_model');
});
```

---

## Method 4: Check Individual Predictions 📊

In the console, check a prediction object:

```javascript
// If predictions are stored in a variable
lifespanPredictions[0]
// Look for:
{
  item_id: 1,
  remaining_years: 3.8,
  method: "catboost_model", ← CatBoost indicator
  lifespan_estimate: 6.3
}
```

---

## Visual Indicators in UI 🎨

The frontend also shows the method in the UI:
- **Analytics page** shows: "CatBoost model" badge when CatBoost is active
- **Prediction cards** display the method used

---

## Quick Test 🧪

**Fastest way to verify:**

1. Open Analytics page
2. Open Console (F12)
3. Look for: `📊 Prediction method: catboost_model`
4. ✅ If you see `catboost_model` → CatBoost is running!
5. ❌ If you see `manual_calculation_fallback` → Using fallback method

---

## Troubleshooting

### If CatBoost is NOT running:

1. **Check Python API is running:**
```bash
curl http://127.0.0.1:5000/health
```

2. **Check model file exists:**
```bash
ls -la /var/www/nia-system/catboost_lifespan_model.cbm
```

3. **Check Python API logs:**
```bash
tail -f /var/www/nia-system/ml_api_env/logs/ml-api.log
```

4. **Look for errors in console:**
   - Connection errors → Python API not running
   - Model loading errors → Model file missing or corrupted

