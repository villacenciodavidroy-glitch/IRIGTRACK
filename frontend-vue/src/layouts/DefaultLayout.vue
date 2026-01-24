<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useAuth from '../composables/useAuth'
import useNotifications from '../composables/useNotifications'
import LogoutModal from '../components/LogoutModal.vue'
import axiosClient from '../axios'

const router = useRouter()
const route = useRoute()

// Use the auth composable to get user data
const { user, loading: userLoading, error: userError, fetchCurrentUser, getUserDisplayName, logout, isAdmin } = useAuth()

// Use the notifications composable
const { notifications, unreadCount, fetchNotifications, fetchUnreadCount, markAsRead, refreshNotifications, refreshUnreadCount, setupRealtimeListener, approveBorrowRequest, rejectBorrowRequest } = useNotifications()

// Global popup notification state
const showGlobalPopup = ref(false)
const globalPopupNotification = ref(null)

// Reject confirmation modal state
const showRejectModal = ref(false)
const notificationToReject = ref(null)

// Simple banner state
const showBanner = ref(false)
const bannerMessage = ref('')
const bannerType = ref('success') // 'success' or 'error'

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

// Format date time for message display
const formatMessageDateTime = (timestamp) => {
  if (!timestamp) return ''
  try {
    const date = new Date(timestamp)
    const month = date.toLocaleDateString('en-US', { month: 'short' })
    const day = date.getDate()
    const year = date.getFullYear()
    const time = date.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit',
      hour12: true 
    })
    return `${month} ${day}, ${year} ${time}`
  } catch (e) {
    return formatRelativeTime(timestamp)
  }
}

// Show simple banner
let bannerTimeout = null
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

// Check for pending borrow requests and show banner
const checkAndShowPendingRequestsBanner = () => {
  // Find all pending borrow requests
  const pendingRequests = notifications.value.filter(
    n => n.type === 'borrow_request' && n.borrowRequest?.status === 'pending'
  )
  
  console.log('🔍 Checking pending requests:', pendingRequests.length, 'pending', pendingRequests)
  
  if (pendingRequests.length > 0) {
    // Show banner with count of pending requests
    const count = pendingRequests.length
    const latestRequest = pendingRequests[0] // Most recent
    const itemName = latestRequest.item?.name || latestRequest.item?.unit || 'Item'
    const requestedBy = latestRequest.borrowRequest?.borrowed_by || 'User'
    const quantity = latestRequest.borrowRequest?.quantity || 1
    
    const message = count === 1
      ? `📦 Pending borrow request: ${itemName} (${quantity} unit(s)) from ${requestedBy}`
      : `📦 ${count} pending borrow request(s) - Latest: ${itemName} (${quantity} unit(s)) from ${requestedBy}`
    
    // ALWAYS update banner if there are pending requests (even if already showing)
    // This ensures the banner updates when new requests come in
    // Force show the banner even if it's already showing to ensure it's visible
    console.log('📢 Showing/updating banner for pending requests:', message, 'Current banner showing:', showBanner.value)
    
    // Clear any existing timeout that might hide the banner
    if (bannerTimeout) {
      clearTimeout(bannerTimeout)
      bannerTimeout = null
    }
    
    // Always show the banner when there are pending requests
    bannerMessage.value = message
    bannerType.value = 'success'
    showBanner.value = true
  } else {
    // No pending requests, hide banner if it's showing a pending request message
    if (showBanner.value && (bannerMessage.value.includes('Pending borrow request') || bannerMessage.value.includes('pending borrow request'))) {
      console.log('✅ No pending requests, hiding banner')
      closeBanner()
    }
  }
}

// Watch for changes in notifications and update banner accordingly
// Use immediate and deep watching to catch all changes
watch(notifications, () => {
  console.log('📬 Notifications changed, checking pending requests...')
  // Use nextTick to ensure the notification array is fully updated
  setTimeout(() => {
    checkAndShowPendingRequestsBanner()
  }, 0)
}, { deep: true, immediate: true })

// Also watch for changes in individual notification properties
watch(() => notifications.value.map(n => ({
  id: n.id,
  type: n.type,
  status: n.borrowRequest?.status
})), () => {
  console.log('📬 Notification properties changed, checking pending requests...')
  setTimeout(() => {
    checkAndShowPendingRequestsBanner()
  }, 0)
}, { deep: true })

// Store interval for periodic banner check
let bannerCheckInterval = null

// Wrapper for refreshNotifications to ensure banner check
const handleRefreshNotifications = async () => {
  await refreshNotifications()
  // Banner check will be triggered by watcher, but also check explicitly multiple times to ensure it shows
  checkAndShowPendingRequestsBanner()
  setTimeout(() => {
    checkAndShowPendingRequestsBanner()
  }, 50)
  setTimeout(() => {
    checkAndShowPendingRequestsBanner()
  }, 100)
  setTimeout(() => {
    checkAndShowPendingRequestsBanner()
  }, 200)
}

// Handle approve from global popup
const handleGlobalApprove = async (notification) => {
  if (!notification.borrowRequest || !notification.item) {
    return
  }
  
  // Check if request is already processed
  if (notification.borrowRequest.status !== 'pending') {
    showSimpleBanner(`Request already ${notification.borrowRequest.status}`, 'error')
    return
  }
  
  // Use UUID if available, otherwise use ID
  const itemId = notification.item.uuid || notification.item.id || notification.item_id
  const requestId = notification.borrowRequest.id
  
  if (!itemId || !requestId) {
    showSimpleBanner('Error: Missing item ID or request ID', 'error')
    return
  }
  
  // Disable button to prevent multiple clicks
  const originalStatus = notification.borrowRequest.status
  notification.borrowRequest.status = 'processing'
  
  try {
    const result = await approveBorrowRequest(itemId, requestId)
    
    if (result.success === true) {
      // Update status immediately to 'approved'
      notification.borrowRequest.status = 'approved'
      
      // Close popup
      showGlobalPopup.value = false
      
      // Show success banner
      showSimpleBanner('✓ Borrow request approved successfully', 'success')
      
      // Refresh notifications to get updated data from server
      await fetchNotifications(5)
      await fetchUnreadCount()
      
      // Check and update banner for pending requests - ensure it always shows if there are requests
      checkAndShowPendingRequestsBanner()
      setTimeout(() => {
        checkAndShowPendingRequestsBanner()
      }, 100)
    } else {
      // Revert status on error
      notification.borrowRequest.status = originalStatus
      showSimpleBanner(result.message || 'Failed to approve borrow request', 'error')
    }
  } catch (error) {
    // Revert status on error
    notification.borrowRequest.status = originalStatus
    console.error('Error in handleGlobalApprove:', error)
    showSimpleBanner(error.message || 'Failed to approve borrow request', 'error')
  }
}

// Handle reject from global popup - show confirmation modal
const handleGlobalReject = (notification) => {
  if (!notification.borrowRequest || !notification.item) {
    return
  }
  
  // Check if request is already processed
  if (notification.borrowRequest.status !== 'pending') {
    showSimpleBanner(`Request already ${notification.borrowRequest.status}`, 'error')
    return
  }
  
  // Show reject confirmation modal
  notificationToReject.value = notification
  showRejectModal.value = true
}

// Confirm reject action
const confirmReject = async () => {
  const notification = notificationToReject.value
  if (!notification) {
    return
  }
  
  // Close the reject modal
  showRejectModal.value = false
  
  // Use UUID if available, otherwise use ID
  const itemId = notification.item.uuid || notification.item.id || notification.item_id
  const requestId = notification.borrowRequest.id
  
  if (!itemId || !requestId) {
    showSimpleBanner('Error: Missing item ID or request ID', 'error')
    notificationToReject.value = null
    return
  }
  
  // Disable button to prevent multiple clicks
  const originalStatus = notification.borrowRequest.status
  notification.borrowRequest.status = 'processing'
  
  try {
    const result = await rejectBorrowRequest(itemId, requestId)
    
    if (result.success) {
      // Update status immediately to 'rejected'
      notification.borrowRequest.status = 'rejected'
      
      // Close popup
      showGlobalPopup.value = false
      
      // Show success banner
      showSimpleBanner('✓ Borrow request rejected', 'success')
      
      // Refresh notifications to get updated data from server
      await fetchNotifications(5)
      await fetchUnreadCount()
      
      // Check and update banner for pending requests - ensure it always shows if there are requests
      checkAndShowPendingRequestsBanner()
      setTimeout(() => {
        checkAndShowPendingRequestsBanner()
      }, 100)
    } else {
      // Revert status on error
      notification.borrowRequest.status = originalStatus
      showSimpleBanner(result.message || 'Failed to reject borrow request', 'error')
    }
  } catch (error) {
    // Revert status on error
    notification.borrowRequest.status = originalStatus
    console.error('Error in confirmReject:', error)
    showSimpleBanner(error.message || 'Failed to reject borrow request', 'error')
  } finally {
    notificationToReject.value = null
  }
}

// Cancel reject action
const cancelReject = () => {
  showRejectModal.value = false
  notificationToReject.value = null
}

// Handle notification click in dropdown - mark as read and navigate
const handleNotificationClick = async (notification) => {
  // Mark as read if not already read
  if (!notification.isRead) {
    await markAsRead(notification.id)
  }
  // Close the dropdown
  isNotificationsOpen.value = false
  
  // For lost/damaged item reports, navigate to Notifications page to show specialized modal
  if (notification.type === 'item_lost_damaged_report') {
    router.push({ 
      name: 'Notifications',
      query: { 
        showLostItem: notification.item_id || notification.item?.id || notification.id,
        notificationId: notification.id
      }
    })
    return
  }
  
  // For all other notifications, navigate to Notifications page to show details modal
  router.push({ 
    name: 'Notifications',
    query: { 
      showDetails: notification.id
    }
  })
}

// Fallback user data for when API is loading
const fallbackUser = ref({
  name: 'Loading...',
  avatar: '/src/assets/avatar.svg'
})

const isSidebarOpen = ref(false)
const isProfileDropdownOpen = ref(false)
const isNotificationsOpen = ref(false)
const isMessagesOpen = ref(false)
const isLogoutModalOpen = ref(false)
const isDarkMode = ref(localStorage.getItem('darkMode') === 'true')
const submenuStates = ref({})
const isMobile = ref(window.innerWidth < 1024)
const avatarError = ref(false)
const unreadMessagesCount = ref(0)
const supplyRequestMessages = ref([])
const loadingMessages = ref(false)
const showMessageDetailModal = ref(false)
const selectedMessage = ref(null)
const messageTab = ref('all') // 'all' or 'unread'
const searchQuery = ref('')
const showQrCodeModal = ref(false)
const selectedQrCodeUrl = ref(null)
const conversationMessages = ref([])
const selectedSupplyRequest = ref(null)
const selectedSender = ref(null) // For grouped conversations
const loadingConversationMessages = ref(false)
const newConversationMessage = ref('')
const highlightedMessageId = ref(null) // Track which message to highlight
const messageScrollContainer = ref(null) // Reference to scroll container
const messagePollingInterval = ref(null) // Polling interval for messages
const messageRealtimeListener = ref(null) // Real-time listener reference

