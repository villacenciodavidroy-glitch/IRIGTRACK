<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 screen-only">
    <div class="max-w-full mx-auto space-y-5">
      <!-- Header Section -->
      <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-lg shadow-lg overflow-hidden screen-only">
        <div class="px-6 py-5 md:px-8 md:py-6 flex flex-wrap items-center gap-4">
          <button 
            @click="goBack" 
            class="p-2.5 bg-white rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-indigo-600 text-xl md:text-2xl">arrow_back</span>
          </button>
          <div class="p-3 bg-white rounded-lg flex-shrink-0 shadow-md">
            <span class="material-icons-outlined text-indigo-600 text-2xl md:text-3xl">inventory_2</span>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-1">Supply Item Usage Report</h1>
            <p class="text-indigo-100 text-base md:text-lg">Track overall usage for each supply item</p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button 
              @click="exportToExcel" 
              :disabled="loading || rankedSupplies.length === 0"
              class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg text-emerald-700">download</span>
              <span>Export Excel</span>
            </button>
            <button 
              @click.stop.prevent="exportToPDF" 
              :disabled="loading || rankedSupplies.length === 0"
              class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg text-emerald-700">picture_as_pdf</span>
              <span>Export PDF</span>
            </button>
            <button 
              @click="openPrintDialog" 
              :disabled="loading || rankedSupplies.length === 0"
              class="bg-emerald-50 text-emerald-800 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:bg-white transition-all font-semibold shadow-lg border border-white/60 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg text-emerald-700">print</span>
              <span>Print Report</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 p-4 sm:p-6 screen-only">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
            <select
              v-model="selectedYear"
              @change="fetchSupplyUsage"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
              <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
            <select
              v-model="sortBy"
              @change="fetchSupplyUsage"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
              <option value="total_usage">Total Usage</option>
              <option value="avg_usage">Average Usage</option>
              <option value="recent_usage">Recent Usage</option>
            </select>
          </div>
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Top N Items</label>
            <select
              v-model="limit"
              @change="fetchSupplyUsage"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
              <option :value="10">Top 10</option>
              <option :value="20">Top 20</option>
              <option :value="50">Top 50</option>
              <option :value="100">All</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="flex flex-col items-center gap-4">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
          <span class="text-gray-600 dark:text-gray-400 font-medium">Loading supply usage data...</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
        <div class="flex items-center gap-3">
          <span class="material-icons-outlined text-red-500 text-3xl">error_outline</span>
          <div>
            <h3 class="text-red-800 dark:text-red-200 font-semibold text-lg">Error Loading Data</h3>
            <p class="text-red-600 dark:text-red-300 mt-1">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div v-else-if="!loading && !error && summary" class="grid grid-cols-1 md:grid-cols-3 gap-4 screen-only">
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
          <div class="flex items-center justify-between mb-2">
            <span class="text-indigo-100 text-sm font-medium">Total Items Tracked</span>
            <span class="material-icons-outlined text-2xl opacity-80">inventory_2</span>
          </div>
          <div class="text-3xl font-bold">{{ summary.total_items }}</div>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
          <div class="flex items-center justify-between mb-2">
            <span class="text-green-100 text-sm font-medium">Total Usage ({{ selectedYear }})</span>
            <span class="material-icons-outlined text-2xl opacity-80">trending_up</span>
          </div>
          <div class="text-3xl font-bold">{{ formatNumber(summary.total_usage_all) }}</div>
          <div class="text-green-100 text-sm mt-1">units</div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
          <div class="flex items-center justify-between mb-2">
            <span class="text-purple-100 text-sm font-medium">Average Usage</span>
            <span class="material-icons-outlined text-2xl opacity-80">analytics</span>
          </div>
          <div class="text-3xl font-bold">{{ formatNumber(summary.avg_usage_all) }}</div>
          <div class="text-purple-100 text-sm mt-1">units per item</div>
        </div>
      </div>

      <!-- Ranking Table -->
      <div v-if="!loading && !error" class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">Top Supplies by Usage - {{ selectedYear }}</h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Ranked by {{ sortByLabel }} to identify high-consumption items</p>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rank</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Supply Item</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Usage</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg/Quarter</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recent Usage</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trend</th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Quarters</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="(supply, index) in rankedSupplies" 
                :key="supply.item_id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <span 
                      class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                      :class="getRankBadgeClass(index)"
                    >
                      {{ index + 1 }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ supply.item?.unit || `Item ${supply.item_id}` }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ supply.item?.description || 'No description' }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ formatNumber(supply.total_usage) }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">units</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatNumber(supply.avg_usage) }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ formatNumber(supply.recent_usage) }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span 
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium"
                    :class="getTrendBadgeClass(supply.trend)"
                  >
                    <span class="material-icons-outlined text-xs">
                      {{ getTrendIcon(supply.trend) }}
                    </span>
                    {{ supply.trend }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ supply.quarters_count }}/4
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="rankedSupplies.length === 0" class="text-center py-12">
          <span class="material-icons-outlined text-5xl text-gray-300 dark:text-gray-600 mb-4 block">bar_chart</span>
          <p class="text-gray-600 dark:text-gray-400">No usage data available for {{ selectedYear }}</p>
        </div>
      </div>

      <!-- Usage Chart -->
      <div v-if="!loading && !error && rankedSupplies.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden screen-only">
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">Usage Comparison Chart</h2>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Visual comparison of top supplies by total usage</p>
        </div>
        <div class="p-6">
          <div class="h-[400px] w-full">
            <Bar :data="chartData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <!-- Budget Insights Section -->
      <div v-if="!loading && !error && rankedSupplies.length > 0" class="bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-xl shadow-md border-2 border-yellow-200 dark:border-yellow-800 p-6 screen-only">
        <div class="flex items-start gap-4">
          <div class="p-3 bg-yellow-100 dark:bg-yellow-900/50 rounded-lg">
            <span class="material-icons-outlined text-yellow-600 dark:text-yellow-400 text-3xl">warning</span>
          </div>
          <div class="flex-1">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Budget Management Insights</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
              The following supplies have the highest consumption rates and may require closer budget monitoring:
            </p>
            <ul class="space-y-2">
              <li 
                v-for="(supply, index) in top3Supplies" 
                :key="supply.item_id"
                class="flex items-center gap-2 text-sm"
              >
                <span class="font-bold text-yellow-600 dark:text-yellow-400">#{{ index + 1 }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ supply.item?.unit }}</span>
                <span class="text-gray-600 dark:text-gray-400">-</span>
                <span class="text-gray-700 dark:text-gray-300">{{ formatNumber(supply.total_usage) }} units total</span>
                <span 
                  v-if="supply.trend === 'increasing'"
                  class="ml-auto px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium"
                >
                  Increasing Trend
                </span>
              </li>
            </ul>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-4 italic">
              💡 Recommendation: Monitor these items closely and consider bulk purchasing or alternative suppliers to optimize budget allocation.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Print-only optimized layout -->
  <div class="print-only" style="display: none;" v-if="!loading && !error && rankedSupplies.length > 0">
    <div style="padding: 15mm 10mm; font-family: 'Times New Roman', serif; max-width: 100%;">
      <!-- Print Header -->
      <div style="text-align: center; margin-bottom: 15px; border-bottom: 1.5px solid #333; padding-bottom: 12px;">
        <div style="font-size: 14px; font-weight: bold; margin-bottom: 4px; white-space: nowrap;">REPUBLIC OF THE PHILIPPINES</div>
        <div style="font-size: 14px; font-weight: bold; margin-bottom: 4px; white-space: nowrap;">NATIONAL IRRIGATION ADMINISTRATION</div>
        <div style="font-size: 12px; margin-bottom: 8px;">Region X</div>
        <div style="font-size: 16px; font-weight: bold; margin-top: 12px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;">
          SUPPLY ITEM USAGE REPORT
        </div>
        <div style="font-size: 13px; font-weight: bold; margin-top: 4px;">
          Year {{ selectedYear }}
        </div>
      </div>

      <!-- Summary Information -->
      <div v-if="summary" style="margin-bottom: 15px; padding: 8px 12px; background: #f8f8f8; border: 0.5px solid #ccc; border-radius: 3px;">
        <div style="display: table; width: 100%; font-size: 10px;">
          <div style="display: table-row;">
            <div style="display: table-cell; padding-right: 20px;">
              <strong>Total Items Tracked:</strong> {{ summary.total_items }}
            </div>
            <div style="display: table-cell; padding-right: 20px;">
              <strong>Total Usage:</strong> {{ formatNumber(summary.total_usage_all) }} units
            </div>
            <div style="display: table-cell;">
              <strong>Average Usage:</strong> {{ formatNumber(summary.avg_usage_all) }} units/item
            </div>
          </div>
        </div>
      </div>

      <!-- Print Table -->
      <table style="width: 100%; border-collapse: collapse; font-size: 9px; margin-top: 8px; table-layout: fixed;">
        <colgroup>
          <col style="width: 5%;">
          <col style="width: 30%;">
          <col style="width: 12%;">
          <col style="width: 12%;">
          <col style="width: 12%;">
          <col style="width: 12%;">
          <col style="width: 10%;">
        </colgroup>
        <thead>
          <tr style="background: #e8e8e8;">
            <th style="border: 0.5px solid #666; padding: 8px 4px; text-align: center; font-weight: bold; font-size: 9px;">Rank</th>
            <th style="border: 0.5px solid #666; padding: 8px 6px; text-align: left; font-weight: bold; font-size: 9px;">Supply Item</th>
            <th style="border: 0.5px solid #666; padding: 8px 4px; text-align: right; font-weight: bold; font-size: 9px;">Total Usage</th>
            <th style="border: 0.5px solid #666; padding: 8px 4px; text-align: right; font-weight: bold; font-size: 9px;">Avg/Quarter</th>
            <th style="border: 0.5px solid #666; padding: 8px 4px; text-align: right; font-weight: bold; font-size: 9px;">Recent Usage</th>
            <th style="border: 0.5px solid #666; padding: 8px 4px; text-align: center; font-weight: bold; font-size: 9px;">Trend</th>
            <th style="border: 0.5px solid #666; padding: 8px 4px; text-align: center; font-weight: bold; font-size: 9px;">Quarters</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="(supply, index) in rankedSupplies" 
            :key="supply.item_id"
          >
            <td style="border: 0.5px solid #666; padding: 6px 4px; text-align: center; vertical-align: top;">{{ index + 1 }}</td>
            <td style="border: 0.5px solid #666; padding: 6px; text-align: left; vertical-align: top;">
              <div style="font-weight: bold; font-size: 9px; margin-bottom: 2px;">{{ supply.item?.unit || `Item ${supply.item_id}` }}</div>
              <div style="font-size: 8px; color: #555; line-height: 1.2;">{{ (supply.item?.description || 'No description').substring(0, 40) }}{{ (supply.item?.description || '').length > 40 ? '...' : '' }}</div>
            </td>
            <td style="border: 0.5px solid #666; padding: 6px 4px; text-align: right; vertical-align: top;">{{ formatNumber(supply.total_usage) }}</td>
            <td style="border: 0.5px solid #666; padding: 6px 4px; text-align: right; vertical-align: top;">{{ formatNumber(supply.avg_usage) }}</td>
            <td style="border: 0.5px solid #666; padding: 6px 4px; text-align: right; vertical-align: top;">{{ formatNumber(supply.recent_usage) }}</td>
            <td style="border: 0.5px solid #666; padding: 6px 4px; text-align: center; vertical-align: top; text-transform: capitalize; font-size: 8px;">{{ supply.trend || 'stable' }}</td>
            <td style="border: 0.5px solid #666; padding: 6px 4px; text-align: center; vertical-align: top;">{{ supply.quarters_count }}/4</td>
          </tr>
        </tbody>
      </table>

      <!-- Print Footer -->
      <div style="margin-top: 25px; padding-top: 12px; border-top: 1px solid #333; font-size: 9px;">
        <div style="text-align: right; margin-top: 15px;">
          <div style="margin-bottom: 35px;">
            <div style="border-top: 0.5px solid #333; width: 180px; margin-left: auto; padding-top: 4px;">
              <strong>Prepared by:</strong><br>
              {{ signatureData.preparedBy.name || 'Jasper Kim Sales' }}<br>
              {{ signatureData.preparedBy.title || 'Property Officer B' }}
            </div>
          </div>
          <div style="margin-bottom: 35px;">
            <div style="border-top: 0.5px solid #333; width: 180px; margin-left: auto; padding-top: 4px;">
              <strong>Reviewed by:</strong><br>
              {{ signatureData.reviewedBy.name || 'ANA LIZA C. DINOPOL' }}<br>
              {{ signatureData.reviewedBy.title || 'Administrative Services Officer A' }}
            </div>
          </div>
          <div>
            <div style="border-top: 0.5px solid #333; width: 180px; margin-left: auto; padding-top: 4px;">
              <strong>Noted by:</strong><br>
              {{ signatureData.notedBy.name || 'LARRY C. FRANADA' }}<br>
              {{ signatureData.notedBy.title || 'Division Manager A' }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Signature Modal -->
  <div v-if="showSignatureModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 print-hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 space-y-5">
      <div class="flex items-start justify-between">
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Edit Signature Information</h3>
        </div>
        <button @click="showSignatureModal = false" class="text-gray-500 hover:text-gray-700">
          <span class="material-icons-outlined">close</span>
        </button>
      </div>

      <div class="grid gap-6">
        <div class="space-y-3">
          <p class="text-sm font-semibold text-gray-800">Prepared by:</p>
          <input v-model="signatureData.preparedBy.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          <input v-model="signatureData.preparedBy.title" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
        </div>
        <div class="space-y-3">
          <p class="text-sm font-semibold text-gray-800">Reviewed by:</p>
          <input v-model="signatureData.reviewedBy.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          <input v-model="signatureData.reviewedBy.title" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
        </div>
        <div class="space-y-3">
          <p class="text-sm font-semibold text-gray-800">Noted by:</p>
          <input v-model="signatureData.notedBy.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          <input v-model="signatureData.notedBy.title" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <button @click="showSignatureModal = false" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
          Cancel
        </button>
        <button @click="printReport" class="px-5 py-2.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow">
          Print Report
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;
}
</style>

