<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axiosClient from '../axios'
import SuccessModal from '../components/SuccessModal.vue'

const router = useRouter()

const user = ref({
  fullName: '',
  username: '',
  email: '',
  location: '',
  image: '',
  password: ''
})
const selectedFile = ref(null)
const loading = ref(false)

// State for success modal
const showSuccessModal = ref(false)
const successMessage = ref('')
const successModalType = ref('success')

onMounted(async () => {
  const userId = localStorage.getItem('userId')
  const backendUrl = 'http://localhost:8000/storage';
  try {
  const res = await axiosClient.get(`/users/${userId}`)
  if(res.status == 200){
    console.log("Success")
    console.log(res.data)
     
  user.value.fullName = res.data.data.fullname
  user.value.username = res.data.data.username
  user.value.email = res.data.data.email
  user.value.location = res.data.data.location.location
  user.value.image = `${backendUrl}/${res.data.data.image}`
  
  } else{
console.log('API Response:', res.data)  // <- Confirm actual data shape
 
  
  console.log('User Data:', user.value)  // <- Confirm reactivity works
  }
  
} catch (e) {
  console.error('API Error:', e.response ? e.response.data : e)
}
})

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    selectedFile.value = file;
  }
};

const saveProfile = async () => {
  loading.value = true;
  const userId = localStorage.getItem('userId');
   const formData = new FormData();

formData.append('fullname', user.value.fullName);
formData.append('username', user.value.username);
formData.append('email', user.value.email);
formData.append('location', user.value.location);
 

  if (selectedFile.value) {
    formData.append('image', selectedFile.value);
  }
   
if (user.password) {
  formData.append('password', user.password)
  formData.append('password_confirmation', user.password_confirmation)
}

  console.log(formData)

  try {
    const res = await axiosClient.put(`/users/${userId}`, formData);
    if(res.status == 200){
       console.log(user.value.fullName)
       
    // Update user info after successful update
    
      const backendUrl = 'http://localhost:8000/storage';
      user.value.fullName = res.data.data.fullname;
      user.value.username = res.data.data.username;
      user.value.email = res.data.data.email;
      user.value.location = res.data.data.location.location;
      user.value.password = res.data.data.password;
      user.value.image = `${backendUrl}/${res.data.data.image}`;
    successMessage.value = 'Profile updated successfully!';
    successModalType.value = 'success';
    showSuccessModal.value = true;
    console.log(user)
     console.log(res.data.data )
  }
  } catch (e) {
    successMessage.value = 'Failed to update profile.';
    successModalType.value = 'error';
    showSuccessModal.value = true;
    console.error('Update Error:', e.response ? e.response.data : e);
  } finally {
    loading.value = false;
  }
};

// Close success modal
const closeSuccessModal = () => {
  showSuccessModal.value = false
  successMessage.value = ''
  successModalType.value = 'success'
}

const goToDashboard = () => {
  router.push('/dashboard')
}

</script>

<template>
  <!-- <pre> {{  formData }}</pre> -->
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Back to Dashboard Button -->
    <button 
      @click="goToDashboard"
      class="mb-6 inline-flex items-center text-green-600 hover:text-green-700 dark:text-green-400 dark:hover:text-green-300"
    >
      <span class="material-icons-outlined mr-1">arrow_back</span>
      Back to Dashboard
    </button>

    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Left Column - Form -->
      <div class="flex-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4 sm:p-6">
        <h1 class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Admin</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-6">Profile</p>

        <form class="space-y-6" @submit.prevent="saveProfile">
          <!-- image Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">image</label>
            <div class="flex items-center">
              <label class="relative cursor-pointer">
                <input
                  type="file"
                  class="hidden"
                  accept="image/*"
                  @change="handleFileChange"
                />
                <div class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                  Choose File
                </div>
              </label>
              <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">
                {{ selectedFile ? selectedFile.name : 'No file chosen' }}
              </span>
            </div>
          </div>

          <!-- Full Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name</label>
            <input
              type="text"
              v-model="user.fullName"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
            <input
              type="email"
              v-model="user.email"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>

          <!-- Location -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Location</label>
            <select
              v-model="user.location"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            >
              <option value="" disabled>Select Location</option>
             <option value="Panabo">Panabo</option>
              <option value="Tagum">Tagum</option>
              <option value="Davao">Davao</option>
              <option value="ICT">ICT</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
            <input
              type="password"
              placeholder="Password"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>
          <!-- Confirm Password -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
            <input
              type="password"
              placeholder="Confirm password"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-300"
            />
          </div>
          <button
            type="submit"
            class="mt-4 px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
            :disabled="loading"
          >
            {{ loading ? 'Saving...' : 'Save Changes' }}
          </button>
        </form>
      </div>

      <!-- Right Column - Account Details -->
      <div class="lg:w-1/3">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Account Details</h2>
          
          <div class="flex justify-center mb-6">
            <div class="relative">
              <img
                :src="user.image"
                alt="Profile"
                class="w-32 h-32 rounded-full object-cover border-4 border-white dark:border-gray-600"
              />
              <span class="absolute bottom-0 right-0 bg-green-500 rounded-full w-6 h-6 border-2 border-white dark:border-gray-600"></span>
            </div>
          </div>
          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-500 dark:text-gray-400">Full Name</label>
              <p class="text-gray-800 dark:text-gray-200">{{ user.fullName }}</p>
            </div>
            <div>
              <label class="block text-sm text-gray-500 dark:text-gray-400">Email</label>
              <p class="text-gray-800 dark:text-gray-200">{{ user.email }}</p>
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
/* Add any component-specific styles here */
</style> 