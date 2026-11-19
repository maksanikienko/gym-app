import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import { router } from './router'
import { i18n } from './i18n'
import { setUnauthorizedHandler } from './lib/api'
import { useAuth } from './stores/auth'
import { initTheme } from './lib/theme'
import { startOutbox } from './lib/outbox'

initTheme()

const app = createApp(App).use(createPinia()).use(router).use(i18n)

setUnauthorizedHandler(() => {
  useAuth().user = null
  if (!router.currentRoute.value.meta.guest) router.push('/login')
})

app.mount('#app')
startOutbox()
