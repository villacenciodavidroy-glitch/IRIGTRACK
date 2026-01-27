<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axiosClient from '../axios'

const stockOverview = ref([])
const stockSummary = ref({
  total_items: 0,
  total_quantity: 0,
  low_stock_count: 0
})
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const notifyingRestockItemId = ref(null)

// Banner state for success/error messages
const showBanner = ref(false)
const bannerMessage = ref('')
const bannerType = ref('success')
let bannerTimeout = null

// Show banner function
const showSimpleBanner = (message, type = 'success', autoHide = true, duration = 4000) => {
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  
  bannerMessage.value = message
  bannerType.value = type
  showBanner.value = true
  
  if (autoHide) {
    bannerTimeout = setTimeout(() => {
      showBanner.value = false
      bannerMessage.value = ''
    }, duration)
  }
}

// Fetch stock overview
const fetchStockOverview = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await axiosClient.get('/supply-requests/stock-overview')
    
    if (response.data.success) {
      stockOverview.value = response.data.data || []
      stockSummary.value = response.data.summary || {
        total_items: 0,
        total_quantity: 0,
        low_stock_count: 0
      }
    } else {
      stockSummary.value = {
        total_items: 0,
        total_quantity: 0,
        low_stock_count: 0
      }
    }
  } catch (err) {
    console.error('Error fetching stock overview:', err)
    error.value = err.response?.data?.message || 'Failed to fetch stock overview'
    stockSummary.value = {
      total_items: 0,
      total_quantity: 0,
      low_stock_count: 0
    }
  } finally {
    loading.value = false
  }
}

// Notify admin that a supply item needs restocking
const notifyAdminRestock = async (item) => {
  if (notifyingRestockItemId.value !== null) return
  notifyingRestockItemId.value = item.id
  try {
    const response = await axiosClient.post('/supply-requests/notify-restock', { item_id: item.id })
    if (response.data?.success) {
      showSimpleBanner('Admin has been notified to restock this item.', 'success', true, 5000)
      // Refresh stock overview after notification
      await fetchStockOverview()
    } else {
      showSimpleBanner(response.data?.message || 'Failed to notify admin.', 'error', true, 5000)
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Failed to notify admin.'
    showSimpleBanner(msg, 'error', true, 5000)
  } finally {
    notifyingRestockItemId.value = null
  }
}

// Filtered stock overview based on search
const filteredStockOverview = computed(() => {
  if (!searchQuery.value.trim()) {
    return stockOverview.value
  }
  const query = searchQuery.value.toLowerCase()
  return stockOverview.value.filter(item => {
    const itemName = (item.unit || item.description || '').toLowerCase()
    return itemName.includes(query)
  })
})

// Format date for snapshot
const formatSnapshotDate = () => {
  const now = new Date()
  return now.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  fetchStockOverview()
})

