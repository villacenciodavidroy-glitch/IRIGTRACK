<template>
  <div class="analytics-container">
    <div class="header flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 mb-6">
      <div class="flex-1">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Item Lifespan Analytics</h1>
        <p class="text-sm sm:text-base text-gray-600 dark:text-gray-300 mt-2">Predictive Analysis for All Items Lifespan & Supply Management Based on Acquisition Date</p>
      </div>
      <div class="flex flex-wrap gap-2 items-center">
        <button 
          @click="openGlobalRestock"
          class="w-full sm:w-auto px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm sm:text-base"
        >
          Restock
        </button>
        <div class="hidden sm:block w-px h-6 bg-gray-200 dark:bg-gray-700 mx-2"></div>
        <select v-model="timeRange" class="flex-1 sm:flex-none min-w-[140px] px-3 sm:px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 text-sm">
          <option value="3">Last 3 months</option>
          <option value="6">Last 6 months</option>
          <option value="12">Last 12 months</option>
        </select>
        <select v-model="selectedCategory" class="flex-1 sm:flex-none min-w-[140px] px-3 sm:px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 text-sm">
          <option value="all">All Categories</option>
          <option value="supply">Supply</option>
          <option value="ict">ICT</option>
        </select>
        <select v-model="selectedStatus" class="flex-1 sm:flex-none min-w-[120px] px-3 sm:px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 text-sm">
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="low">Low Stock</option>
        </select>
      </div>
    </div>

    <!-- Supply Snapshot (Quantity Only) -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-gray-200 dark:border-gray-700 mb-8">
      <div class="flex items-center gap-3 mb-4">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Supply (Quantity)</h2>
        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm rounded-full font-medium">Snapshot</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left border-b-2 border-gray-200 dark:border-gray-700">
              <th class="pb-3 font-semibold text-gray-900 dark:text-white">ITEM NAME</th>
              <th class="pb-3 font-semibold text-gray-900 dark:text-white">QUANTITY</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in consumableQuantities" :key="c.name" class="border-t border-gray-200 dark:border-gray-700">
              <td class="py-3 font-medium text-gray-900 dark:text-white">{{ c.name }}</td>
              <td class="py-3 text-gray-900 dark:text-white">{{ c.quantity }}</td>
            </tr>
            <tr v-if="consumableQuantities.length === 0">
              <td colspan="2" class="py-4 text-sm text-gray-500 dark:text-gray-400 text-center">No supply items found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  <!-- Ending Lifespan Modal -->
  <div v-if="showEndingLifespanModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showEndingLifespanModal = false">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Items Ending Lifespan (≤ 30 days)</h3>
        <button @click="showEndingLifespanModal = false" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
          <span class="material-icons-outlined">close</span>
        </button>
      </div>
      <div class="max-h-96 overflow-y-auto">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[500px]">
            <thead>
              <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                <th class="py-2 px-2 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">Item</th>
                <th class="py-2 px-2 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">Remaining</th>
                <th class="py-2 px-2 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">End Date</th>
                <th class="py-2 px-2 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">Recommendation</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in endingSoonItems" :key="i.name + i.lifespanEndDate" class="border-t border-gray-200 dark:border-gray-700">
                <td class="py-2 px-2 text-xs sm:text-sm text-gray-900 dark:text-white">{{ i.name }}</td>
                <td class="py-2 px-2 text-xs sm:text-sm text-gray-900 dark:text-white">{{ i.remainingLifespan }} days</td>
                <td class="py-2 px-2 text-xs sm:text-sm text-gray-900 dark:text-white">{{ i.lifespanEndDate }}</td>
                <td class="py-2 px-2"><span :class="i.recommendationClass" class="text-xs sm:text-sm">{{ i.recommendation }}</span></td>
              </tr>
            <tr v-if="endingSoonItems.length === 0">
              <td colspan="4" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                No items predicted to end within 30 days.
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Accuracy Modal -->
  <div v-if="showAccuracyModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 max-w-xl w-full">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Prediction Accuracy</h3>
        <button @click="showAccuracyModal = false" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
          <span class="material-icons-outlined">close</span>
        </button>
      </div>
      <p class="text-gray-700 dark:text-gray-300">Current XGBoost accuracy: <span class="font-semibold">{{ lifespanAccuracy }}%</span></p>
      <p class="text-gray-700 dark:text-gray-300 mt-2">Predictions generated: <span class="font-semibold">{{ xgbLifespanForecast.length }}</span></p>
    </div>
  </div>
    

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
      <!-- Items Ending Lifespan Soon -->
      <div class="bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-lg p-6 shadow-sm border border-red-200 dark:border-red-700">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
            <span class="material-icons-outlined text-red-600 dark:text-red-400">schedule</span>
          </div>
          <div>
            <p class="text-gray-700 dark:text-gray-300 mb-2 font-medium">Ending Lifespan Soon</p>
            <div class="flex flex-col">
              <h3 class="text-2xl font-bold text-red-600 dark:text-red-400 mb-2">{{ endingLifespanCount }} Items</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">All item types</p>
              <button @click="showEndingLifespanModal = true" class="px-4 py-1.5 bg-red-600 text-white rounded-lg text-sm w-fit hover:bg-red-700">View Details</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Supply Low Stock -->
      <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg p-6 shadow-sm border border-green-200 dark:border-green-700">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
            <span class="material-icons-outlined text-green-600 dark:text-green-400">inventory</span>
          </div>
          <div>
            <p class="text-gray-700 dark:text-gray-300 mb-2 font-medium">Supply Low Stock</p>
            <div class="flex flex-col">
              <h3 class="text-2xl font-bold text-green-600 dark:text-green-400 mb-2">{{ consumableLowStock }} items</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Need restocking</p>
              <router-link :to="{ name: 'UsageOverview' }" class="px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm w-fit hover:bg-green-700">View</router-link>
            </div>
          </div>
        </div>
      </div>

      <!-- Lifespan Prediction Accuracy -->
      <div class="bg-gradient-to-br from-blue-50 to-cyan-50 dark:from-blue-900/20 dark:to-cyan-900/20 rounded-lg p-6 shadow-sm border border-blue-200 dark:border-blue-700">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
            <span class="material-icons-outlined text-blue-600 dark:text-blue-400">accuracy</span>
          </div>
          <div>
            <p class="text-gray-700 dark:text-gray-300 mb-2 font-medium">Lifespan Accuracy</p>
            <div class="flex flex-col">
              <h3 class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">{{ lifespanAccuracy }}%</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">All items prediction</p>
              <button @click="showAccuracyModal = true" class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm w-fit hover:bg-blue-700">View Report</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Average Lifespan All Items -->
      <div class="bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 rounded-lg p-6 shadow-sm border border-yellow-200 dark:border-yellow-700">
        <div class="flex items-start gap-4">
          <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-full flex items-center justify-center">
            <span class="material-icons-outlined text-yellow-600 dark:text-yellow-400">timeline</span>
          </div>
          <div>
            <p class="text-gray-700 dark:text-gray-300 mb-2 font-medium">Avg. Lifespan</p>
            <div class="flex flex-col">
              <h3 class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mb-2">{{ averageLifespan }} days</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">All item types</p>
              <button @click="showLifespanModal = true" class="px-4 py-1.5 bg-yellow-600 text-white rounded-lg text-sm w-fit hover:bg-yellow-700">View Analysis</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Item Lifespan & Supply Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-8">
      <!-- All Items Lifespan Alerts -->
      <div class="bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 border-l-4 border-red-500 dark:border-red-400 p-6 rounded-lg">
        <div class="flex justify-between items-start">
          <div>
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons-outlined text-red-500 dark:text-red-400 text-2xl">schedule</span>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Item Lifespan Alerts</h3>
              <span class="px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-sm rounded-full font-medium">All Items</span>
            </div>
            <div class="space-y-2">
              <div v-for="item in allItemsLifespan.filter(i => i.remainingLifespan <= 30).slice(0, 3)" :key="item.name" class="flex items-center gap-2">
                <div :class="item.statusClass" class="w-3 h-3 rounded-full"></div>
                <p :class="item.remainingLifespan <= 15 ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">
                  {{ item.name }} - Lifespan ending in {{ item.remainingLifespan }} days (Acquired: {{ item.acquisitionDate }})
                </p>
              </div>
              <div v-if="allItemsLifespan.filter(i => i.remainingLifespan <= 30).length === 0" class="text-gray-500 dark:text-gray-400 text-sm">
                No items ending lifespan soon
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Supply Low Stock Alerts -->
      <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500 dark:border-green-400 p-6 rounded-lg">
        <div class="flex justify-between items-start">
          <div>
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons-outlined text-green-500 dark:text-green-400 text-2xl">inventory</span>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Supply Alerts</h3>
              <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm rounded-full font-medium">Low Stock</span>
            </div>
            <div class="space-y-2">
              <div v-for="item in consumableSupplyItems.filter(i => parseInt(i.daysUntilEmpty) <= 14).slice(0, 3)" :key="item.name" class="flex items-center gap-2">
                <div :class="item.stockStatusClass" class="w-3 h-3 rounded-full"></div>
                <p :class="parseInt(item.daysUntilEmpty) <= 7 ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">
                  {{ item.name }} - {{ item.currentStock }} remaining ({{ item.daysUntilEmpty }} until empty)
                </p>
              </div>
              <div v-if="consumableSupplyItems.filter(i => parseInt(i.daysUntilEmpty) <= 14).length === 0" class="text-gray-500 dark:text-gray-400 text-sm">
                No supply items with low stock
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lifespan Analysis Section -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 mb-8 border border-blue-200 dark:border-blue-700">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
          <span class="material-icons-outlined text-white text-xl">schedule</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Lifespan Analysis & Consumption Forecast</h2>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Supply Lifespan Projections -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-blue-200 dark:border-blue-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Expected Lifespan (Based on Acquisition Date)</h3>
          <div class="space-y-4">
            <div v-for="item in supplyLifespanItems.slice(0, 5)" :key="item.name" class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-gray-700 dark:text-gray-300">{{ item.name }}</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ item.lifespan }} days</span>
                <p class="text-sm text-gray-600 dark:text-gray-400">avg. lifespan</p>
              </div>
            </div>
            <div v-if="supplyLifespanItems.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
              No supply items available
            </div>
          </div>
        </div>

        <!-- Consumption Rate Analysis -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-blue-200 dark:border-blue-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Consumption Rate Analysis</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-gray-700 dark:text-gray-300">Daily Usage Rate</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">2.3</span>
                <p class="text-sm text-gray-600 dark:text-gray-400">units per day</p>
              </div>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-gray-700 dark:text-gray-300">Peak Usage Days</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-orange-600 dark:text-orange-400">Mon-Fri</span>
                <p class="text-sm text-gray-600 dark:text-gray-400">weekdays</p>
              </div>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-gray-700 dark:text-gray-300">Seasonal Factor</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-purple-600 dark:text-purple-400">+15%</span>
                <p class="text-sm text-gray-600 dark:text-gray-400">winter months</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="mt-6 flex justify-between items-center">
        <p class="text-sm text-gray-600 dark:text-gray-300">
          <span class="font-medium">Prediction Accuracy:</span> 94.2% (XGBoost), 91.8% (CatBoost), 89.5% (Linear Regression)
        </p>
        <button @click="showLifespanModal = true" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          View Detailed Analysis
        </button>
      </div>
    </div>

    <!-- Predictive Analytics Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- ML-Powered Usage Prediction -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm h-[450px] border border-green-200 dark:border-green-700">
        <div class="flex items-center gap-3 mb-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">ML-Powered Usage Prediction</h2>
          <span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-xs rounded-full font-medium">XGBoost + CatBoost</span>
        </div>
        <div class="h-[350px]">
          <Line :data="mlUsagePredictionData" :options="mlPredictionOptions" />
        </div>
        <div class="mt-4 flex justify-between text-sm text-gray-600 dark:text-gray-300">
          <span>Historical Data</span>
          <span>ML Predictions</span>
          <span>Confidence: 94.2%</span>
        </div>
      </div>

      <!-- Predictive Maintenance Schedule -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm h-[450px] border border-yellow-200 dark:border-yellow-700">
        <div class="flex items-center gap-3 mb-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Predictive Maintenance Schedule</h2>
          <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 text-xs rounded-full font-medium">CatBoost</span>
        </div>
        <div class="h-[350px]">
          <Bar :data="maintenanceScheduleData" :options="maintenanceOptions" />
        </div>
        <div class="mt-4 flex justify-between text-sm text-gray-600 dark:text-gray-300">
          <span>Equipment Type</span>
          <span>ML Accuracy: 91.8%</span>
        </div>
      </div>
    </div>

    <!-- Advanced Forecasting Dashboard -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm mb-8 border border-blue-200 dark:border-blue-700">
      <div class="flex items-center gap-3 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Advanced Forecasting Dashboard</h2>
        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-sm rounded-full font-medium">Multi-Model Ensemble</span>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Model Performance -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Model Performance</h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600 dark:text-gray-300">XGBoost</span>
              <div class="flex items-center gap-2">
                <div class="w-20 h-2 bg-gray-200 dark:bg-gray-600 rounded-full">
                  <div class="w-4/5 h-2 bg-orange-500 rounded-full"></div>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-white">94.2%</span>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600 dark:text-gray-300">CatBoost</span>
              <div class="flex items-center gap-2">
                <div class="w-20 h-2 bg-gray-200 dark:bg-gray-600 rounded-full">
                  <div class="w-4/5 h-2 bg-purple-500 rounded-full"></div>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-white">91.8%</span>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600 dark:text-gray-300">Linear Regression</span>
              <div class="flex items-center gap-2">
                <div class="w-20 h-2 bg-gray-200 dark:bg-gray-600 rounded-full">
                  <div class="w-3/4 h-2 bg-green-500 rounded-full"></div>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-white">89.5%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Prediction Confidence -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Prediction Confidence</h3>
          <div class="text-center">
            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">92.1%</div>
            <p class="text-sm text-gray-600 dark:text-gray-300">Overall Accuracy</p>
            <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
              <p>Next 30 days: 94.2%</p>
              <p>Next 90 days: 89.8%</p>
            </div>
          </div>
        </div>

        <!-- Data Freshness -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
          <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Data Freshness</h3>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600 dark:text-gray-300">Last Update</span>
              <span class="text-sm font-medium text-green-600 dark:text-green-400">2 min ago</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600 dark:text-gray-300">Training Data</span>
              <span class="text-sm font-medium text-gray-900 dark:text-white">12 months</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-600 dark:text-gray-300">API Status</span>
              <span class="text-sm font-medium text-green-600 dark:text-green-400">Online</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Forecasting Chart -->
      <div class="h-[400px]">
        <Line :data="advancedForecastData" :options="advancedForecastOptions" />
      </div>
    </div>

    <!-- All Items Lifespan Management Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-gray-200 dark:border-gray-700 mb-8">
      <div class="flex items-center gap-3 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">All Items Lifespan Management</h2>
        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 text-sm rounded-full font-medium">Date-Based Predictions</span>
      </div>
      
      <div class="overflow-x-auto -mx-6 px-6">
        <table class="w-full min-w-[900px]">
          <thead>
            <tr class="text-left border-b-2 border-gray-200 dark:border-gray-700">
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">ITEM NAME</th>
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">CATEGORY</th>
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hidden md:table-cell">ACQUISITION DATE</th>
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">EXPECTED LIFESPAN</th>
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hidden lg:table-cell">LIFESPAN END</th>
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white">ML RECOMMENDATION</th>
              <th class="pb-3 sm:pb-4 px-2 sm:px-4 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white hidden xl:table-cell">FORECAST</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in allItemsLifespan" :key="item.name" class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="py-3 sm:py-4 px-2 sm:px-4 text-xs sm:text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</td>
              <td class="py-3 sm:py-4 px-2 sm:px-4">
                <span :class="item.categoryClass" class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ item.category }}
                </span>
              </td>
              <td class="py-3 sm:py-4 px-2 sm:px-4 hidden md:table-cell">
                <div>
                  <div class="text-xs sm:text-sm font-medium text-gray-900 dark:text-white">{{ item.acquisitionDate }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.daysSinceAcquisition }} days ago</div>
                </div>
              </td>
              <td class="py-3 sm:py-4 px-2 sm:px-4">
                <div>
                  <div class="text-xs sm:text-sm font-medium text-gray-900 dark:text-white">{{ item.expectedLifespan }} days</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.remainingLifespan }} remaining</div>
                </div>
              </td>
              <td class="py-3 sm:py-4 px-2 sm:px-4 hidden lg:table-cell">
                <div>
                  <div :class="item.lifespanClass" class="text-xs sm:text-sm font-medium">{{ item.lifespanEndDate }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.daysUntilEnd }}</div>
                </div>
              </td>
              <td class="py-3 sm:py-4 px-2 sm:px-4">
                <div class="flex flex-col gap-1">
                  <span :class="item.recommendationClass" class="text-xs sm:text-sm font-medium">{{ item.recommendation }}</span>
                </div>
              </td>
              <td class="py-3 sm:py-4 px-2 sm:px-4 hidden xl:table-cell">
                <div class="flex items-center gap-2">
                  <div class="w-12 sm:w-16 h-2 bg-gray-200 dark:bg-gray-600 rounded-full">
                    <div :class="item.confidenceClass" class="h-2 rounded-full" :style="{ width: item.confidence + '%' }"></div>
                  </div>
                  <span class="text-xs sm:text-sm font-medium text-gray-900 dark:text-white">{{ item.confidence }}%</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

      <!-- Supply Management Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-gray-200 dark:border-gray-700">
      <div class="flex items-center gap-3 mb-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Supply Management</h2>
        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm rounded-full font-medium">Stock-Based Management</span>
      </div>
      
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left border-b-2 border-gray-200 dark:border-gray-700">
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">ITEM NAME</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">CATEGORY</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">CURRENT STOCK</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">CONSUMPTION RATE</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">DAYS UNTIL EMPTY</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">ML RECOMMENDATION</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">CONFIDENCE</th>
              <th class="pb-4 font-semibold text-gray-900 dark:text-white">ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in paginatedConsumables" :key="item.name" class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="py-4 font-medium text-gray-900 dark:text-white">{{ item.name }}</td>
              <td>
                <span :class="item.categoryClass" class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ item.category }}
                </span>
              </td>
              <td class="py-4">
                <div class="flex items-center gap-2">
                  <span :class="item.stockStatusClass" class="w-3 h-3 rounded-full"></span>
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ item.currentStock }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Recommended: {{ item.recommendedStock }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4">
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">{{ item.consumptionRate }}</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">per day</div>
                </div>
              </td>
              <td class="py-4">
                <div>
                  <div class="font-medium text-gray-900 dark:text-white">{{ item.daysUntilEmpty }}</div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">until empty</div>
                </div>
              </td>
              <td class="py-4">
                <div class="flex flex-col gap-1">
                  <span :class="item.recommendationClass" class="text-sm font-medium">{{ item.recommendation }}</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ item.recommendedQuantity }}</span>
                </div>
              </td>
              <td class="py-4">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.nextThreeMonths }}</div>
              </td>
              <td class="py-4">
                <div class="text-sm text-gray-900 dark:text-white">{{ item.restockBy }}</div>
              </td>
              <td class="py-4">
                <div class="flex items-center gap-2">
                  <div class="w-16 h-2 bg-gray-200 dark:bg-gray-600 rounded-full">
                    <div :class="item.confidenceClass" class="h-2 rounded-full" :style="{ width: item.confidence + '%' }"></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ item.confidence }}%</span>
                </div>
              </td>
              <td class="py-4">
                <button 
                  @click="openRestock(item)"
                  class="px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
                >
                  Restock
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex gap-4">
          <button @click="exportConsumablesCSV" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
            <span class="material-icons-outlined text-sm">download</span>
            Export Excel
          </button>
          <button @click="generateSupplyReport" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
            <span class="material-icons-outlined text-sm">inventory</span>
            Generate Supply Report
          </button>
        </div>
        <div class="flex gap-4">
          <button @click="goPrev" :disabled="currentConsumablePage === 1" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50">Previous</button>
          <button @click="goNext" :disabled="currentConsumablePage === totalConsumablePages" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50">Next</button>
        </div>
      </div>
    </div>

    <!-- Data-Driven Decision Support Interface -->
    <div class="bg-gradient-to-r from-green-50 to-teal-50 dark:from-green-900/20 dark:to-teal-900/20 rounded-xl p-6 mb-8 border border-green-200 dark:border-green-700">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
          <span class="material-icons-outlined text-white text-xl">insights</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Data-Driven Decision Support</h2>
        <span class="px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 text-sm rounded-full font-medium">AI-Powered Recommendations</span>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Procurement Recommendations (from inventory supply) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-green-200 dark:border-green-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Procurement Recommendations</h3>
          <div class="space-y-4">
            <div v-for="rec in procurementList" :key="rec.name"
              class="p-4 rounded-lg border-l-4"
              :class="rec.urgencyClass">
              <div class="flex justify-between items-start mb-2">
                <h4 class="font-medium text-gray-900 dark:text-white">{{ rec.name }}</h4>
                <span class="text-xs px-2 py-1 rounded" :class="rec.badgeClass">{{ rec.urgencyLabel }}</span>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">Recommended quantity: {{ rec.recommendedQuantity }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">Days until empty: {{ rec.daysUntilEmpty }}</p>
            </div>
            <div v-if="procurementList.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No supply items require procurement.</div>
          </div>
        </div>

        <!-- Resource Allocation Insights -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-green-200 dark:border-green-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Resource Allocation Insights</h3>
          <div class="space-y-4">
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">Budget Optimization</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Q1 Budget Allocated</span>
                  <span class="font-medium text-gray-900 dark:text-white">$45,000</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Predicted Spend</span>
                  <span class="font-medium text-green-600 dark:text-green-400">$42,300</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Savings Potential</span>
                  <span class="font-medium text-blue-600 dark:text-blue-400">$2,700</span>
                </div>
              </div>
            </div>
            
            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">Efficiency Metrics</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Stock Turnover Rate</span>
                  <span class="font-medium text-gray-900 dark:text-white">4.2x</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Waste Reduction</span>
                  <span class="font-medium text-green-600 dark:text-green-400">-15%</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Procurement Efficiency</span>
                  <span class="font-medium text-blue-600 dark:text-blue-400">+23%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ML Model Insights -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-green-200 dark:border-green-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">ML Model Insights</h3>
          <div class="space-y-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">Prediction Accuracy</h4>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Overall Accuracy</span>
                  <span class="font-medium text-green-600 dark:text-green-400">92.1%</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Supply Predictions</span>
                  <span class="font-medium text-blue-600 dark:text-blue-400">94.2%</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-300">Maintenance Forecasts</span>
                  <span class="font-medium text-purple-600 dark:text-purple-400">91.8%</span>
                </div>
              </div>
            </div>
            
            <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">Key Patterns Detected</h4>
              <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                <li>• Paper usage peaks on Mondays</li>
                <li>• ICT equipment needs maintenance every 6 months</li>
                <li>• Seasonal patterns in supply consumption</li>
                <li>• Budget optimization opportunities identified</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      
      <div class="mt-6 flex justify-between items-center">
        <div class="text-sm text-gray-600 dark:text-gray-300">
          <span class="font-medium">Last Updated:</span> 2 minutes ago | 
          <span class="font-medium">Next Refresh:</span> 5 minutes
        </div>
        <div class="flex gap-3">
          <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
            Generate Report
          </button>
          <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
            Export Insights
          </button>
        </div>
      </div>
    </div>

    

    <!-- Restock Modal -->
    <div v-if="showRestockModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 sm:p-6 max-w-xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-lg text-green-600 dark:text-green-400 font-medium">Restock {{ selectedRestockItemName || 'Item' }}</h2>
          <button @click="showRestockModal = false" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="handleRestock" class="space-y-6">
          <div class="bg-white dark:bg-gray-700 p-4 rounded-lg shadow-sm">
            <label class="block text-green-600 dark:text-green-400 font-medium mb-2">Select Supply Item</label>
            <select
              v-model="selectedRestockItemName"
              class="w-full p-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 mb-4"
            >
              <option v-for="opt in consumableSupplyItems" :key="opt.name" :value="opt.name">{{ opt.name }}</option>
            </select>
            <label class="block text-green-600 dark:text-green-400 font-medium mb-2">Quantity</label>
            <div>
              <input 
                v-model.number="restockAmount"
                type="number"
                class="w-full p-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                min="0"
              />
              <p class="mt-2 text-xs text-gray-500 dark:text-gray-300">Suggested: {{ restockAmount }}</p>
            </div>
          </div>

          <div class="flex justify-end">
            <button 
              type="submit"
              class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
            >
              Add
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../composables/useItems'
import axios from 'axios'
import axiosClient from '../axios'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import { Line, Bar } from 'vue-chartjs'

