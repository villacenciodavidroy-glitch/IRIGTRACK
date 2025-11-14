<script setup>
import { ref, onMounted, computed, onBeforeUnmount } from 'vue'
import { Bar } from 'vue-chartjs'
import useAuth from '../composables/useAuth'
import useItems from '../composables/useItems'
import useCategories from '../composables/useCategories'
import useUsers from '../composables/useUsers'

import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

// Initialize auth composable
const { user, loading: userLoading, error: userError, getUserDisplayName } = useAuth()

// Initialize data composables
const { items, fetchitems, loading: itemsLoading } = useItems()
const { categories, fetchcategories } = useCategories()
const { users, fetchusers } = useUsers()

// Stats data
const stats = ref({
  totalItems: 0,
  category: 0,
  admins: 0
})

const statsLoading = ref(true)
const statsError = ref(null)
const lastUpdated = ref(null)

// Fetch all dashboard data
const fetchDashboardData = async () => {
  try {
    statsLoading.value = true
    statsError.value = null
    
    // Fetch all data in parallel
    await Promise.all([
      fetchitems(),
      fetchcategories(),
      fetchusers()
    ])
    
    // Update stats with real data
    stats.value = {
      totalItems: items.value?.length || 0,
      category: categories.value?.length || 0,
      admins: users.value?.filter(user => user.role === 'admin' || user.role === 'super_admin')?.length || 0
    }
    
    // Update last updated time
    lastUpdated.value = new Date().toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila' })
    
  } catch (error) {
    console.error('Error fetching dashboard data:', error)
    statsError.value = 'Failed to load dashboard data. Please try again.'
  } finally {
    statsLoading.value = false
  }
}

// Check if dark mode is active
const isDarkMode = ref(document.documentElement.classList.contains('dark'))

// Watch for dark mode changes
let darkModeObserver = null

