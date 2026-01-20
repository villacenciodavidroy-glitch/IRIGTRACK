<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../../composables/useItems'
import useAuth from '../../composables/useAuth'
import axiosClient from '../../axios'
import logoImage from '../../assets/logo.png'

const router = useRouter()
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedStatus = ref('all') // all, serviceable, non-serviceable, maintenance

// Get items from the API using the composable
const { items, fetchitems, loading, error } = useItems()

// Get user data from auth composable
const { getUserDisplayName } = useAuth()

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
// Export to Excel
const performExportToExcel = async () => {
  try {
    // Check if there are items to export
    const itemsToExport = filteredItems.value.length > 0 
      ? filteredItems.value 
      : inventoryItems.value
    
    if (!itemsToExport || itemsToExport.length === 0) {
      alert('No items to export. Please ensure there are items available.')
      return
    }
    
    console.log('Starting Excel export...', { 
      itemCount: itemsToExport.length,
      baseURL: axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    })
    
    // Prepare export parameters
    const params = new URLSearchParams()
    
    // Convert to format expected by backend
    const exportData = itemsToExport.map(item => ({
      unit: item.article || item.unit || '',
      category: item.category || '',
      description: item.description || '',
      pac: item.propertyAccountCode || item.pac || '',
      unit_value: item.unitValue || item.unit_value || '',
      date_acquired: item.dateAcquired || item.date_acquired || '',
      location: item.location || '',
      condition: item.condition || '',
      issued_to: item.issuedTo || item.issued_to || 'Not Assigned',
      quantity: item.quantity || 0,
      // Include serviceable status
      serviceableStatus: item.serviceableStatus || ''
    }))
    
    params.append('items', JSON.stringify(exportData))
    
    // Build the export URL
    // Check if URL would be too long (browsers typically limit to ~2000 chars)
    const itemsParam = params.get('items')
    const itemsLength = itemsParam ? itemsParam.length : 0
    const baseUrl = `/items/export/serviceable-items`
    const fullUrl = `${baseUrl}?${params.toString()}`
    const urlLength = fullUrl.length
    
    const baseURL = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    const fullRequestUrl = baseURL + (baseURL.endsWith('/') ? '' : '/') + baseUrl.replace(/^\//, '')
    
    console.log('Export details:', {
      urlLength,
      itemsParamLength: itemsLength,
      baseURL,
      fullRequestUrl: fullRequestUrl + (urlLength <= 1800 ? `?${params.toString()}` : ''),
      itemCount: itemsToExport.length
    })
    
    let response
    
    // If URL is too long, don't send items - let backend fetch all
    // This is a fallback to avoid network errors from URL length limits
    if (urlLength > 1800) {
      console.warn('URL too long, exporting all items instead of filtered items')
      const exportUrl = baseUrl // No query params
      
      response = await axiosClient.get(exportUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        },
        timeout: 60000,
        maxContentLength: Infinity,
        maxBodyLength: Infinity
      })
    } else {
      const exportUrl = fullUrl
      console.log('Export URL:', exportUrl.substring(0, 200) + (exportUrl.length > 200 ? '...' : ''))
      
      response = await axiosClient.get(exportUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        },
        timeout: 60000,
        maxContentLength: Infinity,
        maxBodyLength: Infinity
      })
    }
    
    console.log('Response received:', {
      status: response.status,
      contentType: response.headers['content-type'],
      size: response.data?.size || 'unknown'
    })
    
    // Check HTTP status code first
    if (response.status !== 200) {
      const text = await response.data.text()
      try {
        const errorData = JSON.parse(text)
        throw new Error(errorData.message || `Export failed with status ${response.status}`)
      } catch (parseError) {
        throw new Error(`Server error (${response.status}): ${text}`)
      }
    }
    
    // Check content type
    const contentType = response.headers['content-type'] || ''
    
    // Check for JSON error response (even with 200 status, might be error)
    if (contentType.includes('application/json')) {
      const text = await response.data.text()
      try {
        const errorData = JSON.parse(text)
        throw new Error(errorData.message || 'Export failed')
      } catch (parseError) {
        throw new Error('Server returned an error: ' + text)
      }
    }
    
    // Verify it's actually an Excel file by checking blob size
    const blobSize = response.data.size || 0
    if (blobSize < 1000 && contentType !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
      const text = await response.data.text()
      throw new Error('Server error: ' + text)
    }
    
    // Create blob URL and trigger download
    const blob = response.data instanceof Blob 
      ? response.data 
      : new Blob([response.data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        })
    const url = window.URL.createObjectURL(blob)
    const downloadLink = document.createElement('a')
    downloadLink.href = url
    downloadLink.download = `Serviceable_Items_${new Date().toISOString().split('T')[0]}.xlsx`
    document.body.appendChild(downloadLink)
    downloadLink.click()
    document.body.removeChild(downloadLink)
    window.URL.revokeObjectURL(url)
    
    console.log('Excel export completed successfully')
  } catch (error) {
    console.error('Error exporting to Excel:', error)
    console.error('Error details:', {
      message: error.message,
      code: error.code,
      response: error.response,
      status: error.response?.status,
      statusText: error.response?.statusText,
      data: error.response?.data,
      request: error.request
    })
    
    let errorMessage = 'Failed to export to Excel. Please try again.'
    
    // Handle network errors specifically
    if (error.code === 'ERR_NETWORK' || error.message === 'Network Error' || !error.response) {
      errorMessage = 'Network Error: Cannot connect to the server. Please check:\n' +
                     '1. The backend server is running\n' +
                     '2. The server URL is correct\n' +
                     '3. There are no firewall issues\n\n' +
                     'Error: ' + (error.message || 'Unknown network error')
    } else if (error.response?.status === 404) {
      errorMessage = 'Export endpoint not found (404). Please check if the server is running and routes are registered.\n' +
                     'Expected route: /api/items/export/serviceable-items'
    } else if (error.response?.status === 500) {
      errorMessage = 'Server error during export (500). Please check the server logs.'
    } else if (error.response?.status === 413) {
      errorMessage = 'Request too large. The export data is too big. Please try filtering the data first.'
    } else if (error.message) {
      errorMessage = error.message
    } else if (error.response?.data) {
      // Try to extract error message from response
      if (typeof error.response.data === 'string') {
        try {
          const errorData = JSON.parse(error.response.data)
          errorMessage = errorData.message || errorMessage
        } catch (e) {
          errorMessage = error.response.data
        }
      } else if (error.response.data.message) {
        errorMessage = error.response.data.message
      }
    }
    
    alert(errorMessage)
  }
}

