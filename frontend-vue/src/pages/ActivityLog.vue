<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'

const entries = ref([])
const entriesPerPage = ref(10)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const totalEntries = ref(0)
const totalPages = ref(1)
const loading = ref(false)
const error = ref('')

onMounted(() => {
  fetchActivityLogs()
})

// Fetch activity logs from API
const fetchActivityLogs = async () => {
  try {
    loading.value = true
    error.value = ''
    
    const params = new URLSearchParams({
      page: currentPage.value,
      per_page: entriesPerPage.value,
      search: debouncedSearchQuery.value
    })
    
    const response = await axiosClient.get(`/activity-logs?${params}`)
    
    if (response.data.success) {
      entries.value = response.data.data
      totalEntries.value = response.data.pagination.total
      totalPages.value = response.data.pagination.last_page
    } else {
      error.value = response.data.message || 'Failed to fetch activity logs'
    }
  } catch (err) {
    console.error('Error fetching activity logs:', err)
    error.value = err.response?.data?.message || 'Failed to fetch activity logs'
  } finally {
    loading.value = false
  }
}

// Watch for changes in debounced search query and reset to page 1
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
  fetchActivityLogs()
})

// Watch for changes in entries per page
watch(entriesPerPage, () => {
  currentPage.value = 1
  fetchActivityLogs()
})

// Watch for changes in current page
watch(currentPage, () => {
  fetchActivityLogs()
})

// Pagination methods
const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

// Generate page numbers for pagination
const pageNumbers = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, currentPage.value + 2)
  
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  return pages
})
</script>

<template>
  <div class="p-3 sm:p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <!-- Header -->
    <div class="mb-4 sm:mb-6">
      <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-gray-200">Activity Log</h1>
    </div>
    
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
      <div class="flex items-center">
        <span class="text-gray-600 dark:text-gray-400 mr-2">Show</span>
        <select 
          v-model="entriesPerPage"
          class="border rounded px-2 py-1 text-gray-600 dark:text-gray-400 dark:bg-gray-700 dark:border-gray-600"
        >
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="text-gray-600 dark:text-gray-400 ml-2">entries</span>
      </div>

      <div class="w-full sm:w-auto">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search..."
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
        />
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500"></div>
      <span class="ml-2 text-gray-600 dark:text-gray-400">Loading activity logs...</span>
    </div>

    <!-- Activity Logs Table -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead>
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-if="entries.length === 0">
            <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
              No activity logs found
            </td>
          </tr>
          <tr v-for="entry in entries" :key="entry.id">
            <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ entry.name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ entry.date }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ entry.time }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ entry.action }}
              </span>
            </td>
            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
              <span v-if="entry.description" class="text-sm">{{ entry.description }}</span>
              <span v-else class="text-sm text-gray-400 italic">No details available</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ entry.role }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex justify-between items-center mt-4">
      <div class="text-sm text-gray-600 dark:text-gray-400">
        Showing {{ totalEntries > 0 ? ((currentPage - 1) * entriesPerPage + 1) : 0 }} to {{ Math.min(currentPage * entriesPerPage, totalEntries) }} of {{ totalEntries }} entries
      </div>
      
      <div v-if="totalPages > 1" class="flex space-x-1">
        <button
          class="px-3 py-1 rounded border text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="currentPage === 1"
          @click="prevPage"
        >
          <span class="material-icons-outlined text-sm">chevron_left</span>
        </button>
        
        <button
          v-for="page in pageNumbers"
          :key="page"
          class="px-3 py-1 rounded border"
          :class="currentPage === page ? 'bg-green-500 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
          @click="goToPage(page)"
        >
          {{ page }}
        </button>
        
        <button
          class="px-3 py-1 rounded border text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="currentPage === totalPages"
          @click="nextPage"
        >
          <span class="material-icons-outlined text-sm">chevron_right</span>
        </button>
      </div>
    </div>
  </div>
</template> 