// Fetch data when component mounts
onMounted(() => {
  fetchDashboardData()
  
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

const chartData = ref({
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
  datasets: [
    {
      label: 'Serviceable',
      data: [49, 45, 44, 45, 45, 60],
      backgroundColor: '#10B981'
    },
    {
      label: 'Non-Serviceable',
      data: [20, 20, 20, 25, 25, 25],
      backgroundColor: '#EF4444'
    },
    {
      label: 'Under Maintenance',
      data: [10, 12, 11, 13, 12, 10],
      backgroundColor: '#F59E0B'
    }
  ]
})

// Computed chart options that react to dark mode
const chartOptions = computed(() => {
  const dark = isDarkMode.value
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false // Hide default legend as we have custom one in header
      },
      tooltip: {
        backgroundColor: dark ? 'rgba(31, 41, 55, 0.95)' : 'rgba(0, 0, 0, 0.8)',
        padding: 12,
        titleFont: {
          size: 14,
          weight: 'bold'
        },
        bodyFont: {
          size: 13
        },
        titleColor: dark ? '#F3F4F6' : '#FFFFFF',
        bodyColor: dark ? '#E5E7EB' : '#FFFFFF',
        borderColor: dark ? 'rgba(75, 85, 99, 0.5)' : 'rgba(255, 255, 255, 0.1)',
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: true,
        callbacks: {
          label: function(context) {
            return context.dataset.label + ': ' + context.parsed.y + ' items'
          }
        }
      }
    },
    scales: {
      x: {
        grid: {
          display: false,
          drawBorder: false
        },
        ticks: {
          font: {
            size: 12,
            weight: '600'
          },
          color: dark ? '#9CA3AF' : '#6B7280'
        }
      },
      y: {
        beginAtZero: true,
        grid: {
          color: dark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)',
          drawBorder: false
        },
        ticks: {
          font: {
            size: 12,
            weight: '600'
          },
          color: dark ? '#9CA3AF' : '#6B7280',
          callback: function(value) {
            return value
          }
        }
      }
    }
  }
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 via-green-50/30 to-gray-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 animate-fadeIn">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 dark:from-green-700 dark:via-green-800 dark:to-green-700 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5 dark:opacity-10"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 dark:bg-white/10 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">dashboard</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Dashboard</h1>
              <p class="text-green-100 dark:text-green-200 text-sm sm:text-base">
                Hello, {{ userLoading ? '...' : (userError ? 'User' : getUserDisplayName()) }}
              </p>
            </div>
          </div>
          <div v-if="lastUpdated && !statsLoading" class="flex items-center gap-2 px-4 py-2 bg-white/20 dark:bg-white/10 backdrop-blur-sm rounded-full">
            <span class="material-icons-outlined text-white text-sm">schedule</span>
            <span class="text-white text-sm font-medium">Last updated: {{ lastUpdated }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Error Message -->
    <div v-if="statsError" class="bg-gradient-to-r from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 border-l-4 border-red-500 dark:border-red-600 rounded-xl p-4 mb-6 shadow-md dark:shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="p-2 bg-red-500 dark:bg-red-600 rounded-lg">
            <span class="material-icons-outlined text-white">error</span>
          </div>
          <p class="text-red-700 dark:text-red-300 font-semibold">{{ statsError }}</p>
        </div>
        <button 
          @click="fetchDashboardData" 
          class="px-4 py-2 bg-red-600 dark:bg-red-700 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-800 transition-colors text-sm font-semibold shadow-md hover:shadow-lg"
        >
          Retry
        </button>
      </div>
    </div>

    <!-- Enhanced Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">
      <!-- Total Items -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 dark:border-gray-700 p-6 overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-orange-500/10 dark:bg-orange-500/20 rounded-bl-full"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-2">Total Items</p>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
              <span v-if="statsLoading" class="inline-block w-12 h-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ stats.totalItems }}</span>
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Total inventory items</p>
          </div>
          <div class="p-4 bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-600 dark:to-orange-700 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
            <span class="material-icons-outlined text-white text-3xl">inventory_2</span>
          </div>
        </div>
      </div>

      <!-- Categories -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 dark:border-gray-700 p-6 overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-green-500/10 dark:bg-green-500/20 rounded-bl-full"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-2">Categories</p>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
              <span v-if="statsLoading" class="inline-block w-12 h-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ stats.category }}</span>
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Active categories</p>
          </div>
          <div class="p-4 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-600 dark:to-green-700 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
            <span class="material-icons-outlined text-white text-3xl">category</span>
          </div>
        </div>
      </div>

      <!-- Admins -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 dark:border-gray-700 p-6 overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500/10 dark:bg-blue-500/20 rounded-bl-full"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex-1">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-2">Admins</p>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
              <span v-if="statsLoading" class="inline-block w-12 h-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ stats.admins }}</span>
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">System administrators</p>
          </div>
          <div class="p-4 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-600 dark:to-blue-700 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
            <span class="material-icons-outlined text-white text-3xl">people</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Action Buttons -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-100 dark:border-gray-700 p-4 mb-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap gap-3">
          <router-link 
            to="/QRGeneration" 
            class="btn-action-primary flex items-center gap-2 px-5 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5"
          >
            <span class="material-icons-outlined text-xl">qr_code_2</span>
            <span class="whitespace-nowrap">QR Generation</span>
          </router-link>
          <router-link 
            to="/reporting" 
            class="btn-action-primary flex items-center gap-2 px-5 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5"
          >
            <span class="material-icons-outlined text-xl">assessment</span>
            <span class="whitespace-nowrap">Reporting</span>
          </router-link>
        </div>
        <button 
          @click="fetchDashboardData" 
          :disabled="statsLoading"
          class="btn-refresh flex items-center justify-center w-12 h-12 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
          title="Refresh Data"
        >
          <span class="material-icons-outlined text-xl" :class="{ 'animate-spin': statsLoading }">refresh</span>
        </button>
      </div>
    </div>

    <!-- Enhanced Chart Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="bg-gradient-to-r from-green-600 to-green-700 dark:from-green-700 dark:to-green-800 px-6 py-4 border-b border-green-800 dark:border-green-900">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">bar_chart</span>
            <h3 class="text-xl font-bold text-white">Inventory Condition Overview</h3>
          </div>
          <div class="flex flex-wrap items-center gap-3 sm:gap-4">
            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/20 dark:bg-white/10 backdrop-blur-sm rounded-full">
              <div class="w-3 h-3 rounded-full bg-green-500 shadow-sm"></div>
              <span class="text-xs sm:text-sm font-semibold text-white">Serviceable</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/20 dark:bg-white/10 backdrop-blur-sm rounded-full">
              <div class="w-3 h-3 rounded-full bg-red-500 shadow-sm"></div>
              <span class="text-xs sm:text-sm font-semibold text-white">Non-Serviceable</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/20 dark:bg-white/10 backdrop-blur-sm rounded-full">
              <div class="w-3 h-3 rounded-full bg-yellow-500 shadow-sm"></div>
              <span class="text-xs sm:text-sm font-semibold text-white">Under Maintenance</span>
            </div>
          </div>
        </div>
      </div>
      <div class="p-6 bg-white dark:bg-gray-800">
        <div class="h-[300px] sm:h-[350px] lg:h-[450px]">
          <Bar 
            :data="chartData" 
            :options="chartOptions"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: inherit;
}

/* Enhanced Button Styles */
.btn-action-primary {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white hover:from-green-700 hover:to-green-800;
}

.btn-refresh {
  @apply bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Animation keyframes */
@keyframes fadeIn {
  from { 
    opacity: 0; 
    transform: translateY(10px); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0); 
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-in-out;
}

/* Card hover effects */
.group:hover {
  transform: translateY(-2px);
}

/* Enhanced stats card animations */
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

/* Smooth transitions for all interactive elements */
* {
  transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}
</style>