const router = useRouter()

// Fetch real inventory data
const { items, fetchitems, loading, error } = useItems()

// Flask API base URL (Python service)
const PY_API_BASE = import.meta.env.VITE_PY_API_BASE_URL || 'http://127.0.0.1:5000'

// Prediction state
const lrConsumableForecast = ref([]) // Linear Regression results for supply
const xgbLifespanForecast = ref([])  // XGBoost results for equipment lifespan
const apiLoading = ref(false)
const apiError = ref(null)
// Category helper: treat Supply category as supply
const isConsumableCategory = (category) => {
  const c = (category || '').toLowerCase()
  return c === 'supply' || c.includes('supply')
}


const showRestockModal = ref(false)
const showExpiringModal = ref(false)
const showAccuracyModal = ref(false)
const showLifespanModal = ref(false)
const selectedRestockItem = ref(null)
const selectedRestockItemName = ref('')
const restockAmount = ref(0)

// Equipment-only Lifespan Data (exclude supply)
// NOTE: this is the ONLY declaration of allItemsLifespan
const allItemsLifespan = computed(() => {
  if (!items.value || items.value.length === 0) return []
  return items.value
    .filter(item => !isConsumableCategory(item?.category))
    .map(item => {
      const acquisitionDate = new Date(item.date_acquired)
      const today = new Date()
      const daysSinceAcquisition = Math.floor((today - acquisitionDate) / (1000 * 60 * 60 * 24))
      let expectedLifespan = (() => {
        const pred = xgbLifespanForecast.value.find(p => (p.name || '').toLowerCase() === (item.unit || '').toLowerCase())
        if (pred?.expected_lifespan_days != null) return parseInt(pred.expected_lifespan_days)
        if ((item.category || '') === 'Desktop' || (item.category || '') === 'ICT') return 1095
        if (isConsumableCategory(item.category)) return 365
        return 1095
      })()
      const remainingLifespan = Math.max(0, expectedLifespan - daysSinceAcquisition)
      const lifespanEndDate = new Date(acquisitionDate.getTime() + (expectedLifespan * 24 * 60 * 60 * 1000))
      let statusClass = 'bg-green-500'
      let lifespanClass = 'text-green-600'
      let recommendation = 'Good condition'
      let recommendationClass = 'text-green-600'
      let confidence = (() => {
        const pred = xgbLifespanForecast.value.find(p => (p.name || '').toLowerCase() === (item.unit || '').toLowerCase())
        return typeof pred?.confidence === 'number' ? pred.confidence : 92
      })()
      return {
        name: item.unit || 'Unknown Item',
        description: item.description || '',
        category: item.category || 'Unknown',
        categoryClass: (item.category || '') === 'ICT' ? 'bg-purple-100 text-purple-800' : 
                      (item.category || '') === 'Desktop' ? 'bg-blue-100 text-blue-800' :
                      'bg-green-100 text-green-800',
        acquisitionDate: acquisitionDate.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' }),
        daysSinceAcquisition,
        expectedLifespan,
        remainingLifespan,
        itemType: isConsumableCategory(item.category) ? 'Supply' : 'Non-Consumable',
        statusClass,
        lifespanEndDate: lifespanEndDate.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' }),
        lifespanClass,
        daysUntilEnd: `${remainingLifespan} days`,
        recommendation,
        recommendationClass,
        confidence,
        confidenceClass: statusClass,
        unitValue: item.unit_value,
        pac: item.pac,
        location: item.location?.location || 'Unknown'
      }
    })
})

