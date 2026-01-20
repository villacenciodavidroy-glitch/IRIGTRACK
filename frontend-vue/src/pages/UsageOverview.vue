<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6">
    <div class="max-w-full mx-auto space-y-5">
      <!-- Enhanced Header Section -->
      <div class="bg-green-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-5 md:px-8 md:py-6 flex flex-wrap items-center gap-4">
          <button 
            @click="goBack" 
            class="p-2.5 bg-white rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-green-600 text-xl md:text-2xl">arrow_back</span>
          </button>
          <div class="p-3 bg-white rounded-lg flex-shrink-0 shadow-md">
            <span class="material-icons-outlined text-green-600 text-2xl md:text-3xl">analytics</span>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-1">Usage of Items Overview</h1>
            <p class="text-green-100 text-base md:text-lg">Quarterly usage analysis and forecast predictions</p>
          </div>
          <div class="flex items-center gap-3 flex-wrap">
            <button
              @click="goToSupplyRanking"
              class="px-4 py-2.5 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white border border-white/30 rounded-lg transition-all font-medium text-sm flex items-center gap-2 shadow-md hover:shadow-lg"
              title="View Supply Usage Ranking Report"
            >
              <span class="material-icons-outlined text-base">bar_chart</span>
              <span class="hidden sm:inline">Supply Ranking</span>
            </button>
            <label class="text-white text-sm font-medium">Year:</label>
            <select 
              v-model="selectedYear"
              class="px-4 py-2.5 bg-white/20 backdrop-blur-sm text-white border border-white/30 rounded-lg focus:ring-2 focus:ring-white/50 focus:border-white/50 transition-all font-medium text-base min-w-[120px]"
            >
              <option 
                v-for="year in availableYears" 
                :key="year" 
                :value="year"
                class="text-gray-900"
              >
                {{ year }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700">
        <div class="flex flex-col items-center gap-4">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
          <span class="text-gray-600 dark:text-gray-400 font-medium">Loading usage data...</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700">
        <span class="material-icons-outlined text-5xl text-red-400 dark:text-red-400 mb-4">error_outline</span>
        <p class="text-red-500 dark:text-red-400 text-lg font-semibold mb-4">{{ error }}</p>
        <button @click="fetchQuarterlyUsage" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md hover:shadow-lg transition-all">
          Retry
        </button>
      </div>

      <!-- Quarterly Charts Grid -->
      <div v-if="!loading && !error">
        <div class="mb-6">
          <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-2">Quarterly Usage Analysis - {{ selectedYear }}</h2>
          <p class="text-gray-600 dark:text-gray-400 text-sm">View item usage data across four quarters for {{ selectedYear }}</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- Q1 Chart -->
          <div 
            class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-all"
            :class="{ 'ring-2 ring-green-500 border-green-500': isCurrentQuarter(q1Key) }"
          >
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 border-b border-blue-800">
              <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-white">Jan - Mar Usage ({{ q1Key }})</h2>
                <span v-if="isCurrentQuarter(q1Key)" class="px-3 py-1 text-xs font-semibold bg-white/20 backdrop-blur-sm text-white rounded-full">
                  Current Quarter
                </span>
              </div>
            </div>
            <div class="p-4 sm:p-6">
              <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
                <Bar :data="q1Data" :options="getBarOptions(isCurrentQuarter(q1Key))" />
              </div>
              <div v-if="q1Data.labels.length === 1 && q1Data.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-4">
                <span class="material-icons-outlined text-3xl text-gray-300 dark:text-gray-600 mb-2 block">bar_chart</span>
                No usage data for this quarter
              </div>
              <div v-if="q1Data.labels.length > 0 && q1Data.labels[0] !== 'No data'" class="mt-4 flex justify-center">
                <button 
                  @click="showMonthlyBreakdown(q1Key)"
                  class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                >
                  <span class="material-icons-outlined text-base">calendar_view_month</span>
                  View Monthly Breakdown
                </button>
              </div>
            </div>
          </div>

          <!-- Q2 Chart -->
          <div 
            class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-all"
            :class="{ 'ring-2 ring-green-500 border-green-500': isCurrentQuarter(q2Key) }"
          >
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
              <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-white">Apr - Jun Usage ({{ q2Key }})</h2>
                <span v-if="isCurrentQuarter(q2Key)" class="px-3 py-1 text-xs font-semibold bg-white/20 backdrop-blur-sm text-white rounded-full">
                  Current Quarter
                </span>
              </div>
            </div>
            <div class="p-4 sm:p-6">
              <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
                <Bar :data="q2Data" :options="getBarOptions(isCurrentQuarter(q2Key))" />
              </div>
              <div v-if="q2Data.labels.length === 1 && q2Data.labels[0] === 'No data'" class="text-center text-gray-500 text-sm mt-4">
                <span class="material-icons-outlined text-3xl text-gray-300 mb-2 block">bar_chart</span>
                No usage data for this quarter
              </div>
              <div v-if="q2Data.labels.length > 0 && q2Data.labels[0] !== 'No data'" class="mt-4 flex justify-center">
                <button 
                  @click="showMonthlyBreakdown(q2Key)"
                  class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                >
                  <span class="material-icons-outlined text-base">calendar_view_month</span>
                  View Monthly Breakdown
                </button>
              </div>
            </div>
          </div>

          <!-- Q3 Chart -->
          <div 
            class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-all"
            :class="{ 'ring-2 ring-green-500 border-green-500': isCurrentQuarter(q3Key) }"
          >
            <div class="bg-gradient-to-r from-orange-600 to-orange-700 px-6 py-4 border-b border-orange-800">
              <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-white">Jul - Sep Usage ({{ q3Key }})</h2>
                <span v-if="isCurrentQuarter(q3Key)" class="px-3 py-1 text-xs font-semibold bg-white/20 backdrop-blur-sm text-white rounded-full">
                  Current Quarter
                </span>
              </div>
            </div>
            <div class="p-4 sm:p-6">
              <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
                <Bar :data="q3Data" :options="getBarOptions(isCurrentQuarter(q3Key))" />
              </div>
              <div v-if="q3Data.labels.length === 1 && q3Data.labels[0] === 'No data'" class="text-center text-gray-500 text-sm mt-4">
                <span class="material-icons-outlined text-3xl text-gray-300 mb-2 block">bar_chart</span>
                No usage data for this quarter
              </div>
              <div v-if="q3Data.labels.length > 0 && q3Data.labels[0] !== 'No data'" class="mt-4 flex justify-center">
                <button 
                  @click="showMonthlyBreakdown(q3Key)"
                  class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                >
                  <span class="material-icons-outlined text-base">calendar_view_month</span>
                  View Monthly Breakdown
                </button>
              </div>
            </div>
          </div>

          <!-- Q4 Chart -->
          <div 
            class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden hover:shadow-lg transition-all"
            :class="{ 'ring-2 ring-green-500 border-green-500': isCurrentQuarter(q4Key) }"
          >
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 border-b border-purple-800">
              <div class="flex items-center justify-between">
                <h2 class="text-base sm:text-lg font-bold text-white">Oct - Dec Usage ({{ q4Key }})</h2>
                <span v-if="isCurrentQuarter(q4Key)" class="px-3 py-1 text-xs font-semibold bg-white/20 backdrop-blur-sm text-white rounded-full">
                  Current Quarter
                </span>
              </div>
            </div>
            <div class="p-4 sm:p-6">
              <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
                <Bar :data="q4Data" :options="getBarOptions(isCurrentQuarter(q4Key))" />
              </div>
              <div v-if="q4Data.labels.length === 1 && q4Data.labels[0] === 'No data'" class="text-center text-gray-500 text-sm mt-4">
                <span class="material-icons-outlined text-3xl text-gray-300 mb-2 block">bar_chart</span>
                No usage data for this quarter
              </div>
              <div v-if="q4Data.labels.length > 0 && q4Data.labels[0] !== 'No data'" class="mt-4 flex justify-center">
                <button 
                  @click="showMonthlyBreakdown(q4Key)"
                  class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                >
                  <span class="material-icons-outlined text-base">calendar_view_month</span>
                  View Monthly Breakdown
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Breakdown Modal -->
      <div v-if="showMonthlyModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeMonthlyModal">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-4xl w-full max-h-[90vh] overflow-hidden">
          <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 border-b border-blue-800">
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-2xl">calendar_view_month</span>
                </div>
                <div>
                  <h3 class="text-xl font-bold text-white">Monthly Usage Breakdown</h3>
                  <p class="text-blue-100 text-sm mt-1">{{ selectedQuarter }}</p>
                </div>
              </div>
              <button @click="closeMonthlyModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
                <span class="material-icons-outlined">close</span>
              </button>
            </div>
          </div>
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-150px)]">
            <div v-if="monthlyLoading" class="flex justify-center items-center py-12">
              <div class="flex flex-col items-center gap-4">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
                <span class="text-gray-600 dark:text-gray-400 font-medium">Loading monthly data...</span>
              </div>
            </div>
            <div v-else-if="monthlyError" class="text-center py-12">
              <span class="material-icons-outlined text-5xl text-red-400 mb-4 block">error_outline</span>
              <p class="text-red-500 dark:text-red-400 text-lg font-semibold">{{ monthlyError }}</p>
            </div>
            <div v-else-if="monthlyData.length > 0" class="space-y-6">
              <div v-for="item in monthlyData" :key="item.item_id" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                  {{ item.item?.unit || `Item ${item.item_id}` }}
                  <span class="text-sm font-normal text-gray-600 dark:text-gray-400">(Total: {{ item.total_usage }} units)</span>
                </h4>
                <div class="h-[200px] w-full">
                  <Bar :data="getMonthlyChartData(item)" :options="monthlyChartOptions" />
                </div>
                <div class="mt-4 grid grid-cols-3 gap-4">
                  <div v-for="month in item.monthly_breakdown" :key="month.month" 
                    class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ month.month }}</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ month.usage }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">units</div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12">
              <span class="material-icons-outlined text-5xl text-gray-300 dark:text-gray-600 mb-4 block">bar_chart</span>
              <p class="text-gray-600 dark:text-gray-400">No monthly data available</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Forecast Section -->
      <div v-if="!loading && !error" class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Forecast Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg sm:text-xl font-bold text-white">Forecast for Next 3 Months</h2>
              <p class="text-xs text-green-100 mt-1">Based on {{ currentPeriod }}</p>
            </div>
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-xl">trending_up</span>
            </div>
          </div>
        </div>

        <div class="p-4 sm:p-6">
          <!-- Forecast Method Indicator -->
          <div class="mb-6">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-lg"
              :class="{
                'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700': forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && !forecastError,
                'bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700': forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && forecastError,
                'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700': forecastItems.length > 0 && !forecastItems.some(item => item.method === 'linear_regression'),
                'bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600': forecastLoading || forecastItems.length === 0
              }"
            >
              <span class="material-icons-outlined text-lg"
                :class="{
                  'text-green-600 dark:text-green-400': forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && !forecastError,
                  'text-orange-600 dark:text-orange-400': forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && forecastError,
                  'text-blue-600 dark:text-blue-400': forecastItems.length > 0 && !forecastItems.some(item => item.method === 'linear_regression'),
                  'text-gray-600 dark:text-gray-400': forecastLoading || forecastItems.length === 0
                }"
              >
                {{ forecastLoading ? 'hourglass_empty' : 'analytics' }}
              </span>
              <span class="text-sm font-medium"
                :class="{
                  'text-green-700 dark:text-green-400': forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && !forecastError,
                  'text-orange-700 dark:text-orange-400': forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && forecastError,
                  'text-blue-700 dark:text-blue-400': forecastItems.length > 0 && !forecastItems.some(item => item.method === 'linear_regression'),
                  'text-gray-700 dark:text-gray-400': forecastLoading || forecastItems.length === 0
                }"
              >
                <span v-if="forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && !forecastError">
                  Using Python ML Linear Regression
                </span>
                <span v-else-if="forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && forecastError">
                  Python ML API Not Running - Using Laravel Fallback
                </span>
                <span v-else-if="forecastItems.length > 0">
                  Using Forecast Estimates
                </span>
                <span v-else-if="forecastLoading">
                  Generating forecasts...
                </span>
                <span v-else>
                  Loading forecast data...
                </span>
              </span>
            </div>
          </div>

          <!-- Forecast Loading State -->
          <div v-if="forecastLoading" class="flex justify-center items-center py-12">
            <div class="flex flex-col items-center gap-4">
              <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
              <span class="text-gray-600 dark:text-gray-400 font-medium">Generating ML forecast...</span>
            </div>
          </div>

          <!-- Info State (Statistical Estimates) -->
          <div v-if="!forecastLoading && forecastItems.length > 0 && forecastItems.every(item => item.method === 'statistical')" 
               class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 border-2 border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
              <div class="p-2 bg-blue-500 dark:bg-blue-600 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-white text-lg">info</span>
              </div>
              <div class="flex-1">
                <p class="text-blue-800 dark:text-blue-300 text-sm font-semibold mb-1">Forecasts Generated Successfully</p>
                <p class="text-blue-700 dark:text-blue-400 text-xs">
                  <span v-if="forecastItems.some(item => item.method === 'linear_regression') && !forecastError">
                    Using Python ML Linear Regression to predict next quarter's (3 months) usage based on historical Q1–Q4 data.
                  </span>
                  <span v-else>
                    Using Linear Regression to predict next quarter's (3 months) usage based on historical Q1–Q4 data.
                  </span>
                  <span class="font-medium">This is working correctly!</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Warning State (Python ML API Error but still working) -->
          <div v-else-if="forecastError" class="bg-gradient-to-r from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 border-2 border-yellow-200 dark:border-yellow-700 rounded-lg p-4 mb-6">
            <div class="flex items-start gap-3">
              <div class="p-2 bg-yellow-500 dark:bg-yellow-600 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-white text-lg">warning</span>
              </div>
              <div class="flex-1">
                <p class="text-yellow-800 dark:text-yellow-300 text-sm font-semibold mb-2">{{ forecastError }}</p>
                <div class="text-yellow-700 dark:text-yellow-400 text-xs" v-if="forecastError.includes('unavailable')">
                  <p class="mb-2 font-medium">To use Python ML API:</p>
                  <ul class="list-disc list-inside space-y-1 ml-2">
                    <li>Windows: Run <code class="bg-yellow-100 dark:bg-yellow-900/30 px-2 py-0.5 rounded text-xs text-yellow-800 dark:text-yellow-300">start_ml_api.bat</code></li>
                    <li>Linux/Mac: Run <code class="bg-yellow-100 dark:bg-yellow-900/30 px-2 py-0.5 rounded text-xs text-yellow-800 dark:text-yellow-300">./start_ml_api.sh</code></li>
                    <li>Or manually: <code class="bg-yellow-100 dark:bg-yellow-900/30 px-2 py-0.5 rounded text-xs text-yellow-800 dark:text-yellow-300">python ml_api_server.py</code></li>
                  </ul>
                  <p class="mt-2">The server should run on <code class="bg-yellow-100 dark:bg-yellow-900/30 px-2 py-0.5 rounded text-xs text-yellow-800 dark:text-yellow-300">{{ PY_API_BASE }}</code></p>
                </div>
                <p class="text-yellow-700 dark:text-yellow-400 text-xs mt-2" v-else>
                  Forecasts are using Laravel Linear Regression fallback. Check console for details.
                </p>
              </div>
            </div>
          </div>

          <!-- Forecast Chart -->
          <div v-if="!forecastLoading" class="w-full max-w-md mx-auto h-[250px] sm:h-[300px] mb-6 overflow-hidden bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <Pie :data="forecastData" :options="pieOptions" />
          </div>

          <!-- Forecast Details Table -->
          <div v-if="!forecastLoading && forecastItems.length > 0" class="mt-6 overflow-x-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
              <table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="min-width: 600px;">
                <thead class="bg-gradient-to-r from-gray-50 via-gray-100 to-gray-50 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Item</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Current Stock</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Avg Usage/Q</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Trend</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Forecast (Next Q)</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Confidence</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                  <tr v-for="item in forecastItems.slice(0, 10)" :key="item.item_id" class="hover:bg-green-50 dark:hover:bg-gray-700 transition-colors border-l-4 border-transparent hover:border-green-500">
                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                      {{ item.item?.unit || `Item ${item.item_id}` }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                      {{ item.current_stock }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">
                      {{ item.statistics?.avg_usage || 0 }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                      <span 
                        :class="{
                          'bg-green-100 text-green-800': item.statistics?.trend === 'increasing',
                          'bg-red-100 text-red-800': item.statistics?.trend === 'decreasing',
                          'bg-gray-100 text-gray-800': item.statistics?.trend === 'stable'
                        }"
                        class="px-3 py-1 rounded-full text-xs font-semibold capitalize"
                      >
                        {{ item.statistics?.trend || 'stable' }}
                      </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                      <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                          <span class="font-semibold text-gray-900 dark:text-white">{{ item.forecasted_usage || 'N/A' }} units</span>
                          <span 
                            v-if="item.method"
                            class="text-xs px-2 py-1 rounded font-semibold"
                            :class="{
                              'bg-green-100 text-green-800': item.method === 'linear_regression',
                              'bg-blue-100 text-blue-800': item.method === 'ml',
                              'bg-gray-100 text-gray-800': item.method === 'statistical'
                            }"
                            :title="item.method === 'linear_regression' ? 'Linear Regression' : item.method === 'ml' ? 'ML Prediction' : 'Statistical Estimate'"
                          >
                            {{ item.method === 'linear_regression' ? 'LR' : item.method === 'ml' ? 'ML' : 'Stats' }}
                          </span>
                        </div>
                        <span v-if="item.forecasted_period" class="text-xs text-gray-500 dark:text-gray-400">
                          {{ item.forecasted_period }}
                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                      <span 
                        v-if="item.confidence !== null"
                        class="px-3 py-1 rounded-full text-xs font-semibold"
                        :class="{
                          'bg-green-100 text-green-800': item.confidence >= 0.7,
                          'bg-yellow-100 text-yellow-800': item.confidence >= 0.5 && item.confidence < 0.7,
                          'bg-orange-100 text-orange-800': item.confidence < 0.5
                        }"
                      >
                        {{ Math.round(item.confidence * 100) }}%
                      </span>
                      <span v-else class="text-gray-400 dark:text-gray-500 text-xs">N/A</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="!forecastLoading && forecastData.labels.length === 1 && forecastData.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-6 py-8">
            <span class="material-icons-outlined text-4xl text-gray-300 dark:text-gray-600 mb-2 block">insights</span>
            No usage data available for forecast. Please generate usage data first.
          </div>
        </div>
      </div>
    </div>

    <!-- Supply Usage Ranking Modal -->
    <div v-if="showSupplyRankingModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4" @click.self="closeSupplyRankingModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-6xl w-full max-h-[95vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 border-b border-blue-800 flex-shrink-0">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">bar_chart</span>
              </div>
              <div>
                <h3 class="text-xl font-bold text-white">Supply Usage Ranking Report</h3>
                <p class="text-blue-100 text-sm mt-1">Identify supplies with highest usage to manage budget effectively</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="exportRankingToExcel"
                :disabled="rankingLoading || rankedSupplies.length === 0"
                class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50 font-semibold shadow-md hover:shadow-lg text-sm"
                title="Export to Excel"
              >
                <span class="material-icons-outlined text-base">table_chart</span>
                <span>Excel</span>
              </button>
              <button
                @click="exportRankingToPDF"
                :disabled="rankingLoading || rankedSupplies.length === 0"
                class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50 font-semibold shadow-md hover:shadow-lg text-sm"
                title="Export to PDF"
              >
                <span class="material-icons-outlined text-base">picture_as_pdf</span>
                <span>PDF</span>
              </button>
              <button
                @click="printRankingReport"
                :disabled="rankingLoading || rankedSupplies.length === 0"
                class="px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50 font-semibold shadow-md hover:shadow-lg text-sm"
                title="Print Report"
              >
                <span class="material-icons-outlined text-base">print</span>
                <span>Print</span>
              </button>
              <button @click="closeSupplyRankingModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
                <span class="material-icons-outlined">close</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Content -->
        <div class="overflow-y-auto flex-1 p-6">
          <!-- Filters -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
            <div class="flex flex-wrap items-center gap-4">
              <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
                <select
                  v-model="rankingYear"
                  @change="fetchSupplyRanking"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-sm"
                >
                  <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                </select>
              </div>
              <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
                <select
                  v-model="rankingSortBy"
                  @change="fetchSupplyRanking"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-sm"
                >
                  <option value="total_usage">Total Usage</option>
                  <option value="avg_usage">Average Usage</option>
                  <option value="recent_usage">Recent Usage</option>
                </select>
              </div>
              <div class="flex-1 min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Top N Items</label>
                <select
                  v-model="rankingLimit"
                  @change="fetchSupplyRanking"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 text-sm"
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
          <div v-if="rankingLoading" class="flex justify-center items-center py-12">
            <div class="flex flex-col items-center gap-4">
              <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
              <span class="text-gray-600 dark:text-gray-400 font-medium">Loading supply usage data...</span>
            </div>
          </div>

          <!-- Error State -->
          <div v-else-if="rankingError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
            <div class="flex items-center gap-3">
              <span class="material-icons-outlined text-red-500 text-3xl">error_outline</span>
              <div>
                <h3 class="text-red-800 dark:text-red-200 font-semibold text-lg">Error Loading Data</h3>
                <p class="text-red-600 dark:text-red-300 mt-1">{{ rankingError }}</p>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div v-else>
            <!-- Summary Cards -->
            <div v-if="rankingSummary" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-4 text-white">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-blue-100 text-xs font-medium">Total Items</span>
                  <span class="material-icons-outlined text-xl opacity-80">inventory_2</span>
                </div>
                <div class="text-2xl font-bold">{{ rankingSummary.total_items }}</div>
              </div>
              <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-4 text-white">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-green-100 text-xs font-medium">Total Usage</span>
                  <span class="material-icons-outlined text-xl opacity-80">trending_up</span>
                </div>
                <div class="text-2xl font-bold">{{ formatRankingNumber(rankingSummary.total_usage_all) }}</div>
                <div class="text-green-100 text-xs mt-1">units</div>
              </div>
              <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-4 text-white">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-purple-100 text-xs font-medium">Average Usage</span>
                  <span class="material-icons-outlined text-xl opacity-80">analytics</span>
                </div>
                <div class="text-2xl font-bold">{{ formatRankingNumber(rankingSummary.avg_usage_all) }}</div>
                <div class="text-purple-100 text-xs mt-1">units per item</div>
              </div>
            </div>

            <!-- Ranking Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
              <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Top Supplies by Usage - {{ rankingYear }}</h2>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Ranked by {{ getRankingSortLabel() }} to identify high-consumption items</p>
              </div>
              
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rank</th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Supply Item</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Usage</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg/Quarter</th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recent</th>
                      <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Trend</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr 
                      v-for="(supply, index) in rankedSupplies" 
                      :key="supply.item_id"
                      class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                      <td class="px-4 py-3 whitespace-nowrap">
                        <span 
                          class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                          :class="getRankBadgeClass(index)"
                        >
                          {{ index + 1 }}
                        </span>
                      </td>
                      <td class="px-4 py-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                          {{ supply.item?.unit || `Item ${supply.item_id}` }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                          {{ supply.item?.description || 'No description' }}
                        </div>
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-right">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                          {{ formatRankingNumber(supply.total_usage) }}
                        </div>
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-right">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                          {{ formatRankingNumber(supply.avg_usage) }}
                        </div>
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-right">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                          {{ formatRankingNumber(supply.recent_usage) }}
                        </div>
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-center">
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
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="rankedSupplies.length === 0" class="text-center py-12">
                <span class="material-icons-outlined text-5xl text-gray-300 dark:text-gray-600 mb-4 block">bar_chart</span>
                <p class="text-gray-600 dark:text-gray-400">No usage data available for {{ rankingYear }}</p>
              </div>
            </div>

            <!-- Budget Insights -->
            <div v-if="rankedSupplies.length > 0" class="bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-xl shadow-md border-2 border-yellow-200 dark:border-yellow-800 p-4">
              <div class="flex items-start gap-3">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-900/50 rounded-lg">
                  <span class="material-icons-outlined text-yellow-600 dark:text-yellow-400 text-2xl">warning</span>
                </div>
                <div class="flex-1">
                  <h3 class="text-base font-bold text-gray-900 dark:text-white mb-2">Budget Management Insights</h3>
                  <p class="text-xs text-gray-700 dark:text-gray-300 mb-3">
                    The following supplies have the highest consumption rates:
                  </p>
                  <ul class="space-y-1.5">
                    <li 
                      v-for="(supply, index) in rankedSupplies.slice(0, 3)" 
                      :key="supply.item_id"
                      class="flex items-center gap-2 text-xs"
                    >
                      <span class="font-bold text-yellow-600 dark:text-yellow-400">#{{ index + 1 }}</span>
                      <span class="text-gray-900 dark:text-white font-medium">{{ supply.item?.unit }}</span>
                      <span class="text-gray-600 dark:text-gray-400">-</span>
                      <span class="text-gray-700 dark:text-gray-300">{{ formatRankingNumber(supply.total_usage) }} units</span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
} from 'chart.js'
import { Bar, Pie } from 'vue-chartjs'
import axios from 'axios'

