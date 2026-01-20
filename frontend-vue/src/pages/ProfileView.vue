<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'
import useLocations from '../composables/useLocations'
import useAuth from '../composables/useAuth'

const router = useRouter()
const { user: authUser, fetchCurrentUser, isAdmin } = useAuth()

const user = ref({
  fullName: '',
  username: '',
  email: '',
  location: '',
  location_id: null,
  image: '',
  role: '', // Add role field
  password: '',
  password_confirmation: ''
})
const selectedFile = ref(null)
const loading = ref(false)
const imageTimestamp = ref(Date.now()) // Add timestamp for cache busting
const issuedItems = ref([])
const loadingIssuedItems = ref(false)

// Password visibility toggles
const showPassword = ref(false)
const showConfirmPassword = ref(false)

// Password complexity requirements state
const passwordRequirements = ref({
  minLength: false,
  hasUpperCase: false,
  hasLowerCase: false,
  hasDigit: false,
  hasSpecialChar: false
})

// Password strength calculation
const passwordStrength = computed(() => {
  if (!user.value.password) return { level: 'none', label: '', color: '', percentage: 0 }
  
  const requirements = passwordRequirements.value
  const metCount = Object.values(requirements).filter(Boolean).length
  
  // Calculate strength based on met requirements
  if (metCount === 5) {
    return { level: 'strong', label: 'Strong', color: 'green', percentage: 100 }
  } else if (metCount === 4) {
    return { level: 'good', label: 'Good', color: 'blue', percentage: 75 }
  } else if (metCount === 3) {
    return { level: 'fair', label: 'Fair', color: 'yellow', percentage: 50 }
  } else if (metCount >= 1) {
    return { level: 'weak', label: 'Weak', color: 'red', percentage: 25 }
  } else {
    return { level: 'none', label: '', color: 'gray', percentage: 0 }
  }
})

// Fetch locations from database
const { locations, fetchLocations, loading: locationsLoading } = useLocations()

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

// Recovery modal state
const showRecoveryModal = ref(false)
const selectedItemForRecovery = ref(null)
const recoveryForm = ref({
  recovery_notes: '',
  recovered_by: '',
  recovery_date: new Date().toISOString().split('T')[0]
})
const recoveryLoading = ref(false)

// Report Lost/Damaged modal state
const showReportModal = ref(false)
const selectedItemForReport = ref(null)
const reportForm = ref({
  status: 'LOST', // 'LOST' or 'DAMAGED'
  remarks: '',
  incident_date: new Date().toISOString().split('T')[0],
  estimated_value_loss: ''
})
const reportLoading = ref(false)

// Check if user should only see Account Details (user or supply role)
const isRegularUser = computed(() => {
  // First check authUser from useAuth composable
  if (authUser.value && authUser.value.role) {
    const role = String(authUser.value.role || '').toLowerCase()
    if (role === 'user' || role === 'supply') {
      console.log('User/Supply role detected from authUser:', role)
      return true
    }
  }
  // Check user.value (from profile data)
  if (user.value && user.value.role) {
    const role = String(user.value.role || '').toLowerCase()
    if (role === 'user' || role === 'supply') {
      console.log('User/Supply role detected from user.value:', role)
      return true
    }
  }
  // Also check from localStorage
  try {
    const storedUser = JSON.parse(localStorage.getItem('user') || '{}')
    if (storedUser && storedUser.role) {
      const role = String(storedUser.role || '').toLowerCase()
      if (role === 'user' || role === 'supply') {
        console.log('User/Supply role detected from localStorage:', role)
        return true
      }
    }
  } catch (e) {
    // Ignore parse errors
  }
  console.log('Not a regular user. authUser:', authUser.value?.role, 'user.value:', user.value?.role)
  return false
})

const fetchIssuedItems = async () => {
  loadingIssuedItems.value = true
  try {
    const response = await axiosClient.get('/memorandum-receipts/my-items')
    if (response.data && response.data.data) {
      issuedItems.value = response.data.data || []
    } else if (Array.isArray(response.data)) {
      issuedItems.value = response.data
    } else {
      issuedItems.value = []
    }
    
    // Debug: Log lost/damaged items
    const lostDamagedItems = issuedItems.value.filter(item => item.status === 'LOST' || item.status === 'DAMAGED')
    if (lostDamagedItems.length > 0) {
      console.log('Lost/Damaged items found:', lostDamagedItems.length)
      lostDamagedItems.forEach(item => {
        console.log(`Item ${item.item?.unit || 'N/A'} (${item.status}):`, {
          status: item.status,
          remarks: item.remarks,
          hasRemarks: !!item.remarks,
          remarksType: typeof item.remarks
        })
      })
    }
  } catch (error) {
    console.error('Error fetching issued items:', error)
    issuedItems.value = []
  } finally {
    loadingIssuedItems.value = false
  }
}

const formatLostDamagedRemarks = (remarks) => {
  if (!remarks) return 'N/A'
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object') {
      return parsed.description || parsed.type || 'N/A'
    }
    return remarks
  } catch (e) {
    return typeof remarks === 'string' ? remarks : 'N/A'
  }
}

const getLostDamagedInfo = (remarks, field) => {
  if (!remarks) return null
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object') {
      // Handle both direct field access and nested structures
      if (parsed[field] !== undefined) {
        return parsed[field]
      }
      // Also check in nested structures if needed
      if (parsed.type && parsed.type === field) {
        return parsed.type
      }
      return null
    }
    return null
  } catch (e) {
    console.warn('Error parsing remarks:', e, remarks)
    return null
  }
}

const getRecoveryInfo = (remarks, field) => {
  if (!remarks) return null
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object' && parsed.recovered) {
      return parsed[field] || null
    }
    return null
  } catch (e) {
    return null
  }
}

const hasRecoveryInfo = (remarks) => {
  if (!remarks) return false
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    return parsed && typeof parsed === 'object' && parsed.recovered === true
  } catch (e) {
    return false
  }
}

const getOriginalStatusInfo = (remarks, field) => {
  if (!remarks) return null
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object' && parsed.original_remarks) {
      const original = parsed.original_remarks
      if (typeof original === 'object') {
        return original[field] || null
      }
    }
    return null
  } catch (e) {
    return null
  }
}

const hasPreviousIncidents = (remarks) => {
  if (!remarks) return false
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    return parsed && typeof parsed === 'object' && Array.isArray(parsed.previous_incidents) && parsed.previous_incidents.length > 0
  } catch (e) {
    return false
  }
}

const getPreviousIncidents = (remarks) => {
  if (!remarks) return []
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object' && Array.isArray(parsed.previous_incidents)) {
      return parsed.previous_incidents
    }
    return []
  } catch (e) {
    return []
  }
}

const isRepeatIncident = (remarks) => {
  if (!remarks) return false
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    return parsed && typeof parsed === 'object' && parsed.is_repeat_incident === true
  } catch (e) {
    return false
  }
}

const getIncidentNumber = (remarks) => {
  if (!remarks) return 1
  try {
    const parsed = typeof remarks === 'string' ? JSON.parse(remarks) : remarks
    if (parsed && typeof parsed === 'object' && parsed.incident_number) {
      return parsed.incident_number
    }
    return 1
  } catch (e) {
    return 1
  }
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch (e) {
    return dateString
  }
}

// Open recovery modal
const openRecoveryModal = (item) => {
  selectedItemForRecovery.value = item
  recoveryForm.value = {
    recovery_notes: '',
    recovered_by: authUser.value?.user_code || authUser.value?.fullname || '',
    recovery_date: new Date().toISOString().split('T')[0]
  }
  showRecoveryModal.value = true
}

