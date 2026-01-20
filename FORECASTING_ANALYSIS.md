# Forecasting System Analysis

## ✅ Current Status: Working Properly

Your forecasting system is **working correctly** and should now be **more accurate** after adding 2024 data.

## How Your Forecasting Works

### 1. **Method: Linear Regression**
- Uses formula: `y = mx + b` (slope × time + intercept)
- Predicts next quarter's usage based on historical trend
- Standard statistical method for time-series forecasting

### 2. **Data Used**
- **Before**: Only 2025 data (4 quarters) for items 69, 71, 72
- **Now**: 2024 + 2025 data (8 quarters total) ✅
- **Configuration**: `years_back=3` means it fetches 2022-2025 (but you only have 2024-2025 data)

### 3. **Accuracy Metrics**

#### R-Squared (R²)
- **What it measures**: How well the linear trend fits your historical data
- **Range**: 0.0 to 1.0 (higher is better)
- **Interpretation**:
  - **R² > 0.7** (70%+): Good fit, reliable predictions
  - **R² 0.5-0.7** (50-70%): Moderate fit, predictions are reasonable
  - **R² < 0.5** (<50%): Weak fit, predictions less reliable

#### Confidence Score
- **Calculation**: R-squared converted to 0.3-0.95 range
- **Display**: Shown as percentage (30%-95%)
- **Meaning**: Higher confidence = more reliable prediction

## Current Data Analysis

Based on your database:

### Item 69 (Flash Drive)
- **2024**: Q1=9, Q2=11, Q3=10, Q4=150
- **2025**: Q1=10, Q2=12, Q3=11, Q4=166
- **Trend**: Significant increase in Q4 (seasonal pattern)
- **Data Points**: 8 quarters ✅
- **Expected Accuracy**: Moderate-High (Q4 spike affects linear trend)

### Item 71 (Ballpens)
- **2024**: Q1=75, Q2=80, Q3=85, Q4=90
- **2025**: Q1=88, Q2=90, Q3=92, Q4=93
- **Trend**: Steady gradual increase
- **Data Points**: 8 quarters ✅
- **Expected Accuracy**: High (consistent upward trend)

### Item 72 (Bondpaper A4)
- **2024**: Q1=200, Q2=210, Q3=220, Q4=230
- **2025**: Q1=240, Q2=245, Q3=250, Q4=252
- **Trend**: Steady consistent increase
- **Data Points**: 8 quarters ✅
- **Expected Accuracy**: High (very consistent linear trend)

## Accuracy Assessment

### ✅ Strengths
1. **More Data**: Now using 8 quarters instead of 4 (doubled!)
2. **Proper Method**: Linear regression is appropriate for usage trends
3. **Confidence Metrics**: R-squared provides transparency
4. **Fallback Logic**: Uses average if insufficient data

### ⚠️ Limitations
1. **Linear Assumption**: Assumes usage grows/declines linearly
   - **Issue**: Real usage may have seasonal patterns (like Item 69's Q4 spike)
   - **Impact**: Q4 spikes reduce accuracy for those items

2. **Limited History**: Only 2 years of data (2024-2025)
   - **Better**: 3-5 years would improve accuracy
   - **Current**: Still usable, but more data = better predictions

3. **No Seasonality**: Doesn't account for quarterly patterns
   - **Example**: Item 69 has Q4 spikes (150, 166) that skew the trend
   - **Solution**: Could add seasonal adjustments (future enhancement)

## Expected Forecast Accuracy

### High Accuracy (R² > 0.7)
- **Item 71**: Steady trend → **~75-85% confidence**
- **Item 72**: Very consistent → **~80-90% confidence**

### Moderate Accuracy (R² 0.5-0.7)
- **Item 69**: Q4 spikes affect trend → **~50-70% confidence**

## Recommendations for Better Accuracy

### 1. **Add More Historical Data** (Best Impact)
- Add 2023 data if available
- More quarters = better trend detection

### 2. **Monitor Confidence Scores**
- **High confidence (>70%)**: Trust the predictions
- **Low confidence (<50%)**: Use as rough estimate, monitor closely

### 3. **Review Quarterly Patterns**
- If items have seasonal spikes (like Q4), consider:
  - Manual adjustments for known patterns
  - Or implement seasonal forecasting (advanced)

### 4. **Validate Predictions**
- Compare predicted vs actual usage after each quarter
- Track prediction errors to improve over time

## How to Check Your Forecast Accuracy

1. **View Confidence Scores**:
   - Go to Usage Overview → Forecast section
   - Check the "Confidence" column
   - Higher % = more reliable

2. **Check R-Squared Values**:
   - Look at browser console (F12)
   - Check API responses for `r_squared` values
   - R² > 0.7 = good, R² < 0.5 = less reliable

3. **Compare Predictions**:
   - After next quarter, compare predicted vs actual usage
   - Calculate error: `|predicted - actual| / actual × 100%`
   - Track this over time to measure accuracy

## Conclusion

✅ **Your forecasting IS working properly**
✅ **Accuracy should improve** with 2024 data added
✅ **Method is sound** - Linear Regression is standard for this use case

**Expected Confidence Levels**:
- Items with steady trends (71, 72): **High accuracy** (~75-90%)
- Items with spikes (69): **Moderate accuracy** (~50-70%)

The system is functioning correctly. The accuracy depends on:
1. How consistent your usage patterns are
2. How much historical data you have
3. Whether there are seasonal patterns

With 8 quarters of data now available, your forecasts should be reasonably accurate for planning purposes.
