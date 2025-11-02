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

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)  # Enable CORS for frontend requests

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
                        # Water damage is severe - reduce lifespan significantly
                        reason_multiplier = 1.5
                    elif 'electrical' in last_reason or 'short' in last_reason or 'circuit' in last_reason:
                        # Electrical issues are critical
                        reason_multiplier = 1.4
                    elif 'overheat' in last_reason or 'over heat' in last_reason or 'thermal' in last_reason:
                        # Overheating indicates serious problems
                        reason_multiplier = 1.3
                    elif 'wear' in last_reason or 'worn' in last_reason:
                        # Wear is expected over time, moderate impact
                        reason_multiplier = 1.1
                    # 'Other' or unknown reasons get no additional multiplier (1.0)
                
                # Apply the reason multiplier to the base penalty
                penalty = base_penalty * reason_multiplier
                
                # Also add a small base penalty if maintenance_count is high
                if maintenance_count >= 3:
                    penalty += 0.3
                elif maintenance_count >= 4:
                    penalty += 0.5
            
            # Calculate remaining lifespan with penalty
            remaining_years = base_lifespan - penalty
            
            # Clip between 0.5 and 8 years
            remaining_years = np.clip(remaining_years, 0.5, 8.0)
            
            # Round to 1 decimal place
            remaining_years = round(remaining_years, 1)
            
            # Total lifespan estimate (years_in_use + remaining_years)
            lifespan_estimate = round(years_in_use + remaining_years, 1)
            
            predictions.append({
                'item_id': item_id,
                'remaining_years': remaining_years,
                'lifespan_estimate': lifespan_estimate,
                'years_in_use': round(years_in_use, 1),
                'penalty_applied': round(penalty, 1) if penalty > 0 else 0.0,
                'last_reason': item.get('last_reason', '') if item.get('last_reason') else None
            })
            
            logger.info(f"Lifespan prediction for item {item_id}: {remaining_years} years remaining (penalty: {penalty:.1f}, reason: {item.get('last_reason', 'N/A')})")
        
        logger.info(f"Successfully generated lifespan predictions for {len(predictions)} items")
        
        return jsonify({
            'success': True,
            'predictions': predictions,
            'total_items': len(predictions),
            'method': 'lifespan_calculation'
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

