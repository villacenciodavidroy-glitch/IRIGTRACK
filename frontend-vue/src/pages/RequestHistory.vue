<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'

const router = useRouter()
const myRequests = ref([])
const loading = ref(false)

// Request history pagination
const requestHistoryPagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0
})
const requestHistoryPage = ref(1)
const requestStatusFilter = ref('')

// Message modal state
const showMessageModal = ref(false)
const selectedRequestForMessage = ref(null)
const messages = ref([])
const newMessage = ref('')
const showQrCodeModal = ref(false)
const selectedQrCodeUrl = ref(null)
const unreadCounts = ref({})
const loadingMessages = ref(false)

// Cancel request modal state
const showCancelModal = ref(false)
const requestToCancel = ref(null)
const cancelingRequest = ref(false)

// View details modal state
const showDetailsModal = ref(false)
const selectedRequestDetails = ref(null)

// Banner state
const showBanner = ref(false)
const bannerMessage = ref('')
const bannerType = ref('success')
let bannerTimeout = null

// Show banner function
const showSimpleBanner = (message, type = 'success', autoHide = true, duration = 4000) => {
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  
  bannerMessage.value = message
  bannerType.value = type
  showBanner.value = true
  
  if (autoHide) {
    bannerTimeout = setTimeout(() => {
      showBanner.value = false
      bannerTimeout = null
    }, duration)
  }
}

const closeBanner = () => {
  if (bannerTimeout) {
    clearTimeout(bannerTimeout)
    bannerTimeout = null
  }
  showBanner.value = false
}

// Fetch user's request history
const fetchMyRequests = async (silent = false) => {
  try {
    loading.value = true
    const params = {
      page: requestHistoryPage.value,
      per_page: requestHistoryPagination.value.per_page
    }
    
    if (requestStatusFilter.value) {
      params.status = requestStatusFilter.value
    }
    
    const response = await axiosClient.get('/supply-requests/my-requests', { params })
    
    if (response.data.success) {
      myRequests.value = response.data.data || []
      requestHistoryPagination.value = response.data.pagination || requestHistoryPagination.value
      fetchUnreadCounts()
    }
  } catch (err) {
    console.error('Error fetching request history:', err)
  } finally {
    loading.value = false
  }
}

// Fetch unread message counts
const fetchUnreadCounts = async () => {
  try {
    for (const request of myRequests.value) {
      if (request.unread_messages_count !== undefined && request.unread_messages_count !== null) {
        unreadCounts.value[request.id] = request.unread_messages_count || 0
      }
    }
  } catch (err) {
    console.error('Error fetching unread counts:', err)
  }
}

// Get status badge class
const getStatusBadgeClass = (status, request = null) => {
  const statusLower = status?.toLowerCase()
  if (statusLower === 'approved' || statusLower === 'supply_approved') return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
  if (statusLower === 'ready_for_pickup') {
    return request?.pickup_scheduled_at 
      ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200 border-2 border-cyan-300 dark:border-cyan-700 animate-pulse'
      : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 border-2 border-yellow-300 dark:border-yellow-700'
  }
  if (statusLower === 'for_claiming') return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 border-2 border-orange-300 dark:border-orange-700 animate-pulse'
  if (statusLower === 'rejected') return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
  if (statusLower === 'fulfilled') return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  if (statusLower === 'admin_assigned') return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
  return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
}

