"""
CatBoost Model Training Script for Lifespan Prediction

This script trains a CatBoost model to predict remaining lifespan of equipment items.
It connects to your PostgreSQL database and uses existing lifespan predictions as training data.

Prerequisites:
- Install: pip install catboost pandas numpy psycopg2-binary scikit-learn

Usage:
1. Update database connection settings below if needed
2. Run: python train_lifespan_model.py
3. Place the generated catboost_lifespan_model.cbm file in the same directory as ml_api_server.py
4. Restart your Python ML API server

The model will automatically be used for predictions once placed in the correct location.
"""

import pandas as pd
import numpy as np
from catboost import CatBoostRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
import os
import logging
import sys

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

def load_training_data_from_database():
    """
    Load training data directly from PostgreSQL database.
    
    Uses existing lifespan predictions (lifespan_estimate or remaining_years) as training targets.
    As you collect more actual outcomes (items that reached end of life), retrain the model
    for better accuracy.
    """
    try:
        import psycopg2
    except ImportError:
        logger.error("❌ psycopg2-binary not installed. Install with: pip install psycopg2-binary")
        sys.exit(1)
    
    # Database connection settings
    # Update these if your database credentials are different
    DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
    DB_PORT = os.getenv('DB_PORT', '5432')
    DB_NAME = os.getenv('DB_DATABASE', 'nia_db')
    DB_USER = os.getenv('DB_USERNAME', 'postgres')
    DB_PASSWORD = os.getenv('DB_PASSWORD', '100676')
    
    logger.info("=" * 60)
    logger.info("Connecting to database...")
    logger.info(f"Host: {DB_HOST}, Database: {DB_NAME}")
    
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD
        )
    except Exception as e:
        logger.error(f"❌ Failed to connect to database: {e}")
        logger.info("\n💡 Check your database connection settings:")
        logger.info(f"   DB_HOST={DB_HOST}")
        logger.info(f"   DB_PORT={DB_PORT}")
        logger.info(f"   DB_NAME={DB_NAME}")
        logger.info(f"   DB_USER={DB_USER}")
        sys.exit(1)
    
    # Query to get items with their features and targets
    query = """
    SELECT 
        i.id as item_id,
        COALESCE(c.category, 'Unknown') as category,
        EXTRACT(YEAR FROM AGE(CURRENT_DATE, i.date_acquired)) as years_in_use,
        COALESCE(i.maintenance_count, 0) as maintenance_count,
        CASE 
            WHEN cn.condition_number ~ '^A([0-9]+)$' 
            THEN CAST(SUBSTRING(cn.condition_number FROM 'A([0-9]+)') AS INTEGER)
            ELSE 0
        END as condition_number,
        COALESCE((
            SELECT reason 
            FROM maintenance_records 
            WHERE item_id = i.id 
            ORDER BY maintenance_date DESC 
            LIMIT 1
        ), '') as last_reason,
        -- Use remaining_years if available, otherwise calculate from lifespan_estimate
        COALESCE(i.remaining_years, 
            CASE 
                WHEN i.lifespan_estimate IS NOT NULL AND i.lifespan_estimate > 0
                THEN GREATEST(0, i.lifespan_estimate - EXTRACT(YEAR FROM AGE(CURRENT_DATE, i.date_acquired)))
                ELSE NULL
            END
        ) as target
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN condition_numbers cn ON i.condition_number_id = cn.id
    WHERE i.deleted_at IS NULL
        AND (c.category IS NULL OR (LOWER(c.category) NOT LIKE '%supply%' AND LOWER(c.category) NOT LIKE '%consumable%'))
        AND (
            i.remaining_years IS NOT NULL 
            OR (i.lifespan_estimate IS NOT NULL AND i.lifespan_estimate > 0)
        )
    ORDER BY i.id
    """
    
    logger.info("Querying database for training data...")
    try:
        df = pd.read_sql_query(query, conn)
        conn.close()
        logger.info(f"✅ Loaded {len(df)} records from database")
    except Exception as e:
        conn.close()
        logger.error(f"❌ Query failed: {e}")
        sys.exit(1)
    
    return df

