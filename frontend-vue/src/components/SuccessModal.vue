<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Success'
  },
  message: {
    type: String,
    default: 'Operation completed successfully'
  },
  buttonText: {
    type: String,
    default: 'Continue'
  },
  type: {
    type: String,
    default: 'success', // success, error, warning, info
    validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
  }
})

const emit = defineEmits(['confirm', 'close'])

const modalRef = ref(null)
const buttonRef = ref(null)

const handleConfirm = () => {
  emit('confirm')
}

const handleClose = () => {
  emit('close')
}

// Handle keyboard events
const handleKeydown = (event) => {
  // Only handle keyboard events when modal is open
  if (!props.isOpen) return
  
  if (event.key === 'Escape' || event.key === 'Enter') {
    handleConfirm()
  }
}

// Focus management
const focusModal = async () => {
  await nextTick()
  if (buttonRef.value) {
    buttonRef.value.focus()
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

// Get icon and colors based on type
const getIconAndColors = () => {
  switch (props.type) {
    case 'error':
      return {
        icon: 'close',
        iconColor: 'text-white',
        iconBg: 'bg-red-500',
        buttonClass: 'bg-red-500 hover:bg-red-600 focus:ring-red-500'
      }
    case 'warning':
      return {
        icon: 'warning',
        iconColor: 'text-white',
        iconBg: 'bg-yellow-500',
        buttonClass: 'bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-500'
      }
    case 'info':
      return {
        icon: 'info',
        iconColor: 'text-white',
        iconBg: 'bg-blue-500',
        buttonClass: 'bg-blue-500 hover:bg-blue-600 focus:ring-blue-500'
      }
    case 'success':
      return {
        icon: 'check',
        iconColor: 'text-white',
        iconBg: 'bg-green-500',
        buttonClass: 'bg-green-500 hover:bg-green-600 focus:ring-green-500'
      }
    default: // fallback to success
      return {
        icon: 'check',
        iconColor: 'text-white',
        iconBg: 'bg-green-500',
        buttonClass: 'bg-green-500 hover:bg-green-600 focus:ring-green-500'
      }
  }
}

const { icon, iconColor, iconBg, buttonClass } = getIconAndColors()
</script>

<template>
  <!-- Modal Backdrop -->
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click="handleClose"
  >
    <!-- Modal Content -->
    <div
      ref="modalRef"
      class="bg-white dark:bg-white rounded-xl sm:rounded-2xl shadow-2xl max-w-md w-[calc(100vw-2rem)] sm:w-full mx-4 transform transition-all duration-300 ease-in-out"
      @click.stop
      tabindex="-1"
    >
      <!-- Modal Body -->
      <div class="px-4 xs:px-6 sm:px-8 py-6 xs:py-8 text-center">
        <!-- Icon -->
        <div class="flex justify-center mb-4 xs:mb-6">
          <div class="w-12 h-12 xs:w-16 xs:h-16 rounded-full flex items-center justify-center" :class="iconBg">
            <span class="material-icons-outlined text-2xl xs:text-3xl" :class="iconColor">{{ icon }}</span>
          </div>
        </div>

        <!-- Title -->
        <h3 class="text-lg xs:text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-900 mb-3 xs:mb-4">
          {{ title }}
        </h3>

        <!-- Message -->
        <p class="text-gray-600 dark:text-gray-600 text-sm xs:text-base leading-relaxed mb-6 xs:mb-8 px-2">
          {{ message }}
        </p>

        <!-- Button -->
        <button
          ref="buttonRef"
          @click="handleConfirm"
          class="w-full xs:w-auto px-6 xs:px-8 py-2.5 xs:py-3 text-white font-semibold rounded-lg xs:rounded-xl focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105 text-sm xs:text-base"
          :class="buttonClass"
        >
          {{ buttonText }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom animations */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
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

@keyframes iconBounce {
  0%, 20%, 50%, 80%, 100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-10px);
  }
  60% {
    transform: translateY(-5px);
  }
}

.fixed.inset-0 {
  animation: fadeIn 0.3s ease-out;
}

.bg-white.dark\:bg-white {
  animation: slideIn 0.4s ease-out;
}

.w-16.h-16 {
  animation: iconBounce 0.6s ease-out 0.2s;
}

/* Button hover effects */
button:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

button:active {
  transform: translateY(0);
}

/* Focus styles */
.focus\:ring-2:focus {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
  .bg-white.dark\:bg-white {
    background-color: #ffffff !important;
  }
}
</style>
