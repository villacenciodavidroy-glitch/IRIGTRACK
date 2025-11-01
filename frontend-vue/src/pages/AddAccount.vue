<template>
  
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-3 sm:p-4 md:p-6">
      <!-- Header -->
      <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6 md:mb-8">
        <button @click="goBack" class="inline-flex items-center text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 px-2 py-1 sm:px-0 transition-colors">
          <span class="material-icons-outlined text-lg sm:text-xl">arrow_back</span>
          <span class="ml-1 text-sm sm:text-base">Back</span>
        </button>
      </div>

      <!-- Main Form -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 sm:p-6 max-w-3xl mx-auto">
        <h2 class="text-base sm:text-lg font-medium text-gray-800 dark:text-white mb-4 sm:mb-6">Add new account</h2>
        
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
              <p v-if="errors.account_type" class="mt-1 text-sm text-red-600">{{ errors.account_type[0] }}</p>
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
                class="flex flex-col items-center justify-center w-full h-32 px-4 transition bg-white border-2 border-gray-300 rounded-md appearance-none cursor-pointer"
                :class="{ 
                  'border-dashed hover:border-gray-400': !selectedFile,
                  'border-solid border-green-500': selectedFile,
                  'border-blue-400 border-solid': dragOver
                }"
              >
                <div v-if="selectedFile" class="flex items-center space-x-2">
                  <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    class="w-16 h-16 object-cover rounded"
                    alt="Preview"
                  />
                  <div class="flex flex-col">
                    <span class="text-sm font-medium text-gray-900">{{ selectedFile.name }}</span>
                    <span class="text-xs text-gray-500">{{ formatFileSize(selectedFile.size) }}</span>
                  </div>
                  <button
                    @click.stop="clearFile"
                    class="p-1 text-gray-500 hover:text-gray-700"
                    title="Remove file"
                  >
                    <span class="material-icons-outlined">close</span>
                  </button>
                </div>
                <div v-else class="flex items-center space-x-2">
                  <span class="material-icons-outlined text-gray-400">cloud_upload</span>
                  <span class="font-medium text-gray-600">
                    <span class="text-green-600 hover:underline">Choose file</span> or drag and drop
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
              <p class="mt-2 text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
              <p v-if="errors.avatar" class="mt-1 text-sm text-red-600">{{ errors.avatar[0] }}</p>
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
              <p v-if="errors.fullname" class="mt-1 text-sm text-red-600">{{ errors.fullname[0] }}</p>
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
              <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
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
            <p v-if="errors.location" class="mt-1 text-sm text-red-600">{{ errors.location[0] }}</p>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="relative">
              <span class="absolute left-0 top-0 text-gray-400">
                <span class="material-icons-outlined">lock</span>
              </span>
              <input 
                type="password" 
                v-model="formData.password"
                class="form-input"
                placeholder="Enter password"
                required
              >
              
            </div>
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="relative">
              <span class="absolute left-0 top-0 text-gray-400">
                <span class="material-icons-outlined">lock</span>
              </span>
              <input 
                type="password" 
                v-model="formData.confirmPassword"
                class="form-input"
                placeholder="Confirm password"
                required
                @input="errors.password_confirmation = []"
              >
              
            </div>
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ errors.password_confirmation[0] }}</p>
          </div>

          <!-- General Error Message -->
          <div v-if="errors.general" class="mt-4">
            <p class="text-sm text-red-600">{{ errors.general[0] }}</p>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end mt-6">
            <button 
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-sm">refresh</span>
              {{ isSubmitting ? 'Creating...' : 'Create Account' }}
            </button>
          </div>
        </form>
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

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import axiosClient from '../axios'
import useLocations from '../composables/useLocations'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const route = useRoute()
const errors = ref({})
const dragOver = ref(false)
const selectedFile = ref(null)
const previewUrl = ref(null)
const fileInput = ref(null)
const isSubmitting = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

const formData = ref({
  accountType: '',
  avatar: null,
  fullname: '',
  email: '',
  location: '',
  password: '',
  confirmPassword: ''
})

// Sample data for dropdowns
const accountTypes = ref([
  { value: 'admin', label: 'Admin' },
  { value: 'user', label: 'User' }
])

const { locations } = useLocations(formData)

// Set account type from URL parameter
onMounted(() => {
  console.log('=== COMPONENT MOUNTED ===')
  console.log('Route query:', route.query)
  console.log('Current form data:', formData.value)
  
  const accountTypeFromUrl = route.query.type
  console.log('Account type from URL:', accountTypeFromUrl)
  
  if (accountTypeFromUrl && (accountTypeFromUrl === 'admin' || accountTypeFromUrl === 'user')) {
    formData.value.accountType = accountTypeFromUrl
    console.log('Account type set to:', formData.value.accountType)
  } else {
    console.log('No valid account type found in URL')
  }
  
  console.log('Final form data after URL processing:', formData.value)
})

// const locations = ref([])

// // Fetch locations from the backend
// const fetchLocations = async () => {
//   try {
//     const response = await axiosClient.get('/locations')
//     if (response.data && response.data.data) {
//       locations.value = response.data.data
//       console.log('Available locations:', locations.value)
//     }
//   } catch (error) {
//     console.error('Error fetching locations:', error)
//   }
// }

