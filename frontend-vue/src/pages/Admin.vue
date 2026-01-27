<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import ConfirmationModal from '../components/ConfirmationModal.vue'
import SuccessModal from '../components/SuccessModal.vue'
import { useDebouncedRef } from '../composables/useDebounce'

// Stats data
const stats = ref({
  admins: 0,
  users: 0,
  supply: 0
})

// Account data
const adminAccounts = ref([])
const userAccounts = ref([])
const supplyAccounts = ref([])

const adminSearchQuery = ref('')
const debouncedAdminSearchQuery = useDebouncedRef(adminSearchQuery, 300)
const userSearchQuery = ref('')
const debouncedUserSearchQuery = useDebouncedRef(userSearchQuery, 300)
const supplySearchQuery = ref('')
const debouncedSupplySearchQuery = useDebouncedRef(supplySearchQuery, 300)
const currentAdminPage = ref(1)
const currentUserPage = ref(1)
const currentSupplyPage = ref(1)
const itemsPerPage = ref(5)
let number = ref(1)

const loading = ref(false)

// Fetch all users from the API
const fetchUsers = async () => {
  loading.value = true
  try {
    console.log('Fetching users...')
    const response = await axiosClient.get('/users')
    console.log('Complete API Response:', response)
    console.log('Response status:', response.status)
    console.log('Response headers:', response.headers)
    
    // Check if response has data
    if (response.data) {
      console.log('Raw response.data:', JSON.stringify(response.data, null, 2))
      
      // Handle both array and object with data property
      const users = Array.isArray(response.data) ? response.data : (response.data.data || [])
      console.log('Processed users array:', JSON.stringify(users, null, 2))

      // Log each user's role for debugging
      let no = 0
      users.forEach(user => {
        console.log(`User: ${user.image || 'image'} - Role: ${user.role || 'no role'}, Name: ${user.fullname || 'no name'}, No: ${number+=1}`)
      })


      // Filter users based on role, handle case-insensitive comparison
      adminAccounts.value = users.filter(user => {
        const hasRole = user.role?.toLowerCase() === 'admin'
        console.log(`Checking user ${user.id} for admin role:`, hasRole)
        return hasRole
      })
      userAccounts.value = users.filter(user => {
        const hasRole = user.role?.toLowerCase() === 'user'
        console.log(`Checking user ${user.id} for user role:`, hasRole)
        return hasRole
      })
      supplyAccounts.value = users.filter(user => {
        const hasRole = user.role?.toLowerCase() === 'supply'
        console.log(`Checking user ${user.id} for supply role:`, hasRole)
        return hasRole
      })
      
      console.log('Filtered admin accounts:', adminAccounts.value)
      console.log('Filtered user accounts:', userAccounts.value)
      console.log('Filtered supply accounts:', supplyAccounts.value)
      
      // Update stats
      stats.value.admins = adminAccounts.value.length
      stats.value.users = userAccounts.value.length
      stats.value.supply = supplyAccounts.value.length
      
      console.log('Updated stats:', stats.value)
    } else {
      console.error('No data in API response')
      adminAccounts.value = []
      userAccounts.value = []
      supplyAccounts.value = []
      stats.value.admins = 0
      stats.value.users = 0
      stats.value.supply = 0
    }
  } catch (error) {
    console.error('Error fetching users:', error)
    if (error.response) {
      console.error('Error response data:', error.response.data)
      console.error('Error response status:', error.response.status)
      console.error('Error response headers:', error.response.headers)
    }
    adminAccounts.value = []
    userAccounts.value = []
    supplyAccounts.value = []
    stats.value.admins = 0
    stats.value.users = 0
    stats.value.supply = 0
  } finally {
    loading.value = false
  }
}

// State for reassign modal
const showReassignModal = ref(false)
const userToDelete = ref(null)
const newUserId = ref('')
const itemCount = ref(0)
const reassignLoading = ref(false)
const availableUsers = ref([])

// State for confirmation modal
const showConfirmModal = ref(false)
const userToConfirmDelete = ref(null)
const confirmLoading = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// State for activity log modal
const showActivityLogModal = ref(false)
const selectedUser = ref(null)
const activityLogs = ref([])
const activityLogsLoading = ref(false)
const activityLogsPage = ref(1)
const activityLogsPerPage = ref(10)
const activityLogsTotal = ref(0)

// Fetch users for reassignment dropdown
const fetchAvailableUsers = async (excludeUserId) => {
  try {
    const response = await axiosClient.get('/users')
    if (response.data && Array.isArray(response.data.data)) {
      // Filter out the user being deleted
      availableUsers.value = response.data.data.filter(user => user.id != excludeUserId)
    } else if (response.data && Array.isArray(response.data)) {
      availableUsers.value = response.data.filter(user => user.id != excludeUserId)
    } else {
      availableUsers.value = []
    }
  } catch (error) {
    console.error('Error fetching available users:', error)
    availableUsers.value = []
  }
}

