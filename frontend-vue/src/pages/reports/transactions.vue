<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../../axios'
import { useDebouncedRef } from '../../composables/useDebounce'
import logoImage from '../../assets/logo.png'

const router = useRouter()
const transactions = ref([])
const allTransactions = ref([])
const loading = ref(false)
const error = ref(null)
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const currentPage = ref(1)
const itemsPerPage = ref(8)
const totalTransactions = ref(0)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 8,
  total: 0,
  from: 0,
  to: 0
})

// Fetch all transactions for export
const fetchAllTransactions = async () => {
  try {
    const params = {
      per_page: 10000
    }
    
    if (debouncedSearchQuery.value) {
      params.search = debouncedSearchQuery.value
    }
    
    const response = await axiosClient.get('/transactions', { params })
    
    if (response.data && response.data.success) {
      allTransactions.value = response.data.data || []
    }
  } catch (err) {
    console.error('Error fetching all transactions:', err)
  }
}

// Fetch transactions
const fetchTransactions = async () => {
  loading.value = true
  error.value = null
  
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value
    }
    
    if (debouncedSearchQuery.value) {
      params.search = debouncedSearchQuery.value
    }
    
    const response = await axiosClient.get('/transactions', { params })
    
    if (response.data && response.data.success) {
      transactions.value = response.data.data || []
      pagination.value = response.data.pagination || pagination.value
      totalTransactions.value = pagination.value.total || 0
    } else {
      transactions.value = []
      error.value = 'Failed to load transactions'
    }
  } catch (err) {
    console.error('Error fetching transactions:', err)
    error.value = `Failed to load transactions: ${err.response?.data?.message || err.message}`
    transactions.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchTransactions()
  fetchAllTransactions()
})

watch(debouncedSearchQuery, () => {
  currentPage.value = 1
  fetchTransactions()
  fetchAllTransactions()
})

watch(currentPage, () => {
  fetchTransactions()
})

watch(itemsPerPage, () => {
  currentPage.value = 1
  fetchTransactions()
})

const confirmModal = ref({
  show: false,
  message: '',
  onConfirm: null
})

// Signature modal state for print
const showSignatureModal = ref(false)
const signatureData = ref({
  preparedBy: {
    name: 'Jasper Kim Sales',
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

// Export to Excel
const performExportToExcel = async () => {
  try {
    if (allTransactions.value.length === 0) {
      alert('No transactions to export')
      return
    }
    
    const params = new URLSearchParams()
    const exportData = allTransactions.value.map(transaction => ({
      requested_by: transaction.requested_by || 'N/A',
      approver_name: transaction.approver_name || transaction.approved_by || 'N/A',
      approved_by: transaction.approver_name || transaction.approved_by || 'N/A',
      borrower_name: transaction.borrower_name || 'N/A',
      location: transaction.location || 'N/A',
      item_name: transaction.item_name || 'N/A',
      quantity: transaction.quantity || 0,
      transaction_time: transaction.transaction_time || 'N/A',
      role: transaction.role || 'USER',
      status: transaction.status || 'Pending'
    }))
    
    params.append('transactions', JSON.stringify(exportData))
    const baseUrl = `/transactions/export`
    const fullUrl = `${baseUrl}?${params.toString()}`
    
    let response
    if (fullUrl.length > 1800) {
      response = await axiosClient.get(baseUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        },
        timeout: 60000
      })
    } else {
      response = await axiosClient.get(fullUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        },
        timeout: 60000
      })
    }
    
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Transactions_${new Date().toISOString().split('T')[0]}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting to Excel:', error)
    alert('Failed to export to Excel. Please try again.')
  }
}

const exportToExcel = () => {
  openConfirm('Do you want to export transactions to Excel?', performExportToExcel)
}

// Export to PDF
const performExportToPDF = async () => {
  try {
    if (allTransactions.value.length === 0) {
      alert('No transactions to export')
      return
    }
    
    const params = new URLSearchParams()
    const exportData = allTransactions.value.map(transaction => ({
      requested_by: transaction.requested_by || 'N/A',
      approved_by: transaction.approver_name || transaction.approved_by || 'N/A',
      borrower_name: transaction.borrower_name || 'N/A',
      location: transaction.location || 'N/A',
      item_name: transaction.item_name || 'N/A',
      quantity: transaction.quantity || 0,
      transaction_time: transaction.transaction_time || 'N/A',
      status: transaction.status || 'Pending'
    }))
    
    params.append('transactions', JSON.stringify(exportData))
    const baseUrl = `/transactions/export-pdf`
    const fullUrl = `${baseUrl}?${params.toString()}`
    
    let response
    if (fullUrl.length > 1800) {
      response = await axiosClient.get(baseUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf'
        },
        timeout: 60000
      })
    } else {
      response = await axiosClient.get(fullUrl, {
        responseType: 'blob',
        headers: {
          'Accept': 'application/pdf'
        },
        timeout: 60000
      })
    }
    
    const blob = new Blob([response.data], {
      type: 'application/pdf'
    })
    
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Transactions_${new Date().toISOString().split('T')[0]}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting to PDF:', error)
    alert('Failed to export to PDF. Please try again.')
  }
}