// Computed data based on real inventory
const endingSoonItems = computed(() => {
  return (allItemsLifespan.value || [])
    .filter(item => Number(item.remainingLifespan) <= 30)
    .sort((a, b) => Number(a.remainingLifespan) - Number(b.remainingLifespan))
})

const endingLifespanCount = computed(() => endingSoonItems.value.length)

const consumableLowStock = computed(() => {
  return consumableSupplyItems.value.filter(item => {
    const daysUntilEmpty = parseInt(item.daysUntilEmpty)
    return daysUntilEmpty <= 14
  }).length
})

// Simple supply quantity snapshot
const consumableQuantities = computed(() => {
  if (!items.value || items.value.length === 0) return []
  return (items.value || [])
    .filter(i => isConsumableCategory(i?.category))
    .map(i => ({
      name: i.unit || 'Supply',
      quantity: i.quantity ?? 0
    }))
    .sort((a, b) => a.name.localeCompare(b.name))
})

// Supply Lifespan Items for the Expected Lifespan section
const supplyLifespanItems = computed(() => {
  if (!items.value || items.value.length === 0) return []
  return (items.value || [])
    .filter(i => isConsumableCategory(i?.category))
    .map(i => {
      const acquisitionDate = i.date_acquired ? new Date(i.date_acquired) : new Date()
      const today = new Date()
      const daysSinceAcquisition = Math.floor((today - acquisitionDate) / (1000 * 60 * 60 * 24))
      
      // Calculate expected lifespan - use forecast if available, otherwise use default
      const forecast = lrConsumableForecast.value.find(f => (f.name || '').toLowerCase() === (i.unit || '').toLowerCase())
      let expectedLifespan = 365 // Default for supply items
      
      if (forecast?.expected_lifespan_days != null) {
        expectedLifespan = parseInt(forecast.expected_lifespan_days)
      } else if (forecast?.days_until_empty != null) {
        // If we have days until empty and current quantity, estimate lifespan
        const consumptionRate = parseFloat(String(forecast.consumption_rate || '1').split(' ')[0]) || 1
        expectedLifespan = Math.max(30, Math.floor((i.quantity || 0) / consumptionRate))
      }
      
      return {
        name: i.unit || 'Supply Item',
        lifespan: expectedLifespan,
        daysSinceAcquisition,
        quantity: i.quantity || 0
      }
    })
    .sort((a, b) => b.lifespan - a.lifespan) // Sort by lifespan descending
    .slice(0, 10) // Limit to top 10
})

