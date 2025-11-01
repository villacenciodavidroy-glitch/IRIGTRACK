<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'
import { useDebouncedRef } from '../composables/useDebounce'

const items = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const showRestoreModal = ref(false)
const showDeleteModal = ref(false)
const selectedItem = ref(null)
const restoreLoading = ref(false)
const deleteLoading = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Fetch deleted items from API
const fetchDeletedItems = async () => {
  loading.value = true
  error.value = null
  
  try {
    console.log('Fetching deleted items from API...')
    const response = await axiosClient.get('/items/deleted')
    console.log('API Response:', response)
    console.log('Response data:', response.data)
    items.value = response.data.data || []
    console.log('Deleted items from API:', items.value)
  } catch (err) {
    console.error('Error fetching deleted items:', err)
    console.error('Error details:', err.response?.data)
    console.error('Error status:', err.response?.status)
    error.value = `Failed to load deleted items: ${err.response?.data?.message || err.message}. Please try again.`
  } finally {
    loading.value = false
  }
}

// Fetch deleted items when component mounts
onMounted(() => {
  fetchDeletedItems()
})

const filteredItems = computed(() => {
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  if (!query) return formattedItems.value
  
  // Optimize search: only search relevant fields
  return formattedItems.value.filter(item => {
    return (
      (item.article || '').toLowerCase().includes(query) ||
      (item.description || '').toLowerCase().includes(query) ||
      (item.propertyAccountCode || '').toLowerCase().includes(query) ||
      (item.location || '').toLowerCase().includes(query)
    )
  })
})

// Reset to first page when search query changes
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
})

const totalItems = computed(() => filteredItems.value.length)
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value))
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage.value + 1)
const endIndex = computed(() => Math.min(startIndex.value + itemsPerPage.value - 1, totalItems.value))

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredItems.value.slice(start, end)
})

// Map API data to the format expected by the table
const formattedItems = computed(() => {
  return items.value.map(item => {
    console.log('Processing item:', item)
    return {
      uuid: item.uuid,
      id: item.id,
      article: item.unit || '',
      description: item.description || '',
      propertyAccountCode: item.pac || '',
      unitValue: item.unit_value || '',
      dateAcquired: item.date_acquired || '',
      poNumber: item.po_number || '',
      location: item.location || '',
      deletedAt: item.deleted_at ? new Date(item.deleted_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) : 'Invalid Date',
      deletionReason: item.deletion_reason || 'No reason provided'
    }
  })
})

const openRestoreModal = (item) => {
  selectedItem.value = item
  showRestoreModal.value = true
}

const closeRestoreModal = () => {
  showRestoreModal.value = false
  selectedItem.value = null
}

const openDeleteModal = (item) => {
  selectedItem.value = item
  showDeleteModal.value = true
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  selectedItem.value = null
}

const handleRestore = async () => {
  if (!selectedItem.value) return
  
  restoreLoading.value = true
  
  try {
    // Call the backend restore API
    const response = await axiosClient.post(`/items/restore/${selectedItem.value.uuid}`)
    
    console.log('Restore response:', response.data)
    
    // Show success message
    successMessage.value = response.data?.message || 'Item has been restored successfully and will appear in the inventory.'
    successModalType.value = 'success'
    showSuccessModal.value = true
    
    // Refresh the deleted items list
    await fetchDeletedItems()
  } catch (error) {
    console.error('Error restoring item:', error)
    
    // Show error message
    if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to restore item. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    restoreLoading.value = false
    closeRestoreModal()
  }
}