const exportToPDF = async () => {
  // Explicitly prevent signature modal from showing
  showSignatureModal.value = false
  // Prevent any modal interference
  confirmModal.value.show = false
  // Directly perform PDF export without any modals or confirmations
  await performExportToPDF()
  // Ensure modal stays closed after export
  showSignatureModal.value = false
}

// Print Report with signature modal
const openPrintDialog = () => {
  showSignatureModal.value = true
}

// Print report with formatted document
const printReport = () => {
  showSignatureModal.value = false
  
  const printWindow = window.open('', '_blank')
  const now = new Date()
  const currentYear = now.getFullYear()
  
  // Use edited signature data
  const preparedByName = signatureData.value.preparedBy.name || 'Jasper Kim Sales'
  const preparedByTitle = signatureData.value.preparedBy.title || 'Property Officer B'
  const reviewedByName = signatureData.value.reviewedBy.name || 'ANA LIZA C. DINOPOL'
  const reviewedByTitle = signatureData.value.reviewedBy.title || 'Administrative Services Officer A'
  const notedByName = signatureData.value.notedBy.name || 'LARRY C. FRANADA'
  const notedByTitle = signatureData.value.notedBy.title || 'Division Manager A'
  
  // Get all transactions for printing
  const transactionsToPrint = allTransactions.value.length > 0 ? allTransactions.value : transactions.value
  
  const tableRows = transactionsToPrint.map((transaction, index) => `
    <tr>
      <td style="text-align: left; padding: 4px;">${index + 1}</td>
      <td style="text-align: left; padding: 4px;">${transaction.requested_by || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${transaction.approver_name || transaction.approved_by || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${transaction.borrower_name || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${transaction.location || 'N/A'}</td>
      <td style="text-align: left; padding: 4px;">${transaction.item_name || 'N/A'}</td>
      <td style="text-align: center; padding: 4px;">${transaction.quantity || 0}</td>
      <td style="text-align: center; padding: 4px;">${formatDateTime(transaction.transaction_time)}</td>
      <td style="text-align: center; padding: 4px;">${transaction.status || 'Pending'}</td>
    </tr>
  `).join('')
  
  const content = `
    <!DOCTYPE html>
    <html>
    <head>
      <title>TRANSACTIONS REPORT - ${currentYear}</title>
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
        .transactions-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
          font-size: 10px;
        }
        .transactions-table th {
          font-weight: bold;
          text-align: left;
          padding: 6px 3px;
          border: 1px solid #000;
          font-size: 9px;
          background-color: #f8f8f8;
        }
        .transactions-table td {
          padding: 4px 3px;
          border: 1px solid #000;
          vertical-align: top;
          font-size: 9px;
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
        
        <div class="report-title">TRANSACTIONS REPORT</div>
        <div class="report-year">For the Year ${currentYear}</div>
      </div>
      
      <table class="transactions-table">
        <thead>
          <tr>
            <th>NO.</th>
            <th>REQUESTED BY</th>
            <th>APPROVED BY</th>
            <th>RECEIVER</th>
            <th>LOCATION</th>
            <th>ITEM NAME</th>
            <th>QUANTITY</th>
            <th>TRANSACTION TIME</th>
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
  router.push('/reporting')
}

const refreshData = () => {
  fetchTransactions()
  fetchAllTransactions()
}

const totalPages = computed(() => pagination.value.last_page || 1)
const startIndex = computed(() => pagination.value.from || 0)
const endIndex = computed(() => pagination.value.to || 0)

// Compute visible pages for pagination
const visiblePages = computed(() => {
  const pages = []
  const current = currentPage.value
  const total = totalPages.value
  
  if (total <= 7) {
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    pages.push(1)
    
    if (current <= 4) {
      for (let i = 2; i <= 5; i++) {
        pages.push(i)
      }
      pages.push('ellipsis')
      pages.push(total)
    } else if (current >= total - 3) {
      pages.push('ellipsis')
      for (let i = total - 4; i <= total; i++) {
        pages.push(i)
      }
    } else {
      pages.push('ellipsis')
      pages.push(current - 1)
      pages.push(current)
      pages.push(current + 1)
      pages.push('ellipsis')
      pages.push(total)
    }
  }
  
  return pages
})

const printTransactions = computed(() => {
  return allTransactions.value.length > 0 ? allTransactions.value : transactions.value
})

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A'
  try {
    const date = new Date(dateString)
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch (e) {
    return dateString
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-8 print-area screen-only">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-emerald-700 shadow-xl rounded-2xl mt-4 sm:mt-6">
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
            <button 
              @click="refreshData" 
              class="p-3 bg-white/15 border border-white/20 text-white rounded-full hover:bg-white/25 transition-all shadow-lg backdrop-blur"
              title="Refresh"
            >
              <span class="material-icons-outlined text-xl">refresh</span>
            </button>
          </div>
          <div class="text-white">
            <h1 class="text-3xl font-extrabold leading-tight">Transactions Report</h1>
            <p class="text-white/90 text-base mt-1">Track approvals, receivers, and movement activity.</p>
            <p class="mt-3 inline-flex items-center px-4 py-1.5 rounded-full bg-white/15 border border-white/25 shadow-sm font-semibold">
              {{ totalTransactions || 0 }} {{ totalTransactions === 1 ? 'transaction' : 'transactions' }}
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button 
            @click="exportToExcel" 
            class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60"
            :disabled="loading || allTransactions.length === 0"
          >
            <span class="material-icons-outlined text-lg text-emerald-700">download</span>
            <span>Export Excel</span>
          </button>
          <button 
            @click.stop.prevent="exportToPDF" 
            class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60"
            :disabled="loading || allTransactions.length === 0"
          >
            <span class="material-icons-outlined text-lg text-emerald-700">picture_as_pdf</span>
            <span>Export PDF</span>
          </button>
          <button 
            @click="openPrintDialog" 
            class="bg-emerald-50 text-emerald-800 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:bg-white transition-all font-semibold shadow-lg border border-white/60"
            :disabled="loading || transactions.length === 0"
          >
            <span class="material-icons-outlined text-lg text-emerald-700">print</span>
            <span>Print Report</span>
          </button>
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

    <!-- Signature Modal -->
    <div v-if="showSignatureModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 print-hidden">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 space-y-5">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-xl font-semibold text-gray-900">Edit Signature Information</h3>
          </div>
          <button @click="showSignatureModal = false" class="text-gray-500 hover:text-gray-700">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>

        <div class="grid gap-6">
          <div class="space-y-3">
            <p class="text-sm font-semibold text-gray-800">Prepared by:</p>
            <input v-model="signatureData.preparedBy.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            <input v-model="signatureData.preparedBy.title" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
          <div class="space-y-3">
            <p class="text-sm font-semibold text-gray-800">Reviewed by:</p>
            <input v-model="signatureData.reviewedBy.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            <input v-model="signatureData.reviewedBy.title" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
          <div class="space-y-3">
            <p class="text-sm font-semibold text-gray-800">Noted by:</p>
            <input v-model="signatureData.notedBy.name" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            <input v-model="signatureData.notedBy.title" type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button @click="showSignatureModal = false" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
          <button @click="printReport" class="px-5 py-2.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow">
            Print Report
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="p-4 sm:p-6">
      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-xl shadow-md border border-gray-100 p-12">
        <div class="flex flex-col items-center justify-center">
          <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
            <span class="material-icons-outlined animate-spin text-5xl text-green-400">refresh</span>
          </div>
          <p class="text-lg font-semibold text-gray-900">Loading transactions...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="transactions.length === 0" class="bg-white rounded-xl shadow-md border border-gray-100 p-12">
        <div class="flex flex-col items-center justify-center">
          <span class="material-icons-outlined text-6xl text-gray-400 mb-4">swap_horiz</span>
          <h3 class="text-xl font-bold text-gray-900 mb-2">No transactions found</h3>
          <p class="text-gray-600 text-center">{{ searchQuery ? 'Try adjusting your search query' : 'No transactions available.' }}</p>
        </div>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden print:shadow-none">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-emerald-50 to-emerald-100">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Requested By</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Approved By</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Receiver</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Location</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Item Name</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Quantity</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Transaction Time</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-900 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-emerald-50/60">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ transaction.requested_by || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ transaction.approver_name || transaction.approved_by || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ transaction.borrower_name || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ transaction.location || 'N/A' }}</td>
                <td class="px-6 py-4 text-sm text-gray-900">{{ transaction.item_name || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ transaction.quantity || 0 }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDateTime(transaction.transaction_time) }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    transaction.status === 'Approved' ? 'bg-green-100 text-green-800' :
                    transaction.status === 'Rejected' ? 'bg-red-100 text-red-800' :
                    'bg-yellow-100 text-yellow-800'
                  ]">
                    {{ transaction.status || 'Pending' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="bg-emerald-50 border-t border-emerald-100 px-6 py-4 print:hidden">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="flex items-center gap-2 text-sm text-emerald-900 bg-white border border-emerald-100 px-4 py-2 rounded-full shadow-sm">
                <span class="material-icons-outlined text-emerald-600 text-lg">info</span>
                <span>Showing <span class="font-medium">{{ startIndex || 0 }}</span> to <span class="font-medium">{{ endIndex || 0 }}</span> of <span class="font-medium">{{ totalTransactions }}</span> items</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-emerald-900">Items per page:</span>
                <select 
                  v-model="itemsPerPage"
                  class="px-4 py-2 border border-emerald-200 rounded-lg text-sm bg-white text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm"
                >
                  <option value="8">8</option>
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>
            <div v-if="totalPages > 1" class="flex items-center gap-1">
              <button 
                @click="currentPage = 1"
                :disabled="currentPage === 1"
                class="px-3 py-2 text-sm font-medium text-emerald-900 bg-white border border-emerald-200 rounded hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                title="First page"
              >
                «
              </button>
              <button 
                @click="currentPage--" 
                :disabled="currentPage === 1"
                class="px-3 py-2 text-sm font-medium text-emerald-900 bg-white border border-emerald-200 rounded hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                title="Previous page"
              >
                &lt;
              </button>
              <div class="flex items-center gap-1">
                <button 
                  v-for="(page, index) in visiblePages" 
                  :key="`page-${index}-${page}`"
                  @click="page !== 'ellipsis' && (currentPage = page)"
                  :disabled="page === 'ellipsis'"
                  :class="[
                    'px-3 py-2 text-sm font-medium rounded transition-colors shadow-sm',
                    page === 'ellipsis' ? 'cursor-default bg-transparent border-0' : 'cursor-pointer border border-emerald-200',
                    currentPage === page 
                      ? 'bg-emerald-600 text-white border-emerald-600' 
                      : 'bg-white text-emerald-900 hover:bg-emerald-50'
                  ]"
                >
                  {{ page === 'ellipsis' ? '...' : page }}
                </button>
              </div>
              <button 
                @click="currentPage++" 
                :disabled="currentPage === totalPages"
                class="px-3 py-2 text-sm font-medium text-emerald-900 bg-white border border-emerald-200 rounded hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                title="Next page"
              >
                &gt;
              </button>
              <button 
                @click="currentPage = totalPages"
                :disabled="currentPage === totalPages"
                class="px-3 py-2 text-sm font-medium text-emerald-900 bg-white border border-emerald-200 rounded hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                title="Last page"
              >
                »
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Print-only full dataset -->
  <div class="print-only">
    <div class="px-6 py-4">
      <h2 class="text-2xl font-bold mb-2">Transactions Report</h2>
      <p class="text-sm text-gray-700 mb-4">All transaction records</p>
      <table class="min-w-full border border-gray-300">
        <thead class="bg-gray-100">
          <tr>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Requested By</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Approved By</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Receiver</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Location</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Item Name</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Quantity</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Transaction Time</th>
            <th class="border px-3 py-2 text-left text-xs font-semibold">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in printTransactions" :key="t.id || `${t.requested_by}-${t.transaction_time}`">
            <td class="border px-3 py-2 text-xs">{{ t.requested_by || 'N/A' }}</td>
            <td class="border px-3 py-2 text-xs">{{ t.approver_name || t.approved_by || 'N/A' }}</td>
            <td class="border px-3 py-2 text-xs">{{ t.borrower_name || 'N/A' }}</td>
            <td class="border px-3 py-2 text-xs">{{ t.location || 'N/A' }}</td>
            <td class="border px-3 py-2 text-xs">{{ t.item_name || 'N/A' }}</td>
            <td class="border px-3 py-2 text-xs text-center">{{ t.quantity || 0 }}</td>
            <td class="border px-3 py-2 text-xs">{{ formatDateTime(t.transaction_time) }}</td>
            <td class="border px-3 py-2 text-xs">{{ t.status || 'Pending' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
@media print {
  .print\:hidden {
    display: none;
  }
  .print\:shadow-none {
    box-shadow: none;
  }
  .print-hidden {
    display: none !important;
  }
  :global(body) {
    background: #fff !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .print-area {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
  }
  .print-only {
    display: block !important;
  }
  .screen-only {
    display: none !important;
  }
  /* Hide common layout chrome (sidebars/navs) */
  ::v-deep nav,
  ::v-deep .sidebar,
  ::v-deep .sidebar-menu,
  ::v-deep .ant-layout-sider,
  ::v-deep .layout__sidebar,
  ::v-deep .layout-sider,
  ::v-deep .layout-menu,
  ::v-deep .el-menu {
    display: none !important;
    visibility: hidden !important;
  }
  /* Expand main content if layout uses margins */
  ::v-deep .ant-layout,
  ::v-deep .layout,
  ::v-deep .layout-content {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
  }
}
</style>
