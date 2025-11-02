<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-3 sm:p-4 md:p-6">
    <!-- Header -->
    <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6 md:mb-8">
      <button @click="goBack" class="inline-flex items-center text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 px-2 py-1 sm:px-0 transition-colors">
        <span class="material-icons-outlined text-lg sm:text-xl">arrow_back</span>
        <span class="ml-1 text-sm sm:text-base">Back</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 sm:p-6 max-w-3xl mx-auto flex justify-center items-center py-8 sm:py-12">
      <div class="flex items-center gap-2 sm:gap-3">
        <span class="material-icons-outlined animate-spin text-green-600 text-xl sm:text-2xl">refresh</span>
        <span class="text-sm sm:text-base text-gray-800 dark:text-gray-200">Loading user data...</span>
      </div>
    </div>

    <!-- Main Form -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 sm:p-6 max-w-3xl mx-auto">
      <h2 class="text-base sm:text-lg font-medium text-gray-800 dark:text-gray-200 mb-4 sm:mb-6">Edit Account</h2>
      
      <form @submit.prevent="handleSubmit" class="space-y-5">
        <!-- Account Type -->
        <div class="form-group">
          <label class="form-label">Account Type</label>
          <div class="relative">
            <span class="absolute left-0 top-0 text-gray-400">
              <span class="material-icons-outlined">badge</span>
            </span>
            <select 
              v-model="formData.accountType"
              class="form-select"
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

        <!-- Avatar Upload -->
        <div class="form-group">
          <label class="form-label">Avatar</label>
          <div class="mt-1">
            <div
              @click="$refs.fileInput.click()"
              @dragover.prevent="dragOver = true"
              @dragleave.prevent="dragOver = false"
              @drop.prevent="handleFileDrop"
              class="flex flex-col items-center justify-center w-full h-32 px-4 transition bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-md appearance-none cursor-pointer"
              :class="{ 
                'border-dashed hover:border-gray-400 dark:hover:border-gray-500': !selectedFile && !previewUrl,
                'border-solid border-green-500': selectedFile || previewUrl,
                'border-blue-400 border-solid': dragOver
              }"
            >
              <div v-if="selectedFile || previewUrl" class="flex items-center space-x-2">
                <img
                  :src="previewUrl"
                  class="w-16 h-16 object-cover rounded"
                  alt="Preview"
                />
                <div class="flex flex-col">
                  <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ selectedFile ? selectedFile.name : 'Current Image' }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ selectedFile ? formatFileSize(selectedFile.size) : 'Click to change' }}
                  </span>
                </div>
                <button
                  @click.stop="clearFile"
                  class="p-1 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                  title="Remove file"
                >
                  <span class="material-icons-outlined">close</span>
                </button>
              </div>
              <div v-else class="flex items-center space-x-2">
                <span class="material-icons-outlined text-gray-400 dark:text-gray-500">cloud_upload</span>
                <span class="font-medium text-gray-600 dark:text-gray-300">
                  <span class="text-green-600 dark:text-green-400 hover:underline">Choose file</span> or drag and drop
                </span>
              </div>
              <input
                ref="fileInput"
                type="file"
                @change="handleImageUpload"
                accept="image/*"
                class="hidden"
              >
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 10MB</p>
            <p v-if="errors.image" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.image[0] }}</p>
          </div>
        </div>

        <!-- Full Name -->
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="relative">
            <span class="absolute left-0 top-0 text-gray-400">
              <span class="material-icons-outlined">person</span>
            </span>
            <input 
              type="text" 
              v-model="formData.fullname"
              class="form-input"
              placeholder="Enter full name"
              required
            >
            <p v-if="errors.fullname" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.fullname[0] }}</p>
          </div>
        </div>

        <!-- Username -->
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="relative">
            <span class="absolute left-0 top-0 text-gray-400">
              <span class="material-icons-outlined">alternate_email</span>
            </span>
            <input 
              type="text" 
              v-model="formData.username"
              class="form-input"
              placeholder="Enter username"
              required
            >
            <p v-if="errors.username" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.username[0] }}</p>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="relative">
            <span class="absolute left-0 top-0 text-gray-400">
              <span class="material-icons-outlined">email</span>
            </span>
            <input 
              type="email" 
              v-model="formData.email"
              class="form-input"
              placeholder="Enter email address"
              required
            >
            <p v-if="errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.email[0] }}</p>
          </div>
        </div>

        <!-- Location -->
        <div class="form-group">
          <label class="form-label">Location</label>
          <div class="relative">
            <span class="absolute left-0 top-0 text-gray-400">
              <span class="material-icons-outlined">location_on</span>
            </span>
            <select 
              v-model="formData.location"
              class="form-select"
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

        <!-- Password Section -->
        <div class="border-t border-gray-200 dark:border-gray-600 pt-4 mt-6">
          <h3 class="text-md font-medium mb-4 text-gray-800 dark:text-gray-200">Change Password (Optional)</h3>
          
          <!-- Password -->
          <div class="form-group">
            <label class="form-label">New Password</label>
            <div class="relative">
              <span class="absolute left-0 top-0 text-gray-400">
                <span class="material-icons-outlined">lock</span>
              </span>
              <input 
                type="password" 
                v-model="formData.password"
                class="form-input"
                placeholder="Enter new password (leave blank to keep current)"
              >
            </div>
            <p v-if="errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.password[0] }}</p>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <div class="relative">
              <span class="absolute left-0 top-0 text-gray-400">
                <span class="material-icons-outlined">lock</span>
              </span>
              <input 
                type="password" 
                v-model="formData.password_confirmation"
                class="form-input"
                placeholder="Confirm new password"
                :disabled="!formData.password"
              >
            </div>
          </div>
        </div>

        <!-- General Error Message -->
        <div v-if="errors.general" class="mt-4">
          <p class="text-sm text-red-600 dark:text-red-400">{{ errors.general[0] }}</p>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end mt-6">
          <button 
            type="submit"
            :disabled="isSubmitting"
            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-sm">refresh</span>
            {{ isSubmitting ? 'Updating...' : 'Update Account' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DefaultLayout from '../layouts/DefaultLayout.vue'
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
  { value: 'user', label: 'User' }
])

