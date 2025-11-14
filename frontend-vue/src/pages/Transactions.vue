<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'

const transactions = ref([])
const transactionsPerPage = ref(10)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
// Remove status filter for transactions table
const currentPage = ref(1)
const totalTransactions = ref(0)
const totalPages = ref(1)
const loading = ref(false)
const error = ref('')

// Store channel reference for cleanup
let transactionsChannel = null

onMounted(() => {
  fetchTransactions()
  
  // Set up real-time listener for transactions
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
    console.log('📡 Transactions Echo connection state:', connectionState)

    // If not connected, wait for connection
    if (connectionState !== 'connected') {
      console.log('⏳ Waiting for Echo connection...')
      
      const connectedHandler = () => {
        console.log('✅ Pusher connected! Setting up transactions listener...')
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
      console.log('🔔 Setting up real-time transactions listener...')
      
      const currentPusher = window.Echo?.connector?.pusher
      if (currentPusher) {
        console.log('📡 Pusher connection state:', currentPusher.connection?.state)
      }
      
      // Get or create channel
      if (!transactionsChannel) {
        transactionsChannel = window.Echo.channel('notifications')
      }
      
      // Use Pusher for subscription confirmation
      const pusher = window.Echo?.connector?.pusher
      if (pusher) {
        pusher.bind('pusher:subscription_succeeded', (data) => {
          if (data && (data.channel === 'notifications' || data.channel === 'private-notifications')) {
            console.log('✅✅✅ Successfully subscribed to notifications channel:', data.channel)
            console.log('🎯 Ready to receive transaction events')
          }
        })
        
        pusher.connection.bind('error', (err) => {
          console.error('❌ Pusher connection error:', err)
        })
        
        pusher.connection.bind('disconnected', () => {
          console.warn('⚠️ Pusher disconnected')
        })
      }
      
      // Listen for borrow request events
      transactionsChannel.listen('.BorrowRequestCreated', (data) => {
        console.log('📝📝📝 BorrowRequestCreated event received 📝📝📝')
        console.log('📝 Full event data:', JSON.stringify(data, null, 2))
        handleNewTransaction(data)
      })
      
      // Listen for borrow request approval/rejection
      transactionsChannel.listen('.BorrowRequestApproved', (data) => {
        console.log('✅ BorrowRequestApproved event received')
        fetchTransactions()
      })
      
      transactionsChannel.listen('.BorrowRequestRejected', (data) => {
        console.log('❌ BorrowRequestRejected event received')
        fetchTransactions()
      })
      
      console.log('✅ Real-time transactions listener active on channel: notifications')
      console.log('🧪 Listener setup complete. Waiting for transaction events...')
      
    } catch (error) {
      console.error('❌ Error setting up channel listener:', error)
      setTimeout(setupRealtimeListener, 3000)
    }
  }

  // Handler function for new transaction
  const handleNewTransaction = (data) => {
    console.log('📝 Processing new transaction:', data)
    
    if (data && data.borrowRequest) {
      // If we're on the first page and not searching/filtering, prepend the new transaction
      if (currentPage.value === 1 && !debouncedSearchQuery.value && !statusFilter.value) {
        const newTransaction = {
          id: data.borrowRequest.id,
          item_name: data.item?.description || data.item?.unit || 'N/A',
          item_pac: data.item?.pac || 'N/A',
          item_quantity: data.item?.quantity || 0,
          quantity: data.borrowRequest.quantity,
          location: data.borrowRequest.location,
          borrowed_by: data.borrowRequest.borrowed_by,
          status: data.borrowRequest.status,
          created_at: data.borrowRequest.created_at || new Date().toISOString(),
          approved_at: data.borrowRequest.approved_at,
          approved_by: data.borrowRequest.approved_by,
          approver: data.approver || null
        }
        
        transactions.value.unshift(newTransaction)
        
        // Update total count
        totalTransactions.value++
        
        // If we exceed transactions per page, remove the last one
        if (transactions.value.length > transactionsPerPage.value) {
          transactions.value.pop()
        }
      } else {
        // Otherwise, just refresh the current page
        fetchTransactions()
      }
    }
  }

  // Start setup - try immediately, then retry if needed
  setTimeout(setupRealtimeListener, 500)
})

