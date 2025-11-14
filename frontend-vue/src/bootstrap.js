import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// Get configuration from environment variables or use defaults
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY || 'your-pusher-app-key'
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1'
const pusherHost = import.meta.env.VITE_PUSHER_HOST || window.location.hostname
const pusherPort = import.meta.env.VITE_PUSHER_PORT || 8080 // Default to Reverb's default port

// Initialize Laravel Echo
// Using Pusher protocol (works with both Pusher.com and Laravel Reverb)
try {
  // Build connection config
  // For Laravel Reverb, we use wsHost/wsPort
  // Pusher.js requires a cluster when using wsHost, so we provide a dummy value
  // The cluster value is ignored when wsHost is set
  // For localhost/Reverb, force non-secure WebSocket
  const isLocalhost = pusherHost === 'localhost' || pusherHost === '127.0.0.1' || pusherHost.includes('localhost')
  const useTLS = !isLocalhost && import.meta.env.VITE_PUSHER_FORCE_TLS === 'true'
  
  const echoConfig = {
    broadcaster: 'pusher',
    key: pusherKey,
    wsHost: pusherHost,
    wsPort: pusherPort,
    wssPort: pusherPort,
    forceTLS: useTLS, // Force TLS only if not localhost
    enabledTransports: isLocalhost ? ['ws'] : ['ws', 'wss'], // Only use ws for localhost
    disableStats: true,
    encrypted: useTLS, // Only encrypt if not localhost
    // Add a dummy cluster to satisfy Pusher.js requirement
    // This is ignored when wsHost is set (Reverb mode)
    cluster: pusherCluster || 'mt1'
  }

  // If using Pusher.com (not Reverb), remove wsHost/wsPort and use cluster only
  const isReverb = pusherHost === 'localhost' || pusherHost === window.location.hostname || pusherPort === 8080
  if (!isReverb && pusherCluster && pusherCluster !== 'mt1') {
    // Using Pusher.com, remove wsHost/wsPort
    delete echoConfig.wsHost
    delete echoConfig.wsPort
    delete echoConfig.wssPort
    echoConfig.cluster = pusherCluster
  }

  window.Echo = new Echo(echoConfig)

  // Add connection event listeners for debugging
  if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
    const pusher = window.Echo.connector.pusher

    pusher.connection.bind('connected', () => {
      console.log('✅ Laravel Echo connected successfully')
    })

    pusher.connection.bind('disconnected', () => {
      console.warn('⚠️ Laravel Echo disconnected')
    })

    pusher.connection.bind('error', (err) => {
      console.error('❌ Laravel Echo connection error:', err)
      console.error('💡 Tip: Make sure Laravel Reverb or Pusher is running and configured')
      console.error('💡 Check that REVERB_APP_KEY matches VITE_PUSHER_APP_KEY')
      console.error('💡 Verify Reverb server is running: php artisan reverb:start')
    })

    pusher.connection.bind('unavailable', () => {
      console.error('❌ Connection unavailable - Reverb server might not be running')
      console.error('💡 Start Reverb: php artisan reverb:start')
    })

    pusher.connection.bind('failed', () => {
      console.error('❌ Connection failed')
      console.error('💡 Check:')
      console.error('   1. Reverb server is running (php artisan reverb:start)')
      console.error('   2. REVERB_APP_KEY matches VITE_PUSHER_APP_KEY')
      console.error('   3. Port 8080 is not blocked by firewall')
    })

    pusher.connection.bind('state_change', (states) => {
      console.log('🔄 Echo connection state changed:', states.previous, '->', states.current)
    })
  }

  console.log('🚀 Laravel Echo initialized')
  console.log('📡 Connecting to:', pusherHost + ':' + pusherPort)
  console.log('🔒 Using secure WebSocket:', useTLS ? 'Yes (wss://)' : 'No (ws://)')
  console.log('🔑 Using key:', pusherKey.substring(0, 10) + '...')
  
  // Warn if using default key
  if (pusherKey === 'your-pusher-app-key') {
    console.warn('⚠️ WARNING: Using default Pusher key!')
    console.warn('💡 Set VITE_PUSHER_APP_KEY in your .env file to match REVERB_APP_KEY from backend')
    console.warn('💡 Or set it in your Vite config')
  }
  
  // Additional debug info
  if (isLocalhost) {
    console.log('🏠 Localhost detected - using non-secure WebSocket (ws://)')
  }
  
} catch (error) {
  console.error('❌ Failed to initialize Laravel Echo:', error)
  console.warn('💡 Real-time updates will not work. Configure broadcasting to enable real-time features.')
  // Create a dummy Echo object to prevent errors
  window.Echo = {
    channel: () => ({
      listen: () => ({})
    }),
    leave: () => {}
  }
}

// Update auth headers when token changes
window.addEventListener('storage', (e) => {
  if (e.key === 'token' && window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
    window.Echo.connector.pusher.config.auth.headers.Authorization = `Bearer ${e.newValue || ''}`
  }
})

export default window.Echo