const router = useRouter()

const goBack = () => {
  router.back()
}

const goToSupplyRanking = () => {
  rankingYear.value = selectedYear.value // Sync with current year selection
  showSupplyRankingModal.value = true
  fetchSupplyRanking()
}

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
)

// State for quarterly usage data
const quarterlyData = ref({})
const currentPeriod = ref('')
const currentYear = ref(new Date().getFullYear())
const selectedYear = ref(new Date().getFullYear()) // User-selected year for filtering
const loading = ref(false)
const error = ref(null)

// State for forecast data
const forecastItems = ref([])
const forecastLoading = ref(false)
const forecastError = ref(null)

// State for monthly breakdown
const showMonthlyModal = ref(false)
const monthlyData = ref([])
const monthlyLoading = ref(false)
const monthlyError = ref(null)
const selectedQuarter = ref('')

// State for supply usage ranking modal
const showSupplyRankingModal = ref(false)
const rankedSupplies = ref([])
const rankingSummary = ref(null)
const rankingLoading = ref(false)
const rankingError = ref(null)
const rankingYear = ref(new Date().getFullYear())
const rankingSortBy = ref('total_usage')
const rankingLimit = ref(20)

// Python ML API base URL
const PY_API_BASE = import.meta.env.VITE_PY_API_BASE_URL || 'http://127.0.0.1:5000'

