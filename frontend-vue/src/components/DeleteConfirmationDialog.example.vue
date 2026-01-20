<!-- 
  Example usage of DeleteConfirmationDialog component
  
  This is a reusable confirmation dialog template that matches the browser-style
  confirmation dialog design with purple buttons.
-->

<template>
  <div>
    <!-- Example: Delete single item -->
    <button @click="showDeleteSingle">Delete Item</button>
    
    <!-- Example: Delete multiple items -->
    <button @click="showDeleteMultiple">Delete Selected ({{ selectedCount }})</button>
    
    <!-- Delete Confirmation Dialog Component -->
    <DeleteConfirmationDialog
      :is-open="showDeleteDialog"
      :message="deleteDialogMessage"
      :is-loading="deleteDialogLoading"
      :show-origin="false"
      :origin-text="'localhost:5174'"
      @confirm="handleDeleteConfirm"
      @cancel="handleDeleteCancel"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import DeleteConfirmationDialog from './DeleteConfirmationDialog.vue'

// Dialog state
const showDeleteDialog = ref(false)
const deleteDialogMessage = ref('')
const deleteDialogLoading = ref(false)
const pendingDeleteAction = ref(null)

// Example: Selected items count
const selectedCount = ref(2)

// Show dialog for single item deletion
const showDeleteSingle = () => {
  deleteDialogMessage.value = 'Are you sure you want to delete this item?'
  pendingDeleteAction.value = { type: 'single', id: 123 }
  showDeleteDialog.value = true
}

// Show dialog for multiple items deletion
const showDeleteMultiple = () => {
  deleteDialogMessage.value = `Are you sure you want to delete ${selectedCount.value} item(s)?`
  pendingDeleteAction.value = { type: 'multiple', ids: [1, 2, 3] }
  showDeleteDialog.value = true
}

// Handle confirmation
const handleDeleteConfirm = async () => {
  deleteDialogLoading.value = true
  
  try {
    if (pendingDeleteAction.value.type === 'single') {
      // Perform single delete
      await deleteSingleItem(pendingDeleteAction.value.id)
    } else if (pendingDeleteAction.value.type === 'multiple') {
      // Perform multiple delete
      await deleteMultipleItems(pendingDeleteAction.value.ids)
    }
    
    // Close dialog on success
    showDeleteDialog.value = false
    pendingDeleteAction.value = null
  } catch (error) {
    console.error('Delete failed:', error)
    // Keep dialog open on error so user can retry
  } finally {
    deleteDialogLoading.value = false
  }
}

// Handle cancellation
const handleDeleteCancel = () => {
  showDeleteDialog.value = false
  pendingDeleteAction.value = null
  deleteDialogLoading.value = false
}

// Mock delete functions
const deleteSingleItem = async (id) => {
  // Your delete API call here
  return new Promise(resolve => setTimeout(resolve, 1000))
}

const deleteMultipleItems = async (ids) => {
  // Your delete API call here
  return new Promise(resolve => setTimeout(resolve, 1000))
}
</script>

<!-- 
  Component Props:
  - isOpen: Boolean - Controls dialog visibility
  - message: String (required) - The confirmation message to display
  - isLoading: Boolean - Shows loading state on OK button
  - showOrigin: Boolean - Shows "localhost:5174 says" text (optional, default: false)
  - originText: String - Custom origin text (optional, default: 'localhost:5174')
  
  Component Events:
  - @confirm - Emitted when OK button is clicked
  - @cancel - Emitted when Cancel button is clicked or Escape key is pressed
-->
