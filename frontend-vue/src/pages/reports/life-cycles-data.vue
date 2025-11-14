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
const selectedCategory = ref('all')
const selectedStatus = ref('all')
const sortingField = ref('remainingLifespan')
const sortingOrder = ref('asc')

// Flask API base URL
const PY_API_BASE = import.meta.env.VITE_PY_API_BASE_URL || 'http://127.0.0.1:5000'

// Get items from the API
const { items, fetchitems, loading, error } = useItems()
const { getUserDisplayName } = useAuth()

// Lifespan predictions state
const lifespanPredictions = ref([])
const apiLoading = ref(false)
const apiError = ref(null)

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

// Category helper
const isConsumableCategory = (category) => {
  const c = (category || '').toLowerCase()
  return c === 'supply' || 
         c.includes('supply') || 
         c === 'consumables' || 
         c.includes('consumable')
}

// Fetch lifespan predictions - use stored database values first, fallback to API if needed
const fetchLifespanPredictions = async () => {
  apiLoading.value = true
  apiError.value = null
  
  try {
    // Get all non-consumable items
    const nonConsumableItems = items.value.filter(item => !isConsumableCategory(item?.category))
    
    if (nonConsumableItems.length === 0) {
      lifespanPredictions.value = []
      return
    }
    
    // First, try to use stored values from database (remaining_years, lifespan_estimate)
    // These are updated by CalculateLifespanJob periodically
    const predictions = nonConsumableItems
      .filter(item => item.remaining_years != null && !isNaN(item.remaining_years))
      .map(item => ({
        item_id: item.id,
        remaining_years: parseFloat(item.remaining_years),
        lifespan_estimate: item.lifespan_estimate != null ? parseFloat(item.lifespan_estimate) : null
      }))
    
    // If we have predictions from database, use them
    if (predictions.length > 0) {
      lifespanPredictions.value = predictions
      apiLoading.value = false
      return
    }
    
    // Fallback: Call Python API if no stored values
    const payload = {
      items: nonConsumableItems.map(item => {
        const acquisitionDate = new Date(item.date_acquired)
        const today = new Date()
        const daysSinceAcquisition = Math.floor((today - acquisitionDate) / (1000 * 60 * 60 * 24))
        const yearsInUse = daysSinceAcquisition / 365.25
        
        const maintenanceCount = item.maintenance_records?.length || 0
        
        let conditionNumber = 0
        if (item.condition_number?.condition_number) {
          const match = String(item.condition_number.condition_number).match(/\d+/)
          if (match) conditionNumber = parseInt(match[0])
        }
        
        let lastReason = item.maintenance_reason || ''
        if (!lastReason && item.maintenance_records && item.maintenance_records.length > 0) {
          const sorted = [...item.maintenance_records].sort((a, b) => 
            new Date(b.maintenance_date) - new Date(a.maintenance_date)
          )
          lastReason = sorted[0]?.reason || sorted[0]?.technician_notes || ''
        }
        
        return {
          item_id: item.id,
          category: item.category || 'Unknown',
          years_in_use: Math.max(0, yearsInUse),
          maintenance_count: maintenanceCount,
          condition_number: conditionNumber,
          last_reason: lastReason
        }
      })
    }
    
    const response = await axiosClient.post(`${PY_API_BASE}/predict/items/lifespan`, payload)
    
    if (response.data && response.data.success && response.data.predictions) {
      lifespanPredictions.value = response.data.predictions
    } else {
      throw new Error('Invalid response from prediction API')
    }
  } catch (err) {
    console.error('Error fetching lifespan predictions:', err)
    apiError.value = err.message || 'Failed to fetch lifespan predictions'
    lifespanPredictions.value = []
  } finally {
    apiLoading.value = false
  }
}

