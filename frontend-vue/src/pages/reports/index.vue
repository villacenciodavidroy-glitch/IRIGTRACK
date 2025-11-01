<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  router.back()
}

const navigateToReport = (reportType) => {
  // Navigate to specific report pages based on the report type
  router.push(`/reports/${reportType.toLowerCase().replace(/\s+/g, '-')}`)
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
  }
])
</script>

<template>
  <div class="bg-white dark:bg-gray-900 p-3 sm:p-4 md:p-6">
    <!-- Back Button -->
    <button 
      @click="goBack"
      class="mb-4 sm:mb-6 px-3 sm:px-4 py-2 bg-green-600 text-white rounded-lg flex items-center hover:bg-green-700 transition-colors text-sm sm:text-base"
    >
      <span class="material-icons-outlined mr-2 text-lg sm:text-xl">arrow_back</span>
      Back
    </button>

    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-white mb-4 sm:mb-6">Generate Reports</h2>

    <!-- Reports Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
      <div 
        v-for="report in reports" 
        :key="report.name"
        class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 md:p-8 shadow-md hover:shadow-lg transition-shadow border border-gray-200 dark:border-gray-700 flex flex-col h-full"
      >
        <!-- Icon Container -->
        <div class="flex justify-center mb-6">
          <div class="bg-green-50 dark:bg-green-900/30 rounded-full w-16 h-16 flex items-center justify-center relative">
            <span class="material-icons-outlined text-green-600 dark:text-green-400 text-3xl leading-none">{{ report.icon }}</span>
          </div>
        </div>
        
        <!-- Title -->
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3 text-center">{{ report.name }}</h3>
        
        <!-- Description -->
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6 text-center flex-grow">{{ report.description }}</p>
        
        <!-- Button -->
        <button 
          @click="navigateToReport(report.type)"
          class="w-full bg-green-600 text-white px-4 py-3 rounded-lg flex items-center justify-center hover:bg-green-700 transition-colors mt-auto"
        >
          <span class="material-icons-outlined mr-2">assessment</span>
          Generate Report
        </button>
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