const currentTime = ref(new Date())
const currentDate = ref(new Date())

// Base navigation items (available to all authenticated users)
const baseNavigation = [
  { name: 'Dashboard', path: '/dashboard', icon: 'dashboard' },
  { name: 'Inventory', path: '/inventory', icon: 'inventory' },
  { name: 'Supply Requests', path: '/supply-requests', icon: 'shopping_cart' },
  { name: 'Analytics', path: '/analytics', icon: 'analytics' },
  { name: 'Profile', path: '/profile', icon: 'person' }
]

// Admin-only base navigation (without Supply Requests for regular users)
const adminBaseNavigation = [
  { name: 'Dashboard', path: '/dashboard', icon: 'dashboard' },
  { name: 'Inventory', path: '/inventory', icon: 'inventory' },
  { name: 'Analytics', path: '/analytics', icon: 'analytics' },
  { name: 'Profile', path: '/profile', icon: 'person' }
]

// User role only navigation (simplified)
const userOnlyNavigation = [
  { name: 'Supply Requests', path: '/supply-requests', icon: 'shopping_cart' },
  { name: 'Profile', path: '/profile', icon: 'person' }
]

// Admin-only navigation items
const adminNavigation = [
  { name: 'Supply Requests from Supply Account', path: '/admin/supply-requests', icon: 'inventory_2' },
  { name: 'Categories', path: '/categories', icon: 'category' },
  { name: 'Units/Sections', path: '/locations', icon: 'location_on' },
  { name: 'Admins', path: '/admin', icon: 'people' },
  { name: 'Personnel Management', path: '/personnel-management', icon: 'badge' },
  { name: 'Transactions', path: '/transactions', icon: 'swap_horiz' },
  { name: 'Activity Log', path: '/activity-log', icon: 'history' },
  {
    name: 'History',
    icon: 'folder',
    hasSubmenu: true,
    id: 'history',
    submenu: [
      { name: 'Deleted Items', path: '/history/deleted-items', icon: 'delete' },
      { name: 'Maintenance Records', path: '/history/maintenance-records', icon: 'build' }
    ]
  }
]

// Supply role navigation items
const supplyNavigation = [
  { name: 'Supply Requests Management', path: '/supply-requests-management', icon: 'inventory_2' },
  { name: 'Unit/Section Analytics', path: '/unit-section-analytics', icon: 'analytics' }
]

// Check if user is supply role
const isSupply = computed(() => {
  if (userLoading.value || !user.value) return false
  const role = (user.value.role || '').toLowerCase()
  return role === 'supply'
})

// Check if user is regular user role (not admin or supply)
const isRegularUser = computed(() => {
  if (userLoading.value || !user.value) return false
  const role = (user.value.role || '').toLowerCase()
  return role === 'user' && !isAdmin() && !isSupply.value
})

// Computed navigation that filters based on user role
const navigation = computed(() => {
  // If regular user, show only simplified navigation
  if (isRegularUser.value) {
    return [...userOnlyNavigation]
  }
  
  // For supply role, show filtered navigation (Inventory, Supply Requests Management, Profile)
  if (isSupply.value) {
    const supplyNav = [
      { name: 'Inventory', path: '/inventory', icon: 'inventory' },
      { name: 'Profile', path: '/profile', icon: 'person' }
    ]
    supplyNav.push(...supplyNavigation)
    return supplyNav
  }
  
  // For admin role, show admin-specific base navigation + admin navigation
  if (isAdmin()) {
    const nav = [...adminBaseNavigation]
    nav.push(...adminNavigation)
    return nav
  }
  
  // For other roles, show base navigation
  const nav = [...baseNavigation]
  return nav
})

// Computed property for user display name
const userDisplayName = computed(() => {
  if (userLoading.value) {
    return 'Loading...'
  }
  if (userError.value) {
    return 'User'
  }
  return getUserDisplayName()
})

// Computed property for user avatar
const userAvatar = computed(() => {
  if (avatarError.value) {
    return '/src/assets/avatar.svg'
  }
  if (user.value && user.value.image) {
    return user.value.image
  }
  return '/src/assets/avatar.svg'
})

// Computed property for sidebar classes
const sidebarClasses = computed(() => {
  return {
    'fixed inset-y-0 left-0 w-64 xs:w-72 sm:w-80 lg:w-64 bg-white dark:bg-white shadow-lg z-20 flex flex-col transform transition-transform duration-300 ease-in-out': true,
    '-translate-x-full': !isSidebarOpen.value && isMobile.value,
    'translate-x-0': isSidebarOpen.value || !isMobile.value
  }
})

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

const toggleProfileDropdown = () => {
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value
}

const toggleNotifications = () => {
  isNotificationsOpen.value = !isNotificationsOpen.value
  if (isMessagesOpen.value) {
    isMessagesOpen.value = false
  }
}

const toggleMessages = () => {
  isMessagesOpen.value = !isMessagesOpen.value
  if (isNotificationsOpen.value) {
    isNotificationsOpen.value = false
  }
  if (isMessagesOpen.value) {
    fetchAllSupplyRequestMessages()
  }
}

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value
  localStorage.setItem('darkMode', isDarkMode.value)
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

const toggleSubmenu = (itemId) => {
  submenuStates.value[itemId] = !submenuStates.value[itemId]
}

const handleImageError = () => {
  avatarError.value = true
}

const isSubmenuOpen = (itemId) => {
  return !!submenuStates.value[itemId]
}

// Helper function to check if a route is active (exact match, normalized)
const isActiveRoute = (path) => {
  if (!path) return false
  // Normalize paths by removing trailing slashes and ensuring exact match
  const currentPath = route.path.replace(/\/$/, '') || '/'
  const itemPath = path.replace(/\/$/, '') || '/'
  return currentPath === itemPath
}

// Helper function to check if any submenu item is active
const isSubmenuActive = (submenu) => {
  if (!submenu || !Array.isArray(submenu)) return false
  return submenu.some(subItem => isActiveRoute(subItem.path))
}

const closeProfileDropdown = (e) => {
  if (isProfileDropdownOpen.value) {
    const dropdown = document.querySelector('.profile-dropdown')
    const button = document.querySelector('.profile-button')
    if (dropdown && !dropdown.contains(e.target) && button && !button.contains(e.target)) {
      isProfileDropdownOpen.value = false
    }
  }
}

const closeNotifications = (e) => {
  if (isNotificationsOpen.value) {
    const dropdown = document.querySelector('.notifications-dropdown')
    const button = document.querySelector('.notifications-button')
    if (dropdown && !dropdown.contains(e.target) && button && !button.contains(e.target)) {
      isNotificationsOpen.value = false
    }
  }
}

const closeMessages = (e) => {
  if (isMessagesOpen.value) {
    const dropdown = document.querySelector('.messages-dropdown')
    const button = document.querySelector('.messages-button')
    if (dropdown && !dropdown.contains(e.target) && button && !button.contains(e.target)) {
      isMessagesOpen.value = false
    }
  }
}

// Fetch unread messages count
const fetchUnreadMessagesCount = async () => {
  try {
    const response = await axiosClient.get('/supply-requests/messages/unread-count')
    if (response.data.success) {
      unreadMessagesCount.value = response.data.count || 0
    }
  } catch (err) {
    console.error('Error fetching unread messages count:', err)
    unreadMessagesCount.value = 0
  }
}

// Fetch all supply request messages
const fetchAllSupplyRequestMessages = async (silent = false) => {
  if (!silent) {
    loadingMessages.value = true
  }
  try {
    const response = await axiosClient.get('/supply-requests/messages/all')
    if (response.data.success) {
      const previousCount = supplyRequestMessages.value.length
      supplyRequestMessages.value = response.data.data || []
      
      // Update unread count: count unique senders with unread messages (grouped conversations)
      const unreadSenders = new Set()
      supplyRequestMessages.value.forEach(msg => {
        if (!msg.is_read && msg.user?.id) {
          unreadSenders.add(msg.user.id)
        }
      })
      const previousUnreadCount = unreadMessagesCount.value
      unreadMessagesCount.value = unreadSenders.size
      
      // Show notification if new messages arrived and dropdown is not open
      if (!silent && supplyRequestMessages.value.length > previousCount && !isMessagesOpen.value) {
        const newMessagesCount = supplyRequestMessages.value.length - previousCount
        if (newMessagesCount > 0) {
          console.log(`📨 ${newMessagesCount} new message(s) received`)
        }
      }
      
      // Show notification if unread count increased
      if (!silent && unreadMessagesCount.value > previousUnreadCount && !isMessagesOpen.value) {
        console.log(`🔔 Unread messages: ${previousUnreadCount} → ${unreadMessagesCount.value}`)
      }
    }
  } catch (err) {
    console.error('Error fetching supply request messages:', err)
    supplyRequestMessages.value = []
  } finally {
    if (!silent) {
      loadingMessages.value = false
    }
  }
}

