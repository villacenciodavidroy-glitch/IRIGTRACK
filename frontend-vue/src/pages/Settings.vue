<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axiosClient from '../axios'
import useAuth from '../composables/useAuth'
import useLogo from '../composables/useLogo'
import useFormLabels from '../composables/useFormLabels'

const route = useRoute()
const { isAdmin } = useAuth()
const { logoUrl, fetchLogo, refetch: refetchLogo } = useLogo()
const { labels, fetchLabels, refetch: refetchLabels, getLabel, getPlaceholder, getSectionTitle, getSectionSubtitle, getHelperText } = useFormLabels()

// Determine which section to show based on route
const currentSection = computed(() => {
  if (route.path.includes('/form-labels')) return 'form-labels'
  if (route.path.includes('/logo')) return 'logo'
  return 'logo' // default to logo
})

const fileInput = ref(null)
const selectedFile = ref(null)
const previewUrl = ref(null)
const uploading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

// Form Labels state
const showFormLabels = ref(false)
const editingLabels = ref({})
const savingLabels = ref(false)
const labelsSuccessMessage = ref('')
const labelsErrorMessage = ref('')

onMounted(() => {
  fetchLogo()
  fetchLabels()
})

const onFileSelect = (e) => {
  const f = e.target?.files?.[0]
  if (!f) return
  if (!f.type.startsWith('image/')) {
    errorMessage.value = 'Please select an image file (PNG, JPEG, GIF, or WebP).'
    selectedFile.value = null
    previewUrl.value = null
    return
  }
  if (f.size > 2 * 1024 * 1024) {
    errorMessage.value = 'Image must be 2 MB or smaller.'
    selectedFile.value = null
    previewUrl.value = null
    return
  }
  errorMessage.value = ''
  successMessage.value = ''
  selectedFile.value = f
  previewUrl.value = URL.createObjectURL(f)
}

const clearSelection = () => {
  selectedFile.value = null
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
  if (fileInput.value) fileInput.value.value = ''
  errorMessage.value = ''
}

const uploadLogo = async () => {
  if (!selectedFile.value) {
    errorMessage.value = 'Please select an image first.'
    return
  }
  uploading.value = true
  errorMessage.value = ''
  successMessage.value = ''
  try {
    const form = new FormData()
    form.append('logo', selectedFile.value)
    
    const res = await axiosClient.post('/settings/logo', form)
    
    if (res.data?.success) {
      successMessage.value = 'Logo updated successfully.'
      clearSelection()
      // Force refetch with cache busting
      await refetchLogo()
      // Also force a page refresh of the logo image
      if (res.data?.url) {
        const separator = res.data.url.includes('?') ? '&' : '?'
        const newUrl = `${res.data.url}${separator}t=${Date.now()}`
        // Update logoUrl directly if needed
      }
    } else {
      errorMessage.value = res.data?.message || 'Failed to update logo.'
    }
  } catch (err) {
    console.error('Logo upload error:', err)
    const errorMsg = err.response?.data?.message || 
                     err.response?.data?.errors?.logo?.[0] || 
                     err.message || 
                     'Failed to update logo. Please check console for details.'
    errorMessage.value = errorMsg
  } finally {
    uploading.value = false
  }
}

// Form Labels functions
const openFormLabels = () => {
  showFormLabels.value = true
  editingLabels.value = {}
  // labels.value is an object keyed by 'key', convert to array
  const labelsArray = Object.values(labels.value)
  labelsArray.forEach(label => {
    if (label && label.key) {
      editingLabels.value[label.key] = {
        key: label.key,
        label: label.label || '',
        placeholder: label.placeholder || '',
        section_title: label.section_title || '',
        section_subtitle: label.section_subtitle || '',
        helper_text: label.helper_text || '',
      }
    }
  })
}

const closeFormLabels = () => {
  showFormLabels.value = false
  editingLabels.value = {}
  labelsSuccessMessage.value = ''
  labelsErrorMessage.value = ''
}

