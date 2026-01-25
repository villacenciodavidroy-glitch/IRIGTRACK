

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8 space-y-6">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex items-center gap-4">
          <button 
            @click="goBack" 
            class="p-2 bg-white/20 backdrop-blur-sm rounded-lg hover:bg-white/30 transition-colors"
            title="Go back"
          >
            <span class="material-icons-outlined text-white text-xl">arrow_back</span>
          </button>
          <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
            <span class="material-icons-outlined text-4xl text-white">add_circle</span>
          </div>
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Add New Item</h1>
            <p class="text-green-100 text-sm sm:text-base">Create and register a new inventory item</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Form Container -->
    <div class="max-w-5xl mx-auto space-y-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Basic Information Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">description</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">{{ getSectionTitle('section_basic_info', 'Basic Information') }}</h2>
                <p class="text-xs text-green-100">{{ getSectionSubtitle('section_basic_info', 'Essential item details and identification') }}</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Article -->
          <div class="form-group">
            <label class="form-label">{{ getLabel('article', 'Article') }} <span class="text-red-500">*</span></label>
            <div class="relative flex items-center">
              <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                <span class="material-icons-outlined">inventory_2</span>
              </span>
              <input
                v-model="formData.unit"
                type="text"
                :placeholder="getPlaceholder('article', 'Enter article')"
                class="form-input-enhanced !pl-12"
                required
              />
            </div>
          </div>

          <!-- Category -->
          <div class="form-group">
            <label class="form-label">{{ getLabel('category', 'Category') }} <span class="text-red-500">*</span></label>
            <div class="relative flex items-center">
              <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                <span class="material-icons-outlined">category</span>
              </span>
              <select v-model="formData.category" class="form-select-enhanced !pl-12" required>
                <option value="" disabled>{{ getPlaceholder('category', 'Select category') }}</option>
                <option v-for="category in categories" 
                    :key="category.id" 
                    :value="category.id || category.category_id">
                  {{ category.category }}
                </option>
              </select>
            </div>
          </div>

              <!-- Description -->
              <div class="form-group md:col-span-2">
                <label class="form-label">{{ getLabel('description', 'Description') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">description</span>
                  </span>
                  <input type="text" v-model="formData.description" class="form-input-enhanced !pl-12" :placeholder="getPlaceholder('description', 'Enter description')" required>
                </div>
              </div>

              <!-- Serial Number -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('serial_number', 'Serial Number') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">qr_code</span>
                  </span>
                  <input
                    v-model="formData.serial_number"
                    type="text"
                    :placeholder="getPlaceholder('serial_number', 'Auto-generated serial number')"
                    class="form-input-enhanced !pl-12 !pr-24"
                    required
                  />
                  <button
                    type="button"
                    @click="generateSerialNumber"
                    class="absolute right-2 px-3 py-1.5 text-xs bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-1"
                    title="Generate new serial number"
                  >
                    <span class="material-icons-outlined text-sm">refresh</span>
                    <span>Generate</span>
                  </button>
                </div>
                <p v-if="getHelperText('serial_number')" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ getHelperText('serial_number') }}</p>
              </div>

              <!-- Model -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('model', 'Model') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">devices</span>
                  </span>
                  <input
                    v-model="formData.model"
                    type="text"
                    :placeholder="getPlaceholder('model', 'Enter model')"
                    class="form-input-enhanced !pl-12"
                    required
                  />
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
                <h2 class="text-lg font-bold text-white">{{ getSectionTitle('section_financial', 'Financial & Acquisition Details') }}</h2>
                <p class="text-xs text-green-100">{{ getSectionSubtitle('section_financial', 'Property account code, valuation, and acquisition information') }}</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Property Account Code -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('property_account_code', 'Property Account Code') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">qr_code</span>
                  </span>
                  <input type="text" v-model="formData.propertyAccountCode" class="form-input-enhanced !pl-12" :placeholder="getPlaceholder('property_account_code', 'Enter property account code')" required>
                </div>
              </div>

              <!-- Unit Value -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('unit_value', 'Unit Value') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">payments</span>
                  </span>
                  <input type="text" v-model="formData.unitValue" class="form-input-enhanced !pl-12" :placeholder="getPlaceholder('unit_value', '32,200.00')" required>
                </div>
              </div>

              <!-- Quantity -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('quantity', 'Quantity') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">inventory</span>
                  </span>
                  <input 
                    type="number" 
                    v-model="formData.quantity" 
                    class="form-input-enhanced !pl-12" 
                    :placeholder="getPlaceholder('quantity', 'Enter quantity')" 
                    min="1"
                    required
                  >
                </div>
              </div>

              <!-- Date Acquired -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('date_acquired', 'Date Acquired') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">calendar_today</span>
                  </span>
                  <input type="date" v-model="formData.dateAcquired" class="form-input-enhanced !pl-12" required>
                </div>
              </div>

              <!-- P.O Number -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('po_number', 'P.O Number') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">receipt</span>
                  </span>
                  <input type="text" v-model="formData.poNumber" class="form-input-enhanced !pl-12" :placeholder="getPlaceholder('po_number', 'Enter P.O number')" required>
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
                <h2 class="text-lg font-bold text-white">{{ getSectionTitle('section_assignment', 'Assignment & Unit/Sections') }}</h2>
                <p class="text-xs text-green-100">{{ getSectionSubtitle('section_assignment', 'Item unit/sections and personnel assignment') }}</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Unit/Sections -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('unit_sections', 'Unit/Sections') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">location_on</span>
                  </span>
                  <select v-model="formData.location" class="form-select-enhanced !pl-12" required>
                    <option value="" disabled>{{ getPlaceholder('unit_sections', 'Select Unit/Section') }}</option>
                    <option  v-for="location in locations" 
                        :key="location.id" 
                        :value="location.id || location.location_id">
                      {{ location.location }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Issued To -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('issued_to', 'Issued To') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">person</span>
                  </span>
                  <select v-model="formData.issuedTo" class="form-select-enhanced !pl-12" required>
                    <option value="" disabled>{{ getPlaceholder('issued_to', 'Select Personnel') }}</option>
                    <option v-for="location in locationsWithPersonnel" 
                        :key="location.id || location.location_id" 
                        :value="location.id || location.location_id">
                      {{ location.personnel_code || 'N/A' }} - {{ location.location }} (Personnel)
                    </option>
                  </select>
                </div>
                <p v-if="locationsWithPersonnel.length === 0" class="mt-2 text-xs text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border-l-4 border-amber-400 dark:border-amber-600 flex items-start gap-2">
                  <span class="material-icons-outlined text-sm text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5">info</span>
                  <span>No personnel assigned to any unit/section. Please assign personnel in Unit/Sections Management first.</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Condition & Status Section -->
        <div v-if="!isSupplyCategory" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-xl">build</span>
              </div>
              <div>
                <h2 class="text-lg font-bold text-white">{{ getSectionTitle('section_condition', 'Condition & Status') }}</h2>
                <p class="text-xs text-green-100">{{ getSectionSubtitle('section_condition', 'Item condition assessment and classification') }}</p>
              </div>
            </div>
          </div>
          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Condition -->
              <div class="form-group">
                <label class="form-label">Condition <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">build</span>
                  </span>
                  <select v-model="formData.condition" class="form-select-enhanced !pl-12" required>
                    <option value="" disabled>Select condition</option>
                    <option v-for="condition in conditions" 
                        :key="condition.id" 
                        :value="condition.id || condition.condition_id">
                      {{ condition.condition }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Condition Number -->
              <div class="form-group">
                <label class="form-label">{{ getLabel('condition_number', 'Condition Number') }} <span class="text-red-500">*</span></label>
                <div class="relative flex items-center">
                  <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                    <span class="material-icons-outlined">tag</span>
                  </span>
                  <select v-model="formData.conditionNumber" class="form-select-enhanced !pl-12" required>
                    <option value="" disabled>{{ getPlaceholder('condition_number', 'Select Condition Number') }}</option>
                    <option v-for="condition_number in filteredConditionNumbers" 
                        :key="condition_number.id" 
                        :value="condition_number.id || condition_number.condition_number_id">
                      {{ condition_number.condition_number }}
                    </option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Maintenance Information Section -->
        <div v-if="!isSupplyCategory && isOnMaintenance" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
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
            <!-- Maintenance Reason -->
            <div class="form-group">
              <label class="form-label">Maintenance Reason <span class="text-red-500">*</span></label>
              <div class="relative flex items-center">
                <span class="absolute left-4 text-green-600 dark:text-green-400 z-10">
                  <span class="material-icons-outlined">info</span>
                </span>
                <input
                  v-model="formData.maintenance_reason"
                  type="text"
                  class="form-input-enhanced !pl-12"
                  placeholder="Enter maintenance reason (e.g., Overheat, Wear, Electrical, etc.)"
                  required
                />
              </div>
              <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Enter the primary reason for this maintenance issue.
              </p>
            </div>
            
            <!-- Technician Notes -->
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
                  v-model="formData.technician_notes"
                  rows="4"
                  class="form-textarea-enhanced !pl-12"
                  placeholder="Enter detailed technician notes regarding the maintenance (e.g., issue description, repair steps, observations, test results, etc.)"
                  required
                ></textarea>
              </div>
              <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Detailed notes will be saved as technician_notes in the maintenance record (maintenance_records table).
              </p>
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
                <h2 class="text-lg font-bold text-white">{{ getSectionTitle('section_asset_image', 'Asset Image') }}</h2>
                <p class="text-xs text-green-100">{{ getSectionSubtitle('section_asset_image', 'Upload item photograph or documentation image') }}</p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="form-group">
              <label class="form-label">{{ getLabel('item_image', 'Item Image') }}</label>
              <div class="mt-3">
                <div
                  @click="$refs.fileInput.click()"
                  @dragover.prevent="dragOver = true"
                  @dragleave.prevent="dragOver = false"
                  @drop.prevent="handleFileDrop"
                  class="flex flex-col items-center justify-center w-full h-44 px-6 transition-all duration-200 bg-gradient-to-br from-gray-50 via-green-50/30 to-gray-50 dark:from-gray-700 dark:via-green-900/20 dark:to-gray-700 border-2 rounded-xl appearance-none cursor-pointer hover:shadow-lg"
                  :class="{ 
                    'border-dashed border-gray-300 dark:border-gray-600 hover:border-green-400 dark:hover:border-green-500': !selectedFile,
                    'border-solid border-green-500 dark:border-green-600 bg-green-50/50 dark:bg-green-900/20': selectedFile,
                    'border-green-500 dark:border-green-600 border-solid bg-green-50 dark:bg-green-900/30': dragOver
                  }"
                >
                  <div v-if="selectedFile" class="flex items-center gap-4 w-full p-4">
                    <div class="flex-shrink-0">
                      <div class="relative">
                        <img
                          v-if="previewUrl"
                          :src="previewUrl"
                          class="w-24 h-24 object-cover rounded-lg border-2 border-green-300 shadow-md"
                          alt="Preview"
                        />
                        <div class="absolute -top-1 -right-1 bg-green-500 text-white rounded-full p-1">
                          <span class="material-icons-outlined text-xs">check_circle</span>
                        </div>
                      </div>
                    </div>
                    <div class="flex-1 flex flex-col">
                      <span class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedFile.name }}</span>
                      <span class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ formatFileSize(selectedFile.size) }}</span>
                      <span class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">✓ Image ready to upload</span>
                    </div>
                    <button
                      @click.stop="clearFile"
                      class="p-2 text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                      title="Remove file"
                    >
                      <span class="material-icons-outlined">close</span>
                    </button>
                  </div>
                  <div v-else class="flex flex-col items-center gap-4 py-6">
                    <div class="p-5 bg-white dark:bg-gray-700 rounded-full shadow-md border-2 border-green-200 dark:border-green-600">
                      <span class="material-icons-outlined text-5xl text-green-600 dark:text-green-400">cloud_upload</span>
                    </div>
                    <div class="text-center space-y-1">
                      <span class="font-bold text-gray-800 dark:text-white block">
                        <span class="text-green-600 dark:text-green-400 hover:text-green-700 dark:hover:text-green-500 hover:underline">{{ getPlaceholder('item_image', 'Click to upload') }}</span> or drag and drop
                      </span>
                      <p v-if="getHelperText('item_image')" class="text-xs text-gray-600 dark:text-gray-400 font-medium">{{ getHelperText('item_image') }}</p>
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
                <p v-if="errors.image" class="mt-3 text-sm text-red-600 dark:text-red-400 flex items-center gap-2 bg-red-50 dark:bg-red-900/20 p-3 rounded-lg border-l-4 border-red-500 dark:border-red-600">
                  <span class="material-icons-outlined text-base">error_outline</span>
                  <span>{{ errors.image[0] }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons Section -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 p-6">
          <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <!-- Submit Button Group -->
            <div class="flex items-center gap-3">
              <button 
                type="button"
                @click="goBack"
                class="px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-400 dark:hover:border-gray-500 font-semibold transition-all duration-200"
              >
                Cancel
              </button>
              <button 
                type="submit"
                :disabled="isSubmitting"
                class="btn-primary-enhanced disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 min-w-[180px] justify-center"
              >
                <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-base">refresh</span>
                <span v-else class="material-icons-outlined text-base">add_circle</span>
                {{ isSubmitting ? 'Creating Item...' : 'Create Item' }}
              </button>
            </div>
          </div>
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
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import useLocations from '../composables/useLocations'
import useConditions from '../composables/useConditions'
import usecategories from '../composables/useCategories'
import useConditionNumbers from '../composables/useConditionNumbers'
import useFormLabels from '../composables/useFormLabels'
import axiosClient from '../axios'
import useUsers from '../composables/useUsers'
import SuccessModal from '../components/SuccessModal.vue'


const router = useRouter()
const errors = ref({})
const dragOver = ref(false)
const selectedFile = ref(null)
const previewUrl = ref(null)
const fileInput = ref(null)
const isSubmitting = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

const formData = ref({
  unit: '',
  category: '',
  description: '',
  serial_number: '',
  model: '',
  propertyAccountCode: '',
  unitValue: '',
  quantity: 1,
  dateAcquired: '',
  poNumber: '',
  location: '',
  issuedTo: '',
  condition: '',
  conditionNumber: '',
  image: '',
  maintenance_reason: '',
  technician_notes: ''
})

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
    errors.value.image = ['File size should not exceed 10MB']
    return
  }
  
  selectedFile.value = file
  formData.value.image = file
  
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
  formData.value.image = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}



