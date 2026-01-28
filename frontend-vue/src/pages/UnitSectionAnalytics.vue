<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 shadow-2xl rounded-2xl mt-4 sm:mt-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-6 sm:px-8 sm:py-7 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-4">
          <div class="flex items-center gap-3 pt-1">
            <button 
              @click="router.push('/supply-requests-management')" 
              class="p-3 bg-white/20 backdrop-blur-sm border-2 border-white/30 text-white rounded-xl hover:bg-white/30 hover:scale-105 transition-all duration-200 shadow-lg"
              title="Go back"
            >
              <span class="material-icons-outlined text-xl">arrow_back</span>
            </button>
            <button 
              @click="fetchStatistics" 
              class="p-3 bg-white/20 backdrop-blur-sm border-2 border-white/30 text-white rounded-xl hover:bg-white/30 hover:scale-105 transition-all duration-200 shadow-lg"
              title="Refresh"
              :disabled="loading"
            >
              <span class="material-icons-outlined text-xl" :class="{ 'animate-spin': loading }">refresh</span>
            </button>
          </div>
          <div class="flex items-start gap-4 flex-1">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-3xl text-white">analytics</span>
            </div>
            <div class="text-white">
              <h1 class="text-3xl sm:text-4xl font-extrabold leading-tight mb-1">Unit/Section Request Analytics</h1>
              <p class="text-white/95 text-base sm:text-lg mt-1 font-medium">View which units/sections request the most supplies with detailed visualizations</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="p-4 sm:p-6 space-y-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="flex flex-col items-center gap-4">
          <div class="relative">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600"></div>
            <div class="absolute inset-0 border-4 border-blue-200 rounded-full"></div>
          </div>
          <span class="text-gray-600 dark:text-gray-300 font-semibold text-lg">Loading analytics...</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 rounded-xl p-6">
        <div class="flex items-center gap-3">
          <span class="material-icons-outlined text-red-500 dark:text-red-400 text-4xl">error_outline</span>
          <div>
            <h3 class="text-red-800 dark:text-red-300 font-bold text-lg">Error Loading Data</h3>
            <p class="text-red-600 dark:text-red-400 mt-1">{{ error }}</p>
          </div>
        </div>
      </div>

      <!-- Analytics Dashboard -->
      <div v-else-if="statistics.length > 0" class="space-y-6">
        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
              <div class="p-3 bg-white/20 rounded-lg">
                <span class="material-icons-outlined text-2xl">inventory_2</span>
              </div>
              <span class="text-3xl font-bold">{{ summaryStats.totalRequests }}</span>
            </div>
            <p class="text-blue-100 font-semibold">Total Requests</p>
            <p class="text-xs text-blue-200 mt-1">{{ summaryStats.totalUnits }} Units/Sections</p>
          </div>

          <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
              <div class="p-3 bg-white/20 rounded-lg">
                <span class="material-icons-outlined text-2xl">check_circle</span>
              </div>
              <span class="text-3xl font-bold">{{ summaryStats.approved }}</span>
            </div>
            <p class="text-green-100 font-semibold">Approved</p>
            <p class="text-xs text-green-200 mt-1">{{ summaryStats.approvalRate }}% Approval Rate</p>
          </div>

          <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
              <div class="p-3 bg-white/20 rounded-lg">
                <span class="material-icons-outlined text-2xl">schedule</span>
              </div>
              <span class="text-3xl font-bold">{{ summaryStats.pending }}</span>
            </div>
            <p class="text-yellow-100 font-semibold">Pending</p>
            <p class="text-xs text-yellow-200 mt-1">Awaiting approval</p>
          </div>

          <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-2">
              <div class="p-3 bg-white/20 rounded-lg">
                <span class="material-icons-outlined text-2xl">people</span>
              </div>
              <span class="text-3xl font-bold">{{ summaryStats.uniqueUsers }}</span>
            </div>
            <p class="text-purple-100 font-semibold">Unique Users</p>
            <p class="text-xs text-purple-200 mt-1">Active requesters</p>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Total Requests Bar Chart -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <span class="material-icons-outlined text-blue-600 dark:text-blue-400">bar_chart</span>
                  Top Units by Total Requests
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Top 10 units/sections</p>
              </div>
            </div>
            <div class="h-80">
              <Bar :data="totalRequestsChartData" :options="barChartOptions" />
            </div>
          </div>

          <!-- Status Distribution Pie Chart -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <span class="material-icons-outlined text-purple-600 dark:text-purple-400">pie_chart</span>
                  Overall Status Distribution
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Request status breakdown</p>
              </div>
            </div>
            <div class="h-80">
              <Doughnut :data="statusDistributionChartData" :options="pieChartOptions" />
            </div>
          </div>
        </div>

        <!-- Status Comparison Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <span class="material-icons-outlined text-green-600 dark:text-green-400">stacked_bar_chart</span>
                Status Comparison - Top 5 Units
              </h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Compare request statuses across top units</p>
            </div>
          </div>
          <div class="h-80">
            <Bar :data="statusComparisonChartData" :options="barChartOptions" />
          </div>
        </div>

        <!-- Enhanced Statistics Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <span class="material-icons-outlined text-blue-600 dark:text-blue-400">table_chart</span>
                  Unit/Section Request Statistics
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Ranked by total number of requests sent</p>
              </div>
            </div>
          </div>
          
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Rank</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Unit/Section</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Personnel</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Total Requests</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Approved</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Pending</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Rejected</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Completed</th>
                  <th class="px-6 py-4 text-right text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Unique Users</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr 
                  v-for="(stat, index) in statistics" 
                  :key="stat.location_id"
                  class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                  :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-700/30'"
                >
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span 
                      class="inline-flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold shadow-md"
                      :class="getRankBadgeClass(index)"
                    >
                      {{ index + 1 }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                      <div class="p-1.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-sm">business</span>
                      </div>
                      <div class="text-sm font-bold text-gray-900 dark:text-white">{{ stat.unit_section_name }}</div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">person</span>
                      <div class="text-sm text-gray-600 dark:text-gray-400">{{ stat.personnel || 'N/A' }}</div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <span class="text-base font-bold text-gray-900 dark:text-white">{{ stat.total_requests }}</span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg text-sm font-semibold">
                      <span class="material-icons-outlined text-xs">check_circle</span>
                      {{ stat.approved_requests }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 rounded-lg text-sm font-semibold">
                      <span class="material-icons-outlined text-xs">schedule</span>
                      {{ stat.pending_requests }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-lg text-sm font-semibold">
                      <span class="material-icons-outlined text-xs">cancel</span>
                      {{ stat.rejected_requests }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-lg text-sm font-semibold">
                      <span class="material-icons-outlined text-xs">done_all</span>
                      {{ stat.fulfilled_requests }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-lg text-sm font-semibold">
                      <span class="material-icons-outlined text-xs">people</span>
                      {{ stat.unique_users }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center">
          <div class="p-6 bg-gradient-to-br from-gray-100 to-gray-50 dark:from-gray-700 dark:to-gray-800 rounded-full mb-4">
            <span class="material-icons-outlined text-6xl text-gray-400 dark:text-gray-500">analytics</span>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No Data Available</h3>
          <p class="text-gray-600 dark:text-gray-400 text-center">No unit/section statistics found at this time.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import { Bar, Pie, Doughnut, Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  Title,
  Tooltip,
  Legend,
  BarElement,
  ArcElement,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler
)

const router = useRouter()

const statistics = ref([])
const loading = ref(false)
const error = ref(null)

// Detect dark mode
const isDarkMode = ref(document.documentElement.classList.contains('dark'))

// Watch for dark mode changes
let darkModeObserver = null

// Chart data computed properties
const totalRequestsChartData = computed(() => {
  const top10 = statistics.value.slice(0, 10)
  return {
    labels: top10.map(s => s.unit_section_name),
    datasets: [{
      label: 'Total Requests',
      data: top10.map(s => s.total_requests),
      backgroundColor: [
        'rgba(59, 130, 246, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(139, 92, 246, 0.8)',
        'rgba(236, 72, 153, 0.8)',
        'rgba(20, 184, 166, 0.8)',
        'rgba(251, 146, 60, 0.8)',
        'rgba(99, 102, 241, 0.8)',
        'rgba(168, 85, 247, 0.8)'
      ],
      borderColor: [
        'rgba(59, 130, 246, 1)',
        'rgba(16, 185, 129, 1)',
        'rgba(245, 158, 11, 1)',
        'rgba(239, 68, 68, 1)',
        'rgba(139, 92, 246, 1)',
        'rgba(236, 72, 153, 1)',
        'rgba(20, 184, 166, 1)',
        'rgba(251, 146, 60, 1)',
        'rgba(99, 102, 241, 1)',
        'rgba(168, 85, 247, 1)'
      ],
      borderWidth: 2,
      borderRadius: 8
    }]
  }
})

const statusDistributionChartData = computed(() => {
  const totals = statistics.value.reduce((acc, stat) => {
    acc.approved += stat.approved_requests || 0
    acc.pending += stat.pending_requests || 0
    acc.rejected += stat.rejected_requests || 0
    acc.fulfilled += stat.fulfilled_requests || 0
    return acc
  }, { approved: 0, pending: 0, rejected: 0, fulfilled: 0 })

  return {
    labels: ['Approved', 'Pending', 'Rejected', 'Completed'],
    datasets: [{
      data: [totals.approved, totals.pending, totals.rejected, totals.fulfilled],
      backgroundColor: [
        'rgba(16, 185, 129, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(59, 130, 246, 0.8)'
      ],
      borderColor: [
        'rgba(16, 185, 129, 1)',
        'rgba(245, 158, 11, 1)',
        'rgba(239, 68, 68, 1)',
        'rgba(59, 130, 246, 1)'
      ],
      borderWidth: 2
    }]
  }
})

const statusComparisonChartData = computed(() => {
  const top5 = statistics.value.slice(0, 5)
  return {
    labels: top5.map(s => s.unit_section_name),
    datasets: [
      {
        label: 'Approved',
        data: top5.map(s => s.approved_requests || 0),
        backgroundColor: 'rgba(16, 185, 129, 0.8)',
        borderColor: 'rgba(16, 185, 129, 1)',
        borderWidth: 2
      },
      {
        label: 'Pending',
        data: top5.map(s => s.pending_requests || 0),
        backgroundColor: 'rgba(245, 158, 11, 0.8)',
        borderColor: 'rgba(245, 158, 11, 1)',
        borderWidth: 2
      },
      {
        label: 'Rejected',
        data: top5.map(s => s.rejected_requests || 0),
        backgroundColor: 'rgba(239, 68, 68, 0.8)',
        borderColor: 'rgba(239, 68, 68, 1)',
        borderWidth: 2
      },
      {
        label: 'Completed',
        data: top5.map(s => s.fulfilled_requests || 0),
        backgroundColor: 'rgba(59, 130, 246, 0.8)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 2
      }
    ]
  }
})

// Summary statistics
const summaryStats = computed(() => {
  const totals = statistics.value.reduce((acc, stat) => {
    acc.totalRequests += stat.total_requests || 0
    acc.approved += stat.approved_requests || 0
    acc.pending += stat.pending_requests || 0
    acc.rejected += stat.rejected_requests || 0
    acc.fulfilled += stat.fulfilled_requests || 0
    acc.uniqueUsers += stat.unique_users || 0
    return acc
  }, { totalRequests: 0, approved: 0, pending: 0, rejected: 0, fulfilled: 0, uniqueUsers: 0 })

  return {
    ...totals,
    totalUnits: statistics.value.length,
    approvalRate: totals.totalRequests > 0 ? ((totals.approved / totals.totalRequests) * 100).toFixed(1) : 0,
    fulfillmentRate: totals.totalRequests > 0 ? ((totals.fulfilled / totals.totalRequests) * 100).toFixed(1) : 0
  }
})

// Chart options that adapt to dark mode
const chartOptions = computed(() => {
  const textColor = isDarkMode.value ? 'rgba(255, 255, 255, 0.9)' : 'rgba(0, 0, 0, 0.87)'
  const gridColor = isDarkMode.value ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)'
  const tooltipBg = isDarkMode.value ? 'rgba(0, 0, 0, 0.9)' : 'rgba(0, 0, 0, 0.8)'
  
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top',
        labels: {
          padding: 15,
          font: {
            size: 12,
            weight: 'bold'
          },
          color: textColor
        }
      },
      tooltip: {
        backgroundColor: tooltipBg,
        padding: 12,
        titleColor: 'rgba(255, 255, 255, 1)',
        bodyColor: 'rgba(255, 255, 255, 0.9)',
        titleFont: {
          size: 14,
          weight: 'bold'
        },
        bodyFont: {
          size: 13
        },
        cornerRadius: 8
      }
    }
  }
})

const barChartOptions = computed(() => {
  const gridColor = isDarkMode.value ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)'
  const textColor = isDarkMode.value ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.6)'
  
  return {
    ...chartOptions.value,
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          stepSize: 1,
          color: textColor
        },
        grid: {
          color: gridColor
        }
      },
      x: {
        ticks: {
          color: textColor
        },
        grid: {
          display: false
        }
      }
    }
  }
})

