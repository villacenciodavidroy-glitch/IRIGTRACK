<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import useItems from '../composables/useItems'
import useCategories from '../composables/useCategories'
import useLocations from '../composables/useLocations'
import useConditions from '../composables/useConditions'
import useConditionNumbers from '../composables/useConditionNumbers'
import useUsers from '../composables/useUsers'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const route = useRoute()

// Get item data and dropdown data using composables
const { items, fetchitems, loading, error } = useItems()
const { categories, fetchcategories } = useCategories()
const { locations, fetchLocations } = useLocations()
const { conditions, fetchconditions } = useConditions()
const { condition_numbers, fetchcondition_numbers } = useConditionNumbers()
const { users, fetchusers } = useUsers()

// Form data
const editForm = ref({
  unit: '',
  description: '',
  category_id: '',
  quantity: '',
  pac: '',
  unit_value: '',
  date_acquired: '',
  po_number: '',
  location_id: '',
  condition_id: '',
  condition_number_id: '',
  issuedTo: null, // Changed from user_id to issuedTo to match personnel selection (null to match select option)
  user_id: '', // Keep for backend submission
  maintenance_reason: '', // Maintenance reason dropdown (enum value)
  technician_notes: '' // Separate technician notes field
})

const editLoading = ref(false)
const itemId = ref(null)
const dataLoading = ref(true)
const showSuccessMessage = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Image state
const selectedImage = ref(null)
const imagePreview = ref(null)
const currentImageUrl = ref(null)
const imageFile = ref(null)
const imageInputRef = ref(null)

// Fetch all data when component mounts
onMounted(async () => {
  itemId.value = route.params.uuid
  console.log('EditItem component mounted with UUID:', itemId.value)
  
  try {
    // Fetch dropdown data - fetch all items (high per_page) to get complete lists
    await Promise.all([
      fetchcategories(1, 1000), // Fetch all categories
      fetchLocations(1, 1000), // Fetch all locations
      fetchconditions(),
      fetchcondition_numbers(),
      fetchusers()
    ])
    
    console.log('Categories loaded:', categories.value.length)
    console.log('Locations loaded:', locations.value.length)
    
    // Fetch single item
    const itemResponse = await axiosClient.get(`/items/check/${itemId.value}`)
    console.log('Item response:', itemResponse.data)
    
    // Extract item from response
    let item = null
    if (itemResponse.data && itemResponse.data.item) {
      item = itemResponse.data.item
      console.log('Found item:', item)
    } else if (itemResponse.data && itemResponse.data.data) {
      // Handle case where response structure is different
      item = itemResponse.data.data
      console.log('Found item:', item)
    } else {
      console.error('Item not found with UUID:', itemId.value)
      console.log('Response:', itemResponse.data)
    }
    
    if (item) {
      populateForm(item)
    }
  } catch (error) {
    console.error('Error fetching data:', error)
    if (error.response?.status === 404) {
      // Item not found, try fallback to fetching all items
      try {
        await Promise.all([
          fetchitems(),
          fetchcategories(1, 1000),
          fetchLocations(1, 1000),
          fetchconditions(),
          fetchcondition_numbers(),
          fetchusers()
        ])
        
        const item = items.value.find(item => item.uuid === itemId.value)
        if (item) {
          console.log('Found item in fallback:', item)
          populateForm(item)
        } else {
          console.error('Item not found with UUID:', itemId.value)
        }
      } catch (fallbackError) {
        console.error('Fallback fetch also failed:', fallbackError)
      }
    }
  } finally {
    dataLoading.value = false
  }
})

