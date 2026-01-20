<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'

const records = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const totalRecords = ref(0)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0
})

// Fetch maintenance records from API
const fetchMaintenanceRecords = async () => {
  loading.value = true
  error.value = null
  
  try {
    console.log('Fetching maintenance records from API...')
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value
    }
    
    if (debouncedSearchQuery.value) {
      params.search = debouncedSearchQuery.value
    }
    
    const response = await axiosClient.get('/maintenance-records', { params })
    console.log('API Response:', response)
    console.log('Response data:', response.data)
    
    if (response.data.success) {
      records.value = response.data.data || []
      pagination.value = response.data.pagination || pagination.value
      totalRecords.value = pagination.value.total || 0
    } else {
      records.value = []
      error.value = response.data.message || 'Failed to load maintenance records'
    }
  } catch (err) {
    console.error('Error fetching maintenance records:', err)
    console.error('Error details:', err.response?.data)
    console.error('Error status:', err.response?.status)
    error.value = `Failed to load maintenance records: ${err.response?.data?.message || err.message}. Please try again.`
    records.value = []
  } finally {
    loading.value = false
  }
}

// Fetch maintenance records when component mounts
onMounted(() => {
  fetchMaintenanceRecords()
})

// Watch for search query changes
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
  fetchMaintenanceRecords()
})

// Watch for page changes
watch(currentPage, () => {
  fetchMaintenanceRecords()
})

// Watch for items per page changes
watch(itemsPerPage, () => {
  currentPage.value = 1
  fetchMaintenanceRecords()
})

const totalPages = computed(() => pagination.value.last_page || 1)
const startIndex = computed(() => pagination.value.from || 0)
const endIndex = computed(() => pagination.value.to || 0)

// Compute visible pages for pagination (show first, last, current, and nearby pages)
const visiblePages = computed(() => {
  const pages = []
  const current = currentPage.value
  const total = totalPages.value
  
  if (total <= 7) {
    // Show all pages if 7 or fewer
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)
    
    if (current <= 4) {
      // Show pages 1-5, then ellipsis, then last
      for (let i = 2; i <= 5; i++) {
        pages.push(i)
      }
      pages.push('ellipsis')
      pages.push(total)
    } else if (current >= total - 3) {
      // Show first, ellipsis, then last 5 pages
      pages.push('ellipsis')
      for (let i = total - 4; i <= total; i++) {
        pages.push(i)
      }
    } else {
      // Show first, ellipsis, current-1, current, current+1, ellipsis, last
      pages.push('ellipsis')
      pages.push(current - 1)
      pages.push(current)
      pages.push(current + 1)
      pages.push('ellipsis')
      pages.push(total)
    }
  }
  
  return pages
})

