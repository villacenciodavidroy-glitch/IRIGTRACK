<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-600 via-blue-700 to-blue-600 rounded-xl shadow-xl mb-6">
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">people</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">Personnel Management</h1>
              <p class="text-blue-100 text-sm sm:text-base">Manage personnel status and accountability</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Alert Banner for Resigned Users with Pending Items -->
    <div
      v-if="resignedWithPendingItems.length > 0"
      class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-md"
    >
      <div class="flex items-start">
        <span class="material-icons-outlined text-red-600 dark:text-red-400 mr-3 mt-0.5">warning</span>
        <div class="flex-1">
          <h3 class="text-lg font-bold text-red-800 dark:text-red-300 mb-1">
            ⚠️ Clearance Required: {{ resignedWithPendingItems.length }} Resigned {{ resignedWithPendingItems.length === 1 ? 'User' : 'Users' }} with Pending Items
          </h3>
          <p class="text-sm text-red-700 dark:text-red-400 mb-2">
            The following resigned users still have items issued to them. Please complete their clearance by returning or reassigning all items.
          </p>
          <div class="flex flex-wrap gap-2 mt-3">
            <button
              v-for="person in resignedWithPendingItems"
              :key="`${person.type}-${person.id}`"
              @click="openClearanceModal(person)"
              class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg font-medium transition-colors"
            >
              {{ person.fullname }} ({{ person.pending_items_count }} {{ person.pending_items_count === 1 ? 'item' : 'items' }})
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
      <div class="flex flex-wrap gap-4 items-center">
        <div class="flex-1 min-w-[200px]">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name or user code..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
          />
        </div>
        <div>
          <select
            v-model="statusFilter"
            @change="currentPage = 1"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
          >
            <option value="all">All Status</option>
            <option value="ACTIVE">Active</option>
            <option value="INACTIVE">Inactive</option>
            <option value="RESIGNED">Resigned</option>
          </select>
        </div>
        <div>
          <select
            v-model="itemsPerPage"
            @change="currentPage = 1"
            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
          >
            <option :value="10">10 per page</option>
            <option :value="25">25 per page</option>
            <option :value="50">50 per page</option>
            <option :value="100">100 per page</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Personnel Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User Code</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pending Items</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Location</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="personnel in paginatedPersonnel"
              :key="`${personnel.type}-${personnel.id}`"
              :class="{
                'bg-red-50 dark:bg-red-900/20': personnel.status === 'RESIGNED',
                'hover:bg-gray-50 dark:hover:bg-gray-700': true
              }"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <img
                    :src="personnel.image || '/images/default-avatar.png'"
                    class="h-10 w-10 rounded-full mr-3"
                    :alt="personnel.fullname"
                  />
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ personnel.fullname }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ personnel.email }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="text-sm font-mono text-gray-900 dark:text-white">
                  {{ personnel.code || personnel.user_code || personnel.personnel_code || 'N/A' }}
                </span>
                <span v-if="personnel.type === 'PERSONNEL'" class="ml-2 text-xs text-blue-600 dark:text-blue-400">
                  (Personnel)
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="{
                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': personnel.status === 'ACTIVE',
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': personnel.status === 'INACTIVE',
                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': personnel.status === 'RESIGNED'
                  }"
                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                >
                  {{ personnel.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <span
                    v-if="personnel.pending_items_count > 0"
                    :class="{
                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': personnel.status === 'RESIGNED',
                      'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': personnel.status !== 'RESIGNED'
                    }"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                  >
                    {{ personnel.pending_items_count }}
                  </span>
                  <span v-else class="text-sm text-gray-500 dark:text-gray-400">0</span>
                  <span
                    v-if="personnel.status === 'RESIGNED' && personnel.pending_items_count > 0"
                    class="material-icons-outlined text-red-600 dark:text-red-400 text-sm"
                    title="Resigned user with pending items - Clearance required!"
                  >
                    warning
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ personnel.location || 'N/A' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button
                  @click="openIssuedItemsModal(personnel)"
                  class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 mr-4"
                  title="View all issued items"
                >
                  View Items
                </button>
                <button
                  v-if="personnel.type === 'USER'"
                  @click="generateAccountabilityReport(personnel)"
                  class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300 mr-4"
                  title="Generate accountability report"
                >
                  <span class="material-icons-outlined text-sm align-middle mr-1">description</span>
                  Report
                </button>
                <button
                  v-if="personnel.pending_items_count > 0"
                  @click="openClearanceModal(personnel)"
                  :class="{
                    'text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-bold': personnel.status === 'RESIGNED',
                    'text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300': personnel.status !== 'RESIGNED'
                  }"
                  class="mr-4"
                  :title="personnel.status === 'RESIGNED' ? 'URGENT: Clearance required for resigned user!' : 'Clear pending items'"
                >
                  {{ personnel.status === 'RESIGNED' ? '⚠️ Clearance Required' : 'Clearance' }}
                </button>
                <button
                  v-if="personnel.type === 'USER' && personnel.status !== 'RESIGNED'"
                  @click="markAsResigned(personnel)"
                  class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                  :disabled="processingResignation === personnel.id"
                >
                  {{ processingResignation === personnel.id ? 'Processing...' : 'Mark as Resigned' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="currentPage = Math.max(1, currentPage - 1)"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          <button
            @click="currentPage = Math.min(totalPages, currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              Showing <span class="font-medium">{{ paginationInfo.start }}</span> to <span class="font-medium">{{ paginationInfo.end }}</span> of <span class="font-medium">{{ paginationInfo.total }}</span> results
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button
                @click="currentPage = Math.max(1, currentPage - 1)"
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span class="material-icons-outlined text-sm">chevron_left</span>
              </button>
              <template v-for="page in totalPages" :key="page">
                <button
                  v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                  @click="currentPage = page"
                  :class="{
                    'z-10 bg-blue-50 dark:bg-blue-900 border-blue-500 dark:border-blue-400 text-blue-600 dark:text-blue-300': currentPage === page,
                    'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600': currentPage !== page
                  }"
                  class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                >
                  {{ page }}
                </button>
                <span
                  v-else-if="page === currentPage - 2 || page === currentPage + 2"
                  class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                  ...
                </span>
              </template>
              <button
                @click="currentPage = Math.min(totalPages, currentPage + 1)"
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span class="material-icons-outlined text-sm">chevron_right</span>
              </button>
            </nav>
          </div>
        </div>
      </div>
      
      <!-- Empty State -->
      <div v-if="filteredPersonnel.length === 0" class="text-center py-12">
        <p class="text-gray-500 dark:text-gray-400">No personnel found</p>
      </div>
    </div>

    <!-- Clearance Modal -->
    <div
      v-if="showClearanceModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeClearanceModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                Clearance for {{ selectedPersonnel?.fullname }} ({{ selectedPersonnel?.code || selectedPersonnel?.user_code || selectedPersonnel?.personnel_code }})
              </h2>
              <p
                v-if="selectedPersonnel?.status === 'RESIGNED'"
                class="text-sm text-red-600 dark:text-red-400 mt-1 font-semibold flex items-center gap-1"
              >
                <span class="material-icons-outlined text-base">warning</span>
                URGENT: This user is resigned and must return all items
              </p>
            </div>
            <button
              @click="closeClearanceModal"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        <div class="p-6">
          <div v-if="pendingItems.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No pending items</p>
          </div>
          <div v-else>
            <!-- Bulk Actions Bar -->
            <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-between">
              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer" :class="{ 'cursor-not-allowed opacity-50': itemsWithMrCount === 0 }">
                  <input
                    type="checkbox"
                    :checked="selectedItems.length === itemsWithMrCount && itemsWithMrCount > 0"
                    @change="toggleSelectAll"
                    :disabled="itemsWithMrCount === 0"
                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 disabled:cursor-not-allowed"
                  />
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Select All ({{ selectedItems.length }}/{{ itemsWithMrCount }}) 
                    <span v-if="itemsWithoutMrCount > 0" class="text-xs text-amber-600 dark:text-amber-400">
                      ({{ itemsWithoutMrCount }} without MR - process individually)
                    </span>
                    <span v-if="itemsWithMrCount === 0 && pendingItems.length > 0" class="text-xs text-amber-600 dark:text-amber-400">
                      - All items need individual processing
                    </span>
                  </span>
                </label>
              </div>
              <div class="flex gap-2">
                <button
                  v-if="selectedItems.length > 0"
                  @click="bulkReturnItems"
                  class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-medium"
                  :disabled="processingBulk"
                >
                  {{ processingBulk ? 'Processing...' : `Return All Selected (${selectedItems.length})` }}
                </button>
                <button
                  v-if="selectedItems.length > 0"
                  @click="openBulkReassignModal"
                  class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium"
                  :disabled="processingBulk"
                >
                  Reassign All Selected ({{ selectedItems.length }})
                </button>
              </div>
            </div>

            <!-- Items List -->
            <div class="space-y-4">
              <div
                v-for="item in pendingItems"
                :key="item.id || `item-${item.item?.id}`"
                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                :class="{ 'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-600': isItemSelected(item) }"
              >
                <div class="flex items-start gap-3 mb-4">
                  <input
                    type="checkbox"
                    :checked="isItemSelected(item)"
                    @change="toggleItemSelection(item)"
                    @click.stop
                    class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500 cursor-pointer"
                    :disabled="!item.id"
                    :class="{ 'cursor-not-allowed opacity-50': !item.id }"
                    :title="!item.id ? 'Items without MR records must be processed individually' : 'Select item for bulk operations'"
                  />
                  <div class="flex-1">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ item.item?.unit || 'N/A' }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ item.item?.description || 'N/A' }}</p>
                    <div class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-500">
                      <p v-if="item.item?.serial_number"><strong>Serial Number:</strong> {{ item.item.serial_number }}</p>
                      <p v-if="item.item?.model"><strong>Model:</strong> {{ item.item.model }}</p>
                      <p><strong>MR Number:</strong> #{{ item.id || 'N/A' }}</p>
                      <p><strong>Category:</strong> {{ getCategoryName(item) }}</p>
                      <p><strong>Value:</strong> ₱{{ item.item?.unit_value?.toLocaleString() || '0' }}</p>
                      <p><strong>Issued:</strong> {{ formatDate(item.issued_at) }}</p>
                      <p><strong>Issued by:</strong> {{ item.issued_by_user_code || 'N/A' }}</p>
                    </div>
                    <p v-if="!item.id" class="text-xs text-amber-600 dark:text-amber-400 mt-2 flex items-center gap-1">
                      <span class="material-icons-outlined text-sm">info</span>
                      No MR record - MR will be created automatically when you perform an action (Return, Reassign, or Lost/Damaged)
                    </p>
                  </div>
                </div>
                <div class="flex gap-2 flex-wrap">
                  <button
                    @click.stop="returnItem(item)"
                    type="button"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
                    :disabled="isItemProcessing(item) || processingBulk"
                  >
                    {{ isItemProcessing(item) ? 'Processing...' : 'Return' }}
                  </button>
                  <button
                    @click.stop="openReassignModal(item)"
                    type="button"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
                    :disabled="isItemProcessing(item) || processingBulk"
                  >
                    Reassign
                  </button>
                  <button
                    @click.stop="openLostDamagedModal(item)"
                    type="button"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all cursor-pointer"
                    :disabled="isItemProcessing(item) || processingBulk"
                  >
                    Lost/Damaged
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reassign Modal -->
    <div
      v-if="showReassignModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeReassignModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">
            {{ selectedItem?.id === 'BULK' ? `Reassign ${selectedItem.mr_ids?.length || 0} Items` : 'Reassign Item' }}
          </h2>
          <p v-if="selectedItem?.id === 'BULK'" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            All selected items will be reassigned to the same personnel
          </p>
        </div>
        <div class="p-6">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Select Personnel
            </label>
            <select
              v-model="reassignToUserId"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            >
              <option value="">Select...</option>
              <option
                v-for="user in activeUsers"
                :key="`${user.type}-${user.id}`"
                :value="user.type === 'USER' ? `USER-${user.id}` : `PERSONNEL-${user.id}`"
              >
                {{ user.fullname }} ({{ user.code || user.user_code || user.personnel_code }}) - {{ user.location || 'N/A' }} [{{ user.status || 'ACTIVE' }}] {{ user.type === 'PERSONNEL' ? '(Personnel)' : '' }}
              </option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button
              @click="closeReassignModal"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              @click="confirmReassign"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
              :disabled="!reassignToUserId || processingItem"
            >
              {{ processingItem ? 'Processing...' : 'Reassign' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Recovery Modal -->
    <div
      v-if="showRecoveryModal && selectedItemForRecovery"
      class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-[9999] p-4"
      @click.self="closeRecoveryModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col border-2 border-green-500/20">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5 border-b-2 border-green-800 flex items-center justify-between shadow-lg">
          <div class="flex items-center gap-4">
            <div class="p-2.5 bg-white/25 backdrop-blur-sm rounded-lg shadow-md">
              <span class="material-icons-outlined text-white text-2xl">restore</span>
            </div>
            <div>
              <h2 class="text-2xl font-bold text-white leading-tight">Recover Item</h2>
              <p class="text-sm text-green-100 mt-0.5">Mark item as found/repaired and restore to original personnel</p>
            </div>
          </div>
          <button
            @click="closeRecoveryModal"
            class="p-2 text-white hover:bg-white/30 rounded-lg transition-all duration-200 hover:scale-110"
            :disabled="recoveryLoading"
          >
            <span class="material-icons-outlined text-2xl">close</span>
          </button>
        </div>
        
        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto p-6 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
          <!-- Item Info -->
          <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-3">
              <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl">info</span>
              Item Details
            </h3>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span class="text-gray-600 dark:text-gray-400">Item:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForRecovery.item?.unit || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-400">Status:</span>
                <span class="ml-2 font-semibold" :class="selectedItemForRecovery.status === 'LOST' ? 'text-red-600' : 'text-orange-600'">
                  {{ selectedItemForRecovery.status }}
                </span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-400">Serial Number:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForRecovery.item?.serial_number || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-400">Model:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForRecovery.item?.model || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Recovery Form -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-6 space-y-6">
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                  Recovery Notes <span class="text-red-500 font-bold">*</span>
                </label>
                <textarea
                  v-model="recoveryForm.recovery_notes"
                  rows="4"
                  required
                  class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-200 resize-none"
                  placeholder="Enter details about how the item was found or repaired..."
                ></textarea>
                <p class="text-xs text-gray-500 dark:text-gray-400">Describe the recovery circumstances (e.g., "Item found in storage room", "Item repaired by technician")</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Recovered By
                  </label>
                  <input
                    v-model="recoveryForm.recovered_by"
                    type="text"
                    class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-200"
                    placeholder="Enter name or code"
                  />
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Recovery Date
                  </label>
                  <input
                    v-model="recoveryForm.recovery_date"
                    type="date"
                    class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-200"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t-2 border-gray-200 dark:border-gray-700 flex justify-end gap-3 shadow-lg">
          <button
            @click="closeRecoveryModal"
            class="px-6 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 font-semibold shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="recoveryLoading"
          >
            Cancel
          </button>
          <button
            @click="recoverItem"
            :disabled="recoveryLoading || !recoveryForm.recovery_notes.trim()"
            class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-lg transition-all duration-200 font-semibold shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <span v-if="recoveryLoading" class="material-icons-outlined animate-spin text-xl">refresh</span>
            <span v-else class="material-icons-outlined text-xl">check_circle</span>
            <span>{{ recoveryLoading ? 'Recovering...' : 'Recover Item' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Issued Items Modal -->
    <div
      v-if="showIssuedItemsModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeIssuedItemsModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                Issued Items for {{ selectedPersonnel?.fullname }} ({{ selectedPersonnel?.code || selectedPersonnel?.user_code || selectedPersonnel?.personnel_code }})
              </h2>
              <p v-if="allIssuedItems.length > 0" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Total: {{ allIssuedItems.length }} | 
                Issued: {{ allIssuedItems.filter(i => i.status === 'ISSUED').length }} | 
                Returned: {{ allIssuedItems.filter(i => i.status === 'RETURNED').length }} | 
                Lost: {{ allIssuedItems.filter(i => i.status === 'LOST').length }} | 
                Damaged: {{ allIssuedItems.filter(i => i.status === 'DAMAGED').length }}
              </p>
            </div>
            <button
              @click="closeIssuedItemsModal"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        <div class="p-6">
          <!-- Status Filter -->
          <div v-if="allIssuedItems.length > 0" class="mb-4 flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Filter by Status:</label>
            <select
              v-model="issuedItemsStatusFilter"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-sm"
            >
              <option value="ALL">All Items</option>
              <option value="ISSUED">Issued Only</option>
              <option value="RETURNED">Returned Only</option>
              <option value="LOST">Lost Only</option>
              <option value="DAMAGED">Damaged Only</option>
              <option value="LOST_DAMAGED">Lost & Damaged</option>
            </select>
            <span class="text-sm text-gray-500 dark:text-gray-400">
              Showing {{ filteredIssuedItems.length }} of {{ allIssuedItems.length }} items
            </span>
          </div>
          
          <div v-if="allIssuedItems.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No items have been issued to this personnel</p>
          </div>
          <div v-else-if="filteredIssuedItems.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No items match the selected filter</p>
          </div>
          <div v-else class="space-y-4">
            <div
              v-for="item in filteredIssuedItems"
              :key="item.id"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
              :class="{
                'bg-green-50 dark:bg-green-900/20': item.status === 'ISSUED',
                'bg-gray-50 dark:bg-gray-700/50': item.status === 'RETURNED',
                'bg-red-50 dark:bg-red-900/20': item.status === 'LOST',
                'bg-orange-50 dark:bg-orange-900/20': item.status === 'DAMAGED'
              }"
            >
              <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ item.item?.unit || 'N/A' }}</h3>
                    <span
                      :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': item.status === 'ISSUED',
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': item.status === 'RETURNED',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': item.status === 'LOST',
                        'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': item.status === 'DAMAGED'
                      }"
                      class="px-2 py-1 text-xs font-semibold rounded-full"
                    >
                      {{ item.status }}
                    </span>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ item.item?.description || 'N/A' }}</p>
                  <div class="mt-2 space-y-1 text-xs text-gray-500 dark:text-gray-500">
                    <p v-if="item.item?.serial_number"><strong>Serial Number:</strong> {{ item.item.serial_number }}</p>
                    <p v-if="item.item?.model"><strong>Model:</strong> {{ item.item.model }}</p>
                    <p><strong>Category:</strong> {{ getCategoryName(item) }}</p>
                    <p><strong>MR Number:</strong> #{{ item.id }}</p>
                    <p><strong>Issued:</strong> {{ formatDate(item.issued_at) }}</p>
                    <p><strong>Issued by:</strong> {{ item.issued_by_user_code || 'N/A' }}</p>
                    <p v-if="item.returned_at"><strong>Returned:</strong> {{ formatDate(item.returned_at) }}</p>
                    <!-- Recovery Information (for RETURNED items that were recovered) -->
                    <div v-if="item.status === 'RETURNED' && hasRecoveryInfo(item.remarks)" class="mt-2 p-3 bg-green-50 dark:bg-green-900/20 rounded text-xs text-green-800 dark:text-green-200 border-l-4 border-green-500">
                      <p class="font-semibold mb-2 flex items-center gap-1">
                        <span class="material-icons-outlined text-sm">check_circle</span>
                        Item Recovery Information
                      </p>
                      <div class="space-y-1.5">
                        <p v-if="getOriginalStatusInfo(item.remarks, 'type')">
                          <strong>Original Status:</strong> {{ getOriginalStatusInfo(item.remarks, 'type') }}
                        </p>
                        <p v-if="getRecoveryInfo(item.remarks, 'recovery_notes')">
                          <strong>Recovery Notes:</strong> {{ getRecoveryInfo(item.remarks, 'recovery_notes') }}
                        </p>
                        <p v-if="getRecoveryInfo(item.remarks, 'recovered_by')">
                          <strong>Recovered By:</strong> {{ getRecoveryInfo(item.remarks, 'recovered_by') }}
                        </p>
                        <p v-if="getRecoveryInfo(item.remarks, 'recovery_date')">
                          <strong>Recovery Date:</strong> {{ formatDate(getRecoveryInfo(item.remarks, 'recovery_date')) }}
                        </p>
                        <p v-if="getRecoveryInfo(item.remarks, 'processed_by')">
                          <strong>Processed By:</strong> {{ getRecoveryInfo(item.remarks, 'processed_by') }}
                        </p>
                      </div>
                    </div>
                    
                    <!-- Regular Remarks (for items without recovery info) -->
                    <p v-else-if="item.remarks && item.status !== 'LOST' && item.status !== 'DAMAGED' && !hasRecoveryInfo(item.remarks)">
                      <strong>Remarks:</strong> {{ typeof item.remarks === 'string' ? item.remarks : (item.remarks?.description || 'N/A') }}
                    </p>
                  </div>
                  <div v-if="(item.status === 'LOST' || item.status === 'DAMAGED') && item.remarks" class="mt-2 p-3 bg-red-50 dark:bg-red-900/20 rounded text-xs text-red-700 dark:text-red-300 border-l-4 border-red-500">
                    <p class="font-semibold mb-2">{{ item.status }} Item Details:</p>
                    <div class="space-y-1">
                      <p v-if="getLostDamagedInfo(item.remarks, 'description')">
                        <strong>Description:</strong> {{ getLostDamagedInfo(item.remarks, 'description') }}
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'reported_by')">
                        <strong>Reported By:</strong> {{ getLostDamagedInfo(item.remarks, 'reported_by') }}
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'incident_date')">
                        <strong>Incident Date:</strong> {{ formatDate(getLostDamagedInfo(item.remarks, 'incident_date')) }}
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'estimated_value_loss')">
                        <strong>Estimated Value Loss:</strong> ₱{{ parseFloat(getLostDamagedInfo(item.remarks, 'estimated_value_loss')).toLocaleString() }}
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'processed_by')">
                        <strong>Processed By:</strong> {{ getLostDamagedInfo(item.remarks, 'processed_by') }}
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'processed_at')">
                        <strong>Processed At:</strong> {{ formatDate(getLostDamagedInfo(item.remarks, 'processed_at')) }}
                      </p>
                    </div>
                    <!-- Recovery Button -->
                    <div class="mt-3">
                      <button
                        @click="openRecoveryModal(item)"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                      >
                        <span class="material-icons-outlined text-sm">restore</span>
                        <span>Recover Item</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lost/Damaged Modal -->
    <div
      v-if="showLostDamagedModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="closeLostDamagedModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">Mark as Lost/Damaged</h2>
          <p v-if="selectedItem?.item" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Item: {{ selectedItem.item.unit }} 
            <span v-if="selectedItem.item.serial_number">(Serial: {{ selectedItem.item.serial_number }})</span>
            <span v-if="selectedItem.item.model">- {{ selectedItem.item.model }}</span>
          </p>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Status <span class="text-red-500">*</span>
            </label>
            <select
              v-model="lostDamagedStatus"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white"
              required
            >
              <option value="">Select Status</option>
              <option value="LOST">Lost</option>
              <option value="DAMAGED">Damaged</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Reported By
            </label>
            <input
              v-model="lostDamagedReportedBy"
              type="text"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white"
              placeholder="Name of person who reported"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Incident Date
            </label>
            <input
              v-model="lostDamagedIncidentDate"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <div v-if="lostDamagedStatus === 'DAMAGED'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Estimated Value Loss (₱)
            </label>
            <input
              v-model="lostDamagedValueLoss"
              type="number"
              min="0"
              step="0.01"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white"
              placeholder="0.00"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Description/Remarks <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="lostDamagedRemarks"
              rows="4"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 dark:bg-gray-700 dark:text-white"
              placeholder="Enter detailed description of the incident..."
              required
            ></textarea>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <button
              @click="closeLostDamagedModal"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              @click="confirmLostDamaged"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
              :disabled="!lostDamagedStatus || !lostDamagedRemarks.trim() || processingItem"
            >
              {{ processingItem ? 'Processing...' : `Mark as ${lostDamagedStatus || 'Lost/Damaged'}` }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <div
      v-if="message"
      :class="{
        'bg-green-100 border-green-400 text-green-700': messageType === 'success',
        'bg-red-100 border-red-400 text-red-700': messageType === 'error'
      }"
      class="fixed top-4 right-4 border px-4 py-3 rounded shadow-lg z-50"
    >
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axiosClient from '../axios'