onBeforeUnmount(() => {
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 sm:p-6">
    <!-- Banner for notifications -->
    <Transition name="banner-slide">
      <div
        v-if="showBanner"
        :class="[
          'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-lg shadow-lg max-w-md w-full mx-4 flex items-center justify-between',
          bannerType === 'success' 
            ? 'bg-green-500 text-white' 
            : 'bg-red-500 text-white'
        ]"
      >
        <span class="font-semibold">{{ bannerMessage }}</span>
        <button
          @click="showBanner = false"
          class="ml-4 text-white hover:text-gray-200"
        >
          <span class="material-icons-outlined">close</span>
        </button>
      </div>
    </Transition>

    <!-- Header Section -->
    <div class="mb-6">
      <div class="bg-gradient-to-br from-green-500 via-green-600 to-teal-500 rounded-xl shadow-lg mb-6 border border-green-400/20">
        <div class="px-5 py-6 sm:px-6 sm:py-7">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
              <div class="p-2.5 bg-white/25 backdrop-blur-md rounded-xl shadow-md border border-white/30">
                <span class="material-icons-outlined text-3xl text-white drop-shadow-md">inventory_2</span>
              </div>
              <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 drop-shadow-sm tracking-tight">Supply (Quantity)</h1>
                <p class="text-green-50 text-sm font-normal">Monitor supply quantities and notify for restocking</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Items</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stockSummary.total_items }}</p>
            </div>
            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
              <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-2xl">category</span>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Quantity</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stockSummary.total_quantity }}</p>
            </div>
            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
              <span class="material-icons-outlined text-green-600 dark:text-green-400 text-2xl">inventory</span>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Low Stock</p>
              <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stockSummary.low_stock_count }}</p>
            </div>
            <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
              <span class="material-icons-outlined text-red-600 dark:text-red-400 text-2xl">warning</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-4 mb-4">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Search</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 material-icons-outlined text-gray-400 text-base">search</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by item name..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 dark:bg-gray-700 dark:text-white transition-all text-sm"
            />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="fetchStockOverview"
            :disabled="loading"
            class="px-4 py-2 bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg text-sm font-medium transition-all duration-200 hover:shadow-sm flex items-center gap-2 border-2 border-green-400 dark:border-green-500 disabled:opacity-50"
            title="Refresh"
          >
            <span class="material-icons-outlined text-sm" :class="{ 'animate-spin': loading }">refresh</span>
            <span>Refresh</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && stockOverview.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-16">
      <div class="flex flex-col items-center justify-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600 dark:border-green-400 mb-4"></div>
        <p class="text-gray-600 dark:text-gray-400">Loading supply quantities...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error && stockOverview.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700 p-16">
      <div class="flex flex-col items-center justify-center">
        <span class="material-icons-outlined text-6xl text-red-300 dark:text-red-600 mb-4">error_outline</span>
        <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">Error Loading Data</h3>
        <p class="text-gray-500 dark:text-gray-400 text-center max-w-md mb-4">{{ error }}</p>
        <button
          @click="fetchStockOverview"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-all"
        >
          Try Again
        </button>
      </div>
    </div>

    <!-- Supply Quantity Table -->
    <div v-else class="bg-white dark:bg-gradient-to-br dark:from-slate-800 dark:to-slate-900 rounded-xl shadow-lg border-2 border-gray-200 dark:border-slate-700 overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-green-500 to-green-600 dark:from-green-600 dark:to-green-700 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-2 sm:gap-3">
          <span class="material-icons-outlined text-white text-xl sm:text-2xl">folder</span>
          <h2 class="text-base sm:text-lg font-bold text-white">Supply (Quantity)</h2>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-white/80 hidden sm:inline">{{ formatSnapshotDate() }}</span>
          <span class="bg-green-400 dark:bg-green-500 text-white text-xs sm:text-sm font-semibold px-2 sm:px-3 py-1 rounded-md">Snapshot</span>
        </div>
      </div>
      
      <!-- Table: scrollable body, sticky header -->
      <div class="px-4 sm:px-6 pb-4 sm:pb-6 pt-2">
        <div 
          class="supply-snapshot-scroll relative rounded-lg border border-gray-200 dark:border-slate-700 max-h-[600px] overflow-y-auto overflow-x-auto"
          style="overscroll-behavior: contain;"
        >
          <table class="w-full min-w-[320px]">
            <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-slate-800">
              <tr class="border-b-2 border-gray-200 dark:border-slate-700 shadow-[0_2px_4px_rgba(0,0,0,0.1)] dark:shadow-[0_2px_4px_rgba(0,0,0,0.2)]">
                <th class="text-left py-3 px-4 text-xs sm:text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider">ITEM NAME</th>
                <th class="text-right py-3 px-4 text-xs sm:text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider">QUANTITY</th>
                <th class="text-right py-3 px-4 text-xs sm:text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider w-36 sm:w-44">ACTION</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800/50">
              <tr 
                v-for="item in filteredStockOverview" 
                :key="item.id"
                class="border-b border-gray-200 dark:border-slate-700/50 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors"
              >
                <td class="py-3 px-4 text-sm sm:text-base text-gray-900 dark:text-white font-medium">{{ item.unit || item.description || 'N/A' }}</td>
                <td class="py-3 px-4 text-right">
                  <span class="inline-flex items-center justify-center bg-green-500 dark:bg-green-500 text-white text-xs sm:text-sm font-semibold px-3 sm:px-4 py-1.5 sm:py-2 rounded-full border-2 border-green-600 dark:border-green-700 min-w-[3rem] sm:min-w-[4rem]">
                    {{ item.quantity || 0 }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    type="button"
                    :disabled="notifyingRestockItemId !== null"
                    @click="notifyAdminRestock(item)"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed bg-amber-500 hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-700 text-white border-2 border-amber-600 dark:border-amber-700 shadow-sm"
                    :title="'Notify admin to restock ' + (item.unit || item.description || 'this item')"
                  >
                    <span class="material-icons-outlined text-sm sm:text-base" :class="{ 'animate-spin': notifyingRestockItemId === item.id }">{{ notifyingRestockItemId === item.id ? 'hourglass_empty' : 'inventory_2' }}</span>
                    <span class="hidden sm:inline">{{ notifyingRestockItemId === item.id ? 'Sending…' : 'Notify restock' }}</span>
                  </button>
                </td>
              </tr>
              <tr v-if="filteredStockOverview.length === 0">
                <td colspan="3" class="py-6 px-4 text-center text-sm text-gray-500 dark:text-white/70">
                  <span v-if="searchQuery.trim()">No items found matching "{{ searchQuery }}"</span>
                  <span v-else>No supply items available</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Supply Snapshot Scrollbar Styling */
.supply-snapshot-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.5) rgba(229, 231, 235, 0.3);
}

.dark .supply-snapshot-scroll {
  scrollbar-color: rgba(148, 163, 184, 0.5) rgba(30, 41, 59, 0.3);
}

.supply-snapshot-scroll::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.supply-snapshot-scroll::-webkit-scrollbar-track {
  background: rgba(229, 231, 235, 0.3);
  border-radius: 4px;
}

.dark .supply-snapshot-scroll::-webkit-scrollbar-track {
  background: rgba(30, 41, 59, 0.3);
}

.supply-snapshot-scroll::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.5);
  border-radius: 4px;
}

.dark .supply-snapshot-scroll::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.5);
}

.supply-snapshot-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.7);
}

.dark .supply-snapshot-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(148, 163, 184, 0.7);
}

/* Banner transition */
.banner-slide-enter-active,
.banner-slide-leave-active {
  transition: all 0.3s ease;
}

.banner-slide-enter-from {
  opacity: 0;
  transform: translate(-50%, -20px);
}

.banner-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, -20px);
}
</style>
