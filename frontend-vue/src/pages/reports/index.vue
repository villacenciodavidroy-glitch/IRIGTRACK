<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  router.back()
}

const navigateToReport = (reportType) => {
  // Navigate to specific report pages based on the report type
  // Handle special cases for maintenance-records and transactions
  if (reportType === 'maintenance-records') {
    router.push('/reports/maintenance-records')
  } else if (reportType === 'transactions') {
    router.push('/reports/transactions')
  } else if (reportType === 'user-supply-usage') {
    router.push('/reports/user-supply-usage')
  } else {
    router.push(`/reports/${reportType.toLowerCase().replace(/\s+/g, '-')}`)
  }
}

const reports = ref([
  {
    name: 'Monitoring Assets',
    icon: 'desktop_windows',
    type: 'monitoring-assets',
    description: 'Track and monitor asset status and performance'
  },
  {
    name: 'Life Cycles Data',
    icon: 'show_chart',
    type: 'life-cycles-data',
    description: 'View asset lifecycle information and history'
  },
  {
    name: 'Serviceable and Non-Serviceable Items',
    icon: 'build',
    type: 'serviceable-items',
    description: 'Report on serviceable and non-serviceable equipment status'
  },
  {
    name: 'Maintenance Records',
    icon: 'build',
    type: 'maintenance-records',
    description: 'Generate comprehensive maintenance records report with detailed maintenance history'
  },
  {
    name: 'Transactions',
    icon: 'swap_horiz',
    type: 'transactions',
    description: 'Generate transactions report with borrow requests and approvals'
  },
  {
    name: 'Supply Item Usage',
    icon: 'inventory_2',
    type: 'user-supply-usage',
    description: 'Track overall usage for each supply item (e.g., Ballpens: 490 usage in Q2)'
  }
])
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6">
    <div class="w-full max-w-full mx-auto space-y-5">
      <!-- Clean Header Section -->
      <div class="bg-green-600 rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-5 md:px-8 md:py-6 flex items-center gap-4">
          <button 
            @click="goBack" 
            class="p-2.5 bg-white rounded-lg hover:bg-gray-100 transition-colors flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-green-600 text-xl md:text-2xl">arrow_back</span>
          </button>
          <div class="p-3 bg-white rounded-lg flex-shrink-0 shadow-md">
            <span class="material-icons-outlined text-green-600 text-2xl md:text-3xl">assessment</span>
          </div>
          <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-1">Reporting</h1>
            <p class="text-green-100 text-base md:text-lg">Generate comprehensive reports and analytics</p>
          </div>
        </div>
      </div>

      <!-- Reports Section -->
      <div class="w-full">
        <!-- Section Header -->
        <div class="mb-5">
          <div class="flex items-center gap-3 mb-2">
            <span class="material-icons-outlined text-green-600 dark:text-green-400 text-2xl md:text-3xl">description</span>
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">Generate Reports</h2>
          </div>
          <p class="text-gray-600 dark:text-gray-400 text-sm md:text-base ml-11">Select a report type to generate detailed analytics and documentation</p>
        </div>

        <!-- Reports Grid - Maximize Space -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
          <div 
            v-for="(report, index) in reports" 
            :key="report.name"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-md dark:shadow-xl border border-gray-200 dark:border-gray-700 hover:shadow-xl dark:hover:shadow-2xl transition-all duration-200 overflow-hidden flex flex-col min-h-[350px]"
          >
            <!-- Card Content -->
            <div class="p-6 md:p-8 flex flex-col flex-grow">
              <!-- Header with Icon Button on Right -->
              <div class="flex items-start justify-between mb-5">
                <div class="flex-1 min-w-0 pr-5">
                  <h3 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white mb-2 leading-tight">{{ report.name }}</h3>
                </div>
                <!-- Colored Icon Button -->
                <div 
                  :class="[
                    index === 0 ? 'bg-orange-500' : 
                    index === 1 ? 'bg-blue-500' : 
                    index === 2 ? 'bg-purple-500' :
                    index === 3 ? 'bg-blue-600' :
                    index === 4 ? 'bg-green-500' :
                    'bg-indigo-500'
                  ]"
                  class="p-4 rounded-lg flex-shrink-0 shadow-md"
                >
                  <span class="material-icons-outlined text-white text-2xl md:text-3xl">{{ report.icon }}</span>
                </div>
              </div>

              <!-- Description -->
              <p class="text-gray-600 dark:text-gray-400 text-sm md:text-base mb-6 flex-grow leading-relaxed">{{ report.description }}</p>
              
              <!-- Button -->
              <button 
                @click="navigateToReport(report.type)"
                class="w-full bg-green-600 text-white px-6 py-3.5 md:py-4 rounded-lg flex items-center justify-center gap-2 hover:bg-green-700 transition-colors font-semibold text-sm md:text-base shadow-md hover:shadow-lg"
              >
                <span class="material-icons-outlined text-lg">assessment</span>
                <span>Generate Report</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
</style> 