// Close recovery modal
const closeRecoveryModal = () => {
  showRecoveryModal.value = false
  selectedItemForRecovery.value = null
  recoveryForm.value = {
    recovery_notes: '',
    recovered_by: '',
    recovery_date: new Date().toISOString().split('T')[0]
  }
}

// Recover item
const recoverItem = async () => {
  if (!selectedItemForRecovery.value || !selectedItemForRecovery.value.id) {
    successMessage.value = 'Invalid item selected'
    successModalType.value = 'error'
    showSuccessModal.value = true
    return
  }

  if (!recoveryForm.value.recovery_notes.trim()) {
    successMessage.value = 'Please enter recovery notes'
    successModalType.value = 'error'
    showSuccessModal.value = true
    return
  }

  recoveryLoading.value = true
  try {
    const baseURL = axiosClient.defaults.baseURL || '/api'
    const path = baseURL.includes('/v1')
      ? `/memorandum-receipts/${selectedItemForRecovery.value.id}/recover`
      : `/v1/memorandum-receipts/${selectedItemForRecovery.value.id}/recover`
    
    const response = await axiosClient.post(path, {
      recovery_notes: recoveryForm.value.recovery_notes,
      recovered_by: recoveryForm.value.recovered_by || authUser.value?.user_code || 'SYSTEM',
      recovery_date: recoveryForm.value.recovery_date
    })
    
    if (response.data.success) {
      successMessage.value = 'Item recovered successfully! It has been restored to you.'
      successModalType.value = 'success'
      showSuccessModal.value = true
      closeRecoveryModal()
      // Refresh issued items list
      await fetchIssuedItems()
    } else {
      successMessage.value = response.data.message || 'Failed to recover item'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } catch (error) {
    console.error('Error recovering item:', error)
    successMessage.value = error.response?.data?.message || 'Failed to recover item'
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    recoveryLoading.value = false
  }
}

// Open report modal
const openReportModal = (item) => {
  selectedItemForReport.value = item
  reportForm.value = {
    status: 'LOST',
    remarks: '',
    incident_date: new Date().toISOString().split('T')[0],
    estimated_value_loss: ''
  }
  showReportModal.value = true
}

// Close report modal
const closeReportModal = () => {
  showReportModal.value = false
  selectedItemForReport.value = null
  reportForm.value = {
    status: 'LOST',
    remarks: '',
    incident_date: new Date().toISOString().split('T')[0],
    estimated_value_loss: ''
  }
}

// Report item as lost/damaged
const reportLostOrDamaged = async () => {
  if (!selectedItemForReport.value || !selectedItemForReport.value.id) {
    successMessage.value = 'Invalid item selected'
    successModalType.value = 'error'
    showSuccessModal.value = true
    return
  }

  if (!reportForm.value.remarks.trim()) {
    successMessage.value = 'Please provide a description/remarks'
    successModalType.value = 'error'
    showSuccessModal.value = true
    return
  }

  reportLoading.value = true
  try {
    const baseURL = axiosClient.defaults.baseURL || '/api'
    const path = baseURL.includes('/v1')
      ? `/memorandum-receipts/${selectedItemForReport.value.id}/report-lost-damaged`
      : `/v1/memorandum-receipts/${selectedItemForReport.value.id}/report-lost-damaged`
    
    const payload = {
      status: reportForm.value.status,
      remarks: reportForm.value.remarks,
      incident_date: reportForm.value.incident_date || new Date().toISOString().split('T')[0]
    }
    
    if (reportForm.value.estimated_value_loss && reportForm.value.status === 'DAMAGED') {
      payload.estimated_value_loss = parseFloat(reportForm.value.estimated_value_loss)
    }
    
    const response = await axiosClient.post(path, payload)
    
    if (response.data.success) {
      successMessage.value = `Item reported as ${reportForm.value.status} successfully. Admin has been notified.`
      successModalType.value = 'success'
      showSuccessModal.value = true
      closeReportModal()
      // Refresh issued items list
      await fetchIssuedItems()
    } else {
      successMessage.value = response.data.message || 'Failed to report item'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } catch (error) {
    console.error('Error reporting item:', error)
    successMessage.value = error.response?.data?.message || 'Failed to report item'
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    reportLoading.value = false
  }
}

const getCategoryName = (item) => {
  // Handle both array format (from virtual MR records) and object format (from actual MR records)
  if (item.item) {
    // ItemResource returns category as a string (category name), not an object
    if (typeof item.item === 'object') {
      // Check if category is a string (from ItemResource)
      if (typeof item.item.category === 'string') {
        return item.item.category || 'N/A'
      }
      // Check if category is an object with name property (from direct relationship)
      if (item.item.category && typeof item.item.category === 'object') {
        return item.item.category.name || item.item.category.category || 'N/A'
      }
      // Fallback: check category_name
      if (item.item.category_name) {
        return item.item.category_name
      }
    }
  }
  // Fallback: check if category is directly on item
  if (item.category) {
    if (typeof item.category === 'string') {
      return item.category
    }
    if (typeof item.category === 'object') {
      return item.category.name || item.category.category || 'N/A'
    }
  }
  return 'N/A'
}

onMounted(async () => {
  // Fetch current user from auth composable first
  await fetchCurrentUser()
  
  const userId = localStorage.getItem('userId')
  
  // Fetch issued items for the user
  await fetchIssuedItems()
  
  // Fetch locations FIRST - we need this to match location_id to location name
  try {
    await fetchLocations(1, 1000) // Fetch all locations (1000 per page)
    console.log('Locations fetched:', locations.value.length, 'locations')
  } catch (error) {
    console.error('Error fetching locations:', error)
  }
  
  // Fetch user data AFTER locations are loaded
  try {
    // Use /user endpoint (singular) to get current user's own profile, or /v1/user
    // This avoids 403 error from trying to access /users/{id} which requires admin
    let res
    try {
      res = await axiosClient.get('/user')
    } catch (e) {
      // Fallback to v1 endpoint
      try {
        res = await axiosClient.get('/v1/user')
      } catch (e2) {
        throw e // Throw original error if both fail
      }
    }
    if(res.status == 200){
      console.log("Success")
      console.log(res.data)
       
      const userData = res.data.data || res.data
      
      user.value.fullName = userData.fullname || userData.fullName || ''
      user.value.username = userData.username || ''
      user.value.email = userData.email || ''
      user.value.location_id = userData.location_id || null
      
      // Handle location - get location name from API response or find it from locations list
      if (userData.location) {
        // If location is a string, use it directly (this is the location name)
        if (typeof userData.location === 'string') {
          user.value.location = userData.location
        } 
        // If location is an object, extract the name
        else if (userData.location.name) {
          user.value.location = userData.location.name
        } else if (userData.location.location) {
          user.value.location = userData.location.location
        }
      } 
      // If location is not in response but location_id exists, find it from locations list
      else if (user.value.location_id && locations.value.length > 0) {
        const foundLocation = locations.value.find(loc => 
          (loc.id || loc.location_id) == user.value.location_id
        )
        if (foundLocation) {
          // LocationResource returns 'location' as the field name (which is the location name)
          user.value.location = foundLocation.location || foundLocation.name || ''
          console.log('Found location from locations list:', user.value.location, 'for location_id:', user.value.location_id)
        } else {
          user.value.location = ''
          console.log('Location not found in locations list for location_id:', user.value.location_id)
        }
      } 
      // If no location data at all
      else {
        user.value.location = ''
        console.log('No location data - location_id:', user.value.location_id, 'location in response:', userData.location)
      }
      
      // Handle image - API returns full URL via asset('storage/' . $this->image)
      if (userData.image && userData.image !== null && userData.image.trim() !== '') {
        // UserResource returns full URL via asset(), so use it directly
        user.value.image = userData.image
        console.log('User image URL from API:', user.value.image)
      } else {
        user.value.image = ''
        console.log('No image in user data. userData.image:', userData.image)
      }
      
      user.value.role = userData.role || authUser.value?.role || ''
      imageTimestamp.value = Date.now() // Initialize timestamp
      
      // If location is still empty but location_id exists, try to fetch it from locations list
      if ((!user.value.location || user.value.location.trim() === '') && user.value.location_id && locations.value.length > 0) {
        const foundLocation = locations.value.find(loc => {
          const locId = loc.id || loc.location_id
          return locId == user.value.location_id
        })
        if (foundLocation) {
          // LocationResource returns 'location' field which contains the location name
          user.value.location = foundLocation.location || foundLocation.name || ''
          console.log('Found location from locations list (retry):', user.value.location, 'from location object:', foundLocation)
        } else {
          console.log('Location still not found. location_id:', user.value.location_id, 'Available locations:', locations.value.map(l => ({ id: l.id || l.location_id, location: l.location })))
        }
      }
      
      console.log('User data loaded:', {
        fullName: user.value.fullName,
        username: user.value.username,
        email: user.value.email,
        location: user.value.location,
        location_id: user.value.location_id,
        image: user.value.image,
        role: user.value.role,
        rawData: userData,
        locationsAvailable: locations.value.length
      })
    } else {
      console.log('API Response:', res.data)
      console.log('User Data:', user.value)
    }
  } catch (e) {
    console.error('API Error:', e.response ? e.response.data : e)
    // If user fetch fails, use authUser data
    if (authUser.value) {
      user.value.fullName = authUser.value.fullname || authUser.value.fullName || ''
      user.value.username = authUser.value.username || ''
      user.value.email = authUser.value.email || ''
      user.value.location_id = authUser.value.location_id || null
      
      // Try to get location from authUser or find from locations list
      if (authUser.value.location) {
        if (typeof authUser.value.location === 'string') {
          user.value.location = authUser.value.location
        } else if (authUser.value.location.name) {
          user.value.location = authUser.value.location.name
        } else if (authUser.value.location.location) {
          user.value.location = authUser.value.location.location
        }
      } else if (user.value.location_id && locations.value.length > 0) {
        const foundLocation = locations.value.find(loc => 
          (loc.id || loc.location_id) == user.value.location_id
        )
        if (foundLocation) {
          user.value.location = foundLocation.location || foundLocation.name || ''
        } else {
          user.value.location = ''
        }
      } else {
        user.value.location = ''
      }
      
      // Handle image from authUser
      if (authUser.value.image) {
        user.value.image = authUser.value.image
      } else {
        user.value.image = ''
      }
      user.value.role = authUser.value.role || ''
    }
  }
})

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    selectedFile.value = file;
    
    // Preview the selected image immediately
    const reader = new FileReader();
    reader.onload = (e) => {
      user.value.image = e.target.result;
    };
    reader.readAsDataURL(file);
  }
};

