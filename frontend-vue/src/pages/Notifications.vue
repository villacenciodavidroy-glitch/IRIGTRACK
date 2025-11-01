<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import useNotifications from '../composables/useNotifications'
import useAuth from '../composables/useAuth'
import { useDebouncedRef } from '../composables/useDebounce'

const router = useRouter()
const { user } = useAuth()

const {
  notifications,
  loading,
  error,
  unreadCount,
  fetchNotifications,
  markAsRead,
  markAllAsRead,
  getNotificationsByType,
  getRecentNotifications,
  getUnreadNotifications
} = useNotifications()

// Filter and search
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const filterType = ref('all')
const filterPriority = ref('all')
const filterDateRange = ref('all')
const currentPage = ref(1)
const itemsPerPage = ref(20)

// Available filter options
const notificationTypes = [
  { value: 'all', label: 'All Types' },
  { value: 'auth', label: 'Authentication' },
  { value: 'create', label: 'Created' },
  { value: 'update', label: 'Updated' },
  { value: 'delete', label: 'Deleted' },
  { value: 'borrow', label: 'Borrowed' },
  { value: 'restore', label: 'Restored' },
  { value: 'info', label: 'Information' }
]

const priorityLevels = [
  { value: 'all', label: 'All Priorities' },
  { value: 'high', label: 'High Priority' },
  { value: 'medium', label: 'Medium Priority' },
  { value: 'low', label: 'Low Priority' }
]

const dateRanges = [
  { value: 'all', label: 'All Time' },
  { value: 'today', label: 'Today' },
  { value: 'week', label: 'This Week' },
  { value: 'month', label: 'This Month' }
]

// Computed properties
const filteredNotifications = computed(() => {
  let filtered = notifications.value

  // Search filter (using debounced query)
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  if (query) {
    filtered = filtered.filter(notification => 
      (notification.title || '').toLowerCase().includes(query) ||
      (notification.message || '').toLowerCase().includes(query) ||
      (notification.user || '').toLowerCase().includes(query) ||
      (notification.action || '').toLowerCase().includes(query)
    )
  }

  // Type filter
  if (filterType.value !== 'all') {
    filtered = filtered.filter(notification => notification.type === filterType.value)
  }

  // Priority filter
  if (filterPriority.value !== 'all') {
    filtered = filtered.filter(notification => notification.priority === filterPriority.value)
  }

  // Date range filter
  if (filterDateRange.value !== 'all') {
    const now = new Date()
    let startDate

    switch (filterDateRange.value) {
      case 'today':
        startDate = new Date(now.getFullYear(), now.getMonth(), now.getDate())
        break
      case 'week':
        startDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
        break
      case 'month':
        startDate = new Date(now.getFullYear(), now.getMonth(), 1)
        break
    }

    if (startDate) {
      filtered = filtered.filter(notification => new Date(notification.timestamp) >= startDate)
    }
  }

  return filtered
})

const paginatedNotifications = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredNotifications.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredNotifications.value.length / itemsPerPage.value)
})

const notificationStats = computed(() => {
  const total = notifications.value.length
  const unread = notifications.value.filter(n => !n.isRead).length
  const today = notifications.value.filter(n => {
    const today = new Date().toDateString()
    return new Date(n.timestamp).toDateString() === today
  }).length

  return { total, unread, today }
})

// Methods
const clearFilters = () => {
  searchQuery.value = ''
  filterType.value = 'all'
  filterPriority.value = 'all'
  filterDateRange.value = 'all'
  currentPage.value = 1
}

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

const getPriorityColor = (priority) => {
  switch (priority) {
    case 'high': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    case 'medium': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    case 'low': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }
}

const getTypeIcon = (type) => {
  switch (type) {
    case 'auth': return 'login'
    case 'create': return 'add_circle'
    case 'update': return 'edit'
    case 'delete': return 'delete'
    case 'borrow': return 'shopping_cart'
    case 'restore': return 'restore'
    case 'info': return 'info'
    default: return 'notifications'
  }
}