<style>
/* Global Print Styles - Must be unscoped to affect layout */
@media print {
  /* Hide screen content */
  .screen-only {
    display: none !important;
  }
  
  /* Show print content */
  .print-only {
    display: block !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    max-width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    z-index: 99999 !important;
    overflow: visible !important;
  }
  
  /* Hide everything except print-only */
  body > *:not(.print-only),
  html > *:not(.print-only) {
    display: none !important;
  }
  
  /* Global body styles */
  body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: visible !important;
  }
  
  /* Hide ALL layout and navigation elements - Very aggressive selectors */
  body > *:not(.print-only),
  body > div > *:not(.print-only),
  aside,
  .sidebar,
  nav:not(.print-only nav),
  header:not(.print-only header),
  .header:not(.print-only .header),
  .topbar,
  .navbar,
  .navigation,
  .sidebar-menu,
  .ant-layout-sider,
  .layout__sidebar,
  .layout-sider,
  .layout-menu,
  .el-menu,
  [class*="sidebar"]:not(.print-only),
  [class*="nav"]:not(.print-only),
  [class*="header"]:not(.print-only),
  [id*="sidebar"],
  [id*="nav"],
  [id*="header"],
  div[class*="min-h-screen"]:not(.print-only),
  div[class*="bg-gray"]:not(.print-only) {
    display: none !important;
    visibility: hidden !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    opacity: 0 !important;
    position: absolute !important;
    left: -9999px !important;
  }
  
  /* Hide buttons and interactive elements outside print content */
  body > *:not(.print-only) button,
  body > *:not(.print-only) .btn,
  body > *:not(.print-only) [class*="button"] {
    display: none !important;
  }
  
  /* Hide layout containers but keep content */
  .ant-layout,
  .layout,
  .layout-content,
  .main-content,
  .content-wrapper,
  div[class*="ml-64"],
  div[class*="lg:ml-64"] {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    margin-left: 0 !important;
  }
  
  /* Hide any element with sidebar in class or id */
  *[class*="sidebar"]:not(.print-only),
  *[id*="sidebar"],
  *[class*="nav"]:not(.print-only),
  *[id*="nav"] {
    display: none !important;
    visibility: hidden !important;
    width: 0 !important;
    height: 0 !important;
  }
  
  /* Hide the main layout wrapper */
  body > div:first-child:not(.print-only),
  body > div > div:not(.print-only) {
    display: none !important;
  }
  
  /* Print page setup */
  @page {
    size: A4 portrait;
    margin: 15mm 10mm;
    /* Suppress browser headers and footers - Note: Users may need to disable headers/footers in browser print settings */
    marks: none;
  }
  
  .print-only {
    page-break-inside: avoid;
  }
  
  .print-only table {
    page-break-inside: auto;
    width: 100%;
  }
  
  .print-only tr {
    page-break-inside: avoid;
    page-break-after: auto;
  }
  
  .print-only thead {
    display: table-header-group;
  }
  
  .print-only tfoot {
    display: table-footer-group;
  }
  
  .print-only th,
  .print-only td {
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
}
</style>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import axiosClient from '../../axios'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