const handleDeletePermanently = async () => {
  if (!selectedItem.value) return
  
  deleteLoading.value = true
  
  try {
    // Call the backend force delete API
    const response = await axiosClient.delete(`/items/force-delete/${selectedItem.value.uuid}`)
    
    console.log('Force delete response:', response.data)
    
    // Show success message
    successMessage.value = response.data?.message || 'Item has been permanently deleted.'
    successModalType.value = 'success'
    showSuccessModal.value = true
    
    // Refresh the deleted items list
    await fetchDeletedItems()
  } catch (error) {
    console.error('Error permanently deleting item:', error)
    
    // Show error message
    if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to permanently delete item. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    deleteLoading.value = false
    closeDeleteModal()
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}
</script>

<template>
  <div class="p-3 sm:p-4 md:p-6 bg-white dark:bg-gray-900 rounded-lg">
    <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-white mb-4 sm:mb-6">Deleted Items History</h1>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
      <div class="p-4">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
          <div class="flex items-center">
            <span class="text-gray-600 dark:text-gray-300 mr-2">Show</span>
            <select 
              v-model="itemsPerPage"
              class="border border-gray-300 dark:border-gray-600 rounded px-3 py-1 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 focus:outline-none focus:border-green-500"
            >
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <span class="text-gray-600 dark:text-gray-300 ml-2">entries</span>
          </div>

          <div class="w-full sm:w-auto">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search..."
              class="w-full px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500"
            />
          </div>
        </div>

        <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
          <!-- Loading indicator -->
          <div v-if="loading" class="flex justify-center items-center py-10">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
          </div>
          
          <!-- Error state -->
          <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
            <span class="material-icons-outlined text-3xl sm:text-4xl text-red-400 dark:text-red-500">error_outline</span>
            <p class="mt-2 text-sm sm:text-base text-red-500 dark:text-red-400 text-center px-4">{{ error }}</p>
            <button 
              @click="fetchDeletedItems" 
              class="mt-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm sm:text-base"
            >
              Try Again
            </button>
          </div>
          
          <!-- Empty state -->
          <div v-else-if="filteredItems.length === 0" class="flex flex-col justify-center items-center py-10">
            <span class="material-icons-outlined text-3xl sm:text-4xl text-gray-400 dark:text-gray-500">delete_outline</span>
            <p class="mt-2 text-sm sm:text-base text-gray-500 dark:text-gray-400">No deleted items found</p>
            <p class="text-xs sm:text-sm text-gray-400 dark:text-gray-500 mt-1 text-center px-4">Items deleted from the inventory will appear here.</p>
            <p v-if="searchQuery" class="text-xs sm:text-sm text-gray-400 dark:text-gray-500 mt-1">Try adjusting your search query</p>
          </div>
          
          <!-- Table with data -->
          <table v-else class="min-w-full bg-white dark:bg-gray-800 min-w-[1000px]">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">ARTICLE</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden md:table-cell">DESCRIPTION</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden lg:table-cell">PAC</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden lg:table-cell">UNIT VALUE</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden xl:table-cell">DATE ACQUIRED</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden xl:table-cell">P.O. NUMBER</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden md:table-cell">LOCATION</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">DELETED AT</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider hidden lg:table-cell">REASON</th>
                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider sticky right-0 bg-white dark:bg-gray-800">ACTION</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="item in paginatedItems" :key="item.uuid" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ item.article }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-pre-line text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden md:table-cell">{{ item.description }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden lg:table-cell">{{ item.propertyAccountCode }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden lg:table-cell">{{ item.unitValue }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden xl:table-cell">{{ item.dateAcquired }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden xl:table-cell">{{ item.poNumber }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden md:table-cell">{{ item.location }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300">{{ item.deletedAt }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-600 dark:text-gray-300 hidden lg:table-cell">{{ item.deletionReason }}</td>
                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap sticky right-0 bg-white dark:bg-gray-800">
                  <div class="flex gap-1 sm:gap-2">
                    <button 
                      @click="openRestoreModal(item)"
                      class="p-1.5 sm:p-2 bg-green-600 rounded-full hover:bg-green-700 transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                      :disabled="loading"
                      title="Restore item to inventory"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                      </svg>
                    </button>
                    <button 
                      @click="openDeleteModal(item)"
                      class="p-2 bg-red-600 rounded-full hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                      :disabled="loading"
                      title="Delete permanently"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex justify-between items-center text-sm text-gray-600 dark:text-gray-300">
          <div>
            Showing {{ startIndex }} to {{ endIndex }} of {{ totalItems }} entries
          </div>
          <div class="flex gap-1">
            <button 
              @click="currentPage--" 
              :disabled="currentPage === 1"
              class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors bg-white dark:bg-gray-800"
              :class="currentPage === 1 ? 'text-gray-400 dark:text-gray-500 cursor-not-allowed' : 'text-gray-600 dark:text-gray-300'"
            >
              &lt;
            </button>
            <button 
              v-for="page in totalPages" 
              :key="page"
              @click="currentPage = page"
              class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded transition-colors bg-white dark:bg-gray-800"
              :class="currentPage === page ? 'bg-green-600 text-white border-green-600' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
            >
              {{ page }}
            </button>
            <button 
              @click="currentPage++" 
              :disabled="currentPage === totalPages"
              class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors bg-white dark:bg-gray-800"
              :class="currentPage === totalPages ? 'text-gray-400 dark:text-gray-500 cursor-not-allowed' : 'text-gray-600 dark:text-gray-300'"
            >
              &gt;
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div v-if="showRestoreModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-sm w-full mx-4">
        <div class="flex justify-end">
          <button @click="closeRestoreModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="text-center mb-6">
          <div class="mb-4">
            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mx-auto flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
          </div>
          <h3 class="text-lg text-gray-700 dark:text-gray-200 mb-4">Are you sure you want to restore this item?</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">This will restore the item back to the inventory and remove it from the deleted items list.</p>
          <div class="flex justify-center gap-4">
            <button
              @click="handleRestore"
              class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 flex items-center justify-center gap-2"
              :disabled="restoreLoading"
            >
              <span v-if="restoreLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              {{ restoreLoading ? 'Restoring...' : 'Yes, restore it' }}
            </button>
            <button
              @click="closeRestoreModal"
              class="px-6 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
              :disabled="restoreLoading"
            >
              No, cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Permanently Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-sm w-full mx-4">
        <div class="flex justify-end">
          <button @click="closeDeleteModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="text-center mb-6">
          <div class="mb-4">
            <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full mx-auto flex items-center justify-center">
              <svg class="w-8 h-8 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
          </div>
          <h3 class="text-lg text-gray-700 dark:text-gray-200 mb-4">Are you sure you want to permanently delete this item?</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">This action cannot be undone. The item will be permanently removed from the database.</p>
          <div class="flex justify-center gap-4">
            <button
              @click="handleDeletePermanently"
              class="px-6 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 flex items-center justify-center gap-2"
              :disabled="deleteLoading"
            >
              <span v-if="deleteLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              {{ deleteLoading ? 'Deleting...' : 'Yes, delete permanently' }}
            </button>
            <button
              @click="closeDeleteModal"
              class="px-6 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
              :disabled="deleteLoading"
            >
              No, cancel
            </button>
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
.shadow-sm {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
</style> 