
<template>
  <div>
    <!-- Preloader -->
    <div v-if="showPreloader" class="pre-loader">
      <div class="pre-loader-box">
        <div class="loader-logo">
          <img :src="logoUrl" alt="" class="w-24 h-24" />
        </div>
        <div class="loader-progress" id="progress_div">
          <div class="bar" :style="{ width: `${progress}%` }"></div>
        </div>
        <div class="percent" id="percent1">{{ progress }}%</div>
        <div class="loading-text">{{ isLoading ? 'Logging in...' : 'Loading...' }}</div>
      </div>
    </div>

    <!-- Main Login Form -->
    <div class="login-container min-h-screen relative flex items-center p-3 sm:p-4 md:p-6">
      <!-- Background Image -->`
      <div class="absolute inset-0 bg-image"></div>
      <!-- Overlay -->
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm"></div>
      
      <div class="mx-auto flex-1 max-w-4xl overflow-hidden rounded-lg bg-white/90 backdrop-blur-md shadow-2xl relative z-10">
        <div class="flex flex-col overflow-y-auto md:flex-row">
          <!-- Left Image Section -->
          <div class="h-32 md:h-auto md:w-1/2">
            <img 
              class="h-full w-full object-cover"
              src="../assets/image-section.jpg" 
              alt="Irrigation System" 
            />
          </div>

          <!-- Right Login Section -->  
          <div class="flex items-center justify-center p-4 sm:p-6 md:p-12 md:w-1/2">
            <div class="w-full max-w-md">
              <!-- Logo and Title -->
              <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row items-center sm:items-start gap-3 sm:gap-0">
                <img :src="logoUrl" alt="IrrigTrack Logo" class="h-8 w-8 sm:h-10 sm:w-10 sm:mr-3" />
                <h1 class="text-base sm:text-lg md:text-xl font-semibold text-gray-700 text-center sm:text-left">
                  IrrigTrack - Tracking Management System
                </h1>
              </div>

              <!-- Login Form -->
              <form @submit.prevent="login">
                <!-- Email Input -->
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700">
                    Email
                  </label>
                  <input
                    v-model="data.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    placeholder="jane.doe@example.com"
                  />
                  <p v-if="errors.email" class="error-msg">{{ errors.email[0] }}</p>
                </div>

                <!-- Password Input -->
                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700">
                    Password
                  </label>
                  <div class="relative">
                    <input
                      v-model="data.password"
                      :type="showPassword ? 'text' : 'password'"
                      required
                      autocomplete="current-password"
                      class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 pr-10 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                      placeholder="••••••••"
                    />
                    <button
                      type="button"
                      @click="showPassword = !showPassword"
                      class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                    >
                      <svg
                        v-if="!showPassword"
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                        />
                      </svg>
                      <svg
                        v-else
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                        />
                      </svg>
                    </button>
                  </div>
                  <p v-if="errors.password" class="error-msg">{{ errors.password }}</p>
                </div>

                <p v-if="errors.general" class="error-msg text-red-600 text-center mb-4">{{ errors.general[0] }}</p>

                <!-- Login Button -->
                <button
                  type="submit"
                  :disabled="isLoading"
                  class="w-full rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                >
                  Log in
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>  
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import useLogo from '../composables/useLogo'

const router = useRouter()
const { logoUrl, fetchLogo } = useLogo()
const isLoading = ref(false)
const progress = ref(0)
const showPreloader = ref(true)

// Form data with proper reactivity
const data = ref({
  email: '',
  password: ''
})

const errors = ref({})
const showPassword = ref(false)

// Prevent back navigation from login page
const preventBackNavigation = () => {
  // Push a new state to prevent back navigation
  window.history.pushState(null, '', window.location.href)
  
  // Listen for popstate events
  const handlePopState = (event) => {
    // Prevent going back by pushing the current state again
    window.history.pushState(null, '', window.location.href)
  }
  
  window.addEventListener('popstate', handlePopState)
  
  // Return cleanup function
  return () => {
    window.removeEventListener('popstate', handlePopState)
  }
}

// Simulate initial page load
const initializeLoader = () => {
  let loadProgress = 0
  const interval = setInterval(() => {
    loadProgress += 1
    progress.value = loadProgress
    if (loadProgress >= 100) {
      clearInterval(interval)
      showPreloader.value = false
    }
  }, 30) // Slower interval for smoother animation
}

const login = async () => {
  isLoading.value = true
  showPreloader.value = true
  progress.value = 0
  errors.value = {}

  // Optional fake delay
  await new Promise(resolve => setTimeout(resolve, 500))

  let loadProgress = 0
  const interval = setInterval(async () => {
    loadProgress += 2
    progress.value = loadProgress

    if (loadProgress >= 100) {
      clearInterval(interval)

      try {
        const response = await axiosClient.post('/login', data.value)
        if (response.status === 200) {
          if (response.data.token) {
            localStorage.setItem('token', response.data.token)
            axiosClient.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`
          }
          if (response.data.user && response.data.user.id) {
            localStorage.setItem('userId', response.data.user.id)
          }
          await router.push('/dashboard')
        }
      } catch (error) {
        if (error.response && (error.response.status === 401 || error.response.status === 422)) {
          errors.value = error.response.data.errors || { general: [error.response.data.message] }
        } else {
          console.error('Unexpected error:', error.message)
          errors.value = { general: ['An unexpected error occurred. Please try again.'] }
        }
      } finally {
        isLoading.value = false
        showPreloader.value = false
      }
    }
  }, 40) // Slower for smoother animation
}

// Component lifecycle
onMounted(() => {
  initializeLoader()
  fetchLogo()
  const cleanup = preventBackNavigation()
  onUnmounted(cleanup)
})

</script>

<style scoped>
.login-container {
  background-color: #f3f4f6;
}

.bg-image {
  background-image: url('../assets/bg.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  filter: blur(4px);
}

.pre-loader {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: white;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.pre-loader-box {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.loader-logo {
  margin-bottom: 30px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.loader-logo img {
  width: 120px;
  height: 120px;
  object-fit: contain;
}

.loader-progress {
  width: 250px;
  height: 6px;
  background: #e5e7eb;
  border-radius: 6px;
  margin: 20px auto;
  overflow: hidden;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
}

.bar {
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, #22c55e, #16a34a);
  border-radius: 6px;
  transition: width 0.3s ease;
}

.percent {
  color: #374151;
  font-size: 16px;
  font-weight: 600;
  margin: 12px 0 8px 0;
}

.loading-text {
  color: #6b7280;
  font-size: 14px;
  font-weight: 500;
}

/* Ensure input fields are properly styled and functional */
input[type="email"], 
input[type="password"], 
input[type="text"] {
  background-color: white !important;
  color: #374151 !important;
  border: 1px solid #d1d5db !important;
  pointer-events: auto !important;
  user-select: text !important;
  -webkit-user-select: text !important;
  -moz-user-select: text !important;
  -ms-user-select: text !important;
}

input[type="email"]:focus, 
input[type="password"]:focus, 
input[type="text"]:focus {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 1px #10b981 !important;
  outline: none !important;
}

input[type="email"]:disabled, 
input[type="password"]:disabled, 
input[type="text"]:disabled {
  background-color: #f9fafb !important;
  color: #6b7280 !important;
  cursor: not-allowed !important;
}

/* Error message styling */
.error-msg {
  color: #ef4444;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
</style>


