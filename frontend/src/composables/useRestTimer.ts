import { computed, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'

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

const TAG = 'rest-timer'
const notifySupported = typeof window !== 'undefined' && 'Notification' in window

async function closeRestNotifications() {
  try {
    const reg = await navigator.serviceWorker?.getRegistration()
    for (const n of (await reg?.getNotifications({ tag: TAG })) ?? []) n.close()
  } catch { /* ignore */ }
}

/**
 * Rest timer based on an absolute end time, so it stays correct when
 * iOS suspends the page in the background.
 */
export function useRestTimer() {
  const { t } = useI18n()
  const notifyPermission = ref<NotificationPermission | 'unsupported'>(notifySupported ? Notification.permission : 'unsupported')

  /** Must be called from a tap (iOS requires a user gesture for the permission prompt). */
  async function enableNotifications() {
    if (!notifySupported) return 'unsupported' as const
    notifyPermission.value = await Notification.requestPermission()
    return notifyPermission.value
  }

  /** System notification — only useful when the app is in the background; in the foreground the beep is enough. */
  async function notify() {
    if (!notifySupported || Notification.permission !== 'granted') return
    const title = t('workout.restOver')
    const options = {
      body: t('workout.restOverBody'), tag: TAG, renotify: true, vibrate: [200, 100, 200], icon: '/pwa-192.png',
    } as NotificationOptions
    try {
      const reg = await navigator.serviceWorker?.getRegistration()
      if (reg) await reg.showNotification(title, options)
      else new Notification(title, options)
    } catch { /* ignore */ }
  }

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
        if (document.visibilityState === 'hidden') void notify()
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
    void closeRestNotifications()
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

  return { start, add, stop, remaining, active, label, progress, notifyPermission, enableNotifications }
}
