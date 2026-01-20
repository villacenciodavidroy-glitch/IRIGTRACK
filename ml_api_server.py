"""
Python ML API Server for Usage Forecasting
Provides Linear Regression forecasting for next quarter usage predictions
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
from sklearn.linear_model import LinearRegression
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import logging
import os
from catboost import CatBoostRegressor

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
# Enable CORS with specific configuration for frontend requests
CORS(app, 
     origins=["http://localhost:5174", "http://localhost:5173", "http://127.0.0.1:5174", "http://127.0.0.1:5173"],
     methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
     allow_headers=["Content-Type", "Authorization"],
     supports_credentials=True)

# Handle OPTIONS requests explicitly
@app.before_request
def handle_preflight():
    if request.method == "OPTIONS":
        response = jsonify({})
        response.headers.add("Access-Control-Allow-Origin", request.headers.get("Origin", "*"))
        response.headers.add('Access-Control-Allow-Headers', "Content-Type,Authorization")
        response.headers.add('Access-Control-Allow-Methods', "GET,PUT,POST,DELETE,OPTIONS")
        response.headers.add('Access-Control-Allow-Credentials', "true")
        return response

# Global variable to store the CatBoost model
lifespan_model = None
MODEL_PATH = os.getenv('LIFESPAN_MODEL_PATH', None)

def load_lifespan_model():
    """
    Load the CatBoost lifespan prediction model.
    Checks multiple locations for the model file.
    """
    global lifespan_model
    
    if lifespan_model is not None:
        return lifespan_model
    
    # Get current working directory and script directory
    current_dir = os.getcwd()
    script_dir = os.path.dirname(os.path.abspath(__file__))
    parent_dir = os.path.dirname(script_dir)
    
    # Possible model file locations (check multiple locations)
    # Priority: Same directory as script (most likely location)
    possible_paths = [
        os.path.join(script_dir, 'catboost_lifespan_model.cbm'),  # Same dir as script (HIGHEST PRIORITY)
        MODEL_PATH,  # Environment variable path
        os.path.join(current_dir, 'catboost_lifespan_model.cbm'),  # Current working directory
        'catboost_lifespan_model.cbm',  # Relative to current dir
        os.path.join(parent_dir, 'catboost_lifespan_model.cbm'),  # Parent directory
        os.path.join(script_dir, 'models', 'catboost_lifespan_model.cbm'),
        os.path.join(current_dir, 'models', 'catboost_lifespan_model.cbm'),
        'models/catboost_lifespan_model.cbm',
        'fastapi_lifespan_api/ml/models/catboost_lifespan_model.cbm',
    ]
    
    # Remove None values and duplicates
    possible_paths = [p for p in possible_paths if p]
    possible_paths = list(dict.fromkeys(possible_paths))  # Remove duplicates
    
    logger.info(f"🔍 Searching for CatBoost model file...")
    logger.info(f"   Current directory: {current_dir}")
    logger.info(f"   Script directory: {script_dir}")
    logger.info(f"   Parent directory: {parent_dir}")
    
    model_path = None
    checked_paths = []
    for path in possible_paths:
        checked_paths.append(path)
        if os.path.exists(path):
            model_path = os.path.abspath(path)  # Use absolute path
            logger.info(f"✅ Found model file at: {model_path}")
            break
    
    if model_path is None:
        logger.warning("⚠️ CatBoost model file not found. Falling back to manual calculation method.")
        logger.info(f"   Checked {len(checked_paths)} paths:")
        for i, path in enumerate(checked_paths, 1):
            exists = "✅" if os.path.exists(path) else "❌"
            logger.info(f"   {i}. {exists} {path}")
        return None
    
    try:
        logger.info(f"🐱 Loading CatBoost model from: {model_path}")
        logger.info(f"   File exists: {os.path.exists(model_path)}")
        logger.info(f"   File size: {os.path.getsize(model_path) / (1024*1024):.2f} MB")
        
        lifespan_model = CatBoostRegressor()
        lifespan_model.load_model(model_path)
        logger.info("✅ CatBoost model loaded successfully!")
        logger.info("✅ CatBoost IS RUNNING - Using ML predictions")
        return lifespan_model
    except ImportError as e:
        logger.error(f"❌ CatBoost library not installed: {str(e)}")
        logger.error("   Install with: pip install catboost")
        logger.warning("⚠️ Falling back to manual calculation method")
        return None
    except Exception as e:
        logger.error(f"❌ Failed to load CatBoost model: {str(e)}")
        logger.error(f"   Error type: {type(e).__name__}")
        import traceback
        logger.error(f"   Traceback: {traceback.format_exc()}")
        logger.warning("⚠️ Falling back to manual calculation method")
        return None

# Load model on module import (lazy loading - will load on first prediction request)
# Model will be loaded when first prediction is requested

def prepare_features_for_prediction(items):
    """
    Convert JSON items to DataFrame with proper feature engineering.
    One-hot encodes category and last_reason to match training data.
    
    Args:
        items: List of item dictionaries with fields:
            - item_id
            - category
            - years_in_use
            - maintenance_count
            - condition_number
            - condition_status (optional): Good, Less Reliable, Un-operational, Disposal
            - condition (optional): Serviceable, Non-Serviceable, On Maintenance
            - last_reason
    
    Returns:
        DataFrame with all features ready for model prediction
    """
    if not items:
        return pd.DataFrame()
    
    # Create base DataFrame from items
    df = pd.DataFrame(items)
    
    # Ensure required numeric columns exist and are properly typed
    numeric_cols = ['years_in_use', 'maintenance_count', 'condition_number']
    for col in numeric_cols:
        if col not in df.columns:
            df[col] = 0
        df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)
    
    # Handle category one-hot encoding
    # Get unique categories from data and create one-hot encoded columns
    if 'category' in df.columns:
        # Normalize category values (strip whitespace, handle case)
        df['category'] = df['category'].astype(str).str.strip()
        df['category'] = df['category'].fillna('Unknown')
        # Create one-hot encoded columns: category_Desktop, category_ICT, etc.
        category_dummies = pd.get_dummies(df['category'], prefix='category')
        # Drop original category column
        df = pd.concat([df.drop('category', axis=1), category_dummies], axis=1)
    else:
        df['category'] = 'Unknown'
        category_dummies = pd.get_dummies(df['category'], prefix='category')
        df = pd.concat([df.drop('category', axis=1), category_dummies], axis=1)
    
    # Handle last_reason one-hot encoding
    # Normalize last_reason values
    if 'last_reason' in df.columns:
        df['last_reason'] = df['last_reason'].astype(str).str.strip().str.lower()
        df['last_reason'] = df['last_reason'].fillna('other')
        # Replace empty strings with 'other'
        df['last_reason'] = df['last_reason'].replace('', 'other')
        # Create one-hot encoded columns: last_reason_Wet, last_reason_Electrical, etc.
        reason_dummies = pd.get_dummies(df['last_reason'], prefix='last_reason')
        # Drop original last_reason column
        df = pd.concat([df.drop('last_reason', axis=1), reason_dummies], axis=1)
    else:
        df['last_reason'] = 'other'
        reason_dummies = pd.get_dummies(df['last_reason'], prefix='last_reason')
        df = pd.concat([df.drop('last_reason', axis=1), reason_dummies], axis=1)
    
    # Handle condition_status one-hot encoding
    if 'condition_status' in df.columns:
        df['condition_status'] = df['condition_status'].astype(str).str.strip()
        df['condition_status'] = df['condition_status'].fillna('Unknown')
        condition_status_dummies = pd.get_dummies(df['condition_status'], prefix='condition_status')
        df = pd.concat([df.drop('condition_status', axis=1), condition_status_dummies], axis=1)
    else:
        df['condition_status'] = 'Unknown'
        condition_status_dummies = pd.get_dummies(df['condition_status'], prefix='condition_status')
        df = pd.concat([df.drop('condition_status', axis=1), condition_status_dummies], axis=1)
    
    # Handle condition one-hot encoding (Serviceable, Non-Serviceable, etc.)
    if 'condition' in df.columns:
        df['condition'] = df['condition'].astype(str).str.strip()
        df['condition'] = df['condition'].fillna('Unknown')
        condition_dummies = pd.get_dummies(df['condition'], prefix='condition')
        df = pd.concat([df.drop('condition', axis=1), condition_dummies], axis=1)
    else:
        df['condition'] = 'Unknown'
        condition_dummies = pd.get_dummies(df['condition'], prefix='condition')
        df = pd.concat([df.drop('condition', axis=1), condition_dummies], axis=1)
    
    # Remove item_id if present (not a feature)
    if 'item_id' in df.columns:
        item_ids = df['item_id'].copy()
        df = df.drop('item_id', axis=1)
    else:
        item_ids = None
    
    return df, item_ids

def align_features_with_model(df, model):
    """
    Ensure DataFrame has all features expected by the model.
    Fill missing columns with 0 and reorder columns to match model.
    
    Args:
        df: DataFrame with features
        model: CatBoost model
    
    Returns:
        DataFrame with all model features in correct order
    """
    if model is None:
        return df
    
    try:
        # Get feature names from model
        model_features = model.feature_names_
        if model_features is None:
            # Try to get from model attributes
            model_features = getattr(model, 'feature_names_', None)
    except:
        # If we can't get feature names, return df as-is
        logger.warning("Could not retrieve feature names from model")
        return df
    
    if model_features is None:
        logger.warning("Model feature names not available, using DataFrame as-is")
        return df
    
    # Create a new DataFrame with all model features
    aligned_df = pd.DataFrame(index=df.index)
    
    # Add all model features, filling with 0 if missing
    for feature in model_features:
        if feature in df.columns:
            aligned_df[feature] = df[feature]
        else:
            aligned_df[feature] = 0
            logger.debug(f"Missing feature '{feature}' in input data, filled with 0")
    
    # Ensure columns are in the same order as model expects
    aligned_df = aligned_df[model_features]
    
    return aligned_df

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'service': 'ML Forecast API',
        'version': '1.0.0',
        'endpoints': {
            'predict_consumables': '/predict/consumables/linear',
            'predict_lifespan': '/predict/items/lifespan',
            'health': '/health'
        }
    })

@app.route('/predict/consumables/linear', methods=['POST'])
def predict_consumables():
    """
    Predict next quarter usage using Linear Regression
    
    Expected request format:
    {
        "items": [
            {
                "item_id": 1,
                "name": "Paper",
                "historical_data": [
                    {
                        "period": "Q1 2024",
                        "timestamp": "2024-01-01",
                        "usage": 50,
                        ...
                    }
                ],
                "forecast_features": {...},
                "current_stock": 150
            }
        ]
    }
    """
    try:
        data = request.json
        if not data or 'items' not in data:
            return jsonify({
                'success': False,
                'error': 'Invalid request format. Expected "items" array.'
            }), 400
        
        items = data.get('items', [])
        logger.info(f"Received forecast request for {len(items)} items")
        
        forecasts = []
        
        for item in items:
            item_id = item.get('item_id')
            name = item.get('name', f'Item {item_id}')
            historical_data = item.get('historical_data', [])
            forecast_features = item.get('forecast_features', {})
            current_stock = item.get('current_stock', 0)
            
            if not historical_data:
                avg_usage = forecast_features.get('avg_usage_per_quarter', 0)
                logger.warning(f"No historical data for item {item_id} ({name}). Using average fallback: {round(avg_usage) if avg_usage else 0} units")
                logger.info(f"💡 To enable Linear Regression predictions: Add usage records (ItemUsage entries) for this item across multiple quarters")
                forecasts.append({
                    'item_id': item_id,
                    'name': name,
                    'predicted_usage': round(avg_usage) if avg_usage else 0,
                    'confidence': 0.3,
                    'method': 'average_fallback',
                    'note': 'No historical usage data available - using average fallback method'
                })
                continue
            
            # Extract usage values from historical data
            usage_values = []
            periods = []
            
            for idx, data_point in enumerate(historical_data):
                usage = data_point.get('usage', 0)
                if usage > 0:  # Only include non-zero usage
                    usage_values.append(usage)
                    periods.append(idx)
            
            # Need at least 2 data points for linear regression
            if len(usage_values) < 2:
                avg_usage = np.mean(usage_values) if usage_values else forecast_features.get('avg_usage_per_quarter', 0)
                logger.warning(f"Insufficient data points ({len(usage_values)}) for item {item_id} ({name}). Need at least 2 quarters of usage data for Linear Regression. Using average method: {round(avg_usage)} units")
                logger.info(f"💡 To get better predictions: Add usage records for at least 2 quarters (Q1-Q4) for item {item_id}")
                forecasts.append({
                    'item_id': item_id,
                    'name': name,
                    'predicted_usage': round(avg_usage),
                    'confidence': 0.3,
                    'method': 'average',
                    'note': f'Using average method (only {len(usage_values)} data point(s) available, need 2+ for Linear Regression)'
                })
                continue
            
            # Prepare data for Linear Regression
            X = np.array(periods).reshape(-1, 1)  # Time periods (independent variable)
            y = np.array(usage_values)  # Usage values (dependent variable)
            
            # Train Linear Regression model
            model = LinearRegression()
            model.fit(X, y)
            
            # Predict next quarter (next period)
            next_period = len(periods)
            predicted_usage = model.predict([[next_period]])[0]
            predicted_usage = max(0, round(predicted_usage))  # Ensure non-negative
            
            # Calculate R-squared (coefficient of determination) for confidence
            y_pred = model.predict(X)
            ss_residual = np.sum((y - y_pred) ** 2)
            ss_total = np.sum((y - np.mean(y)) ** 2)
            
            if ss_total > 0:
                r_squared = 1 - (ss_residual / ss_total)
            else:
                r_squared = 0
            
            # Convert R-squared to confidence (clamp between 0.3 and 0.95)
            confidence = max(0.3, min(0.95, abs(r_squared)))
            
            # Calculate potential shortage date (optional)
            shortage_date = None
            if predicted_usage > 0 and current_stock > 0:
                # Estimate days until stock runs out
                # Assumes usage is distributed evenly over 90 days (1 quarter)
                daily_usage_rate = predicted_usage / 90
                if daily_usage_rate > 0:
                    days_until_shortage = current_stock / daily_usage_rate
                    if days_until_shortage < 180:  # Only show if within 6 months
                        shortage_date_obj = datetime.now() + timedelta(days=int(days_until_shortage))
                        shortage_date = shortage_date_obj.strftime('%B %Y')
            
            # Get model parameters
            slope = model.coef_[0] if len(model.coef_) > 0 else 0
            intercept = model.intercept_ if hasattr(model, 'intercept_') else 0
            
            forecasts.append({
                'item_id': item_id,
                'name': name,
                'predicted_usage': int(predicted_usage),
                'shortage_date': shortage_date,
                'confidence': round(confidence, 2),
                'r_squared': round(r_squared, 4),
                'slope': round(slope, 2),
                'intercept': round(intercept, 2),
                'data_points': len(usage_values),
                'method': 'linear_regression'
            })
            
            # Enhanced logging for consumables predictions
            confidence_pct = f"{confidence:.1%}"
            shortage_info = f", potential shortage: {shortage_date}" if shortage_date else ""
            logger.info(f"Forecast for item {item_id} ({name}): {predicted_usage} units (confidence: {confidence_pct}, R²: {r_squared:.3f}, data points: {len(usage_values)}{shortage_info})")
        
        # Summary logging
        successful_forecasts = len([f for f in forecasts if f.get('method') == 'linear_regression'])
        fallback_forecasts = len([f for f in forecasts if f.get('method') in ['average', 'average_fallback']])
        logger.info(f"✅ Successfully generated forecasts for {len(forecasts)} items: {successful_forecasts} Linear Regression, {fallback_forecasts} Average-based")
        
        return jsonify({
            'success': True,
            'forecast': forecasts,
            'total_items': len(forecasts),
            'method': 'linear_regression'
        })
    
    except Exception as e:
        logger.error(f"Error generating forecasts: {str(e)}", exc_info=True)
        return jsonify({
            'success': False,
            'error': str(e),
            'message': 'Failed to generate forecasts'
        }), 500

@app.route('/predict/items/lifespan', methods=['POST'])
def predict_items_lifespan():
    """
    Predict remaining lifespan (in years) for items based on usage and maintenance
    
    Expected request format:
    {
        "items": [
            {
                "item_id": 1,
                "category": "Desktop",
                "years_in_use": 2.5,
                "maintenance_count": 1,
                "condition_number": 3,
                "condition_status": "Un-operational",
                "condition": "Non-Serviceable",
                "last_reason": "Wear"
            }
        ]
    }
    
    Returns:
    {
        "success": true,
        "predictions": [
            {
                "item_id": 1,
                "remaining_years": 5.2,
                "lifespan_estimate": 8.0
            }
        ]
    }
    """
    try:
        data = request.json
        if not data or 'items' not in data:
            return jsonify({
                'success': False,
                'error': 'Invalid request format. Expected "items" array.'
            }), 400
        
        items = data.get('items', [])
        logger.info(f"📊 Received lifespan prediction request for {len(items)} items")
        
        # Load model if not already loaded
        logger.info("=" * 60)
        logger.info("🐱 ATTEMPTING TO LOAD CATBOOST MODEL")
        logger.info("=" * 60)
        model = load_lifespan_model()
        
        # Try to use CatBoost model if available
        if model is not None:
            logger.info("=" * 60)
            logger.info("✅ CATBOOST MODEL LOADED - USING ML PREDICTIONS")
            logger.info("=" * 60)
            try:
                # Prepare features from items
                df, item_ids = prepare_features_for_prediction(items)
                
                if df.empty:
                    return jsonify({
                        'success': False,
                        'error': 'No valid items to process'
                    }), 400
                
                # Align features with model expectations
                df_aligned = align_features_with_model(df, model)
                
                # Make predictions
                remaining_years_predictions = model.predict(df_aligned)
                
                # Ensure predictions are in reasonable bounds (0.0 to 8 years)
                # Allow values below 0.5 to show items ending soon (≤30 days = 0.082 years)
                remaining_years_predictions = np.clip(remaining_years_predictions, 0.0, 8.0)
                
                # Build predictions response
                predictions = []
                for idx, item in enumerate(items):
                    item_id = item.get('item_id')
                    years_in_use = float(item.get('years_in_use', 0))
                    condition_number = item.get('condition_number', 0)
                    condition_number_str = str(condition_number).upper() if condition_number else ''
                    condition_status = item.get('condition_status', '')
                    condition = item.get('condition', '')
                    
                    # Check if item should be disposed (R condition number, Disposal status, or Non-Serviceable)
                    should_dispose = (
                        condition_number_str == 'R' or  # R = Disposal
                        condition_status == 'Disposal' or
                        'Non-Serviceable' in str(condition) or
                        'Non - Serviceable' in str(condition)
                    )
                    
                    if should_dispose:
                        # Item should be disposed - set remaining_years to 0
                        remaining_years = 0.0
                        logger.info(f"🗑️ Item {item_id} marked for DISPOSAL (R/Disposal/Non-Serviceable) - setting remaining_years to 0")
                    else:
                        remaining_years = float(remaining_years_predictions[idx])
                    
                    remaining_years = round(remaining_years, 1)
                    lifespan_estimate = round(years_in_use + remaining_years, 1)
                    
                    predictions.append({
                        'item_id': item_id,
                        'remaining_years': remaining_years,
                        'lifespan_estimate': lifespan_estimate,
                        'years_in_use': round(years_in_use, 1),
                        'method': 'catboost_model',
                        'disposal_flag': should_dispose
                    })
                
                logger.info(f"✅ Successfully generated {len(predictions)} predictions using CatBoost model")
                
                return jsonify({
                    'success': True,
                    'predictions': predictions,
                    'total_items': len(predictions),
                    'method': 'catboost_model'
                })
                
            except Exception as model_error:
                logger.error(f"CatBoost model prediction failed: {str(model_error)}", exc_info=True)
                logger.warning("Falling back to manual calculation method")
                # Fall through to manual calculation fallback
        
        # Using previous prediction method: Manual calculation (deterministic)
        logger.info("Using previous prediction method: Manual calculation (deterministic)")
        logger.info("Method: Base lifespan calculation with penalties based on maintenance and condition")
        predictions = []
        
        for item in items:
            item_id = item.get('item_id')
            category = item.get('category', 'Unknown')
            years_in_use = float(item.get('years_in_use', 0))
            maintenance_count = int(item.get('maintenance_count', 0))
            condition_number = item.get('condition_number', 0)
            condition_number_str = str(condition_number).upper() if condition_number else ''
            condition_status = item.get('condition_status', '')
            condition = item.get('condition', '')
            last_reason = item.get('last_reason', '').lower()
            
            # Check if item should be disposed (R condition number, Disposal status, or Non-Serviceable)
            should_dispose = (
                condition_number_str == 'R' or  # R = Disposal
                condition_status == 'Disposal' or
                'Non-Serviceable' in str(condition) or
                'Non - Serviceable' in str(condition)
            )
            
            if should_dispose:
                # Item should be disposed - set remaining_years to 0
                remaining_years = 0.0
                lifespan_estimate = round(years_in_use, 1)
                logger.info(f"🗑️ Item {item_id} marked for DISPOSAL (R/Disposal/Non-Serviceable) - setting remaining_years to 0")
                
                predictions.append({
                    'item_id': item_id,
                    'remaining_years': remaining_years,
                    'lifespan_estimate': lifespan_estimate,
                    'years_in_use': round(years_in_use, 1),
                    'method': 'manual_calculation_fallback',
                    'disposal_flag': True
                })
                continue
            
            # Calculate base remaining lifespan: 8 years max minus years in use
            base_lifespan = max(0.0, 8.0 - years_in_use)
            
            # Determine penalty based on maintenance_count, condition_number, and last_reason
            # Using deterministic calculations instead of random values for consistency
            penalty = 0.0
            
            # Base penalty calculation (deterministic based on item characteristics)
            if maintenance_count >= 2 or condition_number >= 4:
                # Deterministic base penalty: scales with maintenance count and condition
                # Base: 1.0 year for maintenance_count=2 or condition_number=4
                base_penalty = 1.0
                
                # Scale based on maintenance count (more maintenance = higher penalty)
                if maintenance_count >= 4:
                    base_penalty = 1.5  # Severe degradation
                elif maintenance_count >= 3:
                    base_penalty = 1.25  # Moderate-severe degradation
                elif maintenance_count >= 2:
                    base_penalty = 1.0  # Moderate degradation
                
                # Scale based on condition number (higher condition number = worse condition)
                condition_factor = max(0.0, (condition_number - 3) * 0.25) if condition_number >= 4 else 0.0
                base_penalty += condition_factor
                
                # Additional penalty multiplier based on maintenance reason severity
                reason_multiplier = 1.0
                if last_reason:
                    if 'wet' in last_reason or 'water' in last_reason:
                        reason_multiplier = 1.5  # Water damage is severe
                    elif 'electrical' in last_reason or 'short' in last_reason or 'circuit' in last_reason:
                        reason_multiplier = 1.4  # Electrical issues are serious
                    elif 'overheat' in last_reason or 'over heat' in last_reason or 'thermal' in last_reason:
                        reason_multiplier = 1.3  # Thermal stress is significant
                    elif 'wear' in last_reason or 'worn' in last_reason:
                        reason_multiplier = 1.1  # Normal wear is minor
                
                penalty = base_penalty * reason_multiplier
                
                # Additional cumulative penalties for multiple maintenance issues
                if maintenance_count >= 4:
                    penalty += 0.5  # Heavy maintenance history
                elif maintenance_count >= 3:
                    penalty += 0.3  # Moderate maintenance history
            
            # Calculate remaining lifespan with penalty
            remaining_years = base_lifespan - penalty
            # Allow values below 0.5 to show items ending soon (≤30 days = 0.082 years)
            remaining_years = np.clip(remaining_years, 0.0, 8.0)
            remaining_years = round(remaining_years, 1)
            lifespan_estimate = round(years_in_use + remaining_years, 1)
            
            predictions.append({
                'item_id': item_id,
                'remaining_years': remaining_years,
                'lifespan_estimate': lifespan_estimate,
                'years_in_use': round(years_in_use, 1),
                'method': 'manual_calculation_fallback',
                'disposal_flag': False
            })
            
            # Enhanced logging with prediction details
            status_indicator = ""
            if remaining_years <= 0.082:
                status_indicator = " [URGENT]"
            elif remaining_years <= 0.164:
                status_indicator = " [SOON]"
            elif remaining_years <= 0.5:
                status_indicator = " [MONITOR]"
            
            logger.info(f"Lifespan prediction for item {item_id}: {remaining_years} years remaining (method: manual, years_in_use: {years_in_use:.1f}, maintenance: {maintenance_count}, condition: {condition_number}){status_indicator}")
        
        logger.info(f"Successfully generated lifespan predictions for {len(predictions)} items")
        
        return jsonify({
            'success': True,
            'predictions': predictions,
            'total_items': len(predictions),
            'method': 'manual_calculation_fallback'
        })
    
    except Exception as e:
        logger.error(f"Error generating lifespan predictions: {str(e)}", exc_info=True)
        return jsonify({
            'success': False,
            'error': str(e),
            'message': 'Failed to generate lifespan predictions'
        }), 500

if __name__ == '__main__':
    # Pre-check CatBoost model availability on startup
    print("=" * 60)
    print("🚀 Starting ML Forecast API Server")
    print("=" * 60)
    print("🔍 Checking CatBoost model availability...")
    
    test_model = load_lifespan_model()
    if test_model is not None:
        print("✅ CatBoost model is READY - ML predictions will be used")
    else:
        print("⚠️ CatBoost model not found - Manual calculations will be used")
        print("   To enable CatBoost: Ensure catboost_lifespan_model.cbm exists")
        print("   in the same directory as ml_api_server.py")
    
    print("=" * 60)
    
    host = '0.0.0.0'
    port = 5000
    
    print('=' * 60)
    print('🚀 Starting ML Forecast API Server')
    print('=' * 60)
    print(f'📊 Service: Linear Regression Forecasting')
    print(f'🌐 Listening on: http://{host}:{port}')
    print(f'✅ Health check: GET http://{host}:{port}/health')
    print(f'🔮 Forecast endpoint: POST http://{host}:{port}/predict/consumables/linear')
    print(f'⏱️  Lifespan endpoint: POST http://{host}:{port}/predict/items/lifespan')
    print('=' * 60)
    print('Press Ctrl+C to stop the server')
    print()
    
    app.run(host=host, port=port, debug=True)

