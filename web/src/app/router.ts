import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

import { BRAND_NAME } from '@/config/brand'
import { useSessionStore } from '@/stores/useSessionStore'
import { pageTitles } from '@/strings'

declare module 'vue-router' {
  interface RouteMeta {
    /** Título da aba do navegador (sem o nome da marca). */
    title?: string
    /** Exige login: sem sessão, vai para /entrar?redirect=… */
    requiresAuth?: boolean
    /** Só para visitantes (entrar, criar conta…): logado, vai para o painel. */
    guestOnly?: boolean
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
  // O editor ocupa a tela inteira, fora do shell do painel.
  {
    path: '/painel/formularios/:id',
    name: 'form-builder',
    component: () => import('@/views/panel/FormBuilderView.vue'),
    props: true,
    meta: { title: pageTitles.formEditor, requiresAuth: true },
  },
  // Página de quem responde: layout próprio, sem login.
  {
    path: '/f/:slug',
    component: () => import('@/layouts/RespondentLayout.vue'),
    children: [
      {
        path: '',
        name: 'public-form',
        component: () => import('@/views/public/PublicFormView.vue'),
        props: true,
        meta: { title: pageTitles.publicForm },
      },
      {
        path: 'respostas/:id/editar',
        name: 'public-form-edit',
        component: () => import('@/views/public/EditSubmissionView.vue'),
        props: true,
        meta: { title: pageTitles.editSubmission },
      },
    ],
  },
  {
    path: '/painel',
    component: () => import('@/layouts/AppShellLayout.vue'),
    meta: { requiresAuth: true },
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
        path: 'entrar',
        name: 'login',
        component: () => import('@/views/auth/LoginView.vue'),
        meta: { title: pageTitles.login, guestOnly: true },
      },
      {
        path: 'criar-conta',
        name: 'register',
        component: () => import('@/views/auth/RegisterView.vue'),
        meta: { title: pageTitles.register, guestOnly: true },
      },
      {
        path: 'esqueci-a-senha',
        name: 'forgot-password',
        component: () => import('@/views/auth/ForgotPasswordView.vue'),
        meta: { title: pageTitles.forgotPassword, guestOnly: true },
      },
      {
        path: 'redefinir-senha',
        name: 'reset-password',
        component: () => import('@/views/auth/ResetPasswordView.vue'),
        meta: { title: pageTitles.resetPassword, guestOnly: true },
      },
      {
        // Aberta a todos: o link do email pode ser aberto logado ou não.
        path: 'verificar-email',
        name: 'verify-email',
        component: () => import('@/views/auth/VerifyEmailView.vue'),
        meta: { title: pageTitles.verifyEmail },
      },
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

router.beforeEach(async (to) => {
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth)
  const guestOnly = to.matched.some((record) => record.meta.guestOnly)

  if (!requiresAuth && !guestOnly) {
    return true
  }

  const session = useSessionStore()
  // Sem resposta da API (rede, servidor fora), trata como visitante: a tela de
  // login mostra o erro ao tentar entrar.
  await session.load().catch(() => undefined)

  if (requiresAuth && !session.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (guestOnly && session.isAuthenticated) {
    return { name: 'forms' }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · ${BRAND_NAME}` : BRAND_NAME
})

export default router