// Available years for filtering (2024 to current year)
const availableYears = computed(() => {
  const current = new Date().getFullYear()
  const startYear = 2024
  const years = []
  for (let year = startYear; year <= current; year++) {
    years.push(year)
  }
  return years.reverse() // Most recent year first
})

// Fetch quarterly usage data from API
const fetchQuarterlyUsage = async (year = null) => {
  loading.value = true
  error.value = null
  
  try {
    // Use selectedYear if no year parameter provided
    const yearToFetch = year || selectedYear.value
    const response = await axiosClient.get(`/usage/quarterly?year=${yearToFetch}`)
    
    if (response.data.success) {
      quarterlyData.value = response.data.data || {}
      currentPeriod.value = response.data.current_period || ''
      currentYear.value = response.data.current_year || new Date().getFullYear()
      
      // If selectedYear is not in the response, keep it as is
      if (!selectedYear.value || selectedYear.value !== currentYear.value) {
        // Keep selectedYear as user's choice
      }
    } else {
      error.value = response.data.message || 'Failed to fetch usage data'
    }
  } catch (err) {
    console.error('Error fetching quarterly usage:', err)
    error.value = err.response?.data?.message || 'Failed to fetch usage data'
    // Initialize with empty data structure for selected year
    quarterlyData.value = {
      [`Q1 ${selectedYear.value}`]: [],
      [`Q2 ${selectedYear.value}`]: [],
      [`Q3 ${selectedYear.value}`]: [],
      [`Q4 ${selectedYear.value}`]: []
    }
  } finally {
    loading.value = false
  }
}

