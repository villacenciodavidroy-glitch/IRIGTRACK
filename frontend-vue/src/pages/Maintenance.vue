<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const maintenanceData = ref({
  weekNumber: '',
  date: ''
})

const goBack = () => {
  router.push('/add-item')
}

const handleAdd = () => {
  // TODO: Implement adding maintenance schedule
  console.log('Adding maintenance:', maintenanceData.value)
}
</script>

<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <button @click="goBack" class="inline-flex items-center text-gray-600 hover:text-gray-800">
        <span class="material-icons-outlined">arrow_back</span>
        <span class="ml-1">Back</span>
      </button>
      <!-- <h1 class="text-xl font-semibold text-gray-800">Items</h1> -->
    </div>

    <!-- Maintenance Form -->
    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 max-w-xl">
      <h3 class="text-base font-medium mb-1">Schedule Of Maintenance (CLEANING)</h3>
      <p class="text-green-600 text-sm mb-4">For aircon and vehicle only</p>
      
      <div class="space-y-4">
        <div class="form-group">
          <label class="block text-sm mb-1">Monthly maintenance:</label>
          <input 
            type="text" 
            v-model="maintenanceData.weekNumber"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 bg-white"
            placeholder="Enter week number (1 to 4)"
          >
        </div>

        <div class="form-group">
          <label class="block text-sm mb-1">Date Acquired:</label>
          <input 
            type="text"
            v-model="maintenanceData.date"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 bg-white"
            placeholder="mm/dd/yyyy"
            onfocus="(this.type='date')"
            onblur="(this.type='text')"
          >
        </div>

        <div>
          <button 
            @click="handleAdd"
            type="button" 
            class="bg-green-600 text-white px-6 py-2 rounded text-sm hover:bg-green-700"
          >
            Add
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.btn-secondary {
  @apply bg-white text-gray-700 px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center text-sm font-medium gap-1;
}
</style> 