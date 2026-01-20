<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- Enhanced Header Section -->
    <div class="bg-green-600 rounded-xl shadow-lg">
      <div class="px-4 py-5 sm:px-6 sm:py-6 md:px-8 md:py-7">
        <div class="flex items-center gap-3 sm:gap-4 md:gap-5">
          <!-- Back Button -->
          <button 
            @click="goBack" 
            class="p-2.5 sm:p-3 bg-white/20 hover:bg-white/30 rounded-lg transition-all duration-200 flex-shrink-0"
            title="Go back"
          >
            <span class="material-icons-outlined text-white text-xl sm:text-2xl">arrow_back</span>
          </button>
          
          <!-- Add Icon Button -->
          <div class="p-2.5 sm:p-3 bg-white/20 rounded-lg flex-shrink-0">
            <span class="material-icons-outlined text-white text-xl sm:text-2xl">person_add</span>
          </div>
          
          <!-- Title and Subtitle -->
          <div class="flex-1 min-w-0">
            <h1 class="text-lg sm:text-xl md:text-2xl font-bold text-white mb-0.5 sm:mb-1 leading-tight">
              Add New Account
            </h1>
            <p class="text-green-100 text-xs sm:text-sm leading-tight">
              Create a new user account for the system
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Form -->
    <div class="max-w-3xl mx-auto space-y-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Account Information Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">badge</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Account Information</h2>
                <p class="text-xs text-green-100">Basic user account details</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <!-- Account Type -->
            <div class="form-group">
              <label class="form-label">Account Type <span class="text-red-500">*</span></label>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                <div
                  v-for="type in accountTypes"
                  :key="type.value"
                  @click="formData.accountType = type.value"
                  class="account-type-card relative cursor-pointer rounded-xl border-2 transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                  :class="{
                    'border-green-500 bg-green-50 dark:bg-green-900/20 shadow-md ring-2 ring-green-500 ring-opacity-50': formData.accountType === type.value,
                    'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:border-green-400 dark:hover:border-green-500': formData.accountType !== type.value
                  }"
                >
                  <!-- Selected Indicator -->
                  <div
                    v-if="formData.accountType === type.value"
                    class="absolute top-3 right-3 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center shadow-md"
                  >
                    <span class="material-icons-outlined text-white text-sm">check</span>
                  </div>
                  
                  <!-- Card Content -->
                  <div class="p-5 flex flex-col items-center text-center space-y-3">
                    <!-- Icon -->
                    <div
                      class="w-16 h-16 rounded-full flex items-center justify-center transition-all duration-300"
                      :class="{
                        'bg-green-500 text-white shadow-lg': formData.accountType === type.value,
                        'bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300': formData.accountType !== type.value
                      }"
                    >
                      <span class="material-icons-outlined text-3xl">{{ type.icon }}</span>
                    </div>
                    
                    <!-- Title -->
                    <div>
                      <h3
                        class="font-bold text-lg transition-colors duration-300"
                        :class="{
                          'text-green-700 dark:text-green-400': formData.accountType === type.value,
                          'text-gray-800 dark:text-white': formData.accountType !== type.value
                        }"
                      >
                        {{ type.label }}
                      </h3>
                    </div>
                    
                    <!-- Description -->
                    <p
                      class="text-xs leading-relaxed transition-colors duration-300"
                      :class="{
                        'text-green-600 dark:text-green-300': formData.accountType === type.value,
                        'text-gray-500 dark:text-gray-400': formData.accountType !== type.value
                      }"
                    >
                      {{ type.description }}
                    </p>
                    
                    <!-- Features List -->
                    <ul class="text-left w-full space-y-1.5 mt-2">
                      <li
                        v-for="feature in type.features"
                        :key="feature"
                        class="flex items-start gap-2 text-xs"
                        :class="{
                          'text-green-700 dark:text-green-300': formData.accountType === type.value,
                          'text-gray-600 dark:text-gray-400': formData.accountType !== type.value
                        }"
                      >
                        <span
                          class="material-icons-outlined text-sm mt-0.5 flex-shrink-0"
                          :class="{
                            'text-green-500': formData.accountType === type.value,
                            'text-gray-400 dark:text-gray-500': formData.accountType !== type.value
                          }"
                        >
                          {{ formData.accountType === type.value ? 'check_circle' : 'radio_button_unchecked' }}
                        </span>
                        <span>{{ feature }}</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <p v-if="errors.account_type" class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
                <span class="material-icons-outlined text-base">error</span>
                {{ errors.account_type[0] }}
              </p>
              <p v-else class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <span class="material-icons-outlined text-sm">info</span>
                Select the appropriate account type for this user
              </p>
            </div>

            <!-- Full Name -->
            <div class="form-group">
              <label class="form-label">Full Name <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">person</span>
                </span>
                <input 
                  type="text" 
                  v-model="formData.fullname"
                  class="form-input-enhanced"
                  placeholder="Enter full name"
                  required
                >
                <p v-if="errors.fullname" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.fullname[0] }}</p>
              </div>
            </div>

            <!-- Email -->
            <div class="form-group">
              <label class="form-label">Email <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">email</span>
                </span>
                <input 
                  type="email" 
                  v-model="formData.email"
                  class="form-input-enhanced"
                  placeholder="Enter email address"
                  required
                >
                <p v-if="errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.email[0] }}</p>
              </div>
            </div>

            <!-- Location -->
            <div class="form-group">
              <label class="form-label">Location <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">location_on</span>
                </span>
                <select
                  v-model.number="formData.location"
                  class="form-select-enhanced"
                  :class="{ 'text-gray-400': formData.location === null }"
                  :disabled="locationsLoading || isSubmitting"
                  required
                >
                  <option :value="null" disabled hidden>Select location</option>
                  <option 
                    v-for="location in locations" 
                    :key="location.id || location.location_id" 
                    :value="Number(location.id || location.location_id)"
                  >
                    {{ location.location }}
                  </option>
                </select>
              </div>
              <p v-if="locationsLoading" class="mt-2 text-xs text-yellow-600 dark:text-yellow-400 flex items-center gap-1">
                <span class="material-icons-outlined text-sm">hourglass_empty</span>
                Loading locations...
              </p>
              <p v-if="!locationsLoading && locations.length === 0" class="mt-2 text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                <span class="material-icons-outlined text-sm">warning</span>
                No locations available. Please add locations first.
              </p>
              <p v-if="errors.location" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.location[0] }}</p>
              <p v-if="errors.location_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.location_id[0] }}</p>
            </div>
          </div>
        </div>

        <!-- Avatar Upload Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">image</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Profile Avatar</h2>
                <p class="text-xs text-green-100">Upload user profile image (optional)</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="form-group">
              <label class="form-label">Avatar Image</label>
              <div class="mt-2">
                <div
                  @click="$refs.fileInput.click()"
                  @dragover.prevent="dragOver = true"
                  @dragleave.prevent="dragOver = false"
                  @drop.prevent="handleFileDrop"
                  class="flex flex-col items-center justify-center w-full h-40 px-4 transition-all duration-200 bg-gray-50 dark:bg-gray-700 border-2 border-dashed rounded-xl appearance-none cursor-pointer hover:border-green-400 dark:hover:border-green-500 hover:bg-green-50/30 dark:hover:bg-green-900/20"
                  :class="{ 
                    'border-gray-300 dark:border-gray-600': !selectedFile,
                    'border-green-500 dark:border-green-600 border-solid bg-green-50/50 dark:bg-green-900/20': selectedFile,
                    'border-blue-400 dark:border-blue-500 border-solid bg-blue-50 dark:bg-blue-900/20': dragOver
                  }"
                >
                  <div v-if="selectedFile" class="flex items-center gap-4 w-full">
                    <img
                      v-if="previewUrl"
                      :src="previewUrl"
                      class="w-20 h-20 object-cover rounded-lg border-2 border-green-200 shadow-md"
                      alt="Preview"
                    />
                    <div class="flex-1 flex flex-col">
                      <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedFile.name }}</span>
                      <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ formatFileSize(selectedFile.size) }}</span>
                    </div>
                    <button
                      @click.stop="clearFile"
                      class="p-2 text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all"
                      title="Remove file"
                    >
                      <span class="material-icons-outlined">close</span>
                    </button>
                  </div>
                  <div v-else class="flex flex-col items-center gap-3 py-4">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                      <span class="material-icons-outlined text-green-600 dark:text-green-400 text-3xl">cloud_upload</span>
                    </div>
                    <div class="text-center">
                      <span class="font-semibold text-gray-700 dark:text-white">
                        <span class="text-green-600 dark:text-green-400 hover:underline">Choose file</span> or drag and drop
                      </span>
                      <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">PNG, JPG, GIF up to 10MB</p>
                    </div>
                  </div>
                  <input
                    ref="fileInput"
                    type="file"
                    @change="handleImageUpload"
                    accept="image/*"
                    class="hidden"
                  >
                </div>
                <p v-if="errors.avatar" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ errors.avatar[0] }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Password Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">lock</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">Password Security</h2>
                <p class="text-xs text-green-100">Set a strong password for account security</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <!-- Password -->
            <div class="form-group">
              <label class="form-label">Password <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">lock</span>
                </span>
                <input 
                  :type="showPassword ? 'text' : 'password'" 
                  v-model="formData.password"
                  class="form-input-enhanced pr-12"
                  placeholder="Enter password"
                  required
                  @input="checkPasswordRequirements"
                >
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3 z-10 flex items-center text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none transition-colors"
                  tabindex="-1"
                >
                  <span class="material-icons-outlined text-xl">
                    {{ showPassword ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </div>
              <!-- Password Strength Meter -->
              <div v-if="formData.password" class="mt-3">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-semibold text-gray-700 dark:text-white">Password Strength:</span>
                  <span 
                    class="text-xs font-bold"
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
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                  <div 
                    class="h-2.5 rounded-full transition-all duration-300"
                    :class="{
                      'bg-red-500 dark:bg-red-500': passwordStrength.level === 'weak',
                      'bg-yellow-500 dark:bg-yellow-500': passwordStrength.level === 'fair',
                      'bg-blue-500 dark:bg-blue-500': passwordStrength.level === 'good',
                      'bg-green-500 dark:bg-green-500': passwordStrength.level === 'strong',
                      'bg-gray-300 dark:bg-gray-600': passwordStrength.level === 'none'
                    }"
                    :style="{ width: `${passwordStrength.percentage}%` }"
                  ></div>
                </div>
              </div>
              <!-- Password Requirements Checklist -->
              <div v-if="formData.password" class="mt-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                <p class="text-xs font-semibold text-gray-700 dark:text-white mb-3">Password must contain:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                  <div class="flex items-center gap-2" :class="passwordRequirements.minLength ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-base">{{ passwordRequirements.minLength ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>Minimum 8 characters</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasUpperCase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-base">{{ passwordRequirements.hasUpperCase ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one uppercase letter</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasLowerCase ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-base">{{ passwordRequirements.hasLowerCase ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one lowercase letter</span>
                  </div>
                  <div class="flex items-center gap-2" :class="passwordRequirements.hasDigit ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-base">{{ passwordRequirements.hasDigit ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one digit</span>
                  </div>
                  <div class="flex items-center gap-2 sm:col-span-2" :class="passwordRequirements.hasSpecialChar ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                    <span class="material-icons-outlined text-base">{{ passwordRequirements.hasSpecialChar ? 'check_circle' : 'radio_button_unchecked' }}</span>
                    <span>At least one special symbol (!@#$%^&*()_+-=[]{}|;:,.<>?)</span>
                  </div>
                </div>
              </div>
              <p v-if="errors.password" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ errors.password[0] }}</p>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label class="form-label">Confirm Password <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">lock</span>
                </span>
                <input 
                  :type="showConfirmPassword ? 'text' : 'password'" 
                  v-model="formData.confirmPassword"
                  class="form-input-enhanced pr-12"
                  placeholder="Confirm password"
                  required
                  @input="() => { errors.password_confirmation = []; checkPasswordMatch(); }"
                >
                <button
                  type="button"
                  @click="showConfirmPassword = !showConfirmPassword"
                  class="absolute right-3 z-10 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none transition-colors"
                  tabindex="-1"
                >
                  <span class="material-icons-outlined text-xl">
                    {{ showConfirmPassword ? 'visibility_off' : 'visibility' }}
                  </span>
                </button>
              </div>
              <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.password_confirmation[0] }}</p>
            </div>
          </div>
        </div>

        <!-- General Error Message -->
        <div v-if="errors.general" class="bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-700 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-red-600 dark:text-red-400">error</span>
            <p class="text-sm font-semibold text-red-700 dark:text-red-300">{{ errors.general[0] }}</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4">
          <button 
            type="button"
            @click="goBack"
            class="px-6 py-3 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 hover:border-gray-400 dark:hover:border-gray-500 font-semibold transition-all duration-200 shadow-sm hover:shadow-md"
          >
            Cancel
          </button>
          <button 
            type="submit"
            :disabled="isSubmitting"
            class="btn-primary-enhanced disabled:opacity-75 disabled:cursor-not-allowed"
          >
            <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-lg">refresh</span>
            {{ isSubmitting ? 'Creating...' : 'Create Account' }}
          </button>
        </div>
        </form>
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

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import axiosClient from '../axios'
import useLocations from '../composables/useLocations'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const route = useRoute()
const errors = ref({})
const dragOver = ref(false)
const selectedFile = ref(null)
const previewUrl = ref(null)
const fileInput = ref(null)
const isSubmitting = ref(false)

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
  if (!formData.value.password) return { level: 'none', label: '', color: '', percentage: 0 }
  
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

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')
const hasNavigated = ref(false)

const formData = ref({
  accountType: '',
  avatar: null,
  fullname: '',
  email: '',
  location: null, // Initialize as null for placeholder
  password: '',
  confirmPassword: ''
})

// Enhanced account types with icons, descriptions, and features
const accountTypes = ref([
  {
    value: 'admin',
    label: 'Administrator',
    icon: 'admin_panel_settings',
    description: 'Full system access and management capabilities',
    features: [
      'Complete system control',
      'User management',
      'All permissions',
      'System configuration'
    ]
  },
  {
    value: 'user',
    label: 'Standard User',
    icon: 'person',
    description: 'Regular user with standard access rights',
    features: [
      'View inventory',
      'Borrow items',
      'Basic reporting',
      'Profile management'
    ]
  },
  {
    value: 'supply',
    label: 'Supply Officer',
    icon: 'inventory_2',
    description: 'Inventory and supply management specialist',
    features: [
      'Manage inventory',
      'Track supplies',
      'Usage reports',
      'Item management'
    ]
  }
])

const { locations, fetchLocations, loading: locationsLoading } = useLocations(formData)

// Set account type from URL parameter and fetch locations
onMounted(async () => {
  console.log('=== COMPONENT MOUNTED ===')
  console.log('Route query:', route.query)
  console.log('Current form data:', formData.value)
  
  // Fetch locations first
  try {
    await fetchLocations(1, 1000)
    console.log('Locations loaded:', locations.value.length)
    console.log('Available locations:', locations.value)
  } catch (error) {
    console.error('Error fetching locations:', error)
  }
  
  // Set account type from URL parameter
  const accountTypeFromUrl = route.query.type
  console.log('Account type from URL:', accountTypeFromUrl)
  
  if (accountTypeFromUrl && (accountTypeFromUrl === 'admin' || accountTypeFromUrl === 'user' || accountTypeFromUrl === 'supply')) {
    formData.value.accountType = accountTypeFromUrl
    console.log('Account type set to:', formData.value.accountType)
  } else {
    console.log('No valid account type found in URL')
  }
  
  console.log('Final form data after URL processing:', formData.value)
})

// const locations = ref([])

// // Fetch locations from the backend
// const fetchLocations = async () => {
//   try {
//     const response = await axiosClient.get('/locations')
//     if (response.data && response.data.data) {
//       locations.value = response.data.data
//       console.log('Available locations:', locations.value)
//     }
//   } catch (error) {
//     console.error('Error fetching locations:', error)
//   }
// }

// // Watch for location changes
// watch(() => formData.value.location, (newValue) => {
//   console.log('Location selected:', newValue)
// })

// // Call fetchLocations when component is mounted
// onMounted(() => {
//   fetchLocations()
// })

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const handleFileDrop = (event) => {
  dragOver.value = false
  const file = event.dataTransfer.files[0]
  if (file && file.type.startsWith('image/')) {
    handleFileSelection(file)
  }
}

const handleFileSelection = (file) => {
  if (file.size > 10 * 1024 * 1024) { // 10MB limit
    errors.value.avatar = ['File size should not exceed 10MB']
    return
  }
  
  selectedFile.value = file
  formData.value.avatar = file
  
  // Create preview URL
  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = e.target.result
  }
  reader.readAsDataURL(file)
}

const handleImageUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    handleFileSelection(file)
  }
}