const router = useRouter()

const goBack = () => {
  router.back()
}

// Signature modal state for print
const showSignatureModal = ref(false)
const signatureData = ref({
  preparedBy: {
    name: 'Jasper Kim Sales',
    title: 'Property Officer B'
  },
  reviewedBy: {
    name: 'ANA LIZA C. DINOPOL',
    title: 'Administrative Services Officer A'
  },
  notedBy: {
    name: 'LARRY C. FRANADA',
    title: 'Division Manager A'
  }
})

// State
const rankedSupplies = ref([])
const summary = ref(null)
const loading = ref(false)
const error = ref(null)
const selectedYear = ref(new Date().getFullYear())
const sortBy = ref('total_usage')
const limit = ref(20)

// Available years
const availableYears = computed(() => {
  const current = new Date().getFullYear()
  const startYear = 2024
  const years = []
  for (let year = startYear; year <= current; year++) {
    years.push(year)
  }
  return years.reverse()
})

// Computed
const sortByLabel = computed(() => {
  const labels = {
    'total_usage': 'Total Usage',
    'avg_usage': 'Average Usage',
    'recent_usage': 'Recent Usage'
  }
  return labels[sortBy.value] || 'Total Usage'
})

const top3Supplies = computed(() => {
  return rankedSupplies.value.slice(0, 3)
})

