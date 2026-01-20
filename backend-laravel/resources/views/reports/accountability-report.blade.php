<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Accountability Report - {{ $report['personnel']['name'] }}</title>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #1e40af;
            margin: 0;
            font-size: 18pt;
            font-weight: bold;
        }
        .header p {
            margin: 5px 0;
            color: #666;
            font-size: 9pt;
        }
        .personnel-info {
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #2563eb;
        }
        .personnel-info h2 {
            color: #1e40af;
            font-size: 12pt;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 150px;
            padding: 3px 0;
            color: #555;
        }
        .info-value {
            display: table-cell;
            padding: 3px 0;
        }
        .summary-section {
            background-color: #eff6ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bfdbfe;
        }
        .summary-section h2 {
            color: #1e40af;
            font-size: 12pt;
            margin: 0 0 15px 0;
            font-weight: bold;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-row {
            display: table-row;
        }
        .summary-cell {
            display: table-cell;
            padding: 5px 10px;
            border-bottom: 1px solid #bfdbfe;
        }
        .summary-label {
            font-weight: bold;
            color: #555;
        }
        .summary-value {
            text-align: right;
            color: #1e40af;
            font-weight: bold;
        }
        .items-section {
            margin-top: 20px;
        }
        .items-section h2 {
            color: #1e40af;
            font-size: 12pt;
            margin: 0 0 15px 0;
            font-weight: bold;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8pt;
        }
        thead {
            background-color: #1e40af;
            color: white;
        }
        th {
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1e3a8a;
        }
        td {
            padding: 6px 5px;
            border: 1px solid #d1d5db;
        }
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        tbody tr:hover {
            background-color: #f3f4f6;
        }
        .status-issued {
            color: #059669;
            font-weight: bold;
        }
        .status-returned {
            color: #0284c7;
            font-weight: bold;
        }
        .status-lost {
            color: #dc2626;
            font-weight: bold;
        }
        .status-damaged {
            color: #ea580c;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 8pt;
            color: #6b7280;
        }
        .page-break {
            page-break-after: always;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>ACCOUNTABILITY REPORT</h1>
        <p>National Irrigation Administration</p>
        <p>Equipment and Property Accountability System</p>
    </div>

    <!-- Personnel Information -->
    <div class="personnel-info">
        <h2>Personnel Information</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value"><strong>{{ $report['personnel']['name'] }}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">User Code:</div>
                <div class="info-value">{{ $report['personnel']['user_code'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">{{ $report['personnel']['status'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Location/Unit:</div>
                <div class="info-value">{{ $report['personnel']['location'] }}</div>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="summary-section">
        <h2>Summary Statistics</h2>
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-cell summary-label">Total Items:</div>
                <div class="summary-cell summary-value">{{ $report['summary']['total_items'] }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Issued Items:</div>
                <div class="summary-cell summary-value">{{ $report['summary']['issued'] }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Returned Items:</div>
                <div class="summary-cell summary-value">{{ $report['summary']['returned'] }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Lost Items:</div>
                <div class="summary-cell summary-value">{{ $report['summary']['lost'] }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Damaged Items:</div>
                <div class="summary-cell summary-value">{{ $report['summary']['damaged'] }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Total Value:</div>
                <div class="summary-cell summary-value">PHP {{ number_format($report['summary']['total_value'], 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Issued Value:</div>
                <div class="summary-cell summary-value">PHP {{ number_format($report['summary']['issued_value'], 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-cell summary-label">Lost/Damaged Value:</div>
                <div class="summary-cell summary-value">PHP {{ number_format($report['summary']['lost_damaged_value'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Items Section -->
    <div class="items-section">
        <h2>Item Details</h2>
        @if(count($report['items']) > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 3%;">#</th>
                    <th style="width: 12%;">Item/Unit</th>
                    <th style="width: 10%;">Serial Number</th>
                    <th style="width: 8%;">Model</th>
                    <th style="width: 8%;">Category</th>
                    <th style="width: 6%;">MR #</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 8%;">Issued Date</th>
                    <th style="width: 7%;">Value</th>
                    <th style="width: 8%;">Condition</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['items'] as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $item['unit'] }}</strong><br><small style="color: #666;">{{ strlen($item['description']) > 30 ? substr($item['description'], 0, 30) . '...' : $item['description'] }}</small></td>
                    <td>{{ $item['serial_number'] }}</td>
                    <td>{{ $item['model'] }}</td>
                    <td>{{ $item['category'] }}</td>
                    <td class="text-center">#{{ $item['mr_number'] }}</td>
                    <td class="text-center">
                        <span class="status-{{ strtolower($item['status']) }}">
                            {{ $item['status'] }}
                        </span>
                    </td>
                    <td>{{ $item['issued_date'] ? date('M d, Y', strtotime($item['issued_date'])) : 'N/A' }}</td>
                    <td class="text-right">PHP {{ number_format($item['unit_value'], 2) }}</td>
                    <td>{{ $item['condition'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="text-align: center; color: #666; padding: 20px;">No items found for this personnel.</p>
        @endif
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Generated on: {{ is_object($generated_at) ? $generated_at->format('F d, Y \a\t h:i A') : date('F d, Y \a\t h:i A', strtotime($generated_at)) }}</p>
        <p>This is a system-generated report. For questions, please contact the Property Custodian.</p>
    </div>
</body>
</html>