const saveFormLabels = async () => {
  savingLabels.value = true
  labelsSuccessMessage.value = ''
  labelsErrorMessage.value = ''
  try {
    const labelsArray = Object.values(editingLabels.value)
    const res = await axiosClient.post('/settings/form-labels', { labels: labelsArray })
    if (res.data?.success) {
      labelsSuccessMessage.value = 'Form labels updated successfully.'
      await refetchLabels()
      setTimeout(() => {
        closeFormLabels()
      }, 1500)
    } else {
      labelsErrorMessage.value = res.data?.message || 'Failed to update form labels.'
    }
  } catch (err) {
    labelsErrorMessage.value = err.response?.data?.message || 'Failed to update form labels.'
  } finally {
    savingLabels.value = false
  }
}

// Group labels by section for better organization in the editor
const groupedLabels = computed(() => {
  const groups = {
    'Basic Information': [],
    'Financial & Acquisition': [],
    'Assignment': [],
    'Condition & Status': [],
    'Asset Image': [],
    'Other': [],
  }
  
  Object.values(editingLabels.value).forEach(label => {
    if (label.section_title) {
      if (label.section_title.includes('Basic')) {
        groups['Basic Information'].push(label)
      } else if (label.section_title.includes('Financial')) {
        groups['Financial & Acquisition'].push(label)
      } else if (label.section_title.includes('Assignment')) {
        groups['Assignment'].push(label)
      } else if (label.section_title.includes('Condition')) {
        groups['Condition & Status'].push(label)
      } else if (label.section_title.includes('Asset') || label.section_title.includes('Image')) {
        groups['Asset Image'].push(label)
      } else {
        groups['Other'].push(label)
      }
    } else {
      // Field labels - assign to appropriate section based on key
      if (['article', 'category', 'description', 'serial_number', 'model'].includes(label.key)) {
        groups['Basic Information'].push(label)
      } else if (['property_account_code', 'unit_value', 'quantity', 'date_acquired', 'po_number'].includes(label.key)) {
        groups['Financial & Acquisition'].push(label)
      } else if (['unit_sections', 'issued_to'].includes(label.key)) {
        groups['Assignment'].push(label)
      } else if (['condition', 'condition_number'].includes(label.key)) {
        groups['Condition & Status'].push(label)
      } else if (label.key === 'item_image') {
        groups['Asset Image'].push(label)
      } else {
        groups['Other'].push(label)
      }
    }
  })
  
  return Object.entries(groups).filter(([_, labels]) => labels.length > 0)
})
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <div class="max-w-2xl mx-auto">
      <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
        <div class="relative px-6 py-8 sm:px-8 sm:py-10">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">settings</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Settings</h1>
              <p class="text-green-100 text-sm sm:text-base">System configuration (Admin only)</p>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!isAdmin()" class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-6 text-center">
        <span class="material-icons-outlined text-4xl text-amber-500 mb-3 block">admin_panel_settings</span>
        <p class="text-amber-700 dark:text-amber-300 font-medium">Only administrators can change settings.</p>
      </div>

      <template v-else>
        <!-- Change Logo -->
        <div v-if="currentSection === 'logo'" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
          <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <span class="material-icons-outlined text-green-600 dark:text-green-400">image</span>
              Change logo
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Upload a new image to replace the IrrigTrack logo. Max 2 MB; PNG, JPEG, GIF, or WebP.</p>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="successMessage" class="flex items-center gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200">
              <span class="material-icons-outlined text-green-600 dark:text-green-400">check_circle</span>
              <span>{{ successMessage }}</span>
            </div>
            <div v-if="errorMessage" class="flex items-center gap-3 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200">
              <span class="material-icons-outlined text-red-600 dark:text-red-400">error</span>
              <span>{{ errorMessage }}</span>
            </div>

            <div class="flex flex-col sm:flex-row gap-6 items-start">
              <div class="flex flex-col items-center gap-2">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Current logo</p>
                <div class="h-20 w-20 rounded-full border-2 border-gray-300 dark:border-gray-600 flex items-center justify-center overflow-hidden bg-gray-100 dark:bg-gray-700">
                  <img :src="logoUrl" alt="Logo" class="h-14 w-14 object-contain" />
                </div>
              </div>
              <div class="flex-1 w-full space-y-4">
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                  class="hidden"
                  @change="onFileSelect"
                />
                <button
                  type="button"
                  @click="fileInput?.click()"
                  class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium transition-colors"
                >
                  <span class="material-icons-outlined text-lg">upload_file</span>
                  Choose image
                </button>
                <div v-if="previewUrl" class="flex flex-wrap items-center gap-4">
                  <div class="h-16 w-16 rounded-lg border-2 border-green-500 overflow-hidden bg-gray-100 dark:bg-gray-700">
                    <img :src="previewUrl" alt="Preview" class="h-full w-full object-contain" />
                  </div>
                  <div class="flex gap-2">
                    <button
                      type="button"
                      :disabled="uploading"
                      @click="uploadLogo"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-medium transition-colors"
                    >
                      <span v-if="uploading" class="material-icons-outlined animate-spin text-lg">hourglass_empty</span>
                      <span v-else class="material-icons-outlined text-lg">save</span>
                      {{ uploading ? 'Uploading…' : 'Upload logo' }}
                    </button>
                    <button
                      type="button"
                      :disabled="uploading"
                      @click="clearSelection"
                      class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium transition-colors disabled:opacity-50"
                    >
                      <span class="material-icons-outlined text-lg">close</span>
                      Cancel
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Labels -->
        <div v-if="currentSection === 'form-labels'" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
          <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <span class="material-icons-outlined text-green-600 dark:text-green-400">label</span>
              Form Labels
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Customize form field labels, placeholders, and section titles for the Add Item form.</p>
          </div>
          <div class="p-6">
            <button
              type="button"
              @click="openFormLabels"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium transition-colors"
            >
              <span class="material-icons-outlined text-lg">edit</span>
              Edit Form Labels
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- Form Labels Editor Modal -->
    <div
      v-if="showFormLabels"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      @click.self="closeFormLabels"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800 flex-shrink-0 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">label</span>
            <h2 class="text-xl font-bold text-white">Edit Form Labels</h2>
          </div>
          <button
            type="button"
            @click="closeFormLabels"
            class="p-2 text-white/80 hover:text-white hover:bg-white/20 rounded-lg transition-colors"
            aria-label="Close"
          >
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
          <div v-if="labelsSuccessMessage" class="flex items-center gap-3 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 mb-4">
            <span class="material-icons-outlined text-green-600 dark:text-green-400">check_circle</span>
            <span>{{ labelsSuccessMessage }}</span>
          </div>
          <div v-if="labelsErrorMessage" class="flex items-center gap-3 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 mb-4">
            <span class="material-icons-outlined text-red-600 dark:text-red-400">error</span>
            <span>{{ labelsErrorMessage }}</span>
          </div>

          <div v-for="[groupName, groupLabels] in groupedLabels" :key="groupName" class="mb-6">
            <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-3 pb-2 border-b border-gray-200 dark:border-gray-600">
              {{ groupName }}
            </h3>
            <div class="space-y-4">
              <div
                v-for="label in groupLabels"
                :key="label.key"
                class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4 border border-gray-200 dark:border-gray-600"
              >
                <div class="mb-2">
                  <span class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ label.key }}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div v-if="label.section_title !== undefined">
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">Section Title</label>
                    <input
                      v-model="label.section_title"
                      type="text"
                      class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Section title"
                    />
                  </div>
                  <div v-if="label.section_subtitle !== undefined">
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">Section Subtitle</label>
                    <input
                      v-model="label.section_subtitle"
                      type="text"
                      class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Section subtitle"
                    />
                  </div>
                  <div v-if="label.label !== undefined">
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">Field Label</label>
                    <input
                      v-model="label.label"
                      type="text"
                      class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Field label"
                    />
                  </div>
                  <div v-if="label.placeholder !== undefined">
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">Placeholder</label>
                    <input
                      v-model="label.placeholder"
                      type="text"
                      class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Placeholder text"
                    />
                  </div>
                  <div v-if="label.helper_text !== undefined" class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1">Helper Text</label>
                    <input
                      v-model="label.helper_text"
                      type="text"
                      class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Helper text"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="flex-shrink-0 p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="closeFormLabels"
            class="px-5 py-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="savingLabels"
            @click="saveFormLabels"
            class="px-5 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-medium transition-colors flex items-center gap-2"
          >
            <span v-if="savingLabels" class="material-icons-outlined animate-spin text-lg">hourglass_empty</span>
            <span v-else class="material-icons-outlined text-lg">save</span>
            {{ savingLabels ? 'Saving…' : 'Save Labels' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
