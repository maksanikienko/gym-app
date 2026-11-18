import { reactive } from 'vue'

export interface ConfirmOptions {
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  /** Red confirm button + warning icon. */
  destructive?: boolean
}

export const confirmState = reactive({
  open: false,
  options: { title: '' } as ConfirmOptions,
  resolve: null as ((ok: boolean) => void) | null,
})

/** Promise-based replacement for window.confirm(), rendered by <ConfirmHost />. */
export function confirmDialog(options: ConfirmOptions | string): Promise<boolean> {
  confirmState.resolve?.(false)
  return new Promise((resolve) => {
    confirmState.options = typeof options === 'string' ? { title: options } : options
    confirmState.resolve = resolve
    confirmState.open = true
  })
}

export function settleConfirm(ok: boolean) {
  const resolve = confirmState.resolve
  confirmState.resolve = null
  confirmState.open = false
  resolve?.(ok)
}
