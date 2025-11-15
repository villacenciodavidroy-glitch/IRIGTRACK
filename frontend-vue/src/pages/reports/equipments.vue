<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  router.back()
}

const items = ref([
  {
    article: 'Equipment',
    description: 'Topcon GTS-235N Total Station Survey Equipment',
    propertyCode: 'DDN-IMO-F501 223-E01 05-20',
    unitValue: '185,000.00',
    dateAcquired: '08-12-20',
    poNumber: '2020-08-0412',
    location: 'Engineering Division/Survey Team',
    condition: 'A2 Good'
  },
  {
    article: 'Equipment',
    description: 'Bosch GBH 2-26 DRE Professional Rotary Hammer',
    propertyCode: 'DDN-IMO-F501 223-E02 05-21',
    unitValue: '12,500.00',
    dateAcquired: '03-15-21',
    poNumber: '2021-03-0175',
    location: 'Maintenance Division/Field Team',
    condition: 'A2 Good'
  },
  {
    article: 'Equipment',
    description: 'Honda EU22i Portable Inverter Generator',
    propertyCode: 'DDN-IMO-F501 223-E03 05-21',
    unitValue: '45,800.00',
    dateAcquired: '06-22-21',
    poNumber: '2021-06-0298',
    location: 'Field Operations/Emergency Response',
    condition: 'A1 Excellent'
  },
  {
    article: 'Equipment',
    description: 'Fluke 287 True-RMS Electronics Logging Multimeter',
    propertyCode: 'DDN-IMO-F501 223-E04 05-22',
    unitValue: '28,600.00',
    dateAcquired: '01-10-22',
    poNumber: '2022-01-0042',
    location: 'Electrical Division/Maintenance',
    condition: 'A1 Excellent'
  }
])

const entriesPerPage = ref(10)
</script>

<template>
  <div class="p-6">
    <!-- Back Button -->
    <button @click="goBack" class="mb-4 flex items-center text-green-600 hover:text-green-700">
      <span class="material-icons-outlined mr-1">arrow_back</span>
      Back
    </button>

    <!-- Header -->
    <div class="text-center mb-8">
      <span class="material-icons-outlined text-6xl text-green-600 mb-4">account_balance</span>
      <h1 class="text-xl font-bold">REPUBLIC OF THE PHILIPPINES</h1>
      <h2 class="text-lg font-bold">NATIONAL IRRIGATION ADMINISTRATION</h2>
      <h3>REGION XI</h3>
      <h4 class="font-bold">EQUIPMENT MONITORING</h4>
      <h5>FOR THE YEAR 2022</h5>
    </div>

    <!-- Search and Show entries -->
    <div class="flex justify-between mb-4">
      <div class="flex items-center">
        <label class="mr-2">Search:</label>
        <input type="text" class="border rounded px-2 py-1">
      </div>
      <div class="flex items-center">
        <label class="mr-2">Show</label>
        <select v-model="entriesPerPage" class="border rounded px-2 py-1">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        <span class="ml-2">entries</span>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full bg-gray-800 dark:bg-gray-800 border border-gray-700 dark:border-gray-700">
        <thead>
          <tr class="bg-gray-100">
            <th class="border px-4 py-2">NO.</th>
            <th class="border px-4 py-2">ARTICLE</th>
            <th class="border px-4 py-2">DESCRIPTION</th>
            <th class="border px-4 py-2">PROPERTY ACCOUNT CODE</th>
            <th class="border px-4 py-2">UNIT VALUE</th>
            <th class="border px-4 py-2">DATE ACQUIRED</th>
            <th class="border px-4 py-2">P.O. NUMBER</th>
            <th class="border px-4 py-2">UNIT/SECTORS</th>
            <th class="border px-4 py-2">CONDITION</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in items" :key="index">
            <td class="border px-4 py-2">{{ index + 1 }}</td>
            <td class="border px-4 py-2">{{ item.article }}</td>
            <td class="border px-4 py-2">{{ item.description }}</td>
            <td class="border px-4 py-2">{{ item.propertyCode }}</td>
            <td class="border px-4 py-2">{{ item.unitValue }}</td>
            <td class="border px-4 py-2">{{ item.dateAcquired }}</td>
            <td class="border px-4 py-2">{{ item.poNumber }}</td>
            <td class="border px-4 py-2">{{ item.location }}</td>
            <td class="border px-4 py-2">{{ item.condition }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex justify-between items-center mt-4">
      <div style="color: #01200E;">Showing <span style="color: #01200E; font-weight: bold;">1</span> to <span style="color: #01200E; font-weight: bold;">{{ items.length }}</span> of <span style="color: #01200E; font-weight: bold;">{{ items.length }}</span> entries</div>
      <div class="flex gap-2">
        <button class="px-3 py-1 border rounded" disabled>Previous</button>
        <button class="px-3 py-1 bg-green-600 text-white rounded">1</button>
        <button class="px-3 py-1 border rounded">Next</button>
      </div>
    </div>

    <!-- Export Button -->
    <div class="mt-6 flex justify-end">
      <button class="bg-green-600 text-white px-4 py-2 rounded-lg flex items-center">
        <span class="material-icons-outlined mr-2">download</span>
        Export to Excel
      </button>
    </div>
  </div>
</template>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
}
</style>