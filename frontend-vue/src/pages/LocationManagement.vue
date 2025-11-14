<template>
  <div class="min-h-screen bg-white dark:bg-gray-800 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 sm:gap-0">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">location_on</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Unit/Sectors Management</h1>
              <p v-if="!loading" class="text-green-100 text-sm sm:text-base">
                {{ pagination.total || 0 }} {{ pagination.total === 1 ? 'unit/sector' : 'units/sectors' }} found
              </p>
              <p v-else class="text-green-100 text-sm sm:text-base">Loading units/sectors...</p>
            </div>
          </div>
          <div class="flex items-center gap-3 w-full sm:w-auto">
            <button 
              @click="openCreateModal"
              class="btn-primary-enhanced flex-1 sm:flex-auto justify-center shadow-lg"
            >
              <span class="material-icons-outlined text-lg mr-1.5">add_circle</span>
              <span>Add Unit/Sector</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistics Card -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Units/Sectors</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ pagination.total || 0 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-2xl">location_city</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Current Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ pagination.current_page || 1 }} / {{ pagination.last_page || 1 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-2xl">description</span>
          </div>
        </div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-lg transition-shadow duration-300 border border-gray-200 dark:border-gray-700 p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Items Per Page</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ pagination.per_page || 10 }}</p>
          </div>
          <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-xl">
            <span class="material-icons-outlined text-purple-400 dark:text-purple-400 text-2xl">list</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <!-- Enhanced Table Header -->
      <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">business</span>
            <h2 class="text-xl font-bold text-white">All Units/Sectors</h2>
          </div>
          <div class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full">
            <span class="text-sm font-semibold text-white">{{ pagination.total || 0 }} units/sectors</span>
          </div>
        </div>
      </div>

      <!-- Enhanced Table Container -->
      <div class="overflow-x-auto">
        <table v-if="!loading && locations.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700">
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-2 cursor-pointer hover:text-green-400 dark:hover:text-green-400 transition-colors" @click="toggleSort('location')">
                  <span class="material-icons-outlined text-base">sort</span>
                  <span>Unit/Sector (Department)</span>
                </div>
              </th>
              <th class="px-6 py-4 text-left text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-2 cursor-pointer hover:text-green-400 dark:hover:text-green-400 transition-colors" @click="toggleSort('personnel')">
                  <span class="material-icons-outlined text-base">sort</span>
                  <span>Personnel</span>
                </div>
              </th>
              <th class="px-6 py-4 text-right text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr 
              v-for="(location, index) in locations" 
              :key="location.id || location.location_id" 
              :class="[
                'group transition-all duration-200 border-l-4 border-transparent cursor-pointer',
                selectedLocation === location.id || selectedLocation === location.location_id 
                  ? 'bg-gray-50 dark:bg-gray-700 border-l-green-500 shadow-sm' 
                  : 'hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-l-green-400'
              ]"
              @click="selectedLocation = location.id || location.location_id"
            >
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div class="flex items-center gap-4">
                  <div class="relative">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-md group-hover:shadow-lg transition-all group-hover:scale-110">
                      <span class="material-icons-outlined text-white text-xl">location_on</span>
                    </div>
                    <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
                  </div>
                  <span class="text-sm font-bold text-gray-900 dark:text-white">{{ location.location }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap border-r border-gray-300 dark:border-gray-600">
                <div v-if="location.personnel" class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-md">
                    <span class="material-icons-outlined text-white text-sm">person</span>
                  </div>
                  <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ location.personnel }}</span>
                </div>
                <span v-else class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-400 italic">
                  Not assigned
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-2">
                  <button 
                    @click.stop="openEditModal(location)"
                    class="p-2.5 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg transition-all duration-200"
                    title="Edit Unit/Sector"
                  >
                    <span class="material-icons-outlined text-base">edit</span>
                  </button>
                  <button 
                    @click.stop="openActionsMenu(location)"
                    class="p-2.5 rounded-lg bg-gradient-to-br from-gray-500 to-gray-600 text-white hover:from-gray-600 hover:to-gray-700 shadow-md hover:shadow-lg transition-all duration-200 relative"
                    title="More Actions"
                  >
                    <span class="material-icons-outlined text-base">more_vert</span>
                    <!-- Enhanced Actions Dropdown -->
                    <div 
                      v-if="actionsMenuOpen === (location.id || location.location_id)"
                      class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 z-20 py-2 overflow-hidden"
                      @click.stop
                    >
                      <button 
                        @click="openEditModal(location)"
                        class="w-full text-left px-4 py-3 text-sm font-medium text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 transition-all"
                      >
                        <span class="material-icons-outlined text-lg text-blue-400 dark:text-blue-400">edit</span>
                        <span>Edit Unit/Sector</span>
                      </button>
                      <div class="h-px bg-gray-50 dark:bg-gray-700 my-1"></div>
                      <button 
                        @click="confirmDelete(location)"
                        class="w-full text-left px-4 py-3 text-sm font-medium text-red-400 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-3 transition-all"
                      >
                        <span class="material-icons-outlined text-lg">delete</span>
                        <span>Delete Unit/Sector</span>
                      </button>
                    </div>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Enhanced Loading State -->
        <div v-if="loading" class="text-center py-20">
          <div class="inline-block p-4 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
            <span class="material-icons-outlined animate-spin text-5xl text-green-400 dark:text-green-400">refresh</span>
          </div>
          <p class="text-lg font-semibold text-gray-900 dark:text-white">Loading units/sectors...</p>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Please wait a moment</p>
        </div>

        <!-- Enhanced Empty State -->
        <div v-else-if="locations.length === 0" class="text-center py-20">
          <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
            <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">location_on</span>
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No units/sectors found</h3>
          <p class="text-gray-600 dark:text-gray-400 mb-6">Create your first unit/sector to get started!</p>
          <button 
            @click="openCreateModal"
            class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 shadow-lg hover:shadow-xl transition-all font-semibold flex items-center gap-2 mx-auto"
          >
            <span class="material-icons-outlined">add_circle</span>
            <span>Create First Unit/Sector</span>
          </button>
        </div>

        <!-- Enhanced Pagination -->
        <div v-if="!loading && locations.length > 0" class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 gap-4">
            <div class="flex items-center gap-2">
              <span class="material-icons-outlined text-lg" style="color: #01200E;">info</span>
              <span class="text-sm font-semibold" style="color: #01200E;">
                Showing <span class="font-bold" style="color: #01200E;">{{ String((pagination.current_page - 1) * pagination.per_page + 1).padStart(2, '0') }}</span> to 
                <span class="font-bold" style="color: #01200E;">{{ String(Math.min(pagination.current_page * pagination.per_page, pagination.total)).padStart(2, '0') }}</span> of 
                <span class="font-bold" style="color: #01200E;">{{ pagination.total }}</span>
              </span>
            </div>
            <div class="flex items-center justify-center sm:justify-end gap-1.5 flex-wrap">
              <button 
                @click="changePage(1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">first_page</span>
              </button>
              <button 
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
              </button>
              <div class="flex items-center gap-1">
                <button
                  v-for="page in visiblePages"
                  :key="page"
                  @click="changePage(page)"
                  :class="[
                    'px-3 py-2 text-sm font-semibold border-2 rounded-lg transition-all shadow-sm hover:shadow-md',
                    pagination.current_page === page
                      ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-green-600 shadow-lg' 
                      : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400'
                  ]"
                >
                  {{ page }}
                </button>
              </div>
              <button 
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
              <button 
                @click="changePage(pagination.last_page)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-3 py-2 text-sm font-medium border-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-600 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md"
              >
                <span class="material-icons-outlined text-base align-middle">last_page</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="closeModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 p-6 sm:p-8 max-w-md w-full transform transition-all animate-modalSlideIn overflow-hidden">
        <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-5 -mx-6 -mt-6 mb-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">{{ isEditing ? 'edit' : 'add_circle' }}</span>
              </div>
              <div>
                <h2 class="text-xl sm:text-2xl font-bold text-white">
                  {{ isEditing ? 'Edit Unit/Sector' : 'Add New Unit/Sector' }}
                </h2>
                <p class="text-xs text-green-100 mt-0.5">{{ isEditing ? 'Update unit/sector information' : 'Create a new unit/sector for your inventory' }}</p>
              </div>
            </div>
            <button @click="closeModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined text-xl">close</span>
            </button>
          </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- Unit/Sector Name -->
          <div class="form-group">
            <label class="form-label">Unit/Sector Name (Department)</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none z-10">
                <span class="material-icons-outlined text-xl leading-none">location_on</span>
              </span>
              <input
                v-model="formData.location"
                type="text"
                placeholder="Enter unit/sector/department name"
                class="form-input pl-12 pr-4 border-gray-300 dark:border-gray-600 focus:border-green-500 focus:ring-green-500/20 relative z-0"
                required
                :disabled="isSubmitting"
              />
            </div>
            <p v-if="errors.location" class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
              <span class="material-icons-outlined text-base">error_outline</span>
              {{ errors.location[0] }}
            </p>
          </div>

          <!-- Personnel Name -->
          <div class="form-group">
            <label class="form-label">Personnel Name</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 pointer-events-none z-10">
                <span class="material-icons-outlined text-xl leading-none">person</span>
              </span>
              <input
                v-model="formData.personnel"
                type="text"
                placeholder="Enter personnel name"
                class="form-input pl-12 pr-4 border-gray-300 dark:border-gray-600 focus:border-green-500 focus:ring-green-500/20 relative z-0"
                :disabled="isSubmitting"
              />
            </div>
            <p v-if="errors.personnel" class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center gap-1">
              <span class="material-icons-outlined text-base">error_outline</span>
              {{ errors.personnel[0] }}
            </p>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button 
              type="button"
              @click="closeModal"
              :disabled="isSubmitting"
              class="px-6 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-all font-medium shadow-sm hover:shadow disabled:opacity-50"
            >
              Cancel
            </button>
            <button 
              type="submit"
              :disabled="isSubmitting"
              class="px-6 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 font-medium shadow-lg hover:shadow-xl transition-all"
            >
              <span v-if="isSubmitting" class="material-icons-outlined animate-spin text-base">refresh</span>
              {{ isSubmitting ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Enhanced Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="closeDeleteModal">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-md w-full overflow-hidden animate-modalSlideIn">
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5">
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
                <span class="material-icons-outlined text-white text-2xl">delete_forever</span>
              </div>
              <h3 class="text-xl font-bold text-white">Delete Unit/Sector</h3>
            </div>
            <button @click="closeDeleteModal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
              <span class="material-icons-outlined">close</span>
            </button>
          </div>
        </div>
        
        <div class="p-6">
          <div class="mb-6">
            <div class="flex items-center gap-3 mb-3 p-3 bg-red-900/20 dark:bg-red-900/20 border-l-4 border-red-500 dark:border-red-600 rounded-r-lg">
              <span class="material-icons-outlined text-red-400 dark:text-red-400">warning</span>
              <p class="text-sm font-medium text-gray-900 dark:text-white">
                Are you sure you want to delete <span class="font-bold text-red-400 dark:text-red-400">"{{ locationToDelete?.location }}"</span>?
              </p>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">This action cannot be undone.</p>
            <div v-if="locationToDelete?.items_count || locationToDelete?.users_count" class="inline-flex items-center gap-2 px-4 py-2 bg-red-900/20 dark:bg-red-900/20 border border-red-700 dark:border-red-700 text-red-300 dark:text-red-300 text-sm rounded-lg">
              <span class="material-icons-outlined text-base">info</span>
              <span>
                This unit/sector is used by 
                <span v-if="locationToDelete.items_count"><strong>{{ locationToDelete.items_count }}</strong> item(s)</span>
                <span v-if="locationToDelete.items_count && locationToDelete.users_count"> and </span>
                <span v-if="locationToDelete.users_count"><strong>{{ locationToDelete.users_count }}</strong> user(s)</span>.
                Deletion will fail.
              </span>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button 
              @click="closeDeleteModal"
              :disabled="isDeleting"
              class="px-5 py-2.5 border-2 border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white font-semibold hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-gray-500 dark:hover:border-gray-500 transition-all shadow-sm disabled:opacity-50"
            >
              Cancel
            </button>
            <button 
              @click="handleDelete"
              :disabled="isDeleting"
              class="px-5 py-2.5 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-xl hover:from-red-700 hover:to-red-800 disabled:opacity-75 disabled:cursor-not-allowed flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transition-all"
            >
              <span v-if="isDeleting" class="material-icons-outlined animate-spin text-base">refresh</span>
              <span v-else class="material-icons-outlined text-base">delete</span>
              {{ isDeleting ? 'Deleting...' : 'Delete Unit/Sector' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Modal -->
    <SuccessModal
      :isOpen="showSuccessModal"
      :title="successModalType === 'success' ? 'Success' : 'Error'"
      :message="successMessage"
      buttonText="OK"
      :type="successModalType"
      @confirm="closeSuccessModal"
      @close="closeSuccessModal"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import useLocations from '../composables/useLocations'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()
const { locations, pagination, loading, fetchLocations } = useLocations()

const currentPage = ref(1)
const perPage = 10 // Fixed to 10 items per page
const showModal = ref(false)
const showDeleteModal = ref(false)
const showSuccessModal = ref(false)
const isEditing = ref(false)
const isSubmitting = ref(false)
const isDeleting = ref(false)
const errors = ref({})
const successMessage = ref('')
const successModalType = ref('success')
const locationToDelete = ref(null)
const selectedLocation = ref(null)
const actionsMenuOpen = ref(null)
const sortField = ref(null)
const sortDirection = ref('asc')

const formData = ref({
  id: null,
  location: '',
  personnel: ''
})

const goBack = () => {
  router.push('/inventory')
}

const openCreateModal = () => {
  isEditing.value = false
  formData.value = { id: null, location: '', personnel: '' }
  errors.value = {}
  showModal.value = true
}

const openEditModal = (location) => {
  actionsMenuOpen.value = null
  isEditing.value = true
  formData.value = {
    id: location.id || location.location_id,
    location: location.location,
    personnel: location.personnel || ''
  }
  errors.value = {}
  showModal.value = true
}

const closeModal = () => {
  if (isSubmitting.value) return
  showModal.value = false
  formData.value = { id: null, location: '', personnel: '' }
  errors.value = {}
  isEditing.value = false
}

const confirmDelete = (location) => {
  actionsMenuOpen.value = null
  locationToDelete.value = location
  showDeleteModal.value = true
}

const closeDeleteModal = () => {
  if (isDeleting.value) return
  showDeleteModal.value = false
  locationToDelete.value = null
}

const handleSubmit = async () => {
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true
    errors.value = {}

    if (!formData.value.location.trim()) {
      errors.value.location = ['Unit/Sector name is required']
      isSubmitting.value = false
      return
    }

    const payload = {
      location: formData.value.location.trim(),
      personnel: formData.value.personnel?.trim() || null
    }

    let response
    if (isEditing.value) {
      response = await axiosClient.put(`/locations/${formData.value.id}`, payload)
    } else {
      response = await axiosClient.post('/locations', payload)
    }

    if (response.data?.success) {
      successMessage.value = isEditing.value 
        ? 'Unit/Sector updated successfully!' 
        : 'Unit/Sector created successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      
      await fetchLocations(currentPage.value, perPage)
      closeModal()
    }
  } catch (error) {
    console.error('Error saving unit/sector:', error)
    
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors
    } else if (error.response?.data?.message) {
      errors.value = {
        location: [error.response.data.message]
      }
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      errors.value = {
        location: ['An unexpected error occurred. Please try again.']
      }
      successMessage.value = 'An unexpected error occurred. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    isSubmitting.value = false
  }
}

const handleDelete = async () => {
  if (isDeleting.value || !locationToDelete.value) return
  
  try {
    isDeleting.value = true
    const locationId = locationToDelete.value.id || locationToDelete.value.location_id
    
    const response = await axiosClient.delete(`/locations/${locationId}`)
    
    if (response.data?.success) {
      successMessage.value = 'Unit/Sector deleted successfully!'
      successModalType.value = 'success'
      showSuccessModal.value = true
      
      await fetchLocations(currentPage.value, perPage)
      closeDeleteModal()
    }
  } catch (error) {
    console.error('Error deleting unit/sector:', error)
    
    if (error.response?.data?.message) {
      successMessage.value = error.response.data.message
      successModalType.value = 'error'
      showSuccessModal.value = true
    } else {
      successMessage.value = 'Failed to delete unit/sector. Please try again.'
      successModalType.value = 'error'
      showSuccessModal.value = true
    }
  } finally {
    isDeleting.value = false
  }
}

const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

const changePage = async (page) => {
  currentPage.value = page
  await fetchLocations(page, perPage)
}

const toggleSort = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
  // Note: Implement actual sorting logic if needed
}

const openActionsMenu = (location) => {
  const locationId = location.id || location.location_id
  actionsMenuOpen.value = actionsMenuOpen.value === locationId ? null : locationId
}

const visiblePages = computed(() => {
  const pages = []
  const current = pagination.value.current_page
  const last = pagination.value.last_page
  
  if (last <= 5) {
    for (let i = 1; i <= last; i++) {
      pages.push(i)
    }
  } else {
    if (current <= 3) {
      for (let i = 1; i <= 5; i++) {
        pages.push(i)
      }
    } else if (current >= last - 2) {
      for (let i = last - 4; i <= last; i++) {
        pages.push(i)
      }
    } else {
      for (let i = current - 2; i <= current + 2; i++) {
        pages.push(i)
      }
    }
  }
  return pages
})

// Close actions menu when clicking outside
const handleClickOutside = (event) => {
  if (!event.target.closest('.relative')) {
    actionsMenuOpen.value = null
  }
}

onMounted(async () => {
  await fetchLocations(currentPage.value, perPage)
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.form-group {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5;
}

.form-input {
  @apply block w-full rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white transition-all duration-200;
  height: 48px;
  position: relative;
  z-index: 0;
  background-color: white !important;
}

.dark .form-input {
  background-color: rgb(55 65 81) !important;
}

.form-input:focus {
  @apply shadow-md;
  background-color: white !important;
}

.dark .form-input:focus {
  background-color: rgb(55 65 81) !important;
}

.form-input:hover:not(:disabled) {
  @apply border-gray-400 dark:border-gray-500;
}

.form-input:disabled {
  @apply opacity-60 cursor-not-allowed;
}

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

/* Animation keyframes */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.2s ease-out;
}

.animate-slideUp {
  animation: slideUp 0.3s ease-out;
}

/* Enhanced table row hover effect */
tbody tr {
  transition: all 0.2s ease;
}

tbody tr:hover {
  transform: translateX(2px);
  box-shadow: inset 4px 0 0 theme('colors.green.500');
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

/* Smooth transitions */
* {
  transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 150ms;
}
</style>