const { locations } = useLocations(formData)

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
    const userData = response.data.data
    
    // Populate form data
    formData.value.accountType = userData.role
    formData.value.fullname = userData.fullname
    formData.value.username = userData.username
    formData.value.email = userData.email
    formData.value.location = userData.location_id
    
    // Set image preview if available
    if (userData.image) {
      previewUrl.value = userData.image
    }
    
    console.log('User data loaded:', userData)
  } catch (error) {
    console.error('Error fetching user data:', error)
    errors.value.general = ['Failed to load user data. Please try again.']
  } finally {
    loading.value = false
  }
}

const validatePasswords = () => {
  // Only validate if a new password is being set
  if (formData.value.password) {
    if (formData.value.password !== formData.value.password_confirmation) {
      errors.value.password = ['Passwords do not match']
      return false
    }
    if (formData.value.password.length < 8) {
      errors.value.password = ['Password must be at least 8 characters long']
      return false
    }
  }
  return true
}

const handleSubmit = async () => {
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true
    errors.value = {}

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
    
    // Append all form fields
    formDataToSend.append('role', formData.value.accountType)
    formDataToSend.append('fullname', formData.value.fullname)
    formDataToSend.append('username', formData.value.username)
    formDataToSend.append('email', formData.value.email)
    formDataToSend.append('location_id', formData.value.location)
    
    // Only append password if it's being changed
    if (formData.value.password) {
      formDataToSend.append('password', formData.value.password)
      formDataToSend.append('password_confirmation', formData.value.password_confirmation)
    }
    
    // Append the image file if it exists
    if (formData.value.avatar) {
      formDataToSend.append('image', formData.value.avatar)
    }

    console.log('Sending update request for user ID:', userId.value)

    // Send PUT request to Laravel API (axios will handle Content-Type automatically for FormData)
    const response = await axiosClient.put(`/users/${userId.value}`, formDataToSend)

    if (response.data) {
      console.log('Account updated successfully:', response.data)
      router.push('/admin')
    }
  } catch (error) {
    console.error('Error updating account:', error)
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else {
      errors.value = {
        general: ['An unexpected error occurred. Please try again.']
      }
    }
  } finally {
    isSubmitting.value = false
  }
}

// Fetch user data when component is mounted
onMounted(() => {
  if (userId.value) {
    fetchUserData()
  } else {
    router.push('/admin')
  }
})
</script>

<style scoped>
.form-group {
  @apply space-y-1;
}

.form-label {
  @apply block text-sm font-medium text-gray-700 dark:text-gray-300;
}

.form-input, .form-select {
  @apply block w-full pl-12 pr-3 py-2.5 text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500;
}

/* Add new styles for the icon container */
.relative {
  position: relative;
}

.relative span.absolute {
  @apply flex items-center justify-center;
  height: 100%;
  width: 40px;
  pointer-events: none;
}

.material-icons-outlined {
  @apply text-xl leading-none;
}

/* Dark mode specific adjustments */
.dark .form-input:disabled {
  @apply bg-gray-600 text-gray-400;
}

.dark .form-select:disabled {
  @apply bg-gray-600 text-gray-400;
}
</style>