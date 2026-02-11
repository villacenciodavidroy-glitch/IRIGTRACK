import { ref, readonly, computed } from 'vue'
import axiosClient from '../axios'
import defaultLogo from '../assets/logo.png'

let cachedUrl = null

export default function useLogo() {
  const logoUrl = ref(cachedUrl ?? defaultLogo)
  const loading = ref(false)
  const error = ref(null)
  const logoDataUrl = ref(null)

  // Always expose an absolute, browser-resolvable logo URL.
  // This is especially important for print windows (about:blank),
  // where relative paths can sometimes fail to resolve correctly.
  const publicLogoUrl = computed(() => {
    const raw = logoUrl.value
    if (!raw) return ''
    try {
      return new URL(raw, window.location.origin).href
    } catch {
      return raw
    }
  })

  const fetchLogo = async () => {
    loading.value = true
    error.value = null
    try {
      // Add timestamp to prevent caching
      const res = await axiosClient.get('/settings/logo', {
        params: { t: Date.now() }
      })
      if (res.data?.success && res.data?.url) {
        // Add cache-busting parameter to URL
        const url = res.data.url
        const separator = url.includes('?') ? '&' : '?'
        cachedUrl = `${url}${separator}t=${Date.now()}`
        logoUrl.value = cachedUrl
      } else {
        logoUrl.value = defaultLogo
      }

      // Build a data URL version for safe use in print windows
      try {
        const finalUrl = logoUrl.value || defaultLogo
        const response = await fetch(finalUrl, { cache: 'no-store' })
        const blob = await response.blob()
        logoDataUrl.value = await new Promise((resolve, reject) => {
          const reader = new FileReader()
          reader.onloadend = () => resolve(reader.result)
          reader.onerror = reject
          reader.readAsDataURL(blob)
        })
      } catch {
        // If conversion fails, fall back to normal URL
        logoDataUrl.value = null
      }
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load logo'
      logoUrl.value = defaultLogo
      logoDataUrl.value = null
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
    publicLogoUrl: readonly(publicLogoUrl),
    logoDataUrl: readonly(logoDataUrl),
    loading: readonly(loading),
    error: readonly(error),
    fetchLogo,
    refetch,
    defaultLogo,
  }
}