// Clean up Echo listeners when component is unmounted
onUnmounted(() => {
  if (window.Echo && transactionsChannel) {
    console.log('🔇 Removing real-time transactions listener...')
    try {
      window.Echo.leave('notifications')
      transactionsChannel = null
      console.log('✅ Real-time transactions listener removed')
    } catch (error) {
      console.error('Error removing listener:', error)
    }
  }
})

// Fetch transactions from API
const fetchTransactions = async () => {
  try {
    loading.value = true
    error.value = ''
    
    // Check baseURL to avoid double /v1 in path
    const baseUrl = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    const requestUrl = baseUrl.includes('/v1') 
      ? `/transactions`
      : `/v1/transactions`
    
    const response = await axiosClient.get(requestUrl)
    
    if (response.data.success && response.data.data) {
      let filteredTransactions = response.data.data
      
      // Apply search filter
      if (debouncedSearchQuery.value) {
        const query = debouncedSearchQuery.value.toLowerCase()
        filteredTransactions = filteredTransactions.filter(t => 
          t.item_name?.toLowerCase().includes(query) ||
          t.borrower_name?.toLowerCase().includes(query) ||
          t.location?.toLowerCase().includes(query) ||
          t.approver_name?.toLowerCase().includes(query)
        )
      }
      
      // Calculate pagination
      totalTransactions.value = filteredTransactions.length
      totalPages.value = Math.ceil(totalTransactions.value / transactionsPerPage.value)
      
      // Apply pagination
      const start = (currentPage.value - 1) * transactionsPerPage.value
      const end = start + transactionsPerPage.value
      transactions.value = filteredTransactions.slice(start, end)
    } else {
      error.value = 'Failed to fetch transactions'
      transactions.value = []
    }
  } catch (err) {
    console.error('Error fetching transactions:', err)
    error.value = err.response?.data?.message || 'Failed to fetch transactions'
    transactions.value = []
  } finally {
    loading.value = false
  }
}

// Watch for changes in debounced search query and reset to page 1
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
  fetchTransactions()
})

// Status filter removed for transactions table

// Watch for changes in transactions per page
watch(transactionsPerPage, () => {
  currentPage.value = 1
  fetchTransactions()
})

// Watch for changes in current page
watch(currentPage, () => {
  fetchTransactions()
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

// Format date
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Format transaction time
const formatTransactionTime = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const hours = date.getHours()
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const ampm = hours >= 12 ? 'PM' : 'AM'
  const displayHours = hours % 12 || 12
  return `${day}-${month}-${year} at ${displayHours}:${minutes} ${ampm}`
}

// Format datetime for table display
const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  })
}

// Get status badge class
const getStatusClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
    case 'approved':
      return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
    case 'rejected':
      return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
    case 'borrowed':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
}

// Get status icon
const getStatusIcon = (status) => {
  switch (status?.toLowerCase()) {
    case 'pending':
      return 'schedule'
    case 'approved':
      return 'check_circle'
    case 'rejected':
      return 'cancel'
    case 'borrowed':
      return 'inventory_2'
    default:
      return 'help'
  }
}

