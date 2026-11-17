import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, firstError } from '@/lib/api'
import type { Problem } from '@/lib/validation'

/**
 * Small form helper: client-side checks per field (shown after the field was touched
 * or on submit) plus server-side validation errors mapped onto the same fields.
 */
export function useForm<F extends Record<string, string>>(
  initial: F,
  checks: Partial<Record<keyof F, (form: F) => Problem>>,
) {
  const { t } = useI18n()
  const form = reactive({ ...initial }) as F
  const touched = reactive<Record<string, boolean>>({})
  const server = reactive<Record<string, string>>({})
  const general = ref('')
  const busy = ref(false)

  function problem(field: keyof F): string | null {
    const p = checks[field]?.(form)
    return p ? t(p.key, p.params ?? {}) : null
  }

  function error(field: keyof F): string | null {
    const key = field as string
    if (server[key]) return server[key]
    return touched[key] ? problem(field) : null
  }

  function touch(field: keyof F) {
    touched[field as string] = true
    delete server[field as string]
  }

  function valid(): boolean {
    Object.keys(checks).forEach((k) => (touched[k] = true))
    return Object.keys(checks).every((k) => !problem(k as keyof F))
  }

  async function submit(action: () => Promise<void>) {
    general.value = ''
    Object.keys(server).forEach((k) => delete server[k])
    if (!valid()) return
    busy.value = true
    try {
      await action()
    } catch (e) {
      if (e instanceof ApiError && Object.keys(e.errors).length) {
        for (const [k, msgs] of Object.entries(e.errors)) {
          if (k in form) server[k] = msgs[0]
          else general.value = msgs[0]
        }
      } else {
        general.value = firstError(e)
      }
    } finally {
      busy.value = false
    }
  }

  return { form, error, touch, submit, busy, general }
}