const chartData = computed(() => {
  const top10 = rankedSupplies.value.slice(0, 10)
  return {
    labels: top10.map(s => s.item?.unit || `Item ${s.item_id}`),
    datasets: [{
      label: 'Total Usage',
      data: top10.map(s => s.total_usage),
      backgroundColor: [
        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
        '#06B6D4', '#F97316', '#84CC16', '#EC4899', '#6366F1'
      ],
      barPercentage: 0.7,
      categoryPercentage: 0.8
    }]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          return `${context.parsed.y.toLocaleString()} units`
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return value.toLocaleString()
        }
      }
    }
  }
}

// Methods
const fetchSupplyUsage = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await axiosClient.get('/usage/ranking', {
      params: {
        year: selectedYear.value,
        sort_by: sortBy.value,
        limit: limit.value
      }
    })
    
    if (response.data.success) {
      rankedSupplies.value = response.data.data || []
      summary.value = response.data.summary || null
    } else {
      error.value = response.data.message || 'Failed to fetch supply usage data'
    }
  } catch (err) {
    console.error('Error fetching supply usage:', err)
    error.value = err.response?.data?.message || 'Failed to fetch supply usage ranking'
  } finally {
    loading.value = false
  }
}

const formatNumber = (num) => {
  if (num == null) return '0'
  return Number(num).toLocaleString()
}

