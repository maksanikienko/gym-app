import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import { applyLocale, i18n } from '@/i18n'
import type { Locale, User } from '@/types'

export const useAuth = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)

  async function init() {
    if (ready.value) return
    try {
      user.value = await api<User>('/me')
      applyLocale(user.value.locale)
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(email: string, password: string) {
    user.value = await api<User>('/login', 'POST', { email, password })
    applyLocale(user.value.locale)
  }

  async function register(payload: { name: string; email: string; password: string; password_confirmation: string }) {
    user.value = await api<User>('/register', 'POST', { ...payload, locale: i18n.global.locale.value })
  }

  async function logout() {
    await api('/logout', 'POST').catch(() => {})
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
    user.value = null
  }

  return { user, ready, init, login, register, logout, setLocale, updateName, changePassword, deleteAccount }
})