const exportToExcel = () => {
  openConfirm('Do you want to export serviceable items to Excel?', performExportToExcel)
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
      category: item.category || '',
      description: item.description || '',
      propertyAccountCode: item.propertyAccountCode || '',
      unitValue: item.unitValue || '',
      dateAcquired: item.dateAcquired || '',
      location: item.location || '',
      condition: item.condition || '',
      issuedTo: item.issuedTo || 'Not Assigned',
      quantity: item.quantity || 0,
      serviceableStatus: item.serviceableStatus || ''
    }))
    
    const params = new URLSearchParams()
    params.append('items', JSON.stringify(exportData))
    
    // Use the same pattern as life-cycles-data
    const baseUrl = `/items/export/serviceable-items-pdf`
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
          items: exportData
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
    link.setAttribute('download', `Serviceable_Items_${new Date().toISOString().split('T')[0]}.pdf`)
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
  
  // Use edited signature data
  const preparedByName = signatureData.value.preparedBy.name || getUserDisplayName() || 'Admin User'
  const preparedByTitle = signatureData.value.preparedBy.title || 'Property Officer B'
  const reviewedByName = signatureData.value.reviewedBy.name || 'ANA LIZA C. DINOPOL'
  const reviewedByTitle = signatureData.value.reviewedBy.title || 'Administrative Services Officer A'
  const notedByName = signatureData.value.notedBy.name || 'LARRY C. FRANADA'
  const notedByTitle = signatureData.value.notedBy.title || 'Division Manager A'
  
  const tableRows = filteredItems.value.map(item => `
    <tr>
      <td style="text-align: left; padding: 4px;">${item.article || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.category || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.description || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.propertyAccountCode || 'N/A'}</td>
      <td style="text-align: right; padding: 4px;">${item.unitValue ? parseFloat(item.unitValue).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.dateAcquired ? new Date(item.dateAcquired).toLocaleDateString('en-PH', { month: '2-digit', day: '2-digit', year: '2-digit' }) : 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.location || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.condition || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.issuedTo || 'Not Assigned'}</td>
      <td style="text-align: center; padding: 4px;">${item.quantity || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.serviceableStatus.replace('-', ' ')}</td>
    </tr>
  `).join('')
  
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>SERVICEABLE ITEMS REPORT - ${currentYear}</title>
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
        .serviceable-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          font-size: 10px;
        }
        .serviceable-table th {
          font-weight: bold;
          text-align: left;
          padding: 6px 3px;
          border: 1px solid #000;
          font-size: 9px;
          background-color: #f8f8f8;
        }
        .serviceable-table td {
          padding: 4px 3px;
          border: 1px solid #000;
          vertical-align: top;
          font-size: 9px;
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
        
        <div class="report-title">SERVICEABLE ITEMS REPORT</div>
        <div class="report-year">For the Year ${currentYear}</div>
      </div>
      
      <table class="serviceable-table">
        <thead>
          <tr>
            <th>ARTICLE</th>
            <th>CATEGORY</th>
            <th>DESCRIPTION</th>
            <th>PROPERTY ACCOUNT CODE</th>
            <th>UNIT VALUE</th>
            <th>DATE ACQUIRED</th>
            <th>UNIT/SECTORS</th>
            <th>CONDITION</th>
            <th>ISSUED TO</th>
            <th>QUANTITY</th>
            <th>STATUS</th>
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
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 sm:p-6">
    <div class="max-w-full mx-auto space-y-5">
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
              <h1 class="text-3xl font-extrabold leading-tight">Serviceable Items Report</h1>
              <p class="text-white/90 text-base mt-1">Report on serviceable and non-serviceable equipment status</p>
            </div>
          </div>
          <div class="flex items-center gap-3 flex-wrap">
            <button @click="exportToExcel" class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60">
              <span class="material-icons-outlined text-lg text-emerald-700">download</span>
              <span>Export Excel</span>
            </button>
            <button @click.stop.prevent="exportToPDF" class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60">
              <span class="material-icons-outlined text-lg text-emerald-700">picture_as_pdf</span>
              <span>Export PDF</span>
            </button>
            <button @click="openPrintDialog" class="bg-emerald-50 text-emerald-800 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:bg-white transition-all font-semibold shadow-lg border border-white/60">
              <span class="material-icons-outlined text-lg text-emerald-700">print</span>
              <span>Print Report</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Statistics -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Serviceable</div>
            <div class="p-3 bg-green-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">check_circle</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ statusCounts.serviceable }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Non-Serviceable</div>
            <div class="p-3 bg-red-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">cancel</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ statusCounts['non-serviceable'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">On Maintenance</div>
            <div class="p-3 bg-yellow-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">build_circle</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ statusCounts.maintenance }}</div>
        </div>
      </div>

      <!-- Enhanced Search and Filter Bar -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
          <div class="flex-1 relative">
            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-green-600 dark:text-green-400">
              <span class="material-icons-outlined">search</span>
            </span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search items..."
              class="w-full pl-12 pr-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
            >
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
            >
              <span class="material-icons-outlined text-lg">close</span>
            </button>
          </div>
          <select 
            v-model="selectedStatus"
            class="px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
          >
            <option value="all">All Status</option>
            <option value="serviceable">Serviceable</option>
            <option value="non-serviceable">Non-Serviceable</option>
            <option value="maintenance">On Maintenance</option>
          </select>
        </div>
      </div>

      <!-- Table Container -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Table Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-xl">table_chart</span>
            </div>
            <div>
              <h2 class="text-lg font-bold text-white">Serviceable Items</h2>
              <p class="text-xs text-green-100">{{ filteredItems.length }} item{{ filteredItems.length !== 1 ? 's' : '' }} found</p>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-20">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex flex-col justify-center items-center py-20">
          <span class="material-icons-outlined text-5xl text-red-400 dark:text-red-400 mb-4">error_outline</span>
          <p class="mt-2 text-red-500 dark:text-red-400 text-lg font-semibold mb-4">{{ error }}</p>
          <button 
            @click="fetchitems" 
            class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md hover:shadow-lg transition-all"
          >
            Try Again
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-20">
          <span class="material-icons-outlined text-5xl text-gray-400 dark:text-gray-500 mb-4">inbox</span>
          <p class="mt-2 text-gray-500 dark:text-gray-400 text-lg font-semibold mb-2">No items found</p>
          <p v-if="searchQuery || selectedStatus !== 'all'" class="text-sm text-gray-400 dark:text-gray-500">Try adjusting your search or filter</p>
        </div>

        <!-- Table with data -->
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">QR Code</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Image</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Article</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Category</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Description</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Property Account Code</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Unit Value</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Date Acquired</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Unit/Sections</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Condition</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Issued To</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Quantity</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
              <tr v-for="item in paginatedItems" :key="item.id" class="hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors border-l-4 border-transparent hover:border-green-500 dark:hover:border-green-500">
                <td class="px-4 py-3">
                  <img :src="item.qrCode" alt="QR Code" class="h-12 w-12 object-contain rounded border border-gray-200 dark:border-gray-600">
                </td>
                <td class="px-4 py-3">
                  <img :src="item.image" alt="Item" class="h-12 w-12 object-contain rounded border border-gray-200 dark:border-gray-600">
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.article }}</div>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                    {{ item.category }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-gray-900 dark:text-white max-w-xs truncate" :title="item.description">
                    {{ item.description }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.propertyAccountCode }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-gray-900 dark:text-white">{{ item.unitValue }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-gray-900 dark:text-white">{{ item.dateAcquired }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-gray-900 dark:text-white flex items-center gap-1">
                    <span class="material-icons-outlined text-green-600 dark:text-green-400 text-base">location_on</span>
                    {{ item.location }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-gray-900 dark:text-white">{{ item.condition }}</div>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm text-gray-900 dark:text-white">{{ item.issuedTo }}</div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300">
                    {{ item.quantity }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span 
                    :class="{
                      'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': item.serviceableStatus === 'serviceable',
                      'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300': item.serviceableStatus === 'non-serviceable',
                      'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300': item.serviceableStatus === 'maintenance'
                    }"
                    class="px-3 py-1 rounded-full text-xs font-semibold capitalize"
                  >
                    {{ item.serviceableStatus.replace('-', ' ') }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="!loading && filteredItems.length > 0" class="bg-gradient-to-r from-green-50 to-green-100 dark:from-gray-700 dark:to-gray-700 px-6 py-4 border-t border-green-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
              <div class="text-sm font-medium" style="color: #01200E;">
                Result <span style="color: #01200E; font-weight: bold;">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>-<span style="color: #01200E; font-weight: bold;">{{ Math.min(currentPage * itemsPerPage, filteredItems.length) }}</span> of <span style="color: #01200E; font-weight: bold;">{{ filteredItems.length }}</span>
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-white">Items per page:</label>
                <select 
                  v-model="itemsPerPage" 
                  @change="changeItemsPerPage($event.target.value)"
                  class="border-2 border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20"
                >
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-2 flex-wrap">
              <button 
                @click="goToPage(1)"
                :disabled="currentPage === 1"
                class="px-3 py-1.5 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-white dark:hover:bg-gray-600 hover:border-green-500 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed bg-white dark:bg-gray-700 text-gray-700 dark:text-white font-medium transition-all"
              >
                <span class="material-icons-outlined text-base align-middle">first_page</span>
              </button>
              <button 
                @click="goToPage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="px-3 py-1.5 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-white dark:hover:bg-gray-600 hover:border-green-500 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed bg-white dark:bg-gray-700 text-gray-700 dark:text-white font-medium transition-all"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <div class="flex items-center gap-1">
                <template v-for="page in totalPages" :key="page">
                  <button 
                    v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                    @click="goToPage(page)"
                    :class="[
                      'px-3 py-1.5 text-sm border-2 rounded-lg font-medium transition-all',
                      currentPage === page 
                        ? 'bg-green-600 text-white border-green-600 shadow-md' 
                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-white hover:bg-green-50 dark:hover:bg-green-900/20 hover:border-green-500 dark:hover:border-green-500'
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
                class="px-3 py-1.5 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-white dark:hover:bg-gray-600 hover:border-green-500 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed bg-white dark:bg-gray-700 text-gray-700 dark:text-white font-medium transition-all"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
              <button 
                @click="goToPage(totalPages)"
                :disabled="currentPage === totalPages"
                class="px-3 py-1.5 text-sm border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-white dark:hover:bg-gray-600 hover:border-green-500 dark:hover:border-green-500 disabled:opacity-50 disabled:cursor-not-allowed bg-white dark:bg-gray-700 text-gray-700 dark:text-white font-medium transition-all"
              >
                <span class="material-icons-outlined text-base align-middle">last_page</span>
              </button>
            </div>
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
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-sm p-6 space-y-4">
      <h3 class="text-lg font-semibold text-gray-900">Confirm Export</h3>
      <p class="text-sm text-gray-700">{{ confirmModal.message }}</p>
      <div class="flex justify-end gap-3 pt-2">
        <button
          class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 transition"
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
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-5 py-2.5 rounded-lg hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-md hover:shadow-lg;
}

.btn-secondary-enhanced {
  @apply bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white px-5 py-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-400 dark:hover:border-green-500 flex items-center text-sm font-semibold transition-all duration-300 shadow-sm hover:shadow-md;
}

/* Dark mode support for select options */
select option {
  @apply bg-white dark:bg-gray-700 text-gray-900 dark:text-white;
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
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
}
</style>
