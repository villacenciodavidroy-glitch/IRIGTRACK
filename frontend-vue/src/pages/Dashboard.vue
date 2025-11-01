<script setup>
import { ref, onMounted } from 'vue'
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

// Fetch data when component mounts
onMounted(() => {
  fetchDashboardData()
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

const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20
      }
    },
    title: {
      display: true,
      text: 'Item Status Overview',
      padding: {
        bottom: 30
      }
    }
  },
  scales: {
    x: {
      grid: {
        display: false
      }
    },
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false
      }
    }
  }
})
</script>

<template>
  <div class="animate-fadeIn">
    <!-- Welcome Message -->
    <h2 class="text-xl text-gray-600 mb-4 sm:mb-6 dark:text-gray-300">
      Hello, {{ userLoading ? '...' : (userError ? 'User' : getUserDisplayName()) }}
    </h2>

    <!-- Error Message -->
    <div v-if="statsError" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 sm:mb-6">
      <div class="flex items-center">
        <span class="material-icons-outlined text-red-500 mr-2">error</span>
        <p class="text-red-700 text-sm">{{ statsError }}</p>
        <button 
          @click="fetchDashboardData" 
          class="ml-auto text-red-600 hover:text-red-800 text-sm font-medium"
        >
          Retry
        </button>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6 mb-4 sm:mb-6">
      <!-- Total Items -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-3 sm:p-4 lg:p-6 flex items-center shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="p-2 sm:p-3 bg-orange-50 dark:bg-orange-900/30 rounded-lg">
          <span class="material-icons-outlined text-orange-600 dark:text-orange-400 text-xl sm:text-2xl">inventory_2</span>
        </div>
        <div class="ml-3 sm:ml-4">
          <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Total Items</p>
          <h3 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-900 dark:text-white">
            <span v-if="statsLoading" class="animate-pulse">...</span>
            <span v-else>{{ stats.totalItems }}</span>
          </h3>
        </div>
      </div>

      <!-- Category -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-3 sm:p-4 lg:p-6 flex items-center shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="p-2 sm:p-3 bg-green-50 dark:bg-green-900/30 rounded-lg">
          <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl sm:text-2xl">category</span>
        </div>
        <div class="ml-3 sm:ml-4">
          <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Categories</p>
          <h3 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-900 dark:text-white">
            <span v-if="statsLoading" class="animate-pulse">...</span>
            <span v-else>{{ stats.category }}</span>
          </h3>
        </div>
      </div>

      <!-- Admins -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-3 sm:p-4 lg:p-6 flex items-center shadow-sm hover:shadow-md transition-shadow duration-300">
        <div class="p-2 sm:p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
          <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-xl sm:text-2xl">people</span>
        </div>
        <div class="ml-3 sm:ml-4">
          <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Admins</p>
          <h3 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-900 dark:text-white">
            <span v-if="statsLoading" class="animate-pulse">...</span>
            <span v-else>{{ stats.admins }}</span>
          </h3>
        </div>
      </div>
    </div>

    <!-- Last Updated Info -->
    <div v-if="lastUpdated && !statsLoading" class="text-xs text-gray-500 dark:text-gray-400 mb-4 text-center">
      Last updated: {{ lastUpdated }}
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-2 sm:gap-3 mb-4 sm:mb-6">
      <router-link to="/QRGeneration" class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg flex items-center hover:bg-green-700 transition-colors text-sm sm:text-base">
        <span class="material-icons-outlined mr-1 sm:mr-2 text-base sm:text-xl">qr_code_2</span>
        <span class="whitespace-nowrap">QR Generation</span>
      </router-link>
      <router-link to="/reporting" class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg flex items-center hover:bg-green-700 transition-colors text-sm sm:text-base">
        <span class="material-icons-outlined mr-1 sm:mr-2 text-base sm:text-xl">assessment</span>
        <span class="whitespace-nowrap">Reporting</span>
      </router-link>
      <button 
        @click="fetchDashboardData" 
        :disabled="statsLoading"
        class="bg-blue-600 text-white p-2 sm:p-3 rounded-lg flex items-center justify-center hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        title="Refresh Data"
      >
        <span class="material-icons-outlined text-base sm:text-xl" :class="{ 'animate-spin': statsLoading }">refresh</span>
      </button>
    </div>

    <!-- Chart Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 sm:p-4 lg:p-6 shadow-sm">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-4 sm:mb-6 space-y-3 lg:space-y-0">
        <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-white">Inventory Condition Overview</h3>
        <div class="flex flex-wrap items-center gap-2 sm:gap-4">
          <div class="flex items-center">
            <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-green-500 mr-1 sm:mr-2"></div>
            <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">Serviceable</span>
          </div>
          <div class="flex items-center">
            <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-red-500 mr-1 sm:mr-2"></div>
            <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">Non-Serviceable</span>
          </div>
          <div class="flex items-center">
            <div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-yellow-500 mr-1 sm:mr-2"></div>
            <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-300">Under Maintenance</span>
          </div>
        </div>
      </div>
      <div class="h-[250px] sm:h-[300px] lg:h-[400px]">
        <Bar 
          :data="chartData" 
          :options="chartOptions"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: inherit;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-in-out;
}
</style>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
}
</style>