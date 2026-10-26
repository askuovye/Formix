import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

// Só em desenvolvimento: o Vite remove este bloco (e o chunk da página) do build de produção.
const devRoutes: RouteRecordRaw[] = import.meta.env.DEV
  ? [
      {
        path: '/styleguide',
        name: 'styleguide',
        component: () => import('@/features/styleguide/StyleguidePage.vue'),
      },
    ]
  : []

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...devRoutes],
})

export default router
