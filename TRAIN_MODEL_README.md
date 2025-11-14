# CatBoost Model Training Guide

This guide will help you train a CatBoost model for higher-accuracy lifespan predictions.

## Prerequisites

1. **Install Python packages:**
   
   **Windows (PowerShell):**
   ```powershell
   cd C:\Users\User\NIA_SystemProject
   .\backend-laravel\ml_api_env\Scripts\Activate.ps1
   pip install psycopg2-binary
   ```
   
   Or double-click `install_training_deps.bat`
   
   **Linux/Mac:**
   ```bash
   source ml_api_env/bin/activate
   pip install psycopg2-binary
   ```
   
   Note: Other packages (catboost, pandas, numpy, scikit-learn) are likely already installed from `requirements_ml_api.txt`

2. **Ensure you have training data:**
   - Items with `lifespan_estimate` or `remaining_years` populated
   - At least 20 items (100+ recommended for better accuracy)
   - Items should not be consumables (Supply category)

## Quick Start

### For Windows (PowerShell):

1. **Activate the virtual environment first:**
   ```powershell
   cd C:\Users\User\NIA_SystemProject
   .\backend-laravel\ml_api_env\Scripts\Activate.ps1
   ```

2. **Run the training script:**
   ```powershell
   python train_lifespan_model.py
   ```

   Or simply double-click `train_model.bat` (recommended for Windows)

### For Windows (Command Prompt):

1. **Double-click `train_model.bat`** - it will activate the environment and run training automatically

### For Linux/Mac:

1. **Activate virtual environment:**
   ```bash
   source ml_api_env/bin/activate
   ```

2. **Run training:**
   ```bash
   python train_lifespan_model.py
   ```

2. **The script will:**
   - Connect to your PostgreSQL database
   - Extract items with lifespan predictions
   - Train a CatBoost model
   - Save it as `catboost_lifespan_model.cbm`
   - Show performance metrics

3. **Move the model file:**
   - Copy `catboost_lifespan_model.cbm` to the same directory as `ml_api_server.py`
   - Or place it in `models/` subdirectory
   - Or set `LIFESPAN_MODEL_PATH` environment variable

4. **Restart your Python ML API server:**
   ```bash
   # Stop current server (Ctrl+C)
   # Start again
   python ml_api_server.py
   ```

## Configuration

If your database credentials are different, update them in `train_lifespan_model.py`:

```python
DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_PORT = os.getenv('DB_PORT', '5432')
DB_NAME = os.getenv('DB_DATABASE', 'nia_db')
DB_USER = os.getenv('DB_USERNAME', 'postgres')
DB_PASSWORD = os.getenv('DB_PASSWORD', '100676')
```

Or set environment variables:
```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=nia_db
export DB_USERNAME=postgres
export DB_PASSWORD=100676
```

## Understanding the Results

After training, you'll see:

- **MAE (Mean Absolute Error):** Average prediction error in years (lower is better)
- **RMSE (Root Mean Squared Error):** Penalizes larger errors more (lower is better)
- **R² Score:** How well the model explains variance (higher is better, max 1.0)
- **Feature Importance:** Which features the model considers most important

## Improving Accuracy Over Time

1. **Initial Training:** Uses current predictions (manual method results)
2. **Collect Real Outcomes:** As items reach end of life, record actual lifespans
3. **Retrain Periodically:** Run training again with more accurate data
4. **Better Model:** More real outcomes = better predictions

## Troubleshooting

### "Failed to connect to database"
- Check PostgreSQL is running
- Verify database credentials
- Ensure database exists

### "Not enough valid samples"
- Run predictions first to populate `lifespan_estimate` or `remaining_years`
- Need at least 20 items with predictions

### "No training data found"
- Make sure items have `lifespan_estimate` or `remaining_years` set
- Exclude consumable items (they're filtered automatically)

## Model File Location

The trained model (`catboost_lifespan_model.cbm`) should be placed where `ml_api_server.py` can find it:

- Same directory as `ml_api_server.py` ✅ (Recommended)
- `models/catboost_lifespan_model.cbm` ✅
- Path specified in `LIFESPAN_MODEL_PATH` environment variable ✅

## Verification

After placing the model file, check your Python server logs. You should see:
```
✅ CatBoost model loaded successfully
```

Instead of:
```
⚠️ CatBoost model file not found
```

---

**Good luck with training! 🚀**