// Populate form with item data
const populateForm = (item) => {
  // Format date for HTML date input (YYYY-MM-DD)
  let formattedDate = ''
  if (item.date_acquired) {
    try {
      // Handle different date formats
      const dateStr = item.date_acquired
      if (dateStr.includes('T')) {
        // ISO format: 2025-09-20T00:00:00.000000Z
        formattedDate = dateStr.split('T')[0]
      } else if (dateStr.includes('/')) {
        // Format: 09/20/2025
        const [month, day, year] = dateStr.split('/')
        formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
      } else if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
        // Already in YYYY-MM-DD format
        formattedDate = dateStr
      } else {
        // Try parsing as Date object
        const date = new Date(dateStr)
        if (!isNaN(date.getTime())) {
          formattedDate = date.toISOString().split('T')[0]
        }
      }
    } catch (e) {
      console.warn('Error formatting date:', e)
    }
  }
  
  // Handle numeric IDs - ensure they're numbers for select comparison
  const toNumber = (val) => {
    if (val === null || val === undefined || val === '') return null
    const num = Number(val)
    return isNaN(num) ? null : num
  }
  
  // Get category and location IDs, handling various data structures
  const categoryId = toNumber(item.category_id || item.category_id || (item.category && (item.category.id || item.category.category_id)))
  const locationId = toNumber(item.location_id || item.location_id || (item.location && (item.location.id || item.location.location_id)))
  const userId = toNumber(item.user_id)
  
  // Find the location that has personnel for issuedTo
  // ISSUED TO IS BASED ON LOCATION'S PERSONNEL, NOT USER MATCHING
  let issuedToValue = null
  
  console.log('=== POPULATE FORM DEBUG ===')
  console.log('Item location_id:', locationId)
  console.log('Item user_id:', userId, '(for reference only - not used for issuedTo)')
  console.log('Available locations with personnel:', locations.value
    .filter(l => l.personnel && l.personnel.trim() !== '')
    .map(l => ({ id: l.id || l.location_id, location: l.location, personnel: l.personnel })))
  
  // Check if the item's location has personnel assigned
  // This is the PRIMARY way to set issuedTo - based on location's personnel
  if (locationId) {
    const itemLocation = locations.value.find(loc => 
      Number(loc.id || loc.location_id) === Number(locationId)
    )
    
    console.log('Checking item location:', itemLocation ? {
      id: itemLocation.id || itemLocation.location_id,
      location: itemLocation.location,
      personnel: itemLocation.personnel
    } : 'Not found')
    
    if (itemLocation && itemLocation.personnel && itemLocation.personnel.trim() !== '') {
      // The location has personnel - use this location for issuedTo
      issuedToValue = Number(locationId)
      console.log('✓ Using item location for issuedTo:', issuedToValue, 'Personnel:', itemLocation.personnel)
    } else {
      console.log('ℹ Item location does not have personnel assigned')
    }
  }
  
  console.log('Final issuedToValue:', issuedToValue)
  console.log('========================')
  
  // Get technician notes from latest maintenance record if available
  let technicianNotes = ''
  try {
    if (item.maintenance_records && Array.isArray(item.maintenance_records) && item.maintenance_records.length > 0) {
      // Sort by maintenance_date descending and get the latest
      const sortedRecords = item.maintenance_records.sort((a, b) => {
        const dateA = a.maintenance_date ? new Date(a.maintenance_date) : new Date(0)
        const dateB = b.maintenance_date ? new Date(b.maintenance_date) : new Date(0)
        return dateB.getTime() - dateA.getTime()
      })
      const latestRecord = sortedRecords[0]
      technicianNotes = latestRecord?.technician_notes || ''
    }
  } catch (e) {
    console.warn('Error accessing maintenance_records:', e)
    technicianNotes = ''
  }
  
  editForm.value = {
    unit: item.unit || '',
    description: item.description || '',
    category_id: categoryId,
    quantity: toNumber(item.quantity) || 0,
    pac: item.pac || '',
    unit_value: item.unit_value || '',
    date_acquired: formattedDate || '',
    po_number: item.po_number || '',
    location_id: locationId ? Number(locationId) : null, // Ensure it's a number to match dropdown options
    condition_id: toNumber(item.condition_id),
    condition_number_id: toNumber(item.condition_number_id),
    issuedTo: issuedToValue !== null && issuedToValue !== undefined ? Number(issuedToValue) : null, // Convert to number to match dropdown option values
    user_id: userId, // Keep original user_id for reference
    maintenance_reason: item.maintenance_reason || '', // From items table
    technician_notes: technicianNotes // From latest maintenance record
  }
  
  // Set current image URL if available (check both image and image_path)
  const imageUrl = item.image || item.image_path
  if (imageUrl) {
    currentImageUrl.value = imageUrl
    imagePreview.value = imageUrl
    console.log('Current image URL set:', imageUrl)
  }
  
  console.log('Form populated:', editForm.value)
  console.log('Category ID:', categoryId, 'Available categories:', categories.value.map(c => ({ id: c.id, category: c.category })))
  console.log('Location ID:', locationId, 'Available locations:', locations.value.map(l => ({ id: l.id, location: l.location })))
}

// Get current item for display
const currentItem = computed(() => {
  return items.value.find(item => item.uuid === itemId.value)
})

