import { reactive } from 'vue'
import { ApiError, api, isNetworkError, patchCache } from '@/lib/api'
import type { Session, SetLog } from '@/types'

/**
 * Offline outbox for logged sets.
 *
 * Every set save goes through here: it is queued in localStorage first, then sent. Without a
 * network the set simply stays queued and is retried when the connection returns (online event,
 * app brought back to the foreground, every 30 s). POST /sessions/{id}/sets is idempotent per
 * (slot, exercise, set_no), so re-sending is always safe and only the latest version is kept.
 */

export interface SetBody {
  day_exercise_id: number
  exercise_id: number
  set_no: number
  weight_kg: number | null
  reps: number | null
  seconds: number | null
  rpe: number | null
}

export interface QueuedSet {
  key: string
  sessionId: number
  body: SetBody
  queuedAt: number
}

export type SettleResult = { set: SetLog } | { error: unknown }

const STORAGE_KEY = 'gym.outbox'

function load(): QueuedSet[] {
  try {
    const items = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]')
    return Array.isArray(items) ? items : []
  } catch {
    return []
  }
}

export const outbox = reactive({ items: load(), syncing: false })

function persist() {
  try {
    if (outbox.items.length) localStorage.setItem(STORAGE_KEY, JSON.stringify(outbox.items))
    else localStorage.removeItem(STORAGE_KEY)
  } catch { /* storage full / private mode: the in-memory queue still works for this visit */ }
}

export const setKey = (sessionId: number, b: Pick<SetBody, 'day_exercise_id' | 'exercise_id' | 'set_no'>) =>
  `${sessionId}:${b.day_exercise_id}:${b.exercise_id}:${b.set_no}`

export function pending(key: string): QueuedSet | undefined {
  return outbox.items.find((i) => i.key === key)
}

export function pendingCount(sessionId: number): number {
  return outbox.items.filter((i) => i.sessionId === sessionId).length
}

/** Queue a set (replacing an older unsent version of the same set). */
export function enqueue(sessionId: number, body: SetBody): QueuedSet {
  const item: QueuedSet = { key: setKey(sessionId, body), sessionId, body, queuedAt: Date.now() }
  const i = outbox.items.findIndex((x) => x.key === item.key)
  if (i >= 0) outbox.items.splice(i, 1, item)
  else outbox.items.push(item)
  persist()
  return item
}

export function dequeue(key: string) {
  const i = outbox.items.findIndex((x) => x.key === key)
  if (i >= 0) {
    outbox.items.splice(i, 1)
    persist()
  }
}

export function clearOutbox() {
  outbox.items.splice(0)
  persist()
}

const listeners = new Set<(item: QueuedSet, result: SettleResult) => void>()

/** Called when a queued set is accepted (or rejected) by the server. Returns an unsubscribe function. */
export function onSettled(fn: (item: QueuedSet, result: SettleResult) => void): () => void {
  listeners.add(fn)
  return () => listeners.delete(fn)
}

/** Worth retrying later: no network, server down, or signed out / CSRF expired. */
function retryable(e: unknown): boolean {
  return isNetworkError(e) || (e instanceof ApiError && [401, 419, 429].includes(e.status))
}

/** Send queued sets in order; stops at the first network failure and leaves the rest queued. */
export async function flush(): Promise<void> {
  if (outbox.syncing) return
  outbox.syncing = true
  try {
    while (outbox.items.length) {
      if (typeof navigator !== 'undefined' && navigator.onLine === false) return
      const item = outbox.items[0]
      try {
        const set = await api<SetLog>(`/sessions/${item.sessionId}/sets`, 'POST', item.body)
        // The user may have edited the set while it was in flight — then the newer version stays queued.
        const replaced = pending(item.key)?.queuedAt !== item.queuedAt
        if (!replaced) dequeue(item.key)
        // Keep the offline copy of the workout in step, so an offline reload shows the set as saved.
        patchCache<Session>(`/sessions/${item.sessionId}`, (s) => {
          const i = s.sets.findIndex((x) => x.id === set.id)
          if (i >= 0) s.sets[i] = set
          else s.sets.push(set)
          return s
        })
        if (!replaced) listeners.forEach((fn) => fn(item, { set }))
      } catch (e) {
        if (retryable(e)) return
        dequeue(item.key) // rejected by the server (validation, workout deleted…): don't retry forever
        listeners.forEach((fn) => fn(item, { error: e }))
      }
    }
  } finally {
    outbox.syncing = false
  }
}

let started = false
/** Retry automatically: when the connection returns, the app comes back to the foreground, and every 30 s. */
export function startOutbox() {
  if (started) return
  started = true
  const kick = () => { if (outbox.items.length) void flush() }
  window.addEventListener('online', kick)
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') kick() })
  window.setInterval(kick, 30_000)
  kick()
}