const { conditions, fetchconditions } = useConditions(formData)
const { locations, fetchLocations } = useLocations(formData)
const { categories, fetchcategories } = usecategories(formData)
const { condition_numbers, fetchcondition_numbers } = useConditionNumbers(formData)
// Note: We're not using users anymore, but keeping for potential future use
const { users, fetchusers } = useUsers(formData)
const { fetchLabels, getLabel, getPlaceholder, getSectionTitle, getSectionSubtitle, getHelperText } = useFormLabels()

// const handleImageUpload = (event) => {
//   const file = event.target.files[0]
//   if (file) {
//     formData.value.image = file
//   }
// }

const goBack = () => {
  router.push('/inventory')
}

const handleSubmit = async () => {
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true
    errors.value = {}

    // Validate passwords
    // if (!validatePasswords()) {
    //   isSubmitting.value = false
    //   return
    // }

    if (!formData.value.category) {
      console.error('No category selected')
      errors.value.category = ['Please select a category']
      isSubmitting.value = false
      return
    }


    // Check if location is selected
    if (!formData.value.location) {
      console.error('No location selected')
      errors.value.location = ['Please select a unit/section']
      isSubmitting.value = false
      return
    }

    if (!isSupplyCategory.value) {
      if (!formData.value.condition) {
        console.error('No condition selected')
        errors.value.condition = ['Please select a condition']
        isSubmitting.value = false
        return
      }

    if (!formData.value.conditionNumber) {
      console.error('No condition number selected')
      errors.value.conditionNumber = ['Please select a condition number']
      isSubmitting.value = false
      return
    }

    // Validate maintenance fields if On Maintenance is selected
    if (isOnMaintenance.value) {
      if (!formData.value.maintenance_reason?.trim()) {
        errors.value.maintenance_reason = ['Please enter a maintenance reason when condition is set to "On Maintenance"']
        isSubmitting.value = false
        return
      }
      if (!formData.value.technician_notes?.trim()) {
        errors.value.technician_notes = ['Please provide technician notes when condition is set to "On Maintenance"']
        isSubmitting.value = false
        return
      }
    }
  }


    

    
    // Create FormData object for file upload
    const formDataToSend = new FormData()
    
    // Append all form fields
    formDataToSend.append('unit', formData.value.unit)
    formDataToSend.append('description', formData.value.description)
    formDataToSend.append('serial_number', formData.value.serial_number || '')
    formDataToSend.append('model', formData.value.model || '')
    formDataToSend.append('pac', formData.value.propertyAccountCode)
    formDataToSend.append('unit_value', formData.value.unitValue)
    formDataToSend.append('quantity', formData.value.quantity)
    formDataToSend.append('date_acquired', formData.value.dateAcquired)
    formDataToSend.append('po_number', formData.value.poNumber)
    formDataToSend.append('category_id', formData.value.category)
    formDataToSend.append('location_id', formData.value.location)
    formDataToSend.append('condition_id', formData.value.condition)
    formDataToSend.append('condition_number_id', formData.value.conditionNumber)
    
    // Append maintenance fields if On Maintenance is selected
    if (isOnMaintenance.value) {
      if (formData.value.maintenance_reason?.trim()) {
        formDataToSend.append('maintenance_reason', formData.value.maintenance_reason.trim())
      }
      if (formData.value.technician_notes?.trim()) {
        formDataToSend.append('technician_notes', formData.value.technician_notes.trim())
      }
    }
    
    // Find the selected location to get personnel info
    // Since backend expects user_id, we'll try to find matching user by personnel name
    const selectedLocation = locations.value.find(loc => 
      (loc.id || loc.location_id) == formData.value.issuedTo
    )
    
    // Try to find a user that matches the personnel name
    // Only send user_id if there's actually a matching user account
    // If assigning to personnel (via location), don't send user_id - let backend handle it via location_id
    let userIdToSend = null
    if (selectedLocation && selectedLocation.personnel) {
      const matchingUser = users.value.find(user => {
        const personnelLower = selectedLocation.personnel.toLowerCase().trim()
        const userFullnameLower = (user.fullname || '').toLowerCase().trim()
        return userFullnameLower === personnelLower || 
               userFullnameLower.includes(personnelLower) ||
               personnelLower.includes(userFullnameLower)
      })
      userIdToSend = matchingUser ? (matchingUser.id || matchingUser.user?.id) : null
    }
    
    // Only append user_id if we found a matching user
    // If no matching user, don't send user_id - the item will be assigned to personnel via location_id
    if (userIdToSend) {
      formDataToSend.append('user_id', userIdToSend)
    }
    
    // Send issuedTo location ID so backend knows which location to assign the item to
    // This is the location where the personnel is assigned (from "Issued To" dropdown)
    if (formData.value.issuedTo) {
      formDataToSend.append('issued_to_location_id', formData.value.issuedTo)
    }
    
    console.log('Form data being sent:', {
      unit: formData.value.unit,
      description: formData.value.description,
      pac: formData.value.propertyAccountCode,
      unit_value: formData.value.unitValue,
      quantity: formData.value.quantity,
      date_acquired: formData.value.dateAcquired,
      po_number: formData.value.poNumber,
      category_id: formData.value.category,
      location_id: formData.value.location,
      condition_id: formData.value.condition,
      condition_number_id: formData.value.conditionNumber,
      selected_personnel: selectedLocation?.personnel,
      user_id: userIdToSend
    })
    
    


    
    // Append the image file if it exists
    if (formData.value.image) {
      formDataToSend.append('image', formData.value.image)
    }

    console.log('About to send form data with image')
    console.log(formData) 

    // Send request to Laravel API (axios will handle Content-Type automatically for FormData)
    const response = await axiosClient.post('/items', formDataToSend)

    if (response.data) {
      console.log('item created successfully:', response.data)
      router.push('/inventory')
    }
  } catch (error) {
    console.error('Full error object:', error)
    console.error('Error response data:', error.response?.data)
    
    // More detailed error logging
    if (error.response) {
      console.error('Status:', error.response.status)
      console.error('Headers:', error.response.headers)
      console.error('Data:', error.response.data)
    } else if (error.request) {
      console.error('Request was made but no response received:', error.request)
    } else {
      console.error('Error setting up request:', error.message)
    }
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      errors.value = {
        general: [error.response.data.message]
      }
    } else {
      errors.value = {
        general: ['An unexpected error occurred. Please try again.']
      }
    }
    
    // Show error message
    successMessage.value = 'Error creating item: ' + (error.response?.data?.message || 'An unexpected error occurred. Please check the form and try again.')
    successModalType.value = 'error'
    showSuccessModal.value = true
  } finally {
    isSubmitting.value = false
  }
}

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

