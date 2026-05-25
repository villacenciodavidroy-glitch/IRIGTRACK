import {createRouter, createWebHistory} from "vue-router"
import DefaultLayout from "./layouts/DefaultLayout.vue"
import Login from "./pages/Login.vue"
import Signup from "./pages/Signup.vue"
import NotFound from "./pages/NotFound.vue"
import { getCachedUserRole } from './composables/useAuth'
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
                component: () => import('./pages/Dashboard.vue')
            },
            {
                path: 'profile',
                name: 'Profile',
                component: () => import('./pages/ProfileView.vue')
            },
            {
                path: 'activity-log',
                name: 'ActivityLog',
                component: () => import('./pages/ActivityLog.vue')
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
                        component: () => import('./pages/DeletedItems.vue')
                    },
                    {
                        path: 'maintenance-records',
                        name: 'MaintenanceRecords',
                        component: () => import('./pages/MaintenanceRecords.vue')
                    }
                ]
            },
            {
                path: 'inventory',
                name: 'Inventory',
                component: () => import('./pages/Inventory.vue')
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
                component: () => import('./pages/AddItem.vue')
            },
            {
                path: 'admin',
                name: 'Admin',
                component: () => import('./pages/Admin.vue')
            },
            {
                path: 'settings',
                name: 'Settings',
                redirect: '/settings/logo',
                component: () => import('./pages/Settings.vue')
            },
            {
                path: 'settings/logo',
                name: 'ChangeLogo',
                component: () => import('./pages/Settings.vue')
            },
            {
                path: 'settings/form-labels',
                name: 'FormLabels',
                component: () => import('./pages/Settings.vue')
            },
            {
                path: 'settings/activity-log',
                name: 'SettingsActivityLog',
                component: () => import('./pages/ActivityLog.vue')
            },
            {
                path: 'personnel-management',
                name: 'PersonnelManagement',
                component: () => import('./pages/PersonnelManagement.vue')
            },{
                path: 'analytics',
                name: 'Analytics',
                component: () => import('./pages/Analytics.vue')
            },
            {
                path: 'supplies',
                name: 'SuppliesOverview',
                component: () => import('./pages/SuppliesOverview.vue')
            },
            {
                path: 'usage',
                name: 'UsageOverview',
                component: () => import('./pages/UsageOverview.vue')
            },
            {
                path: 'add-account',
                name: 'AddAccount',
                component: () => import('./pages/AddAccount.vue')
            },
            {
                path: 'edit-account/:id',
                name: 'EditAccount',
                component: () => import('./pages/EditAccount.vue')
            },
            {
                path: 'reporting',
                name: 'Reporting',
                component: () => import('./pages/reports/index.vue')
            },
            {
                path: 'reports/desktop/:type?',
                name: 'DesktopMonitoring',
                component: () => import('./pages/reports/desktop.vue')
            },
            {
                path: 'reports/serviceable-items',
                name: 'ServiceableItems',
                component: () => import('./pages/reports/serviceable-items.vue')
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
                component: () => import('./pages/QRGeneration.vue')
            },
            {
                path: 'edit-item/:uuid',
                name: 'EditItem',
                component: () => import('./pages/EditItem.vue')
            },
            {
                path: 'notifications',
                name: 'Notifications',
                component: () => import('./pages/Notifications.vue')
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
                path: 'request-history',
                name: 'RequestHistory',
                component: () => import('./pages/RequestHistory.vue')
            },
            {
                path: 'supply-requests-management',
                name: 'SupplyRequestsManagement',
                component: () => import('./pages/SupplyRequestsManagement.vue')
            },
            {
                path: 'supply-requests-management/quantity',
                name: 'SupplyQuantity',
                component: () => import('./pages/SupplyQuantity.vue')
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
    
    const cachedRole = getCachedUserRole()

    // Redirect User role from Dashboard to Supply Requests
    if (to.name === 'Dashboard' && cachedRole === 'user') {
        next('/supply-requests')
        return
    }

    // Check if route requires admin access
    if (adminRoutes.includes(to.name)) {
        if (cachedRole && cachedRole !== 'admin' && cachedRole !== 'super_admin') {
            next('/dashboard')
            return
        }
        if (!cachedRole) {
            try {
                const response = await axiosClient.get('/user')
                const role = (response.data?.role || '').toLowerCase()
                if (response.data) {
                    localStorage.setItem('user', JSON.stringify(response.data))
                }
                if (role !== 'admin' && role !== 'super_admin') {
                    next('/dashboard')
                    return
                }
            } catch (error) {
                if (error.response?.status === 401 || error.response?.status === 403) {
                    localStorage.removeItem('token')
                    localStorage.removeItem('user')
                    next({ name: 'Login', query: { redirect: to.fullPath } })
                    return
                }
            }
        }
    }

    // Check if route requires supply or admin access
    if (supplyRoutes.includes(to.name)) {
        if (cachedRole && !['supply', 'admin', 'super_admin'].includes(cachedRole)) {
            next('/dashboard')
            return
        }
        if (!cachedRole) {
            try {
                const response = await axiosClient.get('/user')
                const role = (response.data?.role || '').toLowerCase()
                if (response.data) {
                    localStorage.setItem('user', JSON.stringify(response.data))
                }
                if (!['supply', 'admin', 'super_admin'].includes(role)) {
                    next('/dashboard')
                    return
                }
            } catch (error) {
                if (error.response?.status === 401 || error.response?.status === 403) {
                    localStorage.removeItem('token')
                    localStorage.removeItem('user')
                    next({ name: 'Login', query: { redirect: to.fullPath } })
                    return
                }
            }
        }
    }
    
    next()
})

export default router 