// Filtered messages based on tab and search query, grouped by sender
const filteredMessages = computed(() => {
  let filtered = supplyRequestMessages.value
  
  // Filter by tab
  if (messageTab.value === 'unread') {
    filtered = filtered.filter(msg => !msg.is_read)
  }
  
  // Filter by search query
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(msg => 
      msg.user.name.toLowerCase().includes(query) ||
      msg.message.toLowerCase().includes(query) ||
      msg.supply_request?.item_name?.toLowerCase().includes(query)
    )
  }
  
  // Group messages by sender (user.id)
  const groupedBySender = {}
  filtered.forEach(msg => {
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
    let requestId = null
    
    // Try to get request ID from multiple sources
    if (msg?.supply_request?.id) {
      requestId = msg.supply_request.id
    } else if (selectedSupplyRequest.value?.id) {
      requestId = selectedSupplyRequest.value.id
    } else if (conversationMessages.value.length > 0) {
      // Try to find request ID from conversation messages
      const messageWithRequest = conversationMessages.value.find(m => m.supply_request?.id)
      if (messageWithRequest) {
        requestId = messageWithRequest.supply_request.id
      }
    }
    
    // Extract request number from URL as fallback
    if (!requestId) {
      const requestNumberMatch = url.match(/approval_receipt_(SR-[^_]+)/)
      if (requestNumberMatch) {
        const requestNumber = requestNumberMatch[1]
        // Try to find the request ID from conversation messages using request number
        const messageWithNumber = conversationMessages.value.find(m => 
          m.supply_request?.request_number === requestNumber || 
          m.message?.includes(requestNumber)
        )
        if (messageWithNumber?.supply_request?.id) {
          requestId = messageWithNumber.supply_request.id
        }
      }
    }
    
    // Use API endpoint if we have request ID (avoids CORS issues)
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
      // Last resort: show error message
      alert('Unable to download PDF. Please download it from the Supply Requests page.')
      return
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
    alert('Failed to download PDF. Please try downloading from the Supply Requests page.')
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
  
  // If message contains receipt URL, remove all receipt metadata
  if (extractReceiptUrl(message)) {
    // Get only the first line (the approval message)
    const lines = message.split('\n')
    const firstLine = lines[0]?.trim()
    
    // If first line contains the approval message, return it
    if (firstLine && firstLine.toLowerCase().includes('approved')) {
      return firstLine
    }
    
    // Fallback: remove everything from common receipt patterns
    const patterns = [
      /\n\nReceipt Number:.*$/s,
      /\nReceipt Number:.*$/s,
      /\n\nApproval Date:.*$/s,
      /\nApproval Date:.*$/s,
      /\n\nApproved By:.*$/s,
      /\nApproved By:.*$/s,
      /\n\nItem Name:.*$/s,
      /\nItem Name:.*$/s,
      /\n\nItem Details:.*$/s,
      /\nItem Details:.*$/s,
      /\n\nReceipt Link:.*$/s,
      /\nReceipt Link:.*$/s,
      /\n\nQR Code:.*$/s,
      /\nQR Code:.*$/s
    ]
    
    let cleaned = message
    patterns.forEach(pattern => {
      cleaned = cleaned.replace(pattern, '').trim()
    })
    
    // Return first line only
    return cleaned.split('\n')[0]?.trim() || cleaned
  }
  
  return message
}

// Get current user ID
const getCurrentUserId = () => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    return user.id || null
  } catch {
    return null
  }
}

// Fetch messages for a supply request
const fetchConversationMessages = async (supplyRequestId) => {
  loadingConversationMessages.value = true
  try {
    const response = await axiosClient.get(`/supply-requests/${supplyRequestId}/messages`)
    if (response.data.success) {
      conversationMessages.value = response.data.data || []
    }
  } catch (err) {
    console.error('Error fetching conversation messages:', err)
    // Handle unauthorized access
    if (err.response?.status === 403) {
      alert('You do not have permission to view messages for this request.')
      closeMessageDetailModal()
      conversationMessages.value = []
    } else {
      conversationMessages.value = []
    }
  } finally {
    loadingConversationMessages.value = false
  }
}

// Mark messages as read
const markConversationMessagesAsRead = async (supplyRequestId) => {
  try {
    await axiosClient.post(`/supply-requests/${supplyRequestId}/messages/mark-read`)
    // Update local state
    supplyRequestMessages.value.forEach(msg => {
      if (msg.supply_request?.id === supplyRequestId) {
        msg.is_read = true
      }
    })
    // Recalculate unread count: count unique senders with unread messages
    const unreadSenders = new Set()
    supplyRequestMessages.value.forEach(msg => {
      if (!msg.is_read && msg.user?.id) {
        unreadSenders.add(msg.user.id)
      }
    })
    unreadMessagesCount.value = unreadSenders.size
  } catch (err) {
    console.error('Error marking messages as read:', err)
    // Silently fail for unauthorized access (user might not have permission)
    if (err.response?.status !== 403) {
      // Log other errors but don't show alert for permission issues
    }
  }
}

// Send a message in conversation
const sendConversationMessage = async () => {
  if (!newConversationMessage.value.trim() || !selectedSupplyRequest.value) {
    if (!selectedSupplyRequest.value) {
      alert('Unable to send message: No supply request selected')
    }
    return
  }
  
  const messageText = newConversationMessage.value.trim()
  newConversationMessage.value = '' // Clear input immediately for better UX
  
  try {
    const response = await axiosClient.post(`/supply-requests/${selectedSupplyRequest.value.id}/messages`, {
      message: messageText
    })
    
    if (response.data.success) {
      // Add message to conversation immediately
      conversationMessages.value.push(response.data.data)
      // Refresh unread counts and messages list in background (non-blocking)
      fetchAllSupplyRequestMessages().catch(err => {
        console.error('Error refreshing messages list:', err)
      })
    }
  } catch (err) {
    console.error('Error sending message:', err)
    // Restore message text on error
    newConversationMessage.value = messageText
    const errorMessage = err.response?.status === 403 
      ? 'You do not have permission to send messages for this request.'
      : (err.response?.data?.message || err.message)
    alert('Failed to send message: ' + errorMessage)
  }
}

// Mark message as read and open chat conversation
const handleMessageClick = async (message) => {
  if (!message.sender || !message.groupedMessages) return
  
  // Store the clicked message ID to highlight it
  highlightedMessageId.value = message.id
  
  // Set selected sender
  selectedSender.value = message.sender
  
  // Get all messages from this sender (grouped messages)
  const senderMessages = message.groupedMessages || []
  
  // Find the most recent supply request
  const mostRecentMessage = senderMessages.sort((a, b) => {
    return new Date(b.created_at) - new Date(a.created_at)
  })[0]
  selectedSupplyRequest.value = mostRecentMessage?.supply_request || null
  
  // Initialize with grouped messages immediately (for instant display)
  conversationMessages.value = senderMessages
    .map(msg => ({
      ...msg,
      user: msg.user || message.sender,
      supply_request: msg.supply_request
    }))
    .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
  
  // Set loading state
  loadingConversationMessages.value = true
  
  // Open chat modal immediately
  showMessageDetailModal.value = true
  isMessagesOpen.value = false
  
  // Collect all unique supply request IDs from this sender's messages
  const supplyRequestIds = [...new Set(senderMessages.map(msg => msg.supply_request?.id).filter(Boolean))]
  
  // Fetch all messages from all supply requests in parallel to get complete data
  Promise.all(
    supplyRequestIds.map(requestId =>
      axiosClient.get(`/supply-requests/${requestId}/messages`)
        .then(response => {
          if (response.data.success) {
            // Filter to only include messages from this sender
            return response.data.data.filter(msg => msg.user?.id === message.sender.id)
          }
          return []
        })
        .catch(err => {
          console.error(`Error fetching messages for supply request ${requestId}:`, err)
          return []
        })
    )
  ).then(allMessages => {
    // Flatten and sort all messages by timestamp
    conversationMessages.value = allMessages
      .flat()
      .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    loadingConversationMessages.value = false
    
    // Scroll to highlighted message after a short delay to ensure DOM is updated
    setTimeout(() => {
      scrollToHighlightedMessage()
    }, 100)
  }).catch(err => {
    console.error('Error loading conversation messages:', err)
    loadingConversationMessages.value = false
  })
  
  // Mark all messages from this sender as read in background (non-blocking)
  Promise.all(
    supplyRequestIds.map(requestId => markConversationMessagesAsRead(requestId))
  ).catch(err => {
    console.error('Error marking messages as read:', err)
  })
}

// Scroll to highlighted message
const scrollToHighlightedMessage = () => {
  if (!highlightedMessageId.value) return
  
  // Use nextTick to ensure DOM is updated
  nextTick(() => {
    // Find the message element
    const messageElement = document.querySelector(`[data-message-id="${highlightedMessageId.value}"]`)
    if (messageElement) {
      // Scroll to the message with smooth behavior
      messageElement.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center',
        inline: 'nearest'
      })
      
      // Remove highlight after 3 seconds
      setTimeout(() => {
        highlightedMessageId.value = null
      }, 3000)
    }
  })
}

// Close message detail modal
const closeMessageDetailModal = () => {
  showMessageDetailModal.value = false
  selectedMessage.value = null
  selectedSupplyRequest.value = null
  selectedSender.value = null
  conversationMessages.value = []
  newConversationMessage.value = ''
  highlightedMessageId.value = null
}

// Close sidebar when clicking outside on mobile
const closeSidebarOnOutsideClick = (e) => {
  if (isSidebarOpen.value && isMobile.value) {
    const sidebar = document.querySelector('.sidebar')
    const menuButton = document.querySelector('.menu-button')
    if (sidebar && !sidebar.contains(e.target) && menuButton && !menuButton.contains(e.target)) {
      isSidebarOpen.value = false
    }
  }
}

// Handle window resize
const handleResize = () => {
  isMobile.value = window.innerWidth < 1024
  if (!isMobile.value) {
    isSidebarOpen.value = true
  } else {
    isSidebarOpen.value = false
  }
}

// Update time every second
const startClock = () => {
  setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
}

// Format date in Philippine format
const formatPhDate = (date) => {
  return date.toLocaleDateString('en-PH', {
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  })
}

// Format time with AM/PM in Philippines timezone
const formatTime = (date) => {
  return date.toLocaleTimeString('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
    timeZone: 'Asia/Manila'
  })
}

// Handle logout
const handleLogout = () => {
  isProfileDropdownOpen.value = false
  isLogoutModalOpen.value = true
}

// Handle logout confirmation
const confirmLogout = async () => {
  try {
    await logout()
    isLogoutModalOpen.value = false
  } catch (error) {
    console.error('Logout error:', error)
    // Even if there's an error, try to redirect to login
    await router.push('/login')
    isLogoutModalOpen.value = false
  }
}

// Handle logout cancellation
const cancelLogout = () => {
  isLogoutModalOpen.value = false
}

// Auto-open submenus when their items are active
watch(() => route.path, (newPath) => {
  // Check if any submenu item is active and auto-open the parent
  const adminNav = adminNavigation.find(item => item.hasSubmenu)
  if (adminNav && adminNav.submenu) {
    const isActive = adminNav.submenu.some(subItem => isActiveRoute(subItem.path))
    if (isActive && !isSubmenuOpen(adminNav.id)) {
      submenuStates.value[adminNav.id] = true
    }
  }
}, { immediate: true })

