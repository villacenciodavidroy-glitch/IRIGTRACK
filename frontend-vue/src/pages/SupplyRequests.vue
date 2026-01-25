<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axiosClient from '../axios'
import { useDebouncedRef } from '../composables/useDebounce'

const router = useRouter()
const route = useRoute()
const supplies = ref([])
const myRequests = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const itemsPerPage = ref(12)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 12,
  total: 0,
  from: 0,
  to: 0
})

// Request modal state
const showRequestModal = ref(false)
const selectedSupply = ref(null)
const requestForm = ref({
  quantity: 1,
  notes: '',
  target_supply_account_id: null
})

// Supply accounts for selection
const supplyAccounts = ref([])
const loadingSupplyAccounts = ref(false)

// Cart for multiple items
const cart = ref([])
const showCart = ref(false)

// Request history modal
const showHistoryModal = ref(false)
const requestHistoryPagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0
})
const requestHistoryPage = ref(1)
const requestStatusFilter = ref('')

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

// All messages view state
const showAllMessagesView = ref(false)
const allMessages = ref([])
const loadingAllMessages = ref(false)
const selectedSender = ref(null) // For grouped conversations

// Cancel request modal state
const showCancelModal = ref(false)
const requestToCancel = ref(null)
const cancelingRequest = ref(false)
const requestPollingInterval = ref(null) // Polling interval for requests
const requestRealtimeListener = ref(null) // Real-time listener reference

// Banner state for success/error messages
const showBanner = ref(false)
const bannerMessage = ref('')
const bannerType = ref('success') // 'success' or 'error'
let bannerTimeout = null

// Show banner function
const showSimpleBanner = (message, type = 'success', autoHide = true, duration = 4000) => {
  // Clear any existing timeout
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  
  bannerMessage.value = message
  bannerType.value = type
  showBanner.value = true
  
  // Auto-hide after specified duration (default 4 seconds)
  if (autoHide) {
    bannerTimeout = setTimeout(() => {
      showBanner.value = false
      bannerTimeout = null
    }, duration)
  }
}

// Close banner
const closeBanner = () => {
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  showBanner.value = false
}

// Fetch available supplies
const fetchSupplies = async (silent = false) => {
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
    
    const response = await axiosClient.get('/supply-requests/available-supplies', { params })
    
    if (response.data.success) {
      supplies.value = response.data.data || []
      pagination.value = response.data.pagination || pagination.value
    } else {
      supplies.value = []
      error.value = response.data.message || 'Failed to load supplies'
    }
  } catch (err) {
    console.error('Error fetching supplies:', err)
    error.value = `Failed to load supplies: ${err.response?.data?.message || err.message}`
    supplies.value = []
  } finally {
    if (!silent) {
      loading.value = false
    }
  }
}

// Fetch user's request history
const fetchMyRequests = async (silent = false) => {
  try {
    const params = {
      page: requestHistoryPage.value,
      per_page: requestHistoryPagination.value.per_page
    }
    
    if (requestStatusFilter.value) {
      params.status = requestStatusFilter.value
    }
    
    const response = await axiosClient.get('/supply-requests/my-requests', { params })
    
    if (response.data.success) {
      const previousCount = myRequests.value.length
      myRequests.value = response.data.data || []
      requestHistoryPagination.value = response.data.pagination || requestHistoryPagination.value
      
      // Fetch unread message counts for each request
      fetchUnreadCounts()
      
      // Show notification if new requests arrived (only if not silent and on first page)
      if (!silent && requestHistoryPage.value === 1 && myRequests.value.length > previousCount) {
        const newRequestsCount = myRequests.value.length - previousCount
        if (newRequestsCount > 0) {
          console.log(`📋 ${newRequestsCount} new request(s) in your history`)
        }
      }
    }
  } catch (err) {
    console.error('Error fetching request history:', err)
  }
}

