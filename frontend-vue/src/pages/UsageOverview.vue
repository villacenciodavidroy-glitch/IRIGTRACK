<template>
  <div class="usage-overview-container p-3 sm:p-4 md:p-6">
    <!-- Back Button -->
    <button @click="goBack" class="back-button mb-4 sm:mb-6 px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
      <i class="fas fa-arrow-left"></i>
      <span>Back</span>
    </button>

    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-6 sm:mb-8 text-gray-900 dark:text-white">Usage of Items Overview</h1>

    <!-- Quarterly Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
      <!-- Q1 Chart -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm">
        <h2 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4 text-gray-900 dark:text-white">Jan - Mar Usage</h2>
        <div class="h-[250px] sm:h-[300px]">
          <Bar :data="q1Data" :options="barOptions" />
        </div>
      </div>

      <!-- Q2 Chart -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm">
        <h2 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4 text-gray-900 dark:text-white">Apr - Jun Usage</h2>
        <div class="h-[250px] sm:h-[300px]">
          <Bar :data="q2Data" :options="barOptions" />
        </div>
      </div>

      <!-- Q3 Chart -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm">
        <h2 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4 text-gray-900 dark:text-white">Jul - Sep Usage</h2>
        <div class="h-[250px] sm:h-[300px]">
          <Bar :data="q3Data" :options="barOptions" />
        </div>
      </div>

      <!-- Q4 Chart -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm">
        <h2 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4 text-gray-900 dark:text-white">Oct - Dec Usage</h2>
        <div class="h-[250px] sm:h-[300px]">
          <Bar :data="q4Data" :options="barOptions" />
        </div>
      </div>
    </div>

    <!-- Forecast Section -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 shadow-sm">
      <h2 class="text-lg sm:text-xl font-semibold mb-4 sm:mb-6 text-gray-900 dark:text-white">Forecast for Next 3 Months</h2>
      <div class="w-full max-w-md mx-auto h-[250px] sm:h-[300px]">
        <Pie :data="forecastData" :options="pieOptions" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../composables/useItems'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
} from 'chart.js'
import { Bar, Pie } from 'vue-chartjs'

const router = useRouter()

const goBack = () => {
  router.back()
}

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
)

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    y: {
      beginAtZero: true,
      max: 70,
      grid: {
        color: '#E5E7EB',
        drawBorder: false
      },
      ticks: {
        stepSize: 10
      }
    },
    x: {
      grid: {
        display: false
      }
    }
  },
  plugins: {
    legend: {
      display: false
    }
  }
}

const pieOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
}

// Load real inventory items
const { items, fetchitems } = useItems()
onMounted(() => fetchitems())

const isConsumableCategory = (category) => {
  const c = (category || '').toLowerCase()
  return c === 'consumables' || c === 'consumable' || c.includes('consumable')
}

const consumableItems = computed(() => {
  return (items.value || []).filter(i => isConsumableCategory(i?.category))
})

const labels = computed(() => consumableItems.value.map(i => i.unit || `Item ${i.id}`))
const quantities = computed(() => consumableItems.value.map(i => Number(i.quantity || 0)))

const q1Data = computed(() => ({
  labels: labels.value,
  datasets: [{
    data: quantities.value,
    backgroundColor: ['#3B82F6', '#34D399', '#F59E0B', '#06B6D4', '#8B5CF6'],
    barPercentage: 0.5,
    categoryPercentage: 0.7
  }]
}))

const q2Data = computed(() => ({
  labels: labels.value,
  datasets: [{
    data: quantities.value,
    backgroundColor: ['#3B82F6', '#34D399', '#F59E0B', '#06B6D4', '#8B5CF6'],
    barPercentage: 0.5,
    categoryPercentage: 0.7
  }]
}))

const q3Data = computed(() => ({
  labels: labels.value,
  datasets: [{
    data: quantities.value,
    backgroundColor: ['#3B82F6', '#34D399', '#F59E0B', '#06B6D4', '#8B5CF6'],
    barPercentage: 0.5,
    categoryPercentage: 0.7
  }]
}))

const q4Data = computed(() => ({
  labels: labels.value,
  datasets: [{
    data: quantities.value,
    backgroundColor: ['#3B82F6', '#34D399', '#F59E0B', '#06B6D4', '#8B5CF6'],
    barPercentage: 0.5,
    categoryPercentage: 0.7
  }]
}))

const forecastData = computed(() => ({
  labels: labels.value,
  datasets: [{
    data: quantities.value,
    backgroundColor: ['#4C51BF', '#059669', '#DC2626', '#14B8A6', '#F472B6']
  }]
}))
</script>

<style scoped>
.usage-overview-container {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
  background-color: #f9fafb;
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 2rem;
}

.bg-white {
  background-color: white;
  border-radius: 0.75rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  height: 400px;
  display: flex;
  flex-direction: column;
}

.text-2xl {
  font-size: 1.75rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 2rem;
}

.text-lg {
  font-size: 1.25rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 1rem;
}

.chart-container {
  flex: 1;
  position: relative;
  width: 100%;
}

.w-full {
  width: 100%;
  height: 300px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.max-w-md {
  max-width: 32rem;
}

.mb-8 {
  margin-bottom: 2rem;
}

.shadow-sm {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.back-button {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background-color: #f3f4f6;
  border: none;
  border-radius: 0.375rem;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.back-button:hover {
  background-color: #e5e7eb;
}

.mr-2 {
  margin-right: 0.5rem;
}
</style> 