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
const selectedLocation = ref('all') // all locations
const viewMode = ref('cards') // 'cards' or 'table'
const selectedCategory = ref(null)

// Get items from the API using the composable
const { items, fetchitems, loading, error } = useItems()

// Get user data from auth composable
const { user, getUserDisplayName } = useAuth()

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
  }))
})




// Filter items based on search query, condition, and location
const filteredItems = computed(() => {
  let filtered = selectedCategory.value ? categoryItems.value : inventoryItems.value

  // Filter by location
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



// Get unique locations for filter
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

// Status counts for summary
const statusCounts = computed(() => {
  const counts = { excellent: 0, good: 0, poor: 0, maintenance: 0 }
  
  filteredItems.value.forEach(item => {
    const condition = item.condition?.toLowerCase() || ''
    if (condition.includes('excellent')) counts.excellent++
    else if (condition.includes('good')) counts.good++
    else if (condition.includes('poor')) counts.poor++
    else if (condition.includes('maintenance')) counts.maintenance++
  })
  
  return counts
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

// Print report
const printReport = () => {
  const printWindow = window.open('', '_blank')
  
  const now = new Date()
  const currentYear = now.getFullYear()
  
  // Get category name for report title
  const categoryName = selectedCategory.value || 'DESKTOP'
  
  // Get logged-in user info
  const userName = getUserDisplayName() || 'Admin User'
  
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
      <td style="text-align: center; padding: 4px;"></td>
      <td style="text-align: center; padding: 4px;"></td>
      <td style="text-align: center; padding: 4px;"></td>
      <td style="text-align: center; padding: 4px;"></td>
      <td style="text-align: center; padding: 4px;"></td>
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
        .monitoring-table .maintenance-col {
          width: 10%;
        }
        .maintenance-subheader {
          font-size: 8px;
          font-weight: bold;
          text-align: center;
          padding: 2px;
        }
        .maintenance-year {
          font-size: 8px;
          font-weight: bold;
          text-align: center;
          padding: 2px;
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
        }
        .signature-name {
          font-size: 10px;
          font-weight: bold;
          margin-bottom: 5px;
          text-transform: uppercase;
        }
        .signature-title {
          font-size: 9px;
          font-weight: normal;
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
            <th class="location-col">LOCATION</th>
            <th class="condition-col">CONDITION</th>
            <th colspan="5" class="maintenance-col">SCHEDULE OF MAINTENANCE (CLEANING)</th>
          </tr>
          <tr>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th class="maintenance-year">${currentYear}</th>
            <th class="maintenance-subheader">1st</th>
            <th class="maintenance-subheader">2nd</th>
            <th class="maintenance-subheader">3rd</th>
            <th class="maintenance-subheader">4th</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows}
        </tbody>
      </table>
      
      <div class="signature-section">
        <div class="signature-item">
          <div class="signature-label">Prepared by:</div>
          <div class="signature-name">${userName}</div>
          <div class="signature-title">Property Officer B</div>
        </div>
        <div class="signature-item">
          <div class="signature-label">Reviewed by:</div>
          <div class="signature-name">ANA LIZA C. DINOPOL</div>
          <div class="signature-title">Administrative Services Officer A</div>
        </div>
        <div class="signature-item">
          <div class="signature-label">Noted by:</div>
          <div class="signature-name">LARRY C. FRANADA</div>
          <div class="signature-title">Division Manager A</div>
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
        <h1 class="text-xl sm:text-2xl font-semibold text-green-700 dark:text-green-400">Asset Monitoring Report</h1>
      </div>
      <div class="flex items-center gap-2">
        <button @click="printReport" class="btn-primary flex items-center">
          <span class="material-icons-outlined text-lg mr-1">print</span>
          <span>Print Report</span>
        </button>
      </div>
    </div>



    <!-- Search and Filter Bar -->
    <div v-if="viewMode === 'table'" class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 sm:gap-0">
      <div class="relative w-full sm:w-96">
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 dark:text-gray-500">
            <span class="material-icons-outlined text-lg">search</span>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search assets..."
            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
          >
        </div>
      </div>
      <div class="flex items-center gap-2">
        <select 
          v-model="selectedLocation"
          class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
        >
          <option value="all">All Locations</option>
          <option v-for="location in uniqueLocations" :key="location" :value="location">
            {{ location }}
          </option>
        </select>
      </div>
    </div>

    <!-- Category Cards View -->
    <div v-if="viewMode === 'cards'" class="space-y-6">
      <!-- Search Bar for Categories -->
      <div class="relative w-full max-w-md">
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400 dark:text-gray-500">
            <span class="material-icons-outlined text-lg">search</span>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search categories..."
            class="w-full pl-10 pr-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
          >
        </div>
      </div>

      <!-- Category Cards Grid -->
      <div v-if="loading" class="flex justify-center items-center py-10">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
      </div>
      
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
      
      <div v-else-if="categoryCards.length === 0" class="flex flex-col justify-center items-center py-10">
        <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500">category</span>
        <p class="mt-2 text-gray-500 dark:text-gray-400">No categories found</p>
      </div>
      
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div 
          v-for="category in categoryCards.filter(cat => 
            !searchQuery || cat.name.toLowerCase().includes(searchQuery.toLowerCase())
          )" 
          :key="category.name"
          @click="showCategoryDetails(category.name)"
          class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow cursor-pointer p-6 hover:border-green-300 dark:hover:border-green-600"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
              <div class="bg-green-100 dark:bg-green-900/30 rounded-full p-3">
                <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl">category</span>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ category.name }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ category.count }} items</p>
              </div>
            </div>
            <span class="material-icons-outlined text-gray-400 dark:text-gray-500">chevron_right</span>
          </div>
          
          <div class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Total Items:</span>
              <span class="font-medium text-gray-900 dark:text-white">{{ category.count }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Status:</span>
              <span class="text-green-600 dark:text-green-400 font-medium">Active</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Container -->
    <div v-if="viewMode === 'table'" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
      <!-- Category Details Header -->
      <div v-if="selectedCategory" class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <button 
              @click="showAllCategories"
              class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors"
            >
              <span class="material-icons-outlined">arrow_back</span>
            </button>
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedCategory }} Items</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ filteredItems.length }} items found</p>
            </div>
          </div>
          <button 
            @click="showAllCategories"
            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors"
          >
            View All Categories
          </button>
        </div>
      </div>
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
        <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500">monitoring</span>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
          {{ selectedCategory ? `No items found in ${selectedCategory}` : 'No assets found' }}
        </p>
        <p v-if="searchQuery || selectedLocation !== 'all'" class="text-sm text-gray-400 dark:text-gray-500">Try adjusting your search or filters</p>
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
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && filteredItems.length > 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 gap-3 sm:gap-0">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
          <div class="text-sm text-gray-600 dark:text-gray-300">
            Result {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredItems.length) }} of {{ filteredItems.length }}
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600 dark:text-gray-300">Items per page:</label>
            <select 
              v-model="itemsPerPage" 
              @change="changeItemsPerPage($event.target.value)"
              class="border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
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
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
          >
            First
          </button>
          <button 
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
          >
            &lt; Previous
          </button>
          <div class="flex items-center gap-1">
            <template v-for="page in totalPages" :key="page">
              <button 
                v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                @click="goToPage(page)"
                :class="[
                  'px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white',
                  currentPage === page ? 'bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 border-green-500 dark:border-green-400' : ''
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
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
          >
            Next &gt;
          </button>
          <button 
            @click="goToPage(totalPages)"
            :disabled="currentPage === totalPages"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
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

.status-excellent {
  @apply text-green-600 font-bold;
}

.status-good {
  @apply text-blue-600 font-bold;
}

.status-poor {
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