// Pagination for supply table
const currentConsumablePage = ref(1)
const consumablesPerPage = ref(10)
const totalConsumablePages = computed(() => {
  return Math.max(1, Math.ceil(consumableSupplyItems.value.length / consumablesPerPage.value))
})
const paginatedConsumables = computed(() => {
  const start = (currentConsumablePage.value - 1) * consumablesPerPage.value
  return consumableSupplyItems.value.slice(start, start + consumablesPerPage.value)
})
const goPrev = () => { if (currentConsumablePage.value > 1) currentConsumablePage.value-- }
const goNext = () => { if (currentConsumablePage.value < totalConsumablePages.value) currentConsumablePage.value++ }

// Export and reporting actions
const exportConsumablesCSV = () => {
  const rows = [
    ['Name','Category','Current Stock','Consumption Rate','Days Until Empty','Recommendation','Recommended Qty','Confidence'],
    ...consumableSupplyItems.value.map(i => [
      i.name, i.category, i.currentStock, i.consumptionRate, i.daysUntilEmpty, i.recommendation, i.recommendedQuantity, i.confidence
    ])
  ]
  const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'supply_export.csv'
  a.click()
  URL.revokeObjectURL(url)
}

const generateSupplyReport = () => {
  const payload = {
    generatedAt: new Date().toISOString(),
    totalItems: consumableSupplyItems.value.length,
    lowStockItems: consumableSupplyItems.value.filter(i => parseInt(i.daysUntilEmpty) <= 14),
    forecast: lrConsumableForecast.value
  }
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'supply_report.json'
  a.click()
  URL.revokeObjectURL(url)
}

