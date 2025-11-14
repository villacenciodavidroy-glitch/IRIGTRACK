<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useAuth from '../composables/useAuth'
import useNotifications from '../composables/useNotifications'
import LogoutModal from '../components/LogoutModal.vue'

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
  
  // For borrow request notifications, navigate to Notifications page with ID
  if (notification.type === 'borrow_request') {
    router.push({ 
      name: 'Notifications',
      query: { highlight: notification.id }
    })
  } else {
    // For other notifications, navigate to Analytics page
    router.push({ name: 'Analytics' })
  }
}

// Fallback user data for when API is loading
const fallbackUser = ref({
  name: 'Loading...',
  avatar: '/src/assets/avatar.svg'
})

const isSidebarOpen = ref(false)
const isProfileDropdownOpen = ref(false)
const isNotificationsOpen = ref(false)
const isLogoutModalOpen = ref(false)
const isDarkMode = ref(localStorage.getItem('darkMode') === 'true')
const submenuStates = ref({})
const isMobile = ref(window.innerWidth < 1024)
const avatarError = ref(false)

const currentTime = ref(new Date())
const currentDate = ref(new Date())

// Base navigation items (available to all authenticated users)
const baseNavigation = [
  { name: 'Dashboard', path: '/dashboard', icon: 'dashboard' },
  { name: 'Inventory', path: '/inventory', icon: 'inventory' },
  { name: 'Analytics', path: '/analytics', icon: 'analytics' },
  { name: 'Profile', path: '/profile', icon: 'person' }
]

// Admin-only navigation items
const adminNavigation = [
  { name: 'Categories', path: '/categories', icon: 'category' },
  { name: 'Units/Sections', path: '/locations', icon: 'location_on' },
  { name: 'Admins', path: '/admin', icon: 'people' },
  { name: 'Transactions', path: '/transactions', icon: 'swap_horiz' },
  { name: 'Activity Log', path: '/activity-log', icon: 'history' },
  {
    name: 'History',
    icon: 'folder',
    hasSubmenu: true,
    id: 'history',
    submenu: [
      { name: 'Deleted Items', path: '/history/deleted-items', icon: 'delete' }
    ]
  }
]

// Computed navigation that filters based on user role
const navigation = computed(() => {
  const nav = [...baseNavigation]
  if (isAdmin()) {
    nav.push(...adminNavigation)
  }
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
        const newNotification = {
          id: data.notification.id,
          type: data.notification.type || 'low_stock',
          title: data.notification.title || 'Low Stock Alert',
          message: data.notification.message,
          user: data.notification.item?.unit || 'System',
          role: 'System',
          timestamp: data.notification.timestamp || data.notification.created_at,
          date: data.notification.date,
          time: data.notification.time,
          action: data.notification.title || (data.notification.type === 'borrow_request' ? 'Borrow Request' : 'Low Stock Alert'),
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

onMounted(async () => {
  if (isDarkMode.value) {
    document.documentElement.classList.add('dark')
  }
  document.addEventListener('click', closeProfileDropdown)
  document.addEventListener('click', closeNotifications)
  document.addEventListener('click', closeSidebarOnOutsideClick)
  window.addEventListener('resize', handleResize)
  handleResize() // Initialize on mount
  startClock()
  
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
  
  // Setup global real-time listener for notifications (works on all pages)
  setTimeout(() => {
    setupGlobalNotificationListener()
    // Also use the composable's listener as backup
    setupRealtimeListener()
  }, 500) // Wait a bit for Echo to initialize
  
  // Set up periodic banner check (every 2 seconds) to ensure banner always shows if there are requests
  // More frequent checks ensure the banner is always visible when there are pending requests
  bannerCheckInterval = setInterval(() => {
    checkAndShowPendingRequestsBanner()
  }, 2000) // Check every 2 seconds to ensure banner always shows
  
  // Also refresh notifications periodically to ensure we have latest data
  const notificationRefreshInterval = setInterval(async () => {
    console.log('🔄 Periodic notification refresh...')
    await fetchNotifications(5)
    // Check banner multiple times after refresh to ensure it updates and always shows if there are requests
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
  }, 30000) // 30 seconds
  
  // Store intervals for cleanup
  window.bannerCheckInterval = bannerCheckInterval
  window.notificationRefreshInterval = notificationRefreshInterval
  
  // Store interval ID for cleanup
  window.unreadCountInterval = unreadCountInterval
  
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

          <!-- Notifications -->
          <div class="relative">
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
                <h3 class="text-base font-bold text-white">Recent Notifications</h3>
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

    <!-- Simple Success/Error Banner -->
    <Transition name="banner-slide">
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
    </Transition>
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
    transform: translateX(-100%);
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
  transform: translateX(-100%);
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
  transform: translateX(-100%);
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