const personnel = ref([])
const searchQuery = ref('')
const statusFilter = ref('all')
const loading = ref(false)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const showClearanceModal = ref(false)
const showReassignModal = ref(false)
const showLostDamagedModal = ref(false)
const showIssuedItemsModal = ref(false)
const showRecoveryModal = ref(false)
const selectedPersonnel = ref(null)
const pendingItems = ref([])
const allIssuedItems = ref([])
const issuedItemsStatusFilter = ref('ALL')
const activeUsers = ref([])
const selectedItem = ref(null)
const selectedItemForRecovery = ref(null)
const recoveryForm = ref({
  recovery_notes: '',
  recovered_by: '',
  recovery_date: new Date().toISOString().split('T')[0]
})
const recoveryLoading = ref(false)
const reassignToUserId = ref('')
const lostDamagedRemarks = ref('')
const lostDamagedStatus = ref('')
const lostDamagedReportedBy = ref('')
const lostDamagedIncidentDate = ref('')
const lostDamagedValueLoss = ref('')
const processingItem = ref(null)
const processingResignation = ref(null)
const processingBulk = ref(false)
const selectedItems = ref([])
const message = ref('')
const messageType = ref('success')

// Computed properties for items with/without MR records
const itemsWithMrCount = computed(() => {
  return pendingItems.value.filter(item => item.id).length
})