// Setup global real-time notification listener
const setupGlobalNotificationListener = () => {
  if (!window.Echo) {
    console.warn('⚠️ Laravel Echo not available. Will retry...')
    setTimeout(setupGlobalNotificationListener, 2000)
    return
  }

  try {
    const channel = window.Echo.channel('notifications')
    
    channel.listen('.NotificationCreated', (data) => {
      console.log('📬 Global: New notification received:', data)
      
      if (data.notification) {
        // Determine title based on notification type if not provided
        let notificationTitle = data.notification.title
        if (!notificationTitle) {
          switch(data.notification.type) {
            case 'supply_request_created':
              notificationTitle = 'New Supply Request'
              break
            case 'supply_request_approved':
            case 'supply_request_admin_approved':
              notificationTitle = 'Receipt Available'
              break
            case 'supply_request_rejected':
            case 'supply_request_admin_rejected':
              notificationTitle = 'Request Rejected'
              break
            case 'supply_request_ready_pickup':
            case 'supply_request_ready_for_pickup':
              notificationTitle = 'Ready for Pickup'
              break
            case 'borrow_request':
              notificationTitle = 'Borrow Request'
              break
            default:
              notificationTitle = 'Low Stock Alert'
          }
        }
        
        const newNotification = {
          id: data.notification.id,
          type: data.notification.type || 'low_stock',
          title: notificationTitle,
          message: data.notification.message,
          user: data.notification.item?.unit || 'System',
          role: 'System',
          timestamp: data.notification.timestamp || data.notification.created_at,
          date: data.notification.date,
          time: data.notification.time,
          action: notificationTitle,
          isRead: data.notification.isRead ?? false,
          priority: data.notification.priority || 'high',
          item: data.notification.item,
          borrowRequest: data.notification.borrowRequest || null,
          selected: false
        }
        
        // Check if notification already exists (prevent duplicates)
        const exists = notifications.value.some(n => n.id === newNotification.id)
        if (!exists) {
          // Add to beginning of notifications array (for dropdown)
          notifications.value.unshift(newNotification)
          
          // Keep only latest 10 in dropdown
          if (notifications.value.length > 10) {
            notifications.value = notifications.value.slice(0, 10)
          }
          
          // Update unread count
          if (data.unread_count !== undefined) {
            unreadCount.value = data.unread_count
          } else if (!newNotification.isRead) {
            unreadCount.value++
          }
          
          // Show popup for NEW borrow requests (even on other pages)
          if (newNotification.type === 'borrow_request' && newNotification.borrowRequest?.status === 'pending') {
            globalPopupNotification.value = newNotification
            showGlobalPopup.value = true
            
            // Auto-hide popup after 10 seconds
            setTimeout(() => {
              showGlobalPopup.value = false
            }, 10000)
          }
          
          // For NEW borrow requests, immediately check and show banner
          if (newNotification.type === 'borrow_request' && newNotification.borrowRequest?.status === 'pending') {
            console.log('🆕 New borrow request received, checking banner immediately')
            // Force immediate check - check multiple times to ensure it shows
            checkAndShowPendingRequestsBanner()
            setTimeout(() => {
              checkAndShowPendingRequestsBanner()
            }, 10)
            setTimeout(() => {
              checkAndShowPendingRequestsBanner()
            }, 50)
            setTimeout(() => {
              checkAndShowPendingRequestsBanner()
            }, 100)
            setTimeout(() => {
              checkAndShowPendingRequestsBanner()
            }, 300)
          }
          
          // Show banner for NEW supply requests
          if (newNotification.type === 'supply_request_created') {
            console.log('🆕 New supply request received, showing banner')
            const bannerMessage = newNotification.message || 'New supply request received'
            showSimpleBanner(bannerMessage, 'success', true, 6000)
          }
          
        } else {
          // Notification already exists, update it in place to trigger reactivity
          const existingIndex = notifications.value.findIndex(n => n.id === newNotification.id)
          if (existingIndex !== -1) {
            // Update the existing notification to trigger reactivity
            notifications.value[existingIndex] = { ...notifications.value[existingIndex], ...newNotification }
          }
          
          // Update unread count
          if (data.unread_count !== undefined) {
            unreadCount.value = data.unread_count
          }
        }
        
        // ALWAYS check and show banner for pending requests after any notification update
        // Check multiple times to ensure it catches all updates and always shows if there are requests
        checkAndShowPendingRequestsBanner()
        setTimeout(() => {
          checkAndShowPendingRequestsBanner()
        }, 10)
        setTimeout(() => {
          checkAndShowPendingRequestsBanner()
        }, 50)
        setTimeout(() => {
          checkAndShowPendingRequestsBanner()
        }, 100)
        setTimeout(() => {
          checkAndShowPendingRequestsBanner()
        }, 300)
      }
    })
    
    console.log('✅ Global real-time notifications listener active')
  } catch (error) {
    console.error('❌ Error setting up global notifications listener:', error)
    // Retry after delay
    setTimeout(setupGlobalNotificationListener, 3000)
  }
}

// Setup supply request approval listener (for all authenticated users)
const setupSupplyRequestApprovalListener = () => {
  if (!window.Echo) {
    console.warn('⚠️ Laravel Echo not available for supply request approval. Will retry...')
    setTimeout(setupSupplyRequestApprovalListener, 2000)
    return
  }

  try {
    // Listen on user-specific private channel
    if (user.value && user.value.id) {
      const userChannel = window.Echo.private(`user.${user.value.id}`)
      userChannel.listen('.SupplyRequestApproved', (data) => {
        console.log('✅ Supply request approved event received:', data)
        
        // Show professional banner notification
        const itemName = data.item_name || 'your request'
        const quantity = data.quantity || 1
        const bannerMessage = `✓ Request Approved: ${itemName} (${quantity} ${quantity === 1 ? 'unit' : 'units'}) - Check messages for receipt details`
        showSimpleBanner(bannerMessage, 'success', true, 8000)
        
        // Refresh unread messages count and messages list
        fetchUnreadMessagesCount()
        if (isMessagesOpen.value) {
          fetchAllSupplyRequestMessages()
        }
      })
      console.log('✅ Supply request approval listener active for user:', user.value.id)
    }
    
    // Also listen on general notifications channel
    const notificationsChannel = window.Echo.channel('notifications')
    notificationsChannel.listen('.SupplyRequestApproved', (data) => {
      console.log('✅ Supply request approved event received (general channel):', data)
      
      // Only show if this is for the current user
      if (user.value && user.value.id && data.supply_request_id) {
        // Show professional banner notification
        const itemName = data.item_name || 'your request'
        const quantity = data.quantity || 1
        const bannerMessage = `✓ Request Approved: ${itemName} (${quantity} ${quantity === 1 ? 'unit' : 'units'}) - Check messages for receipt details`
        showSimpleBanner(bannerMessage, 'success', true, 8000)
        
        // Refresh unread messages count and messages list
        fetchUnreadMessagesCount()
        if (isMessagesOpen.value) {
          fetchAllSupplyRequestMessages()
        }
      }
    })
  } catch (error) {
    console.error('❌ Error setting up supply request approval listener:', error)
    // Retry after delay
    setTimeout(setupSupplyRequestApprovalListener, 3000)
  }
}

// Setup real-time listener for supply request messages
const setupMessageRealtimeListener = () => {
  try {
    if (!window.Echo || !user.value) {
      console.log('⏳ Echo not ready or user not loaded, retrying...')
      setTimeout(setupMessageRealtimeListener, 2000)
      return
    }
    
    // Clean up existing listener if any
    if (messageRealtimeListener.value) {
      try {
        window.Echo.leave('supply-request-messages')
      } catch (e) {
        console.log('No existing listener to clean up')
      }
    }
    
    // Listen on supply request messages channel
    const messagesChannel = window.Echo.channel('supply-request-messages')
    
    // Listen for new messages
    messagesChannel.listen('.SupplyRequestMessageCreated', (data) => {
      console.log('📨 New message received via WebSocket:', data)
      
      // Refresh messages list silently (without loading indicator)
      fetchAllSupplyRequestMessages(true)
      
      // Update unread count
      fetchUnreadMessagesCount()
      
      // If message modal is open for this conversation, add message to it
      if (showMessageDetailModal.value && selectedSender.value && 
          data.message && data.message.user_id === selectedSender.value.id) {
        // Add new message to conversation
        conversationMessages.value.push(data.message)
        // Scroll to bottom after a short delay
        setTimeout(() => {
          if (messageScrollContainer.value) {
            messageScrollContainer.value.scrollTop = messageScrollContainer.value.scrollHeight
          }
        }, 100)
      }
    })
    
    // Listen for message read status updates
    messagesChannel.listen('.SupplyRequestMessageRead', (data) => {
      console.log('✅ Message marked as read:', data)
      // Refresh messages to update read status
      fetchAllSupplyRequestMessages(true)
      fetchUnreadMessagesCount()
    })
    
    messageRealtimeListener.value = messagesChannel
    console.log('✅ Real-time message listener active')
  } catch (error) {
    console.error('❌ Error setting up message real-time listener:', error)
    // Retry after delay
    setTimeout(setupMessageRealtimeListener, 3000)
  }
}

