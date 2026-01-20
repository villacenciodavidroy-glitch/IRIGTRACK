"""
Quick test script to verify CatBoost model can be loaded
Run this to check if the model file is valid
"""

import os
import sys

print("=" * 60)
print("Testing CatBoost Model Loading")
print("=" * 60)
print()

# Check if catboost is installed
try:
    from catboost import CatBoostRegressor
    print("✅ CatBoost library is installed")
except ImportError as e:
    print(f"❌ CatBoost library not installed: {e}")
    print("   Install with: pip install catboost")
    sys.exit(1)

# Check model file location
script_dir = os.path.dirname(os.path.abspath(__file__))
model_path = os.path.join(script_dir, 'catboost_lifespan_model.cbm')

print(f"📁 Script directory: {script_dir}")
print(f"📁 Model file path: {model_path}")
print(f"📁 File exists: {os.path.exists(model_path)}")

if not os.path.exists(model_path):
    print()
    print("❌ Model file not found!")
    print(f"   Expected location: {model_path}")
    sys.exit(1)

# Check file size
file_size = os.path.getsize(model_path) / (1024 * 1024)
print(f"📊 File size: {file_size:.2f} MB")

# Try to load the model
print()
print("🔄 Attempting to load model...")
try:
    model = CatBoostRegressor()
    model.load_model(model_path)
    print("✅ Model loaded successfully!")
    print("✅ CatBoost model is VALID and can be used!")
    print()
    print("=" * 60)
    print("SUCCESS: Model file is ready to use!")
    print("=" * 60)
except Exception as e:
    print()
    print("❌ Failed to load model!")
    print(f"   Error: {e}")
    print(f"   Error type: {type(e).__name__}")
    import traceback
    print()
    print("Full traceback:")
    print(traceback.format_exc())
    sys.exit(1)

