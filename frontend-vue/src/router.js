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
import Maintenance from "./pages/Maintenance.vue"
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
import DeletedAccounts from './pages/DeletedAccounts.vue'
import Notifications from './pages/Notifications.vue'

const routes = [
    {
        path: '/',
        component: DefaultLayout,
        children: [
            {
                path: '/dashboard',
                name: 'Dashboard',
                component: Dashboard
            },
            {
                path: '/profile',
                name: 'Profile',
                component: Profile
            },
            {
                path: '/activity-log',
                name: 'ActivityLog',
                component: ActivityLog
            },
            {
                path: '/history',
                name: 'History',
                children: [
                    {
                        path: '',
                        redirect: '/history/deleted-items'
                    },
                    {
                        path: 'deleted-items',
                        name: 'DeletedItems',
                        component: DeletedItems
                    },
                    {
                        path: 'deleted-accounts',
                        name: 'DeletedAccounts',
                        component: DeletedAccounts
                    }
                ]
            },
            {
                path: '/inventory',
                name: 'Inventory',
                component: Inventory
            },
            {
                path: '/add-item',
                name: 'AddItem',
                component: AddItem
            },
            {
                path: '/maintenance',
                name: 'Maintenance',
                component: Maintenance
            },
            {
                path: '/admin',
                name: 'Admin',
                component: Admin
            },{
                path: '/analytics',
                name: 'Analytics',
                component: Analytics
            },
            {
                path: '/supplies',
                name: 'SuppliesOverview',
                component: SuppliesOverview
            },
            {
                path: '/usage',
                name: 'UsageOverview',
                component: UsageOverview
            },
            {
                path: '/add-account',
                name: 'AddAccount',
                component: AddAccount
            },
            {
                path: '/edit-account/:id',
                name: 'EditAccount',
                component: EditAccount
            },
            {
                path: '/reporting',
                name: 'Reporting',
                component: Reporting
            },
            {
                path: '/reports/desktop/:type?',
                name: 'DesktopMonitoring',
                component: DesktopMonitoring
            },
            {
                path: '/reports/serviceable-items',
                name: 'ServiceableItems',
                component: ServiceableItems
            },
            {
                path: '/reports/monitoring-assets',
                name: 'MonitoringAssets',
                component: () => import('./pages/reports/monitoring-assets.vue')
            },
            {
                path: '/QRGeneration',
                name: 'QRGeneration',
                component: QRGeneration
            },
            {
                path: '/edit-item/:uuid',
                name: 'EditItem',
                component: () => import('./pages/EditItem.vue')
            },
            {
                path: '/notifications',
                name: 'Notifications',
                component: Notifications
            }
        ]
    },
    {
        path: '',
        redirect: '/Login',
        component: Login
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

// Navigation guard to prevent back navigation from login page
router.beforeEach((to, from, next) => {
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
    
    next()
})

export default router 