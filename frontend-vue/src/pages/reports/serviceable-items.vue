<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../../composables/useItems'
import axiosClient from '../../axios'

const router = useRouter()
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedStatus = ref('all') // all, serviceable, non-serviceable, maintenance

// Get items from the API using the composable
const { items, fetchitems, loading, error } = useItems()

// Fetch items when component mounts
onMounted(async () => {
  await fetchitems()
})

// Map API data to the format expected by the table
const inventoryItems = computed(() => {
  return items.value.map(item => ({
    qrCode: item.qr_code_image || '/images/qr-sample.png',
    image: item.image_path || '/images/default.jpg',
    article: item.unit || '',
    category: item.category || 'Inventory',
    description: item.description || '',
    propertyAccountCode: item.pac || '',
    unitValue: item.unit_value || '',
    dateAcquired: item.date_acquired || '',
    poNumber: item.po_number || '',
    location: item.location || '',
    condition: item.condition || '',
    issuedTo: item.issued_to || 'Not Assigned',
    id: item.id,
    uuid: item.uuid,
    quantity: item.quantity,
    // Determine serviceable status based on condition
    serviceableStatus: getServiceableStatus(item.condition)
  }))
})

// Function to determine serviceable status based on condition
const getServiceableStatus = (condition) => {
  if (!condition) return 'non-serviceable'
  
  const conditionLower = condition.toLowerCase()
  
  // Check for "Non - Serviceable" first (most specific)
  if (conditionLower.includes('non') && conditionLower.includes('serviceable')) {
    return 'non-serviceable'
  }
  // Check for "On Maintenance"
  else if (conditionLower.includes('maintenance')) {
    return 'maintenance'
  }
  // Check for "Serviceable" (should be last to avoid false positives)
  else if (conditionLower.includes('serviceable')) {
    return 'serviceable'
  }
  
  // Default to non-serviceable for any unrecognized conditions
  return 'non-serviceable'
}

// Filter items based on search query and status
const filteredItems = computed(() => {
  let filtered = inventoryItems.value

  // Filter by status
  if (selectedStatus.value !== 'all') {
    filtered = filtered.filter(item => item.serviceableStatus === selectedStatus.value)
  }

  // Filter by search query
  if (searchQuery.value) {
    filtered = filtered.filter(item => {
      return Object.values(item).some(value => 
        value.toString().toLowerCase().includes(searchQuery.value.toLowerCase())
      )
    })
  }

  return filtered
})

// Pagination
const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredItems.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage.value))

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const changeItemsPerPage = (newValue) => {
  itemsPerPage.value = Number(newValue)
  currentPage.value = 1
}

// Status counts for summary
const statusCounts = computed(() => {
  const counts = {
    serviceable: 0,
    'non-serviceable': 0,
    maintenance: 0
  }
  
  inventoryItems.value.forEach(item => {
    if (counts.hasOwnProperty(item.serviceableStatus)) {
      counts[item.serviceableStatus]++
    }
  })
  
  return counts
})

