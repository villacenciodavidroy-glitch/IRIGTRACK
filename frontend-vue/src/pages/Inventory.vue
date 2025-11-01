<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../composables/useItems'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'
import { useDebouncedRef } from '../composables/useDebounce'

const router = useRouter()
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const itemsPerPage = ref(8)
const totalItems = ref(0)

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
    actions: ['edit', 'delete'],
    id: item.id, // Keep the original ID for reference
    uuid: item.uuid, // Keep the UUID for API operations
    quantity: item.quantity
  }))
})

// Non-consumable items for the main inventory table
const nonConsumableItems = computed(() => {
  return inventoryItems.value.filter(i => (i.category || '').toLowerCase() !== 'consumables')
})
const filteredItems = computed(() => {
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  if (!query) return nonConsumableItems.value
  
  // Optimize search: only search relevant fields
  return nonConsumableItems.value.filter(item => {
    return (
      (item.article || '').toLowerCase().includes(query) ||
      (item.description || '').toLowerCase().includes(query) ||
      (item.category || '').toLowerCase().includes(query) ||
      (item.propertyAccountCode || '').toLowerCase().includes(query) ||
      (item.location || '').toLowerCase().includes(query)
    )
  })
})

// Update total items based on filtered results
const totalFilteredItems = computed(() => {
  return filteredItems.value.length
})

// Reset to first page when search query changes
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
})

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredItems.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(totalFilteredItems.value / itemsPerPage.value))

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Add method to change items per page
const changeItemsPerPage = (newValue) => {
  itemsPerPage.value = Number(newValue)
  currentPage.value = 1 // Reset to first page when changing items per page
}

// Separate view for Supply category
const consumableItems = computed(() => {
  return inventoryItems.value.filter(i => (i.category || '').toLowerCase() === 'supply')
})

const filteredConsumableItems = computed(() => {
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  if (!query) return consumableItems.value
  
  // Optimize search: only search relevant fields
  return consumableItems.value.filter(item => {
    return (
      (item.article || '').toLowerCase().includes(query) ||
      (item.description || '').toLowerCase().includes(query) ||
      (item.category || '').toLowerCase().includes(query) ||
      (item.propertyAccountCode || '').toLowerCase().includes(query) ||
      (item.location || '').toLowerCase().includes(query)
    )
  })
})

const currentConsumablePage = ref(1)
const itemsPerConsumablePage = ref(8)
const totalConsumableFilteredItems = computed(() => filteredConsumableItems.value.length)
const totalConsumablePages = computed(() => Math.ceil(totalConsumableFilteredItems.value / itemsPerConsumablePage.value) || 1)
const paginatedConsumableItems = computed(() => {
  const start = (currentConsumablePage.value - 1) * itemsPerConsumablePage.value
  const end = start + itemsPerConsumablePage.value
  return filteredConsumableItems.value.slice(start, end)
})
const goToConsumablePage = (page) => {
  if (page >= 1 && page <= totalConsumablePages.value) {
    currentConsumablePage.value = page
  }
}
const changeConsumableItemsPerPage = (newValue) => {
  itemsPerConsumablePage.value = Number(newValue)
  currentConsumablePage.value = 1
}

const goToAddItem = () => {
  router.push('/add-item')
}

const goToAddSupply = () => {
  router.push('/inventory/add').catch(err => {
    console.error('Navigation error:', err)
  })
}

// Handle edit action
const editItem = (item) => {
  // Navigate to edit page using the UUID
  router.push(`/edit-item/${item.uuid}`)
}

// Create a separate loading state for delete operations
const deleteLoading = ref(false)
const itemBeingDeleted = ref(null)

// Show delete confirmation modal
const showDeleteModal = ref(false)
const itemToDelete = ref(null)
const deleteReason = ref('')

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Open delete modal
const openDeleteModal = (item) => {
  itemToDelete.value = item
  deleteReason.value = ''
  showDeleteModal.value = true
}

// Close delete modal
const closeDeleteModal = () => {
  showDeleteModal.value = false
  itemToDelete.value = null
  deleteReason.value = ''
}