const saveProfile = async () => {
  const userId = localStorage.getItem('userId')
  if (!userId) return

  if (!user.value) {
    console.error('user.value is undefined')
    return
  }

  loading.value = true

  const formData = new FormData()
  formData.append('role', user.value.role || '') // Include role (required by validation)
  formData.append('fullname', user.value.fullName ?? '')
  formData.append('username', user.value.username ?? '')
  formData.append('email', user.value.email ?? '')
  
  // Convert location name to location_id
  let locationIdToSend = user.value.location_id
  
  // If location_id is not set but location name is, find the ID from fetched locations
  if (!locationIdToSend && user.value.location) {
    const foundLocation = locations.value.find(loc => 
      loc.location?.toLowerCase() === user.value.location?.toLowerCase()
    )
    if (foundLocation) {
      locationIdToSend = foundLocation.id || foundLocation.location_id
    }
  }
  
  // Only append location_id if we have a valid ID
  if (locationIdToSend) {
    formData.append('location_id', locationIdToSend)
    console.log('Sending location_id:', locationIdToSend, 'for location:', user.value.location)
  }

  // Append image if a new file is selected
  if (selectedFile.value && selectedFile.value instanceof File) {
    formData.append('image', selectedFile.value)
  }
  
  // Only append password if it's being changed
  if (user.value.password && user.value.password.trim()) {
    // Validate password before submitting
    const passwordValidation = validatePasswords()
    if (!passwordValidation.valid) {
      successMessage.value = passwordValidation.message
      successModalType.value = 'error'
      showSuccessModal.value = true
      loading.value = false
      return
    }
    
    formData.append('password', user.value.password)
    formData.append('password_confirmation', user.value.password_confirmation || user.value.password)
  }

  try {
    // Use POST endpoint for FormData (PUT requests don't parse FormData correctly in Laravel)
    const res = await axiosClient.post(`/users/${userId}/update`, formData)

    if (res.status === 200 && res.data) {
      // Handle response structure - could be res.data or res.data.data
      const responseData = res.data.data || res.data
      
      // ✅ Update image with timestamp to force refresh
      if (responseData.image) {
        user.value.image = responseData.image
        imageTimestamp.value = Date.now()
      }

      // Update user data after successful update
      user.value.fullName = responseData.fullname || user.value.fullName
      user.value.username = responseData.username || user.value.username
      user.value.email = responseData.email || user.value.email
      user.value.location_id = responseData.location_id || null
      user.value.location = responseData.location || ''
      
      // Clear password fields
      user.value.password = ''
      user.value.password_confirmation = ''
      
      // Reset selected file
      selectedFile.value = null
      
      // Reset file input
      const fileInput = document.querySelector('input[type="file"]')
      if (fileInput) {
        fileInput.value = ''
      }

      successMessage.value = 'Profile updated successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
    }
  } catch (err) {
    console.error('Save error', err.response ? err.response.data : err)
    
    // Show specific error messages
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      const errorMessages = Object.values(errors).flat().join(', ')
      successMessage.value = errorMessages || 'Failed to update profile. Please check the form.'
    } else {
      successMessage.value = err.response?.data?.message || 'Failed to update profile.'
    }
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    loading.value = false
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

const goToDashboard = () => {
  // Redirect User role to supply-requests, Supply role to inventory, others to dashboard
  if (authUser.value?.role?.toLowerCase() === 'user') {
    router.push('/supply-requests')
  } else if (authUser.value?.role?.toLowerCase() === 'supply') {
    router.push('/inventory')
  } else {
  router.push('/dashboard')
  }
}

// Update location_id when location changes
const updateLocationId = () => {
  if (user.value.location && locations.value.length > 0) {
    const foundLocation = locations.value.find(loc => 
      loc.location?.toLowerCase() === user.value.location?.toLowerCase()
    )
    if (foundLocation) {
      user.value.location_id = foundLocation.id || foundLocation.location_id
    }
  } else {
    user.value.location_id = null
  }
}

// Check password requirements in real-time
const checkPasswordRequirements = () => {
  const password = user.value.password || ''
  
  passwordRequirements.value = {
    minLength: password.length >= 8,
    hasUpperCase: /[A-Z]/.test(password),
    hasLowerCase: /[a-z]/.test(password),
    hasDigit: /[0-9]/.test(password),
    hasSpecialChar: /[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)
  }
}

// Check password match in real-time
const checkPasswordMatch = () => {
  if (user.value.password_confirmation && user.value.password !== user.value.password_confirmation) {
    // Don't show error immediately, only on submit or blur
    // This allows user to type without constant error messages
  }
}

// Get image URL with cache busting
const getImageUrl = (imagePath) => {
  if (!imagePath) return ''
  
  // If it's already a full URL (starts with http:// or https://), use it directly
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath.includes('?') ? `${imagePath}&t=${imageTimestamp}` : `${imagePath}?t=${imageTimestamp}`
  }
  
  // If it's a relative path, construct full URL
  // UserResource returns asset('storage/' . $this->image) which should be full URL
  // But if it's not, try to construct it
  const baseURL = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
  const apiBase = baseURL.replace('/api', '').replace('/v1', '') || window.location.origin
  
  // Remove /storage/ prefix if present since asset() already adds it
  let imageUrl = imagePath
  if (imagePath.startsWith('/storage/')) {
    imageUrl = `${apiBase}${imagePath}`
  } else if (!imagePath.startsWith('http')) {
    imageUrl = `${apiBase}/storage/${imagePath}`
  }
  
  return imageUrl.includes('?') ? `${imageUrl}&t=${imageTimestamp}` : `${imageUrl}?t=${imageTimestamp}`
}