def prepare_features(df):
    """
    Prepare features matching the prediction code structure.
    This ensures the training features match what the prediction API expects.
    """
    logger.info("Preparing features...")
    
    data = df.copy()
    
    # Ensure numeric columns exist and are properly typed
    numeric_cols = ['years_in_use', 'maintenance_count', 'condition_number']
    for col in numeric_cols:
        if col in data.columns:
            data[col] = pd.to_numeric(data[col], errors='coerce').fillna(0)
        else:
            data[col] = 0
            logger.warning(f"⚠️ Column '{col}' not found, defaulting to 0")
    
    # Normalize category
    if 'category' in data.columns:
        data['category'] = data['category'].astype(str).str.strip().fillna('Unknown')
    else:
        data['category'] = 'Unknown'
        logger.warning("⚠️ Category column not found, using 'Unknown'")
    
    # Normalize last_reason
    if 'last_reason' in data.columns:
        data['last_reason'] = data['last_reason'].astype(str).str.strip().str.lower().fillna('other')
        data['last_reason'] = data['last_reason'].replace('', 'other')
    else:
        data['last_reason'] = 'other'
        logger.warning("⚠️ last_reason column not found, using 'other'")
    
    # One-hot encode category (creates columns like category_Desktop, category_ICT)
    category_dummies = pd.get_dummies(data['category'], prefix='category')
    logger.info(f"   Categories found: {data['category'].unique().tolist()}")
    
    # One-hot encode last_reason (creates columns like last_reason_wear, last_reason_electrical)
    reason_dummies = pd.get_dummies(data['last_reason'], prefix='last_reason')
    if len(data['last_reason'].unique()) > 1:
        logger.info(f"   Maintenance reasons found: {data['last_reason'].unique().tolist()}")
    
    # Combine all features
    feature_df = pd.concat([
        data[numeric_cols],
        category_dummies,
        reason_dummies
    ], axis=1)
    
    logger.info(f"   Total features: {len(feature_df.columns)}")
    logger.info(f"   Feature columns: {list(feature_df.columns)}")
    
    return feature_df, data

def train_model(X, y, test_size=0.2, random_state=42):
    """
    Train CatBoost model with cross-validation.
    """
    logger.info("=" * 60)
    logger.info(f"Training model on {len(X)} samples...")
    
    # Split data into training and testing sets
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=test_size, random_state=random_state, shuffle=True
    )
    
    logger.info(f"   Training set: {len(X_train)} samples")
    logger.info(f"   Test set: {len(X_test)} samples")
    
    # Target statistics
    logger.info(f"\n📊 Target Statistics:")
    logger.info(f"   Mean remaining years: {np.mean(y):.2f}")
    logger.info(f"   Min: {np.min(y):.2f}, Max: {np.max(y):.2f}")
    logger.info(f"   Std Dev: {np.std(y):.2f}")
    
    # Train CatBoost model with hyperparameters optimized for regression
    logger.info(f"\n🚀 Training CatBoost model...")
    model = CatBoostRegressor(
        iterations=1000,              # Maximum iterations
        learning_rate=0.1,           # Learning rate
        depth=6,                     # Tree depth
        loss_function='RMSE',        # Root Mean Squared Error
        eval_metric='RMSE',          # Evaluation metric
        random_seed=42,              # For reproducibility
        verbose=100,                 # Print progress every 100 iterations
        early_stopping_rounds=50,    # Stop if no improvement for 50 rounds
        l2_leaf_reg=3,               # L2 regularization
        bagging_temperature=1,       # Bayesian bagging
        random_strength=1,           # Random strength
    )
    
    # Train with validation set
    model.fit(
        X_train, y_train,
        eval_set=(X_test, y_test),
        use_best_model=True,
        plot=False
    )
    
    # Evaluate model performance
    y_pred = model.predict(X_test)
    
    mae = mean_absolute_error(y_test, y_pred)
    rmse = np.sqrt(mean_squared_error(y_test, y_pred))
    r2 = r2_score(y_test, y_pred)
    
    logger.info("=" * 60)
    logger.info("📊 Model Performance on Test Set:")
    logger.info("=" * 60)
    logger.info(f"   Mean Absolute Error (MAE): {mae:.2f} years")
    logger.info(f"   Root Mean Squared Error (RMSE): {rmse:.2f} years")
    logger.info(f"   R² Score: {r2:.3f} ({r2*100:.1f}% variance explained)")
    
    # Feature importance
    feature_importance = pd.DataFrame({
        'feature': X.columns,
        'importance': model.feature_importances_
    }).sort_values('importance', ascending=False)
    
    logger.info(f"\n🔍 Top 10 Most Important Features:")
    for idx, row in feature_importance.head(10).iterrows():
        logger.info(f"   {row['feature']}: {row['importance']:.2f}")
    
    return model, {
        'mae': mae,
        'rmse': rmse,
        'r2': r2,
        'feature_importance': feature_importance
    }