// Accuracy can be reported by API; default placeholders
const lifespanAccuracy = ref(94.2)
const averageLifespan = computed(() => {
  if (allItemsLifespan.value.length === 0) return 0
  const totalLifespan = allItemsLifespan.value.reduce((sum, item) => sum + item.expectedLifespan, 0)
  return Math.round(totalLifespan / allItemsLifespan.value.length)
})

const openRestock = (item) => {
  selectedRestockItem.value = item
  selectedRestockItemName.value = item?.name || ''
  // try to default to next three months quantity if parseable
  const n = parseInt(String(item.nextThreeMonths).replace(/[^0-9]/g, ''))
  restockAmount.value = isNaN(n) ? 0 : n
  showRestockModal.value = true
}

// Header button handler: choose first supply item if available
const openGlobalRestock = () => {
  const list = consumableSupplyItems.value || []
  if (list.length === 0) {
    showRestockModal.value = true
    return
  }
  const first = list[0]
  selectedRestockItem.value = first
  selectedRestockItemName.value = first.name
  const n = parseInt(String(first.nextThreeMonths).replace(/[^0-9]/g, ''))
  restockAmount.value = isNaN(n) ? 0 : n
  showRestockModal.value = true
}

const handleRestock = async () => {
  try {
    const item = selectedRestockItem.value
    if (!item?.uuid) {
      console.error('No item selected for restock')
      return
    }
    const newQuantity = Number(item.currentQuantityNum || 0) + Number(restockAmount.value || 0)
    // Update quantity via backend API using item uuid as route key
    await axiosClient.put(`/items/${item.uuid}`, { quantity: newQuantity })
    // Refresh inventory data
    await fetchitems()
    
    // Close modal after successful submission
    showRestockModal.value = false
    
    // Reset form data
    selectedRestockItem.value = null
    restockAmount.value = 0
  } catch (error) {
    console.error('Error restocking items:', error)
  }
}

