<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useNotifications from '../composables/useNotifications'
import useAuth from '../composables/useAuth'
const router = useRouter()
const route = useRoute()
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
  getUnreadNotifications,
  setupRealtimeListener,
  approveBorrowRequest,
  rejectBorrowRequest,
  deleteNotification,
  deleteMultipleNotifications
} = useNotifications()

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(20)

// Computed properties - no filtering, just pagination
const filteredNotifications = computed(() => {
  return notifications.value
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

// Handle notification click - scroll to notification and mark as read
const handleNotificationClick = async (notification) => {
  // Mark as read
  if (!notification.isRead) {
    await markAsRead(notification.id)
  }
  
  // For borrow requests, scroll to the notification and highlight it
  if (notification.type === 'borrow_request') {
    // Scroll to the notification element
    setTimeout(() => {
      const notificationElement = document.querySelector(`[data-notification-id="${notification.id}"]`)
      if (notificationElement) {
        notificationElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
        // Add highlight effect
        notificationElement.classList.add('highlight-notification')
        setTimeout(() => {
          notificationElement.classList.remove('highlight-notification')
        }, 2000)
      }
    }, 100)
    return
  }
  
  // For other notifications, navigate to Analytics page
  router.push({ name: 'Analytics' })
}

// Show success banner
const showSuccessMessage = (message, type = 'success', details = null) => {
  console.log('📢 Showing banner:', { message, type, details, showSuccessBanner: showSuccessBanner.value })
  successBannerMessage.value = message
  successBannerType.value = type
  successBannerDetails.value = details
  showSuccessBanner.value = true
  console.log('📢 Banner state after setting:', { showSuccessBanner: showSuccessBanner.value, message: successBannerMessage.value })
  
  // Auto-hide after 5 seconds
  setTimeout(() => {
    showSuccessBanner.value = false
    console.log('📢 Banner auto-hidden after 5 seconds')
  }, 5000)
}

// Close success banner
const closeSuccessBanner = () => {
  showSuccessBanner.value = false
}

// Handle delete notification
const handleDelete = async (notification) => {
  if (!confirm(`Are you sure you want to delete this notification?\n\n"${notification.message.substring(0, 50)}${notification.message.length > 50 ? '...' : ''}"`)) {
    return
  }
  
  const result = await deleteNotification(notification.id)
  
  if (result.success) {
    showSuccessMessage('Notification deleted successfully', 'success')
  } else {
    showSuccessMessage(`Error: ${result.message || 'Failed to delete notification'}`, 'error')
  }
}

// Handle delete multiple notifications
const handleDeleteMultiple = async () => {
  const selectedNotifications = notifications.value.filter(n => n.selected)
  
  if (selectedNotifications.length === 0) {
    showSuccessMessage('Please select notifications to delete', 'error')
    return
  }
  
  if (!confirm(`Are you sure you want to delete ${selectedNotifications.length} notification(s)?`)) {
    return
  }
  
  const ids = selectedNotifications.map(n => n.id)
  const result = await deleteMultipleNotifications(ids)
  
  if (result.success) {
    showSuccessMessage(`${result.deletedCount || selectedNotifications.length} notification(s) deleted successfully`, 'success')
    // Clear selection
    notifications.value.forEach(n => n.selected = false)
  } else {
    showSuccessMessage(`Error: ${result.message || 'Failed to delete notifications'}`, 'error')
  }
}

// Handle approve borrow request
const handleApprove = async (notification) => {
  if (!notification.borrowRequest || !notification.item) {
    showSuccessMessage('Error: Missing borrow request or item information', 'error')
    return
  }
  
  // Check if request is already processed
  if (notification.borrowRequest.status !== 'pending') {
    showSuccessMessage(`This borrow request has already been ${notification.borrowRequest.status}.`, 'error')
    return
  }
  
  // Use UUID if available, otherwise use ID
  const itemId = notification.item.uuid || notification.item.id || notification.item_id
  const requestId = notification.borrowRequest.id
  
  if (!itemId || !requestId) {
    showSuccessMessage('Error: Missing item ID or request ID', 'error')
    return
  }
  
  // Disable button to prevent multiple clicks
  const originalStatus = notification.borrowRequest.status
  notification.borrowRequest.status = 'processing' // Temporary status to disable button
  
  try {
    const result = await approveBorrowRequest(itemId, requestId)
    
    // Handle unknown status (might have succeeded but got error during event broadcasting)
    if (result.success === 'unknown' && result.shouldRefresh) {
      // Refresh to check actual status
      await fetchNotifications(100)
      
      // Find the notification again after refresh
      const refreshedNotification = notifications.value.find(n => 
        n.borrowRequest && n.borrowRequest.id === requestId
      )
      
      if (refreshedNotification && refreshedNotification.borrowRequest.status === 'approved') {
        // It actually succeeded!
        notification.borrowRequest.status = 'approved'
        
        // Mark notification as read
        if (!notification.isRead) {
          await markAsRead(notification.id)
        }
        
        // Show success banner
        const message = `Borrow Request Approved Successfully`
        const details = {
          item: notification.item.unit,
          requestedBy: notification.borrowRequest.borrowed_by,
          quantity: notification.borrowRequest.quantity,
          newQuantity: refreshedNotification.item?.quantity || notification.item.quantity,
          location: notification.borrowRequest.location
        }
        showSuccessMessage(message, 'success', details)
      } else {
        // It actually failed
        notification.borrowRequest.status = originalStatus
        showSuccessMessage(`Error: ${result.message || 'Failed to approve borrow request'}`, 'error')
      }
      return
    }
    
    if (result.success === true) {
      // Update status immediately to 'approved'
      notification.borrowRequest.status = 'approved'
      
      // Mark notification as read
      if (!notification.isRead) {
        await markAsRead(notification.id)
      }
      
      // Show professional success banner
      const updatedQty = result.item?.quantity !== undefined ? result.item.quantity : 'N/A'
      const message = `Borrow Request Approved Successfully`
      const details = {
        item: notification.item.unit,
        requestedBy: notification.borrowRequest.borrowed_by,
        quantity: notification.borrowRequest.quantity,
        newQuantity: updatedQty,
        location: notification.borrowRequest.location
      }
      showSuccessMessage(message, 'success', details)
      
      // Refresh notifications to get updated data from server
      await fetchNotifications(100)
    } else {
      // Revert status on error
      notification.borrowRequest.status = originalStatus
      showSuccessMessage(`Error: ${result.message || 'Failed to approve borrow request'}`, 'error')
    }
  } catch (error) {
    // Revert status on error
    notification.borrowRequest.status = originalStatus
    console.error('Error in handleApprove:', error)
    showSuccessMessage(`Error: ${error.message || 'Failed to approve borrow request. Please check console for details.'}`, 'error')
  }
}

// Open rejection confirmation modal
const openRejectModal = (notification) => {
  if (!notification.borrowRequest || !notification.item) {
    showSuccessMessage('Error: Missing borrow request or item information', 'error')
    return
  }
  
  // Check if request is already processed
  if (notification.borrowRequest.status !== 'pending') {
    showSuccessMessage(`This borrow request has already been ${notification.borrowRequest.status}.`, 'error')
    return
  }
  
  notificationToReject.value = notification
  showRejectModal.value = true
}

// Close rejection modal
const closeRejectModal = () => {
  showRejectModal.value = false
  notificationToReject.value = null
}

// Handle reject borrow request (called from modal)
const handleReject = async () => {
  const notification = notificationToReject.value
  if (!notification) return
  
  // Use UUID if available, otherwise use ID
  const itemId = notification.item.uuid || notification.item.id || notification.item_id
  const requestId = notification.borrowRequest.id
  
  // Close modal first
  closeRejectModal()
  
  // Disable button to prevent multiple clicks
  const originalStatus = notification.borrowRequest.status
  notification.borrowRequest.status = 'processing' // Temporary status to disable button
  
  try {
    const result = await rejectBorrowRequest(itemId, requestId)
    
    if (result.success) {
      // Update status immediately to 'rejected'
      notification.borrowRequest.status = 'rejected'
      
      // Mark notification as read
      if (!notification.isRead) {
        await markAsRead(notification.id)
      }
      
      // Show professional success banner
      const message = `Borrow Request Rejected`
      const details = {
        item: notification.item.unit,
        requestedBy: notification.borrowRequest.borrowed_by,
        quantity: notification.borrowRequest.quantity,
        location: notification.borrowRequest.location
      }
      showSuccessMessage(message, 'success', details)
      
      // Refresh notifications to get updated data from server
      await fetchNotifications(100)
    } else {
      // Revert status on error
      notification.borrowRequest.status = originalStatus
      showSuccessMessage(`Error: ${result.message || 'Failed to reject borrow request'}`, 'error')
    }
  } catch (error) {
    // Revert status on error
    notification.borrowRequest.status = originalStatus
    console.error('Error in handleReject:', error)
    showSuccessMessage(`Error: ${error.message || 'Failed to reject borrow request. Please check console for details.'}`, 'error')
  }
}

// Wrapper functions for popup buttons to close popup after handling
const handleApproveFromPopup = async (notification) => {
  await handleApprove(notification)
  // Close popup after a short delay to ensure banner is shown
  setTimeout(() => {
    showPopup.value = false
  }, 100)
}

const handleRejectFromPopup = async (notification) => {
  // Open the rejection modal instead of directly rejecting
  openRejectModal(notification)
  // Close popup after opening modal
  setTimeout(() => {
    showPopup.value = false
  }, 100)
}

// Methods

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
    case 'high': return 'bg-red-100 text-red-800'
    case 'medium': return 'bg-yellow-100 text-yellow-800'
    case 'low': return 'bg-green-100 text-green-800'
    default: return 'bg-gray-100 text-gray-800'
  }
}

