import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

import { BRAND_NAME } from '@/config/brand'
import { pageTitles } from '@/strings'

declare module 'vue-router' {
  interface RouteMeta {
    /** Título da aba do navegador (sem o nome da marca). */
    title?: string
  }
}

// Só em desenvolvimento: o Vite remove este bloco (e o chunk da página) do build de produção.
const devRoutes: RouteRecordRaw[] = import.meta.env.DEV
  ? [
      {
        path: '/styleguide',
        name: 'styleguide',
        component: () => import('@/views/StyleguideView.vue'),
        meta: { title: pageTitles.styleguide },
      },
    ]
  : []

const routes: RouteRecordRaw[] = [
  // A landing chega na fase 6; até lá, o início é o painel.
  { path: '/', redirect: '/painel' },
  {
    path: '/painel',
    component: () => import('@/layouts/AppShellLayout.vue'),
    children: [
      { path: '', redirect: { name: 'forms' } },
      {
        path: 'formularios',
        name: 'forms',
        component: () => import('@/views/panel/FormsView.vue'),
        meta: { title: pageTitles.forms },
      },
      {
        path: 'configuracoes',
        name: 'settings',
        component: () => import('@/views/panel/SettingsView.vue'),
        meta: { title: pageTitles.settings },
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    children: [
      {
        path: 'termos',
        name: 'terms',
        component: () => import('@/views/legal/LegalView.vue'),
        props: { document: 'terms' },
        meta: { title: pageTitles.terms },
      },
      {
        path: 'privacidade',
        name: 'privacy',
        component: () => import('@/views/legal/LegalView.vue'),
        props: { document: 'privacy' },
        meta: { title: pageTitles.privacy },
      },
      ...devRoutes,
      {
        path: ':pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/NotFoundView.vue'),
        meta: { title: pageTitles.notFound },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: (_to, _from, savedPosition) => savedPosition ?? { top: 0 },
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · ${BRAND_NAME}` : BRAND_NAME
})

export default router
