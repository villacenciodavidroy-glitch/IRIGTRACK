<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../composables/useItems'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'
import { useDebouncedRef } from '../composables/useDebounce'

const router = useRouter()
const searchQuery = ref('')
const debouncedSearchQuery = useDebouncedRef(searchQuery, 300)
const selectedCategory = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(8)
const totalItems = ref(0)

// Get items from the API using the composable
const { items, fetchitems, loading, error } = useItems()

// Store channel reference for cleanup
let inventoryChannel = null

// Fetch items when component mounts
onMounted(async () => {
  await fetchitems()
  
  // Set up real-time listener - try multiple times until connected
  const setupRealtimeListener = () => {
    if (!window.Echo) {
      console.warn('⚠️ Laravel Echo not available. Will retry...')
      setTimeout(setupRealtimeListener, 2000) // Retry in 2 seconds
      return
    }

    const pusher = window.Echo.connector?.pusher
    
    if (!pusher) {
      console.warn('⚠️ Pusher connector not found. Will retry...')
      setTimeout(setupRealtimeListener, 2000)
      return
    }

    const connectionState = pusher.connection?.state
    console.log('📡 Echo connection state:', connectionState)

    // If not connected, wait for connection
    if (connectionState !== 'connected') {
      console.log('⏳ Waiting for Echo connection...')
      
      // Listen for connection
      const connectedHandler = () => {
        console.log('✅ Pusher connected! Setting up listener...')
        setupChannelListener()
        pusher.connection.unbind('connected', connectedHandler) // Remove handler after use
      }
      
      pusher.connection.bind('connected', connectedHandler)
      
      // Also set up listener immediately in case connection happens quickly
      setTimeout(() => {
        if (pusher.connection?.state === 'connected') {
          setupChannelListener()
        }
      }, 500)
      
      return
    }

    // Already connected - set up listener immediately
    setupChannelListener()
  }

  // Function to set up the channel listener
  const setupChannelListener = () => {
    try {
      console.log('🔔 Setting up real-time inventory listener...')
      
      const currentPusher = window.Echo?.connector?.pusher
      if (currentPusher) {
        console.log('📡 Pusher connection state:', currentPusher.connection?.state)
      }
      
      // Get or create channel
      if (!inventoryChannel) {
        inventoryChannel = window.Echo.channel('inventory')
      }
      
      // Use Pusher for subscription confirmation
      const pusher = window.Echo?.connector?.pusher
      if (pusher) {
        // Listen for subscription success
        pusher.bind('pusher:subscription_succeeded', (data) => {
          if (data && (data.channel === 'inventory' || data.channel === 'private-inventory')) {
            console.log('✅✅✅ Successfully subscribed to inventory channel:', data.channel)
            console.log('🎯 Ready to receive ItemBorrowed events')
          }
        })
        
        // Listen for errors
        pusher.connection.bind('error', (err) => {
          console.error('❌ Pusher connection error:', err)
        })
        
        // Listen for disconnection
        pusher.connection.bind('disconnected', () => {
          console.warn('⚠️ Pusher disconnected')
        })
      }
      
      // Listen with dot prefix (Laravel's default format for broadcastAs)
      inventoryChannel.listen('.ItemBorrowed', (data) => {
        console.log('📦📦📦 ItemBorrowed event received (with dot) 📦📦📦')
        console.log('📦 Full event data:', JSON.stringify(data, null, 2))
        handleItemBorrowedUpdate(data)
      })
      
      // Also try without dot prefix as fallback
      inventoryChannel.listen('ItemBorrowed', (data) => {
        console.log('📦📦📦 ItemBorrowed event received (without dot) 📦📦📦')
        console.log('📦 Full event data:', JSON.stringify(data, null, 2))
        handleItemBorrowedUpdate(data)
      })
      
      // Listen using wildcard for any item-related events
      inventoryChannel.listen('.ItemUpdated', (data) => {
        console.log('📦 ItemUpdated event received:', data)
        if (data?.item) {
          handleItemBorrowedUpdate({ item: data.item })
        }
      })
      
      console.log('🎯 Listening for events:', [
        '.ItemBorrowed',
        'ItemBorrowed', 
        '.ItemUpdated',
        '* (all events)'
      ])
      
      // Listen for subscription success
      if (currentPusher) {
        currentPusher.bind('pusher:subscription_succeeded', (data) => {
          if (data && (data.channel === 'inventory' || data.channel === 'private-inventory')) {
            console.log('✅ Successfully subscribed to inventory channel:', data.channel)
          }
        })
      }
      
      console.log('✅ Real-time inventory listener active on channel: inventory')
      console.log('🧪 Listener setup complete. Waiting for ItemBorrowed events...')
      
      // Test event reception after a delay
      setTimeout(() => {
        console.log('🧪 Testing event reception...')
        console.log('   Channel:', inventoryChannel ? 'exists' : 'missing')
        console.log('   Echo connection:', window.Echo?.connector?.pusher?.connection?.state)
        console.log('   Current items count:', items.value.length)
        console.log('   Supply items count:', consumableItems.value.length)
      }, 2000)
      
    } catch (error) {
      console.error('❌ Error setting up channel listener:', error)
      // Retry after a delay
      setTimeout(setupRealtimeListener, 3000)
    }
  }

  // Start setup - try immediately, then retry if needed
  setTimeout(setupRealtimeListener, 500) // Wait 500ms for Echo to initialize
})