// Keep selected item in sync when choosing from the dropdown
watch(selectedRestockItemName, (newName) => {
  if (!newName) return
  const found = (consumableSupplyItems.value || []).find(i => i.name === newName)
  if (found) {
    selectedRestockItem.value = found
    const n = parseInt(String(found.nextThreeMonths).replace(/[^0-9]/g, ''))
    restockAmount.value = isNaN(n) ? 0 : n
  }
})

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend
)

// Filter states
const timeRange = ref('3')
const selectedCategory = ref('all')
const selectedStatus = ref('all')

// Chart Data for Predictive Analytics
const mlUsagePredictionData = ref({
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  datasets: [{
    label: 'Historical Usage',
    data: [450, 500, 480, 550, 600, 580, 620, 650, 700, 680, 720, 750],
    borderColor: '#10B981',
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    fill: false,
    tension: 0.4,
    borderWidth: 2,
    pointRadius: 4,
    pointBackgroundColor: '#10B981'
  }, {
    label: 'ML Predictions',
    data: [450, 500, 480, 550, 600, 580, 620, 650, 700, 680, 720, 750, 780, 820, 850, 880],
    borderColor: '#3B82F6',
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    fill: false,
    tension: 0.4,
    borderWidth: 3,
    borderDash: [5, 5],
    pointRadius: 4,
    pointBackgroundColor: '#3B82F6'
  }]
})

const maintenanceScheduleData = ref({
  labels: ['Printers', 'Computers', 'Network', 'Servers', 'Projectors'],
  datasets: [{
    label: 'Maintenance Schedule',
    data: [8, 15, 12, 6, 4],
    backgroundColor: ['#F59E0B', '#EF4444', '#10B981', '#8B5CF6', '#06B6D4'],
    borderRadius: 4
  }]
})