// Save edited item
const saveEditedItem = async () => {
  if (!itemId.value) return
  
  // Validate issuedTo is selected
  if (editForm.value.issuedTo === null || editForm.value.issuedTo === undefined || editForm.value.issuedTo === '') {
    successMessage.value = 'Please select personnel in "Issued To" field'
    successModalType.value = 'error'
    showSuccessModal.value = true
    return
  }
  
  console.log('=== SAVE VALIDATION PASSED ===')
  console.log('issuedTo value:', editForm.value.issuedTo, typeof editForm.value.issuedTo)
  
  // Validate maintenance reason and technician notes if On Maintenance is selected
  if (isOnMaintenance.value) {
    if (!editForm.value.maintenance_reason?.trim()) {
      successMessage.value = 'Please select a maintenance reason when condition is set to "On Maintenance"'
      successModalType.value = 'error'
      showSuccessModal.value = true
      return
    }
    if (!editForm.value.technician_notes?.trim()) {
      successMessage.value = 'Please provide technician notes when condition is set to "On Maintenance"'
      successModalType.value = 'error'
      showSuccessModal.value = true
      return
    }
  }
  
  try {
    editLoading.value = true
    
    // Map issuedTo (location ID) to update location_id and optionally user_id
    let userIdToSend = editForm.value.user_id // Keep original as fallback
    let locationIdToSend = editForm.value.location_id ? Number(editForm.value.location_id) : null
    
    console.log('=== SAVING ISSUED TO ===')
    console.log('Issued To (personnel) value:', editForm.value.issuedTo)
    console.log('Current location_id:', editForm.value.location_id)
    
    // If Issued To is selected, ALWAYS update location_id to match that location
    // This ensures the selected personnel persists
    if (editForm.value.issuedTo !== null && editForm.value.issuedTo !== undefined && editForm.value.issuedTo !== '') {
      const issuedToLocationId = Number(editForm.value.issuedTo)
      
      // ALWAYS update location_id to match the selected Issued To location
      locationIdToSend = issuedToLocationId
      console.log('✓ Updating location_id to:', locationIdToSend, 'to match selected Issued To personnel')
      // Try multiple comparison methods to ensure we find the location
      let selectedLocation = locations.value.find(loc => 
        Number(loc.id || loc.location_id) === Number(editForm.value.issuedTo)
      )
      
      // Fallback to loose comparison if strict doesn't work
      if (!selectedLocation) {
        selectedLocation = locations.value.find(loc => 
          (loc.id || loc.location_id) == editForm.value.issuedTo
        )
      }
      
      console.log('=== SAVING ISSUED TO ===')
      console.log('issuedTo value:', editForm.value.issuedTo, typeof editForm.value.issuedTo)
      console.log('Selected location:', selectedLocation)
      console.log('All locations:', locations.value.map(l => ({ 
        id: l.id || l.location_id, 
        personnel: l.personnel,
        matches: (l.id || l.location_id) == editForm.value.issuedTo 
      })))
      
      if (selectedLocation) {
        const selectedLocationId = Number(selectedLocation.id || selectedLocation.location_id)
        const personnelName = selectedLocation.personnel ? selectedLocation.personnel.trim() : null
        
        console.log('=== PROCESSING ISSUED TO ===')
        console.log('Selected location ID from Issued To:', selectedLocationId)
        console.log('Location name:', selectedLocation.location)
        console.log('Personnel name:', personnelName || '(No personnel assigned)')
        console.log('Current locationIdToSend (from Location dropdown):', locationIdToSend)
        
        // Note: locationIdToSend is already set from Location dropdown
        // Only update if Location dropdown is empty, otherwise keep Location dropdown value
        
        // Optionally try to match personnel to a user for user_id
        if (personnelName) {
          // Normalize names for comparison (remove extra spaces, convert to lowercase)
          const normalizeName = (name) => {
            return (name || '').trim().toLowerCase().replace(/\s+/g, ' ')
          }
          
          const normalizedPersonnel = normalizeName(personnelName)
          console.log('Attempting to find user matching personnel:', personnelName)
          
          // Try exact match first (normalized)
          let matchingUser = users.value.find(user => {
            const userFullname = (user.fullname || '').trim()
            const normalizedUser = normalizeName(userFullname)
            return normalizedUser === normalizedPersonnel
          })
          
          // Try partial match if exact not found
          if (!matchingUser) {
            matchingUser = users.value.find(user => {
              const userFullname = (user.fullname || '').trim()
              const normalizedUser = normalizeName(userFullname)
              return normalizedUser.includes(normalizedPersonnel) ||
                     normalizedPersonnel.includes(normalizedUser)
            })
          }
          
          // Try word-by-word matching
          if (!matchingUser) {
            const personnelWords = normalizedPersonnel.split(' ').filter(w => w.length > 0)
            matchingUser = users.value.find(user => {
              const userFullname = (user.fullname || '').trim()
              const normalizedUser = normalizeName(userFullname)
              const userWords = normalizedUser.split(' ').filter(w => w.length > 0)
              
              if (personnelWords.length > 0) {
                const allWordsMatch = personnelWords.every((word, index) => {
                  return index < userWords.length && userWords[index].includes(word)
                })
                return allWordsMatch
              }
              return false
            })
          }
          
          if (matchingUser) {
            userIdToSend = matchingUser.id || matchingUser.user?.id
            console.log('✓ Found matching user - Mapped to user_id:', userIdToSend, 'User:', matchingUser.fullname)
          } else {
            console.log('ℹ No matching user found for personnel:', personnelName, '- Will use original user_id or null')
            console.log('(This is OK - personnel in locations table may not always match users)')
            // Don't block save - just keep original user_id or use null
            // Personnel is in locations table, user_id is optional
          }
        } else {
          console.log('ℹ Location has no personnel assigned')
          // No personnel, keep original user_id or use null
        }
      } else {
        console.warn('Selected location not found')
      }
    } else {
      console.warn('issuedTo is null/undefined/empty, skipping user mapping')
    }
    
    console.log('Final locationIdToSend:', locationIdToSend, '(based on Issued To:', editForm.value.issuedTo, ')')
    console.log('Final userIdToSend:', userIdToSend, '(was:', editForm.value.user_id, ')')
    console.log('===============================')
    
    // Prepare payload with all form data
    const payload = { 
      ...editForm.value,
      location_id: locationIdToSend, // Update location_id to match selected personnel location
      user_id: userIdToSend || null // Override with mapped user_id, use null if not found
    }
    // Remove issuedTo from payload as backend doesn't need it
    delete payload.issuedTo
    
    console.log('Saving - Payload location_id:', payload.location_id)
    console.log('Saving - Payload user_id:', payload.user_id)
    console.log('Saving - Full payload:', payload)
    
    // Handle maintenance fields when On Maintenance
    if (isOnMaintenance.value) {
      // Include both maintenance_reason (enum) and technician_notes
      // Only send if they have values (validation already ensures they're required)
      if (editForm.value.maintenance_reason?.trim()) {
        payload.maintenance_reason = editForm.value.maintenance_reason.trim()
      }
      if (editForm.value.technician_notes?.trim()) {
        payload.technician_notes = editForm.value.technician_notes.trim()
      }
    } else {
      // Remove maintenance fields if not on maintenance
      delete payload.maintenance_reason
      delete payload.technician_notes
    }
    
    console.log('Payload being sent:', payload)
    
    let response
    
    // If image is being uploaded, use FormData
    if (imageFile.value) {
      console.log('=== IMAGE UPLOAD DETECTED ===')
      console.log('Image file:', imageFile.value.name, imageFile.value.size, 'bytes')
      
      const formData = new FormData()
      
      // Append all form fields to FormData
      // Convert all values appropriately for FormData
      Object.keys(payload).forEach(key => {
        const value = payload[key]
        // Only skip null and undefined, but include empty strings and 0
        if (value !== null && value !== undefined) {
          // Convert numbers to strings for FormData
          if (typeof value === 'number') {
            formData.append(key, String(value))
          } else if (typeof value === 'boolean') {
            formData.append(key, value ? '1' : '0')
          } else {
            // Append strings (including empty strings) and other types
            formData.append(key, value)
          }
        }
      })
      
      // Append image file with correct field name (backend expects 'image_path')
      formData.append('image_path', imageFile.value)
      
      // Add _method for Laravel method spoofing (PUT requests with FormData work better as POST)
      formData.append('_method', 'PUT')
      
      console.log('FormData entries:')
      for (let pair of formData.entries()) {
        if (pair[1] instanceof File) {
          console.log(`${pair[0]}: [File: ${pair[1].name}, ${pair[1].size} bytes, type: ${pair[1].type}]`)
        } else {
          console.log(`${pair[0]}: ${pair[1]}`)
        }
      }
      
      // Use POST with method spoofing for file uploads (Laravel handles FormData better with POST)
      response = await axiosClient.post(`/items/${itemId.value}`, formData)
      
      console.log('Image upload response:', response.data)
    } else {
      // No image upload, use regular JSON payload
      console.log('=== NO IMAGE UPLOAD - USING JSON ===')
      response = await axiosClient.put(`/items/${itemId.value}`, payload)
    }
    
    console.log('Update response:', response.data)
    
    // If image was uploaded and response contains new image URL, update the preview
    if (imageFile.value && response.data) {
      const updatedItem = response.data.data || response.data.item || response.data
      // Backend returns image_path, check for both image_path and image
      const newImageUrl = updatedItem?.image_path || updatedItem?.image
      if (newImageUrl) {
        console.log('Image updated successfully, new URL:', newImageUrl)
        currentImageUrl.value = newImageUrl
        imagePreview.value = newImageUrl
        imageFile.value = null // Clear the file since it's been uploaded
      } else {
        console.warn('Image uploaded but no image URL in response:', response.data)
      }
    }
    
    // Show success message
    showSuccessMessage.value = true
    
    // Wait a moment to show success message, then navigate back
    setTimeout(() => {
      router.push('/inventory')
    }, 1500)
  } catch (error) {
    console.error('Error updating item:', error)
    console.error('Error details:', {
      message: error.message,
      response: error.response?.data,
      status: error.response?.status,
      headers: error.response?.headers
    })
    
    // Provide more specific error messages
    let errorMsg = 'Failed to update item. Please try again.'
    
    if (error.response?.data?.message) {
      errorMsg = error.response.data.message
    } else if (error.response?.status === 413) {
      errorMsg = 'Image file is too large. Please use an image smaller than 5MB.'
    } else if (error.response?.status === 422) {
      errorMsg = error.response.data?.errors 
        ? Object.values(error.response.data.errors).flat().join(', ')
        : 'Validation error. Please check your input.'
    } else if (error.response?.status === 500) {
      errorMsg = 'Server error. Please try again later.'
    } else if (error.message?.includes('Network')) {
      errorMsg = 'Network error. Please check your connection.'
    }
    
    successMessage.value = errorMsg
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    editLoading.value = false
  }
}

