import { ref, computed } from 'vue'
import axiosClient from '../axios'

export default function useNotifications() {
  const notifications = ref([])
  const loading = ref(false)
  const error = ref('')
  const unreadCount = ref(0)

  // Fetch unread count from database
  const fetchUnreadCount = async () => {
    try {
      const response = await axiosClient.get('/notifications/unread-count')
      if (response.data.success) {
        unreadCount.value = response.data.count || 0
      }
    } catch (err) {
      console.error('Error fetching unread count:', err)
      // Fallback to calculating from local notifications
      unreadCount.value = notifications.value.filter(n => !n.isRead).length
    }
  }

  // Fetch notifications from notifications table (low stock alerts)
  const fetchNotifications = async (limit = 20) => {
    try {
      loading.value = true
      error.value = ''
      
      const response = await axiosClient.get(`/notifications?per_page=${limit}`)
      
      if (response.data.success) {
        notifications.value = response.data.data.map(notification => {
          // Determine title based on notification type
          let title = notification.title || 'Low Stock Alert'
          if (!notification.title) {
            switch(notification.type) {
              case 'supply_request_created':
                title = 'New Supply Request'
                break
              case 'supply_request_approved':
              case 'supply_request_admin_approved':
                title = 'Receipt Available'
                break
              case 'supply_request_rejected':
              case 'supply_request_admin_rejected':
                title = 'Request Rejected'
                break
              case 'supply_request_ready_pickup':
              case 'supply_request_ready_for_pickup':
                title = 'Ready for Pickup'
                break
              case 'borrow_request':
                title = 'Borrow Request'
                break
              default:
                title = 'Low Stock Alert'
            }
          }
          
          return {
            id: notification.id,
            item_id: notification.item_id || null,
            type: notification.type || 'low_stock',
            title: title,
            message: notification.message,
            user: notification.item?.unit || 'System',
            role: 'System',
            timestamp: notification.timestamp || notification.created_at,
            date: notification.date,
            time: notification.time,
            action: title,
            isRead: notification.isRead ?? false,
            priority: notification.priority || 'high',
            item: notification.item,
            borrowRequest: notification.borrowRequest || null,
            selected: false // For bulk delete selection
          }
        })
        
        // Update unread count from database (more accurate than calculating from fetched items)
        await fetchUnreadCount()
      } else {
        error.value = response.data.message || 'Failed to fetch notifications'
      }
    } catch (err) {
      console.error('Error fetching notifications:', err)
      error.value = err.response?.data?.message || 'Failed to fetch notifications'
      // Initialize with empty array on error
      notifications.value = []
    } finally {
      loading.value = false
    }
  }

  // Get notification type - all are low stock
  const getNotificationType = (type) => {
    return type || 'low_stock'
  }

  // Get notification title - all are low stock alerts
  const getNotificationTitle = (title) => {
    return title || 'Low Stock Alert'
  }

  // Mark notification as read
  const markAsRead = async (notificationId) => {
    try {
      const response = await axiosClient.put(`/notifications/${notificationId}/read`)
      
      if (response.data.success) {
        const notification = notifications.value.find(n => n.id === notificationId)
        if (notification) {
          notification.isRead = true
        }
        // Update unread count from database (more accurate)
        await fetchUnreadCount()
        return true
      }
      return false
    } catch (err) {
      console.error('Error marking notification as read:', err)
      // Still update locally even if API fails
      const notification = notifications.value.find(n => n.id === notificationId)
      if (notification) {
        notification.isRead = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
      return false
    }
  }

  // Mark all notifications as read
  const markAllAsRead = async () => {
    try {
      // Get all unread notifications BEFORE updating
      const unreadNotifications = notifications.value.filter(n => !n.isRead)
      
      // Update all notifications in the local array
      notifications.value.forEach(notification => {
        notification.isRead = true
      })
      
      // Update all unread notifications in database
      for (const notification of unreadNotifications) {
        await axiosClient.put(`/notifications/${notification.id}/read`).catch(() => {})
      }
      
      // Refresh unread count from database (more accurate)
      await fetchUnreadCount()
    } catch (err) {
      console.error('Error marking all as read:', err)
      unreadCount.value = 0
    }
  }

  // Get notifications by type
  const getNotificationsByType = (type) => {
    return computed(() => notifications.value.filter(n => n.type === type))
  }

  // Get recent notifications (last 24 hours)
  const getRecentNotifications = () => {
    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)
    
    return computed(() => 
      notifications.value.filter(n => new Date(n.timestamp) > yesterday)
    )
  }

  // Get unread notifications
  const getUnreadNotifications = () => {
    return computed(() => notifications.value.filter(n => !n.isRead))
  }

  // Refresh notifications
  const refreshNotifications = async () => {
    await fetchNotifications(20)
  }

  // Refresh unread count only (lighter than fetching all notifications)
  const refreshUnreadCount = async () => {
    await fetchUnreadCount()
  }

  // Setup real-time listener for notifications
  const setupRealtimeListener = () => {
    if (!window.Echo) {
      console.warn('⚠️ Laravel Echo not available for notifications')
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
          } else {
            // Notification already exists, just update unread count if needed
            if (data.unread_count !== undefined) {
              unreadCount.value = data.unread_count
            }
          }
          
          // Notification added to list and unread count updated
        }
      })
      
      console.log('✅ Real-time notifications listener active')
    } catch (error) {
      console.error('❌ Error setting up notifications listener:', error)
    }
  }

  // Approve a borrow request
  const approveBorrowRequest = async (itemId, requestId) => {
    try {
      console.log(`Approving borrow request: itemId=${itemId}, requestId=${requestId}`)
      const response = await axiosClient.post(`/items/${itemId}/borrow-request/${requestId}/approve`)
      
      console.log('Approve response:', response)
      console.log('Response status:', response.status)
      console.log('Response data:', response.data)
      
      // Check if response is successful (status 200-299)
      if (response.status >= 200 && response.status < 300) {
        // Check if message indicates success (not an error message)
        const message = response.data?.message || ''
        const isError = message.toLowerCase().includes('error') || 
                       message.toLowerCase().includes('failed') ||
                       response.data?.error
        
        if (isError) {
          console.error('Response contains error message:', message)
          return { 
            success: false, 
            message: message || response.data?.error || 'Failed to approve borrow request'
          }
        }
        
        // Success - update the notification's borrow request status
        const notification = notifications.value.find(n => 
          n.borrowRequest && n.borrowRequest.id === requestId
        )
        if (notification && notification.borrowRequest) {
          notification.borrowRequest.status = 'approved'
          console.log(`Updated notification status to: approved`)
        }
        
        // Update item quantity if provided in response
        if (response.data?.item && notification && notification.item) {
          notification.item.quantity = response.data.item.quantity
          console.log(`Updated item quantity to: ${response.data.item.quantity}`)
        }
        
        return { 
          success: true, 
          message: message || 'Borrow request approved successfully',
          item: response.data?.item
        }
      }
      
      return { success: false, message: 'Failed to approve request - invalid response status' }
    } catch (err) {
      console.error('Error approving borrow request:', err)
      console.error('Error response:', err.response?.data)
      console.error('Error status:', err.response?.status)
      
      // Check if the error might have occurred after successful approval
      // (e.g., event broadcasting failed but approval succeeded)
      const errorMessage = err.response?.data?.message || err.response?.data?.error || err.message
      
      // If it's a 500 error with "Error approving borrow request" message,
      // it might have succeeded but failed during event broadcasting
      // We should check the actual status by refreshing
      if (err.response?.status === 500 && errorMessage.includes('Error approving borrow request')) {
        console.warn('Received 500 error - approval may have succeeded. Will refresh to verify.')
        // Return a special flag to indicate we should refresh and check
        return {
          success: 'unknown',
          message: 'Processing request... Please wait while we verify the status.',
          shouldRefresh: true
        }
      }
      
      return { 
        success: false, 
        message: errorMessage || 'Failed to approve borrow request. Please check if you have admin access.' 
      }
    }
  }

  // Delete a notification
  const deleteNotification = async (notificationId) => {
    try {
      const response = await axiosClient.delete(`/notifications/${notificationId}`)
      
      if (response.data.success) {
        // Remove from local array
        const index = notifications.value.findIndex(n => n.id === notificationId)
        if (index !== -1) {
          notifications.value.splice(index, 1)
        }
        
        // Update unread count
        await fetchUnreadCount()
        
        return { success: true, message: response.data.message }
      }
      return { success: false, message: 'Failed to delete notification' }
    } catch (err) {
      console.error('Error deleting notification:', err)
      return { 
        success: false, 
        message: err.response?.data?.message || 'Failed to delete notification' 
      }
    }
  }

  // Delete multiple notifications
  const deleteMultipleNotifications = async (notificationIds) => {
    try {
      const response = await axiosClient.post('/notifications/delete-multiple', {
        ids: notificationIds
      })
      
      if (response.data.success) {
        // Remove from local array
        notificationIds.forEach(id => {
          const index = notifications.value.findIndex(n => n.id === id)
          if (index !== -1) {
            notifications.value.splice(index, 1)
          }
        })
        
        // Update unread count
        await fetchUnreadCount()
        
        return { 
          success: true, 
          message: response.data.message,
          deletedCount: response.data.deleted_count
        }
      }
      return { success: false, message: 'Failed to delete notifications' }
    } catch (err) {
      console.error('Error deleting multiple notifications:', err)
      return { 
        success: false, 
        message: err.response?.data?.message || 'Failed to delete notifications' 
      }
    }
  }

  // Reject a borrow request
  const rejectBorrowRequest = async (itemId, requestId) => {
    try {
      const response = await axiosClient.post(`/items/${itemId}/borrow-request/${requestId}/reject`)
      
      if (response.data.message) {
        // Update the notification's borrow request status
        const notification = notifications.value.find(n => 
          n.borrowRequest && n.borrowRequest.id === requestId
        )
        if (notification && notification.borrowRequest) {
          notification.borrowRequest.status = 'rejected'
        }
        return { success: true, message: response.data.message }
      }
      return { success: false, message: 'Failed to reject request' }
    } catch (err) {
      console.error('Error rejecting borrow request:', err)
      return { 
        success: false, 
        message: err.response?.data?.message || 'Failed to reject borrow request' 
      }
    }
  }

  return {
    notifications,
    loading,
    error,
    unreadCount,
    fetchNotifications,
    fetchUnreadCount,
    refreshNotifications,
    refreshUnreadCount,
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
  }
}