// Fetch unread message counts
const fetchUnreadCounts = async () => {
  try {
    // Use unread_messages_count from API response if available
    for (const request of myRequests.value) {
      if (request.unread_messages_count !== undefined) {
        unreadCounts.value[request.id] = request.unread_messages_count
    } else {
        // Fallback: fetch messages if count not in response
        try {
          const response = await axiosClient.get(`/supply-requests/${request.id}/messages`)
          if (response.data.success) {
            const unread = response.data.data.filter(msg => !msg.is_read && msg.user.id !== getCurrentUserId()).length
            unreadCounts.value[request.id] = unread
          }
        } catch (err) {
          console.error(`Error fetching unread count for request ${request.id}:`, err)
        }
      }
    }
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

// Format message to detect receipt links
const formatMessage = (message) => {
  if (!message) return ''
  
  // Detect receipt link pattern
  const receiptLinkPattern = /Receipt Link:\s*(https?:\/\/[^\s]+)/gi
  const hasReceipt = receiptLinkPattern.test(message)
  
  if (hasReceipt) {
    // Extract receipt URL
    const match = message.match(/Receipt Link:\s*(https?:\/\/[^\s]+)/i)
    if (match && match[1]) {
      return {
        text: message,
        receiptUrl: match[1],
        hasReceipt: true
      }
    }
  }
  
  return {
    text: message,
    receiptUrl: null,
    hasReceipt: false
  }
}

// Extract receipt URL from message
const extractReceiptUrl = (message) => {
  if (!message) return null
  const match = message.match(/Receipt Link:\s*(https?:\/\/[^\s]+)/i)
  return match ? match[1] : null
}

// Download PDF receipt from message
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

// Download receipt PDF from request history
const downloadRequestReceipt = async (requestId, event) => {
  event.preventDefault()
  if (!requestId) return
  
  try {
    const response = await axiosClient.get(`/supply-requests/${requestId}/receipt`, {
      responseType: 'blob',
      headers: {
        'Accept': 'application/pdf'
      }
    })
    
    const blob = response.data
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = `receipt_${new Date().toISOString().split('T')[0]}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch (error) {
    console.error('Error downloading receipt:', error)
    showSimpleBanner('Failed to download receipt. Please try again.', 'error', true, 5000)
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

// Fetch supply accounts
const fetchSupplyAccounts = async () => {
  loadingSupplyAccounts.value = true
  try {
    const response = await axiosClient.get('/users')
    const users = Array.isArray(response.data) ? response.data : (response.data.data || [])
    // Filter for supply accounts
    supplyAccounts.value = users.filter(user => {
      const role = (user.role || '').toLowerCase()
      return role === 'supply'
    })
  } catch (err) {
    console.error('Error fetching supply accounts:', err)
    supplyAccounts.value = []
  } finally {
    loadingSupplyAccounts.value = false
  }
}

// Open request modal
const openRequestModal = async (supply) => {
  selectedSupply.value = supply
  requestForm.value = {
    quantity: 1,
    notes: '',
    target_supply_account_id: null
  }
  // Fetch supply accounts when opening modal
  await fetchSupplyAccounts()
  showRequestModal.value = true
}

// Close request modal
const closeRequestModal = () => {
  showRequestModal.value = false
  selectedSupply.value = null
  requestForm.value = {
    quantity: 1,
    notes: '',
    target_supply_account_id: null
  }
}

// Add item to cart
const addToCart = async () => {
  if (!selectedSupply.value) return
  
  if (requestForm.value.quantity < 1) {
    showSimpleBanner('Quantity must be at least 1', 'error', true, 3000)
    return
  }
  
  if (requestForm.value.quantity > selectedSupply.value.quantity) {
    showSimpleBanner(`Requested quantity exceeds available stock. Available: ${selectedSupply.value.quantity}`, 'error', true, 4000)
    return
  }
  
  // Check if item already in cart
  const existingIndex = cart.value.findIndex(item => item.uuid === selectedSupply.value.uuid)
  if (existingIndex !== -1) {
    // Update quantity if already in cart
    cart.value[existingIndex].quantity = requestForm.value.quantity
    showSimpleBanner('Item quantity updated in request list', 'success', true, 3000)
  } else {
    // Add new item to cart
    cart.value.push({
      uuid: selectedSupply.value.uuid,
      unit: selectedSupply.value.unit,
      description: selectedSupply.value.description,
      quantity: requestForm.value.quantity,
      availableStock: selectedSupply.value.quantity
    })
    showSimpleBanner('Item added to request list successfully!', 'success', true, 3000)
  }
  
  closeRequestModal()
  // Fetch supply accounts if not already loaded
  if (supplyAccounts.value.length === 0) {
    await fetchSupplyAccounts()
  }
  showCart.value = true
}

// Open cart modal
const openCart = async () => {
  // Fetch supply accounts if not already loaded
  if (supplyAccounts.value.length === 0) {
    await fetchSupplyAccounts()
  }
  showCart.value = true
}

// Remove item from cart
const removeFromCart = (index) => {
  cart.value.splice(index, 1)
  if (cart.value.length === 0) {
    showCart.value = false
  }
}

// Update cart item quantity
const updateCartQuantity = (index, newQuantity) => {
  if (newQuantity < 1) {
    cart.value[index].quantity = 1
    return
  }
  if (newQuantity > cart.value[index].availableStock) {
    showSimpleBanner(`Maximum available stock: ${cart.value[index].availableStock}`, 'error', true, 3000)
    cart.value[index].quantity = cart.value[index].availableStock
    return
  }
  cart.value[index].quantity = newQuantity
}

// Submit supply request (single or multiple items)
const submitRequest = async (useCart = false) => {
  if (useCart) {
    // Submit multiple items from cart
    if (cart.value.length === 0) {
      showSimpleBanner('Request list is empty', 'error', true, 3000)
      return
    }
    
    try {
      loading.value = true
      const items = cart.value.map(item => ({
        item_id: item.uuid,
        quantity: item.quantity
      }))
      
      // Validate supply account is selected
      if (!requestForm.value.target_supply_account_id) {
        showSimpleBanner('Please select a supply account to submit the request to', 'error', true, 4000)
        return
      }
      
      const response = await axiosClient.post('/supply-requests', {
        items: items,
        notes: requestForm.value.notes,
        target_supply_account_id: requestForm.value.target_supply_account_id
      })
      
      if (response.data.success) {
        const successMessage = `Supply request submitted successfully for ${cart.value.length} item(s)!`
        showSimpleBanner(successMessage, 'success', true, 5000)
        cart.value = []
        showCart.value = false
        fetchSupplies()
        fetchMyRequests()
      } else {
        showSimpleBanner(response.data.message || 'Failed to submit request', 'error', true, 5000)
      }
    } catch (err) {
      console.error('Error submitting request:', err)
      const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'Failed to submit request'
      console.error('Error details:', err.response?.data)
      
      // If error mentions item not found or deleted, refresh supplies and clean cart
      if (errorMessage.includes('not found') || errorMessage.includes('deleted') || errorMessage.includes('removed')) {
        // Refresh supplies to get updated list
        await fetchSupplies()
        
        // Remove items from cart that no longer exist in supplies
        const validSupplies = supplies.value.map(s => s.uuid)
        const originalCartLength = cart.value.length
        cart.value = cart.value.filter(item => validSupplies.includes(item.uuid))
        
        if (cart.value.length < originalCartLength) {
          const removedCount = originalCartLength - cart.value.length
          showSimpleBanner(`${errorMessage}. ${removedCount} item(s) have been removed from your cart as they are no longer available.`, 'error', true, 6000)
          if (cart.value.length === 0) {
            showCart.value = false
          }
        } else {
          showSimpleBanner(errorMessage, 'error', true, 5000)
        }
      } else {
        showSimpleBanner(errorMessage, 'error', true, 5000)
      }
    } finally {
      loading.value = false
    }
  } else {
    // Submit single item (backward compatible)
    if (!selectedSupply.value) return
    
    if (requestForm.value.quantity < 1) {
      showSimpleBanner('Quantity must be at least 1', 'error', true, 3000)
      return
    }
    
    if (requestForm.value.quantity > selectedSupply.value.quantity) {
      showSimpleBanner(`Requested quantity exceeds available stock. Available: ${selectedSupply.value.quantity}`, 'error', true, 4000)
      return
    }
    
    // Validate supply account is selected
    if (!requestForm.value.target_supply_account_id) {
      showSimpleBanner('Please select a supply account to submit the request to', 'error', true, 4000)
      return
    }
    
    try {
      loading.value = true
      const response = await axiosClient.post('/supply-requests', {
        item_id: selectedSupply.value.uuid,
        quantity: requestForm.value.quantity,
        notes: requestForm.value.notes,
        target_supply_account_id: requestForm.value.target_supply_account_id
      })
      
      if (response.data.success) {
        showSimpleBanner('Supply request submitted successfully!', 'success', true, 5000)
        closeRequestModal()
        fetchSupplies()
        fetchMyRequests()
      } else {
        showSimpleBanner(response.data.message || 'Failed to submit request', 'error', true, 5000)
      }
    } catch (err) {
      console.error('Error submitting request:', err)
      const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message || 'Failed to submit request'
      console.error('Error details:', err.response?.data)
      showSimpleBanner(errorMessage, 'error', true, 5000)
    } finally {
      loading.value = false
    }
  }
}

// Open cancel request modal
const openCancelModal = (request) => {
  // Check if request can be cancelled
  if (request.status !== 'pending') {
    showSimpleBanner(`Cannot cancel request. Current status: ${request.status}. Only pending requests can be cancelled.`, 'error', true, 5000)
    return
  }
  requestToCancel.value = request
  showCancelModal.value = true
}

// Close cancel modal
const closeCancelModal = () => {
  if (!cancelingRequest.value) {
    showCancelModal.value = false
    requestToCancel.value = null
  }
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

// Cancel pending request
const cancelRequest = async () => {
  if (!requestToCancel.value) return
  
  cancelingRequest.value = true
  
  try {
    const response = await axiosClient.delete(`/supply-requests/${requestToCancel.value.id}/cancel`)
    
    if (response.data.success) {
      showSimpleBanner('Request cancelled successfully', 'success', true, 4000)
      showCancelModal.value = false
      requestToCancel.value = null
      fetchMyRequests()
      fetchSupplies()
    } else {
      const errorMsg = response.data.message || 'Failed to cancel request'
      showSimpleBanner(errorMsg, 'error', true, 5000)
    }
  } catch (err) {
    console.error('Error cancelling request:', err)
    
    // Get detailed error message
    let errorMessage = 'Failed to cancel request'
    
    if (err.response) {
      // Server responded with error
      errorMessage = err.response.data?.message || errorMessage
      
      // Include debug error if available
      if (err.response.data?.error && import.meta.env.DEV) {
        errorMessage += `: ${err.response.data.error}`
      }
      
      // Handle specific error codes
      if (err.response.status === 400) {
        errorMessage = err.response.data?.message || 'Invalid request. The request may have already been processed.'
      } else if (err.response.status === 403) {
        errorMessage = err.response.data?.message || 'You do not have permission to cancel this request.'
      } else if (err.response.status === 404) {
        errorMessage = 'Request not found. It may have been deleted.'
      } else if (err.response.status === 500) {
        errorMessage = err.response.data?.message || 'Server error occurred. Please try again later or contact support.'
      }
    } else if (err.request) {
      // Request was made but no response received
      errorMessage = 'Network error. Please check your connection and try again.'
    } else {
      // Error setting up request
      errorMessage = 'An error occurred. Please try again.'
    }
    
    showSimpleBanner(errorMessage, 'error', true, 6000)
    
    // If the request status changed or there's a database issue, refresh the list
    if (errorMessage.includes('status') || errorMessage.includes('Cannot cancel') || errorMessage.includes('Database error')) {
      setTimeout(() => {
        fetchMyRequests()
      }, 1000)
    }
  } finally {
    cancelingRequest.value = false
  }
}

// Get status badge class
const getStatusBadgeClass = (status, request = null) => {
  const statusLower = status?.toLowerCase()
  if (statusLower === 'approved') return 'bg-green-100 text-green-800'
  if (statusLower === 'ready_for_pickup') {
    // Only show "ready" styling if pickup time is scheduled
    // Otherwise show "waiting" styling
    return request?.pickup_scheduled_at 
      ? 'bg-cyan-100 text-cyan-800 border-2 border-cyan-300 animate-pulse'
      : 'bg-yellow-100 text-yellow-800 border-2 border-yellow-300'
  }
  if (statusLower === 'rejected') return 'bg-red-100 text-red-800'
  if (statusLower === 'fulfilled') return 'bg-blue-100 text-blue-800'
  return 'bg-yellow-100 text-yellow-800'
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

// Max quantity for request form
const maxQuantity = computed(() => {
  return selectedSupply.value ? selectedSupply.value.quantity : 0
})

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
  // Group messages by sender (user.id)
  const groupedBySender = {}
  allMessages.value.forEach(msg => {
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
  let request = myRequests.value.find(r => r.id === mostRecentMessage.supply_request.id)
  
  if (!request) {
    // Fetch requests in background, but open modal immediately with available data
    fetchMyRequests()
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

// Setup real-time listener for user's supply requests
const setupRequestRealtimeListener = () => {
  try {
    if (!window.Echo) {
      console.log('⏳ Echo not ready, retrying...')
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
    
    // Listen for request status updates (for user's own requests)
    requestsChannel.listen('.SupplyRequestUpdated', (data) => {
      console.log('🔄 Supply request updated via WebSocket:', data)
      fetchMyRequests(true) // Silent refresh
      // Only refresh supplies if quantity might have changed (approval/fulfillment)
      // For general updates, supplies don't need to refresh
    })
    
    // Listen for request approval
    requestsChannel.listen('.SupplyRequestApproved', (data) => {
      console.log('✅ Supply request approved via WebSocket:', data)
      fetchMyRequests(true) // Silent refresh
      fetchSupplies(true) // Silent refresh available supplies (quantities may change)
    })
    
    // Listen for request rejection
    requestsChannel.listen('.SupplyRequestRejected', (data) => {
      console.log('❌ Supply request rejected via WebSocket:', data)
      fetchMyRequests(true) // Silent refresh
      // Rejection doesn't change supplies, no need to refresh
    })
    
    // Listen for request fulfillment
    requestsChannel.listen('.SupplyRequestFulfilled', (data) => {
      console.log('📦 Supply request fulfilled via WebSocket:', data)
      fetchMyRequests(true) // Silent refresh
      fetchSupplies(true) // Silent refresh available supplies (quantities changed)
    })
    
    // Listen for request cancellation
    requestsChannel.listen('.SupplyRequestCancelled', (data) => {
      console.log('🚫 Supply request cancelled via WebSocket:', data)
      fetchMyRequests(true) // Silent refresh
      fetchSupplies(true) // Silent refresh available supplies (quantities restored)
    })
    
    requestRealtimeListener.value = requestsChannel
    console.log('✅ Real-time supply request listener active (User)')
  } catch (error) {
    console.error('❌ Error setting up request real-time listener:', error)
    setTimeout(setupRequestRealtimeListener, 3000)
  }
}

onMounted(() => {
  fetchSupplies()
  fetchMyRequests()
  
  // Check if we should show all messages view
  if (route.query.view === 'messages') {
    showAllMessagesView.value = true
    fetchAllMessages()
  }
  
  // Setup real-time listener for user's supply requests
  setTimeout(() => {
    setupRequestRealtimeListener()
  }, 1000) // Wait for Echo to initialize
  
  // Setup polling as fallback for requests only (supplies don't need frequent polling)
  // Real-time listeners handle updates, polling is just a backup
  requestPollingInterval.value = setInterval(async () => {
    if (document.visibilityState === 'visible') {
      await fetchMyRequests(true) // Silent refresh - only poll requests, not supplies
      // Supplies are refreshed via real-time listeners when requests are approved/fulfilled
    }
  }, 30000) // Poll every 30 seconds instead of 5 (slower since real-time is primary)
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
  fetchSupplies()
})

watch(currentPage, () => {
  fetchSupplies()
})

watch(itemsPerPage, () => {
    currentPage.value = 1
  fetchSupplies()
})

watch(requestHistoryPage, () => {
  fetchMyRequests()
})

watch(requestStatusFilter, () => {
  requestHistoryPage.value = 1
  fetchMyRequests()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 pb-8">
    <!-- Success/Error Banner -->
    <div
      v-if="showBanner"
      class="fixed top-4 left-4 z-[10000] max-w-sm w-auto"
    >
      <div
        class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg border-2 animate-banner-in"
        :class="{
          'bg-green-50 dark:bg-green-900/30 border-green-500 text-green-800 dark:text-green-200': bannerType === 'success',
          'bg-red-50 dark:bg-red-900/30 border-red-500 text-red-800 dark:text-red-200': bannerType === 'error'
        }"
      >
        <span
          class="material-icons-outlined text-xl flex-shrink-0"
          :class="{
            'text-green-600 dark:text-green-400': bannerType === 'success',
            'text-red-600 dark:text-red-400': bannerType === 'error'
          }"
        >
          {{ bannerType === 'success' ? 'check_circle' : 'error' }}
        </span>
        <p class="text-sm font-semibold flex-1">{{ bannerMessage }}</p>
        <button
          @click="closeBanner"
          class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex-shrink-0"
        >
          <span class="material-icons-outlined text-lg">close</span>
        </button>
      </div>
    </div>
    
    <!-- Enhanced Header Section -->
    <div v-if="!showAllMessagesView" class="bg-gradient-to-r from-emerald-600 via-green-600 to-emerald-700 shadow-2xl rounded-2xl mt-4 sm:mt-6 overflow-hidden relative">
      <!-- Decorative Background Elements -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white rounded-full -ml-24 -mb-24"></div>
      </div>
      
      <div class="px-6 py-6 sm:px-8 sm:py-7 relative z-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-4">
          <div class="flex items-center gap-3 pt-1">
            <button 
              @click="router.push('/dashboard')" 
              class="p-3 bg-white/20 backdrop-blur-sm border-2 border-white/30 text-white rounded-xl hover:bg-white/30 hover:scale-105 transition-all duration-200 shadow-lg"
              title="Go back"
            >
              <span class="material-icons-outlined text-xl">arrow_back</span>
            </button>
          </div>
          <div class="text-white">
            <div class="flex items-center gap-3 mb-2">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-2xl">inventory_2</span>
              </div>
              <h1 class="text-3xl sm:text-4xl font-extrabold leading-tight">Available Supplies</h1>
            </div>
            <p class="text-white/95 text-base sm:text-lg mt-1 font-medium">View available stock and request supplies for your needs</p>
          </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <button
            v-if="cart.length > 0"
            @click="openCart" 
            class="bg-white dark:bg-gray-800 text-blue-700 dark:text-blue-400 px-5 py-3 rounded-xl flex items-center gap-2 hover:-translate-y-1 hover:shadow-xl transition-all duration-200 font-bold shadow-lg border-2 border-white/80 dark:border-gray-700 relative group"
          >
            <span class="material-icons-outlined text-lg text-blue-700 dark:text-blue-400 group-hover:scale-110 transition-transform">playlist_add</span>
            <span>Request List</span>
            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center shadow-lg animate-pulse">{{ cart.length }}</span>
          </button>
          <button
            @click="showHistoryModal = true; fetchMyRequests()" 
            class="bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 px-5 py-3 rounded-xl flex items-center gap-2 hover:-translate-y-1 hover:shadow-xl transition-all duration-200 font-bold shadow-lg border-2 border-white/80 dark:border-gray-700 group"
          >
            <span class="material-icons-outlined text-lg text-emerald-700 dark:text-emerald-400 group-hover:rotate-180 transition-transform duration-500">history</span>
            <span>My Requests</span>
          </button>
        </div>
      </div>
    </div>

    <!-- All Messages View -->
    <div v-if="showAllMessagesView" class="p-4 sm:p-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
          <button
              @click="closeAllMessagesView"
              class="p-2 hover:bg-white/20 rounded-lg transition-colors"
              title="Back to supplies"
          >
              <span class="material-icons-outlined text-white">arrow_back</span>
          </button>
            <h2 class="text-xl font-bold text-white">Messages</h2>
          </div>
          <button
            @click="fetchAllMessages"
            class="p-2 hover:bg-white/20 rounded-lg transition-colors"
            title="Refresh messages"
            :disabled="loadingAllMessages"
          >
            <span class="material-icons-outlined text-white" :class="{ 'animate-spin': loadingAllMessages }">refresh</span>
          </button>
        </div>

        <!-- Messages List -->
        <div class="p-6">
          <div v-if="loadingAllMessages" class="text-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-600">Loading messages...</p>
          </div>

          <div v-else-if="groupedMessages.length === 0" class="text-center py-12">
            <span class="material-icons-outlined text-6xl text-gray-300 mb-4 block">message</span>
            <h3 class="text-xl font-bold text-gray-900 mb-2">No messages</h3>
            <p class="text-gray-600">You don't have any supply request messages yet.</p>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="message in groupedMessages"
              :key="`${message.sender?.id || message.user?.id}-${message.id}`"
              @click="handleAllMessageClick(message)"
              class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors relative"
              :class="{ 'bg-blue-50 dark:bg-blue-900/30 border-blue-300 dark:border-blue-600': message.hasUnread }"
            >
              <!-- Unread indicator -->
              <div v-if="message.hasUnread" class="absolute left-0 top-0 bottom-0 w-1 bg-blue-600 rounded-l-lg"></div>

              <div class="flex items-start gap-4 pl-2">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                  <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center overflow-hidden">
                    <span v-if="!(message.sender?.avatar || message.user?.avatar)" class="text-white font-semibold text-lg">
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
                      <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                        {{ message.sender?.role || message.user?.role || 'user' }}
                      </span>
                      <span v-if="message.groupedMessages && message.groupedMessages.length > 1" class="text-xs text-gray-500 dark:text-gray-400 bg-blue-100 dark:bg-blue-900/30 px-2 py-1 rounded">
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

                  <div class="flex items-center gap-4 text-xs text-gray-500">
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
                  <div class="h-3 w-3 rounded-full bg-blue-600"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="p-4 sm:p-6">
          <!-- Enhanced Search Bar -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <div class="flex flex-col sm:flex-row gap-4 items-center">
          <div class="flex-1 relative w-full">
            <div class="absolute left-4 top-1/2 transform -translate-y-1/2 z-10">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-xl">search</span>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search supplies by name..."
              class="w-full pl-12 pr-4 py-3.5 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 shadow-sm hover:border-gray-400 dark:hover:border-gray-500"
            />
          </div>
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <span class="material-icons-outlined text-lg">info</span>
            <span>{{ supplies.length }} {{ supplies.length === 1 ? 'item' : 'items' }} found</span>
          </div>
        </div>
      </div>

          <!-- Enhanced Loading State -->
      <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center space-y-4">
            <div class="relative">
              <div class="inline-block p-5 bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-full mb-2">
                <span class="material-icons-outlined animate-spin text-5xl text-emerald-600 dark:text-emerald-400">refresh</span>
              </div>
              <div class="absolute inset-0 border-4 border-emerald-200 dark:border-emerald-800 border-t-emerald-600 dark:border-t-emerald-400 rounded-full animate-spin"></div>
            </div>
            <div class="text-center">
              <p class="text-lg font-bold text-gray-900 dark:text-white mb-1">Loading supplies...</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">Please wait while we fetch the latest inventory</p>
            </div>
        </div>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-red-800">{{ error }}</p>
          </div>

          <!-- Enhanced Empty State -->
      <div v-else-if="supplies.length === 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center space-y-4">
            <div class="p-6 bg-gradient-to-br from-gray-100 to-gray-50 dark:from-gray-700 dark:to-gray-800 rounded-full">
              <span class="material-icons-outlined text-6xl text-gray-400 dark:text-gray-500">inventory_2</span>
            </div>
            <div class="text-center">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">No supplies found</h3>
              <p class="text-gray-600 dark:text-gray-400 text-center max-w-md">
                {{ searchQuery ? 'Try adjusting your search query or clear the search to see all available supplies.' : 'No supplies available at the moment. Please check back later.' }}
              </p>
            </div>
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="mt-4 px-6 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-semibold flex items-center gap-2"
            >
              <span class="material-icons-outlined text-lg">clear</span>
              <span>Clear Search</span>
            </button>
                </div>
              </div>
              
      <!-- Enhanced Supplies Grid/Table -->
      <div v-else class="space-y-4">
        <!-- Grid View for Supplies -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="supply in supplies"
            :key="supply.id"
            class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
            :class="supply.quantity > 0 
              ? 'border-emerald-200 dark:border-emerald-700 hover:border-emerald-400 dark:hover:border-emerald-500' 
              : 'border-gray-200 dark:border-gray-700 opacity-75'"
          >
            <!-- Card Header -->
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                      <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-xl">inventory_2</span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ supply.unit }}</h3>
                  </div>
                  <p v-if="supply.description" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                    {{ supply.description }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Card Body -->
            <div class="p-5 space-y-4">
              <!-- Stock Information -->
              <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-lg">warehouse</span>
                  <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Available Stock</span>
                </div>
                <div class="flex items-center gap-2">
                  <span 
                    class="px-3 py-1.5 text-sm font-bold rounded-full flex items-center gap-1.5 shadow-sm"
                    :class="supply.quantity > 0 
                      ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300' 
                      : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'"
                  >
                    <span 
                      class="w-2 h-2 rounded-full"
                      :class="supply.quantity > 0 ? 'bg-emerald-500' : 'bg-red-500'"
                    ></span>
                    {{ supply.quantity }}
                  </span>
                </div>
              </div>

              <!-- Stock Level Indicator -->
              <div v-if="supply.quantity > 0" class="space-y-1">
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                  <span>Stock Level</span>
                  <span class="font-semibold">
                    {{ supply.quantity > 50 ? 'High' : supply.quantity > 20 ? 'Medium' : 'Low' }}
                  </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                  <div 
                    class="h-2 rounded-full transition-all duration-500"
                    :class="{
                      'bg-emerald-500': supply.quantity > 50,
                      'bg-yellow-500': supply.quantity > 20 && supply.quantity <= 50,
                      'bg-orange-500': supply.quantity > 0 && supply.quantity <= 20
                    }"
                    :style="{ width: `${Math.min((supply.quantity / 100) * 100, 100)}%` }"
                  ></div>
                </div>
              </div>

              <!-- Action Button -->
              <button
                @click="openRequestModal(supply)"
                :disabled="supply.quantity === 0"
                class="w-full px-4 py-3 rounded-xl font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-300 transform hover:scale-105 disabled:transform-none disabled:hover:scale-100 shadow-md"
                :class="supply.quantity > 0
                  ? 'bg-gradient-to-r from-emerald-600 to-emerald-700 text-white hover:from-emerald-700 hover:to-emerald-800 hover:shadow-lg'
                  : 'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed'"
              >
                <span class="material-icons-outlined text-lg">{{ supply.quantity > 0 ? 'playlist_add' : 'block' }}</span>
                <span>{{ supply.quantity > 0 ? 'Request Item' : 'Out of Stock' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="supplies.length > 0" class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <span class="material-icons-outlined text-lg">info</span>
            <span>
              Showing <span class="font-semibold text-gray-900 dark:text-white">{{ pagination.from || 0 }}</span> to 
              <span class="font-semibold text-gray-900 dark:text-white">{{ pagination.to || 0 }}</span> of 
              <span class="font-semibold text-gray-900 dark:text-white">{{ pagination.total }}</span> supplies
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="currentPage--" 
              :disabled="currentPage === 1"
              class="px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-700 border-2 border-emerald-200 dark:border-emerald-700 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:border-emerald-300 dark:hover:border-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-700 transition-all duration-200 flex items-center gap-1.5"
            >
              <span class="material-icons-outlined text-lg">chevron_left</span>
              <span>Previous</span>
            </button>
            <div class="px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
              Page <span class="text-emerald-600 dark:text-emerald-400">{{ currentPage }}</span> of 
              <span class="text-emerald-600 dark:text-emerald-400">{{ pagination.last_page }}</span>
            </div>
            <button
              @click="currentPage++" 
              :disabled="currentPage >= pagination.last_page"
              class="px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-700 border-2 border-emerald-200 dark:border-emerald-700 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:border-emerald-300 dark:hover:border-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-700 transition-all duration-200 flex items-center gap-1.5"
            >
              <span>Next</span>
              <span class="material-icons-outlined text-lg">chevron_right</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Request Modal -->
    <div v-if="showRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeRequestModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col animate-modal-in overflow-hidden">
        <!-- Enhanced Header -->
        <div class="flex items-start justify-between p-6 md:p-8 pb-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
          <div class="flex items-start gap-4 flex-1">
            <div class="p-3 bg-gradient-to-br from-emerald-100 to-green-100 dark:from-emerald-900/30 dark:to-green-900/30 rounded-xl">
              <span class="material-icons-outlined text-2xl text-emerald-600 dark:text-emerald-400">playlist_add</span>
            </div>
            <div class="flex-1">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">Request Supply</h3>
              <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                <span class="material-icons-outlined text-base">inventory_2</span>
                {{ selectedSupply?.unit }}
              </p>
            </div>
          </div>
          <button 
            @click="closeRequestModal" 
            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200"
          >
            <span class="material-icons-outlined text-xl">close</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 md:px-8 py-4 space-y-5 min-h-0">
          <!-- Item Information Card -->
          <div class="bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800">
            <div class="grid grid-cols-2 gap-4">
              <div class="flex items-start gap-3">
                <div class="p-2 bg-white dark:bg-gray-700 rounded-lg">
                  <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">category</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Item</p>
                  <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ selectedSupply?.unit }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="p-2 bg-white dark:bg-gray-700 rounded-lg">
                  <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">warehouse</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Available Stock</p>
                  <div class="flex items-center gap-2">
                    <span 
                      class="px-2.5 py-1 text-sm font-bold rounded-full"
                      :class="selectedSupply?.quantity > 0 
                        ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300' 
                        : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'"
                    >
                      {{ selectedSupply?.quantity }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Quantity Input -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
              <span class="material-icons-outlined text-lg text-emerald-600 dark:text-emerald-400">numbers</span>
              <span>Quantity <span class="text-red-500">*</span></span>
            </label>
            <div class="relative">
              <input
                v-model.number="requestForm.quantity"
                type="number"
                min="1"
                :max="maxQuantity"
                class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 font-semibold"
              />
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                <span class="material-icons-outlined">inventory</span>
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
              <span class="material-icons-outlined text-sm">info</span>
              <span>Maximum available: <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ maxQuantity }}</span></span>
            </div>
          </div>

          <!-- Supply Account Selection -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
              <span class="material-icons-outlined text-lg text-emerald-600 dark:text-emerald-400">person</span>
              <span>Submit To Supply Account <span class="text-red-500">*</span></span>
            </label>
            <div class="relative">
              <select
                v-model="requestForm.target_supply_account_id"
                :disabled="loadingSupplyAccounts"
                class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 appearance-none disabled:bg-gray-100 dark:disabled:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-60 font-medium"
                required
              >
                <option :value="null">-- Select Supply Account --</option>
                <option 
                  v-for="account in supplyAccounts" 
                  :key="account.id" 
                  :value="account.id"
                >
                  {{ account.fullname || account.username || account.email }}
                </option>
              </select>
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                <span class="material-icons-outlined">account_circle</span>
              </span>
              <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                <span class="material-icons-outlined">keyboard_arrow_down</span>
              </span>
            </div>
            <div v-if="loadingSupplyAccounts" class="flex items-center gap-2 text-xs text-blue-600 dark:text-blue-400">
              <span class="material-icons-outlined text-sm animate-spin">refresh</span>
              <span>Loading supply accounts...</span>
            </div>
            <p v-else-if="supplyAccounts.length === 0" class="flex items-center gap-2 text-xs text-red-500 dark:text-red-400">
              <span class="material-icons-outlined text-sm">error</span>
              <span>No supply accounts available</span>
            </p>
          </div>

          <!-- Notes -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
              <span class="material-icons-outlined text-lg text-emerald-600 dark:text-emerald-400">note</span>
              <span>Notes <span class="text-xs font-normal text-gray-500">(Optional)</span></span>
            </label>
            <div class="relative">
              <textarea
                v-model="requestForm.notes"
                rows="3"
                class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 resize-none"
                placeholder="Add any additional notes or special instructions..."
              ></textarea>
              <span class="absolute left-4 top-4 text-gray-400 dark:text-gray-500">
                <span class="material-icons-outlined">edit_note</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Enhanced Action Buttons (sticky footer, all buttons in one row) -->
        <div class="flex-shrink-0 p-6 md:p-8 pt-4 border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
          <div class="flex flex-row items-center justify-end gap-3">
            <button
              @click="closeRequestModal"
              class="flex items-center justify-center gap-2 px-5 py-3 rounded-xl border-2 border-gray-400 dark:border-gray-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-600 font-semibold transition-all duration-200 whitespace-nowrap min-w-[110px] flex-shrink-0"
            >
              <span class="material-icons-outlined text-lg leading-none">close</span>
              <span>Cancel</span>
            </button>
            <button
              @click="addToCart"
              :disabled="loading || requestForm.quantity < 1 || requestForm.quantity > maxQuantity"
              class="flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed font-semibold transition-all duration-200 shadow-lg hover:shadow-xl disabled:transform-none whitespace-nowrap min-w-[180px] flex-shrink-0"
            >
              <span class="material-icons-outlined text-lg leading-none">playlist_add</span>
              <span>Add To Request List</span>
            </button>
            <button
              @click="submitRequest(false)"
              :disabled="loading || requestForm.quantity < 1 || requestForm.quantity > maxQuantity || !requestForm.target_supply_account_id"
              class="flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-700 text-white hover:from-emerald-700 hover:to-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed font-semibold transition-all duration-200 shadow-lg hover:shadow-xl disabled:transform-none whitespace-nowrap min-w-[150px] flex-shrink-0"
            >
              <span class="material-icons-outlined text-lg leading-none">playlist_add</span>
              <span>Request Item</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Cart Modal -->
    <div v-if="showCart" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="showCart = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl p-6 md:p-8 space-y-6 max-h-[90vh] overflow-y-auto animate-modal-in">
        <!-- Enhanced Header -->
        <div class="flex items-start justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-start gap-4 flex-1">
            <div class="p-3 bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-xl">
              <span class="material-icons-outlined text-2xl text-blue-600 dark:text-blue-400">shopping_cart</span>
            </div>
            <div class="flex-1">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">Request List</h3>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Review and submit multiple items at once</p>
            </div>
            <div v-if="cart.length > 0" class="px-3 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded-full text-sm font-bold">
              {{ cart.length }} {{ cart.length === 1 ? 'item' : 'items' }}
            </div>
          </div>
          <button 
            @click="showCart = false" 
            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200"
          >
            <span class="material-icons-outlined text-xl">close</span>
          </button>
        </div>

        <!-- Empty Cart State -->
        <div v-if="cart.length === 0" class="text-center py-16">
          <div class="p-6 bg-gradient-to-br from-gray-100 to-gray-50 dark:from-gray-700 dark:to-gray-800 rounded-full inline-block mb-4">
            <span class="material-icons-outlined text-6xl text-gray-400 dark:text-gray-500">shopping_cart</span>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Your request list is empty</h3>
          <p class="text-gray-600 dark:text-gray-400 mb-6">Add items to your request list to submit a request</p>
          <button
            @click="showCart = false"
            class="px-6 py-3 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition-colors font-semibold flex items-center gap-2 mx-auto"
          >
            <span class="material-icons-outlined">arrow_back</span>
            <span>Continue Shopping</span>
          </button>
        </div>

        <!-- Cart Items -->
        <div v-else class="space-y-4">
          <div
            v-for="(item, index) in cart"
            :key="index"
            class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-700/50 dark:to-gray-800/50 p-5 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-emerald-300 dark:hover:border-emerald-700 transition-all duration-200 shadow-sm hover:shadow-md"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="p-2.5 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex-shrink-0">
                  <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-xl">inventory_2</span>
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="font-bold text-lg text-gray-900 dark:text-white mb-1.5 truncate">{{ item.unit }}</h4>
                  <div class="flex items-center gap-2 text-sm">
                    <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-base">warehouse</span>
                    <span class="text-gray-600 dark:text-gray-400">Available: <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ item.availableStock }}</span></span>
                  </div>
                </div>
              </div>
              
              <!-- Quantity Controls -->
              <div class="flex items-center gap-3 flex-shrink-0">
                <div class="flex items-center gap-2 bg-white dark:bg-gray-700 rounded-lg border-2 border-gray-300 dark:border-gray-600 p-1">
                  <button
                    @click="updateCartQuantity(index, item.quantity - 1)"
                    class="w-9 h-9 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center justify-center transition-all duration-200 font-bold text-gray-700 dark:text-gray-300 hover:scale-110"
                    :disabled="item.quantity <= 1"
                  >
                    <span class="material-icons-outlined text-lg">remove</span>
                  </button>
                  <input
                    type="number"
                    :value="item.quantity"
                    @input="updateCartQuantity(index, parseInt($event.target.value) || 1)"
                    min="1"
                    :max="item.availableStock"
                    class="w-16 px-2 py-1.5 text-center border-0 bg-transparent text-gray-900 dark:text-white font-bold text-base focus:outline-none"
                  />
                  <button
                    @click="updateCartQuantity(index, item.quantity + 1)"
                    class="w-9 h-9 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center justify-center transition-all duration-200 font-bold text-gray-700 dark:text-gray-300 hover:scale-110"
                    :disabled="item.quantity >= item.availableStock"
                  >
                    <span class="material-icons-outlined text-lg">add</span>
                  </button>
                </div>
                <button
                  @click="removeFromCart(index)"
                  class="p-2.5 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all duration-200"
                  title="Remove from cart"
                >
                  <span class="material-icons-outlined text-xl">delete</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Request Details Section -->
          <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-5">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400">info</span>
              <h4 class="text-lg font-bold text-gray-900 dark:text-white">Request Details</h4>
            </div>

            <!-- Supply Account Selection -->
            <div class="space-y-2">
              <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                <span class="material-icons-outlined text-lg text-emerald-600 dark:text-emerald-400">person</span>
                <span>Submit To Supply Account <span class="text-red-500">*</span></span>
              </label>
              <div class="relative">
                <select
                  v-model="requestForm.target_supply_account_id"
                  :disabled="loadingSupplyAccounts"
                  class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 appearance-none disabled:bg-gray-100 dark:disabled:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-60 font-medium"
                  required
                >
                  <option :value="null">-- Select Supply Account --</option>
                  <option 
                    v-for="account in supplyAccounts" 
                    :key="account.id" 
                    :value="account.id"
                  >
                    {{ account.fullname || account.username || account.email }}
                  </option>
                </select>
                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                  <span class="material-icons-outlined">account_circle</span>
                </span>
                <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                  <span class="material-icons-outlined">keyboard_arrow_down</span>
                </span>
              </div>
              <div v-if="loadingSupplyAccounts" class="flex items-center gap-2 text-xs text-blue-600 dark:text-blue-400">
                <span class="material-icons-outlined text-sm animate-spin">refresh</span>
                <span>Loading supply accounts...</span>
              </div>
              <p v-else-if="supplyAccounts.length === 0" class="flex items-center gap-2 text-xs text-red-500 dark:text-red-400">
                <span class="material-icons-outlined text-sm">error</span>
                <span>No supply accounts available</span>
              </p>
            </div>

            <!-- Notes -->
            <div class="space-y-2">
              <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                <span class="material-icons-outlined text-lg text-emerald-600 dark:text-emerald-400">note</span>
                <span>Notes <span class="text-xs font-normal text-gray-500">(Optional)</span></span>
              </label>
              <div class="relative">
                <textarea
                  v-model="requestForm.notes"
                  rows="3"
                  class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 resize-none"
                  placeholder="Add any additional notes or special instructions..."
                ></textarea>
                <span class="absolute left-4 top-4 text-gray-400 dark:text-gray-500">
                  <span class="material-icons-outlined">edit_note</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Enhanced Action Buttons -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              @click="showCart = false"
              class="px-6 py-3 rounded-xl border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 font-semibold transition-all duration-200 flex items-center justify-center gap-2 whitespace-nowrap"
            >
              <span class="material-icons-outlined text-lg">arrow_back</span>
              <span>Continue Shopping</span>
            </button>
            <button
              @click="submitRequest(true)"
              :disabled="loading || cart.length === 0 || !requestForm.target_supply_account_id"
              class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-700 text-white hover:from-emerald-700 hover:to-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed font-semibold transition-all duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl transform hover:scale-105 disabled:transform-none whitespace-nowrap"
            >
              <span class="material-icons-outlined text-lg">send</span>
              <span>Submit {{ cart.length }} {{ cart.length === 1 ? 'Item' : 'Items' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Request History Modal -->
    <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="showHistoryModal = false">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-5xl p-6 md:p-8 space-y-6 max-h-[90vh] overflow-y-auto animate-modal-in">
        <!-- Enhanced Header -->
        <div class="flex items-start justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-start gap-4 flex-1">
            <div class="p-3 bg-gradient-to-br from-purple-100 to-indigo-100 dark:from-purple-900/30 dark:to-indigo-900/30 rounded-xl">
              <span class="material-icons-outlined text-2xl text-purple-600 dark:text-purple-400">history</span>
            </div>
            <div class="flex-1">
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">My Request History</h3>
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">View and manage all your supply requests</p>
            </div>
            <div v-if="myRequests.length > 0" class="px-3 py-1 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 rounded-full text-sm font-bold">
              {{ requestHistoryPagination.total || myRequests.length }} {{ (requestHistoryPagination.total || myRequests.length) === 1 ? 'request' : 'requests' }}
            </div>
          </div>
          <button 
            @click="showHistoryModal = false" 
            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200"
          >
            <span class="material-icons-outlined text-xl">close</span>
          </button>
        </div>

        <!-- Enhanced Filter -->
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
            <span class="material-icons-outlined text-lg text-emerald-600 dark:text-emerald-400">filter_list</span>
            <span>Filter by Status:</span>
          </div>
          <div class="relative flex-1 max-w-xs">
            <select 
              v-model="requestStatusFilter"
              class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 appearance-none cursor-pointer font-medium"
            >
              <option value="">All Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="fulfilled">Fulfilled</option>
            </select>
            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
              <span class="material-icons-outlined">tune</span>
            </span>
            <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
              <span class="material-icons-outlined">keyboard_arrow_down</span>
            </span>
          </div>
        </div>

        <!-- Enhanced Requests Table -->
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Item</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Quantity</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Date</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="(request, index) in myRequests" 
                :key="request.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200"
                :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50/50 dark:bg-gray-700/30'"
              >
                <td class="px-6 py-4">
                  <div v-if="request.items && request.items.length > 1" class="space-y-3">
                    <div v-for="(item, idx) in request.items" :key="idx" class="border-l-4 border-emerald-300 dark:border-emerald-700 pl-3 pb-3 last:pb-0 last:border-b-0">
                      <div class="flex items-center gap-2 mb-1">
                        <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-sm">inventory_2</span>
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ item.item_name }}</div>
                      </div>
                      <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                        <span class="material-icons-outlined text-xs">warehouse</span>
                        <span>Stock: <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ item.item_quantity }}</span></span>
                      </div>
                    </div>
                  </div>
                  <div v-else class="flex items-start gap-3">
                    <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                      <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">inventory_2</span>
                    </div>
                    <div>
                      <div class="text-sm font-bold text-gray-900 dark:text-white mb-1">{{ request.item_name }}</div>
                      <div v-if="request.item_description" class="text-xs text-gray-500 dark:text-gray-400">{{ request.item_description }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div v-if="request.items && request.items.length > 1" class="space-y-2">
                    <div v-for="(item, idx) in request.items" :key="idx" 
                         :class="['text-sm font-semibold', isItemRejected(item) 
                           ? 'text-red-600 dark:text-red-400 line-through' 
                           : 'text-gray-900 dark:text-white']">
                      {{ item.quantity }}
                    </div>
                    <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 pt-2 mt-2 border-t-2 border-emerald-200 dark:border-emerald-700">
                      Total: {{ getTotalQuantity(request) }}
                    </div>
                  </div>
                  <div v-else class="text-lg font-bold" 
                       :class="request.items && request.items.length === 1 && isItemRejected(request.items[0])
                         ? 'text-red-600 dark:text-red-400 line-through'
                         : 'text-gray-900 dark:text-white'">
                    {{ getTotalQuantity(request) }}
                  </div>
                </td>
                <td class="px-6 py-4">
                  <span 
                    :class="[
                      'px-3 py-1.5 text-xs font-bold rounded-full flex items-center gap-1.5 w-fit shadow-sm',
                      getStatusBadgeClass(request.status, request)
                    ]"
                  >
                    <span class="material-icons-outlined text-xs">
                      {{ request.status === 'pending' ? 'hourglass_empty' : request.status === 'approved' ? 'check_circle' : request.status === 'ready_for_pickup' ? (request.pickup_scheduled_at ? 'schedule' : 'pending') : request.status === 'fulfilled' ? 'done_all' : 'cancel' }}
                    </span>
                    {{ request.status === 'ready_for_pickup' 
                        ? (request.pickup_scheduled_at ? 'Ready for Pickup' : 'Awaiting Pickup Schedule') 
                        : request.status }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                      <span class="material-icons-outlined text-base">calendar_today</span>
                      <span>{{ formatDate(request.created_at) }}</span>
                    </div>
                    <div v-if="request.status === 'ready_for_pickup' && request.pickup_scheduled_at" class="flex items-center gap-2 text-xs text-cyan-600 dark:text-cyan-400 font-semibold mt-1">
                      <span class="material-icons-outlined text-sm">schedule</span>
                      <span>Pickup: {{ formatDate(request.pickup_scheduled_at) }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <!-- Receipt Download Button -->
                    <button
                      v-if="request.approval_proof && (request.status === 'approved' || request.status === 'fulfilled' || request.status === 'ready_for_pickup')"
                      @click="downloadRequestReceipt(request.id, $event)"
                      class="p-2.5 text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md"
                      title="Download Approval Receipt"
                    >
                      <span class="material-icons-outlined text-xl">receipt</span>
                    </button>
                    <button
                      @click="openMessageModal(request)"
                      class="relative p-2.5 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-all duration-200 shadow-sm hover:shadow-md"
                      title="Messages"
                    >
                      <span class="material-icons-outlined text-xl">message</span>
                      <span 
                        v-if="unreadCounts[request.id] > 0"
                        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center font-bold shadow-lg animate-pulse"
                      >
                        {{ unreadCounts[request.id] > 9 ? '9+' : unreadCounts[request.id] }}
                      </span>
                    </button>
                    <button
                      v-if="request.status === 'pending'"
                      @click="openCancelModal(request)"
                      class="px-4 py-2 text-sm font-semibold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all duration-200 border border-red-300 dark:border-red-700 hover:border-red-400 dark:hover:border-red-600 flex items-center gap-2"
                    >
                      <span class="material-icons-outlined text-sm">cancel</span>
                      Cancel
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="myRequests.length === 0" class="text-center py-16">
          <div class="p-6 bg-gradient-to-br from-gray-100 to-gray-50 dark:from-gray-700 dark:to-gray-800 rounded-full inline-block mb-4">
            <span class="material-icons-outlined text-6xl text-gray-400 dark:text-gray-500">history</span>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No requests found</h3>
          <p class="text-gray-600 dark:text-gray-400">
            {{ requestStatusFilter ? 'No requests match the selected filter. Try a different status.' : 'You haven\'t made any supply requests yet.' }}
          </p>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="myRequests.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <span class="material-icons-outlined text-lg">info</span>
            <span>
              Showing <span class="font-semibold text-gray-900 dark:text-white">{{ requestHistoryPagination.from || 0 }}</span> to 
              <span class="font-semibold text-gray-900 dark:text-white">{{ requestHistoryPagination.to || 0 }}</span> of 
              <span class="font-semibold text-gray-900 dark:text-white">{{ requestHistoryPagination.total }}</span> requests
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="requestHistoryPage--" 
              :disabled="requestHistoryPage === 1"
              class="px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-700 border-2 border-emerald-200 dark:border-emerald-700 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:border-emerald-300 dark:hover:border-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-700 transition-all duration-200 flex items-center gap-1.5"
            >
              <span class="material-icons-outlined text-lg">chevron_left</span>
              <span>Previous</span>
            </button>
            <div class="px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
              Page <span class="text-emerald-600 dark:text-emerald-400">{{ requestHistoryPage }}</span> of 
              <span class="text-emerald-600 dark:text-emerald-400">{{ requestHistoryPagination.last_page }}</span>
            </div>
            <button 
              @click="requestHistoryPage++" 
              :disabled="requestHistoryPage >= requestHistoryPagination.last_page"
              class="px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400 bg-white dark:bg-gray-700 border-2 border-emerald-200 dark:border-emerald-700 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 hover:border-emerald-300 dark:hover:border-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-700 transition-all duration-200 flex items-center gap-1.5"
            >
              <span>Next</span>
              <span class="material-icons-outlined text-lg">chevron_right</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Message Modal -->
    <div v-if="showMessageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-4 md:p-6 space-y-3 md:space-y-4 max-h-[85vh] flex flex-col">
        <div class="flex items-start justify-between flex-shrink-0">
          <div>
            <h3 class="text-lg md:text-xl font-semibold text-gray-900">Messages</h3>
            <p class="text-xs md:text-sm text-gray-500 mt-1">{{ selectedRequestForMessage?.item_name || 'Request' }}</p>
          </div>
          <button @click="closeMessageModal" class="text-gray-500 hover:text-gray-700 flex-shrink-0">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>

        <!-- Messages List -->
        <div class="flex-1 overflow-y-auto space-y-3 md:space-y-4 min-h-0 pr-1">
          <div v-if="loadingMessages" class="flex justify-center py-8">
            <span class="material-icons-outlined animate-spin text-2xl text-emerald-600">refresh</span>
          </div>
          <div v-else-if="messages.length === 0" class="text-center py-8 text-gray-500">
            <span class="material-icons-outlined text-4xl mb-2 block">message</span>
            <p>No messages yet</p>
          </div>
          <div v-else v-for="msg in messages" :key="msg.id" class="flex gap-3">
            <div class="flex-shrink-0">
              <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                <span class="material-icons-outlined text-emerald-600 text-sm">person</span>
              </div>
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-semibold text-gray-900">{{ msg.user.name }}</span>
                <span class="text-xs text-gray-500">{{ msg.created_at_formatted }}</span>
                <span v-if="!msg.is_read && msg.user.id !== getCurrentUserId()" class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">New</span>
              </div>
              <div class="bg-gray-50 rounded-lg p-3">
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
                          <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                            <span class="material-icons-outlined text-green-600 text-sm">check_circle</span>
                            <span>Item Details</span>
                          </div>
                          <div class="bg-white rounded-lg border border-green-200 p-3 space-y-3">
                            <template v-if="extractItemDetails(msg.message)?.Items">
                              <!-- Multiple Items -->
                              <div v-for="(item, idx) in extractItemDetails(msg.message).Items" :key="idx" 
                                   class="border-b border-green-100 last:border-b-0 pb-2 last:pb-0">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                  <div class="flex-1 min-w-0">
                                    <div class="text-xs font-semibold text-green-700 mb-0.5">Item {{ item.number }}</div>
                                    <div class="text-sm font-bold text-gray-900 break-words">{{ item.name }}</div>
                                  </div>
                                  <div class="text-right flex-shrink-0">
                                    <div class="text-xs text-gray-500">Qty</div>
                                    <div class="text-sm font-bold text-green-700">{{ item.quantity }}</div>
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
        <div class="border-t pt-4">
          <div class="flex gap-2">
            <textarea
              v-model="newMessage"
              @keydown.enter.exact.prevent="sendMessage"
              rows="2"
              placeholder="Type your message..."
              class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
            ></textarea>
            <button
              @click="sendMessage"
              :disabled="!newMessage.trim()"
              class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span class="material-icons-outlined text-sm">send</span>
              <span>Send</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Request Confirmation Modal -->
    <Transition name="modal-fade">
      <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeCancelModal">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 w-full max-w-md transform transition-all">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 rounded-t-xl border-b-2 border-red-800">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-xl">warning</span>
                </div>
                <h3 class="text-lg font-bold text-white">Cancel Request</h3>
              </div>
              <button
                @click="closeCancelModal"
                :disabled="cancelingRequest"
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
              <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg flex-shrink-0">
                <span class="material-icons-outlined text-red-600 dark:text-red-400 text-2xl">help_outline</span>
              </div>
              <div class="flex-1">
                <p class="text-base font-semibold text-gray-900 dark:text-white mb-2">
                  Are you sure you want to cancel this request?
                </p>
                <div v-if="requestToCancel" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 mt-3">
                  <div class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory_2</span>
                      <span><strong>Item:</strong> {{ requestToCancel.item_name || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">inventory</span>
                      <span><strong>Quantity:</strong> {{ requestToCancel.quantity }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="material-icons-outlined text-gray-400 dark:text-gray-500 text-sm">flag</span>
                      <span><strong>Status:</strong> <span class="capitalize">{{ requestToCancel.status }}</span></span>
                    </div>
                  </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-3 flex items-start gap-2">
                  <span class="material-icons-outlined text-red-500 text-sm mt-0.5">info</span>
                  <span>This action cannot be undone. The request will be marked as cancelled.</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 rounded-b-xl border-t border-gray-200 dark:border-gray-600 flex items-center gap-3">
            <button
              @click="closeCancelModal"
              :disabled="cancelingRequest"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span class="material-icons-outlined text-lg">close</span>
              <span>Keep Request</span>
            </button>
            <button
              @click="cancelRequest"
              :disabled="cancelingRequest"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="!cancelingRequest" class="material-icons-outlined text-lg">cancel</span>
              <span v-else class="material-icons-outlined text-lg animate-spin">refresh</span>
              <span>{{ cancelingRequest ? 'Cancelling...' : 'Cancel Request' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- QR Code Modal -->
    <div v-if="showQrCodeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="showQrCodeModal = false">
      <div class="bg-white rounded-xl shadow-2xl p-6 md:p-8 max-w-md w-full mx-4">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="text-lg md:text-xl font-semibold text-gray-900">📱 Receipt QR Code</h3>
            <p class="text-xs text-gray-500 mt-1">Scan with your mobile device to verify receipt details</p>
          </div>
          <button @click="showQrCodeModal = false" class="text-gray-500 hover:text-gray-700 transition-colors">
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
            class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors"
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

/* Banner animation */
@keyframes banner-in {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.animate-banner-in {
  animation: banner-in 0.3s ease-out;
}

/* Modal animation */
@keyframes modal-in {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.animate-modal-in {
  animation: modal-in 0.3s ease-out;
}

/* Modal fade transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .bg-white,
.modal-fade-leave-active .bg-white,
.modal-fade-enter-active .dark\:bg-gray-800,
.modal-fade-leave-active .dark\:bg-gray-800 {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.modal-fade-enter-from .bg-white,
.modal-fade-leave-to .bg-white,
.modal-fade-enter-from .dark\:bg-gray-800,
.modal-fade-leave-to .dark\:bg-gray-800 {
  transform: scale(0.95);
  opacity: 0;
}
</style>
