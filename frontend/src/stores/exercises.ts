import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/lib/api'
import type { Exercise } from '@/types'

/** Cached exercise catalogue (shared + the user's own). */
export const useExercises = defineStore('exercises', () => {
  const list = ref<Exercise[]>([])
  let loading: Promise<void> | null = null

  function load(force = false) {
    if (list.value.length && !force) return Promise.resolve()
    loading ??= api<Exercise[]>('/exercises')
      .then((r) => { list.value = r })
      .finally(() => { loading = null })
    return loading
  }

  function byId(id: number) {
    return list.value.find((e) => e.id === id)
  }

  async function create(payload: { name_ru?: string; name_en?: string; muscles: string[] }) {
    const ex = await api<Exercise>('/exercises', 'POST', payload)
    list.value.push(ex)
    return ex
  }

  return { list, load, byId, create }
})
