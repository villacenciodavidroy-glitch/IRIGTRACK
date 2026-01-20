<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6">
    <div class="max-w-full mx-auto space-y-5">
      <!-- Header Section -->
      <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-5 md:px-8 md:py-6 flex flex-wrap items-center gap-4">
          <button 
            @click="goBack" 
            class="p-2.5 bg-white rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-blue-600 text-xl md:text-2xl">arrow_back</span>
          </button>
          <div class="p-3 bg-white rounded-lg flex-shrink-0 shadow-md">
            <span class="material-icons-outlined text-blue-600 text-2xl md:text-3xl">bar_chart</span>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-1">Supply Usage Ranking Report</h1>
            <p class="text-blue-100 text-base md:text-lg">Identify supplies with highest usage to manage budget effectively</p>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button
              @click="exportReport"
              :disabled="loading || rankedSupplies.length === 0"
              class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50"
            >
              <span class="material-icons-outlined text-base">download</span>
              <span class="hidden sm:inline">Export</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Year</label>
            <select
              v-model="selectedYear"
              @change="fetchRanking"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
            <select
              v-model="sortBy"
              @change="fetchRanking"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
              @change="fetchRanking"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
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
      <div v-else-if="!loading && !error && summary" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
          <div class="flex items-center justify-between mb-2">
            <span class="text-blue-100 text-sm font-medium">Total Items Tracked</span>
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
      <div v-if="!loading && !error && rankedSupplies.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
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
      <div v-if="!loading && !error && rankedSupplies.length > 0" class="bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-xl shadow-md border-2 border-yellow-200 dark:border-yellow-800 p-6">
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
</template>

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
const fetchRanking = async () => {
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
      error.value = response.data.message || 'Failed to fetch ranking data'
    }
  } catch (err) {
    console.error('Error fetching ranking:', err)
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

const exportReport = () => {
  // Create CSV content
  const headers = ['Rank', 'Supply Item', 'Total Usage', 'Avg/Quarter', 'Recent Usage', 'Trend', 'Quarters']
  const rows = rankedSupplies.value.map((supply, index) => [
    index + 1,
    supply.item?.unit || `Item ${supply.item_id}`,
    supply.total_usage,
    supply.avg_usage,
    supply.recent_usage,
    supply.trend,
    `${supply.quarters_count}/4`
  ])
  
  const csvContent = [
    `Supply Usage Ranking Report - ${selectedYear.value}`,
    `Generated: ${new Date().toLocaleString()}`,
    '',
    ...headers.join(','),
    ...rows.map(row => row.join(','))
  ].join('\n')
  
  // Download
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  link.setAttribute('href', url)
  link.setAttribute('download', `supply-usage-ranking-${selectedYear.value}.csv`)
  link.style.visibility = 'hidden'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// Lifecycle
onMounted(() => {
  fetchRanking()
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
</style>