const getRankBadgeClass = (index) => {
  if (index === 0) return 'bg-yellow-500 text-white'
  if (index === 1) return 'bg-gray-400 text-white'
  if (index === 2) return 'bg-orange-600 text-white'
  return 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
}

const getTrendBadgeClass = (trend) => {
  const classes = {
    'increasing': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
    'decreasing': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    'stable': 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
  }
  return classes[trend] || classes.stable
}

const getTrendIcon = (trend) => {
  const icons = {
    'increasing': 'trending_up',
    'decreasing': 'trending_down',
    'stable': 'trending_flat'
  }
  return icons[trend] || 'trending_flat'
}

// Export to Excel
const exportToExcel = async () => {
  try {
    if (rankedSupplies.value.length === 0) {
      alert('No supply usage data to export')
      return
    }
    
    // Use ranking export endpoint as it has similar data structure
    // Or fallback to CSV if endpoint doesn't exist
    try {
      const params = new URLSearchParams()
      params.append('year', selectedYear.value)
      params.append('sort_by', sortBy.value)
      params.append('limit', limit.value)
      
      const baseUrl = `/usage/ranking/export-excel`
      const fullUrl = `${baseUrl}?${params.toString()}`
      
      let response
      if (fullUrl.length > 1800) {
        response = await axiosClient.get(baseUrl, {
          params: {
            year: selectedYear.value,
            sort_by: sortBy.value,
            limit: limit.value
          },
          responseType: 'blob',
          headers: {
            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
          },
          timeout: 60000
        })
      } else {
        response = await axiosClient.get(fullUrl, {
          responseType: 'blob',
          headers: {
            'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
          },
          timeout: 60000
        })
      }
      
      const blob = new Blob([response.data], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      })
      
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `Supply_Item_Usage_${selectedYear.value}_${new Date().toISOString().split('T')[0]}.xlsx`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (apiError) {
      // Fallback to CSV if Excel export fails
      console.warn('Excel export failed, using CSV fallback:', apiError)
      exportToCSV()
    }
  } catch (error) {
    console.error('Error exporting to Excel:', error)
    exportToCSV()
  }
}

