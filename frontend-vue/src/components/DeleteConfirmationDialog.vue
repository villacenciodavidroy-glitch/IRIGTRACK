<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    @click="handleCancel"
  >
    <div
      class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all"
      @click.stop
    >
      <!-- Origin indicator (optional, matches browser style) -->
      <div v-if="showOrigin" class="px-4 pt-3 pb-1 text-xs text-gray-500">
        {{ originText }} says
      </div>
      
      <!-- Message -->
      <div class="px-6 py-4">
        <p class="text-base text-gray-900">
          {{ message }}
        </p>
      </div>
      
      <!-- Buttons -->
      <div class="px-6 pb-4 flex justify-end space-x-3">
        <button
          @click="handleCancel"
          :disabled="isLoading"
          class="px-4 py-2 text-sm font-medium text-purple-600 bg-white border border-purple-600 rounded hover:bg-purple-50 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleConfirm"
          :disabled="isLoading"
          class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <span v-if="isLoading">Processing...</span>
          <span v-else>OK</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { watch } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  message: {
    type: String,
    required: true
  },
  isLoading: {
    type: Boolean,
    default: false
  },
  showOrigin: {
    type: Boolean,
    default: false
  },
  originText: {
    type: String,
    default: 'localhost:5174'
  }
})

const emit = defineEmits(['confirm', 'cancel'])

const handleConfirm = () => {
  if (!props.isLoading) {
    emit('confirm')
  }
}

const handleCancel = () => {
  if (!props.isLoading) {
    emit('cancel')
  }
}

// Handle Escape key
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    const handleKeydown = (event) => {
      if (event.key === 'Escape' && !props.isLoading) {
        handleCancel()
      }
    }
    document.addEventListener('keydown', handleKeydown)
    return () => {
      document.removeEventListener('keydown', handleKeydown)
    }
  }
})
</script>

<style scoped>
/* Smooth fade-in animation */
.fixed.inset-0 {
  animation: fadeIn 0.2s ease-out;
}

.bg-white {
  animation: slideIn 0.2s ease-out;
}

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
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