// Get priority for restock notifications (should be medium priority)
const getNotificationPriority = (notification) => {
  // If it's a restock notification, give it medium priority
  if (notification.type === 'restocked_supply' || 
      notification.type === 'restock' || 
      notification.action === 'Restocked Supply') {
    return 'medium'
  }
  return notification.priority || 'high'
}

const getTypeIcon = (type) => {
  switch (type) {
    case 'low_stock': return 'warning'
    case 'borrow_request': return 'shopping_cart'
    case 'auth': return 'login'
    case 'create': return 'add_circle'
    case 'update': return 'edit'
    case 'delete': return 'delete'
    case 'borrow': return 'shopping_cart'
    case 'restore': return 'restore'
    case 'restocked_supply':
    case 'restock':
    case 'Restocked Supply': return 'inventory_2'
    case 'info': return 'info'
    default: return 'notifications'
  }
}

const formatRelativeTime = (timestamp) => {
  if (!timestamp) return 'Unknown time'
  
  try {
    const now = new Date()
    const time = new Date(timestamp)
    
    // Check if date is valid
    if (isNaN(time.getTime())) {
      return 'Invalid date'
    }
    
    const diffInSeconds = Math.floor((now - time) / 1000)

    if (diffInSeconds < 0) return 'Just now' // Future dates
    if (diffInSeconds < 60) return 'Just now'
    if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60)
      return `${minutes}m ago`
    }
    if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600)
      return `${hours}h ago`
    }
    if (diffInSeconds < 604800) {
      const days = Math.floor(diffInSeconds / 86400)
      return `${days}d ago`
    }
    // For older dates, show formatted date
    return time.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric',
      year: time.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    })
  } catch (error) {
    console.error('Error formatting time:', error)
    return 'Invalid time'
  }
}