// Fallback CSV export
const exportToCSV = () => {
  const headers = ['Rank', 'Supply Item', 'Description', 'Total Usage', 'Avg/Quarter', 'Recent Usage', 'Trend', 'Quarters']
  const rows = rankedSupplies.value.map((supply, index) => [
    index + 1,
    supply.item?.unit || `Item ${supply.item_id}`,
    supply.item?.description || 'N/A',
    supply.total_usage,
    supply.avg_usage,
    supply.recent_usage,
    supply.trend,
    `${supply.quarters_count}/4`
  ])
  
  const csvContent = [
    `Supply Item Usage Report - ${selectedYear.value}`,
    `Generated: ${new Date().toLocaleString()}`,
    '',
    headers.join(','),
    ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
  ].join('\n')
  
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  link.setAttribute('href', url)
  link.setAttribute('download', `supply-usage-${selectedYear.value}.csv`)
  link.style.visibility = 'hidden'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// Export to PDF
const exportToPDF = async () => {
  try {
    if (rankedSupplies.value.length === 0) {
      alert('No supply usage data to export')
      return
    }
    
    // Use ranking export endpoint as it has similar data structure
    const params = new URLSearchParams()
    params.append('year', selectedYear.value)
    params.append('sort_by', sortBy.value)
    params.append('limit', limit.value)
    
    const baseUrl = `/usage/ranking/export-pdf`
    const fullUrl = `${baseUrl}?${params.toString()}`
    
    let response
    if (fullUrl.length > 1800) {
      response = await axiosClient.get(baseUrl, {
        params: {
          year: selectedYear.value,
          sort_by: sortBy.value,
          limit: limit.value
        },
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf'
        },
        timeout: 60000
      })
    } else {
      response = await axiosClient.get(fullUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf'
        },
        timeout: 60000
      })
    }
    
    const blob = new Blob([response.data], {
      type: 'application/pdf'
    })
    
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Supply_Item_Usage_${selectedYear.value}_${new Date().toISOString().split('T')[0]}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting to PDF:', error)
    alert('Failed to export to PDF. Please try again.')
  }
}