// Format date helper - format as MM/DD/YYYY
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  try {
    const date = new Date(dateString)
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const year = date.getFullYear()
    return `${month}/${day}/${year}`
  } catch (e) {
    return dateString
  }
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-blue-700 to-blue-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">build</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Maintenance Records</h1>
              <p v-if="!loading" class="text-blue-100 text-sm sm:text-base">
                {{ totalRecords || 0 }} {{ totalRecords === 1 ? 'maintenance record' : 'maintenance records' }} found
              </p>
              <p v-else class="text-blue-100 text-sm sm:text-base">Loading maintenance records...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Records</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ totalRecords || 0 }}</span>
            </p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-2xl">build</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Current Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ pagination.current_page || 1 }} / {{ totalPages || 1 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-2xl">description</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Items Per Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ itemsPerPage }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-2xl">filter_list</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Search and Filter Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mb-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3">
          <span class="text-gray-900 dark:text-white font-semibold">Show</span>
          <select 
            v-model="itemsPerPage"
            class="px-4 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all font-medium shadow-sm hover:shadow-md"
          >
            <option value="10" class="bg-gray-700 text-white">10</option>
            <option value="25" class="bg-gray-700 text-white">25</option>
            <option value="50" class="bg-gray-700 text-white">50</option>
            <option value="100" class="bg-gray-700 text-white">100</option>
          </select>
          <span class="text-gray-900 dark:text-white font-semibold">entries</span>
        </div>

        <div class="w-full sm:w-auto relative">
          <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
            <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-xl">search</span>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search records..."
            class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
          />
          <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <button @click="searchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <span class="material-icons-outlined text-lg">close</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-gradient-to-r from-red-900/20 to-red-800/20 dark:from-red-900/20 dark:to-red-800/20 border-l-4 border-red-500 dark:border-red-600 rounded-xl p-4 mb-6 shadow-md dark:shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="material-icons-outlined text-red-400 dark:text-red-400 text-2xl">error</span>
          <p class="text-red-300 dark:text-red-300 font-semibold">{{ error }}</p>
        </div>
        <button 
          @click="fetchMaintenanceRecords" 
          class="px-4 py-2 bg-red-600 dark:bg-red-600 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-700 transition-all shadow-md hover:shadow-lg flex items-center gap-2 font-semibold"
        >
          <span class="material-icons-outlined text-base">refresh</span>
          Try Again
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-12">
      <div class="flex flex-col items-center justify-center">
        <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
          <span class="material-icons-outlined animate-spin text-5xl text-blue-400 dark:text-blue-400">refresh</span>
        </div>
        <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading maintenance records...</p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="records.length === 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-12">
      <div class="flex flex-col items-center justify-center">
        <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
          <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">build</span>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No maintenance records found</h3>
        <p class="text-gray-600 dark:text-gray-400 text-center">{{ searchQuery ? 'Try adjusting your search query' : 'Maintenance records will appear here when items are sent for maintenance.' }}</p>
      </div>
    </div>

    <!-- Table with data -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-700">
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <span>ITEM NAME</span>
                  <span class="material-icons-outlined text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">arrow_upward</span>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <span>REPORT DATE</span>
                  <span class="material-icons-outlined text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">arrow_upward</span>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <span>REASON</span>
                  <span class="material-icons-outlined text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">arrow_upward</span>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <span>CONDITION BEFORE</span>
                  <span class="material-icons-outlined text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">arrow_upward</span>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <span>CONDITION AFTER</span>
                  <span class="material-icons-outlined text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">arrow_upward</span>
                </div>
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 dark:text-white uppercase tracking-wider">
                <div class="flex items-center gap-2">
                  <span>TECHNICIAN NOTES</span>
                  <span class="material-icons-outlined text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-200">arrow_upward</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr 
              v-for="record in records" 
              :key="record.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-lg">inventory_2</span>
                  <div class="text-sm text-gray-900 dark:text-white">
                    <div class="font-medium">{{ record.item_unit }}</div>
                    <div v-if="record.item_description && record.item_description !== record.item_unit" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ record.item_description }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-gray-900 dark:text-white">{{ formatDate(record.maintenance_date) }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="text-sm text-gray-900 dark:text-white">{{ record.reason }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-gray-900 dark:text-white">{{ record.condition_before }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm text-gray-900 dark:text-white">{{ record.condition_after }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="text-sm text-gray-900 dark:text-white">{{ record.technician_notes || 'N/A' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && records.length > 0" class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="text-sm text-gray-700 dark:text-gray-300">
            Showing <span class="font-medium">{{ startIndex || 0 }}</span> to <span class="font-medium">{{ endIndex || 0 }}</span> of <span class="font-medium">{{ totalRecords }}</span> entries
          </div>
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button 
              @click="currentPage = 1"
              :disabled="currentPage === 1"
              class="px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title="First page"
            >
              <span class="material-icons-outlined text-base">first_page</span>
            </button>
            <button 
              @click="currentPage--" 
              :disabled="currentPage === 1"
              class="px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title="Previous page"
            >
              <span class="material-icons-outlined text-base">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
              <button 
                v-for="(page, index) in visiblePages" 
                :key="`page-${index}-${page}`"
                @click="page !== 'ellipsis' && (currentPage = page)"
                :disabled="page === 'ellipsis'"
                :class="[
                  'px-3 py-1 text-sm font-medium rounded transition-colors',
                  page === 'ellipsis' ? 'cursor-default' : 'cursor-pointer',
                  currentPage === page 
                    ? 'bg-blue-600 text-white' 
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                ]"
              >
                {{ page === 'ellipsis' ? '...' : page }}
              </button>
            </div>
            <button 
              @click="currentPage++" 
              :disabled="currentPage === totalPages"
              class="px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title="Next page"
            >
              <span class="material-icons-outlined text-base">chevron_right</span>
            </button>
            <button 
              @click="currentPage = totalPages"
              :disabled="currentPage === totalPages"
              class="px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              title="Last page"
            >
              <span class="material-icons-outlined text-base">last_page</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes modalFadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes modalSlideIn {
  from {
    transform: translateY(-20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-modalFadeIn {
  animation: modalFadeIn 0.2s ease-out;
}

.animate-modalSlideIn {
  animation: modalSlideIn 0.3s ease-out;
}
</style>

