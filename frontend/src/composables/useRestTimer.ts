import { computed, onBeforeUnmount, ref } from 'vue'

const KEY = 'gym.rest.endsAt'
let audio: AudioContext | null = null

function beep() {
  try {
    if (!audio) return
    const now = audio.currentTime
    for (const offset of [0, 0.25, 0.5]) {
      const osc = audio.createOscillator()
      const gain = audio.createGain()
      osc.frequency.value = 880
      gain.gain.setValueAtTime(0.25, now + offset)
      gain.gain.exponentialRampToValueAtTime(0.001, now + offset + 0.18)
      osc.connect(gain).connect(audio.destination)
      osc.start(now + offset)
      osc.stop(now + offset + 0.2)
    }
  } catch { /* audio not available */ }
  navigator.vibrate?.([200, 100, 200])
}

/**
 * Rest timer based on an absolute end time, so it stays correct when
 * iOS suspends the page in the background.
 */
export function useRestTimer() {
  const endsAt = ref<number | null>(null)
  const total = ref(0)
  const now = ref(Date.now())
  let tick: number | undefined

  try {
    const saved = Number(localStorage.getItem(KEY))
    if (saved > Date.now()) endsAt.value = saved
  } catch { /* ignore */ }

  function persist() {
    try {
      if (endsAt.value) localStorage.setItem(KEY, String(endsAt.value))
      else localStorage.removeItem(KEY)
    } catch { /* ignore */ }
  }

  function loop() {
    clearInterval(tick)
    tick = window.setInterval(() => {
      now.value = Date.now()
      if (endsAt.value && now.value >= endsAt.value) {
        endsAt.value = null
        persist()
        clearInterval(tick)
        beep()
      }
    }, 250)
  }
  if (endsAt.value) loop()

  function start(seconds: number) {
    // Must run inside a user gesture on iOS to unlock audio.
    try {
      audio ??= new AudioContext()
      void audio.resume()
    } catch { /* ignore */ }
    total.value = seconds
    now.value = Date.now()
    endsAt.value = now.value + seconds * 1000
    persist()
    loop()
  }

  function add(seconds: number) {
    if (!endsAt.value) return
    endsAt.value += seconds * 1000
    total.value += seconds
    persist()
  }

  function stop() {
    endsAt.value = null
    persist()
    clearInterval(tick)
  }

  onBeforeUnmount(() => clearInterval(tick))

  const remaining = computed(() => (endsAt.value ? Math.max(0, Math.ceil((endsAt.value - now.value) / 1000)) : 0))
  const active = computed(() => endsAt.value !== null)
  const label = computed(() => {
    const s = remaining.value
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
  })
  const progress = computed(() => (total.value ? (remaining.value / total.value) * 100 : 0))

  return { start, add, stop, remaining, active, label, progress }
}
