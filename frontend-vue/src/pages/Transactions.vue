<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'

const transactions = ref([])
const allTransactions = ref([]) // Store all transactions for filter options
const transactionsPerPage = ref(10)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const statusFilter = ref('')
const roleFilter = ref('')
const locationFilter = ref('')
const requestedByFilter = ref('')
const approvedByFilter = ref('')
const borrowerNameFilter = ref('')
const itemNameFilter = ref('')
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
    
    // Note: Transactions are only created when borrow requests are approved/rejected
    // This handler is for BorrowRequestCreated events, but transactions don't exist yet
    // So we just refresh the list when approval/rejection events are received instead
    // This function is kept for compatibility but transactions should come from fetchTransactions()
    
    if (data && data.borrowRequest) {
      // Transactions are created on approval/rejection, so just refresh the list
      // The BorrowRequestApproved and BorrowRequestRejected events will trigger fetchTransactions()
      console.log('📝 Borrow request created - transactions will be available after approval/rejection')
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
      // Store all transactions for filter options
      allTransactions.value = response.data.data
      
      let filteredTransactions = response.data.data
      
      // Apply search filter
      if (debouncedSearchQuery.value) {
        const query = debouncedSearchQuery.value.toLowerCase()
        filteredTransactions = filteredTransactions.filter(t => 
          t.item_name?.toLowerCase().includes(query) ||
          t.borrower_name?.toLowerCase().includes(query) ||
          t.location?.toLowerCase().includes(query) ||
          t.approver_name?.toLowerCase().includes(query) ||
          t.requested_by?.toLowerCase().includes(query)
        )
      }
      
      // Apply status filter
      if (statusFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.status?.toLowerCase() === statusFilter.value.toLowerCase()
        )
      }
      
      // Apply role filter
      if (roleFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.role?.toUpperCase() === roleFilter.value.toUpperCase()
        )
      }
      
      // Apply location filter
      if (locationFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.location === locationFilter.value
        )
      }
      
      // Apply requested by filter
      if (requestedByFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.requested_by === requestedByFilter.value
        )
      }
      
      // Apply approved by filter
      if (approvedByFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.approver_name === approvedByFilter.value
        )
      }
      
      // Apply borrower name filter
      if (borrowerNameFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.borrower_name === borrowerNameFilter.value
        )
      }
      
      // Apply item name filter
      if (itemNameFilter.value) {
        filteredTransactions = filteredTransactions.filter(t => 
          t.item_name === itemNameFilter.value
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

// Watch for changes in filters and reset to page 1
watch([statusFilter, roleFilter, locationFilter, requestedByFilter, approvedByFilter, borrowerNameFilter, itemNameFilter], () => {
  currentPage.value = 1
  fetchTransactions()
})

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

// Computed properties for unique filter values
const uniqueLocations = computed(() => {
  const locations = [...new Set(allTransactions.value.map(t => t.location).filter(Boolean))]
  return locations.sort()
})

const uniqueRequestedBy = computed(() => {
  const requestedBy = [...new Set(allTransactions.value.map(t => t.requested_by).filter(Boolean))]
  return requestedBy.sort()
})

const uniqueApprovedBy = computed(() => {
  const approvedBy = [...new Set(allTransactions.value.map(t => t.approver_name).filter(Boolean))]
  return approvedBy.sort()
})

const uniqueBorrowerNames = computed(() => {
  const borrowerNames = [...new Set(allTransactions.value.map(t => t.borrower_name).filter(Boolean))]
  return borrowerNames.sort()
})

const uniqueItemNames = computed(() => {
  const itemNames = [...new Set(allTransactions.value.map(t => t.item_name).filter(Boolean))]
  return itemNames.sort()
})

const uniqueStatuses = computed(() => {
  const statuses = [...new Set(allTransactions.value.map(t => t.status).filter(Boolean))]
  return statuses.sort()
})

const uniqueRoles = computed(() => {
  const roles = [...new Set(allTransactions.value.map(t => t.role).filter(Boolean))]
  return roles.sort()
})

// Get filtered transactions (all, not just current page)
const getFilteredTransactions = () => {
  let filteredTransactions = allTransactions.value
  
  // Apply search filter
  if (debouncedSearchQuery.value) {
    const query = debouncedSearchQuery.value.toLowerCase()
    filteredTransactions = filteredTransactions.filter(t => 
      t.item_name?.toLowerCase().includes(query) ||
      t.borrower_name?.toLowerCase().includes(query) ||
      t.location?.toLowerCase().includes(query) ||
      t.approver_name?.toLowerCase().includes(query) ||
      t.requested_by?.toLowerCase().includes(query)
    )
  }
  
  // Apply status filter
  if (statusFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.status?.toLowerCase() === statusFilter.value.toLowerCase()
    )
  }
  
  // Apply role filter
  if (roleFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.role?.toUpperCase() === roleFilter.value.toUpperCase()
    )
  }
  
  // Apply location filter
  if (locationFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.location === locationFilter.value
    )
  }
  
  // Apply requested by filter
  if (requestedByFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.requested_by === requestedByFilter.value
    )
  }
  
  // Apply approved by filter
  if (approvedByFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.approver_name === approvedByFilter.value
    )
  }
  
  // Apply borrower name filter
  if (borrowerNameFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.borrower_name === borrowerNameFilter.value
    )
  }
  
  // Apply item name filter
  if (itemNameFilter.value) {
    filteredTransactions = filteredTransactions.filter(t => 
      t.item_name === itemNameFilter.value
    )
  }
  
  return filteredTransactions
}