const itemsWithoutMrCount = computed(() => {
  return pendingItems.value.filter(item => !item.id).length
})

const fetchPersonnel = async () => {
  loading.value = true
  try {
    // Fetch users with accounts
    const usersResponse = await axiosClient.get('/users')
    const users = Array.isArray(usersResponse.data) ? usersResponse.data : (usersResponse.data.data || [])
    
    // Fetch locations with personnel (personnel without accounts)
    const locationsResponse = await axiosClient.get('/locations')
    const locations = Array.isArray(locationsResponse.data) ? locationsResponse.data : (locationsResponse.data.data || [])
    const locationsWithPersonnel = locations.filter(loc => loc.personnel && loc.personnel.trim() !== '')
    
    // Fetch pending items count for each user
    const usersWithPending = await Promise.all(
      users.map(async (user) => {
        try {
          const pendingResponse = await axiosClient.get(`/memorandum-receipts/user/${user.id}/pending`)
          return {
            ...user,
            type: 'USER',
            code: user.user_code,
            pending_items_count: pendingResponse.data.data?.length || 0
          }
        } catch (error) {
          return {
            ...user,
            type: 'USER',
            code: user.user_code,
            pending_items_count: 0
          }
        }
      })
    )
    
    // Fetch pending items count for each personnel (location)
    const personnelWithPending = await Promise.all(
      locationsWithPersonnel.map(async (location) => {
        try {
          const pendingResponse = await axiosClient.get(`/memorandum-receipts/personnel/${location.id}/pending`)
          return {
            id: location.id,
            fullname: location.personnel,
            email: null,
            role: null,
            image: null,
            location: location.location,
            location_id: location.id,
            user_code: location.personnel_code,
            code: location.personnel_code,
            status: 'ACTIVE', // Personnel are always active
            type: 'PERSONNEL',
            pending_items_count: pendingResponse.data.data?.length || 0
          }
        } catch (error) {
          return {
            id: location.id,
            fullname: location.personnel,
            email: null,
            role: null,
            image: null,
            location: location.location,
            location_id: location.id,
            user_code: location.personnel_code,
            code: location.personnel_code,
            status: 'ACTIVE',
            type: 'PERSONNEL',
            pending_items_count: 0
          }
        }
      })
    )
    
    // Combine users and personnel
    personnel.value = [...usersWithPending, ...personnelWithPending]
  } catch (error) {
    console.error('Error fetching personnel:', error)
    showMessage('Failed to fetch personnel', 'error')
  } finally {
    loading.value = false
  }
}

