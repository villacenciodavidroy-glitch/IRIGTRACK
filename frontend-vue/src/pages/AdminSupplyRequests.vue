<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'
import useAuth from '../composables/useAuth'

const { isAdmin, fetchCurrentUser, user: currentUser } = useAuth()

const router = useRouter()
const requests = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const statusFilter = ref('') // Default filter to show all requests (supply_approved and admin_assigned)
const urgencyFilter = ref('')
const startDate = ref('')
const endDate = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(8)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 8,
  total: 0,
  from: 0,
  to: 0
})

// Action modals
const showRejectModal = ref(false)
const showApproveModal = ref(false)
const selectedRequest = ref(null)
const requestToApprove = ref(null)
const approvingRequest = ref(false)
const rejectForm = ref({
  reason: ''
})

// Message modal state
const showMessageModal = ref(false)
const selectedRequestForMessage = ref(null)
const messages = ref([])
const newMessage = ref('')
const showQrCodeModal = ref(false)
const selectedQrCodeUrl = ref(null)
const hoveredQrCode = ref(null)
const unreadCounts = ref({})
const loadingMessages = ref(false)
const requestPollingInterval = ref(null) // Polling interval for requests
const requestRealtimeListener = ref(null) // Real-time listener reference

// Banner state for success/error messages
const showBanner = ref(false)
const bannerMessage = ref('')
const bannerType = ref('success') // 'success' or 'error'
const bannerDetails = ref(null) // Additional details for the banner
let bannerTimeout = null
let bannerProgressInterval = null
const bannerProgress = ref(100)

// Show banner function
const showSimpleBanner = (message, type = 'success', autoHide = true, duration = 4000, details = null) => {
  // Clear any existing timeout
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  if (bannerProgressInterval) {
    clearInterval(bannerProgressInterval)
    bannerProgressInterval = null
  }
  
  bannerMessage.value = message
  bannerType.value = type
  bannerDetails.value = details
  bannerProgress.value = 100
  showBanner.value = true
  
  // Auto-hide after specified duration (default 4 seconds)
  if (autoHide) {
    // Animate progress bar
    const startTime = Date.now()
    bannerProgressInterval = setInterval(() => {
      const elapsed = Date.now() - startTime
      const remaining = Math.max(0, 100 - (elapsed / duration) * 100)
      bannerProgress.value = remaining
      
      if (remaining <= 0) {
        clearInterval(bannerProgressInterval)
        bannerProgressInterval = null
      }
    }, 50)
    
    bannerTimeout = setTimeout(() => {
      showBanner.value = false
      bannerTimeout = null
      if (bannerProgressInterval) {
        clearInterval(bannerProgressInterval)
        bannerProgressInterval = null
      }
    }, duration)
  }
}

// Close banner
const closeBanner = () => {
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  if (bannerProgressInterval) {
    clearInterval(bannerProgressInterval)
    bannerProgressInterval = null
  }
  showBanner.value = false
  bannerProgress.value = 100
}

// Fetch all requests (filtered for admin view)
const fetchRequests = async (silent = false) => {
  if (!silent) {
    loading.value = true
  }
  error.value = null
  
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value
    }
    
    if (debouncedSearchQuery.value) {
      params.search = debouncedSearchQuery.value
    }
    if (statusFilter.value) {
      params.status = statusFilter.value
    }
    if (urgencyFilter.value) {
      params.urgency = urgencyFilter.value
    }
    if (startDate.value) {
      params.start_date = startDate.value
    }
    if (endDate.value) {
      params.end_date = endDate.value
    }
    
    const response = await axiosClient.get('/supply-requests/all', { params })
    
    if (response.data.success) {
      const previousCount = requests.value.length
      const allRequests = response.data.data || []
      // Limit to itemsPerPage if backend returns more
      requests.value = allRequests.slice(0, itemsPerPage.value)
      
      // Calculate correct pagination values
      const apiPagination = response.data.pagination || pagination.value
      const total = apiPagination.total || allRequests.length
      const from = (currentPage.value - 1) * itemsPerPage.value + 1
      const to = Math.min(from + itemsPerPage.value - 1, total)
      const lastPage = Math.ceil(total / itemsPerPage.value)
      
      pagination.value = {
        current_page: currentPage.value,
        last_page: lastPage,
        per_page: itemsPerPage.value,
        total: total,
        from: from,
        to: to
      }
      
      // Initialize unread counts
      requests.value.forEach(request => {
        if (!unreadCounts.value.hasOwnProperty(request.id)) {
          unreadCounts.value[request.id] = request.unread_messages_count || 0
        }
      })
      
      fetchUnreadCounts()
    } else {
      requests.value = []
      error.value = response.data.message || 'Failed to load requests'
    }
  } catch (err) {
    console.error('Error fetching requests:', err)
    error.value = `Failed to load requests: ${err.response?.data?.message || err.message}`
    requests.value = []
  } finally {
    loading.value = false
  }
}

// Fetch unread message counts
const fetchUnreadCounts = async () => {
  try {
    for (const request of requests.value) {
      if (request.unread_messages_count !== undefined && request.unread_messages_count !== null) {
        unreadCounts.value[request.id] = request.unread_messages_count || 0
      }
    }
  } catch (err) {
    console.error('Error fetching unread counts:', err)
  }
}

// Get current user ID
const getCurrentUserId = () => {
  // Try to get from useAuth user first (more reliable)
  if (currentUser.value && currentUser.value.id) {
    return currentUser.value.id
  }
  // Fallback to localStorage
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    return user.id || null
  } catch {
    return null
  }
}

