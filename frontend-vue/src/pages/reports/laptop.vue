<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  router.back()
}

const items = ref([
  {
    article: 'Laptop',
    description: 'Dell Latitude E5470 i5-6300U 8GB RAM 256GB SSD',
    propertyCode: 'DDN-IMO-F501 223-L01 05-20',
    unitValue: '45,800.00',
    dateAcquired: '06-15-20',
    poNumber: '2020-06-0325',
    location: 'Engineering Division/J.Santos',
    condition: 'A1 Excellent'
  },
  {
    article: 'Laptop',
    description: 'Lenovo ThinkPad T480 i7-8550U 16GB RAM 512GB SSD',
    propertyCode: 'DDN-IMO-F501 223-L02 05-21',
    unitValue: '52,600.00',
    dateAcquired: '03-22-21',
    poNumber: '2021-03-0187',
    location: 'Planning Division/R.Mendoza',
    condition: 'A2 Good'
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
      <h4 class="font-bold">LAPTOP MONITORING</h4>
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
            <th class="border px-4 py-2">LOCATION</th>
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
      <div>Showing 1 to {{ items.length }} of {{ items.length }} entries</div>
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