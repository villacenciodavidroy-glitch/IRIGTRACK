

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-3 sm:p-4 md:p-6">
    <!-- Header -->
    <div class="flex items-center gap-2 sm:gap-3 mb-4 sm:mb-6 md:mb-8">
      <button @click="goBack" class="inline-flex items-center text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 px-2 py-1 sm:px-0 transition-colors">
        <span class="material-icons-outlined text-lg sm:text-xl">arrow_back</span>
        <span class="ml-1 text-sm sm:text-base">Back</span>
      </button>
      <!-- <h1 class="text-xl font-semibold text-gray-800">Items</h1> -->
    </div>

    <!-- Main Form -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 sm:p-6 max-w-3xl mx-auto">
      <h2 class="text-base sm:text-lg font-medium text-gray-800 dark:text-white mb-4 sm:mb-6">Add new item</h2>
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Article -->
        <div class="form-group">
          <label class="form-label">Article</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">inventory_2</span>
            </span>
            <input
              v-model="formData.unit"
              type="text"
              placeholder="Enter article"
              class="form-input !pl-12"
              required
            />
          </div>
        </div>

        <!-- Category -->
        <div class="form-group">
          <label class="form-label">Category</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">category</span>
            </span>
            <select v-model="formData.category" class="form-select !pl-12" required>
              <option value="" disabled>Select category</option>
              <option v-for="category in categories" 
                  :key="category.id" 
                  :value="category.id || category.category_id">
                {{ category.category }}
              </option>
            </select>
          </div>
        </div>

        <!-- Description -->
        <div class="form-group">
          <label class="form-label">Description</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">description</span>
            </span>
            <input type="text" v-model="formData.description" class="form-input !pl-12" placeholder="Enter description" required>
          </div>
        </div>

        <!-- Property Account Code -->
        <div class="form-group">
          <label class="form-label">Property Account Code</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">qr_code</span>
            </span>
            <input type="text" v-model="formData.propertyAccountCode" class="form-input !pl-12" placeholder="Enter property account code" required>
          </div>
        </div>

        <!-- Unit Value -->
        <div class="form-group">
          <label class="form-label">Unit Value</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">payments</span>
            </span>
            <input type="text" v-model="formData.unitValue" class="form-input !pl-12" placeholder="32,200.00" required>
          </div>
        </div>

        <!-- Quantity -->
        <div class="form-group">
          <label class="form-label">Quantity</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">inventory</span>
            </span>
            <input 
              type="number" 
              v-model="formData.quantity" 
              class="form-input !pl-12" 
              placeholder="Enter quantity" 
              min="1"
              required
            >
          </div>
        </div>

        <!-- Date Acquired -->
        <div class="form-group">
          <label class="form-label">Date Acquired</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">calendar_today</span>
            </span>
            <input type="date" v-model="formData.dateAcquired" class="form-input !pl-12" required>
          </div>
        </div>

        <!-- P.O Number -->
        <div class="form-group">
          <label class="form-label">P.O Number</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">receipt</span>
            </span>
            <input type="text" v-model="formData.poNumber" class="form-input !pl-12" placeholder="Enter P.O number" required>
          </div>
        </div>

        <!-- Location -->
        <div class="form-group">
          <label class="form-label">Location</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">location_on</span>
            </span>
            <select v-model="formData.location" class="form-select !pl-12" required>
              <option value="" disabled>Select location</option>
              <option  v-for="location in locations" 
                  :key="location.id" 
                  :value="location.id || location.location_id">
                {{ location.location }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Issued To</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">location_on</span>
            </span>
            <select v-model="formData.issuedTo" class="form-select !pl-12" required>
              <option value="" disabled>Select Person</option>
              <option v-for="user in users" 
                  :key="user.id" 
                  :value="user.id || user.user.id">
                {{ `${user.fullname.charAt(0)}. MANDARIN` }}
              </option>
            </select>
          </div>
        </div>

        <!-- Condition -->
        <div class="form-group" v-if="!isSupplyCategory">
          <label class="form-label">Condition</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">build</span>
            </span>
            <select v-model="formData.condition" class="form-select !pl-12" required>
              <option value="" disabled>Select condition</option>
              <option v-for="condition in conditions" 
                  :key="condition.id" 
                  :value="condition.id || condition.condition_id">
                {{ condition.condition }}
              </option>
            </select>
          </div>
        </div>

        

         <!-- Condition -->
        <div class="form-group" v-if="!isSupplyCategory">
          <label class="form-label">Condition Number</label>
          <div class="relative flex items-center">
            <span class="absolute left-4 text-gray-400">
              <span class="material-icons-outlined">tag</span>
            </span>
            <select v-model="formData.conditionNumber" class="form-select !pl-12" required>
              <option value="" disabled>Select Condition Number</option>
              <option v-for="condition_number in condition_numbers" 
                  :key="condition_number.id" 
                  :value="condition_number.id || condition_number.condition_number_id">
                {{ condition_number.condition_number }}
              </option>
            </select>
          </div>
        </div>


        <!-- Image Upload -->
        <div class="form-group">
            <label class="form-label">image</label>
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
              <p v-if="errors.image" class="mt-1 text-sm text-red-600">{{ errors.image[0] }}</p>
            </div>
          </div>

        <!-- Maintenance Link -->
        <div class="flex items-center text-sm">
          <button type="button" @click="goToMaintenance" class="inline-flex items-center text-green-600 hover:text-green-700 font-medium">
            <span class="material-icons-outlined text-xl mr-1">schedule</span>
            Maintenance
          </button>
        </div>

        <!-- Submit Button -->
         <div class="flex justify-end mt-6">
            <button 
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-sm">refresh</span>
              {{ isSubmitting ? 'Creating...' : 'Create Item' }}
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
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import useLocations from '../composables/useLocations'
import useConditions from '../composables/useConditions'
import usecategories from '../composables/useCategories'
import useConditionNumbers from '../composables/useConditionNumbers'
import axiosClient from '../axios'
import useUsers from '../composables/useUsers'
import SuccessModal from '../components/SuccessModal.vue'
import { computed } from 'vue'


const router = useRouter()
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
  unit: '',
  category: '',
  description: '',
  propertyAccountCode: '',
  unitValue: '',
  quantity: 1,
  dateAcquired: '',
  poNumber: '',
  location: '',
  issuedTo: '',
  condition: '',
  conditionNumber: '',
  image: ''
})

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
  formData.value.image = file
  
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
  formData.value.image = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}



