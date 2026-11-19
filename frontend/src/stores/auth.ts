import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, cachedGet, clearCache } from '@/lib/api'
import { clearOutbox } from '@/lib/outbox'
import { applyLocale, i18n } from '@/i18n'
import type { Locale, User } from '@/types'

export const useAuth = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)

  async function init() {
    if (ready.value) return
    try {
      user.value = (await cachedGet<User>('/me')).data // offline → last known user
      applyLocale(user.value.locale)
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  /**
   * Offline data (cached screens, unsent sets) belongs to one account. Re-signing in as the same user
   * (e.g. after the session expired) must keep unsent sets; a different account starts clean.
   */
  const OWNER_KEY = 'gym.localDataOwner'
  function forgetLocalData() {
    clearCache()
    clearOutbox()
  }
  function claimLocalData(u: User) {
    try {
      if (localStorage.getItem(OWNER_KEY) !== String(u.id)) forgetLocalData()
      localStorage.setItem(OWNER_KEY, String(u.id))
    } catch { /* ignore */ }
  }

  async function login(email: string, password: string) {
    user.value = await api<User>('/login', 'POST', { email, password })
    claimLocalData(user.value)
    applyLocale(user.value.locale)
  }

  async function register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
    user.value = await api<User>('/register', 'POST', { ...payload, locale: i18n.global.locale.value })
    claimLocalData(user.value)
  }

  async function logout() {
    await api('/logout', 'POST').catch(() => {})
    clearCache() // unsent sets stay until a different account signs in
    user.value = null
  }

  async function setLocale(locale: Locale) {
    applyLocale(locale)
    if (user.value) user.value = await api<User>('/me', 'PATCH', { locale })
  }

  async function updateName(name: string) {
    user.value = await api<User>('/me', 'PATCH', { name })
  }

  async function changePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
    await api('/me/password', 'PUT', payload)
  }

  async function deleteAccount(password: string) {
    await api('/me', 'DELETE', { password })
    forgetLocalData()
    user.value = null
  }

  return { user, ready, init, login, register, logout, setLocale, updateName, changePassword, deleteAccount }
})
