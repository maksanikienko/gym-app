export type Locale = 'ru' | 'en'
export type Equipment = 'barbell' | 'dumbbell' | 'machine' | 'cable' | 'bodyweight' | 'other'

export interface Advice {
  basis: string
  weight_kg: number | null
  reps: number | null
  seconds: number | null
  params: Record<string, number | string>
  e1rm: number | null
  last_date: string | null
}

export interface RecordHit {
  type: 'weight' | 'e1rm' | 'reps'
  value: number
  previous: number | null
}

export interface RecordPoint { weight_kg: number; reps: number; rpe: number | null; date: string; session_id: number }

export interface ExerciseRecord {
  exercise: Exercise
  heaviest: RecordPoint
  best_e1rm: (RecordPoint & { e1rm: number }) | null
  last_date: string
  sessions: number
}

export interface User {
  id: number
  name: string
  email: string
  locale: Locale
}

export interface Exercise {
  id: number
  slug: string
  name_en: string
  name_ru: string | null
  muscles: string[]
  equipment: Equipment
  video_url: string | null
  custom: boolean
  substitutes?: (Exercise & { note_en: string | null; note_ru: string | null })[]
}

export interface Slot {
  id: number
  position: number
  sets: number
  warmup_sets: number | null
  reps: number | null
  reps_max: number | null
  reps_text: string | null
  seconds: number | null
  target_rpe: number | null
  load_pct: number | null
  rest_min: number | null
  rest_max: number | null
  notes_en: string | null
  notes_ru: string | null
  exercise: Exercise
  advice?: Record<string, Advice>
}

export interface ProgramSummary {
  id: number
  slug: string
  name_en: string
  name_ru: string | null
  description_en: string | null
  description_ru: string | null
  days_per_week: number
  weeks_count: number
  visibility: 'public' | 'private'
  source: 'system' | 'user' | 'import'
  is_mine: boolean
  kind: 'template' | 'library' | 'custom'
}

export interface ProgramDay {
  id: number
  used?: boolean
  day_no: number
  title_en: string
  title_ru: string | null
  exercises: Slot[]
}

export interface Program extends ProgramSummary {
  weeks: { id: number; week_no: number; block: string | null; days: ProgramDay[] }[]
}

export type SessionStatus = 'in_progress' | 'finished' | 'skipped'
export type DayStatus = SessionStatus | 'next' | 'locked'

export interface CycleDay {
  id: number
  week_no: number
  day_no: number
  title_en: string
  title_ru: string | null
  status: DayStatus
  session_id: number | null
  exercises: { name_en: string; name_ru: string | null; sets: number; reps: number | null; reps_max: number | null; reps_text: string | null; seconds: number | null }[]
}

export interface Cycle {
  id: number
  started_at: string
  program: ProgramSummary
  progress: { done: number; total: number }
  open_session: {
    id: number
    started_at: string
    week_no: number | null
    title_en: string | null
    title_ru: string | null
    sets_count: number
  } | null
  next_day_id: number | null
  days: CycleDay[]
}

export interface SetLog {
  id: number
  day_exercise_id: number | null
  exercise_id: number
  set_no: number
  is_warmup: boolean
  weight_kg: number | null
  reps: number | null
  seconds: number | null
  rpe: number | null
  records?: RecordHit[]
}

export interface LastPerformance {
  session_id: number
  date: string
  sets: SetLog[]
}

export interface Session {
  id: number
  status: SessionStatus
  started_at: string
  finished_at: string | null
  note: string | null
  /** Slot ids the user chose to skip in this workout (their logged sets are kept). */
  skipped_slots: number[]
  day: { id: number; week_no: number; block: string | null; day_no: number; title_en: string; title_ru: string | null } | null
  slots: Slot[]
  sets: SetLog[]
  previous: Record<string, LastPerformance>
}

export interface SessionSummary {
  id: number
  status: SessionStatus
  started_at: string
  finished_at: string | null
  duration_min: number | null
  day: { week_no: number; title_en: string; title_ru: string | null } | null
  note: string | null
  sets_count: number
  volume_kg: number
}

export interface ExerciseHistory {
  exercise: Exercise
  best_e1rm: number | null
  entries: { session_id: number; date: string; sets: SetLog[]; best_e1rm: number | null; top_weight: number | null; volume_kg: number }[]
}
