<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 sm:p-6 md:p-8">
    <!-- Enhanced Header Section -->
    <div class="relative overflow-hidden bg-gradient-to-r from-green-600 via-green-700 to-green-600 rounded-xl shadow-xl mb-6">
      <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
      <div class="relative px-6 py-8 sm:px-8 sm:py-10">
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
          <div class="flex items-center gap-4">
            <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl shadow-lg">
              <span class="material-icons-outlined text-4xl text-white">analytics</span>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 tracking-tight">Item Lifespan Analytics</h1>
              <p class="text-green-100 text-base sm:text-lg">Predictive Analysis for All Items Lifespan & Supply Management</p>
            </div>
          </div>
          <div class="flex flex-wrap gap-3 items-center">
            <button 
              @click="openGlobalRestock"
              class="btn-primary-enhanced flex items-center gap-2 shadow-lg"
            >
              <span class="material-icons-outlined text-lg">inventory_2</span>
              <span>Restock</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Low Stock Notification Alert -->
    <div v-if="lowStockItems.length > 0 && !dismissLowStockAlert" class="mb-6 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-600 dark:border-red-600 rounded-xl p-6 shadow-lg">
      <div class="flex items-start gap-4">
        <div class="flex-shrink-0 w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
          <span class="material-icons-outlined text-red-600 dark:text-red-400 text-2xl">warning</span>
        </div>
        <div class="flex-1">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
              Low Stock Alert
              <span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-900 dark:text-red-300 text-base rounded-full font-medium">
                {{ lowStockItems.length }} item{{ lowStockItems.length !== 1 ? 's' : '' }} need restocking
              </span>
            </h3>
            <button 
              @click="dismissLowStockAlert = true"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              aria-label="Dismiss"
            >
              <span class="material-icons-outlined text-xl">close</span>
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="item in lowStockItems.slice(0, 5)" :key="item.name" class="flex items-center gap-3 p-2 bg-white/50 dark:bg-gray-800/50 rounded">
              <div :class="item.stockStatusClass" class="w-3 h-3 rounded-full flex-shrink-0"></div>
              <div class="flex-1">
                <p class="text-base font-medium text-gray-900 dark:text-white">{{ item.name }}</p>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                  {{ item.currentStock }} remaining • {{ item.daysUntilEmpty }} until empty
                </p>
              </div>
              <span :class="item.recommendationClass" class="text-sm font-medium px-2 py-1 rounded">
                {{ item.recommendation }}
              </span>
            </div>
            <div v-if="lowStockItems.length > 5" class="text-base text-gray-700 dark:text-gray-300 pt-2">
              + {{ lowStockItems.length - 5 }} more item{{ lowStockItems.length - 5 !== 1 ? 's' : '' }} need restocking
            </div>
            <div class="flex gap-2 pt-2">
              <button 
                @click="openGlobalRestock"
                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-base font-medium flex items-center gap-2"
              >
                <span class="material-icons-outlined text-base">inventory_2</span>
                Restock Items
              </button>
              <router-link 
                :to="{ name: 'UsageOverview' }"
                class="px-4 py-2 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-700 text-red-700 dark:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-base font-medium flex items-center gap-2"
              >
                <span class="material-icons-outlined text-base">visibility</span>
                View Details
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Supply Snapshot (Quantity Only) -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 overflow-hidden mb-8">
      <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">inventory_2</span>
            <h2 class="text-xl font-bold text-white">Supply (Quantity)</h2>
            <span class="px-3 py-1 bg-white/20 backdrop-blur-sm text-white text-sm rounded-full font-semibold">Snapshot</span>
          </div>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700 border-b-2 border-gray-300 dark:border-gray-600">
              <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">ITEM NAME</th>
              <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">QUANTITY</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr 
              v-for="c in consumableQuantities" 
              :key="c.name"
              class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
            >
              <td class="px-6 py-4 font-semibold text-base text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600">{{ c.name }}</td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-base font-bold bg-green-100 text-green-900">
                  {{ c.quantity }}
                </span>
              </td>
            </tr>
            <tr v-if="consumableQuantities.length === 0">
              <td colspan="2" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center">
                  <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                    <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">inventory_2</span>
                  </div>
                  <p class="text-base text-gray-700 dark:text-gray-300">No supply items found</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  <!-- Ending Lifespan Modal -->
  <div v-if="showEndingLifespanModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="showEndingLifespanModal = false">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-6xl w-full max-h-[90vh] overflow-hidden animate-modalSlideIn">
      <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-5 border-b border-red-800">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-2xl">schedule</span>
            </div>
            <div>
              <h3 class="text-xl font-bold text-white">Items Ending Lifespan Soon</h3>
              <p class="text-red-100 text-sm mt-1">
                {{ endingSoonItems.length }} item{{ endingSoonItems.length !== 1 ? 's' : '' }} with ≤ 30 days remaining
              </p>
            </div>
          </div>
          <button @click="showEndingLifespanModal = false" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
      </div>
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-100px)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[800px]">
            <thead>
              <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700 border-b-2 border-gray-300 dark:border-gray-600">
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Item Name</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Category</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Remaining Days</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Remaining Years</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">End Date</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Acquired</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="i in endingSoonItems" 
                :key="i.name + i.lifespanEndDate"
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-red-500"
              >
                <td class="px-6 py-4 font-semibold text-base text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600">{{ i.name }}</td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span :class="i.categoryClass" class="px-2 py-1 rounded-full text-sm font-semibold">
                    {{ i.category }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="font-bold text-base" :class="{
                    'text-red-600 dark:text-red-400': i.remainingLifespan <= 15,
                    'text-orange-600 dark:text-orange-400': i.remainingLifespan > 15 && i.remainingLifespan <= 30
                  }">
                    {{ i.remainingLifespan }} days
                  </span>
                </td>
                <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium border-r border-gray-300 dark:border-gray-600">
                  {{ i.remainingYears != null ? i.remainingYears.toFixed(2) : 'N/A' }} years
                </td>
                <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium border-r border-gray-300 dark:border-gray-600">{{ i.lifespanEndDate }}</td>
                <td class="px-6 py-4 text-base text-gray-700 dark:text-gray-300 border-r border-gray-300 dark:border-gray-600">{{ i.acquisitionDate }}</td>
                <td class="px-6 py-4">
                  <span class="px-4 py-2 rounded-full text-sm sm:text-base font-bold whitespace-nowrap inline-block min-w-[100px] text-center" :class="{
                    'bg-red-600 text-white dark:bg-red-700 dark:text-white shadow-md': i.conditionStatus === 'Disposal',
                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': i.remainingLifespan <= 15 && i.conditionStatus !== 'Disposal',
                    'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': i.remainingLifespan > 15 && i.remainingLifespan <= 30 && i.conditionStatus !== 'Disposal'
                  }">
                    {{ i.conditionStatus === 'Disposal' ? 'DISPOSE' : 'For Checking' }}
                  </span>
                </td>
              </tr>
              <tr v-if="endingSoonItems.length === 0">
                <td colspan="7" class="px-6 py-12 text-center">
                  <div class="flex flex-col items-center">
                    <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                      <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">check_circle</span>
                    </div>
                    <p class="text-gray-900 dark:text-white font-semibold">No items predicted to end within 30 days.</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">All items have sufficient remaining lifespan.</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Accuracy Modal -->
  <div v-if="showAccuracyModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="showAccuracyModal = false">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-xl w-full overflow-hidden animate-modalSlideIn">
      <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 border-b border-blue-800">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-2xl">accuracy</span>
            </div>
            <h3 class="text-xl font-bold text-white">Prediction Accuracy</h3>
          </div>
          <button @click="showAccuracyModal = false" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
      </div>
      <div class="p-6">
        <div class="space-y-4">
          <div class="p-4 bg-blue-900/20 dark:bg-blue-900/20 rounded-lg border border-blue-700 dark:border-blue-700">
            <p class="text-gray-900 dark:text-white font-semibold">Current XGBoost accuracy: <span class="text-blue-400 dark:text-blue-400 text-xl font-bold">{{ lifespanAccuracy }}%</span></p>
          </div>
          <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg border-2 border-gray-300 dark:border-gray-600">
            <p class="text-gray-900 dark:text-white font-semibold">Predictions generated: <span class="text-gray-900 dark:text-white text-xl font-bold">{{ xgbLifespanForecast.length }}</span></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Lifespan Analysis Modal -->
  <div v-if="showLifespanModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-modalFadeIn" @click.self="showLifespanModal = false">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 max-w-6xl w-full max-h-[90vh] overflow-hidden animate-modalSlideIn">
      <div class="bg-gradient-to-r from-yellow-600 to-yellow-700 px-6 py-5 border-b border-yellow-800">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 backdrop-blur-sm rounded-lg">
              <span class="material-icons-outlined text-white text-2xl">timeline</span>
            </div>
            <div>
              <h3 class="text-xl font-bold text-white">Average Lifespan Analysis</h3>
              <p class="text-yellow-100 text-sm mt-1">
                Comprehensive analysis of all items' expected lifespan
              </p>
            </div>
          </div>
          <button @click="showLifespanModal = false" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-1.5 transition-colors">
            <span class="material-icons-outlined">close</span>
          </button>
        </div>
      </div>
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-100px)]">

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border-2 border-gray-300 dark:border-gray-600 p-5 overflow-hidden relative group">
            <div class="absolute top-0 right-0 w-20 h-20 bg-yellow-500/10 rounded-bl-full"></div>
            <div class="relative">
              <div class="flex items-center gap-3 mb-3">
                <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <span class="material-icons-outlined text-yellow-400 dark:text-yellow-400">timeline</span>
                </div>
                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Average Lifespan</h4>
              </div>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mb-1">{{ averageLifespan }} days</p>
              <p class="text-sm text-gray-700 dark:text-gray-300">{{ Math.round(averageLifespan / 365.25 * 10) / 10 }} years</p>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border-2 border-gray-300 dark:border-gray-600 p-5 overflow-hidden relative group">
            <div class="absolute top-0 right-0 w-20 h-20 bg-blue-500/10 rounded-bl-full"></div>
            <div class="relative">
              <div class="flex items-center gap-3 mb-3">
                <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <span class="material-icons-outlined text-blue-400 dark:text-blue-400">inventory</span>
                </div>
                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Total Items</h4>
              </div>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mb-1">{{ allItemsLifespan.length }}</p>
              <p class="text-sm text-gray-700 dark:text-gray-300">Items analyzed</p>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border-2 border-gray-300 dark:border-gray-600 p-5 overflow-hidden relative group">
            <div class="absolute top-0 right-0 w-20 h-20 bg-green-500/10 rounded-bl-full"></div>
            <div class="relative">
              <div class="flex items-center gap-3 mb-3">
                <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <span class="material-icons-outlined text-green-400 dark:text-green-400">accuracy</span>
                </div>
                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Prediction Accuracy</h4>
              </div>
              <p class="text-3xl font-bold text-gray-900 dark:text-white mb-1">{{ lifespanAccuracy }}%</p>
              <p class="text-sm text-gray-700 dark:text-gray-300">CatBoost model</p>
            </div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <!-- Chart 1: Average Lifespan by Category (Bar Chart) -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 p-5">
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">bar_chart</span>
              <h4 class="text-lg font-bold text-gray-900 dark:text-white">Average Lifespan by Category</h4>
            </div>
            <div class="h-64">
              <Bar v-if="categoryLifespanChartData" :data="categoryLifespanChartData" :options="categoryChartOptions" />
            </div>
          </div>

          <!-- Chart 2: Items by Status (Doughnut/Pie-like visualization using Bar) -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 p-5">
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons-outlined text-blue-400 dark:text-blue-400 text-xl">pie_chart</span>
              <h4 class="text-lg font-bold text-gray-900 dark:text-white">Items by Status</h4>
            </div>
            <div class="h-64">
              <Bar v-if="statusDistributionChartData" :data="statusDistributionChartData" :options="statusChartOptions" />
            </div>
          </div>

          <!-- Chart 3: Lifespan Distribution -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 p-5">
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons-outlined text-purple-400 dark:text-purple-400 text-xl">show_chart</span>
              <h4 class="text-lg font-bold text-gray-900 dark:text-white">Lifespan Distribution</h4>
            </div>
            <div class="h-64">
              <Bar v-if="lifespanDistributionChartData" :data="lifespanDistributionChartData" :options="distributionChartOptions" />
            </div>
          </div>

          <!-- Chart 4: Remaining Lifespan Trends -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 p-5">
            <div class="flex items-center gap-3 mb-4">
              <span class="material-icons-outlined text-orange-400 dark:text-orange-400 text-xl">trending_down</span>
              <h4 class="text-lg font-bold text-gray-900 dark:text-white">Remaining Lifespan Overview</h4>
            </div>
            <div class="h-64">
              <Line v-if="remainingLifespanChartData" :data="remainingLifespanChartData" :options="remainingChartOptions" />
            </div>
          </div>
        </div>

        <!-- Category Breakdown (Data Table) -->
        <div class="mb-6">
          <div class="flex items-center gap-3 mb-4">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">category</span>
            <h4 class="text-lg font-bold text-gray-900 dark:text-white">Lifespan by Category - Detailed</h4>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="(categoryData, category) in lifespanByCategory" :key="category" 
                 class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg border-2 border-gray-300 dark:border-gray-600 p-5 hover:shadow-lg transition-shadow">
              <div class="flex items-center justify-between mb-3">
                <h5 class="font-bold text-gray-900 dark:text-white">{{ category || 'Unknown' }}</h5>
                <span class="px-3 py-1 bg-green-900 dark:bg-green-900 text-green-300 dark:text-green-300 text-xs rounded-full font-semibold">
                  {{ categoryData.count }} items
                </span>
              </div>
              <div class="space-y-2">
                <div class="flex justify-between text-sm p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Avg. Lifespan:</span>
                  <span class="font-bold text-gray-900 dark:text-white">{{ categoryData.avgLifespan }} days</span>
                </div>
                <div class="flex justify-between text-sm p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Shortest:</span>
                  <span class="font-bold text-gray-900 dark:text-white">{{ categoryData.minLifespan }} days</span>
                </div>
                <div class="flex justify-between text-sm p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <span class="text-gray-600 dark:text-gray-400 font-medium">Longest:</span>
                  <span class="font-bold text-gray-900 dark:text-white">{{ categoryData.maxLifespan }} days</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Items Table -->
        <div>
          <div class="flex items-center gap-3 mb-4">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-xl">list</span>
            <h4 class="text-lg font-bold text-gray-900 dark:text-white">Lifespan Details</h4>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700 sticky top-0 border-b-2 border-gray-300 dark:border-gray-600">
                <tr>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">PROPERTY CODE</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Category</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Expected Lifespan</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Remaining</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Status</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Acquisition Date</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr 
                  v-for="item in allItemsLifespan.slice(0, 50)" 
                  :key="item.name + item.acquisitionDate"
                  class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
                >
                  <td class="px-6 py-4 font-semibold text-base text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600">{{ item.pac || 'N/A' }}</td>
                  <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                    <span :class="item.categoryClass" class="px-2 py-1 rounded-full text-sm font-semibold">
                      {{ item.category }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium border-r border-gray-300 dark:border-gray-600">{{ item.expectedLifespan }} days</td>
                  <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                    <span class="text-base font-bold" :class="item.remainingLifespan <= 30 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                      {{ item.remainingLifespan }} days
                    </span>
                  </td>
                  <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                    <span :class="item.statusClass" class="px-3 py-1.5 rounded-full text-sm font-bold">
                      {{ item.remainingLifespan <= 30 ? 'URGENT' : item.remainingLifespan <= 90 ? 'MONITOR' : 'GOOD' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-base text-gray-700 dark:text-gray-300 font-medium">{{ item.acquisitionDate }}</td>
                </tr>
                <tr v-if="allItemsLifespan.length === 0">
                  <td colspan="6" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center">
                      <div class="inline-block p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                        <span class="material-icons-outlined text-6xl text-gray-600 dark:text-gray-400">inventory_2</span>
                      </div>
                      <p class="text-base text-gray-900 dark:text-white font-semibold">No items with lifespan predictions available</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-if="allItemsLifespan.length > 50" class="mt-4 text-center text-sm text-gray-600 dark:text-gray-400 font-semibold p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
              Showing 50 of {{ allItemsLifespan.length }} items
            </div>
          </div>
        </div>

        <!-- Update Schedule Info -->
        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
          <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 font-semibold">
            <span class="material-icons-outlined text-green-400 dark:text-green-400 text-base">update</span>
            <span>{{ lifespanScheduleInfo }} • Next update: {{ nextLifespanCalculation }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
    

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
      <!-- Items Ending Lifespan Soon -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-gray-300 dark:border-gray-600 p-6 overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-red-500/10 rounded-bl-full"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex-1">
            <p class="text-base font-medium text-gray-700 dark:text-gray-300 mb-2">Ending Lifespan Soon</p>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">
              <span v-if="apiLoading" class="inline-block w-12 h-8 bg-gray-50 dark:bg-gray-700 rounded animate-pulse"></span>
              <span v-else>{{ endingLifespanCount }} Items</span>
            </h3>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">All item types</p>
            <button @click="showEndingLifespanModal = true" class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg text-base w-36 hover:from-red-700 hover:to-red-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2">
              <span class="material-icons-outlined text-base">visibility</span>
              View Details
            </button>
          </div>
          <div class="p-4 bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
            <span class="material-icons-outlined text-white text-3xl">schedule</span>
          </div>
        </div>
      </div>

      <!-- Supply Low Stock -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-gray-300 dark:border-gray-600 p-6 overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-green-500/10 rounded-bl-full"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex-1">
            <p class="text-base font-medium text-gray-700 dark:text-gray-300 mb-2">Supply Low Stock</p>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">{{ consumableLowStock }} items</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">Need restocking</p>
            <router-link :to="{ name: 'UsageOverview' }" class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg text-base w-36 hover:from-green-700 hover:to-green-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2">
              <span class="material-icons-outlined text-base">visibility</span>
              View
            </router-link>
          </div>
          <div class="p-4 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
            <span class="material-icons-outlined text-white text-3xl">inventory</span>
          </div>
        </div>
      </div>

      <!-- Average Lifespan All Items -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md dark:shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-gray-300 dark:border-gray-600 p-6 overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-yellow-500/10 rounded-bl-full"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex-1">
            <p class="text-base font-medium text-gray-700 dark:text-gray-300 mb-2">Avg. Lifespan</p>
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-1">{{ averageLifespan }} days</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">All item types</p>
            <button @click="showLifespanModal = true" class="px-4 py-2 bg-gradient-to-r from-yellow-600 to-yellow-700 text-white rounded-lg text-base w-36 hover:from-yellow-700 hover:to-yellow-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2">
              <span class="material-icons-outlined text-base">timeline</span>
              View Analysis
            </button>
          </div>
          <div class="p-4 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg group-hover:scale-110 transition-transform">
            <span class="material-icons-outlined text-white text-3xl">timeline</span>
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
        <!-- Equipment Lifespan Projections -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-2 border-gray-300 dark:border-gray-600">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Expected Lifespan (Based on Acquisition Date)</h3>
          <div class="space-y-4">
            <div v-for="item in equipmentLifespanItems.slice(0, 5)" :key="item.name" class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-base text-gray-900 dark:text-white">{{ item.name }}</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ item.expectedLifespan }} days</span>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ item.remainingLifespan }} days remaining</p>
              </div>
            </div>
            <div v-if="equipmentLifespanItems.length === 0" class="text-center py-4 text-base text-gray-700 dark:text-gray-300">
              No equipment items with lifespan predictions available
            </div>
          </div>
        </div>

        <!-- Consumption Rate Analysis -->
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border-2 border-gray-300 dark:border-gray-600">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Consumption Rate Analysis</h3>
          <div class="space-y-4">
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-base text-gray-900 dark:text-white">Daily Usage Rate</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">2.3</span>
                <p class="text-sm text-gray-700 dark:text-gray-300">units per day</p>
              </div>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="font-medium text-base text-gray-900 dark:text-white">Peak Usage Days</span>
              <div class="text-right">
                <span class="text-2xl font-bold text-orange-600 dark:text-orange-400">Mon-Fri</span>
                <p class="text-sm text-gray-700 dark:text-gray-300">weekdays</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Predictive Analytics Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

    </div>

    <!-- All Items Lifespan Management Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 overflow-hidden mb-8">
      <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 border-b border-purple-800">
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">science</span>
            <h2 class="text-xl font-bold text-white">All Items Lifespan Management</h2>
          </div>
        </div>
      </div>
      <div class="p-6">
      
        <!-- Predictions Summary Card -->
        <div v-if="lifespanPredictions.length > 0" class="mb-6 p-6 bg-blue-900/20 dark:bg-blue-900/20 rounded-xl border-2 border-blue-700 dark:border-blue-700 shadow-md">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div class="text-center">
            <div class="text-2xl font-bold text-blue-400 dark:text-blue-400">{{ lifespanPredictions.length }}</div>
            <div class="text-base text-gray-700 dark:text-gray-300">Total Predictions</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ allItemsLifespan.length }}</div>
            <div class="text-base text-gray-700 dark:text-gray-300">Items Displayed</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
              {{ lifespanPredictions.filter(p => p.remaining_years != null && p.remaining_years <= 0.082).length }}
            </div>
            <div class="text-base text-gray-700 dark:text-gray-300">Ending Soon (≤30 days)</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
              {{ Math.round(lifespanPredictions.reduce((sum, p) => sum + (p.remaining_years || 0), 0) / lifespanPredictions.length * 365) }}
            </div>
            <div class="text-base text-gray-700 dark:text-gray-300">Avg. Remaining Days</div>
          </div>
        </div>
        
          <!-- View All Predictions Button -->
          <div class="mt-4 pt-4 border-t border-blue-700 dark:border-blue-700">
            <button 
              @click="showAllPredictions = !showAllPredictions"
              class="w-full px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center justify-center gap-2"
            >
              <span class="material-icons-outlined text-base">{{ showAllPredictions ? 'expand_less' : 'expand_more' }}</span>
              {{ showAllPredictions ? 'Hide' : 'Show' }} All {{ lifespanPredictions.length }} Predictions
            </button>
          </div>
        </div>
      
        <!-- All Predictions Detail View -->
        <div v-if="showAllPredictions && lifespanPredictions.length > 0" class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border-2 border-gray-300 dark:border-gray-600 overflow-hidden">
          <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4 border-b border-purple-800">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
              <span class="material-icons-outlined text-xl">list</span>
              All Predictions Details
            </h3>
          </div>
          <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
              <thead class="sticky top-0 bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700 z-10">
                <tr class="border-b-2 border-gray-300 dark:border-gray-600">
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Item ID</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Item Name</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Description</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Remaining Years</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Remaining Days</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">Lifespan Estimate</th>
                  <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr 
                  v-for="(pred, index) in sortedLifespanPredictions" 
                  :key="pred.item_id || index"
                  class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-purple-500"
                >
                  <td class="px-6 py-4 font-semibold text-base text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600">{{ pred.item_id }}</td>
                  <td class="px-6 py-4 font-medium text-base text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600">
                    {{ items.find(i => i.id === pred.item_id)?.unit || 'Unknown' }}
                  </td>
                  <td class="px-6 py-4 text-base text-gray-700 dark:text-gray-300 border-r border-gray-300 dark:border-gray-600 max-w-xs truncate" :title="items.find(i => i.id === pred.item_id)?.description || 'N/A'">
                    {{ items.find(i => i.id === pred.item_id)?.description || 'N/A' }}
                  </td>
                  <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                    <span class="font-bold text-base" :class="{
                      'text-red-600 dark:text-red-400': pred.remaining_years <= 0.082,
                      'text-orange-600 dark:text-orange-400': pred.remaining_years > 0.082 && pred.remaining_years <= 0.164,
                      'text-yellow-600 dark:text-yellow-400': pred.remaining_years > 0.164 && pred.remaining_years <= 0.5,
                      'text-green-600 dark:text-green-400': pred.remaining_years > 0.5
                    }">
                      {{ pred.remaining_years != null ? pred.remaining_years.toFixed(2) : 'N/A' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium border-r border-gray-300 dark:border-gray-600">
                    {{ pred.remaining_years != null ? Math.round(pred.remaining_years * 365) : 'N/A' }}
                  </td>
                  <td class="px-6 py-4 text-base text-gray-900 dark:text-white font-medium border-r border-gray-300 dark:border-gray-600">
                    {{ pred.lifespan_estimate != null ? pred.lifespan_estimate.toFixed(2) : 'N/A' }}
                  </td>
                  <td class="px-6 py-4">
                    <span 
                      v-if="pred.remaining_years != null || (() => { const item = items.find(i => i.id === pred.item_id); return item?.condition_status === 'Disposal'; })()" 
                      class="px-4 py-2 rounded-full text-sm sm:text-base font-bold whitespace-nowrap inline-block min-w-[100px] text-center" 
                      :class="{
                        'bg-red-600 text-white dark:bg-red-700 dark:text-white shadow-md': (() => {
                          const item = items.find(i => i.id === pred.item_id);
                          return item?.condition_status === 'Disposal';
                        })(),
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': pred.remaining_years != null && pred.remaining_years <= 0.082 && (() => {
                          const item = items.find(i => i.id === pred.item_id);
                          return item?.condition_status !== 'Disposal';
                        })(),
                        'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': pred.remaining_years != null && pred.remaining_years > 0.082 && pred.remaining_years <= 0.164 && (() => {
                          const item = items.find(i => i.id === pred.item_id);
                          return item?.condition_status !== 'Disposal';
                        })(),
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': pred.remaining_years != null && pred.remaining_years > 0.164 && pred.remaining_years <= 0.5 && (() => {
                          const item = items.find(i => i.id === pred.item_id);
                          return item?.condition_status !== 'Disposal';
                        })(),
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': pred.remaining_years != null && pred.remaining_years > 0.5 && (() => {
                          const item = items.find(i => i.id === pred.item_id);
                          return item?.condition_status !== 'Disposal';
                        })()
                      }"
                    >
                      {{
                        (() => {
                          const item = items.find(i => i.id === pred.item_id);
                          // Priority 1: Check condition_status first - if Disposal, always show DISPOSE
                          if (item?.condition_status === 'Disposal') {
                            return 'DISPOSE';
                          }
                          // Priority 2: Check remaining_years only if condition_status is not Disposal
                          if (pred.remaining_years == null) return 'N/A';
                          if (pred.remaining_years <= 0.082) return 'For Checking';
                          if (pred.remaining_years <= 0.164) return 'Soon';
                          if (pred.remaining_years <= 0.5) return 'Monitor';
                          return 'Good';
                        })()
                      }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      
        <!-- Error message when Python API fails -->
        <div v-if="apiError && (!lifespanPredictions || lifespanPredictions.length === 0)" 
             class="mb-4 p-6 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-600 dark:border-red-600 rounded-xl shadow-md">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-red-400 dark:text-red-400 text-3xl">error</span>
            <div>
              <p class="text-base font-bold text-red-600 dark:text-red-400">Python API Error</p>
              <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ apiError }}</p>
              <p class="text-sm text-red-700 dark:text-red-300 mt-1">Please ensure the Python API server is running at {{ PY_API_BASE }}</p>
            </div>
          </div>
        </div>
        
        <!-- Empty state when no predictions available -->
        <div v-if="!apiLoading && !apiError && (!lifespanPredictions || lifespanPredictions.length === 0)" 
             class="mb-4 p-6 bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-600 dark:border-yellow-600 rounded-xl shadow-md">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-yellow-600 dark:text-yellow-400 text-3xl">warning</span>
            <div>
              <p class="text-base font-bold text-yellow-700 dark:text-yellow-400">No Predictions Available</p>
              <p class="text-sm text-yellow-800 dark:text-yellow-300 mt-1">Python API did not return any lifespan predictions. Waiting for API response...</p>
            </div>
          </div>
        </div>
        
        <!-- Loading state -->
        <div v-if="apiLoading" class="mb-4 p-6 bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-600 dark:border-blue-600 rounded-xl shadow-md">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-blue-600 dark:text-blue-400 text-3xl animate-spin">sync</span>
            <p class="text-base font-semibold text-blue-700 dark:text-blue-400">Loading predictions from Python API...</p>
          </div>
        </div>
      </div>
    </div>

      <!-- Supply Management Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 border-b border-green-800">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-icons-outlined text-white text-2xl">inventory</span>
            <h2 class="text-xl font-bold text-white">Supply Management</h2>
            <span class="px-3 py-1 bg-white/20 backdrop-blur-sm text-white text-sm rounded-full font-semibold">Stock-Based Management</span>
          </div>
        </div>
      </div>
      
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1200px]">
            <thead>
              <tr class="bg-gradient-to-r from-gray-200 via-gray-200 to-gray-200 dark:from-gray-700 dark:via-gray-700 dark:to-gray-700 border-b-2 border-gray-300 dark:border-gray-600">
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">ITEM NAME</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CATEGORY</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CURRENT STOCK</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CONSUMPTION RATE</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">DAYS UNTIL EMPTY</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">ML RECOMMENDATION</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">NEXT 3 MONTHS</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">RESTOCK BY</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider border-r border-gray-300 dark:border-gray-600">CONFIDENCE</th>
                <th class="px-6 py-4 text-left text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr 
                v-for="item in paginatedConsumables" 
                :key="item.name"
                class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 border-l-4 border-transparent hover:border-green-500"
              >
                <td class="px-6 py-4 font-semibold text-base text-gray-900 dark:text-white border-r border-gray-300 dark:border-gray-600">{{ item.name }}</td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span :class="item.categoryClass" class="px-3 py-1 rounded-full text-sm font-semibold">
                    {{ item.category }}
                  </span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div class="flex items-center gap-3">
                    <span :class="item.stockStatusClass" class="w-4 h-4 rounded-full"></span>
                    <div>
                      <div class="font-bold text-base text-gray-900 dark:text-white">{{ item.currentStock }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div>
                    <div class="font-semibold text-base text-gray-900 dark:text-white">{{ item.consumptionRate }}</div>
                    <div class="text-sm text-gray-700 dark:text-gray-300">per day</div>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div>
                    <div class="font-semibold text-base text-gray-900 dark:text-white">{{ item.daysUntilEmpty }}</div>
                    <div class="text-sm text-gray-700 dark:text-gray-300">until empty</div>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div class="flex flex-col gap-1">
                    <span :class="item.recommendationClass" class="text-base font-semibold">{{ item.recommendation }}</span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ item.recommendedQuantity }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="text-base font-medium text-gray-900 dark:text-white">{{ item.nextThreeMonths }}</span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <span class="text-base font-medium text-gray-900 dark:text-white">{{ item.restockBy }}</span>
                </td>
                <td class="px-6 py-4 border-r border-gray-300 dark:border-gray-600">
                  <div class="flex items-center gap-2">
                    <div class="w-16 h-2 bg-gray-50 dark:bg-gray-700 rounded-full">
                      <div :class="item.confidenceClass" class="h-2 rounded-full" :style="{ width: item.confidence + '%' }"></div>
                    </div>
                    <span class="text-base font-bold text-gray-900 dark:text-white">{{ item.confidence }}%</span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <button 
                    @click="openRestock(item)"
                    class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all shadow-md hover:shadow-lg font-semibold text-base"
                  >
                    Restock
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      
        <div class="bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 px-6 py-4">
          <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex gap-3">
              <button @click="exportConsumablesCSV" class="px-4 py-2.5 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2 text-base">
                <span class="material-icons-outlined text-base">download</span>
                Export Excel
              </button>
              <button @click="generateSupplyReport" class="px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg font-semibold flex items-center gap-2 text-base">
                <span class="material-icons-outlined text-base">inventory</span>
                Generate Report
              </button>
            </div>
            <div class="flex gap-2">
              <button 
                @click="goPrev" 
                :disabled="currentConsumablePage === 1" 
                class="px-4 py-2 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md font-semibold"
              >
                <span class="material-icons-outlined text-base align-middle">chevron_left</span>
                Previous
              </button>
              <button 
                @click="goNext" 
                :disabled="currentConsumablePage === totalConsumablePages" 
                class="px-4 py-2 border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 hover:border-green-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow-md font-semibold"
              >
                Next
                <span class="material-icons-outlined text-base align-middle">chevron_right</span>
              </button>
            </div>
          </div>
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
          <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow-sm">
            <label class="block text-green-600 dark:text-green-400 font-medium mb-2">Select Supply Item</label>
            <select
              v-model="selectedRestockItemName"
              class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 mb-4"
            >
              <option v-for="opt in consumableSupplyItems" :key="opt.name" :value="opt.name">{{ opt.name }}</option>
            </select>
            <label class="block text-green-600 dark:text-green-400 font-medium mb-2">Quantity</label>
            <div>
              <input 
                v-model.number="restockAmount"
                type="number"
                class="w-full p-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
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
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import useItems from '../composables/useItems'
import useNotifications from '../composables/useNotifications'
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