// Cancel and go back
const cancelEdit = () => {
  router.push('/inventory')
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

// Format date for input
const formatDateForInput = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toISOString().split('T')[0]
}

// Check if "On Maintenance" condition is selected
const isOnMaintenance = computed(() => {
  if (!editForm.value.condition_id) return false
  const selectedCondition = conditions.value.find(c => c.id == editForm.value.condition_id)
  return selectedCondition && (selectedCondition.condition === 'On Maintenance' || selectedCondition.condition === 'Under Maintenance')
})

// Get locations that have personnel assigned
const locationsWithPersonnel = computed(() => {
  return locations.value.filter(location => 
    location.personnel && location.personnel.trim() !== ''
  )
})

// Filter condition numbers to only show A1, A2, A3, and R
const filteredConditionNumbers = computed(() => {
  const allowedNumbers = ['A1', 'A2', 'A3', 'R'];
  return condition_numbers.value.filter(cn => {
    const cnValue = cn.condition_number?.trim();
    return allowedNumbers.includes(cnValue);
  });
})

// Watch for condition changes and auto-select "R" for "Non - Serviceable"
watch(() => editForm.value.condition_id, (newConditionId) => {
  if (newConditionId) {
    // Find the selected condition
    const selectedCondition = conditions.value.find(cond => 
      cond.id == newConditionId
    )
    
    // Check if the condition is "Non - Serviceable" (handle variations)
    if (selectedCondition) {
      const conditionName = (selectedCondition.condition || '').toLowerCase().trim()
      const isNonServiceable = conditionName.includes('non') && conditionName.includes('serviceable')
      
      if (isNonServiceable) {
        // Find the condition number "R"
        const conditionNumberR = condition_numbers.value.find(cn => {
          const cnValue = (cn.condition_number || '').trim().toUpperCase()
          return cnValue === 'R'
        })
        
        if (conditionNumberR) {
          editForm.value.condition_number_id = conditionNumberR.id
          console.log('Auto-selected condition number "R" for Non-Serviceable condition')
        }
      }
    }
  }
})

