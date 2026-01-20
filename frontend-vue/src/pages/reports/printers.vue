<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  router.back()
}

const items = ref([
  {
    article: 'Printer',
    description: 'HP LaserJet Pro M404dn Monochrome Laser Printer',
    propertyCode: 'DDN-IMO-F501 223-P01 05-21',
    unitValue: '18,500.00',
    dateAcquired: '05-10-21',
    poNumber: '2021-05-0245',
    location: 'Admin Office/Main Reception',
    condition: 'A1 Excellent'
  },
  {
    article: 'Printer',
    description: 'Epson EcoTank L3210 All-in-One Ink Tank Printer',
    propertyCode: 'DDN-IMO-F501 223-P02 05-21',
    unitValue: '12,800.00',
    dateAcquired: '05-10-21',
    poNumber: '2021-05-0245',
    location: 'Finance Division/Accounting',
    condition: 'A2 Good'
  },
  {
    article: 'Printer',
    description: 'Brother MFC-L8900CDW Color Laser All-in-One',
    propertyCode: 'DDN-IMO-F501 223-P03 05-22',
    unitValue: '32,600.00',
    dateAcquired: '02-15-22',
    poNumber: '2022-02-0118',
    location: 'Engineering Division/Design Section',
    condition: 'A1 Excellent'
  }
])

const entriesPerPage = ref(10)

const printReport = () => {
  window.print()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6 space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-emerald-700 shadow-xl rounded-2xl">
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
            <h1 class="text-3xl font-extrabold leading-tight">Printer Monitoring Report</h1>
            <p class="text-white/90 text-base mt-1">Track printer inventory, value, and condition</p>
          </div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <button class="bg-white text-emerald-700 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:-translate-y-0.5 transition-all font-semibold shadow-lg border border-white/60">
            <span class="material-icons-outlined text-lg text-emerald-700">download</span>
            <span>Export Excel</span>
          </button>
          <button @click="printReport" class="bg-emerald-50 text-emerald-800 px-5 py-2.5 rounded-xl flex items-center gap-2 hover:bg-white transition-all font-semibold shadow-lg border border-white/60">
            <span class="material-icons-outlined text-lg text-emerald-700">print</span>
            <span>Print Report</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Search and Show entries -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white rounded-xl shadow-md border border-gray-100 p-4">
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-700">Search:</label>
        <input type="text" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-700">Show</label>
        <select v-model="entriesPerPage" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="text-sm text-gray-700">entries</span>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-emerald-50 to-emerald-100">
            <tr>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">NO.</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">ARTICLE</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">DESCRIPTION</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">PROPERTY ACCOUNT CODE</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">UNIT VALUE</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">DATE ACQUIRED</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">P.O. NUMBER</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">UNIT/SECTIONS</th>
              <th class="border px-4 py-3 text-left text-xs font-semibold text-emerald-900">CONDITION</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(item, index) in items" :key="index" class="hover:bg-emerald-50/40">
              <td class="border px-4 py-2 text-sm text-gray-900">{{ index + 1 }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.article }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.description }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.propertyCode }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.unitValue }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.dateAcquired }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.poNumber }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.location }}</td>
              <td class="border px-4 py-2 text-sm text-gray-900">{{ item.condition }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="bg-emerald-50 border-t border-emerald-100 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-sm text-emerald-900">Showing <span class="font-semibold">{{ items.length }}</span> entries</div>
        <div class="flex gap-2">
          <button class="px-3 py-2 text-sm font-medium text-emerald-900 bg-white border border-emerald-200 rounded hover:bg-emerald-50 disabled:opacity-50">Previous</button>
          <button class="px-3 py-2 text-sm font-semibold text-white bg-emerald-600 border border-emerald-600 rounded">1</button>
          <button class="px-3 py-2 text-sm font-medium text-emerald-900 bg-white border border-emerald-200 rounded hover:bg-emerald-50">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
}
</style>