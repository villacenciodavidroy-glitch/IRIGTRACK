import { ref, onMounted } from 'vue'
import axiosClient from '../axios'
import { useRouter } from 'vue-router'

export default function useAuth() {
  const user = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const router = useRouter()

  const fetchCurrentUser = async () => {
    loading.value = true
    error.value = null
    
    try {
      const response = await axiosClient.get('/user')
      user.value = response.data
      console.log('Current user:', user.value)
    } catch (err) {
      console.error('Error fetching current user:', err)
      error.value = err.response?.data?.message || 'Failed to fetch user data'
    } finally {
      loading.value = false
    }
  }

  const getUserDisplayName = () => {
    if (!user.value) return 'User'
    
    // Return fullname if available, otherwise fallback to username
    return user.value.fullname || user.value.username || 'User'
  }

  const isAuthenticated = () => {
    return !!user.value && !!localStorage.getItem('token')
  }

  const isAdmin = () => {
    if (!user.value) return false
    const role = (user.value.role || '').toLowerCase()
    return role === 'admin' || role === 'super_admin'
  }

  const hasRole = (role) => {
    if (!user.value) return false
    return (user.value.role || '').toLowerCase() === role.toLowerCase()
  }

  const logout = async () => {
    try {
      loading.value = true
      
      // Call the logout API endpoint
      await axiosClient.post('/logout')
      
      // Clear local storage
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('userId')
      
      // Clear axios default headers
      delete axiosClient.defaults.headers.common['Authorization']
      
      // Clear user data
      user.value = null
      
      // Redirect to login page
      await router.push('/login')
      
      console.log('User logged out successfully')
    } catch (err) {
      console.error('Error during logout:', err)
      
      // Even if API call fails, clear local data and redirect
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('userId')
      delete axiosClient.defaults.headers.common['Authorization']
      user.value = null
      
      await router.push('/login')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    fetchCurrentUser()
  })

  return {
    user,
    loading,
    error,
    fetchCurrentUser,
    getUserDisplayName,
    logout,
    isAuthenticated,
    isAdmin,
    hasRole
  }
}