// Assuming categories are like: [{ id: 1, category: 'Supply' }, ...]
const isSupplyCategory = computed(() => {
  const selected = categories.value.find(cat => 
    cat.id == formData.value.category || cat.category_id == formData.value.category
  );
  return selected && selected.category?.toLowerCase() === 'supply';
});

// Check if "On Maintenance" condition is selected
const isOnMaintenance = computed(() => {
  if (!formData.value.condition) return false
  const selectedCondition = conditions.value.find(c => 
    (c.id || c.condition_id) == formData.value.condition
  )
  return selectedCondition && (selectedCondition.condition === 'On Maintenance' || selectedCondition.condition === 'Under Maintenance')
})

// Get locations that have personnel assigned
const locationsWithPersonnel = computed(() => {
  return locations.value.filter(location => 
    location.personnel && location.personnel.trim() !== ''
  );
});

// Filter condition numbers to only show A1, A2, A3, and R
const filteredConditionNumbers = computed(() => {
  const allowedNumbers = ['A1', 'A2', 'A3', 'R'];
  return condition_numbers.value.filter(cn => {
    const cnValue = cn.condition_number?.trim();
    return allowedNumbers.includes(cnValue);
  });
});

// Watch for location changes and auto-select personnel
watch(() => formData.value.location, (newLocationId) => {
  if (newLocationId) {
    // Find the selected location
    const selectedLocation = locations.value.find(loc => 
      (loc.id || loc.location_id) == newLocationId
    )
    
    // If the location has personnel assigned, automatically set issuedTo
    if (selectedLocation && selectedLocation.personnel && selectedLocation.personnel.trim() !== '') {
      formData.value.issuedTo = newLocationId
    } else {
      // Clear issuedTo if location has no personnel
      formData.value.issuedTo = ''
    }
  } else {
    // Clear issuedTo if location is cleared
    formData.value.issuedTo = ''
  }
})