// Print Report - show signature modal first
const openPrintDialog = () => {
  showSignatureModal.value = true
}

// Print report with formatted document
const printReport = () => {
  showSignatureModal.value = false
  
  // Get the print-only content
  const printContent = document.querySelector('.print-only')
  if (!printContent) {
    window.print()
    return
  }
  
  // Create a new window with only the print content
  const printWindow = window.open('', '_blank', 'width=800,height=600')
  if (!printWindow) {
    // Fallback to regular print if popup is blocked
    window.print()
    return
  }
  
  // Clone the print content (Vue will have already rendered it with the signature data)
  const content = printContent.cloneNode(true)
  content.style.display = 'block'
  content.style.position = 'relative'
  content.style.width = '100%'
  
  // Write to new window - break up script tag to avoid Vue parser issues
  const scriptTagStart = '<scr' + 'ipt>'
  const scriptTagEnd = '</scr' + 'ipt>'
  
  const htmlContent = '<!DOCTYPE html>' +
    '<html>' +
    '<head>' +
    '<title>Supply Item Usage Report - ' + selectedYear.value + '</title>' +
    '<style>' +
    '@page { size: A4 portrait; margin: 15mm 10mm; }' +
    'body { margin: 0; padding: 0; font-family: "Times New Roman", serif; }' +
    '* { box-sizing: border-box; }' +
    '</style>' +
    '</head>' +
    '<body>' +
    content.outerHTML +
    scriptTagStart +
    'window.onload = function() {' +
    '  window.print();' +
    '  window.onafterprint = function() {' +
    '    window.close();' +
    '  };' +
    '};' +
    scriptTagEnd +
    '</body>' +
    '</html>'
  
  printWindow.document.write(htmlContent)
  printWindow.document.close()
}

// Lifecycle
onMounted(() => {
  fetchSupplyUsage()
})
</script>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;
}

/* Print Styles */
@media print {
  /* Hide screen content */
  .screen-only {
    display: none !important;
  }
  
  /* Show print content */
  .print-only {
    display: block !important;
  }
  
  /* Global body styles */
  body {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  
  /* Hide ALL layout and navigation elements */
  aside,
  .sidebar,
  nav,
  header,
  .header,
  .topbar,
  .navbar,
  .navigation,
  .sidebar-menu,
  .ant-layout-sider,
  .layout__sidebar,
  .layout-sider,
  .layout-menu,
  .el-menu,
  [class*="sidebar"],
  [class*="nav"],
  [class*="header"],
  [id*="sidebar"],
  [id*="nav"],
  [id*="header"] {
    display: none !important;
    visibility: hidden !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
  }
  
  /* Hide layout containers */
  .ant-layout,
  .layout,
  .layout-content,
  .main-content,
  .content-wrapper {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
  }
  
  /* Ensure print-only content takes full width */
  .print-only {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
  }
  
  /* Print page setup */
  @page {
    size: A4 portrait;
    margin: 15mm 10mm;
    /* Suppress browser headers and footers - Note: Users may need to disable headers/footers in browser print settings */
    marks: none;
  }
  
  .print-only {
    page-break-inside: avoid;
  }
  
  .print-only table {
    page-break-inside: auto;
    width: 100%;
  }
  
  .print-only tr {
    page-break-inside: avoid;
    page-break-after: auto;
  }
  
  .print-only thead {
    display: table-header-group;
  }
  
  .print-only tfoot {
    display: table-footer-group;
  }
  
  .print-only th,
  .print-only td {
    word-wrap: break-word;
    overflow-wrap: break-word;
  }
}
</style>