const fetchActiveUsers = async () => {
  try {
    // Fetch active users
    const usersResponse = await axiosClient.get('/users')
    const users = Array.isArray(usersResponse.data) ? usersResponse.data : (usersResponse.data.data || [])
    const activeUsersList = users.filter(u => u.status === 'ACTIVE').map(u => ({
      ...u,
      type: 'USER',
      code: u.user_code
    }))
    
    // Fetch locations with personnel
    const locationsResponse = await axiosClient.get('/locations')
    const locations = Array.isArray(locationsResponse.data) ? locationsResponse.data : (locationsResponse.data.data || [])
    const activePersonnel = locations
      .filter(loc => loc.personnel && loc.personnel.trim() !== '')
      .map(loc => ({
        id: loc.id,
        fullname: loc.personnel,
        user_code: loc.personnel_code,
        code: loc.personnel_code,
        location: loc.location,
        type: 'PERSONNEL',
        status: 'ACTIVE'
      }))
    
    activeUsers.value = [...activeUsersList, ...activePersonnel]
  } catch (error) {
    console.error('Error fetching active users:', error)
  }
}

const resignedWithPendingItems = computed(() => {
  return personnel.value.filter(p => 
    p.status === 'RESIGNED' && p.pending_items_count > 0
  )
})

const filteredPersonnel = computed(() => {
  let filtered = personnel.value

  // Filter by status
  if (statusFilter.value !== 'all') {
    filtered = filtered.filter(p => p.status === statusFilter.value)
  }

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(p => {
      return (
        p.fullname?.toLowerCase().includes(query) ||
        p.user_code?.toLowerCase().includes(query) ||
        p.personnel_code?.toLowerCase().includes(query) ||
        p.code?.toLowerCase().includes(query) ||
        p.email?.toLowerCase().includes(query)
      )
    })
  }

  // Reset to page 1 when filters change
  if (currentPage.value > Math.ceil(filtered.length / itemsPerPage.value)) {
    currentPage.value = 1
  }

  return filtered
})