const clearFile = () => {
  selectedFile.value = null
  previewUrl.value = null
  formData.value.avatar = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const goBack = () => {
  router.push('/admin')
}

// Check password requirements in real-time
const checkPasswordRequirements = () => {
  const password = formData.value.password || ''
  
  passwordRequirements.value = {
    minLength: password.length >= 8,
    hasUpperCase: /[A-Z]/.test(password),
    hasLowerCase: /[a-z]/.test(password),
    hasDigit: /[0-9]/.test(password),
    hasSpecialChar: /[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/.test(password)
  }
  
  // Clear password errors when user starts typing
  if (password) {
    errors.value.password = []
  }
}

// Check password match in real-time
const checkPasswordMatch = () => {
  if (formData.value.confirmPassword && formData.value.password !== formData.value.confirmPassword) {
    // Don't show error immediately, only on submit or blur
    // This allows user to type without constant error messages
  } else {
    errors.value.password_confirmation = []
  }
}

const validatePasswords = () => {
  const password = formData.value.password
  
  // Check password match
  if (password !== formData.value.confirmPassword) {
    errors.value.password_confirmation = ['Passwords do not match']
    return false
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
    errors.value.password = validationErrors
    return false
  }
  
  // Clear any previous errors if validation passes
  errors.value.password = []
  errors.value.password_confirmation = []
  return true
}

const handleSubmit = async () => {
  console.log('=== FORM SUBMISSION STARTED ===')
  console.log('isSubmitting:', isSubmitting.value)
  
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true
    errors.value = {}

    console.log('Form data before validation:', formData.value)

    // Validate all required fields
    if (!formData.value.accountType) {
      console.log('ERROR: Account type not set')
      errors.value.account_type = ['Please select an account type']
      isSubmitting.value = false
      return
    }

    if (!formData.value.fullname.trim()) {
      console.log('ERROR: Full name not set')
      errors.value.fullname = ['Full name is required']
      isSubmitting.value = false
      return
    }

    if (!formData.value.email.trim()) {
      console.log('ERROR: Email not set')
      errors.value.email = ['Email is required']
      isSubmitting.value = false
      return
    }

    if (!formData.value.location) {
      console.log('ERROR: Location not set')
      errors.value.location = ['Please select a location']
      isSubmitting.value = false
      return
    }

    // Validate passwords
    if (!validatePasswords()) {
      console.log('ERROR: Password validation failed')
      isSubmitting.value = false
      return
    }

    console.log('All validations passed, proceeding with submission...')

    // Create FormData object for file upload
    const formDataToSend = new FormData()
    
    // Append all form fields
    formDataToSend.append('role', formData.value.accountType)
    formDataToSend.append('fullname', formData.value.fullname)
    formDataToSend.append('email', formData.value.email)
    formDataToSend.append('location_id', formData.value.location)
    formDataToSend.append('password', formData.value.password)
    formDataToSend.append('password_confirmation', formData.value.confirmPassword)
    
    // Append the image file if it exists
    if (formData.value.avatar) {
      formDataToSend.append('image', formData.value.avatar)
    }

    // Debug: Log form data being sent
    console.log('Form data being sent:', {
      role: formData.value.accountType,
      fullname: formData.value.fullname,
      email: formData.value.email,
      location_id: formData.value.location,
      password: formData.value.password,
      password_confirmation: formData.value.confirmPassword,
      hasImage: !!formData.value.avatar
    })

    console.log('Sending request to /register endpoint...')

    // Send request to Laravel API (axios will handle Content-Type automatically for FormData)
    const response = await axiosClient.post('/register', formDataToSend)

    if (response.data) {
      console.log('Registration successful:', response.data)
      successMessage.value = 'Account created successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      hasNavigated.value = false
      
      // Reset form after successful creation
      setTimeout(() => {
        formData.value = {
          accountType: '',
          avatar: null,
          fullname: '',
          email: '',
          location: null, // Reset to null for placeholder
          password: '',
          confirmPassword: ''
        }
        selectedFile.value = null
        previewUrl.value = null
        if (fileInput.value) {
          fileInput.value.value = ''
        }
        errors.value = {}
      }, 2000)
      
      // Navigate to Admin page after showing success message (if user hasn't clicked the button)
      setTimeout(() => {
        if (!hasNavigated.value) {
          hasNavigated.value = true
          router.push('/admin')
        }
      }, 2500)
    }
  } catch (error) {
    console.error('Registration error:', error)
    console.error('Error response:', error.response)
    console.error('Error status:', error.response?.status)
    console.error('Error data:', error.response?.data)
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
      console.error('Validation errors:', error.response.data.errors)
      
      // Show specific error messages
      if (error.response.data.errors.email) {
        successMessage.value = `Email error: ${error.response.data.errors.email[0]}`
        successModalType.value = 'error'
        showSuccessModal.value = true
      } else if (error.response.data.errors.password) {
        successMessage.value = `Password error: ${error.response.data.errors.password[0]}`
        successModalType.value = 'error'
        showSuccessModal.value = true
      } else if (error.response.data.errors.location_id) {
        successMessage.value = `Location error: ${error.response.data.errors.location_id[0]}`
        successModalType.value = 'error'
        showSuccessModal.value = true
      }
    } else if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = `An unexpected error occurred: ${error.message}`
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    isSubmitting.value = false
    console.log('=== FORM SUBMISSION COMPLETED ===')
  }
}