const { conditions } = useConditions(formData)
const { locations } = useLocations(formData)
const { categories } = usecategories(formData)
const { condition_numbers } = useConditionNumbers(formData)
const { users } = useUsers(formData)

// const handleImageUpload = (event) => {
//   const file = event.target.files[0]
//   if (file) {
//     formData.value.image = file
//   }
// }

const goBack = () => {
  router.push('/inventory')
}

const goToMaintenance = () => {
  router.push('/maintenance')
}

const handleSubmit = async () => {
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true
    errors.value = {}

    // Validate passwords
    // if (!validatePasswords()) {
    //   isSubmitting.value = false
    //   return
    // }

    if (!formData.value.category) {
      console.error('No category selected')
      errors.value.category = ['Please select a category']
      isSubmitting.value = false
      return
    }


    // Check if location is selected
    if (!formData.value.location) {
      console.error('No location selected')
      errors.value.location = ['Please select a location']
      isSubmitting.value = false
      return
    }

    if (!isSupplyCategory.value) {
      if (!formData.value.condition) {
        console.error('No condition selected')
        errors.value.condition = ['Please select a condition']
        isSubmitting.value = false
        return
      }

    if (!formData.value.conditionNumber) {
      console.error('No condition number selected')
      errors.value.conditionNumber = ['Please select a condition number']
      isSubmitting.value = false
      return
    }
  }


    

    
    // Create FormData object for file upload
    const formDataToSend = new FormData()
    
    // Append all form fields
    formDataToSend.append('unit', formData.value.unit)
    formDataToSend.append('description', formData.value.description)
    formDataToSend.append('pac', formData.value.propertyAccountCode)
    formDataToSend.append('unit_value', formData.value.unitValue)
    formDataToSend.append('quantity', formData.value.quantity)
    formDataToSend.append('date_acquired', formData.value.dateAcquired)
    formDataToSend.append('po_number', formData.value.poNumber)
    formDataToSend.append('category_id', formData.value.category)
    formDataToSend.append('location_id', formData.value.location)
    formDataToSend.append('condition_id', formData.value.condition)
    formDataToSend.append('condition_number_id', formData.value.conditionNumber)
    formDataToSend.append('user_id', formData.value.issuedTo)
    
    console.log('Form data being sent:', {
      unit: formData.value.unit,
      description: formData.value.description,
      pac: formData.value.propertyAccountCode,
      unit_value: formData.value.unitValue,
      quantity: formData.value.quantity,
      date_acquired: formData.value.dateAcquired,
      po_number: formData.value.poNumber,
      category_id: formData.value.category,
      location_id: formData.value.location,
      condition_id: formData.value.condition,
      condition_number_id: formData.value.conditionNumber,
      user_id: formData.value.issuedTo
    })
    
    


    
    // Append the image file if it exists
    if (formData.value.image) {
      formDataToSend.append('image', formData.value.image)
    }

    console.log('About to send form data with image')
    console.log(formData) 

    // Send request to Laravel API with proper headers for multipart form data
    const response = await axiosClient.post('/items', formDataToSend, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    if (response.data) {
      console.log('item created successfully:', response.data)
      router.push('/inventory')
    }
  } catch (error) {
    console.error('Full error object:', error)
    console.error('Error response data:', error.response?.data)
    
    // More detailed error logging
    if (error.response) {
      console.error('Status:', error.response.status)
      console.error('Headers:', error.response.headers)
      console.error('Data:', error.response.data)
    } else if (error.request) {
      console.error('Request was made but no response received:', error.request)
    } else {
      console.error('Error setting up request:', error.message)
    }
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      errors.value = {
        general: [error.response.data.message]
      }
    } else {
      errors.value = {
        general: ['An unexpected error occurred. Please try again.']
      }
    }
    
    // Show error message
    successMessage.value = 'Error creating item: ' + (error.response?.data?.message || 'An unexpected error occurred. Please check the form and try again.')
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    isSubmitting.value = false
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

// Assuming categories are like: [{ id: 1, category: 'Supply' }, ...]
const isSupplyCategory = computed(() => {
  const selected = categories.value.find(cat => 
    cat.id == formData.value.category || cat.category_id == formData.value.category
  );
  return selected && selected.category?.toLowerCase() === 'supply';
});
</script>

<style scoped>
.form-group {
  @apply space-y-1;
}

.form-label {
  @apply block text-sm font-medium text-gray-700;
}

.form-input {
  @apply block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm;
  height: 45px;
}

.form-select {
  @apply block w-full rounded-lg border-gray-200 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm;
  height: 45px;
}

.btn-primary {
  @apply px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200;
}

.material-icons-outlined {
  font-size: 20px;
}
</style> 