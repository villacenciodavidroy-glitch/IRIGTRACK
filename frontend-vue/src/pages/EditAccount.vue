<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- Enhanced Header Section -->
    <div class="bg-green-600 rounded-xl shadow-lg">
      <div class="px-4 py-5 sm:px-6 sm:py-6 md:px-8 md:py-7">
        <div class="flex items-center gap-3 sm:gap-4 md:gap-5">
          <!-- Back Button -->
          <button 
            @click="goBack" 
            class="p-2.5 sm:p-3 bg-white/20 hover:bg-white/30 rounded-lg transition-all duration-200 flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-white text-xl sm:text-2xl">arrow_back</span>
          </button>
          
          <!-- Edit Icon Button -->
          <div class="p-2.5 sm:p-3 bg-white/20 rounded-lg flex-shrink-0">
            <span class="material-icons-outlined text-white text-xl sm:text-2xl">person</span>
          </div>
          
          <!-- Title and Subtitle -->
          <div class="flex-1 min-w-0">
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-white mb-0.5 sm:mb-1 leading-tight">
              Edit Account
            </h1>
            <p class="text-green-100 text-xs sm:text-sm leading-tight">
              Update user account information and settings
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 max-w-3xl mx-auto flex justify-center items-center py-12 sm:py-16">
      <div class="flex flex-col items-center gap-4">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
        <span class="text-base font-medium text-gray-700 dark:text-gray-300">Loading user data...</span>
      </div>
    </div>

    <!-- Main Form -->
    <div v-else class="max-w-3xl mx-auto space-y-6">
      
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Account Information Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">badge</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Account Information</h2>
                <p class="text-xs text-green-100">Basic user account details</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <!-- Account Type -->
            <div class="form-group">
              <label class="form-label">Account Type <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">badge</span>
                </span>
                <select 
                  v-model="formData.accountType"
                  class="form-select-enhanced"
                  required
                >
                  <option value="" disabled>Select account type</option>
                  <option 
                    v-for="type in accountTypes" 
                    :key="type.value" 
                    :value="type.value"
                  >
                    {{ type.label }}
                  </option>
                </select>
                <p v-if="errors.role" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.role[0] }}</p>
              </div>
            </div>

            <!-- Full Name -->
            <div class="form-group">
              <label class="form-label">Full Name <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">person</span>
                </span>
                <input 
                  type="text" 
                  v-model="formData.fullname"
                  class="form-input-enhanced"
                  placeholder="Enter full name"
                  required
                >
                <p v-if="errors.fullname" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.fullname[0] }}</p>
              </div>
            </div>

            <!-- Username -->
            <div class="form-group">
              <label class="form-label">Username <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">alternate_email</span>
                </span>
                <input 
                  type="text" 
                  v-model="formData.username"
                  class="form-input-enhanced"
                  placeholder="Enter username"
                  required
                >
                <p v-if="errors.username" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.username[0] }}</p>
              </div>
            </div>

            <!-- Email -->
            <div class="form-group">
              <label class="form-label">Email <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">email</span>
                </span>
                <input 
                  type="email" 
                  v-model="formData.email"
                  class="form-input-enhanced"
                  placeholder="Enter email address"
                  required
                >
                <p v-if="errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.email[0] }}</p>
              </div>
            </div>

            <!-- Location -->
            <div class="form-group">
              <label class="form-label">Location <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">location_on</span>
                </span>
                <select 
                  v-model="formData.location"
                  class="form-select-enhanced"
                  required
                >
                  <option value="" disabled>Select location</option>
                  <option 
                    v-for="location in locations" 
                    :key="location.id" 
                    :value="location.id || location.location_id"
                  >
                    {{ location.location }}
                  </option>
                </select>
              </div>
              <p v-if="errors.location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.location_id[0] }}</p>
            </div>
          </div>
        </div>

        <!-- Avatar Upload Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">image</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Profile Avatar</h2>
                <p class="text-xs text-green-100">Upload or update user profile image</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="form-group">
              <label class="form-label">Avatar Image</label>
              <div class="mt-2">
                <div
                  @click="$refs.fileInput.click()"
                  @dragover.prevent="dragOver = true"
                  @dragleave.prevent="dragOver = false"
                  @drop.prevent="handleFileDrop"
                  class="flex flex-col items-center justify-center w-full h-40 px-4 transition-all duration-200 bg-gray-50 dark:bg-gray-700 border-2 border-dashed rounded-xl appearance-none cursor-pointer hover:border-green-400 hover:bg-green-50/30 dark:hover:bg-green-900/20"
                  :class="{ 
                    'border-gray-300 dark:border-gray-600': !selectedFile && !previewUrl,
                    'border-green-500 border-solid bg-green-50/50 dark:bg-green-900/30': selectedFile || previewUrl,
                    'border-blue-400 border-solid bg-blue-50 dark:bg-blue-900/30': dragOver
                  }"
                >
                  <div v-if="selectedFile || previewUrl" class="flex items-center gap-4 w-full">
                    <img
                      :src="previewUrl"
                      class="w-20 h-20 object-cover rounded-lg border-2 border-green-200 dark:border-green-700 shadow-md"
                      alt="Preview"
                    />
                    <div class="flex-1 flex flex-col">
                      <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ selectedFile ? selectedFile.name : 'Current Image' }}
                      </span>
                      <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ selectedFile ? formatFileSize(selectedFile.size) : 'Click to change' }}
                      </span>
                    </div>
                    <button
                      @click.stop="clearFile"
                      class="p-2 text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-all"
                      title="Remove file"
                    >
                      <span class="material-icons-outlined">close</span>
                    </button>
                  </div>
                  <div v-else class="flex flex-col items-center gap-3 py-4">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                      <span class="material-icons-outlined text-green-600 dark:text-green-400 text-3xl">cloud_upload</span>
                    </div>
                    <div class="text-center">
                      <span class="font-semibold text-gray-700 dark:text-gray-300">
                        <span class="text-green-600 dark:text-green-400 hover:underline">Choose file</span> or drag and drop
                      </span>
                      <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">PNG, JPG, GIF up to 10MB</p>
                    </div>
                  </div>
                  <input
                    ref="fileInput"
                    type="file"
                    @change="handleImageUpload"
                    accept="image/*"
                    class="hidden"
                  >
                </div>
                <p v-if="errors.image" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ errors.image[0] }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Password Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">lock</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Change Password</h2>
                <p class="text-xs text-green-100">Optional - leave blank to keep current password</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <!-- Password -->
            <div class="form-group">
              <label class="form-label">New Password</label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">lock</span>
                </span>
                <input 
                  :type="showPassword ? 'text' : 'password'" 
                  v-model="formData.password"
                  class="form-input-enhanced pr-10"
                  placeholder="Enter new password (leave blank to keep current)"
                  @input="checkPasswordRequirements"
                >
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3 top-0 h-full flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none z-10"
                  tabindex="-1"
                >
                  <span class="material-icons-outlined text-xl">
                    {{ showPassword ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </div>
              <!-- Password Strength Meter -->
              <div v-if="formData.password" class="mt-2">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Password Strength:</span>
                  <span 
                    class="text-xs font-semibold"
                    :class="{
                      'text-red-600 dark:text-red-400': passwordStrength.level === 'weak',
                      'text-yellow-600 dark:text-yellow-400': passwordStrength.level === 'fair',
                      'text-blue-600 dark:text-blue-400': passwordStrength.level === 'good',
                      'text-green-600 dark:text-green-400': passwordStrength.level === 'strong'
                    }"
                  >
                    {{ passwordStrength.label }}
                  </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                  <div 
                    class="h-2 rounded-full transition-all duration-300"
                    :class="{
                      'bg-red-500': passwordStrength.level === 'weak',
                      'bg-yellow-500': passwordStrength.level === 'fair',
                      'bg-blue-500': passwordStrength.level === 'good',
                      'bg-green-500': passwordStrength.level === 'strong',
                      'bg-gray-300 dark:bg-gray-600': passwordStrength.level === 'none'
                    }"
                    :style="{ width: `${passwordStrength.percentage}%` }"
                  ></div>
                </div>
              </div>
              <!-- Password Requirements Checklist -->
              <div v-if="formData.password" class="mt-2 space-y-1">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Password must contain:</p>
                <div class="space-y-1 text-xs">
                  <div class="flex items-center gap-2" :class="passwordRequirements.minLength ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-sm">{{ passwordRequirements.minLength ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>Minimum 8 characters</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasUpperCase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasUpperCase ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one uppercase letter</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasLowerCase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasLowerCase ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one lowercase letter</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasDigit ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasDigit ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one digit</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasSpecialChar ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasSpecialChar ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one special symbol (!@#$%^&*()_+-=[]{}|;:,.<>?)</span>
                  </div>
                </div>
              </div>
              <p v-if="errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.password[0] }}</p>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">lock</span>
                </span>
                <input 
                  :type="showConfirmPassword ? 'text' : 'password'" 
                  v-model="formData.password_confirmation"
                  class="form-input-enhanced pr-10"
                  placeholder="Confirm new password"
                  :disabled="!formData.password"
                  @input="() => { errors.password_confirmation = []; checkPasswordMatch(); }"
                >
                <button
                  type="button"
                  @click="showConfirmPassword = !showConfirmPassword"
                  :disabled="!formData.password"
                  class="absolute right-3 top-0 h-full flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none z-10 disabled:opacity-50 disabled:cursor-not-allowed"
                  tabindex="-1"
                >
                  <span class="material-icons-outlined text-xl">
                    {{ showConfirmPassword ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </div>
              <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.password_confirmation[0] }}</p>
            </div>
          </div>
        </div>

        <!-- General Error Message -->
        <div v-if="errors.general" class="bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-red-600 dark:text-red-400">error</span>
            <p class="text-sm font-semibold text-red-700 dark:text-red-400">{{ errors.general[0] }}</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4">
          <button 
            type="button"
            @click="goBack"
            class="px-6 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-400 dark:hover:border-gray-500 font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
          >
            Cancel
          </button>
          <button 
            type="submit"
            :disabled="isSubmitting"
            class="btn-primary-enhanced disabled:opacity-75 disabled:cursor-not-allowed"
          >
            <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-lg">refresh</span>
            {{ isSubmitting ? 'Updating...' : 'Update Account' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axiosClient from '../axios'
import useLocations from '../composables/useLocations'

const router = useRouter()
const route = useRoute()
const userId = computed(() => route.params.id)

const errors = ref({})
const dragOver = ref(false)
const selectedFile = ref(null)
const previewUrl = ref(null)
const fileInput = ref(null)
const isSubmitting = ref(false)
const loading = ref(true)

// Password visibility toggles
const showPassword = ref(false)
const showConfirmPassword = ref(false)

// Password complexity requirements state
const passwordRequirements = ref({
  minLength: false,
  hasUpperCase: false,
  hasLowerCase: false,
  hasDigit: false,
  hasSpecialChar: false
})

// Password strength calculation
const passwordStrength = computed(() => {
  if (!formData.value.password) return { level: 'none', label: '', color: '', percentage: 0 }
  
  const requirements = passwordRequirements.value
  const metCount = Object.values(requirements).filter(Boolean).length
  
  // Calculate strength based on met requirements
  if (metCount === 5) {
    return { level: 'strong', label: 'Strong', color: 'green', percentage: 100 }
  } else if (metCount === 4) {
    return { level: 'good', label: 'Good', color: 'blue', percentage: 75 }
  } else if (metCount === 3) {
    return { level: 'fair', label: 'Fair', color: 'yellow', percentage: 50 }
  } else if (metCount >= 1) {
    return { level: 'weak', label: 'Weak', color: 'red', percentage: 25 }
  } else {
    return { level: 'none', label: '', color: 'gray', percentage: 0 }
  }
})

const formData = ref({
  accountType: '',
  avatar: null,
  fullname: '',
  username: '',
  email: '',
  location: '',
  password: '',
  password_confirmation: ''
})

// Sample data for dropdowns
const accountTypes = ref([
  { value: 'admin', label: 'Admin' },
  { value: 'user', label: 'User' },
  { value: 'supply', label: 'Supply' }
])

const { locations, fetchLocations } = useLocations(formData)

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const handleFileDrop = (event) => {
  dragOver.value = false
  const file = event.dataTransfer.files[0]
  if (file && file.type.startsWith('image/')) {
    handleFileSelection(file)
  }
}

const handleFileSelection = (file) => {
  if (file.size > 10 * 1024 * 1024) { // 10MB limit
    errors.value.image = ['File size should not exceed 10MB']
    return
  }
  
  selectedFile.value = file
  formData.value.avatar = file
  
  // Create preview URL
  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = e.target.result
  }
  reader.readAsDataURL(file)
}

const handleImageUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    handleFileSelection(file)
  }
}

const clearFile = () => {
  selectedFile.value = null
  previewUrl.value = null
  formData.value.avatar = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const goBack = () => {
  router.push('/admin')
}

const fetchUserData = async () => {
  loading.value = true
  try {
    const response = await axiosClient.get(`/users/${userId.value}`)
    const userData = response.data.data || response.data
    
    console.log('=== LOADING USER DATA ===')
    console.log('Full response:', response.data)
    console.log('User data:', userData)
    
    // Populate form data
    formData.value.accountType = userData.role || ''
    formData.value.fullname = userData.fullname || ''
    formData.value.username = userData.username || ''
    formData.value.email = userData.email || ''
    
    // Handle location_id - ensure it's a number
    if (userData.location_id) {
      formData.value.location = typeof userData.location_id === 'string' 
        ? parseInt(userData.location_id) 
        : userData.location_id
    } else {
      formData.value.location = ''
    }
    
    // Set image preview if available
    if (userData.image) {
      previewUrl.value = userData.image
    }
    
    console.log('Form populated with:', {
      accountType: formData.value.accountType,
      fullname: formData.value.fullname,
      username: formData.value.username,
      email: formData.value.email,
      location: formData.value.location
    })
    
    // Verify data was loaded correctly
    if (!formData.value.accountType || !formData.value.fullname || !formData.value.username || !formData.value.email) {
      console.error('WARNING: Form data not fully loaded!')
      console.error('Missing fields:', {
        accountType: !formData.value.accountType,
        fullname: !formData.value.fullname,
        username: !formData.value.username,
        email: !formData.value.email
      })
    }
  } catch (error) {
    console.error('Error fetching user data:', error)
    errors.value.general = ['Failed to load user data. Please try again.']
  } finally {
    loading.value = false
  }
}

// Check password requirements in real-time
const checkPasswordRequirements = () => {
  const password = formData.value.password || ''
  
  passwordRequirements.value = {
    minLength: password.length >= 8,
    hasUpperCase: /[A-Z]/.test(password),
    hasLowerCase: /[a-z]/.test(password),
    hasDigit: /[0-9]/.test(password),
    hasSpecialChar: /[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)
  }
  
  // Clear password errors when user starts typing
  if (password) {
    errors.value.password = []
  }
}

// Check password match in real-time
const checkPasswordMatch = () => {
  if (formData.value.password_confirmation && formData.value.password !== formData.value.password_confirmation) {
    // Don't show error immediately, only on submit or blur
    // This allows user to type without constant error messages
  } else {
    errors.value.password_confirmation = []
  }
}

const validatePasswords = () => {
  // Only validate if a new password is being set
  if (formData.value.password) {
    const password = formData.value.password
    
    // Check password match
    if (password !== formData.value.password_confirmation) {
      errors.value.password_confirmation = ['Passwords do not match']
      return false
    }
    
    // Check all complexity requirements
    const validationErrors = []
    
    if (password.length < 8) {
      validationErrors.push('Password must be at least 8 characters long')
    }
    
    if (!/[A-Z]/.test(password)) {
      validationErrors.push('Password must contain at least one uppercase letter')
    }
    
    if (!/[a-z]/.test(password)) {
      validationErrors.push('Password must contain at least one lowercase letter')
    }
    
    if (!/[0-9]/.test(password)) {
      validationErrors.push('Password must contain at least one digit')
    }
    
    if (!/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)) {
      validationErrors.push('Password must contain at least one special symbol (!@#$%^&*()_+-=[]{}|;:,.<>?)')
    }
    
    if (validationErrors.length > 0) {
      errors.value.password = validationErrors
      return false
    }
    
    // Clear any previous errors if validation passes
    errors.value.password = []
    errors.value.password_confirmation = []
  }
  return true
}

const handleSubmit = async () => {
  if (isSubmitting.value) return
  if (loading.value) {
    console.warn('Form is still loading, cannot submit')
    return
  }
  
  try {
    isSubmitting.value = true
    errors.value = {}

    // DEBUG: Check formData values BEFORE any processing
    console.log('=== FORM SUBMIT START ===')
    console.log('formData.value:', JSON.parse(JSON.stringify(formData.value)))
    console.log('Account Type:', formData.value.accountType, 'Type:', typeof formData.value.accountType, 'Length:', String(formData.value.accountType).length)
    console.log('Full Name:', formData.value.fullname, 'Type:', typeof formData.value.fullname, 'Length:', String(formData.value.fullname).length)
    console.log('Username:', formData.value.username, 'Type:', typeof formData.value.username, 'Length:', String(formData.value.username).length)
    console.log('Email:', formData.value.email, 'Type:', typeof formData.value.email, 'Length:', String(formData.value.email).length)

    // Validate form data has content before submitting
    if (!formData.value.accountType || String(formData.value.accountType).trim() === '') {
      errors.value.role = ['Account type is required']
      isSubmitting.value = false
      return
    }
    if (!formData.value.fullname || String(formData.value.fullname).trim() === '') {
      errors.value.fullname = ['Full name is required']
      isSubmitting.value = false
      return
    }
    if (!formData.value.username || String(formData.value.username).trim() === '') {
      errors.value.username = ['Username is required']
      isSubmitting.value = false
      return
    }
    if (!formData.value.email || String(formData.value.email).trim() === '') {
      errors.value.email = ['Email is required']
      isSubmitting.value = false
      return
    }

    // Validate passwords if provided
    if (!validatePasswords()) {
      isSubmitting.value = false
      return
    }

    // Check if location is selected
    if (!formData.value.location) {
      errors.value.location_id = ['Please select a location']
      isSubmitting.value = false
      return
    }

    // Create FormData object for file upload
    const formDataToSend = new FormData()
    
    // Append all required form fields with actual values
    // Ensure we're sending non-empty values
    const roleValue = String(formData.value.accountType).trim()
    const fullnameValue = String(formData.value.fullname).trim()
    const usernameValue = String(formData.value.username).trim()
    const emailValue = String(formData.value.email).trim()
    
    console.log('Values to send:', {
      role: roleValue,
      fullname: fullnameValue,
      username: usernameValue,
      email: emailValue
    })
    
    if (!roleValue || !fullnameValue || !usernameValue || !emailValue) {
      console.error('ERROR: One or more required fields are empty!')
      errors.value.general = ['Please fill in all required fields. Values appear to be empty.']
      isSubmitting.value = false
      return
    }
    
    formDataToSend.append('role', roleValue)
    formDataToSend.append('fullname', fullnameValue)
    formDataToSend.append('username', usernameValue)
    formDataToSend.append('email', emailValue)
    
    // Location can be null/empty
    if (formData.value.location) {
      formDataToSend.append('location_id', String(formData.value.location))
    } else {
      // Send empty string to allow clearing location (nullable field)
      formDataToSend.append('location_id', '')
    }
    
    // Only append password if it's being changed
    if (formData.value.password && formData.value.password.trim()) {
      formDataToSend.append('password', formData.value.password)
      if (formData.value.password_confirmation) {
        formDataToSend.append('password_confirmation', formData.value.password_confirmation)
      } else {
        formDataToSend.append('password_confirmation', formData.value.password)
      }
    }
    
    // Append the image file if it exists and is a new file
    if (formData.value.avatar && selectedFile.value) {
      formDataToSend.append('image', formData.value.avatar)
    }

    // Debug: Log what we're sending
    console.log('=== SENDING UPDATE ===')
    console.log('User ID:', userId.value)
    console.log('Form values (raw):', {
      accountType: formData.value.accountType,
      fullname: formData.value.fullname,
      username: formData.value.username,
      email: formData.value.email,
      location: formData.value.location
    })
    console.log('Form values (types):', {
      accountType_type: typeof formData.value.accountType,
      fullname_type: typeof formData.value.fullname,
      username_type: typeof formData.value.username,
      email_type: typeof formData.value.email
    })
    console.log('Form values (lengths):', {
      accountType_length: String(formData.value.accountType).length,
      fullname_length: String(formData.value.fullname).length,
      username_length: String(formData.value.username).length,
      email_length: String(formData.value.email).length
    })
    
    // Log FormData entries
    console.log('FormData entries:')
    for (let pair of formDataToSend.entries()) {
      console.log(`  ${pair[0]}:`, pair[1] instanceof File ? `[File: ${pair[1].name}]` : `"${pair[1]}" (${typeof pair[1]})`)
    }

    // Use POST endpoint for FormData (PUT requests don't parse FormData correctly in Laravel)
    const response = await axiosClient.post(`/users/${userId.value}/update`, formDataToSend)

    console.log('=== UPDATE RESPONSE ===')
    console.log('Status:', response.status)
    console.log('Response:', response.data)

    if (response.status === 200 && response.data) {
      const responseData = response.data.data || response.data
      console.log('Updated user:', {
        fullname: responseData.fullname,
        username: responseData.username,
        email: responseData.email,
        role: responseData.role,
        location_id: responseData.location_id
      })
      router.push('/admin')
    }
  } catch (error) {
    console.error('Error updating account:', error)
    console.error('Error response:', error.response?.data)
    console.error('Error status:', error.response?.status)
    
    if (error.response?.data?.errors) {
      console.error('Validation errors from backend:', error.response.data.errors)
      errors.value = error.response.data.errors
      
      // If we have values but backend says they're required, log a warning
      if (error.response.data.errors.role && formData.value.accountType) {
        console.warn('Backend says role is required, but we have:', formData.value.accountType)
      }
      if (error.response.data.errors.fullname && formData.value.fullname) {
        console.warn('Backend says fullname is required, but we have:', formData.value.fullname)
      }
      if (error.response.data.errors.username && formData.value.username) {
        console.warn('Backend says username is required, but we have:', formData.value.username)
      }
      if (error.response.data.errors.email && formData.value.email) {
        console.warn('Backend says email is required, but we have:', formData.value.email)
      }
    } else {
      errors.value = {
        general: [error.response?.data?.message || 'An unexpected error occurred. Please try again.']
      }
    }
  } finally {
    isSubmitting.value = false
  }
}

// Fetch user data when component is mounted
onMounted(async () => {
  if (userId.value) {
    // Fetch locations first
    try {
      await fetchLocations(1, 1000)
    } catch (error) {
      console.error('Error fetching locations:', error)
    }
    
    // Then fetch user data
    await fetchUserData()
  } else {
    router.push('/admin')
  }
})
</script>

<style scoped>
.form-group {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2;
}

.form-input-enhanced {
  @apply block w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm transition-all duration-200;
  @apply focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20;
  @apply hover:border-gray-400 dark:hover:border-gray-500;
  height: 48px;
  padding-left: 3rem;
  padding-right: 1rem;
}

.form-input-enhanced:focus {
  @apply shadow-md;
}

.form-select-enhanced {
  @apply block w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm transition-all duration-200;
  @apply focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20;
  @apply hover:border-gray-400 dark:hover:border-gray-500;
  height: 48px;
  padding-left: 3rem;
  padding-right: 1rem;
}

.form-select-enhanced:focus {
  @apply shadow-md;
}

.form-input-enhanced::placeholder,
.form-select-enhanced::placeholder {
  @apply text-gray-400 dark:text-gray-400;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-3 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

.material-icons-outlined {
  font-size: 20px;
}

/* Improved focus states for accessibility */
button:focus, input:focus, select:focus {
  @apply outline-none ring-2 ring-green-500 ring-opacity-50;
}

input:disabled {
  @apply bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed;
}
</style>