// Handler function for ItemBorrowed updates
const handleItemBorrowedUpdate = (data) => {
  console.log('📦 Processing ItemBorrowed update:', data)
  console.log('📦 Full event data:', JSON.stringify(data, null, 2))
  
  // Find the item in the items array and update its quantity
  if (data && data.item && data.item.uuid) {
    const uuid = data.item.uuid
    const newQuantity = parseInt(data.item.quantity, 10) || 0
    const itemCategory = data.item.category || (data.item.category_name || '').toLowerCase()
    
    console.log(`🔍 Looking for item with UUID: ${uuid}, New quantity: ${newQuantity}, Category: ${itemCategory}`)
    console.log(`📋 Current items array has ${items.value.length} items`)
    
    // Log first few items for debugging
    console.log('📋 Sample items:', items.value.slice(0, 3).map(i => ({ 
      uuid: i.uuid, 
      category: i.category || 'no-category',
      article: i.unit || i.description 
    })))
    
    // Find item by UUID
    const itemIndex = items.value.findIndex(item => item.uuid === uuid)
    
    if (itemIndex !== -1) {
      const item = items.value[itemIndex]
      const oldQuantity = item.quantity
      const currentCategory = item.category || 'no-category'
      
      console.log(`✅ Found item at index ${itemIndex}`)
      console.log(`   Current category: ${currentCategory}, Event category: ${itemCategory}`)
      console.log(`   Updating quantity from ${oldQuantity} to ${newQuantity}`)
      
      // Create a new array with updated item to ensure Vue reactivity
      const updatedItems = [...items.value]
      updatedItems[itemIndex] = {
        ...updatedItems[itemIndex],
        quantity: newQuantity
      }
      
      // Also update category if provided in event (in case it changed)
      if (data.item.category) {
        updatedItems[itemIndex].category = data.item.category
      }
      
      // Replace the entire array to trigger reactivity
      items.value = updatedItems
      
      console.log(`✅ Updated item ${uuid} quantity from ${oldQuantity} to ${newQuantity} in real-time`)
      console.log(`✅ Item category: ${updatedItems[itemIndex].category || 'no-category'}`)
      
      // Force reactivity update for Supply items
      // Since consumableItems is computed from inventoryItems which is computed from items,
      // updating items.value should automatically update everything, but let's verify
      const isSupplyItem = itemCategory?.toLowerCase() === 'supply' || 
                          currentCategory?.toLowerCase() === 'supply' ||
                          itemCategory?.toLowerCase() === 'supplies'
      
      if (isSupplyItem) {
        console.log('📦 This is a Supply item - ensuring Supply table updates')
        
        // Check if item appears in Supply items computed property
        const supplyItem = consumableItems.value.find(i => i.uuid === uuid)
        if (supplyItem) {
          console.log(`✅ Item found in Supply items table, quantity: ${supplyItem.quantity}`)
          // Force a reactive update by touching the computed
          // The computed should already be updated since we updated items.value
          // But Vue might need a small nudge
          const temp = consumableItems.value.length
          console.log(`✅ Supply items count: ${temp}`)
        } else {
          console.warn(`⚠️ Item not found in Supply items table`)
          console.warn(`   Checking category filter: "${currentCategory}" vs "supply"`)
          console.warn(`   All categories in items:`, [...new Set(items.value.map(i => i.category || 'no-category'))])
        }
        
        // Force a refresh of computed properties by triggering a watch
        // This ensures the UI updates even if Vue's reactivity didn't catch it
        nextTick(() => {
          console.log('🔄 Checking Supply table after nextTick...')
          const afterTickItem = consumableItems.value.find(i => i.uuid === uuid)
          if (afterTickItem) {
            console.log(`✅ After nextTick: Supply item quantity is ${afterTickItem.quantity}`)
          } else {
            console.warn(`⚠️ After nextTick: Still not found in Supply table`)
          }
        })
      }
      
      if (data.borrowed_quantity) {
        console.log(`📊 Borrowed: ${data.borrowed_quantity} by ${data.borrowed_by || 'Unknown'}`)
      }
      
      // Verify the update worked
      const verifyItem = items.value.find(item => item.uuid === uuid)
      if (verifyItem) {
        console.log(`✅ Verification: Item ${uuid} now has quantity: ${verifyItem.quantity}`)
        console.log(`✅ Verification: Category is: ${verifyItem.category || 'no-category'}`)
      } else {
        console.error(`❌ Verification failed: Item ${uuid} not found after update!`)
      }
      
    } else {
      console.warn(`⚠️ Item with UUID ${uuid} not found in current items list`)
      console.log('📋 Available UUIDs:', items.value.slice(0, 5).map(i => ({ 
        uuid: i.uuid, 
        category: i.category || 'no-category',
        article: i.unit || i.description 
      })))
      
      // Try to find by other identifiers
      const byId = items.value.findIndex(item => item.id === data.item.id)
      if (byId !== -1) {
        console.log(`🔍 Found by ID instead, updating...`)
        const updatedItems = [...items.value]
        updatedItems[byId].quantity = newQuantity
        items.value = updatedItems
        console.log(`✅ Updated item by ID`)
      } else {
        // Refresh the items list to get the latest data
        console.log('🔄 Item not found - refreshing items list...')
        fetchitems().then(() => {
          console.log('✅ Items list refreshed after borrow event')
        })
      }
    }
  } else {
    console.error('❌ Invalid event data received:', data)
    console.error('Expected structure: { item: { uuid: "...", quantity: ... }, borrowed_quantity: ..., borrowed_by: ... }')
    console.error('Received data keys:', data ? Object.keys(data) : 'null')
    if (data?.item) {
      console.error('Item data keys:', Object.keys(data.item))
      console.error('Item data:', data.item)
    }
  }
}

// Clean up Echo listeners when component is unmounted
onUnmounted(() => {
  if (window.Echo && inventoryChannel) {
    console.log('🔇 Removing real-time inventory listener...')
    try {
      window.Echo.leave('inventory')
      inventoryChannel = null
      console.log('✅ Real-time inventory listener removed')
    } catch (error) {
      console.error('Error removing listener:', error)
    }
  }
})

// Map API data to the format expected by the table
const inventoryItems = computed(() => {
  return items.value.map(item => ({
    qrCode: item.qr_code_image || '/images/qr-sample.png',
    image: item.image_path || '/images/default.jpg',
    article: item.unit || '',
    category: item.category || 'Inventory',
    description: item.description || '',
    propertyAccountCode: item.pac || '',
    unitValue: item.unit_value || '',
    dateAcquired: item.date_acquired || '',
    poNumber: item.po_number || '',
    location: item.location || '',
    condition: item.condition || '',
    conditionStatus: item.condition_status || null,
    issuedTo: item.issued_to || 'Not Assigned',
    actions: ['edit', 'delete'],
    id: item.id, // Keep the original ID for reference
    uuid: item.uuid, // Keep the UUID for API operations
    quantity: item.quantity,
    createdAt: item.created_at || null, // For sorting by newest
    updatedAt: item.updated_at || null // Fallback for sorting
  }))
})

// Non-consumable items for the main inventory table
const nonConsumableItems = computed(() => {
  return inventoryItems.value.filter(i => (i.category || '').toLowerCase() !== 'consumables')
})

// Get unique categories for filter dropdown
const uniqueCategories = computed(() => {
  const categories = new Set()
  nonConsumableItems.value.forEach(item => {
    const category = item.category || 'Uncategorized'
    if (category && category.trim()) {
      categories.add(category)
    }
  })
  return Array.from(categories).sort()
})

const filteredItems = computed(() => {
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  let result = nonConsumableItems.value
  
  // Filter by category first
  if (selectedCategory.value) {
    result = result.filter(item => {
      const itemCategory = (item.category || '').trim()
      return itemCategory === selectedCategory.value
    })
  }
  
  // Then filter by search query
  if (query) {
    // Optimize search: only search relevant fields
    result = result.filter(item => {
      return (
        (item.article || '').toLowerCase().includes(query) ||
        (item.description || '').toLowerCase().includes(query) ||
        (item.category || '').toLowerCase().includes(query) ||
        (item.propertyAccountCode || '').toLowerCase().includes(query) ||
        (item.location || '').toLowerCase().includes(query)
      )
    })
  }
  
  // Sort: If category is selected, sort by newest first. Otherwise, sort alphabetically
  if (selectedCategory.value) {
    // Sort by newest first (most recently added/updated) when category is selected
    return result.sort((a, b) => {
      // First try to sort by created_at (newest first)
      if (a.createdAt && b.createdAt) {
        const dateA = new Date(a.createdAt)
        const dateB = new Date(b.createdAt)
        return dateB - dateA // Descending order (newest first)
      }
      
      // If created_at not available, try updated_at
      if (a.updatedAt && b.updatedAt) {
        const dateA = new Date(a.updatedAt)
        const dateB = new Date(b.updatedAt)
        return dateB - dateA // Descending order (newest first)
      }
      
      // Fallback: sort by ID (higher ID = newer item, assuming auto-increment)
      const idA = a.id || 0
      const idB = b.id || 0
      return idB - idA // Descending order (newest first)
    })
  } else {
    // Sort alphabetically by article when no category is selected
    return result.sort((a, b) => {
      const articleA = (a.article || '').toLowerCase()
      const articleB = (b.article || '').toLowerCase()
      return articleA.localeCompare(articleB)
    })
  }
})