// Check if current admin is the assigned admin for this request
const isAssignedAdmin = (request) => {
  const currentUserId = getCurrentUserId()
  if (!currentUserId) {
    return false
  }
  
  // Convert to number for comparison to avoid type mismatch
  const currentId = Number(currentUserId)
  
  // If request has assigned_to_admin, check if current admin matches
  if (request.assigned_to_admin && request.assigned_to_admin.id) {
    const assignedId = Number(request.assigned_to_admin.id)
    const isMatch = assignedId === currentId
    
    // For admin_assigned status, only the assigned admin can approve/reject
    if (request.status === 'admin_assigned') {
      return isMatch
    }
    
    // For supply_approved or pending with assignment, only assigned admin can approve/reject
    if (request.status === 'supply_approved' || request.status === 'pending') {
      return isMatch
    }
    
    // If there's an assigned admin but status doesn't match, don't allow
    return false
  }
  
  // If status is admin_assigned but no assigned admin data, don't allow
  if (request.status === 'admin_assigned') {
    return false
  }
  
  // Require assignment before admin can approve/reject
  // Admins can only approve/reject requests that have been assigned to them
  return false
}

// Extract receipt URL from message
const extractReceiptUrl = (message) => {
  if (!message) return null
  const match = message.match(/Receipt Link:\s*(https?:\/\/[^\s]+)/i)
  return match ? match[1] : null
}