// Fetch notifications for low stock alerts
const { notifications, fetchNotifications, refreshNotifications } = useNotifications()

// Flask API base URL (Python service)
const PY_API_BASE = import.meta.env.VITE_PY_API_BASE_URL || 'http://127.0.0.1:5000'

// Prediction state
const lrConsumableForecast = ref([]) // Linear Regression results for supply
const xgbLifespanForecast = ref([])  // XGBoost results for equipment lifespan
const lifespanPredictions = ref([])  // New lifespan predictions with remaining_years
const usageForecastData = ref([])    // Usage-based forecast data from /usage/forecast-data
const apiLoading = ref(false)
const apiError = ref(null)

// Sorted predictions: newest first, items without remaining days/years at the end
const sortedLifespanPredictions = computed(() => {
  if (!lifespanPredictions.value || lifespanPredictions.value.length === 0) return []
  
  // Separate items with and without remaining days/years
  const withRemaining = []
  const withoutRemaining = []
  
  lifespanPredictions.value.forEach(pred => {
    const hasRemaining = pred.remaining_years != null && !isNaN(pred.remaining_years) && pred.remaining_years > 0
    if (hasRemaining) {
      withRemaining.push(pred)
    } else {
      withoutRemaining.push(pred)
    }
  })
  
  // Sort items with remaining: newest first
  withRemaining.sort((a, b) => {
    const itemA = items.value.find(i => i.id === a.item_id)
    const itemB = items.value.find(i => i.id === b.item_id)
    
    // Try to sort by created_at (newest first)
    if (itemA?.created_at && itemB?.created_at) {
      const dateA = new Date(itemA.created_at)
      const dateB = new Date(itemB.created_at)
      return dateB - dateA // Descending order (newest first)
    }
    
    // If created_at not available, try updated_at
    if (itemA?.updated_at && itemB?.updated_at) {
      const dateA = new Date(itemA.updated_at)
      const dateB = new Date(itemB.updated_at)
      return dateB - dateA // Descending order (newest first)
    }
    
    // Fallback: sort by item_id (higher ID = newer item, assuming auto-increment)
    const idA = a.item_id || 0
    const idB = b.item_id || 0
    return idB - idA // Descending order (newest first)
  })
  
  // Sort items without remaining: newest first
  withoutRemaining.sort((a, b) => {
    const itemA = items.value.find(i => i.id === a.item_id)
    const itemB = items.value.find(i => i.id === b.item_id)
    
    // Try to sort by created_at (newest first)
    if (itemA?.created_at && itemB?.created_at) {
      const dateA = new Date(itemA.created_at)
      const dateB = new Date(itemB.created_at)
      return dateB - dateA // Descending order (newest first)
    }
    
    // If created_at not available, try updated_at
    if (itemA?.updated_at && itemB?.updated_at) {
      const dateA = new Date(itemA.updated_at)
      const dateB = new Date(itemB.updated_at)
      return dateB - dateA // Descending order (newest first)
    }
    
    // Fallback: sort by item_id (higher ID = newer item, assuming auto-increment)
    const idA = a.item_id || 0
    const idB = b.item_id || 0
    return idB - idA // Descending order (newest first)
  })
  
  // Return: items with remaining first, then items without remaining
  return [...withRemaining, ...withoutRemaining]
})
// Category helper: treat Supply category as supply
const isConsumableCategory = (category) => {
  const c = (category || '').toLowerCase()
  return c === 'supply' || 
         c.includes('supply') || 
         c === 'consumables' || 
         c.includes('consumable')
}

