import { ref, watch } from 'vue'

/**
 * Debounce composable for optimizing search and filter inputs
 * @param {Function} fn - Function to debounce
 * @param {Number} delay - Delay in milliseconds
 * @returns {Object} - Debounced function and cancel method
 */
export function useDebounce(fn, delay = 300) {
  let timeoutId = null
  
  const debouncedFn = (...args) => {
    if (timeoutId) {
      clearTimeout(timeoutId)
    }
    timeoutId = setTimeout(() => {
      fn(...args)
    }, delay)
  }
  
  const cancel = () => {
    if (timeoutId) {
      clearTimeout(timeoutId)
      timeoutId = null
    }
  }
  
  return { debouncedFn, cancel }
}

/**
 * Debounced ref - creates a debounced version of a ref
 * @param {Ref} source - Source ref to debounce
 * @param {Number} delay - Delay in milliseconds
 * @returns {Ref} - Debounced ref
 */
export function useDebouncedRef(source, delay = 300) {
  const debounced = ref(source.value)
  
  watch(source, (newValue) => {
    const timeoutId = setTimeout(() => {
      debounced.value = newValue
    }, delay)
    
    return () => clearTimeout(timeoutId)
  }, { immediate: true })
  
  return debounced
}

