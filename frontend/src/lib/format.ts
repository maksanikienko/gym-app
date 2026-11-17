import type { SetLog } from '@/types'

export function fmtNum(n: number | null | undefined): string {
  if (n === null || n === undefined) return '–'
  return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '')
}

export interface Prescription {
  sets: number
  reps: number | null
  reps_max?: number | null
  reps_text?: string | null
  seconds: number | null
}

/** "3 × 10", "3 × 10–12", "3 × 15/15", "3 × AMRAP", "3 × 30s" */
export function fmtTarget(x: Prescription, sec = 's'): string {
  let amount: string
  if (x.reps_text) amount = x.reps_text
  else if (x.reps && x.reps_max) amount = `${x.reps}–${x.reps_max}`
  else if (x.reps) amount = String(x.reps)
  else if (x.seconds) amount = `${x.seconds}${sec}`
  else amount = '–'
  return `${x.sets} × ${amount}`
}

/** "80×6 · 80×6 · 82.5×5" */
export function fmtSets(sets: SetLog[], sec = 's'): string {
  return sets
    .map((s) => {
      const amount = s.reps ?? (s.seconds ? `${s.seconds}${sec}` : '–')
      return s.weight_kg ? `${fmtNum(s.weight_kg)}×${amount}` : String(amount)
    })
    .join(' · ')
}

export function fmtDate(iso: string, locale: string): string {
  return new Date(iso).toLocaleDateString(locale === 'ru' ? 'ru-RU' : 'en-GB', {
    weekday: 'short', day: 'numeric', month: 'short',
  })
}

export function youtubeSearch(name: string): string {
  return `https://www.youtube.com/results?search_query=${encodeURIComponent(`${name} exercise technique`)}`
}
