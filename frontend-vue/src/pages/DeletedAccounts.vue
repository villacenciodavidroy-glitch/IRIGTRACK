<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ArrowPathIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'
import { useDebouncedRef } from '../composables/useDebounce'

const deletedAccounts = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const showRestoreModal = ref(false)
const showDeleteModal = ref(false)
const accountToRestore = ref(null)
const accountToDelete = ref(null)
const restoreLoading = ref(false)
const deleteLoading = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Fetch deleted accounts from API
const fetchDeletedAccounts = async () => {
  loading.value = true
  error.value = null
  
  try {
    console.log('Fetching deleted accounts from API...')
    const response = await axiosClient.get('/users/deleted')
    console.log('API Response:', response)
    console.log('Response data:', response.data)
    deletedAccounts.value = response.data.data || []
    console.log('Deleted accounts from API:', deletedAccounts.value)
  } catch (err) {
    console.error('Error fetching deleted accounts:', err)
    console.error('Error details:', err.response?.data)
    console.error('Error status:', err.response?.status)
    error.value = `Failed to load deleted accounts: ${err.response?.data?.message || err.message}. Please try again.`
  } finally {
    loading.value = false
  }
}

// Fetch deleted accounts when component mounts
onMounted(() => {
  fetchDeletedAccounts()
})

const filteredAccounts = computed(() => {
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  if (!query) return formattedAccounts.value
  
  // Optimize search: only search relevant fields
  return formattedAccounts.value.filter(account => {
    return (
      (account.name || '').toLowerCase().includes(query) ||
      (account.username || '').toLowerCase().includes(query) ||
      (account.email || '').toLowerCase().includes(query)
    )
  })
})

// Reset to first page when search query changes
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
})

// Map API data to the format expected by the table
const formattedAccounts = computed(() => {
  return deletedAccounts.value.map(account => {
    return {
      id: account.id,
      name: account.fullname || '',
      username: account.username || '',
      email: account.email || '',
      deletedBy: 'System', // We'll need to get this from activity logs
      deletionDate: account.deleted_at ? new Date(account.deleted_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) : 'Unknown',
      location: account.location?.location || 'Unknown'
    }
  })
})

const totalAccounts = computed(() => filteredAccounts.value.length)
const totalPages = computed(() => Math.ceil(totalAccounts.value / itemsPerPage.value))
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage.value + 1)
const endIndex = computed(() => Math.min(startIndex.value + itemsPerPage.value - 1, totalAccounts.value))
const paginatedAccounts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredAccounts.value.slice(start, end)
})

const openRestoreModal = (account) => {
  accountToRestore.value = account
  showRestoreModal.value = true
}

const openDeleteModal = (account) => {
  accountToDelete.value = account
  showDeleteModal.value = true
}

const handleRestore = async () => {
  if (!accountToRestore.value) return
  
  restoreLoading.value = true
  
  try {
    console.log('Restoring account:', accountToRestore.value)
    const response = await axiosClient.post(`/users/${accountToRestore.value.id}/restore`)
    
    if (response.data) {
      successMessage.value = 'Account restored successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      
      // Refresh the list
      await fetchDeletedAccounts()
    }
  } catch (error) {
    console.error('Error restoring account:', error)
    
    // Handle specific error cases
    if (error.response?.status === 404) {
      successMessage.value = 'Account not found or already restored. The list will be refreshed.'
      successModalType.value = 'warning'
      showSuccessModal.value = true
      // Refresh the list to remove the non-existent account
      await fetchDeletedAccounts()
    } else if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to restore account. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    restoreLoading.value = false
    showRestoreModal.value = false
    accountToRestore.value = null
  }
}

const handleDelete = async () => {
  if (!accountToDelete.value) return
  
  deleteLoading.value = true
  
  try {
    console.log('Permanently deleting account:', accountToDelete.value)
    const response = await axiosClient.delete(`/users/${accountToDelete.value.id}/force-delete`)
    
    if (response.data) {
      successMessage.value = 'Account permanently deleted successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      
      // Refresh the list
      await fetchDeletedAccounts()
    }
  } catch (error) {
    console.error('Error permanently deleting account:', error)
    
    // Handle specific error cases
    if (error.response?.status === 404) {
      successMessage.value = 'Account not found or already deleted. The list will be refreshed.'
      successModalType.value = 'warning'
      showSuccessModal.value = true
      // Refresh the list to remove the non-existent account
      await fetchDeletedAccounts()
    } else if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to permanently delete account. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    deleteLoading.value = false
    showDeleteModal.value = false
    accountToDelete.value = null
  }
}

const closeModal = () => {
  showRestoreModal.value = false
  accountToRestore.value = null
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  accountToDelete.value = null
}