const paginatedPersonnel = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredPersonnel.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredPersonnel.value.length / itemsPerPage.value)
})

const paginationInfo = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value + 1
  const end = Math.min(currentPage.value * itemsPerPage.value, filteredPersonnel.value.length)
  const total = filteredPersonnel.value.length
  return { start, end, total }
})

const openIssuedItemsModal = async (person) => {
  selectedPersonnel.value = person
  try {
    let response
    if (person.type === 'PERSONNEL') {
      response = await axiosClient.get(`/memorandum-receipts/personnel/${person.id}/all`)
    } else {
      response = await axiosClient.get(`/memorandum-receipts/user/${person.id}/all`)
    }
    allIssuedItems.value = response.data.data || []
    showIssuedItemsModal.value = true
  } catch (error) {
    console.error('Error fetching issued items:', error)
    showMessage('Failed to fetch issued items', 'error')
  }
}

const closeIssuedItemsModal = () => {
  showIssuedItemsModal.value = false
  selectedPersonnel.value = null
  allIssuedItems.value = []
  issuedItemsStatusFilter.value = 'ALL' // Reset filter when closing
}

// Filter issued items by status
const filteredIssuedItems = computed(() => {
  if (!allIssuedItems.value || allIssuedItems.value.length === 0) return []
  if (issuedItemsStatusFilter.value === 'ALL') return allIssuedItems.value
  if (issuedItemsStatusFilter.value === 'LOST_DAMAGED') {
    return allIssuedItems.value.filter(item => item.status === 'LOST' || item.status === 'DAMAGED')
  }
  return allIssuedItems.value.filter(item => item.status === issuedItemsStatusFilter.value)
})

const openClearanceModal = async (person) => {
  // Reset all states first
  selectedPersonnel.value = person
  selectedItems.value = [] // Reset selections
  processingItem.value = null // Reset processing state
  processingBulk.value = false // Reset bulk processing state
  pendingItems.value = [] // Clear previous items
  
  try {
    let response
    if (person.type === 'PERSONNEL') {
      response = await axiosClient.get(`/memorandum-receipts/personnel/${person.id}/pending`)
    } else {
      response = await axiosClient.get(`/memorandum-receipts/user/${person.id}/pending`)
    }
    pendingItems.value = response.data.data || []
    showClearanceModal.value = true
    
    // Debug: Log the items to see their structure
    console.log('Pending items loaded:', pendingItems.value.length)
    console.log('Processing state:', processingItem.value)
  } catch (error) {
    console.error('Error fetching pending items:', error)
    showMessage('Failed to fetch pending items', 'error')
  }
}

const closeClearanceModal = () => {
  showClearanceModal.value = false
  selectedPersonnel.value = null
  pendingItems.value = []
  selectedItems.value = []
}