// Close success modal
const closeSuccessModal = () => {
  const wasSuccess = successModalType.value === 'success' && successMessage.value.includes('successfully')
  
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
  
  // Navigate to Admin page if account was created successfully
  if (wasSuccess && !hasNavigated.value) {
    hasNavigated.value = true
    router.push('/admin')
  }
}
</script>

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

.form-input-enhanced::placeholder,
.form-select-enhanced::placeholder {
  @apply text-gray-400 dark:text-gray-400;
}

/* Dark mode support for select options */
.form-select-enhanced option {
  @apply bg-white dark:bg-gray-700 text-gray-900 dark:text-white;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  @apply bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-3 rounded-xl hover:from-green-700 hover:to-green-800 flex items-center text-sm font-semibold transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5;
}

.material-icons-outlined {
  font-size: 20px;
}

/* Improved focus states for accessibility */
button:focus, input:focus, select:focus {
  @apply outline-none ring-2 ring-green-500 ring-opacity-50;
}

input:disabled {
  @apply bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed;
}

select:disabled {
  @apply bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed;
}

/* Account Type Card Styles */
.account-type-card {
  position: relative;
  overflow: hidden;
}

.account-type-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #10b981, #059669);
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.account-type-card.border-green-500::before {
  transform: scaleX(1);
}

.account-type-card:hover::before {
  transform: scaleX(1);
}

/* Smooth animations */
.account-type-card {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.account-type-card:active {
  transform: scale(0.98);
}
</style>
