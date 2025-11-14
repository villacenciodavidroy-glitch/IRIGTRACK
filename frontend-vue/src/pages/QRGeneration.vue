<script setup>
import { ref, computed, onMounted, onActivated } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../composables/useItems'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(8)
const totalItems = ref(0)

// Get items from the API using the composable
const { items, fetchitems, loading, error } = useItems()

// Fetch items when component mounts
onMounted(async () => {
  await fetchitems()
})

// Refresh data when component becomes active again (e.g., returning from edit page)
onActivated(async () => {
  // Only refresh if we have items already (to avoid double loading on initial mount)
  if (items.value.length > 0) {
    await fetchitems()
  }
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

const filteredItems = computed(() => {
  const query = searchQuery.value?.toLowerCase().trim()
  let result = inventoryItems.value
  
  if (query) {
    // Optimize search: only search relevant fields
    result = inventoryItems.value.filter(item => {
      return (
        (item.article || '').toLowerCase().includes(query) ||
        (item.description || '').toLowerCase().includes(query) ||
        (item.category || '').toLowerCase().includes(query) ||
        (item.propertyAccountCode || '').toLowerCase().includes(query)
      )
    })
  }
  
  // Sort alphabetically by article
  return result.sort((a, b) => {
    const articleA = (a.article || '').toLowerCase()
    const articleB = (b.article || '').toLowerCase()
    return articleA.localeCompare(articleB)
  })
})

// Update total items based on filtered results
const totalFilteredItems = computed(() => {
  return filteredItems.value.length
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
  router.push(`/edit-item/${item.uuid}`)
}

// Create a separate loading state for delete operations
const deleteLoading = ref(false)
const itemBeingDeleted = ref(null)

// Show delete confirmation modal
const showDeleteModal = ref(false)
const itemToDelete = ref(null)
const deleteReason = ref('')

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

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

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
  const qrItem = selectedQrItem.value || selectedItem.value
  if (!qrItem) return
  
  // Use new QR code if available, otherwise use the original
  const qrCodeToPrint = qrCodeUpdated.value ? newQrCode.value : qrItem.qrCode
  
  // Create a new window for printing
  const printWindow = window.open('', '_blank')
  
  // Create the content for the print window
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>QR Code - ${qrItem.article || 'Item'}</title>
      <style>
        @page {
          size: A4;
          margin: 0;
        }
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        body {
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
          background: white;
          padding: 40px 20px;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
        }
        .print-container {
          width: 100%;
          max-width: 800px;
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 30px;
        }
        .header {
          text-align: center;
          margin-bottom: 20px;
        }
        .header h1 {
          font-size: 36px;
          font-weight: 700;
          color: #1f2937;
          letter-spacing: 1px;
          margin-bottom: 10px;
        }
        .year-badge {
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          padding: 8px 20px;
          border-radius: 20px;
          font-size: 14px;
          font-weight: 600;
          display: inline-block;
          margin-top: 10px;
          box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        .qr-wrapper {
          background: white;
          border: 3px solid #10b981;
          border-radius: 12px;
          padding: 30px;
          box-shadow: 0 4px 20px rgba(16, 185, 129, 0.15);
          display: flex;
          justify-content: center;
          align-items: center;
          margin: 20px 0;
        }
        .qr-code {
          width: 350px;
          height: 350px;
          object-fit: contain;
          display: block;
        }
        .paper-size {
          font-size: 18px;
          font-weight: 600;
          color: #374151;
          margin: 20px 0;
          text-align: center;
        }
        .details-section {
          width: 100%;
          max-width: 600px;
          background: #f9fafb;
          border: 2px solid #e5e7eb;
          border-radius: 8px;
          padding: 30px;
          margin-top: 20px;
        }
        .details-section h2 {
          font-size: 24px;
          font-weight: 600;
          color: #1f2937;
          margin-bottom: 20px;
          text-align: center;
          border-bottom: 2px solid #10b981;
          padding-bottom: 10px;
        }
        .detail-item {
          margin-bottom: 18px;
          padding: 12px;
          background: white;
          border-left: 4px solid #10b981;
          border-radius: 4px;
        }
        .detail-label {
          font-weight: 700;
          color: #374151;
          font-size: 14px;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          margin-bottom: 6px;
        }
        .detail-value {
          color: #1f2937;
          font-size: 16px;
          font-weight: 500;
        }
        .print-button {
          margin-top: 30px;
          padding: 12px 40px;
          background: linear-gradient(135deg, #10b981 0%, #059669 100%);
          color: white;
          border: none;
          border-radius: 8px;
          font-size: 16px;
          font-weight: 600;
          cursor: pointer;
          box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
          transition: all 0.3s ease;
        }
        .print-button:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        @media print {
          body {
            padding: 0;
          }
          .print-button {
            display: none;
          }
          .qr-wrapper {
            box-shadow: none;
          }
          .details-section {
            border: 1px solid #d1d5db;
          }
        }
      </style>
    </head>
    <body>
      <div class="print-container">
        <div class="header">
          <h1>QR Code ${qrCodeUpdated.value ? '(Updated 2025)' : ''}</h1>
          ${qrCodeUpdated.value ? '<div class="year-badge">Updated 2025</div>' : ''}
        </div>
        
        <div class="qr-wrapper">
          <img src="${qrCodeToPrint}" alt="QR Code" class="qr-code">
        </div>
        
        <div class="paper-size">Bondpaper A4 (size)</div>
        
        <button class="print-button" onclick="window.print(); return false;">Print</button>
      </div>
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
      <title>QR Generation Report - ${dateFormatted}</title>
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
        <h1 class="report-title">QR Generation Report</h1>
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
            <th>Unit/Sectors</th>
            <th>Condition</th>
            <th>Issued To</th>
            <th>Quantity</th>
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
        <p>This report was generated from the IrrigTrack QR Generation System.</p>
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


// Validation functionality
const showValidation = ref(false)
const selectedItem = ref(null)
const qrCodeUpdated = ref(false)
const newQrCode = ref('')

const specs = ref({
  article: '',
  category: '',
  quantity: '',
  propertyAccountCode: '',
  unitValue: '',
  dateAcquired: '',
  poNumber: '',
  location: '',
  condition: '',
  issuedTo: ''
})

const openValidation = (item) => {
  selectedItem.value = item
  // Populate specs with actual item data
  specs.value = {
    article: item.article || '',
    category: item.category || '',
    quantity: item.quantity || '',
    propertyAccountCode: item.propertyAccountCode || '',
    unitValue: item.unitValue || '',
    dateAcquired: item.dateAcquired || '',
    poNumber: item.poNumber || '',
    location: item.location || '',
    condition: item.condition || '',
    issuedTo: item.issuedTo || ''
  }
  showValidation.value = true
}

const goBack = () => {
  showValidation.value = false
  selectedItem.value = null
  qrCodeUpdated.value = false
  newQrCode.value = ''
}

// Generate new QR code for 2025
const generateNewQrCode = async () => {
  if (!selectedItem.value) return
  
  try {
    // Show loading state
    const validateButton = document.querySelector('.validate-button')
    if (validateButton) {
      validateButton.disabled = true
      validateButton.textContent = 'Generating...'
    }
    
    // Call the API to validate and generate new QR code
    const response = await axiosClient.post(`/items/${selectedItem.value.uuid}/validate-qr`)
    
    if (response.data.status === 'success' && response.data.data) {
      // Update the QR code with the new image from API
      newQrCode.value = response.data.data.qr_code_image
      qrCodeUpdated.value = true
      
      // Update the selected item with the new QR code
      selectedItem.value.qrCode = response.data.data.qr_code_image
      
      // Show success message
      successMessage.value = 'QR Code successfully updated to 2025!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      
      // Refresh the items list to get the updated QR code
      await fetchitems()
    }
    
  } catch (error) {
    console.error('Error generating new QR code:', error)
    successMessage.value = error.response?.data?.message || 'Failed to generate new QR code. Please try again.'
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    // Reset button state
    const validateButton = document.querySelector('.validate-button')
    if (validateButton) {
      validateButton.disabled = false
      validateButton.textContent = 'VALIDATE'
    }
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

const navigateBack = () => {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- QR Generation List View -->
    <div v-if="!showValidation">
      <!-- Enhanced Header Section -->
      <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl">
        <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
        <div class="relative px-6 py-8 sm:px-8 sm:py-10">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0">
            <div class="flex items-center gap-4">
              <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
                <span class="material-icons-outlined text-4xl text-white">qr_code</span>
              </div>
              <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">QR Generation Management</h1>
                <p class="text-green-100 text-base sm:text-lg">Comprehensive QR code tracking and validation system</p>
              </div>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto flex-wrap">
              <button 
                @click="router.push('/category-management')"
                class="btn-secondary-enhanced flex-1 sm:flex-auto justify-center"
              >
                <span class="material-icons-outlined text-lg mr-1.5">category</span>
                <span>Categories</span>
              </button>
              <button 
                @click="router.push('/location-management')"
                class="btn-secondary-enhanced flex-1 sm:flex-auto justify-center"
              >
                <span class="material-icons-outlined text-lg mr-1.5">location_on</span>
                <span>Unit/Sectors</span>
              </button>
              <button 
                @click="goToAddItem"
                class="btn-primary-enhanced flex-1 sm:flex-auto justify-center shadow-lg"
              >
                <span class="material-icons-outlined text-lg mr-1.5">add_circle</span>
                <span>Add New Item</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <!-- Total Items Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-base font-medium text-gray-600 dark:text-gray-400 mb-1">Total Items</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalFilteredItems }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">inventory</span>
            </div>
          </div>
        </div>

        <!-- Supply Items Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-base font-medium text-gray-600 dark:text-gray-400 mb-1">Supply Items</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ filteredItems.filter(item => item.category?.toLowerCase().includes('supply')).length }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
              <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-2xl">local_shipping</span>
            </div>
          </div>
        </div>

        <!-- Current Page Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-base font-medium text-gray-600 dark:text-gray-400 mb-1">Current Page</p>
              <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ currentPage }} / {{ totalPages || 1 }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
              <span class="material-icons-outlined text-purple-400 dark:text-purple-400 text-2xl">description</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Search Bar -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
          <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-600 text-xl">search</span>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by article, description, category, PAC, or unit/sectors..."
              class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium text-base"
            >
            <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
              <button @click="searchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-700 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons-outlined text-lg">close</span>
              </button>
            </div>
          </div>
          <button 
            @click="printInventory"
            class="btn-print flex items-center justify-center gap-2 px-4 py-3 whitespace-nowrap"
          >
            <span class="material-icons-outlined">print</span>
            <span>Print Report</span>
          </button>
        </div>
      </div>

      <!-- Main Inventory Table Container -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Enhanced Table Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="material-icons-outlined text-white text-2xl">qr_code_scanner</span>
              <h2 class="text-xl font-bold text-white">QR Generation Items</h2>
            </div>
            <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
              <span class="text-base font-semibold text-white">{{ totalFilteredItems }} items</span>
            </div>
          </div>
        </div>
          
        <!-- Mobile View (Card Layout) -->
        <div class="block sm:hidden">
          <!-- Loading indicator -->
          <div v-if="loading" class="flex justify-center items-center py-10">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
          </div>
          
          <!-- Error state -->
          <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
            <span class="material-icons-outlined text-4xl text-red-400 dark:text-red-500">error_outline</span>
            <p class="mt-2 text-base text-red-500 dark:text-red-400">{{ error }}</p>
            <button 
              @click="fetchitems" 
              class="mt-4 px-4 py-2 text-base bg-green-600 text-white rounded-lg hover:bg-green-700"
            >
              Try Again
            </button>
          </div>
          
          <!-- Empty state -->
          <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
            <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500">inventory_2</span>
            <p class="mt-2 text-base text-gray-500 dark:text-gray-400">No inventory items found</p>
            <p v-if="searchQuery" class="text-base text-gray-400 dark:text-gray-500">Try adjusting your search query</p>
          </div>
          
          <!-- Card layout for mobile -->
          <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
            <div 
              v-for="item in paginatedItems" 
              :key="item.id || item.propertyAccountCode" 
              class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              <div class="flex items-start gap-3 mb-3">
                <!-- Item image -->
                <div class="flex-shrink-0">
                  <img :src="item.image" alt="Item" class="h-14 w-14 object-cover rounded-md border border-gray-200 dark:border-gray-600">
                </div>
                
                <!-- Item details -->
                <div class="flex-1 min-w-0">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate">{{ item.article }}</h3>
                  <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate">{{ item.description }}</p>
                  <div class="flex items-center gap-2 mt-1">
                    <span class="text-sm font-medium px-2 py-0.5 rounded-full" style="background-color: #01200E; color: #FFFFFF;">{{ item.category }}</span>
                    <span class="inline-block h-1 w-1 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                    <span class="text-sm font-medium px-2 py-0.5 rounded-full bg-blue-900 dark:bg-blue-900" style="color: #FFFFFF;">{{ item.condition }}</span>
                  </div>
                </div>
                
                <!-- QR Code -->
                <div 
                  class="flex-shrink-0 cursor-pointer transition-all duration-300 hover:scale-110"
                  @click="openQrPreviewModal(item)"
                >
                  <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain border border-gray-200 dark:border-gray-600 rounded-md">
                </div>
              </div>
              
              <!-- Action buttons -->
              <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  <span class="font-medium">Unit/Sectors:</span> {{ item.location || 'N/A' }}
                </div>
                <div class="flex justify-end gap-2">
                  <button 
                    @click="openValidation(item)"
                    class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-base"
                  >
                    Validate
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
            <span class="material-icons-outlined text-4xl text-red-400 dark:text-red-500">error_outline</span>
            <p class="mt-2 text-base text-red-500 dark:text-red-400">{{ error }}</p>
            <button 
              @click="fetchitems" 
              class="mt-4 px-4 py-2 text-base bg-green-600 text-white rounded-lg hover:bg-green-700"
            >
              Try Again
            </button>
          </div>
          
          <!-- Empty state -->
          <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
            <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500">inventory_2</span>
            <p class="mt-2 text-base text-gray-500 dark:text-gray-400">No inventory items found</p>
            <p v-if="searchQuery" class="text-base text-gray-400 dark:text-gray-500">Try adjusting your search query</p>
          </div>
          
          <!-- Table with data -->
          <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 whitespace-nowrap bg-white dark:bg-gray-800">
            <thead>
              <tr class="bg-gradient-to-r from-gray-50 via-gray-100 to-gray-50 dark:from-gray-700 dark:via-gray-800 dark:to-gray-700">
                <th class="sticky left-0 z-10 bg-gray-100 dark:bg-gray-700 w-12 px-4 py-4 border-r border-gray-200 dark:border-gray-600">
                  <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-gray-500 text-green-600 focus:ring-green-500 focus:ring-2 cursor-pointer bg-white dark:bg-gray-600">
                </th>
                <th class="min-w-[90px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">QR CODE</th>
                <th class="min-w-[90px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">IMAGE</th>
                <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">ARTICLE</th>
                <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">CATEGORY</th>
                <th class="min-w-[220px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">DESCRIPTION</th>
                <th class="min-w-[100px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">QUANTITY</th>
                <th class="min-w-[180px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">PROPERTY ACCOUNT CODE</th>
                <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">UNIT VALUE</th>
                <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">DATE ACQUIRED</th>
                <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">P.O. NUMBER</th>
                <th class="min-w-[160px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">UNIT/SECTORS</th>
                <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">CONDITION</th>
                <th class="min-w-[160px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">ISSUED TO</th>
                <th class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 min-w-[120px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="(item, index) in paginatedItems" :key="item.id || item.propertyAccountCode" 
                  :class="[
                    'group transition-all duration-200 border-l-4 border-transparent hover:border-green-500',
                    index % 2 === 0 
                      ? 'bg-white dark:bg-gray-800 hover:bg-gradient-to-r hover:from-green-50 hover:to-transparent dark:hover:from-gray-700 dark:hover:to-transparent' 
                      : 'bg-gray-50 dark:bg-gray-700 hover:bg-gradient-to-r hover:from-green-50 hover:to-transparent dark:hover:from-gray-600 dark:hover:to-transparent'
                  ]">
                <td class="sticky left-0 z-10 px-4 py-3 border-r border-gray-200 dark:border-gray-600 group-hover:bg-green-50 dark:group-hover:bg-gray-700" :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700'">
                  <input type="checkbox" class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 focus:ring-2 cursor-pointer">
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div 
                    class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-green-500 rounded-lg overflow-hidden inline-block p-1 bg-gradient-to-br from-green-50 to-green-100 dark:from-gray-600 dark:to-gray-700"
                    @click="openQrPreviewModal(item)"
                    title="View QR Code"
                  >
                    <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain">
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-blue-500 rounded-lg overflow-hidden inline-block p-1 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-gray-600 dark:to-gray-700">
                    <img :src="item.image" alt="Item" class="h-10 w-10 object-cover rounded">
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base font-semibold text-gray-900 dark:text-white truncate max-w-[130px]" :title="item.article">
                    {{ item.article }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold" style="background-color: #01200E; color: #FFFFFF;">
                    {{ item.category }}
                  </span>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[220px]" :title="item.description">
                    {{ item.description }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-base font-bold bg-purple-900 dark:bg-purple-900" style="color: #FFFFFF;">
                    {{ item.quantity || '0' }}
                  </span>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base font-mono text-gray-700 dark:text-gray-300 truncate max-w-[180px]" :title="item.propertyAccountCode">
                    {{ item.propertyAccountCode }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base font-medium text-gray-700 dark:text-gray-300 truncate max-w-[130px]" :title="item.unitValue">
                    {{ item.unitValue }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base text-gray-600 dark:text-gray-400 truncate max-w-[130px]" :title="item.dateAcquired">
                    {{ item.dateAcquired }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base text-gray-600 dark:text-gray-400 truncate max-w-[130px]" :title="item.poNumber">
                    {{ item.poNumber }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[160px]" :title="item.location">
                    <span class="material-icons-outlined text-base align-middle mr-1 text-gray-400 dark:text-gray-500">location_on</span>
                    {{ item.location }}
                  </div>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold bg-blue-900 dark:bg-blue-900" style="color: #FFFFFF;">
                    {{ item.condition }}
                  </span>
                </td>
                <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-600">
                  <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[160px]" :title="item.issuedTo">
                    {{ item.issuedTo }}
                  </div>
                </td>
                <td class="sticky right-0 z-10 px-4 py-3 group-hover:bg-green-50 dark:group-hover:bg-gray-700" :class="index % 2 === 0 ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700'">
                  <div class="flex justify-center gap-2">
                    <button 
                      @click="openValidation(item)"
                      class="p-2 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200"
                      title="Validate"
                    >
                      <span class="material-icons-outlined text-sm">check_circle</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="!loading && totalFilteredItems > 0" class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 border-t-2 border-gray-200 dark:border-gray-600">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
              <div class="flex items-center gap-2">
                <span class="material-icons-outlined text-lg" style="color: #01200E;">info</span>
                <span class="text-base font-semibold" style="color: #01200E;">
                  Showing <span class="font-bold" style="color: #01200E;">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to 
                  <span class="font-bold" style="color: #01200E;">{{ Math.min(currentPage * itemsPerPage, totalFilteredItems) }}</span> of 
                  <span class="font-bold" style="color: #01200E;">{{ totalFilteredItems }}</span> items
                </span>
              </div>
              <div class="flex items-center gap-2">
                <label class="text-base font-medium text-gray-700 dark:text-gray-300">Items per page:</label>
                <select 
                  v-model="itemsPerPage" 
                  @change="changeItemsPerPage($event.target.value)"
                  class="bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg px-3 py-1.5 text-base font-medium focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm hover:shadow-md transition-shadow"
                >
                  <option value="8">8</option>
                  <option value="16">16</option>
                  <option value="24">24</option>
                  <option value="32">32</option>
                </select>
              </div>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
              <button 
                @click="goToPage(1)"
                :disabled="currentPage === 1"
                class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">first_page</span>
              </button>
              <button 
                @click="goToPage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <div class="flex items-center gap-1">
                <template v-for="page in totalPages" :key="page">
                  <button 
                    v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                    @click="goToPage(page)"
                    :class="[
                      'px-3 py-2 text-base font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                      currentPage === page 
                        ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400'
                    ]"
                  >
                    {{ page }}
                  </button>
                  <span 
                    v-else-if="page === currentPage - 2 || page === currentPage + 2"
                    class="px-2 text-gray-500 dark:text-gray-400"
                  >...</span>
                </template>
              </div>
              <button 
                @click="goToPage(currentPage + 1)"
                :disabled="currentPage === totalPages"
                class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
              <button 
                @click="goToPage(totalPages)"
                :disabled="currentPage === totalPages"
                class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">last_page</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-4 sm:p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Item</h3>
            <button @click="closeDeleteModal" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
          
          <p class="mb-4 text-gray-700 dark:text-gray-300">
            Are you sure you want to delete <span class="font-semibold">{{ itemToDelete?.article }}</span>?
            This item will be moved to the trash and can be viewed in the Deleted Items section.
          </p>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reason for deletion (optional)</label>
            <textarea 
              v-model="deleteReason"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
              rows="2"
              placeholder="Enter a reason for deleting this item"
            ></textarea>
          </div>
          
          <div class="flex justify-end gap-3 mt-6">
            <button 
              @click="closeDeleteModal"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-700"
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
      <div v-if="showQrPreviewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden border-2 border-gray-200">
          <!-- Enhanced Header with Green Bar -->
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5">
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-2xl">qr_code_scanner</span>
                </div>
                <h3 class="text-xl font-bold text-white">QR Code Preview</h3>
              </div>
              <button 
                @click="closeQrPreviewModal" 
                class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors"
                title="Close"
              >
                <span class="material-icons-outlined">close</span>
              </button>
            </div>
          </div>
          
          <!-- Modal Content -->
          <div class="p-6 sm:p-8">
            <div class="flex flex-col lg:flex-row items-center lg:items-start gap-6 sm:gap-8">
              <!-- Left side: QR Code Display -->
              <div class="flex flex-col items-center w-full lg:w-auto">
                <!-- QR Code in White Card with Green Border -->
                <div class="bg-white border-2 border-green-500 rounded-lg p-4 mb-4 shadow-md">
                  <img 
                    :src="selectedQrItem?.qrCode" 
                    alt="QR Code" 
                    class="w-56 h-56 sm:w-64 sm:h-64 object-contain"
                  >
                </div>
                
                <!-- Print Button -->
                <button 
                  @click="printQrCode" 
                  class="w-full sm:w-auto px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2 transition-all duration-300 shadow-md hover:shadow-lg font-semibold"
                >
                  <span class="material-icons-outlined">print</span>
                  Print QR Code
                </button>
              </div>
              
              <!-- Right side: Item Details -->
              <div class="flex-1 w-full lg:w-auto">
                <!-- Item Title -->
                <h4 class="text-2xl font-bold mb-4 text-gray-900">{{ selectedQrItem?.article || 'Item' }}</h4>
                
                <!-- Item Image -->
                <div class="mb-4 flex justify-center lg:justify-start">
                  <img 
                    :src="selectedQrItem?.image" 
                    alt="Item" 
                    class="h-40 sm:h-48 object-contain border border-gray-200 rounded-lg shadow-sm"
                  >
                </div>
                
                <!-- Property Details Card -->
                <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Left Column -->
                    <div class="space-y-3">
                      <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">DESCRIPTION</div>
                        <div class="text-sm font-medium text-gray-900">{{ selectedQrItem?.description || 'N/A' }}</div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">UNIT/SECTORS</div>
                        <div class="text-sm font-medium text-gray-900 flex items-center gap-1">
                          <span class="material-icons-outlined text-green-600 text-base">location_on</span>
                          {{ selectedQrItem?.location || 'N/A' }}
                        </div>
                      </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-3">
                      <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">PROPERTY ACCOUNT CODE</div>
                        <div class="text-sm font-medium text-gray-900">{{ selectedQrItem?.propertyAccountCode || 'N/A' }}</div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">CONDITION</div>
                        <div class="text-sm">
                          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                            {{ selectedQrItem?.condition || 'N/A' }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Validation View -->
    <div v-else class="max-w-7xl mx-auto space-y-6">
      <!-- Enhanced Header Section -->
      <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl">
        <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
        <div class="relative px-6 py-8 sm:px-8 sm:py-10">
          <div class="flex items-center gap-4">
            <button 
              @click="goBack" 
              class="p-2 bg-white/20 backdrop-blur-sm rounded-lg hover:bg-white/30 transition-colors"
              title="Go back"
            >
              <span class="material-icons-outlined text-white text-xl">arrow_back</span>
            </button>
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">qr_code_scanner</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">QR Code Validation</h1>
              <p class="text-green-100 text-sm sm:text-base">{{ selectedItem?.description || 'Item Validation' }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column: Current QR Code (2024) -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">qr_code</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Current QR Code</h2>
                <p class="text-xs text-green-100">Calendar Year 2024</p>
              </div>
            </div>
          </div>
          
          <div class="p-6 space-y-6">
            <!-- QR Code Display -->
            <div class="flex flex-col items-center">
              <div class="bg-white dark:bg-gray-700 border-2 border-green-500 rounded-lg p-4 shadow-md mb-4">
                <img :src="selectedItem?.qrCode" alt="QR Code 2024" class="w-56 h-56 object-contain">
              </div>
              <p class="text-center text-gray-700 dark:text-gray-300 font-semibold text-sm mb-2">Property Account Code</p>
              <p class="text-center text-gray-900 dark:text-white font-bold text-base mb-6">{{ selectedItem?.propertyAccountCode }}</p>
            </div>
            
            <!-- Item Details Section -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
              <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl">inventory_2</span>
                Item Details
              </h3>
              <div class="bg-gradient-to-br from-gray-50 to-green-50/30 dark:from-gray-700 dark:to-gray-800 rounded-lg p-5 space-y-3 border border-gray-200 dark:border-gray-600">
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Article</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.article || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Category</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.category || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Quantity</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.quantity || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Property Account Code</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.propertyAccountCode || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Unit Value</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.unitValue || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Date Acquired</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.dateAcquired || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">P.O. Number</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.poNumber || 'N/A' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Unit/Sectors</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1">
                      <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm">location_on</span>
                      {{ specs.location || 'N/A' }}
                    </div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Condition</div>
                    <div class="text-sm">
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-white">
                        {{ specs.condition || 'N/A' }}
                      </span>
                    </div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Issued To</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ specs.issuedTo || 'N/A' }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
              <button 
                @click="generateNewQrCode"
                class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-3 rounded-lg hover:from-green-700 hover:to-green-800 flex items-center justify-center gap-2 transition-all duration-300 shadow-md hover:shadow-lg font-semibold validate-button disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span class="material-icons-outlined">verified</span>
                Validate & Generate 2025 QR Code
              </button>
            </div>
          </div>
        </div>

        <!-- Right Column: New QR Code (2025) -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">qr_code_2</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">New QR Code</h2>
                <p class="text-xs text-green-100">Calendar Year 2025</p>
              </div>
            </div>
          </div>
          
          <div class="p-6 space-y-6">
            <!-- QR Code Display -->
            <div class="flex flex-col items-center">
              <div class="bg-white dark:bg-gray-700 border-2 border-green-500 rounded-lg p-4 shadow-md mb-4" :class="{ 'qr-pulse-border': qrCodeUpdated }">
                <img 
                  :src="qrCodeUpdated ? newQrCode : selectedItem?.qrCode" 
                  alt="QR Code 2025" 
                  class="w-56 h-56 object-contain"
                >
              </div>
              <p class="text-center text-gray-700 dark:text-gray-300 font-semibold text-sm mb-2">Property Account Code</p>
              <p class="text-center text-gray-900 dark:text-white font-bold text-base mb-6">{{ selectedItem?.propertyAccountCode }}</p>
            </div>

            <!-- Status Messages -->
            <div v-if="qrCodeUpdated" class="space-y-4">
              <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 border-2 border-green-400 dark:border-green-600 rounded-lg p-5">
                <div class="flex items-start gap-3">
                  <div class="p-2 bg-green-500 rounded-full flex-shrink-0">
                    <span class="material-icons-outlined text-white text-xl">check_circle</span>
                  </div>
                  <div>
                    <p class="text-green-800 dark:text-green-300 font-bold text-base mb-1">QR Code Updated Successfully!</p>
                    <p class="text-green-700 dark:text-green-400 text-sm">The QR code has been validated and updated from Calendar Year 2024 to 2025.</p>
                  </div>
                </div>
              </div>
              
              <button 
                @click="printQrCode"
                class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3 rounded-lg hover:from-blue-700 hover:to-blue-800 flex items-center justify-center gap-2 transition-all duration-300 shadow-md hover:shadow-lg font-semibold"
              >
                <span class="material-icons-outlined">print</span>
                Print New QR Code
              </button>
            </div>
            
            <div v-else class="space-y-4">
              <div class="bg-gradient-to-br from-gray-50 to-blue-50/30 dark:from-gray-700 dark:to-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg p-5">
                <div class="flex items-start gap-3">
                  <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-full flex-shrink-0">
                    <span class="material-icons-outlined text-blue-600 dark:text-blue-300 text-xl">info</span>
                  </div>
                  <div>
                    <p class="text-gray-800 dark:text-white font-bold text-base mb-1">Validation Required</p>
                    <p class="text-gray-700 dark:text-gray-300 text-sm">Click the <span class="font-semibold text-green-600 dark:text-green-400">"Validate & Generate 2025 QR Code"</span> button to generate the new QR code for Calendar Year 2025.</p>
                    <p class="text-gray-600 dark:text-gray-400 text-xs mt-2 italic">This will update the QR code from 2024 to 2025.</p>
                  </div>
                </div>
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
/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-4 py-2.5 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-base font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

.btn-secondary-enhanced {
  @apply bg-white text-gray-700 px-4 py-2.5 rounded-xl border-2 border-gray-300 hover:bg-gray-50 hover:border-green-400 flex items-center text-base font-semibold transition-all duration-200 shadow-sm hover:shadow-md;
}

.btn-print {
  @apply bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-lg hover:from-gray-700 hover:to-gray-800 font-semibold shadow-md hover:shadow-lg transition-all duration-200;
}

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

.material-icons-outlined {
  font-size: 20px;
}

.qr-code-container {
  transition: transform 0.2s ease-in-out;
}

.qr-code-container:hover {
  transform: scale(1.1);
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}
</style> 