// QR Code Preview Modal
const showQrPreviewModal = ref(false)
const selectedQrItem = ref(null)

// Open QR preview modal
const openQrPreviewModal = (item) => {
  selectedQrItem.value = item
  showQrPreviewModal.value = true
}

// Close QR preview modal
const closeQrPreviewModal = () => {
  showQrPreviewModal.value = false
  selectedQrItem.value = null
}

// Print QR code
const printQrCode = () => {
  if (!selectedQrItem.value) return
  
  // Create a new window for printing
  const printWindow = window.open('', '_blank')
  
  // Create the content for the print window
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>QR Code - ${selectedQrItem.value.article || 'Item'}</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          text-align: center;
          padding: 20px;
        }
        .qr-container {
          margin: 20px auto;
          max-width: 400px;
        }
        .qr-code {
          width: 300px;
          height: 300px;
          margin: 0 auto 20px;
        }
        .item-details {
          margin-top: 20px;
          text-align: left;
          border-top: 1px solid #eee;
          padding-top: 20px;
        }
        .item-details p {
          margin: 5px 0;
        }
        @media print {
          button {
            display: none;
          }
        }
      </style>
    </head>
    <body>
      <h1>QR Code</h1>
      <div class="qr-container">
        <img src="${selectedQrItem.value.qrCode}" alt="QR Code" class="qr-code">
        <h2>${selectedQrItem.value.article || 'Item'}</h2>
        <div class="item-details">
          <p><strong>Description:</strong> ${selectedQrItem.value.description || 'N/A'}</p>
          <p><strong>Property Account Code:</strong> ${selectedQrItem.value.propertyAccountCode || 'N/A'}</p>
          <p><strong>Location:</strong> ${selectedQrItem.value.location || 'N/A'}</p>
        </div>
      </div>
      <button onclick="window.print(); return false;">Print</button>
    </body>
    </html>
  `
  
  // Write the content to the new window
  printWindow.document.open()
  printWindow.document.write(content)
  printWindow.document.close()
}

// Print inventory list
const printInventory = () => {
  // Create a new window for printing
  const printWindow = window.open('', '_blank')
  
  // Get current date and time for the report header
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
  
  // Create table rows from the filtered items
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
      <td>${item.quantity || 'Not Assigned'}</td>
    </tr>
  `).join('')
  
  // Create the content for the print window
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Inventory Report - ${dateFormatted}</title>
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
        .inventory-summary {
          margin-top: 20px;
          text-align: right;
          font-size: 14px;
        }
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
        .footer {
          margin-top: 30px;
          text-align: center;
          font-size: 12px;
          color: #6b7280;
          border-top: 1px solid #e5e7eb;
          padding-top: 15px;
        }
        @media print {
          .print-button {
            display: none;
          }
          body {
            padding: 0;
            margin: 0;
          }
          @page {
            margin: 1.5cm;
          }
        }
      </style>
    </head>
    <body>
      <div class="report-header">
        <h1 class="report-title">Inventory Report</h1>
        <p class="report-subtitle">IrrigTrack System</p>
        <div class="report-meta">
          <div>Generated on: ${dateFormatted} at ${timeFormatted}</div>
          <div>Total Items: ${filteredItems.value.length}</div>
          ${searchQuery.value ? `<div>Filter: "${searchQuery.value}"</div>` : ''}
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
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div class="inventory-summary">
        <p><strong>Total Items:</strong> ${filteredItems.value.length}</p>
      </div>
      
      <div style="text-align: center; margin-top: 30px;">
        <button onclick="window.print(); return false;" class="print-button">Print Report</button>
      </div>
      
      <div class="footer">
        <p>This report was generated from the IrrigTrack Inventory Management System.</p>
      </div>
    </body>
    </html>
  `
  
  // Write the content to the new window
  printWindow.document.open()
  printWindow.document.write(content)
  printWindow.document.close()
}

// Handle delete action
const deleteItem = async () => {
  if (!itemToDelete.value) return
  
  try {
    // Set loading state
    deleteLoading.value = true
    itemBeingDeleted.value = itemToDelete.value.id
    
    console.log('Deleting item with UUID:', itemToDelete.value.uuid)
    
    // Call the delete API with deletion reason
    const response = await axiosClient.delete(`/items/delete/${itemToDelete.value.uuid}`, {
      data: {
        deletion_reason: deleteReason.value || 'User initiated deletion'
      }
    })
    
    console.log('Delete response:', response.data)
    
    // Show success message
    successMessage.value = response.data?.message || 'Item deleted successfully'
    successModalType.value = 'success'
    showSuccessModal.value = true
    
    // Refresh the items list
    await fetchitems()
  } catch (error) {
    // Log detailed error information
    console.error('Error deleting item:', error)
    
    // Show error message
    if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to delete item. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    deleteLoading.value = false
    itemBeingDeleted.value = null
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
  <div class="p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0">
      <h1 class="text-xl sm:text-2xl font-semibold text-green-700">Inventory</h1>
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <button @click="goToAddItem" class="btn-primary flex-1 sm:flex-auto justify-center">
          <span class="material-icons-outlined text-lg mr-1">add</span>
          <span>Add New</span>
        </button>
      </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-0">
      <div class="relative w-full sm:w-96">
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
            <span class="material-icons-outlined text-lg">search</span>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search item..."
            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
          >
        </div>
      </div>
      <div class="flex items-center gap-2 w-full sm:w-auto">
        <button class="btn-secondary flex-1 sm:flex-auto justify-center">
          <span class="material-icons-outlined text-lg mr-1">filter_alt</span>
          <span>Filter</span>
        </button>
        <button class="btn-secondary flex-1 sm:flex-auto justify-center">
          <span>Articles</span>
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
      <!-- Mobile View (Card Layout) -->
      <div class="block sm:hidden">
        <!-- Loading indicator -->
        <div v-if="loading" class="flex justify-center items-center py-10">
          <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
        </div>
        
        <!-- Error state -->
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
        
        <!-- Empty state -->
        <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
          <p class="mt-2 text-gray-500">No inventory items found</p>
          <p v-if="searchQuery" class="text-sm text-gray-400">Try adjusting your search query</p>
        </div>
        
        <!-- Card layout for mobile -->
        <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
          <div 
            v-for="item in paginatedItems" 
            :key="item.id || item.propertyAccountCode" 
            class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800"
          >
            <div class="flex items-start gap-3 mb-3">
              <!-- Item image -->
              <div class="flex-shrink-0">
                <img :src="item.image" alt="Item" class="h-14 w-14 object-cover rounded-md border border-gray-200">
              </div>
              
              <!-- Item details -->
              <div class="flex-1 min-w-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ item.article }}</h3>
                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 truncate">{{ item.description }}</p>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ item.category }}</span>
                  <span class="inline-block h-1 w-1 rounded-full bg-gray-400"></span>
                  <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ item.condition }}</span>
                </div>
              </div>
              
              <!-- QR Code -->
              <div 
                class="flex-shrink-0 cursor-pointer transition-all duration-300 hover:scale-110"
                @click="openQrPreviewModal(item)"
              >
                <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain border border-gray-200 rounded-md">
              </div>
            </div>
            
            <!-- Action buttons -->
            <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
              <div class="text-xs text-gray-600 dark:text-gray-300">
                <span class="font-medium">Location:</span> {{ item.location || 'N/A' }}
              </div>
              <div class="flex justify-end gap-2">
                <button 
                  @click="editItem(item)" 
                  class="p-1.5 rounded-md bg-blue-100 text-blue-600 hover:bg-blue-200"
                  title="Edit item"
                >
                  <span class="material-icons-outlined text-sm">edit</span>
                </button>
                <button 
                  @click="openDeleteModal(item)" 
                  class="p-1.5 rounded-md bg-red-100 text-red-600 hover:bg-red-200"
                  title="Delete item"
                  :disabled="deleteLoading && itemBeingDeleted === item.id"
                >
                  <span v-if="!(deleteLoading && itemBeingDeleted === item.id)" class="material-icons-outlined text-sm">delete</span>
                  <span v-else class="material-icons-outlined text-sm animate-spin">refresh</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Desktop View (Table Layout) -->
      <div class="hidden sm:block overflow-x-auto">
        <!-- Loading indicator -->
        <div v-if="loading" class="flex justify-center items-center py-10">
          <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
        </div>
        
        <!-- Error state -->
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
        
        <!-- Empty state -->
        <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
          <p class="mt-2 text-gray-500">No inventory items found</p>
          <p v-if="searchQuery" class="text-sm text-gray-400">Try adjusting your search query</p>
        </div>
        
        <!-- Table with data -->
        <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 whitespace-nowrap">
          <thead>
            <tr class="bg-gray-50 dark:bg-gray-800">
              <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 w-10 px-4 py-3">
                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-green-600 focus:ring-green-500">
              </th>
              <th class="min-w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">QR CODE</th>
              <th class="min-w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">IMAGE</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">ARTICLE</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">CATEGORY</th>
              <th class="min-w-[200px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">DESCRIPTION</th>
               <th class="min-w-[200px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">QUANTITY</th>
              <th class="min-w-[200px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">PROPERTY ACCOUNT CODE</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">UNIT VALUE</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">DATE ACQUIRED</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">P.O. NUMBER</th>
              <th class="min-w-[150px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">LOCATION</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">CONDITION</th>
              <th class="min-w-[150px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">ISSUED TO</th>
              <th class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-800 min-w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">ACTIONS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in paginatedItems" :key="item.id || item.propertyAccountCode" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 px-4 py-2">
                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-green-600 focus:ring-green-500">
              </td>
              <td class="px-4 py-2">
                <div 
                  class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-green-500 rounded-md overflow-hidden inline-block"
                  @click="openQrPreviewModal(item)"
                >
                  <img :src="item.qrCode" alt="QR Code" class="h-8 w-8 object-contain">
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-blue-500 rounded-md overflow-hidden inline-block">
                  <img :src="item.image" alt="Item" class="h-8 w-8 object-contain">
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[120px]" :title="item.article">
                  {{ item.article }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[120px]" :title="item.category">
                  {{ item.category }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[200px]" :title="item.description">
                  {{ item.description }}
                </div>
              </td>
               <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[200px]" :title="item.quantity">
                  {{ item.quantity }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[200px]" :title="item.propertyAccountCode">
                  {{ item.propertyAccountCode }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[120px]" :title="item.unitValue">
                  {{ item.unitValue }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[120px]" :title="item.dateAcquired">
                  {{ item.dateAcquired }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[120px]" :title="item.poNumber">
                  {{ item.poNumber }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[150px]" :title="item.location">
                  {{ item.location }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[120px]" :title="item.condition">
                  {{ item.condition }}
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[150px]" :title="item.issuedTo">
                  {{ item.issuedTo }}
                </div>
              </td>
              <td class="sticky right-0 z-10 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 px-4 py-2">
                <div class="flex justify-center gap-1">
                  <button 
                    @click="editItem(item)" 
                    class="p-1 rounded-md bg-blue-100 text-blue-600 hover:bg-blue-200"
                    title="Edit item"
                  >
                    <span class="material-icons-outlined text-sm">edit</span>
                  </button>
                  <button 
                    @click="openDeleteModal(item)" 
                    class="p-1 rounded-md bg-red-100 text-red-600 hover:bg-red-200"
                    title="Delete item"
                    :disabled="deleteLoading && itemBeingDeleted === item.id"
                  >
                    <span v-if="!(deleteLoading && itemBeingDeleted === item.id)" class="material-icons-outlined text-sm">delete</span>
                    <span v-else class="material-icons-outlined text-sm animate-spin">refresh</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination - only show when not loading and has items -->
      <div v-if="!loading && totalFilteredItems > 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 gap-3 sm:gap-0">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
          <div class="text-sm text-gray-600 dark:text-gray-300">
            Result {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, totalFilteredItems) }} of {{ totalFilteredItems }}
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600 dark:text-gray-300">Items per page:</label>
            <select 
              v-model="itemsPerPage" 
              @change="changeItemsPerPage($event.target.value)"
              class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded px-2 py-1 text-sm"
            >
              <option value="8">8</option>
              <option value="16">16</option>
              <option value="24">24</option>
              <option value="32">32</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-center sm:justify-end gap-1 flex-wrap">
          <button 
            @click="goToPage(1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          >
            First
          </button>
          <button 
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          >
            &lt; Previous
          </button>
          <div class="flex items-center gap-1">
            <template v-for="page in totalPages" :key="page">
              <!-- Show first page, last page, current page, and pages around current -->
              <button 
                v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                @click="goToPage(page)"
                :class="[
                  'px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700',
                  currentPage === page ? 'bg-green-600 text-white border-green-500' : ''
                ]"
              >
                {{ page }}
              </button>
              <!-- Show dots for skipped pages -->
              <span 
                v-else-if="page === currentPage - 2 || page === currentPage + 2"
                class="px-2"
              >...</span>
            </template>
          </div>
          <button 
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          >
            Next &gt;
          </button>
          <button 
            @click="goToPage(totalPages)"
            :disabled="currentPage === totalPages"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
          >
            Last
          </button>
        </div>
      </div>
    </div>

    <!-- Supply Category Table -->
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm mt-6">
      <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Supply</h2>
        <div class="text-sm text-gray-500 dark:text-gray-400">{{ totalConsumableFilteredItems }} items</div>
      </div>

      <div class="hidden sm:block overflow-x-auto">
        <table v-if="paginatedConsumableItems.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 whitespace-nowrap">
          <thead>
            <tr class="bg-gray-50 dark:bg-gray-800">
              <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 w-10 px-4 py-3">
                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-green-600 focus:ring-green-500">
              </th>
              <th class="min-w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">QR CODE</th>
              <th class="min-w-[80px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">IMAGE</th>
              <th class="min-w-[160px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">ARTICLE</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">CATEGORY</th>
              <th class="min-w-[240px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">DESCRIPTION</th>
              <th class="min-w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">QUANTITY</th>
              <th class="min-w-[120px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">UNIT VALUE</th>
              <th class="min-w-[140px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">DATE ACQUIRED</th>
              <th class="min-w-[140px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">LOCATION</th>
              <th class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-800 min-w-[100px] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">ACTIONS</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in paginatedConsumableItems" :key="item.id || item.propertyAccountCode" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 px-4 py-2">
                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700 text-green-600 focus:ring-green-500">
              </td>
              <td class="px-4 py-2">
                <div 
                  class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-green-500 rounded-md overflow-hidden inline-block"
                  @click="openQrPreviewModal(item)"
                >
                  <img :src="item.qrCode" alt="QR Code" class="h-8 w-8 object-contain">
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-blue-500 rounded-md overflow-hidden inline-block">
                  <img :src="item.image" alt="Item" class="h-8 w-8 object-contain">
                </div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[200px]" :title="item.article">{{ item.article }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[140px]" :title="item.category">{{ item.category }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white truncate max-w-[320px]" :title="item.description">{{ item.description }}</div>
              </td>
              <td class="px-4 py-2">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.quantity }}</div>
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
              <td class="sticky right-0 z-10 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 px-4 py-2">
                <div class="flex justify-center gap-1">
                  <button 
                    @click="editItem(item)" 
                    class="p-1 rounded-md bg-blue-100 text-blue-600 hover:bg-blue-200"
                    title="Edit item"
                  >
                    <span class="material-icons-outlined text-sm">edit</span>
                  </button>
                  <button 
                    @click="openDeleteModal(item)" 
                    class="p-1 rounded-md bg-red-100 text-red-600 hover:bg-red-200"
                    title="Delete item"
                    :disabled="deleteLoading && itemBeingDeleted === item.id"
                  >
                    <span v-if="!(deleteLoading && itemBeingDeleted === item.id)" class="material-icons-outlined text-sm">delete</span>
                    <span v-else class="material-icons-outlined text-sm animate-spin">refresh</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
          <p class="mt-2 text-gray-500">No supply items found</p>
        </div>
      </div>

      <!-- Supply Pagination -->
      <div v-if="!loading && totalConsumableFilteredItems > 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 gap-3 sm:gap-0">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
          <div class="text-sm text-gray-600 dark:text-gray-300">
            Result {{ (currentConsumablePage - 1) * itemsPerConsumablePage + 1 }}-{{ Math.min(currentConsumablePage * itemsPerConsumablePage, totalConsumableFilteredItems) }} of {{ totalConsumableFilteredItems }}
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600 dark:text-gray-300">Items per page:</label>
            <select 
              v-model="itemsPerConsumablePage" 
              @change="changeConsumableItemsPerPage($event.target.value)"
              class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded px-2 py-1 text-sm"
            >
              <option value="8">8</option>
              <option value="16">16</option>
              <option value="24">24</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-center sm:justify-end gap-1 flex-wrap">
          <button @click="goToConsumablePage(1)" :disabled="currentConsumablePage === 1" class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">First</button>
          <button @click="goToConsumablePage(currentConsumablePage - 1)" :disabled="currentConsumablePage === 1" class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">&lt; Previous</button>
          <div class="flex items-center gap-1">
            <template v-for="page in totalConsumablePages" :key="page">
              <button 
                v-if="page === 1 || page === totalConsumablePages || (page >= currentConsumablePage - 1 && page <= currentConsumablePage + 1)"
                @click="goToConsumablePage(page)"
                :class="[
                  'px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700',
                  currentConsumablePage === page ? 'bg-green-600 text-white border-green-500' : ''
                ]"
              >
                {{ page }}
              </button>
              <span v-else-if="page === currentConsumablePage - 2 || page === currentConsumablePage + 2" class="px-2">...</span>
            </template>
          </div>
          <button @click="goToConsumablePage(currentConsumablePage + 1)" :disabled="currentConsumablePage === totalConsumablePages" class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">Next &gt;</button>
          <button @click="goToConsumablePage(totalConsumablePages)" :disabled="currentConsumablePage === totalConsumablePages" class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50">Last</button>
        </div>
      </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-4 sm:p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Delete Item</h3>
          <button @click="closeDeleteModal" class="text-gray-400 hover:text-gray-600">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
        
        <p class="mb-4">
          Are you sure you want to delete <span class="font-semibold">{{ itemToDelete?.article }}</span>?
          This item will be moved to the trash and can be viewed in the Deleted Items section.
        </p>
        
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason for deletion (optional)</label>
          <textarea 
            v-model="deleteReason"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500"
            rows="2"
            placeholder="Enter a reason for deleting this item"
          ></textarea>
        </div>
        
        <div class="flex justify-end gap-3 mt-6">
          <button 
            @click="closeDeleteModal"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            :disabled="deleteLoading"
          >
            Cancel
          </button>
          <button 
            @click="deleteItem"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2"
            :disabled="deleteLoading"
          >
            <span v-if="deleteLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            {{ deleteLoading ? 'Deleting...' : 'Delete Item' }}
          </button>
        </div>
      </div>
    </div>
    
    <!-- QR Code Preview Modal -->
    <div v-if="showQrPreviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-4 sm:p-6 relative">
        <!-- Close button (X) in the top-right corner -->
        <button 
          @click="closeQrPreviewModal" 
          class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 bg-white rounded-full p-1 hover:bg-gray-100 transition-colors"
          title="Close"
        >
          <span class="material-icons-outlined">close</span>
        </button>
        
        <div class="flex justify-between items-center mb-4 sm:mb-6">
          <h3 class="text-lg sm:text-xl font-medium text-green-700">QR Code Preview</h3>
        </div>
        
        <div class="flex flex-col md:flex-row items-center md:items-start gap-4 sm:gap-6">
          <!-- Left side: QR Code Image with pulsing border effect -->
          <div class="flex flex-col items-center">
            <div class="border-4 border-green-500 rounded-lg p-2 mb-4 bg-white qr-pulse-border">
              <img 
                :src="selectedQrItem?.qrCode" 
                alt="QR Code" 
                class="w-48 h-48 sm:w-64 sm:h-64 object-contain"
              >
            </div>
            
            <!-- Print Button -->
            <button 
              @click="printQrCode" 
              class="w-full sm:w-auto px-4 sm:px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2 transition-all duration-300 hover:shadow-lg transform hover:-translate-y-1"
            >
              <span class="material-icons-outlined">print</span>
              Print QR Code
            </button>
          </div>
          
          <!-- Right side: Item details and image -->
          <div class="flex-1">
            <!-- Item Title -->
            <h4 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4 text-center md:text-left">{{ selectedQrItem?.article || 'Item' }}</h4>
            
            <!-- Item Image - Always show, use default if not available -->
            <div class="mb-3 sm:mb-4 flex justify-center md:justify-start">
              <img 
                :src="selectedQrItem?.image" 
                alt="Item" 
                class="h-32 sm:h-40 object-contain border rounded-lg shadow-sm"
              >
            </div>
            
            <!-- Item Details -->
            <div class="bg-gray-50 p-3 sm:p-4 rounded-lg">
              <div class="grid grid-cols-2 gap-2">
                <div class="text-xs sm:text-sm text-gray-500">Description:</div>
                <div class="text-xs sm:text-sm font-medium">{{ selectedQrItem?.description || 'N/A' }}</div>
                
                <div class="text-xs sm:text-sm text-gray-500">Property Account Code:</div>
                <div class="text-xs sm:text-sm font-medium">{{ selectedQrItem?.propertyAccountCode || 'N/A' }}</div>
                
                <div class="text-xs sm:text-sm text-gray-500">Location:</div>
                <div class="text-xs sm:text-sm font-medium">{{ selectedQrItem?.location || 'N/A' }}</div>
                
                <div class="text-xs sm:text-sm text-gray-500">Condition:</div>
                <div class="text-xs sm:text-sm font-medium">{{ selectedQrItem?.condition || 'N/A' }}</div>
              </div>
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

<style scoped>
.btn-primary {
  @apply bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 flex items-center text-sm font-medium transition-colors duration-200 shadow-sm hover:shadow;
}

.btn-secondary {
  @apply bg-white text-gray-700 px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center text-sm font-medium transition-colors duration-200;
}

/* Animation for page load */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Modal animations */
@keyframes modalFadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

/* QR Code Modal Styles */
.qr-preview-enter-active,
.qr-preview-leave-active {
  transition: all 0.3s ease;
}

.qr-preview-enter-from,
.qr-preview-leave-to {
  opacity: 0;
  transform: scale(0.9);
}

@keyframes pulse-border {
  0% {
    border-color: #10B981;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
  }
  50% {
    border-color: #34D399;
    box-shadow: 0 0 15px rgba(52, 211, 153, 0.6);
  }
  100% {
    border-color: #10B981;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
  }
}

.qr-pulse-border {
  animation: pulse-border 2s infinite;
}

/* Ensure consistent checkbox styling */
input[type="checkbox"] {
  @apply rounded border-gray-300 text-green-600 focus:ring-green-500;
}

/* Table specific styles */
.overflow-x-auto {
  @apply relative;
  scrollbar-width: thin;
  scrollbar-color: theme('colors.gray.300') theme('colors.gray.100');
}

.overflow-x-auto::-webkit-scrollbar {
  @apply h-2;
}

.overflow-x-auto::-webkit-scrollbar-track {
  @apply bg-gray-100 rounded-full;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  @apply bg-gray-300 rounded-full hover:bg-gray-400;
}

/* Ensure sticky columns work with hover states */
tr:hover td.sticky {
  @apply bg-gray-50;
}

/* Responsive table adjustments */
@media (max-width: 640px) {
  .material-icons-outlined {
    font-size: 18px;
  }
}

/* Hover effects for interactive elements */
button, a {
  @apply transition-all duration-200;
}

button:active, a:active {
  @apply transform scale-95;
}

/* Improved focus states for accessibility */
button:focus, a:focus, input:focus, select:focus, textarea:focus {
  @apply outline-none ring-2 ring-green-500 ring-opacity-50;
}

/* Fix for hamburger menu toggle button */
.menu-button {
  @apply focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50;
}

/* Sidebar toggle animation */
.sidebar-enter-active,
.sidebar-leave-active {
  transition: transform 0.3s ease;
}

.sidebar-enter-from,
.sidebar-leave-to {
  transform: translateX(-100%);
}
</style>