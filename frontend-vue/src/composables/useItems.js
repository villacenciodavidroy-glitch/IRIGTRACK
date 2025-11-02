import { ref, onMounted, watch } from 'vue'
import axiosClient from '../axios' // or wherever your axiosClient is

export default function useItems() {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)
  const retryCount = ref(0)
  const maxRetries = 3

  const fetchitems = async (retry = false) => {
    if (retry) {
      retryCount.value++
    } else {
      retryCount.value = 0
    }
    
    loading.value = true
    error.value = null
    
    try {
      console.log(`Fetching active items... (Attempt ${retryCount.value + 1})`)
      const response = await axiosClient.get('/items')
      console.log('Items response:', response)
      
      if (response.data && response.data.data) {
        items.value = response.data.data
        console.log('Available items:', items.value)
      } else {
        console.warn('Unexpected response format:', response.data)
        items.value = []
      }
      
      // Reset retry count on success
      retryCount.value = 0
    } catch (err) {
      console.error('Error fetching items:', err)
      console.error('Error details:', err.response || err.message)
      
      // Auto-retry if we haven't exceeded max retries
      if (retryCount.value < maxRetries) {
        console.log(`Retrying... (${retryCount.value + 1}/${maxRetries})`)
        setTimeout(() => fetchitems(true), 1000) // Wait 1 second before retrying
        return
      }
      
      error.value = err.response?.data?.message || 'Failed to load items. Please try again.'
    } finally {
      if (retryCount.value === 0 || retryCount.value >= maxRetries) {
        loading.value = false
      }
    }
  }

  onMounted(() => {
    fetchitems()
  })

//   if (formData) {
//     watch(() => formData.value.item, (newValue) => {
//       console.log('item selected:', newValue)
//     })
//   }

  return {
    items,
    loading,
    error,
    fetchitems
  }
}
