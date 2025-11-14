import { createRouter, createWebHistory } from 'vue-router'
import Analytics from '../pages/Analytics.vue'
import SuppliesOverview from '../pages/SuppliesOverview.vue'
import UsageOverview from '../pages/UsageOverview.vue'
import QRGeneration from '../pages/QRGeneration.vue'
import Inventory from '../pages/Inventory.vue'
import Restock from '../pages/Restock.vue'
import ItemValidation from '../pages/ItemValidation.vue'
import DashboardView from '../pages/DashboardView.vue'
import ActivityLog from '../pages/ActivityLog.vue'
import DeletedItems from '../pages/DeletedItems.vue'
import NotFound from '../pages/NotFound.vue'
import AddSupply from '../pages/AddSupply.vue'
import EditItems from '../pages/EditItems.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/dashboard'
    },
    {
      path: '/dashboard',
      name: 'Dashboard',
      component: DashboardView
    },
    {
      path: '/profile',
      name: 'Profile',
      component: () => import('../pages/ProfileView.vue')
    },
    {
      path: '/activity-log',
      name: 'ActivityLog',
      component: ActivityLog
    },
    {
      path: '/history',
      children: [
        {
          path: 'deleted-items',
          name: 'DeletedItems',
          component: DeletedItems
        }
      ]
    },
    {
      path: '/QRGeneration',
      children: [
        {
          path: '',
          name: 'QRGeneration',
          component: QRGeneration
        },
        {
          path: 'item-validation/:id',
          name: 'ItemValidation',
          component: ItemValidation
        }
      ]
    },
    {
      path: '/test-edit/:uuid',
      name: 'TestEdit',
      component: () => import('../pages/TestEdit.vue')
    },
    {
      path: '/analytics',
      name: 'Analytics',
      component: Analytics
    },
    {
      path: '/transactions',
      name: 'Transactions',
      component: () => import('../pages/Transactions.vue')
    },
    {
      path: '/SuppliesOverview',
      name: 'SuppliesOverview',
      component: SuppliesOverview
    },
    {
      path: '/UsageOverview',
      name: 'UsageOverview',
      component: UsageOverview
    },
    {
      path: '/inventory',
      children: [
        {
          path: '',
          name: 'Inventory',
          component: Inventory
        },
        {
          path: 'add',
          name: 'AddSupply',
          component: AddSupply
        }
      ]
    },
    {
      path: '/categories',
      name: 'CategoryManagement',
      component: () => import('../pages/CategoryManagement.vue')
    },
    {
      path: '/restock',
      name: 'Restock',
      component: Restock
    },
    {
      path: '/reports',
      name: 'Reports',
      component: () => import('../pages/reports/index.vue')
    },
    {
      path: '/reports/serviceable-items',
      name: 'ServiceableItems',
      component: () => import('../pages/reports/serviceable-items.vue')
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      component: NotFound
    }
  ]
})

export default router 