// Watch for condition changes and auto-select "R" for "Non - Serviceable"
watch(() => formData.value.condition, (newConditionId) => {
  if (newConditionId && !isSupplyCategory.value) {
    // Find the selected condition
    const selectedCondition = conditions.value.find(cond => 
      (cond.id || cond.condition_id) == newConditionId
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
          formData.value.conditionNumber = conditionNumberR.id || conditionNumberR.condition_number_id
          console.log('Auto-selected condition number "R" for Non-Serviceable condition')
        }
      }
    }
  }
})

// Generate serial number
const generateSerialNumber = async () => {
  try {
    const response = await axiosClient.get('/items/generate-serial-number')
    if (response.data.success) {
      formData.value.serial_number = response.data.serial_number
    }
  } catch (error) {
    console.error('Error generating serial number:', error)
    // Fallback: generate a simple one if API fails
    const year = new Date().getFullYear()
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0')
    formData.value.serial_number = `NIA-EQ-${year}-${random}`
  }
}

// Fetch all dropdown data when component mounts
onMounted(async () => {
  try {
    await fetchLabels()
    // Generate serial number automatically
    await generateSerialNumber()
    
    // Fetch categories and locations with high per_page to get all items
    await Promise.all([
      fetchcategories(1, 1000), // Fetch all categories
      fetchLocations(1, 1000), // Fetch all locations
      fetchconditions(), // Already has onMounted, but calling explicitly to ensure it runs
      fetchcondition_numbers(), // Already has onMounted, but calling explicitly to ensure it runs
      fetchusers() // Already has onMounted, but calling explicitly to ensure it runs
    ])
    
    console.log('Categories loaded:', categories.value.length)
    console.log('Locations loaded:', locations.value.length)
    console.log('Conditions loaded:', conditions.value.length)
    console.log('Condition numbers loaded:', condition_numbers.value.length)
    console.log('Users loaded:', users.value.length)
  } catch (error) {
    console.error('Error fetching dropdown data:', error)
  }
})
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

.btn-primary {
  @apply px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200;
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
</style> 