// Delete user function
const deleteUser = async (userId) => {
  userToConfirmDelete.value = userId
  showConfirmModal.value = true
}

// Confirm delete user function
const confirmDeleteUser = async () => {
  if (!userToConfirmDelete.value) return
  
  try {
    confirmLoading.value = true
    
    // Send delete request to API
    const response = await axiosClient.delete(`/users/${userToConfirmDelete.value}`)
    
    // Show success message
    successMessage.value = response.data?.message || 'User deleted successfully'
    showSuccessModal.value = true
    
    // Refresh the users list
    await fetchUsers()
    
    // Close modal
    closeConfirmModal()
  } catch (error) {
    console.error('Error deleting user:', error)
    
    // Check if the error is due to associated items
    if (error.response?.data?.status === 'error' && 
        error.response?.data?.itemCount > 0) {
      
      // Close confirmation modal
      closeConfirmModal()
      
      // Set up the reassign modal
      userToDelete.value = error.response.data.userId
      itemCount.value = error.response.data.itemCount
      
      // Fetch available users for reassignment
      await fetchAvailableUsers(userToDelete.value)
      
      // Show the modal
      showReassignModal.value = true
    } else {
      // Show error message to user
      if (error.response?.data?.message) {
        showErrorModal(error.response.data.message)
      } else {
        showErrorModal('Failed to delete user. Please try again.')
      }
    }
  } finally {
    confirmLoading.value = false
  }
}

// Close confirmation modal
const closeConfirmModal = () => {
  showConfirmModal.value = false
  userToConfirmDelete.value = null
  confirmLoading.value = false
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

// Show error modal
const showErrorModal = (message) => {
  successMessage.value = message
  successModalType.value = 'error'
  showSuccessModal.value = true
}

// Show warning modal
const showWarningModal = (message) => {
  successMessage.value = message
  successModalType.value = 'warning'
  showSuccessModal.value = true
}

// Reassign items and then delete user
const reassignAndDelete = async () => {
  if (!newUserId.value) {
    showWarningModal('Please select a user to reassign items to')
    return
  }
  
  try {
    reassignLoading.value = true
    
    // First reassign the items
    const reassignResponse = await axiosClient.post(`/users/${userToDelete.value}/reassign-items`, {
      new_user_id: newUserId.value
    })
    
    // Then delete the user
    const deleteResponse = await axiosClient.delete(`/users/${userToDelete.value}`)
    
    // Show success message
    successMessage.value = 'Items reassigned and user deleted successfully'
    showSuccessModal.value = true
    
    // Close the modal and reset
    closeReassignModal()
    
    // Refresh the users list
    await fetchUsers()
  } catch (error) {
    console.error('Error during reassign and delete:', error)
    showErrorModal(error.response?.data?.message || 'Failed to reassign items and delete user')
  } finally {
    reassignLoading.value = false
  }
}

// Close the reassign modal
const closeReassignModal = () => {
  showReassignModal.value = false
  userToDelete.value = null
  newUserId.value = ''
  itemCount.value = 0
}

// Edit user function is now handled by router-link

// Safe date formatting function
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return 'N/A'
  return date.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' })
}

const filteredAdminAccounts = computed(() => {
  const query = debouncedAdminSearchQuery.value?.toLowerCase().trim()
  if (!query) return adminAccounts.value
  
  // Optimize search: only search relevant fields
  return adminAccounts.value.filter(account => {
    return (
      account.fullname?.toLowerCase().includes(query) ||
      account.email?.toLowerCase().includes(query) ||
      account.location?.toLowerCase().includes(query)
    )
  })
})

const filteredUserAccounts = computed(() => {
  const query = debouncedUserSearchQuery.value?.toLowerCase().trim()
  if (!query) return userAccounts.value
  
  // Optimize search: only search relevant fields
  return userAccounts.value.filter(account => {
    return (
      account.fullname?.toLowerCase().includes(query) ||
      account.email?.toLowerCase().includes(query) ||
      account.location?.toLowerCase().includes(query)
    )
  })
})

const filteredSupplyAccounts = computed(() => {
  const query = debouncedSupplySearchQuery.value?.toLowerCase().trim()
  if (!query) return supplyAccounts.value
  
  // Optimize search: only search relevant fields
  return supplyAccounts.value.filter(account => {
    return (
      account.fullname?.toLowerCase().includes(query) ||
      account.email?.toLowerCase().includes(query) ||
      account.location?.toLowerCase().includes(query)
    )
  })
})