// Format date
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Download receipt
const downloadRequestReceipt = async (requestId, event) => {
  event.preventDefault()
  if (!requestId) return
  
  try {
    const response = await axiosClient.get(`/supply-requests/${requestId}/receipt`, {
      responseType: 'blob',
      headers: {
        'Accept': 'application/pdf'
      }
    })
    
    const blob = response.data
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = `receipt_${new Date().toISOString().split('T')[0]}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(blobUrl)
  } catch (error) {
    console.error('Error downloading receipt:', error)
    showSimpleBanner('Failed to download receipt. Please try again.', 'error', true, 5000)
  }
}

// Open details modal
const openDetailsModal = (request) => {
  selectedRequestDetails.value = request
  showDetailsModal.value = true
}

// Close details modal
const closeDetailsModal = () => {
  showDetailsModal.value = false
  selectedRequestDetails.value = null
}

// Watch for filter changes
watch(requestStatusFilter, () => {
  requestHistoryPage.value = 1
  fetchMyRequests()
})

// Watch for page changes
watch(requestHistoryPage, () => {
  fetchMyRequests()
})

// Fetch on mount
onMounted(() => {
  fetchMyRequests()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Request</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View your supply request history</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- Filter -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <div class="flex items-center gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Filter by Status:
            </label>
            <select 
              v-model="requestStatusFilter"
              class="w-full px-4 py-3 pl-12 border-2 border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 appearance-none cursor-pointer font-medium"
            >
              <option value="">All Status</option>
              <option value="pending">New Request</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="fulfilled">Completed</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600"></div>
          <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Loading requests...</p>
        </div>
      </div>

      <!-- Requests Table -->
      <div v-else-if="myRequests.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-emerald-50 dark:bg-emerald-900/20">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Date</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Status</th>
                <th class="px-6 py-4 text-left text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="request in myRequests" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ formatDate(request.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span 
                    :class="[
                      'px-3 py-1.5 text-xs font-bold rounded-full flex items-center gap-1.5 w-fit shadow-sm',
                      getStatusBadgeClass(request.status, request)
                    ]"
                  >
                    <span class="material-icons-outlined text-xs">
                      {{ request.status === 'pending' ? 'hourglass_empty' : request.status === 'approved' || request.status === 'supply_approved' ? 'check_circle' : request.status === 'ready_for_pickup' ? (request.pickup_scheduled_at ? 'schedule' : 'pending') : request.status === 'for_claiming' ? 'shopping_cart' : request.status === 'fulfilled' ? 'done_all' : 'cancel' }}
                    </span>
                    {{ request.status === 'ready_for_pickup' 
                        ? (request.pickup_scheduled_at ? 'Ready for Pickup' : 'Approved By Admin') 
                        : request.status === 'for_claiming' ? 'For Claiming'
                        : request.status === 'fulfilled' ? 'Completed' : request.status === 'supply_approved' ? 'Request Approved' : request.status === 'approved' ? 'Approved By Admin' : request.status === 'admin_assigned' ? 'For Admin Approval' : request.status === 'pending' ? 'New Request' : request.status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button
                    @click="openDetailsModal(request)"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition-colors"
                  >
                    <span class="material-icons-outlined text-sm">visibility</span>
                    View
                  </button>
                  <button
                    v-if="request.approval_proof && (request.status === 'approved' || request.status === 'fulfilled' || request.status === 'ready_for_pickup' || request.status === 'for_claiming')"
                    @click="downloadRequestReceipt(request.id, $event)"
                    class="ml-2 inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                  >
                    <span class="material-icons-outlined text-sm">download</span>
                    Receipt
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="requestHistoryPagination.last_page > 1" class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-600">
          <div class="flex-1 flex justify-between sm:hidden">
            <button
              @click="requestHistoryPage--"
              :disabled="requestHistoryPage === 1"
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button
              @click="requestHistoryPage++"
              :disabled="requestHistoryPage >= requestHistoryPagination.last_page"
              class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-gray-700 dark:text-gray-300">
                Showing <span class="font-medium">{{ requestHistoryPagination.from || 0 }}</span> to
                <span class="font-medium">{{ requestHistoryPagination.to || 0 }}</span> of
                <span class="font-medium">{{ requestHistoryPagination.total || 0 }}</span> results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <button
                  @click="requestHistoryPage--"
                  :disabled="requestHistoryPage === 1"
                  class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">
                  Page {{ requestHistoryPage }} of {{ requestHistoryPagination.last_page }}
                </span>
                <button
                  @click="requestHistoryPage++"
                  :disabled="requestHistoryPage >= requestHistoryPagination.last_page"
                  class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </nav>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-12">
        <div class="flex flex-col items-center justify-center">
          <span class="material-icons-outlined text-6xl text-gray-400 dark:text-gray-500 mb-4">inbox</span>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No requests found</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ requestStatusFilter ? 'No requests match the selected filter. Try a different status.' : 'You haven\'t made any supply requests yet.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Request Details Modal -->
    <Transition name="modal">
      <div v-if="showDetailsModal" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeDetailsModal">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDetailsModal"></div>
          <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
          <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-emerald-600 dark:bg-emerald-700 px-6 py-4 flex items-center justify-between">
              <h3 class="text-lg font-bold text-white">Request Details</h3>
              <button @click="closeDetailsModal" class="text-white hover:text-gray-200">
                <span class="material-icons-outlined">close</span>
              </button>
            </div>
            <div v-if="selectedRequestDetails" class="bg-white dark:bg-gray-800 px-6 py-4">
              <div class="space-y-4">
                <div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</label>
                  <div class="mt-1">
                    <span :class="['px-4 py-2 rounded-xl text-sm font-bold inline-flex items-center shadow-sm', getStatusBadgeClass(selectedRequestDetails.status, selectedRequestDetails)]">
                      <span class="material-icons-outlined text-sm">
                        {{ selectedRequestDetails.status === 'pending' ? 'hourglass_empty' : selectedRequestDetails.status === 'approved' || selectedRequestDetails.status === 'supply_approved' ? 'check_circle' : selectedRequestDetails.status === 'ready_for_pickup' ? (selectedRequestDetails.pickup_scheduled_at ? 'schedule' : 'pending') : selectedRequestDetails.status === 'for_claiming' ? 'shopping_cart' : selectedRequestDetails.status === 'fulfilled' ? 'done_all' : 'cancel' }}
                      </span>
                      {{ selectedRequestDetails.status === 'ready_for_pickup' 
                          ? (selectedRequestDetails.pickup_scheduled_at ? 'Ready for Pickup' : 'Approved By Admin') 
                          : selectedRequestDetails.status === 'for_claiming' ? 'For Claiming'
                          : selectedRequestDetails.status === 'fulfilled' ? 'Completed' : selectedRequestDetails.status === 'supply_approved' ? 'Request Approved' : selectedRequestDetails.status === 'approved' ? 'Approved By Admin' : selectedRequestDetails.status === 'admin_assigned' ? 'For Admin Approval' : selectedRequestDetails.status === 'pending' ? 'New Request' : selectedRequestDetails.status }}
                    </span>
                  </div>
                </div>
                <div>
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Date Submitted</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(selectedRequestDetails.created_at) }}</p>
                </div>
                <div v-if="selectedRequestDetails.items && selectedRequestDetails.items.length > 0">
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Items</label>
                  <div class="mt-2 space-y-2">
                    <div v-for="(item, index) in selectedRequestDetails.items" :key="index" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                      <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.item_name || item.name || 'N/A' }}</p>
                      <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Quantity: {{ item.quantity }}</p>
                    </div>
                  </div>
                </div>
                <div v-if="selectedRequestDetails.notes">
                  <label class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Notes</label>
                  <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ selectedRequestDetails.notes }}</p>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 flex justify-end">
              <button
                @click="closeDetailsModal"
                class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Banner Notification -->
    <Transition name="banner-slide">
      <div
        v-if="showBanner"
        :class="[
          'fixed top-0 left-0 right-0 z-50 px-4 py-3 shadow-lg',
          bannerType === 'success' 
            ? 'bg-emerald-600 text-white' 
            : 'bg-red-600 text-white'
        ]"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-between">
          <p class="text-sm font-medium">{{ bannerMessage }}</p>
          <button @click="closeBanner" class="text-white hover:text-gray-200">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.banner-slide-enter-active,
.banner-slide-leave-active {
  transition: transform 0.3s ease;
}

.banner-slide-enter-from {
  transform: translateY(-100%);
}

.banner-slide-leave-to {
  transform: translateY(-100%);
}
</style>
