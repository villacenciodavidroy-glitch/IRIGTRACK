import { ref, readonly, computed } from 'vue'
import axiosClient from '../axios'

let cachedLabels = null

export default function useFormLabels() {
  const labels = ref(cachedLabels || {})
  const loading = ref(false)
  const error = ref(null)

  const fetchLabels = async () => {
    loading.value = true
    error.value = null
    try {
      const res = await axiosClient.get('/settings/form-labels')
      if (res.data?.success && res.data?.data) {
        // Convert array to object keyed by 'key' for easy lookup
        const labelsObj = {}
        if (Array.isArray(res.data.data)) {
          res.data.data.forEach(label => {
            if (label && label.key) {
              labelsObj[label.key] = label
            }
          })
        } else if (typeof res.data.data === 'object') {
          // Already an object
          Object.values(res.data.data).forEach(label => {
            if (label && label.key) {
              labelsObj[label.key] = label
            }
          })
        }
        cachedLabels = labelsObj
        labels.value = cachedLabels
      }
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load form labels'
      console.error('Error fetching form labels:', e)
    } finally {
      loading.value = false
    }
  }

  const getLabel = (key, fallback = '') => {
    const label = labels.value[key]
    return (label && label.label) ? label.label : fallback
  }

  const getPlaceholder = (key, fallback = '') => {
    const label = labels.value[key]
    return (label && label.placeholder) ? label.placeholder : fallback
  }

  const getSectionTitle = (key, fallback = '') => {
    const label = labels.value[key]
    return (label && label.section_title) ? label.section_title : fallback
  }

  const getSectionSubtitle = (key, fallback = '') => {
    const label = labels.value[key]
    return (label && label.section_subtitle) ? label.section_subtitle : fallback
  }

  const getHelperText = (key, fallback = '') => {
    const label = labels.value[key]
    return (label && label.helper_text) ? label.helper_text : fallback
  }

  const refetch = () => {
    cachedLabels = null
    return fetchLabels()
  }

  return {
    labels: readonly(labels),
    loading: readonly(loading),
    error: readonly(error),
    fetchLabels,
    refetch,
    getLabel,
    getPlaceholder,
    getSectionTitle,
    getSectionSubtitle,
    getHelperText,
  }
}
