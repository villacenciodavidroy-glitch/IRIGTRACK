import axios from "axios"
import router from './router'

const axiosClient = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
    withCredentials: true,
    withXSRFToken: true,    
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    }
})

// Request interceptor
axiosClient.interceptors.request.use(config => {
    const token = localStorage.getItem('token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    
    // If sending FormData, remove the Content-Type header completely
    // so browser can set it with boundary automatically
    if (config.data instanceof FormData) {
        // Always delete Content-Type for FormData - browser will add it with boundary
        delete config.headers['Content-Type']
    }
    
    return config
})

// Response interceptor
axiosClient.interceptors.response.use(
    response => response, // Return response as is for successful requests
    error => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('token')
            localStorage.removeItem('user') 
            if (router.currentRoute.value.name !== 'login') {
                router.push('/login')
            }
        }
        return Promise.reject(error)
    }
)

export default axiosClient