// Print report
const printReport = () => {
  const printWindow = window.open('', '_blank')
  
  const now = new Date()
  const dateFormatted = now.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone: 'Asia/Manila'
  })
  const timeFormatted = now.toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Asia/Manila'
  })
  
  const tableRows = filteredItems.value.map(item => `
    <tr>
      <td>${item.article || 'N/A'}</td>
      <td>${item.category || 'N/A'}</td>
      <td>${item.description || 'N/A'}</td>
      <td>${item.propertyAccountCode || 'N/A'}</td>
      <td>${item.unitValue || 'N/A'}</td>
      <td>${item.dateAcquired || 'N/A'}</td>
      <td>${item.location || 'N/A'}</td>
      <td>${item.condition || 'N/A'}</td>
      <td>${item.issuedTo || 'Not Assigned'}</td>
      <td>${item.quantity || 'N/A'}</td>
      <td>${item.serviceableStatus.replace('-', ' ')}</td>
    </tr>
  `).join('')
  
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Serviceable Items Report - ${dateFormatted}</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          margin: 0;
          padding: 20px;
          color: #333;
        }
        .report-header {
          text-align: center;
          margin-bottom: 20px;
          padding-bottom: 15px;
          border-bottom: 2px solid #10B981;
        }
        .report-title {
          font-size: 24px;
          font-weight: bold;
          color: #10B981;
          margin: 0 0 5px 0;
        }
        .report-subtitle {
          font-size: 14px;
          color: #666;
          margin: 0;
        }
        .report-meta {
          margin-top: 15px;
          font-size: 12px;
          color: #666;
        }
        .summary-cards {
          display: flex;
          justify-content: space-around;
          margin: 20px 0;
          flex-wrap: wrap;
        }
        .summary-card {
          background: #f0fdf4;
          border: 1px solid #10B981;
          border-radius: 8px;
          padding: 15px;
          margin: 5px;
          text-align: center;
          min-width: 120px;
        }
        .summary-card h3 {
          margin: 0 0 5px 0;
          font-size: 18px;
          color: #10B981;
        }
        .summary-card p {
          margin: 0;
          font-size: 14px;
          color: #666;
        }
        .inventory-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 20px;
          font-size: 12px;
        }
        .inventory-table th {
          background-color: #f0fdf4;
          color: #065f46;
          font-weight: bold;
          text-align: left;
          padding: 10px;
          border: 1px solid #d1d5db;
        }
        .inventory-table td {
          padding: 8px 10px;
          border: 1px solid #d1d5db;
          vertical-align: top;
        }
        .inventory-table tr:nth-child(even) {
          background-color: #f9fafb;
        }
        .status-serviceable { color: #10B981; font-weight: bold; }
        .status-non-serviceable { color: #EF4444; font-weight: bold; }
        .status-maintenance { color: #F59E0B; font-weight: bold; }
        .status-unknown { color: #6B7280; font-weight: bold; }
        .print-button {
          background-color: #10B981;
          color: white;
          border: none;
          padding: 10px 20px;
          border-radius: 4px;
          cursor: pointer;
          font-size: 14px;
          margin-top: 20px;
        }
        .print-button:hover {
          background-color: #059669;
        }
        @media print {
          .print-button { display: none; }
          body { padding: 0; margin: 0; }
          @page { margin: 1.5cm; }
        }
      </style>
    </head>
    <body>
      <div class="report-header">
        <h1 class="report-title">Serviceable Items Report</h1>
        <p class="report-subtitle">IrrigTrack System</p>
        <div class="report-meta">
          <div>Generated on: ${dateFormatted} at ${timeFormatted}</div>
          <div>Total Items: ${filteredItems.value.length}</div>
          ${selectedStatus.value !== 'all' ? `<div>Filter: ${selectedStatus.value}</div>` : ''}
        </div>
      </div>
      
      <div class="summary-cards">
        <div class="summary-card">
          <h3>${statusCounts.value.serviceable}</h3>
          <p>Serviceable</p>
        </div>
        <div class="summary-card">
          <h3>${statusCounts.value['non-serviceable']}</h3>
          <p>Non-Serviceable</p>
        </div>
        <div class="summary-card">
          <h3>${statusCounts.value.maintenance}</h3>
          <p>On Maintenance</p>
        </div>
      </div>
      
      <table class="inventory-table">
        <thead>
          <tr>
            <th>Article</th>
            <th>Category</th>
            <th>Description</th>
            <th>Property Account Code</th>
            <th>Unit Value</th>
            <th>Date Acquired</th>
            <th>Location</th>
            <th>Condition</th>
            <th>Issued To</th>
            <th>Quantity</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div style="text-align: center; margin-top: 30px;">
        <button onclick="window.print(); return false;" class="print-button">Print Report</button>
      </div>
    </body>
    </html>
  `
  
  printWindow.document.open()
  printWindow.document.write(content)
  printWindow.document.close()
}

const goBack = () => {
  router.back()
}
</script>

<template>
  <div class="p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6 bg-white dark:bg-gray-900">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0">
      <div class="flex items-center gap-3">
        <button 
          @click="goBack"
          class="px-4 py-2 bg-green-600 text-white rounded-lg flex items-center hover:bg-green-700 transition-colors"
        >
          <span class="material-icons-outlined mr-2">arrow_back</span>
          Back
        </button>
        <h1 class="text-xl sm:text-2xl font-semibold text-green-700 dark:text-green-400">Serviceable Items Report</h1>
      </div>
      <div class="flex items-center gap-2">
        <button @click="printReport" class="btn-primary flex items-center">
          <span class="material-icons-outlined text-lg mr-1">print</span>
          <span>Print Report</span>
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg p-4 text-center">
        <h3 class="text-2xl font-bold text-green-600 dark:text-green-400">{{ statusCounts.serviceable }}</h3>
        <p class="text-sm text-green-700 dark:text-green-300">Serviceable</p>
      </div>
      <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg p-4 text-center">
        <h3 class="text-2xl font-bold text-red-600 dark:text-red-400">{{ statusCounts['non-serviceable'] }}</h3>
        <p class="text-sm text-red-700 dark:text-red-300">Non-Serviceable</p>
      </div>
      <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 text-center">
        <h3 class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ statusCounts.maintenance }}</h3>
        <p class="text-sm text-yellow-700 dark:text-yellow-300">On Maintenance</p>
      </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-0">
      <div class="relative w-full sm:w-96">
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 dark:text-gray-500">
            <span class="material-icons-outlined text-lg">search</span>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search items..."
            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
          >
        </div>
      </div>
      <div class="flex items-center gap-2">
        <select 
          v-model="selectedStatus"
          class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        >
          <option value="all">All Status</option>
          <option value="serviceable">Serviceable</option>
          <option value="non-serviceable">Non-Serviceable</option>
          <option value="maintenance">On Maintenance</option>
        </select>
      </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
      <!-- Loading indicator -->
      <div v-if="loading" class="flex justify-center items-center py-10">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
      </div>
      
      <!-- Error state -->
      <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
        <span class="material-icons-outlined text-4xl text-red-400 dark:text-red-500">error_outline</span>
        <p class="mt-2 text-red-500 dark:text-red-400">{{ error }}</p>
        <button 
          @click="fetchitems" 
          class="mt-4 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
        >
          Try Again
        </button>
      </div>
      
      <!-- Empty state -->
      <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
        <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500">inventory_2</span>
        <p class="mt-2 text-gray-500 dark:text-gray-400">No items found</p>
        <p v-if="searchQuery || selectedStatus !== 'all'" class="text-sm text-gray-400 dark:text-gray-500">Try adjusting your search or filter</p>
      </div>
      
      <!-- Table with data -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">QR Code</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Image</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Article</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Category</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Description</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Property Account Code</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Unit Value</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Date Acquired</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Location</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Condition</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Issued To</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Quantity</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Serviceable Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-4 py-2">
                <img :src="item.qrCode" alt="QR Code" class="h-8 w-8 object-contain">
              </td>
              <td class="px-4 py-2">
                <img :src="item.image" alt="Item" class="h-8 w-8 object-contain">
              </td>
              <td class="px-4 py-2">
                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.article }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.category }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white max-w-xs truncate" :title="item.description">
                  {{ item.description }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.propertyAccountCode }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.unitValue }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.dateAcquired }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.location }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.condition }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.issuedTo }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.quantity }}</div>
              </td>
              <td class="px-4 py-2">
                <span 
                  :class="{
                    'status-serviceable': item.serviceableStatus === 'serviceable',
                    'status-non-serviceable': item.serviceableStatus === 'non-serviceable',
                    'status-maintenance': item.serviceableStatus === 'maintenance'
                  }"
                  class="text-sm font-medium capitalize"
                >
                  {{ item.serviceableStatus.replace('-', ' ') }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && filteredItems.length > 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-gray-200 gap-3 sm:gap-0">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
          <div class="text-sm text-gray-600">
            Result {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }}
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Items per page:</label>
            <select 
              v-model="itemsPerPage" 
              @change="changeItemsPerPage($event.target.value)"
              class="border rounded px-2 py-1 text-sm"
            >
              <option value="10">10</option>
              <option value="20">20</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-center sm:justify-end gap-1 flex-wrap">
          <button 
            @click="goToPage(1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50"
          >
            First
          </button>
          <button 
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50"
          >
            &lt; Previous
          </button>
          <div class="flex items-center gap-1">
            <template v-for="page in totalPages" :key="page">
              <button 
                v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                @click="goToPage(page)"
                :class="[
                  'px-2 py-1 text-sm border rounded hover:bg-gray-50',
                  currentPage === page ? 'bg-green-50 text-green-600 border-green-500' : ''
                ]"
              >
                {{ page }}
              </button>
              <span 
                v-else-if="page === currentPage - 2 || page === currentPage + 2"
                class="px-2"
              >...</span>
            </template>
          </div>
          <button 
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-2 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50"
          >
            Next &gt;
          </button>
          <button 
            @click="goToPage(totalPages)"
            :disabled="currentPage === totalPages"
            class="px-2 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50"
          >
            Last
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-primary {
  @apply bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 flex items-center text-sm font-medium transition-colors duration-200 shadow-sm hover:shadow;
}

.status-serviceable {
  @apply text-green-600 font-bold;
}

.status-non-serviceable {
  @apply text-red-600 font-bold;
}

.status-maintenance {
  @apply text-yellow-600 font-bold;
}

.status-unknown {
  @apply text-gray-600 font-bold;
}

.material-icons-outlined {
  font-size: 24px;
}
</style>