// Store notification interval for cleanup
const notificationInterval = ref(null)
const popupNotification = ref(null)
const showPopup = ref(false)

// Success banner state
const showSuccessBanner = ref(false)
const successBannerMessage = ref('')
const successBannerType = ref('success') // 'success' or 'error'
const successBannerDetails = ref(null)

// Rejection confirmation modal state
const showRejectModal = ref(false)
const notificationToReject = ref(null)

// Setup real-time listener with popup notification
const setupRealtimeWithPopup = () => {
  if (!window.Echo) {
    console.warn('⚠️ Laravel Echo not available. Will retry...')
    setTimeout(setupRealtimeWithPopup, 2000)
    return
  }

  try {
    const channel = window.Echo.channel('notifications')
    
    channel.listen('.NotificationCreated', (data) => {
      console.log('📬 New notification received:', data)
      
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
          selected: false // For bulk delete selection
        }
        
        // Check if notification already exists (prevent duplicates from multiple listeners)
        const exists = notifications.value.some(n => n.id === newNotification.id)
        if (!exists) {
          // Add to beginning of notifications array
          notifications.value.unshift(newNotification)
          
          // Update unread count
          if (data.unread_count !== undefined) {
            unreadCount.value = data.unread_count
          } else if (!newNotification.isRead) {
            unreadCount.value++
          }
          
          // Show popup for borrow requests
          if (newNotification.type === 'borrow_request') {
            popupNotification.value = newNotification
            showPopup.value = true
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
              showPopup.value = false
            }, 10000)
          }
        } else {
          // Notification already exists, just update unread count
          if (data.unread_count !== undefined) {
            unreadCount.value = data.unread_count
          }
        }
      }
    })
    
    console.log('✅ Real-time notifications listener active with popup support')
  } catch (error) {
    console.error('❌ Error setting up notifications listener:', error)
  }
}

