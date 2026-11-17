import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import { router } from './router'
import { i18n } from './i18n'
import { setUnauthorizedHandler } from './lib/api'
import { useAuth } from './stores/auth'

// Follow the system colour scheme.
const media = window.matchMedia('(prefers-color-scheme: dark)')
const applyTheme = () => document.documentElement.classList.toggle('dark', media.matches)
applyTheme()
media.addEventListener('change', applyTheme)

const app = createApp(App).use(createPinia()).use(router).use(i18n)

setUnauthorizedHandler(() => {
  useAuth().user = null
  if (!router.currentRoute.value.meta.guest) router.push('/login')
})

app.mount('#app')
