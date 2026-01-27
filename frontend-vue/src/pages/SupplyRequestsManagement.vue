<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'
import useAuth from '../composables/useAuth'

const { isAdmin, fetchCurrentUser, user: currentUser } = useAuth()

const router = useRouter()
const route = useRoute()
const requests = ref([])
const stockOverview = ref([])
const stockSummary = ref({
  total_items: 0,
  total_quantity: 0,
  low_stock_count: 0
})
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const statusFilter = ref('')
const startDate = ref('')
const endDate = ref('')
const activeTab = ref('active') // 'active' or 'completed'
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
const showForwardModal = ref(false)
const showAssignModal = ref(false)
const showRejectModal = ref(false)
const showApproveModal = ref(false)
const showFulfillModal = ref(false)
const showPickupScheduleModal = ref(false)
const showViewDetailsModal = ref(false)
const selectedViewRequest = ref(null)
const selectedRequest = ref(null)
const requestToApprove = ref(null)
const requestToFulfill = ref(null)
const requestToSchedulePickup = ref(null)
const pickupScheduleForm = ref({
  pickup_scheduled_at: '',
  notify_user: true,
  quick_interval: null // Store selected quick interval
})
const schedulingPickup = ref(false)
const manualMinutes = ref(null) // For manual minutes input
const notifyingUser = ref(false) // For tracking notify button state
const approvingRequest = ref(false)
const fulfillingRequest = ref(false)
const forwardForm = ref({
  supply_account_id: '',
  comments: ''
})
const assignForm = ref({
  admin_id: ''
})
const rejectForm = ref({
  reason: ''
})
const showRejectItemModal = ref(false)
const rejectItemTarget = ref(null) // { requestId, item }
const rejectItemReason = ref('')
const rejectingItem = ref(false)
const admins = ref([])
const supplyAccounts = ref([])
const notifyingRestockItemId = ref(null) // item id when "Notify restock" is in progress
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

// Message modal state
const showMessageModal = ref(false)
const selectedRequestForMessage = ref(null)
const messages = ref([])
const newMessage = ref('')
const showQrCodeModal = ref(false)
const selectedQrCodeUrl = ref(null)
const hoveredQrCode = ref(null)
const unreadCounts = ref({}) // Store unread counts per request
const loadingMessages = ref(false)

// Approval proof modal state
const showApprovalProofModal = ref(false)
const selectedRequestForProof = ref(null)
const pdfError = ref(false)
const imageError = ref(false)

// All messages view state
const showAllMessagesView = ref(false)
const allMessages = ref([])
const loadingAllMessages = ref(false)
const selectedSender = ref(null) // For grouped conversations

// Fetch all requests
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
    // Only apply status filter if activeTab is 'completed' or if explicitly set
    // For 'active' tab, we want to see all non-completed statuses, so don't filter by status
    if (statusFilter.value && activeTab.value !== 'active') {
      params.status = statusFilter.value
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
      
      // Initialize unread counts for all requests from API response
      requests.value.forEach(request => {
        unreadCounts.value[request.id] = request.unread_messages_count || 0
      })
      
      // Show notification if new requests arrived (only if not silent and on first page)
      if (!silent && currentPage.value === 1 && requests.value.length > previousCount) {
        const newRequestsCount = requests.value.length - previousCount
        if (newRequestsCount > 0) {
          console.log(`📋 ${newRequestsCount} new request(s) received`)
        }
      }
    } else {
      requests.value = []
      error.value = response.data.message || 'Failed to load requests'
    }
  } catch (err) {
    console.error('Error fetching requests:', err)
    error.value = `Failed to load requests: ${err.response?.data?.message || err.message}`
    requests.value = []
  } finally {
    if (!silent) {
      loading.value = false
    }
    // Fetch unread counts in background (non-blocking) after loading is done
    fetchUnreadCounts().catch(err => console.error('Error fetching unread counts:', err))
  }
}

// Fetch stock overview
const fetchStockOverview = async () => {
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
      // If API returns success: false, set default values
      stockSummary.value = {
        total_items: 0,
        total_quantity: 0,
        low_stock_count: 0
      }
    }
  } catch (err) {
    console.error('Error fetching stock overview:', err)
    // Set default values on error so KPI cards still display
    stockSummary.value = {
      total_items: 0,
      total_quantity: 0,
      low_stock_count: 0
    }
    // Only show error banner if it's not a silent refresh
    if (err.response?.status !== 401 && err.response?.status !== 403) {
      console.warn('Stock overview fetch failed, using default values:', err.message)
    }
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

// Fetch unread message counts (non-blocking, runs in background)
const fetchUnreadCounts = async () => {
  try {
    // Only fetch for requests that don't have unread_messages_count from API
    const requestsNeedingFetch = requests.value.filter(request => 
      request.unread_messages_count === undefined || request.unread_messages_count === null
    )
    
    if (requestsNeedingFetch.length === 0) {
      return // All counts already available from API
    }
    
    // Fetch all unread counts in parallel (much faster than sequential)
    const fetchPromises = requestsNeedingFetch.map(async (request) => {
      try {
        const response = await axiosClient.get(`/supply-requests/${request.id}/messages`)
        if (response.data.success) {
          const currentUserId = getCurrentUserId()
          const unread = response.data.data.filter(msg => !msg.is_read && msg.user.id !== currentUserId).length
          unreadCounts.value[request.id] = unread || 0
        } else {
          unreadCounts.value[request.id] = 0
        }
      } catch (err) {
        console.error(`Error fetching unread count for request ${request.id}:`, err)
        unreadCounts.value[request.id] = 0
      }
    })
    
    // Wait for all requests to complete in parallel
    await Promise.all(fetchPromises)
  } catch (err) {
    console.error('Error fetching unread counts:', err)
  }
}

// Get current user ID (helper function)
const getCurrentUserId = () => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    return user.id || null
  } catch {
    return null
  }
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

// Extract item details from message (supports multiple items)
const extractItemDetails = (message) => {
  if (!message) return null
  
  const details = {}
  const items = []
  
  // Check for multiple items pattern (Item 1:, Item 2:, etc.)
  const multiItemPattern = /Item\s+(\d+):\s*\n\s*Item Name\s*:\s*([^\n]+)\s*\n\s*Quantity\s*:\s*([^\n]+)/gi
  let match
  let hasMultipleItems = false
  
  while ((match = multiItemPattern.exec(message)) !== null) {
    hasMultipleItems = true
    items.push({
      number: match[1],
      name: match[2].trim(),
      quantity: match[3].trim()
    })
  }
  
  if (hasMultipleItems && items.length > 0) {
    // Multiple items found
    details['Items'] = items
    // Extract Total Quantity
    const totalMatch = message.match(/Total Quantity\s*:\s*([^\n]+)/i)
    if (totalMatch) {
      details['Total Quantity'] = totalMatch[1].trim()
    }
  } else {
    // Single item (backward compatible)
    const itemMatch = message.match(/Item Name\s*:\s*([^\n]+)/i)
    if (itemMatch) {
      details['Item'] = itemMatch[1].trim()
    }
    
    // Extract Quantity
    const quantityMatch = message.match(/Quantity\s*:\s*([^\n]+)/i)
    if (quantityMatch) {
      details['Quantity'] = quantityMatch[1].trim()
    }
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
  
  // Fetch messages and mark as read in parallel (non-blocking)
  Promise.all([
    fetchMessages(request.id),
    markMessagesAsRead(request.id)
  ]).catch(err => {
    console.error('Error loading messages:', err)
  })
}

// Fetch messages for a request
const fetchMessages = async (requestId) => {
  loadingMessages.value = true
  try {
    const response = await axiosClient.get(`/supply-requests/${requestId}/messages`)
    if (response.data.success) {
      messages.value = response.data.data || []
    }
  } catch (err) {
    console.error('Error fetching messages:', err)
    // Handle unauthorized access
    if (err.response?.status === 403) {
      showSimpleBanner('You do not have permission to view messages for this request.', 'error', true, 5000)
      closeMessageModal()
      messages.value = []
    } else {
      messages.value = []
    }
  } finally {
    loadingMessages.value = false
  }
}

// Send a message
const sendMessage = async () => {
  if (!newMessage.value.trim() || !selectedRequestForMessage.value) return
  
  const messageText = newMessage.value.trim()
  newMessage.value = '' // Clear input immediately for better UX
  
  try {
    const response = await axiosClient.post(`/supply-requests/${selectedRequestForMessage.value.id}/messages`, {
      message: messageText
    })
    
    if (response.data.success) {
      messages.value.push(response.data.data)
      // Refresh unread counts in background (non-blocking)
      fetchUnreadCounts().catch(err => console.error('Error refreshing unread counts:', err))
    }
  } catch (err) {
    console.error('Error sending message:', err)
    // Restore message text on error
    newMessage.value = messageText
    const errorMessage = err.response?.status === 403 
      ? 'You do not have permission to send messages for this request.'
      : (err.response?.data?.message || err.message)
    showSimpleBanner('Failed to send message: ' + errorMessage, 'error', true, 5000)
  }
}

// Mark messages as read
const markMessagesAsRead = async (requestId) => {
  try {
    await axiosClient.post(`/supply-requests/${requestId}/messages/mark-read`)
    // Update local unread counts
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

// View Details Modal
const openViewDetailsModal = (request) => {
  selectedViewRequest.value = request
  showViewDetailsModal.value = true
}

const closeViewDetailsModal = () => {
  showViewDetailsModal.value = false
  selectedViewRequest.value = null
}

// Helper functions to open modals from View Details Modal
const handleApproveFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    openApproveModal(request)
  }, 200)
}

const handleRejectFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    openRejectModal(request)
  }, 200)
}

const handleForwardFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    openForwardModal(request)
  }, 200)
}

const handleNotifyUserFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    notifyUserReadyForPickup(request)
  }, 200)
}

const handleSchedulePickupFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    openPickupScheduleModal(request)
  }, 200)
}

const handleFulfillFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    openFulfillModal(request)
  }, 200)
}

const handleAssignFromView = (request) => {
  closeViewDetailsModal()
  setTimeout(() => {
    openAssignModal(request)
  }, 200)
}

// View approval proof
const viewApprovalProof = (request) => {
  selectedRequestForProof.value = request
  showApprovalProofModal.value = true
  pdfError.value = false
  imageError.value = false
}

// Handle PDF loading errors
const handlePdfError = () => {
  pdfError.value = true
}

// Handle image loading errors
const handleImageError = () => {
  imageError.value = true
}

// Get receipt URL using API endpoint (with token in query for iframe/object tags)
const getReceiptUrl = (requestId) => {
  if (!requestId) return ''
  
  // Get base URL from environment or use default
  let baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
  
  // Remove trailing slashes
  baseUrl = baseUrl.replace(/\/+$/, '')
  
  // Check if base URL already includes /api/v1
  if (baseUrl.includes('/api/v1')) {
    // Already has /api/v1, just add the endpoint
    baseUrl = baseUrl
  } else if (baseUrl.includes('/api')) {
    // Has /api but not /v1, add /v1
    baseUrl = `${baseUrl}/v1`
  } else if (baseUrl.startsWith('http')) {
    // Full URL without /api, add /api/v1
    baseUrl = `${baseUrl}/api/v1`
  } else {
    // Relative path, prepend origin and add /api/v1
    baseUrl = `${window.location.origin}${baseUrl.startsWith('/') ? baseUrl : '/' + baseUrl}/api/v1`
  }
  
  const token = localStorage.getItem('token')
  // Use API endpoint with token in query parameter (for iframe/object tags that can't send headers)
  return `${baseUrl}/supply-requests/${requestId}/receipt${token ? '?token=' + encodeURIComponent(token) : ''}`
}

// Close approval proof modal
const closeApprovalProofModal = () => {
  showApprovalProofModal.value = false
  selectedRequestForProof.value = null
  pdfError.value = false
  imageError.value = false
}

// Fetch admins for forwarding
const fetchAdmins = async () => {
  try {
    const response = await axiosClient.get('/users')
    if (response.data) {
      admins.value = (response.data.data || response.data || []).filter(user => {
        const role = (user.role || '').toLowerCase()
        return role === 'admin' || role === 'super_admin'
      })
    }
  } catch (err) {
    console.error('Error fetching admins:', err)
  }
}

// Fetch supply accounts (excluding current user)
const fetchSupplyAccounts = async () => {
  try {
    const response = await axiosClient.get('/users')
    if (response.data) {
      const currentUser = await fetchCurrentUser()
      const allUsers = response.data.data || response.data || []
      supplyAccounts.value = allUsers.filter(user => {
        const role = (user.role || '').toLowerCase()
        return role === 'supply' && user.id !== currentUser?.id
      })
    }
  } catch (err) {
    console.error('Error fetching supply accounts:', err)
    supplyAccounts.value = []
  }
}

// Open approve modal
const openApproveModal = (request) => {
  if (request.status !== 'pending' && request.status !== 'supply_approved' && request.status !== 'admin_assigned') {
    showSimpleBanner(`Cannot approve request. Current status: ${request.status}.`, 'error', true, 5000)
    return
  }
  requestToApprove.value = request
  showApproveModal.value = true
}

const isMultiItemApprove = () => {
  const r = requestToApprove.value
  return r && ((r.items_count > 1) || (r.items && r.items.length > 1))
}

const approveModalNonRejectedItems = () => {
  const r = requestToApprove.value
  if (!r?.items?.length) return []
  return r.items.filter((i) => (i.status || 'pending') !== 'rejected')
}

const allItemsRejectedInApproveModal = () => {
  return isMultiItemApprove() && approveModalNonRejectedItems().length === 0
}

// Calculate total quantity excluding rejected items
const getTotalQuantity = (request) => {
  if (!request) return 0
  if (request.items && request.items.length > 0) {
    return request.items
      .filter((item) => (item.status || 'pending') !== 'rejected')
      .reduce((sum, item) => sum + (parseInt(item.quantity) || 0), 0)
  }
  // For single-item requests, check if rejected
  if (request.items && request.items.length === 1) {
    const item = request.items[0]
    if ((item.status || 'pending') === 'rejected') {
      return 0
    }
  }
  return parseInt(request.quantity) || 0
}

// Check if an item is rejected
const isItemRejected = (item) => {
  return item && (item.status || 'pending') === 'rejected'
}

const openRejectItemModal = (item) => {
  if (!requestToApprove.value) return
  rejectItemTarget.value = { requestId: requestToApprove.value.id, item }
  rejectItemReason.value = ''
  showRejectItemModal.value = true
}

const closeRejectItemModal = () => {
  showRejectItemModal.value = false
  rejectItemTarget.value = null
  rejectItemReason.value = ''
}

