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
  password: '',
  password_confirmation: ''
})
const selectedFile = ref(null)
const loading = ref(false)
const imageTimestamp = ref(Date.now()) // Add timestamp for cache busting

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
      user.value.location = res.data.data.location?.location || ''
      user.value.image = `${res.data.data.image}`
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
    formData.append('password', user.value.password)
    formData.append('password_confirmation', user.value.password_confirmation || user.value.password)
  }

  try {
    const res = await axiosClient.put(`/users/${userId}`, formData)

    if (res.status === 200 && res.data.success) {
      // ✅ Update image with timestamp to force refresh
      if (res.data.data.image) {
        user.value.image = `${res.data.data.image}?t=${Date.now()}`
        imageTimestamp.value = Date.now()
      }

      // Update user data after successful update
      user.value.fullName = res.data.data.fullname
      user.value.username = res.data.data.username
      user.value.email = res.data.data.email
      user.value.location_id = res.data.data.location_id
      user.value.location = res.data.data.location?.location || ''
      
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
</script>

<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Back to Dashboard Button -->
    <button 
      @click="goToDashboard"
      class="mb-6 inline-flex items-center text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
    >
      <span class="material-icons-outlined mr-1">arrow_back</span>
      Back to Dashboard
    </button>

    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Left Column - Form -->
      <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Admin</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-6">Profile</p>

        <form class="space-y-6" @submit.prevent="saveProfile" enctype="multipart/form-data">
          <!-- Image Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Profile Image</label>
            <div class="flex items-center">
              <label class="relative cursor-pointer">
                <input
                  type="file"
                  class="hidden"
                  accept="image/*"
                  @change="handleFileChange"
                />
                <div class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                  Choose File
                </div>
              </label>
              <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">
                {{ selectedFile ? selectedFile.name : 'No file chosen' }}
              </span>
            </div>
          </div>

          <!-- Your other form fields remain the same -->
          <!-- Full Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
            <input
              type="text"
              v-model="user.fullName"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
            <input
              type="email"
              v-model="user.email"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>

          <!-- Location -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Location</label>
            <select
              v-model="user.location"
              :disabled="locationsLoading"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <option value="" disabled>Select Location</option>
              <option
                v-for="location in locations"
                :key="location.id || location.location_id"
                :value="location.location"
              >
                {{ location.location }}
              </option>
            </select>
            <p v-if="locationsLoading" class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
              Loading locations...
            </p>
            <p v-if="!locationsLoading && locations.length === 0" class="mt-1 text-xs text-red-600 dark:text-red-400">
              No locations available. Please add locations first.
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
            <input
              type="password"
              placeholder="Password"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
            <input
              type="password"
              placeholder="Confirm password"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>

          <button
            type="submit"
            class="mt-4 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            :disabled="loading"
          >
            {{ loading ? 'Saving...' : 'Save Changes' }}
          </button>
        </form>
      </div>

      <!-- Right Column - Account Details -->
      <div class="lg:w-1/3">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Account Details</h2>
          
          <div class="flex justify-center mb-6">
            <div class="relative">
              <!-- Add timestamp to image URL to prevent caching -->
              <img
                :src="user.image + '?t=' + imageTimestamp"
                alt="Profile"
                class="w-32 h-32 rounded-full object-cover border-4 border-white dark:border-gray-600"
              />
              <span class="absolute bottom-0 right-0 bg-green-500 rounded-full w-6 h-6 border-2 border-white dark:border-gray-600"></span>
            </div>
          </div>
          
          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-500 dark:text-gray-400">Full Name</label>
              <p class="text-gray-800 dark:text-gray-200">{{ user.fullName }}</p>
            </div>
            <div>
              <label class="block text-sm text-gray-500 dark:text-gray-400">Email</label>
              <p class="text-gray-800 dark:text-gray-200">{{ user.email }}</p>
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