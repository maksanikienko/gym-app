import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    { path: '/register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true } },
    { path: '/', component: () => import('@/views/TodayView.vue') },
    { path: '/programs', component: () => import('@/views/ProgramsView.vue') },
    { path: '/builder', component: () => import('@/views/BuilderView.vue'), meta: { focus: true } },
    { path: '/builder/:id', component: () => import('@/views/BuilderView.vue'), props: true, meta: { focus: true } },
    { path: '/programs/:id', component: () => import('@/views/ProgramView.vue'), props: true },
    { path: '/workout/:id', component: () => import('@/views/WorkoutView.vue'), props: true, meta: { focus: true } },
    { path: '/history', component: () => import('@/views/HistoryView.vue') },
    { path: '/exercises/:id', component: () => import('@/views/ExerciseHistoryView.vue'), props: true },
    { path: '/settings', component: () => import('@/views/SettingsView.vue') },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  await auth.init()
  if (to.meta.guest) return auth.user ? '/' : true
  if (!auth.user) return { path: '/login', query: { redirect: to.fullPath } }
  return true
})
