import { ref, onMounted, watch } from 'vue'
import axiosClient from '../axios' // or wherever your axiosClient is

export default function useItems() {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)
  const retryCount = ref(0)
  const maxRetries = 3
  const totalItems = ref(0) // Store total items count from pagination

  // Default to fetching more items (10000) to ensure we get accurate totals
  // Pages that need accurate counts should use a high perPage value
  const fetchitems = async (retry = false, perPage = 10000) => {
    if (retry) {
      retryCount.value++
    } else {
      retryCount.value = 0
    }
    
    loading.value = true
    error.value = null
    
    try {
      console.log(`Fetching active items... (Attempt ${retryCount.value + 1})`)
      const response = await axiosClient.get('/items', {
        params: {
          per_page: perPage,
          page: 1
        }
      })
      console.log('Items response:', response)
      
      if (response.data && response.data.data) {
        items.value = response.data.data
        // Store total from pagination if available
        if (response.data.pagination && response.data.pagination.total !== undefined) {
          totalItems.value = response.data.pagination.total
        } else {
          // Fallback to items length if pagination not available
          totalItems.value = items.value.length
        }
        console.log('Available items:', items.value)
        console.log('Total items:', totalItems.value)
      } else {
        console.warn('Unexpected response format:', response.data)
        items.value = []
        totalItems.value = 0
      }
      
      // Reset retry count on success
      retryCount.value = 0
    } catch (err) {
      console.error('Error fetching items:', err)
      console.error('Error details:', err.response || err.message)
      
      // Auto-retry if we haven't exceeded max retries
      if (retryCount.value < maxRetries) {
        console.log(`Retrying... (${retryCount.value + 1}/${maxRetries})`)
        setTimeout(() => fetchitems(true, perPage), 1000) // Wait 1 second before retrying
        return
      }
      
      error.value = err.response?.data?.message || 'Failed to load items. Please try again.'
      totalItems.value = 0
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
    totalItems,
    fetchitems
  }
}