// Check if there's a valid image to show close button for
const hasValidImage = computed(() => {
  // Show close button if there's a newly uploaded file
  if (imageFile.value) {
    return true
  }
  // Show close button if there's a current image URL (existing uploaded image)
  // Only if it's a real image path, not a placeholder
  if (currentImageUrl.value && imagePreview.value) {
    // Check if it looks like a real image URL (not a placeholder icon)
    const url = currentImageUrl.value
    // If it's a data URL, http/https URL, or storage path, it's a real image
    return url.startsWith('data:') || 
           url.startsWith('http://') || 
           url.startsWith('https://') || 
           url.startsWith('/storage/') ||
           url.startsWith('/images/') ||
           url.includes('.jpg') ||
           url.includes('.jpeg') ||
           url.includes('.png') ||
           url.includes('.gif')
  }
  return false
})

// Handle location change to ensure value is properly set
const handleLocationChange = (event) => {
  const rawValue = event.target.value
  console.log('=== LOCATION CHANGE EVENT ===')
  console.log('Raw value from select:', rawValue, typeof rawValue)
  console.log('Current editForm.location_id:', editForm.value.location_id, typeof editForm.value.location_id)
  
  if (rawValue === '' || rawValue === null || rawValue === 'null' || rawValue === undefined) {
    editForm.value.location_id = null
  } else {
    // Always convert to number
    const numValue = Number(rawValue)
    if (!isNaN(numValue)) {
      editForm.value.location_id = numValue
      console.log('✓ Location updated to:', editForm.value.location_id)
      
      // Check if this location has personnel - if so, automatically update issuedTo
      const selectedLocation = locations.value.find(loc => 
        Number(loc.id || loc.location_id) === numValue
      )
      if (selectedLocation && selectedLocation.personnel && selectedLocation.personnel.trim() !== '') {
        // Location has personnel - automatically select it in Issued To
        editForm.value.issuedTo = numValue
        console.log('✓ Auto-selected personnel in Issued To:', selectedLocation.personnel, '(Location ID:', numValue, ')')
      } else {
        // Location has no personnel - clear Issued To
        editForm.value.issuedTo = null
        console.log('ℹ Location has no personnel assigned, cleared Issued To')
      }
    } else {
      editForm.value.location_id = null
      console.warn('Invalid number value:', rawValue)
    }
  }
  
  console.log('Updated editForm.location_id:', editForm.value.location_id, typeof editForm.value.location_id)
  console.log('=================================')
}

// Handle issuedTo change to ensure value is properly set
const handleIssuedToChange = (event) => {
  const rawValue = event.target.value
  console.log('=== ISSUED TO CHANGE EVENT ===')
  console.log('Raw value from select:', rawValue, typeof rawValue)
  console.log('Current editForm.issuedTo:', editForm.value.issuedTo, typeof editForm.value.issuedTo)
  
  if (rawValue === '' || rawValue === null || rawValue === 'null' || rawValue === undefined) {
    editForm.value.issuedTo = null
  } else {
    // Always convert to number to match option values
    const numValue = Number(rawValue)
    if (!isNaN(numValue)) {
      editForm.value.issuedTo = numValue
    } else {
      editForm.value.issuedTo = null
      console.warn('Invalid number value:', rawValue)
    }
  }
  
  console.log('Updated editForm.issuedTo:', editForm.value.issuedTo, typeof editForm.value.issuedTo)
  console.log('Available locations:', locationsWithPersonnel.value.map(l => {
    const locId = Number(l.id || l.location_id)
    return {
      id: locId,
      personnel: l.personnel,
      matches: locId === Number(editForm.value.issuedTo)
    }
  }))
  console.log('=================================')
}

// Handle image selection
const onImageChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Validate file type
    if (!file.type.startsWith('image/')) {
      successMessage.value = 'Please select a valid image file'
      successModalType.value = 'error'
      showSuccessModal.value = true
      return
    }
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      successMessage.value = 'Image size must be less than 5MB'
      successModalType.value = 'error'
      showSuccessModal.value = true
      return
    }
    
    imageFile.value = file
    selectedImage.value = file
    
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

