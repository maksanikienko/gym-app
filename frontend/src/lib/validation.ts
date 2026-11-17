// Mirrors backend/app/Support/AuthRules.php — keep the limits in sync.
export const NAME_MIN = 2
export const NAME_MAX = 100
export const EMAIL_MAX = 255
export const PASSWORD_MIN = 8
export const PASSWORD_MAX = 72

/** Each validator returns an i18n key (+ params) or null when the value is valid. */
export type Problem = { key: string; params?: Record<string, unknown> } | null

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/

export function checkName(v: string): Problem {
  const s = v.trim()
  if (!s) return { key: 'validation.required' }
  if (s.length < NAME_MIN) return { key: 'validation.minChars', params: { n: NAME_MIN } }
  if (s.length > NAME_MAX) return { key: 'validation.maxChars', params: { n: NAME_MAX } }
  if (!/^[\p{L}\p{M}\s'.-]+$/u.test(s)) return { key: 'validation.nameChars' }
  return null
}

export function checkEmail(v: string): Problem {
  const s = v.trim()
  if (!s) return { key: 'validation.required' }
  if (s.length > EMAIL_MAX) return { key: 'validation.maxChars', params: { n: EMAIL_MAX } }
  if (!EMAIL_RE.test(s)) return { key: 'validation.email' }
  return null
}

export function checkPassword(v: string): Problem {
  if (!v) return { key: 'validation.required' }
  if (v.length < PASSWORD_MIN) return { key: 'validation.minChars', params: { n: PASSWORD_MIN } }
  if (v.length > PASSWORD_MAX) return { key: 'validation.maxChars', params: { n: PASSWORD_MAX } }
  if (!/\p{L}/u.test(v)) return { key: 'validation.passwordLetter' }
  if (!/\d/.test(v)) return { key: 'validation.passwordDigit' }
  return null
}

export function checkConfirm(password: string, confirm: string): Problem {
  if (!confirm) return { key: 'validation.required' }
  return password === confirm ? null : { key: 'validation.mismatch' }
}

/** 0–4: length, letters+digits, mixed case, symbols. */
export function passwordStrength(v: string): number {
  if (!v) return 0
  let score = 0
  if (v.length >= PASSWORD_MIN) score++
  if (v.length >= 12) score++
  if (/[a-zа-яё]/.test(v) && /[A-ZА-ЯЁ]/.test(v)) score++
  if (/\d/.test(v) && /[^\p{L}\d]/u.test(v)) score++
  if (checkPassword(v)) score = Math.min(score, 1)
  return score
}
