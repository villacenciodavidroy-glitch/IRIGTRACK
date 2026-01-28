<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../../composables/useItems'
import useFormLabels from '../../composables/useFormLabels'
import useAuth from '../../composables/useAuth'
import axiosClient from '../../axios'
import logoImage from '../../assets/logo.png'

const router = useRouter()
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedLocation = ref('all') // all units/sections
const viewMode = ref('cards') // 'cards' or 'table'
const selectedCategory = ref(null)

// Get items from the API using the composable
const { items, fetchitems, loading, error } = useItems()

// Form labels
const { fetchLabels, getLabel } = useFormLabels()

// Get user data from auth composable
const { user, getUserDisplayName } = useAuth()

// Signature editing state
const showSignatureModal = ref(false)
const signatureData = ref({
  preparedBy: {
    name: '',
    title: 'Property Officer B'
  },
  reviewedBy: {
    name: 'ANA LIZA C. DINOPOL',
    title: 'Administrative Services Officer A'
  },
  notedBy: {
    name: 'LARRY C. FRANADA',
    title: 'Division Manager A'
  }
})

// Fetch items when component mounts
// Force refresh to ensure we get the latest data matching Inventory.vue
onMounted(async () => {
  await fetchLabels()
  // Force a fresh fetch to ensure data matches Inventory.vue
  await fetchitems()
  // Log to verify we're getting the same data structure
  if (process.env.NODE_ENV === 'development' && items.value.length > 0) {
    console.log('Monitoring Assets - Sample item data:', {
      location: items.value[0]?.location,
      issued_to: items.value[0]?.issued_to,
      fullItem: items.value[0]
    })
  }
})

// Map API data to the format expected by the table
// IMPORTANT: This mapping MUST match Inventory.vue EXACTLY to ensure data consistency
// Both pages use the same API endpoint (/items) via useItems() composable
// The API (ItemResource) returns:
// - location: unit/section name as string (from location relationship)
// - issued_to: location.personnel (priority) or user.fullname (fallback) as string
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
    // EXACT match to Inventory.vue line 334
    location: item.location || '',
    condition: item.condition || '',
    // EXACT match to Inventory.vue line 336
    issuedTo: item.issued_to || 'Not Assigned',
    id: item.id,
    uuid: item.uuid,
    quantity: item.quantity,
  }))
})




