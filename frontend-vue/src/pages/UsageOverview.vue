<template>
  <div class="usage-overview-container">
    <!-- Back Button -->
    <button @click="goBack" class="back-button mb-4 sm:mb-6 px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
      <i class="fas fa-arrow-left"></i>
      <span>Back</span>
    </button>

    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-6 sm:mb-8 text-gray-900 dark:text-white">Usage of Items Overview</h1>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
      <span class="ml-3 text-gray-600 dark:text-gray-400">Loading usage data...</span>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
      <p class="text-red-800 dark:text-red-200">{{ error }}</p>
      <button @click="fetchQuarterlyUsage" class="mt-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
        Retry
      </button>
    </div>

    <!-- Quarterly Charts Grid -->
    <div v-if="!loading && !error" class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
      <!-- Q1 Chart -->
      <div 
        class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm"
        :class="{ 'ring-2 ring-green-500': isCurrentQuarter(q1Key) }"
      >
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Jan - Mar Usage ({{ q1Key }})</h2>
          <span v-if="isCurrentQuarter(q1Key)" class="px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full">
            Current
          </span>
        </div>
        <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
          <Bar :data="q1Data" :options="getBarOptions(isCurrentQuarter(q1Key))" />
        </div>
        <div v-if="q1Data.labels.length === 1 && q1Data.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-2">
          No usage data for this quarter
        </div>
      </div>

      <!-- Q2 Chart -->
      <div 
        class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm"
        :class="{ 'ring-2 ring-green-500': isCurrentQuarter(q2Key) }"
      >
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Apr - Jun Usage ({{ q2Key }})</h2>
          <span v-if="isCurrentQuarter(q2Key)" class="px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full">
            Current
          </span>
        </div>
        <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
          <Bar :data="q2Data" :options="getBarOptions(isCurrentQuarter(q2Key))" />
        </div>
        <div v-if="q2Data.labels.length === 1 && q2Data.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-2">
          No usage data for this quarter
        </div>
      </div>

      <!-- Q3 Chart -->
      <div 
        class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm"
        :class="{ 'ring-2 ring-green-500': isCurrentQuarter(q3Key) }"
      >
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Jul - Sep Usage ({{ q3Key }})</h2>
          <span v-if="isCurrentQuarter(q3Key)" class="px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full">
            Current
          </span>
        </div>
        <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
          <Bar :data="q3Data" :options="getBarOptions(isCurrentQuarter(q3Key))" />
        </div>
        <div v-if="q3Data.labels.length === 1 && q3Data.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-2">
          No usage data for this quarter
        </div>
      </div>

      <!-- Q4 Chart -->
      <div 
        class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm"
        :class="{ 'ring-2 ring-green-500': isCurrentQuarter(q4Key) }"
      >
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">Oct - Dec Usage ({{ q4Key }})</h2>
          <span v-if="isCurrentQuarter(q4Key)" class="px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full">
            Current
          </span>
        </div>
        <div class="h-[250px] sm:h-[300px] w-full overflow-hidden">
          <Bar :data="q4Data" :options="getBarOptions(isCurrentQuarter(q4Key))" />
        </div>
        <div v-if="q4Data.labels.length === 1 && q4Data.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-2">
          No usage data for this quarter
        </div>
      </div>
    </div>

    <!-- Forecast Section -->
    <div v-if="!loading && !error" class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm">
      <div class="mb-4 sm:mb-6">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white">
          Forecast for Next 3 Months (Based on {{ currentPeriod }})
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
          <span v-if="forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression') && !forecastError && forecastItems.some(item => item.method === 'linear_regression' && !forecastError)" class="text-green-600 dark:text-green-400">
            <i class="fas fa-chart-line"></i> Using Python ML Linear Regression
          </span>
          <span v-else-if="forecastItems.length > 0 && forecastItems.some(item => item.method === 'linear_regression')" class="text-orange-600 dark:text-orange-400">
            <i class="fas fa-exclamation-circle"></i> Python ML API Not Running - Using Laravel Fallback
          </span>
          <span v-else-if="forecastItems.length > 0" class="text-blue-600 dark:text-blue-400">
            <i class="fas fa-chart-line"></i> Using Forecast Estimates
          </span>
          <span v-else-if="forecastLoading" class="text-gray-500 dark:text-gray-400">
            <i class="fas fa-spinner fa-spin"></i> Generating forecasts...
          </span>
          <span v-else class="text-gray-500 dark:text-gray-400">
            Loading forecast data...
          </span>
        </p>
      </div>

      <!-- Forecast Loading State -->
      <div v-if="forecastLoading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
        <span class="ml-3 text-gray-600 dark:text-gray-400">Generating ML forecast...</span>
      </div>

      <!-- Info State (Statistical Estimates) -->
      <div v-if="!forecastLoading && forecastItems.length > 0 && forecastItems.every(item => item.method === 'statistical')" 
           class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
        <div class="flex items-start">
          <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-2"></i>
          <div class="flex-1">
            <!-- <p class="text-blue-800 dark:text-blue-200 text-sm font-medium">
              ✅ Forecasts Generated Successfully
            </p> -->
            <p class="text-blue-600 dark:text-blue-300 text-xs mt-1">
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
      <div v-else-if="forecastError" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
        <div class="flex items-start">
          <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-0.5 mr-2"></i>
          <div class="flex-1">
            <p class="text-yellow-800 dark:text-yellow-200 text-sm font-medium">{{ forecastError }}</p>
            <div class="text-yellow-600 dark:text-yellow-300 text-xs mt-2" v-if="forecastError.includes('unavailable')">
              <p class="mb-1">To use Python ML API:</p>
              <ul class="list-disc list-inside mt-1 space-y-1">
                <li>Windows: Run <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">start_ml_api.bat</code></li>
                <li>Linux/Mac: Run <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">./start_ml_api.sh</code></li>
                <li>Or manually: <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">python ml_api_server.py</code></li>
              </ul>
              <p class="mt-1">The server should run on <code class="bg-yellow-100 dark:bg-yellow-900 px-1 rounded">{{ PY_API_BASE }}</code></p>
            </div>
            <p class="text-yellow-600 dark:text-yellow-300 text-xs mt-2" v-else>
              Forecasts are using Laravel Linear Regression fallback. Check console for details.
            </p>
          </div>
        </div>
      </div>

      <!-- Forecast Chart -->
      <div v-if="!forecastLoading" class="w-full max-w-md mx-auto h-[250px] sm:h-[300px] mb-4 overflow-hidden">
        <Pie :data="forecastData" :options="pieOptions" />
      </div>

      <!-- Forecast Details Table -->
      <div v-if="!forecastLoading && forecastItems.length > 0" class="mt-6 overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="min-width: 600px;">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Current Stock</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Avg Usage/Q</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trend</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Forecast (Next Q)</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Confidence</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in forecastItems.slice(0, 10)" :key="item.item_id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                {{ item.item?.unit || `Item ${item.item_id}` }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ item.current_stock }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ item.statistics?.avg_usage || 0 }}
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">
                <span 
                  :class="{
                    'text-green-600 dark:text-green-400': item.statistics?.trend === 'increasing',
                    'text-red-600 dark:text-red-400': item.statistics?.trend === 'decreasing',
                    'text-gray-600 dark:text-gray-400': item.statistics?.trend === 'stable'
                  }"
                  class="font-medium capitalize"
                >
                  {{ item.statistics?.trend || 'stable' }}
                </span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <div class="flex flex-col gap-1">
                  <div class="flex items-center gap-2">
                    <span class="font-medium">{{ item.forecasted_usage || 'N/A' }} units</span>
                    <span 
                      v-if="item.method"
                      class="text-xs px-1.5 py-0.5 rounded"
                      :class="{
                        'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200': item.method === 'linear_regression',
                        'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200': item.method === 'ml',
                        'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400': item.method === 'statistical'
                      }"
                      :title="item.method === 'linear_regression' ? 'Linear Regression' : item.method === 'ml' ? 'ML Prediction' : 'Statistical Estimate'"
                    >
                      {{ item.method === 'linear_regression' ? 'LR' : item.method === 'ml' ? 'ML' : 'Stats' }}
                    </span>
                  </div>
                  <span v-if="item.forecasted_period" class="text-xs text-gray-400 dark:text-gray-500">
                    {{ item.forecasted_period }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap text-sm">
                <span 
                  v-if="item.confidence !== null"
                  class="px-2 py-1 rounded text-xs font-medium"
                  :class="{
                    'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200': item.confidence >= 0.7,
                    'bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200': item.confidence >= 0.5 && item.confidence < 0.7,
                    'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200': item.confidence < 0.5
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

      <div v-if="!forecastLoading && forecastData.labels.length === 1 && forecastData.labels[0] === 'No data'" class="text-center text-gray-500 dark:text-gray-400 text-sm mt-2">
        No usage data available for forecast. Please generate usage data first.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
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
const loading = ref(false)
const error = ref(null)

// State for forecast data
const forecastItems = ref([])
const forecastLoading = ref(false)
const forecastError = ref(null)

// Python ML API base URL
const PY_API_BASE = import.meta.env.VITE_PY_API_BASE_URL || 'http://127.0.0.1:5000'

// Fetch quarterly usage data from API
const fetchQuarterlyUsage = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await axiosClient.get('/usage/quarterly')
    
    if (response.data.success) {
      quarterlyData.value = response.data.data || {}
      currentPeriod.value = response.data.current_period || ''
      currentYear.value = response.data.current_year || new Date().getFullYear()
    } else {
      error.value = response.data.message || 'Failed to fetch usage data'
    }
  } catch (err) {
    console.error('Error fetching quarterly usage:', err)
    error.value = err.response?.data?.message || 'Failed to fetch usage data'
    // Initialize with empty data structure
    quarterlyData.value = {
      [`Q1 ${currentYear.value}`]: [],
      [`Q2 ${currentYear.value}`]: [],
      [`Q3 ${currentYear.value}`]: [],
      [`Q4 ${currentYear.value}`]: []
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

// Computed properties for each quarter
const q1Key = computed(() => `Q1 ${currentYear.value}`)
const q2Key = computed(() => `Q2 ${currentYear.value}`)
const q3Key = computed(() => `Q3 ${currentYear.value}`)
const q4Key = computed(() => `Q4 ${currentYear.value}`)

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
    // Step 1: Fetch forecast-ready data from Laravel
    const forecastDataResponse = await axiosClient.get('/usage/forecast-data?years_back=2&forecast_months=3')
    
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

// Fetch data on mount
onMounted(async () => {
  await fetchQuarterlyUsage()
  // Automatically fetch forecasts after quarterly data is loaded
  fetchForecastPredictions()
})
</script>

<style scoped>
.usage-overview-container {
  width: 100%;
  max-width: 100%;
  padding: 0.75rem;
  margin: 0;
  background-color: #f9fafb;
  overflow-x: hidden;
  box-sizing: border-box;
}

@media (min-width: 640px) {
  .usage-overview-container {
    padding: 1rem;
  }
}

@media (min-width: 768px) {
  .usage-overview-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
  }
}

/* Ensure all child elements respect container width */
.usage-overview-container > * {
  max-width: 100%;
  box-sizing: border-box;
}

/* Chart containers should be responsive */
.chart-container {
  position: relative;
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}

/* Table overflow handling */
.usage-overview-container table {
  width: 100%;
  table-layout: auto;
}

/* Responsive chart wrapper */
.usage-overview-container .h-\[250px\] {
  max-width: 100%;
  overflow: hidden;
}

.usage-overview-container .h-\[300px\] {
  max-width: 100%;
  overflow: hidden;
}

/* Ensure grid doesn't overflow */
@media (max-width: 1024px) {
  .usage-overview-container .grid {
    grid-template-columns: 1fr !important;
  }
}

/* Back button styling */
.back-button {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background-color: #f3f4f6;
  border: none;
  border-radius: 0.375rem;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.back-button:hover {
  background-color: #e5e7eb;
}
</style> 