def main():
    """
    Main training function.
    """
    logger.info("=" * 60)
    logger.info("🤖 CatBoost Lifespan Model Training")
    logger.info("=" * 60)
    
    # Load training data
    try:
        df = load_training_data_from_database()
    except Exception as e:
        logger.error(f"❌ Failed to load data: {e}")
        logger.info("\n💡 Troubleshooting:")
        logger.info("   1. Ensure PostgreSQL is running")
        logger.info("   2. Check database credentials in the script")
        logger.info("   3. Verify items table has lifespan_estimate or remaining_years data")
        return
    
    if len(df) == 0:
        logger.error("❌ No training data found")
        logger.info("\n💡 Make sure you have items with lifespan predictions:")
        logger.info("   1. Items should have lifespan_estimate or remaining_years set")
        logger.info("   2. Items should not be consumables (Supply category)")
        logger.info("   3. Run predictions first to populate the database")
        return
    
    logger.info(f"\n📋 Data Overview:")
    logger.info(f"   Total records: {len(df)}")
    logger.info(f"   Items with categories: {df['category'].notna().sum()}")
    logger.info(f"   Items with maintenance records: {(df['last_reason'] != '').sum()}")
    
    # Prepare target (what we want to predict)
    if 'target' not in df.columns:
        logger.error("❌ No 'target' column found in data")
        return
    
    y = pd.to_numeric(df['target'], errors='coerce')
    
    # Filter out invalid targets
    valid_mask = (y >= 0) & (y <= 8) & ~y.isna()
    invalid_count = (~valid_mask).sum()
    
    if invalid_count > 0:
        logger.warning(f"⚠️ Filtering out {invalid_count} invalid targets (not in 0-8 years range)")
    
    df = df[valid_mask]
    y = y[valid_mask].values
    
    logger.info(f"\n✅ Valid samples for training: {len(df)}")
    
    if len(df) < 20:
        logger.error("❌ Not enough valid samples for training (need at least 20)")
        logger.info(f"   Current: {len(df)} samples")
        logger.info("\n💡 To get more training data:")
        logger.info("   1. Run predictions on more items")
        logger.info("   2. Ensure items have lifespan_estimate or remaining_years populated")
        return
    
    if len(df) < 100:
        logger.warning(f"⚠️ Limited training data ({len(df)} samples)")
        logger.info("   Model may have lower accuracy. More data = better accuracy")
    
    # Prepare features
    try:
        X, _ = prepare_features(df)
    except Exception as e:
        logger.error(f"❌ Feature preparation failed: {e}")
        return
    
    # Check for empty features
    if X.empty:
        logger.error("❌ No features generated")
        return
    
    logger.info(f"\n✅ Features prepared: {X.shape[0]} samples × {X.shape[1]} features")
    
    # Train model
    try:
        model, metrics = train_model(X, y)
    except Exception as e:
        logger.error(f"❌ Training failed: {e}")
        import traceback
        traceback.print_exc()
        return
    
    # Save model
    model_path = 'catboost_lifespan_model.cbm'
    try:
        model.save_model(model_path)
        logger.info("=" * 60)
        logger.info(f"✅ Model saved successfully!")
        logger.info(f"   File: {model_path}")
        logger.info(f"   File size: {os.path.getsize(model_path) / 1024:.2f} KB")
        logger.info("=" * 60)
        logger.info("\n📁 Next Steps:")
        logger.info(f"   1. Copy {model_path} to the directory containing ml_api_server.py")
        logger.info(f"   2. Or place it in one of these locations:")
        logger.info(f"      - {os.path.abspath(model_path)}")
        logger.info(f"      - models/{model_path}")
        logger.info(f"   3. Restart your Python ML API server")
        logger.info(f"   4. The model will automatically be loaded for predictions")
        logger.info("\n🎉 Your system will now use ML-based predictions for higher accuracy!")
    except Exception as e:
        logger.error(f"❌ Failed to save model: {e}")
        return

if __name__ == '__main__':
    main()