onMounted(async () => {
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark')
  }
  document.addEventListener('click', closeProfileDropdown)
  document.addEventListener('click', closeNotifications)
  document.addEventListener('click', closeMessages)
  document.addEventListener('click', closeSidebarOnOutsideClick)
  window.addEventListener('resize', handleResize)
  handleResize() // Initialize on mount
  startClock()
  
  // Fetch notifications for Admin only
  if (isAdmin()) {
    // Fetch unread count from database immediately (for badge)
    await fetchUnreadCount()
    
    // Fetch notifications when component mounts
    await fetchNotifications(5) // Fetch only 5 for the dropdown
    
    // Check for pending borrow requests and show banner after fetching notifications
    // Check multiple times to ensure banner shows if there are requests
    checkAndShowPendingRequestsBanner()
    setTimeout(() => {
      checkAndShowPendingRequestsBanner()
    }, 100) // Small delay to ensure notifications are loaded
    setTimeout(() => {
      checkAndShowPendingRequestsBanner()
    }, 200) // Additional check
    setTimeout(() => {
      checkAndShowPendingRequestsBanner()
    }, 500) // Final check to ensure banner is shown
  }
  
  // Setup supply request approval listener (for all authenticated users)
  setTimeout(() => {
    setupSupplyRequestApprovalListener()
  }, 500)
  
  // Setup global real-time listener for notifications (Admin only)
  if (isAdmin()) {
    setTimeout(() => {
      setupGlobalNotificationListener()
      // Also use the composable's listener as backup
      setupRealtimeListener()
    }, 500) // Wait a bit for Echo to initialize
    
    // Set up periodic banner check (every 2 seconds)
    bannerCheckInterval = setInterval(() => {
      checkAndShowPendingRequestsBanner()
    }, 2000) // Check every 2 seconds to ensure banner always shows
    
    // Refresh notifications periodically to ensure we have latest data
    const notificationRefreshInterval = setInterval(async () => {
      console.log('🔄 Periodic notification refresh...')
      await fetchNotifications(5)
      // Check banner multiple times after refresh
      checkAndShowPendingRequestsBanner()
      setTimeout(() => {
        checkAndShowPendingRequestsBanner()
      }, 50)
      setTimeout(() => {
        checkAndShowPendingRequestsBanner()
      }, 100)
      setTimeout(() => {
        checkAndShowPendingRequestsBanner()
      }, 300)
    }, 10000) // Refresh every 10 seconds
    
    // Refresh unread count periodically (every 30 seconds) to keep badge updated
    // Note: Real-time updates will handle most cases, but this is a backup
    const unreadCountInterval = setInterval(async () => {
      await refreshUnreadCount()
      await fetchUnreadMessagesCount()
    }, 30000) // 30 seconds
    
    // Store intervals for cleanup
    window.notificationRefreshInterval = notificationRefreshInterval
    window.unreadCountInterval = unreadCountInterval
  }
  
  // Setup real-time listener for messages (all authenticated users)
  setTimeout(() => {
    setupMessageRealtimeListener()
  }, 1000) // Wait for Echo to initialize
  
  // Fetch unread count on mount (for badge)
  fetchUnreadMessagesCount()
  
  // Setup polling as fallback for messages (less frequent)
  // This ensures messages update even if WebSocket fails
  messagePollingInterval.value = setInterval(async () => {
    if (document.visibilityState === 'visible') {
      // Always update unread count (lightweight)
      await fetchUnreadMessagesCount()
      
      // Only fetch full messages if dropdown is open
      if (isMessagesOpen.value) {
        await fetchAllSupplyRequestMessages(true) // Silent refresh
      }
    }
  }, 15000) // Poll every 15 seconds (reduced from 5 seconds)
  
  // Store intervals for cleanup (only if admin)
  if (isAdmin()) {
    window.bannerCheckInterval = bannerCheckInterval
  }
  
  // Auto-open submenus on mount if their items are active
  const adminNav = adminNavigation.find(item => item.hasSubmenu)
  if (adminNav && adminNav.submenu) {
    const isActive = adminNav.submenu.some(subItem => isActiveRoute(subItem.path))
    if (isActive) {
      submenuStates.value[adminNav.id] = true
    }
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeProfileDropdown)
  document.removeEventListener('click', closeNotifications)
  document.removeEventListener('click', closeMessages)
  document.removeEventListener('click', closeSidebarOnOutsideClick)
  window.removeEventListener('resize', handleResize)
  
  // Clear all intervals
  if (bannerCheckInterval) {
    clearInterval(bannerCheckInterval)
    bannerCheckInterval = null
  }
  if (window.bannerCheckInterval) {
    clearInterval(window.bannerCheckInterval)
    delete window.bannerCheckInterval
  }
  if (window.notificationRefreshInterval) {
    clearInterval(window.notificationRefreshInterval)
    delete window.notificationRefreshInterval
  }
  if (window.unreadCountInterval) {
    clearInterval(window.unreadCountInterval)
    delete window.unreadCountInterval
  }
  
  // Clean up message polling interval
  if (messagePollingInterval.value) {
    clearInterval(messagePollingInterval.value)
    messagePollingInterval.value = null
  }
  
  // Clean up message real-time listener
  if (messageRealtimeListener.value && window.Echo) {
    try {
      window.Echo.leave('supply-request-messages')
      messageRealtimeListener.value = null
    } catch (e) {
      console.error('Error cleaning up message listener:', e)
    }
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Sidebar -->
    <aside :class="sidebarClasses" class="sidebar transition-transform duration-300 ease-in-out">
      <!-- Logo -->
      <div class="flex items-center px-4 xs:px-5 py-4 xs:py-5 h-16 xs:h-20 border-b border-gray-100">
        <div class="h-8 w-8 xs:h-10 xs:w-10 rounded-full border-2 border-green-600 flex items-center justify-center flex-shrink-0">
          <img src="../assets/logo.png" alt="IrrigTrack" class="h-5 w-5 xs:h-7 xs:w-7 object-contain" />
        </div>
        <span class="ml-2 xs:ml-3 text-base xs:text-lg font-semibold text-gray-800 dark:text-gray-900" style="font-weight: 600;">IrrigTrack</span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-3 xs:px-4 py-4 overflow-y-auto">
        <template v-for="item in navigation" :key="item.name">
          <!-- Regular nav items -->
          <router-link
            v-if="!item.hasSubmenu"
            :to="item.path"
            :class="[
              'flex items-center px-3 xs:px-4 sm:px-5 py-2.5 xs:py-3 text-sm xs:text-base rounded-md mb-3 xs:mb-4 transition-colors',
              isActiveRoute(item.path)
                ? 'bg-green-50 text-green-600 dark:bg-green-50 dark:text-green-600 border border-green-200'
                : 'text-gray-600 dark:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-50'
            ]"
            style="font-weight: 500;"
            @click="isMobile ? isSidebarOpen = false : null"
          >
            <span 
              class="material-icons-outlined mr-2 xs:mr-3 text-lg xs:text-xl flex-shrink-0"
              :class="isActiveRoute(item.path) ? 'text-green-600' : 'text-gray-600'"
            >
              {{ item.icon }}
            </span>
            <span class="truncate">{{ item.name }}</span>
          </router-link>

          <!-- Submenu items -->
          <div v-else class="mb-3 xs:mb-4">
            <button
              @click="toggleSubmenu(item.id)"
              :class="[
                'w-full flex items-center justify-between px-3 xs:px-4 sm:px-5 py-2.5 xs:py-3 text-sm xs:text-base rounded-md transition-colors',
                isSubmenuActive(item.submenu) || isSubmenuOpen(item.id)
                  ? 'bg-green-50 text-green-600 dark:bg-green-50 dark:text-green-600 border border-green-200'
                  : 'text-gray-600 dark:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-50'
              ]"
              style="font-weight: 500;"
            >
              <div class="flex items-center min-w-0 flex-1">
                <span 
                  class="material-icons-outlined mr-2 xs:mr-3 text-lg xs:text-xl flex-shrink-0"
                  :class="isSubmenuActive(item.submenu) || isSubmenuOpen(item.id) ? 'text-green-600' : 'text-gray-600'"
                >
                  {{ item.icon }}
                </span>
                <span class="truncate">{{ item.name }}</span>
              </div>
              <span 
                class="material-icons-outlined text-base xs:text-lg transition-transform duration-200 flex-shrink-0"
                :class="[
                  isSubmenuActive(item.submenu) || isSubmenuOpen(item.id) ? 'text-green-600' : 'text-gray-600',
                  { 'transform rotate-180': isSubmenuOpen(item.id) }
                ]"
              >
                expand_more
              </span>
            </button>
            
            <transition
              enter-active-class="transition duration-200 ease-out"
              enter-from-class="transform scale-95 opacity-0"
              enter-to-class="transform scale-100 opacity-100"
              leave-active-class="transition duration-200 ease-in"
              leave-from-class="transform scale-100 opacity-100"
              leave-to-class="transform scale-95 opacity-0"
            >
              <div v-show="isSubmenuOpen(item.id)" class="ml-8 xs:ml-10 sm:ml-12 space-y-2 mt-2">
                <router-link
                  v-for="subItem in item.submenu"
                  :key="subItem.name"
                  :to="subItem.path"
                  :class="[
                    'flex items-center px-3 xs:px-4 sm:px-5 py-2.5 xs:py-3 text-sm xs:text-base rounded-md transition-colors',
                    isActiveRoute(subItem.path)
                      ? 'bg-green-50 text-green-600 dark:bg-green-50 dark:text-green-600 border border-green-200'
                      : 'text-gray-600 dark:text-gray-600 hover:bg-gray-50 dark:hover:bg-gray-50'
                  ]"
                  style="font-weight: 500;"
                  @click="isMobile ? isSidebarOpen = false : null"
                >
                  <span 
                    class="material-icons-outlined mr-2 xs:mr-3 text-lg xs:text-xl flex-shrink-0"
                    :class="isActiveRoute(subItem.path) ? 'text-green-600' : 'text-gray-600'"
                  >
                    {{ subItem.icon }}
                  </span>
                  <span class="truncate">{{ subItem.name }}</span>
                </router-link>
              </div>
            </transition>
          </div>
        </template>
      </nav>

      <!-- User Info at Bottom -->
      <div class="mt-auto border-t border-gray-200 dark:border-gray-200 p-3 xs:p-4 sm:p-5 bg-gray-50 dark:bg-gray-50">
        <div class="text-gray-800 dark:text-gray-800 font-semibold mb-1 text-sm xs:text-base truncate">{{ userDisplayName }}</div>
        <div class="text-gray-600 dark:text-gray-600 text-xs xs:text-sm">
          <div class="truncate">{{ formatPhDate(currentDate) }}</div>
          <div class="truncate">at {{ formatTime(currentTime) }}</div>
        </div>
      </div>
    </aside>

    <!-- Overlay -->
    <transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-300 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div 
        v-if="isSidebarOpen && isMobile" 
        class="fixed inset-0 bg-black bg-opacity-50 z-10 lg:hidden"
        @click="toggleSidebar"
      ></div>
    </transition>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen flex flex-col transition-all duration-300">
      <!-- Top Bar -->
      <header class="h-14 sm:h-16 bg-white dark:bg-gray-800 shadow-sm flex items-center justify-between px-2 xs:px-3 sm:px-4 lg:px-6 sticky top-0 z-30">
        <div class="flex items-center gap-1.5 xs:gap-2 sm:gap-4 min-w-0 flex-1">
          <!-- Mobile Menu Button -->
          <button 
            class="lg:hidden p-1.5 sm:p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 menu-button focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 flex-shrink-0"
            @click="toggleSidebar"
            aria-label="Toggle menu"
          >
            <span class="material-icons-outlined text-xl sm:text-2xl text-green-600 dark:text-green-400">
              {{ isSidebarOpen ? 'close' : 'menu' }}
            </span>
          </button>
          <h1 class="text-base xs:text-lg sm:text-xl lg:text-2xl font-semibold text-gray-800 dark:text-green-400 truncate">{{ route.name }}</h1>
        </div>

        <!-- Right Side Icons -->
        <div class="flex items-center space-x-1.5 xs:space-x-2 sm:space-x-3 md:space-x-4 flex-shrink-0">
          <!-- Dark Mode Toggle -->
          <button 
            class="p-1.5 xs:p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-100 transition-colors"
            @click="toggleDarkMode"
            aria-label="Toggle dark mode"
          >
            <span class="material-icons-outlined text-lg xs:text-xl sm:text-2xl text-gray-800 dark:text-white" v-if="!isDarkMode">dark_mode</span>
            <span class="material-icons-outlined text-lg xs:text-xl sm:text-2xl text-gray-800 dark:text-white" v-else>light_mode</span>
          </button>

          <!-- Messages -->
          <div class="relative">
            <button 
              class="p-1.5 xs:p-2 rounded-full hover:bg-gray-100 border-2 border-green-200 messages-button relative transition-all"
              @click="toggleMessages"
              aria-label="Messages"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 xs:h-6 xs:w-6 sm:h-7 sm:w-7 text-gray-800 dark:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
              <!-- Dynamic message badge -->
              <span 
                v-if="unreadMessagesCount > 0" 
                class="absolute -top-1 -right-1 h-4 w-4 xs:h-5 xs:w-5 sm:h-6 sm:w-6 bg-red-500 rounded-full text-[9px] xs:text-[10px] sm:text-xs text-white flex items-center justify-center font-bold shadow-lg ring-2 ring-white"
              >
                {{ unreadMessagesCount > 99 ? '99+' : unreadMessagesCount }}
              </span>
            </button>

            <!-- Messages Dropdown -->
            <div 
              v-if="isMessagesOpen"
              class="absolute right-0 mt-2 w-[calc(100vw-2rem)] max-w-[90vw] xs:w-80 sm:w-96 bg-white dark:bg-[#1F2937] rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 z-50 messages-dropdown overflow-hidden flex flex-col max-h-[600px]"
            >
              <!-- Simplified Header -->
              <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center flex-shrink-0 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-[#1E3A8A] dark:to-[#312E81]">
                <div class="flex items-center gap-2">
                  <span class="material-icons-outlined text-blue-600 dark:text-blue-300">chat_bubble</span>
                  <h3 class="text-base font-bold text-gray-900 dark:text-white">Messages</h3>
                </div>
                <button 
                  @click="fetchAllSupplyRequestMessages"
                  class="p-1.5 hover:bg-white/50 dark:hover:bg-gray-700 rounded-lg transition-colors"
                  title="Refresh"
                  :disabled="loadingMessages"
                >
                  <span class="material-icons-outlined text-sm text-gray-700 dark:text-gray-300" :class="{ 'animate-spin': loadingMessages }">refresh</span>
                </button>
              </div>

              <!-- Search and Tabs Combined -->
              <div class="px-3 py-2 bg-gray-50 dark:bg-[#111827] border-b border-gray-200 dark:border-gray-700 flex-shrink-0 space-y-2">
                <!-- Search Bar -->
                <div class="relative">
                  <span class="material-icons-outlined absolute left-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">search</span>
                  <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search messages..."
                    class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-[#1F2937] border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
                
                <!-- Simplified Tabs -->
                <div class="flex gap-1">
                  <button
                    @click="messageTab = 'all'"
                    :class="[
                      'flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition-all',
                      messageTab === 'all' 
                        ? 'bg-blue-600 text-white shadow-sm' 
                        : 'bg-white dark:bg-[#1F2937] text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                  >
                    All
                  </button>
                  <button
                    @click="messageTab = 'unread'"
                    :class="[
                      'flex-1 px-3 py-1.5 text-xs font-medium rounded-md transition-all relative',
                      messageTab === 'unread' 
                        ? 'bg-blue-600 text-white shadow-sm' 
                        : 'bg-white dark:bg-[#1F2937] text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                  >
                    Unread
                    <span 
                      v-if="unreadMessagesCount > 0 && messageTab !== 'unread'" 
                      class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center"
                    >
                      {{ unreadMessagesCount > 9 ? '9+' : unreadMessagesCount }}
                    </span>
                  </button>
                </div>
              </div>
              
              <!-- Messages List -->
              <div class="flex-1 overflow-y-auto bg-white dark:bg-[#1F2937]">
                <div v-if="loadingMessages" class="px-5 py-12 text-center">
                  <div class="animate-spin rounded-full h-10 w-10 border-3 border-blue-600 border-t-transparent mx-auto mb-3"></div>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Loading messages...</p>
                </div>
                
                <div v-else-if="filteredMessages.length === 0" class="px-5 py-12 text-center">
                  <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <span class="material-icons-outlined text-gray-400 text-3xl">chat_bubble_outline</span>
                  </div>
                  <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No messages</p>
                  <p class="text-xs text-gray-500 dark:text-gray-500">{{ searchQuery ? 'No messages match your search' : 'You don\'t have any messages yet' }}</p>
                </div>
                
                <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                  <button
                    v-for="message in filteredMessages"
                    :key="`${message.sender?.id || message.user?.id}-${message.id}`"
                    @click="handleMessageClick(message)"
                    class="group w-full px-4 py-3 text-left hover:bg-blue-50 dark:hover:bg-gray-800/50 transition-all duration-150 relative flex items-start gap-3 cursor-pointer"
                    :class="{ 
                      'bg-blue-50 dark:bg-blue-900/20 border-l-3 border-l-blue-500': message.hasUnread,
                      'hover:border-l-2 hover:border-l-blue-300 dark:hover:border-l-blue-600': !message.hasUnread
                    }"
                  >
                    <!-- Avatar -->
                    <div class="flex-shrink-0 relative">
                      <div class="h-11 w-11 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center overflow-hidden shadow-sm"
                        :class="message.hasUnread ? 'ring-2 ring-blue-400 ring-offset-1 ring-offset-white dark:ring-offset-[#1F2937]' : ''">
                        <span v-if="!(message.sender?.avatar || message.user?.avatar)" class="text-white font-semibold text-base">
                          {{ (message.sender?.name || message.user?.name || 'U').charAt(0).toUpperCase() }}
                        </span>
                        <img v-else :src="message.sender?.avatar || message.user?.avatar" :alt="message.sender?.name || message.user?.name" class="w-full h-full object-cover" />
                      </div>
                      <!-- Unread indicator -->
                      <div v-if="message.hasUnread" class="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-blue-500 border-2 border-white dark:border-[#1F2937]"></div>
                    </div>
                    
                    <!-- Message content -->
                    <div class="flex-1 min-w-0">
                      <!-- Header: Name, Role, Time -->
                      <div class="flex items-start justify-between gap-2 mb-1.5">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                          <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ message.sender?.name || message.user?.name || 'Unknown' }}
                          </p>
                          <span v-if="message.sender?.role || message.user?.role" 
                            class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 flex-shrink-0 uppercase tracking-wide">
                            {{ (message.sender?.role || message.user?.role) }}
                          </span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0 whitespace-nowrap"
                          :class="{ 'text-blue-600 dark:text-blue-400 font-semibold': message.hasUnread }">
                          {{ formatRelativeTime(message.created_at) }}
                        </span>
                      </div>
                      
                      <!-- Item Badge -->
                      <div v-if="message.supply_request?.item_name" class="mb-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-md bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                          <span class="material-icons-outlined text-xs">inventory_2</span>
                          {{ message.supply_request.item_name }}
                        </span>
                      </div>
                      
                      <!-- Message preview -->
                      <p class="text-sm text-gray-700 dark:text-gray-300 line-clamp-2 leading-relaxed mb-1.5">
                        {{ cleanMessageText(message.message) }}
                      </p>
                      
                      <!-- Status Badge -->
                      <div v-if="message.supply_request?.status" class="flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full"
                          :class="{
                            'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300': message.supply_request.status === 'fulfilled',
                            'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300': message.supply_request.status === 'approved' || message.supply_request.status === 'ready_for_pickup',
                            'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300': message.supply_request.status === 'rejected',
                            'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300': !['fulfilled', 'approved', 'ready_for_pickup', 'rejected'].includes(message.supply_request.status)
                          }">
                          <span class="w-1.5 h-1.5 rounded-full"
                            :class="{
                              'bg-green-500': message.supply_request.status === 'fulfilled',
                              'bg-yellow-500': message.supply_request.status === 'approved' || message.supply_request.status === 'ready_for_pickup',
                              'bg-red-500': message.supply_request.status === 'rejected',
                              'bg-gray-400': !['fulfilled', 'approved', 'ready_for_pickup', 'rejected'].includes(message.supply_request.status)
                            }"></span>
                          {{ message.supply_request.status.replace('_', ' ') }}
                        </span>
                      </div>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Simplified Footer -->
              <div class="bg-gray-50 dark:bg-[#111827] px-4 py-2.5 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                <router-link 
                  :to="isSupply ? '/supply-requests-management?view=messages' : '/supply-requests?view=messages'"
                  class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium flex items-center justify-center gap-1.5 transition-colors"
                  @click="isMessagesOpen = false"
                >
                  <span>View All Messages</span>
                  <span class="material-icons-outlined text-sm">arrow_forward</span>
                </router-link>
              </div>
            </div>
          </div>

          <!-- Notifications - All users -->
          <div v-if="user" class="relative">
            <button 
              class="p-1.5 xs:p-2 rounded-full hover:bg-gray-100 border-2 border-green-200 notifications-button relative transition-all"
              @click="toggleNotifications"
              aria-label="Notifications"
            >
              <span class="material-icons-outlined text-lg xs:text-xl sm:text-2xl text-gray-800 dark:text-white">notifications</span>
              <!-- Dynamic notification badge from database -->
              <span 
                v-if="unreadCount > 0" 
                class="absolute -top-1 -right-1 h-4 w-4 xs:h-5 xs:w-5 sm:h-6 sm:w-6 bg-red-500 rounded-full text-[9px] xs:text-[10px] sm:text-xs text-white flex items-center justify-center font-bold shadow-lg ring-2 ring-white"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </button>

            <!-- Enhanced Notifications Dropdown -->
            <div 
              v-if="isNotificationsOpen"
              class="absolute right-0 mt-2 w-[calc(100vw-2rem)] max-w-[90vw] xs:w-72 sm:w-96 bg-white dark:bg-[#2D3748] rounded-xl shadow-2xl border border-gray-200 dark:border-[#4A5568] z-50 notifications-dropdown overflow-hidden"
            >
              <!-- Header -->
              <div class="bg-gradient-to-r from-green-600 to-green-700 dark:bg-[#2D6A4F] px-5 py-3 border-b border-green-800 dark:border-[#388E3C] flex justify-between items-center">
                <h3 class="text-base font-bold text-white">
                  {{ isAdmin() ? 'Recent Notifications' : (isSupply.value ? 'Supply Request Notifications' : 'My Notifications') }}
                </h3>
                <button 
                  @click="handleRefreshNotifications"
                  class="p-1.5 hover:bg-white/20 rounded-lg transition-colors"
                  title="Refresh notifications"
                >
                  <span class="material-icons-outlined text-sm text-white">refresh</span>
                </button>
              </div>
              
              <!-- Notification Items -->
              <div class="max-h-[500px] overflow-y-auto bg-gray-50 dark:bg-[#2D3748]">
                <div v-if="notifications.length === 0" class="px-5 py-8 text-center">
                  <span class="material-icons-outlined text-4xl text-gray-300 dark:text-gray-500 mb-3 block">notifications_off</span>
                  <p class="text-sm text-gray-500 dark:text-[#A0AEC0] font-medium">No notifications</p>
                </div>
                
                <div
                  v-for="notification in notifications.slice(0, 5)"
                  :key="notification.id"
                  @click="handleNotificationClick(notification)"
                  class="px-5 py-3.5 hover:bg-white dark:hover:bg-[#4A5568] cursor-pointer transition-colors border-b border-gray-100 dark:border-[#4A5568] last:border-b-0 bg-white dark:bg-[#2D3748]"
                  :class="{ 'bg-blue-50/30 dark:bg-blue-900/20': !notification.isRead }"
                >
                  <div class="flex items-start gap-3">
                    <!-- Unread Indicator (Red Dot) -->
                    <div v-if="!notification.isRead" class="flex-shrink-0 w-2 h-2 mt-2 rounded-full bg-red-500"></div>
                    <div v-else class="flex-shrink-0 w-2 h-2 mt-2"></div>
                    
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                      <!-- Title and Time -->
                      <div class="flex items-start justify-between gap-2 mb-1.5">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-[#E2E8F0] leading-tight">{{ notification.title }}</h4>
                        <span class="text-xs font-medium text-gray-500 dark:text-[#A0AEC0] flex-shrink-0 whitespace-nowrap">{{ notification.time }}</span>
                      </div>
                      
                      <!-- Message -->
                      <p class="text-xs text-gray-600 dark:text-[#E2E8F0] leading-relaxed mb-2">
                        {{ notification.message }}
                      </p>
                      
                      <!-- Source/Item Info -->
                      <p class="text-xs text-gray-500 dark:text-[#A0AEC0]">
                        <span v-if="notification.item && notification.item.unit">
                          {{ notification.item.unit }}
                        </span>
                        <span v-else-if="notification.user">
                          {{ notification.user }}
                        </span>
                        <span v-else>
                          System
                        </span>
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- View All Link -->
              <div class="bg-white dark:bg-[#2D3748] px-5 py-3 border-t border-gray-200 dark:border-[#4A5568]">
                <router-link 
                  to="/notifications"
                  class="text-sm text-green-600 dark:text-[#48BB78] hover:text-green-700 dark:hover:text-[#4CAF50] font-semibold flex items-center justify-center gap-2 transition-colors"
                  @click="isNotificationsOpen = false"
                >
                  <span>View all notifications</span>
                  <span class="material-icons-outlined text-base">arrow_forward</span>
                </router-link>
              </div>
            </div>
          </div>

          <!-- Profile -->
          <div class="relative">
            <button 
              class="flex items-center justify-center profile-button border-2 border-green-200 rounded-full p-0.5 hover:bg-gray-100 transition-all"
              @click="toggleProfileDropdown"
              aria-label="Profile menu"
            >
              <div class="h-7 w-7 xs:h-8 xs:w-8 sm:h-9 sm:w-9 rounded-full bg-green-600 flex items-center justify-center text-white text-xs xs:text-sm sm:text-base font-semibold">
                {{ userDisplayName.charAt(0).toUpperCase() }}
              </div>
            </button>

            <!-- Profile Dropdown -->
            <div 
              v-if="isProfileDropdownOpen"
              class="absolute right-0 mt-2 w-48 xs:w-56 bg-white dark:bg-[#2D3748] rounded-lg shadow-lg py-2 z-50 profile-dropdown border border-gray-100 dark:border-[#4A5568]"
            >
              <router-link 
                to="/profile"
                class="flex items-center px-4 py-2.5 text-sm text-gray-800 dark:text-[#E2E8F0] hover:bg-gray-50 dark:hover:bg-[#4A5568] transition-colors"
                @click="isProfileDropdownOpen = false"
              >
                <span class="material-icons-outlined mr-3 text-lg text-gray-800 dark:text-[#E2E8F0]">person</span>
                <span class="font-medium">Profile</span>
              </router-link>
              <router-link
                to="/activity-log"
                class="flex items-center px-4 py-2.5 text-sm text-gray-800 dark:text-[#E2E8F0] hover:bg-gray-50 dark:hover:bg-[#4A5568] transition-colors"
                @click="isProfileDropdownOpen = false"
              >
                <span class="material-icons-outlined mr-3 text-lg text-gray-800 dark:text-[#E2E8F0]">history</span>
                <span class="font-medium">Activity Log</span>
              </router-link>
              <router-link
                to="/history/deleted-items"
                class="flex items-center px-4 py-2.5 text-sm text-gray-800 dark:text-[#E2E8F0] hover:bg-gray-50 dark:hover:bg-[#4A5568] transition-colors"
                @click="isProfileDropdownOpen = false"
              >
                <span class="material-icons-outlined mr-3 text-lg text-gray-800 dark:text-[#E2E8F0]">folder</span>
                <span class="font-medium">History</span>
              </router-link>
              <button
                class="w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-800 dark:text-[#E2E8F0] hover:bg-gray-50 dark:hover:bg-[#4A5568] transition-colors"
                @click="handleLogout"
              >
                <span class="material-icons-outlined mr-3 text-lg text-gray-800 dark:text-[#E2E8F0]">logout</span>
                <span class="font-medium">Log out</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-2 xs:p-3 sm:p-4 lg:p-6 dark:bg-gray-900">
        <RouterView />
      </main>

      <!-- Footer -->
      <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-2 px-2 xs:px-3 sm:px-4">
        <div class="flex flex-col xs:flex-row xs:items-center xs:justify-between text-xs xs:text-sm text-gray-600 dark:text-gray-400 gap-1.5 xs:gap-2">
          <div class="flex items-center space-x-1.5 xs:space-x-2 truncate min-w-0">
            <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm xs:text-base flex-shrink-0">person</span>
            <span class="font-medium text-green-600 dark:text-green-400 truncate">{{ userDisplayName }}</span>
          </div>
          <div class="flex items-center space-x-2 xs:space-x-3 sm:space-x-4 flex-wrap">
            <div class="whitespace-nowrap">{{ formatPhDate(currentDate) }}</div>
            <div class="whitespace-nowrap">{{ formatTime(currentTime) }}</div>
          </div>
        </div>
      </footer>

    </div>

    <!-- Logout Confirmation Modal -->
    <LogoutModal 
      :is-open="isLogoutModalOpen"
      :is-loading="userLoading"
      @confirm="confirmLogout"
      @cancel="cancelLogout"
    />

    <!-- Global Popup Notification for Borrow Requests (shows on all pages) -->
    <div
      v-if="showGlobalPopup && globalPopupNotification"
      class="fixed top-4 right-4 z-50 max-w-md w-full bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-green-500 animate-slide-in"
      @click.stop
    >
      <div class="p-6">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <span class="material-icons-outlined text-green-600 dark:text-green-400 text-2xl">shopping_cart</span>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ globalPopupNotification.title || 'New Borrow Request' }}</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">{{ formatRelativeTime(globalPopupNotification.timestamp) }}</p>
            </div>
          </div>
          <button
            @click="showGlobalPopup = false"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
        
        <p class="text-gray-700 dark:text-gray-300 mb-4">{{ globalPopupNotification.message }}</p>
        
        <div v-if="globalPopupNotification.borrowRequest" class="mb-4 space-y-2 text-sm">
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-base text-blue-400">person</span>
            <span class="text-gray-700 dark:text-gray-300"><strong>Requested by:</strong> {{ globalPopupNotification.borrowRequest.borrowed_by }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-base text-purple-400">location_on</span>
            <span class="text-gray-700 dark:text-gray-300"><strong>Location:</strong> {{ globalPopupNotification.borrowRequest.location }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-base text-green-400">inventory_2</span>
            <span class="text-gray-700 dark:text-gray-300"><strong>Quantity:</strong> {{ globalPopupNotification.borrowRequest.quantity }} unit(s)</span>
          </div>
        </div>
        
        <div class="flex items-center gap-3">
          <button
            @click="handleGlobalApprove(globalPopupNotification)"
            :disabled="globalPopupNotification.borrowRequest?.status !== 'pending'"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
          >
            <span class="material-icons-outlined text-lg">check_circle</span>
            <span>Approve</span>
          </button>
          <button
            @click="handleGlobalReject(globalPopupNotification)"
            :disabled="globalPopupNotification.borrowRequest?.status !== 'pending'"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
          >
            <span class="material-icons-outlined text-lg">cancel</span>
            <span>Decline</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Confirmation Modal -->
    <Transition name="modal-fade">
      <div
        v-if="showRejectModal && notificationToReject"
        class="fixed inset-0 z-[10001] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click.self="cancelReject"
      >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 w-full max-w-md animate-slide-up">
          <!-- Header -->
          <div class="bg-gradient-to-r from-red-600 to-red-700 dark:from-red-700 dark:to-red-800 px-6 py-4 rounded-t-xl border-b border-red-800 dark:border-red-900">
            <div class="flex items-center gap-3">
              <span class="material-icons-outlined text-2xl text-white">warning</span>
              <h3 class="text-lg font-bold text-white">Confirm Rejection</h3>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6 space-y-4">
            <p class="text-gray-700 dark:text-gray-300 text-base">
              Are you sure you want to reject this borrow request?
            </p>
            
            <!-- Request Details -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 space-y-3 border border-gray-200 dark:border-gray-600">
              <div class="flex items-center gap-2">
                <span class="material-icons-outlined text-base text-blue-500">inventory_2</span>
                <span class="text-gray-700 dark:text-gray-300">
                  <strong>Item:</strong> {{ notificationToReject.item?.name || notificationToReject.item?.unit || 'N/A' }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="material-icons-outlined text-base text-purple-500">person</span>
                <span class="text-gray-700 dark:text-gray-300">
                  <strong>Requested by:</strong> {{ notificationToReject.borrowRequest?.borrowed_by || 'N/A' }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="material-icons-outlined text-base text-green-500">numbers</span>
                <span class="text-gray-700 dark:text-gray-300">
                  <strong>Quantity:</strong> {{ notificationToReject.borrowRequest?.quantity || 0 }} unit(s)
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="material-icons-outlined text-base text-orange-500">location_on</span>
                <span class="text-gray-700 dark:text-gray-300">
                  <strong>Location:</strong> {{ notificationToReject.borrowRequest?.location || 'N/A' }}
                </span>
              </div>
            </div>
            
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">
              This action cannot be undone. The request will be marked as rejected.
            </p>
          </div>
          
          <!-- Actions -->
          <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/30 rounded-b-xl border-t border-gray-200 dark:border-gray-600 flex items-center gap-3">
            <button
              @click="cancelReject"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-lg">close</span>
              <span>Cancel</span>
            </button>
            <button
              @click="confirmReject"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
            >
              <span class="material-icons-outlined text-lg">cancel</span>
              <span>Reject Request</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Professional Success/Error Banner -->
    <Transition name="banner-slide">
      <div
        v-if="showBanner"
        class="fixed top-4 right-4 z-[10000] max-w-md w-full sm:w-auto"
      >
        <div
          class="flex items-start gap-3 px-5 py-4 rounded-xl shadow-2xl border animate-banner-in backdrop-blur-sm"
          :class="{
            'bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/40 dark:to-emerald-900/40 border-green-400/50 text-green-900 dark:text-green-100': bannerType === 'success',
            'bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/40 dark:to-rose-900/40 border-red-400/50 text-red-900 dark:text-red-100': bannerType === 'error'
          }"
        >
          <div
            class="flex-shrink-0 mt-0.5"
            :class="{
              'text-green-600 dark:text-green-400': bannerType === 'success',
              'text-red-600 dark:text-red-400': bannerType === 'error'
            }"
          >
            <span class="material-icons-outlined text-2xl">
              {{ bannerType === 'success' ? 'check_circle' : 'error' }}
            </span>
          </div>
          <div class="flex-1 min-w-0">
            <p 
              class="text-sm font-semibold leading-relaxed break-words"
              :class="{
                'text-green-900 dark:text-green-100': bannerType === 'success',
                'text-red-900 dark:text-red-100': bannerType === 'error'
              }"
            >
              {{ bannerMessage }}
            </p>
          </div>
          <button
            @click="closeBanner"
            class="flex-shrink-0 mt-0.5 transition-colors rounded-full p-1 hover:bg-black/5 dark:hover:bg-white/10"
            :class="{
              'text-green-700 hover:text-green-900 dark:text-green-300 dark:hover:text-green-100': bannerType === 'success',
              'text-red-700 hover:text-red-900 dark:text-red-300 dark:hover:text-red-100': bannerType === 'error'
            }"
          >
            <span class="material-icons-outlined text-lg">close</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>

  <!-- Message Detail Modal - Chat Interface -->
  <div v-if="showMessageDetailModal && selectedSender" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="closeMessageDetailModal">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl h-[85vh] flex flex-col overflow-hidden">
      <!-- Header -->
      <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between flex-shrink-0 bg-gray-50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center overflow-hidden">
            <span v-if="!selectedSender.avatar" class="text-white font-semibold">
              {{ selectedSender.name?.charAt(0).toUpperCase() || 'U' }}
            </span>
            <img v-else :src="selectedSender.avatar" :alt="selectedSender.name" class="w-full h-full object-cover" />
          </div>
          <div>
            <h3 class="text-base font-semibold text-gray-900">{{ selectedSender.name || 'Unknown User' }}</h3>
            <p class="text-xs text-gray-500">{{ selectedSender.role || 'user' }} · {{ conversationMessages.length }} message{{ conversationMessages.length !== 1 ? 's' : '' }}</p>
          </div>
        </div>
        <button @click="closeMessageDetailModal" class="p-2 hover:bg-gray-200 rounded-full transition-colors">
          <span class="material-icons-outlined text-gray-600 text-lg">close</span>
        </button>
      </div>

      <!-- Messages List - Chat Bubbles -->
      <div 
        ref="messageScrollContainer"
        class="flex-1 overflow-y-auto p-4 space-y-2 bg-gray-50"
      >
        <div v-if="loadingConversationMessages" class="flex justify-center py-8">
          <span class="material-icons-outlined animate-spin text-2xl text-emerald-600">refresh</span>
        </div>
        <div v-else-if="conversationMessages.length === 0" class="text-center py-8 text-gray-500">
          <span class="material-icons-outlined text-4xl mb-2 block">message</span>
          <p>No messages yet</p>
        </div>
        <template v-else>
          <div 
            v-for="msg in conversationMessages" 
            :key="msg.id"
            :data-message-id="msg.id"
            class="flex gap-2 transition-all duration-300"
            :class="{ 
              'justify-end': msg.user?.id === getCurrentUserId(),
              'ring-4 ring-blue-400 ring-opacity-75 bg-blue-50 dark:bg-blue-900/30 rounded-lg p-2 -m-2 animate-pulse': highlightedMessageId === msg.id
            }"
          >
            <!-- Avatar (only for received messages) -->
            <div v-if="msg.user?.id !== getCurrentUserId()" class="flex-shrink-0">
              <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                <span class="material-icons-outlined text-green-600 text-sm">person</span>
              </div>
            </div>
            
            <!-- Message Bubble -->
            <div 
              class="max-w-[70%] rounded-2xl px-4 py-2"
              :class="msg.user?.id === getCurrentUserId() 
                ? 'bg-blue-500 text-white' 
                : 'bg-white text-gray-900 shadow-sm'"
            >
              <!-- Sender name (only for received messages) -->
              <div v-if="msg.user?.id !== getCurrentUserId()" class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold" :class="msg.user?.id === getCurrentUserId() ? 'text-white' : 'text-gray-900'">
                  {{ msg.user?.name || 'Unknown' }}
                </span>
                <span class="text-xs" :class="msg.user?.id === getCurrentUserId() ? 'text-blue-100' : 'text-gray-500'">
                  {{ msg.user?.role || 'user' }}
                </span>
              </div>
              
              <!-- Supply Request Context (if message is from a different request) -->
              <div v-if="msg.supply_request?.item_name" class="mb-1">
                <span class="text-xs font-medium opacity-75" :class="msg.user?.id === getCurrentUserId() ? 'text-blue-100' : 'text-gray-600'">
                  {{ msg.supply_request.item_name }}
                </span>
              </div>
              
              <!-- Message Text -->
              <p class="text-sm whitespace-pre-wrap break-words">{{ cleanMessageText(msg.message) }}</p>
              
              <!-- Embedded Approval Receipt Card (inside message bubble) -->
              <div v-if="extractReceiptUrl(msg.message)" class="mt-3 pt-3 border-t" :class="msg.user?.id === getCurrentUserId() ? 'border-blue-400' : 'border-gray-200'">
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
                      <!-- Approved By -->
                      <div v-if="extractApproverName(msg.message)" class="flex items-start justify-between py-1.5 border-b border-green-200">
                        <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide flex-shrink-0">Approved By</span>
                        <span class="text-xs text-gray-900 break-words text-right ml-4">{{ extractApproverName(msg.message) }}</span>
                      </div>
                      
                      <!-- Item Details Section -->
                      <div v-if="extractItemDetails(msg.message)" class="pt-2">
                        <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Item Details</div>
                        <div class="space-y-1.5">
                          <div v-for="(value, key) in extractItemDetails(msg.message)" :key="key" class="flex items-start justify-between">
                            <span class="text-xs text-gray-600 flex-shrink-0">{{ key }}:</span>
                            <span class="text-xs font-medium text-gray-900 break-words text-right ml-4">{{ value }}</span>
                          </div>
                        </div>
                      </div>
                      
                      <!-- QR Code Section -->
                      <div v-if="extractQrCodeUrl(msg.message)" class="pt-3 border-t border-green-200">
                        <div class="flex items-center justify-center gap-2 mb-2">
                          <span class="material-icons-outlined text-blue-600 text-sm">qr_code</span>
                          <div class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Scan QR Code</div>
                        </div>
                        <div class="flex justify-center">
                          <div 
                            class="relative cursor-pointer group"
                            @click="selectedQrCodeUrl = extractQrCodeUrl(msg.message); showQrCodeModal = true"
                          >
                            <img 
                              :src="extractQrCodeUrl(msg.message)" 
                              alt="Receipt QR Code" 
                              class="w-32 h-32 border-2 border-green-300 rounded-lg p-1 bg-white transition-all duration-300 hover:border-green-500 hover:shadow-lg hover:scale-110"
                            />
                          </div>
                        </div>
                        <p class="text-xs text-center text-gray-600 mt-2">Scan to verify receipt details • Click to enlarge</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Receipt Footer -->
                  <div class="bg-green-50 px-3 py-2.5 border-t border-green-200">
                    <div class="flex items-center justify-between gap-2">
                      <div class="flex items-start gap-2 flex-1 min-w-0">
                        <span class="material-icons-outlined text-green-600 text-sm flex-shrink-0 mt-0.5">info</span>
                        <p class="text-xs text-green-700 break-words">Present this receipt when picking up items</p>
                      </div>
                      <button
                        v-if="extractReceiptUrl(msg.message)"
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
              
              <!-- Timestamp -->
              <div class="flex items-center justify-end gap-1 mt-1">
                <span class="text-xs opacity-70" :class="msg.user?.id === getCurrentUserId() ? 'text-blue-100' : 'text-gray-500'">
                  {{ formatMessageDateTime(msg.created_at) }}
                </span>
              </div>
            </div>
            
            <!-- Avatar (only for sent messages) -->
            <div v-if="msg.user?.id === getCurrentUserId()" class="flex-shrink-0">
              <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="material-icons-outlined text-blue-600 text-sm">person</span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Message Input -->
      <div class="px-4 py-3 border-t border-gray-200 bg-white flex-shrink-0">
        <div class="flex items-end gap-2">
          <textarea
            v-model="newConversationMessage"
            @keydown.enter.exact.prevent="sendConversationMessage"
            rows="1"
            placeholder="Aa"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none max-h-32"
          ></textarea>
          <button
            @click="sendConversationMessage"
            :disabled="!newConversationMessage.trim()"
            class="p-2 bg-emerald-600 text-white rounded-full hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <span class="material-icons-outlined text-sm">send</span>
          </button>
        </div>
      </div>
    </div>
  </div>

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
          class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons+Outlined');