const showRestockModal = ref(false)
const showExpiringModal = ref(false)
const showAccuracyModal = ref(false)
const showLifespanModal = ref(false)
const showEndingLifespanModal = ref(false)
const showAllPredictions = ref(false)
const selectedRestockItem = ref(null)
const selectedRestockItemName = ref('')
const restockAmount = ref(0)
const dismissLowStockAlert = ref(false)

// Equipment-only Lifespan Data (exclude supply)
// NOTE: this is the ONLY declaration of allItemsLifespan
const allItemsLifespan = computed(() => {
  if (!items.value || items.value.length === 0) return []
  if (!lifespanPredictions.value || lifespanPredictions.value.length === 0) return []
  
  // ONLY use Python API predictions - filter out items without predictions
  return items.value
    .filter(item => !isConsumableCategory(item?.category))
    .map(item => {
      // Find CatBoost prediction from Python API for this item
      const catboostPred = lifespanPredictions.value.find(p => p.item_id === item.id)
      
      // If no prediction from Python API, exclude this item
      if (!catboostPred || catboostPred.remaining_years == null || isNaN(catboostPred.remaining_years)) {
        return null // Will be filtered out
      }
      
      const acquisitionDate = new Date(item.date_acquired)
      const today = new Date()
      const daysSinceAcquisition = Math.floor((today - acquisitionDate) / (1000 * 60 * 60 * 24))
      const yearsInUse = daysSinceAcquisition / 365.25
      
      // ONLY use CatBoost prediction from Python API (ml_api_server.py)
      const remainingYears = parseFloat(catboostPred.remaining_years)
      const remainingLifespanDays = Math.round(remainingYears * 365)
      
      // Calculate expected lifespan from CatBoost prediction
      let expectedLifespan
      if (catboostPred.lifespan_estimate != null && !isNaN(catboostPred.lifespan_estimate)) {
        expectedLifespan = Math.round(catboostPred.lifespan_estimate * 365)
      } else {
        expectedLifespan = Math.round((remainingYears + yearsInUse) * 365)
      }
      
      // Calculate lifespan end date
      // remainingYears is from TODAY, not from acquisition date
      // So end date = today + remainingYears
      const lifespanEndDate = new Date(today.getTime() + (remainingYears * 365.25 * 24 * 60 * 60 * 1000))
      
      // Get condition_status from item (comes from condition_number->condition_status)
      const conditionStatus = item.condition_status || null
      
      // Status classes and recommendations based on remaining years
      // If condition_status is "Disposal", override with disposal status
      let statusClass = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
      let lifespanClass = 'text-green-600 dark:text-green-400'
      let recommendation = 'Good condition'
      let recommendationClass = 'text-green-600 dark:text-green-400'
      
      // Check if item is marked for disposal first
      if (conditionStatus === 'Disposal') {
        statusClass = 'bg-red-600 text-white dark:bg-red-700 dark:text-white'
        lifespanClass = 'text-red-600 dark:text-red-400'
        recommendation = 'URGENT: Item marked for disposal - Dispose immediately'
        recommendationClass = 'text-red-600 dark:text-red-400'
      } else if (remainingYears <= 0.082) { // <= 30 days
        statusClass = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
        lifespanClass = 'text-red-600 dark:text-red-400'
        recommendation = 'URGENT: End of life reached - Replacement required'
        recommendationClass = 'text-red-600 dark:text-red-400'
      } else if (remainingYears <= 0.164) { // <= 60 days (approximately 2 months)
        statusClass = 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
        lifespanClass = 'text-orange-600 dark:text-orange-400'
        recommendation = 'Plan replacement soon'
        recommendationClass = 'text-orange-600 dark:text-orange-400'
      } else if (remainingYears <= 0.5) { // <= 6 months
        statusClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
        lifespanClass = 'text-yellow-600 dark:text-yellow-400'
        recommendation = 'Monitor closely'
        recommendationClass = 'text-yellow-600 dark:text-yellow-400'
      }
      
      // CatBoost predictions from Python API have highest confidence
      const confidence = 94
      
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
        remainingLifespan: remainingLifespanDays,
        remainingYears: parseFloat(remainingYears.toFixed(1)),
        predictionMethod: 'catboost_python_api', // Always Python API
        itemType: isConsumableCategory(item.category) ? 'Supply' : 'Non-Consumable',
        statusClass,
        lifespanEndDate: lifespanEndDate.toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' }),
        lifespanClass,
        daysUntilEnd: `${remainingLifespanDays} days`,
        recommendation,
        recommendationClass,
        confidence,
        confidenceClass: statusClass,
        unitValue: item.unit_value,
        pac: item.pac,
        location: item.location?.location || 'Unknown',
        conditionStatus: conditionStatus // Include condition_status for status display
      }
    })
    .filter(item => item !== null) // Remove items without Python API predictions
})

