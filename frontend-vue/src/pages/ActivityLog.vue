<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
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

// Store channel reference for cleanup
let activityLogsChannel = null

onMounted(() => {
  fetchActivityLogs()
  
  // Set up real-time listener for activity logs
  const setupRealtimeListener = () => {
    if (!window.Echo) {
      console.warn('⚠️ Laravel Echo not available. Will retry...')
      setTimeout(setupRealtimeListener, 2000)
      return
    }

    const pusher = window.Echo.connector?.pusher
    
    if (!pusher) {
      console.warn('⚠️ Pusher connector not found. Will retry...')
      setTimeout(setupRealtimeListener, 2000)
      return
    }

    const connectionState = pusher.connection?.state
    console.log('📡 Activity Logs Echo connection state:', connectionState)

    // If not connected, wait for connection
    if (connectionState !== 'connected') {
      console.log('⏳ Waiting for Echo connection...')
      
      const connectedHandler = () => {
        console.log('✅ Pusher connected! Setting up activity logs listener...')
        setupChannelListener()
        pusher.connection.unbind('connected', connectedHandler)
      }
      
      pusher.connection.bind('connected', connectedHandler)
      
      setTimeout(() => {
        if (pusher.connection?.state === 'connected') {
          setupChannelListener()
        }
      }, 500)
      
      return
    }

    // Already connected - set up listener immediately
    setupChannelListener()
  }

  // Function to set up the channel listener
  const setupChannelListener = () => {
    try {
      console.log('🔔 Setting up real-time activity logs listener...')
      
      const currentPusher = window.Echo?.connector?.pusher
      if (currentPusher) {
        console.log('📡 Pusher connection state:', currentPusher.connection?.state)
      }
      
      // Get or create channel
      if (!activityLogsChannel) {
        activityLogsChannel = window.Echo.channel('activity-logs')
      }
      
      // Use Pusher for subscription confirmation
      const pusher = window.Echo?.connector?.pusher
      if (pusher) {
        pusher.bind('pusher:subscription_succeeded', (data) => {
          if (data && (data.channel === 'activity-logs' || data.channel === 'private-activity-logs')) {
            console.log('✅✅✅ Successfully subscribed to activity-logs channel:', data.channel)
            console.log('🎯 Ready to receive ActivityLogCreated events')
          }
        })
        
        pusher.connection.bind('error', (err) => {
          console.error('❌ Pusher connection error:', err)
        })
        
        pusher.connection.bind('disconnected', () => {
          console.warn('⚠️ Pusher disconnected')
        })
      }
      
      // Listen with dot prefix (Laravel's default format for broadcastAs)
      activityLogsChannel.listen('.ActivityLogCreated', (data) => {
        console.log('📝📝📝 ActivityLogCreated event received 📝📝📝')
        console.log('📝 Full event data:', JSON.stringify(data, null, 2))
        handleNewActivityLog(data)
      })
      
      // Also try without dot prefix as fallback
      activityLogsChannel.listen('ActivityLogCreated', (data) => {
        console.log('📝📝📝 ActivityLogCreated event received (without dot) 📝📝📝')
        console.log('📝 Full event data:', JSON.stringify(data, null, 2))
        handleNewActivityLog(data)
      })
      
      console.log('✅ Real-time activity logs listener active on channel: activity-logs')
      console.log('🧪 Listener setup complete. Waiting for ActivityLogCreated events...')
      
    } catch (error) {
      console.error('❌ Error setting up channel listener:', error)
      setTimeout(setupRealtimeListener, 3000)
    }
  }

  // Handler function for new activity log
  const handleNewActivityLog = (data) => {
    console.log('📝 Processing new activity log:', data)
    
    if (data && data.id) {
      // If we're on the first page and not searching, prepend the new entry
      if (currentPage.value === 1 && !debouncedSearchQuery.value) {
        entries.value.unshift({
          id: data.id,
          name: data.name,
          action: data.action,
          date: data.date,
          time: data.time,
          description: data.description,
          role: data.role
        })
        
        // Update total count
        totalEntries.value++
        
        // If we exceed entries per page, remove the last one
        if (entries.value.length > entriesPerPage.value) {
          entries.value.pop()
        }
      } else {
        // Otherwise, just refresh the current page
        fetchActivityLogs()
      }
    }
  }

  // Start setup - try immediately, then retry if needed
  setTimeout(setupRealtimeListener, 500)
})