// Update total items based on filtered results
const totalFilteredItems = computed(() => {
  return filteredItems.value.length
})

// Reset to first page when search query or category changes
watch(debouncedSearchQuery, () => {
  currentPage.value = 1
})

watch(selectedCategory, () => {
  currentPage.value = 1
})

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredItems.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(totalFilteredItems.value / itemsPerPage.value))

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Add method to change items per page
const changeItemsPerPage = (newValue) => {
  itemsPerPage.value = Number(newValue)
  currentPage.value = 1 // Reset to first page when changing items per page
}

// Separate view for Supply category
// Handle multiple variations: "Supply", "Supplies", "supply", etc.
const consumableItems = computed(() => {
  return inventoryItems.value.filter(i => {
    const category = (i.category || '').toLowerCase().trim()
    return category === 'supply' || category === 'supplies'
  })
})

const filteredConsumableItems = computed(() => {
  const query = debouncedSearchQuery.value?.toLowerCase().trim()
  let result = consumableItems.value
  
  if (query) {
    // Optimize search: only search relevant fields
    result = consumableItems.value.filter(item => {
      return (
        (item.article || '').toLowerCase().includes(query) ||
        (item.description || '').toLowerCase().includes(query) ||
        (item.category || '').toLowerCase().includes(query) ||
        (item.propertyAccountCode || '').toLowerCase().includes(query) ||
        (item.location || '').toLowerCase().includes(query)
      )
    })
  }
  
  // Sort by newest first (most recently added/updated)
  return result.sort((a, b) => {
    // First try to sort by created_at (newest first)
    if (a.createdAt && b.createdAt) {
      const dateA = new Date(a.createdAt)
      const dateB = new Date(b.createdAt)
      return dateB - dateA // Descending order (newest first)
    }
    
    // If created_at not available, try updated_at
    if (a.updatedAt && b.updatedAt) {
      const dateA = new Date(a.updatedAt)
      const dateB = new Date(b.updatedAt)
      return dateB - dateA // Descending order (newest first)
    }
    
    // Fallback: sort by ID (higher ID = newer item, assuming auto-increment)
    const idA = a.id || 0
    const idB = b.id || 0
    return idB - idA // Descending order (newest first)
  })
})

const currentConsumablePage = ref(1)
const itemsPerConsumablePage = ref(8)
const totalConsumableFilteredItems = computed(() => filteredConsumableItems.value.length)
const totalConsumablePages = computed(() => Math.ceil(totalConsumableFilteredItems.value / itemsPerConsumablePage.value) || 1)
const paginatedConsumableItems = computed(() => {
  const start = (currentConsumablePage.value - 1) * itemsPerConsumablePage.value
  const end = start + itemsPerConsumablePage.value
  return filteredConsumableItems.value.slice(start, end)
})
const goToConsumablePage = (page) => {
  if (page >= 1 && page <= totalConsumablePages.value) {
    currentConsumablePage.value = page
  }
}
const changeConsumableItemsPerPage = (newValue) => {
  itemsPerConsumablePage.value = Number(newValue)
  currentConsumablePage.value = 1
}

const goToAddItem = () => {
  router.push('/add-item')
}

const goToCategories = () => {
  router.push('/categories')
}

const goToLocations = () => {
  router.push('/locations')
}

const goToAddSupply = () => {
  router.push('/inventory/add').catch(err => {
    console.error('Navigation error:', err)
  })
}

// Handle edit action
const editItem = (item) => {
  // Navigate to edit page using the UUID
  router.push(`/edit-item/${item.uuid}`)
}

// Create a separate loading state for delete operations
const deleteLoading = ref(false)
const itemBeingDeleted = ref(null)

// Show delete confirmation modal
const showDeleteModal = ref(false)
const itemToDelete = ref(null)
const deleteReason = ref('')

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Open delete modal
const openDeleteModal = (item) => {
  itemToDelete.value = item
  deleteReason.value = ''
  showDeleteModal.value = true
}

// Close delete modal
const closeDeleteModal = () => {
  showDeleteModal.value = false
  itemToDelete.value = null
  deleteReason.value = ''
}

// QR Code Preview Modal
const showQrPreviewModal = ref(false)
const selectedQrItem = ref(null)

// Open QR preview modal
const openQrPreviewModal = (item) => {
  selectedQrItem.value = item
  showQrPreviewModal.value = true
}

// Close QR preview modal
const closeQrPreviewModal = () => {
  showQrPreviewModal.value = false
  selectedQrItem.value = null
}

// Print QR code
const printQrCode = () => {
  if (!selectedQrItem.value) return
  
  // Get item data
  const item = selectedQrItem.value
  const article = item.article || 'Item'
  const pac = item.propertyAccountCode || 'N/A'
  const qrCode = item.qrCode || '/images/qr-sample.png'
  
  // Create a new window for printing
  const printWindow = window.open('', '_blank')
  
  // Create the content for the print window with 2x2 format (4 QR codes per page)
  const content = '<!DOCTYPE html>' +
    '<html>' +
    '<head>' +
    '<title>QR Code - ' + article + '</title>' +
    '<style>' +
    '@page { size: A4; margin: 10mm; }' +
    '* { margin: 0; padding: 0; box-sizing: border-box; }' +
    'body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: white; padding: 0; }' +
    '.print-container { width: 100%; height: 100vh; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 0; page-break-inside: avoid; }' +
    '.qr-item { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px; border: 2px solid #10b981; page-break-inside: avoid; }' +
    '.qr-item:nth-child(1) { border-right: 1px solid #10b981; border-bottom: 1px solid #10b981; }' +
    '.qr-item:nth-child(2) { border-left: 1px solid #10b981; border-bottom: 1px solid #10b981; }' +
    '.qr-item:nth-child(3) { border-right: 1px solid #10b981; border-top: 1px solid #10b981; }' +
    '.qr-item:nth-child(4) { border-left: 1px solid #10b981; border-top: 1px solid #10b981; }' +
    '.qr-wrapper { background: white; border: 2px solid #10b981; border-radius: 8px; padding: 15px; display: flex; justify-content: center; align-items: center; margin-bottom: 10px; width: 100%; max-width: 200px; }' +
    '.qr-code { width: 100%; height: auto; max-width: 180px; max-height: 180px; object-fit: contain; display: block; }' +
    '.item-title { font-size: 14px; font-weight: 700; color: #1f2937; text-align: center; margin-bottom: 5px; word-wrap: break-word; max-width: 200px; }' +
    '.item-pac { font-size: 12px; font-weight: 600; color: #374151; text-align: center; font-family: monospace; }' +
    '@media print { body { margin: 0; padding: 0; } .print-container { width: 100%; height: 100%; } .qr-item { page-break-inside: avoid; } }' +
    '</style>' +
    '</head>' +
    '<body>' +
    '<div class="print-container">' +
    '<div class="qr-item">' +
    '<div class="qr-wrapper">' +
    '<img src="' + qrCode + '" alt="QR Code" class="qr-code">' +
    '</div>' +
    '<div class="item-title">' + article + '</div>' +
    '<div class="item-pac">' + pac + '</div>' +
    '</div>' +
    '<div class="qr-item">' +
    '<div class="qr-wrapper">' +
    '<img src="' + qrCode + '" alt="QR Code" class="qr-code">' +
    '</div>' +
    '<div class="item-title">' + article + '</div>' +
    '<div class="item-pac">' + pac + '</div>' +
    '</div>' +
    '<div class="qr-item">' +
    '<div class="qr-wrapper">' +
    '<img src="' + qrCode + '" alt="QR Code" class="qr-code">' +
    '</div>' +
    '<div class="item-title">' + article + '</div>' +
    '<div class="item-pac">' + pac + '</div>' +
    '</div>' +
    '<div class="qr-item">' +
    '<div class="qr-wrapper">' +
    '<img src="' + qrCode + '" alt="QR Code" class="qr-code">' +
    '</div>' +
    '<div class="item-title">' + article + '</div>' +
    '<div class="item-pac">' + pac + '</div>' +
    '</div>' +
    '</div>' +
    '<script>' +
    'window.onload = function() { setTimeout(function() { window.print(); }, 250); };' +
    '<' + '/script>' +
    '</body>' +
    '</html>'
  
  // Write the content to the new window
  printWindow.document.open()
  printWindow.document.write(content)
  printWindow.document.close()
  
  // Also trigger print after a short delay to ensure content is loaded
  setTimeout(() => {
    if (printWindow && !printWindow.closed) {
      printWindow.focus()
    }
  }, 500)
}