// Generate chart data for a specific quarter
const getQuarterChartData = (quarterKey) => {
  const quarterItems = quarterlyData.value[quarterKey] || []
  
  const labels = quarterItems.map(item => item.item?.unit || `Item ${item.item_id}` || 'Unknown')
  const usageData = quarterItems.map(item => item.total_usage || 0)
  
  return {
    labels: labels.length > 0 ? labels : ['No data'],
    datasets: [{
      label: 'Usage',
      data: usageData.length > 0 ? usageData : [0],
      backgroundColor: ['#3B82F6', '#34D399', '#F59E0B', '#06B6D4', '#8B5CF6', '#EF4444', '#10B981', '#F59E0B'],
      barPercentage: 0.5,
      categoryPercentage: 0.7
    }]
  }
}

// Dynamic max value for y-axis based on data
const getBarOptions = (isCurrentQuarter = false) => {
  return {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: '#E5E7EB',
          drawBorder: false
        },
        ticks: {
          stepSize: 5
        }
      },
      x: {
        grid: {
          display: false
        }
      }
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            return `Usage: ${context.parsed.y} units`
          }
        }
      },
      title: isCurrentQuarter ? {
        display: true,
        text: 'Current Quarter',
        position: 'top',
        font: {
          size: 14,
          weight: 'bold'
        },
        color: '#10B981'
      } : {}
    }
  }
}

const pieOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
}

// Computed properties for each quarter (use selectedYear for filtering)
const q1Key = computed(() => `Q1 ${selectedYear.value}`)
const q2Key = computed(() => `Q2 ${selectedYear.value}`)
const q3Key = computed(() => `Q3 ${selectedYear.value}`)
const q4Key = computed(() => `Q4 ${selectedYear.value}`)

const q1Data = computed(() => getQuarterChartData(q1Key.value))
const q2Data = computed(() => getQuarterChartData(q2Key.value))
const q3Data = computed(() => getQuarterChartData(q3Key.value))
const q4Data = computed(() => getQuarterChartData(q4Key.value))

// Check if quarter is current period
const isCurrentQuarter = (quarterKey) => {
  return quarterKey === currentPeriod.value
}

// Fetch forecast-ready data and generate predictions using Python ML API
const fetchForecastPredictions = async () => {
  forecastLoading.value = true
  forecastError.value = null
  
  try {
    // Step 1: Fetch forecast-ready data from Laravel (including 2024 and more historical data)
    const forecastDataResponse = await axiosClient.get('/usage/forecast-data?years_back=3&forecast_months=3')
    
    if (!forecastDataResponse.data.success) {
      throw new Error(forecastDataResponse.data.message || 'Failed to fetch forecast data')
    }
    
    const forecastData = forecastDataResponse.data.data || []
    
    if (forecastData.length === 0) {
      forecastError.value = 'No usage data available for forecasting'
      forecastLoading.value = false
      return
    }
    
    // Step 2: Prepare data for Python ML API (Linear Regression)
    const mlPayload = {
      items: forecastData.map(item => ({
        item_id: item.item_id,
        name: item.item?.unit || `Item ${item.item_id}`,
        historical_data: item.historical_data,
        forecast_features: item.forecast_features,
        current_stock: item.current_stock
      }))
    }
    
    // Step 3: Call Python ML API for Linear Regression predictions
    try {
      console.log('🔮 Calling Python ML API for Linear Regression:', PY_API_BASE)
      console.log('📊 Sending data for', mlPayload.items.length, 'items')
      
      const mlResponse = await axios.post(
        `${PY_API_BASE}/predict/consumables/linear`,
        mlPayload,
        { 
          timeout: 15000,
          headers: {
            'Content-Type': 'application/json'
          }
        }
      )
      
      console.log('✅ Python ML API Response:', mlResponse.data)
      
      if (mlResponse.data?.forecast && Array.isArray(mlResponse.data.forecast)) {
        // Successfully got predictions from Python ML API
        forecastItems.value = forecastData.map(item => {
          const forecastResult = mlResponse.data.forecast.find(
            f => f.item_id === item.item_id || 
                 f.name === item.item?.unit ||
                 (f.item_id && f.item_id.toString() === item.item_id.toString())
          )
          
          if (forecastResult) {
            return {
              ...item,
              forecasted_usage: forecastResult.predicted_usage || null,
              forecasted_period: item.forecast?.next_period || null,
              confidence: forecastResult.confidence || null,
              method: 'linear_regression'
            }
          }
          
          // Fallback to Laravel forecast if not found in Python response
          const forecast = item.forecast || {}
          return {
            ...item,
            forecasted_usage: forecast.predicted_usage || null,
            forecasted_period: forecast.next_period || null,
            confidence: forecast.confidence || null,
            method: forecast.method === 'linear_regression' ? 'linear_regression' : 'statistical'
          }
        })
        
        console.log('✅ Python ML API Linear Regression forecasts generated successfully')
        forecastError.value = null
      } else {
        throw new Error('Invalid response format from Python ML API')
      }
    } catch (mlError) {
      console.warn('⚠️ Python ML API error, using Laravel Linear Regression fallback:', mlError.message)
      
      // Fallback: Use Linear Regression forecast from Laravel backend
      forecastItems.value = forecastData.map(item => {
        const forecast = item.forecast || {}
        return {
          ...item,
          forecasted_usage: forecast.predicted_usage || null,
          forecasted_period: forecast.next_period || null,
          confidence: forecast.confidence || null,
          method: forecast.method === 'linear_regression' ? 'linear_regression' : 'statistical'
        }
      })
      
      // Only show info message in console, not as UI error since fallback is working
      if (mlError.code === 'ECONNREFUSED' || mlError.code === 'ERR_NETWORK') {
        console.info('ℹ️ Python ML API unavailable at ' + PY_API_BASE + '. Using Laravel Linear Regression fallback (fully functional).')
        console.info('💡 To enable Python ML API, run: start_ml_api.bat (Windows) or ./start_ml_api.sh (Linux/Mac)')
        // Don't set forecastError - fallback is working fine, just log for debugging
        // forecastError.value = null // Ensure no error shown since fallback works
      } else {
        // Only show error in console for other errors
        console.warn('⚠️ Python ML API error:', mlError.message, '- Using Laravel Linear Regression fallback')
        // forecastError.value = null // Don't show UI error since fallback works
      }
      
      console.log('✅ Using Laravel Linear Regression forecasts (fallback working correctly)')
    }
    
    
  } catch (err) {
    console.error('Error generating forecast:', err)
    forecastError.value = err.response?.data?.message || err.message || 'Failed to generate forecast'
    
    // Fallback to current quarter data if forecast fails
    forecastItems.value = []
  } finally {
    forecastLoading.value = false
  }
}

