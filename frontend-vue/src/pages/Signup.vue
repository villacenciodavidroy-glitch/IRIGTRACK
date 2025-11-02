<template>
  <div>
    <!-- Preloader -->
    <div v-if="showPreloader" class="pre-loader">
      <div class="pre-loader-box">
        <div class="loader-logo">
          <img src="../assets/logo.png" alt="" class="w-24 h-24" />
        </div>
        <div class="loader-progress" id="progress_div">
          <div class="bar" :style="{ width: `${progress}%` }"></div>
        </div>
        <div class="percent" id="percent1">{{ progress }}%</div>
        <div class="loading-text">{{ isLoading ? 'Creating account...' : 'Loading...' }}</div>
      </div>
    </div>

    <!-- Main Registration Form -->
    <div class="login-container min-h-screen relative flex items-center p-3 sm:p-4 md:p-6">
      <!-- Background Image -->
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

          <!-- Right Registration Section -->  
          <div class="flex items-center justify-center p-4 sm:p-6 md:p-12 md:w-1/2 overflow-y-auto max-h-screen">
            <div class="w-full max-w-md">
              <!-- Logo and Title -->
              <div class="mb-6 sm:mb-8 flex flex-col sm:flex-row items-center sm:items-start gap-3 sm:gap-0">
                <img src="../assets/logo.png" alt="IrrigTrack Logo" class="h-8 w-8 sm:h-10 sm:w-10 sm:mr-3" />
                <h1 class="text-base sm:text-lg md:text-xl font-semibold text-gray-700 text-center sm:text-left">
                  IrrigTrack - Create Account
                </h1>
              </div>

              <!-- Registration Form -->
              <form @submit.prevent="register" class="space-y-4">
                <!-- Full Name -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Full Name <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="formData.fullname"
                    type="text"
                    required
                    autocomplete="name"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    placeholder="John Doe"
                  />
                  <p v-if="errors.fullname" class="error-msg">{{ errors.fullname[0] }}</p>
                </div>

                <!-- Username (Optional) -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Username <span class="text-gray-500 text-xs">(Optional)</span>
                  </label>
                  <input
                    v-model="formData.username"
                    type="text"
                    autocomplete="username"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    placeholder="johndoe (defaults to email)"
                  />
                  <p class="text-xs text-gray-500 mt-1">If not provided, email will be used as username</p>
                  <p v-if="errors.username" class="error-msg">{{ errors.username[0] }}</p>
                </div>

                <!-- Email -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Email Address <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="formData.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    placeholder="john.doe@example.com"
                  />
                  <p v-if="errors.email" class="error-msg">{{ errors.email[0] }}</p>
                </div>

                <!-- Location -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Location/Department <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model.number="formData.location_id"
                    required
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                    :disabled="locationsLoading"
                  >
                    <option :value="null" disabled>Select location</option>
                    <option 
                      v-for="location in locations" 
                      :key="location.id" 
                      :value="location.id"
                    >
                      {{ location.location }}
                    </option>
                  </select>
                  <p v-if="locationsLoading" class="text-xs text-gray-500 mt-1">Loading locations...</p>
                  <p v-if="errors.location_id" class="error-msg">{{ errors.location_id[0] }}</p>
                </div>

                <!-- Password -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Password <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input
                      v-model="formData.password"
                      :type="showPassword ? 'text' : 'password'"
                      required
                      autocomplete="new-password"
                      class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 pr-10 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                      placeholder="••••••••"
                      minlength="8"
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
                  <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                  <p v-if="errors.password" class="error-msg">{{ errors.password[0] }}</p>
                </div>

                <!-- Confirm Password -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Confirm Password <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <input
                      v-model="formData.password_confirmation"
                      :type="showPasswordConfirmation ? 'text' : 'password'"
                      required
                      autocomplete="new-password"
                      class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 pr-10 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                      placeholder="••••••••"
                    />
                    <button
                      type="button"
                      @click="showPasswordConfirmation = !showPasswordConfirmation"
                      class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                    >
                      <svg
                        v-if="!showPasswordConfirmation"
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
                  <p v-if="errors.password_confirmation" class="error-msg">{{ errors.password_confirmation[0] }}</p>
                </div>

                <!-- Role (Optional) -->
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Role <span class="text-gray-500 text-xs">(Optional, defaults to user)</span>
                  </label>
                  <select
                    v-model="formData.role"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                  >
                    <option value="">Select role (optional)</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                  </select>
                  <p v-if="errors.role" class="error-msg">{{ errors.role[0] }}</p>
                </div>

                <!-- Image Upload (Optional) -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Profile Picture <span class="text-gray-500 text-xs">(Optional)</span>
                  </label>
                  <div class="mt-1 flex items-center gap-4">
                    <div v-if="previewUrl" class="flex-shrink-0">
                      <img
                        :src="previewUrl"
                        alt="Preview"
                        class="h-16 w-16 rounded-full object-cover border-2 border-gray-300"
                      />
                    </div>
                    <div class="flex-1">
                      <label
                        for="image-upload"
                        class="cursor-pointer flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                      >
                        <svg class="h-5 w-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Choose Image
                      </label>
                      <input
                        id="image-upload"
                        type="file"
                        accept="image/*"
                        @change="handleImageUpload"
                        class="hidden"
                      />
                      <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF up to 5MB</p>
                    </div>
                    <button
                      v-if="previewUrl"
                      type="button"
                      @click="clearImage"
                      class="flex-shrink-0 text-red-600 hover:text-red-800"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  <p v-if="errors.image" class="error-msg">{{ errors.image[0] }}</p>
                </div>

                <!-- General Error -->
                <p v-if="errors.general" class="error-msg text-red-600 text-center">{{ errors.general[0] }}</p>

                <!-- Register Button -->
                <button
                  type="submit"
                  :disabled="isLoading || locationsLoading"
                  class="w-full rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ isLoading ? 'Creating Account...' : 'Create Account' }}
                </button>
              </form>

              <!-- Divider -->
              <div class="my-6 flex items-center">
                <div class="flex-1 border-t border-gray-300"></div>
                <div class="mx-4 text-sm text-gray-500">or</div>
                <div class="flex-1 border-t border-gray-300"></div>
              </div>

              <!-- Login Link -->
              <div class="text-center">
                <p class="text-sm text-gray-600">
                  Already have an account?
                  <router-link
                    to="/login"
                    class="font-medium text-green-600 hover:text-green-500"
                  >
                    Sign in here
                  </router-link>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <SuccessModal
      :isOpen="showSuccessModal"
      title="Registration Successful"
      :message="successMessage"
      buttonText="Go to Login"
      type="success"
      @confirm="goToLogin"
      @close="goToLogin"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import useLocations from '../composables/useLocations'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const { locations, loading: locationsLoading, fetchLocations } = useLocations()

