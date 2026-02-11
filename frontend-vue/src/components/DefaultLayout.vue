<template>
  <!--
    This example requires updating your template:

    ```
    <html class="h-full bg-gray-100">
    <body class="h-full">
    ```
  -->
  <div class="min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-20">
      <!-- Logo -->
      <div class="flex items-center px-6 py-4 h-16">
        <img src="../assets/logo.png" alt="IrrigTrack" class="h-8 w-8" />
        <span class="ml-2 text-xl font-semibold text-gray-800">IrrigTrack</span>
      </div>

      <!-- Navigation -->
      <nav class="mt-4 px-4">
        <router-link
          v-for="item in navigation"
          :key="item.name"
          :to="item.path"
          :class="[
            'flex items-center px-4 py-3 text-gray-700 rounded-lg mb-1',
            isCurrentRoute(item.path)
              ? 'bg-green-50 text-green-600'
              : 'hover:bg-gray-50'
          ]"
        >
          <span class="material-icons-outlined mr-3">{{ item.icon }}</span>
          {{ item.name }}
        </router-link>

        <!-- Create Account Button -->
        <div class="px-4 mt-8">
          <button class="w-full bg-green-600 text-white px-4 py-2 rounded-lg flex items-center justify-center hover:bg-green-700">
            <span class="material-icons-outlined mr-2">add</span>
            Create account
          </button>
        </div>
      </nav>
    </aside>

    <!-- Main Content -->
    <div class="ml-64">
      <!-- Top Bar -->
      <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 sticky top-0 z-10">
        <div class="flex items-center">
          <h1 class="text-2xl font-semibold text-gray-800">{{ route.name }}</h1>
          
          <!-- Action Buttons -->
          <div class="ml-8 flex space-x-3">
            <button class="bg-green-600 text-white px-4 py-2 rounded-lg flex items-center hover:bg-green-700">
              <span class="material-icons-outlined mr-2">qr_code_2</span>
              QR Generation Validation
            </button>
            <button class="bg-green-600 text-white px-4 py-2 rounded-lg flex items-center hover:bg-green-700">
              <span class="material-icons-outlined mr-2">assessment</span>
              Reporting
            </button>
          </div>
        </div>

        <!-- Right Side Icons -->
        <div class="flex items-center space-x-4">
          <!-- Dark Mode Toggle -->
          <button class="p-2 rounded-full hover:bg-gray-100">
            <span class="material-icons-outlined">dark_mode</span>
          </button>

          <!-- Notifications -->
          <button class="p-2 rounded-full hover:bg-gray-100 relative">
            <span class="material-icons-outlined">notifications</span>
            <span class="absolute top-0 right-0 h-4 w-4 bg-red-500 rounded-full text-xs text-white flex items-center justify-center">
              2
            </span>
          </button>

          <!-- Profile -->
          <button class="flex items-center space-x-2">
            <img
              :src="user.avatar"
              alt="Profile"
              class="h-8 w-8 rounded-full object-cover"
            />
          </button>
        </div>
      </header>

      <!-- Page Content -->
      <main class="p-6">
        <!-- Preloader removed -->
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const user = ref({
  name: 'JGludo',
  avatar: '../assets/avatar.jpg'
})

const navigation = [
  { name: 'Dashboard', path: '/dashboard', icon: 'dashboard' },
  { name: 'Inventory', path: '/inventory', icon: 'inventory' },
  { name: 'Admins', path: '/admins', icon: 'people' },
  { name: 'Analytics', path: '/analytics', icon: 'analytics' }
]

const isCurrentRoute = (path) => {
  return route.path === path
}
</script>

<style scoped>
.material-icons-outlined {
  font-size: 20px;
}
</style>