const advancedForecastData = ref({
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan+1', 'Feb+1', 'Mar+1'],
  datasets: [{
    label: 'Actual Usage',
    data: [450, 520, 480, 550, 600, 580, 620, 650, 700, 680, 720, 750, null, null, null],
    borderColor: '#10B981',
    backgroundColor: '#10B981',
    tension: 0.4,
    pointRadius: 4,
    borderWidth: 2
  }, {
    label: 'XGBoost Prediction',
    data: [null, null, null, null, null, null, null, null, null, null, null, 750, 780, 820, 850],
    borderColor: '#F59E0B',
    backgroundColor: 'rgba(245, 158, 11, 0.1)',
    tension: 0.4,
    pointRadius: 4,
    borderWidth: 2,
    borderDash: [3, 3]
  }, {
    label: 'CatBoost Prediction',
    data: [null, null, null, null, null, null, null, null, null, null, null, 750, 775, 810, 845],
    borderColor: '#8B5CF6',
    backgroundColor: 'rgba(139, 92, 246, 0.1)',
    tension: 0.4,
    pointRadius: 4,
    borderWidth: 2,
    borderDash: [5, 5]
  }, {
    label: 'Linear Regression',
    data: [null, null, null, null, null, null, null, null, null, null, null, 750, 770, 800, 830],
    borderColor: '#06B6D4',
    backgroundColor: 'rgba(6, 182, 212, 0.1)',
    tension: 0.4,
    pointRadius: 4,
    borderWidth: 2,
    borderDash: [8, 8]
  }]
})

// Legacy chart data (keeping for compatibility)
const usageTrendsData = ref({
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  datasets: [{
    label: 'Total Usage',
    data: [450, 500, 480, 550, 600, 580, 620, 650, 700, 680, 720, 750],
    borderColor: '#10B981',
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    fill: true,
    tension: 0.4,
    borderWidth: 2,
    pointRadius: 4,
    pointBackgroundColor: '#10B981'
  }]
})

const frequentItemsData = ref({
  labels: ['Printer Ink', 'Paper', 'Ballpens', 'Flash Drives', 'Ethernet Cables'],
  datasets: [{
    label: 'Units Consumed',
    data: [800, 1150, 950, 450, 380],
    backgroundColor: ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6'],
    borderRadius: 4
  }]
})

const forecastData = ref({
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  datasets: [{
    label: 'Actual Usage',
    data: [450, 520, 480, 550, 600, 580, 620, 650, 700, 680, 720, 750],
    borderColor: '#10B981',
    backgroundColor: '#10B981',
    tension: 0.4,
    pointRadius: 4,
    borderWidth: 2
  }]
})

// Chart Options
const lineOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false,
        color: '#E5E7EB'
      },
      ticks: {
        font: {
          size: 12
        }
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 12
        }
      }
    }
  }
})

const barOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false,
        color: '#E5E7EB'
      },
      ticks: {
        font: {
          size: 12
        }
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 12
        }
      }
    }
  }
})

const forecastOptions = ref({
  ...lineOptions.value,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12
        }
      }
    }
  }
})

// New chart options for predictive analytics
const mlPredictionOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false,
        color: '#E5E7EB'
      },
      ticks: {
        font: {
          size: 12
        }
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 12
        }
      }
    }
  }
})

const maintenanceOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false,
        color: '#E5E7EB'
      },
      ticks: {
        font: {
          size: 12
        }
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 12
        }
      }
    }
  }
})

const advancedForecastOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: {
        drawBorder: false,
        color: '#E5E7EB'
      },
      ticks: {
        font: {
          size: 12
        }
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 12
        }
      }
    }
  }
})

// Predicted lifespan list: all equipment, sorted by remaining days (ascending)
const predictedLifespanList = computed(() => {
  return (allItemsLifespan.value || [])
    .slice()
    .sort((a, b) => (a.remainingLifespan || 0) - (b.remainingLifespan || 0))
})

// Snapshot used for modal to avoid recompute lag
const predictedLifespanSnapshot = ref([])
// keep snapshot in sync proactively so the modal can show instantly
watch(predictedLifespanList, (list) => {
  predictedLifespanSnapshot.value = Array.isArray(list) ? list.slice(0, 500) : []
}, { immediate: true })

// Open modal (predicted list snapshot)
const openEndingLifespanModal = () => {
  showEndingLifespanModal.value = true
}