// Get role badge class
const getRoleClass = (role) => {
  switch (role?.toLowerCase()) {
    case 'admin':
    case 'super_admin':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
    case 'user':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
}

// Get role icon
const getRoleIcon = (role) => {
  switch (role?.toLowerCase()) {
    case 'admin':
    case 'super_admin':
      return 'admin_panel_settings'
    case 'user':
      return 'person'
    default:
      return 'help'
  }
}
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
              <span class="material-icons-outlined text-4xl text-white">swap_horiz</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Transactions</h1>
              <p v-if="!loading" class="text-green-100 text-sm sm:text-base">
                {{ totalTransactions || 0 }} {{ totalTransactions === 1 ? 'transaction' : 'transactions' }} found
              </p>
              <p v-else class="text-green-100 text-sm sm:text-base">Loading transactions...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Card -->
    <div class="grid grid-cols-1 sm:grid-cols-1 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Transactions</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ totalTransactions || 0 }}</span>
            </p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">list_alt</span>
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
            v-model="transactionsPerPage"
            class="px-4 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md"
          >
            <option value="10" class="bg-gray-700 text-white">10</option>
            <option value="25" class="bg-gray-700 text-white">25</option>
            <option value="50" class="bg-gray-700 text-white">50</option>
            <option value="100" class="bg-gray-700 text-white">100</option>
          </select>
          <span class="text-gray-900 dark:text-white font-semibold">entries</span>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
          <div class="relative w-full sm:w-auto">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by item, borrower, location, or approver..."
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
        <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading transactions...</p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
      </div>
    </div>

    <!-- Transactions Table -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Approved By</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Borrower Name</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Location</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Item Name</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Quantity</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Transaction Time</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Role</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Status</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Created At</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Updated At</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="transactions.length === 0">
              <td colspan="10" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center">
                  <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                    <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">swap_horiz</span>
                  </div>
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No transactions found</h3>
                  <p class="text-gray-600 dark:text-gray-400">{{ searchQuery ? 'Try adjusting your search query' : 'Transactions will appear here' }}</p>
                </div>
              </td>
            </tr>
            <tr 
              v-for="transaction in transactions" 
              :key="transaction.id"
              class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
            >
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-indigo-400 text-sm">admin_panel_settings</span>
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ transaction.approver_name || 'N/A' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-blue-400 text-sm">person</span>
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ transaction.borrower_name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-purple-400 text-sm">location_on</span>
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ transaction.location || 'N/A' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-green-400 text-sm">inventory_2</span>
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ transaction.item_name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                  {{ transaction.quantity }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ formatDateTime(transaction.transaction_time) }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold', getRoleClass(transaction.role)]">
                  <span class="material-icons-outlined text-sm">{{ getRoleIcon(transaction.role) }}</span>
                  {{ transaction.role || 'USER' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold', getStatusClass(transaction.status)]">
                  <span class="material-icons-outlined text-sm">{{ getStatusIcon(transaction.status) }}</span>
                  {{ transaction.status || 'Pending' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ formatDateTime(transaction.created_at) }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ formatDateTime(transaction.updated_at) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && transactions.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-lg">info</span>
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
              Showing <span class="text-green-400 dark:text-green-400 font-bold">{{ totalTransactions > 0 ? ((currentPage - 1) * transactionsPerPage + 1) : 0 }}</span> to 
              <span class="text-green-400 dark:text-green-400 font-bold">{{ Math.min(currentPage * transactionsPerPage, totalTransactions) }}</span> of 
              <span class="text-green-400 dark:text-green-400 font-bold">{{ totalTransactions }}</span> entries
            </span>
          </div>
          <div v-if="totalPages > 1" class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
            <button 
              @click="goToPage(1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-semibold shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-sm">first_page</span>
            </button>
            <button 
              @click="prevPage"
              :disabled="currentPage === 1"
              class="px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-semibold shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-sm">chevron_left</span>
            </button>
            <button
              v-for="page in pageNumbers"
              :key="page"
              @click="goToPage(page)"
              :class="[
                'px-4 py-2 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md',
                currentPage === page
                  ? 'bg-green-600 text-white dark:bg-green-600 dark:text-white'
                  : 'bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'
              ]"
            >
              {{ page }}
            </button>
            <button 
              @click="nextPage"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-semibold shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-sm">chevron_right</span>
            </button>
            <button 
              @click="goToPage(totalPages)"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all font-semibold shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-sm">last_page</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-family: 'Material Icons Outlined';
  font-weight: normal;
  font-style: normal;
  font-size: inherit;
  line-height: 1;
  letter-spacing: normal;
  text-transform: none;
  display: inline-block;
  white-space: nowrap;
  word-wrap: normal;
  direction: ltr;
  -webkit-font-smoothing: antialiased;
}
</style>

