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
        notifications.value = response.data.data.map(notification => ({
          id: notification.id,
          type: notification.type || 'low_stock',
          title: notification.title || 'Low Stock Alert',
          message: notification.message,
          user: notification.item?.unit || 'System',
          role: 'System',
          timestamp: notification.timestamp || notification.created_at,
          date: notification.date,
          time: notification.time,
          action: 'Low Stock Alert',
          isRead: notification.isRead ?? false,
          priority: notification.priority || 'high',
          item: notification.item
        }))
        
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
    getUnreadNotifications
  }
}
