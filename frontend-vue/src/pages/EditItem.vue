<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useItems from '../composables/useItems'
import useCategories from '../composables/useCategories'
import useLocations from '../composables/useLocations'
import useConditions from '../composables/useConditions'
import useConditionNumbers from '../composables/useConditionNumbers'
import useUsers from '../composables/useUsers'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const route = useRoute()

// Get item data and dropdown data using composables
const { items, fetchitems, loading, error } = useItems()
const { categories, fetchcategories } = useCategories()
const { locations, fetchLocations } = useLocations()
const { conditions, fetchconditions } = useConditions()
const { condition_numbers, fetchcondition_numbers } = useConditionNumbers()
const { users, fetchusers } = useUsers()

// Form data
const editForm = ref({
  unit: '',
  description: '',
  category_id: '',
  quantity: '',
  pac: '',
  unit_value: '',
  date_acquired: '',
  po_number: '',
  location_id: '',
  condition_id: '',
  condition_number_id: '',
  user_id: ''
})

const editLoading = ref(false)
const itemId = ref(null)
const dataLoading = ref(true)
const showSuccessMessage = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Fetch all data when component mounts
onMounted(async () => {
  itemId.value = route.params.uuid
  console.log('EditItem component mounted with UUID:', itemId.value)
  
  try {
    await Promise.all([
      fetchitems(),
      fetchcategories(),
      fetchLocations(),
      fetchconditions(),
      fetchcondition_numbers(),
      fetchusers()
    ])
    
    console.log('Data fetched successfully. Items count:', items.value.length)
    
    // Find and populate the item data
    if (itemId.value) {
      const item = items.value.find(item => item.uuid === itemId.value)
      if (item) {
        console.log('Found item:', item)
        populateForm(item)
      } else {
        console.error('Item not found with UUID:', itemId.value)
        console.log('Available items:', items.value.map(i => ({ uuid: i.uuid, unit: i.unit })))
      }
    }
  } catch (error) {
    console.error('Error fetching data:', error)
  } finally {
    dataLoading.value = false
  }
})

// Populate form with item data
const populateForm = (item) => {
  editForm.value = {
    unit: item.unit || '',
    description: item.description || '',
    category_id: item.category_id || '',
    quantity: item.quantity || '',
    pac: item.pac || '',
    unit_value: item.unit_value || '',
    date_acquired: item.date_acquired || '',
    po_number: item.po_number || '',
    location_id: item.location_id || '',
    condition_id: item.condition_id || '',
    condition_number_id: item.condition_number_id || '',
    user_id: item.user_id || ''
  }
}

// Get current item for display
const currentItem = computed(() => {
  return items.value.find(item => item.uuid === itemId.value)
})

// Save edited item
const saveEditedItem = async () => {
  if (!itemId.value) return
  
  try {
    editLoading.value = true
    
    const response = await axiosClient.put(`/items/${itemId.value}`, editForm.value)
    
    console.log('Update response:', response.data)
    
    // Show success message
    showSuccessMessage.value = true
    
    // Wait a moment to show success message, then navigate back
    setTimeout(() => {
      router.push('/inventory')
    }, 1500)
  } catch (error) {
    console.error('Error updating item:', error)
    
    if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to update item. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    editLoading.value = false
  }
}

// Cancel and go back
const cancelEdit = () => {
  router.push('/inventory')
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

// Format date for input
const formatDateForInput = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toISOString().split('T')[0]
}
</script>