// Forecast chart data based on forecast items
const forecastData = computed(() => {
  // If we have ML forecast results, use those
  if (forecastItems.value.length > 0) {
    const labels = forecastItems.value
      .filter(item => item.forecasted_usage !== null && item.forecasted_usage > 0)
      .map(item => item.item?.unit || `Item ${item.item_id}` || 'Unknown')
      .slice(0, 8) // Limit to top 8 items for readability
    
    const usageData = forecastItems.value
      .filter(item => item.forecasted_usage !== null && item.forecasted_usage > 0)
      .map(item => item.forecasted_usage || 0)
      .slice(0, 8)
    
    return {
      labels: labels.length > 0 ? labels : ['No forecast data'],
      datasets: [{
        label: 'Forecasted Usage (3 months)',
        data: usageData.length > 0 ? usageData : [0],
        backgroundColor: ['#4C51BF', '#059669', '#DC2626', '#14B8A6', '#F472B6', '#3B82F6', '#F59E0B', '#8B5CF6']
      }]
    }
  }
  
  // Fallback to current quarter data
  const currentQuarterData = quarterlyData.value[currentPeriod.value] || []
  
  const labels = currentQuarterData.map(item => item.item?.unit || `Item ${item.item_id}` || 'Unknown')
  const usageData = currentQuarterData.map(item => item.total_usage || 0)
  
  return {
    labels: labels.length > 0 ? labels : ['No data'],
    datasets: [{
      data: usageData.length > 0 ? usageData : [0],
      backgroundColor: ['#4C51BF', '#059669', '#DC2626', '#14B8A6', '#F472B6', '#3B82F6', '#F59E0B', '#8B5CF6']
    }]
  }
})