const isLoading = ref(false)
const progress = ref(0)
const showPreloader = ref(true)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const previewUrl = ref(null)
const showSuccessModal = ref(false)
const successMessage = ref('')

const formData = ref({
  fullname: '',
  username: '',
  email: '',
  location_id: null,
  password: '',
  password_confirmation: '',
  role: '',
  image: null
})

const errors = ref({})

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
  }, 30)
}

// Handle image upload
const handleImageUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Validate file type
    if (!file.type.startsWith('image/')) {
      errors.value.image = ['File must be an image']
      return
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      errors.value.image = ['Image size must not exceed 5MB']
      return
    }
    
    formData.value.image = file
    
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      previewUrl.value = e.target.result
    }
    reader.readAsDataURL(file)
    
    // Clear any previous errors
    if (errors.value.image) {
      delete errors.value.image
    }
  }
}

// Clear image
const clearImage = () => {
  formData.value.image = null
  previewUrl.value = null
  const fileInput = document.getElementById('image-upload')
  if (fileInput) {
    fileInput.value = ''
  }
}

// Register user
const register = async () => {
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
        // Prepare form data
        const formDataToSend = new FormData()
        formDataToSend.append('fullname', formData.value.fullname)
        if (formData.value.username) {
          formDataToSend.append('username', formData.value.username)
        }
        formDataToSend.append('email', formData.value.email)
        formDataToSend.append('location_id', formData.value.location_id)
        formDataToSend.append('password', formData.value.password)
        formDataToSend.append('password_confirmation', formData.value.password_confirmation)
        if (formData.value.role) {
          formDataToSend.append('role', formData.value.role)
        }
        if (formData.value.image) {
          formDataToSend.append('image', formData.value.image)
        }

        // Send request (axios will handle Content-Type automatically for FormData)
        const response = await axiosClient.post('/register', formDataToSend)

        if (response.data && response.data.success) {
          successMessage.value = 'Account created successfully! You can now log in.'
          showSuccessModal.value = true
          
          // Store token if provided (optional - may want to auto-login)
          if (response.data.token) {
            localStorage.setItem('token', response.data.token)
            axiosClient.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`
          }
        }
      } catch (error) {
        console.error('Registration error:', error)
        if (error.response && error.response.data) {
          if (error.response.data.errors) {
            errors.value = error.response.data.errors
          } else if (error.response.data.message) {
            errors.value = { general: [error.response.data.message] }
          }
        } else {
          errors.value = { general: ['An unexpected error occurred. Please try again.'] }
        }
      } finally {
        isLoading.value = false
        showPreloader.value = false
      }
    }
  }, 40)
}

// Go to login page
const goToLogin = () => {
  router.push('/login')
}

// Fetch locations on mount
onMounted(async () => {
  initializeLoader()
  await fetchLocations(1, 1000) // Fetch all locations
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
input[type="text"],
select {
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
input[type="text"]:focus,
select:focus {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 1px #10b981 !important;
  outline: none !important;
}

input[type="email"]:disabled, 
input[type="password"]:disabled, 
input[type="text"]:disabled,
select:disabled {
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