// Computed data based on real inventory
const endingSoonItems = computed(() => {
  return (allItemsLifespan.value || [])
    .filter(item => {
      // Filter items with remaining lifespan <= 30 days
      // Use remainingLifespan (in days) or calculate from remainingYears
      const remainingDays = item.remainingLifespan ?? (item.remainingYears ? item.remainingYears * 365 : 999)
      return remainingDays <= 30
    })
    .sort((a, b) => {
      const daysA = a.remainingLifespan ?? (a.remainingYears ? a.remainingYears * 365 : 999)
      const daysB = b.remainingLifespan ?? (b.remainingYears ? b.remainingYears * 365 : 999)
      return daysA - daysB
    })
})

const endingLifespanCount = computed(() => endingSoonItems.value.length)

// Calculate next lifespan calculation date (runs every 14 days: 1st and 15th of each month at 2:00 AM)
const nextLifespanCalculation = computed(() => {
  const now = new Date()
  const currentDay = now.getDate()
  const currentMonth = now.getMonth()
  const currentYear = now.getFullYear()
  
  // Calculate next scheduled date (1st or 15th of current or next month)
  let nextDate
  if (currentDay < 1) {
    // Before 1st, next is 1st of current month
    nextDate = new Date(currentYear, currentMonth, 1, 2, 0, 0)
  } else if (currentDay < 15) {
    // Between 1st and 15th, next is 15th of current month
    nextDate = new Date(currentYear, currentMonth, 15, 2, 0, 0)
  } else {
    // After 15th, next is 1st of next month
    nextDate = new Date(currentYear, currentMonth + 1, 1, 2, 0, 0)
  }
  
  // If next date is in the past (same day but past 2 AM), move to next scheduled date
  if (nextDate < now) {
    if (currentDay >= 15) {
      nextDate = new Date(currentYear, currentMonth + 1, 1, 2, 0, 0)
    } else {
      nextDate = new Date(currentYear, currentMonth, 15, 2, 0, 0)
    }
  }
  
  return nextDate.toLocaleDateString('en-PH', { 
    timeZone: 'Asia/Manila',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
})

const lifespanScheduleInfo = computed(() => {
  return `Updated every 14 days`
})

const consumableLowStock = computed(() => {
  if (!items.value || items.value.length === 0) return 0
  
  // Count items that are low stock by either:
  // 1. Quantity threshold (backend CheckLowStockJob uses < 50)
  // 2. Days until empty (≤14 days)
  const supplyItems = items.value.filter(item => isConsumableCategory(item?.category))
  
  return supplyItems.filter(item => {
    const quantity = Number(item.quantity || 0)
    const isLowByQuantity = quantity < 50 && quantity >= 0
    
    // Check days until empty from consumableSupplyItems (match by uuid or name)
    const consumableItem = consumableSupplyItems.value.find(c => 
      (c.uuid && item.uuid && c.uuid === item.uuid) ||
      (c.name && item.unit && c.name.toLowerCase() === item.unit.toLowerCase())
    )
    if (consumableItem) {
      const daysUntilEmpty = parseInt(consumableItem.daysUntilEmpty) || 999
      const isLowByDays = daysUntilEmpty <= 14
      return isLowByQuantity || isLowByDays
    }
    
    // If not found in consumableSupplyItems, use quantity threshold only
    return isLowByQuantity
  }).length
})

// Low stock items for notification display
// Includes items low by quantity (< 50) OR by days (≤14 days)
const lowStockItems = computed(() => {
  if (dismissLowStockAlert.value) return []
  if (!items.value || items.value.length === 0) return []
  
  // Get supply items that are low stock
  const supplyItems = items.value.filter(item => isConsumableCategory(item?.category))
  
  const lowStockItemsList = supplyItems.filter(item => {
    const quantity = Number(item.quantity || 0)
    const isLowByQuantity = quantity < 50 && quantity >= 0
    
    // Find matching consumableSupplyItems entry (match by uuid or name)
    const consumableItem = consumableSupplyItems.value.find(c => 
      (c.uuid && item.uuid && c.uuid === item.uuid) ||
      (c.name && item.unit && c.name.toLowerCase() === item.unit.toLowerCase())
    )
    if (consumableItem) {
      const daysUntilEmpty = parseInt(consumableItem.daysUntilEmpty) || 999
      const isLowByDays = daysUntilEmpty <= 14
      return isLowByQuantity || isLowByDays
    }
    
    // If not in consumableSupplyItems, use quantity threshold
    return isLowByQuantity
  }).map(item => {
    // Find or create consumableSupplyItems entry for this item (match by uuid or name)
    let consumableItem = consumableSupplyItems.value.find(c => 
      (c.uuid && item.uuid && c.uuid === item.uuid) ||
      (c.name && item.unit && c.name.toLowerCase() === item.unit.toLowerCase())
    )
    
    if (!consumableItem) {
      // Create a basic entry if not found
      const quantity = Number(item.quantity || 0)
      const daysUntilEmpty = quantity // Fallback: use quantity as days
      
      let stockStatusClass = 'bg-green-500'
      let recommendation = 'Good stock level'
      let recommendationClass = 'text-green-600'
      
      if (daysUntilEmpty <= 7 || quantity < 10) {
        stockStatusClass = 'bg-red-500'
        recommendation = 'URGENT: Restock immediately'
        recommendationClass = 'text-red-600'
      } else if (daysUntilEmpty <= 14 || quantity < 25) {
        stockStatusClass = 'bg-orange-500'
        recommendation = 'Order within 1 week'
        recommendationClass = 'text-orange-600'
      }
      
      consumableItem = {
        name: item.unit || 'Unknown Item',
        currentStock: `${quantity} units`,
        daysUntilEmpty: `${daysUntilEmpty} days`,
        stockStatusClass,
        recommendation,
        recommendationClass,
        uuid: item.uuid
      }
    }
    
    return consumableItem
  })
  
  // Sort by urgency: first by quantity (lower = more urgent), then by days
  return lowStockItemsList.sort((a, b) => {
    // Parse days for comparison
    const daysA = parseInt(a.daysUntilEmpty) || 999
    const daysB = parseInt(b.daysUntilEmpty) || 999
    
    // Parse quantity for comparison (from currentStock string or currentQuantityNum)
    const qtyA = a.currentQuantityNum || parseInt(a.currentStock) || 999
    const qtyB = b.currentQuantityNum || parseInt(b.currentStock) || 999
    
    // Sort by quantity first (lower quantity = more urgent), then by days
    if (qtyA !== qtyB) return qtyA - qtyB
    return daysA - daysB
  })
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

// Equipment Lifespan Items for the Expected Lifespan section (excludes supply items)
const equipmentLifespanItems = computed(() => {
  // Use allItemsLifespan which already filters out supply items and uses Python API predictions
  // Only include items with valid predictions (remainingLifespan > 0)
  return (allItemsLifespan.value || [])
    .filter(item => item.remainingLifespan != null && item.remainingLifespan >= 0 && item.expectedLifespan != null)
    .sort((a, b) => a.remainingLifespan - b.remainingLifespan) // Sort by remaining lifespan ascending (most urgent first)
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

// ML Model Insights - Calculate accuracy from actual forecast data
const mlModelInsights = computed(() => {
  // Calculate Supply Predictions accuracy from usage forecast data
  const supplyItemsWithForecast = consumableSupplyItems.value.filter(item => item.hasForecast)
  let supplyAccuracy = 0
  if (supplyItemsWithForecast.length > 0) {
    const totalConfidence = supplyItemsWithForecast.reduce((sum, item) => sum + (item.confidence || 0), 0)
    supplyAccuracy = Math.round((totalConfidence / supplyItemsWithForecast.length) * 10) / 10
  } else {
    supplyAccuracy = 0
  }
  
  // Calculate Maintenance Forecasts accuracy from lifespan predictions
  const equipmentItemsWithForecast = allItemsLifespan.value.filter(item => item.confidence != null)
  let maintenanceAccuracy = 0
  if (equipmentItemsWithForecast.length > 0) {
    const totalConfidence = equipmentItemsWithForecast.reduce((sum, item) => sum + (item.confidence || 0), 0)
    maintenanceAccuracy = Math.round((totalConfidence / equipmentItemsWithForecast.length) * 10) / 10
  } else {
    maintenanceAccuracy = lifespanAccuracy.value || 0
  }
  
  // Overall accuracy (weighted average)
  const totalItems = supplyItemsWithForecast.length + equipmentItemsWithForecast.length
  let overallAccuracy = 0
  if (totalItems > 0) {
    const totalConfidence = (supplyAccuracy * supplyItemsWithForecast.length) + (maintenanceAccuracy * equipmentItemsWithForecast.length)
    overallAccuracy = Math.round((totalConfidence / totalItems) * 10) / 10
  } else {
    overallAccuracy = 92.1 // Default if no data
  }
  
  return {
    overallAccuracy: overallAccuracy > 0 ? overallAccuracy : 92.1,
    supplyAccuracy: supplyAccuracy > 0 ? supplyAccuracy : 94.2,
    maintenanceAccuracy: maintenanceAccuracy > 0 ? maintenanceAccuracy : 91.8
  }
})

// Group items by category for analysis (Based on CatBoost Predictions)
// Uses allItemsLifespan which contains only items with valid CatBoost predictions from Python API
const lifespanByCategory = computed(() => {
  const categoryMap = {}
  
  allItemsLifespan.value.forEach(item => {
    const category = item.category || 'Unknown'
    
    if (!categoryMap[category]) {
      categoryMap[category] = {
        count: 0,
        totalLifespan: 0,
        lifespans: []
      }
    }
    
    categoryMap[category].count++
    categoryMap[category].totalLifespan += item.expectedLifespan
    categoryMap[category].lifespans.push(item.expectedLifespan)
  })
  
  // Calculate statistics for each category
  const result = {}
  Object.keys(categoryMap).forEach(category => {
    const data = categoryMap[category]
    const lifespans = data.lifespans.sort((a, b) => a - b)
    
    result[category] = {
      count: data.count,
      avgLifespan: Math.round(data.totalLifespan / data.count),
      minLifespan: lifespans[0] || 0,
      maxLifespan: lifespans[lifespans.length - 1] || 0
    }
  })
  
  return result
})

// Chart Data: Average Lifespan by Category (Based on CatBoost Predictions)
// Uses expectedLifespan from allItemsLifespan which comes from Python API CatBoost predictions
const categoryLifespanChartData = computed(() => {
  const categories = Object.keys(lifespanByCategory.value)
  if (categories.length === 0) return null
  
  return {
    labels: categories,
    datasets: [{
      label: 'Average Lifespan (days)',
      data: categories.map(cat => lifespanByCategory.value[cat].avgLifespan),
      backgroundColor: [
        'rgba(59, 130, 246, 0.8)',
        'rgba(139, 92, 246, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(236, 72, 153, 0.8)'
      ],
      borderColor: [
        'rgb(59, 130, 246)',
        'rgb(139, 92, 246)',
        'rgb(16, 185, 129)',
        'rgb(245, 158, 11)',
        'rgb(239, 68, 68)',
        'rgb(236, 72, 153)'
      ],
      borderWidth: 2,
      borderRadius: 4
    }]
  }
})

// Chart Data: Status Distribution (Based on CatBoost Predictions)
// Uses remainingLifespan from allItemsLifespan which comes from Python API CatBoost predictions
const statusDistributionChartData = computed(() => {
  if (allItemsLifespan.value.length === 0) return null
  
  const urgent = allItemsLifespan.value.filter(item => item.remainingLifespan <= 30).length
  const monitor = allItemsLifespan.value.filter(item => item.remainingLifespan > 30 && item.remainingLifespan <= 90).length
  const good = allItemsLifespan.value.filter(item => item.remainingLifespan > 90).length
  
  return {
    labels: ['URGENT (≤30 days)', 'MONITOR (31-90 days)', 'GOOD (>90 days)'],
    datasets: [{
      label: 'Number of Items',
      data: [urgent, monitor, good],
      backgroundColor: [
        'rgba(239, 68, 68, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(16, 185, 129, 0.8)'
      ],
      borderColor: [
        'rgb(239, 68, 68)',
        'rgb(245, 158, 11)',
        'rgb(16, 185, 129)'
      ],
      borderWidth: 2,
      borderRadius: 4
    }]
  }
})

// Chart Data: Lifespan Distribution (Based on CatBoost Predictions)
// Uses expectedLifespan from allItemsLifespan which comes from Python API CatBoost predictions
const lifespanDistributionChartData = computed(() => {
  if (allItemsLifespan.value.length === 0) return null
  
  const buckets = {
    '0-1 years': 0,
    '1-2 years': 0,
    '2-3 years': 0,
    '3-5 years': 0,
    '5+ years': 0
  }
  
  allItemsLifespan.value.forEach(item => {
    const years = item.expectedLifespan / 365.25
    if (years <= 1) buckets['0-1 years']++
    else if (years <= 2) buckets['1-2 years']++
    else if (years <= 3) buckets['2-3 years']++
    else if (years <= 5) buckets['3-5 years']++
    else buckets['5+ years']++
  })
  
  return {
    labels: Object.keys(buckets),
    datasets: [{
      label: 'Number of Items',
      data: Object.values(buckets),
      backgroundColor: [
        'rgba(239, 68, 68, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(251, 191, 36, 0.8)',
        'rgba(59, 130, 246, 0.8)',
        'rgba(16, 185, 129, 0.8)'
      ],
      borderColor: [
        'rgb(239, 68, 68)',
        'rgb(245, 158, 11)',
        'rgb(251, 191, 36)',
        'rgb(59, 130, 246)',
        'rgb(16, 185, 129)'
      ],
      borderWidth: 2,
      borderRadius: 4
    }]
  }
})

// Chart Data: Remaining Lifespan Overview (Based on CatBoost Predictions)
// Uses remainingLifespan from allItemsLifespan which comes from Python API CatBoost predictions
// Groups by category and shows average remaining lifespan per category
const remainingLifespanChartData = computed(() => {
  if (allItemsLifespan.value.length === 0) return null
  
  // Group items by category and calculate average remaining lifespan per category
  const categoryMap = {}
  
  allItemsLifespan.value.forEach(item => {
    const category = item.category || 'Unknown'
    
    if (!categoryMap[category]) {
      categoryMap[category] = {
        totalRemaining: 0,
        count: 0
      }
    }
    
    categoryMap[category].totalRemaining += item.remainingLifespan
    categoryMap[category].count++
  })
  
  // Calculate average remaining lifespan per category
  const categoryData = Object.keys(categoryMap).map(category => ({
    category,
    avgRemaining: Math.round(categoryMap[category].totalRemaining / categoryMap[category].count)
  }))
  
  // Sort by average remaining lifespan (ascending)
  categoryData.sort((a, b) => a.avgRemaining - b.avgRemaining)
  
  return {
    labels: categoryData.map(cat => cat.category),
    datasets: [{
      label: 'Average Remaining Lifespan (days)',
      data: categoryData.map(cat => cat.avgRemaining),
      borderColor: 'rgb(59, 130, 246)',
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      borderWidth: 2,
      fill: true,
      tension: 0.4,
      pointRadius: 4,
      pointBackgroundColor: 'rgb(59, 130, 246)'
    }]
  }
})

// Chart Options
const categoryChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          const days = context.parsed.y
          const years = (days / 365.25).toFixed(1)
          return `${days} days (${years} years)`
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return value + ' days'
        }
      }
    }
  }
})

const statusChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          return `${context.label}: ${context.parsed.y} items`
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1
      }
    }
  }
})

const distributionChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          return `${context.label}: ${context.parsed.y} items`
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1
      }
    }
  }
})

const remainingChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          const days = context.parsed.y
          const years = (days / 365.25).toFixed(2)
          return `Avg: ${days} days (${years} years) remaining`
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return value + ' days'
        }
      }
    },
    x: {
      ticks: {
        maxRotation: 45,
        minRotation: 45
      }
    }
  }
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

// Build procurement list from supply items with lowest days until empty (based on usage predictions)
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
  // Only show items with forecast data (prediction-based) and prioritize urgent items
  return (consumableSupplyItems.value || [])
    .filter(i => i.hasForecast) // Only items with prediction data
    .map(i => {
      const days = parseDays(i.daysUntilEmpty)
      const badge = toBadge(days)
      // Use recommendedQuantity which comes from forecast.predicted_usage (usage-based)
      // Parse recommendedQuantity to extract just the number
      const qtyMatch = String(i.recommendedQuantity || '').match(/(\d+)/)
      const recommendedQty = qtyMatch ? qtyMatch[1] + ' units' : i.recommendedQuantity
      return {
        name: i.name,
        daysUntilEmpty: i.daysUntilEmpty,
        recommendedQuantity: recommendedQty, // Based on forecast.predicted_usage from usage data
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
  
  // Map items with forecast data
  const itemsWithForecast = base.map(item => {
    const currentQuantity = item.quantity || 0

    // PRIORITY 1: Try to find usage forecast data from /usage/forecast-data (most accurate - based on actual usage)
    const usageForecast = usageForecastData.value.find(f => {
      return f.item_id != null && item.id != null && f.item_id === item.id
    })

    // PRIORITY 2: Try to find forecast from Flask API response - match by item_id first, then by name
    const forecast = lrConsumableForecast.value.find(f => {
      // Try matching by item_id if available
      if (f.item_id != null && item.id != null && f.item_id === item.id) {
        return true
      }
      // Fallback to name matching
      const forecastName = (f.name || '').toLowerCase().trim()
      const itemName = (item.unit || '').toLowerCase().trim()
      return forecastName === itemName || forecastName.includes(itemName) || itemName.includes(forecastName)
    })
    
    // Use usage-based forecast data if available (most accurate), otherwise fallback to Python API forecast
    let daysUntilEmpty
    let recommendedStock
    let consumptionRate
    let confidence
    let recommendedQuantity
    
    if (usageForecast) {
      // Use usage-based calculations from actual usage data (same source as Usage Overview)
      const forecastFeatures = usageForecast.forecast_features || {}
      const forecast = usageForecast.forecast || {} // Backend provides: forecast.predicted_usage
      const historicalData = usageForecast.historical_data || []
      
      // Calculate average usage per quarter from historical data
      const avgUsagePerQuarter = forecastFeatures.avg_usage_per_quarter || 0
      // Calculate average daily consumption rate from actual usage
      // Average usage per quarter / 90 days (approximately 3 months per quarter)
      const avgDailyUsage = avgUsagePerQuarter > 0 ? (avgUsagePerQuarter / 90) : 0
      
      // Use calculated consumption rate from actual usage
      if (avgDailyUsage > 0) {
        consumptionRate = `${avgDailyUsage.toFixed(2)} units/day`
      } else if (historicalData.length > 0) {
        // Calculate from historical data if avg not available
        const totalUsage = historicalData.reduce((sum, h) => sum + (h.usage || 0), 0)
        const totalDays = historicalData.length * 90 // Approximate days per quarter
        const calculatedRate = totalDays > 0 ? (totalUsage / totalDays) : 0
        consumptionRate = calculatedRate > 0 ? `${calculatedRate.toFixed(2)} units/day` : '1 unit/day'
      } else {
        consumptionRate = '1 unit/day'
      }
      
      // Calculate days until empty based on actual consumption rate
      const dailyRate = parseFloat(consumptionRate.split(' ')[0]) || 1
      daysUntilEmpty = dailyRate > 0 && currentQuantity > 0
        ? Math.max(0, Math.floor(currentQuantity / dailyRate))
        : (currentQuantity > 0 ? currentQuantity : 0)
      
      // Use forecast.predicted_usage from backend (EXACT same value as Usage Overview)
      const predictedUsage = forecast.predicted_usage || avgUsagePerQuarter || 0
      recommendedStock = Math.max(50, Math.round(predictedUsage * 1.2)) // 20% buffer
      recommendedQuantity = Math.round(predictedUsage)
      
      // Confidence from forecast (convert from 0-1 to 0-100 if needed)
      if (forecast.confidence != null) {
        confidence = forecast.confidence <= 1 
          ? Math.max(0, Math.min(100, Math.round(forecast.confidence * 100)))
          : Math.max(0, Math.min(100, Math.round(forecast.confidence)))
      } else {
        confidence = 75 // Default confidence for usage-based forecasts
      }
    } else if (forecast) {
      // Fallback to Python API forecast
      daysUntilEmpty = forecast.days_until_empty != null && !isNaN(forecast.days_until_empty)
        ? Math.max(0, Math.round(forecast.days_until_empty))
        : (currentQuantity > 0 ? Math.max(0, Math.floor(currentQuantity / 1)) : 0)
      
      recommendedStock = forecast.recommended_stock != null && !isNaN(forecast.recommended_stock)
        ? Math.round(forecast.recommended_stock)
        : Math.max(50, currentQuantity * 2)
      
      // Parse consumption rate from forecast
      if (forecast.consumption_rate != null) {
        if (typeof forecast.consumption_rate === 'string') {
          consumptionRate = forecast.consumption_rate
        } else if (typeof forecast.consumption_rate === 'number') {
          consumptionRate = `${forecast.consumption_rate.toFixed(2)} units/day`
        } else {
          consumptionRate = '1 unit/day'
        }
      } else {
        // Calculate from days until empty and current stock
        const rate = currentQuantity > 0 && daysUntilEmpty > 0 
          ? (currentQuantity / daysUntilEmpty).toFixed(2)
          : 1
        consumptionRate = `${rate} units/day`
      }
      
      confidence = typeof forecast.confidence === 'number' && !isNaN(forecast.confidence)
        ? Math.max(0, Math.min(100, Math.round(forecast.confidence * 100) / 100)) // Ensure 0-100 range
        : (forecast ? 85 : 0) // Default confidence if forecast exists but no confidence value
      
      recommendedQuantity = forecast.recommended_quantity != null && !isNaN(forecast.recommended_quantity)
        ? Math.round(forecast.recommended_quantity)
        : recommendedStock
    } else {
      // No forecast available - use basic calculations
      daysUntilEmpty = currentQuantity > 0 ? Math.max(0, Math.floor(currentQuantity / 1)) : 0
      recommendedStock = Math.max(50, currentQuantity * 2)
      consumptionRate = '1 unit/day'
      confidence = 0 // No confidence without forecast
      recommendedQuantity = recommendedStock
    }

    // Next 3 months consumption prediction
    // MUST match Usage Overview: Use forecast.predicted_usage from /usage/forecast-data (EXACT same value)
    let nextThreeMonths = 'N/A'
    if (usageForecast) {
      // Use forecast.predicted_usage from backend (EXACT same field that Usage Overview uses)
      const forecast = usageForecast.forecast || {}
      let predictedUsage = forecast.predicted_usage
      
      // Also check direct forecasted_usage (if already processed by Python ML API like Usage Overview)
      if (predictedUsage == null && usageForecast.forecasted_usage != null) {
        predictedUsage = usageForecast.forecasted_usage
      }
      
      if (predictedUsage != null && !isNaN(predictedUsage) && predictedUsage > 0) {
        // Use the EXACT same predicted usage value as Usage Overview (forecast.predicted_usage)
        nextThreeMonths = `${Math.round(predictedUsage)} units`
      } else {
        // Fallback to average usage per quarter (same as Usage Overview fallback)
        const forecastFeatures = usageForecast.forecast_features || {}
        const statistics = usageForecast.statistics || {}
        // Backend provides: forecast_features.avg_usage_per_quarter (from stats.avg_usage)
        const avgUsagePerQuarter = forecastFeatures.avg_usage_per_quarter || statistics.avg_usage || 0
        if (avgUsagePerQuarter > 0) {
          nextThreeMonths = `${Math.round(avgUsagePerQuarter)} units`
        } else {
          const rateNum = parseFloat(String(consumptionRate).split(' ')[0]) || 1
          nextThreeMonths = `${Math.round(rateNum * 90)} units`
        }
      }
    } else if (forecast) {
      if (Array.isArray(forecast.usage_history) && forecast.usage_history.length) {
        const avgDaily = forecast.usage_history
          .slice(-90) // last ~3 months if provided daily
          .reduce((s, v) => s + Number(v || 0), 0) / Math.min(90, forecast.usage_history.length)
        nextThreeMonths = `${Math.round(avgDaily * 90)} units`
      } else if (Array.isArray(forecast.monthly) && forecast.monthly.length) {
        const sum = forecast.monthly.slice(0, 3).reduce((s, m) => s + Number(m.quantity || 0), 0)
        nextThreeMonths = `${sum} units`
      } else if (forecast.forecasted_consumption) {
        // Use forecasted consumption if available
        nextThreeMonths = `${Math.round(forecast.forecasted_consumption)} units`
      } else {
        const rateNum = parseFloat(String(consumptionRate).split(' ')[0]) || 1
        nextThreeMonths = `${Math.round(rateNum * 90)} units`
      }
    } else {
      const rateNum = parseFloat(String(consumptionRate).split(' ')[0]) || 1
      nextThreeMonths = `${Math.round(rateNum * 90)} units`
    }
    
    // Restock by date if will empty within 90 days
    const restockBy = daysUntilEmpty <= 90 && daysUntilEmpty > 0
      ? new Date(Date.now() + daysUntilEmpty * 24 * 60 * 60 * 1000).toLocaleDateString('en-PH', { timeZone: 'Asia/Manila' })
      : '>' + 90 + ' days'

    let stockStatusClass = 'bg-green-500'
    let recommendation = 'Good stock level'
    let recommendationClass = 'text-green-600'

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
      uuid: item.uuid,
      hasForecast: !!(usageForecast || forecast) // Track if forecast data is available (usage-based or API)
    }
  })
  
  // Sort: items with forecast data first, then by days until empty (most urgent first)
  return itemsWithForecast.sort((a, b) => {
    // Prioritize items with forecast data
    if (a.hasForecast !== b.hasForecast) {
      return b.hasForecast ? 1 : -1
    }
    // Then sort by days until empty (ascending - most urgent first)
    const daysA = parseInt(a.daysUntilEmpty) || 999
    const daysB = parseInt(b.daysUntilEmpty) || 999
    return daysA - daysB
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

// Fetch forecast data and build payload for Python API consumables prediction
const fetchConsumablesForecast = async () => {
  try {
    // Step 1: Fetch forecast-ready data from Laravel API
    const forecastDataResponse = await axiosClient.get('/usage/forecast-data?years_back=2&forecast_months=3')
    
    if (!forecastDataResponse.data.success || !forecastDataResponse.data.data) {
      console.warn('⚠️ Failed to fetch forecast data for consumables')
      usageForecastData.value = []
      return null
    }
    
    const forecastData = forecastDataResponse.data.data || []
    
    // Store usage forecast data for use in Supply Management table
    usageForecastData.value = forecastData
    
    // Debug: Log categories found in forecast data
    const categoriesFound = [...new Set(forecastData.map(item => item.item?.category || 'No category'))]
    console.log('📋 Categories in forecast data:', categoriesFound)
    
    // Filter only consumable items
    const consumables = forecastData.filter(item => {
      const category = item.item?.category || ''
      return isConsumableCategory(category)
    })
    
    console.log(`📊 Found ${consumables.length} consumable items out of ${forecastData.length} total items in forecast data`)
    
    if (consumables.length === 0) {
      console.warn('⚠️ No consumable items found in forecast data')
      console.warn('💡 This could mean: 1) No consumable items have usage records, or 2) Category names don\'t match')
      return null
    }
    
    // Step 2: Build payload in the correct format for Python ML API
    const mlPayload = {
      items: consumables.map(item => ({
        item_id: item.item_id,
        name: item.item?.unit || `Item ${item.item_id}`,
        historical_data: item.historical_data || [],
        forecast_features: item.forecast_features || {},
        current_stock: item.current_stock || 0
      }))
    }
    
    return mlPayload
  } catch (error) {
    console.warn('⚠️ Error fetching consumables forecast data:', error.message)
    usageForecastData.value = []
    return null
  }
}

// Helper function to extract numeric value from condition_number string (e.g., "A1" -> 1, "3" -> 3)
const extractConditionNumber = (conditionNumberStr) => {
  if (!conditionNumberStr) return 0
  const match = conditionNumberStr.toString().match(/\d+/)
  return match ? parseInt(match[0]) : 0
}

// Build payload for lifespan prediction API
const buildLifespanPayload = () => {
  const equipment = (items.value || [])
    .filter(i => !isConsumableCategory(i?.category))
  
  return {
    items: equipment.map(i => {
      // Calculate years in use from date_acquired
      const acquisitionDate = i.date_acquired ? new Date(i.date_acquired) : new Date()
      const today = new Date()
      const yearsInUse = (today - acquisitionDate) / (1000 * 60 * 60 * 24 * 365.25)
      
      // Get maintenance count from item or estimate from maintenance_records if available
      // Note: maintenance_records might not be loaded, so we use maintenance_count if available
      const maintenanceCount = i.maintenance_count ?? 0
      
      // Extract condition number numeric value
      // Try to get from condition string (format: "Condition (A1)" or similar)
      // Or use condition_number_id to lookup, but for now we'll parse from condition string
      let conditionNumber = 0
      if (i.condition && typeof i.condition === 'string') {
        // Extract numeric value from condition string like "Serviceable (A1)" -> 1
        conditionNumber = extractConditionNumber(i.condition)
      }
      
      // Get last maintenance reason from item or maintenance_reason field
      const lastReason = i.maintenance_reason || ''
      
      return {
        item_id: i.id,
        category: i.category || 'Unknown',
        years_in_use: Math.max(0, yearsInUse),
        maintenance_count: maintenanceCount,
        condition_number: conditionNumber,
        last_reason: lastReason
      }
    })
  }
}

// Build payload for XGBoost lifespan prediction (legacy - keeping for compatibility)
const buildXgbLifespanPayload = () => {
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
    // PRIORITY: Only CatBoost lifespan prediction API call (Python API) - REQUIRED
    const lifespanPayload = buildLifespanPayload()
    
    if (lifespanPayload.items.length === 0) {
      console.warn('⚠️ No items to predict lifespan for')
      lifespanPredictions.value = []
      apiLoading.value = false
      return
    }
    
    // Make CatBoost API call with better error handling
    let lifespanRes = null
    try {
      lifespanRes = await axios.post(
        `${PY_API_BASE}/predict/items/lifespan`, 
        lifespanPayload, 
        { 
          timeout: 15000,
          validateStatus: (status) => status < 500 // Don't throw on 4xx, only on 5xx
        }
      )
    } catch (err) {
      // Network errors (connection refused, timeout, etc.)
      if (err.code === 'ECONNABORTED' || err.code === 'ERR_NETWORK' || err.message?.includes('Network Error') || err.code === 'ERR_CONNECTION_REFUSED') {
        // Suppress verbose console errors for network issues - user-friendly message shown in UI
        lifespanRes = null
      } else {
        // Other errors might be worth logging
        console.warn('CatBoost API call failed:', err.message)
        lifespanRes = null
      }
    }

    // Process CatBoost lifespan predictions - ONLY source of truth
    if (lifespanRes?.data?.success && lifespanRes.data.predictions && Array.isArray(lifespanRes.data.predictions)) {
      // Store CatBoost predictions immediately for use in UI
      lifespanPredictions.value = lifespanRes.data.predictions
      apiError.value = null // Clear any previous errors
      
      // Log predictions summary for visibility
      console.log(`✅ Received ${lifespanRes.data.predictions.length} predictions from Python API`)
      console.log(`📊 Prediction method: ${lifespanRes.data.method || 'catboost_model'}`)
      console.log(`📋 Sample predictions:`, lifespanRes.data.predictions.slice(0, 5))
      
      // Prepare predictions for batch update - map item_id to uuid
      const predictionsToUpdate = lifespanRes.data.predictions
        .map(pred => {
          const item = items.value.find(i => i.id === pred.item_id)
          if (!item || !item.uuid || pred.remaining_years == null || isNaN(pred.remaining_years)) {
            return null
          }
          return {
            uuid: item.uuid,
            remaining_years: parseFloat(pred.remaining_years),
            lifespan_estimate: pred.lifespan_estimate != null ? parseFloat(pred.lifespan_estimate) : null
          }
        })
        .filter(p => p !== null)
      
      // Batch update items via Laravel API (non-blocking)
      // IMPORTANT: Check authentication before making the request
      const token = localStorage.getItem('token')
      if (!token) {
        console.warn('⚠️ Cannot update lifespan predictions: User not authenticated. Please log in first.')
        return // Exit early if not authenticated
      }
      
      if (predictionsToUpdate.length > 0) {
        // Route is in v1 group, so final URL should be: /api/v1/items/update-lifespan-predictions
        // If baseURL is /api/v1, use: /items/update-lifespan-predictions
        // If baseURL is /api, use: /v1/items/update-lifespan-predictions
        const baseUrl = axiosClient.defaults.baseURL || import.meta.env.VITE_API_BASE_URL || '/api'
        const updateUrl = baseUrl.includes('/v1') 
          ? '/items/update-lifespan-predictions'
          : '/v1/items/update-lifespan-predictions'
        
        console.log('🔧 Updating lifespan predictions - baseURL:', baseUrl, 'updateUrl:', updateUrl)
        
        console.log(`📤 Sending ${predictionsToUpdate.length} predictions to Laravel backend for database update`)
        
        axiosClient.post(updateUrl, {
          predictions: predictionsToUpdate
        }).then((updateResponse) => {
          console.log('✅ Database update response:', updateResponse.data)
          if (updateResponse.data) {
            console.log(`📊 Update Summary:`)
            console.log(`   - Total predictions sent: ${predictionsToUpdate.length}`)
            console.log(`   - Successfully updated: ${updateResponse.data.updated_count || 0}`)
            if (updateResponse.data.errors && updateResponse.data.errors.length > 0) {
              console.warn(`   - Errors: ${updateResponse.data.errors.length}`)
              console.warn(`   - First few errors:`, updateResponse.data.errors.slice(0, 3))
            }
          }
          fetchitems() // Refresh items in background
        }).catch((updateError) => {
          // Handle authentication errors - redirect to login
          if (updateError.response?.status === 401) {
            console.error('❌ Authentication required. Redirecting to login...')
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            localStorage.removeItem('userId')
            // Redirect to login page
            window.location.href = '/login'
            return
          }
          
          // Log other errors but don't fail - predictions are still available in UI
          console.error('❌ Database update failed:', updateError.response?.data || updateError.message)
          if (updateError.response?.data) {
            console.error('   Error details:', updateError.response.data)
          }
        })
      }
    } else {
      // API call failed or returned invalid data
      lifespanPredictions.value = []
      apiError.value = 'Python API server is not available. Please ensure the server is running at ' + PY_API_BASE
    }

    // Optional: Try to fetch Linear Regression for supply (non-blocking, silent on failure)
    // Fetch forecast data and build correct payload
    fetchConsumablesForecast().then(mlPayload => {
      if (mlPayload && mlPayload.items && mlPayload.items.length > 0) {
        return axios.post(`${PY_API_BASE}/predict/consumables/linear`, mlPayload, { 
          timeout: 15000,
          headers: {
            'Content-Type': 'application/json'
          }
        })
      }
      return Promise.resolve(null)
    })
      .then(lrRes => {
        if (lrRes?.data?.forecast) {
          lrConsumableForecast.value = lrRes.data.forecast
          console.log('✅ Consumables Linear Regression forecasts received:', lrRes.data.forecast.length)
        }
      })
      .catch((error) => {
        // Silent fail - not critical for lifespan predictions
        console.warn('⚠️ Consumables forecast API error (non-critical):', error.message)
        lrConsumableForecast.value = []
      })

    // Optional: Try to fetch XGBoost (legacy, non-blocking, silent on failure)
    axios.post(`${PY_API_BASE}/predict/equipment-lifespan/xgboost`, buildXgbLifespanPayload(), { timeout: 12000 })
      .then(xgbRes => {
    if (xgbRes?.data?.lifespan_predictions) {
      xgbLifespanForecast.value = xgbRes.data.lifespan_predictions
      if (typeof xgbRes.data.accuracy === 'number') {
        lifespanAccuracy.value = xgbRes.data.accuracy
      }
        }
      })
      .catch(() => {
        // Silent fail - not critical
      xgbLifespanForecast.value = []
      })

  } catch (e) {
    // Unexpected errors
    console.error('Unexpected error in fetchPredictions:', e)
    apiError.value = 'An unexpected error occurred while fetching predictions'
    lifespanPredictions.value = []
  } finally {
    apiLoading.value = false
  }
}

// Store notification interval for cleanup
const notificationInterval = ref(null)

onMounted(async () => {
  await fetchitems()
  await fetchPredictions()
  // Fetch notifications to show low stock alerts
  await fetchNotifications(10)
  
  // Refresh notifications periodically to keep them updated
  notificationInterval.value = setInterval(async () => {
    await refreshNotifications()
  }, 30000) // Refresh every 30 seconds
})

// Cleanup interval on unmount
onBeforeUnmount(() => {
  if (notificationInterval.value) {
    clearInterval(notificationInterval.value)
  }
})
</script>

<style scoped>
.material-icons-outlined {
  font-size: 24px;
}

/* Enhanced Button Styles */
.btn-primary-enhanced {
  background: linear-gradient(to right, #000000, #575757);
  color: white;
  padding: 0.625rem 1rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  font-size: 0.875rem;
  font-weight: 600;
  transition: all 0.3s;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transform: translateY(0);
}
.btn-primary-enhanced:hover {
  background: linear-gradient(to right, #1a1a1a, #6b6b6b);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  transform: translateY(-2px);
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

/* DISPOSE badge - Enhanced visibility and cross-browser compatibility */
/* Target spans with red-600 background (DISPOSE status) */
span.bg-red-600 {
  background-color: #dc2626 !important; /* red-600 */
  color: #ffffff !important; /* white */
  font-weight: 700 !important;
  letter-spacing: 0.05em;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  border: 1px solid rgba(0, 0, 0, 0.1);
}

/* Dark mode DISPOSE badge */
.dark span.bg-red-700 {
  background-color: #b91c1c !important; /* red-700 */
  color: #ffffff !important; /* white */
  border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Ensure DISPOSE text is visible on all screen sizes */
@media (max-width: 640px) {
  span.bg-red-600,
  span.bg-red-700 {
    font-size: 0.875rem !important; /* text-sm */
    padding: 0.5rem 0.75rem !important;
    min-width: 90px !important;
  }
}

@media (min-width: 641px) {
  span.bg-red-600,
  span.bg-red-700 {
    font-size: 1rem !important; /* text-base */
    padding: 0.5rem 1rem !important;
  }
}

/* Additional fallback for maximum compatibility */
[class*="bg-red-600"] {
  background-color: #dc2626 !important;
  color: #ffffff !important;
}

.dark [class*="bg-red-700"] {
  background-color: #b91c1c !important;
  color: #ffffff !important;
}
</style>