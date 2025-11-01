<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  isLoading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['confirm', 'cancel'])

const modalRef = ref(null)
const cancelButtonRef = ref(null)
const confirmButtonRef = ref(null)

const handleConfirm = () => {
  emit('confirm')
}

const handleCancel = () => {
  emit('cancel')
}

// Handle keyboard events
const handleKeydown = (event) => {
  // Only handle keyboard events when modal is open
  if (!props.isOpen) return
  
  if (event.key === 'Escape') {
    handleCancel()
  } else if (event.key === 'Enter' && !props.isLoading) {
    handleConfirm()
  }
}

// Focus management
const focusModal = async () => {
  await nextTick()
  if (modalRef.value) {
    modalRef.value.focus()
  }
}

// Watch for modal open/close to manage focus and keyboard listeners
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    focusModal()
    document.addEventListener('keydown', handleKeydown)
  } else {
    document.removeEventListener('keydown', handleKeydown)
  }
})

onMounted(() => {
  // Don't add listener on mount, only when modal opens
})

onUnmounted(() => {
  // Clean up listener if component unmounts while modal is open
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <!-- Modal Backdrop -->
  <div 
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click="handleCancel"
  >
    <!-- Modal Content -->
    <div 
      ref="modalRef"
      class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 ease-in-out"
      @click.stop
      tabindex="-1"
    >
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
              <span class="material-icons-outlined text-red-600 dark:text-red-400">logout</span>
            </div>
          </div>
          <div class="ml-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
              Confirm Logout
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Are you sure you want to log out?
            </p>
          </div>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="px-6 py-4">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900/20 rounded-full flex items-center justify-center">
              <span class="material-icons-outlined text-yellow-600 dark:text-yellow-400 text-sm">warning</span>
            </div>
          </div>
          <div class="ml-3">
            <p class="text-sm text-gray-700 dark:text-gray-300">
              You will be redirected to the login page and will need to sign in again to access your account.
            </p>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 rounded-b-lg">
        <div class="flex justify-end space-x-3">
          <button
            ref="cancelButtonRef"
            @click="handleCancel"
            :disabled="isLoading"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Cancel
          </button>
          <button
            ref="confirmButtonRef"
            @click="handleConfirm"
            :disabled="isLoading"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center"
          >
            <span v-if="isLoading" class="flex items-center">
              <span class="material-icons-outlined mr-2 text-sm animate-spin">refresh</span>
              Logging out...
            </span>
            <span v-else class="flex items-center">
              <span class="material-icons-outlined mr-2 text-sm">logout</span>
              Log out
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.fixed.inset-0 {
  animation: fadeIn 0.2s ease-out;
}

.bg-white.dark\\:bg-gray-800 {
  animation: slideIn 0.3s ease-out;
}

/* Focus styles */
.focus\\:ring-2:focus {
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}

/* Button hover effects */
button:hover {
  transform: translateY(-1px);
  transition: transform 0.2s ease-in-out;
}

button:active {
  transform: translateY(0);
}

/* Loading spinner animation */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}
</style>
