/** Limits shared with the API validation (SetLogController / ProgramWriter). */
export const LIMITS = {
  weightKg: 1000,
  reps: 500,
  seconds: 3600,
  /** Program builder: target reps per set. */
  planReps: 200,
} as const

/**
 * Clean a typed value down to a non-negative number string.
 * - only digits and, if `decimals` > 0, a single "." or "," with at most `decimals` digits after it;
 * - leading zeros dropped ("007" → "7", "0.5" stays);
 * - if the result would exceed `max`, the previous value is kept (the keystroke is ignored).
 */
export function sanitizeNumber(raw: string, opts: { max: number; decimals?: number; previous?: string }): string {
  const decimals = opts.decimals ?? 0
  let s = raw.replace(decimals ? /[^\d.,]/g : /\D/g, '')

  if (decimals) {
    const sep = s.search(/[.,]/)
    if (sep >= 0) {
      const int = s.slice(0, sep)
      const frac = s.slice(sep + 1).replace(/[.,]/g, '').slice(0, decimals)
      s = `${int || '0'}${s[sep]}${frac}`
    }
  }
  s = s.replace(/^0+(?=\d)/, '')

  const value = Number(s.replace(',', '.'))
  if (s !== '' && (!Number.isFinite(value) || value > opts.max)) {
    return opts.previous !== undefined && Number(opts.previous.replace(',', '.')) <= opts.max ? opts.previous : String(opts.max)
  }
  return s
}

/** Parse a field value; null for empty, NaN for garbage. */
export const parseNumber = (v: string): number | null => (v.trim() === '' ? null : Number(v.replace(',', '.')))

/** Whole/decimal number within [min, max]. */
export function inRange(v: number | null, min: number, max: number, integer = false): v is number {
  return v !== null && Number.isFinite(v) && v >= min && v <= max && (!integer || Number.isInteger(v))
}