// Compute all items with lifespan data
const allItemsLifespan = computed(() => {
  if (!items.value || items.value.length === 0) return []
  if (!lifespanPredictions.value || lifespanPredictions.value.length === 0) return []
  
  return items.value
    .filter(item => !isConsumableCategory(item?.category))
    .map(item => {
      const catboostPred = lifespanPredictions.value.find(p => p.item_id === item.id)
      
      if (!catboostPred || catboostPred.remaining_years == null || isNaN(catboostPred.remaining_years)) {
        return null
      }
      
      const acquisitionDate = new Date(item.date_acquired)
      const today = new Date()
      const daysSinceAcquisition = Math.floor((today - acquisitionDate) / (1000 * 60 * 60 * 24))
      const yearsInUse = daysSinceAcquisition / 365.25
      
      const remainingYears = parseFloat(catboostPred.remaining_years)
      const remainingLifespanDays = Math.round(remainingYears * 365)
      
      let expectedLifespan
      if (catboostPred.lifespan_estimate != null && !isNaN(catboostPred.lifespan_estimate)) {
        expectedLifespan = Math.round(catboostPred.lifespan_estimate * 365)
      } else {
        expectedLifespan = Math.round((remainingYears + yearsInUse) * 365)
      }
      
      const lifespanEndDate = new Date(today.getTime() + (remainingYears * 365.25 * 24 * 60 * 60 * 1000))
      
      let statusClass = 'bg-green-500'
      let statusText = 'GOOD'
      let recommendation = 'Good condition'
      
      if (remainingYears <= 0.082) {
        statusClass = 'bg-red-500'
        statusText = 'DISPOSE'
        recommendation = 'URGENT: Dispose immediately'
      } else if (remainingYears <= 0.164) {
        statusClass = 'bg-orange-500'
        statusText = 'SOON'
        recommendation = 'Plan replacement soon'
      } else if (remainingYears <= 0.5) {
        statusClass = 'bg-yellow-500'
        statusText = 'MONITOR'
        recommendation = 'Monitor closely'
      }
      
      return {
        id: item.id,
        uuid: item.uuid,
        name: item.unit || 'Unknown Item',
        description: item.description || '',
        category: item.category || 'Unknown',
        pac: item.pac || '',
        unitValue: item.unit_value || 0,
        location: item.location || 'Unknown',
        condition: item.condition || 'Unknown',
        acquisitionDate: acquisitionDate.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' }),
        daysSinceAcquisition,
        yearsInUse: parseFloat(yearsInUse.toFixed(2)),
        expectedLifespan,
        expectedLifespanYears: parseFloat((expectedLifespan / 365.25).toFixed(2)),
        remainingLifespan: remainingLifespanDays,
        remainingYears: parseFloat(remainingYears.toFixed(2)),
        lifespanEndDate: lifespanEndDate.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' }),
        statusClass,
        statusText,
        recommendation,
        maintenanceCount: item.maintenance_records?.length || 0,
        confidence: 94
      }
    })
    .filter(item => item !== null)
})