// Bulk operations
const toggleSelectAll = () => {
  const itemsWithMr = pendingItems.value.filter(item => item.id)
  const itemsWithMrIds = itemsWithMr.map(item => item.id)
  
  // Check if all items with MR are selected
  const allSelected = itemsWithMrIds.length > 0 && 
    itemsWithMrIds.every(id => selectedItems.value.includes(id))
  
  if (allSelected) {
    // Deselect all
    selectedItems.value = []
  } else {
    // Select all items with MR records
    selectedItems.value = [...itemsWithMrIds]
  }
}

const toggleItemSelection = (item) => {
  if (!item.id) return // Skip items without MR records
  
  const index = selectedItems.value.indexOf(item.id)
  if (index > -1) {
    selectedItems.value.splice(index, 1)
  } else {
    selectedItems.value.push(item.id)
  }
}

const isItemSelected = (item) => {
  return item.id && selectedItems.value.includes(item.id)
}

const isItemProcessing = (item) => {
  if (!item) return false
  if (!processingItem.value) return false // No item is being processed
  
  const itemId = item.item_id || item.item?.id
  
  // Check if this specific item is being processed
  const isProcessing = processingItem.value === item.id || 
                       (itemId && processingItem.value === `item-${itemId}`)
  
  return isProcessing
}

const bulkReturnItems = async () => {
  if (selectedItems.value.length === 0) {
    showMessage('Please select at least one item', 'error')
    return
  }

  if (!confirm(`Are you sure you want to return ${selectedItems.value.length} item(s)?`)) {
    return
  }

  processingBulk.value = true
  try {
    const response = await axiosClient.post(
      `/memorandum-receipts/user/${selectedPersonnel.value.id}/bulk-return`,
      {
        mr_ids: selectedItems.value,
        remarks: `Bulk returned during clearance for ${selectedPersonnel.value.fullname}`
      }
    )
    
    if (response.data.success) {
      showMessage(response.data.message, 'success')
      selectedItems.value = []
      await openClearanceModal(selectedPersonnel.value)
      await fetchPersonnel()
    }
  } catch (error) {
    showMessage(error.response?.data?.message || 'Failed to bulk return items', 'error')
  } finally {
    processingBulk.value = false
  }
}

const openBulkReassignModal = () => {
  if (selectedItems.value.length === 0) {
    showMessage('Please select at least one item', 'error')
    return
  }
  selectedItem.value = { id: 'BULK', mr_ids: selectedItems.value }
  reassignToUserId.value = ''
  fetchActiveUsers()
  showReassignModal.value = true
}

const markAsResigned = async (person) => {
  // Only users with accounts can be marked as resigned
  if (person.type === 'PERSONNEL') {
    showMessage('Personnel without accounts cannot be marked as resigned. Remove them from the location instead.', 'error')
    return
  }
  
  if (!confirm(`Are you sure you want to mark ${person.fullname} as resigned?`)) {
    return
  }

  processingResignation.value = person.id
  try {
    const response = await axiosClient.post(`/users/${person.id}/mark-resigned`)
    if (response.data.success) {
      showMessage('User marked as resigned successfully', 'success')
      await fetchPersonnel()
    }
  } catch (error) {
    if (error.response?.data?.error === 'PENDING_ITEMS') {
      // Open clearance modal if there are pending items
      openClearanceModal(person)
      showMessage(error.response.data.message, 'error')
    } else {
      showMessage(error.response?.data?.message || 'Failed to mark as resigned', 'error')
    }
  } finally {
    processingResignation.value = null
  }
}

const returnItem = async (item) => {
  console.log('Return item clicked:', item)
  
  // For items without MR records, we need to create an MR record first, then return it
  if (!item.id) {
    // Item doesn't have MR record - formalize it first, then return
    const itemId = item.item_id || item.item?.id
    if (!itemId || !item.item?.uuid || !selectedPersonnel.value?.id) {
      showMessage('Item or personnel information is missing. Please refresh and try again.', 'error')
      return
    }
    
    const processingKey = `item-${itemId}`
    console.log('Processing item without MR:', processingKey)
    processingItem.value = processingKey
    
    try {
      // Step 1: Formalize the item (create MR record with ISSUED status)
      const formalizeResponse = await axiosClient.post(
        `/memorandum-receipts/formalize/user/${selectedPersonnel.value.id}`,
        {
          item_ids: [itemId],
          issued_by_user_code: item.issued_by_user_code || 'SYSTEM',
          issued_at: item.issued_at || new Date().toISOString().split('T')[0],
          remarks: 'Formalized during return process - item was assigned before MR system was implemented'
        }
      )
      
      if (formalizeResponse.data.success && formalizeResponse.data.data && formalizeResponse.data.data.length > 0) {
        // Step 2: Get the newly created MR ID
        const newMrId = formalizeResponse.data.data[0].id
        
        // Step 3: Immediately return the item (mark MR as RETURNED)
        const returnResponse = await axiosClient.post(
          `/memorandum-receipts/${newMrId}/return`,
          {
            remarks: 'Item returned during clearance process'
          }
        )
        
        if (returnResponse.data.success) {
          showMessage('Item returned successfully', 'success')
          // Refresh pending items and issued items
          await openClearanceModal(selectedPersonnel.value)
          await fetchPersonnel()
          // Refresh the "View Items" modal if it's open
          if (showIssuedItemsModal.value && selectedPersonnel.value) {
            await openIssuedItemsModal(selectedPersonnel.value)
          }
        } else {
          showMessage('Item was formalized but failed to mark as returned. Please try again.', 'error')
        }
      } else {
        showMessage('Failed to formalize item. Please try again.', 'error')
      }
    } catch (error) {
      console.error('Error returning item:', error)
      showMessage(error.response?.data?.message || 'Failed to return item', 'error')
    } finally {
      console.log('Resetting processing state')
      processingItem.value = null
    }
    return
  }
  
  // Item has MR record - use MR endpoint
  console.log('Processing item with MR:', item.id)
  processingItem.value = item.id
  try {
    const response = await axiosClient.post(`/memorandum-receipts/${item.id}/return`)
    if (response.data.success) {
      showMessage('Item returned successfully', 'success')
      await openClearanceModal(selectedPersonnel.value)
      await fetchPersonnel()
      // Refresh the "View Items" modal if it's open
      if (showIssuedItemsModal.value && selectedPersonnel.value) {
        await openIssuedItemsModal(selectedPersonnel.value)
      }
    }
  } catch (error) {
    console.error('Error returning item:', error)
    showMessage(error.response?.data?.message || 'Failed to return item', 'error')
  } finally {
    console.log('Resetting processing state')
    processingItem.value = null
  }
}

const openReassignModal = (item) => {
  selectedItem.value = item
  reassignToUserId.value = ''
  fetchActiveUsers()
  showReassignModal.value = true
}

