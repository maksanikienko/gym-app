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
    // A weak gym signal can leave a request hanging forever; fail it so it can be queued / retried.
    signal: typeof AbortSignal !== 'undefined' && 'timeout' in AbortSignal ? AbortSignal.timeout(15_000) : undefined,
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

/** No connection, request timed out, or the server is down (5xx) — as opposed to a real API error. */
export function isNetworkError(e: unknown): boolean {
  if (e instanceof ApiError) return e.status >= 500
  return e instanceof Error && (e instanceof TypeError || e.name === 'TimeoutError' || e.name === 'AbortError')
}

/* Offline copies of a few GET responses (current user, today, open workout), so the app opens without a network. */
const CACHE_PREFIX = 'gym.cache.'

function readCache<T>(path: string): { v: T } | null {
  try {
    const raw = localStorage.getItem(CACHE_PREFIX + path)
    return raw ? (JSON.parse(raw) as { v: T }) : null
  } catch {
    return null
  }
}

export function writeCache<T>(path: string, data: T) {
  try { localStorage.setItem(CACHE_PREFIX + path, JSON.stringify({ v: data })) } catch { /* storage full */ }
}

export function patchCache<T>(path: string, fn: (data: T) => T) {
  const cached = readCache<T>(path)
  if (cached?.v) writeCache(path, fn(cached.v))
}

export function clearCache() {
  try {
    for (const key of Object.keys(localStorage)) if (key.startsWith(CACHE_PREFIX)) localStorage.removeItem(key)
  } catch { /* ignore */ }
}

/** GET that remembers the last response and falls back to it when there's no network. */
export async function cachedGet<T>(path: string): Promise<{ data: T; offline: boolean }> {
  try {
    const data = await api<T>(path)
    writeCache(path, data)
    return { data, offline: false }
  } catch (e) {
    const cached = isNetworkError(e) ? readCache<T>(path) : null
    if (cached) return { data: cached.v, offline: true }
    throw e
  }
}

export function firstError(e: unknown): string {
  if (isNetworkError(e) && !(e instanceof ApiError)) return i18n.global.t('offline.needNetwork')
  if (e instanceof ApiError) {
    const first = Object.values(e.errors)[0]?.[0]
    return first ?? e.message
  }
  return e instanceof Error ? e.message : String(e)
}
