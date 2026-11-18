import { ref } from 'vue'

export type Theme = 'dark' | 'light' | 'system'

const KEY = 'theme'
const COLORS = { dark: '#0f1014', light: '#f9f9fb' } as const
const media = window.matchMedia('(prefers-color-scheme: dark)')

function read(): Theme {
  try {
    const v = localStorage.getItem(KEY)
    if (v === 'dark' || v === 'light' || v === 'system') return v
  } catch { /* ignore */ }
  return 'dark'
}

export const theme = ref<Theme>(read())
export const isDark = ref(true)

function apply() {
  const dark = theme.value === 'dark' || (theme.value === 'system' && media.matches)
  isDark.value = dark
  const root = document.documentElement
  root.classList.toggle('dark', dark)
  root.style.colorScheme = dark ? 'dark' : 'light'
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? COLORS.dark : COLORS.light)
}

export function setTheme(t: Theme) {
  theme.value = t
  try { localStorage.setItem(KEY, t) } catch { /* ignore */ }
  apply()
}

export function initTheme() {
  apply()
  media.addEventListener('change', () => { if (theme.value === 'system') apply() })
}