// Remove selected image
const removeImage = () => {
  // If there's a newly uploaded file, just clear it and revert to current image
  if (imageFile.value) {
    imageFile.value = null
    selectedImage.value = null
    // Revert preview to current image URL if available, otherwise clear
    imagePreview.value = currentImageUrl.value || null
  } else if (currentImageUrl.value) {
    // If no new file but there's a current image, remove it completely
    currentImageUrl.value = null
    imagePreview.value = null
  }
  
  // Reset file input
  if (imageInputRef.value) {
    imageInputRef.value.value = ''
  }
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- Enhanced Header Section -->
    <div class="bg-green-600 rounded-xl shadow-lg">
      <div class="px-4 py-5 sm:px-6 sm:py-6 md:px-8 md:py-7">
        <div class="flex items-center gap-3 sm:gap-4 md:gap-5">
          <!-- Back Button -->
          <button 
            @click="cancelEdit" 
            class="p-2.5 sm:p-3 bg-white/20 hover:bg-white/30 rounded-lg transition-all duration-200 flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-white text-xl sm:text-2xl">arrow_back</span>
          </button>
          
          <!-- Edit Icon Button -->
          <div class="p-2.5 sm:p-3 bg-white/20 rounded-lg flex-shrink-0">
            <span class="material-icons-outlined text-white text-xl sm:text-2xl">edit</span>
          </div>
          
          <!-- Title and Subtitle -->
          <div class="flex-1 min-w-0">
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-white mb-0.5 sm:mb-1 leading-tight">
              Edit Item
            </h1>
            <p class="text-green-100 text-xs sm:text-sm leading-tight">
              Update and modify inventory item information
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="showSuccessMessage" class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-2 border-green-400 dark:border-green-600 rounded-xl p-5 shadow-md dark:shadow-xl">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-green-500 rounded-full">
          <span class="material-icons-outlined text-white text-xl">check_circle</span>
        </div>
        <p class="text-green-800 dark:text-green-300 font-bold text-base">Item updated successfully! Redirecting back to inventory...</p>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading || dataLoading" class="flex justify-center items-center py-10">
      <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl p-6">
      <span class="material-icons-outlined text-5xl text-red-400 dark:text-red-400 mb-4">error_outline</span>
      <p class="mt-2 text-red-500 dark:text-red-400 text-lg font-semibold mb-4">{{ error }}</p>
      <button 
        @click="fetchitems" 
        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md hover:shadow-lg transition-all"
      >
        Try Again
      </button>
    </div>

    <!-- Form -->
    <div v-else-if="itemId" class="max-w-5xl mx-auto space-y-6">
      <form @submit.prevent="saveEditedItem" class="space-y-6">
        <!-- Basic Information Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">description</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Basic Information</h2>
                <p class="text-xs text-green-100">Essential item details and identification</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Article -->
              <div class="form-group">
                <label class="form-label">Article <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">inventory_2</span>
                  </span>
                  <input
                    v-model="editForm.unit"
                    type="text"
                    required
                    class="form-input-enhanced !pl-12"
                    placeholder="Enter article name"
                  >
                </div>
              </div>
              
              <!-- Category -->
              <div class="form-group">
                <label class="form-label">Category <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">category</span>
                  </span>
                  <select
                    v-model.number="editForm.category_id"
                    required
                    class="form-select-enhanced !pl-12"
                  >
                    <option :value="null">Select category</option>
                    <option v-for="category in categories" :key="category.id" :value="Number(category.id)">
                      {{ category.category }}
                    </option>
                  </select>
                </div>
                <p v-if="categories.length === 0" class="mt-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 p-2 rounded border border-amber-200 dark:border-amber-700">
                  <span class="material-icons-outlined text-sm align-middle mr-1">info</span>
                  Loading categories...
                </p>
              </div>
              
              <!-- Description -->
              <div class="form-group md:col-span-2">
                <label class="form-label">Description <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 top-3 text-green-600 z-10">
                    <span class="material-icons-outlined">description</span>
                  </span>
                  <textarea
                    v-model="editForm.description"
                    required
                    rows="3"
                    class="form-textarea-enhanced !pl-12"
                    placeholder="Enter item description"
                  ></textarea>
                </div>
              </div>
              
              <!-- Quantity -->
              <div class="form-group">
                <label class="form-label">Quantity <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">inventory</span>
                  </span>
                  <input
                    v-model="editForm.quantity"
                    type="number"
                    min="1"
                    required
                    class="form-input-enhanced !pl-12"
                    placeholder="Enter quantity"
                  >
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Financial & Acquisition Details Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">payments</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Financial & Acquisition Details</h2>
                <p class="text-xs text-green-100">Property account code, valuation, and acquisition information</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Property Account Code -->
              <div class="form-group">
                <label class="form-label">Property Account Code <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">qr_code</span>
                  </span>
                  <input
                    v-model="editForm.pac"
                    type="text"
                    required
                    class="form-input-enhanced !pl-12"
                    placeholder="Enter PAC"
                  >
                </div>
              </div>
              
              <!-- Unit Value -->
              <div class="form-group">
                <label class="form-label">Unit Value <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">payments</span>
                  </span>
                  <input
                    v-model="editForm.unit_value"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    class="form-input-enhanced !pl-12"
                    placeholder="Enter unit value"
                  >
                </div>
              </div>
              
              <!-- Date Acquired -->
              <div class="form-group">
                <label class="form-label">Date Acquired <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">calendar_today</span>
                  </span>
                  <input
                    v-model="editForm.date_acquired"
                    type="date"
                    required
                    class="form-input-enhanced !pl-12"
                  >
                </div>
              </div>
              
              <!-- P.O. Number -->
              <div class="form-group">
                <label class="form-label">P.O. Number <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">receipt</span>
                  </span>
                  <input
                    v-model="editForm.po_number"
                    type="text"
                    required
                    class="form-input-enhanced !pl-12"
                    placeholder="Enter P.O. number"
                  >
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Assignment & Location Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">location_on</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Assignment & Unit/Sectors</h2>
                <p class="text-xs text-green-100">Item unit/sectors and personnel assignment</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Unit/Sectors -->
              <div class="form-group">
                <label class="form-label">Unit/Sectors <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">location_on</span>
                  </span>
                  <select
                    v-model="editForm.location_id"
                    @change="handleLocationChange"
                    required
                    class="form-select-enhanced !pl-12"
                  >
                    <option :value="null">Select Unit/Sector</option>
                    <option v-for="location in locations" :key="location.id" :value="Number(location.id || location.location_id)">
                      {{ location.location }}
                    </option>
                  </select>
                </div>
                <p v-if="locations.length === 0" class="mt-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 p-2 rounded border border-amber-200 dark:border-amber-700">
                  <span class="material-icons-outlined text-sm align-middle mr-1">info</span>
                  Loading units/sectors...
                </p>
              </div>
              
              <!-- Issued To -->
              <div class="form-group">
                <label class="form-label">Issued To <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">person</span>
                  </span>
                  <select
                    v-model="editForm.issuedTo"
                    required
                    @change="handleIssuedToChange"
                    class="form-select-enhanced !pl-12"
                  >
                    <option :value="null">Select Personnel</option>
                    <option v-for="location in locationsWithPersonnel" 
                        :key="location.id || location.location_id" 
                        :value="Number(location.id || location.location_id)">
                      {{ location.personnel }}
                    </option>
                  </select>
                </div>
                <p v-if="locationsWithPersonnel.length === 0" class="mt-2 text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border-l-4 border-amber-400 dark:border-amber-600 flex items-start gap-2">
                  <span class="material-icons-outlined text-sm text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5">info</span>
                  <span>No personnel assigned to any unit/sector. Please assign personnel in Unit/Sectors Management first.</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Condition & Status Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">build</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Condition & Status</h2>
                <p class="text-xs text-green-100">Item condition assessment and classification</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Condition -->
              <div class="form-group">
                <label class="form-label">Condition</label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">build</span>
                  </span>
                  <select
                    v-model="editForm.condition_id"
                    class="form-select-enhanced !pl-12"
                  >
                    <option value="">Select condition</option>
                    <option v-for="condition in conditions" :key="condition.id" :value="condition.id">
                      {{ condition.condition }}
                    </option>
                  </select>
                </div>
              </div>
              
              <!-- Condition Number -->
              <div class="form-group">
                <label class="form-label">Condition Number</label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">tag</span>
                  </span>
                  <select
                    v-model="editForm.condition_number_id"
                    class="form-select-enhanced !pl-12"
                  >
                    <option value="">Select condition number</option>
                    <option v-for="conditionNumber in filteredConditionNumbers" :key="conditionNumber.id" :value="conditionNumber.id">
                      {{ conditionNumber.condition_number }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Asset Image Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">image</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Asset Image</h2>
                <p class="text-xs text-green-100">Upload or update item image</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="form-group">
              <label class="form-label">Item Image</label>
              
              <!-- Image Preview Area -->
              <div v-if="imagePreview" class="mt-4 mb-4">
                <div class="relative inline-block">
                  <img 
                    :src="imagePreview" 
                    alt="Item preview" 
                    class="max-w-full h-auto max-h-80 rounded-lg border-2 border-gray-300 dark:border-gray-600 shadow-md object-contain bg-gray-50 dark:bg-gray-700"
                  >
                  <button
                    v-if="hasValidImage"
                    @click="removeImage"
                    type="button"
                    class="absolute top-2 right-2 p-2 bg-red-500 text-white rounded-full hover:bg-red-600 transition-colors shadow-lg"
                    title="Remove image"
                  >
                    <span class="material-icons-outlined text-sm">close</span>
                  </button>
                </div>
                <p v-if="imageFile" class="mt-2 text-xs text-green-600 dark:text-green-400 font-medium">
                  <span class="material-icons-outlined text-sm align-middle">info</span>
                  New image selected: {{ imageFile.name }}
                </p>
                <p v-else-if="currentImageUrl" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  <span class="material-icons-outlined text-sm align-middle">info</span>
                  Current image (click "Choose Image" to replace)
                </p>
              </div>

              <!-- Image Upload Area -->
              <div class="mt-4">
                <label 
                  for="image-upload"
                  class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg cursor-pointer bg-gradient-to-br from-gray-50 to-green-50/30 dark:from-gray-700 dark:to-green-900/20 hover:bg-green-50/50 dark:hover:bg-green-900/30 transition-colors duration-200"
                  :class="{ 'border-green-500 dark:border-green-600 bg-green-50/30 dark:bg-green-900/20': imagePreview }"
                >
                  <div class="flex flex-col items-center justify-center pt-5 pb-6 px-4">
                    <span class="material-icons-outlined text-4xl text-gray-400 dark:text-gray-500 mb-2">cloud_upload</span>
                    <p class="mb-2 text-sm text-gray-600 dark:text-white font-semibold">
                      <span class="text-green-600 dark:text-green-400">Click to upload</span> or drag and drop
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 5MB</p>
                  </div>
                  <input 
                    id="image-upload" 
                    ref="imageInputRef"
                    type="file" 
                    accept="image/*" 
                    @change="onImageChange" 
                    class="hidden"
                  >
                </label>
              </div>
              
              <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
                <span class="material-icons-outlined text-sm align-middle mr-1">info</span>
                Upload a clear image of the asset for better identification and tracking.
              </p>
            </div>
          </div>
        </div>
        
        <!-- Maintenance Section -->
        <div v-if="isOnMaintenance" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-amber-600 to-amber-700 px-6 py-4 border-b border-amber-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">build_circle</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Maintenance Information</h2>
                <p class="text-xs text-amber-100">Maintenance reason and technician notes</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <!-- Maintenance Reason Dropdown -->
            <div class="form-group">
              <label class="form-label">Maintenance Reason <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">info</span>
                </span>
                <select
                  v-model="editForm.maintenance_reason"
                  required
                  class="form-select-enhanced !pl-12"
                >
                  <option value="">Select maintenance reason</option>
                  <option value="Overheat">Overheat</option>
                  <option value="Wear">Wear</option>
                  <option value="Electrical">Electrical</option>
                  <option value="Wet">Wet</option>
                  <option value="Component Failure">Component Failure</option>
                  <option value="Physical Damage">Physical Damage</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Select the primary reason for this maintenance issue.
              </p>
            </div>
            
            <!-- Technician Notes Textarea -->
            <div class="form-group">
              <label class="form-label">
                Technician Notes <span class="text-red-500">*</span>
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(Saved to technician_notes)</span>
              </label>
              <div class="relative flex items-start">
                <span class="absolute left-4 top-3 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">notes</span>
                </span>
                <textarea
                  v-model="editForm.technician_notes"
                  required
                  rows="4"
                  class="form-textarea-enhanced !pl-12"
                  placeholder="Enter detailed technician notes regarding the maintenance (e.g., issue description, repair steps, observations, test results, etc.)"
                ></textarea>
              </div>
              <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Detailed notes will be saved as technician_notes in the maintenance record (maintenance_records table).
              </p>
            </div>
          </div>
        </div>

        <!-- Action Buttons Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-6">
          <div class="flex flex-col sm:flex-row justify-end items-center gap-4">
            <button
              type="button"
              @click="cancelEdit"
              class="w-full sm:w-auto px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-400 dark:hover:border-gray-500 font-semibold transition-all duration-200"
              :disabled="editLoading"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn-primary-enhanced disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 min-w-[180px] justify-center"
              :disabled="editLoading"
            >
              <span v-if="editLoading" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">save</span>
              {{ editLoading ? 'Saving Changes...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Item Not Found -->
    <div v-else class="flex flex-col justify-center items-center py-20 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl p-6">
      <span class="material-icons-outlined text-5xl text-gray-400 dark:text-gray-500 mb-4">inventory_2</span>
      <p class="mt-2 text-gray-500 dark:text-gray-400 text-lg font-semibold mb-4">Item not found</p>
      <button 
        @click="cancelEdit" 
        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold shadow-md hover:shadow-lg transition-all"
      >
        Go Back
      </button>
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
.form-group {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-semibold text-gray-700 dark:text-white mb-2;
}

.form-input-enhanced {
  @apply block w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 shadow-sm transition-all duration-200;
  @apply focus:border-green-500 dark:focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20;
  @apply hover:border-gray-400 dark:hover:border-gray-500;
  height: 48px;
  padding-left: 3rem;
  padding-right: 1rem;
}

.form-input-enhanced:focus {
  @apply shadow-md;
}

.form-select-enhanced {
  @apply block w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm transition-all duration-200;
  @apply focus:border-green-500 dark:focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20;
  @apply hover:border-gray-400 dark:hover:border-gray-500;
  height: 48px;
  padding-left: 3rem;
  padding-right: 1rem;
}

.form-select-enhanced:focus {
  @apply shadow-md;
}

.form-textarea-enhanced {
  @apply block w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 shadow-sm transition-all duration-200;
  @apply focus:border-green-500 dark:focus:border-green-500 focus:ring-2 focus:ring-green-500 focus:ring-opacity-20;
  @apply hover:border-gray-400 dark:hover:border-gray-500;
  padding-left: 3rem;
  padding-right: 1rem;
  padding-top: 0.75rem;
  padding-bottom: 0.75rem;
}

.form-textarea-enhanced:focus {
  @apply shadow-md;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-3 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

/* Animation for page load */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Form animations */
@keyframes formFadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

/* Ensure consistent styling */
input, select, textarea {
  @apply transition-colors duration-200;
}

input:focus, select:focus, textarea:focus {
  @apply ring-2 ring-green-500 border-green-500;
}

/* Button hover effects */
button {
  @apply transition-all duration-200;
}

button:active {
  @apply transform scale-95;
}

/* Improved focus states for accessibility */
button:focus, input:focus, select:focus, textarea:focus {
  @apply outline-none ring-2 ring-green-500 ring-opacity-50;
}

.material-icons-outlined {
  font-size: 20px;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Dark mode support for select options */
select option {
  @apply bg-white dark:bg-gray-700 text-gray-900 dark:text-white;
}
</style>
