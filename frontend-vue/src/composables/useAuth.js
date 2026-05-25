import { ref } from 'vue'
import axiosClient from '../axios'
import { useRouter } from 'vue-router'

// Shared auth state (single fetch for the whole app)
const user = ref(null)
const loading = ref(false)
const error = ref(null)
let fetchPromise = null
let initStarted = false

function readStoredUser() {
  try {
    const raw = localStorage.getItem('user')
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function writeStoredUser(data) {
  if (data) {
    localStorage.setItem('user', JSON.stringify(data))
  } else {
    localStorage.removeItem('user')
  }
}

// Hydrate immediately so UI is not blocked on first paint
const storedUser = readStoredUser()
if (storedUser) {
  user.value = storedUser
}

async function fetchCurrentUser(options = {}) {
  const { force = false, silent = false } = options

  if (!force && user.value && fetchPromise) {
    return fetchPromise
  }

  if (!force && user.value && !silent) {
    fetchCurrentUser({ silent: true })
    return user.value
  }

  if (fetchPromise) {
    return fetchPromise
  }

  if (!silent) {
    loading.value = !user.value
  }
  error.value = null

  fetchPromise = (async () => {
    try {
      const response = await axiosClient.get('/user')
      user.value = response.data
      writeStoredUser(response.data)
      return user.value
    } catch (err) {
      console.error('Error fetching current user:', err)
      error.value = err.response?.data?.message || 'Failed to fetch user data'
      if (err.response?.status === 401) {
        user.value = null
        writeStoredUser(null)
      }
      throw err
    } finally {
      if (!silent) {
        loading.value = false
      }
      fetchPromise = null
    }
  })()

  return fetchPromise
}

function startAuthInit() {
  if (initStarted || !localStorage.getItem('token')) return
  initStarted = true
  fetchCurrentUser({ silent: !!user.value })
}

export function getCachedUser() {
  return user.value || readStoredUser()
}

export function getCachedUserRole() {
  const u = getCachedUser()
  return (u?.role || '').toLowerCase()
}

startAuthInit()

export default function useAuth() {
  const router = useRouter()

  const getUserDisplayName = () => {
    if (!user.value) return 'User'
    return user.value.fullname || user.value.username || 'User'
  }

  const isAuthenticated = () => {
    return !!user.value && !!localStorage.getItem('token')
  }

  const isAdmin = () => {
    if (!user.value) {
      const role = getCachedUserRole()
      return role === 'admin' || role === 'super_admin'
    }
    const role = (user.value.role || '').toLowerCase()
    return role === 'admin' || role === 'super_admin'
  }

  const hasRole = (role) => {
    const current = user.value || getCachedUser()
    if (!current) return false
    return (current.role || '').toLowerCase() === role.toLowerCase()
  }

  const logout = async () => {
    try {
      loading.value = true
      await axiosClient.post('/logout')
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('userId')
      delete axiosClient.defaults.headers.common['Authorization']
      user.value = null
      initStarted = false
      await router.push('/login')
    } catch (err) {
      console.error('Error during logout:', err)
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('userId')
      delete axiosClient.defaults.headers.common['Authorization']
      user.value = null
      initStarted = false
      await router.push('/login')
    } finally {
      loading.value = false
    }
  }

  return {
    user,
    loading,
    error,
    fetchCurrentUser,
    getUserDisplayName,
    logout,
    isAuthenticated,
    isAdmin,
    hasRole,
  }
}
