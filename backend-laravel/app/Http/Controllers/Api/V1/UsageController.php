<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ItemUsage;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    /**
     * Get quarterly usage data grouped by period and item
     *
     * @return JsonResponse
     */
    public function getQuarterlyUsage(): JsonResponse
    {
        try {
            $currentYear = now()->year;
            
            // Get all quarters for current year
            $quarters = [
                "Q1 $currentYear",
                "Q2 $currentYear",
                "Q3 $currentYear",
                "Q4 $currentYear"
            ];
            
            // Initialize grouped data structure
            $groupedData = [];
            foreach ($quarters as $quarter) {
                $groupedData[$quarter] = [];
            }
            
            // Check if table exists
            try {
                $tableExists = DB::select("SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = 'supply_usages'
                )")[0]->exists ?? false;
                
                if (!$tableExists) {
                    \Log::warning('supply_usages table does not exist');
                    return response()->json([
                        'success' => true,
                        'data' => $groupedData,
                        'current_period' => ItemUsage::getCurrentPeriod(),
                        'current_year' => $currentYear,
                        'message' => 'Table does not exist yet. Run migration first.'
                    ], 200);
                }
            } catch (\Exception $e) {
                \Log::error('Error checking table existence: ' . $e->getMessage());
            }
            
            // Get quarterly usage data - using a simpler approach
            try {
                $usageRecords = ItemUsage::with('item')
                    ->whereIn('period', $quarters)
                    ->get();
                
                // Group and aggregate manually
                $aggregated = [];
                foreach ($usageRecords as $record) {
                    $key = $record->period . '_' . $record->item_id;
                    
                    if (!isset($aggregated[$key])) {
                        $aggregated[$key] = [
                            'period' => $record->period,
                            'item_id' => $record->item_id,
                            'total_usage' => 0,
                            'total_restocked' => 0,
                            'stock_start' => null,
                            'stock_end' => null,
                            'restocked' => false,
                            'item' => $record->item
                        ];
                    }
                    
                    $aggregated[$key]['total_usage'] += $record->usage ?? 0;
                    $aggregated[$key]['total_restocked'] += $record->restock_qty ?? 0;
                    
                    if ($record->restocked) {
                        $aggregated[$key]['restocked'] = true;
                    }
                    
                    // Keep the latest stock_end
                    if ($record->stock_end !== null) {
                        if ($aggregated[$key]['stock_end'] === null || $record->stock_end > $aggregated[$key]['stock_end']) {
                            $aggregated[$key]['stock_end'] = $record->stock_end;
                        }
                    }
                    
                    // Keep the earliest stock_start
                    if ($record->stock_start !== null) {
                        if ($aggregated[$key]['stock_start'] === null || $record->stock_start < $aggregated[$key]['stock_start']) {
                            $aggregated[$key]['stock_start'] = $record->stock_start;
                        }
                    }
                }
                
                // Populate grouped data
                foreach ($aggregated as $data) {
                    $period = $data['period'];
                    if (isset($groupedData[$period])) {
                        $groupedData[$period][] = [
                            'item_id' => $data['item_id'],
                            'item' => $data['item'] ? [
                                'id' => $data['item']->id,
                                'unit' => $data['item']->unit,
                                'description' => $data['item']->description,
                            ] : null,
                            'total_usage' => (int)$data['total_usage'],
                            'total_restocked' => (int)$data['total_restocked'],
                            'stock_start' => $data['stock_start'],
                            'stock_end' => $data['stock_end'],
                            'restocked' => $data['restocked'],
                        ];
                    }
                }
                
            } catch (\Exception $queryError) {
                \Log::error('Error querying usage data: ' . $queryError->getMessage());
                \Log::error('Query error trace: ' . $queryError->getTraceAsString());
                // Return empty structure instead of failing
            }
            
            // Get current period
            $currentPeriod = ItemUsage::getCurrentPeriod();
            
            return response()->json([
                'success' => true,
                'data' => $groupedData,
                'current_period' => $currentPeriod,
                'current_year' => $currentYear,
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching quarterly usage: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch quarterly usage data',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching usage data',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get summarized usage data prepared for ML forecasting
     * This endpoint provides data in a format suitable for Python ML API
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getForecastData(Request $request): JsonResponse
    {
        try {
            // Get parameters (with defaults)
            $yearsBack = (int)($request->input('years_back', 2)); // Default: 2 years of history
            $currentYear = now()->year;
            $startYear = $currentYear - $yearsBack;
            
            // Build periods array (Q1-Q4 for each year)
            $periods = [];
            for ($year = $startYear; $year <= $currentYear; $year++) {
                for ($q = 1; $q <= 4; $q++) {
                    $periods[] = "Q{$q} {$year}";
                }
            }
            
            // Get all usage records for the specified time range
            $usageRecords = ItemUsage::with(['item.category'])
                ->whereIn('period', $periods)
                ->orderBy('period')
                ->orderBy('item_id')
                ->orderBy('created_at')
                ->get();
            
            // Get all unique items
            $itemIds = $usageRecords->pluck('item_id')->unique()->sort()->values();
            
            // Structure data for ML forecasting
            $forecastData = [];
            
            foreach ($itemIds as $itemId) {
                $itemRecords = $usageRecords->where('item_id', $itemId);
                $item = Item::find($itemId);
                
                if (!$item) {
                    continue;
                }
                
                // Build time series data for this item
                $timeSeries = [];
                $historicalData = [];
                
                foreach ($periods as $period) {
                    $periodRecords = $itemRecords->where('period', $period);
                    
                    if ($periodRecords->isEmpty()) {
                        // No data for this period, but include it for continuity
                        $timeSeries[] = [
                            'period' => $period,
                            'usage' => 0,
                            'restocked' => false,
                            'restock_qty' => 0,
                            'stock_start' => null,
                            'stock_end' => null,
                        ];
                        continue;
                    }
                    
                    // Aggregate data for this period
                    $totalUsage = $periodRecords->sum('usage');
                    $totalRestocked = $periodRecords->sum('restock_qty');
                    $hasRestocked = $periodRecords->where('restocked', true)->isNotEmpty();
                    
                    // Get stock values (latest values)
                    $latestRecord = $periodRecords->sortByDesc('created_at')->first();
                    $stockStart = $latestRecord->stock_start;
                    $stockEnd = $latestRecord->stock_end;
                    
                    $timeSeries[] = [
                        'period' => $period,
                        'usage' => (int)$totalUsage,
                        'restocked' => $hasRestocked,
                        'restock_qty' => (int)$totalRestocked,
                        'stock_start' => $stockStart,
                        'stock_end' => $stockEnd,
                    ];
                    
                    // Historical data point for ML
                    $historicalData[] = [
                        'period' => $period,
                        'timestamp' => $this->periodToTimestamp($period),
                        'usage' => (int)$totalUsage,
                        'restock_qty' => (int)$totalRestocked,
                        'restocked' => $hasRestocked ? 1 : 0,
                        'stock_start' => $stockStart ?? 0,
                        'stock_end' => $stockEnd ?? 0,
                        'net_change' => $stockEnd !== null && $stockStart !== null 
                            ? $stockEnd - $stockStart 
                            : 0,
                    ];
                }
                
                // Calculate trends and statistics
                $stats = $this->calculateItemStatistics($historicalData);
                
                // Calculate Linear Regression forecast for next quarter (3 months)
                $lrForecast = $this->calculateLinearRegressionForecast($historicalData);
                
                // Determine next period
                $currentPeriod = ItemUsage::getCurrentPeriod();
                $nextPeriod = $this->getNextQuarter($currentPeriod);
                
                // Prepare forecast-ready data structure
                $forecastData[] = [
                    'item_id' => $itemId,
                    'item' => [
                        'id' => $item->id,
                        'unit' => $item->unit,
                        'description' => $item->description,
                        'category' => $item->category ? $item->category->category : null,
                    ],
                    'current_stock' => $item->quantity ?? 0,
                    'time_series' => $timeSeries,
                    'historical_data' => $historicalData,
                    'statistics' => $stats,
                    'forecast' => [
                        'next_period' => $nextPeriod,
                        'predicted_usage' => $lrForecast['predicted_usage'],
                        'confidence' => $lrForecast['confidence'],
                        'method' => $lrForecast['method'],
                        'r_squared' => $lrForecast['r_squared'] ?? null,
                        'slope' => $lrForecast['slope'] ?? null,
                        'data_points' => $lrForecast['data_points'] ?? 0,
                    ],
                    'forecast_features' => [
                        'avg_usage_per_quarter' => $stats['avg_usage'],
                        'trend' => $stats['trend'],
                        'volatility' => $stats['volatility'],
                        'restock_frequency' => $stats['restock_frequency'],
                        'current_stock' => $item->quantity ?? 0,
                        'last_usage' => $stats['last_usage'],
                        'usage_growth_rate' => $stats['growth_rate'],
                    ],
                ];
            }
            
            // Overall summary statistics
            $summary = [
                'total_items' => count($forecastData),
                'years_analyzed' => $yearsBack + 1,
                'periods_analyzed' => count($periods),
                'current_period' => ItemUsage::getCurrentPeriod(),
                'forecast_horizon' => $request->input('forecast_months', 3), // Default 3 months
            ];
            
            return response()->json([
                'success' => true,
                'data' => $forecastData,
                'summary' => $summary,
                'metadata' => [
                    'format' => 'ml_ready',
                    'timestamp' => now()->toISOString(),
                    'prepared_for' => 'linear_regression_forecasting',
                ],
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error preparing forecast data: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare forecast data',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while preparing forecast data',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Convert period string (e.g., "Q1 2025") to timestamp
     * 
     * @param string $period
     * @return string ISO timestamp
     */
    private function periodToTimestamp(string $period): string
    {
        // Parse period format: "Q1 2025"
        if (preg_match('/Q(\d)\s+(\d{4})/', $period, $matches)) {
            $quarter = (int)$matches[1];
            $year = (int)$matches[2];
            
            // Calculate month (Q1=Jan, Q2=Apr, Q3=Jul, Q4=Oct)
            $month = ($quarter - 1) * 3 + 1;
            
            return date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
        }
        
        return now()->toDateString();
    }

    /**
     * Get the next quarter period from current period
     * 
     * @param string $currentPeriod e.g., "Q4 2025"
     * @return string e.g., "Q1 2026"
     */
    private function getNextQuarter(string $currentPeriod): string
    {
        if (preg_match('/Q(\d)\s+(\d{4})/', $currentPeriod, $matches)) {
            $quarter = (int)$matches[1];
            $year = (int)$matches[2];
            
            if ($quarter == 4) {
                // Next year, Q1
                return "Q1 " . ($year + 1);
            } else {
                // Same year, next quarter
                return "Q" . ($quarter + 1) . " $year";
            }
        }
        
        // Fallback: calculate from current date
        $nextMonth = now()->addMonths(3)->month;
        $nextYear = now()->addMonths(3)->year;
        
        if ($nextMonth >= 1 && $nextMonth <= 3) {
            return "Q1 $nextYear";
        } elseif ($nextMonth >= 4 && $nextMonth <= 6) {
            return "Q2 $nextYear";
        } elseif ($nextMonth >= 7 && $nextMonth <= 9) {
            return "Q3 $nextYear";
        } else {
            return "Q4 $nextYear";
        }
    }

    /**
     * Calculate Linear Regression forecast for next quarter
     * 
     * @param array $historicalData
     * @return array
     */
    private function calculateLinearRegressionForecast(array $historicalData): array
    {
        if (empty($historicalData)) {
            return [
                'predicted_usage' => 0,
                'confidence' => 0,
                'method' => 'insufficient_data'
            ];
        }
        
        // Extract usage values and time periods
        $usageValues = [];
        $periods = [];
        $timeIndex = 0;
        
        foreach ($historicalData as $data) {
            if (isset($data['usage']) && $data['usage'] > 0) {
                $usageValues[] = $data['usage'];
                $periods[] = $timeIndex++;
            }
        }
        
        // Need at least 2 data points for linear regression
        if (count($usageValues) < 2) {
            $avgUsage = !empty($usageValues) ? array_sum($usageValues) / count($usageValues) : 0;
            return [
                'predicted_usage' => (int)round($avgUsage),
                'confidence' => 0.3,
                'method' => 'average'
            ];
        }
        
        // Calculate Linear Regression: y = mx + b
        // m = slope, b = y-intercept
        $n = count($periods);
        $sumX = array_sum($periods);
        $sumY = array_sum($usageValues);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $periods[$i] * $usageValues[$i];
            $sumX2 += $periods[$i] * $periods[$i];
        }
        
        // Calculate slope (m) and intercept (b)
        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        
        if (abs($denominator) < 0.0001) {
            // Avoid division by zero, use average
            $avgUsage = $sumY / $n;
            return [
                'predicted_usage' => (int)round($avgUsage),
                'confidence' => 0.5,
                'method' => 'average'
            ];
        }
        
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;
        
        // Predict next quarter (next period index)
        $nextPeriodIndex = count($periods);
        $predictedUsage = max(0, round($slope * $nextPeriodIndex + $intercept));
        
        // Calculate R-squared (coefficient of determination) for confidence
        $yMean = $sumY / $n;
        $ssTotal = 0; // Total sum of squares
        $ssResidual = 0; // Residual sum of squares
        
        for ($i = 0; $i < $n; $i++) {
            $yPredicted = $slope * $periods[$i] + $intercept;
            $ssTotal += pow($usageValues[$i] - $yMean, 2);
            $ssResidual += pow($usageValues[$i] - $yPredicted, 2);
        }
        
        $rSquared = 0;
        if ($ssTotal > 0) {
            $rSquared = 1 - ($ssResidual / $ssTotal);
        }
        
        // Convert R-squared to confidence (0.3 to 0.95 range)
        $confidence = max(0.3, min(0.95, abs($rSquared)));
        
        return [
            'predicted_usage' => (int)$predictedUsage,
            'slope' => round($slope, 2),
            'intercept' => round($intercept, 2),
            'r_squared' => round($rSquared, 4),
            'confidence' => round($confidence, 2),
            'method' => 'linear_regression',
            'data_points' => $n
        ];
    }

    /**
     * Calculate statistics for an item's usage history
     * 
     * @param array $historicalData
     * @return array
     */
    private function calculateItemStatistics(array $historicalData): array
    {
        if (empty($historicalData)) {
            return [
                'avg_usage' => 0,
                'max_usage' => 0,
                'min_usage' => 0,
                'trend' => 'stable',
                'volatility' => 0,
                'restock_frequency' => 0,
                'last_usage' => 0,
                'growth_rate' => 0,
            ];
        }
        
        $usages = array_column($historicalData, 'usage');
        $nonZeroUsages = array_filter($usages, fn($u) => $u > 0);
        
        $avgUsage = !empty($nonZeroUsages) ? array_sum($nonZeroUsages) / count($nonZeroUsages) : 0;
        $maxUsage = max($usages);
        $minUsage = min($usages);
        $lastUsage = end($usages);
        
        // Calculate trend (increasing, decreasing, stable)
        $recentUsages = array_slice($usages, -4); // Last 4 quarters
        if (count($recentUsages) >= 2) {
            $firstHalf = array_slice($recentUsages, 0, count($recentUsages) / 2);
            $secondHalf = array_slice($recentUsages, count($recentUsages) / 2);
            $firstAvg = array_sum($firstHalf) / count($firstHalf);
            $secondAvg = array_sum($secondHalf) / count($secondHalf);
            
            $diff = $secondAvg - $firstAvg;
            $trendPercent = $firstAvg > 0 ? ($diff / $firstAvg) * 100 : 0;
            
            if ($trendPercent > 10) {
                $trend = 'increasing';
            } elseif ($trendPercent < -10) {
                $trend = 'decreasing';
            } else {
                $trend = 'stable';
            }
        } else {
            $trend = 'stable';
        }
        
        // Calculate volatility (standard deviation of usage)
        $volatility = 0;
        if (count($nonZeroUsages) > 1) {
            $mean = $avgUsage;
            $variance = 0;
            foreach ($nonZeroUsages as $usage) {
                $variance += pow($usage - $mean, 2);
            }
            $volatility = sqrt($variance / count($nonZeroUsages));
        }
        
        // Restock frequency
        $restockedPeriods = array_filter($historicalData, fn($d) => $d['restocked'] == 1);
        $restockFrequency = count($restockedPeriods) / count($historicalData);
        
        // Growth rate (simple linear regression slope approximation)
        $growthRate = 0;
        if (count($usages) >= 2) {
            $firstUsage = reset($usages);
            $lastUsage = end($usages);
            $periodsCount = count($usages) - 1;
            if ($periodsCount > 0) {
                $growthRate = ($lastUsage - $firstUsage) / $periodsCount;
            }
        }
        
        return [
            'avg_usage' => round($avgUsage, 2),
            'max_usage' => $maxUsage,
            'min_usage' => $minUsage,
            'trend' => $trend,
            'volatility' => round($volatility, 2),
            'restock_frequency' => round($restockFrequency, 4),
            'last_usage' => $lastUsage,
            'growth_rate' => round($growthRate, 2),
        ];
    }
}