/* Popup animation */
@keyframes slide-in {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.animate-slide-in {
  animation: slide-in 0.3s ease-out;
}

/* Banner animations */
@keyframes banner-in {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.animate-banner-in {
  animation: banner-in 0.3s ease-out;
}

.banner-slide-enter-active {
  transition: all 0.3s ease-out;
}

.banner-slide-leave-active {
  transition: all 0.3s ease-in;
}

.banner-slide-enter-from {
  transform: translateX(100%);
  opacity: 0;
}

/* Modal animations */
@keyframes slide-up {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-slide-up {
  animation: slide-up 0.3s ease-out;
}

.modal-fade-enter-active {
  transition: all 0.3s ease-out;
}

.modal-fade-leave-active {
  transition: all 0.3s ease-in;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-from .animate-slide-up,
.modal-fade-leave-to .animate-slide-up {
  transform: translateY(20px);
  opacity: 0;
}

.banner-slide-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

:root {
  color-scheme: light dark;
}

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

/* Responsive sidebar transitions */
.sidebar {
  transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
}

/* Smooth transitions for all interactive elements */
button, a, .router-link-active {
  transition: all 0.2s ease-in-out;
}

/* Improved mobile experience */
@media (max-width: 1023px) {
  .sidebar {
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
  }
  
  .menu-button {
    transform-origin: center;
    transition: transform 0.3s ease;
  }
  
  .menu-button:active {
    transform: scale(0.9);
  }
}

/* Extra small devices */
@media (max-width: 374px) {
  .sidebar {
    width: calc(100vw - 1rem);
    max-width: 280px;
  }
}

/* Touch-friendly targets */
@media (max-width: 640px) {
  button, a {
    min-height: 44px;
    min-width: 44px;
  }
  
  .menu-button {
    min-width: 40px;
    min-height: 40px;
  }
}

/* Improved focus states for accessibility */
button:focus, a:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.5);
}

/* Hamburger menu animation */
.menu-button .material-icons-outlined {
  transition: transform 0.3s ease;
}

.menu-button:hover .material-icons-outlined {
  transform: scale(1.1);
}
</style> 