// Clean up Echo listeners when component is unmounted
onUnmounted(() => {
  if (window.Echo && activityLogsChannel) {
    console.log('🔇 Removing real-time activity logs listener...')
    try {
      window.Echo.leave('activity-logs')
      activityLogsChannel = null
      console.log('✅ Real-time activity logs listener removed')
    } catch (error) {
      console.error('Error removing listener:', error)
    }
  }
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
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">history</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Activity Log</h1>
              <p v-if="!loading" class="text-green-100 text-sm sm:text-base">
                {{ totalEntries || 0 }} {{ totalEntries === 1 ? 'entry' : 'entries' }} found
              </p>
              <p v-else class="text-green-100 text-sm sm:text-base">Loading activity logs...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Card -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Entries</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ totalEntries || 0 }}</span>
            </p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">list_alt</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Current Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ currentPage }} / {{ totalPages || 1 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">description</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Entries Per Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ entriesPerPage }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">filter_list</span>
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
            v-model="entriesPerPage"
            class="px-4 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md"
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
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name, action, or details..."
            class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
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
      <div class="flex items-center gap-3">
        <span class="material-icons-outlined text-red-400 dark:text-red-400 text-2xl">error</span>
        <p class="text-red-300 dark:text-red-300 font-semibold">{{ error }}</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-12">
      <div class="flex flex-col items-center justify-center">
        <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
          <span class="material-icons-outlined animate-spin text-5xl text-green-400 dark:text-green-400">refresh</span>
        </div>
        <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading activity logs...</p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
      </div>
    </div>

    <!-- Activity Logs Table -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Name</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Date</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Time</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Action</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Details</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Role</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="entries.length === 0">
              <td colspan="6" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center">
                  <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                    <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">history</span>
                  </div>
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No activity logs found</h3>
                  <p class="text-gray-600 dark:text-gray-400">{{ searchQuery ? 'Try adjusting your search query' : 'Activity logs will appear here' }}</p>
                </div>
              </td>
            </tr>
            <tr 
              v-for="entry in entries" 
              :key="entry.id"
              class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
            >
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">person</span>
                  </div>
                  <span class="font-semibold text-gray-900 dark:text-white">{{ entry.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ entry.date }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ entry.time }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-gradient-to-r from-green-500 to-green-600 text-white shadow-md">
                  {{ entry.action }}
                </span>
              </td>
              <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                <div v-if="entry.description" class="text-sm text-gray-700 dark:text-gray-300 max-w-md truncate" :title="entry.description">
                  {{ entry.description }}
                </div>
                <span v-else class="text-sm text-gray-600 dark:text-gray-400 italic">No details available</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-900 dark:bg-blue-900 text-blue-300 dark:text-blue-300">
                  <span class="material-icons-outlined text-sm">badge</span>
                  {{ entry.role }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && entries.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-lg">info</span>
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
              Showing <span class="text-green-400 dark:text-green-400 font-bold">{{ totalEntries > 0 ? ((currentPage - 1) * entriesPerPage + 1) : 0 }}</span> to 
              <span class="text-green-400 dark:text-green-400 font-bold">{{ Math.min(currentPage * entriesPerPage, totalEntries) }}</span> of 
              <span class="text-green-400 dark:text-green-400 font-bold">{{ totalEntries }}</span> entries
            </span>
          </div>
          <div v-if="totalPages > 1" class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
            <button 
              @click="goToPage(1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">first_page</span>
            </button>
            <button 
              @click="prevPage"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
              <button 
                v-for="page in pageNumbers" 
                :key="page"
                @click="goToPage(page)"
                :class="[
                  'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                  currentPage === page 
                    ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                    : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                ]"
              >
                {{ page }}
              </button>
            </div>
            <button 
              @click="nextPage"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_right</span>
            </button>
            <button 
              @click="goToPage(totalPages)"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">last_page</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 20px;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Fade in animation */
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
  animation: fadeIn 0.3s ease-out;
}

/* Enhanced table row hover effect */
tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  transform: translateX(2px);
}

/* Enhanced scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  @apply bg-gray-700 rounded-full;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  @apply bg-green-400 rounded-full hover:bg-green-500;
}

/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
}
</style> 