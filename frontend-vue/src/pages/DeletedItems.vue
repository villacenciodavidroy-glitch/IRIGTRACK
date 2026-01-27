<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axiosClient from '../axios'
import useFormLabels from '../composables/useFormLabels'
import SuccessModal from '../components/SuccessModal.vue'
import { useDebouncedRef } from '../composables/useDebounce'

const { fetchLabels, getLabel } = useFormLabels()

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
onMounted(async () => {
  await fetchLabels()
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
      deletionReason: item.reason_for_deletion || item.deletion_reason || 'No reason provided' // Use reason_for_deletion from deleted_items table
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
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">delete_outline</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Deleted Items History</h1>
              <p v-if="!loading" class="text-green-100 text-sm sm:text-base">
                {{ totalItems || 0 }} {{ totalItems === 1 ? 'deleted item' : 'deleted items' }} found
              </p>
              <p v-else class="text-green-100 text-sm sm:text-base">Loading deleted items...</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Deleted Items</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
              <span v-if="loading" class="inline-block w-12 h-8 bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ totalItems || 0 }}</span>
            </p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">delete_sweep</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Current Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ currentPage }} / {{ totalPages || 1 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">description</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Items Per Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ itemsPerPage }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">filter_list</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Search and Filter Section -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mb-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3">
          <span class="text-gray-900 dark:text-white font-semibold">Show</span>
          <select 
            v-model="itemsPerPage"
            class="px-4 py-2.5 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all font-medium shadow-sm hover:shadow-md"
          >
            <option value="10" class="bg-gray-700 text-white">10</option>
            <option value="25" class="bg-gray-700 text-white">25</option>
            <option value="50" class="bg-gray-700 text-white">50</option>
            <option value="100" class="bg-gray-700 text-white">100</option>
          </select>
          <span class="text-gray-900 dark:text-white font-semibold">entries</span>
        </div>

        <div class="w-full sm:w-auto relative">
          <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by article, description, PAC, or location..."
            class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
          />
          <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <button @click="searchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <span class="material-icons-outlined text-lg">close</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-gradient-to-r from-red-900/20 to-red-800/20 dark:from-red-900/20 dark:to-red-800/20 border-l-4 border-red-500 dark:border-red-600 rounded-xl p-4 mb-6 shadow-md dark:shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="material-icons-outlined text-red-400 dark:text-red-400 text-2xl">error</span>
          <p class="text-red-300 dark:text-red-300 font-semibold">{{ error }}</p>
        </div>
        <button 
          @click="fetchDeletedItems" 
          class="px-4 py-2 bg-red-600 dark:bg-red-600 text-white rounded-lg hover:bg-red-700 dark:hover:bg-red-700 transition-all shadow-md hover:shadow-lg flex items-center gap-2 font-semibold"
        >
          <span class="material-icons-outlined text-base">refresh</span>
          Try Again
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-12">
      <div class="flex flex-col items-center justify-center">
        <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
          <span class="material-icons-outlined animate-spin text-5xl text-green-400 dark:text-green-400">refresh</span>
        </div>
        <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading deleted items...</p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredItems.length === 0" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-12">
      <div class="flex flex-col items-center justify-center">
        <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
          <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">delete_outline</span>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No deleted items found</h3>
        <p class="text-gray-600 dark:text-gray-400 text-center">{{ searchQuery ? 'Try adjusting your search query' : 'Items deleted from the inventory will appear here.' }}</p>
      </div>
    </div>

    <!-- Table with data -->
    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 min-w-[1000px]">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">{{ getLabel('article', 'ARTICLE') }}</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden md:table-cell">{{ getLabel('description', 'DESCRIPTION') }}</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden lg:table-cell">{{ getLabel('property_account_code', 'PAC') }}</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden lg:table-cell">{{ getLabel('unit_value', 'UNIT VALUE') }}</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden xl:table-cell">{{ getLabel('date_acquired', 'DATE ACQUIRED') }}</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden xl:table-cell">{{ getLabel('po_number', 'P.O. NUMBER') }}</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden md:table-cell">LOCATION</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">DELETED AT</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600 hidden lg:table-cell">REASON</th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider sticky right-0 bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">ACTION</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr 
              v-for="item in paginatedItems" 
              :key="item.uuid"
              class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-red-500"
            >
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <span class="material-icons-outlined text-red-400 dark:text-red-400 text-sm">inventory_2</span>
                  </div>
                  <span class="font-semibold text-gray-900 dark:text-white">{{ item.article }}</span>
                </div>
              </td>
              <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600 hidden md:table-cell">
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ item.description }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600 hidden lg:table-cell">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ item.propertyAccountCode }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600 hidden lg:table-cell">
                <span class="text-gray-700 dark:text-gray-300 font-medium">₱{{ item.unitValue }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600 hidden xl:table-cell">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ item.dateAcquired }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600 hidden xl:table-cell">
                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ item.poNumber }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600 hidden md:table-cell">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-green-900 dark:bg-green-900 text-green-300 dark:text-green-300">
                  <span class="material-icons-outlined text-sm">location_on</span>
                  {{ item.location }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ item.deletedAt }}</span>
              </td>
              <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600 hidden lg:table-cell">
                <span class="text-sm text-gray-700 dark:text-gray-300 max-w-md truncate block" :title="item.deletionReason">{{ item.deletionReason }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap sticky right-0 bg-white dark:bg-gray-800 group-hover:bg-gray-700 dark:group-hover:bg-gray-700 transition-colors">
                <div class="flex gap-2">
                  <button 
                    @click="openRestoreModal(item)"
                    class="p-2.5 rounded-lg bg-gradient-to-br from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 shadow-md hover:shadow-lg transition-all duration-200"
                    :disabled="loading"
                    title="Restore item to inventory"
                  >
                    <span class="material-icons-outlined text-base">restore</span>
                  </button>
                  <button 
                    @click="openDeleteModal(item)"
                    class="p-2.5 rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200"
                    :disabled="loading"
                    title="Delete permanently"
                  >
                    <span class="material-icons-outlined text-base">delete_forever</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && paginatedItems.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="flex items-center gap-2">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-lg">info</span>
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
              Showing <span class="text-green-400 dark:text-green-400 font-bold">{{ startIndex }}</span> to 
              <span class="text-green-400 dark:text-green-400 font-bold">{{ endIndex }}</span> of 
              <span class="text-green-400 dark:text-green-400 font-bold">{{ totalItems }}</span> entries
            </span>
          </div>
          <div v-if="totalPages > 1" class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
            <button 
              @click="currentPage = 1"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">first_page</span>
            </button>
            <button 
              @click="currentPage--" 
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
              <button 
                v-for="page in totalPages" 
                :key="page"
                @click="currentPage = page"
                :class="[
                  'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                  currentPage === page 
                    ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                    : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                ]"
              >
                {{ page }}
              </button>
            </div>
            <button 
              @click="currentPage++" 
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_right</span>
            </button>
            <button 
              @click="currentPage = totalPages"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">last_page</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div v-if="showRestoreModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="closeRestoreModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-md w-full overflow-hidden animate-modalSlideIn">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">restore</span>
              </div>
              <h3 class="text-xl font-bold text-white">Restore Item</h3>
            </div>
            <button @click="closeRestoreModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6 bg-white dark:bg-gray-800">
          <div class="flex flex-col items-center text-center mb-6">
            <div class="w-16 h-16 bg-gray-50 dark:bg-gray-700 rounded-full mx-auto flex items-center justify-center mb-4">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-3xl">restore</span>
            </div>
            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Are you sure you want to restore this item?</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">This will restore the item back to the inventory and remove it from the deleted items list.</p>
          </div>
          
          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              @click="closeRestoreModal"
              :disabled="restoreLoading"
              class="px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-gray-500 transition-all shadow-sm disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              @click="handleRestore"
              class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transition-all"
              :disabled="restoreLoading"
            >
              <span v-if="restoreLoading" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">check</span>
              {{ restoreLoading ? 'Restoring...' : 'Yes, Restore It' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Permanently Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="closeDeleteModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-md w-full overflow-hidden animate-modalSlideIn">
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">delete_forever</span>
              </div>
              <h3 class="text-xl font-bold text-white">Permanent Deletion</h3>
            </div>
            <button @click="closeDeleteModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6 bg-white dark:bg-gray-800">
          <div class="flex flex-col items-center text-center mb-6">
            <div class="w-16 h-16 bg-red-900/20 dark:bg-red-900/20 rounded-full mx-auto flex items-center justify-center mb-4">
              <span class="material-icons-outlined text-red-400 dark:text-red-400 text-3xl">warning</span>
            </div>
            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Are you sure you want to permanently delete this item?</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">This action cannot be undone. The item will be permanently removed from the database.</p>
          </div>
          
          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              @click="closeDeleteModal"
              :disabled="deleteLoading"
              class="px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-gray-500 transition-all shadow-sm disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              @click="handleDeletePermanently"
              class="px-5 py-2.5 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:from-red-700 hover:to-red-800 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transition-all"
              :disabled="deleteLoading"
            >
              <span v-if="deleteLoading" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">delete_forever</span>
              {{ deleteLoading ? 'Deleting...' : 'Yes, Delete Permanently' }}
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
.material-icons-outlined {
  font-size: 20px;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Modal animations */
@keyframes modalFadeIn {
  from { 
    opacity: 0; 
  }
  to { 
    opacity: 1; 
  }
}

@keyframes modalSlideIn {
  from { 
    opacity: 0; 
    transform: scale(0.9) translateY(-20px); 
  }
  to { 
    opacity: 1; 
    transform: scale(1) translateY(0); 
  }
}

.animate-modalFadeIn {
  animation: modalFadeIn 0.2s ease-out;
}

.animate-modalSlideIn {
  animation: modalSlideIn 0.3s ease-out;
}

/* Enhanced table row hover effect */
tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  transform: translateX(2px);
}

/* Enhanced scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  @apply bg-gray-700 rounded-full;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  @apply bg-green-400 rounded-full hover:bg-green-500;
}

/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
}
</style> 