const rejectItem = async () => {
  const t = rejectItemTarget.value
  if (!t?.requestId || !t?.item?.id || !rejectItemReason.value.trim()) {
    showSimpleBanner('Please provide a reason (e.g. Defective)', 'error', true, 4000)
    return
  }
  rejectingItem.value = true
  try {
    const res = await axiosClient.post(`/supply-requests/${t.requestId}/items/${t.item.id}/reject`, {
      rejection_reason: rejectItemReason.value.trim()
    })
    if (res.data.success) {
      const idx = requestToApprove.value?.items?.findIndex((i) => i.id === t.item.id)
      if (idx !== undefined && idx >= 0 && requestToApprove.value.items) {
        requestToApprove.value.items[idx].status = 'rejected'
        requestToApprove.value.items[idx].rejection_reason = rejectItemReason.value.trim()
      }
      showSimpleBanner(res.data.message || 'Item rejected. Remaining items can still be processed.', 'success', true, 4000)
      closeRejectItemModal()
    } else {
      showSimpleBanner(res.data.message || 'Failed to reject item', 'error', true, 5000)
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Failed to reject item'
    showSimpleBanner(msg, 'error', true, 5000)
  } finally {
    rejectingItem.value = false
  }
}

const unrejectItem = async (item) => {
  if (!requestToApprove.value || !item?.id) return
  try {
    const res = await axiosClient.post(`/supply-requests/${requestToApprove.value.id}/items/${item.id}/unreject`)
    if (res.data.success) {
      const idx = requestToApprove.value.items?.findIndex((i) => i.id === item.id)
      if (idx !== undefined && idx >= 0 && requestToApprove.value.items) {
        requestToApprove.value.items[idx].status = 'pending'
        requestToApprove.value.items[idx].rejection_reason = null
      }
      showSimpleBanner(res.data.message || 'Item restored.', 'success', true, 3000)
    } else {
      showSimpleBanner(res.data.message || 'Failed to restore item', 'error', true, 5000)
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Failed to restore item'
    showSimpleBanner(msg, 'error', true, 5000)
  }
}

// Close approve modal
const closeApproveModal = () => {
  if (!approvingRequest.value) {
    showApproveModal.value = false
    requestToApprove.value = null
  }
}

// Approve request
const approveRequest = async () => {
  if (!requestToApprove.value) return
  
  approvingRequest.value = true
  
  try {
  loading.value = true
    const response = await axiosClient.post(`/supply-requests/${requestToApprove.value.id}/approve`)
    
    if (response.data.success) {
      const requestDetails = requestToApprove.value
      const requestId = requestToApprove.value.id
      
      // Close approve modal
      showApproveModal.value = false
      const savedRequest = { ...requestToApprove.value }
      requestToApprove.value = null
      
      // Clear status filter if it's set to 'pending' so approved requests will show up
      if (statusFilter.value === 'pending') {
        statusFilter.value = ''
      }
      // Fetch updated requests
      await fetchRequests()
      await fetchStockOverview()
      
      // Automatically open assign modal after approval
      setTimeout(() => {
        // Find the updated request from the fetched requests
        const updatedRequest = requests.value.find(r => r.id === requestId)
        if (updatedRequest && updatedRequest.status === 'supply_approved') {
          openAssignModal(updatedRequest)
        } else if (savedRequest) {
          // Fallback: use saved request data if updated request not found
          openAssignModal({ ...savedRequest, status: 'supply_approved' })
        }
      }, 300)
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
  rejectForm.value = {
    reason: ''
  }
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

// Open forward modal
const openForwardModal = (request) => {
  selectedRequest.value = request
  forwardForm.value = {
    supply_account_id: '',
    comments: ''
  }
  fetchSupplyAccounts()
  showForwardModal.value = true
}

// Close forward modal
const closeForwardModal = () => {
  showForwardModal.value = false
  selectedRequest.value = null
}

// Forward to another supply account
const forwardRequest = async () => {
  if (!forwardForm.value.supply_account_id || !forwardForm.value.comments.trim()) {
    showSimpleBanner('Please select a supply account and provide comments', 'error', true, 4000)
    return
  }
  
  try {
  loading.value = true
    const response = await axiosClient.post(`/supply-requests/${selectedRequest.value.id}/forward`, {
      supply_account_id: forwardForm.value.supply_account_id,
      comments: forwardForm.value.comments
    })
    
    if (response.data.success) {
      showSimpleBanner('Request forwarded to another supply account successfully!', 'success', true, 5000)
      closeForwardModal()
      fetchRequests()
    } else {
      showSimpleBanner(response.data.message || 'Failed to forward request', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error forwarding request:', err)
    showSimpleBanner(err.response?.data?.message || 'Failed to forward request', 'error', true, 5000)
  } finally {
    loading.value = false
  }
}

// Open assign modal
const openAssignModal = (request) => {
  selectedRequest.value = request
  assignForm.value = {
    admin_id: ''
  }
  fetchAdmins()
  showAssignModal.value = true
}

// Close assign modal
const closeAssignModal = () => {
  showAssignModal.value = false
  selectedRequest.value = null
}

// Assign to admin
const assignToAdmin = async () => {
  if (!assignForm.value.admin_id) {
    showSimpleBanner('Please select an admin', 'error', true, 4000)
    return
  }
  
  try {
    loading.value = true
    const response = await axiosClient.post(`/supply-requests/${selectedRequest.value.id}/assign-admin`, {
      admin_id: assignForm.value.admin_id
    })
    
    if (response.data.success) {
      closeAssignModal()
      fetchRequests()
    } else {
      showSimpleBanner(response.data.message || 'Failed to assign request', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error assigning request:', err)
    showSimpleBanner(err.response?.data?.message || 'Failed to assign request', 'error', true, 5000)
  } finally {
    loading.value = false
  }
}

// Accept by admin
const acceptByAdmin = async (requestId) => {
  if (!confirm('Are you sure you want to accept this request?')) return
  
  try {
    loading.value = true
    const response = await axiosClient.post(`/supply-requests/${requestId}/accept-admin`)
    
    if (response.data.success) {
      // Clear status filter if it's set to 'pending' so accepted requests will show up
      if (statusFilter.value === 'pending') {
        statusFilter.value = ''
      }
      showSimpleBanner('Request accepted successfully!', 'success', true, 5000)
      fetchRequests()
      fetchStockOverview()
    } else {
      showSimpleBanner(response.data.message || 'Failed to accept request', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error accepting request:', err)
    showSimpleBanner(err.response?.data?.message || 'Failed to accept request', 'error', true, 5000)
  } finally {
    loading.value = false
  }
}

// Open pickup schedule modal
const openPickupScheduleModal = (request) => {
  if (request.status !== 'ready_for_pickup') {
    showSimpleBanner(`Cannot schedule pickup. Current status: ${request.status}. Only ready-for-pickup requests can be scheduled.`, 'error', true, 5000)
    return
  }
  requestToSchedulePickup.value = request
  pickupScheduleForm.value = {
    pickup_scheduled_at: '',
    notify_user: true,
    quick_interval: null
  }
  manualMinutes.value = null
  showPickupScheduleModal.value = true
}

// Close pickup schedule modal
const closePickupScheduleModal = () => {
  if (!schedulingPickup.value) {
    showPickupScheduleModal.value = false
    requestToSchedulePickup.value = null
    pickupScheduleForm.value = {
      pickup_scheduled_at: '',
      notify_user: true,
      quick_interval: null
    }
    manualMinutes.value = null
  }
}

// Set time from manual minutes input
const setTimeFromMinutes = () => {
  if (!manualMinutes.value || manualMinutes.value < 1) {
    return
  }
  
  const now = new Date()
  const futureTime = new Date(now.getTime() + manualMinutes.value * 60000)
  
  // Format for datetime-local input (YYYY-MM-DDTHH:mm)
  const year = futureTime.getFullYear()
  const month = String(futureTime.getMonth() + 1).padStart(2, '0')
  const day = String(futureTime.getDate()).padStart(2, '0')
  const hours = String(futureTime.getHours()).padStart(2, '0')
  const mins = String(futureTime.getMinutes()).padStart(2, '0')
  
  pickupScheduleForm.value.pickup_scheduled_at = `${year}-${month}-${day}T${hours}:${mins}`
  pickupScheduleForm.value.quick_interval = null
}

// Set quick time interval
const setQuickInterval = (minutes) => {
  const now = new Date()
  const futureTime = new Date(now.getTime() + minutes * 60000)
  
  // Format for datetime-local input (YYYY-MM-DDTHH:mm)
  const year = futureTime.getFullYear()
  const month = String(futureTime.getMonth() + 1).padStart(2, '0')
  const day = String(futureTime.getDate()).padStart(2, '0')
  const hours = String(futureTime.getHours()).padStart(2, '0')
  const mins = String(futureTime.getMinutes()).padStart(2, '0')
  
  pickupScheduleForm.value.pickup_scheduled_at = `${year}-${month}-${day}T${hours}:${mins}`
  pickupScheduleForm.value.quick_interval = minutes
}

// Adjust time by minutes (positive to add, negative to subtract)
const adjustTime = (minutes) => {
  let currentTime
  
  // If there's already a time set, use it; otherwise start from now
  if (pickupScheduleForm.value.pickup_scheduled_at) {
    currentTime = new Date(pickupScheduleForm.value.pickup_scheduled_at)
  } else {
    currentTime = new Date()
    // Round to nearest 15 minutes
    const roundedMins = Math.round(currentTime.getMinutes() / 15) * 15
    currentTime.setMinutes(roundedMins, 0, 0)
  }
  
  // Add/subtract minutes
  const newTime = new Date(currentTime.getTime() + minutes * 60000)
  
  // Ensure it's in the future
  const now = new Date()
  if (newTime <= now) {
    // If adjustment would make it past, set to now + 15 minutes minimum
    newTime.setTime(now.getTime() + 15 * 60000)
  }
  
  // Format for datetime-local input (YYYY-MM-DDTHH:mm)
  const year = newTime.getFullYear()
  const month = String(newTime.getMonth() + 1).padStart(2, '0')
  const day = String(newTime.getDate()).padStart(2, '0')
  const hours = String(newTime.getHours()).padStart(2, '0')
  const mins = String(newTime.getMinutes()).padStart(2, '0')
  
  pickupScheduleForm.value.pickup_scheduled_at = `${year}-${month}-${day}T${hours}:${mins}`
  pickupScheduleForm.value.quick_interval = null // Clear quick interval when manually adjusting
}

// Format pickup time for display
const formatPickupTime = (datetimeString) => {
  if (!datetimeString) return ''
  try {
    const date = new Date(datetimeString)
    const now = new Date()
    const diffMs = date.getTime() - now.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    
    if (diffMins < 60) {
      return `in ${diffMins} minute${diffMins !== 1 ? 's' : ''} (${date.toLocaleString()})`
    } else if (diffMins < 1440) {
      const hours = Math.floor(diffMins / 60)
      const mins = diffMins % 60
      return `in ${hours} hour${hours !== 1 ? 's' : ''}${mins > 0 ? ` and ${mins} minute${mins !== 1 ? 's' : ''}` : ''} (${date.toLocaleString()})`
    } else {
      return date.toLocaleString()
    }
  } catch (e) {
    return datetimeString
  }
}

// Notify user that request is ready for pickup
const notifyUserReadyForPickup = async (request) => {
  if (request.status !== 'ready_for_pickup') {
    showSimpleBanner(`Cannot notify user. Current status: ${request.status}. Only ready-for-pickup requests can notify users.`, 'error', true, 5000)
    return
  }
  
  notifyingUser.value = true
  
  try {
    const response = await axiosClient.post(`/supply-requests/${request.id}/notify-ready-pickup`)
    
    if (response.data.success) {
      fetchRequests()
    } else {
      showSimpleBanner(response.data.message || 'Failed to notify user', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error notifying user:', err)
    const errorMessage = err.response?.data?.message || err.message || 'Failed to notify user'
    showSimpleBanner(errorMessage, 'error', true, 5000)
  } finally {
    notifyingUser.value = false
  }
}

// Schedule pickup
const schedulePickup = async () => {
  if (!requestToSchedulePickup.value) return
  
  if (!pickupScheduleForm.value.pickup_scheduled_at) {
    showSimpleBanner('Please select a pickup time', 'error', true, 4000)
    return
  }
  
  // Validate that the selected time is in the future
  const selectedTime = new Date(pickupScheduleForm.value.pickup_scheduled_at)
  const now = new Date()
  if (selectedTime <= now) {
    showSimpleBanner('Pickup time must be in the future', 'error', true, 4000)
    return
  }
  
  schedulingPickup.value = true
  
  try {
    const response = await axiosClient.post(`/supply-requests/${requestToSchedulePickup.value.id}/schedule-pickup`, {
      pickup_scheduled_at: pickupScheduleForm.value.pickup_scheduled_at,
      notify_user: pickupScheduleForm.value.notify_user
    })
    
    if (response.data.success) {
      showSimpleBanner(
        pickupScheduleForm.value.notify_user 
          ? 'Pickup time scheduled and user notified successfully!'
          : 'Pickup time scheduled successfully!',
        'success',
        true,
        6000
      )
      closePickupScheduleModal()
      fetchRequests()
    } else {
      showSimpleBanner(response.data.message || 'Failed to schedule pickup', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error scheduling pickup:', err)
    const errorMessage = err.response?.data?.message || err.message || 'Failed to schedule pickup'
    showSimpleBanner(errorMessage, 'error', true, 5000)
  } finally {
    schedulingPickup.value = false
  }
}

// Open fulfill modal
const openFulfillModal = (request) => {
  // Check if request can be fulfilled
  // Allow fulfillment for approved, ready_for_pickup, and admin_accepted statuses
  // ready_for_pickup means the user has been notified and can pick up, so fulfillment happens after pickup
  if (!['admin_accepted', 'approved', 'ready_for_pickup'].includes(request.status)) {
    showSimpleBanner(`Cannot fulfill request. Current status: ${request.status}. Only approved or ready-for-pickup requests can be fulfilled.`, 'error', true, 5000)
    return
  }
  requestToFulfill.value = request
  showFulfillModal.value = true
}

// Close fulfill modal
const closeFulfillModal = () => {
  if (!fulfillingRequest.value) {
    showFulfillModal.value = false
    requestToFulfill.value = null
  }
}

// Fulfill request
const fulfillRequest = async () => {
  if (!requestToFulfill.value) return
  
  fulfillingRequest.value = true
  
  try {
    loading.value = true
    const response = await axiosClient.post(`/supply-requests/${requestToFulfill.value.id}/fulfill`)
    
    if (response.data.success) {
      showFulfillModal.value = false
      requestToFulfill.value = null
      fetchRequests()
      
      // Refresh messages if messages view is open
      if (showAllMessagesView.value) {
        fetchAllMessages()
      }
    } else {
      showSimpleBanner(response.data.message || 'Failed to fulfill request', 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error fulfilling request:', err)
    const errorMessage = err.response?.data?.message || err.message || 'Failed to fulfill request'
    showSimpleBanner(errorMessage, 'error', true, 5000)
  } finally {
    loading.value = false
    fulfillingRequest.value = false
  }
}

// Check if request needs action
const needsAction = (request) => {
  if (!request) return false
  
  // For Supply Account users
  if (!isAdmin()) {
    // Pending requests need Approve/Reject
    if (request.status === 'pending') return true
    // Supply approved requests need Assign to Admin
    if (request.status === 'supply_approved') return true
    // Ready for pickup requests need Notify User, Schedule Pickup, or Fulfill
    if (request.status === 'ready_for_pickup') return true
  }
  
  // For Admin users
  if (isAdmin()) {
    // Supply approved requests need Assign to Admin
    if (request.status === 'supply_approved') return true
    // Admin assigned requests need Accept
    if (request.status === 'admin_assigned') return true
    // Ready for pickup requests need Fulfill
    if (request.status === 'ready_for_pickup') return true
  }
  
  return false
}

// Action reminder banner state
const showActionReminderBanner = ref(false)
const currentReminderRequest = ref(null)
const reminderBannerIndex = ref(0)
let reminderBannerInterval = null

// Get requests that need action
const requestsNeedingAction = computed(() => {
  return requests.value.filter(request => needsAction(request))
})

// Separate active/pending requests from completed ones
const activeRequests = computed(() => {
  const completedStatuses = ['fulfilled', 'rejected', 'cancelled']
  return requests.value.filter(request => {
    const status = request.status?.toLowerCase()
    return !completedStatuses.includes(status)
  })
})

const completedRequests = computed(() => {
  const completedStatuses = ['fulfilled', 'rejected', 'cancelled']
  return requests.value.filter(request => {
    const status = request.status?.toLowerCase()
    return completedStatuses.includes(status)
  })
})

// Get the requests to display based on active tab
const displayedRequests = computed(() => {
  return activeTab.value === 'active' ? activeRequests.value : completedRequests.value
})

// Start action reminder banner
const startActionReminderBanner = () => {
  // Clear any existing interval
  if (reminderBannerInterval) {
    clearInterval(reminderBannerInterval)
  }
  
  // Only start if there are requests needing action
  if (requestsNeedingAction.value.length === 0) {
    showActionReminderBanner.value = false
    return
  }
  
  // Show banner immediately
  updateReminderBanner()
  
  // Update every 5 seconds
  reminderBannerInterval = setInterval(() => {
    updateReminderBanner()
  }, 5000)
}

// Update reminder banner to show next request
const updateReminderBanner = () => {
  if (requestsNeedingAction.value.length === 0) {
    showActionReminderBanner.value = false
    return
  }
  
  // Cycle through requests
  currentReminderRequest.value = requestsNeedingAction.value[reminderBannerIndex.value % requestsNeedingAction.value.length]
  reminderBannerIndex.value = (reminderBannerIndex.value + 1) % requestsNeedingAction.value.length
  showActionReminderBanner.value = true
  
  // Auto-hide after 4 seconds (leaving 1 second gap before next shows)
  setTimeout(() => {
    showActionReminderBanner.value = false
  }, 4000)
}

// Close reminder banner
const closeActionReminderBanner = () => {
  showActionReminderBanner.value = false
}

// Navigate to request from reminder banner
const goToRequestFromReminder = () => {
  if (currentReminderRequest.value) {
    openViewDetailsModal(currentReminderRequest.value)
    closeActionReminderBanner()
  }
}

// Get action required tooltip text
const getActionRequiredTooltip = (request) => {
  if (!request) return 'Action required'
  
  // For Supply Account users
  if (!isAdmin()) {
    if (request.status === 'pending') return 'Action required - Approve or Reject'
    if (request.status === 'supply_approved') return 'Action required - Assign to Admin'
    if (request.status === 'ready_for_pickup') return 'Action required - Notify User, Schedule Pickup, or Fulfill'
  }
  
  // For Admin users
  if (isAdmin()) {
    if (request.status === 'supply_approved') return 'Action required - Assign to Admin'
    if (request.status === 'admin_assigned') return 'Action required - Accept Request'
    if (request.status === 'ready_for_pickup') return 'Action required - Fulfill Request'
  }
  
  return 'Action required'
}

// Get status badge class
const getStatusBadgeClass = (status) => {
  const statusLower = status?.toLowerCase()
  if (statusLower === 'approved' || statusLower === 'admin_accepted') return 'bg-gradient-to-r from-green-500 to-green-600 text-white border-2 border-green-700 dark:border-green-500'
  if (statusLower === 'supply_approved') return 'bg-gradient-to-r from-blue-500 to-blue-600 text-white border-2 border-blue-700 dark:border-blue-500'
  if (statusLower === 'admin_assigned') return 'bg-gradient-to-r from-purple-500 to-purple-600 text-white border-2 border-purple-700 dark:border-purple-500'
  if (statusLower === 'ready_for_pickup') return 'bg-gradient-to-r from-cyan-500 to-cyan-600 text-white border-2 border-cyan-700 dark:border-cyan-500'
  if (statusLower === 'rejected') return 'bg-gradient-to-r from-red-500 to-red-600 text-white border-2 border-red-700 dark:border-red-500'
  if (statusLower === 'fulfilled') return 'bg-gradient-to-r from-indigo-500 to-indigo-600 text-white border-2 border-indigo-700 dark:border-indigo-500'
  return 'bg-gradient-to-r from-yellow-500 to-yellow-600 text-white border-2 border-yellow-700 dark:border-yellow-500'
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
    fetchStockOverview()
  }

// Fetch all messages
const fetchAllMessages = async () => {
  loadingAllMessages.value = true
  try {
    const response = await axiosClient.get('/supply-requests/messages/all')
    if (response.data.success) {
      allMessages.value = response.data.data || []
    }
  } catch (err) {
    console.error('Error fetching all messages:', err)
    allMessages.value = []
  } finally {
    loadingAllMessages.value = false
  }
}

// Grouped messages by sender
const groupedMessages = computed(() => {
  // Filter out messages for fulfilled, rejected, or cancelled requests
  // Only show messages for pending or in-progress requests
  const activeMessages = allMessages.value.filter(msg => {
    const requestStatus = msg.supply_request?.status?.toLowerCase()
    // Hide messages for completed or rejected requests
    const excludedStatuses = ['fulfilled', 'rejected', 'cancelled']
    return !excludedStatuses.includes(requestStatus)
  })
  
  // Group messages by sender (user.id)
  const groupedBySender = {}
  activeMessages.forEach(msg => {
    const senderId = msg.user?.id
    if (!senderId) return
    
    if (!groupedBySender[senderId]) {
      groupedBySender[senderId] = {
        sender: msg.user,
        messages: [],
        hasUnread: false,
        latestMessage: null,
        latestTimestamp: null
      }
    }
    
    groupedBySender[senderId].messages.push(msg)
    
    // Track unread status
    if (!msg.is_read) {
      groupedBySender[senderId].hasUnread = true
    }
    
    // Track latest message
    const msgTime = new Date(msg.created_at).getTime()
    if (!groupedBySender[senderId].latestTimestamp || msgTime > groupedBySender[senderId].latestTimestamp) {
      groupedBySender[senderId].latestTimestamp = msgTime
      groupedBySender[senderId].latestMessage = msg
    }
  })
  
  // Convert grouped object to array and sort by latest timestamp (most recent first)
  return Object.values(groupedBySender)
    .map(group => ({
      ...group.latestMessage, // Use latest message as the preview
      groupedMessages: group.messages, // Store all messages for this sender
      hasUnread: group.hasUnread,
      sender: group.sender
    }))
    .sort((a, b) => {
      const timeA = new Date(a.created_at).getTime()
      const timeB = new Date(b.created_at).getTime()
      return timeB - timeA // Most recent first
    })
})

// Format relative time helper
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

// Handle message click - navigate to specific request (grouped by sender)
const handleAllMessageClick = async (message) => {
  if (!message.sender || !message.groupedMessages) return
  
  // Set selected sender
  selectedSender.value = message.sender
  
  // Get all messages from this sender (grouped messages)
  const senderMessages = message.groupedMessages || []
  
  // Find the most recent supply request
  const mostRecentMessage = senderMessages.sort((a, b) => {
    return new Date(b.created_at) - new Date(a.created_at)
  })[0]
  
  if (!mostRecentMessage?.supply_request?.id) return
  
  // Find or fetch the request
  let request = requests.value.find(r => r.id === mostRecentMessage.supply_request.id)
  
  if (!request) {
    // Fetch requests in background, but open modal immediately with available data
    fetchRequests()
    // Try to construct a minimal request object from the message data
    request = {
      id: mostRecentMessage.supply_request.id,
      item_name: mostRecentMessage.supply_request.item_name,
      quantity: mostRecentMessage.supply_request.quantity,
      status: mostRecentMessage.supply_request.status
    }
  }
  
  // Open modal immediately
  selectedRequestForMessage.value = request
  showMessageModal.value = true
  
  // Fetch messages and mark as read in parallel (non-blocking)
  Promise.all([
    fetchMessages(request.id),
    markMessagesAsRead(request.id)
  ]).catch(err => {
    console.error('Error loading messages:', err)
  })
  
  // Mark all other messages from this sender as read in background (non-blocking)
  const supplyRequestIds = [...new Set(senderMessages.map(msg => msg.supply_request?.id).filter(Boolean))]
  Promise.all(
    supplyRequestIds
      .filter(id => id !== request.id) // Don't mark the current request again
      .map(requestId => 
        axiosClient.post(`/supply-requests/${requestId}/messages/mark-read`)
          .then(() => {
            // Update local state
            allMessages.value.forEach(msg => {
              if (msg.supply_request?.id === requestId && msg.user?.id === message.sender.id) {
                msg.is_read = true
              }
            })
          })
          .catch(err => console.error(`Error marking messages as read for request ${requestId}:`, err))
      )
  )
}

// Close all messages view
const closeAllMessagesView = () => {
  showAllMessagesView.value = false
  router.replace({ query: {} })
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
      
      // Refresh requests list silently (without loading indicator)
      fetchRequests(true)
      fetchStockOverview()
    })
    
    // Listen for request status updates (approved, rejected, fulfilled, etc.)
    requestsChannel.listen('.SupplyRequestUpdated', (data) => {
      console.log('🔄 Supply request updated via WebSocket:', data)
      
      // Refresh requests list silently
      fetchRequests(true)
      fetchStockOverview()
    })
    
    // Listen for request approval
    requestsChannel.listen('.SupplyRequestApproved', (data) => {
      console.log('✅ Supply request approved via WebSocket:', data)
      
      // Refresh requests list silently
      fetchRequests(true)
      fetchStockOverview()
    })
    
    // Listen for request rejection
    requestsChannel.listen('.SupplyRequestRejected', (data) => {
      console.log('❌ Supply request rejected via WebSocket:', data)
      
      // Refresh requests list silently
      fetchRequests(true)
      fetchStockOverview()
    })
    
    // Listen for request fulfillment
    requestsChannel.listen('.SupplyRequestFulfilled', (data) => {
      console.log('📦 Supply request fulfilled via WebSocket:', data)
      
      // Refresh requests list silently
      fetchRequests(true)
      fetchStockOverview()
      
      // Refresh messages if messages view is open
      if (showAllMessagesView.value) {
        fetchAllMessages()
      }
    })
    
    requestRealtimeListener.value = requestsChannel
    console.log('✅ Real-time supply request listener active')
  } catch (error) {
    console.error('❌ Error setting up request real-time listener:', error)
    // Retry after delay
    setTimeout(setupRequestRealtimeListener, 3000)
  }
}

onMounted(async () => {
  try {
  await fetchCurrentUser()
    // Initialize stock summary with default values to ensure KPI cards display
    if (!stockSummary.value || stockSummary.value.total_items === undefined) {
      stockSummary.value = {
        total_items: 0,
        total_quantity: 0,
        low_stock_count: 0
      }
    }
    
    // Start action reminder banner after initial load
    setTimeout(() => {
      startActionReminderBanner()
    }, 2000)
  await fetchRequests()
  await fetchStockOverview()
  } catch (err) {
    console.error('Error during page initialization:', err)
    // Ensure loading is false so page can display
    loading.value = false
    // Set default values for stock summary
    stockSummary.value = {
      total_items: 0,
      total_quantity: 0,
      low_stock_count: 0
    }
  }
  
  // Check if we should show all messages view
  if (route.query.view === 'messages') {
    showAllMessagesView.value = true
    fetchAllMessages()
  }
  
  // Setup real-time listener for supply requests
  setTimeout(() => {
    setupRequestRealtimeListener()
  }, 1000) // Wait for Echo to initialize
  
  // Setup polling as fallback for requests (every 5 seconds)
  // This ensures requests update even if WebSocket fails
  requestPollingInterval.value = setInterval(async () => {
    // Only poll if page is visible
    if (document.visibilityState === 'visible') {
      await fetchRequests(true) // Silent refresh
      await fetchStockOverview()
    }
  }, 5000) // Poll every 5 seconds
})

// Store resize handler reference for cleanup
let resizeHandler = null

// Force re-render on window resize to handle viewport changes (e.g., dev tools open/close)
// This is added in a separate onMounted to ensure it runs after the main one
onMounted(() => {
  resizeHandler = () => {
    // Force Vue to re-evaluate responsive classes by triggering a reactive update
    // This is a workaround for viewport-dependent CSS classes not updating when dev tools open/close
    if (document.visibilityState === 'visible' && requests.value.length > 0) {
      // Small delay to ensure viewport has settled
      setTimeout(() => {
        // Trigger a reactive update by touching a reactive property
        requests.value = [...requests.value]
      }, 100)
    }
  }
  
  window.addEventListener('resize', resizeHandler)
})

// Cleanup on unmount
onBeforeUnmount(() => {
  // Clean up request polling interval
  if (requestPollingInterval.value) {
    clearInterval(requestPollingInterval.value)
    requestPollingInterval.value = null
  }
  
  // Clean up reminder banner interval
  if (reminderBannerInterval) {
    clearInterval(reminderBannerInterval)
    reminderBannerInterval = null
  }
  
  // Clean up resize listener
  if (resizeHandler) {
    window.removeEventListener('resize', resizeHandler)
    resizeHandler = null
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

// Watch for route changes
watch(() => route.query.view, (newView) => {
  if (newView === 'messages') {
    showAllMessagesView.value = true
    fetchAllMessages()
  } else {
    showAllMessagesView.value = false
  }
})

watch(debouncedSearchQuery, () => {
  currentPage.value = 1
  fetchRequests()
})

watch([statusFilter, startDate, endDate], () => {
    currentPage.value = 1
  fetchRequests()
})

watch(activeTab, () => {
  currentPage.value = 1
  // Clear status filter when switching tabs to ensure all relevant requests are shown
  // Active tab should show all active requests (pending, approved, admin_assigned, etc.)
  // Completed tab should show all completed requests (fulfilled, rejected, cancelled)
  if (activeTab.value === 'active') {
    // Clear status filter so we can see all active statuses (pending, approved, admin_assigned, etc.)
    statusFilter.value = ''
  }
  // Refetch requests after clearing filter
  fetchRequests()
})

watch(currentPage, () => {
  fetchRequests()
})

watch(itemsPerPage, () => {
  currentPage.value = 1
  fetchRequests()
})

// Watch for requests needing action and restart reminder banner
watch(requestsNeedingAction, () => {
  startActionReminderBanner()
}, { deep: true })
</script>

<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-8" :class="{ 'pt-20 sm:pt-24': showBanner }">
    <!-- Action Reminder Banner -->
    <Transition name="slide-down">
      <div
        v-if="showActionReminderBanner && currentReminderRequest"
        class="fixed bottom-4 right-4 z-50 max-w-sm w-full sm:w-auto"
      >
        <div class="bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 text-white rounded-lg shadow-2xl border-2 border-orange-600 animate-pulse">
          <div class="p-3 flex items-center gap-3">
            <div class="flex-shrink-0 p-2 bg-white/20 rounded-lg backdrop-blur-sm">
              <span class="material-icons-outlined text-xl">notification_important</span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-bold mb-0.5">Action Required!</div>
              <div class="text-[10px] opacity-90 truncate">
                {{ currentReminderRequest.user?.name || 'N/A' }} - {{ getActionRequiredTooltip(currentReminderRequest) }}
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button
                @click="goToRequestFromReminder"
                class="p-1.5 bg-white/20 hover:bg-white/30 rounded transition-all"
                title="View Request"
              >
                <span class="material-icons-outlined text-sm">arrow_forward</span>
              </button>
              <button
                @click="closeActionReminderBanner"
                class="p-1.5 bg-white/20 hover:bg-white/30 rounded transition-all"
                title="Dismiss"
              >
                <span class="material-icons-outlined text-sm">close</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
    
    <!-- Enhanced Professional Banner Notification -->
    <Transition name="banner-slide">
      <div
        v-if="showBanner"
        :class="[
          'fixed top-0 left-0 right-0 z-[60] shadow-2xl',
          bannerType === 'success' 
            ? 'bg-gradient-to-r from-green-500 via-green-600 to-green-700 text-white' 
            : 'bg-gradient-to-r from-red-500 via-red-600 to-red-700 text-white'
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

    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 dark:from-gray-800 dark:via-gray-700 dark:to-gray-800 shadow-2xl border-b-4 border-blue-900 dark:border-gray-600 mt-0">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-7 flex flex-col gap-3 sm:gap-4">
        <div class="flex items-start gap-3 sm:gap-4">
          <div class="flex items-center gap-2 sm:gap-3 pt-1 flex-shrink-0">
            <button 
              @click="router.push('/dashboard')" 
              class="p-2 sm:p-3 bg-white/20 dark:bg-gray-700/80 backdrop-blur-sm border-2 border-white/30 dark:border-gray-600 text-white rounded-lg sm:rounded-xl hover:bg-white/30 dark:hover:bg-gray-600/80 hover:scale-105 transition-all duration-200 shadow-lg"
              title="Return to Dashboard"
            >
              <span class="material-icons-outlined text-lg sm:text-xl">arrow_back</span>
            </button>
            <button 
              @click="refreshData" 
              class="p-2 sm:p-3 bg-white/20 dark:bg-gray-700/80 backdrop-blur-sm border-2 border-white/30 dark:border-gray-600 text-white rounded-lg sm:rounded-xl hover:bg-white/30 dark:hover:bg-gray-600/80 hover:scale-105 transition-all duration-200 shadow-lg"
              title="Refresh Data"
              :disabled="loading"
            >
              <span class="material-icons-outlined text-lg sm:text-xl" :class="{ 'animate-spin': loading }">refresh</span>
            </button>
          </div>
          <div class="flex items-start gap-2 sm:gap-4 flex-1 min-w-0">
            <div class="p-2 sm:p-3 bg-white/20 dark:bg-gray-700/80 backdrop-blur-sm rounded-lg sm:rounded-xl shadow-lg flex-shrink-0">
              <span class="material-icons-outlined text-2xl sm:text-3xl text-white">inventory_2</span>
          </div>
          <div class="text-white flex-1 min-w-0">
              <h1 class="text-lg sm:text-2xl lg:text-3xl xl:text-4xl font-extrabold leading-tight tracking-tight break-words mb-1">SUPPLY REQUESTS MANAGEMENT SYSTEM</h1>
              <p class="text-white/95 dark:text-gray-300 text-xs sm:text-sm lg:text-base mt-1 sm:mt-1.5 font-semibold">Official Request Processing and Approval Portal</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Stock Overview Cards -->
    <div class="p-3 sm:p-4 lg:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
      <div class="bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-900 dark:to-blue-800 rounded-lg sm:rounded-xl shadow-lg border-2 border-blue-400 dark:border-blue-700 p-4 sm:p-5 lg:p-6 text-white transform hover:scale-105 transition-all duration-200">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <div class="p-2 sm:p-3 bg-white/20 dark:bg-gray-700/50 backdrop-blur-sm rounded-lg sm:rounded-xl">
            <span class="material-icons-outlined text-2xl sm:text-3xl">inventory_2</span>
          </div>
          <div class="text-right">
            <p class="text-3xl sm:text-4xl font-extrabold">{{ stockSummary.total_items }}</p>
          </div>
        </div>
          <div>
          <p class="text-xs sm:text-sm font-bold text-blue-100 dark:text-blue-200 uppercase tracking-wider mb-1">Total Inventory Items</p>
          <p class="text-xs text-blue-200 dark:text-blue-300">Active items in system</p>
          </div>
          </div>
      <div class="bg-gradient-to-br from-green-500 to-green-600 dark:from-green-900 dark:to-green-800 rounded-lg sm:rounded-xl shadow-lg border-2 border-green-400 dark:border-green-700 p-4 sm:p-5 lg:p-6 text-white transform hover:scale-105 transition-all duration-200">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <div class="p-2 sm:p-3 bg-white/20 dark:bg-gray-700/50 backdrop-blur-sm rounded-lg sm:rounded-xl">
            <span class="material-icons-outlined text-2xl sm:text-3xl">shopping_cart</span>
        </div>
          <div class="text-right">
            <p class="text-3xl sm:text-4xl font-extrabold">{{ stockSummary.total_quantity }}</p>
      </div>
        </div>
          <div>
          <p class="text-xs sm:text-sm font-bold text-green-100 dark:text-green-200 uppercase tracking-wider mb-1">Total Stock Quantity</p>
          <p class="text-xs text-green-200 dark:text-green-300">Units available</p>
          </div>
          </div>
      <div class="bg-gradient-to-br from-red-500 to-red-600 dark:from-red-900 dark:to-red-800 rounded-lg sm:rounded-xl shadow-lg border-2 border-red-400 dark:border-red-700 p-4 sm:p-5 lg:p-6 text-white transform hover:scale-105 transition-all duration-200 sm:col-span-2 lg:col-span-1" :class="{ 'animate-pulse': stockSummary.low_stock_count > 0 }">
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <div class="p-2 sm:p-3 bg-white/20 dark:bg-gray-700/50 backdrop-blur-sm rounded-lg sm:rounded-xl">
            <span class="material-icons-outlined text-2xl sm:text-3xl">warning</span>
          </div>
          <div class="text-right">
            <p class="text-3xl sm:text-4xl font-extrabold">{{ stockSummary.low_stock_count }}</p>
          </div>
        </div>
        <div>
          <p class="text-xs sm:text-sm font-bold text-red-100 dark:text-red-200 uppercase tracking-wider mb-1">Low Stock Alert</p>
          <p class="text-xs text-red-200 dark:text-red-300">Requires attention</p>
        </div>
      </div>
    </div>

    <!-- All Messages View -->
    <div v-if="showAllMessagesView" class="p-6">
      <div class="bg-white dark:bg-gray-800 shadow-sm border-2 border-gray-300 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-800 to-blue-700 dark:from-gray-700 dark:to-gray-800 px-6 py-4 border-b-2 border-blue-900 dark:border-gray-600 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button
              @click="closeAllMessagesView"
              class="p-2 hover:bg-white/20 dark:hover:bg-gray-600 transition-colors rounded"
              title="Return to Requests"
            >
              <span class="material-icons-outlined text-white text-lg">arrow_back</span>
            </button>
            <h2 class="text-lg font-bold text-white uppercase tracking-wide">Supply Request Communications</h2>
          </div>
          <button
            @click="fetchAllMessages"
            class="p-2 hover:bg-white/20 dark:hover:bg-gray-600 transition-colors rounded"
            title="Refresh Messages"
            :disabled="loadingAllMessages"
          >
            <span class="material-icons-outlined text-white text-lg" :class="{ 'animate-spin': loadingAllMessages }">refresh</span>
          </button>
        </div>

        <!-- Messages List -->
        <div class="p-6">
          <div v-if="loadingAllMessages" class="text-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 dark:border-blue-400 mx-auto mb-4"></div>
            <p class="text-gray-600 dark:text-gray-400">Loading messages...</p>
          </div>

          <div v-else-if="groupedMessages.length === 0" class="text-center py-12">
            <span class="material-icons-outlined text-6xl text-gray-300 dark:text-gray-600 mb-4 block">message</span>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">No Messages Available</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">No supply request communications found in the system.</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="message in groupedMessages"
              :key="`${message.sender?.id || message.user?.id}-${message.id}`"
              @click="handleAllMessageClick(message)"
              class="p-4 border-2 border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-blue-500 dark:hover:border-blue-500 cursor-pointer transition-all relative"
              :class="{ 'bg-blue-50 dark:bg-blue-900/30 border-blue-500 dark:border-blue-500': message.hasUnread }"
            >
              <!-- Unread indicator -->
              <div v-if="message.hasUnread" class="absolute left-0 top-0 bottom-0 w-1 bg-blue-700"></div>

              <div class="flex items-start gap-4 pl-2">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                  <div class="h-12 w-12 bg-blue-700 border-2 border-blue-800 flex items-center justify-center overflow-hidden">
                    <span v-if="!(message.sender?.avatar || message.user?.avatar)" class="text-white font-bold text-base">
                      {{ (message.sender?.name || message.user?.name || 'U').charAt(0).toUpperCase() }}
                    </span>
                    <img v-else :src="message.sender?.avatar || message.user?.avatar" :alt="message.sender?.name || message.user?.name" class="w-full h-full object-cover" />
                  </div>
                </div>

                <!-- Message content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ message.sender?.name || message.user?.name || 'Unknown' }}
                      </p>
                      <span class="text-xs text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 px-2 py-0.5 font-medium uppercase">
                        {{ message.sender?.role || message.user?.role || 'user' }}
                      </span>
                      <span v-if="message.groupedMessages && message.groupedMessages.length > 1" class="text-xs text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 border border-blue-300 dark:border-blue-700 px-2 py-0.5 font-medium">
                        {{ message.groupedMessages.length }} messages
                      </span>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                      {{ formatRelativeTime(message.created_at) }}
                    </span>
                  </div>

                  <p class="text-sm text-gray-700 dark:text-gray-300 mb-2 line-clamp-2">
                    {{ message.message }}
                  </p>

                  <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span>
                      <span class="font-medium">Request:</span> {{ message.supply_request?.item_name || 'N/A' }}
                    </span>
                    <span v-if="message.supply_request?.quantity">
                      <span class="font-medium">Quantity:</span> {{ message.supply_request.quantity }}
                    </span>
                    <span v-if="message.supply_request?.status">
                      <span class="font-medium">Status:</span> 
                      <span class="capitalize">{{ message.supply_request.status }}</span>
                    </span>
                  </div>
                </div>

                <!-- Unread badge -->
                <div v-if="message.hasUnread" class="flex-shrink-0">
                  <div class="h-3 w-3 bg-blue-700 border border-blue-900"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="p-6">
          <!-- Enhanced Filters -->
      <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 rounded-lg sm:rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 p-4 sm:p-5 lg:p-6 mb-4 sm:mb-6">
        <div class="mb-3 sm:mb-4 flex items-center gap-2 sm:gap-3">
          <div class="p-1.5 sm:p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex-shrink-0">
            <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-lg sm:text-xl">filter_list</span>
        </div>
          <h3 class="text-sm sm:text-base font-bold text-gray-900 dark:text-white uppercase tracking-wide">Search & Filter Options</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
          <div class="lg:col-span-2">
            <label class="flex items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase">
              <span class="material-icons-outlined text-sm">search</span>
              <span>Search Requests</span>
            </label>
            <div class="relative">
              <span class="material-icons-outlined absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-lg">search</span>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Enter search term..."
                class="w-full pl-12 pr-4 py-3 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-xl focus:outline-none focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 text-sm font-medium transition-all"
              />
            </div>
          </div>
          <div>
            <label class="flex items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase">
              <span class="material-icons-outlined text-sm">flag</span>
              <span>Request Status</span>
            </label>
            <div class="relative">
            <select
              v-model="statusFilter"
                class="w-full px-4 py-3 pl-10 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 text-sm font-medium appearance-none transition-all"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="fulfilled">Fulfilled</option>
            </select>
              <span class="material-icons-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm pointer-events-none">flag</span>
              <span class="material-icons-outlined absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm pointer-events-none">keyboard_arrow_down</span>
            </div>
          </div>
          <div>
            <label class="flex items-center gap-2 text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase">
              <span class="material-icons-outlined text-sm">calendar_today</span>
              <span>Start Date</span>
            </label>
            <div class="relative">
            <input
              v-model="startDate"
              type="date"
                class="w-full px-4 py-3 pl-10 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 text-sm font-medium transition-all"
            />
              <span class="material-icons-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-sm pointer-events-none">calendar_today</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs to separate Active/Pending from Completed requests -->
      <div v-if="!loading && !error" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 mb-4">
        <div class="flex border-b border-gray-200 dark:border-gray-700">
          <button
            @click="activeTab = 'active'"
            :class="[
              'flex-1 px-4 py-3 text-sm font-bold uppercase tracking-wide transition-all duration-200 flex items-center justify-center gap-2',
              activeTab === 'active'
                ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white border-b-2 border-blue-800 dark:border-blue-500'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
            ]"
          >
            <span class="material-icons-outlined text-base">{{ activeTab === 'active' ? 'pending_actions' : 'schedule' }}</span>
            <span>Active/Pending</span>
            <span v-if="activeRequests.length > 0" class="px-2 py-0.5 bg-white/20 dark:bg-gray-800/20 rounded-full text-xs font-bold">
              {{ activeRequests.length }}
            </span>
          </button>
          <button
            @click="activeTab = 'completed'"
            :class="[
              'flex-1 px-4 py-3 text-sm font-bold uppercase tracking-wide transition-all duration-200 flex items-center justify-center gap-2',
              activeTab === 'completed'
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white border-b-2 border-indigo-800 dark:border-indigo-500'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'
            ]"
          >
            <span class="material-icons-outlined text-base">{{ activeTab === 'completed' ? 'check_circle' : 'done_all' }}</span>
            <span>Completed</span>
            <span v-if="completedRequests.length > 0" class="px-2 py-0.5 bg-white/20 dark:bg-gray-800/20 rounded-full text-xs font-bold">
              {{ completedRequests.length }}
            </span>
          </button>
        </div>
      </div>

          <!-- Enhanced Loading State -->
      <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center">
            <div class="relative mb-6">
              <div class="inline-block p-5 bg-gradient-to-br from-blue-100 to-blue-50 dark:from-blue-900/30 dark:to-blue-800/30 rounded-2xl border-2 border-blue-300 dark:border-blue-700 shadow-lg">
                <span class="material-icons-outlined animate-spin text-5xl text-blue-600 dark:text-blue-400">refresh</span>
            </div>
              <div class="absolute inset-0 border-4 border-blue-200 dark:border-blue-800 rounded-2xl animate-pulse"></div>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white mb-2">Loading Request Data...</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Please wait while we retrieve the information</p>
        </div>
      </div>

          <!-- Enhanced Error State -->
          <div v-else-if="error" class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 border-2 border-red-400 dark:border-red-700 rounded-xl p-6 shadow-lg">
            <div class="flex items-start gap-4">
              <div class="p-3 bg-red-100 dark:bg-red-900/40 rounded-xl flex-shrink-0">
                <span class="material-icons-outlined text-red-700 dark:text-red-400 text-3xl">error</span>
              </div>
              <div class="flex-1">
                <p class="text-base font-bold text-red-900 dark:text-red-300 mb-2 flex items-center gap-2">
                  <span class="material-icons-outlined text-lg">warning</span>
                  System Error
                </p>
                <p class="text-sm text-red-800 dark:text-red-400">{{ error }}</p>
              </div>
            </div>
          </div>

          <!-- Enhanced Empty State -->
      <div v-else-if="displayedRequests.length === 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center">
          <div class="p-6 bg-gradient-to-br from-gray-100 to-gray-50 dark:from-gray-700 dark:to-gray-800 rounded-full mb-6">
            <span class="material-icons-outlined text-6xl text-gray-400 dark:text-gray-500">inbox</span>
          </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">No Requests Found</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400 text-center max-w-md">{{ searchQuery || statusFilter ? 'No records match your current filter criteria. Please adjust your search parameters.' : (activeTab === 'active' ? 'No active or pending requests are currently available.' : 'No completed requests are currently available.') }}</p>
        </div>
      </div>

          <!-- Enhanced Requests - Mobile Card View -->
      <div v-if="!loading && !error && displayedRequests.length > 0" class="block lg:hidden space-y-4">
        <div
          v-for="request in displayedRequests"
          :key="request.id"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 p-4 space-y-4"
        >
          <!-- Card Header -->
          <div class="flex items-start justify-between gap-3 pb-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-2">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex-shrink-0">
                  <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-sm">person</span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ request.user?.name || 'N/A' }}</div>
                  <div class="text-xs text-gray-600 dark:text-gray-400 truncate flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">location_on</span>
                    {{ request.user?.location || 'N/A' }}
                  </div>
                </div>
              </div>
              <div v-if="isAdmin()" class="flex items-center gap-2 mt-2">
                <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">location_on</span>
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ request.user?.location || 'N/A' }}</span>
              </div>
            </div>
            <div class="flex flex-col items-end gap-2">
              <span :class="['inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold uppercase rounded-lg shadow-sm', getStatusBadgeClass(request.status)]">
                <span class="material-icons-outlined text-xs">flag</span>
                <span class="hidden sm:inline">{{ request.status === 'supply_approved' ? 'Supply Approved' : request.status === 'admin_assigned' ? 'Assigned' : request.status === 'admin_accepted' ? 'Accepted' : request.status }}</span>
                <span class="sm:hidden">{{ request.status === 'supply_approved' ? 'Approved' : request.status === 'admin_assigned' ? 'Assigned' : request.status === 'admin_accepted' ? 'Accepted' : request.status.substring(0, 4) }}</span>
              </span>
            </div>
          </div>

          <!-- Item Details -->
          <div class="space-y-3">
            <div class="flex items-center gap-2 mb-2">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-base">inventory_2</span>
              <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase">Item Details</span>
            </div>
            <div v-if="request.items && request.items.length > 1" class="space-y-3">
              <div v-for="(item, idx) in request.items" :key="idx" 
                   :class="['rounded-lg p-3 border', isItemRejected(item) 
                     ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' 
                     : 'bg-gray-50 dark:bg-gray-700/50 border-gray-200 dark:border-gray-600']">
                <div class="flex items-center justify-between mb-1">
                  <div :class="['text-sm font-bold', isItemRejected(item) 
                    ? 'text-red-700 dark:text-red-300 line-through' 
                    : 'text-gray-900 dark:text-white']">
                    {{ item.item_name }}
                  </div>
                  <span v-if="isItemRejected(item)" class="px-2 py-0.5 text-xs font-semibold bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded">
                    Rejected
                  </span>
                </div>
                <div v-if="isItemRejected(item) && item.rejection_reason" class="text-xs text-red-600 dark:text-red-400 mb-2 italic">
                  Reason: {{ item.rejection_reason }}
                </div>
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="material-icons-outlined text-xs text-gray-400 dark:text-gray-500">inventory</span>
                    <span :class="['text-xs', isItemRejected(item) 
                      ? 'text-red-600 dark:text-red-400 line-through' 
                      : 'text-gray-600 dark:text-gray-400']">
                      Qty: <span :class="['font-bold', isItemRejected(item) 
                        ? 'text-red-700 dark:text-red-300' 
                        : 'text-gray-900 dark:text-white']">{{ item.quantity }}</span>
                    </span>
                  </div>
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs text-gray-400 dark:text-gray-500">storage</span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">Stock: <span class="font-bold text-blue-600 dark:text-blue-400">{{ item.item_quantity }}</span></span>
                  </div>
                </div>
              </div>
              <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-2 flex items-center justify-between border border-blue-200 dark:border-blue-700">
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">Total Quantity (Non-Rejected):</span>
                <span class="text-sm font-bold text-blue-700 dark:text-blue-300">{{ getTotalQuantity(request) }}</span>
              </div>
            </div>
            <div v-else class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
              <div class="text-sm font-bold text-gray-900 dark:text-white mb-2">{{ request.item_name }}</div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-xs text-gray-400 dark:text-gray-500">inventory</span>
                  <span class="text-xs text-gray-600 dark:text-gray-400">Qty: <span class="font-bold text-gray-900 dark:text-white">{{ request.quantity }}</span></span>
                </div>
                <div class="flex items-center gap-1">
                  <span class="material-icons-outlined text-xs text-gray-400 dark:text-gray-500">storage</span>
                  <span class="text-xs text-gray-600 dark:text-gray-400">Stock: <span class="font-bold text-blue-600 dark:text-blue-400">{{ request.item_quantity }}</span></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Info -->
          <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <div v-if="isAdmin()" class="flex items-center gap-2">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">business</span>
              <div class="flex-1 min-w-0">
                <div class="text-xs text-gray-500 dark:text-gray-400">Supply Unit</div>
                <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ request.supply_name || 'N/A' }}</div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">calendar_today</span>
              <div class="flex-1 min-w-0">
                <div class="text-xs text-gray-500 dark:text-gray-400">Date Submitted</div>
                <div class="text-xs font-semibold text-gray-900 dark:text-white">{{ formatDate(request.created_at) }}</div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2 flex-wrap">
              <!-- View Details Button -->
              <button
                @click="openViewDetailsModal(request)"
                class="relative p-1.5 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded transition-all duration-200 hover:shadow-sm flex items-center justify-center border-2 border-blue-400 dark:border-blue-500"
                title="View details"
              >
                <span class="material-icons-outlined text-sm">visibility</span>
                <!-- Action Required Indicator -->
                <span 
                  v-if="needsAction(request)"
                  class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-3.5 h-3.5 bg-red-500 text-white text-[8px] rounded-full font-bold animate-pulse shadow-md border border-white dark:border-gray-800"
                  :title="getActionRequiredTooltip(request)"
                >
                  !
                </span>
              </button>
              <button
                @click="openMessageModal(request)"
                class="relative p-1.5 text-blue-700 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 border-2 border-blue-300 dark:border-blue-700 rounded transition-all flex items-center justify-center shadow-sm hover:shadow-md flex-shrink-0"
                title="View Messages"
              >
                <span class="material-icons-outlined text-sm">message</span>
                <span 
                  v-if="(unreadCounts[request.id] || 0) > 0"
                  class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[8px] rounded-full w-3.5 h-3.5 flex items-center justify-center font-bold shadow-lg animate-pulse"
                >
                  {{ (unreadCounts[request.id] || 0) > 9 ? '9+' : (unreadCounts[request.id] || 0) }}
                </span>
              </button>
              <!-- Supply Account Actions -->
              <template v-if="!isAdmin()">
                <button
                  v-if="request.status === 'supply_approved'"
                  @click="openAssignModal(request)"
                  class="px-2 py-1 bg-gradient-to-r from-purple-600 to-purple-700 text-white hover:from-purple-700 hover:to-purple-800 border-2 border-purple-800 rounded text-[10px] font-semibold uppercase transition-all shadow-sm hover:shadow-md flex items-center gap-1 whitespace-nowrap"
                  title="Assign to Administrator"
                >
                  <span class="material-icons-outlined text-xs">person_add</span>
                  <span class="hidden sm:inline">Assign</span>
                  <span class="sm:hidden">+</span>
                </button>
                <span v-if="!isAdmin() && request.status !== 'pending' && request.status !== 'supply_approved' && request.status !== 'admin_accepted' && request.status !== 'approved' && request.status !== 'ready_for_pickup'" class="inline-flex items-center gap-1 px-2 py-1 text-gray-500 dark:text-gray-400 text-[10px] font-medium bg-gray-100 dark:bg-gray-700 rounded">
                  <span class="material-icons-outlined text-xs">block</span>
                  <span class="hidden sm:inline">No Action</span>
                  <span class="sm:hidden">-</span>
                </span>
              </template>
              
              <!-- Admin Actions -->
              <template v-if="isAdmin()">
                <button
                  v-if="request.status === 'supply_approved'"
                  @click="openAssignModal(request)"
                  class="px-2 py-1 bg-gradient-to-r from-purple-600 to-purple-700 text-white hover:from-purple-700 hover:to-purple-800 border-2 border-purple-800 rounded text-[10px] font-semibold uppercase transition-all shadow-sm hover:shadow-md flex items-center gap-1 whitespace-nowrap"
                  title="Assign to Administrator"
                >
                  <span class="material-icons-outlined text-xs">person_add</span>
                  <span class="hidden sm:inline">Assign</span>
                  <span class="sm:hidden">+</span>
                </button>
                <button
                  v-if="request.status === 'admin_assigned'"
                  @click="acceptByAdmin(request.id)"
                  class="px-2 py-1 bg-gradient-to-r from-green-600 to-green-700 text-white hover:from-green-700 hover:to-green-800 border-2 border-green-800 rounded text-[10px] font-semibold uppercase transition-all shadow-sm hover:shadow-md flex items-center gap-1 whitespace-nowrap"
                  title="Accept Request"
                >
                  <span class="material-icons-outlined text-xs">check_circle</span>
                  <span class="hidden sm:inline">Accept</span>
                  <span class="sm:hidden">OK</span>
                </button>
                <span v-if="isAdmin() && request.status !== 'supply_approved' && request.status !== 'admin_assigned' && request.status !== 'admin_accepted' && request.status !== 'approved' && request.status !== 'ready_for_pickup'" class="inline-flex items-center gap-1 px-2 py-1 text-gray-500 dark:text-gray-400 text-[10px] font-medium bg-gray-100 dark:bg-gray-700 rounded">
                  <span class="material-icons-outlined text-xs">block</span>
                  <span class="hidden sm:inline">No Action</span>
                  <span class="sm:hidden">-</span>
                </span>
              </template>
            </div>
          </div>
        </div>
      </div>

          <!-- Enhanced Requests Table - Desktop View -->
      <div v-if="!loading && !error && displayedRequests.length > 0" class="hidden lg:block bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
              <thead>
                <tr class="bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 dark:from-gray-800 dark:via-gray-700 dark:to-gray-800 border-b-2 border-blue-900 dark:border-gray-600">
                <th class="px-2 sm:px-3 py-2 text-left text-xs font-bold text-white uppercase tracking-wide border-r border-blue-500/30 dark:border-gray-600">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">person</span>
                    <span class="hidden sm:inline">Requestor</span>
                    <span class="sm:hidden">User</span>
                  </div>
                </th>
                <th v-if="isAdmin()" class="px-2 sm:px-3 py-2 text-left text-xs font-bold text-white uppercase tracking-wide border-r border-blue-500/30 dark:border-gray-600">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">location_on</span>
                    <span class="hidden sm:inline">Location</span>
                    <span class="sm:hidden">Loc</span>
                  </div>
                </th>
                <th v-if="isAdmin()" class="px-2 sm:px-3 py-2 text-left text-xs font-bold text-white uppercase tracking-wide border-r border-blue-500/30 dark:border-gray-600">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">business</span>
                    <span class="hidden xl:inline">Supply Unit</span>
                    <span class="xl:hidden">Unit</span>
                  </div>
                </th>
                <th class="px-2 sm:px-3 py-2 text-left text-xs font-bold text-white uppercase tracking-wide border-r border-blue-500/30 dark:border-gray-600">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">flag</span>
                    <span>Status</span>
                  </div>
                </th>
                <th class="px-2 sm:px-3 py-2 text-left text-xs font-bold text-white uppercase tracking-wide border-r border-blue-500/30 dark:border-gray-600">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">calendar_today</span>
                    <span class="hidden xl:inline">Date Submitted</span>
                    <span class="xl:hidden">Date</span>
                  </div>
                </th>
                <th class="px-2 sm:px-3 py-2 text-left text-xs font-bold text-white uppercase tracking-wide">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-xs">settings</span>
                    <span class="hidden sm:inline">Actions</span>
                    <span class="sm:hidden">Act</span>
                  </div>
                </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800">
              <tr v-for="(request, index) in displayedRequests" :key="request.id" class="border-b border-gray-200 dark:border-gray-700 hover:bg-blue-50/50 dark:hover:bg-gray-700/50 transition-colors" :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-800/50'">
                  <td class="px-2 sm:px-3 py-2 border-r border-gray-200 dark:border-gray-700">
                    <div class="flex items-start gap-1.5">
                      <div class="p-1 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex-shrink-0">
                        <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-xs">person</span>
                      </div>
                      <div class="min-w-0 flex-1">
                        <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ request.user?.name || 'N/A' }}</div>
                        <div class="text-[10px] text-gray-600 dark:text-gray-400 mt-0.5 flex items-center gap-0.5 truncate">
                          <span class="material-icons-outlined text-[10px] hidden sm:inline">location_on</span>
                          <span class="truncate">{{ request.user?.location || 'N/A' }}</span>
                        </div>
                      </div>
                    </div>
                  </td>
                <td v-if="isAdmin()" class="px-2 sm:px-3 py-2 border-r border-gray-200 dark:border-gray-700">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-xs">location_on</span>
                    <div class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ request.user?.location || 'N/A' }}</div>
                  </div>
                </td>
                <td v-if="isAdmin()" class="px-2 sm:px-3 py-2 border-r border-gray-200 dark:border-gray-700">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-xs">business</span>
                    <span class="text-xs font-medium text-gray-900 dark:text-white truncate">{{ request.supply_name || 'N/A' }}</span>
                  </div>
                </td>
                <td class="px-2 sm:px-3 py-2 border-r border-gray-200 dark:border-gray-700">
                  <span :class="['inline-flex items-center gap-0.5 px-1.5 sm:px-2 py-0.5 text-[10px] sm:text-xs font-semibold uppercase tracking-wide rounded shadow-sm', getStatusBadgeClass(request.status)]">
                    <span class="material-icons-outlined text-[10px]">flag</span>
                    <span class="hidden xl:inline">{{ request.status === 'supply_approved' ? 'Supply Approved' : request.status === 'admin_assigned' ? 'Assigned to Admin' : request.status === 'admin_accepted' ? 'Admin Accepted' : request.status === 'ready_for_pickup' ? 'Ready for Pickup' : request.status }}</span>
                    <span class="xl:hidden">{{ request.status === 'supply_approved' ? 'Approved' : request.status === 'admin_assigned' ? 'Assigned' : request.status === 'admin_accepted' ? 'Accepted' : request.status === 'ready_for_pickup' ? 'Pickup' : request.status.substring(0, 6) }}</span>
                    </span>
                  </td>
                <td class="px-2 sm:px-3 py-2 border-r border-gray-200 dark:border-gray-700">
                  <div class="flex items-center gap-1">
                    <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-xs hidden sm:inline">calendar_today</span>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ formatDate(request.created_at) }}</span>
                  </div>
                </td>
                <td class="px-2 sm:px-3 py-2">
                  <div class="flex items-center gap-1 flex-wrap">
                    <!-- View Details Button -->
                    <button
                      @click="openViewDetailsModal(request)"
                      class="relative p-1.5 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg transition-all duration-200 hover:shadow-sm flex items-center justify-center border-2 border-blue-400 dark:border-blue-500 w-8 h-8"
                      title="View details"
                    >
                      <span class="material-icons-outlined text-sm">visibility</span>
                      <!-- Action Required Indicator -->
                      <span 
                        v-if="needsAction(request)"
                        class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center w-3.5 h-3.5 bg-red-500 text-white text-[8px] rounded-full font-bold animate-pulse shadow-md border border-white dark:border-gray-800"
                        :title="getActionRequiredTooltip(request)"
                      >
                        !
                      </span>
                    </button>
                    <!-- Enhanced Message Icon Button -->
                    <button
                      @click="openMessageModal(request)"
                      class="relative p-1.5 text-blue-700 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/30 border-2 border-blue-300 dark:border-blue-700 rounded-lg transition-all flex items-center justify-center shadow-sm hover:shadow-md flex-shrink-0 w-8 h-8"
                      title="View Messages"
                    >
                      <span class="material-icons-outlined text-sm">message</span>
                      <span 
                        v-if="(unreadCounts[request.id] || 0) > 0"
                        class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[8px] rounded-full w-3.5 h-3.5 flex items-center justify-center font-bold shadow-lg animate-pulse"
                      >
                        {{ (unreadCounts[request.id] || 0) > 9 ? '9+' : (unreadCounts[request.id] || 0) }}
                      </span>
                    </button>
                    <!-- Enhanced Supply Account Actions -->
                    <template v-if="!isAdmin()">
                      <!-- Assign to Admin button for Supply Account when request is supply_approved -->
                      <button
                        v-if="request.status === 'supply_approved'"
                        @click="openAssignModal(request)"
                        class="px-2 sm:px-3 py-1.5 sm:py-2 bg-gradient-to-r from-purple-600 to-purple-700 text-white hover:from-purple-700 hover:to-purple-800 border-2 border-purple-800 rounded-lg text-xs font-bold uppercase transition-all shadow-md hover:shadow-lg flex items-center gap-1 whitespace-nowrap"
                        title="Assign to Administrator"
                      >
                        <span class="material-icons-outlined text-xs sm:text-sm">person_add</span>
                        <span class="hidden sm:inline">Assign</span>
                      </button>
                    </template>
                    
                    <!-- Enhanced Admin Actions -->
                    <template v-if="isAdmin()">
                      <button
                        v-if="request.status === 'supply_approved'"
                        @click="openAssignModal(request)"
                        class="px-2 sm:px-3 py-1.5 sm:py-2 bg-gradient-to-r from-purple-600 to-purple-700 text-white hover:from-purple-700 hover:to-purple-800 border-2 border-purple-800 rounded-lg text-xs font-bold uppercase transition-all shadow-md hover:shadow-lg flex items-center gap-1 whitespace-nowrap"
                        title="Assign to Administrator"
                      >
                        <span class="material-icons-outlined text-xs sm:text-sm">person_add</span>
                        <span class="hidden sm:inline">Assign</span>
                      </button>
                      <button
                        v-if="request.status === 'admin_assigned'"
                        @click="acceptByAdmin(request.id)"
                        class="px-2 sm:px-3 py-1.5 sm:py-2 bg-gradient-to-r from-green-600 to-green-700 text-white hover:from-green-700 hover:to-green-800 border-2 border-green-800 rounded-lg text-xs font-bold uppercase transition-all shadow-md hover:shadow-lg flex items-center gap-1 whitespace-nowrap"
                        title="Accept Request"
                      >
                        <span class="material-icons-outlined text-xs sm:text-sm">check_circle</span>
                        <span class="hidden sm:inline">Accept</span>
                      </button>
                    </template>
                    
                    <span v-if="!isAdmin() && request.status !== 'pending' && request.status !== 'supply_approved' && request.status !== 'admin_accepted' && request.status !== 'approved' && request.status !== 'ready_for_pickup'" class="inline-flex items-center gap-1 px-2 sm:px-3 py-1.5 sm:py-2 text-gray-500 dark:text-gray-400 text-xs font-semibold bg-gray-100 dark:bg-gray-700 rounded-lg">
                      <span class="material-icons-outlined text-xs sm:text-sm">block</span>
                      <span class="hidden sm:inline">No Action</span>
                    </span>
                    <span v-if="isAdmin() && request.status !== 'supply_approved' && request.status !== 'admin_assigned' && request.status !== 'admin_accepted' && request.status !== 'approved'" class="inline-flex items-center gap-1 px-2 sm:px-3 py-1.5 sm:py-2 text-gray-500 dark:text-gray-400 text-xs font-semibold bg-gray-100 dark:bg-gray-700 rounded-lg">
                      <span class="material-icons-outlined text-xs sm:text-sm">block</span>
                      <span class="hidden sm:inline">No Action</span>
                    </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Enhanced Pagination -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 border-t-2 border-gray-200 dark:border-gray-700 px-4 sm:px-6 py-3 sm:py-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-2 text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300">
              <span class="material-icons-outlined text-sm sm:text-base hidden sm:inline">info</span>
              <span class="text-center sm:text-left">
                <span class="hidden sm:inline">Displaying records </span>
                <span class="font-bold text-blue-600 dark:text-blue-400">{{ pagination.from || 0 }}</span>
                <span class="hidden sm:inline"> through </span>
                <span class="sm:hidden">-</span>
                <span class="font-bold text-blue-600 dark:text-blue-400">{{ pagination.to || 0 }}</span>
                <span class="hidden sm:inline"> of </span>
                <span class="sm:hidden">/</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ pagination.total }}</span>
                <span class="hidden sm:inline"> total requests</span>
              </span>
            </div>
            <div class="flex items-center gap-2 justify-center sm:justify-end">
              <button
                @click="currentPage--"
                :disabled="currentPage === 1"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-500 dark:hover:border-blue-400 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-800 disabled:hover:border-gray-300 dark:disabled:hover:border-gray-600 transition-all flex items-center gap-1 sm:gap-1.5 shadow-sm"
              >
                <span class="material-icons-outlined text-xs sm:text-sm">chevron_left</span>
                <span class="hidden sm:inline">Previous</span>
                <span class="sm:hidden">Prev</span>
              </button>
              <span class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-bold text-gray-900 dark:text-white bg-white dark:bg-gray-800 border-2 border-blue-500 dark:border-blue-400 rounded-lg shadow-sm flex items-center gap-1 sm:gap-1.5">
                <span class="material-icons-outlined text-xs sm:text-sm hidden sm:inline">description</span>
                <span>{{ currentPage }}/{{ pagination.last_page }}</span>
              </span>
              <button
                @click="currentPage++"
                :disabled="currentPage >= pagination.last_page"
                class="px-3 sm:px-4 py-2 sm:py-2.5 text-xs sm:text-sm font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-blue-50 dark:hover:bg-gray-700 hover:border-blue-500 dark:hover:border-blue-400 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-800 disabled:hover:border-gray-300 dark:disabled:hover:border-gray-600 transition-all flex items-center gap-1 sm:gap-1.5 shadow-sm"
              >
                <span class="hidden sm:inline">Next</span>
                <span class="sm:hidden">Next</span>
                <span class="material-icons-outlined text-xs sm:text-sm">chevron_right</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign to Admin Modal -->
    <div v-if="showAssignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div class="bg-white dark:bg-gray-800 shadow-lg border-2 border-gray-400 dark:border-gray-600 w-full max-w-md">
        <div class="bg-gradient-to-r from-blue-800 to-blue-700 dark:from-gray-700 dark:to-gray-800 border-b-2 border-blue-900 dark:border-gray-600 px-6 py-4 flex items-start justify-between">
          <div>
            <h3 class="text-lg font-bold text-white uppercase tracking-wide">Assign Request to Administrator</h3>
            <p class="text-xs text-white/90 dark:text-gray-300 mt-1">Request Item: {{ selectedRequest?.item_name }}</p>
          </div>
          <button @click="closeAssignModal" class="text-white hover:bg-white/20 dark:hover:bg-gray-600 p-1 transition-colors">
            <span class="material-icons-outlined text-lg">close</span>
          </button>
        </div>

        <div class="p-6 space-y-5">
          <div>
            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide">Select Administrator <span class="text-red-600 dark:text-red-400">*</span></label>
            <select
              v-model="assignForm.admin_id"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:border-blue-600 dark:focus:border-blue-500 focus:ring-1 focus:ring-blue-600 dark:focus:ring-blue-500 text-sm rounded-lg"
            >
              <option value="">-- Select Administrator --</option>
              <option v-for="admin in admins" :key="admin.id" :value="admin.id" class="bg-white dark:bg-gray-700">
                {{ admin.fullname || admin.username || admin.email }}
              </option>
            </select>
          </div>
        </div>

        <div class="bg-gray-100 dark:bg-gray-700/50 border-t-2 border-gray-300 dark:border-gray-600 px-6 py-4 flex items-center justify-end gap-3">
          <button
            @click="closeAssignModal"
            class="px-5 py-2 text-sm font-semibold border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-400 dark:hover:border-gray-500 transition-all rounded-lg"
          >
            Cancel
          </button>
          <button
            @click="assignToAdmin"
            :disabled="loading || !assignForm.admin_id"
            class="px-5 py-2 text-sm font-semibold bg-purple-700 text-white border-2 border-purple-900 dark:border-purple-800 hover:bg-purple-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-purple-700 transition-all rounded-lg"
          >
            Assign Request
          </button>
        </div>
      </div>
    </div>

    <!-- Forward to Another Supply Account Modal -->
    <Transition name="modal-fade">
      <div v-if="showForwardModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeForwardModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md transform transition-all">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 rounded-t-xl border-b-2 border-blue-800">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-xl">forward</span>
                </div>
          <div>
                  <h3 class="text-lg font-bold text-white">Forward Request</h3>
                  <p class="text-xs text-white/90 mt-0.5">To Another Supply Account</p>
          </div>
              </div>
              <button
                @click="closeForwardModal"
                :disabled="loading"
                class="p-1.5 text-white/80 hover:text-white hover:bg-white/20 rounded-lg transition-all"
                title="Close"
              >
                <span class="material-icons-outlined text-xl">close</span>
          </button>
            </div>
        </div>

          <!-- Modal Body -->
        <div class="p-6 space-y-5">
            <!-- Request Info Card -->
            <div v-if="selectedRequest" class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-sm">inventory_2</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">Request Item:</span>
              </div>
              <p class="text-base font-bold text-blue-900 dark:text-blue-100">{{ selectedRequest.item_name || selectedRequest.item?.name || 'N/A' }}</p>
            </div>

            <!-- Select Supply Account -->
          <div>
              <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-lg">person</span>
                <span>Select Supply Account <span class="text-red-600 dark:text-red-400">*</span></span>
              </label>
              <div class="relative">
            <select
                  v-model="forwardForm.supply_account_id"
                  class="w-full pl-11 pr-10 py-3 border-2 border-gray-300 dark:border-gray-600 focus:outline-none focus:border-blue-600 dark:focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-all appearance-none cursor-pointer hover:border-gray-400 dark:hover:border-gray-500"
                >
                  <option value="" class="bg-white dark:bg-gray-700">-- Select Supply Account --</option>
                  <option 
                    v-for="account in supplyAccounts" 
                    :key="account.id" 
                    :value="account.id"
                    class="bg-white dark:bg-gray-700"
                  >
                    {{ account.fullname || account.username || account.email }}
              </option>
            </select>
                <span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none text-lg">business</span>
                <span class="material-icons-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none text-sm">arrow_drop_down</span>
              </div>
          </div>

            <!-- Forwarding Comments -->
          <div>
              <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-lg">comment</span>
                <span>Forwarding Comments <span class="text-red-600 dark:text-red-400">*</span></span>
              </label>
              <div class="relative">
            <textarea
              v-model="forwardForm.comments"
              rows="4"
                  class="w-full pl-11 pr-4 py-3 border-2 border-gray-300 dark:border-gray-600 focus:outline-none focus:border-blue-600 dark:focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-lg text-sm resize-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition-all placeholder-gray-400 dark:placeholder-gray-500"
                  placeholder="Provide reason for forwarding this request to another supply account..."
            ></textarea>
                <span class="material-icons-outlined absolute left-3 top-3 text-gray-400 dark:text-gray-500 pointer-events-none text-lg">notes</span>
              </div>
          </div>
        </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 rounded-b-xl border-t border-gray-200 dark:border-gray-600 flex items-center justify-end gap-3">
          <button
            @click="closeForwardModal"
              :disabled="loading"
              class="flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
          >
              <span class="material-icons-outlined text-lg">close</span>
              <span>Cancel</span>
          </button>
          <button
              @click="forwardRequest"
              :disabled="loading || !forwardForm.supply_account_id || !forwardForm.comments.trim()"
              class="flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!loading" class="material-icons-outlined text-lg">forward</span>
              <span v-else class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ loading ? 'Forwarding...' : 'Forward Request' }}</span>
          </button>
        </div>
      </div>
    </div>
    </Transition>

    <!-- Approval Confirmation Modal -->
    <Transition name="modal-fade">
      <div v-if="showApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeApproveModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md transform transition-all">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 rounded-t-xl border-b-2 border-green-800">
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
              <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-green-600 dark:text-green-400 text-2xl">help_outline</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-base font-semibold text-gray-900 dark:text-white mb-2">
                  Are you sure you want to approve this request?
                </p>
                <div v-if="requestToApprove" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 mt-3">
                  <div v-if="!isMultiItemApprove()" class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
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
                  <div v-else class="text-sm text-gray-700 dark:text-gray-300 space-y-3">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">person</span>
                      <span><strong>Requested by:</strong> {{ requestToApprove.requested_by_user?.name || requestToApprove.user?.name || 'N/A' }}</span>
                    </div>
                    <p class="text-xs text-amber-600 dark:text-amber-400 font-medium">Reject defective items below. Only non-rejected items will be approved.</p>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                      <div
                        v-for="(it, idx) in requestToApprove.items"
                        :key="it.id || idx"
                        class="flex flex-wrap items-center justify-between gap-2 p-2 rounded-lg border"
                        :class="(it.status || 'pending') === 'rejected' ? 'border-red-300 dark:border-red-700 bg-red-50/50 dark:bg-red-900/20' : 'border-gray-200 dark:border-gray-600'"
                      >
                        <div class="min-w-0 flex-1">
                          <span class="font-medium">{{ it.item_name || 'N/A' }}</span>
                          <span class="text-gray-500 dark:text-gray-400 ml-1">× {{ it.quantity }}</span>
                          <p v-if="(it.status || 'pending') === 'rejected'" class="text-xs text-red-600 dark:text-red-400 mt-0.5">
                            Rejected: {{ it.rejection_reason || 'Defective' }}
                          </p>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                          <template v-if="(it.status || 'pending') === 'rejected'">
                            <button
                              type="button"
                              @click="unrejectItem(it)"
                              class="px-2 py-1 text-xs font-medium rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200"
                            >
                              Undo
                            </button>
                          </template>
                          <template v-else>
                            <button
                              type="button"
                              @click="openRejectItemModal(it)"
                              class="px-2 py-1 text-xs font-medium rounded bg-red-100 hover:bg-red-200 dark:bg-red-900/40 dark:hover:bg-red-900/60 text-red-700 dark:text-red-300 flex items-center gap-1"
                            >
                              <span class="material-icons-outlined text-sm">report_problem</span>
                              Reject (defective)
                            </button>
                          </template>
                        </div>
                      </div>
                    </div>
                    <p v-if="allItemsRejectedInApproveModal()" class="text-sm text-red-600 dark:text-red-400 font-medium">
                      All items rejected. Reject the entire request instead, or undo some rejections.
                    </p>
                  </div>
                </div>
                <p v-if="!allItemsRejectedInApproveModal()" class="text-sm text-gray-600 dark:text-gray-400 mt-3 flex items-start gap-2">
                  <span class="material-icons-outlined text-green-500 text-sm mt-0.5">info</span>
                  <span>{{ isMultiItemApprove() ? 'Only non-rejected items will be approved and the requester notified.' : 'The request will be approved and the requester will be notified.' }}</span>
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
              :disabled="approvingRequest || allItemsRejectedInApproveModal()"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!approvingRequest" class="material-icons-outlined text-lg">check_circle</span>
              <span v-else class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ approvingRequest ? 'Approving...' : 'Approve Request' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Reject Item (Defective) Modal -->
    <Transition name="modal-fade">
      <div v-if="showRejectItemModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeRejectItemModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md p-6">
          <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Reject item (defective)</h4>
          <p v-if="rejectItemTarget?.item" class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            {{ rejectItemTarget.item.item_name }} × {{ rejectItemTarget.item.quantity }}
          </p>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason <span class="text-red-500">*</span></label>
          <input
            v-model="rejectItemReason"
            type="text"
            placeholder="e.g. Defective, Damaged, Expired"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 mb-4"
          />
          <div class="flex gap-3 justify-end">
            <button
              type="button"
              @click="closeRejectItemModal"
              :disabled="rejectingItem"
              class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="rejectItem"
              :disabled="rejectingItem || !rejectItemReason.trim()"
              class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span v-if="rejectingItem" class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ rejectingItem ? 'Rejecting...' : 'Reject item' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Fulfill Request Confirmation Modal -->
    <Transition name="modal-fade">
      <div v-if="showFulfillModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeFulfillModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md transform transition-all">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-6 py-4 rounded-t-xl border-b-2 border-indigo-800">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-xl">done_all</span>
                </div>
                <h3 class="text-lg font-bold text-white">Mark as Fulfilled</h3>
              </div>
              <button
                @click="closeFulfillModal"
                :disabled="fulfillingRequest"
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
              <div class="p-3 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-indigo-600 dark:text-indigo-400 text-2xl">help_outline</span>
              </div>
              <div class="flex-1">
                <p class="text-base font-semibold text-gray-900 dark:text-white mb-2">
                  Mark this request as fulfilled?
                </p>
                <div v-if="requestToFulfill" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 mt-3">
                  <div v-if="requestToFulfill.items && requestToFulfill.items.length > 1" class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">person</span>
                      <span><strong>Requested by:</strong> {{ requestToFulfill.requested_by_user?.name || requestToFulfill.user?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">flag</span>
                      <span><strong>Status:</strong> <span class="capitalize">{{ requestToFulfill.status }}</span></span>
                    </div>
                    <p class="text-xs font-medium text-amber-600 dark:text-amber-400 pt-1">Approved items to fulfill (rejected excluded):</p>
                    <div class="space-y-1.5 max-h-32 overflow-y-auto">
                      <div v-for="(it, idx) in requestToFulfill.items.filter(i => !isItemRejected(i))" :key="it.id || idx" class="flex justify-between text-xs">
                        <span class="text-gray-700 dark:text-gray-300">{{ it.item_name }}</span>
                        <span class="font-bold text-gray-900 dark:text-white">× {{ it.quantity }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-600">
                      <span class="font-semibold text-gray-700 dark:text-gray-300">Total quantity (approved):</span>
                      <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ getTotalQuantity(requestToFulfill) }}</span>
                    </div>
                  </div>
                  <div v-else class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory_2</span>
                      <span><strong>Item:</strong> {{ requestToFulfill.item_name || requestToFulfill.item?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory</span>
                      <span><strong>Quantity:</strong> {{ getTotalQuantity(requestToFulfill) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">person</span>
                      <span><strong>Requested by:</strong> {{ requestToFulfill.requested_by_user?.name || requestToFulfill.user?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">flag</span>
                      <span><strong>Status:</strong> <span class="capitalize">{{ requestToFulfill.status }}</span></span>
                    </div>
                  </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-3 flex items-start gap-2">
                  <span class="material-icons-outlined text-indigo-500 text-sm mt-0.5">info</span>
                  <span v-if="requestToFulfill?.status === 'ready_for_pickup'">
                    This will mark the request as fulfilled after the user has picked up their items. Make sure the user has collected their items before marking as fulfilled.
                  </span>
                  <span v-else>
                    This will mark the request as fulfilled and complete the request process.
                  </span>
                </p>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 rounded-b-xl border-t border-gray-200 dark:border-gray-600 flex items-center gap-3">
            <button
              @click="closeFulfillModal"
              :disabled="fulfillingRequest"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg">close</span>
              <span>Cancel</span>
            </button>
            <button
              @click="fulfillRequest"
              :disabled="fulfillingRequest"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!fulfillingRequest" class="material-icons-outlined text-lg">done_all</span>
              <span v-else class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ fulfillingRequest ? 'Marking...' : 'Mark as Fulfilled' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Schedule Pickup Modal -->
    <Transition name="fade">
      <div v-if="showPickupScheduleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closePickupScheduleModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md transform transition-all">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-cyan-600 to-cyan-700 px-6 py-4 rounded-t-xl border-b-2 border-cyan-800">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-xl">schedule</span>
                </div>
                <h3 class="text-lg font-bold text-white">Schedule Pickup Time</h3>
              </div>
              <button
                @click="closePickupScheduleModal"
                :disabled="schedulingPickup"
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
              <div class="p-3 bg-cyan-100 dark:bg-cyan-900/30 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-cyan-600 dark:text-cyan-400 text-2xl">calendar_today</span>
              </div>
              <div class="flex-1">
                <p class="text-base font-semibold text-gray-900 dark:text-white mb-2">
                  Set pickup time for this request
                </p>
                <div v-if="requestToSchedulePickup" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 mt-3">
                  <div class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory_2</span>
                      <span><strong>Item:</strong> {{ requestToSchedulePickup.item_name || requestToSchedulePickup.item?.name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory</span>
                      <span><strong>Quantity:</strong> {{ requestToSchedulePickup.quantity }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">person</span>
                      <span><strong>Requested by:</strong> {{ requestToSchedulePickup.requested_by_user?.name || requestToSchedulePickup.user?.name || 'N/A' }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pickup Time Input -->
            <div class="space-y-3">
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                <span class="flex items-center gap-2">
                  <span class="material-icons-outlined text-cyan-600 dark:text-cyan-400 text-lg">access_time</span>
                  Pickup Scheduled Time <span class="text-red-500">*</span>
                </span>
              </label>
              
              <!-- Quick Time Interval Buttons -->
              <div class="space-y-2">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Quick Select:</p>
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                  <button
                    v-for="interval in [
                      { label: '15 min', minutes: 15 },
                      { label: '30 min', minutes: 30 },
                      { label: '1 hour', minutes: 60 },
                      { label: '2 hours', minutes: 120 },
                      { label: '3 hours', minutes: 180 }
                    ]"
                    :key="interval.minutes"
                    @click="setQuickInterval(interval.minutes)"
                    :class="[
                      'px-3 py-2 text-xs font-semibold rounded-lg border-2 transition-all',
                      pickupScheduleForm.quick_interval === interval.minutes
                        ? 'bg-cyan-600 text-white border-cyan-700 shadow-md'
                        : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-cyan-500 hover:bg-cyan-50 dark:hover:bg-cyan-900/20'
                    ]"
                    type="button"
                  >
                    {{ interval.label }}
                  </button>
                </div>
              </div>
              
              <!-- Custom Date/Time Input -->
              <div class="space-y-3">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Or set custom date & time:</p>
                
                <!-- Manual Minutes Input (Quick Option) -->
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Set time in minutes from now:
                  </label>
                  <div class="flex items-center gap-2">
                    <input
                      v-model.number="manualMinutes"
                      @input="setTimeFromMinutes"
                      type="number"
                      min="1"
                      step="1"
                      placeholder="e.g., 5, 10, 30, 60"
                      class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 focus:outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm"
                    />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">minutes</span>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    Type any number of minutes (e.g., 2, 5, 10, 45, 120)
                  </p>
                </div>
                
                <!-- Full Date/Time Input -->
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                    Or select specific date & time:
                  </label>
                  <div class="flex items-center gap-2">
                    <input
                      v-model="pickupScheduleForm.pickup_scheduled_at"
                      @input="pickupScheduleForm.quick_interval = null; manualMinutes = null"
                      type="datetime-local"
                      :min="new Date().toISOString().slice(0, 16)"
                      class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 focus:outline-none focus:border-cyan-600 focus:ring-2 focus:ring-cyan-500 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm"
                    />
                    <!-- Quick Time Adjustment Buttons -->
                    <div class="flex flex-col gap-1">
                      <button
                        type="button"
                        @click="adjustTime(15)"
                        class="p-1.5 bg-cyan-100 dark:bg-cyan-900/30 hover:bg-cyan-200 dark:hover:bg-cyan-900/50 border border-cyan-300 dark:border-cyan-700 rounded text-cyan-700 dark:text-cyan-300 transition-all"
                        title="Add 15 minutes"
                      >
                        <span class="material-icons-outlined text-sm">add</span>
                      </button>
                      <button
                        type="button"
                        @click="adjustTime(-15)"
                        class="p-1.5 bg-cyan-100 dark:bg-cyan-900/30 hover:bg-cyan-200 dark:hover:bg-cyan-900/50 border border-cyan-300 dark:border-cyan-700 rounded text-cyan-700 dark:text-cyan-300 transition-all"
                        title="Subtract 15 minutes"
                      >
                        <span class="material-icons-outlined text-sm">remove</span>
                      </button>
                    </div>
                  </div>
                </div>
                
                <!-- Time Adjustment Quick Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-xs text-gray-500 dark:text-gray-400">Quick adjust current time:</span>
                  <button
                    v-for="adjustment in [
                      { label: '+15 min', minutes: 15 },
                      { label: '+30 min', minutes: 30 },
                      { label: '+1 hour', minutes: 60 },
                      { label: '-15 min', minutes: -15 },
                      { label: '-30 min', minutes: -30 },
                      { label: '-1 hour', minutes: -60 }
                    ]"
                    :key="adjustment.label"
                    type="button"
                    @click="adjustTime(adjustment.minutes); manualMinutes = null"
                    class="px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 hover:bg-cyan-100 dark:hover:bg-cyan-900/30 text-gray-700 dark:text-gray-300 hover:text-cyan-700 dark:hover:text-cyan-300 border border-gray-300 dark:border-gray-600 hover:border-cyan-300 dark:hover:border-cyan-700 rounded transition-all"
                  >
                    {{ adjustment.label }}
                  </button>
                </div>
              </div>
              
              <!-- Display Selected Time -->
              <div v-if="pickupScheduleForm.pickup_scheduled_at" class="p-3 bg-cyan-50 dark:bg-cyan-900/20 rounded-lg border border-cyan-200 dark:border-cyan-800">
                <div class="flex items-center gap-2 text-sm">
                  <span class="material-icons-outlined text-cyan-600 dark:text-cyan-400 text-lg">schedule</span>
                  <span class="font-semibold text-gray-900 dark:text-white">
                    Pickup scheduled for: 
                    <span class="text-cyan-700 dark:text-cyan-300">
                      {{ formatPickupTime(pickupScheduleForm.pickup_scheduled_at) }}
                    </span>
                  </span>
                </div>
              </div>
              
              <p class="text-xs text-gray-500 dark:text-gray-400 flex items-start gap-1">
                <span class="material-icons-outlined text-xs mt-0.5">info</span>
                <span>Select a quick interval or choose a custom date and time when the items will be ready for pickup.</span>
              </p>
            </div>

            <!-- Notify User Checkbox -->
            <div class="flex items-start gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
              <input
                v-model="pickupScheduleForm.notify_user"
                type="checkbox"
                id="notify-user"
                class="mt-1 w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
              />
              <label for="notify-user" class="flex-1 text-sm text-gray-700 dark:text-gray-300">
                <span class="font-semibold">Notify user immediately</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Send a notification and message to the user informing them that their request is ready for pickup.
                </p>
              </label>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 rounded-b-xl border-t border-gray-200 dark:border-gray-600 flex items-center gap-3">
            <button
              @click="closePickupScheduleModal"
              :disabled="schedulingPickup"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg">close</span>
              <span>Cancel</span>
            </button>
            <button
              @click="schedulePickup"
              :disabled="schedulingPickup || !pickupScheduleForm.pickup_scheduled_at"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-cyan-600 to-cyan-700 hover:from-cyan-700 hover:to-cyan-800 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!schedulingPickup" class="material-icons-outlined text-lg">schedule</span>
              <span v-else class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ schedulingPickup ? 'Scheduling...' : 'Schedule Pickup' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Reject Request Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div class="bg-white dark:bg-gray-800 shadow-lg border-2 border-gray-400 dark:border-gray-600 w-full max-w-md rounded-xl overflow-hidden">
        <div class="bg-gradient-to-r from-red-800 to-red-700 dark:from-gray-700 dark:to-gray-800 border-b-2 border-red-900 dark:border-gray-600 px-6 py-4 flex items-start justify-between">
          <div>
            <h3 class="text-lg font-bold text-white uppercase tracking-wide">Reject Request</h3>
            <p class="text-xs text-white/90 dark:text-gray-300 mt-1">Request Item: {{ selectedRequest?.item_name }}</p>
          </div>
          <button @click="closeRejectModal" class="text-white hover:bg-white/20 dark:hover:bg-gray-600 p-1 transition-colors rounded">
            <span class="material-icons-outlined text-lg">close</span>
          </button>
        </div>

        <div class="p-6 space-y-5">
          <div>
            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide">Rejection Reason <span class="text-red-600 dark:text-red-400">*</span></label>
            <textarea
              v-model="rejectForm.reason"
              rows="4"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-red-600 dark:focus:border-red-500 focus:ring-1 focus:ring-red-600 dark:focus:ring-red-500 text-sm resize-none rounded-lg"
              placeholder="Please provide a reason for rejecting this request..."
            ></textarea>
          </div>
        </div>

        <div class="bg-gray-100 dark:bg-gray-700/50 border-t-2 border-gray-300 dark:border-gray-600 px-6 py-4 flex items-center justify-end gap-3">
          <button
            @click="closeRejectModal"
            class="px-5 py-2 text-sm font-semibold border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-400 dark:hover:border-gray-500 transition-all rounded-lg"
          >
            Cancel
          </button>
          <button
            @click="rejectRequest"
            :disabled="loading || !rejectForm.reason.trim()"
            class="px-5 py-2 text-sm font-semibold bg-red-700 text-white border-2 border-red-900 dark:border-red-800 hover:bg-red-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-red-700 transition-all rounded-lg"
          >
            Reject Request
          </button>
        </div>
      </div>
    </div>

    <!-- Message Modal -->
    <div v-if="showMessageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div class="bg-white dark:bg-gray-800 shadow-lg border-2 border-gray-400 dark:border-gray-600 w-full max-w-2xl max-h-[85vh] flex flex-col rounded-xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-800 to-blue-700 dark:from-gray-700 dark:to-gray-800 border-b-2 border-blue-900 dark:border-gray-600 px-6 py-4 flex items-start justify-between flex-shrink-0">
          <div>
            <h3 class="text-lg font-bold text-white uppercase tracking-wide">Request Communications</h3>
            <p class="text-xs text-white/90 dark:text-gray-300 mt-1">{{ selectedRequestForMessage?.item_name || 'Request' }} - {{ selectedRequestForMessage?.user?.name || 'User' }}</p>
          </div>
          <button @click="closeMessageModal" class="text-white hover:bg-white/20 dark:hover:bg-gray-600 p-1 transition-colors flex-shrink-0 rounded">
            <span class="material-icons-outlined text-lg">close</span>
          </button>
        </div>

        <!-- Messages List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4 min-h-0 message-modal-scroll">
          <div v-if="loadingMessages" class="flex justify-center py-8">
            <span class="material-icons-outlined animate-spin text-2xl text-blue-700 dark:text-blue-400">refresh</span>
          </div>
          <div v-else-if="messages.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            <span class="material-icons-outlined text-4xl mb-2 block">message</span>
            <p class="text-sm font-medium">No messages available</p>
          </div>
          <div v-else v-for="msg in messages" :key="msg.id" class="flex gap-3 border-b border-gray-200 dark:border-gray-700 pb-4 last:border-0">
            <div class="flex-shrink-0">
              <div class="w-10 h-10 bg-blue-700 dark:bg-gray-600 border-2 border-blue-800 dark:border-gray-500 flex items-center justify-center rounded-lg">
                <span class="material-icons-outlined text-white text-sm">person</span>
              </div>
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ msg.user.name }}</span>
                <span class="text-xs text-gray-600 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 px-2 py-0.5 font-medium uppercase rounded">{{ msg.user.role }}</span>
                <span class="text-xs text-gray-600 dark:text-gray-400">{{ msg.created_at_formatted }}</span>
                <span v-if="!msg.is_read && msg.user.id !== getCurrentUserId()" class="text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-300 border border-blue-300 dark:border-blue-700 px-2 py-0.5 font-semibold uppercase rounded">New</span>
              </div>
              <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-300 dark:border-gray-600 p-3 rounded-lg">
                <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">{{ cleanMessageText(msg.message) }}</p>
                <!-- Receipt Details Display -->
                <div v-if="extractReceiptUrl(msg.message)" class="mt-3 pt-3 border-t border-gray-200">
                  <div class="bg-white dark:bg-gray-700/50 border-2 border-green-300 dark:border-green-700 rounded-lg shadow-sm overflow-hidden max-w-full">
                    <!-- Receipt Header -->
                    <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-3 py-2.5">
                      <div class="flex items-center gap-2">
                        <span class="material-icons-outlined text-white text-lg flex-shrink-0">receipt_long</span>
                        <h4 class="text-xs font-bold text-white uppercase tracking-wide truncate">Approval Receipt</h4>
                      </div>
                    </div>
                    
                    <!-- Receipt Body -->
                    <div class="p-3 bg-gradient-to-br from-green-50 to-white dark:from-gray-700 dark:to-gray-800 rounded-b-lg">
                      <div class="space-y-2">
                        <!-- Receipt Number -->
                        <div v-if="extractReceiptNumber(msg.message)" class="flex flex-col sm:flex-row sm:items-start sm:justify-between py-1.5 border-b border-green-200 dark:border-green-800 gap-1">
                          <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide flex-shrink-0">Receipt #</span>
                          <span class="text-xs font-mono font-bold text-gray-900 dark:text-white break-all">{{ extractReceiptNumber(msg.message) }}</span>
                        </div>
                        
                        <!-- Approval Date -->
                        <div v-if="extractApprovalDate(msg.message)" class="flex flex-col sm:flex-row sm:items-start sm:justify-between py-1.5 border-b border-green-200 dark:border-green-800 gap-1">
                          <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide flex-shrink-0">Approved</span>
                          <span class="text-xs text-gray-900 dark:text-white break-words sm:text-right">{{ extractApprovalDate(msg.message) }}</span>
                        </div>
                        
                        <!-- Approved By -->
                        <div v-if="extractApproverName(msg.message)" class="flex flex-col sm:flex-row sm:items-start sm:justify-between py-1.5 border-b border-green-200 dark:border-green-800 gap-1">
                          <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide flex-shrink-0">Approved By</span>
                          <span class="text-xs text-gray-900 dark:text-white break-words sm:text-right">{{ extractApproverName(msg.message) }}</span>
                        </div>
                        
                        <!-- Item Details Section -->
                        <div v-if="extractItemDetails(msg.message)" class="pt-2">
                          <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                            <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm">check_circle</span>
                            <span>Item Details</span>
                          </div>
                          <div class="bg-white dark:bg-gray-700/50 rounded-lg border border-green-200 dark:border-green-800 p-3 space-y-3">
                            <template v-if="extractItemDetails(msg.message)?.Items">
                              <!-- Multiple Items -->
                              <div v-for="(item, idx) in extractItemDetails(msg.message).Items" :key="idx" 
                                   class="border-b border-green-100 last:border-b-0 pb-2 last:pb-0">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                  <div class="flex-1 min-w-0">
                                    <div class="text-xs font-semibold text-green-700 dark:text-green-400 mb-0.5">Item {{ item.number }}</div>
                                    <div class="text-sm font-bold text-gray-900 dark:text-white break-words">{{ item.name }}</div>
                                  </div>
                                  <div class="text-right flex-shrink-0">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Qty</div>
                                    <div class="text-sm font-bold text-green-700 dark:text-green-400">{{ item.quantity }}</div>
                                  </div>
                                </div>
                              </div>
                              <div v-if="extractItemDetails(msg.message)['Total Quantity']" 
                                   class="pt-2 border-t-2 border-green-300 mt-2">
                                <div class="flex items-center justify-between">
                                  <span class="text-xs font-bold text-gray-700 uppercase">Total Quantity</span>
                                  <span class="text-sm font-bold text-green-700">{{ extractItemDetails(msg.message)['Total Quantity'] }}</span>
                                </div>
                              </div>
                            </template>
                            <template v-else>
                              <!-- Single Item (backward compatible) -->
                              <div v-for="(value, key) in extractItemDetails(msg.message)" :key="key" 
                                   class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1">
                                <span class="text-xs text-gray-600 flex-shrink-0 font-semibold">{{ key }}:</span>
                                <span class="text-xs font-bold text-gray-900 break-words sm:text-right">{{ value }}</span>
                              </div>
                            </template>
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
                                  'w-32 h-32 border-2 rounded-lg p-1 bg-white dark:bg-gray-700 transition-all duration-300',
                                  hoveredQrCode === msg.id 
                                    ? 'border-green-500 dark:border-green-400 shadow-lg scale-110 border-4' 
                                    : 'border-green-300 dark:border-green-700 shadow-sm'
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
        <div class="bg-gray-100 dark:bg-gray-700/50 border-t-2 border-gray-300 dark:border-gray-600 p-4">
          <div class="flex gap-2">
            <textarea
              v-model="newMessage"
              @keydown.enter.exact.prevent="sendMessage"
              rows="2"
              placeholder="Enter your message..."
              class="flex-1 px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-blue-600 dark:focus:border-blue-500 focus:ring-1 focus:ring-blue-600 dark:focus:ring-blue-500 resize-none text-sm rounded-lg"
            ></textarea>
            <button
              @click="sendMessage"
              :disabled="!newMessage.trim()"
              class="px-5 py-2.5 bg-blue-700 text-white border-2 border-blue-900 dark:border-blue-800 hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-700 flex items-center gap-2 font-semibold text-sm transition-all rounded-lg"
            >
              <span class="material-icons-outlined text-sm">send</span>
              <span>Send</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Approval Proof Modal -->
    <div v-if="showApprovalProofModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div class="bg-white dark:bg-gray-800 shadow-lg border-2 border-gray-400 dark:border-gray-600 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-800 to-blue-700 dark:from-gray-700 dark:to-gray-800 border-b-2 border-blue-900 dark:border-gray-600 px-6 py-4 flex items-start justify-between">
          <div>
            <h3 class="text-lg font-bold text-white uppercase tracking-wide">Approval Receipt Verification</h3>
            <p class="text-xs text-white/90 dark:text-gray-300 mt-1">{{ selectedRequestForProof?.item_name || 'Request' }} - {{ selectedRequestForProof?.user?.name || 'User' }}</p>
          </div>
          <button @click="closeApprovalProofModal" class="text-white hover:bg-white/20 dark:hover:bg-gray-600 p-1 transition-colors rounded">
            <span class="material-icons-outlined text-lg">close</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 min-h-0">
          <div v-if="selectedRequestForProof?.approval_proof" class="space-y-4">
            <!-- Verification Info Box -->
            <div class="bg-gradient-to-r from-blue-50 to-gray-50 dark:from-gray-700 dark:to-gray-800 border-2 border-blue-300 dark:border-gray-600 p-4 rounded-lg">
              <div class="flex items-start gap-3">
                <span class="material-icons-outlined text-green-600 dark:text-green-400 text-2xl">verified</span>
                <div class="flex-1">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Receipt Verification for Item Pickup</p>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-700 dark:text-gray-300">
                    <div v-if="selectedRequestForProof?.request_number">
                      <span class="font-semibold">Receipt Number:</span> 
                      <span class="font-mono bg-white dark:bg-gray-700 px-2 py-1 rounded border border-gray-300 dark:border-gray-600">{{ selectedRequestForProof.request_number }}</span>
                    </div>
                    <div v-if="selectedRequestForProof?.quantity">
                      <span class="font-semibold">Quantity:</span> {{ selectedRequestForProof.quantity }}
                    </div>
                    <div v-if="selectedRequestForProof?.user?.name">
                      <span class="font-semibold">Requested By:</span> {{ selectedRequestForProof.user.name }}
                    </div>
                    <div v-if="selectedRequestForProof?.status">
                      <span class="font-semibold">Status:</span> 
                      <span class="capitalize">{{ selectedRequestForProof.status }}</span>
                    </div>
                  </div>
                  <p class="text-xs text-gray-600 dark:text-gray-400 mt-3 italic">⚠️ Please verify the receipt details match the request before releasing items to the user.</p>
                </div>
              </div>
            </div>
            
            <!-- Display image or PDF -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden bg-gray-50 dark:bg-gray-700/50">
              <img
                v-if="selectedRequestForProof.approval_proof && selectedRequestForProof.approval_proof.match(/\.(jpg|jpeg|png|gif)$/i)"
                :src="selectedRequestForProof.approval_proof"
                alt="Approval Proof"
                class="w-full h-auto max-h-[60vh] object-contain bg-white dark:bg-gray-800"
                @error="handleImageError"
              />
              <div v-else-if="selectedRequestForProof.approval_proof && selectedRequestForProof.approval_proof.match(/\.pdf$/i)" class="relative">
                <!-- PDF Display Area -->
                <div class="bg-white dark:bg-gray-800 min-h-[60vh] flex flex-col">
                  <!-- Try to display PDF using API endpoint -->
                  <div class="flex-1 p-4">
                    <object
                      :data="getReceiptUrl(selectedRequestForProof.id)"
                      type="application/pdf"
                      class="w-full h-[60vh] border border-gray-300 rounded"
                    >
                      <!-- Fallback content if PDF can't be displayed -->
                      <div class="w-full h-[60vh] flex flex-col items-center justify-center p-8 bg-gray-50 rounded border-2 border-dashed border-gray-300">
                        <span class="material-icons-outlined text-6xl text-blue-500 mb-4">picture_as_pdf</span>
                        <p class="text-lg font-semibold text-gray-900 mb-2">Receipt PDF Ready for Download</p>
                        <p class="text-sm text-gray-600 mb-6 text-center max-w-md">
                          The receipt PDF is available for download. Click the button below to download and verify the receipt details.
                        </p>
                        <a
                          :href="getReceiptUrl(selectedRequestForProof.id)"
                          target="_blank"
                          download
                          class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold shadow-lg transition-all"
                        >
                          <span class="material-icons-outlined">download</span>
                          <span>Download Receipt PDF</span>
                        </a>
                        <p class="text-xs text-gray-500 mt-4" v-if="selectedRequestForProof.request_number">
                          Receipt Number: <span class="font-mono font-semibold text-gray-900">{{ selectedRequestForProof.request_number }}</span>
                        </p>
                      </div>
                    </object>
                  </div>
                  
                  <!-- Download button footer -->
                  <div class="bg-gray-100 px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-xs text-gray-600">
                      <span class="font-semibold">💡 Tip:</span> Download the PDF to view full receipt details
                    </div>
                    <a
                      :href="getReceiptUrl(selectedRequestForProof.id)"
                      target="_blank"
                      download
                      class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-2 font-semibold"
                    >
                      <span class="material-icons-outlined text-base">download</span>
                      <span>Download PDF</span>
                    </a>
                  </div>
                </div>
              </div>
              <div v-else class="p-8 text-center">
                <span class="material-icons-outlined text-4xl text-gray-400 mb-2 block">description</span>
                <p class="text-gray-600">Proof document available</p>
                <a
                  v-if="selectedRequestForProof.approval_proof"
                  :href="selectedRequestForProof.approval_proof"
                  target="_blank"
                  download
                  class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                >
                  Download Document
                </a>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
            <span class="material-icons-outlined text-4xl mb-2 block">description</span>
            <p>No approval proof available</p>
          </div>
        </div>

        <div class="bg-gray-100 dark:bg-gray-700/50 border-t-2 border-gray-300 dark:border-gray-600 px-6 py-4 flex items-center justify-between gap-3">
          <div class="text-xs text-gray-700 dark:text-gray-300 font-medium">
            <span class="font-bold">Note:</span> Verify receipt number and user details before releasing items
          </div>
          <div class="flex items-center gap-3">
            <button
              @click="closeApprovalProofModal"
              class="px-5 py-2 text-sm font-semibold border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-400 dark:hover:border-gray-500 transition-all rounded-lg"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- QR Code Modal -->
    <div v-if="showQrCodeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="showQrCodeModal = false">
      <div class="bg-white dark:bg-gray-800 shadow-lg border-2 border-gray-400 dark:border-gray-600 max-w-md w-full mx-4 rounded-xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-800 to-blue-700 dark:from-gray-700 dark:to-gray-800 border-b-2 border-blue-900 dark:border-gray-600 px-6 py-4 flex items-start justify-between">
          <div>
            <h3 class="text-lg font-bold text-white uppercase tracking-wide">Receipt QR Code</h3>
            <p class="text-xs text-white/90 dark:text-gray-300 mt-1">Scan with mobile device to verify receipt details</p>
          </div>
          <button @click="showQrCodeModal = false" class="text-white hover:bg-white/20 dark:hover:bg-gray-600 p-1 transition-colors rounded">
            <span class="material-icons-outlined text-lg">close</span>
          </button>
        </div>
        
        <div class="p-6 flex flex-col items-center space-y-5">
          <div class="bg-gray-50 dark:bg-gray-700/50 p-6 border-2 border-gray-300 dark:border-gray-600 rounded-lg">
            <img 
              v-if="selectedQrCodeUrl"
              :src="selectedQrCodeUrl" 
              alt="Receipt QR Code" 
              class="w-64 h-64 mx-auto"
            />
          </div>
          
          <div class="text-center space-y-3 w-full">
            <p class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wide">QR Code Functions:</p>
            <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-2 text-left max-w-xs mx-auto">
              <li class="flex items-start gap-2 border-b border-gray-200 dark:border-gray-600 pb-2">
                <span class="text-blue-700 dark:text-blue-400 mt-0.5 font-bold">•</span>
                <span>Verify receipt authenticity</span>
              </li>
              <li class="flex items-start gap-2 border-b border-gray-200 dark:border-gray-600 pb-2">
                <span class="text-blue-700 dark:text-blue-400 mt-0.5 font-bold">•</span>
                <span>View receipt details</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="text-blue-700 dark:text-blue-400 mt-0.5 font-bold">•</span>
                <span>Check approval status</span>
              </li>
            </ul>
          </div>
          
          <button
            @click="showQrCodeModal = false"
            class="w-full px-4 py-2.5 bg-blue-700 text-white border-2 border-blue-900 hover:bg-blue-800 font-semibold text-sm transition-all"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- View Details Modal -->
    <Transition name="modal-fade">
      <div v-if="showViewDetailsModal && selectedViewRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeViewDetailsModal">
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-200/50 dark:border-gray-700/50 max-w-4xl w-full max-h-[90vh] overflow-hidden">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 dark:from-gray-700 dark:via-gray-800 dark:to-gray-900 px-6 py-5 border-b-2 border-blue-800 dark:border-gray-600">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                  <span class="material-icons-outlined text-white text-2xl">info</span>
                </div>
                <div>
                  <h3 class="text-2xl font-extrabold text-white drop-shadow-md">Request Details</h3>
                  <p class="text-blue-50 text-base font-medium mt-1">{{ selectedViewRequest.user?.name || 'N/A' }}</p>
                </div>
              </div>
              <button @click="closeViewDetailsModal" class="text-white/90 hover:text-white hover:bg-white/20 rounded-xl p-2 transition-all duration-200 hover:scale-110">
                <span class="material-icons-outlined text-2xl">close</span>
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="p-8 overflow-y-auto max-h-[70vh] bg-gradient-to-br from-gray-50/50 to-white dark:from-gray-800 dark:to-gray-900">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- User Information -->
              <div class="bg-white dark:bg-gray-800/50 rounded-xl p-5 shadow-md border border-gray-100 dark:border-gray-700/50">
                <div class="flex items-center gap-3 mb-3">
                  <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <span class="material-icons-outlined text-blue-600 dark:text-blue-400">person</span>
                  </div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Requestor</label>
                </div>
                <p class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ selectedViewRequest.user?.name || 'N/A' }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                  <span class="material-icons-outlined text-xs">location_on</span>
                  {{ selectedViewRequest.user?.location || 'N/A' }}
                </p>
              </div>

              <!-- Location -->
              <div class="bg-white dark:bg-gray-800/50 rounded-xl p-5 shadow-md border border-gray-100 dark:border-gray-700/50">
                <div class="flex items-center gap-3 mb-3">
                  <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                    <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400">location_on</span>
                  </div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Location</label>
                </div>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedViewRequest.user?.location || 'N/A' }}</p>
              </div>

              <!-- Item Description -->
              <div class="bg-white dark:bg-gray-800/50 rounded-xl p-5 shadow-md border border-gray-100 dark:border-gray-700/50 md:col-span-2">
                <div class="flex items-center gap-3 mb-3">
                  <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <span class="material-icons-outlined text-purple-600 dark:text-purple-400">inventory_2</span>
                  </div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Item Description</label>
                </div>
                <div v-if="selectedViewRequest.items && selectedViewRequest.items.length > 1" class="space-y-3">
                  <div v-for="(item, idx) in selectedViewRequest.items" :key="idx" 
                       class="p-4 rounded-lg border-2 border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/30">
                    <div class="flex items-start justify-between">
                      <div class="flex-1">
                        <p class="text-base font-bold text-gray-900 dark:text-white mb-2">{{ item.item_name || 'N/A' }}</p>
                        <div class="flex items-center gap-4 text-sm">
                          <div class="flex items-center gap-1">
                            <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory</span>
                            <span class="text-gray-600 dark:text-gray-400">Quantity: <span class="font-bold text-gray-900 dark:text-white">{{ item.quantity || 0 }}</span></span>
                          </div>
                          <div class="flex items-center gap-1">
                            <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">storage</span>
                            <span class="text-gray-600 dark:text-gray-400">Stock: <span class="font-bold text-blue-600 dark:text-blue-400">{{ item.item_quantity || 'N/A' }}</span></span>
                          </div>
                        </div>
                        <div v-if="isItemRejected(item)" class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-800">
                          <p class="text-xs font-semibold text-red-700 dark:text-red-300 uppercase">Rejected</p>
                          <p class="text-sm text-red-600 dark:text-red-400">{{ item.rejection_reason || 'Defective' }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                    <div class="flex items-center justify-between">
                      <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total Quantity (Non-Rejected):</span>
                      <span class="text-lg font-bold text-blue-700 dark:text-blue-300">{{ getTotalQuantity(selectedViewRequest) }}</span>
                    </div>
                  </div>
                </div>
                <div v-else>
                  <p class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ selectedViewRequest.item_name || 'N/A' }}</p>
                  <div class="flex items-center gap-4 text-sm">
                    <div class="flex items-center gap-1">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory</span>
                      <span class="text-gray-600 dark:text-gray-400">Quantity: <span class="font-bold text-gray-900 dark:text-white">{{ getTotalQuantity(selectedViewRequest) }}</span></span>
                    </div>
                    <div class="flex items-center gap-1">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">storage</span>
                      <span class="text-gray-600 dark:text-gray-400">Stock: <span class="font-bold text-blue-600 dark:text-blue-400">{{ selectedViewRequest.item_quantity || 'N/A' }}</span></span>
                    </div>
                  </div>
                  <div v-if="selectedViewRequest.items && selectedViewRequest.items.length === 1 && isItemRejected(selectedViewRequest.items[0])" 
                       class="mt-3 p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-800">
                    <p class="text-xs font-semibold text-red-700 dark:text-red-300 uppercase">Rejected</p>
                    <p class="text-sm text-red-600 dark:text-red-400">{{ selectedViewRequest.items[0].rejection_reason || 'Defective' }}</p>
                  </div>
                </div>
              </div>

              <!-- Status -->
              <div class="bg-white dark:bg-gray-800/50 rounded-xl p-5 shadow-md border border-gray-100 dark:border-gray-700/50">
                <div class="flex items-center gap-3 mb-3">
                  <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <span class="material-icons-outlined text-amber-600 dark:text-amber-400">flag</span>
                  </div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</label>
                </div>
                <span :class="['px-4 py-2 rounded-xl text-sm font-bold inline-flex items-center shadow-sm', getStatusBadgeClass(selectedViewRequest.status)]">
                  {{ selectedViewRequest.status === 'supply_approved' ? 'Supply Approved' : 
                     selectedViewRequest.status === 'admin_assigned' ? 'Assigned to Admin' :
                     selectedViewRequest.status === 'admin_accepted' ? 'Admin Accepted' :
                     selectedViewRequest.status === 'ready_for_pickup' ? 'For Pickup' :
                     selectedViewRequest.status }}
                </span>
              </div>

              <!-- Date -->
              <div class="bg-white dark:bg-gray-800/50 rounded-xl p-5 shadow-md border border-gray-100 dark:border-gray-700/50">
                <div class="flex items-center gap-3 mb-3">
                  <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                    <span class="material-icons-outlined text-indigo-600 dark:text-indigo-400">schedule</span>
                  </div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Date Submitted</label>
                </div>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatDate(selectedViewRequest.created_at) }}</p>
              </div>

              <!-- Supply Unit (if admin) -->
              <div v-if="isAdmin() && selectedViewRequest.supply_name" class="bg-white dark:bg-gray-800/50 rounded-xl p-5 shadow-md border border-gray-100 dark:border-gray-700/50">
                <div class="flex items-center gap-3 mb-3">
                  <div class="p-2 bg-teal-100 dark:bg-teal-900/30 rounded-lg">
                    <span class="material-icons-outlined text-teal-600 dark:text-teal-400">business</span>
                  </div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Supply Unit</label>
                </div>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedViewRequest.supply_name }}</p>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="bg-gradient-to-r from-gray-50 to-gray-100/50 dark:from-gray-800/50 dark:to-gray-700/50 px-8 py-5 border-t-2 border-gray-200 dark:border-gray-700/50">
            <div class="flex flex-col gap-4">
              <!-- Action Buttons Row -->
              <div class="flex items-center justify-end gap-3 flex-wrap">
                <!-- Supply Account Actions (for pending requests) -->
                <template v-if="!isAdmin() && selectedViewRequest.status === 'pending'">
                  <button
                    @click="handleApproveFromView(selectedViewRequest)"
                    class="px-5 py-2.5 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:hover:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg font-semibold transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2 border-2 border-emerald-400 dark:border-emerald-500"
                  >
                    <span class="material-icons-outlined text-lg">check_circle</span>
                    <span>Approve</span>
                  </button>
                  <button
                    @click="handleRejectFromView(selectedViewRequest)"
                    class="px-5 py-2.5 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg font-semibold transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2 border-2 border-red-400 dark:border-red-500"
                  >
                    <span class="material-icons-outlined text-lg">cancel</span>
                    <span>Reject</span>
                  </button>
                </template>
                
                <!-- Assign to Admin (for supply_approved status) -->
                <template v-if="selectedViewRequest.status === 'supply_approved'">
                  <button
                    @click="handleAssignFromView(selectedViewRequest)"
                    class="px-5 py-2.5 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg font-semibold transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2 border-2 border-purple-400 dark:border-purple-500"
                  >
                    <span class="material-icons-outlined text-lg">person_add</span>
                    <span>Assign to Admin</span>
                  </button>
                </template>
                
                <!-- Notify User and Schedule Pickup (for ready_for_pickup status) -->
                <template v-if="!isAdmin() && selectedViewRequest.status === 'ready_for_pickup'">
                  <button
                    @click="handleNotifyUserFromView(selectedViewRequest)"
                    :disabled="notifyingUser"
                    class="px-5 py-2.5 bg-green-50 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg font-semibold transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2 border-2 border-green-400 dark:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <span class="material-icons-outlined text-lg">{{ notifyingUser ? 'hourglass_empty' : 'notifications' }}</span>
                    <span>{{ notifyingUser ? 'Notifying...' : 'Notify User' }}</span>
                  </button>
                  <button
                    @click="handleSchedulePickupFromView(selectedViewRequest)"
                    class="px-5 py-2.5 bg-cyan-50 hover:bg-cyan-100 dark:bg-cyan-900/20 dark:hover:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400 rounded-lg font-semibold transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2 border-2 border-cyan-400 dark:border-cyan-500"
                  >
                    <span class="material-icons-outlined text-lg">schedule</span>
                    <span>Schedule Pickup</span>
                  </button>
                </template>
                
                <!-- Fulfill button (for admin_accepted, approved, or ready_for_pickup) -->
                <template v-if="selectedViewRequest.status === 'admin_accepted' || selectedViewRequest.status === 'approved' || selectedViewRequest.status === 'ready_for_pickup'">
                  <button
                    @click="handleFulfillFromView(selectedViewRequest)"
                    class="px-5 py-2.5 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg font-semibold transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2 border-2 border-blue-400 dark:border-blue-500"
                  >
                    <span class="material-icons-outlined text-lg">done_all</span>
                    <span>Fulfill</span>
                  </button>
                </template>
                
                <button 
                  @click="closeViewDetailsModal"
                  class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-semibold transition-all duration-200 shadow-md hover:shadow-lg"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Slide down transition for action reminder banner */
.slide-down-enter-active {
  transition: all 0.3s ease-out;
}

.slide-down-leave-active {
  transition: all 0.3s ease-in;
}

.slide-down-enter-from {
  opacity: 0;
  transform: translateY(-20px) scale(0.95);
}

.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-20px) scale(0.95);
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

</style>