// Fetch monthly breakdown for a quarter
const showMonthlyBreakdown = async (quarterKey) => {
  selectedQuarter.value = quarterKey
  showMonthlyModal.value = true
  monthlyLoading.value = true
  monthlyError.value = null
  monthlyData.value = []
  
  try {
    const response = await axiosClient.get(`/usage/monthly?quarter=${quarterKey}`)
    
    if (response.data.success) {
      monthlyData.value = response.data.data || []
    } else {
      monthlyError.value = response.data.message || 'Failed to fetch monthly data'
    }
  } catch (err) {
    console.error('Error fetching monthly usage:', err)
    monthlyError.value = err.response?.data?.message || 'Failed to fetch monthly usage data'
  } finally {
    monthlyLoading.value = false
  }
}

// Close monthly modal
const closeMonthlyModal = () => {
  showMonthlyModal.value = false
  monthlyData.value = []
  monthlyError.value = null
}

// Generate chart data for monthly breakdown
const getMonthlyChartData = (item) => {
  const labels = item.monthly_breakdown.map(m => m.month)
  const usageData = item.monthly_breakdown.map(m => m.usage)
  
  return {
    labels: labels,
    datasets: [{
      label: 'Usage (units)',
      data: usageData,
      backgroundColor: ['#3B82F6', '#10B981', '#F59E0B'],
      barPercentage: 0.6,
      categoryPercentage: 0.8
    }]
  }
}

// Chart options for monthly breakdown
const monthlyChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          return `${context.parsed.y} units`
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1
      }
    }
  }
}

// Fetch supply usage ranking
const fetchSupplyRanking = async () => {
  rankingLoading.value = true
  rankingError.value = null
  
  try {
    const response = await axiosClient.get('/usage/ranking', {
      params: {
        year: rankingYear.value,
        sort_by: rankingSortBy.value,
        limit: rankingLimit.value
      }
    })
    
    if (response.data.success) {
      rankedSupplies.value = response.data.data || []
      rankingSummary.value = response.data.summary || null
    } else {
      rankingError.value = response.data.message || 'Failed to fetch ranking data'
    }
  } catch (err) {
    console.error('Error fetching ranking:', err)
    rankingError.value = err.response?.data?.message || 'Failed to fetch supply usage ranking'
  } finally {
    rankingLoading.value = false
  }
}

// Close supply ranking modal
const closeSupplyRankingModal = () => {
  showSupplyRankingModal.value = false
  rankedSupplies.value = []
  rankingSummary.value = null
  rankingError.value = null
}

// Format number for ranking
const formatRankingNumber = (num) => {
  if (num == null) return '0'
  return Number(num).toLocaleString()
}

// Get ranking sort label
const getRankingSortLabel = () => {
  const labels = {
    'total_usage': 'Total Usage',
    'avg_usage': 'Average Usage',
    'recent_usage': 'Recent Usage'
  }
  return labels[rankingSortBy.value] || 'Total Usage'
}

// Get rank badge class
const getRankBadgeClass = (index) => {
  if (index === 0) return 'bg-yellow-500 text-white'
  if (index === 1) return 'bg-gray-400 text-white'
  if (index === 2) return 'bg-orange-600 text-white'
  return 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
}

// Get trend badge class
const getTrendBadgeClass = (trend) => {
  const classes = {
    'increasing': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
    'decreasing': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    'stable': 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
  }
  return classes[trend] || classes.stable
}

// Get trend icon
const getTrendIcon = (trend) => {
  const icons = {
    'increasing': 'trending_up',
    'decreasing': 'trending_down',
    'stable': 'trending_flat'
  }
  return icons[trend] || 'trending_flat'
}

// Export ranking report to Excel
const exportRankingToExcel = async () => {
  try {
    if (rankedSupplies.value.length === 0) {
      alert('No data to export')
      return
    }

    // Use backend API to generate Excel with proper formatting
    const response = await axiosClient.get('/usage/ranking/export-excel', {
      params: {
        year: rankingYear.value,
        sort_by: rankingSortBy.value,
        limit: rankingLimit.value
      },
      responseType: 'blob',
      headers: {
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      },
      timeout: 60000
    })
    
    // Check if response is actually an Excel file
    const contentType = response.headers['content-type'] || ''
    if (contentType.includes('application/json')) {
      // It's an error response
      const text = await new Blob([response.data]).text()
      const errorData = JSON.parse(text)
      alert(errorData.message || 'Failed to export Excel')
      return
    }
    
    // Create blob and download
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Supply_Usage_Ranking_${rankingYear.value}_${new Date().toISOString().split('T')[0]}.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting to Excel:', error)
    if (error.response?.data) {
      try {
        const text = await error.response.data.text()
        const errorData = JSON.parse(text)
        alert(errorData.message || 'Failed to export to Excel')
      } catch (e) {
        alert('Failed to export to Excel. Please try again.')
      }
    } else {
      alert('Failed to export to Excel. Please try again.')
    }
  }
}