// Reset to first page when search query changes
watch(debouncedAdminSearchQuery, () => {
  currentAdminPage.value = 1
})

watch(debouncedUserSearchQuery, () => {
  currentUserPage.value = 1
})

watch(debouncedSupplySearchQuery, () => {
  currentSupplyPage.value = 1
})

// Admin pagination
const totalAdminPages = computed(() => {
  return Math.ceil(filteredAdminAccounts.value.length / itemsPerPage.value)
})

const paginatedAdminAccounts = computed(() => {
  const start = (currentAdminPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredAdminAccounts.value.slice(start, end)
})

const adminStartIndex = computed(() => {
  return (currentAdminPage.value - 1) * itemsPerPage.value + 1
})

const adminEndIndex = computed(() => {
  return Math.min(currentAdminPage.value * itemsPerPage.value, filteredAdminAccounts.value.length)
})

// User pagination
const totalUserPages = computed(() => {
  return Math.ceil(filteredUserAccounts.value.length / itemsPerPage.value)
})

const paginatedUserAccounts = computed(() => {
  const start = (currentUserPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredUserAccounts.value.slice(start, end)
})

const userStartIndex = computed(() => {
  return (currentUserPage.value - 1) * itemsPerPage.value + 1
})

const userEndIndex = computed(() => {
  return Math.min(currentUserPage.value * itemsPerPage.value, filteredUserAccounts.value.length)
})

// Supply pagination
const totalSupplyPages = computed(() => {
  return Math.ceil(filteredSupplyAccounts.value.length / itemsPerPage.value)
})

const paginatedSupplyAccounts = computed(() => {
  const start = (currentSupplyPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredSupplyAccounts.value.slice(start, end)
})

const supplyStartIndex = computed(() => {
  return (currentSupplyPage.value - 1) * itemsPerPage.value + 1
})

const supplyEndIndex = computed(() => {
  return Math.min(currentSupplyPage.value * itemsPerPage.value, filteredSupplyAccounts.value.length)
})

const router = useRouter()

const addNewAdmin = () => {
  router.push({ 
    path: '/add-account',
    query: { type: 'admin' }
  })
}

const addAdmin = () => {
  router.push({ 
    path: '/add-account',
    query: { type: 'admin' }
  })
}

const addUser = () => {
  router.push({ 
    path: '/add-account',
    query: { type: 'user' }
  })
}

const addSupply = () => {
  router.push({ 
    path: '/add-account',
    query: { type: 'supply' }
  })
}

// Pagination methods
const goToAdminPage = (page) => {
  if (page >= 1 && page <= totalAdminPages.value) {
    currentAdminPage.value = page
  }
}

const goToUserPage = (page) => {
  if (page >= 1 && page <= totalUserPages.value) {
    currentUserPage.value = page
  }
}

const goToSupplyPage = (page) => {
  if (page >= 1 && page <= totalSupplyPages.value) {
    currentSupplyPage.value = page
  }
}

// View user activity logs
const viewUserActivity = async (user) => {
  selectedUser.value = user
  showActivityLogModal.value = true
  activityLogsPage.value = 1
  await fetchUserActivityLogs(user.id)
}

// Fetch activity logs for a specific user
const fetchUserActivityLogs = async (userId, page = 1) => {
  activityLogsLoading.value = true
  try {
    const response = await axiosClient.get('/activity-logs', {
      params: {
        user_id: userId,
        page: page,
        per_page: activityLogsPerPage.value
      }
    })
    
    if (response.data && response.data.success) {
      activityLogs.value = response.data.data || []
      if (response.data.pagination) {
        activityLogsTotal.value = response.data.pagination.total || 0
      }
    }
  } catch (error) {
    console.error('Error fetching activity logs:', error)
    activityLogs.value = []
    showErrorModal('Failed to load activity logs. Please try again.')
  } finally {
    activityLogsLoading.value = false
  }
}

// Close activity log modal
const closeActivityLogModal = () => {
  showActivityLogModal.value = false
  selectedUser.value = null
  activityLogs.value = []
  activityLogsPage.value = 1
}

// Pagination for activity logs
const goToActivityLogPage = (page) => {
  if (selectedUser.value) {
    activityLogsPage.value = page
    fetchUserActivityLogs(selectedUser.value.id, page)
  }
}

// Format activity log date
const formatActivityDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return 'N/A'
  return date.toLocaleDateString('en-PH', { 
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Format activity log time
const formatActivityTime = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  if (isNaN(date.getTime())) return 'N/A'
  return date.toLocaleTimeString('en-PH', { 
    timeZone: 'Asia/Manila',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Get image URL with proper fallback
const getImageUrl = (imagePath) => {
  if (!imagePath || imagePath.trim() === '') {
    return '/images/default-avatar.png'
  }
  
  // If it's already a full URL, use it directly
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath
  }
  
  // If it's a relative path starting with /, use it as is
  if (imagePath.startsWith('/')) {
    return imagePath
  }
  
  // Otherwise, assume it's a storage path
  return imagePath
}

// Handle image error - fallback to default avatar
const handleImageError = (event) => {
  const defaultAvatar = '/images/default-avatar.png'
  if (event.target.src !== defaultAvatar && !event.target.src.includes('default-avatar')) {
    event.target.src = defaultAvatar
  } else {
    // If default avatar also fails, hide image and show icon placeholder
    event.target.onerror = null // Prevent infinite loop
    event.target.style.display = 'none'
    // The background gradient will show as fallback
  }
}


// Fetch users when component is mounted
onMounted(() => {
  fetchUsers()
  console.log(paginatedAdminAccounts.value)
})
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
      <!-- Enhanced Header Section -->
      <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
        <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
        <div class="relative px-6 py-8 sm:px-8 sm:py-10">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0">
            <div class="flex items-center gap-4">
              <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
                <span class="material-icons-outlined text-4xl text-white">admin_panel_settings</span>
              </div>
              <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Admin Management</h1>
                <p class="text-green-100 text-sm sm:text-base">Manage administrators and user accounts</p>
              </div>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto">
              <button 
                @click="addNewAdmin" 
                class="btn-primary-enhanced flex-1 sm:flex-auto justify-center shadow-lg"
              >
                <span class="material-icons-outlined text-lg mr-1.5">add_circle</span>
                <span>Add New Account</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700 p-6 overflow-hidden relative group">
          <div class="absolute top-0 right-0 w-24 h-24 bg-green-500/10 rounded-bl-full"></div>
          <div class="relative flex items-center justify-between">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Admin Accounts</p>
              <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
                <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
                <span v-else>{{ stats.admins }}</span>
              </h3>
              <p class="text-xs text-gray-600 dark:text-gray-400">System administrators</p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
              <span class="material-icons-outlined text-white text-3xl">admin_panel_settings</span>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700 p-6 overflow-hidden relative group">
          <div class="absolute top-0 right-0 w-24 h-24 bg-green-500/10 rounded-bl-full"></div>
          <div class="relative flex items-center justify-between">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">User Accounts</p>
              <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
                <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
                <span v-else>{{ stats.users }}</span>
              </h3>
              <p class="text-xs text-gray-600 dark:text-gray-400">Regular users</p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
              <span class="material-icons-outlined text-white text-3xl">group</span>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700 p-6 overflow-hidden relative group">
          <div class="absolute top-0 right-0 w-24 h-24 bg-green-500/10 rounded-bl-full"></div>
          <div class="relative flex items-center justify-between">
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Supply Accounts</p>
              <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
                <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
                <span v-else>{{ stats.supply }}</span>
              </h3>
              <p class="text-xs text-gray-600 dark:text-gray-400">Supply management</p>
            </div>
            <div class="p-4 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
              <span class="material-icons-outlined text-white text-3xl">inventory_2</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin Accounts Section -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
              <span class="material-icons-outlined text-white text-2xl">admin_panel_settings</span>
              <h2 class="text-xl font-bold text-white">Admin Accounts</h2>
            </div>
            <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
              <span class="text-sm font-semibold text-white">{{ filteredAdminAccounts.length }} accounts</span>
            </div>
          </div>
        </div>

        <!-- Enhanced Search Bar -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
            </div>
            <input
              v-model="adminSearchQuery"
              type="text"
              placeholder="Search by name, email, or unit/sections..."
              class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium"
            >
            <div v-if="adminSearchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
              <button @click="adminSearchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons-outlined text-lg">close</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Enhanced Admin Table -->
        <div class="overflow-x-auto">
          <template v-if="loading">
            <div class="text-center py-20">
              <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                <span class="material-icons-outlined animate-spin text-5xl text-green-400 dark:text-green-400">refresh</span>
              </div>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading admin accounts...</p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
            </div>
          </template>
          <table v-else-if="paginatedAdminAccounts.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Full Name</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">User Code</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Status</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">No.</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Date</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Unit/Sections</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="(account, index) in paginatedAdminAccounts" 
                :key="index" 
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
              >
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div class="flex items-center gap-4">
                    <div class="relative flex-shrink-0">
                      <img 
                        :src="getImageUrl(account.image)"
                        @error="handleImageError($event)"
                        class="h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-gradient-to-br from-green-900 to-green-800 object-cover object-center border-2 border-green-600 shadow-md group-hover:shadow-lg transition-all group-hover:scale-110"
                        :alt="account.fullname || 'User'"
                        loading="lazy"
                      >
                      <div class="absolute -top-1 -right-1 w-3 h-3 sm:w-4 sm:h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ account.fullname }}</div>
                      <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 truncate max-w-[200px]">{{ account.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="text-sm font-mono text-gray-900 dark:text-white">
                    {{ account.user_code || 'N/A' }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span
                    :class="{
                      'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': account.status === 'ACTIVE',
                      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': account.status === 'INACTIVE',
                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': account.status === 'RESIGNED'
                    }"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                  >
                    {{ account.status || 'ACTIVE' }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="inline-flex items-center justify-center px-3 py-1 rounded-lg text-sm font-bold bg-purple-900 dark:bg-purple-900 text-purple-300 dark:text-purple-300">
                    {{ adminStartIndex + index }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white">{{ formatDate(account.created_at) }}</td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-900 dark:bg-green-900 text-green-300 dark:text-green-300">
                    <span class="material-icons-outlined text-sm">location_on</span>
                    {{ account.location || 'N/A' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex gap-2">
                    <button 
                      @click="viewUserActivity(account)" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="View Activity"
                    >
                      <span class="material-icons-outlined text-base">visibility</span>
                    </button>
                    <router-link 
                      :to="`/edit-account/${account.id}`" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="Edit Account"
                    >
                      <span class="material-icons-outlined text-base">edit</span>
                    </router-link>
                    <button 
                      @click="deleteUser(account.id)" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200"
                      :disabled="loading"
                      title="Delete Account"
                    >
                      <span class="material-icons-outlined text-base" v-if="!loading">delete</span>
                      <span class="material-icons-outlined text-base animate-spin" v-else>refresh</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="text-center py-20">
            <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
              <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">admin_panel_settings</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No admin accounts found</h3>
            <p class="text-gray-600 dark:text-gray-400">{{ adminSearchQuery ? 'Try adjusting your search query' : 'Create your first admin account!' }}</p>
          </div>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="!loading && paginatedAdminAccounts.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-lg text-gray-700 dark:text-gray-300">info</span>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Showing <span class="font-bold text-gray-900 dark:text-white">{{ adminStartIndex }}</span> to 
                <span class="font-bold text-gray-900 dark:text-white">{{ adminEndIndex }}</span> of 
                <span class="font-bold text-gray-900 dark:text-white">{{ filteredAdminAccounts.length }}</span> entries
              </span>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
              <button 
                @click="goToAdminPage(1)"
                :disabled="currentAdminPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">first_page</span>
              </button>
              <button 
                @click="goToAdminPage(currentAdminPage - 1)"
                :disabled="currentAdminPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <div class="flex items-center gap-1">
                <button 
                  v-for="page in totalAdminPages" 
                  :key="page"
                  @click="goToAdminPage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                    currentAdminPage === page 
                      ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                      : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
              <button 
                @click="goToAdminPage(currentAdminPage + 1)"
                :disabled="currentAdminPage === totalAdminPages"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
              <button 
                @click="goToAdminPage(totalAdminPages)"
                :disabled="currentAdminPage === totalAdminPages"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">last_page</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- User Accounts Section -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
              <span class="material-icons-outlined text-white text-2xl">group</span>
              <h2 class="text-xl font-bold text-white">User Accounts</h2>
            </div>
            <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
              <span class="text-sm font-semibold text-white">{{ filteredUserAccounts.length }} accounts</span>
            </div>
          </div>
        </div>

        <!-- Enhanced Search Bar -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
            </div>
            <input
              v-model="userSearchQuery"
              type="text"
              placeholder="Search by name, email, or unit/sections..."
              class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium"
            >
            <div v-if="userSearchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
              <button @click="userSearchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons-outlined text-lg">close</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Enhanced User Table -->
        <div class="overflow-x-auto">
          <template v-if="loading">
            <div class="text-center py-20">
              <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                <span class="material-icons-outlined animate-spin text-5xl text-green-400 dark:text-green-400">refresh</span>
              </div>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading user accounts...</p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
            </div>
          </template>
          <table v-else-if="paginatedUserAccounts.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Full Name</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">No.</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Date</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Unit/Sections</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="(account, index) in paginatedUserAccounts" 
                :key="index" 
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
              >
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div class="flex items-center gap-4">
                    <div class="relative flex-shrink-0">
                      <img 
                        :src="account.image || '/images/default-avatar.png'" 
                        @error="handleImageError($event)"
                        class="h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-gradient-to-br from-green-900 to-green-800 object-cover object-center border-2 border-green-600 shadow-md group-hover:shadow-lg transition-all group-hover:scale-110"
                        :alt="account.fullname"
                        loading="lazy"
                      >
                      <div class="absolute -top-1 -right-1 w-3 h-3 sm:w-4 sm:h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ account.fullname }}</div>
                      <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 truncate max-w-[200px]">{{ account.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="text-sm font-mono text-gray-900 dark:text-white">
                    {{ account.user_code || 'N/A' }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span
                    :class="{
                      'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': account.status === 'ACTIVE',
                      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': account.status === 'INACTIVE',
                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': account.status === 'RESIGNED'
                    }"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                  >
                    {{ account.status || 'ACTIVE' }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="inline-flex items-center justify-center px-3 py-1 rounded-lg text-sm font-bold bg-purple-900 dark:bg-purple-900 text-purple-300 dark:text-purple-300">
                    {{ userStartIndex + index }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white">{{ formatDate(account.created_at) }}</td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-900 dark:bg-green-900 text-green-300 dark:text-green-300">
                    <span class="material-icons-outlined text-sm">location_on</span>
                    {{ account.location || 'N/A' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex gap-2">
                    <button 
                      @click="viewUserActivity(account)" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="View Activity"
                    >
                      <span class="material-icons-outlined text-base">visibility</span>
                    </button>
                    <router-link 
                      :to="`/edit-account/${account.id}`" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="Edit Account"
                    >
                      <span class="material-icons-outlined text-base">edit</span>
                    </router-link>
                    <button 
                      @click="deleteUser(account.id)" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200"
                      :disabled="loading"
                      title="Delete Account"
                    >
                      <span class="material-icons-outlined text-base" v-if="!loading">delete</span>
                      <span class="material-icons-outlined text-base animate-spin" v-else>refresh</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="text-center py-20">
            <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
              <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">group</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No user accounts found</h3>
            <p class="text-gray-600 dark:text-gray-400">{{ userSearchQuery ? 'Try adjusting your search query' : 'Create your first user account!' }}</p>
          </div>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="!loading && paginatedUserAccounts.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-lg text-gray-700 dark:text-gray-300">info</span>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Showing <span class="font-bold text-gray-900 dark:text-white">{{ userStartIndex }}</span> to 
                <span class="font-bold text-gray-900 dark:text-white">{{ userEndIndex }}</span> of 
                <span class="font-bold text-gray-900 dark:text-white">{{ filteredUserAccounts.length }}</span> entries
              </span>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
              <button 
                @click="goToUserPage(1)"
                :disabled="currentUserPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">first_page</span>
              </button>
              <button 
                @click="goToUserPage(currentUserPage - 1)"
                :disabled="currentUserPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <div class="flex items-center gap-1">
                <button 
                  v-for="page in totalUserPages" 
                  :key="page"
                  @click="goToUserPage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                    currentUserPage === page 
                      ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                      : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
              <button 
                @click="goToUserPage(currentUserPage + 1)"
                :disabled="currentUserPage === totalUserPages"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
              <button 
                @click="goToUserPage(totalUserPages)"
                :disabled="currentUserPage === totalUserPages"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">last_page</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Supply Accounts Section -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-3">
              <span class="material-icons-outlined text-white text-2xl">inventory_2</span>
              <h2 class="text-xl font-bold text-white">Supply Accounts</h2>
            </div>
            <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
              <span class="text-sm font-semibold text-white">{{ filteredSupplyAccounts.length }} accounts</span>
            </div>
          </div>
        </div>

        <!-- Enhanced Search Bar -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
            </div>
            <input
              v-model="supplySearchQuery"
              type="text"
              placeholder="Search by name, email, or unit/sections..."
              class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium"
            >
            <div v-if="supplySearchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
              <button @click="supplySearchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons-outlined text-lg">close</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Enhanced Supply Table -->
        <div class="overflow-x-auto">
          <template v-if="loading">
            <div class="text-center py-20">
              <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                <span class="material-icons-outlined animate-spin text-5xl text-green-400 dark:text-green-400">refresh</span>
              </div>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading supply accounts...</p>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
            </div>
          </template>
          <table v-else-if="paginatedSupplyAccounts.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Full Name</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">No.</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Date</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Unit/Sections</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="(account, index) in paginatedSupplyAccounts" 
                :key="index" 
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
              >
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div class="flex items-center gap-4">
                    <div class="relative flex-shrink-0">
                      <img 
                        :src="account.image || '/images/default-avatar.png'" 
                        @error="handleImageError($event)"
                        class="h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-gradient-to-br from-green-900 to-green-800 object-cover object-center border-2 border-green-600 shadow-md group-hover:shadow-lg transition-all group-hover:scale-110"
                        :alt="account.fullname"
                        loading="lazy"
                      >
                      <div class="absolute -top-1 -right-1 w-3 h-3 sm:w-4 sm:h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ account.fullname }}</div>
                      <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 truncate max-w-[200px]">{{ account.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="inline-flex items-center justify-center px-3 py-1 rounded-lg text-sm font-bold bg-purple-900 dark:bg-purple-900 text-purple-300 dark:text-purple-300">
                    {{ supplyStartIndex + index }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white">{{ formatDate(account.created_at) }}</td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-900 dark:bg-green-900 text-green-300 dark:text-green-300">
                    <span class="material-icons-outlined text-sm">location_on</span>
                    {{ account.location || 'N/A' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex gap-2">
                    <button 
                      @click="viewUserActivity(account)" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="View Activity"
                    >
                      <span class="material-icons-outlined text-base">visibility</span>
                    </button>
                    <router-link 
                      :to="`/edit-account/${account.id}`" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="Edit Account"
                    >
                      <span class="material-icons-outlined text-base">edit</span>
                    </router-link>
                    <button 
                      @click="deleteUser(account.id)" 
                      class="p-2.5 rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200"
                      :disabled="loading"
                      title="Delete Account"
                    >
                      <span class="material-icons-outlined text-base" v-if="!loading">delete</span>
                      <span class="material-icons-outlined text-base animate-spin" v-else>refresh</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="text-center py-20">
            <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
              <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">inventory_2</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No supply accounts found</h3>
            <p class="text-gray-600 dark:text-gray-400">{{ supplySearchQuery ? 'Try adjusting your search query' : 'Create your first supply account!' }}</p>
          </div>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="!loading && paginatedSupplyAccounts.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-lg text-gray-700 dark:text-gray-300">info</span>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Showing <span class="font-bold text-gray-900 dark:text-white">{{ supplyStartIndex }}</span> to 
                <span class="font-bold text-gray-900 dark:text-white">{{ supplyEndIndex }}</span> of 
                <span class="font-bold text-gray-900 dark:text-white">{{ filteredSupplyAccounts.length }}</span> entries
              </span>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
              <button 
                @click="goToSupplyPage(1)"
                :disabled="currentSupplyPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">first_page</span>
              </button>
              <button 
                @click="goToSupplyPage(currentSupplyPage - 1)"
                :disabled="currentSupplyPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <div class="flex items-center gap-1">
                <button 
                  v-for="page in totalSupplyPages" 
                  :key="page"
                  @click="goToSupplyPage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                    currentSupplyPage === page 
                      ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                      : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
              <button 
                @click="goToSupplyPage(currentSupplyPage + 1)"
                :disabled="currentSupplyPage === totalSupplyPages"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
              <button 
                @click="goToSupplyPage(totalSupplyPages)"
                :disabled="currentSupplyPage === totalSupplyPages"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">last_page</span>
              </button>
            </div>
          </div>
        </div>
      </div>

    <!-- Enhanced Reassign Items Modal -->
    <div v-if="showReassignModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="closeReassignModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-md w-full overflow-hidden animate-modalSlideIn">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">swap_horiz</span>
              </div>
              <h3 class="text-xl font-bold text-white">Reassign Items</h3>
            </div>
            <button @click="closeReassignModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6 bg-white dark:bg-gray-800">
          <div class="mb-6">
            <div class="flex items-center gap-3 mb-4 p-4 bg-green-900/20 dark:bg-green-900/20 border-l-4 border-green-500 dark:border-green-500 rounded-r-lg">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">info</span>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                This user has <span class="font-bold text-green-400 dark:text-green-400">{{ itemCount }}</span> associated items. Please select another user to reassign these items to before deleting.
              </p>
            </div>
            
            <div class="mb-4">
              <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Select User to Reassign Items</label>
              <select 
                v-model="newUserId"
                class="w-full bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md"
                required
              >
                <option value="" disabled class="bg-white dark:bg-gray-700 text-gray-900 dark:text-white">Select a user</option>
                <option 
                  v-for="user in availableUsers" 
                  :key="user.id" 
                  :value="user.id"
                  class="bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                >
                  {{ user.fullname }} ({{ user.role }})
                </option>
              </select>
            </div>
          </div>
          
          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button 
              @click="closeReassignModal"
              class="px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-gray-500 transition-all shadow-sm"
              :disabled="reassignLoading"
            >
              Cancel
            </button>
            <button 
              @click="reassignAndDelete"
              class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transition-all"
              :disabled="!newUserId || reassignLoading"
            >
              <span v-if="reassignLoading" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">swap_horiz</span>
              {{ reassignLoading ? 'Processing...' : 'Reassign & Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity Log Modal -->
    <div v-if="showActivityLogModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="closeActivityLogModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-4xl w-full max-h-[90vh] overflow-hidden animate-modalSlideIn flex flex-col">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 flex-shrink-0">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">visibility</span>
              </div>
              <div>
                <h3 class="text-xl font-bold text-white">User Activity Log</h3>
                <p v-if="selectedUser" class="text-sm text-blue-100 mt-1">{{ selectedUser.fullname }} ({{ selectedUser.email }})</p>
              </div>
            </div>
            <button @click="closeActivityLogModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6 bg-white dark:bg-gray-800">
          <!-- Loading State -->
          <div v-if="activityLogsLoading" class="text-center py-20">
            <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
              <span class="material-icons-outlined animate-spin text-5xl text-blue-400 dark:text-blue-400">refresh</span>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading activity logs...</p>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
          </div>

          <!-- Activity Logs List -->
          <div v-else-if="activityLogs.length > 0" class="space-y-4">
            <div 
              v-for="log in activityLogs" 
              :key="log.id"
              class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 border-l-4 border-blue-500 hover:shadow-md transition-all"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-2">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                      {{ log.action }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatActivityDate(log.created_at) }} at {{ formatActivityTime(log.created_at) }}
                    </span>
                  </div>
                  <p class="text-sm text-gray-700 dark:text-gray-300 mb-2">{{ log.description || 'No description' }}</p>
                  <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                      <span class="material-icons-outlined text-sm">location_on</span>
                      {{ log.location || 'N/A' }}
                    </span>
                    <span v-if="log.ip_address" class="flex items-center gap-1">
                      <span class="material-icons-outlined text-sm">computer</span>
                      {{ log.ip_address }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else class="text-center py-20">
            <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
              <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">history</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No activity logs found</h3>
            <p class="text-gray-600 dark:text-gray-400">This user hasn't performed any actions yet.</p>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="!activityLogsLoading && activityLogs.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 px-6 py-4 flex-shrink-0">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-lg">info</span>
              <span class="text-sm font-semibold text-gray-900 dark:text-white">
                Total: <span class="text-blue-400 dark:text-blue-400 font-bold">{{ activityLogsTotal }}</span> activities
              </span>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-1.5">
              <button 
                @click="goToActivityLogPage(activityLogsPage - 1)"
                :disabled="activityLogsPage === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-blue-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <span class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white">
                Page {{ activityLogsPage }} of {{ Math.ceil(activityLogsTotal / activityLogsPerPage) }}
              </span>
              <button 
                @click="goToActivityLogPage(activityLogsPage + 1)"
                :disabled="activityLogsPage >= Math.ceil(activityLogsTotal / activityLogsPerPage)"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-blue-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Close Button -->
        <div class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 px-6 py-4 flex-shrink-0">
          <div class="flex justify-end">
            <button 
              @click="closeActivityLogModal"
              class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 font-semibold shadow-lg hover:shadow-xl transition-all"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <ConfirmationModal
      :isOpen="showConfirmModal"
      :isLoading="confirmLoading"
      title="Delete User"
      message="Are you sure you want to delete this user? This action cannot be undone."
      confirmText="Delete User"
      cancelText="Cancel"
      type="danger"
      @confirm="confirmDeleteUser"
      @cancel="closeConfirmModal"
    />

    <!-- Success Modal -->
    <SuccessModal
      :isOpen="showSuccessModal"
      :title="successModalType === 'success' ? 'Success' : successModalType === 'error' ? 'Error' : 'Warning'"
      :message="successMessage"
      buttonText="Continue"
      :type="successModalType"
      @confirm="closeSuccessModal"
      @close="closeSuccessModal"
    />
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 20px;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-4 py-2.5 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Modal animations */
@keyframes modalFadeIn {
  from { 
    opacity: 0; 
  }
  to { 
    opacity: 1; 
  }
}

@keyframes modalSlideIn {
  from { 
    opacity: 0; 
    transform: scale(0.9) translateY(-20px); 
  }
  to { 
    opacity: 1; 
    transform: scale(1) translateY(0); 
  }
}

.animate-modalFadeIn {
  animation: modalFadeIn 0.2s ease-out;
}

.animate-modalSlideIn {
  animation: modalSlideIn 0.3s ease-out;
}

/* Enhanced table row hover effect */
tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  transform: translateX(2px);
}

/* Enhanced scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  @apply bg-gray-700 rounded-full;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  @apply bg-green-400 rounded-full hover:bg-green-500;
}

/* Responsive image styles */
img[class*="rounded-full"] {
  aspect-ratio: 1 / 1;
  max-width: 100%;
  height: auto;
  display: block;
}

/* Ensure images don't overflow their containers */
td img {
  max-width: 100%;
  height: auto;
}

/* Responsive adjustments for smaller screens */
@media (max-width: 640px) {
  td .flex.items-center.gap-4 {
    gap: 0.75rem;
  }
  
  td .font-bold {
    font-size: 0.875rem;
  }
  
  td .text-sm {
    font-size: 0.75rem;
  }
}
</style>