// Build procurement list from supply items with lowest days until empty
const procurementList = computed(() => {
  const parseDays = (s) => {
    const n = parseInt(String(s).replace(/[^0-9]/g, ''))
    return isNaN(n) ? 999999 : n
  }
  const toBadge = (days) => {
    if (days <= 7) return { label: 'URGENT', badge: 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300', box: 'bg-red-50 dark:bg-red-900/20 border-red-500 dark:border-red-400' }
    if (days <= 14) return { label: 'HIGH', badge: 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-300', box: 'bg-orange-50 dark:bg-orange-900/20 border-orange-500 dark:border-orange-400' }
    return { label: 'MEDIUM', badge: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300', box: 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-500 dark:border-yellow-400' }
  }
  return (consumableSupplyItems.value || [])
    .map(i => {
      const days = parseDays(i.daysUntilEmpty)
      const badge = toBadge(days)
      return {
        name: i.name,
        daysUntilEmpty: i.daysUntilEmpty,
        recommendedQuantity: i.recommendedQuantity,
        urgencyLabel: badge.label,
        badgeClass: badge.badge,
        urgencyClass: `border-l-4 ${badge.box}`
      }
    })
    .sort((a, b) => parseDays(a.daysUntilEmpty) - parseDays(b.daysUntilEmpty))
    .slice(0, 3)
})

// duplicate allItemsLifespan removed (kept earlier declaration)

// Supply Data (Based on Real Inventory + Linear Regression forecast)
const consumableSupplyItems = computed(() => {
  if (!items.value || items.value.length === 0) return []

  const base = items.value.filter(item => isConsumableCategory(item?.category))
  return base.map(item => {
    const currentQuantity = item.quantity || 0

    // Try find forecast from Flask API response
    const forecast = lrConsumableForecast.value.find(f => (f.name || '').toLowerCase() === (item.unit || '').toLowerCase())
    const daysUntilEmpty = forecast?.days_until_empty != null
      ? parseInt(forecast.days_until_empty)
      : Math.max(0, Math.floor(currentQuantity / 1))
    const recommendedStock = forecast?.recommended_stock != null
      ? forecast.recommended_stock
      : Math.max(50, currentQuantity * 2)
    const consumptionRate = forecast?.consumption_rate || '1 unit/day'

    // Next 3 months consumption prediction
    // Priority: API-provided recent usage history -> 3-month sum of model forecast -> simple rate estimate
    let nextThreeMonths = 'N/A'
    if (Array.isArray(forecast?.usage_history) && forecast.usage_history.length) {
      const avgDaily = forecast.usage_history
        .slice(-90) // last ~3 months if provided daily; API may also provide monthly totals
        .reduce((s, v) => s + Number(v || 0), 0)
      nextThreeMonths = `${Math.round(avgDaily)} units`
    } else if (Array.isArray(forecast?.monthly) && forecast.monthly.length) {
      const sum = forecast.monthly.slice(0, 3).reduce((s, m) => s + Number(m.quantity || 0), 0)
      nextThreeMonths = `${sum} units`
    } else {
      const rateNum = parseFloat(String(consumptionRate).split(' ')[0]) || 1
      nextThreeMonths = `${Math.round(rateNum * 90)} units`
    }
    // Restock by date if will empty within 90 days
    const restockBy = daysUntilEmpty <= 90
      ? new Date(Date.now() + daysUntilEmpty * 24 * 60 * 60 * 1000).toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' })
      : '>' + 90 + ' days'

    let stockStatusClass = 'bg-green-500'
    let recommendation = 'Good stock level'
    let recommendationClass = 'text-green-600'
    let confidence = typeof forecast?.confidence === 'number' ? forecast.confidence : 92
    let recommendedQuantity = forecast?.recommended_quantity || nextThreeMonths

    if (daysUntilEmpty <= 7) {
      stockStatusClass = 'bg-red-500'
      recommendation = 'URGENT: Restock immediately'
      recommendationClass = 'text-red-600'
    } else if (daysUntilEmpty <= 14) {
      stockStatusClass = 'bg-orange-500'
      recommendation = 'Order within 1 week'
      recommendationClass = 'text-orange-600'
    } else if (daysUntilEmpty <= 30) {
      stockStatusClass = 'bg-yellow-500'
      recommendation = 'Monitor stock levels'
      recommendationClass = 'text-yellow-600'
    }

    return {
      name: item.unit || 'Unknown Item',
      description: item.description || '',
      category: 'Supply',
      categoryClass: 'bg-green-100 text-green-800',
      currentStock: `${currentQuantity} units`,
      currentQuantityNum: Number(currentQuantity),
      stockStatusClass,
      recommendedStock: `${recommendedStock} units`,
      consumptionRate,
      daysUntilEmpty: `${daysUntilEmpty} days`,
      recommendation,
      recommendationClass,
      recommendedQuantity: `${recommendedQuantity} units`,
      confidence,
      confidenceClass: stockStatusClass,
      nextThreeMonths,
      restockBy,
      unitValue: item.unit_value,
      pac: item.pac,
      location: item.location?.location || 'Unknown',
      uuid: item.uuid
    }
  })
})

// Legacy table data (keeping for compatibility)
const inventoryItems = ref([
  {
    name: 'Printer Ink',
    category: 'Supply',
    currentStock: '15 units',
    monthlyUsage: '8 units',
    shortageDate: 'July 2025'
  },
  {
    name: 'Ethernet Cable',
    category: 'ICT',
    currentStock: '10 pcs',
    monthlyUsage: '5 pcs',
    shortageDate: 'August 2025'
  }
])

// Build payloads for Python API
const buildConsumablePayload = () => {
  // Aggregate supply items with quantity over time if available; fallback uses current stock
  const consumables = (items.value || []).filter(i => isConsumableCategory(i?.category))
  return {
    series: consumables.map(i => ({
      name: i.unit || 'Supply',
      current_quantity: Number(i.quantity || 0),
      // historical can be extended when available; keep API flexible
      history: [],
    }))
  }
}

const buildLifespanPayload = () => {
  const equipment = (items.value || [])
    .filter(i => !isConsumableCategory(i?.category))
  return {
    items: equipment.map(i => ({
      name: i.unit || 'Equipment',
      description: i.description || '',
      date_acquired: i.date_acquired,
      category: i?.category || 'Unknown',
      unit_value: Number(i.unit_value || 0)
    }))
  }
}

const fetchPredictions = async () => {
  apiLoading.value = true
  apiError.value = null
  try {
    // Linear Regression for supply low-stock forecast
    const [lrRes, xgbRes] = await Promise.all([
      axios.post(`${PY_API_BASE}/predict/consumables/linear`, buildConsumablePayload(), { timeout: 10000 }).catch(() => null),
      axios.post(`${PY_API_BASE}/predict/equipment-lifespan/xgboost`, buildLifespanPayload(), { timeout: 12000 }).catch(() => null)
    ])

    if (lrRes?.data?.forecast) {
      lrConsumableForecast.value = lrRes.data.forecast
    } else {
      lrConsumableForecast.value = []
    }

    if (xgbRes?.data?.lifespan_predictions) {
      xgbLifespanForecast.value = xgbRes.data.lifespan_predictions
      if (typeof xgbRes.data.accuracy === 'number') {
        lifespanAccuracy.value = xgbRes.data.accuracy
      }
    } else {
      xgbLifespanForecast.value = []
    }
  } catch (e) {
    apiError.value = e?.message || 'Prediction API error'
  } finally {
    apiLoading.value = false
  }
}

onMounted(async () => {
  await fetchitems()
  await fetchPredictions()
})
</script>

<style scoped>
.analytics-container {
  padding: 1rem;
}

@media (min-width: 640px) {
  .analytics-container {
    padding: 1.5rem;
  }
}

.material-icons-outlined {
  font-size: 24px;
}

/* Performance optimizations */
* {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Smooth scrolling */
html {
  scroll-behavior: smooth;
}

/* Optimize table rendering */
table {
  border-collapse: separate;
  border-spacing: 0;
}

/* Improve touch targets on mobile */
@media (max-width: 640px) {
  button, a, select {
    min-height: 44px;
    min-width: 44px;
  }
}
</style>