// Function to scroll to and highlight a specific notification
const scrollToNotification = (notificationId) => {
  // Wait for DOM to be ready
  setTimeout(() => {
    const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`)
    if (notificationElement) {
      notificationElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
      // Add highlight effect
      notificationElement.classList.add('highlight-notification')
      setTimeout(() => {
        notificationElement.classList.remove('highlight-notification')
      }, 3000)
    } else {
      // If notification not found, try again after a short delay (might be on another page)
      setTimeout(() => {
        const retryElement = document.querySelector(`[data-notification-id="${notificationId}"]`)
        if (retryElement) {
          retryElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
          retryElement.classList.add('highlight-notification')
          setTimeout(() => {
            retryElement.classList.remove('highlight-notification')
          }, 3000)
        }
      }, 500)
    }
  }, 300)
}

// Watch for route query changes to highlight notification
watch(() => route.query.highlight, (notificationId) => {
  if (notificationId) {
    // Make sure notifications are loaded first
    if (notifications.value.length > 0) {
      scrollToNotification(parseInt(notificationId))
      // Clear the query parameter after highlighting
      router.replace({ query: {} })
    } else {
      // Wait for notifications to load
      const unwatch = watch(() => notifications.value.length, (length) => {
        if (length > 0) {
          scrollToNotification(parseInt(notificationId))
          router.replace({ query: {} })
          unwatch()
        }
      })
    }
  }
}, { immediate: true })

// Lifecycle
onMounted(async () => {
  // Fetch notifications initially
  await fetchNotifications(100) // Fetch more notifications for the full page
  
  // Setup real-time listener for this page (with popup)
  setupRealtimeWithPopup()
  
  // Also use the composable's listener as backup
  setupRealtimeListener()
  
  // Check if there's a notification to highlight from route query
  if (route.query.highlight) {
    scrollToNotification(parseInt(route.query.highlight))
    
    // Check if there's an action to perform
    if (route.query.action === 'approve') {
      // Find the notification and trigger approve
      const notification = notifications.value.find(n => n.id === parseInt(route.query.highlight))
      if (notification && notification.borrowRequest && notification.borrowRequest.status === 'pending') {
        setTimeout(() => {
          handleApprove(notification)
        }, 500) // Small delay to ensure page is loaded
      }
    }
    
    // Clear the query parameter
    router.replace({ query: {} })
  }
  
  // Refresh notifications periodically to keep them updated (less frequent now with real-time)
  notificationInterval.value = setInterval(async () => {
    await fetchNotifications(100) // Refresh with same limit
  }, 30000) // Refresh every 30 seconds (less frequent due to real-time updates)
})

// Cleanup interval on unmount
onBeforeUnmount(() => {
  if (notificationInterval.value) {
    clearInterval(notificationInterval.value)
    notificationInterval.value = null
  }
})
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6">
    <div class="max-w-full mx-auto space-y-5">
      <!-- Enhanced Header Section -->
      <div class="bg-green-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-5 md:px-8 md:py-6 flex flex-wrap items-center gap-4">
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg flex-shrink-0 shadow-md">
            <span class="material-icons-outlined text-green-600 text-2xl md:text-3xl">notifications</span>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-1">All Notifications</h1>
            <p class="text-green-100 text-base md:text-lg">Track all system activities and user actions</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              @click="markAllAsRead"
              :disabled="unreadCount === 0"
              class="btn-primary-enhanced disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span class="material-icons-outlined text-lg">done_all</span>
              <span>Mark All Read</span>
            </button>
            <button
              @click="handleDeleteMultiple"
              :disabled="notifications.filter(n => n.selected).length === 0"
              class="btn-danger-enhanced disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span class="material-icons-outlined text-lg">delete_sweep</span>
              <span>Delete Selected</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Notifications</div>
            <div class="p-3 bg-blue-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">notifications</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ notificationStats.total }}</div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Unread</div>
            <div class="p-3 bg-red-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">mark_email_unread</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-red-600">{{ notificationStats.unread }}</div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Today</div>
            <div class="p-3 bg-green-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">today</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-green-600">{{ notificationStats.today }}</div>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="error" class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg">
        <span class="material-icons-outlined text-5xl text-red-400 mb-4">error_outline</span>
        <p class="text-red-500 text-lg font-semibold">{{ error }}</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg">
        <div class="flex flex-col items-center gap-4">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
          <span class="text-gray-600 dark:text-gray-400 font-medium">Loading notifications...</span>
        </div>
      </div>

      <!-- Notifications List -->
      <div v-else>
        <div class="mb-4">
          <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mb-2">Notification List</h2>
          <p class="text-gray-600 dark:text-gray-400 text-sm">{{ filteredNotifications.length }} notification{{ filteredNotifications.length !== 1 ? 's' : '' }} total</p>
        </div>

        <div class="space-y-3">
          <div v-if="paginatedNotifications.length === 0" class="text-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg">
            <span class="material-icons-outlined text-6xl text-gray-300 mb-4 block">notifications_off</span>
            <p class="text-gray-500 text-lg font-semibold">No notifications found</p>
          </div>
          
          <div
            v-for="notification in paginatedNotifications"
            :key="notification.id"
            :data-notification-id="notification.id"
            class="p-5 bg-white dark:bg-gray-800 border-2 rounded-xl hover:shadow-lg transition-all duration-200 relative"
            :class="{ 
              'border-blue-400 dark:border-blue-400 bg-blue-900/20 dark:bg-blue-900/20': !notification.isRead,
              'border-gray-200 dark:border-gray-700 hover:border-green-400': notification.isRead,
              'border-purple-400 dark:border-purple-400 bg-purple-50 dark:bg-purple-900/20': notification.selected
            }"
          >
            <!-- Selection checkbox - positioned to not overlap with time text -->
            <div class="absolute top-4 right-4 z-10">
              <input
                type="checkbox"
                :checked="notification.selected || false"
                @click.stop="notification.selected = !notification.selected"
                class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500 cursor-pointer"
              />
            </div>
            
            <div @click="handleNotificationClick(notification)" class="cursor-pointer">
              <div class="flex items-start gap-4">
              <!-- Icon -->
              <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-lg flex items-center justify-center shadow-md"
                  :class="{
                    'bg-green-900/30 dark:bg-green-900/30': getNotificationPriority(notification) === 'low',
                    'bg-yellow-900/30 dark:bg-yellow-900/30': getNotificationPriority(notification) === 'medium',
                    'bg-red-900/30 dark:bg-red-900/30': getNotificationPriority(notification) === 'high',
                    'bg-gray-50 dark:bg-gray-700': !getNotificationPriority(notification)
                  }"
                >
                  <span class="material-icons-outlined"
                    :class="{
                      'text-green-400 dark:text-green-400': getNotificationPriority(notification) === 'low',
                      'text-yellow-400 dark:text-yellow-400': getNotificationPriority(notification) === 'medium',
                      'text-red-400 dark:text-red-400': getNotificationPriority(notification) === 'high',
                      'text-gray-600 dark:text-gray-400': !getNotificationPriority(notification)
                    }"
                  >
                    {{ getTypeIcon(notification.type || notification.action) }}
                  </span>
                </div>
              </div>
              
              <!-- Content -->
              <div class="flex-1 min-w-0 pr-12">
                <div class="flex items-start justify-between mb-2 gap-2">
                  <div class="flex items-center gap-2 flex-wrap flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                      {{ notification.title }}
                    </h3>
                    <span
                      :class="getPriorityColor(getNotificationPriority(notification))"
                      class="px-3 py-1 text-xs font-semibold rounded-full"
                    >
                      {{ getNotificationPriority(notification) }}
                    </span>
                    <span v-if="!notification.isRead" class="w-2.5 h-2.5 bg-blue-500 rounded-full animate-pulse"></span>
                  </div>
                  <p class="text-xs font-medium text-gray-500 flex-shrink-0 whitespace-nowrap">
                    {{ formatRelativeTime(notification.timestamp) }}
                  </p>
                </div>
                
                <p class="text-sm text-gray-300 dark:text-gray-300 mb-3 leading-relaxed">
                  {{ notification.message }}
                </p>
                
                <!-- Borrow Request Action Buttons -->
                <div v-if="notification.type === 'borrow_request' && notification.borrowRequest && notification.borrowRequest.status === 'pending'" class="flex items-center gap-3 mb-3">
                  <button
                    @click.stop="handleApprove(notification)"
                    :disabled="notification.borrowRequest.status !== 'pending'"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
                  >
                    <span class="material-icons-outlined text-lg">check_circle</span>
                    <span>{{ notification.borrowRequest.status === 'processing' ? 'Processing...' : 'Approve' }}</span>
                  </button>
                  <button
                    @click.stop="openRejectModal(notification)"
                    :disabled="notification.borrowRequest.status !== 'pending'"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
                  >
                    <span class="material-icons-outlined text-lg">cancel</span>
                    <span>{{ notification.borrowRequest.status === 'processing' ? 'Processing...' : 'Decline' }}</span>
                  </button>
                </div>
                
                <!-- Status Badge for processed requests -->
                <div v-if="notification.type === 'borrow_request' && notification.borrowRequest && notification.borrowRequest.status !== 'pending'" class="mb-3">
                  <span
                    :class="{
                      'bg-green-100 text-green-800': notification.borrowRequest.status === 'approved',
                      'bg-red-100 text-red-800': notification.borrowRequest.status === 'rejected'
                    }"
                    class="px-3 py-1 text-xs font-semibold rounded-full"
                  >
                    {{ notification.borrowRequest.status === 'approved' ? '✓ Approved' : '✗ Rejected' }}
                  </span>
                </div>
                
                <div class="flex items-center flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                  <span v-if="notification.item" class="flex items-center gap-1 bg-gray-50 dark:bg-gray-700 px-3 py-1.5 rounded-lg">
                    <span class="material-icons-outlined text-base text-green-400 dark:text-green-400">inventory_2</span>
                    <span class="font-medium text-gray-900 dark:text-white">Item: {{ notification.item.unit }}</span>
                    <span class="text-gray-600 dark:text-gray-400">(Qty: {{ notification.item.quantity }})</span>
                  </span>
                  <span v-if="notification.borrowRequest" class="flex items-center gap-1 bg-blue-50 dark:bg-blue-900/30 px-3 py-1.5 rounded-lg">
                    <span class="material-icons-outlined text-base text-blue-400 dark:text-blue-400">person</span>
                    <span class="font-medium text-gray-900 dark:text-white">Requested by: {{ notification.borrowRequest.borrowed_by }}</span>
                    <span class="text-gray-600 dark:text-gray-400">({{ notification.borrowRequest.quantity }} unit(s))</span>
                  </span>
                  <span v-if="notification.borrowRequest && notification.borrowRequest.location" class="flex items-center gap-1 bg-purple-50 dark:bg-purple-900/30 px-3 py-1.5 rounded-lg">
                    <span class="material-icons-outlined text-base text-purple-400 dark:text-purple-400">location_on</span>
                    <span class="text-gray-900 dark:text-white">{{ notification.borrowRequest.location }}</span>
                  </span>
                  <span v-else class="flex items-center gap-1 bg-gray-50 dark:bg-gray-700 px-3 py-1.5 rounded-lg">
                    <span class="material-icons-outlined text-base text-blue-400 dark:text-blue-400">person</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ notification.user }}</span>
                    <span class="text-gray-600 dark:text-gray-400">({{ notification.role }})</span>
                  </span>
                  <span class="flex items-center gap-1 bg-gray-50 dark:bg-gray-700 px-3 py-1.5 rounded-lg">
                    <span class="material-icons-outlined text-base text-purple-400 dark:text-purple-400">schedule</span>
                    <span class="text-gray-900 dark:text-white">{{ notification.date }} at {{ notification.time }}</span>
                  </span>
                </div>
              </div>
            </div>
            </div>
            
            <!-- Delete Button -->
            <div class="mt-3 flex justify-end">
              <button
                @click.stop="handleDelete(notification)"
                class="flex items-center gap-2 px-3 py-1.5 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg font-medium text-sm transition-all shadow-sm hover:shadow-md"
                title="Delete notification"
              >
                <span class="material-icons-outlined text-base">delete</span>
                <span>Delete</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && totalPages > 1" class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="text-sm font-medium text-gray-900 dark:text-white">
            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredNotifications.length) }} of {{ filteredNotifications.length }} notifications
          </div>
          
          <div class="flex items-center justify-center sm:justify-end gap-2 flex-wrap">
            <button
              class="px-3 py-1.5 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-700 dark:hover:bg-gray-700 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white font-medium transition-all"
              :disabled="currentPage === 1"
              @click="prevPage"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_left</span>
            </button>
            
            <button
              v-for="page in Array.from({ length: Math.min(5, totalPages) }, (_, i) => Math.max(1, currentPage - 2) + i)"
              :key="page"
              class="px-3 py-1.5 text-sm border-2 rounded-lg font-medium transition-all"
              :class="currentPage === page 
                ? 'bg-green-600 text-white border-green-600 shadow-md' 
                : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
            
            <button
              class="px-3 py-1.5 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-700 dark:hover:bg-gray-700 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white font-medium transition-all"
              :disabled="currentPage === totalPages"
              @click="nextPage"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_right</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Popup Notification for Borrow Requests -->
      <div
        v-if="showPopup && popupNotification"
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
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ popupNotification.title || 'New Borrow Request' }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ formatRelativeTime(popupNotification.timestamp) }}</p>
              </div>
            </div>
            <button
              @click="showPopup = false"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
          
          <p class="text-gray-700 dark:text-gray-300 mb-4">{{ popupNotification.message }}</p>
          
          <div v-if="popupNotification.borrowRequest" class="mb-4 space-y-2 text-sm">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-base text-blue-400">person</span>
              <span class="text-gray-700 dark:text-gray-300"><strong>Requested by:</strong> {{ popupNotification.borrowRequest.borrowed_by }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-base text-purple-400">location_on</span>
              <span class="text-gray-700 dark:text-gray-300"><strong>Location:</strong> {{ popupNotification.borrowRequest.location }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-base text-green-400">inventory_2</span>
              <span class="text-gray-700 dark:text-gray-300"><strong>Quantity:</strong> {{ popupNotification.borrowRequest.quantity }} unit(s)</span>
            </div>
          </div>
          
          <div class="flex items-center gap-3">
            <button
              @click="handleApproveFromPopup(popupNotification)"
              :disabled="popupNotification.borrowRequest?.status !== 'pending'"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
            >
              <span class="material-icons-outlined text-lg">check_circle</span>
              <span>{{ popupNotification.borrowRequest?.status === 'processing' ? 'Processing...' : 'Approve' }}</span>
            </button>
            <button
              @click="handleRejectFromPopup(popupNotification)"
              :disabled="popupNotification.borrowRequest?.status !== 'pending'"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
            >
              <span class="material-icons-outlined text-lg">cancel</span>
              <span>{{ popupNotification.borrowRequest?.status === 'processing' ? 'Processing...' : 'Decline' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Professional Government-Style Success Banner -->
      <div
        v-if="showSuccessBanner"
        class="fixed top-4 left-1/2 transform -translate-x-1/2 z-[9999] max-w-2xl w-[calc(100vw-2rem)]"
      >
        <div
          class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl border-2 animate-slide-down"
          :class="{
            'border-green-500': successBannerType === 'success',
            'border-red-500': successBannerType === 'error'
          }"
        >
          <div class="p-5">
            <!-- Header -->
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-4">
                <div
                  class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
                  :class="{
                    'bg-green-100 dark:bg-green-900/30': successBannerType === 'success',
                    'bg-red-100 dark:bg-red-900/30': successBannerType === 'error'
                  }"
                >
                  <span
                    class="material-icons-outlined text-2xl"
                    :class="{
                      'text-green-600 dark:text-green-400': successBannerType === 'success',
                      'text-red-600 dark:text-red-400': successBannerType === 'error'
                    }"
                  >
                    {{ successBannerType === 'success' ? 'check_circle' : 'error' }}
                  </span>
                </div>
                <div>
                  <h3
                    class="text-lg font-bold"
                    :class="{
                      'text-green-800 dark:text-green-300': successBannerType === 'success',
                      'text-red-800 dark:text-red-300': successBannerType === 'error'
                    }"
                  >
                    {{ successBannerType === 'success' ? 'Request Processed Successfully' : 'Error Processing Request' }}
                  </h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">National Inventory Administration System</p>
                </div>
              </div>
              <button
                @click="closeSuccessBanner"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
              >
                <span class="material-icons-outlined">close</span>
              </button>
            </div>

            <!-- Message -->
            <div
              class="mb-4 p-4 rounded-lg"
              :class="{
                'bg-green-50 dark:bg-green-900/20': successBannerType === 'success',
                'bg-red-50 dark:bg-red-900/20': successBannerType === 'error'
              }"
            >
              <p
                class="font-semibold"
                :class="{
                  'text-green-900 dark:text-green-200': successBannerType === 'success',
                  'text-red-900 dark:text-red-200': successBannerType === 'error'
                }"
              >
                {{ successBannerMessage }}
              </p>
            </div>

            <!-- Details (for success) -->
            <div v-if="successBannerType === 'success' && successBannerDetails" class="space-y-2 text-sm">
              <div class="grid grid-cols-2 gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                <div>
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Item:</span>
                  <span class="text-gray-900 dark:text-white font-semibold ml-2">{{ successBannerDetails.item }}</span>
                </div>
                <div>
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Requested By:</span>
                  <span class="text-gray-900 dark:text-white font-semibold ml-2">{{ successBannerDetails.requestedBy }}</span>
                </div>
                <div>
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Quantity:</span>
                  <span class="text-gray-900 dark:text-white font-semibold ml-2">{{ successBannerDetails.quantity }} unit(s)</span>
                </div>
                <div v-if="successBannerDetails.newQuantity !== undefined">
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Remaining Stock:</span>
                  <span class="text-gray-900 dark:text-white font-semibold ml-2">{{ successBannerDetails.newQuantity }} unit(s)</span>
                </div>
                <div v-if="successBannerDetails.location" class="col-span-2">
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Location:</span>
                  <span class="text-gray-900 dark:text-white font-semibold ml-2">{{ successBannerDetails.location }}</span>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="mt-4 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
              <span>{{ new Date().toLocaleString() }}</span>
              <span>Transaction ID: {{ Date.now() }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Rejection Confirmation Modal -->
      <div
        v-if="showRejectModal && notificationToReject"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[10000] p-4"
        @click.self="closeRejectModal"
      >
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full overflow-hidden">
          <!-- Red Header -->
          <div class="bg-red-600 dark:bg-red-700 px-6 py-4 flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">warning</span>
            <h3 class="text-xl font-bold text-white">Confirm Rejection</h3>
          </div>
          
          <!-- Body Content -->
          <div class="p-6">
            <p class="text-gray-900 dark:text-white text-base font-medium mb-4">
              Are you sure you want to reject this Supplies Request?
            </p>
            
            <!-- Request Details Box -->
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4 mb-4 space-y-3">
              <div class="flex items-center gap-3">
                <span class="material-icons-outlined text-gray-600 dark:text-gray-400">description</span>
                <span class="text-gray-900 dark:text-white font-medium">
                  Item: {{ notificationToReject.item?.unit || 'N/A' }}
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="material-icons-outlined text-gray-600 dark:text-gray-400">person</span>
                <span class="text-gray-900 dark:text-white font-medium">
                  Requested by: {{ notificationToReject.borrowRequest?.borrowed_by || 'N/A' }}
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="material-icons-outlined text-gray-600 dark:text-gray-400">hash</span>
                <span class="text-gray-900 dark:text-white font-medium">
                  Quantity: {{ notificationToReject.borrowRequest?.quantity || 0 }} unit(s)
                </span>
              </div>
              <div class="flex items-center gap-3">
                <span class="material-icons-outlined text-gray-600 dark:text-gray-400">location_on</span>
                <span class="text-gray-900 dark:text-white font-medium">
                  Location: {{ notificationToReject.borrowRequest?.location || 'N/A' }}
                </span>
              </div>
            </div>
            
            <!-- Warning Message -->
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
              This action cannot be undone. The Supplies Request will be marked as rejected.
            </p>
            
            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3">
              <button
                @click="closeRejectModal"
                class="flex items-center gap-2 px-5 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-lg">close</span>
                <span>Cancel</span>
              </button>
              <button
                @click="handleReject"
                class="flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition-all shadow-md hover:shadow-lg"
              >
                <span class="material-icons-outlined text-lg">block</span>
                <span>Reject Supplies Request</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-5 py-2.5 rounded-lg hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-md hover:shadow-lg;
}

.btn-danger-enhanced {
  @apply bg-gradient-to-r from-red-600 to-red-700 text-white px-5 py-2.5 rounded-lg hover:from-red-700 hover:to-red-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-md hover:shadow-lg;
}

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

/* Highlight notification effect */
.highlight-notification {
  animation: highlight-pulse 2s ease-in-out;
  border-color: #22c55e !important;
  box-shadow: 0 0 20px rgba(34, 197, 94, 0.5) !important;
}

@keyframes highlight-pulse {
  0% {
    transform: scale(1);
    box-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
  }
  50% {
    transform: scale(1.02);
    box-shadow: 0 0 30px rgba(34, 197, 94, 0.7);
  }
  100% {
    transform: scale(1);
    box-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
  }
}

/* Success banner slide down animation */
@keyframes slide-down {
  from {
    transform: translate(-50%, -100%);
    opacity: 0;
  }
  to {
    transform: translate(-50%, 0);
    opacity: 1;
  }
}

.animate-slide-down {
  animation: slide-down 0.4s ease-out;
}
</style>
