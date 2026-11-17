import type { Equipment } from '@/types'

// Mirrors backend App\Support\Equipment.
export const BAR_KG = 20
const PLATES = [25, 20, 15, 10, 5, 2.5, 1.25]

export function loadStep(eq: Equipment | undefined, weight = 0): number {
  return eq === 'dumbbell' ? (weight < 10 ? 1 : 2) : 2.5
}

export function roundLoad(weight: number, eq: Equipment | undefined): number {
  const step = loadStep(eq, weight)
  let w = Math.round(weight / step) * step
  if (eq === 'barbell' && w > 0 && w < BAR_KG) w = BAR_KG
  return Math.max(0, Math.round(w * 100) / 100)
}

export interface WarmupSet { weight: number; reps: number; bar?: boolean }

/** Ramp-up sets before the first working set. */
export function warmupSets(work: number, count: number, eq: Equipment | undefined): WarmupSet[] {
  if (!work || count <= 0) return []
  const plan: Record<number, [number, number][]> = {
    1: [[0.6, 5]],
    2: [[0.5, 8], [0.75, 3]],
    3: [[0.4, 8], [0.6, 5], [0.8, 3]],
    4: [[0.3, 10], [0.5, 6], [0.7, 4], [0.85, 2]],
  }
  const steps = plan[Math.min(4, count)]
  const out: WarmupSet[] = []
  steps.forEach(([pct, reps], i) => {
    let weight = roundLoad(work * pct, eq)
    if (eq === 'barbell' && i === 0 && count >= 2 && work >= 40) weight = BAR_KG
    if (weight > 0 && weight < work && !out.some((s) => s.weight === weight)) {
      out.push({ weight, reps, bar: eq === 'barbell' && weight === BAR_KG })
    }
  })
  return out
}

/** Plates per side for a barbell load (20 kg bar). */
export function platesPerSide(total: number, bar = BAR_KG): { plates: number[]; rest: number } {
  let side = Math.max(0, (total - bar) / 2)
  const plates: number[] = []
  for (const p of PLATES) {
    while (side >= p - 1e-9) {
      plates.push(p)
      side -= p
    }
  }
  return { plates, rest: Math.round(side * 100) / 100 }
}
