<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'
import useLocations from '../composables/useLocations'

const router = useRouter()

const user = ref({
  fullName: '',
  username: '',
  email: '',
  location: '',
  location_id: null,
  image: '',
  role: '', // Add role field
  password: '',
  password_confirmation: ''
})
const selectedFile = ref(null)
const loading = ref(false)
const imageTimestamp = ref(Date.now()) // Add timestamp for cache busting

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
  if (!user.value.password) return { level: 'none', label: '', color: '', percentage: 0 }
  
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

// Fetch locations from database
const { locations, fetchLocations, loading: locationsLoading } = useLocations()

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

onMounted(async () => {
  const userId = localStorage.getItem('userId')
  
  // Fetch locations first
  try {
    await fetchLocations(1, 1000) // Fetch all locations (1000 per page)
  } catch (error) {
    console.error('Error fetching locations:', error)
  }
  
  // Fetch user data
  try {
    const res = await axiosClient.get(`/users/${userId}`)
    if(res.status == 200){
      console.log("Success")
      console.log(res.data)
       
      user.value.fullName = res.data.data.fullname
      user.value.username = res.data.data.username
      user.value.email = res.data.data.email
      user.value.location_id = res.data.data.location_id || null
      user.value.location = res.data.data.location || ''
      user.value.image = res.data.data.image || ''
      user.value.role = res.data.data.role || '' // Fetch role from API
      imageTimestamp.value = Date.now() // Initialize timestamp
    } else {
      console.log('API Response:', res.data)
      console.log('User Data:', user.value)
    }
  } catch (e) {
    console.error('API Error:', e.response ? e.response.data : e)
  }
})

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    selectedFile.value = file;
    
    // Preview the selected image immediately
    const reader = new FileReader();
    reader.onload = (e) => {
      user.value.image = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const saveProfile = async () => {
  const userId = localStorage.getItem('userId')
  if (!userId) return

  if (!user.value) {
    console.error('user.value is undefined')
    return
  }

  loading.value = true

  const formData = new FormData()
  formData.append('role', user.value.role || '') // Include role (required by validation)
  formData.append('fullname', user.value.fullName ?? '')
  formData.append('username', user.value.username ?? '')
  formData.append('email', user.value.email ?? '')
  
  // Convert location name to location_id
  let locationIdToSend = user.value.location_id
  
  // If location_id is not set but location name is, find the ID from fetched locations
  if (!locationIdToSend && user.value.location) {
    const foundLocation = locations.value.find(loc => 
      loc.location?.toLowerCase() === user.value.location?.toLowerCase()
    )
    if (foundLocation) {
      locationIdToSend = foundLocation.id || foundLocation.location_id
    }
  }
  
  // Only append location_id if we have a valid ID
  if (locationIdToSend) {
    formData.append('location_id', locationIdToSend)
    console.log('Sending location_id:', locationIdToSend, 'for location:', user.value.location)
  }

  // Append image if a new file is selected
  if (selectedFile.value && selectedFile.value instanceof File) {
    formData.append('image', selectedFile.value)
  }
  
  // Only append password if it's being changed
  if (user.value.password && user.value.password.trim()) {
    // Validate password before submitting
    const passwordValidation = validatePasswords()
    if (!passwordValidation.valid) {
      successMessage.value = passwordValidation.message
      successModalType.value = 'error'
      showSuccessModal.value = true
      loading.value = false
      return
    }
    
    formData.append('password', user.value.password)
    formData.append('password_confirmation', user.value.password_confirmation || user.value.password)
  }

  try {
    // Use POST endpoint for FormData (PUT requests don't parse FormData correctly in Laravel)
    const res = await axiosClient.post(`/users/${userId}/update`, formData)

    if (res.status === 200 && res.data) {
      // Handle response structure - could be res.data or res.data.data
      const responseData = res.data.data || res.data
      
      // ✅ Update image with timestamp to force refresh
      if (responseData.image) {
        user.value.image = responseData.image
        imageTimestamp.value = Date.now()
      }

      // Update user data after successful update
      user.value.fullName = responseData.fullname || user.value.fullName
      user.value.username = responseData.username || user.value.username
      user.value.email = responseData.email || user.value.email
      user.value.location_id = responseData.location_id || null
      user.value.location = responseData.location || ''
      
      // Clear password fields
      user.value.password = ''
      user.value.password_confirmation = ''
      
      // Reset selected file
      selectedFile.value = null
      
      // Reset file input
      const fileInput = document.querySelector('input[type="file"]')
      if (fileInput) {
        fileInput.value = ''
      }

      successMessage.value = 'Profile updated successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
    }
  } catch (err) {
    console.error('Save error', err.response ? err.response.data : err)
    
    // Show specific error messages
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const errorMessages = Object.values(errors).flat().join(', ')
      successMessage.value = errorMessages || 'Failed to update profile. Please check the form.'
    } else {
      successMessage.value = err.response?.data?.message || 'Failed to update profile.'
    }
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    loading.value = false
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

const goToDashboard = () => {
  router.push('/dashboard')
}

// Update location_id when location changes
const updateLocationId = () => {
  if (user.value.location && locations.value.length > 0) {
    const foundLocation = locations.value.find(loc => 
      loc.location?.toLowerCase() === user.value.location?.toLowerCase()
    )
    if (foundLocation) {
      user.value.location_id = foundLocation.id || foundLocation.location_id
    }
  } else {
    user.value.location_id = null
  }
}

// Check password requirements in real-time
const checkPasswordRequirements = () => {
  const password = user.value.password || ''
  
  passwordRequirements.value = {
    minLength: password.length >= 8,
    hasUpperCase: /[A-Z]/.test(password),
    hasLowerCase: /[a-z]/.test(password),
    hasDigit: /[0-9]/.test(password),
    hasSpecialChar: /[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)
  }
}

// Check password match in real-time
const checkPasswordMatch = () => {
  if (user.value.password_confirmation && user.value.password !== user.value.password_confirmation) {
    // Don't show error immediately, only on submit or blur
    // This allows user to type without constant error messages
  }
}

// Validate passwords before submission
const validatePasswords = () => {
  // Only validate if a new password is being set
  if (user.value.password && user.value.password.trim()) {
    const password = user.value.password
    
    // Check password match
    if (password !== user.value.password_confirmation) {
      return { valid: false, message: 'Passwords do not match' }
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
      return { valid: false, message: validationErrors.join(', ') }
    }
  }
  return { valid: true }
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">person</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Profile Settings</h1>
              <p class="text-green-100 text-sm sm:text-base">Manage your account information and preferences</p>
            </div>
          </div>
          <button 
            @click="goToDashboard"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/20 backdrop-blur-sm text-white rounded-xl hover:bg-white/30 transition-all shadow-lg hover:shadow-xl"
          >
            <span class="material-icons-outlined text-lg">arrow_back</span>
            <span>Back to Dashboard</span>
          </button>
        </div>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Left Column - Form -->
      <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <span class="material-icons-outlined text-2xl">edit</span>
            Edit Profile Information
          </h2>
        </div>

        <form class="p-6 space-y-6" @submit.prevent="saveProfile" enctype="multipart/form-data">
          <!-- Image Upload -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-3">Profile Image</label>
            <div class="flex items-center gap-4">
              <label class="relative cursor-pointer">
                <input
                  type="file"
                  class="hidden"
                  accept="image/*"
                  @change="handleFileChange"
                />
                <div class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2">
                  <span class="material-icons-outlined text-base">photo_camera</span>
                  Choose File
                </div>
              </label>
              <span class="text-sm text-gray-600 font-medium">
                {{ selectedFile ? selectedFile.name : 'No file chosen' }}
              </span>
            </div>
          </div>

          <!-- Full Name -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Full Name</label>
            <input
              type="text"
              v-model="user.fullName"
              class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
              placeholder="Enter your full name"
            />
          </div>

          <!-- Username -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Username</label>
            <input
              type="text"
              v-model="user.username"
              class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
              placeholder="Enter your username"
            />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Email</label>
            <input
              type="email"
              v-model="user.email"
              class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
              placeholder="Enter your email address"
            />
          </div>

          <!-- Unit/Sections -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Unit/Sections</label>
            <select
              v-model="user.location"
              @change="updateLocationId"
              :disabled="locationsLoading"
              class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="" disabled>Select Unit/Section</option>
              <option
                v-for="location in locations"
                :key="location.id || location.location_id"
                :value="location.location"
              >
                {{ location.location }}
              </option>
            </select>
            <p v-if="locationsLoading" class="mt-2 text-xs text-yellow-600 flex items-center gap-1">
              <span class="material-icons-outlined text-sm">hourglass_empty</span>
              Loading units/sections...
            </p>
            <p v-if="!locationsLoading && locations.length === 0" class="mt-2 text-xs text-red-600 flex items-center gap-1">
              <span class="material-icons-outlined text-sm">warning</span>
              No units/sections available. Please add units/sections first.
            </p>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Password</label>
            <div class="relative">
              <input
                :type="showPassword ? 'text' : 'password'"
                v-model="user.password"
                placeholder="Leave blank to keep current password"
                class="w-full px-4 py-3 pr-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                @input="checkPasswordRequirements"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-0 h-full flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none"
                tabindex="-1"
              >
                <span class="material-icons-outlined text-xl">
                  {{ showPassword ? 'visibility_off' : 'visibility' }}
                </span>
              </button>
            </div>
            <!-- Password Strength Meter -->
            <div v-if="user.password" class="mt-2">
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
            <div v-if="user.password" class="mt-2 space-y-1">
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
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Confirm Password</label>
            <div class="relative">
              <input
                :type="showConfirmPassword ? 'text' : 'password'"
                v-model="user.password_confirmation"
                placeholder="Confirm password"
                :disabled="!user.password"
                class="w-full px-4 py-3 pr-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                @input="() => { checkPasswordMatch(); }"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                :disabled="!user.password"
                class="absolute right-3 top-0 h-full flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                tabindex="-1"
              >
                <span class="material-icons-outlined text-xl">
                  {{ showConfirmPassword ? 'visibility_off' : 'visibility' }}
                </span>
              </button>
            </div>
          </div>

          <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              class="btn-primary-enhanced flex items-center gap-2 shadow-lg"
              :disabled="loading"
            >
              <span v-if="loading" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">save</span>
              {{ loading ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Right Column - Account Details -->
      <div class="lg:w-1/3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
              <span class="material-icons-outlined text-2xl">account_circle</span>
              Account Details
            </h2>
          </div>
          
          <div class="p-6">
            <div class="flex justify-center mb-6">
              <div class="relative">
                <!-- Add timestamp to image URL to prevent caching -->
                <img
                  :src="user.image ? `${user.image}?t=${imageTimestamp}` : '/images/default-avatar.jpg'"
                  alt="Profile"
                  class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-700 shadow-lg"
                  @error="user.image = ''"
                />
                <span class="absolute bottom-0 right-0 bg-green-500 rounded-full w-6 h-6 border-4 border-gray-800 dark:border-gray-800 shadow-md"></span>
              </div>
            </div>
            
            <div class="space-y-4">
              <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-600 dark:hover:bg-gray-600 transition-colors">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1">Full Name</label>
                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ user.fullName || 'Not set' }}</p>
              </div>
              <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-600 dark:hover:bg-gray-600 transition-colors">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1">Username</label>
                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ user.username || 'Not set' }}</p>
              </div>
              <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-600 dark:hover:bg-gray-600 transition-colors">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1">Email</label>
                <p class="text-base font-semibold text-gray-900 dark:text-white break-words">{{ user.email || 'Not set' }}</p>
              </div>
              <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-600 dark:hover:bg-gray-600 transition-colors">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-1">Unit/Sections</label>
                <p class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <span v-if="user.location" class="material-icons-outlined text-green-400 dark:text-green-400 text-sm">location_on</span>
                  {{ user.location || 'Not set' }}
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
      :title="successModalType === 'success' ? 'Success' : 'Error'"
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

/* Fade in animation */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-out;
}

/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
}
</style>