// Export to Excel function
const exportToExcel = async () => {
  try {
    const filteredTransactions = getFilteredTransactions()
    
    if (filteredTransactions.length === 0) {
      alert('No transactions to export')
      return
    }
    
    console.log('Starting Excel export...', { 
      transactionCount: filteredTransactions.length,
      baseURL: axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    })
    
    // Prepare export parameters
    const params = new URLSearchParams()
    
    // Convert filtered transactions to format expected by backend
    const exportData = filteredTransactions.map(transaction => ({
      requested_by: transaction.requested_by || 'N/A',
      approver_name: transaction.approver_name || transaction.approved_by || 'N/A',
      approved_by: transaction.approver_name || transaction.approved_by || 'N/A',
      borrower_name: transaction.borrower_name || 'N/A',
      location: transaction.location || 'N/A',
      item_name: transaction.item_name || 'N/A',
      quantity: transaction.quantity || 0,
      transaction_time: transaction.transaction_time || 'N/A',
      role: transaction.role || 'USER',
      status: transaction.status || 'Pending'
    }))
    
    params.append('transactions', JSON.stringify(exportData))
    
    // Build the export URL
    const baseUrl = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    const exportUrl = baseUrl.includes('/v1') 
      ? '/transactions/export'
      : '/v1/transactions/export'
    
    const fullUrl = `${exportUrl}?${params.toString()}`
    const urlLength = fullUrl.length
    
    console.log('Export details:', {
      urlLength,
      baseURL: baseUrl,
      exportUrl,
      transactionCount: filteredTransactions.length
    })
    
    let response
    
    // If URL is too long, don't send transactions - let backend fetch all
    if (urlLength > 1800) {
      console.warn('URL too long, exporting all transactions instead of filtered transactions')
      response = await axiosClient.get(exportUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        },
        timeout: 60000,
        maxContentLength: Infinity,
        maxBodyLength: Infinity
      })
    } else {
      response = await axiosClient.get(fullUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        },
        timeout: 60000,
        maxContentLength: Infinity,
        maxBodyLength: Infinity
      })
    }
    
    console.log('Response received:', {
      status: response.status,
      contentType: response.headers['content-type'],
      size: response.data?.size || 'unknown'
    })
    
    // Check HTTP status code
    if (response.status !== 200) {
      const text = await response.data.text()
      try {
        const errorData = JSON.parse(text)
        throw new Error(errorData.message || `Export failed with status ${response.status}`)
      } catch (parseError) {
        throw new Error(`Server error (${response.status}): ${text}`)
      }
    }
    
    // Check content type
    const contentType = response.headers['content-type'] || ''
    
    // Check for JSON error response
    if (contentType.includes('application/json')) {
      const text = await response.data.text()
      try {
        const errorData = JSON.parse(text)
        throw new Error(errorData.message || 'Export failed')
      } catch (parseError) {
        throw new Error('Server returned an error: ' + text)
      }
    }
    
    // Verify it's actually an Excel file
    const blobSize = response.data.size || 0
    if (blobSize < 1000 && contentType !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
      const text = await response.data.text()
      throw new Error('Server error: ' + text)
    }
    
    // Create blob URL and trigger download
    const blob = response.data instanceof Blob 
      ? response.data 
      : new Blob([response.data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        })
    const url = window.URL.createObjectURL(blob)
    const downloadLink = document.createElement('a')
    downloadLink.href = url
    const now = new Date()
    const dateStr = now.toISOString().split('T')[0]
    downloadLink.download = `Transactions_${dateStr}.xlsx`
    document.body.appendChild(downloadLink)
    downloadLink.click()
    document.body.removeChild(downloadLink)
    window.URL.revokeObjectURL(url)
    
    console.log('✅ Excel file exported successfully')
  } catch (error) {
    console.error('❌ Error exporting to Excel:', error)
    console.error('Error details:', {
      message: error.message,
      code: error.code,
      response: error.response,
      status: error.response?.status,
      statusText: error.response?.statusText,
      data: error.response?.data
    })
    
    let errorMessage = 'Failed to export to Excel. Please try again.'
    
    if (error.code === 'ERR_NETWORK' || error.message === 'Network Error' || !error.response) {
      errorMessage = 'Network Error: Cannot connect to the server. Please check if the backend server is running.'
    } else if (error.response?.status === 404) {
      errorMessage = 'Export endpoint not found (404). Please check if the server is running and routes are registered.'
    } else if (error.response?.status === 500) {
      errorMessage = 'Server error during export (500). Please check the server logs.'
    } else if (error.message) {
      errorMessage = error.message
    } else if (error.response?.data) {
      if (typeof error.response.data === 'string') {
        try {
          const errorData = JSON.parse(error.response.data)
          errorMessage = errorData.message || errorMessage
        } catch (e) {
          errorMessage = error.response.data
        }
      } else if (error.response.data.message) {
        errorMessage = error.response.data.message
      }
    }
    
    alert(errorMessage)
  }
}

