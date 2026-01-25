<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ItemUsage;
use App\Models\Item;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Exports\SupplyUsageRankingExport;
use Maatwebsite\Excel\Facades\Excel;

class UsageController extends Controller
{
    /**
     * Get quarterly usage data grouped by period and item
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getQuarterlyUsage(Request $request): JsonResponse
    {
        try {
            // Get year from request parameter, default to current year
            $requestedYear = $request->input('year', now()->year);
            $requestedYear = (int)$requestedYear; // Ensure it's an integer
            $currentYear = now()->year;
            
            // Get all quarters for requested year
            $quarters = [
                "Q1 $requestedYear",
                "Q2 $requestedYear",
                "Q3 $requestedYear",
                "Q4 $requestedYear"
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
                        'requested_year' => $requestedYear,
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
                'requested_year' => $requestedYear,
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
     * Get supply usage ranking report - shows which supplies have the most usage
     * Helps identify items that might exceed budget
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSupplyUsageRanking(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            $limit = (int)$request->input('limit', 20); // Top N items
            $sortBy = $request->input('sort_by', 'total_usage'); // total_usage, avg_usage, recent_usage
            
            // Get all usage records for the specified year
            $quarters = [
                "Q1 $year",
                "Q2 $year",
                "Q3 $year",
                "Q4 $year"
            ];
            
            // Get usage data grouped by item
            $usageData = ItemUsage::with(['item.category'])
                ->whereIn('period', $quarters)
                ->select(
                    'item_id',
                    DB::raw('SUM(usage) as total_usage'),
                    DB::raw('AVG(usage) as avg_usage'),
                    DB::raw('COUNT(*) as quarters_count'),
                    DB::raw('MAX(usage) as max_usage'),
                    DB::raw('MIN(usage) as min_usage')
                )
                ->groupBy('item_id')
                ->get();
            
            // Get recent usage (last quarter)
            $recentQuarter = end($quarters);
            $recentUsage = ItemUsage::where('period', $recentQuarter)
                ->pluck('usage', 'item_id')
                ->toArray();
            
            // Enrich with item details and calculate metrics
            $rankedSupplies = [];
            foreach ($usageData as $usage) {
                $item = $usage->item;
                if (!$item) continue;
                
                // Only include supply items
                if (!$item->category || stripos($item->category->category, 'supply') === false) {
                    continue;
                }
                
                $totalUsage = (int)$usage->total_usage;
                $avgUsage = (float)$usage->avg_usage;
                $recentUsageValue = $recentUsage[$usage->item_id] ?? 0;
                
                // Calculate trend (comparing last 2 quarters if available)
                $trend = 'stable';
                $quartersData = ItemUsage::where('item_id', $usage->item_id)
                    ->whereIn('period', $quarters)
                    ->orderBy('period', 'desc')
                    ->limit(2)
                    ->pluck('usage')
                    ->toArray();
                
                if (count($quartersData) >= 2) {
                    $lastQuarter = $quartersData[0];
                    $previousQuarter = $quartersData[1];
                    if ($lastQuarter > $previousQuarter) {
                        $trend = 'increasing';
                    } elseif ($lastQuarter < $previousQuarter) {
                        $trend = 'decreasing';
                    }
                }
                
                $rankedSupplies[] = [
                    'item_id' => $usage->item_id,
                    'item' => [
                        'id' => $item->id,
                        'unit' => $item->unit,
                        'description' => $item->description,
                        'category' => $item->category ? $item->category->category : null,
                    ],
                    'total_usage' => $totalUsage,
                    'avg_usage' => round($avgUsage, 2),
                    'recent_usage' => (int)$recentUsageValue,
                    'max_usage' => (int)$usage->max_usage,
                    'min_usage' => (int)$usage->min_usage,
                    'quarters_count' => (int)$usage->quarters_count,
                    'trend' => $trend,
                    'usage_by_quarter' => ItemUsage::where('item_id', $usage->item_id)
                        ->whereIn('period', $quarters)
                        ->orderBy('period')
                        ->get()
                        ->map(function($record) {
                            return [
                                'period' => $record->period,
                                'usage' => $record->usage,
                                'stock_start' => $record->stock_start,
                                'stock_end' => $record->stock_end,
                            ];
                        })
                        ->values()
                        ->toArray()
                ];
            }
            
            // Sort by requested field
            usort($rankedSupplies, function($a, $b) use ($sortBy) {
                return $b[$sortBy] <=> $a[$sortBy];
            });
            
            // Limit results
            $rankedSupplies = array_slice($rankedSupplies, 0, $limit);
            
            // Calculate summary statistics
            $totalItems = count($rankedSupplies);
            $totalUsageAll = array_sum(array_column($rankedSupplies, 'total_usage'));
            $avgUsageAll = $totalItems > 0 ? round($totalUsageAll / $totalItems, 2) : 0;
            
            return response()->json([
                'success' => true,
                'data' => $rankedSupplies,
                'summary' => [
                    'year' => $year,
                    'total_items' => $totalItems,
                    'total_usage_all' => $totalUsageAll,
                    'avg_usage_all' => $avgUsageAll,
                    'sort_by' => $sortBy,
                ],
                'meta' => [
                    'quarters' => $quarters,
                    'recent_quarter' => $recentQuarter,
                ]
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching supply usage ranking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supply usage ranking',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Export supply usage ranking to PDF
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function exportSupplyRankingPDF(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $limit = (int)$request->input('limit', 20);
            $sortBy = $request->input('sort_by', 'total_usage');
            
            // Get ranking data (reuse the same logic)
            $quarters = [
                "Q1 $year",
                "Q2 $year",
                "Q3 $year",
                "Q4 $year"
            ];
            
            $usageData = ItemUsage::with(['item.category'])
                ->whereIn('period', $quarters)
                ->select(
                    'item_id',
                    DB::raw('SUM(usage) as total_usage'),
                    DB::raw('AVG(usage) as avg_usage'),
                    DB::raw('COUNT(*) as quarters_count'),
                    DB::raw('MAX(usage) as max_usage'),
                    DB::raw('MIN(usage) as min_usage')
                )
                ->groupBy('item_id')
                ->get();
            
            $recentQuarter = end($quarters);
            $recentUsage = ItemUsage::where('period', $recentQuarter)
                ->pluck('usage', 'item_id')
                ->toArray();
            
            $rankedSupplies = [];
            foreach ($usageData as $usage) {
                $item = $usage->item;
                if (!$item || !$item->category || stripos($item->category->category, 'supply') === false) {
                    continue;
                }
                
                $totalUsage = (int)$usage->total_usage;
                $avgUsage = (float)$usage->avg_usage;
                $recentUsageValue = $recentUsage[$usage->item_id] ?? 0;
                
                $trend = 'stable';
                $quartersData = ItemUsage::where('item_id', $usage->item_id)
                    ->whereIn('period', $quarters)
                    ->orderBy('period', 'desc')
                    ->limit(2)
                    ->pluck('usage')
                    ->toArray();
                
                if (count($quartersData) >= 2) {
                    $lastQuarter = $quartersData[0];
                    $previousQuarter = $quartersData[1];
                    if ($lastQuarter > $previousQuarter) {
                        $trend = 'increasing';
                    } elseif ($lastQuarter < $previousQuarter) {
                        $trend = 'decreasing';
                    }
                }
                
                $rankedSupplies[] = [
                    'item_id' => $usage->item_id,
                    'item' => [
                        'id' => $item->id,
                        'unit' => $item->unit,
                        'description' => $item->description,
                    ],
                    'total_usage' => $totalUsage,
                    'avg_usage' => round($avgUsage, 2),
                    'recent_usage' => (int)$recentUsageValue,
                    'trend' => $trend,
                ];
            }
            
            usort($rankedSupplies, function($a, $b) use ($sortBy) {
                return $b[$sortBy] <=> $a[$sortBy];
            });
            
            $rankedSupplies = array_slice($rankedSupplies, 0, $limit);
            
            $totalItems = count($rankedSupplies);
            $totalUsageAll = array_sum(array_column($rankedSupplies, 'total_usage'));
            $avgUsageAll = $totalItems > 0 ? round($totalUsageAll / $totalItems, 2) : 0;
            
            // Generate HTML for PDF
            $html = $this->generateRankingPdfHtml($rankedSupplies, $year, $totalItems, $totalUsageAll, $avgUsageAll);
            
            // Check if DOMPDF is available
            $dompdfExists = class_exists('Dompdf\Dompdf') || class_exists('\Dompdf\Dompdf');
            $optionsExists = class_exists('Dompdf\Options') || class_exists('\Dompdf\Options');
            
            if (!$dompdfExists || !$optionsExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF generation library not available. Please contact administrator.',
                ], 500);
            }
            
            // Generate PDF
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Arial');
            $options->set('chroot', base_path());
            
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $fileName = 'Supply_Usage_Ranking_' . $year . '_' . date('Y-m-d_His') . '.pdf';
            
            return response()->streamDownload(function () use ($dompdf) {
                echo $dompdf->output();
            }, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error exporting supply usage ranking to PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Export supply usage ranking to Excel
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportSupplyRankingExcel(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $limit = (int)$request->input('limit', 20);
            $sortBy = $request->input('sort_by', 'total_usage');
            
            // Get ranking data (reuse the same logic from getSupplyUsageRanking)
            $quarters = [
                "Q1 $year",
                "Q2 $year",
                "Q3 $year",
                "Q4 $year"
            ];
            
            $usageData = ItemUsage::with(['item.category'])
                ->whereIn('period', $quarters)
                ->select(
                    'item_id',
                    DB::raw('SUM(usage) as total_usage'),
                    DB::raw('AVG(usage) as avg_usage'),
                    DB::raw('COUNT(*) as quarters_count'),
                    DB::raw('MAX(usage) as max_usage'),
                    DB::raw('MIN(usage) as min_usage')
                )
                ->groupBy('item_id')
                ->get();
            
            $recentQuarter = end($quarters);
            $recentUsage = ItemUsage::where('period', $recentQuarter)
                ->pluck('usage', 'item_id')
                ->toArray();
            
            $rankedSupplies = [];
            foreach ($usageData as $usage) {
                $item = $usage->item;
                if (!$item || !$item->category || stripos($item->category->category, 'supply') === false) {
                    continue;
                }
                
                $totalUsage = (int)$usage->total_usage;
                $avgUsage = (float)$usage->avg_usage;
                $recentUsageValue = $recentUsage[$usage->item_id] ?? 0;
                
                $trend = 'stable';
                $quartersData = ItemUsage::where('item_id', $usage->item_id)
                    ->whereIn('period', $quarters)
                    ->orderBy('period', 'desc')
                    ->limit(2)
                    ->pluck('usage')
                    ->toArray();
                
                if (count($quartersData) >= 2) {
                    $lastQuarter = $quartersData[0];
                    $previousQuarter = $quartersData[1];
                    if ($lastQuarter > $previousQuarter) {
                        $trend = 'increasing';
                    } elseif ($lastQuarter < $previousQuarter) {
                        $trend = 'decreasing';
                    }
                }
                
                $rankedSupplies[] = [
                    'item_id' => $usage->item_id,
                    'item' => [
                        'id' => $item->id,
                        'unit' => $item->unit,
                        'description' => $item->description,
                    ],
                    'total_usage' => $totalUsage,
                    'avg_usage' => round($avgUsage, 2),
                    'recent_usage' => (int)$recentUsageValue,
                    'trend' => $trend,
                ];
            }
            
            usort($rankedSupplies, function($a, $b) use ($sortBy) {
                return $b[$sortBy] <=> $a[$sortBy];
            });
            
            $rankedSupplies = array_slice($rankedSupplies, 0, $limit);
            
            $totalItems = count($rankedSupplies);
            $totalUsageAll = array_sum(array_column($rankedSupplies, 'total_usage'));
            $avgUsageAll = $totalItems > 0 ? round($totalUsageAll / $totalItems, 2) : 0;
            
            $summary = [
                'year' => $year,
                'total_items' => $totalItems,
                'total_usage_all' => $totalUsageAll,
                'avg_usage_all' => $avgUsageAll,
            ];
            
            $fileName = 'Supply_Usage_Ranking_' . $year . '_' . date('Y-m-d_His') . '.xlsx';
            
            return Excel::download(new SupplyUsageRankingExport($rankedSupplies, $year, $summary), $fileName);
            
        } catch (\Exception $e) {
            \Log::error('Error exporting supply usage ranking to Excel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export Excel',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Generate HTML content for ranking PDF
     */
    private function generateRankingPdfHtml($rankedSupplies, $year, $totalItems, $totalUsageAll, $avgUsageAll): string
    {
        $currentDate = date('F d, Y');
        
        // Get logo as base64 if it exists
        $logoBase64 = '';
        $logoPath = \App\Support\Logo::path();
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        }
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    margin: 2cm 1.5cm;
                    size: A4 portrait;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: "Calibri", "Arial", sans-serif;
                    font-size: 11pt;
                    line-height: 1.6;
                    color: #1a1a1a;
                    background: #ffffff;
                }
                .document-wrapper {
                    width: 100%;
                    max-width: 100%;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 3px solid #059669;
                    position: relative;
                }
                .header-top {
                    margin-bottom: 15px;
                }
                .header h1 {
                    font-size: 20pt;
                    font-weight: 700;
                    margin: 5px 0;
                    letter-spacing: 1px;
                    color: #1a1a1a;
                    text-transform: uppercase;
                }
                .header h2 {
                    font-size: 18pt;
                    font-weight: 600;
                    margin: 5px 0;
                    letter-spacing: 0.5px;
                    color: #1a1a1a;
                }
                .header h3 {
                    font-size: 14pt;
                    font-weight: 500;
                    margin: 5px 0;
                    color: #333;
                }
                .logo-container {
                    margin: 20px 0;
                    height: 100px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .logo-container img {
                    max-height: 100px;
                    max-width: 100px;
                    height: auto;
                    width: auto;
                    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
                }
                .report-title-section {
                    margin-top: 25px;
                    padding: 15px 0;
                    background: linear-gradient(to bottom, #f8f9fa, #ffffff);
                    border-radius: 4px;
                }
                .report-title {
                    font-size: 16pt;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                    margin: 10px 0;
                    color: #059669;
                }
                .report-subtitle {
                    font-size: 12pt;
                    margin: 8px 0;
                    color: #555;
                    font-weight: 500;
                }
                .report-date {
                    font-size: 10pt;
                    margin-top: 10px;
                    color: #777;
                    font-style: italic;
                }
                .table-container {
                    margin: 25px 0;
                    overflow: visible;
                }
                table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                    margin: 0;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                    border-radius: 6px;
                    overflow: hidden;
                }
                thead {
                    display: table-header-group;
                }
                tbody {
                    display: table-row-group;
                }
                th {
                    background: linear-gradient(to bottom, #059669, #047857);
                    color: #FFFFFF;
                    padding: 12px 10px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 10pt;
                    border: none;
                    border-right: 1px solid rgba(255,255,255,0.2);
                    vertical-align: middle;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                th:first-child {
                    text-align: center;
                    border-left: none;
                }
                th:last-child {
                    border-right: none;
                }
                th:nth-child(3),
                th:nth-child(4),
                th:nth-child(5) {
                    text-align: right;
                }
                td {
                    padding: 10px;
                    border: none;
                    border-bottom: 1px solid #e5e7eb;
                    border-right: 1px solid #e5e7eb;
                    font-size: 10pt;
                    vertical-align: middle;
                    transition: background-color 0.2s;
                }
                td:first-child {
                    text-align: center;
                    font-weight: 600;
                    color: #059669;
                    border-left: none;
                }
                td:last-child {
                    border-right: none;
                }
                td:nth-child(3),
                td:nth-child(4),
                td:nth-child(5) {
                    text-align: right;
                    font-family: "Courier New", monospace;
                    font-weight: 500;
                }
                tbody tr {
                    transition: background-color 0.2s;
                }
                tbody tr:nth-child(even) {
                    background-color: #f9fafb;
                }
                tbody tr:nth-child(odd) {
                    background-color: #ffffff;
                }
                tbody tr:hover {
                    background-color: #f0fdf4;
                }
                tbody tr:last-child td {
                    border-bottom: none;
                }
                .footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 2px solid #e5e7eb;
                    text-align: center;
                    font-size: 9pt;
                    color: #666;
                    line-height: 1.8;
                }
                .footer-summary {
                    font-weight: 600;
                    color: #1a1a1a;
                    margin-bottom: 8px;
                    font-size: 10pt;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    display: inline-block;
                }
                .footer-end {
                    margin-top: 15px;
                    font-style: italic;
                    color: #999;
                }
            </style>
        </head>
        <body>
            <div class="document-wrapper">
                <div class="header">
                    <div class="header-top">
                        <h1>Republic of the Philippines</h1>
                        <h2>National Irrigation Administration</h2>
                        <h3>Region XI</h3>';
        
        // Add logo if available
        if ($logoBase64) {
            $html .= '
                        <div class="logo-container">
                            <img src="' . $logoBase64 . '" alt="NIA Logo" />
                        </div>';
        }
        
        $html .= '
                    </div>
                    <div class="report-title-section">
                        <div class="report-title">Supply Usage Ranking Report</div>
                        <div class="report-subtitle">For the Year ' . $year . '</div>
                        <div class="report-date">Generated on: ' . $currentDate . '</div>
                    </div>
                </div>
                <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Supply Item</th>
                        <th>Total Usage</th>
                        <th>Avg/Quarter</th>
                        <th>Recent Usage</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($rankedSupplies as $index => $supply) {
            $html .= '
                    <tr>
                        <td>' . ($index + 1) . '</td>
                        <td>' . htmlspecialchars($supply['item']['unit'] ?? 'Item ' . $supply['item_id']) . '</td>
                        <td>' . number_format($supply['total_usage']) . '</td>
                        <td>' . number_format($supply['avg_usage'], 2) . '</td>
                        <td>' . number_format($supply['recent_usage']) . '</td>
                        <td>' . ucfirst($supply['trend']) . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
                </div>
            <div class="footer">
                <div class="footer-summary">
                    Total Items: ' . $totalItems . ' | Total Usage: ' . number_format($totalUsageAll) . ' units | Average Usage: ' . number_format($avgUsageAll, 2) . ' units per item
                </div>
                <div class="footer-end">
                    End of Report
                </div>
            </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }

    /**
     * Get monthly usage data for a specific quarter
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getMonthlyUsage(Request $request): JsonResponse
    {
        try {
            $quarter = $request->input('quarter'); // e.g., "Q1 2025"
            $year = $request->input('year', now()->year);
            
            if (!$quarter && $year) {
                // If only year provided, get current quarter
                $quarter = ItemUsage::getCurrentPeriod();
            }
            
            if (!$quarter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quarter parameter is required (e.g., "Q1 2025")'
                ], 400);
            }
            
            // Parse quarter to get months
            if (preg_match('/Q(\d)\s+(\d{4})/', $quarter, $matches)) {
                $quarterNum = (int)$matches[1];
                $year = (int)$matches[2];
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid quarter format. Use "Q1 2025" format.'
                ], 400);
            }
            
            // Map quarter to months
            $monthMap = [
                1 => ['January', 'February', 'March'],
                2 => ['April', 'May', 'June'],
                3 => ['July', 'August', 'September'],
                4 => ['October', 'November', 'December']
            ];
            
            $months = $monthMap[$quarterNum] ?? [];
            $monthNumbers = [
                1 => [1, 2, 3],
                2 => [4, 5, 6],
                3 => [7, 8, 9],
                4 => [10, 11, 12]
            ];
            
            $monthNums = $monthNumbers[$quarterNum] ?? [];
            
            // Check if this is the current quarter
            $currentPeriod = ItemUsage::getCurrentPeriod();
            $isCurrentQuarter = ($quarter === $currentPeriod);
            $currentMonth = now()->month;
            $currentYear = now()->year;
            
            // Get all items that have usage in this quarter
            $quarterRecords = ItemUsage::with('item')
                ->where('period', $quarter)
                ->get();
            
            $itemIds = $quarterRecords->pluck('item_id')->unique();
            
            $monthlyData = [];
            
            foreach ($itemIds as $itemId) {
                $item = Item::find($itemId);
                if (!$item) continue;
                
                $quarterRecord = $quarterRecords->where('item_id', $itemId)->first();
                $totalUsage = $quarterRecord->usage ?? 0;
                
                // Distribute quarterly usage across months (proportional or equal)
                // Option 1: Equal distribution (simple)
                $usagePerMonth = $totalUsage > 0 ? round($totalUsage / 3) : 0;
                $remainder = $totalUsage - ($usagePerMonth * 3);
                
                $monthlyBreakdown = [];
                
                // If this is the current quarter, only show data for months that have passed
                if ($isCurrentQuarter && $year == $currentYear) {
                    // Calculate how many months have passed in this quarter
                    $firstMonthOfQuarter = $monthNums[0];
                    $monthsPassed = $currentMonth - $firstMonthOfQuarter + 1;
                    
                    // Only distribute usage among months that have actually occurred
                    foreach ($months as $index => $monthName) {
                        $monthNum = $monthNums[$index];
                        
                        if ($monthNum > $currentMonth) {
                            // Future month - show 0 (no usage yet)
                            $monthlyBreakdown[] = [
                                'month' => $monthName,
                                'month_number' => $monthNum,
                                'usage' => 0,
                                'year' => $year,
                                'period' => "$monthName $year",
                                'is_future' => true
                            ];
                        } else {
                            // Past or current month - distribute usage among months that have passed
                            if ($monthsPassed > 0) {
                                $usagePerPassedMonth = $totalUsage > 0 ? round($totalUsage / $monthsPassed) : 0;
                                $remainderForPassed = $totalUsage - ($usagePerPassedMonth * $monthsPassed);
                                
                                $monthUsage = $usagePerPassedMonth;
                                // Add remainder to first month that has passed
                                if ($index == 0 && $remainderForPassed > 0) {
                                    $monthUsage += $remainderForPassed;
                                }
                            } else {
                                $monthUsage = 0;
                            }
                            
                            $monthlyBreakdown[] = [
                                'month' => $monthName,
                                'month_number' => $monthNum,
                                'usage' => max(0, $monthUsage),
                                'year' => $year,
                                'period' => "$monthName $year",
                                'is_future' => false
                            ];
                        }
                    }
                } else {
                    // Past quarter - distribute evenly across all months
                    foreach ($months as $index => $monthName) {
                        $monthNum = $monthNums[$index];
                        $monthUsage = $usagePerMonth;
                        // Add remainder to first month
                        if ($index === 0 && $remainder > 0) {
                            $monthUsage += $remainder;
                        }
                        
                        $monthlyBreakdown[] = [
                            'month' => $monthName,
                            'month_number' => $monthNum,
                            'usage' => max(0, $monthUsage),
                            'year' => $year,
                            'period' => "$monthName $year",
                            'is_future' => false
                        ];
                    }
                }
                
                $monthlyData[] = [
                    'item_id' => $itemId,
                    'item' => [
                        'id' => $item->id,
                        'unit' => $item->unit,
                        'description' => $item->description,
                    ],
                    'quarter' => $quarter,
                    'total_usage' => $totalUsage,
                    'monthly_breakdown' => $monthlyBreakdown
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $monthlyData,
                'quarter' => $quarter,
                'year' => $year,
                'months' => $months,
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching monthly usage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch monthly usage data',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
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
     * Create or update a usage record
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'item_id' => 'required|exists:items,id',
                'period' => 'required|string', // e.g., "Q1 2024"
                'usage' => 'required|integer|min:0',
                'stock_start' => 'nullable|integer|min:0',
                'stock_end' => 'nullable|integer|min:0',
                'restocked' => 'nullable|boolean',
                'restock_qty' => 'nullable|integer|min:0',
            ]);

            // Find or create usage record
            $usage = ItemUsage::updateOrCreate(
                [
                    'item_id' => $request->item_id,
                    'period' => $request->period,
                ],
                [
                    'usage' => $request->usage,
                    'stock_start' => $request->stock_start,
                    'stock_end' => $request->stock_end,
                    'restocked' => $request->restocked ?? false,
                    'restock_qty' => $request->restock_qty ?? 0,
                ]
            );

            \Log::info("Created/Updated usage record for item {$request->item_id} in period {$request->period}");

            return response()->json([
                'success' => true,
                'message' => 'Usage record created/updated successfully',
                'data' => [
                    'id' => $usage->id,
                    'item_id' => $usage->item_id,
                    'period' => $usage->period,
                    'usage' => $usage->usage,
                    'stock_start' => $usage->stock_start,
                    'stock_end' => $usage->stock_end,
                    'restocked' => $usage->restocked,
                    'restock_qty' => $usage->restock_qty,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error creating usage record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create usage record',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Bulk create usage records
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'records' => 'required|array',
                'records.*.item_id' => 'required|exists:items,id',
                'records.*.period' => 'required|string',
                'records.*.usage' => 'required|integer|min:0',
                'records.*.stock_start' => 'nullable|integer|min:0',
                'records.*.stock_end' => 'nullable|integer|min:0',
                'records.*.restocked' => 'nullable|boolean',
                'records.*.restock_qty' => 'nullable|integer|min:0',
            ]);

            $created = [];
            $updated = [];

            foreach ($request->records as $record) {
                $usage = ItemUsage::updateOrCreate(
                    [
                        'item_id' => $record['item_id'],
                        'period' => $record['period'],
                    ],
                    [
                        'usage' => $record['usage'],
                        'stock_start' => $record['stock_start'] ?? null,
                        'stock_end' => $record['stock_end'] ?? null,
                        'restocked' => $record['restocked'] ?? false,
                        'restock_qty' => $record['restock_qty'] ?? 0,
                    ]
                );

                if ($usage->wasRecentlyCreated) {
                    $created[] = $usage->id;
                } else {
                    $updated[] = $usage->id;
                }
            }

            \Log::info("Bulk created/updated " . count($request->records) . " usage records");

            return response()->json([
                'success' => true,
                'message' => 'Usage records created/updated successfully',
                'data' => [
                    'total' => count($request->records),
                    'created' => count($created),
                    'updated' => count($updated),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error bulk creating usage records: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create usage records',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
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

    /**
     * Get user supply usage ranking - tracks which users have supplies with high usage
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserSupplyUsage(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            $limit = (int)$request->input('limit', 20); // Top N users
            $sortBy = $request->input('sort_by', 'total_quantity'); // total_quantity, request_count, avg_quantity
            
            // Get supply requests for the specified year (approved or fulfilled)
            $startDate = "$year-01-01 00:00:00";
            $endDate = "$year-12-31 23:59:59";
            
            // Get supply requests grouped by user
            $userUsageData = SupplyRequest::whereIn('status', ['approved', 'fulfilled'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'requested_by_user_id',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('AVG(quantity) as avg_quantity'),
                    DB::raw('MAX(quantity) as max_quantity'),
                    DB::raw('MIN(quantity) as min_quantity')
                )
                ->groupBy('requested_by_user_id')
                ->get();
            
            // Get all user IDs to load relationships efficiently
            $userIds = $userUsageData->pluck('requested_by_user_id')->unique()->toArray();
            $users = User::with('location')->whereIn('id', $userIds)->get()->keyBy('id');
            
            // Enrich with user details and calculate metrics
            $rankedUsers = [];
            foreach ($userUsageData as $usage) {
                $user = $users->get($usage->requested_by_user_id);
                if (!$user) continue;
                
                $totalQuantity = (int)$usage->total_quantity;
                $requestCount = (int)$usage->request_count;
                $avgQuantity = (float)$usage->avg_quantity;
                
                // Get user location if available
                $location = $user->location ? $user->location->location : 'N/A';
                
                // Get unique items requested by this user
                $uniqueItems = SupplyRequest::where('requested_by_user_id', $usage->requested_by_user_id)
                    ->whereIn('status', ['approved', 'fulfilled'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->distinct('item_id')
                    ->count('item_id');
                
                $rankedUsers[] = [
                    'user_id' => $usage->requested_by_user_id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'location' => $location,
                    ],
                    'total_quantity' => $totalQuantity,
                    'request_count' => $requestCount,
                    'avg_quantity' => round($avgQuantity, 2),
                    'max_quantity' => (int)$usage->max_quantity,
                    'min_quantity' => (int)$usage->min_quantity,
                    'unique_items' => $uniqueItems,
                ];
            }
            
            // Sort by the specified field
            usort($rankedUsers, function($a, $b) use ($sortBy) {
                return $b[$sortBy] <=> $a[$sortBy];
            });
            
            // Limit results
            $rankedUsers = array_slice($rankedUsers, 0, $limit);
            
            // Calculate summary statistics
            $totalUsers = count($rankedUsers);
            $totalQuantityAll = array_sum(array_column($rankedUsers, 'total_quantity'));
            $totalRequestsAll = array_sum(array_column($rankedUsers, 'request_count'));
            $avgQuantityAll = $totalUsers > 0 ? round($totalQuantityAll / $totalUsers, 2) : 0;
            
            $summary = [
                'year' => $year,
                'total_users' => $totalUsers,
                'total_quantity_all' => $totalQuantityAll,
                'total_requests_all' => $totalRequestsAll,
                'avg_quantity_all' => $avgQuantityAll,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $rankedUsers,
                'summary' => $summary,
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching user supply usage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user supply usage data',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Get supply item usage by user - tracks which users have high usage for a specific supply item
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSupplyItemUsageByUser(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            $itemId = $request->input('item_id'); // Can be UUID or ID
            $limit = (int)$request->input('limit', 20); // Top N users
            $sortBy = $request->input('sort_by', 'total_quantity'); // total_quantity, request_count, avg_quantity
            
            if (!$itemId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item ID is required'
                ], 400);
            }
            
            // Get supply requests for the specified year and item (approved or fulfilled)
            $startDate = "$year-01-01 00:00:00";
            $endDate = "$year-12-31 23:59:59";
            
            // Check if item_id is UUID or numeric ID
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId);
            
            $query = SupplyRequest::whereIn('status', ['approved', 'fulfilled'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            
            if ($isUuid) {
                $query->where('item_id', $itemId);
            } else {
                $query->where('item_id', $itemId);
            }
            
            // Get supply requests grouped by user for this specific item
            $userUsageData = $query->select(
                    'requested_by_user_id',
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('AVG(quantity) as avg_quantity'),
                    DB::raw('MAX(quantity) as max_quantity'),
                    DB::raw('MIN(quantity) as min_quantity')
                )
                ->groupBy('requested_by_user_id')
                ->get();
            
            // Get item details
            $item = null;
            if ($isUuid) {
                $item = Item::withTrashed()->where('uuid', $itemId)->first();
            } else {
                $item = Item::withTrashed()->where('id', $itemId)->first();
            }
            
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found'
                ], 404);
            }
            
            // Get all user IDs to load relationships efficiently
            $userIds = $userUsageData->pluck('requested_by_user_id')->unique()->toArray();
            $users = User::with('location')->whereIn('id', $userIds)->get()->keyBy('id');
            
            // Enrich with user details and calculate metrics
            $rankedUsers = [];
            foreach ($userUsageData as $usage) {
                $user = $users->get($usage->requested_by_user_id);
                if (!$user) continue;
                
                $totalQuantity = (int)$usage->total_quantity;
                $requestCount = (int)$usage->request_count;
                $avgQuantity = (float)$usage->avg_quantity;
                
                // Get user location if available
                $location = $user->location ? $user->location->location : 'N/A';
                
                $rankedUsers[] = [
                    'user_id' => $usage->requested_by_user_id,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'location' => $location,
                    ],
                    'total_quantity' => $totalQuantity,
                    'request_count' => $requestCount,
                    'avg_quantity' => round($avgQuantity, 2),
                    'max_quantity' => (int)$usage->max_quantity,
                    'min_quantity' => (int)$usage->min_quantity,
                ];
            }
            
            // Sort by the specified field
            usort($rankedUsers, function($a, $b) use ($sortBy) {
                return $b[$sortBy] <=> $a[$sortBy];
            });
            
            // Limit results
            $rankedUsers = array_slice($rankedUsers, 0, $limit);
            
            // Calculate summary statistics
            $totalUsers = count($rankedUsers);
            $totalQuantityAll = array_sum(array_column($rankedUsers, 'total_quantity'));
            $totalRequestsAll = array_sum(array_column($rankedUsers, 'request_count'));
            $avgQuantityAll = $totalUsers > 0 ? round($totalQuantityAll / $totalUsers, 2) : 0;
            
            $summary = [
                'year' => $year,
                'item' => [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'unit' => $item->unit,
                    'description' => $item->description,
                ],
                'total_users' => $totalUsers,
                'total_quantity_all' => $totalQuantityAll,
                'total_requests_all' => $totalRequestsAll,
                'avg_quantity_all' => $avgQuantityAll,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $rankedUsers,
                'summary' => $summary,
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching supply item usage by user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supply item usage by user',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
