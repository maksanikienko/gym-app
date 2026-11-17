import { i18n } from '@/i18n'

const BASE = import.meta.env.VITE_API_BASE ?? ''

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public errors: Record<string, string[]> = {},
  ) {
    super(message)
  }
}

let onUnauthorized: () => void = () => {}
export function setUnauthorizedHandler(fn: () => void) {
  onUnauthorized = fn
}

function xsrfToken(): string {
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return m ? decodeURIComponent(m[1]) : ''
}

async function csrf() {
  await fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' })
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export async function api<T = unknown>(path: string, method: Method = 'GET', body?: unknown, retried = false): Promise<T> {
  if (method !== 'GET' && !xsrfToken()) await csrf()

  const res = await fetch(`${BASE}/api${path}`, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': xsrfToken(),
      'Accept-Language': i18n.global.locale.value,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (res.status === 419 && !retried) {
    await csrf()
    return api<T>(path, method, body, true)
  }
  if (res.status === 401) {
    onUnauthorized()
    throw new ApiError('Unauthenticated', 401)
  }
  if (res.status === 204) return undefined as T

  const data = await res.json().catch(() => null)
  if (!res.ok) {
    throw new ApiError(data?.message ?? res.statusText, res.status, data?.errors ?? {})
  }
  return data as T
}

export function firstError(e: unknown): string {
  if (e instanceof ApiError) {
    const first = Object.values(e.errors)[0]?.[0]
    return first ?? e.message
  }
  return e instanceof Error ? e.message : String(e)
}