// Filter and sort items
const filteredItems = computed(() => {
  let filtered = [...allItemsLifespan.value]
  
  // Filter by category
  if (selectedCategory.value !== 'all') {
    filtered = filtered.filter(item => 
      item.category.toLowerCase() === selectedCategory.value.toLowerCase()
    )
  }
  
  // Filter by status
  if (selectedStatus.value !== 'all') {
    filtered = filtered.filter(item => {
      if (selectedStatus.value === 'urgent') return item.statusText === 'DISPOSE'
      if (selectedStatus.value === 'soon') return item.statusText === 'SOON'
      if (selectedStatus.value === 'monitor') return item.statusText === 'MONITOR'
      if (selectedStatus.value === 'good') return item.statusText === 'GOOD'
      return true
    })
  }
  
  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(item =>
      item.name.toLowerCase().includes(query) ||
      item.description.toLowerCase().includes(query) ||
      item.pac.toLowerCase().includes(query) ||
      item.category.toLowerCase().includes(query) ||
      item.location.toLowerCase().includes(query)
    )
  }
  
  // Sort
  filtered.sort((a, b) => {
    let aVal = a[sortingField.value]
    let bVal = b[sortingField.value]
    
    if (typeof aVal === 'string') {
      aVal = aVal.toLowerCase()
      bVal = bVal.toLowerCase()
    }
    
    if (sortingOrder.value === 'asc') {
      return aVal > bVal ? 1 : aVal < bVal ? -1 : 0
    } else {
      return aVal < bVal ? 1 : aVal > bVal ? -1 : 0
    }
  })
  
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

// Summary statistics
const summaryStats = computed(() => {
  const items = filteredItems.value
  const total = items.length
  const urgent = items.filter(i => i.statusText === 'DISPOSE').length
  const soon = items.filter(i => i.statusText === 'SOON').length
  const monitor = items.filter(i => i.statusText === 'MONITOR').length
  const good = items.filter(i => i.statusText === 'GOOD').length
  
  const avgRemainingYears = total > 0
    ? items.reduce((sum, i) => sum + i.remainingYears, 0) / total
    : 0
  
  const avgExpectedLifespan = total > 0
    ? items.reduce((sum, i) => sum + i.expectedLifespanYears, 0) / total
    : 0
  
  return {
    total,
    urgent,
    soon,
    monitor,
    good,
    avgRemainingYears: parseFloat(avgRemainingYears.toFixed(2)),
    avgExpectedLifespan: parseFloat(avgExpectedLifespan.toFixed(2))
  }
})

// Get unique categories
const uniqueCategories = computed(() => {
  const cats = [...new Set(allItemsLifespan.value.map(item => item.category))]
  return cats.sort()
})

// Export functions
const exportToExcel = async () => {
  try {
    // Check if there are items to export
    if (!filteredItems.value || filteredItems.value.length === 0) {
      alert('No items to export. Please ensure there are items with lifespan data.')
      return
    }
    
    console.log('Starting Excel export...', { 
      itemCount: filteredItems.value.length,
      baseURL: axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    })
    
    // Prepare export parameters
    const params = new URLSearchParams()
    
    // Convert filtered items to format expected by backend
    const exportData = filteredItems.value.map(item => ({
      unit: item.name || '',
      description: item.description || '',
      category: item.category || '',
      pac: item.pac || '',
      unit_value: item.unitValue || '',
      date_acquired: item.acquisitionDate || '',
      location: item.location || '',
      condition: item.condition || '',
      years_in_use: item.yearsInUse || 0,
      expected_lifespan_years: item.expectedLifespanYears || 0,
      remaining_years: item.remainingYears || 0,
      remaining_days: item.remainingLifespan || 0,
      lifespan_end_date: item.lifespanEndDate || '',
      status: item.statusText || '',
      maintenance_count: item.maintenanceCount || 0
    }))
    
    params.append('items', JSON.stringify(exportData))
    
    // Build the export URL
    // Check if URL would be too long (browsers typically limit to ~2000 chars)
    const itemsParam = params.get('items')
    const itemsLength = itemsParam ? itemsParam.length : 0
    const baseUrl = `/items/export/life-cycles-data`
    const fullUrl = `${baseUrl}?${params.toString()}`
    const urlLength = fullUrl.length
    
    const baseURL = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
    const fullRequestUrl = baseURL + (baseURL.endsWith('/') ? '' : '/') + baseUrl.replace(/^\//, '')
    
    console.log('Export details:', {
      urlLength,
      itemsParamLength: itemsLength,
      baseURL,
      fullRequestUrl: fullRequestUrl + (urlLength <= 1800 ? `?${params.toString()}` : ''),
      itemCount: filteredItems.value.length
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
      // If not 200, try to read error message from blob
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
    // Excel files are typically larger than error messages (>1KB)
    const blobSize = response.data.size || 0
    if (blobSize < 1000 && contentType !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
      // Small blob might be an error - read it
      const text = await response.data.text()
      throw new Error('Server error: ' + text)
    }
    
    // Create blob URL and trigger download
    // Use the response data directly (it's already a blob)
    const blob = response.data instanceof Blob 
      ? response.data 
      : new Blob([response.data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        })
    const url = window.URL.createObjectURL(blob)
    const downloadLink = document.createElement('a')
    downloadLink.href = url
    downloadLink.download = `Life_Cycles_Data_${new Date().toISOString().split('T')[0]}.xlsx`
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
                     'Expected route: /api/items/export/life-cycles-data'
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
      <td style="text-align: left; padding: 4px;">${item.name || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.category || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${item.pac || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.acquisitionDate || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.yearsInUse || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.expectedLifespanYears || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.remainingYears || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.remainingLifespan || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.lifespanEndDate || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.statusText || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${item.maintenanceCount || 0}</td>
    </tr>
  `).join('')
  
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>LIFE CYCLES DATA REPORT - ${currentYear}</title>
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
        }
        .report-year {
          font-size: 13px;
          font-weight: bold;
          margin-bottom: 15px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          font-size: 10px;
        }
        table th {
          font-weight: bold;
          text-align: left;
          padding: 6px 3px;
          border: 1px solid #000;
          font-size: 9px;
          background-color: #f8f8f8;
        }
        table td {
          padding: 4px 3px;
          border: 1px solid #000;
          vertical-align: top;
          font-size: 9px;
        }
        .summary {
          margin-top: 20px;
          font-size: 11px;
        }
        @media print {
          body { padding: 0; margin: 0; }
          @page { margin: 1.5cm; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="org-info">
          <div>Republic of the Philippines</div>
          <div>National Irrigation Administration</div>
          <div>Region XI</div>
        </div>
        <img src="${logoImage}" alt="NIA Logo" class="logo" />
        <div class="report-title">LIFE CYCLES DATA REPORT</div>
        <div class="report-year">For the Year ${currentYear}</div>
      </div>
      
      <div class="summary">
        <p><strong>Total Items:</strong> ${summaryStats.value.total}</p>
        <p><strong>Average Remaining Lifespan:</strong> ${summaryStats.value.avgRemainingYears} years</p>
        <p><strong>Average Expected Lifespan:</strong> ${summaryStats.value.avgExpectedLifespan} years</p>
        <p><strong>Status Breakdown:</strong> DISPOSE: ${summaryStats.value.urgent}, SOON: ${summaryStats.value.soon}, MONITOR: ${summaryStats.value.monitor}, GOOD: ${summaryStats.value.good}</p>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>Item Name</th>
            <th>Category</th>
            <th>PAC</th>
            <th>Date Acquired</th>
            <th>Years In Use</th>
            <th>Expected Lifespan (Years)</th>
            <th>Remaining (Years)</th>
            <th>Remaining (Days)</th>
            <th>End Date</th>
            <th>Status</th>
            <th>Maintenance Count</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div style="margin-top: 40px; display: flex; justify-content: space-between; padding: 20px 0;">
        <div>
          <div style="font-size: 10px; font-weight: bold; margin-bottom: 20px; color: #000;">Prepared by:</div>
          <div style="font-size: 10px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; color: #000;">${preparedByName}</div>
          <div style="font-size: 9px; color: #000;">${preparedByTitle}</div>
        </div>
        <div>
          <div style="font-size: 10px; font-weight: bold; margin-bottom: 20px; color: #000;">Reviewed by:</div>
          <div style="font-size: 10px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; color: #000;">${reviewedByName}</div>
          <div style="font-size: 9px; color: #000;">${reviewedByTitle}</div>
        </div>
        <div>
          <div style="font-size: 10px; font-weight: bold; margin-bottom: 20px; color: #000;">Noted by:</div>
          <div style="font-size: 10px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; color: #000;">${notedByName}</div>
          <div style="font-size: 9px; color: #000;">${notedByTitle}</div>
        </div>
      </div>
    </body>
    </html>
  `
  
  printWindow.document.open()
  printWindow.document.write(content)
  printWindow.document.close()
}

const changeSorting = (field) => {
  if (sortingField.value === field) {
    sortingOrder.value = sortingOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortingField.value = field
    sortingOrder.value = 'asc'
  }
  currentPage.value = 1
}

// Initialize data
onMounted(async () => {
  await fetchitems()
  await fetchLifespanPredictions()
})

const goBack = () => {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6">
    <div class="max-w-full mx-auto space-y-5">
      <!-- Enhanced Header Section -->
      <div class="bg-green-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-5 md:px-8 md:py-6 flex flex-wrap items-center gap-4">
          <button 
            @click="goBack" 
            class="p-2.5 bg-white rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-green-600 text-xl md:text-2xl">arrow_back</span>
          </button>
          <div class="p-3 bg-white rounded-lg flex-shrink-0 shadow-md">
            <span class="material-icons-outlined text-green-600 text-2xl md:text-3xl">show_chart</span>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-1">Life Cycles Data Report</h1>
            <p class="text-green-100 text-base md:text-lg">View asset lifecycle information and history</p>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <button @click="exportToExcel" class="btn-secondary-enhanced flex items-center gap-2">
              <span class="material-icons-outlined text-lg">file_download</span>
              <span>Export Excel</span>
            </button>
            <button @click="openPrintDialog" class="btn-primary-enhanced flex items-center gap-2">
              <span class="material-icons-outlined text-lg">print</span>
              <span>Print Report</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Statistics -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Items</div>
            <div class="p-3 bg-blue-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">inventory_2</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ summaryStats.total }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Remaining</div>
            <div class="p-3 bg-green-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">schedule</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ summaryStats.avgRemainingYears }} <span class="text-lg text-gray-600 dark:text-gray-400">years</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Expected</div>
            <div class="p-3 bg-purple-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">event</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ summaryStats.avgExpectedLifespan }} <span class="text-lg text-gray-600 dark:text-gray-400">years</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">DISPOSE Items</div>
            <div class="p-3 bg-red-500 rounded-lg">
              <span class="material-icons-outlined text-white text-xl">warning</span>
            </div>
          </div>
          <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ summaryStats.urgent }}</div>
        </div>
      </div>

      <!-- Status Breakdown -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Status Breakdown</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-700">
            <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ summaryStats.urgent }}</div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-1">DISPOSE</div>
          </div>
          <div class="text-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-700">
            <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ summaryStats.soon }}</div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-1">SOON</div>
          </div>
          <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-700">
            <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ summaryStats.monitor }}</div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-1">MONITOR</div>
          </div>
          <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
            <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ summaryStats.good }}</div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-1">GOOD</div>
          </div>
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
            v-model="selectedCategory"
            class="px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
          >
            <option value="all">All Categories</option>
            <option v-for="cat in uniqueCategories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
          <select 
            v-model="selectedStatus"
            class="px-4 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20 transition-all"
          >
            <option value="all">All Status</option>
            <option value="urgent">DISPOSE</option>
            <option value="soon">SOON</option>
            <option value="monitor">MONITOR</option>
            <option value="good">GOOD</option>
          </select>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading || apiLoading" class="flex justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error || apiError" class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl">
        <span class="material-icons-outlined text-5xl text-red-400 dark:text-red-400 mb-4">error_outline</span>
        <p class="mt-2 text-red-500 dark:text-red-400 text-lg font-semibold">{{ error || apiError }}</p>
      </div>

      <!-- Table Container -->
      <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Table Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-xl">table_chart</span>
            </div>
            <div>
              <h2 class="text-lg font-bold text-white">Life Cycles Data</h2>
              <p class="text-xs text-green-100">{{ filteredItems.length }} item{{ filteredItems.length !== 1 ? 's' : '' }} found</p>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-700">
              <tr>
                <th 
                  @click="changeSorting('name')"
                  class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Item Name
                  <span v-if="sortingField === 'name'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th 
                  @click="changeSorting('category')"
                  class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Category
                  <span v-if="sortingField === 'category'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">PAC</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Location</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Date Acquired</th>
                <th 
                  @click="changeSorting('yearsInUse')"
                  class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Years In Use
                  <span v-if="sortingField === 'yearsInUse'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th 
                  @click="changeSorting('expectedLifespanYears')"
                  class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Expected (Years)
                  <span v-if="sortingField === 'expectedLifespanYears'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th 
                  @click="changeSorting('remainingYears')"
                  class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Remaining (Years)
                  <span v-if="sortingField === 'remainingYears'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th 
                  @click="changeSorting('remainingLifespan')"
                  class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Remaining (Days)
                  <span v-if="sortingField === 'remainingLifespan'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">End Date</th>
                <th 
                  @click="changeSorting('statusText')"
                  class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase cursor-pointer hover:bg-green-50 dark:hover:bg-green-900/20 border-b border-gray-200 dark:border-gray-600"
                >
                  Status
                  <span v-if="sortingField === 'statusText'" class="material-icons-outlined text-xs align-middle">
                    {{ sortingOrder === 'asc' ? 'arrow_upward' : 'arrow_downward' }}
                  </span>
                </th>
                <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 dark:text-white uppercase border-b border-gray-200 dark:border-gray-600">Maintenance</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
              <tr 
                v-for="item in paginatedItems" 
                :key="item.id"
                class="hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors border-l-4 border-transparent hover:border-green-500 dark:hover:border-green-500"
              >
                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ item.category }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ item.pac }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ item.location }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ item.acquisitionDate }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{{ item.yearsInUse }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{{ item.expectedLifespanYears }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{{ item.remainingYears }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{{ item.remainingLifespan }}</td>
                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{{ item.lifespanEndDate }}</td>
                <td class="px-4 py-3 text-center">
                  <span 
                    :class="{
                      'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300': item.statusText === 'DISPOSE',
                      'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300': item.statusText === 'SOON',
                      'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300': item.statusText === 'MONITOR',
                      'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300': item.statusText === 'GOOD'
                    }"
                    class="px-3 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ item.statusText }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{{ item.maintenanceCount }}</td>
              </tr>
              <tr v-if="paginatedItems.length === 0">
                <td colspan="12" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                  <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500 mb-2 block">inbox</span>
                  No items found
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="filteredItems.length > 0" class="bg-gradient-to-r from-green-50 to-green-100 dark:from-gray-700 dark:to-gray-700 px-6 py-4 border-t border-green-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
              <div class="text-sm font-medium text-gray-700 dark:text-white">
                Result {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }}
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-white">Items per page:</label>
                <select 
                  v-model="itemsPerPage" 
                  @change="currentPage = 1"
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
</template>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
}

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
</style>