const closeReassignModal = () => {
  showReassignModal.value = false
  selectedItem.value = null
  reassignToUserId.value = ''
}

const confirmReassign = async () => {
  // Handle bulk reassign
  if (selectedItem.value && selectedItem.value.id === 'BULK' && selectedItem.value.mr_ids) {
    if (!reassignToUserId.value) {
      showMessage('Please select a personnel to reassign to', 'error')
      return
    }

    const [type, id] = reassignToUserId.value.split('-')
    processingBulk.value = true
    try {
      const response = await axiosClient.post(
        `/memorandum-receipts/user/${selectedPersonnel.value.id}/bulk-reassign`,
        {
          mr_ids: selectedItem.value.mr_ids,
          reassign_to_type: type,
          reassign_to_id: parseInt(id),
          remarks: `Bulk reassigned during clearance for ${selectedPersonnel.value.fullname}`
        }
      )
      
      if (response.data.success) {
        showMessage(response.data.message, 'success')
        selectedItems.value = []
        closeReassignModal()
        await openClearanceModal(selectedPersonnel.value)
        await fetchPersonnel()
      }
    } catch (error) {
      showMessage(error.response?.data?.message || 'Failed to bulk reassign items', 'error')
    } finally {
      processingBulk.value = false
    }
    return
  }

  // Handle single item reassign
  if (!selectedItem.value || !reassignToUserId.value) {
    showMessage('Please select a personnel to reassign to', 'error')
    return
  }

  const [type, id] = reassignToUserId.value.split('-')
  
  // For items without MR records, update item directly
  if (!selectedItem.value.id) {
    const itemId = selectedItem.value.item_id || selectedItem.value.item?.id
    if (!itemId || !selectedItem.value.item?.uuid) {
      showMessage('Item information is missing. Please refresh and try again.', 'error')
      return
    }
    
    processingItem.value = `item-${itemId}`
    try {
      const updateData = {}
      if (type === 'USER') {
        updateData.user_id = parseInt(id)
        updateData.location_id = null
      } else if (type === 'PERSONNEL') {
        updateData.location_id = parseInt(id)
        updateData.user_id = null
      }
      
      const response = await axiosClient.put(`/items/${selectedItem.value.item.uuid}`, updateData)
      if (response.data) {
        showMessage('Item reassigned successfully', 'success')
        closeReassignModal()
        await openClearanceModal(selectedPersonnel.value)
        await fetchPersonnel()
      }
    } catch (error) {
      console.error('Error reassigning item:', error)
      showMessage(error.response?.data?.message || 'Failed to reassign item', 'error')
    } finally {
      processingItem.value = null
    }
    return
  }
  
  // Item has MR record - use MR endpoint
  processingItem.value = selectedItem.value.id
  try {
    const response = await axiosClient.post(
      `/memorandum-receipts/${selectedItem.value.id}/reassign`,
      {
        reassign_to_type: type,
        reassign_to_id: parseInt(id),
        remarks: `Reassigned during clearance`
      }
    )
    
    if (response.data.success) {
      showMessage('Item reassigned successfully', 'success')
      closeReassignModal()
      await openClearanceModal(selectedPersonnel.value)
      await fetchPersonnel()
    }
  } catch (error) {
    showMessage(error.response?.data?.message || 'Failed to reassign item', 'error')
  } finally {
    processingItem.value = null
  }
}

const confirmReassignOld = async () => {
  if (!reassignToUserId.value) return

  processingItem.value = selectedItem.value.id
  try {
    const [type, id] = reassignToUserId.value.split('-')
    const payload = {}
    
    if (type === 'PERSONNEL') {
      payload.new_location_id = parseInt(id)
    } else {
      payload.new_user_id = parseInt(id)
    }
    
    const response = await axiosClient.post(`/memorandum-receipts/${selectedItem.value.id}/reassign`, payload)
    if (response.data.success) {
      showMessage('Item reassigned successfully', 'success')
      closeReassignModal()
      await openClearanceModal(selectedPersonnel.value)
      await fetchPersonnel()
    }
  } catch (error) {
    showMessage(error.response?.data?.message || 'Failed to reassign item', 'error')
  } finally {
    processingItem.value = null
  }
}

const openLostDamagedModal = (item) => {
  selectedItem.value = item
  lostDamagedRemarks.value = ''
  lostDamagedStatus.value = ''
  lostDamagedReportedBy.value = selectedPersonnel.value?.fullname || ''
  lostDamagedIncidentDate.value = new Date().toISOString().split('T')[0]
  lostDamagedValueLoss.value = ''
  showLostDamagedModal.value = true
}

// Open recovery modal
const openRecoveryModal = (item) => {
  selectedItemForRecovery.value = item
  recoveryForm.value = {
    recovery_notes: '',
    recovered_by: '',
    recovery_date: new Date().toISOString().split('T')[0]
  }
  showRecoveryModal.value = true
}

// Close recovery modal
const closeRecoveryModal = () => {
  showRecoveryModal.value = false
  selectedItemForRecovery.value = null
  recoveryForm.value = {
    recovery_notes: '',
    recovered_by: '',
    recovery_date: new Date().toISOString().split('T')[0]
  }
}

// Recover item
const recoverItem = async () => {
  if (!selectedItemForRecovery.value || !selectedItemForRecovery.value.id) {
    showMessage('Invalid item selected', 'error')
    return
  }

  if (!recoveryForm.value.recovery_notes.trim()) {
    showMessage('Please enter recovery notes', 'error')
    return
  }

  recoveryLoading.value = true
  try {
    const baseURL = axiosClient.defaults.baseURL || '/api'
    const path = baseURL.includes('/v1')
      ? `/memorandum-receipts/${selectedItemForRecovery.value.id}/recover`
      : `/v1/memorandum-receipts/${selectedItemForRecovery.value.id}/recover`
    
    const response = await axiosClient.post(path, {
      recovery_notes: recoveryForm.value.recovery_notes,
      recovered_by: recoveryForm.value.recovered_by || 'SYSTEM',
      recovery_date: recoveryForm.value.recovery_date
    })
    
    if (response.data.success) {
      showMessage('Item recovered successfully! It has been restored to the original personnel.', 'success')
      closeRecoveryModal()
      // Refresh issued items if modal is open
      if (showIssuedItemsModal.value && selectedPersonnel.value) {
        await openIssuedItemsModal(selectedPersonnel.value)
      }
      // Refresh personnel list
      await fetchPersonnel()
    } else {
      showMessage(response.data.message || 'Failed to recover item', 'error')
    }
  } catch (error) {
    console.error('Error recovering item:', error)
    showMessage(error.response?.data?.message || 'Failed to recover item', 'error')
  } finally {
    recoveryLoading.value = false
  }
}

