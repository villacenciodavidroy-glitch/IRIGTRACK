import {createRouter, createWebHistory} from "vue-router"
import DefaultLayout from "./layouts/DefaultLayout.vue"
import Dashboard from "./pages/Dashboard.vue"
// import Inventory from "./pages/Inventory.vue"
import Login from "./pages/Login.vue"
import Signup from "./pages/Signup.vue"
import NotFound from "./pages/NotFound.vue"
import Inventory from "./pages/Inventory.vue"
import Admin from "./pages/Admin.vue"
import Analytics from "./pages/Analytics.vue"
import AddItem from "./pages/AddItem.vue"
import AddAccount from './pages/AddAccount.vue'
import EditAccount from './pages/EditAccount.vue'
import Reporting from './pages/reports/index.vue'
import DesktopMonitoring from './pages/reports/desktop.vue'
import ServiceableItems from './pages/reports/serviceable-items.vue'
import SuppliesOverview from './pages/SuppliesOverview.vue'
import UsageOverview from './pages/UsageOverview.vue'
import QRGeneration from './pages/QRGeneration.vue'
import ActivityLog from './pages/ActivityLog.vue'
import Profile from './pages/ProfileView.vue'
import DeletedItems from './pages/DeletedItems.vue'
import MaintenanceRecords from './pages/MaintenanceRecords.vue'
import Notifications from './pages/Notifications.vue'
import axiosClient from './axios'