// Filter items based on search query, condition, and location
const filteredItems = computed(() => {
  let filtered = selectedCategory.value ? categoryItems.value : inventoryItems.value

  // Filter by unit/section
  if (selectedLocation.value !== 'all') {
    filtered = filtered.filter(item => item.location === selectedLocation.value)
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



// Get unique units/sections for filter
const uniqueLocations = computed(() => {
  const locations = [...new Set(inventoryItems.value.map(item => item.location).filter(Boolean))]
  return locations.sort()
})

// Get unique categories for cards
const categoryCards = computed(() => {
  const categories = {}
  
  inventoryItems.value.forEach(item => {
    const category = item.category || 'Uncategorized'
    if (!categories[category]) {
      categories[category] = {
        name: category,
        count: 0,
        items: []
      }
    }
    categories[category].count++
    categories[category].items.push(item)
  })
  
  return Object.values(categories).sort((a, b) => a.name.localeCompare(b.name))
})

// Get items for selected category
const categoryItems = computed(() => {
  if (!selectedCategory.value) return []
  return inventoryItems.value.filter(item => item.category === selectedCategory.value)
})

// Navigation functions
const showCategoryDetails = (categoryName) => {
  selectedCategory.value = categoryName
  viewMode.value = 'table'
  currentPage.value = 1
}

const showAllCategories = () => {
  selectedCategory.value = null
  viewMode.value = 'cards'
  currentPage.value = 1
}

const confirmModal = ref({
  show: false,
  message: '',
  onConfirm: null
})

const openConfirm = (message, action) => {
  confirmModal.value = { show: true, message, onConfirm: action }
}

const confirmCancel = () => {
  confirmModal.value.show = false
  confirmModal.value.onConfirm = null
}

const confirmOk = () => {
  const action = confirmModal.value.onConfirm
  confirmCancel()
  if (typeof action === 'function') action()
}

// Open signature edit modal before printing
const openPrintDialog = () => {
  // Initialize signature data with current user
  const userName = getUserDisplayName() || 'Admin User'
  signatureData.value.preparedBy.name = userName
  signatureData.value.preparedBy.title = 'Property Officer B'
  signatureData.value.reviewedBy.name = 'ANA LIZA C. DINOPOL'
  signatureData.value.reviewedBy.title = 'Administrative Services Officer A'
  signatureData.value.notedBy.name = 'LARRY C. FRANADA'
  signatureData.value.notedBy.title = 'Division Manager A'
  
  showSignatureModal.value = true
}

// Export to PDF using backend DOMPDF
const exportToPDF = async () => {
  try {
    // Ensure modal is closed
    showSignatureModal.value = false
    
    if (!filteredItems.value || filteredItems.value.length === 0) {
      alert('No items to export')
      return
    }
    
    // Prepare export data
    const exportData = filteredItems.value.map(item => ({
      article: item.article || '',
      description: item.description || '',
      propertyAccountCode: item.propertyAccountCode || '',
      unitValue: item.unitValue || '',
      dateAcquired: item.dateAcquired || '',
      poNumber: item.poNumber || '',
      location: item.location || '',
      condition: item.condition || ''
    }))
    
    const params = new URLSearchParams()
    params.append('items', JSON.stringify(exportData))
    if (selectedCategory.value) {
      params.append('category', selectedCategory.value)
    }
    
    // Use the same pattern as life-cycles-data
    const baseUrl = `/items/export/monitoring-assets-pdf`
    const fullUrl = `${baseUrl}?${params.toString()}`
    
    // Log the full URL being requested (for debugging)
    const axiosBaseUrl = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    console.log('Axios baseURL:', axiosBaseUrl)
    console.log('Request path:', baseUrl)
    console.log('Full URL will be:', `${axiosBaseUrl}${fullUrl}`)
    
    let response
    try {
      if (fullUrl.length > 1800) {
        // If URL is too long, send as POST with data in body
        console.log('URL too long, using POST method')
        response = await axiosClient.post(baseUrl, {
          items: exportData,
          category: selectedCategory.value || null
        }, {
          responseType: 'blob',
          headers: {
            'Accept': 'application/pdf'
          },
          timeout: 60000
        })
      } else {
        console.log('Making request to:', fullUrl)
        response = await axiosClient.get(fullUrl, {
          responseType: 'blob',
          headers: {
            'Accept': 'application/pdf'
          },
          timeout: 60000
        })
      }
    } catch (axiosError) {
      // Handle error response that might be JSON instead of blob
      if (axiosError.response && axiosError.response.data) {
        let errorMessage = 'Failed to export to PDF'
        try {
          if (axiosError.response.data instanceof Blob) {
            const text = await axiosError.response.data.text()
            const errorData = JSON.parse(text)
            errorMessage = errorData.message || errorMessage
          } else if (axiosError.response.data && axiosError.response.data.message) {
            errorMessage = axiosError.response.data.message
          }
        } catch (e) {
          console.error('Error parsing error response:', e)
        }
        alert(errorMessage)
        return
      }
      throw axiosError
    }
    
    // Check if response is actually a PDF or an error JSON
    const contentType = response.headers['content-type'] || ''
    if (contentType.includes('application/json')) {
      // It's an error response
      const text = await new Blob([response.data]).text()
      const errorData = JSON.parse(text)
      alert(errorData.message || 'Failed to export PDF')
      return
    }
    
    const blob = new Blob([response.data], {
      type: 'application/pdf'
    })
    
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const categoryName = selectedCategory.value || 'DESKTOP'
    link.setAttribute('download', `Monitoring_Assets_${categoryName}_${new Date().toISOString().split('T')[0]}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting to PDF:', error)
    let errorMessage = 'Failed to export to PDF. Please try again.'
    
    if (error.response) {
      try {
        if (error.response.data instanceof Blob) {
          const text = await error.response.data.text()
          const errorData = JSON.parse(text)
          errorMessage = errorData.message || errorMessage
        } else if (error.response.data && error.response.data.message) {
          errorMessage = error.response.data.message
        }
      } catch (e) {
        // If parsing fails, use default message
      }
    } else if (error.message) {
      errorMessage = error.message
    }
    
    alert(errorMessage)
  } finally {
    showSignatureModal.value = false
  }
}

// Print report with edited signature data
const printReport = () => {
  showSignatureModal.value = false
  
  const printWindow = window.open('', '_blank')
  
  const now = new Date()
  const currentYear = now.getFullYear()
  
  // Get category name for report title
  const categoryName = selectedCategory.value || 'DESKTOP'
  
  // Use edited signature data
  const preparedByName = signatureData.value.preparedBy.name || getUserDisplayName() || 'Admin User'
  const preparedByTitle = signatureData.value.preparedBy.title || 'Property Officer B'
  const reviewedByName = signatureData.value.reviewedBy.name || 'ANA LIZA C. DINOPOL'
  const reviewedByTitle = signatureData.value.reviewedBy.title || 'Administrative Services Officer A'
  const notedByName = signatureData.value.notedBy.name || 'LARRY C. FRANADA'
  const notedByTitle = signatureData.value.notedBy.title || 'Division Manager A'
  
  const tableRows = filteredItems.value.map((item, index) => `
    <tr>
      <td style="text-align: left; padding: 4px;">${item.article || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.description || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.propertyAccountCode || 'N/A'}</td>
      <td style="text-align: right; padding: 4px;">${item.unitValue ? parseFloat(item.unitValue).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.dateAcquired ? new Date(item.dateAcquired).toLocaleDateString('en-PH', { month: '2-digit', day: '2-digit', year: '2-digit' }) : 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.poNumber || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.location || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.condition || 'N/A'}</td>
    </tr>
  `).join('')
  
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>${categoryName.toUpperCase()} MONITORING - ${currentYear}</title>
      <style>
        body {
          font-family: 'Times New Roman', serif;
          margin: 0;
          padding: 20px;
          color: #000;
          font-size: 12px;
          line-height: 1.3;
        }
        .header {
          text-align: center;
          margin-bottom: 25px;
        }
        .org-info {
          margin-bottom: 15px;
        }
        .org-info div {
          margin-bottom: 2px;
          font-weight: bold;
          font-size: 14px;
        }
        .org-info .republic {
          font-size: 15px;
          font-weight: bold;
        }
        .org-info .nia {
          font-size: 15px;
          font-weight: bold;
        }
        .org-info .region {
          font-size: 13px;
          font-weight: normal;
        }
        .logo {
          width: 50px;
          height: 50px;
          margin: 8px auto;
          display: block;
        }
        .report-title {
          font-size: 16px;
          font-weight: bold;
          margin: 12px 0 3px 0;
          text-transform: uppercase;
          letter-spacing: 1px;
        }
        .report-year {
          font-size: 13px;
          font-weight: bold;
          margin-bottom: 15px;
        }
        .monitoring-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          font-size: 10px;
        }
        .monitoring-table th {
          font-weight: bold;
          text-align: left;
          padding: 6px 3px;
          border: 1px solid #000;
          font-size: 9px;
          background-color: #f8f8f8;
        }
        .monitoring-table td {
          padding: 4px 3px;
          border: 1px solid #000;
          vertical-align: top;
          font-size: 9px;
        }
        .monitoring-table .article-col {
          width: 4%;
        }
        .monitoring-table .description-col {
          width: 30%;
        }
        .monitoring-table .code-col {
          width: 12%;
        }
        .monitoring-table .value-col {
          width: 8%;
        }
        .monitoring-table .date-col {
          width: 8%;
        }
        .monitoring-table .po-col {
          width: 10%;
        }
        .monitoring-table .location-col {
          width: 10%;
        }
        .monitoring-table .condition-col {
          width: 8%;
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
        .signature-section {
          margin-top: 40px;
          display: flex;
          justify-content: space-between;
          padding: 20px 0;
        }
        .signature-item {
          text-align: left;
          width: 30%;
        }
        .signature-label {
          font-size: 10px;
          font-weight: bold;
          margin-bottom: 20px;
          color: #000;
        }
        .signature-name {
          font-size: 10px;
          font-weight: bold;
          margin-bottom: 5px;
          text-transform: uppercase;
          color: #000;
        }
        .signature-title {
          font-size: 9px;
          font-weight: normal;
          color: #000;
        }
        @media print {
          .print-button { display: none; }
          body { padding: 0; margin: 0; }
          @page { margin: 1.5cm; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="org-info">
          <div class="republic">Republic of the Philippines</div>
          <div class="nia">National Irrigation Administration</div>
          <div class="region">Region XI</div>
        </div>
        
        <img src="${logoImage}" alt="NIA Logo" class="logo" />
        
        <div class="report-title">${categoryName.toUpperCase()} MONITORING</div>
        <div class="report-year">For the Year ${currentYear}</div>
      </div>
      
      <table class="monitoring-table">
        <thead>
          <tr>
            <th class="article-col">ARTICLE</th>
            <th class="description-col">DESCRIPTION</th>
            <th class="code-col">PROPERTY ACCOUNT CODE</th>
            <th class="value-col">UNIT VALUE</th>
            <th class="date-col">DATE ACQUIRED</th>
            <th class="po-col">P.O. NUMBER</th>
            <th class="location-col">UNIT/SECTIONS</th>
            <th class="condition-col">CONDITION</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div class="signature-section">
        <div class="signature-item">
          <div class="signature-label">Prepared by:</div>
          <div class="signature-name">${preparedByName}</div>
          <div class="signature-title">${preparedByTitle}</div>
        </div>
        <div class="signature-item">
          <div class="signature-label">Reviewed by:</div>
          <div class="signature-name">${reviewedByName}</div>
          <div class="signature-title">${reviewedByTitle}</div>
        </div>
        <div class="signature-item">
          <div class="signature-label">Noted by:</div>
          <div class="signature-name">${notedByName}</div>
          <div class="signature-title">${notedByTitle}</div>
        </div>
      </div>
      
      <div style="text-align: center; margin-top: 30px;">
        <button onclick="window.print(); return false;" class="print-button">Print Report</button>
      </div>
    </body>
    </html>
  `
  
  printWindow.document.open()
  printWindow.document.write(content)
  printWindow.document.close()
  
  // Wait for the document to be fully loaded, then trigger print
  const checkAndPrint = () => {
    if (printWindow.document.readyState === 'complete') {
      printWindow.focus()
      printWindow.print()
    } else {
      printWindow.addEventListener('load', () => {
        printWindow.focus()
        printWindow.print()
      }, { once: true })
      // Fallback timeout
      setTimeout(() => {
        if (!printWindow.closed) {
          printWindow.focus()
          printWindow.print()
        }
      }, 500)
    }
  }
  
  checkAndPrint()
}

const goBack = () => {
  router.back()
}

// Export to Excel
const performExportToExcel = async () => {
  try {
    const params = new URLSearchParams()
    
    if (selectedCategory.value) {
      params.append('category', selectedCategory.value)
    }
    
    if (selectedLocation.value && selectedLocation.value !== 'all') {
      params.append('location', selectedLocation.value)
    }
    
    if (filteredItems.value.length > 0 && filteredItems.value.length !== inventoryItems.value.length) {
      params.append('items', JSON.stringify(filteredItems.value))
    }
    
    const exportUrl = `/items/export/monitoring-assets?${params.toString()}`
    
    const response = await axiosClient.get(exportUrl, {
      responseType: 'blob',
      headers: {
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      }
    })
    
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    const url = window.URL.createObjectURL(blob)
    const downloadLink = document.createElement('a')
    downloadLink.href = url
    downloadLink.download = `Monitoring_Assets_${new Date().toISOString().split('T')[0]}.xlsx`
    document.body.appendChild(downloadLink)
    downloadLink.click()
    document.body.removeChild(downloadLink)
    window.URL.revokeObjectURL(url)
    
    console.log('Excel export completed successfully')
  } catch (error) {
    console.error('Error exporting to Excel:', error)
    alert('Failed to export to Excel. Please try again.')
  }
}

const exportToExcel = () => {
  openConfirm('Do you want to export asset monitoring data to Excel?', performExportToExcel)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-emerald-700 shadow-xl rounded-2xl mt-2">
      <div class="px-6 py-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-4">
          <div class="flex items-center gap-3 pt-1">
            <button 
              @click="goBack" 
              class="p-3 bg-white/15 border border-white/20 text-white rounded-full hover:bg-white/25 transition-all shadow-lg backdrop-blur"
              title="Go back"
            >
              <span class="material-icons-outlined text-xl">arrow_back</span>
            </button>
          </div>
          <div class="text-white">
            <h1 class="text-3xl font-extrabold leading-tight">Asset Monitoring Report</h1>
            <p class="text-white/90 text-base mt-1">Track and monitor asset status and performance</p>
          </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <button 
            @click="exportToExcel" 
            class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60"
          >
            <span class="material-icons-outlined text-lg text-emerald-700">download</span>
            <span>Export Excel</span>
          </button>
          <button 
            @click.stop.prevent="exportToPDF" 
            class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60"
          >
            <span class="material-icons-outlined text-lg text-emerald-700">picture_as_pdf</span>
            <span>Export PDF</span>
          </button>
          <button 
            @click="openPrintDialog" 
            class="bg-emerald-50 text-emerald-800 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:bg-white transition-all font-semibold shadow-lg border border-white/60"
          >
            <span class="material-icons-outlined text-lg text-emerald-700">print</span>
            <span>Print Report</span>
          </button>
        </div>
      </div>
    </div>



    <!-- Enhanced Search and Filter Bar -->
    <div v-if="viewMode === 'table'" class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-100 dark:border-gray-700 p-4">
      <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
            <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl">search</span>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search assets..."
            class="w-full pl-12 pr-4 py-3 bg-gray-50 dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium"
          >
          <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <button @click="searchQuery = ''" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
              <span class="material-icons-outlined text-lg">close</span>
            </button>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <select 
            v-model="selectedLocation"
            class="bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm hover:shadow-md transition-shadow"
          >
            <option value="all">All Unit/Sections</option>
            <option v-for="location in uniqueLocations" :key="location" :value="location">
              {{ location }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Category Cards View -->
    <div v-if="viewMode === 'cards'" class="space-y-6">
      <!-- Enhanced Search Bar for Categories -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-100 dark:border-gray-700 p-4">
        <div class="relative max-w-md">
          <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
            <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl">search</span>
          </div>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search categories..."
            class="w-full pl-12 pr-10 py-3 bg-gray-50 dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium"
          >
          <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
            <button @click="searchQuery = ''" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
              <span class="material-icons-outlined text-lg">close</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Category Cards Grid -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
      </div>
      
      <div v-else-if="error" class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl p-6">
        <span class="material-icons-outlined text-5xl text-red-400 dark:text-red-400 mb-4">error_outline</span>
        <p class="mt-2 text-red-500 dark:text-red-400 text-lg font-semibold mb-4">{{ error }}</p>
        <button 
          @click="fetchitems" 
          class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md hover:shadow-lg transition-all"
        >
          Try Again
        </button>
      </div>
      
      <div v-else-if="categoryCards.length === 0" class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl p-6">
        <span class="material-icons-outlined text-5xl text-gray-400 dark:text-gray-500 mb-4">category</span>
        <p class="mt-2 text-gray-500 dark:text-gray-400 text-lg font-semibold">No categories found</p>
      </div>
      
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div 
          v-for="category in categoryCards.filter(cat => 
            !searchQuery || cat.name.toLowerCase().includes(searchQuery.toLowerCase())
          )" 
          :key="category.name"
          @click="showCategoryDetails(category.name)"
          class="group bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-500 shadow-lg dark:shadow-xl hover:shadow-2xl dark:hover:shadow-2xl transition-all duration-300 cursor-pointer overflow-hidden"
        >
          <!-- Card Header -->
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                  <span class="material-icons-outlined text-white text-xl">category</span>
                </div>
                <h3 class="text-lg font-bold text-white">{{ category.name }}</h3>
              </div>
              <span class="material-icons-outlined text-white group-hover:translate-x-1 transition-transform">chevron_right</span>
            </div>
          </div>
          
          <!-- Card Body -->
          <div class="p-6">
            <div class="space-y-4">
              <div class="flex justify-between items-center p-3 bg-gradient-to-br from-gray-50 to-green-50/30 dark:from-gray-700 dark:to-green-900/20 rounded-lg border border-gray-200 dark:border-gray-600">
                <span class="text-sm font-semibold text-gray-600 dark:text-gray-400">Total Items:</span>
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">{{ category.count }}</span>
              </div>
            </div>
          </div>
          
          <!-- Decorative Element -->
          <div class="h-1 bg-gradient-to-r from-green-600 via-green-500 to-green-600"></div>
        </div>
      </div>
    </div>

    <!-- Enhanced Table Container -->
    <div v-if="viewMode === 'table'" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <!-- Category Details Header -->
      <div v-if="selectedCategory" class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <button 
              @click="showAllCategories"
              class="p-2 bg-white/20 backdrop-blur-sm rounded-lg hover:bg-white/30 transition-colors"
            >
              <span class="material-icons-outlined text-white">arrow_back</span>
            </button>
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-xl">category</span>
            </div>
            <div>
              <h2 class="text-xl font-bold text-white">{{ selectedCategory }} Items</h2>
              <p class="text-xs text-green-100">{{ filteredItems.length }} items found</p>
            </div>
          </div>
          <button 
            @click="showAllCategories"
            class="px-4 py-2 text-sm bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 rounded-lg transition-colors font-semibold"
          >
            View All Categories
          </button>
        </div>
      </div>
      
      <!-- Table Header -->
      <div v-else class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">table_chart</span>
            <h2 class="text-xl font-bold text-white">Asset Details</h2>
          </div>
          <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
            <span class="text-sm font-semibold text-white">{{ filteredItems.length }} items</span>
          </div>
        </div>
      </div>
      <!-- Loading indicator -->
      <div v-if="loading" class="flex justify-center items-center py-10">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
      </div>
      
      <!-- Error state -->
      <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
        <span class="material-icons-outlined text-5xl text-red-400 dark:text-red-400 mb-4">error_outline</span>
        <p class="mt-2 text-red-500 dark:text-red-400 text-lg font-semibold mb-4">{{ error }}</p>
        <button 
          @click="fetchitems" 
          class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md hover:shadow-lg transition-all"
        >
          Try Again
        </button>
      </div>
      
      <!-- Empty state -->
      <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
        <span class="material-icons-outlined text-5xl text-gray-400 dark:text-gray-500 mb-4">monitoring</span>
        <p class="mt-2 text-gray-500 dark:text-gray-400 text-lg font-semibold">
          {{ selectedCategory ? `No items found in ${selectedCategory}` : 'No assets found' }}
        </p>
        <p v-if="searchQuery || selectedLocation !== 'all'" class="text-sm text-gray-400 dark:text-gray-500 mt-2">Try adjusting your search or filters</p>
      </div>
      
      <!-- Enhanced Table with data -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr class="bg-gradient-to-r from-gray-50 via-gray-100 to-gray-50 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">QR CODE</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">IMAGE</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('article', 'ARTICLE') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('category', 'CATEGORY') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('description', 'DESCRIPTION') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('property_account_code', 'P.A.C.') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('unit_value', 'UNIT VALUE') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('date_acquired', 'DATE ACQUIRED') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">{{ getLabel('unit_sections', 'UNIT/SECTIONS') }}</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">CONDITION</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-200 dark:border-gray-600">ISSUED TO</th>
              <th class="px-4 py-4 text-left text-xs font-bold text-gray-700 dark:text-white uppercase tracking-wider">QUANTITY</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in paginatedItems" :key="item.id" 
                class="group hover:bg-gradient-to-r hover:from-green-50 dark:hover:from-green-900/20 hover:to-transparent transition-all duration-200 border-l-4 border-transparent hover:border-green-500 dark:hover:border-green-500">
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain rounded-lg border border-gray-200 dark:border-gray-600">
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <img :src="item.image" alt="Item" class="h-10 w-10 object-cover rounded-lg border border-gray-200 dark:border-gray-600">
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.article }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                  {{ item.category }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate" :title="item.description">
                  {{ item.description }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ item.propertyAccountCode }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ item.unitValue }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ item.dateAcquired }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-700 dark:text-gray-300 flex items-center gap-1">
                  <span class="material-icons-outlined text-sm text-gray-400 dark:text-gray-500">location_on</span>
                  {{ item.location }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                  {{ item.condition }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-700 dark:text-gray-300">{{ item.issuedTo }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-sm font-bold bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300">
                  {{ item.quantity || '0' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && filteredItems.length > 0" class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-700 border-t-2 border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-lg text-gray-700 dark:text-gray-300">info</span>
              <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                Showing <span class="font-bold text-gray-900 dark:text-white">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to 
                <span class="font-bold text-gray-900 dark:text-white">{{ Math.min(currentPage * itemsPerPage, filteredItems.length) }}</span> of 
                <span class="font-bold text-gray-900 dark:text-white">{{ filteredItems.length }}</span> items
              </span>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-sm font-medium text-gray-700 dark:text-white">Items per page:</label>
              <select 
                v-model="itemsPerPage" 
                @change="changeItemsPerPage($event.target.value)"
                class="bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg px-3 py-1.5 text-sm font-medium focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm hover:shadow-md transition-shadow"
              >
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
          </div>
          <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
            <button 
              @click="goToPage(1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">first_page</span>
            </button>
            <button 
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
              <template v-for="page in totalPages" :key="page">
                <button 
                  v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                  @click="goToPage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                    currentPage === page 
                      ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                      : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 dark:hover:border-green-500'
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
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_right</span>
            </button>
            <button 
              @click="goToPage(totalPages)"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">last_page</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Signature Edit Modal -->
    <div 
      v-if="showSignatureModal" 
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click.self="showSignatureModal = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 rounded-t-xl">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">Edit Signature Information</h3>
            <button 
              @click="showSignatureModal = false"
              class="text-white hover:bg-white/20 rounded-lg p-2 transition-colors"
            >
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6 space-y-6">
          <!-- Prepared By Section -->
          <div class="space-y-3">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Prepared by:
            </label>
            <input
              v-model="signatureData.preparedBy.name"
              type="text"
              placeholder="Enter name"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            />
            <input
              v-model="signatureData.preparedBy.title"
              type="text"
              placeholder="Enter title/position"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            />
          </div>

          <!-- Reviewed By Section -->
          <div class="space-y-3">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Reviewed by:
            </label>
            <input
              v-model="signatureData.reviewedBy.name"
              type="text"
              placeholder="Enter name"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            />
            <input
              v-model="signatureData.reviewedBy.title"
              type="text"
              placeholder="Enter title/position"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            />
          </div>

          <!-- Noted By Section -->
          <div class="space-y-3">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Noted by:
            </label>
            <input
              v-model="signatureData.notedBy.name"
              type="text"
              placeholder="Enter name"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            />
            <input
              v-model="signatureData.notedBy.title"
              type="text"
              placeholder="Enter title/position"
              class="w-full px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            />
          </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 rounded-b-xl flex justify-end gap-3">
          <button
            @click="showSignatureModal = false"
            class="px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors font-medium"
          >
            Cancel
          </button>
          <button
            @click="printReport"
            class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all font-medium shadow-md hover:shadow-lg"
          >
            Print Report
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirm Modal -->
  <div v-if="confirmModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-full max-w-sm p-6 space-y-4 border border-gray-200 dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Export</h3>
      <p class="text-sm text-gray-700 dark:text-gray-300">{{ confirmModal.message }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button
          class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
          @click="confirmCancel"
        >
          Cancel
        </button>
        <button
          class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 transition shadow"
          @click="confirmOk"
        >
          Continue
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-4 py-2.5 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

.btn-secondary-enhanced {
  @apply bg-white dark:bg-gray-700 text-gray-700 dark:text-white px-4 py-2.5 rounded-xl border-2 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-green-400 dark:hover:border-green-500 flex items-center text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow-md;
}

.btn-primary {
  @apply bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 flex items-center text-sm font-medium transition-colors duration-200 shadow-sm hover:shadow;
}

.material-icons-outlined {
  font-size: 24px;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Dark mode support for select options */
select option {
  @apply bg-white dark:bg-gray-700 text-gray-900 dark:text-white;
}
</style>