// // Watch for location changes
// watch(() => formData.value.location, (newValue) => {
//   console.log('Location selected:', newValue)
// })

// // Call fetchLocations when component is mounted
// onMounted(() => {
//   fetchLocations()
// })

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
    errors.value.avatar = ['File size should not exceed 10MB']
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

const validatePasswords = () => {
  if (formData.value.password !== formData.value.confirmPassword) {
    errors.value.password_confirmation = ['Passwords do not match']
    return false
  }
  if (formData.value.password.length < 8) {
    errors.value.password = ['Password must be at least 8 characters long']
    return false
  }
  // Clear any previous errors if validation passes
  errors.value.password = []
  errors.value.password_confirmation = []
  return true
}

const handleSubmit = async () => {
  console.log('=== FORM SUBMISSION STARTED ===')
  console.log('isSubmitting:', isSubmitting.value)
  
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true
    errors.value = {}

    console.log('Form data before validation:', formData.value)

    // Validate all required fields
    if (!formData.value.accountType) {
      console.log('ERROR: Account type not set')
      errors.value.account_type = ['Please select an account type']
      isSubmitting.value = false
      return
    }

    if (!formData.value.fullname.trim()) {
      console.log('ERROR: Full name not set')
      errors.value.fullname = ['Full name is required']
      isSubmitting.value = false
      return
    }

    if (!formData.value.email.trim()) {
      console.log('ERROR: Email not set')
      errors.value.email = ['Email is required']
      isSubmitting.value = false
      return
    }

    if (!formData.value.location) {
      console.log('ERROR: Location not set')
      errors.value.location = ['Please select a location']
      isSubmitting.value = false
      return
    }

    // Validate passwords
    if (!validatePasswords()) {
      console.log('ERROR: Password validation failed')
      isSubmitting.value = false
      return
    }

    console.log('All validations passed, proceeding with submission...')

    // Create FormData object for file upload
    const formDataToSend = new FormData()
    
    // Append all form fields
    formDataToSend.append('role', formData.value.accountType)
    formDataToSend.append('fullname', formData.value.fullname)
    formDataToSend.append('email', formData.value.email)
    formDataToSend.append('location_id', formData.value.location)
    formDataToSend.append('password', formData.value.password)
    formDataToSend.append('password_confirmation', formData.value.confirmPassword)
    
    // Append the image file if it exists
    if (formData.value.avatar) {
      formDataToSend.append('image', formData.value.avatar)
    }

    // Debug: Log form data being sent
    console.log('Form data being sent:', {
      role: formData.value.accountType,
      fullname: formData.value.fullname,
      email: formData.value.email,
      location_id: formData.value.location,
      password: formData.value.password,
      password_confirmation: formData.value.confirmPassword,
      hasImage: !!formData.value.avatar
    })

    console.log('Sending request to /register endpoint...')

    // Test API connectivity first
    try {
      const testResponse = await axiosClient.get('/')
      console.log('API connectivity test:', testResponse.data)
    } catch (testError) {
      console.error('API connectivity test failed:', testError)
    }

    // Send request to Laravel API with proper headers for multipart form data
    const response = await axiosClient.post('/register', formDataToSend, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    if (response.data) {
      console.log('Registration successful:', response.data)
      successMessage.value = 'Account created successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      
      // Reset form after successful creation
      setTimeout(() => {
        formData.value = {
          accountType: '',
          avatar: null,
          fullname: '',
          email: '',
          location: '',
          password: '',
          confirmPassword: ''
        }
        selectedFile.value = null
        previewUrl.value = null
        if (fileInput.value) {
          fileInput.value.value = ''
        }
        errors.value = {}
      }, 2000)
    }
  } catch (error) {
    console.error('Registration error:', error)
    console.error('Error response:', error.response)
    console.error('Error status:', error.response?.status)
    console.error('Error data:', error.response?.data)
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
      console.error('Validation errors:', error.response.data.errors)
      
      // Show specific error messages
      if (error.response.data.errors.email) {
        successMessage.value = `Email error: ${error.response.data.errors.email[0]}`
        successModalType.value = 'error'
        showSuccessModal.value = true
      } else if (error.response.data.errors.password) {
        successMessage.value = `Password error: ${error.response.data.errors.password[0]}`
        successModalType.value = 'error'
        showSuccessModal.value = true
      } else if (error.response.data.errors.location_id) {
        successMessage.value = `Location error: ${error.response.data.errors.location_id[0]}`
        successModalType.value = 'error'
        showSuccessModal.value = true
      }
    } else if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = `An unexpected error occurred: ${error.message}`
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    isSubmitting.value = false
    console.log('=== FORM SUBMISSION COMPLETED ===')
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}
</script>

<style scoped>
.form-group {
  @apply space-y-1;
}

.form-label {
  @apply block text-sm font-medium text-gray-700;
}

.form-input, .form-select {
  @apply block w-full pl-12 pr-3 py-2.5 text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500;
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
</style>