// Download PDF receipt
const downloadReceiptPDF = async (url, event, msg = null) => {
  event.preventDefault()
  if (!url) return
  
  try {
    let blob
    
    // Get request ID from message or selectedRequestForMessage
    const requestId = msg?.supply_request?.id || selectedRequestForMessage.value?.id
    
    // If we have the request ID, use the API endpoint (avoids CORS issues)
    if (requestId) {
      const response = await axiosClient.get(`/supply-requests/${requestId}/receipt`, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf'
        }
      })
      blob = response.data
    } else if (url.startsWith('/')) {
      // If URL is relative path, use axiosClient (includes auth headers)
      const response = await axiosClient.get(url, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf'
        }
      })
      blob = response.data
    } else {
      // For full URLs, try to extract path and use API endpoint if possible
      // Extract request number from URL if it's a storage URL
      const requestNumberMatch = url.match(/approval_receipt_(SR-[^_]+)/)
      if (requestNumberMatch && requestId) {
        // Use API endpoint with request ID
        const response = await axiosClient.get(`/supply-requests/${requestId}/receipt`, {
          responseType: 'blob',
          headers: {
            'Accept': 'application/pdf'
          }
        })
        blob = response.data
      } else {
        // Fallback: try fetch (may fail due to CORS)
        const response = await fetch(url, {
          method: 'GET',
          credentials: 'include',
          headers: {
            'Accept': 'application/pdf'
          }
        })
        
        if (!response.ok) {
          throw new Error('Failed to download PDF')
        }
        
        blob = await response.blob()
      }
    }
    
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = `receipt_${new Date().toISOString().split('T')[0]}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch (error) {
    console.error('Error downloading PDF:', error)
    showSimpleBanner('Failed to download PDF. Please try again.', 'error', true, 5000)
  }
}

// Extract receipt number from message
const extractReceiptNumber = (message) => {
  if (!message) return null
  const match = message.match(/Receipt Number:\s*([^\n]+)/i)
  return match ? match[1].trim() : null
}

// Extract approval date from message
const extractApprovalDate = (message) => {
  if (!message) return null
  const match = message.match(/Approval Date:\s*([^\n]+)/i)
  return match ? match[1].trim() : null
}

// Extract approver name from message
const extractApproverName = (message) => {
  if (!message) return null
  const match = message.match(/Approved By\s*:\s*([^\n]+)/i)
  return match ? match[1].trim() : null
}

// Extract item details from message
const extractItemDetails = (message) => {
  if (!message) return null
  
  const details = {}
  
  // Extract Item Name
  const itemMatch = message.match(/Item Name\s*:\s*([^\n]+)/i)
  if (itemMatch) {
    details['Item'] = itemMatch[1].trim()
  }
  
  // Extract Quantity
  const quantityMatch = message.match(/Quantity\s*:\s*([^\n]+)/i)
  if (quantityMatch) {
    details['Quantity'] = quantityMatch[1].trim()
  }
  
  // Extract Urgency Level
  const urgencyMatch = message.match(/Urgency Level\s*:\s*([^\n]+)/i)
  if (urgencyMatch) {
    details['Urgency'] = urgencyMatch[1].trim()
  }
  
  return Object.keys(details).length > 0 ? details : null
}

// Extract QR code URL from message
const extractQrCodeUrl = (message) => {
  if (!message) return null
  const match = message.match(/QR Code:\s*(https?:\/\/[^\s]+)/i)
  return match ? match[1] : null
}

// Clean message text by removing receipt details section
const cleanMessageText = (message) => {
  if (!message) return message
  
  // If message contains receipt URL, remove the receipt details section
  if (extractReceiptUrl(message)) {
    // Remove everything from the receipt separator onwards
    const receiptStartPattern = /━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━.*$/s
    const cleaned = message.replace(receiptStartPattern, '').trim()
    return cleaned || message.split('\n')[0] // Fallback to first line if everything was removed
  }
  
  return message
}

// Open message modal
const openMessageModal = async (request) => {
  selectedRequestForMessage.value = request
  showMessageModal.value = true
  await fetchMessages(request.id)
  await markMessagesAsRead(request.id)
}

// Fetch messages
const fetchMessages = async (requestId) => {
  loadingMessages.value = true
  try {
    const response = await axiosClient.get(`/supply-requests/${requestId}/messages`)
    if (response.data.success) {
      messages.value = response.data.data || []
    }
  } catch (err) {
    console.error('Error fetching messages:', err)
    messages.value = []
  } finally {
    loadingMessages.value = false
  }
}

// Send a message
const sendMessage = async () => {
  if (!newMessage.value.trim() || !selectedRequestForMessage.value) return
  
  try {
    const response = await axiosClient.post(`/supply-requests/${selectedRequestForMessage.value.id}/messages`, {
      message: newMessage.value.trim()
    })
    
    if (response.data.success) {
      messages.value.push(response.data.data)
      newMessage.value = ''
      await fetchUnreadCounts()
    }
  } catch (err) {
    console.error('Error sending message:', err)
    showSimpleBanner('Failed to send message: ' + (err.response?.data?.message || err.message), 'error', true, 5000)
  }
}

// Mark messages as read
const markMessagesAsRead = async (requestId) => {
  try {
    await axiosClient.post(`/supply-requests/${requestId}/messages/mark-read`)
    unreadCounts.value[requestId] = 0
  } catch (err) {
    console.error('Error marking messages as read:', err)
  }
}

// Close message modal
const closeMessageModal = () => {
  showMessageModal.value = false
  selectedRequestForMessage.value = null
  messages.value = []
  newMessage.value = ''
}

// Open approve modal
const openApproveModal = (request) => {
  // Check if request can be approved
  if (request.status !== 'pending' && request.status !== 'supply_approved' && request.status !== 'admin_assigned') {
    showSimpleBanner(`Cannot approve request. Current status: ${request.status}.`, 'error', true, 5000)
    return
  }
  requestToApprove.value = request
  showApproveModal.value = true
}

// Close approve modal
const closeApproveModal = () => {
  if (!approvingRequest.value) {
    showApproveModal.value = false
    requestToApprove.value = null
  }
}

// Approve request (Admin only - automatically generates receipt)
const approveRequest = async () => {
  if (!requestToApprove.value) return
  
  approvingRequest.value = true
  
  try {
    loading.value = true
    const response = await axiosClient.post(`/supply-requests/${requestToApprove.value.id}/approve`)
    
    if (response.data.success) {
      const requestDetails = requestToApprove.value
      showSimpleBanner(
        'Request Approved Successfully',
        'success', 
        true, 
        7000,
        {
          itemName: requestDetails?.item_name || requestDetails?.item?.name || 'N/A',
          quantity: requestDetails?.quantity,
          requester: requestDetails?.requested_by_user?.name || requestDetails?.user?.name || 'N/A',
          receiptGenerated: true
        }
      )
      showApproveModal.value = false
      requestToApprove.value = null
      fetchRequests()
    } else {
      showSimpleBanner(response.data.message || 'Failed to approve request', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error approving request:', err)
    const errorMessage = err.response?.data?.message || err.message || 'Failed to approve request'
    showSimpleBanner(errorMessage, 'error', true, 5000)
  } finally {
    loading.value = false
    approvingRequest.value = false
  }
}

// Open reject modal
const openRejectModal = (request) => {
  selectedRequest.value = request
  rejectForm.value = {
    reason: ''
  }
  showRejectModal.value = true
}

// Close reject modal
const closeRejectModal = () => {
  showRejectModal.value = false
  selectedRequest.value = null
}

// Reject request
const rejectRequest = async () => {
  if (!rejectForm.value.reason.trim()) {
    showSimpleBanner('Please provide a rejection reason', 'error', true, 4000)
    return
  }
  
  try {
    loading.value = true
    const response = await axiosClient.post(`/supply-requests/${selectedRequest.value.id}/reject`, {
      rejection_reason: rejectForm.value.reason.trim()
    })
    
    if (response.data.success) {
      showSimpleBanner('Request rejected successfully!', 'success', true, 5000)
      closeRejectModal()
      fetchRequests()
    } else {
      showSimpleBanner(response.data.message || 'Failed to reject request', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error rejecting request:', err)
    showSimpleBanner(err.response?.data?.message || 'Failed to reject request', 'error', true, 5000)
  } finally {
    loading.value = false
  }
}

// Get status badge class - Exact match to Personnel Management style
const getStatusBadgeClass = (status) => {
  const statusLower = status?.toLowerCase()
  if (statusLower === 'approved' || statusLower === 'admin_accepted' || statusLower === 'fulfilled') {
    return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
  }
  if (statusLower === 'supply_approved' || statusLower === 'admin_assigned') {
    return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  }
  if (statusLower === 'rejected') {
    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
  }
  return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
}

// Get urgency badge class - Exact match to Personnel Management style
const getUrgencyBadgeClass = (urgency) => {
  const urgencyLower = urgency?.toLowerCase()
  if (urgencyLower === 'high') {
    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
  }
  if (urgencyLower === 'medium') {
    return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
  }
  return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
}

// Format date
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch (e) {
    return dateString
  }
}

// Refresh data
const refreshData = () => {
  fetchRequests()
}

// Setup real-time listener for supply requests
const setupRequestRealtimeListener = () => {
  try {
    if (!window.Echo || !currentUser.value) {
      console.log('⏳ Echo not ready or user not loaded, retrying...')
      setTimeout(setupRequestRealtimeListener, 2000)
      return
    }
    
    // Clean up existing listener if any
    if (requestRealtimeListener.value) {
      try {
        window.Echo.leave('supply-requests')
      } catch (e) {
        console.log('No existing listener to clean up')
      }
    }
    
    // Listen on supply requests channel
    const requestsChannel = window.Echo.channel('supply-requests')
    
    // Listen for new requests
    requestsChannel.listen('.SupplyRequestCreated', (data) => {
      console.log('📋 New supply request received via WebSocket:', data)
      fetchRequests(true) // Silent refresh
    })
    
    // Listen for request status updates
    requestsChannel.listen('.SupplyRequestUpdated', (data) => {
      console.log('🔄 Supply request updated via WebSocket:', data)
      fetchRequests(true) // Silent refresh
    })
    
    // Listen for request approval
    requestsChannel.listen('.SupplyRequestApproved', (data) => {
      console.log('✅ Supply request approved via WebSocket:', data)
      fetchRequests(true) // Silent refresh
    })
    
    // Listen for request rejection
    requestsChannel.listen('.SupplyRequestRejected', (data) => {
      console.log('❌ Supply request rejected via WebSocket:', data)
      fetchRequests(true) // Silent refresh
    })
    
    // Listen for request fulfillment
    requestsChannel.listen('.SupplyRequestFulfilled', (data) => {
      console.log('📦 Supply request fulfilled via WebSocket:', data)
      fetchRequests(true) // Silent refresh
    })
    
    requestRealtimeListener.value = requestsChannel
    console.log('✅ Real-time supply request listener active (Admin)')
  } catch (error) {
    console.error('❌ Error setting up request real-time listener:', error)
    setTimeout(setupRequestRealtimeListener, 3000)
  }
}

onMounted(async () => {
  await fetchCurrentUser()
  await fetchRequests()
  
  // Setup real-time listener for supply requests
  setTimeout(() => {
    setupRequestRealtimeListener()
  }, 1000) // Wait for Echo to initialize
  
  // Setup polling as fallback for requests (every 5 seconds)
  requestPollingInterval.value = setInterval(async () => {
    if (document.visibilityState === 'visible') {
      await fetchRequests(true) // Silent refresh
    }
  }, 5000) // Poll every 5 seconds
})

// Cleanup on unmount
onBeforeUnmount(() => {
  // Clean up request polling interval
  if (requestPollingInterval.value) {
    clearInterval(requestPollingInterval.value)
    requestPollingInterval.value = null
  }
  
  // Clean up request real-time listener
  if (requestRealtimeListener.value && window.Echo) {
    try {
      window.Echo.leave('supply-requests')
      requestRealtimeListener.value = null
    } catch (e) {
      console.error('Error cleaning up request listener:', e)
    }
  }
})

watch(debouncedSearchQuery, () => {
  currentPage.value = 1
  fetchRequests()
})

watch([statusFilter, urgencyFilter, startDate, endDate], () => {
  currentPage.value = 1
  fetchRequests()
})

watch(currentPage, () => {
  fetchRequests()
})

watch(itemsPerPage, () => {
  currentPage.value = 1
  fetchRequests()
})

// Computed statistics for summary cards
const statistics = computed(() => {
  const totalRequests = pagination.value.total || 0
  const pendingRequests = requests.value.filter(r => 
    r.status === 'supply_approved' || r.status === 'pending' || r.status === 'admin_assigned'
  ).length
  const approvedRequests = requests.value.filter(r => 
    r.status === 'approved' || r.status === 'admin_accepted' || r.status === 'fulfilled'
  ).length
  
  return {
    total: totalRequests,
    pending: pendingRequests,
    approved: approvedRequests,
    currentPage: currentPage.value,
    lastPage: pagination.value.last_page || 1
  }
})
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8" :class="{ 'pt-20 sm:pt-24': showBanner }">
    <!-- Enhanced Professional Banner Notification -->
    <Transition name="banner-slide">
      <div
        v-if="showBanner"
        :class="[
          'fixed top-0 left-0 right-0 z-[60] shadow-2xl',
          bannerType === 'success' 
            ? 'bg-emerald-600 text-white' 
            : 'bg-red-600 text-white'
        ]"
      >
        <!-- Progress Bar -->
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-black/20">
          <div
            :class="[
              'h-full transition-all duration-75 ease-linear',
              bannerType === 'success' 
                ? 'bg-white/80' 
                : 'bg-white/80'
            ]"
            :style="{ width: bannerProgress + '%' }"
          ></div>
        </div>

        <div class="px-4 sm:px-6 py-4 sm:py-5">
          <div class="max-w-7xl mx-auto flex items-start gap-4">
            <!-- Icon Section -->
            <div :class="[
              'flex-shrink-0 p-2.5 sm:p-3 rounded-xl shadow-lg',
              bannerType === 'success' 
                ? 'bg-white/20 backdrop-blur-sm border-2 border-white/30' 
                : 'bg-white/20 backdrop-blur-sm border-2 border-white/30'
            ]">
              <span v-if="bannerType === 'success'" class="material-icons-outlined text-2xl sm:text-3xl text-white">check_circle</span>
              <span v-else class="material-icons-outlined text-2xl sm:text-3xl text-white">error</span>
            </div>

            <!-- Content Section -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <h3 class="text-base sm:text-lg font-bold mb-1.5 text-white drop-shadow-sm">
                    {{ bannerMessage }}
                  </h3>
                  
                  <!-- Additional Details for Approval Success -->
                  <div v-if="bannerType === 'success' && bannerDetails" class="mt-2 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs sm:text-sm text-white/90">
                      <div class="flex items-center gap-1.5 bg-white/10 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/20">
                        <span class="material-icons-outlined text-sm">inventory_2</span>
                        <span class="font-medium">{{ bannerDetails.itemName }}</span>
                      </div>
                      <div class="flex items-center gap-1.5 bg-white/10 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/20">
                        <span class="material-icons-outlined text-sm">inventory</span>
                        <span class="font-medium">Qty: {{ bannerDetails.quantity }}</span>
                      </div>
                      <div class="flex items-center gap-1.5 bg-white/10 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/20">
                        <span class="material-icons-outlined text-sm">person</span>
                        <span class="font-medium">{{ bannerDetails.requester }}</span>
                      </div>
                      <div v-if="bannerDetails.receiptGenerated" class="flex items-center gap-1.5 bg-white/10 backdrop-blur-sm px-2.5 py-1.5 rounded-lg border border-white/20">
                        <span class="material-icons-outlined text-sm">receipt</span>
                        <span class="font-medium">Receipt Generated</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Close Button -->
                <button
                  @click="closeBanner"
                  class="flex-shrink-0 p-1.5 sm:p-2 hover:bg-white/20 rounded-lg transition-all duration-200 text-white/90 hover:text-white hover:scale-110 active:scale-95"
                  title="Dismiss"
                >
                  <span class="material-icons-outlined text-xl sm:text-2xl">close</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-blue-700 to-blue-600 rounded-xl shadow-xl mb-6">
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">assignment</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">Supply Requests from Supply Account</h1>
              <p class="text-blue-100 text-sm sm:text-base">View and manage requests approved by Supply Account. Approve or reject requests. Stock will be deducted when you approve.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div>
      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-wrap gap-4 items-center">
          <div class="flex-1 min-w-[200px]">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by name, item, or request..."
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>
          <div>
            <select
              v-model="statusFilter"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option value="">All Status</option>
              <option value="supply_approved">Supply Approved</option>
              <option value="admin_assigned">Assigned to Admin</option>
              <option value="admin_accepted">Admin Accepted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="fulfilled">Fulfilled</option>
            </select>
          </div>
          <div>
            <select
              v-model="urgencyFilter"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option value="">All Urgency</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
            </select>
          </div>
          <div>
            <select
              v-model="itemsPerPage"
              @change="currentPage = 1"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option :value="8">8 per page</option>
              <option :value="10">10 per page</option>
              <option :value="25">25 per page</option>
              <option :value="50">50 per page</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-12">
        <div class="flex flex-col items-center justify-center">
          <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg mb-4">
            <span class="material-icons-outlined animate-spin text-4xl text-blue-600 dark:text-blue-400">refresh</span>
          </div>
          <p class="text-base font-medium text-gray-700 dark:text-gray-300">Loading requests...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
        <p class="text-red-700 dark:text-red-400 text-sm">{{ error }}</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="requests.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-12">
        <div class="flex flex-col items-center justify-center">
          <span class="material-icons-outlined text-5xl text-gray-400 dark:text-gray-500 mb-4">inbox</span>
          <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No requests found</h3>
          <p class="text-gray-600 dark:text-gray-400 text-center text-sm">{{ searchQuery || statusFilter ? 'Try adjusting your filters' : 'No supply requests from Supply Account available.' }}</p>
        </div>
      </div>

      <!-- Requests Table -->
      <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supply Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Urgency</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="(request, index) in requests" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <td class="px-6 py-4">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ request.user?.name || 'N/A' }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ request.user?.email || 'N/A' }}</div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900 dark:text-white">{{ request.user?.location || 'N/A' }}</div>
                </td>
                <td class="px-6 py-4">
                  <div v-if="request.items && request.items.length > 1" class="space-y-2">
                    <div v-for="(item, idx) in request.items" :key="idx" class="border-b border-gray-200 dark:border-gray-600 last:border-b-0 pb-2 last:pb-0">
                      <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.item_name }}</div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">Stock: {{ item.item_quantity }}</div>
                    </div>
                  </div>
                  <div v-else>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ request.item_name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Stock: {{ request.item_quantity }}</div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div v-if="request.items && request.items.length > 1" class="space-y-1">
                    <div v-for="(item, idx) in request.items" :key="idx" class="text-sm text-gray-900 dark:text-white">
                      {{ item.quantity }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 pt-1 border-t border-gray-200 dark:border-gray-600">
                      Total: {{ request.quantity }}
                    </div>
                  </div>
                  <div v-else class="text-sm text-gray-900 dark:text-white">
                    {{ request.quantity }}
                  </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ request.supply_name || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getUrgencyBadgeClass(request.urgency_level)]">
                    {{ request.urgency_level }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full', getStatusBadgeClass(request.status)]">
                    {{ request.status === 'supply_approved' ? 'Supply Approved' : 
                       request.status === 'admin_assigned' ? 'Assigned to Admin' :
                       request.status === 'admin_accepted' ? 'Admin Accepted' :
                       request.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(request.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <button
                    @click="openMessageModal(request)"
                    class="relative text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-4"
                    title="Messages"
                  >
                    Messages
                    <span 
                      v-if="(unreadCounts[request.id] || 0) > 0"
                      class="ml-1.5 inline-flex items-center justify-center w-5 h-5 bg-red-600 text-white text-xs rounded-full font-semibold"
                    >
                      {{ (unreadCounts[request.id] || 0) > 9 ? '9+' : (unreadCounts[request.id] || 0) }}
                    </span>
                  </button>
                  
                  <!-- Admin Actions -->
                  <!-- Only show approve/reject if current admin is the assigned admin -->
                  <template v-if="(request.status === 'supply_approved' || request.status === 'pending' || request.status === 'admin_assigned') && isAssignedAdmin(request)">
                    <button
                      @click="openApproveModal(request)"
                      class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-4"
                      title="Approve Request"
                    >
                      Approve
                    </button>
                    <button
                      @click="openRejectModal(request)"
                      class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                      title="Reject Request"
                    >
                      Reject
                    </button>
                  </template>
                  <div v-else-if="request.status === 'supply_approved' || request.status === 'pending' || request.status === 'admin_assigned'" class="text-xs text-gray-500 dark:text-gray-400">
                    <span v-if="request.assigned_to_admin">Assigned to: {{ request.assigned_to_admin.name }}</span>
                    <span v-else>Not assigned</span>
                  </div>
                  <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
          <div class="flex-1 flex justify-between sm:hidden">
            <button
              @click="currentPage = Math.max(1, currentPage - 1)"
              :disabled="currentPage === 1"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button
              @click="currentPage = Math.min(pagination.last_page, currentPage + 1)"
              :disabled="currentPage >= pagination.last_page"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700 dark:text-gray-300">
                Showing <span class="font-medium">{{ pagination.from || 0 }}</span> to <span class="font-medium">{{ pagination.to || 0 }}</span> of <span class="font-medium">{{ pagination.total || 0 }}</span> results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button
                  @click="currentPage = Math.max(1, currentPage - 1)"
                  :disabled="currentPage === 1"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span class="material-icons-outlined text-sm">chevron_left</span>
                </button>
                <template v-for="page in pagination.last_page" :key="page">
                  <button
                    v-if="page === 1 || page === pagination.last_page || (page >= currentPage - 1 && page <= currentPage + 1)"
                    @click="currentPage = page"
                    :class="{
                      'z-10 bg-blue-50 dark:bg-blue-900 border-blue-500 dark:border-blue-400 text-blue-600 dark:text-blue-300': currentPage === page,
                      'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600': currentPage !== page
                    }"
                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                  >
                    {{ page }}
                  </button>
                  <span
                    v-else-if="page === currentPage - 2 || page === currentPage + 2"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    ...
                  </span>
                </template>
                <button
                  @click="currentPage = Math.min(pagination.last_page, currentPage + 1)"
                  :disabled="currentPage >= pagination.last_page"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span class="material-icons-outlined text-sm">chevron_right</span>
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Approval Confirmation Modal -->
    <Transition name="modal-fade">
      <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeApproveModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md transform transition-all">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 rounded-t-lg border-b-2 border-blue-800">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-xl">check_circle</span>
                </div>
                <h3 class="text-lg font-bold text-white">Approve Request</h3>
              </div>
              <button
                @click="closeApproveModal"
                :disabled="approvingRequest"
                class="p-1.5 text-white/80 hover:text-white hover:bg-white/20 rounded-lg transition-all"
                title="Close"
              >
                <span class="material-icons-outlined text-xl">close</span>
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-4">
            <div class="flex items-start gap-4">
              <div class="p-3 bg-slate-100 dark:bg-slate-800 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-slate-600 dark:text-slate-400 text-2xl">help_outline</span>
              </div>
              <div class="flex-1">
                <p class="text-base font-semibold text-gray-900 dark:text-white mb-2">
                  Are you sure you want to approve this request?
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                  A receipt will be automatically generated and sent to the user.
                </p>
                <div v-if="requestToApprove" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 mt-3">
                  <div class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory_2</span>
                      <span><strong>Item:</strong> {{ requestToApprove.item_name || requestToApprove.item?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory</span>
                      <span><strong>Quantity:</strong> {{ requestToApprove.quantity }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">person</span>
                      <span><strong>Requested by:</strong> {{ requestToApprove.requested_by_user?.name || requestToApprove.user?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">flag</span>
                      <span><strong>Status:</strong> <span class="capitalize">{{ requestToApprove.status }}</span></span>
                    </div>
                  </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-3 flex items-start gap-2">
                  <span class="material-icons-outlined text-slate-500 text-sm mt-0.5">info</span>
                  <span>The request will be approved, a receipt will be generated, and the requester will be notified.</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 rounded-b-xl border-t border-gray-200 dark:border-gray-600 flex items-center gap-3">
            <button
              @click="closeApproveModal"
              :disabled="approvingRequest"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg">close</span>
              <span>Cancel</span>
            </button>
            <button
              @click="approveRequest"
              :disabled="approvingRequest"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-all shadow-sm hover:shadow disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!approvingRequest" class="material-icons-outlined text-lg">check_circle</span>
              <span v-else class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ approvingRequest ? 'Approving...' : 'Approve Request' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Reject Request Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-xl border border-gray-200 w-full max-w-md p-6 space-y-4">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-lg font-semibold text-slate-800">Reject Request</h3>
            <p class="text-sm text-slate-600 mt-1">Request: {{ selectedRequest?.item_name }}</p>
          </div>
          <button @click="closeRejectModal" class="text-slate-500 hover:text-slate-700 transition-colors">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Rejection Reason *</label>
            <textarea
              v-model="rejectForm.reason"
              rows="4"
              placeholder="Please provide a reason for rejection..."
              class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
          <button
            @click="closeRejectModal"
            class="px-4 py-2 text-sm font-medium rounded-md border border-gray-300 text-slate-700 hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            @click="rejectRequest"
            :disabled="loading || !rejectForm.reason.trim()"
            class="px-4 py-2 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm"
          >
            Reject Request
          </button>
        </div>
      </div>
    </div>

    <!-- Message Modal -->
    <div v-if="showMessageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-xl border border-gray-200 w-full max-w-2xl p-4 md:p-6 space-y-3 md:space-y-4 max-h-[85vh] flex flex-col">
        <div class="flex items-start justify-between flex-shrink-0 border-b border-gray-200 pb-3">
          <div>
            <h3 class="text-lg md:text-xl font-semibold text-slate-800">Messages</h3>
            <p class="text-xs md:text-sm text-slate-600 mt-1">{{ selectedRequestForMessage?.item_name || 'Request' }} - {{ selectedRequestForMessage?.user?.name || 'User' }}</p>
          </div>
          <button @click="closeMessageModal" class="text-slate-500 hover:text-slate-700 flex-shrink-0 transition-colors">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>

        <!-- Messages List -->
        <div class="flex-1 overflow-y-auto space-y-3 md:space-y-4 min-h-0 pr-1">
          <div v-if="loadingMessages" class="flex justify-center py-8">
            <span class="material-icons-outlined animate-spin text-2xl text-slate-600">refresh</span>
          </div>
          <div v-else-if="messages.length === 0" class="text-center py-8 text-slate-500">
            <span class="material-icons-outlined text-4xl mb-2 block">message</span>
            <p class="text-sm">No messages yet</p>
          </div>
          <div v-else v-for="msg in messages" :key="msg.id" class="flex gap-3">
            <div class="flex-shrink-0">
              <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center border border-slate-200">
                <span class="material-icons-outlined text-slate-600 text-sm">person</span>
              </div>
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-semibold text-gray-900">{{ msg.user.name }}</span>
                <span class="text-xs text-gray-500">{{ msg.user.role }}</span>
                <span class="text-xs text-gray-500">{{ msg.created_at_formatted || formatDate(msg.created_at) }}</span>
                <span v-if="!msg.is_read && msg.user.id !== getCurrentUserId()" class="text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md border border-slate-200">New</span>
              </div>
              <div class="bg-gray-50 rounded-md p-3 border border-gray-200">
                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">{{ cleanMessageText(msg.message) }}</p>
                <!-- Receipt Details Display -->
                <div v-if="extractReceiptUrl(msg.message)" class="mt-3 pt-3 border-t border-gray-200">
                  <div class="bg-white border-2 border-green-300 rounded-lg shadow-sm overflow-hidden max-w-full">
                    <!-- Receipt Header -->
                    <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-3 py-2.5">
                      <div class="flex items-center gap-2">
                        <span class="material-icons-outlined text-white text-lg flex-shrink-0">receipt_long</span>
                        <h4 class="text-xs font-bold text-white uppercase tracking-wide truncate">Approval Receipt</h4>
                      </div>
                    </div>
                    
                    <!-- Receipt Body -->
                    <div class="p-3 bg-gradient-to-br from-green-50 to-white">
                      <div class="space-y-2">
                        <!-- Receipt Number -->
                        <div v-if="extractReceiptNumber(msg.message)" class="flex flex-col sm:flex-row sm:items-start sm:justify-between py-1.5 border-b border-green-200 gap-1">
                          <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide flex-shrink-0">Receipt #</span>
                          <span class="text-xs font-mono font-bold text-gray-900 break-all">{{ extractReceiptNumber(msg.message) }}</span>
                        </div>
                        
                        <!-- Approval Date -->
                        <div v-if="extractApprovalDate(msg.message)" class="flex flex-col sm:flex-row sm:items-start sm:justify-between py-1.5 border-b border-green-200 gap-1">
                          <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide flex-shrink-0">Approved</span>
                          <span class="text-xs text-gray-900 break-words sm:text-right">{{ extractApprovalDate(msg.message) }}</span>
                        </div>
                        
                        <!-- Approved By -->
                        <div v-if="extractApproverName(msg.message)" class="flex flex-col sm:flex-row sm:items-start sm:justify-between py-1.5 border-b border-green-200 gap-1">
                          <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide flex-shrink-0">Approved By</span>
                          <span class="text-xs text-gray-900 break-words sm:text-right">{{ extractApproverName(msg.message) }}</span>
                        </div>
                        
                        <!-- Item Details Section -->
                        <div v-if="extractItemDetails(msg.message)" class="pt-2">
                          <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Item Details</div>
                          <div class="bg-white rounded border border-green-200 p-2 space-y-1.5">
                            <div v-for="(value, key) in extractItemDetails(msg.message)" :key="key" class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1">
                              <span class="text-xs text-gray-600 flex-shrink-0">{{ key }}:</span>
                              <span class="text-xs font-medium text-gray-900 break-words sm:text-right">{{ value }}</span>
                            </div>
                          </div>
                        </div>
                        
                        <!-- QR Code Section -->
                        <div v-if="extractQrCodeUrl(msg.message)" class="pt-3 border-t border-green-200">
                          <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2 text-center">📱 Scan QR Code</div>
                          <div class="flex justify-center">
                            <div 
                              class="relative cursor-pointer group"
                              @click="selectedQrCodeUrl = extractQrCodeUrl(msg.message); showQrCodeModal = true"
                              @mouseenter="hoveredQrCode = msg.id"
                              @mouseleave="hoveredQrCode = null"
                            >
                              <img 
                                :src="extractQrCodeUrl(msg.message)" 
                                alt="Receipt QR Code" 
                                :class="[
                                  'w-32 h-32 border-2 rounded-lg p-1 bg-white transition-all duration-300',
                                  hoveredQrCode === msg.id 
                                    ? 'border-green-500 shadow-lg scale-110 border-4' 
                                    : 'border-green-300 shadow-sm'
                                ]"
                              />
                              <div 
                                v-if="hoveredQrCode === msg.id"
                                class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-lg transition-opacity duration-300"
                              >
                                <div class="text-white text-xs font-semibold bg-green-600 px-3 py-1 rounded-full">
                                  Click to enlarge
                                </div>
                              </div>
                            </div>
                          </div>
                          <p class="text-xs text-center text-gray-600 mt-2">Scan to verify receipt details • Click to enlarge</p>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Receipt Footer -->
                    <div class="bg-green-50 px-3 py-2.5 border-t border-green-200">
                      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-start gap-2 flex-1 min-w-0">
                          <span class="material-icons-outlined text-green-600 text-sm flex-shrink-0 mt-0.5">info</span>
                          <p class="text-xs text-green-700 break-words">Present this receipt when picking up items</p>
                        </div>
                        <button
                          @click="downloadReceiptPDF(extractReceiptUrl(msg.message), $event, msg)"
                          class="px-2.5 py-1.5 text-xs text-green-700 hover:text-green-900 hover:bg-green-100 rounded-md transition-colors flex items-center gap-1.5 border border-green-300 bg-white flex-shrink-0 whitespace-nowrap"
                          title="Download PDF receipt"
                        >
                          <span class="material-icons-outlined text-sm">download</span>
                          <span>PDF</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Message Input -->
        <div class="border-t border-gray-200 pt-4">
          <div class="flex gap-2">
            <textarea
              v-model="newMessage"
              @keydown.enter.exact.prevent="sendMessage"
              rows="2"
              placeholder="Type your message..."
              class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 resize-none"
            ></textarea>
            <button
              @click="sendMessage"
              :disabled="!newMessage.trim()"
              class="px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
            >
              <span class="material-icons-outlined text-sm">send</span>
              <span>Send</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- QR Code Modal -->
    <div v-if="showQrCodeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showQrCodeModal = false">
      <div class="bg-white rounded-lg shadow-xl border border-gray-200 p-6 md:p-8 max-w-md w-full mx-4">
        <div class="flex items-start justify-between mb-4 border-b border-gray-200 pb-3">
          <div>
            <h3 class="text-lg md:text-xl font-semibold text-slate-800">📱 Receipt QR Code</h3>
            <p class="text-xs text-slate-600 mt-1">Scan with your mobile device to verify receipt details</p>
          </div>
          <button @click="showQrCodeModal = false" class="text-slate-500 hover:text-slate-700 transition-colors">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
        
        <div class="flex flex-col items-center space-y-4">
          <div class="bg-gradient-to-br from-green-50 to-white p-6 rounded-lg border-2 border-green-300">
            <img 
              v-if="selectedQrCodeUrl"
              :src="selectedQrCodeUrl" 
              alt="Receipt QR Code" 
              class="w-64 h-64 mx-auto"
            />
          </div>
          
          <div class="text-center space-y-2">
            <p class="text-sm font-medium text-gray-700">Scan this QR code to:</p>
            <ul class="text-xs text-gray-600 space-y-1 text-left max-w-xs mx-auto">
              <li class="flex items-start gap-2">
                <span class="text-green-600 mt-0.5">✓</span>
                <span>Verify receipt authenticity</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="text-green-600 mt-0.5">✓</span>
                <span>View receipt details</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="text-green-600 mt-0.5">✓</span>
                <span>Check approval status</span>
              </li>
            </ul>
          </div>
          
          <button
            @click="showQrCodeModal = false"
            class="w-full px-4 py-2 bg-slate-700 text-white rounded-md hover:bg-slate-800 transition-colors"
          >
            Close
          </button>
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
  font-size: 24px;
  line-height: 1;
  letter-spacing: normal;
  text-transform: none;
  display: inline-block;
  white-space: nowrap;
  word-wrap: normal;
  direction: ltr;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  background: linear-gradient(to right, #000000, #575757);
  @apply text-white px-4 py-2.5 rounded-xl flex items-center text-base font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

.btn-primary-enhanced:hover {
  background: linear-gradient(to right, #1a1a1a, #6b6b6b);
}

.btn-secondary-enhanced {
  @apply bg-white text-gray-700 px-4 py-2.5 rounded-xl border-2 border-gray-300 hover:bg-gray-50 hover:border-green-400 flex items-center text-base font-semibold transition-all duration-200 shadow-sm hover:shadow-md;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Modal fade transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Banner slide transition */
/* Banner slide transition */
.banner-slide-enter-active {
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.banner-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.7, 0, 0.84, 0);
}

.banner-slide-enter-from {
  transform: translateY(-100%);
  opacity: 0;
}

.banner-slide-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}

/* Banner animation for icon */
@keyframes bannerIconPulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

.banner-slide-enter-active .material-icons-outlined {
  animation: bannerIconPulse 0.6s ease-in-out;
}
</style>
