import { useI18n } from 'vue-i18n'

/** Pick `${field}_${locale}` from an API object, falling back to English. */
export function useLocalized() {
  const { locale } = useI18n()
  function l(obj: object | null | undefined, field: string): string {
    if (!obj) return ''
    const o = obj as Record<string, unknown>
    return (o[`${field}_${locale.value}`] as string) || (o[`${field}_en`] as string) || ''
  }
  return { l, locale }
}
