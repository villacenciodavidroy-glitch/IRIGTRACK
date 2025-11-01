<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useAuth from '../composables/useAuth'
import useNotifications from '../composables/useNotifications'
import LogoutModal from '../components/LogoutModal.vue'

const router = useRouter()
const route = useRoute()

// Use the auth composable to get user data
const { user, loading: userLoading, error: userError, fetchCurrentUser, getUserDisplayName, logout } = useAuth()

// Use the notifications composable
const { notifications, unreadCount, fetchNotifications, fetchUnreadCount, markAsRead, refreshNotifications, refreshUnreadCount } = useNotifications()

// Handle notification click in dropdown - mark as read and navigate to Analytics
const handleNotificationClick = async (notification) => {
  // Mark as read if not already read
  if (!notification.isRead) {
    await markAsRead(notification.id)
  }
  // Close the dropdown
  isNotificationsOpen.value = false
  // Navigate to Analytics page
  router.push({ name: 'Analytics' })
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

const navigation = [
  { name: 'Dashboard', path: '/dashboard', icon: 'dashboard' },
  { name: 'Inventory', path: '/inventory', icon: 'inventory' },
  { name: 'Admins', path: '/admin', icon: 'people' },
  { name: 'Analytics', path: '/analytics', icon: 'analytics' },
  { name: 'Profile', path: '/profile', icon: 'person' },
  { name: 'Activity Log', path: '/activity-log', icon: 'history' },
  {
    name: 'History',
    icon: 'folder',
    hasSubmenu: true,
    id: 'history',
    submenu: [
      { name: 'Deleted Items', path: '/history/deleted-items', icon: 'delete' },
      { name: 'Deleted Accounts', path: '/history/deleted-accounts', icon: 'person_off' }
    ]
  }
]

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
    'fixed inset-y-0 left-0 w-64 bg-white dark:bg-gray-800 shadow-lg z-20 flex flex-col transform transition-transform duration-300 ease-in-out': true,
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
  
  // Refresh unread count periodically (every 30 seconds) to keep badge updated
  const unreadCountInterval = setInterval(async () => {
    await refreshUnreadCount()
  }, 30000) // 30 seconds
  
  // Store interval ID for cleanup
  window.unreadCountInterval = unreadCountInterval
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeProfileDropdown)
  document.removeEventListener('click', closeNotifications)
  document.removeEventListener('click', closeSidebarOnOutsideClick)
  window.removeEventListener('resize', handleResize)
  
  // Clear unread count refresh interval
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
      <div class="flex items-center px-6 py-4 h-16">
        <img src="../assets/logo.png" alt="IrrigTrack" class="h-8 w-8" />
        <span class="ml-2 text-xl font-semibold text-gray-800 dark:text-green-400">IrrigTrack</span>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-4 py-2 overflow-y-auto">
        <template v-for="item in navigation" :key="item.name">
          <!-- Regular nav items -->
          <router-link
            v-if="!item.hasSubmenu"
            :to="item.path"
            :class="[
              'flex items-center px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base text-gray-700 dark:text-gray-300 rounded-lg mb-1',
              $route.path === item.path
                ? 'bg-green-50 text-green-600 dark:bg-green-900/50 dark:text-green-400'
                : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
            ]"
            @click="isMobile ? isSidebarOpen = false : null"
          >
            <span class="material-icons-outlined mr-2 sm:mr-3 text-current text-xl sm:text-2xl">{{ item.icon }}</span>
            {{ item.name }}
          </router-link>

          <!-- Submenu items -->
          <div v-else class="mb-1">
            <button
              @click="toggleSubmenu(item.id)"
              :class="[
                'w-full flex items-center justify-between px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base text-gray-700 dark:text-gray-300 rounded-lg',
                item.submenu.some(subItem => $route.path === subItem.path) || isSubmenuOpen(item.id)
                  ? 'bg-green-50 text-green-600 dark:bg-green-900/50 dark:text-green-400'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
              ]"
            >
              <div class="flex items-center">
                <span class="material-icons-outlined mr-2 sm:mr-3 text-current text-xl sm:text-2xl">{{ item.icon }}</span>
                {{ item.name }}
              </div>
              <span 
                class="material-icons-outlined text-current text-xl transition-transform duration-200"
                :class="{ 'transform rotate-180': isSubmenuOpen(item.id) }"
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
              <div v-show="isSubmenuOpen(item.id)" class="ml-8 space-y-1 mt-1">
                <router-link
                  v-for="subItem in item.submenu"
                  :key="subItem.name"
                  :to="subItem.path"
                  :class="[
                    'flex items-center px-3 sm:px-4 py-2 sm:py-3 text-sm sm:text-base text-gray-700 dark:text-gray-300 rounded-lg',
                    $route.path === subItem.path
                      ? 'bg-green-50 text-green-600 dark:bg-green-900/50 dark:text-green-400'
                      : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
                  ]"
                  @click="isMobile ? isSidebarOpen = false : null"
                >
                  <span class="material-icons-outlined mr-2 sm:mr-3 text-current text-xl sm:text-2xl">{{ subItem.icon }}</span>
                  {{ subItem.name }}
                </router-link>
              </div>
            </transition>
          </div>
        </template>
      </nav>

      <!-- User Info at Bottom -->
      <div class="mt-auto border-t border-gray-200 dark:border-gray-700 p-4">
        <div class="text-green-600 dark:text-green-400 font-medium">{{ userDisplayName }}</div>
        <div class="text-gray-600 dark:text-gray-400 text-sm mt-1">
          {{ formatPhDate(currentDate) }} at {{ formatTime(currentTime) }}
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
      <header class="h-14 sm:h-16 bg-white dark:bg-gray-800 shadow-sm flex items-center justify-between px-3 sm:px-4 lg:px-6 sticky top-0 z-30">
        <div class="flex items-center gap-2 sm:gap-4">
          <!-- Mobile Menu Button -->
          <button 
            class="lg:hidden p-1.5 sm:p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50 menu-button focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50"
            @click="toggleSidebar"
            aria-label="Toggle menu"
          >
            <span class="material-icons-outlined text-xl sm:text-2xl text-green-600 dark:text-green-400">
              {{ isSidebarOpen ? 'close' : 'menu' }}
            </span>
          </button>
          <h1 class="text-lg sm:text-xl lg:text-2xl font-semibold text-gray-800 dark:text-green-400">{{ route.name }}</h1>
        </div>

        <!-- Right Side Icons -->
        <div class="flex items-center space-x-2 sm:space-x-4">
          <!-- Dark Mode Toggle -->
          <button 
            class="p-1.5 sm:p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700/50"
            @click="toggleDarkMode"
            aria-label="Toggle dark mode"
          >
            <span class="material-icons-outlined text-xl sm:text-2xl dark:text-gray-300" v-if="!isDarkMode">dark_mode</span>
            <span class="material-icons-outlined text-xl sm:text-2xl text-yellow-500" v-else>light_mode</span>
          </button>

          <!-- Notifications -->
          <div class="relative">
            <button 
              class="p-1.5 sm:p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700/50 notifications-button relative"
              @click="toggleNotifications"
              aria-label="Notifications"
            >
              <span class="material-icons-outlined text-xl sm:text-2xl dark:text-gray-300">notifications</span>
              <!-- Dynamic notification badge from database -->
              <span 
                v-if="unreadCount > 0" 
                class="absolute top-0 right-0 h-4 w-4 sm:h-5 sm:w-5 bg-red-500 rounded-full text-[10px] sm:text-xs text-white flex items-center justify-center font-semibold shadow-lg"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </button>

            <!-- Notifications Dropdown -->
            <div 
              v-if="isNotificationsOpen"
              class="absolute right-0 mt-2 w-64 sm:w-80 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-2 z-50 notifications-dropdown max-h-[80vh] overflow-y-auto"
            >
              <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-sm font-medium text-gray-800 dark:text-gray-200">Recent Notifications</h3>
                <button 
                  @click="refreshNotifications"
                  class="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                  title="Refresh notifications"
                >
                  <span class="material-icons-outlined text-sm text-gray-500 dark:text-gray-400">refresh</span>
                </button>
              </div>
              
              <!-- Notification Items -->
              <div class="max-h-[400px] overflow-y-auto">
                <div v-if="notifications.length === 0" class="px-4 py-3 text-center">
                  <p class="text-sm text-gray-500 dark:text-gray-400">No notifications</p>
                </div>
                
                <div
                  v-for="notification in notifications.slice(0, 5)"
                  :key="notification.id"
                  @click="handleNotificationClick(notification)"
                  class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer flex items-start gap-3"
                  :class="{ 'bg-blue-50 dark:bg-blue-900/20': !notification.isRead }"
                >
                  <div class="flex-shrink-0 w-2 h-2 mt-2 rounded-full"
                       :class="notification.priority === 'high' ? 'bg-red-500' : 
                               notification.priority === 'medium' ? 'bg-yellow-500' : 'bg-green-500'">
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-800 dark:text-gray-300 font-medium">{{ notification.title }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ notification.message }}</p>
                    <div class="flex items-center justify-between mt-1">
                      <p class="text-xs text-gray-400 dark:text-gray-500">{{ notification.user }}</p>
                      <p class="text-xs text-gray-400 dark:text-gray-500">{{ notification.time }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- View All Link -->
              <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-700">
                <router-link 
                  to="/notifications"
                  class="text-sm text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300 font-medium"
                  @click="isNotificationsOpen = false"
                >
                  View all notifications
                </router-link>
              </div>
            </div>
          </div>

          <!-- Profile -->
          <div class="relative">
            <button 
              class="flex items-center space-x-2 profile-button"
              @click="toggleProfileDropdown"
              aria-label="Profile menu"
            >
              <div v-if="avatarError" class="h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-green-500 flex items-center justify-center text-white text-sm font-semibold">
                {{ userDisplayName.charAt(0).toUpperCase() }}
              </div>
              <img
                v-else
                :src="userAvatar"
                alt="Profile"
                class="h-7 w-7 sm:h-8 sm:w-8 rounded-full object-cover"
                @error="handleImageError"
              />
            </button>

            <!-- Profile Dropdown -->
            <div 
              v-if="isProfileDropdownOpen"
              class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-1 z-50 profile-dropdown"
            >
              <router-link 
                to="/profile"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center"
                @click="isProfileDropdownOpen = false"
              >
                <span class="material-icons-outlined mr-2 text-sm">person</span>
                Profile
              </router-link>
              <router-link
                to="/activity-log"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center"
                @click="isProfileDropdownOpen = false"
              >
                <span class="material-icons-outlined mr-2 text-sm">history</span>
                Activity Log
              </router-link>
              <router-link
                to="/history/deleted-items"
                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center"
                @click="isProfileDropdownOpen = false"
              >
                <span class="material-icons-outlined mr-2 text-sm">folder</span>
                History
              </router-link>
              <button
                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 flex items-center"
                @click="handleLogout"
              >
                <span class="material-icons-outlined mr-2 text-sm">logout</span>
                Log out
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-3 sm:p-4 lg:p-6 dark:bg-gray-900">
        <RouterView />
      </main>

      <!-- Footer -->
      <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-2 px-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-sm text-gray-600 dark:text-gray-400 gap-2">
          <div class="flex items-center space-x-2">
            <span class="material-icons-outlined text-green-600 dark:text-green-400">person</span>
            <span class="font-medium text-green-600 dark:text-green-400">{{ userDisplayName }}</span>
          </div>
          <div class="flex items-center space-x-4">
            <div>{{ formatPhDate(currentDate) }}</div>
            <div>{{ formatTime(currentTime) }}</div>
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
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons+Outlined');

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