<template>
  <div class="p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
    <!-- Success Message -->
    <div v-if="showSuccessMessage" class="bg-green-100 border border-green-300 rounded-lg p-4 mb-4">
      <div class="flex items-center">
        <span class="material-icons-outlined text-green-600 mr-2">check_circle</span>
        <p class="text-green-800 font-medium">Item updated successfully! Redirecting back to inventory...</p>
      </div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-green-600">EditItem</h1>
        <p class="text-green-600 mt-1">Edit Item Details</p>
        <p class="text-gray-600 text-sm">Update item information</p>
      </div>
      <button 
        @click="cancelEdit"
        class="flex items-center text-white bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors duration-200"
      >
        <span class="material-icons-outlined mr-2">arrow_back</span>
        Back
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading || dataLoading" class="flex justify-center items-center py-10">
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
      <span class="material-icons-outlined text-4xl text-red-400">error_outline</span>
      <p class="mt-2 text-red-500">{{ error }}</p>
      <button 
        @click="fetchitems" 
        class="mt-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
      >
        Try Again
      </button>
    </div>

    <!-- Form -->
    <div v-else-if="currentItem" class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 sm:p-6">
      <form @submit.prevent="saveEditedItem" class="space-y-4 sm:space-y-6">
        <!-- Row 1: Article and Category -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Article *</label>
            <input
              v-model="editForm.unit"
              type="text"
              required
              class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="Enter article name"
            >
          </div>
          <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category *</label>
            <select
              v-model="editForm.category_id"
              required
              class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">Select category</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.category }}
              </option>
            </select>
          </div>
        </div>
        
        <!-- Row 2: Description and Quantity -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
            <textarea
              v-model="editForm.description"
              required
              rows="3"
              class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="Enter item description"
            ></textarea>
          </div>
          <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity *</label>
            <input
              v-model="editForm.quantity"
              type="number"
              min="1"
              required
              class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="Enter quantity"
            >
          </div>
        </div>
        
        <!-- Row 3: Property Account Code and Unit Value -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Property Account Code *</label>
            <input
              v-model="editForm.pac"
              type="text"
              required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="Enter PAC"
            >
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Unit Value *</label>
            <input
              v-model="editForm.unit_value"
              type="number"
              step="0.01"
              min="0"
              required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="Enter unit value"
            >
          </div>
        </div>
        
        <!-- Row 4: Date Acquired and P.O. Number -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date Acquired *</label>
            <div class="relative">
              <input
                v-model="editForm.date_acquired"
                type="date"
                required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              >
              <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <span class="material-icons-outlined text-gray-400">calendar_today</span>
              </span>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">P.O. Number *</label>
            <input
              v-model="editForm.po_number"
              type="text"
              required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="Enter P.O. number"
            >
          </div>
        </div>
        
        <!-- Row 5: Location and Condition -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Location *</label>
            <select
              v-model="editForm.location_id"
              required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">Select location</option>
              <option v-for="location in locations" :key="location.id" :value="location.id">
                {{ location.location }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Condition</label>
            <select
              v-model="editForm.condition_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">Select condition</option>
              <option v-for="condition in conditions" :key="condition.id" :value="condition.id">
                {{ condition.condition }}
              </option>
            </select>
          </div>
        </div>
        
        <!-- Row 6: Condition Number and Issued To -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Condition Number</label>
            <select
              v-model="editForm.condition_number_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">Select condition number</option>
              <option v-for="conditionNumber in condition_numbers" :key="conditionNumber.id" :value="conditionNumber.id">
                {{ conditionNumber.condition_number }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Issued To *</label>
            <select
              v-model="editForm.user_id"
              required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
            >
              <option value="">Select user</option>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.fullname }}
              </option>
            </select>
          </div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex justify-end gap-3 pt-6 border-t border-gray-200">
          <button
            type="button"
            @click="cancelEdit"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            :disabled="editLoading"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
            :disabled="editLoading"
          >
            <span v-if="editLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            {{ editLoading ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Item Not Found -->
    <div v-else class="flex flex-col justify-center items-center py-10">
      <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
      <p class="mt-2 text-gray-500">Item not found</p>
      <button 
        @click="cancelEdit" 
        class="mt-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
      >
        Go Back
      </button>
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
/* Animation for page load */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Form animations */
@keyframes formFadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

/* Ensure consistent styling */
input, select, textarea {
  @apply transition-colors duration-200;
}

input:focus, select:focus, textarea:focus {
  @apply ring-2 ring-green-500 border-green-500;
}

/* Button hover effects */
button {
  @apply transition-all duration-200;
}

button:active {
  @apply transform scale-95;
}

/* Improved focus states for accessibility */
button:focus, input:focus, select:focus, textarea:focus {
  @apply outline-none ring-2 ring-green-500 ring-opacity-50;
}

.material-icons-outlined {
  font-size: 20px;
}
</style>