const pieChartOptions = computed(() => {
  return {
    ...chartOptions.value,
    plugins: {
      ...chartOptions.value.plugins,
      legend: {
        ...chartOptions.value.plugins.legend,
        position: 'bottom'
      }
    }
  }
})

const fetchStatistics = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await axiosClient.get('/supply-requests/unit-section-statistics')
    if (response.data.success) {
      statistics.value = response.data.data || []
    } else {
      error.value = response.data.message || 'Failed to load statistics'
    }
  } catch (err) {
    console.error('Error fetching unit/section statistics:', err)
    error.value = err.response?.data?.message || 'Failed to load unit/section statistics'
  } finally {
    loading.value = false
  }
}

const getRankBadgeClass = (index) => {
  if (index === 0) return 'bg-gradient-to-br from-yellow-400 to-yellow-600 text-white shadow-lg'
  if (index === 1) return 'bg-gradient-to-br from-gray-400 to-gray-600 text-white shadow-lg'
  if (index === 2) return 'bg-gradient-to-br from-orange-400 to-orange-600 text-white shadow-lg'
  return 'bg-gradient-to-br from-blue-400 to-blue-600 text-white'
}

onMounted(() => {
  fetchStatistics()
  
  // Create a MutationObserver to watch for dark mode class changes
  darkModeObserver = new MutationObserver(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark')
  })
  
  darkModeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class']
  })
})

// Cleanup observer on unmount
onBeforeUnmount(() => {
  if (darkModeObserver) {
    darkModeObserver.disconnect()
  }
})
</script>
