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
    
    # Possible model file locations
    possible_paths = [
        MODEL_PATH,  # Environment variable path
        'catboost_lifespan_model.cbm',
        'models/catboost_lifespan_model.cbm',
        'fastapi_lifespan_api/ml/models/catboost_lifespan_model.cbm',
        os.path.join(os.path.dirname(__file__), 'catboost_lifespan_model.cbm'),
        os.path.join(os.path.dirname(__file__), 'models', 'catboost_lifespan_model.cbm'),
    ]
    
    model_path = None
    for path in possible_paths:
        if path and os.path.exists(path):
            model_path = path
            break
    
    if model_path is None:
        logger.warning("CatBoost model file not found. Model-based predictions will not be available.")
        logger.info(f"Checked paths: {possible_paths}")
        return None
    
    try:
        logger.info(f"Loading CatBoost model from: {model_path}")
        lifespan_model = CatBoostRegressor()
        lifespan_model.load_model(model_path)
        logger.info("✅ CatBoost model loaded successfully")
        return lifespan_model
    except Exception as e:
        logger.error(f"Failed to load CatBoost model: {str(e)}")
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
                logger.warning(f"No historical data for item {item_id}, using fallback")
                avg_usage = forecast_features.get('avg_usage_per_quarter', 0)
                forecasts.append({
                    'item_id': item_id,
                    'name': name,
                    'predicted_usage': round(avg_usage) if avg_usage else 0,
                    'confidence': 0.3,
                    'method': 'average_fallback'
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
                logger.warning(f"Insufficient data points ({len(usage_values)}) for item {item_id}")
                avg_usage = np.mean(usage_values) if usage_values else forecast_features.get('avg_usage_per_quarter', 0)
                forecasts.append({
                    'item_id': item_id,
                    'name': name,
                    'predicted_usage': round(avg_usage),
                    'confidence': 0.3,
                    'method': 'average'
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
            
            logger.info(f"Forecast for item {item_id} ({name}): {predicted_usage} units (confidence: {confidence:.2%})")
        
        logger.info(f"Successfully generated forecasts for {len(forecasts)} items")
        
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
        logger.info(f"Received lifespan prediction request for {len(items)} items")
        
        # Load model if not already loaded
        model = load_lifespan_model()
        
        # Try to use CatBoost model if available
        if model is not None:
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
                    remaining_years = float(remaining_years_predictions[idx])
                    remaining_years = round(remaining_years, 1)
                    lifespan_estimate = round(years_in_use + remaining_years, 1)
                    
                    predictions.append({
                        'item_id': item_id,
                        'remaining_years': remaining_years,
                        'lifespan_estimate': lifespan_estimate,
                        'years_in_use': round(years_in_use, 1),
                        'method': 'catboost_model'
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
        
        # Fallback: Manual calculation if model not available or prediction failed
        logger.info("Using manual calculation method (model not available or failed)")
        predictions = []
        
        for item in items:
            item_id = item.get('item_id')
            category = item.get('category', 'Unknown')
            years_in_use = float(item.get('years_in_use', 0))
            maintenance_count = int(item.get('maintenance_count', 0))
            condition_number = int(item.get('condition_number', 0))
            last_reason = item.get('last_reason', '').lower()
            
            # Calculate base remaining lifespan: 8 years max minus years in use
            base_lifespan = 8.0 - years_in_use
            
            # Determine penalty based on maintenance_count, condition_number, and last_reason
            penalty = 0.0
            
            # Base penalty if maintenance_count >= 2 OR condition_number >= 4
            if maintenance_count >= 2 or condition_number >= 4:
                # Random penalty between 0.5 and 1.5 years
                base_penalty = np.random.uniform(0.5, 1.5)
                penalty = base_penalty
                
                # Additional penalty multiplier based on maintenance reason severity
                reason_multiplier = 1.0
                if last_reason:
                    if 'wet' in last_reason or 'water' in last_reason:
                        reason_multiplier = 1.5
                    elif 'electrical' in last_reason or 'short' in last_reason or 'circuit' in last_reason:
                        reason_multiplier = 1.4
                    elif 'overheat' in last_reason or 'over heat' in last_reason or 'thermal' in last_reason:
                        reason_multiplier = 1.3
                    elif 'wear' in last_reason or 'worn' in last_reason:
                        reason_multiplier = 1.1
                
                penalty = base_penalty * reason_multiplier
                
                if maintenance_count >= 3:
                    penalty += 0.3
                elif maintenance_count >= 4:
                    penalty += 0.5
            
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
                'method': 'manual_calculation_fallback'
            })
            
            logger.info(f"Lifespan prediction for item {item_id}: {remaining_years} years remaining (method: manual)")
        
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

