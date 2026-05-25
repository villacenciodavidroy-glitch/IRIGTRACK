import { ref, onMounted, watch } from 'vue'
import axiosClient from '../axios' // or wherever your axiosClient is

/** Unwrap Laravel ResourceCollection payloads ({ data: [...] }) to a plain array */
const normalizeListPayload = (payload) => {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

export default function useLocations(formData = null) {
  const locations = ref([])
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: null,
    to: null
  })
  const loading = ref(false)

  const fetchLocations = async (page = 1, perPage = 10) => {
    try {
      loading.value = true
      const response = await axiosClient.get('/locations', {
        params: {
          page,
          per_page: perPage
        }
      })
      if (response.data && response.data.data) {
        locations.value = normalizeListPayload(response.data.data)
        if (response.data.pagination) {
          pagination.value = response.data.pagination
        }
        console.log('Available locations:', locations.value)
      }
    } catch (error) {
      console.error('Error fetching locations:', error)
    } finally {
      loading.value = false
    }
  }

  const createLocation = async (locationData) => {
    try {
      const response = await axiosClient.post('/locations', locationData)
      await fetchLocations(pagination.value.current_page, pagination.value.per_page) // Refresh list
      return response.data
    } catch (error) {
      console.error('Error creating location:', error)
      throw error
    }
  }

  const updateLocation = async (id, locationData) => {
    try {
      const response = await axiosClient.put(`/locations/${id}`, locationData)
      await fetchLocations(pagination.value.current_page, pagination.value.per_page) // Refresh list
      return response.data
    } catch (error) {
      console.error('Error updating location:', error)
      throw error
    }
  }

  const deleteLocation = async (id) => {
    try {
      const response = await axiosClient.delete(`/locations/${id}`)
      await fetchLocations(pagination.value.current_page, pagination.value.per_page) // Refresh list
      return response.data
    } catch (error) {
      console.error('Error deleting location:', error)
      throw error
    }
  }

  // Note: Pages should call fetchLocations() manually with page and perPage

  if (formData) {
    watch(() => formData.value.location, (newValue) => {
      console.log('Location selected:', newValue)
    })
  }

  return {
    locations,
    pagination,
    loading,
    fetchLocations,
    createLocation,
    updateLocation,
    deleteLocation
  }
}