// Print function
const printTransactions = () => {
  try {
    const filteredTransactions = getFilteredTransactions()
    
    if (filteredTransactions.length === 0) {
      alert('No transactions to print')
      return
    }
    
    // Create print window content
    const printContent = `
      <!DOCTYPE html>
      <html>
        <head>
          <title>Transactions Report</title>
          <style>
            @media print {
              @page {
                margin: 1cm;
                size: A4 landscape;
              }
              body {
                margin: 0;
                padding: 20px;
                font-family: Arial, sans-serif;
                font-size: 10px;
              }
            }
            body {
              font-family: Arial, sans-serif;
              font-size: 10px;
              margin: 0;
              padding: 20px;
            }
            h1 {
              text-align: center;
              color: #059669;
              margin-bottom: 20px;
              font-size: 24px;
            }
            .print-info {
              text-align: center;
              margin-bottom: 20px;
              color: #666;
              font-size: 12px;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              margin-top: 20px;
            }
            th {
              background-color: #059669;
              color: white;
              padding: 10px 8px;
              text-align: left;
              border: 1px solid #ddd;
              font-weight: bold;
              font-size: 10px;
            }
            td {
              padding: 8px;
              border: 1px solid #ddd;
              font-size: 9px;
            }
            tr:nth-child(even) {
              background-color: #f9fafb;
            }
            tr:hover {
              background-color: #f3f4f6;
            }
            .footer {
              margin-top: 30px;
              text-align: center;
              color: #666;
              font-size: 10px;
            }
          </style>
        </head>
        <body>
          <h1>Transactions Report</h1>
          <div class="print-info">
            <p>Generated on: ${new Date().toLocaleString('en-PH', { 
              year: 'numeric', 
              month: 'long', 
              day: 'numeric', 
              hour: '2-digit', 
              minute: '2-digit' 
            })}</p>
            <p>Total Transactions: ${filteredTransactions.length}</p>
          </div>
          <table>
            <thead>
              <tr>
                <th>Requested By</th>
                <th>Approved By</th>
                <th>Name of Receiver</th>
                <th>Unit/Sectors</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Transaction Time</th>
                <th>Role</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${filteredTransactions.map(transaction => `
                <tr>
                  <td>${transaction.requested_by || 'N/A'}</td>
                  <td>${transaction.approver_name || 'N/A'}</td>
                  <td>${transaction.borrower_name || 'N/A'}</td>
                  <td>${transaction.location || 'N/A'}</td>
                  <td>${transaction.item_name || 'N/A'}</td>
                  <td>${transaction.quantity || 0}</td>
                  <td>${formatDateTime(transaction.transaction_time)}</td>
                  <td>${transaction.role || 'USER'}</td>
                  <td>${transaction.status || 'Pending'}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <div class="footer">
            <p>End of Report</p>
          </div>
        </body>
      </html>
    `
    
    // Open print window
    const printWindow = window.open('', '_blank')
    printWindow.document.write(printContent)
    printWindow.document.close()
    
    // Wait for content to load, then print
    printWindow.onload = () => {
      setTimeout(() => {
        printWindow.print()
      }, 250)
    }
    
    console.log('✅ Print dialog opened')
  } catch (error) {
    console.error('❌ Error printing transactions:', error)
    alert('Failed to print. Please try again.')
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
          <div class="flex items-center gap-3">
            <button
              @click="exportToExcel"
              :disabled="loading || totalTransactions === 0"
              class="flex items-center gap-2 px-4 py-2.5 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-lg transition-all font-semibold shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg">download</span>
              <span class="hidden sm:inline">Export Excel</span>
            </button>
            <button
              @click="printTransactions"
              :disabled="loading || totalTransactions === 0"
              class="flex items-center gap-2 px-4 py-2.5 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-lg transition-all font-semibold shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg">print</span>
              <span class="hidden sm:inline">Print</span>
            </button>
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
      <div class="flex flex-col gap-4">
        <!-- First Row: Show entries and Search -->
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
                placeholder="Search by item, borrower, unit/sectors, or approver..."
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

        <!-- Second Row: Column Filters -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
          <!-- Status Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">flag</span>
            </div>
            <select 
              v-model="statusFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Status</option>
              <option v-for="status in uniqueStatuses" :key="status" :value="status" class="bg-gray-700 text-white">{{ status }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Role Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">badge</span>
            </div>
            <select 
              v-model="roleFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Roles</option>
              <option v-for="role in uniqueRoles" :key="role" :value="role" class="bg-gray-700 text-white">{{ role }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Unit/Sectors Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">location_on</span>
            </div>
            <select 
              v-model="locationFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Unit/Sectors</option>
              <option v-for="location in uniqueLocations" :key="location" :value="location" class="bg-gray-700 text-white">{{ location }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Requested By Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">person_add</span>
            </div>
            <select 
              v-model="requestedByFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Requesters</option>
              <option v-for="requester in uniqueRequestedBy" :key="requester" :value="requester" class="bg-gray-700 text-white">{{ requester }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Approved By Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">admin_panel_settings</span>
            </div>
            <select 
              v-model="approvedByFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Approvers</option>
              <option v-for="approver in uniqueApprovedBy" :key="approver" :value="approver" class="bg-gray-700 text-white">{{ approver }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Name of Receiver Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">person</span>
            </div>
            <select 
              v-model="borrowerNameFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Receivers</option>
              <option v-for="borrower in uniqueBorrowerNames" :key="borrower" :value="borrower" class="bg-gray-700 text-white">{{ borrower }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Item Name Filter -->
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">inventory_2</span>
            </div>
            <select 
              v-model="itemNameFilter"
              class="w-full pl-10 pr-8 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md appearance-none cursor-pointer"
            >
              <option value="">All Items</option>
              <option v-for="item in uniqueItemNames" :key="item" :value="item" class="bg-gray-700 text-white">{{ item }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">arrow_drop_down</span>
            </div>
          </div>

          <!-- Clear Filters Button -->
          <button
            v-if="statusFilter || roleFilter || locationFilter || requestedByFilter || approvedByFilter || borrowerNameFilter || itemNameFilter"
            @click="statusFilter = ''; roleFilter = ''; locationFilter = ''; requestedByFilter = ''; approvedByFilter = ''; borrowerNameFilter = ''; itemNameFilter = ''"
            class="flex items-center justify-center gap-2 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all font-medium shadow-sm hover:shadow-md"
          >
            <span class="material-icons-outlined text-sm">clear</span>
            <span>Clear</span>
          </button>
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
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Requested By</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Approved By</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Name of Receiver</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Unit/Sectors</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Item Name</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Quantity</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Transaction Time</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Role</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="transactions.length === 0">
              <td colspan="9" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center">
                  <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                    <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">swap_horiz</span>
                  </div>
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No transactions found</h3>
                  <p class="text-gray-600 dark:text-gray-400">
                    {{ searchQuery || statusFilter || roleFilter || locationFilter || requestedByFilter || approvedByFilter || borrowerNameFilter || itemNameFilter
                      ? 'Try adjusting your search query or filters' 
                      : 'Transactions will appear here' }}
                  </p>
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
                  <span class="material-icons-outlined text-orange-400 text-sm">person_add</span>
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ transaction.requested_by || 'N/A' }}</span>
                </div>
              </td>
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
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold', getStatusClass(transaction.status)]">
                  <span class="material-icons-outlined text-sm">{{ getStatusIcon(transaction.status) }}</span>
                  {{ transaction.status || 'Pending' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && transactions.length > 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-lg" style="color: #01200E;">info</span>
            <span class="text-sm font-semibold" style="color: #01200E;">
              Showing <span class="font-bold" style="color: #01200E;">{{ totalTransactions > 0 ? ((currentPage - 1) * transactionsPerPage + 1) : 0 }}</span> to 
              <span class="font-bold" style="color: #01200E;">{{ Math.min(currentPage * transactionsPerPage, totalTransactions) }}</span> of 
              <span class="font-bold" style="color: #01200E;">{{ totalTransactions }}</span> entries
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