const routes = [
    {
        path: '/',
        redirect: '/login'
    },
    {
        path: '/',
        component: DefaultLayout,
        children: [
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: Dashboard
            },
            {
                path: 'profile',
                name: 'Profile',
                component: Profile
            },
            {
                path: 'activity-log',
                name: 'ActivityLog',
                component: ActivityLog
            },
            {
                path: 'history',
                name: 'History',
                children: [
                    {
                        path: '',
                        redirect: 'deleted-items'
                    },
                    {
                        path: 'deleted-items',
                        name: 'DeletedItems',
                        component: DeletedItems
                    },
                    {
                        path: 'maintenance-records',
                        name: 'MaintenanceRecords',
                        component: MaintenanceRecords
                    }
                ]
            },
            {
                path: 'inventory',
                name: 'Inventory',
                component: Inventory
            },
            {
                path: 'categories',
                name: 'CategoryManagement',
                component: () => import('./pages/CategoryManagement.vue')
            },
            {
                path: 'locations',
                name: 'LocationManagement',
                component: () => import('./pages/LocationManagement.vue')
            },
            {
                path: 'add-item',
                name: 'AddItem',
                component: AddItem
            },
            {
                path: 'admin',
                name: 'Admin',
                component: Admin
            },
            {
                path: 'settings',
                name: 'Settings',
                component: () => import('./pages/Settings.vue')
            },
            {
                path: 'personnel-management',
                name: 'PersonnelManagement',
                component: () => import('./pages/PersonnelManagement.vue')
            },{
                path: 'analytics',
                name: 'Analytics',
                component: Analytics
            },
            {
                path: 'supplies',
                name: 'SuppliesOverview',
                component: SuppliesOverview
            },
            {
                path: 'usage',
                name: 'UsageOverview',
                component: UsageOverview
            },
            {
                path: 'add-account',
                name: 'AddAccount',
                component: AddAccount
            },
            {
                path: 'edit-account/:id',
                name: 'EditAccount',
                component: EditAccount
            },
            {
                path: 'reporting',
                name: 'Reporting',
                component: Reporting
            },
            {
                path: 'reports/desktop/:type?',
                name: 'DesktopMonitoring',
                component: DesktopMonitoring
            },
            {
                path: 'reports/serviceable-items',
                name: 'ServiceableItems',
                component: ServiceableItems
            },
            {
                path: 'reports/monitoring-assets',
                name: 'MonitoringAssets',
                component: () => import('./pages/reports/monitoring-assets.vue')
            },
            {
                path: 'reports/life-cycles-data',
                name: 'LifeCyclesData',
                component: () => import('./pages/reports/life-cycles-data.vue')
            },
            {
                path: 'reports/maintenance-records',
                name: 'MaintenanceRecordsReport',
                component: () => import('./pages/reports/maintenance-records.vue')
            },
            {
                path: 'reports/transactions',
                name: 'TransactionsReport',
                component: () => import('./pages/reports/transactions.vue')
            },
            {
                path: 'reports/supply-usage-ranking',
                name: 'SupplyUsageRanking',
                component: () => import('./pages/reports/supply-usage-ranking.vue')
            },
            {
                path: 'reports/user-supply-usage',
                name: 'UserSupplyUsage',
                component: () => import('./pages/reports/user-supply-usage.vue')
            },
            {
                path: 'QRGeneration',
                name: 'QRGeneration',
                component: QRGeneration
            },
            {
                path: 'edit-item/:uuid',
                name: 'EditItem',
                component: () => import('./pages/EditItem.vue')
            },
            {
                path: 'notifications',
                name: 'Notifications',
                component: Notifications
            },
            {
                path: 'transactions',
                name: 'Transactions',
                component: () => import('./pages/Transactions.vue')
            },
            {
                path: 'supply-requests',
                name: 'SupplyRequests',
                component: () => import('./pages/SupplyRequests.vue')
            },
            {
                path: 'supply-requests-management',
                name: 'SupplyRequestsManagement',
                component: () => import('./pages/SupplyRequestsManagement.vue')
            },
            {
                path: 'unit-section-analytics',
                name: 'UnitSectionAnalytics',
                component: () => import('./pages/UnitSectionAnalytics.vue')
            },
            {
                path: 'admin/supply-requests',
                name: 'AdminSupplyRequests',
                component: () => import('./pages/AdminSupplyRequests.vue')
            }
        ]
    },
    {
        path: '/login',
        name: 'Login',
        component: Login
    },
    {
        path: '/signup',
        name: 'Signup',
        component: Signup
    },
    
    {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: NotFound
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Define public routes that don't require authentication
const publicRoutes = ['Login', 'Signup', 'NotFound']

// Define admin-only routes
const adminRoutes = ['Admin', 'AddAccount', 'EditAccount', 'ActivityLog', 'Transactions', 'AdminSupplyRequests', 'Settings']

// Define supply-only routes (supply role can access)
const supplyRoutes = ['SupplyRequestsManagement', 'UnitSectionAnalytics']

// Navigation guard for authentication and authorization
router.beforeEach(async (to, from, next) => {
    const token = localStorage.getItem('token')
    const isPublicRoute = publicRoutes.includes(to.name)
    
    // Handle root path - always redirect to login
    if (to.path === '/' || (to.path === '/' && to.name === undefined)) {
        next('/login')
        return
    }
    
    // If trying to navigate away from login page using browser back button
    if (from.name === 'Login' && to.name === undefined) {
        // Stay on login page
        next('/login')
        return
    }
    
    // If trying to navigate to login page, prevent back navigation
    if (to.name === 'Login') {
        // Push a new state to prevent back navigation
        window.history.pushState(null, '', window.location.href)
        
        // Add popstate listener
        const handlePopState = () => {
            window.history.pushState(null, '', window.location.href)
        }
        
        window.addEventListener('popstate', handlePopState)
        
        // Store the handler for cleanup
        router._loginPopStateHandler = handlePopState
    } else {
        // Remove the popstate listener when leaving login page
        if (router._loginPopStateHandler) {
            window.removeEventListener('popstate', router._loginPopStateHandler)
            router._loginPopStateHandler = null
        }
    }
    
    // If route is public (login, signup), allow access
    if (isPublicRoute) {
        // If user is already logged in and tries to access login/signup, redirect to dashboard
        if (token && (to.name === 'Login' || to.name === 'Signup')) {
            next('/dashboard')
            return
        }
        next()
        return
    }
    
    // Protected routes - check authentication
    if (!token) {
        // No token, redirect to login
        next({
            name: 'Login',
            query: { redirect: to.fullPath }
        })
        return
    }
    
    // Redirect User role from Dashboard to Supply Requests
    if (to.name === 'Dashboard') {
        try {
            const response = await axiosClient.get('/user')
            if (response.data) {
                const userRole = (response.data.role || '').toLowerCase()
                if (userRole === 'user') {
                    next('/supply-requests')
                    return
                }
            }
        } catch (error) {
            // If error checking user, continue normally
            console.error('Error checking user role for dashboard redirect:', error)
        }
    }
    
    // Check if route requires admin access
    if (adminRoutes.includes(to.name)) {
        try {
            // Fetch current user to check role using axiosClient
            const response = await axiosClient.get('/user')
            
            if (response.data) {
                const user = response.data
                const role = (user.role || '').toLowerCase()
                
                if (role !== 'admin' && role !== 'super_admin') {
                    // Not an admin, redirect to dashboard
                    next('/dashboard')
                    return
                }
            } else {
                // No user data, redirect to login
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                next({
                    name: 'Login',
                    query: { redirect: to.fullPath }
                })
                return
            }
        } catch (error) {
            console.error('Error checking user role:', error)
            // On error (401/403), redirect to login
            if (error.response?.status === 401 || error.response?.status === 403) {
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                next({
                    name: 'Login',
                    query: { redirect: to.fullPath }
                })
                return
            }
            // For other errors, still check but allow with warning
            next()
        }
    }
    
    // Check if route requires supply or admin access
    if (supplyRoutes.includes(to.name)) {
        try {
            const response = await axiosClient.get('/user')
            
            if (response.data) {
                const user = response.data
                const role = (user.role || '').toLowerCase()
                
                if (!['supply', 'admin', 'super_admin'].includes(role)) {
                    // Not supply or admin, redirect to dashboard
                    next('/dashboard')
                    return
                }
            } else {
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                next({
                    name: 'Login',
                    query: { redirect: to.fullPath }
                })
                return
            }
        } catch (error) {
            console.error('Error checking user role:', error)
            if (error.response?.status === 401 || error.response?.status === 403) {
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                next({
                    name: 'Login',
                    query: { redirect: to.fullPath }
                })
                return
            }
            next()
        }
    }
    
    next()
})

export default router 