const closeLostDamagedModal = () => {
  showLostDamagedModal.value = false
  selectedItem.value = null
  lostDamagedRemarks.value = ''
  lostDamagedStatus.value = ''
  lostDamagedReportedBy.value = ''
  lostDamagedIncidentDate.value = ''
  lostDamagedValueLoss.value = ''
}

const confirmLostDamaged = async () => {
  if (!lostDamagedStatus.value || !lostDamagedRemarks.value.trim()) return

  // For items without MR records, we need to formalize them first
  if (!selectedItem.value.id) {
    const itemId = selectedItem.value.item_id || selectedItem.value.item?.id
    if (!itemId || !selectedPersonnel.value?.id) {
      showMessage('Item or personnel information is missing. Please refresh and try again.', 'error')
      return
    }
    
    processingItem.value = `item-${itemId}`
    try {
      // First, formalize the item (create MR record)
      const formalizeResponse = await axiosClient.post(
        `/memorandum-receipts/formalize/user/${selectedPersonnel.value.id}`,
        {
          item_ids: [itemId]
        }
      )
      
      if (formalizeResponse.data.success && formalizeResponse.data.data && formalizeResponse.data.data.length > 0) {
        // Get the newly created MR ID
        const newMrId = formalizeResponse.data.data[0].id
        
        // Now mark as lost/damaged
        const payload = {
          status: lostDamagedStatus.value,
          remarks: lostDamagedRemarks.value,
          reported_by: lostDamagedReportedBy.value || selectedPersonnel.value?.fullname || 'N/A',
          incident_date: lostDamagedIncidentDate.value || new Date().toISOString().split('T')[0],
          estimated_value_loss: lostDamagedStatus.value === 'DAMAGED' && lostDamagedValueLoss.value ? parseFloat(lostDamagedValueLoss.value) : null,
          investigation_required: false
        }
        
        const response = await axiosClient.post(`/memorandum-receipts/${newMrId}/lost-damaged`, payload)
        if (response.data.success) {
          showMessage(`Item marked as ${lostDamagedStatus.value.toLowerCase()} successfully`, 'success')
          closeLostDamagedModal()
          await openClearanceModal(selectedPersonnel.value)
          await fetchPersonnel()
        }
      } else {
        showMessage('Failed to formalize item. Please try again.', 'error')
      }
    } catch (error) {
      console.error('Error marking item as lost/damaged:', error)
      showMessage(error.response?.data?.message || 'Failed to mark item', 'error')
    } finally {
      processingItem.value = null
    }
    return
  }

  // Item has MR record - use MR endpoint directly
  processingItem.value = selectedItem.value.id
  try {
    const payload = {
      status: lostDamagedStatus.value,
      remarks: lostDamagedRemarks.value,
      reported_by: lostDamagedReportedBy.value || selectedPersonnel.value?.fullname || 'N/A',
      incident_date: lostDamagedIncidentDate.value || new Date().toISOString().split('T')[0],
      estimated_value_loss: lostDamagedStatus.value === 'DAMAGED' && lostDamagedValueLoss.value ? parseFloat(lostDamagedValueLoss.value) : null,
      investigation_required: false
    }

    const response = await axiosClient.post(`/memorandum-receipts/${selectedItem.value.id}/lost-damaged`, payload)
    if (response.data.success) {
      showMessage(`Item marked as ${lostDamagedStatus.value.toLowerCase()} successfully`, 'success')
      closeLostDamagedModal()
      await openClearanceModal(selectedPersonnel.value)
      await fetchPersonnel()
    }
  } catch (error) {
    showMessage(error.response?.data?.message || 'Failed to mark item', 'error')
  } finally {
    processingItem.value = null
  }
}

const formatLostDamagedRemarks = (remarks) => {
  if (!remarks) return 'N/A'
  try {
    // If remarks is a JSON string, parse it
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object') {
      return parsed.description || parsed.type || 'N/A'
    }
    return remarks
  } catch (e) {
    // If not JSON, return as is
    return typeof remarks === 'string' ? remarks : 'N/A'
  }
}

const getLostDamagedInfo = (remarks, field) => {
  if (!remarks) return null
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object') {
      return parsed[field] || null
    }
    return null
  } catch (e) {
    return null
  }
}

const getRecoveryInfo = (remarks, field) => {
  if (!remarks) return null
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object' && parsed.recovered) {
      return parsed[field] || null
    }
    return null
  } catch (e) {
    return null
  }
}

const hasRecoveryInfo = (remarks) => {
  if (!remarks) return false
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    return parsed && typeof parsed === 'object' && parsed.recovered === true
  } catch (e) {
    return false
  }
}

const getOriginalStatusInfo = (remarks, field) => {
  if (!remarks) return null
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object' && parsed.original_remarks) {
      const original = parsed.original_remarks
      if (typeof original === 'object') {
        return original[field] || null
      }
    }
    return null
  } catch (e) {
    return null
  }
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString()
}

const getCategoryName = (item) => {
  // Handle both array format (from virtual MR records) and object format (from actual MR records)
  if (item.item) {
    // ItemResource returns category as a string (category name), not an object
    if (typeof item.item === 'object') {
      // Check if category is a string (from ItemResource)
      if (typeof item.item.category === 'string') {
        return item.item.category || 'N/A'
      }
      // Check if category is an object with name property (from direct relationship)
      if (item.item.category && typeof item.item.category === 'object') {
        return item.item.category.name || item.item.category.category || 'N/A'
      }
      // Fallback: check category_name
      if (item.item.category_name) {
        return item.item.category_name
      }
    }
  }
  return 'N/A'
}

const showMessage = (msg, type = 'success') => {
  message.value = msg
  messageType.value = type
  setTimeout(() => {
    message.value = ''
  }, 5000)
}

// Generate accountability report (PDF)
const generateAccountabilityReport = async (personnel) => {
  if (!personnel || personnel.type !== 'USER') {
    showMessage('Accountability report is only available for user accounts', 'error')
    return
  }
  
  try {
    // Request PDF format
    const response = await axiosClient.get(`/memorandum-receipts/accountability-report/user/${personnel.id}`, {
      params: { format: 'pdf' },
      responseType: 'blob' // Important for PDF download
    })
    
    // Create blob from response
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Accountability_Report_${personnel.user_code || personnel.code}_${new Date().toISOString().split('T')[0]}.pdf`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    showMessage('Accountability report (PDF) generated successfully', 'success')
  } catch (error) {
    console.error('Error generating accountability report:', error)
    showMessage(error.response?.data?.message || 'Failed to generate accountability report', 'error')
  }
}

onMounted(() => {
  fetchPersonnel()
  fetchActiveUsers()
})
</script>

