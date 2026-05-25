import { ref, onMounted, watch } from 'vue'
import axiosClient from '../axios' // or wherever your axiosClient is

/** Unwrap Laravel ResourceCollection payloads ({ data: [...] }) to a plain array */
const normalizeListPayload = (payload) => {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

export default function useUsers(formData = null) {
  const users = ref([])

  const fetchusers = async () => {
    try {
      const response = await axiosClient.get('/users', { params: { per_page: 1000 } })
      if (response.data && response.data.data) {
        users.value = normalizeListPayload(response.data.data)
      } else if (Array.isArray(response.data)) {
        users.value = response.data
        console.log('Available users:', users.value)
      }
    } catch (error) {
      console.error('Error fetching users:', error)
    }
  }

  onMounted(() => {
    fetchusers()
  })

  if (formData) {
    watch(() => formData.value.user, (newValue) => {
      console.log('user selected:', newValue)
    })
  }

  return {
    users,
    fetchusers
  }
}
