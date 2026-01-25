import { ref, readonly } from 'vue'
import axiosClient from '../axios'
import defaultLogo from '../assets/logo.png'

let cachedUrl = null

export default function useLogo() {
  const logoUrl = ref(cachedUrl ?? defaultLogo)
  const loading = ref(false)
  const error = ref(null)

  const fetchLogo = async () => {
    loading.value = true
    error.value = null
    try {
      const res = await axiosClient.get('/settings/logo')
      if (res.data?.success && res.data?.url) {
        cachedUrl = res.data.url
        logoUrl.value = cachedUrl
      }
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load logo'
      logoUrl.value = defaultLogo
    } finally {
      loading.value = false
    }
  }

  const refetch = () => {
    cachedUrl = null
    return fetchLogo()
  }

  return {
    logoUrl: readonly(logoUrl),
    loading: readonly(loading),
    error: readonly(error),
    fetchLogo,
    refetch,
    defaultLogo,
  }
}
