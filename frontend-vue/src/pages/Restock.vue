<template>
  <div class="p-6">
    <div class="flex items-center mb-6">
      <router-link :to="{ name: 'Analytics' }" class="flex items-center text-gray-600 hover:text-gray-800">
        <span class="material-icons-outlined mr-2">arrow_back</span>
        Back
      </router-link>
    </div>
    
    <h1 class="text-2xl font-semibold mb-6">Items</h1>
    <h2 class="text-lg text-green-600 mb-6">Restock item</h2>

    <form @submit.prevent="handleSubmit" class="max-w-xl space-y-6">
      <!-- Paper -->
      <div class="bg-gray-800 dark:bg-gray-800 p-4 rounded-lg shadow-sm dark:shadow-lg">
        <label class="block text-green-600 font-medium mb-2">Paper</label>
        <div>
          <label class="block text-gray-600 mb-1">Quantity:</label>
          <input 
            v-model="formData.paper"
            type="number"
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
            min="0"
          />
        </div>
      </div>

      <!-- Ballpens -->
      <div class="bg-gray-800 dark:bg-gray-800 p-4 rounded-lg shadow-sm dark:shadow-lg">
        <label class="block text-green-600 font-medium mb-2">Ballpens</label>
        <div>
          <label class="block text-gray-600 mb-1">Quantity:</label>
          <input 
            v-model="formData.ballpens"
            type="number"
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
            min="0"
          />
        </div>
      </div>

      <!-- Flashdrive -->
      <div class="bg-gray-800 dark:bg-gray-800 p-4 rounded-lg shadow-sm dark:shadow-lg">
        <label class="block text-green-600 font-medium mb-2">Flashdrive</label>
        <div>
          <label class="block text-gray-600 mb-1">Quantity:</label>
          <input 
            v-model="formData.flashdrive"
            type="number"
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
            min="0"
          />
        </div>
      </div>

      <div class="flex justify-end">
        <button 
          type="submit"
          class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
        >
          Add
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const formData = ref({
  paper: 0,
  ballpens: 0,
  flashdrive: 0
})

const handleSubmit = async () => {
  try {
    // TODO: Implement API call to update stock quantities
    console.log('Submitting restock:', formData.value)
    
    // Navigate back to analytics page after successful submission
    router.push('/analytics')
  } catch (error) {
    console.error('Error restocking items:', error)
  }
}
</script>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type="number"] {
  -moz-appearance: textfield;
}
</style> 