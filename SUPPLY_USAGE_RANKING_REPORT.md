# Supply Usage Ranking Report

## Overview

The Supply Usage Ranking Report helps government agencies identify which supplies have the highest consumption rates. This is crucial for budget management and preventing budget overruns.

## Purpose

- **Budget Management**: Identify high-consumption items that may exceed allocated budgets
- **Insights for Government**: Provide data-driven insights for procurement planning
- **Cost Optimization**: Help identify opportunities for bulk purchasing or alternative suppliers
- **Trend Analysis**: Track usage trends (increasing/decreasing/stable) to anticipate future needs

## Features

### 1. **Ranking System**
- Ranks supplies by total usage, average usage, or recent usage
- Shows top N items (configurable: Top 10, 20, 50, or All)
- Displays comprehensive metrics for each supply item

### 2. **Key Metrics Displayed**
- **Total Usage**: Sum of all quarterly usage for the selected year
- **Average Usage**: Average usage per quarter
- **Recent Usage**: Usage in the most recent quarter
- **Trend**: Indicates if usage is increasing, decreasing, or stable
- **Quarters Count**: Number of quarters with data (out of 4)

### 3. **Visualizations**
- **Summary Cards**: Quick overview of total items, total usage, and average usage
- **Ranking Table**: Detailed table with all metrics and trends
- **Comparison Chart**: Bar chart comparing top 10 supplies by total usage
- **Budget Insights**: Highlights top 3 items requiring budget monitoring

### 4. **Filtering Options**
- **Year Selection**: View data for any year (2024 onwards)
- **Sort By**: Sort by Total Usage, Average Usage, or Recent Usage
- **Limit**: Show Top 10, 20, 50, or All items

### 5. **Export Functionality**
- Export report as CSV file
- Includes all ranking data for further analysis
- Filename includes year for easy organization

## How to Access

1. Navigate to **Reporting** page from the main menu
2. Click on **"Supply Usage Ranking"** card
3. Select year and filters
4. View the ranking report
5. Export if needed

## API Endpoint

**GET** `/api/v1/usage/ranking`

**Parameters:**
- `year` (optional): Year to analyze (default: current year)
- `sort_by` (optional): Sort field - `total_usage`, `avg_usage`, or `recent_usage` (default: `total_usage`)
- `limit` (optional): Number of items to return (default: 20)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "item_id": 72,
      "item": {
        "id": 72,
        "unit": "Bondpaper A4(size)",
        "description": "...",
        "category": "Supply"
      },
      "total_usage": 200,
      "avg_usage": 50.0,
      "recent_usage": 200,
      "max_usage": 200,
      "min_usage": 200,
      "quarters_count": 1,
      "trend": "stable",
      "usage_by_quarter": [...]
    }
  ],
  "summary": {
    "year": 2024,
    "total_items": 3,
    "total_usage_all": 284,
    "avg_usage_all": 94.67
  }
}
```

## Use Cases

### 1. **Budget Planning**
- Identify items with highest consumption
- Allocate budget accordingly
- Plan for bulk purchases

### 2. **Cost Control**
- Monitor items with increasing trends
- Take preventive measures before budget overruns
- Optimize procurement strategies

### 3. **Government Reporting**
- Generate reports for stakeholders
- Provide data-driven insights
- Support decision-making processes

### 4. **Supplier Management**
- Identify items that may need alternative suppliers
- Negotiate better prices for high-volume items
- Optimize supply chain

## Example Insights

The report highlights:
- **Top 3 Items**: Items requiring closest budget monitoring
- **Trend Indicators**: Items with increasing trends (red badge)
- **Usage Patterns**: Quarterly breakdown for each item
- **Comparison**: Visual comparison of top items

## Recommendations

Based on the report, government agencies can:
1. **Increase Budget Allocation** for high-consumption items
2. **Negotiate Bulk Pricing** for frequently used supplies
3. **Monitor Trends** closely for items showing increasing usage
4. **Optimize Inventory** by adjusting stock levels based on usage patterns
5. **Plan Procurement** in advance for predictable high-usage items

## Technical Details

- **Backend**: Laravel API endpoint (`UsageController@getSupplyUsageRanking`)
- **Frontend**: Vue.js component (`supply-usage-ranking.vue`)
- **Data Source**: `supply_usages` table
- **Filtering**: Only includes items with category containing "supply"
- **Sorting**: Server-side sorting by selected metric
- **Export**: CSV format with all ranking data

## Future Enhancements

Potential improvements:
- Add cost per unit to calculate total spending
- Include budget vs actual spending comparison
- Add forecasting for next period
- Generate PDF reports
- Add email scheduling for regular reports
- Include supplier information
- Add historical comparison (year-over-year)

