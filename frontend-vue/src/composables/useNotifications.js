import { ref, computed } from 'vue'
import axiosClient from '../axios'

export default function useNotifications() {
  const notifications = ref([])
  const loading = ref(false)
  const error = ref('')
  const unreadCount = ref(0)

  // Fetch notifications from activity logs
  const fetchNotifications = async (limit = 10) => {
    try {
      loading.value = true
      error.value = ''
      
      const response = await axiosClient.get(`/activity-logs?per_page=${limit}`)
      
      if (response.data.success) {
        notifications.value = response.data.data.map(log => ({
          id: log.id,
          type: getNotificationType(log.action),
          title: getNotificationTitle(log.action),
          message: log.description || getDefaultMessage(log.action),
          user: log.name,
          role: log.role,
          timestamp: log.created_at || new Date(`${log.date} ${log.time}`).toISOString(),
          date: log.date,
          time: log.time,
          action: log.action,
          isRead: false,
          priority: getNotificationPriority(log.action)
        }))
        
        // Calculate unread count
        unreadCount.value = notifications.value.filter(n => !n.isRead).length
      } else {
        error.value = response.data.message || 'Failed to fetch notifications'
      }
    } catch (err) {
      console.error('Error fetching notifications:', err)
      error.value = err.response?.data?.message || 'Failed to fetch notifications'
    } finally {
      loading.value = false
    }
  }

  // Get notification type based on action
  const getNotificationType = (action) => {
    const actionLower = action.toLowerCase()
    
    if (actionLower.includes('login') || actionLower.includes('logout')) {
      return 'auth'
    } else if (actionLower.includes('create') || actionLower.includes('add')) {
      return 'create'
    } else if (actionLower.includes('update') || actionLower.includes('edit')) {
      return 'update'
    } else if (actionLower.includes('delete') || actionLower.includes('remove')) {
      return 'delete'
    } else if (actionLower.includes('borrow') || actionLower.includes('return')) {
      return 'borrow'
    } else if (actionLower.includes('restore')) {
      return 'restore'
    } else {
      return 'info'
    }
  }

  // Get notification title based on action
  const getNotificationTitle = (action) => {
    const actionLower = action.toLowerCase()
    
    if (actionLower.includes('login')) {
      return 'User Login'
    } else if (actionLower.includes('logout')) {
      return 'User Logout'
    } else if (actionLower.includes('create') && actionLower.includes('item')) {
      return 'New Item Added'
    } else if (actionLower.includes('create') && actionLower.includes('user')) {
      return 'New User Created'
    } else if (actionLower.includes('update') && actionLower.includes('item')) {
      return 'Item Updated'
    } else if (actionLower.includes('update') && actionLower.includes('user')) {
      return 'User Updated'
    } else if (actionLower.includes('delete') && actionLower.includes('item')) {
      return 'Item Deleted'
    } else if (actionLower.includes('delete') && actionLower.includes('user')) {
      return 'User Deleted'
    } else if (actionLower.includes('borrow')) {
      return 'Item Borrowed'
    } else if (actionLower.includes('return')) {
      return 'Item Returned'
    } else if (actionLower.includes('restore')) {
      return 'Item Restored'
    } else {
      return action
    }
  }

  // Get default message based on action
  const getDefaultMessage = (action) => {
    const actionLower = action.toLowerCase()
    
    if (actionLower.includes('login')) {
      return 'User has logged into the system'
    } else if (actionLower.includes('logout')) {
      return 'User has logged out of the system'
    } else if (actionLower.includes('create') && actionLower.includes('item')) {
      return 'A new item has been added to the inventory'
    } else if (actionLower.includes('create') && actionLower.includes('user')) {
      return 'A new user account has been created'
    } else if (actionLower.includes('update') && actionLower.includes('item')) {
      return 'Item details have been updated'
    } else if (actionLower.includes('update') && actionLower.includes('user')) {
      return 'User profile has been updated'
    } else if (actionLower.includes('delete') && actionLower.includes('item')) {
      return 'An item has been deleted from the inventory'
    } else if (actionLower.includes('delete') && actionLower.includes('user')) {
      return 'A user account has been deleted'
    } else if (actionLower.includes('borrow')) {
      return 'An item has been borrowed'
    } else if (actionLower.includes('return')) {
      return 'An item has been returned'
    } else if (actionLower.includes('restore')) {
      return 'A deleted item has been restored'
    } else {
      return `Action performed: ${action}`
    }
  }

  // Get notification priority based on action
  const getNotificationPriority = (action) => {
    const actionLower = action.toLowerCase()
    
    if (actionLower.includes('delete') || actionLower.includes('logout')) {
      return 'high'
    } else if (actionLower.includes('create') || actionLower.includes('borrow')) {
      return 'medium'
    } else {
      return 'low'
    }
  }

  // Mark notification as read
  const markAsRead = (notificationId) => {
    const notification = notifications.value.find(n => n.id === notificationId)
    if (notification) {
      notification.isRead = true
      unreadCount.value = notifications.value.filter(n => !n.isRead).length
    }
  }

  // Mark all notifications as read
  const markAllAsRead = () => {
    notifications.value.forEach(notification => {
      notification.isRead = true
    })
    unreadCount.value = 0
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
    await fetchNotifications(5) // Refresh with 5 notifications for dropdown
  }

  return {
    notifications,
    loading,
    error,
    unreadCount,
    fetchNotifications,
    refreshNotifications,
    markAsRead,
    markAllAsRead,
    getNotificationsByType,
    getRecentNotifications,
    getUnreadNotifications
  }
}