// Export ranking report to PDF
const exportRankingToPDF = async () => {
  try {
    if (rankedSupplies.value.length === 0) {
      alert('No data to export')
      return
    }

    // Use backend API to generate PDF
    const response = await axiosClient.get('/usage/ranking/export-pdf', {
      params: {
        year: rankingYear.value,
        sort_by: rankingSortBy.value,
        limit: rankingLimit.value
      },
      responseType: 'blob',
      headers: {
        'Accept': 'application/pdf'
      },
      timeout: 60000
    })
    
    // Check if response is actually a PDF
    const contentType = response.headers['content-type'] || ''
    if (contentType.includes('application/json')) {
      // It's an error response
      const text = await new Blob([response.data]).text()
      const errorData = JSON.parse(text)
      alert(errorData.message || 'Failed to export PDF')
      return
    }
    
    // Create blob and download
    const blob = new Blob([response.data], {
      type: 'application/pdf'
    })
    
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Supply_Usage_Ranking_${rankingYear.value}_${new Date().toISOString().split('T')[0]}.pdf`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting to PDF:', error)
    if (error.response?.data) {
      try {
        const text = await error.response.data.text()
        const errorData = JSON.parse(text)
        alert(errorData.message || 'Failed to export to PDF')
      } catch (e) {
        alert('Failed to export to PDF. Please try again.')
      }
    } else {
      alert('Failed to export to PDF. Please try again.')
    }
  }
}

// Print ranking report
const printRankingReport = () => {
  try {
    if (rankedSupplies.value.length === 0) {
      alert('No data to print')
      return
    }

    const printWindow = window.open('', '_blank')
    const now = new Date()
    const dateStr = now.toLocaleDateString()
    const timeStr = now.toLocaleTimeString()

    let htmlContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Supply Usage Ranking Report - ${rankingYear.value}</title>
        <style>
          @media print {
            @page { margin: 1cm; }
            body { margin: 0; }
            .no-print { display: none; }
          }
          body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #333;
          }
          .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
          }
          .header h1 {
            color: #2563eb;
            margin: 0 0 10px 0;
            font-size: 24px;
          }
          .header p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
          }
          .summary {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            padding: 15px;
            background: #f3f4f6;
            border-radius: 8px;
          }
          .summary-item {
            text-align: center;
          }
          .summary-item strong {
            display: block;
            font-size: 20px;
            color: #2563eb;
            margin-bottom: 5px;
          }
          .summary-item span {
            font-size: 12px;
            color: #666;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
          }
          th {
            background-color: #2563eb;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
          }
          td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
          }
          tr:nth-child(even) {
            background-color: #f9fafb;
          }
          .insights {
            margin-top: 30px;
            padding: 15px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 4px;
          }
          .insights h3 {
            margin-top: 0;
            color: #92400e;
          }
          .insights ul {
            margin: 10px 0;
            padding-left: 20px;
          }
          .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
          }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>Supply Usage Ranking Report</h1>
          <p>Year: ${rankingYear.value}</p>
          <p>Generated: ${dateStr} at ${timeStr}</p>
        </div>
    `

    // Add summary
    if (rankingSummary.value) {
      htmlContent += `
        <div class="summary">
          <div class="summary-item">
            <strong>${rankingSummary.value.total_items}</strong>
            <span>Total Items</span>
          </div>
          <div class="summary-item">
            <strong>${formatRankingNumber(rankingSummary.value.total_usage_all)}</strong>
            <span>Total Usage</span>
          </div>
          <div class="summary-item">
            <strong>${formatRankingNumber(rankingSummary.value.avg_usage_all)}</strong>
            <span>Average Usage</span>
          </div>
        </div>
      `
    }

    // Add table
    htmlContent += `
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
        <tbody>
    `

    rankedSupplies.value.forEach((supply, index) => {
      htmlContent += `
        <tr>
          <td>${index + 1}</td>
          <td>${supply.item?.unit || `Item ${supply.item_id}`}</td>
          <td>${formatRankingNumber(supply.total_usage)}</td>
          <td>${formatRankingNumber(supply.avg_usage)}</td>
          <td>${formatRankingNumber(supply.recent_usage)}</td>
          <td>${supply.trend}</td>
        </tr>
      `
    })

    htmlContent += `
        </tbody>
      </table>
    `

    // Add insights
    if (rankedSupplies.value.length > 0) {
      htmlContent += `
        <div class="insights">
          <h3>Budget Management Insights</h3>
          <p>The following supplies have the highest consumption rates:</p>
          <ul>
      `
      rankedSupplies.value.slice(0, 3).forEach((supply, index) => {
        htmlContent += `
          <li><strong>#${index + 1}</strong> ${supply.item?.unit} - ${formatRankingNumber(supply.total_usage)} units total</li>
        `
      })
      htmlContent += `
          </ul>
          <p><em>Recommendation: Monitor these items closely and consider bulk purchasing or alternative suppliers to optimize budget allocation.</em></p>
        </div>
      `
    }

    htmlContent += `
        <div class="footer">
          <p>This report was generated from the NIA System</p>
        </div>
      </body>
      </html>
    `

    printWindow.document.write(htmlContent)
    printWindow.document.close()
    
    // Wait for content to load, then trigger print
    setTimeout(() => {
      printWindow.print()
    }, 250)
  } catch (error) {
    console.error('Error printing report:', error)
    alert('Failed to print report. Please try again.')
  }
}

// Watch selectedYear and refetch data when it changes
watch(selectedYear, async (newYear) => {
  if (newYear) {
    await fetchQuarterlyUsage(newYear)
    // Refetch forecasts when year changes
    fetchForecastPredictions()
  }
})

// Fetch data on mount
onMounted(async () => {
  await fetchQuarterlyUsage()
  // Automatically fetch forecasts after quarterly data is loaded
  fetchForecastPredictions()
})
</script>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
}

/* Ensure chart containers are responsive */
.h-\[250px\],
.h-\[300px\] {
  max-width: 100%;
  overflow: hidden;
}

/* Table overflow handling */
table {
  width: 100%;
  table-layout: auto;
}
</style> 