const formatRelativeTime = (timestamp) => {
  const now = new Date()
  const time = new Date(timestamp)
  const diffInSeconds = Math.floor((now - time) / 1000)

  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`
  return time.toLocaleDateString()
}

// Watch for filter changes to reset pagination
watch([searchQuery, filterType, filterPriority, filterDateRange], () => {
  currentPage.value = 1
})

// Lifecycle
onMounted(() => {
  fetchNotifications(100) // Fetch more notifications for the full page
})
</script>

<template>
  <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">All Notifications</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">Track all system activities and user actions</p>
        </div>
        <div class="flex space-x-2">
          <button
            @click="markAllAsRead"
            :disabled="unreadCount === 0"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
          >
            <span class="material-icons-outlined mr-2 text-sm">done_all</span>
            Mark All Read
          </button>
          <button
            @click="clearFilters"
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center"
          >
            <span class="material-icons-outlined mr-2 text-sm">clear</span>
            Clear Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <div class="flex items-center">
          <span class="material-icons-outlined text-blue-600 dark:text-blue-400 mr-3">notifications</span>
          <div>
            <p class="text-sm text-blue-600 dark:text-blue-400">Total Notifications</p>
            <p class="text-2xl font-semibold text-blue-800 dark:text-blue-200">{{ notificationStats.total }}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg">
        <div class="flex items-center">
          <span class="material-icons-outlined text-red-600 dark:text-red-400 mr-3">mark_email_unread</span>
          <div>
            <p class="text-sm text-red-600 dark:text-red-400">Unread</p>
            <p class="text-2xl font-semibold text-red-800 dark:text-red-200">{{ notificationStats.unread }}</p>
          </div>
        </div>
      </div>
      
      <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
        <div class="flex items-center">
          <span class="material-icons-outlined text-green-600 dark:text-green-400 mr-3">today</span>
          <div>
            <p class="text-sm text-green-600 dark:text-green-400">Today</p>
            <p class="text-2xl font-semibold text-green-800 dark:text-green-200">{{ notificationStats.today }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 space-y-4">
      <!-- Search -->
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search notifications..."
            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
          />
        </div>
        
        <div class="flex flex-col sm:flex-row gap-2">
          <select
            v-model="filterType"
            class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
          >
            <option v-for="type in notificationTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
          
          <select
            v-model="filterPriority"
            class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
          >
            <option v-for="priority in priorityLevels" :key="priority.value" :value="priority.value">
              {{ priority.label }}
            </option>
          </select>
          
          <select
            v-model="filterDateRange"
            class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
          >
            <option v-for="range in dateRanges" :key="range.value" :value="range.value">
              {{ range.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500"></div>
      <span class="ml-2 text-gray-600 dark:text-gray-400">Loading notifications...</span>
    </div>

    <!-- Notifications List -->
    <div v-else class="space-y-3">
      <div v-if="paginatedNotifications.length === 0" class="text-center py-8">
        <span class="material-icons-outlined text-6xl text-gray-400 mb-4">notifications_off</span>
        <p class="text-gray-500 dark:text-gray-400">No notifications found</p>
      </div>
      
      <div
        v-for="notification in paginatedNotifications"
        :key="notification.id"
        @click="markAsRead(notification.id)"
        class="p-4 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
        :class="{ 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800': !notification.isRead }"
      >
        <div class="flex items-start space-x-3">
          <!-- Icon -->
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
              <span class="material-icons-outlined text-gray-600 dark:text-gray-400">
                {{ getTypeIcon(notification.type) }}
              </span>
            </div>
          </div>
          
          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ notification.title }}
                </h3>
                <span
                  :class="getPriorityColor(notification.priority)"
                  class="px-2 py-1 text-xs font-medium rounded-full"
                >
                  {{ notification.priority }}
                </span>
                <span v-if="!notification.isRead" class="w-2 h-2 bg-blue-500 rounded-full"></span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatRelativeTime(notification.timestamp) }}
              </p>
            </div>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              {{ notification.message }}
            </p>
            
            <div class="flex items-center justify-between mt-2">
              <div class="flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center">
                  <span class="material-icons-outlined mr-1 text-sm">person</span>
                  {{ notification.user }} ({{ notification.role }})
                </span>
                <span class="flex items-center">
                  <span class="material-icons-outlined mr-1 text-sm">schedule</span>
                  {{ notification.date }} at {{ notification.time }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex justify-between items-center mt-6">
      <div class="text-sm text-gray-600 dark:text-gray-400">
        Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredNotifications.length) }} of {{ filteredNotifications.length }} notifications
      </div>
      
      <div class="flex space-x-1">
        <button
          class="px-3 py-1 rounded border text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="currentPage === 1"
          @click="prevPage"
        >
          <span class="material-icons-outlined text-sm">chevron_left</span>
        </button>
        
        <button
          v-for="page in Array.from({ length: Math.min(5, totalPages) }, (_, i) => Math.max(1, currentPage - 2) + i)"
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