const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-semibold mb-6">Deleted Account History</h1>
    
    <!-- Loading State -->
    <div v-if="loading" class="bg-white rounded-lg shadow p-6 text-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto"></div>
      <p class="mt-2 text-gray-600">Loading deleted accounts...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="flex">
        <ExclamationCircleIcon class="h-5 w-5 text-red-400" />
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">Error</h3>
          <div class="mt-2 text-sm text-red-700">{{ error }}</div>
          <div class="mt-4">
            <button @click="fetchDeletedAccounts" class="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200">
              Try Again
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div v-else class="bg-white rounded-lg shadow">
      <div class="p-4 flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
        <div class="flex items-center space-x-2">
          <span class="text-sm">Show</span>
          <select 
            v-model="itemsPerPage"
            class="border rounded px-2 py-1 text-sm"
          >
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="text-sm">entries</span>
        </div>

        <div class="w-full sm:w-auto">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search..."
            class="w-full px-3 py-2 border rounded-lg text-sm"
          />
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="bg-gray-50 border-y">
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted By</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deletion Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="account in paginatedAccounts" :key="account.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ account.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ account.username }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ account.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ account.deletedBy }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ account.deletionDate }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <button 
                    @click="openRestoreModal(account)"
                    :disabled="restoreLoading || deleteLoading"
                    class="p-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 rounded-lg transition-colors duration-200 flex items-center justify-center"
                    title="Restore Account"
                  >
                    <ArrowPathIcon class="h-5 w-5 text-white" />
                    <span class="sr-only">Restore</span>
                  </button>
                  <button 
                    @click="openDeleteModal(account)"
                    :disabled="restoreLoading || deleteLoading"
                    class="p-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 rounded-lg transition-colors duration-200 flex items-center justify-center"
                    title="Delete Permanently"
                  >
                    <TrashIcon class="h-5 w-5 text-white" />
                    <span class="sr-only">Delete Permanently</span>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="paginatedAccounts.length === 0">
              <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                No deleted accounts found
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 flex items-center justify-between border-t">
        <div class="text-sm text-gray-500">
          Showing {{ startIndex }} to {{ endIndex }} of {{ totalAccounts }} entries
        </div>
        <div class="flex items-center space-x-2">
          <button 
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 rounded border disabled:opacity-50 disabled:cursor-not-allowed"
          >
            &lt;
          </button>
          <button 
            v-for="page in Math.min(5, totalPages)" 
            :key="page"
            @click="currentPage = page"
            :class="[
              'px-3 py-1 rounded',
              currentPage === page ? 'bg-green-600 text-white' : 'border'
            ]"
          >
            {{ page }}
          </button>
          <button 
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-2 py-1 rounded border disabled:opacity-50 disabled:cursor-not-allowed"
          >
            &gt;
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <Teleport to="body">
    <div v-if="showRestoreModal" class="fixed inset-0 z-50 overflow-y-auto">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

      <!-- Modal panel -->
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
          <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                <ArrowPathIcon class="h-6 w-6 text-green-600" />
              </div>
              <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                <h3 class="text-lg font-semibold leading-6 text-gray-900">
                  Are you sure you want to restore this account?
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    The account will be restored and can be used again.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              type="button"
              :disabled="restoreLoading"
              class="inline-flex w-full justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-50 sm:ml-3 sm:w-auto flex items-center"
              @click="handleRestore"
            >
              <ArrowPathIcon v-if="restoreLoading" class="h-4 w-4 mr-2 animate-spin" />
              {{ restoreLoading ? 'Restoring...' : "Yes, I'm sure" }}
            </button>
            <button
              type="button"
              :disabled="restoreLoading"
              class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 sm:mt-0 sm:w-auto"
              @click="closeModal"
            >
              No, cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Delete Confirmation Modal -->
  <Teleport to="body">
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDeleteModal"></div>
        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
          <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <TrashIcon class="h-6 w-6 text-red-600" />
              </div>
              <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                <h3 class="text-lg font-semibold leading-6 text-gray-900">
                  Are you sure you want to permanently delete this account?
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    This action cannot be undone. The account will be permanently removed from the database.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              type="button"
              :disabled="deleteLoading"
              class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50 sm:ml-3 sm:w-auto flex items-center"
              @click="handleDelete"
            >
              <TrashIcon v-if="deleteLoading" class="h-4 w-4 mr-2 animate-spin" />
              {{ deleteLoading ? 'Deleting...' : "Yes, Delete Permanently" }}
            </button>
            <button
              type="button"
              :disabled="deleteLoading"
              class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 sm:mt-0 sm:w-auto"
              @click="closeDeleteModal"
            >
              No, cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

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
</template> 