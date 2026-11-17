import { createI18n } from 'vue-i18n'
import ru from './ru'
import en from './en'
import type { Locale } from '@/types'

const KEY = 'gym.locale'

function initialLocale(): Locale {
  try {
    const saved = localStorage.getItem(KEY)
    if (saved === 'ru' || saved === 'en') return saved
  } catch { /* storage unavailable */ }
  return navigator.language.startsWith('ru') ? 'ru' : 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: 'en',
  messages: { ru, en },
})

export function applyLocale(locale: Locale) {
  i18n.global.locale.value = locale
  document.documentElement.lang = locale
  try { localStorage.setItem(KEY, locale) } catch { /* ignore */ }
}