// Handle image load error
const handleImageError = (event) => {
  console.error('Image failed to load. Image URL:', user.value.image)
  console.error('Attempted URL:', event.target.src)
  console.error('Image error event:', event)
  // Don't clear the image immediately, might be a temporary network issue
  // Instead, show placeholder
  event.target.style.display = 'none'
}

// Handle image load success
const handleImageLoad = (event) => {
  console.log('Image loaded successfully:', event.target.src)
}

// Validate passwords before submission
const validatePasswords = () => {
  // Only validate if a new password is being set
  if (user.value.password && user.value.password.trim()) {
    const password = user.value.password
    
    // Check password match
    if (password !== user.value.password_confirmation) {
      return { valid: false, message: 'Passwords do not match' }
    }
    
    // Check all complexity requirements
    const validationErrors = []
    
    if (password.length < 8) {
      validationErrors.push('Password must be at least 8 characters long')
    }
    
    if (!/[A-Z]/.test(password)) {
      validationErrors.push('Password must contain at least one uppercase letter')
    }
    
    if (!/[a-z]/.test(password)) {
      validationErrors.push('Password must contain at least one lowercase letter')
    }
    
    if (!/[0-9]/.test(password)) {
      validationErrors.push('Password must contain at least one digit')
    }
    
    if (!/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)) {
      validationErrors.push('Password must contain at least one special symbol (!@#$%^&*()_+-=[]{}|;:,.<>?)')
    }
    
    if (validationErrors.length > 0) {
      return { valid: false, message: validationErrors.join(', ') }
    }
  }
  return { valid: true }
}
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">person</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Profile Settings</h1>
              <p class="text-green-100 text-sm sm:text-base">Manage your account information and preferences</p>
            </div>
          </div>
          <button 
            @click="goToDashboard"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/20 backdrop-blur-sm text-white rounded-xl hover:bg-white/30 transition-all shadow-lg hover:shadow-xl"
          >
            <span class="material-icons-outlined text-lg">arrow_back</span>
            <span>{{ 
              isRegularUser 
                ? (authUser.value?.role?.toLowerCase() === 'supply' ? 'Back to Inventory' : 'Back to Supply Requests') 
                : 'Back to Dashboard' 
            }}</span>
          </button>
        </div>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- Left Column - Form (Hidden for User role) -->
      <div v-if="!isRegularUser" class="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
          <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <span class="material-icons-outlined text-2xl">edit</span>
            Edit Profile Information
          </h2>
        </div>

        <form class="p-6 space-y-6" @submit.prevent="saveProfile" enctype="multipart/form-data">
          <!-- Image Upload -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-3">Profile Image</label>
            <div class="flex items-center gap-4">
              <label class="relative cursor-pointer">
                <input
                  type="file"
                  class="hidden"
                  accept="image/*"
                  @change="handleFileChange"
                />
                <div class="px-5 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2">
                  <span class="material-icons-outlined text-base">photo_camera</span>
                  Choose File
                </div>
              </label>
              <span class="text-sm text-gray-600 font-medium">
                {{ selectedFile ? selectedFile.name : 'No file chosen' }}
              </span>
            </div>
          </div>

          <!-- Full Name -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
              <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">badge</span>
              <span>Full Name</span>
            </label>
            <div class="relative">
              <input
                type="text"
                v-model="user.fullName"
                class="w-full px-4 py-3 pl-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                placeholder="Enter your full name"
              />
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                <span class="material-icons-outlined">person</span>
              </span>
            </div>
          </div>

          <!-- Username -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
              <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">alternate_email</span>
              <span>Username</span>
            </label>
            <div class="relative">
              <input
                type="text"
                v-model="user.username"
                class="w-full px-4 py-3 pl-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                placeholder="Enter your username"
              />
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                <span class="material-icons-outlined">account_circle</span>
              </span>
            </div>
          </div>

          <!-- Email -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
              <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">email</span>
              <span>Email</span>
            </label>
            <div class="relative">
              <input
                type="email"
                v-model="user.email"
                class="w-full px-4 py-3 pl-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                placeholder="Enter your email address"
              />
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                <span class="material-icons-outlined">mail</span>
              </span>
            </div>
          </div>

          <!-- Unit/Sections -->
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
              <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-lg">location_on</span>
              <span>Unit/Sections</span>
            </label>
            <div class="relative">
              <select
                v-model="user.location"
                @change="updateLocationId"
                :disabled="locationsLoading"
                class="w-full px-4 py-3 pl-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed appearance-none"
              >
                <option value="" disabled>Select Unit/Section</option>
                <option
                  v-for="location in locations"
                  :key="location.id || location.location_id"
                  :value="location.location"
                >
                  {{ location.location }}
                </option>
              </select>
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                <span class="material-icons-outlined">business</span>
              </span>
              <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">
                <span class="material-icons-outlined">keyboard_arrow_down</span>
              </span>
            </div>
            <p v-if="locationsLoading" class="mt-2 text-xs text-yellow-600 flex items-center gap-1">
              <span class="material-icons-outlined text-sm">hourglass_empty</span>
              Loading units/sections...
            </p>
            <p v-if="!locationsLoading && locations.length === 0" class="mt-2 text-xs text-red-600 flex items-center gap-1">
              <span class="material-icons-outlined text-sm">warning</span>
              No units/sections available. Please add units/sections first.
            </p>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Password</label>
            <div class="relative">
              <input
                :type="showPassword ? 'text' : 'password'"
                v-model="user.password"
                placeholder="Leave blank to keep current password"
                class="w-full px-4 py-3 pr-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md"
                @input="checkPasswordRequirements"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-0 h-full flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none"
                tabindex="-1"
              >
                <span class="material-icons-outlined text-xl">
                  {{ showPassword ? 'visibility_off' : 'visibility' }}
                </span>
              </button>
            </div>
            <!-- Password Strength Meter -->
            <div v-if="user.password" class="mt-2">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Password Strength:</span>
                <span 
                  class="text-xs font-semibold"
                  :class="{
                    'text-red-600 dark:text-red-400': passwordStrength.level === 'weak',
                    'text-yellow-600 dark:text-yellow-400': passwordStrength.level === 'fair',
                    'text-blue-600 dark:text-blue-400': passwordStrength.level === 'good',
                    'text-green-600 dark:text-green-400': passwordStrength.level === 'strong'
                  }"
                >
                  {{ passwordStrength.label }}
                </span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div 
                  class="h-2 rounded-full transition-all duration-300"
                  :class="{
                    'bg-red-500': passwordStrength.level === 'weak',
                    'bg-yellow-500': passwordStrength.level === 'fair',
                    'bg-blue-500': passwordStrength.level === 'good',
                    'bg-green-500': passwordStrength.level === 'strong',
                    'bg-gray-300 dark:bg-gray-600': passwordStrength.level === 'none'
                  }"
                  :style="{ width: `${passwordStrength.percentage}%` }"
                ></div>
              </div>
            </div>
            <!-- Password Requirements Checklist -->
            <div v-if="user.password" class="mt-2 space-y-1">
              <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Password must contain:</p>
              <div class="space-y-1 text-xs">
                <div class="flex items-center gap-2" :class="passwordRequirements.minLength ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                  <span class="material-icons-outlined text-sm">{{ passwordRequirements.minLength ? 'check_circle' : 'radio_button_unchecked' }}</span>
                  <span>Minimum 8 characters</span>
                </div>
                <div class="flex items-center gap-2" :class="passwordRequirements.hasUpperCase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                  <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasUpperCase ? 'check_circle' : 'radio_button_unchecked' }}</span>
                  <span>At least one uppercase letter</span>
                </div>
                <div class="flex items-center gap-2" :class="passwordRequirements.hasLowerCase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                  <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasLowerCase ? 'check_circle' : 'radio_button_unchecked' }}</span>
                  <span>At least one lowercase letter</span>
                </div>
                <div class="flex items-center gap-2" :class="passwordRequirements.hasDigit ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                  <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasDigit ? 'check_circle' : 'radio_button_unchecked' }}</span>
                  <span>At least one digit</span>
                </div>
                <div class="flex items-center gap-2" :class="passwordRequirements.hasSpecialChar ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                  <span class="material-icons-outlined text-sm">{{ passwordRequirements.hasSpecialChar ? 'check_circle' : 'radio_button_unchecked' }}</span>
                  <span>At least one special symbol (!@#$%^&*()_+-=[]{}|;:,.<>?)</span>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Confirm Password</label>
            <div class="relative">
              <input
                :type="showConfirmPassword ? 'text' : 'password'"
                v-model="user.password_confirmation"
                placeholder="Confirm password"
                :disabled="!user.password"
                class="w-full px-4 py-3 pr-12 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-400 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200 font-medium shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                @input="() => { checkPasswordMatch(); }"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                :disabled="!user.password"
                class="absolute right-3 top-0 h-full flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                tabindex="-1"
              >
                <span class="material-icons-outlined text-xl">
                  {{ showConfirmPassword ? 'visibility_off' : 'visibility' }}
                </span>
              </button>
            </div>
          </div>

          <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              class="btn-primary-enhanced flex items-center gap-2 shadow-lg"
              :disabled="loading"
            >
              <span v-if="loading" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">save</span>
              {{ loading ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Right Column - Account Details -->
      <div :class="isRegularUser ? 'w-full' : 'lg:w-1/3'">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
              <span class="material-icons-outlined text-2xl">account_circle</span>
              Account Details
            </h2>
          </div>
          
          <div class="p-6">
            <div class="flex justify-center mb-6">
              <div class="relative">
                <!-- Profile Picture -->
                <div v-if="user.image && user.image.trim() !== '' && user.image !== null" class="relative">
                <img
                    :src="getImageUrl(user.image)"
                  alt="Profile"
                  class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-700 shadow-lg"
                    @error="handleImageError"
                    @load="handleImageLoad"
                />
                  <span class="absolute bottom-0 right-0 bg-green-500 rounded-full w-6 h-6 border-4 border-white dark:border-gray-800 shadow-md"></span>
                </div>
                <!-- Default Avatar Placeholder -->
                <div v-else class="relative">
                  <div class="w-32 h-32 rounded-full bg-gradient-to-br from-green-400 to-green-600 border-4 border-gray-200 dark:border-gray-700 shadow-lg flex items-center justify-center">
                    <span class="material-icons-outlined text-white text-5xl">person</span>
                  </div>
                  <span class="absolute bottom-0 right-0 bg-green-500 rounded-full w-6 h-6 border-4 border-white dark:border-gray-800 shadow-md"></span>
                </div>
              </div>
            </div>
            
            <div class="space-y-4">
              <div class="p-5 bg-gradient-to-br from-gray-50 to-white dark:from-gray-700 dark:to-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md transition-all duration-200">
                <div class="flex items-start gap-3">
                  <div class="p-2.5 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex-shrink-0">
                    <span class="material-icons-outlined text-emerald-600 dark:text-emerald-400 text-xl">badge</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Full Name</label>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ user.fullName || 'Not set' }}</p>
                  </div>
                </div>
              </div>
              <div class="p-5 bg-gradient-to-br from-gray-50 to-white dark:from-gray-700 dark:to-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md transition-all duration-200">
                <div class="flex items-start gap-3">
                  <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex-shrink-0">
                    <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-xl">alternate_email</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Username</label>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ user.username || 'Not set' }}</p>
                  </div>
                </div>
              </div>
              <div class="p-5 bg-gradient-to-br from-gray-50 to-white dark:from-gray-700 dark:to-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md transition-all duration-200">
                <div class="flex items-start gap-3">
                  <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex-shrink-0">
                    <span class="material-icons-outlined text-purple-600 dark:text-purple-400 text-xl">email</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Email</label>
                    <p class="text-lg font-bold text-gray-900 dark:text-white break-words">{{ user.email || 'Not set' }}</p>
                  </div>
                </div>
              </div>
              <div class="p-5 bg-gradient-to-br from-gray-50 to-white dark:from-gray-700 dark:to-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-600 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md transition-all duration-200">
                <div class="flex items-start gap-3">
                  <div class="p-2.5 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex-shrink-0">
                    <span class="material-icons-outlined text-orange-600 dark:text-orange-400 text-xl">location_on</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Unit/Sections</label>
                    <p class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                      <span v-if="!user.location || user.location.trim() === ''" class="text-gray-400 dark:text-gray-500">Not set</span>
                      <span v-else class="flex items-center gap-1.5">
                        <span class="material-icons-outlined text-emerald-500 dark:text-emerald-400 text-base">check_circle</span>
                        {{ user.location }}
                      </span>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Issued Items Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden mt-6">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
              <span class="material-icons-outlined text-2xl">inventory_2</span>
              My Issued Items
            </h2>
          </div>
          
          <div class="p-6">
            <div v-if="loadingIssuedItems" class="text-center py-8">
              <span class="material-icons-outlined text-4xl text-gray-400 animate-spin">refresh</span>
              <p class="text-gray-500 dark:text-gray-400 mt-2">Loading issued items...</p>
            </div>
            
            <div v-else-if="issuedItems.length === 0" class="text-center py-8">
              <span class="material-icons-outlined text-4xl text-gray-400">inventory_2</span>
              <p class="text-gray-500 dark:text-gray-400 mt-2">No items have been issued to you</p>
            </div>
            
            <div v-else class="space-y-4">
              <!-- Enhanced Summary Bar -->
              <div class="mb-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border-2 border-blue-200 dark:border-blue-800 shadow-sm">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                  <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                      <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-xl">inventory_2</span>
                    </div>
                    <div>
                      <span class="text-sm font-semibold text-blue-700 dark:text-blue-300 uppercase tracking-wide">Total Items</span>
                      <p class="text-2xl font-bold text-blue-900 dark:text-blue-200">{{ issuedItems.length }}</p>
                    </div>
                  </div>
                  <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                      <span class="material-icons-outlined text-yellow-600 dark:text-yellow-400 text-sm">schedule</span>
                      <span class="text-xs font-semibold text-yellow-800 dark:text-yellow-300">Pending: <strong class="text-base">{{ issuedItems.filter(i => i.status === 'ISSUED').length }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-green-100 dark:bg-green-900/30 rounded-lg">
                      <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm">check_circle</span>
                      <span class="text-xs font-semibold text-green-800 dark:text-green-300">Returned: <strong class="text-base">{{ issuedItems.filter(i => i.status === 'RETURNED').length }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-red-100 dark:bg-red-900/30 rounded-lg">
                      <span class="material-icons-outlined text-red-600 dark:text-red-400 text-sm">error</span>
                      <span class="text-xs font-semibold text-red-800 dark:text-red-300">Lost: <strong class="text-base">{{ issuedItems.filter(i => i.status === 'LOST').length }}</strong></span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                      <span class="material-icons-outlined text-orange-600 dark:text-orange-400 text-sm">warning</span>
                      <span class="text-xs font-semibold text-orange-800 dark:text-orange-300">Damaged: <strong class="text-base">{{ issuedItems.filter(i => i.status === 'DAMAGED').length }}</strong></span>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2">
                <div
                  v-for="item in issuedItems"
                  :key="item.id || `item-${item.item?.id}`"
                  class="border-2 rounded-xl p-5 hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5"
                  :class="{
                    'bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-green-300 dark:border-green-700': item.status === 'ISSUED',
                    'bg-gradient-to-br from-gray-50 to-slate-50 dark:from-gray-700/50 dark:to-slate-700/50 border-gray-300 dark:border-gray-600': item.status === 'RETURNED',
                    'bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border-red-300 dark:border-red-700': item.status === 'LOST',
                    'bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 border-orange-300 dark:border-orange-700': item.status === 'DAMAGED'
                  }"
                >
                  <div class="flex items-start justify-between mb-4">
                    <div class="flex items-start gap-4 flex-1">
                      <div 
                        class="p-3 rounded-xl flex-shrink-0"
                        :class="{
                          'bg-green-100 dark:bg-green-900/40': item.status === 'ISSUED',
                          'bg-gray-100 dark:bg-gray-700': item.status === 'RETURNED',
                          'bg-red-100 dark:bg-red-900/40': item.status === 'LOST',
                          'bg-orange-100 dark:bg-orange-900/40': item.status === 'DAMAGED'
                        }"
                      >
                        <span 
                          class="material-icons-outlined text-2xl"
                          :class="{
                            'text-green-600 dark:text-green-400': item.status === 'ISSUED',
                            'text-gray-600 dark:text-gray-400': item.status === 'RETURNED',
                            'text-red-600 dark:text-red-400': item.status === 'LOST',
                            'text-orange-600 dark:text-orange-400': item.status === 'DAMAGED'
                          }"
                        >inventory_2</span>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-2">
                          <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ item.item?.unit || 'N/A' }}</h3>
                          <span
                            :class="{
                              'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 border-green-300 dark:border-green-600': item.status === 'ISSUED',
                              'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600': item.status === 'RETURNED',
                              'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 border-red-300 dark:border-red-600': item.status === 'LOST',
                              'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 border-orange-300 dark:border-orange-600': item.status === 'DAMAGED'
                            }"
                            class="px-3 py-1 text-xs font-bold rounded-full border-2 flex items-center gap-1.5 shadow-sm"
                          >
                            <span class="material-icons-outlined text-xs">
                              {{ item.status === 'ISSUED' ? 'schedule' : item.status === 'RETURNED' ? 'check_circle' : item.status === 'LOST' ? 'error' : 'warning' }}
                            </span>
                            {{ item.status }}
                          </span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ item.item?.description || 'N/A' }}</p>
                      </div>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex items-center gap-2 text-sm">
                      <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-base">category</span>
                      <span class="text-gray-600 dark:text-gray-400"><span class="font-semibold">Category:</span> <span class="font-bold text-gray-900 dark:text-white">{{ getCategoryName(item) }}</span></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                      <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-base">calendar_today</span>
                      <span class="text-gray-600 dark:text-gray-400"><span class="font-semibold">Issued:</span> <span class="font-bold text-gray-900 dark:text-white">{{ formatDate(item.issued_at) }}</span></span>
                    </div>
                    <div v-if="item.returned_at" class="flex items-center gap-2 text-sm">
                      <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-base">assignment_returned</span>
                      <span class="text-gray-600 dark:text-gray-400"><span class="font-semibold">Returned:</span> <span class="font-bold text-gray-900 dark:text-white">{{ formatDate(item.returned_at) }}</span></span>
                    </div>
                    <div v-if="item.issued_by_user_code" class="flex items-center gap-2 text-sm">
                      <span class="material-icons-outlined text-gray-500 dark:text-gray-400 text-base">person</span>
                      <span class="text-gray-600 dark:text-gray-400"><span class="font-semibold">Issued by:</span> <span class="font-bold text-gray-900 dark:text-white">{{ item.issued_by_user_code }}</span></span>
                    </div>
                  </div>
                  
                  <!-- Enhanced Recovery Information (for RETURNED items that were recovered) -->
                  <div v-if="item.status === 'RETURNED' && hasRecoveryInfo(item.remarks)" class="mt-4 p-4 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 rounded-xl text-sm text-green-800 dark:text-green-200 border-l-4 border-green-500 shadow-sm">
                    <p class="font-bold mb-3 flex items-center gap-2 text-base">
                      <span class="material-icons-outlined text-green-600 dark:text-green-400">check_circle</span>
                      Item Recovery Information
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 space-y-1.5">
                      <div v-if="getOriginalStatusInfo(item.remarks, 'type')" class="flex items-start gap-2">
                        <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm mt-0.5">info</span>
                        <div>
                          <span class="font-semibold">Original Status:</span>
                          <span class="ml-2">{{ getOriginalStatusInfo(item.remarks, 'type') }}</span>
                        </div>
                      </div>
                      <div v-if="getRecoveryInfo(item.remarks, 'recovery_notes')" class="flex items-start gap-2 sm:col-span-2">
                        <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm mt-0.5">note</span>
                        <div>
                          <span class="font-semibold">Recovery Notes:</span>
                          <span class="ml-2">{{ getRecoveryInfo(item.remarks, 'recovery_notes') }}</span>
                        </div>
                      </div>
                      <div v-if="getRecoveryInfo(item.remarks, 'recovered_by')" class="flex items-start gap-2">
                        <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm mt-0.5">person</span>
                        <div>
                          <span class="font-semibold">Recovered By:</span>
                          <span class="ml-2">{{ getRecoveryInfo(item.remarks, 'recovered_by') }}</span>
                        </div>
                      </div>
                      <div v-if="getRecoveryInfo(item.remarks, 'recovery_date')" class="flex items-start gap-2">
                        <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm mt-0.5">calendar_today</span>
                        <div>
                          <span class="font-semibold">Recovery Date:</span>
                          <span class="ml-2">{{ formatDate(getRecoveryInfo(item.remarks, 'recovery_date')) }}</span>
                        </div>
                      </div>
                      <div v-if="getRecoveryInfo(item.remarks, 'processed_by')" class="flex items-start gap-2">
                        <span class="material-icons-outlined text-green-600 dark:text-green-400 text-sm mt-0.5">verified</span>
                        <div>
                          <span class="font-semibold">Processed By:</span>
                          <span class="ml-2">{{ getRecoveryInfo(item.remarks, 'processed_by') }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Lost/Damaged Item Details -->
                  <div v-if="item.status === 'LOST' || item.status === 'DAMAGED'" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-sm text-red-700 dark:text-red-300 border-l-4 border-red-500">
                    <div class="flex items-center justify-between mb-3">
                      <p class="font-bold text-base">{{ item.status }} Item Details:</p>
                      <span v-if="item.remarks && isRepeatIncident(item.remarks)" class="px-2 py-0.5 bg-red-200 dark:bg-red-800 text-red-800 dark:text-red-200 rounded-full text-xs font-bold flex items-center gap-1">
                        <span class="material-icons-outlined text-xs">warning</span>
                        Incident #{{ getIncidentNumber(item.remarks) }}
                      </span>
                    </div>
                    <div v-if="item.remarks" class="space-y-2">
                      <p v-if="getLostDamagedInfo(item.remarks, 'description')" class="text-sm">
                        <strong class="font-semibold">Description:</strong> 
                        <span class="ml-2">{{ getLostDamagedInfo(item.remarks, 'description') }}</span>
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'reported_by')" class="text-sm">
                        <strong class="font-semibold">Reported By:</strong> 
                        <span class="ml-2">{{ getLostDamagedInfo(item.remarks, 'reported_by') }}</span>
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'incident_date')" class="text-sm">
                        <strong class="font-semibold">Incident Date:</strong> 
                        <span class="ml-2">{{ formatDate(getLostDamagedInfo(item.remarks, 'incident_date')) }}</span>
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'estimated_value_loss')" class="text-sm">
                        <strong class="font-semibold">Estimated Value Loss:</strong> 
                        <span class="ml-2">₱{{ parseFloat(getLostDamagedInfo(item.remarks, 'estimated_value_loss')).toLocaleString() }}</span>
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'reported_at')" class="text-sm">
                        <strong class="font-semibold">Reported At:</strong> 
                        <span class="ml-2">{{ formatDate(getLostDamagedInfo(item.remarks, 'reported_at')) }}</span>
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'processed_by')" class="text-sm">
                        <strong class="font-semibold">Processed By:</strong> 
                        <span class="ml-2">{{ getLostDamagedInfo(item.remarks, 'processed_by') }}</span>
                      </p>
                      <p v-if="getLostDamagedInfo(item.remarks, 'processed_at')" class="text-sm">
                        <strong class="font-semibold">Processed At:</strong> 
                        <span class="ml-2">{{ formatDate(getLostDamagedInfo(item.remarks, 'processed_at')) }}</span>
                      </p>
                    </div>
                    <div v-else class="text-red-600 dark:text-red-400 italic text-sm">
                      No additional details available.
                    </div>
                    
                    <!-- Previous Incidents History -->
                    <div v-if="hasPreviousIncidents(item.remarks)" class="mt-3 pt-3 border-t border-red-300 dark:border-red-700">
                      <p class="font-semibold mb-2 text-red-800 dark:text-red-200 flex items-center gap-1">
                        <span class="material-icons-outlined text-sm">history</span>
                        Previous Incident History
                      </p>
                      <div v-for="(incident, index) in getPreviousIncidents(item.remarks)" :key="index" class="mb-2 p-2 bg-red-100 dark:bg-red-900/30 rounded border border-red-200 dark:border-red-800">
                        <p class="font-medium text-xs mb-1">Incident #{{ incident.incident_number || index + 1 }} - {{ incident.original_status || 'LOST/DAMAGED' }}</p>
                        <p v-if="incident.original_remarks && incident.original_remarks.description" class="text-xs opacity-90">
                          <strong>Description:</strong> {{ incident.original_remarks.description }}
                        </p>
                        <p v-if="incident.original_remarks && incident.original_remarks.reported_by" class="text-xs opacity-90">
                          <strong>Reported By:</strong> {{ incident.original_remarks.reported_by }}
                        </p>
                        <div v-if="incident.recovery_info" class="mt-1 pt-1 border-t border-red-200 dark:border-red-800">
                          <p class="text-xs font-medium text-green-700 dark:text-green-300">Recovery:</p>
                          <p v-if="incident.recovery_info.recovery_notes" class="text-xs opacity-90">
                            <strong>Notes:</strong> {{ incident.recovery_info.recovery_notes }}
                          </p>
                          <p v-if="incident.recovery_info.recovered_by" class="text-xs opacity-90">
                            <strong>Recovered By:</strong> {{ incident.recovery_info.recovered_by }}
                          </p>
                          <p v-if="incident.recovery_info.recovery_date" class="text-xs opacity-90">
                            <strong>Date:</strong> {{ formatDate(incident.recovery_info.recovery_date) }}
                          </p>
                        </div>
                      </div>
                    </div>
                    <!-- Recovery Button (Admin only) -->
                    <div v-if="isAdmin()" class="mt-3">
                      <button
                        @click="openRecoveryModal(item)"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                      >
                        <span class="material-icons-outlined text-sm">restore</span>
                        <span>Recover Item</span>
                      </button>
                    </div>
                  </div>
                  
                  <!-- Report Lost/Damaged Button (for ISSUED items only) -->
                  <div v-if="item.status === 'ISSUED'" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button
                      @click="openReportModal(item)"
                      class="w-full px-4 py-2.5 bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                    >
                      <span class="material-icons-outlined text-base">error_outline</span>
                      <span>Report Lost/Damaged</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recovery Modal -->
    <div
      v-if="showRecoveryModal && selectedItemForRecovery"
      class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-[9999] p-4"
      @click.self="closeRecoveryModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col border-2 border-green-500/20">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5 border-b-2 border-green-800 flex items-center justify-between shadow-lg">
          <div class="flex items-center gap-4">
            <div class="p-2.5 bg-white/25 backdrop-blur-sm rounded-lg shadow-md">
              <span class="material-icons-outlined text-white text-2xl">restore</span>
            </div>
            <div>
              <h2 class="text-2xl font-bold text-white leading-tight">Recover Item</h2>
              <p class="text-sm text-green-100 mt-0.5">Mark item as found/repaired and restore to you</p>
            </div>
          </div>
          <button
            @click="closeRecoveryModal"
            class="p-2 text-white hover:bg-white/30 rounded-lg transition-all duration-200 hover:scale-110"
            :disabled="recoveryLoading"
          >
            <span class="material-icons-outlined text-2xl">close</span>
          </button>
        </div>
        
        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto p-6 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
          <!-- Item Info -->
          <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-3">
              <span class="material-icons-outlined text-green-600 dark:text-green-400 text-xl">info</span>
              Item Details
            </h3>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span class="text-gray-600 dark:text-gray-400">Item:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForRecovery.item?.unit || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-400">Status:</span>
                <span class="ml-2 font-semibold" :class="selectedItemForRecovery.status === 'LOST' ? 'text-red-600' : 'text-orange-600'">
                  {{ selectedItemForRecovery.status }}
                </span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-400">Serial Number:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForRecovery.item?.serial_number || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-gray-600 dark:text-gray-400">Model:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForRecovery.item?.model || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Recovery Form -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-6 space-y-6">
              <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                  Recovery Notes <span class="text-red-500 font-bold">*</span>
                </label>
                <textarea
                  v-model="recoveryForm.recovery_notes"
                  rows="4"
                  required
                  class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-200 resize-none"
                  placeholder="Enter details about how the item was found or repaired..."
                ></textarea>
                <p class="text-xs text-gray-500 dark:text-gray-400">Describe the recovery circumstances (e.g., "Item found in storage room", "Item repaired by technician")</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Recovered By
                  </label>
                  <input
                    v-model="recoveryForm.recovered_by"
                    type="text"
                    class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-200"
                    placeholder="Enter name or code"
                  />
                </div>

                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                    Recovery Date
                  </label>
                  <input
                    v-model="recoveryForm.recovery_date"
                    type="date"
                    class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-200"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t-2 border-gray-200 dark:border-gray-700 flex justify-end gap-3 shadow-lg">
          <button
            @click="closeRecoveryModal"
            class="px-6 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 font-semibold shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="recoveryLoading"
          >
            Cancel
          </button>
          <button
            @click="recoverItem"
            :disabled="recoveryLoading || !recoveryForm.recovery_notes.trim()"
            class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white rounded-lg transition-all duration-200 font-semibold shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <span v-if="recoveryLoading" class="material-icons-outlined animate-spin text-xl">refresh</span>
            <span v-else class="material-icons-outlined text-xl">check_circle</span>
            <span>{{ recoveryLoading ? 'Recovering...' : 'Recover Item' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Report Lost/Damaged Modal -->
    <div
      v-if="showReportModal && selectedItemForReport"
      class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-[9999] p-2 sm:p-4 overflow-y-auto"
      @click.self="closeReportModal"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full my-4 flex flex-col border-2 border-red-500/20 max-h-[95vh] sm:max-h-[90vh]">
        <!-- Red Banner at Top -->
        <div class="bg-red-600 px-4 sm:px-6 py-3 border-b border-red-700 flex-shrink-0">
          <p class="text-white text-xs sm:text-sm font-medium text-center">Report this item as lost or damaged. Admin will be notified.</p>
        </div>

        <!-- Modal Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-shrink-0">
          <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white">Report Lost/Damaged Item</h2>
          <button
            @click="closeReportModal"
            class="p-1.5 sm:p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-700 flex-shrink-0"
            :disabled="reportLoading"
          >
            <span class="material-icons-outlined text-xl sm:text-2xl">close</span>
          </button>
        </div>
        
        <!-- Modal Body - Scrollable -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 bg-white dark:bg-gray-800 min-h-0">
          <!-- Item Info -->
          <div class="mb-4 sm:mb-6 p-3 sm:p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
            <h3 class="text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-2 sm:mb-3">
              <span class="material-icons-outlined text-gray-600 dark:text-gray-400 text-base sm:text-lg">info</span>
              Item Details
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 text-xs sm:text-sm">
              <div class="break-words">
                <span class="text-gray-600 dark:text-gray-400">Item:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForReport.item?.unit || 'N/A' }}</span>
              </div>
              <div class="break-words">
                <span class="text-gray-600 dark:text-gray-400">Category:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ getCategoryName(selectedItemForReport) }}</span>
              </div>
              <div class="break-words">
                <span class="text-gray-600 dark:text-gray-400">Serial Number:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForReport.item?.serial_number || 'N/A' }}</span>
              </div>
              <div class="break-words">
                <span class="text-gray-600 dark:text-gray-400">Model:</span>
                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ selectedItemForReport.item?.model || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Report Form -->
          <div class="space-y-4 sm:space-y-6">
            <div class="space-y-2">
              <label class="block text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300">
                Status <span class="text-red-500 font-bold">*</span>
              </label>
              <div class="flex flex-col sm:flex-row gap-3 sm:gap-6">
                <label class="flex items-center gap-2 cursor-pointer group">
                  <input
                    v-model="reportForm.status"
                    type="radio"
                    value="LOST"
                    class="w-4 h-4 sm:w-5 sm:h-5 text-red-600 focus:ring-red-500 border-gray-300 cursor-pointer flex-shrink-0"
                  />
                  <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5 group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors">
                    <span class="material-icons-outlined text-base sm:text-lg" :class="reportForm.status === 'LOST' ? 'text-red-600' : 'text-gray-400'">error</span>
                    Lost
                  </span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer group">
                  <input
                    v-model="reportForm.status"
                    type="radio"
                    value="DAMAGED"
                    class="w-4 h-4 sm:w-5 sm:h-5 text-orange-600 focus:ring-orange-500 border-gray-300 cursor-pointer flex-shrink-0"
                  />
                  <span class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1.5 group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                    <span class="material-icons-outlined text-base sm:text-lg" :class="reportForm.status === 'DAMAGED' ? 'text-orange-600' : 'text-gray-400'">warning</span>
                    Damaged
                  </span>
                </label>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300">
                Description/Remarks <span class="text-red-500 font-bold">*</span>
              </label>
              <textarea
                v-model="reportForm.remarks"
                rows="4"
                required
                class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all duration-200 resize-none"
                :placeholder="reportForm.status === 'LOST' ? 'Describe how the item was lost, when it was last seen, and any relevant details...' : 'Describe the damage, when it occurred, and any relevant details...'"
              ></textarea>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ reportForm.status === 'LOST' ? 'Provide details about when and how the item was lost.' : 'Provide details about the nature and extent of the damage.' }}
              </p>
            </div>

            <div class="space-y-2">
              <label class="block text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300">
                Incident Date
              </label>
              <input
                v-model="reportForm.incident_date"
                type="date"
                class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all duration-200"
              />
            </div>

            <div v-if="reportForm.status === 'DAMAGED'" class="space-y-2">
              <label class="block text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300">
                Estimated Value Loss (₱)
              </label>
              <input
                v-model="reportForm.estimated_value_loss"
                type="number"
                min="0"
                step="0.01"
                class="w-full px-3 sm:px-4 py-2 sm:py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all duration-200"
                placeholder="0.00"
              />
            </div>

            <!-- Warning Note -->
            <div class="p-3 sm:p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border-l-4 border-amber-500">
              <p class="text-xs sm:text-sm text-amber-800 dark:text-amber-200 flex items-start gap-2">
                <span class="material-icons-outlined text-amber-600 dark:text-amber-400 text-base sm:text-lg flex-shrink-0">info</span>
                <span>
                  <strong>Note:</strong> Once you submit this report, the item status will be updated immediately and administrators will be notified for review and action.
                </span>
              </p>
            </div>
          </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-4 sm:px-6 py-3 sm:py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row justify-end gap-2 sm:gap-3 flex-shrink-0">
          <button
            @click="closeReportModal"
            class="w-full sm:w-auto px-4 sm:px-6 py-2 sm:py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 text-xs sm:text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="reportLoading"
          >
            Cancel
          </button>
          <button
            @click="reportLostOrDamaged"
            :disabled="reportLoading || !reportForm.remarks.trim()"
            class="w-full sm:w-auto px-4 sm:px-6 py-2 sm:py-2.5 bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white rounded-lg transition-all duration-200 text-xs sm:text-sm font-semibold shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <span v-if="reportLoading" class="material-icons-outlined text-sm sm:text-base animate-spin">refresh</span>
            <span v-else class="material-icons-outlined text-sm sm:text-base">report</span>
            <span>{{ reportLoading ? 'Reporting...' : 'Report Item' }}</span>
          </button>
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
.material-icons-outlined {
  font-size: 20px;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-4 py-2.5 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

/* Grid pattern background */
.bg-grid-pattern {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.1) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
  background-size: 20px 20px;
}

/* Fade in animation */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-out;
}

/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
}
</style>