// Handle delete action
const deleteItem = async () => {
  if (!itemToDelete.value) return
  
  try {
    // Set loading state
    deleteLoading.value = true
    itemBeingDeleted.value = itemToDelete.value.id
    
    console.log('Deleting item with UUID:', itemToDelete.value.uuid)
    
    // Call the delete API with deletion reason
    const response = await axiosClient.delete(`/items/delete/${itemToDelete.value.uuid}`, {
      data: {
        deletion_reason: deleteReason.value || 'User initiated deletion'
      }
    })
    
    console.log('Delete response:', response.data)
    
    // Show success message
    successMessage.value = response.data?.message || 'Item deleted successfully'
    successModalType.value = 'success'
    showSuccessModal.value = true
    
    // Refresh the items list
    await fetchitems()
  } catch (error) {
    // Log detailed error information
    console.error('Error deleting item:', error)
    
    // Show error message
    if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to delete item. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    deleteLoading.value = false
    itemBeingDeleted.value = null
    closeDeleteModal()
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">inventory_2</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Inventory Management</h1>
              <p class="text-green-100 text-base sm:text-lg">Comprehensive Asset Tracking and Management System</p>
            </div>
          </div>
          <div class="flex items-center gap-3 w-full sm:w-auto flex-wrap">
            <button @click="goToCategories" class="btn-secondary-enhanced flex-1 sm:flex-auto justify-center">
              <span class="material-icons-outlined text-lg mr-1.5">category</span>
              <span>Categories</span>
            </button>
            <button @click="goToLocations" class="btn-secondary-enhanced flex-1 sm:flex-auto justify-center">
              <span class="material-icons-outlined text-lg mr-1.5">location_on</span>
              <span>Locations</span>
            </button>
            <button @click="goToAddItem" class="btn-primary-enhanced flex-1 sm:flex-auto justify-center shadow-lg">
              <span class="material-icons-outlined text-lg mr-1.5">add_circle</span>
              <span>Add New Item</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-base font-medium text-gray-600 dark:text-gray-400 mb-1">Total Items</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalFilteredItems }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">inventory</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-base font-medium text-gray-600 dark:text-gray-400 mb-1">Supply Items</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalConsumableFilteredItems }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-2xl">local_shipping</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-base font-medium text-gray-600 dark:text-gray-400 mb-1">Current Page</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ currentPage }} / {{ totalPages || 1 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-purple-400 dark:text-purple-400 text-2xl">description</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Search Bar -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border border-gray-200 dark:border-gray-700 p-4">
      <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
        <div class="flex flex-col sm:flex-row gap-3 flex-1">
          <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">search</span>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search by article, description, category, PAC, or location..."
              class="w-full pl-12 pr-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium text-base"
            >
            <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
              <button @click="searchQuery = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons-outlined text-lg">close</span>
              </button>
            </div>
          </div>
          <div class="relative flex-shrink-0 sm:w-64">
            <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">category</span>
            </div>
            <select
              v-model="selectedCategory"
              class="w-full pl-12 pr-10 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium text-base appearance-none cursor-pointer"
            >
              <option value="">All Categories</option>
              <option v-for="category in uniqueCategories" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
              <span class="material-icons-outlined text-gray-400 dark:text-gray-500">arrow_drop_down</span>
            </div>
            <div v-if="selectedCategory" class="absolute inset-y-0 right-8 flex items-center pointer-events-auto">
              <button @click="selectedCategory = ''" class="p-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-300 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <span class="material-icons-outlined text-lg">close</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Inventory Table Container -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">warehouse</span>
            <h2 class="text-xl font-bold text-white">Inventory Items</h2>
          </div>
          <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
            <span class="text-base font-semibold text-white">{{ totalFilteredItems }} items</span>
          </div>
        </div>
      </div>
      <!-- Mobile View (Card Layout) -->
      <div class="block sm:hidden">
        <!-- Loading indicator -->
        <div v-if="loading" class="flex justify-center items-center py-10">
          <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
        </div>
        
        <!-- Error state -->
        <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-red-400">error_outline</span>
          <p class="mt-2 text-base text-red-500">{{ error }}</p>
          <button 
            @click="fetchitems" 
            class="mt-4 px-4 py-2 text-base bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Try Again
          </button>
        </div>
        
        <!-- Empty state -->
        <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
          <p class="mt-2 text-base text-gray-500">No inventory items found</p>
          <p v-if="searchQuery" class="text-base text-gray-400">Try adjusting your search query</p>
        </div>
        
        <!-- Enhanced Card layout for mobile -->
        <div v-else class="p-4 space-y-4">
          <div 
            v-for="item in paginatedItems" 
            :key="item.id || item.propertyAccountCode" 
            class="group bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-green-400 shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 p-4 overflow-hidden relative"
          >
            <div class="absolute top-0 right-0 w-20 h-20 bg-green-500/10 dark:bg-green-500/10 rounded-bl-full"></div>
            <div class="relative flex items-start gap-4 mb-3">
              <!-- Item image -->
              <div class="flex-shrink-0">
                <div class="relative">
                  <img :src="item.image" alt="Item" class="h-16 w-16 object-cover rounded-xl border-2 border-gray-300 dark:border-gray-600 shadow-md group-hover:border-green-400 transition-colors">
                  <div class="absolute -top-1 -right-1 bg-green-500 text-white rounded-full p-1 shadow-md">
                    <span class="material-icons-outlined text-xs">check_circle</span>
                  </div>
                </div>
              </div>
              
              <!-- Item details -->
              <div class="flex-1 min-w-0">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate mb-1">{{ item.article }}</h3>
                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 line-clamp-2 mb-2">{{ item.description }}</p>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="px-2 py-0.5 text-sm font-semibold rounded-full" style="background-color: #01200E; color: #FFFFFF;">{{ item.category }}</span>
                  <span class="px-2 py-0.5 bg-blue-900 dark:bg-blue-900 text-sm font-semibold rounded-full" style="color: #FFFFFF;">{{ item.condition }}</span>
                  <span v-if="item.conditionStatus" 
                        :class="[
                          'px-2 py-0.5 text-xs font-semibold rounded-full',
                          item.conditionStatus === 'Good' ? 'bg-green-600 text-white' :
                          item.conditionStatus === 'Less Reliable' ? 'bg-yellow-600 text-white' :
                          item.conditionStatus === 'Un-operational' ? 'bg-orange-600 text-white' :
                          item.conditionStatus === 'Disposal' ? 'bg-red-600 text-white' :
                          'bg-gray-600 text-white'
                        ]">
                    {{ item.conditionStatus }}
                  </span>
                  <span v-if="item.quantity" class="px-2 py-0.5 bg-purple-900 dark:bg-purple-900 text-sm font-semibold rounded-full" style="color: #FFFFFF;">Qty: {{ item.quantity }}</span>
                </div>
              </div>
              
              <!-- QR Code -->
              <div 
                class="flex-shrink-0 cursor-pointer transition-all duration-300 hover:scale-110 hover:rotate-3"
                @click="openQrPreviewModal(item)"
              >
                <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg border-2 border-gray-300 dark:border-gray-600 shadow-sm">
                  <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain">
                </div>
              </div>
            </div>
            
            <!-- Additional Info -->
            <div class="grid grid-cols-2 gap-2 mb-3 pt-3 border-t border-gray-200 dark:border-gray-700">
              <div class="text-sm">
                <span class="font-semibold text-gray-600 dark:text-gray-400">Location:</span>
                <span class="text-gray-900 dark:text-white ml-1">{{ item.location || 'N/A' }}</span>
              </div>
              <div class="text-sm">
                <span class="font-semibold text-gray-600 dark:text-gray-400">PAC:</span>
                <span class="text-gray-900 dark:text-white ml-1">{{ item.propertyAccountCode || 'N/A' }}</span>
              </div>
            </div>
            
            <!-- Action buttons -->
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
              <button 
                @click="editItem(item)" 
                style="background: linear-gradient(to right, #009832, #007a28);"
                class="px-4 py-2 rounded-lg text-white hover:opacity-90 shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1.5 font-medium text-base"
                title="Edit item"
              >
                <span class="material-icons-outlined text-sm">edit</span>
                <span>Edit</span>
              </button>
              <button 
                @click="openDeleteModal(item)" 
                class="px-4 py-2 rounded-lg bg-gradient-to-r from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200 flex items-center gap-1.5 font-medium text-sm"
                title="Delete item"
                :disabled="deleteLoading && itemBeingDeleted === item.id"
              >
                <span v-if="!(deleteLoading && itemBeingDeleted === item.id)" class="material-icons-outlined text-sm">delete</span>
                <span v-else class="material-icons-outlined text-sm animate-spin">refresh</span>
                <span>{{ deleteLoading && itemBeingDeleted === item.id ? 'Deleting...' : 'Delete' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Desktop View (Table Layout) -->
      <div class="hidden sm:block overflow-x-auto">
        <!-- Loading indicator -->
        <div v-if="loading" class="flex justify-center items-center py-10">
          <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
        </div>
        
        <!-- Error state -->
        <div v-else-if="error" class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-red-400">error_outline</span>
          <p class="mt-2 text-base text-red-500">{{ error }}</p>
          <button 
            @click="fetchitems" 
            class="mt-4 px-4 py-2 text-base bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Try Again
          </button>
        </div>
        
        <!-- Empty state -->
        <div v-else-if="paginatedItems.length === 0" class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
          <p class="mt-2 text-base text-gray-500">No inventory items found</p>
          <p v-if="searchQuery" class="text-base text-gray-400">Try adjusting your search query</p>
        </div>
        
        <!-- Enhanced Table with data -->
        <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 whitespace-nowrap">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-700 w-12 px-4 py-4 border-r border-gray-300 dark:border-gray-600">
                <input type="checkbox" class="w-4 h-4 rounded border-gray-500 dark:border-gray-500 text-green-600 focus:ring-green-500 focus:ring-2 cursor-pointer bg-gray-600 dark:bg-gray-600">
              </th>
              <th class="min-w-[90px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">QR CODE</th>
              <th class="min-w-[90px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">IMAGE</th>
              <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">ARTICLE</th>
              <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CATEGORY</th>
              <th class="min-w-[220px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">DESCRIPTION</th>
              <th class="min-w-[100px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">QUANTITY</th>
              <th class="min-w-[180px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">PROPERTY ACCOUNT CODE</th>
              <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">UNIT VALUE</th>
              <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">DATE ACQUIRED</th>
              <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">P.O. NUMBER</th>
              <th class="min-w-[160px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">LOCATION</th>
              <th class="min-w-[130px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CONDITION</th>
              <th class="min-w-[140px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">STATUS</th>
              <th class="min-w-[160px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">ISSUED TO</th>
              <th class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-700 min-w-[120px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider">ACTIONS</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="(item, index) in paginatedItems" :key="item.id || item.propertyAccountCode" 
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500">
              <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 group-hover:bg-gray-700 dark:group-hover:bg-gray-700 px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <input type="checkbox" class="w-4 h-4 rounded border-gray-500 dark:border-gray-500 text-green-600 focus:ring-green-500 focus:ring-2 cursor-pointer bg-gray-600 dark:bg-gray-600">
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div 
                  class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-green-500 rounded-lg overflow-hidden inline-block p-1 bg-gray-50 dark:bg-gray-700"
                  @click="openQrPreviewModal(item)"
                  title="View QR Code"
                >
                  <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain">
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-blue-500 rounded-lg overflow-hidden inline-block p-1 bg-gray-50 dark:bg-gray-700">
                  <img :src="item.image" alt="Item" class="h-10 w-10 object-cover rounded">
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base font-semibold text-gray-900 dark:text-white truncate max-w-[130px]" :title="item.article">
                  {{ item.article }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold" style="background-color: #01200E; color: #FFFFFF;">
                  {{ item.category }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[220px]" :title="item.description">
                  {{ item.description }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-base font-bold bg-purple-900 dark:bg-purple-900" style="color: #FFFFFF;">
                  {{ item.quantity || '0' }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base font-mono text-gray-700 dark:text-gray-300 truncate max-w-[180px]" :title="item.propertyAccountCode">
                  {{ item.propertyAccountCode }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base font-medium text-gray-700 dark:text-gray-300 truncate max-w-[130px]" :title="item.unitValue">
                  {{ item.unitValue }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-600 dark:text-gray-400 truncate max-w-[130px]" :title="item.dateAcquired">
                  {{ item.dateAcquired }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-600 dark:text-gray-400 truncate max-w-[130px]" :title="item.poNumber">
                  {{ item.poNumber }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[160px]" :title="item.location">
                  <span class="material-icons-outlined text-base align-middle mr-1 text-gray-600 dark:text-gray-400">location_on</span>
                  {{ item.location }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold bg-blue-900 dark:bg-blue-900" style="color: #FFFFFF;">
                  {{ item.condition }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span v-if="item.conditionStatus" 
                      :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold',
                        item.conditionStatus === 'Good' ? 'bg-green-600 text-white' :
                        item.conditionStatus === 'Less Reliable' ? 'bg-yellow-600 text-white' :
                        item.conditionStatus === 'Un-operational' ? 'bg-orange-600 text-white' :
                        item.conditionStatus === 'Disposal' ? 'bg-red-600 text-white' :
                        'bg-gray-600 text-white'
                      ]">
                  {{ item.conditionStatus }}
                </span>
                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-400 text-white">
                  N/A
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[160px]" :title="item.issuedTo">
                  {{ item.issuedTo }}
                </div>
              </td>
              <td class="sticky right-0 z-10 bg-white dark:bg-gray-800 group-hover:bg-gray-700 dark:group-hover:bg-gray-700 px-4 py-3">
                <div class="flex justify-center gap-2">
                  <button 
                    @click="editItem(item)" 
                    style="background: linear-gradient(to bottom right, #009832, #007a28);"
                    class="p-2 rounded-lg text-white hover:opacity-90 shadow-md hover:shadow-lg transition-all duration-200"
                    title="Edit item"
                  >
                    <span class="material-icons-outlined text-sm">edit</span>
                  </button>
                  <button 
                    @click="openDeleteModal(item)" 
                    class="p-2 rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200"
                    title="Delete item"
                    :disabled="deleteLoading && itemBeingDeleted === item.id"
                  >
                    <span v-if="!(deleteLoading && itemBeingDeleted === item.id)" class="material-icons-outlined text-sm">delete</span>
                    <span v-else class="material-icons-outlined text-sm animate-spin">refresh</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Enhanced Pagination -->
      <div v-if="!loading && totalFilteredItems > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
          <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-lg">info</span>
              <span class="text-base font-semibold text-gray-900 dark:text-white">
                Showing <span class="text-green-400 dark:text-green-400 font-bold">{{ (currentPage - 1) * itemsPerPage + 1 }}</span> to 
                <span class="text-green-400 dark:text-green-400 font-bold">{{ Math.min(currentPage * itemsPerPage, totalFilteredItems) }}</span> of 
                <span class="text-green-400 dark:text-green-400 font-bold">{{ totalFilteredItems }}</span> items
              </span>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-base font-medium text-gray-900 dark:text-white">Items per page:</label>
              <select 
                v-model="itemsPerPage" 
                @change="changeItemsPerPage($event.target.value)"
                class="bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg px-3 py-1.5 text-base font-medium focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm hover:shadow-md transition-shadow"
              >
                <option value="8">8</option>
                <option value="16">16</option>
                <option value="24">24</option>
                <option value="32">32</option>
              </select>
            </div>
          </div>
          <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
            <button 
              @click="goToPage(1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">first_page</span>
            </button>
            <button 
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_left</span>
            </button>
            <div class="flex items-center gap-1">
              <template v-for="page in totalPages" :key="page">
                <button 
                  v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                  @click="goToPage(page)"
                  :class="[
                    'px-3 py-2 text-base font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                    currentPage === page 
                      ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                      : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                  ]"
                >
                  {{ page }}
                </button>
                <span 
                  v-else-if="page === currentPage - 2 || page === currentPage + 2"
                  class="px-2 text-gray-600 dark:text-gray-400"
                >...</span>
              </template>
            </div>
            <button 
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">chevron_right</span>
            </button>
            <button 
              @click="goToPage(totalPages)"
              :disabled="currentPage === totalPages"
              class="px-3 py-2 text-base font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
            >
              <span class="material-icons-outlined text-base align-middle">last_page</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Supply Category Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden mt-8">
      <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 border-b border-blue-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">local_shipping</span>
            <h2 class="text-xl font-bold text-white">Supply Items</h2>
          </div>
          <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
            <span class="text-sm font-semibold text-white">{{ totalConsumableFilteredItems }} items</span>
          </div>
        </div>
      </div>

      <div class="hidden sm:block overflow-x-auto">
        <table v-if="paginatedConsumableItems.length" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 whitespace-nowrap">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-700 w-12 px-4 py-4 border-r border-gray-300 dark:border-gray-600">
                <input type="checkbox" class="w-4 h-4 rounded border-gray-500 dark:border-gray-500 text-green-600 focus:ring-green-500 focus:ring-2 cursor-pointer bg-gray-600 dark:bg-gray-600">
              </th>
              <th class="min-w-[90px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">QR CODE</th>
              <th class="min-w-[90px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">IMAGE</th>
              <th class="min-w-[160px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">ARTICLE</th>
              <th class="min-w-[120px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CATEGORY</th>
              <th class="min-w-[240px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">DESCRIPTION</th>
              <th class="min-w-[100px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">QUANTITY</th>
              <th class="min-w-[120px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">UNIT VALUE</th>
              <th class="min-w-[140px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">DATE ACQUIRED</th>
              <th class="min-w-[140px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">LOCATION</th>
              <th class="min-w-[140px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">STATUS</th>
              <th class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-700 min-w-[120px] px-4 py-4 text-left text-sm font-bold text-gray-700 dark:text-white uppercase tracking-wider">ACTIONS</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="item in paginatedConsumableItems" :key="item.id || item.propertyAccountCode" 
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-blue-500">
              <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 group-hover:bg-gray-700 dark:group-hover:bg-gray-700 px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <input type="checkbox" class="w-4 h-4 rounded border-gray-500 dark:border-gray-500 text-green-600 focus:ring-green-500 focus:ring-2 cursor-pointer bg-gray-600 dark:bg-gray-600">
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div 
                  class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-green-500 rounded-lg overflow-hidden inline-block p-1 bg-gray-50 dark:bg-gray-700"
                  @click="openQrPreviewModal(item)"
                  title="View QR Code"
                >
                  <img :src="item.qrCode" alt="QR Code" class="h-10 w-10 object-contain">
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="cursor-pointer transition-all duration-300 hover:scale-125 hover:border-2 hover:border-blue-500 rounded-lg overflow-hidden inline-block p-1 bg-gray-50 dark:bg-gray-700">
                  <img :src="item.image" alt="Item" class="h-10 w-10 object-cover rounded">
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base font-semibold text-gray-900 dark:text-white truncate max-w-[160px]" :title="item.article">{{ item.article }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold" style="background-color: #01200E; color: #FFFFFF;">
                  {{ item.category }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[240px]" :title="item.description">{{ item.description }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-base font-bold bg-purple-900 dark:bg-purple-900" style="color: #FFFFFF;">
                  {{ item.quantity || '0' }}
                </span>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base font-medium text-gray-700 dark:text-gray-300">{{ item.unitValue }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-600 dark:text-gray-400">{{ item.dateAcquired }}</div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <div class="text-base text-gray-700 dark:text-gray-300 truncate max-w-[140px]" :title="item.location">
                  <span class="material-icons-outlined text-base align-middle mr-1 text-gray-600 dark:text-gray-400">location_on</span>
                  {{ item.location }}
                </div>
              </td>
              <td class="px-4 py-3 border-r border-gray-300 dark:border-gray-600">
                <span v-if="item.conditionStatus" 
                      :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold',
                        item.conditionStatus === 'Good' ? 'bg-green-600 text-white' :
                        item.conditionStatus === 'Less Reliable' ? 'bg-yellow-600 text-white' :
                        item.conditionStatus === 'Un-operational' ? 'bg-orange-600 text-white' :
                        item.conditionStatus === 'Disposal' ? 'bg-red-600 text-white' :
                        'bg-gray-600 text-white'
                      ]">
                  {{ item.conditionStatus }}
                </span>
                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-400 text-white">
                  N/A
                </span>
              </td>
              <td class="sticky right-0 z-10 bg-white dark:bg-gray-800 group-hover:bg-gray-700 dark:group-hover:bg-gray-700 px-4 py-3">
                <div class="flex justify-center gap-2">
                  <button 
                    @click="editItem(item)" 
                    style="background: linear-gradient(to bottom right, #009832, #007a28);"
                    class="p-2 rounded-lg text-white hover:opacity-90 shadow-md hover:shadow-lg transition-all duration-200"
                    title="Edit item"
                  >
                    <span class="material-icons-outlined text-sm">edit</span>
                  </button>
                  <button 
                    @click="openDeleteModal(item)" 
                    class="p-2 rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all duration-200"
                    title="Delete item"
                    :disabled="deleteLoading && itemBeingDeleted === item.id"
                  >
                    <span v-if="!(deleteLoading && itemBeingDeleted === item.id)" class="material-icons-outlined text-sm">delete</span>
                    <span v-else class="material-icons-outlined text-sm animate-spin">refresh</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="flex flex-col justify-center items-center py-10">
          <span class="material-icons-outlined text-4xl text-gray-600 dark:text-gray-400">inventory_2</span>
          <p class="mt-2 text-base text-gray-600 dark:text-gray-400">No supply items found</p>
        </div>
      </div>

      <!-- Supply Pagination -->
      <div v-if="!loading && totalConsumableFilteredItems > 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 gap-3 sm:gap-0">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
          <div class="text-base text-gray-900 dark:text-white">
            Result {{ (currentConsumablePage - 1) * itemsPerConsumablePage + 1 }}-{{ Math.min(currentConsumablePage * itemsPerConsumablePage, totalConsumableFilteredItems) }} of {{ totalConsumableFilteredItems }}
          </div>
          <div class="flex items-center gap-2">
            <label class="text-base text-gray-900 dark:text-white">Items per page:</label>
            <select 
              v-model="itemsPerConsumablePage" 
              @change="changeConsumableItemsPerPage($event.target.value)"
              class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded px-2 py-1 text-base"
            >
              <option value="8">8</option>
              <option value="16">16</option>
              <option value="24">24</option>
            </select>
          </div>
        </div>
        <div class="flex items-center justify-center sm:justify-end gap-1 flex-wrap">
          <button @click="goToConsumablePage(1)" :disabled="currentConsumablePage === 1" class="px-2 py-1 text-base border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-600 dark:hover:bg-gray-600 disabled:opacity-50">First</button>
          <button @click="goToConsumablePage(currentConsumablePage - 1)" :disabled="currentConsumablePage === 1" class="px-2 py-1 text-base border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-600 dark:hover:bg-gray-600 disabled:opacity-50">&lt; Previous</button>
          <div class="flex items-center gap-1">
            <template v-for="page in totalConsumablePages" :key="page">
              <button 
                v-if="page === 1 || page === totalConsumablePages || (page >= currentConsumablePage - 1 && page <= currentConsumablePage + 1)"
                @click="goToConsumablePage(page)"
                :class="[
                  'px-2 py-1 text-base border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-600 dark:hover:bg-gray-600',
                  currentConsumablePage === page ? 'bg-green-600 text-white border-green-500' : ''
                ]"
              >
                {{ page }}
              </button>
              <span v-else-if="page === currentConsumablePage - 2 || page === currentConsumablePage + 2" class="px-2 text-gray-600 dark:text-gray-400">...</span>
            </template>
          </div>
          <button @click="goToConsumablePage(currentConsumablePage + 1)" :disabled="currentConsumablePage === totalConsumablePages" class="px-2 py-1 text-base border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-600 dark:hover:bg-gray-600 disabled:opacity-50">Next &gt;</button>
          <button @click="goToConsumablePage(totalConsumablePages)" :disabled="currentConsumablePage === totalConsumablePages" class="px-2 py-1 text-base border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-600 dark:hover:bg-gray-600 disabled:opacity-50">Last</button>
        </div>
      </div>
    </div>
    
    <!-- Enhanced Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn">
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border-2 border-gray-200 animate-modalSlideIn">
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">delete_forever</span>
              </div>
              <h3 class="text-xl font-bold text-white">Delete Item</h3>
            </div>
            <button @click="closeDeleteModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6">
          <div class="mb-6">
            <div class="flex items-center gap-3 mb-3 p-3 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
              <span class="material-icons-outlined text-red-600">warning</span>
              <p class="text-sm font-medium text-gray-800">
                Are you sure you want to delete <span class="font-bold text-red-600">{{ itemToDelete?.article }}</span>?
              </p>
            </div>
            <p class="text-sm text-gray-600">
              This item will be moved to the trash and can be viewed in the Deleted Items section.
            </p>
          </div>
          
          <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
              <span class="material-icons-outlined text-base align-middle mr-1">description</span>
              Reason for deletion (optional)
            </label>
            <textarea 
              v-model="deleteReason"
              class="w-full bg-white text-gray-900 placeholder:text-gray-400 border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all resize-none"
              rows="3"
              placeholder="Enter a reason for deleting this item..."
            ></textarea>
          </div>
          
          <div class="flex justify-end gap-3">
            <button 
              @click="closeDeleteModal"
              class="px-5 py-2.5 border-2 border-gray-300 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all shadow-sm"
              :disabled="deleteLoading"
            >
              Cancel
            </button>
            <button 
              @click="deleteItem"
              class="px-5 py-2.5 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:from-red-700 hover:to-red-800 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transition-all"
              :disabled="deleteLoading"
            >
              <span v-if="deleteLoading" class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
              <span v-else class="material-icons-outlined text-base">delete</span>
              {{ deleteLoading ? 'Deleting...' : 'Delete Item' }}
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Enhanced QR Code Preview Modal -->
    <div v-if="showQrPreviewModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn">
      <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full overflow-hidden border-2 border-gray-200 animate-modalSlideIn">
        <!-- Enhanced Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">qr_code_scanner</span>
              </div>
              <h3 class="text-xl font-bold text-white">QR Code Preview</h3>
            </div>
            <button 
              @click="closeQrPreviewModal" 
              class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors"
              title="Close"
            >
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6 sm:p-8">
          <div class="flex flex-col lg:flex-row items-center lg:items-start gap-6 sm:gap-8">
            <!-- Left side: QR Code Image with enhanced styling -->
            <div class="flex flex-col items-center w-full lg:w-auto">
              <div class="relative mb-6">
                <div class="absolute inset-0 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl blur-xl opacity-30 animate-pulse"></div>
                <div class="relative border-4 border-green-500 rounded-2xl p-6 bg-white qr-pulse-border shadow-2xl">
                  <img 
                    :src="selectedQrItem?.qrCode" 
                    alt="QR Code" 
                    class="w-56 h-56 sm:w-72 sm:h-72 object-contain"
                  >
                </div>
              </div>
              
              <!-- Print Button -->
              <button 
                @click="printQrCode" 
                class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 flex items-center justify-center gap-2 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 font-semibold"
              >
                <span class="material-icons-outlined">print</span>
                Print QR Code
              </button>
            </div>
            
            <!-- Right side: Item details and image -->
            <div class="flex-1 w-full lg:w-auto">
              <!-- Item Title -->
              <h4 class="text-xl sm:text-2xl font-bold mb-4 text-center lg:text-left text-gray-900 border-b-2 border-green-200 pb-3">
                {{ selectedQrItem?.article || 'Item' }}
              </h4>
              
              <!-- Item Image -->
              <div class="mb-4 flex justify-center lg:justify-start">
                <div class="relative">
                  <div class="absolute inset-0 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl blur-lg opacity-20"></div>
                  <img 
                    :src="selectedQrItem?.image" 
                    alt="Item" 
                    class="relative h-40 sm:h-48 object-contain border-2 border-gray-200 rounded-xl shadow-lg"
                  >
                </div>
              </div>
              
              <!-- Enhanced Item Details -->
              <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-xl border-2 border-gray-200 shadow-inner">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</div>
                    <div class="text-sm font-semibold text-gray-900">{{ selectedQrItem?.description || 'N/A' }}</div>
                  </div>
                  
                  <div class="space-y-1">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Property Account Code</div>
                    <div class="text-sm font-mono font-semibold text-gray-900">{{ selectedQrItem?.propertyAccountCode || 'N/A' }}</div>
                  </div>
                  
                  <div class="space-y-1">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Location</div>
                    <div class="text-sm font-semibold text-gray-900 flex items-center gap-1">
                      <span class="material-icons-outlined text-base text-green-600">location_on</span>
                      {{ selectedQrItem?.location || 'N/A' }}
                    </div>
                  </div>
                  
                  <div class="space-y-1">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Condition</div>
                    <div class="text-sm">
                      <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        {{ selectedQrItem?.condition || 'N/A' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <SuccessModal
      :isOpen="showSuccessModal"
      :title="successModalType === 'success' ? 'Success' : 'Error'"
      :message="successMessage"
      buttonText="Continue"
      :type="successModalType"
      @confirm="closeSuccessModal"
      @close="closeSuccessModal"
    />
  </div>
</template>

<style scoped>
/* Enhanced Button Styles */
.btn-primary-enhanced {
  background: linear-gradient(to right, #000000, #575757);
  @apply text-white px-4 py-2.5 rounded-xl flex items-center text-base font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

.btn-primary-enhanced:hover {
  background: linear-gradient(to right, #1a1a1a, #6b6b6b);
}

.btn-secondary-enhanced {
  @apply bg-white text-gray-700 px-4 py-2.5 rounded-xl border-2 border-gray-300 hover:bg-gray-50 hover:border-green-400 flex items-center text-base font-semibold transition-all duration-200 shadow-sm hover:shadow-md;
}

/* Animation for page load */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Modal animations */
@keyframes modalFadeIn {
  from { 
    opacity: 0; 
  }
  to { 
    opacity: 1; 
  }
}

@keyframes modalSlideIn {
  from { 
    opacity: 0; 
    transform: scale(0.9) translateY(-20px); 
  }
  to { 
    opacity: 1; 
    transform: scale(1) translateY(0); 
  }
}

.animate-modalFadeIn {
  animation: modalFadeIn 0.2s ease-out;
}

.animate-modalSlideIn {
  animation: modalSlideIn 0.3s ease-out;
}

/* QR Code Modal Styles */
.qr-preview-enter-active,
.qr-preview-leave-active {
  transition: all 0.3s ease;
}

.qr-preview-enter-from,
.qr-preview-leave-to {
  opacity: 0;
  transform: scale(0.9);
}

@keyframes pulse-border {
  0% {
    border-color: #10B981;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
  }
  50% {
    border-color: #34D399;
    box-shadow: 0 0 15px rgba(52, 211, 153, 0.6);
  }
  100% {
    border-color: #10B981;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
  }
}

.qr-pulse-border {
  animation: pulse-border 2s infinite;
}

/* Ensure consistent checkbox styling */
input[type="checkbox"] {
  @apply rounded border-gray-300 text-green-600 focus:ring-green-500;
}

/* Table specific styles */
.overflow-x-auto {
  @apply relative;
  scrollbar-width: thin;
  scrollbar-color: theme('colors.gray.300') theme('colors.gray.100');
}

.overflow-x-auto::-webkit-scrollbar {
  @apply h-2;
}

.overflow-x-auto::-webkit-scrollbar-track {
  @apply bg-gray-100 rounded-full;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  @apply bg-gray-300 rounded-full hover:bg-gray-400;
}

/* Ensure sticky columns work with hover states */
tr:hover td.sticky {
  @apply bg-gray-50;
}

/* Responsive table adjustments */
@media (max-width: 640px) {
  .material-icons-outlined {
    font-size: 18px;
  }
}

/* Hover effects for interactive elements */
button, a {
  @apply transition-all duration-200;
}

button:active, a:active {
  @apply transform scale-95;
}

/* Improved focus states for accessibility */
button:focus, a:focus, input:focus, select:focus, textarea:focus {
  @apply outline-none ring-2 ring-green-500 ring-opacity-50;
}

/* Fix for hamburger menu toggle button */
.menu-button {
  @apply focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50;
}

/* Sidebar toggle animation */
.sidebar-enter-active,
.sidebar-leave-active {
  transition: transform 0.3s ease;
}

.sidebar-enter-from,
.sidebar-leave-to {
  transform: translateX(-100%);
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Enhanced scrollbar for tables */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  @apply bg-gray-100 rounded-full;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  @apply bg-green-400 rounded-full hover:bg-green-500;
}

/* Line clamp utility */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Enhanced table row hover effect */
tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  transform: translateX(2px);
  box-shadow: inset 4px 0 0 theme('colors.green.500');
}

/* Card hover effects */
.group:hover {
  transform: translateY(-2px);
}

/* Professional government-style borders */
.border-official {
  border-image: linear-gradient(to right, theme('colors.green.600'), theme('colors.green.800')) 1;
}

/* Responsive text adjustments */
@media (max-width: 640px) {
  .material-icons-outlined {
    font-size: 18px;
  }
  
  h1 {
    font-size: 1.5rem;
  }
}
</style>