import { createRouter, createWebHistory } from 'vue-router'
// Public & Core Views
import PublicHomeView from '@/views/public/PublicHomeView.vue'
import StoryDetailView from '@/views/public/StoryDetailView.vue'
import SearchResultsView from '@/views/public/SearchResultsView.vue'
import BlogView from '@/views/public/BlogView.vue'
import PublicArticleDetailView from '@/views/public/PublicArticleDetailView.vue'
import LandingView from '@/views/public/LandingView.vue'
import LoginView from '@/views/public/LoginView.vue'
import RegisterView from '@/views/public/RegisterView.vue'

// Subscriber Views
import DashboardView from '@/views/subscriber/DashboardView.vue'
import TrendsView from '@/views/subscriber/TrendsView.vue'
import CompaniesView from '@/views/subscriber/CompaniesView.vue'
import ReportsView from '@/views/subscriber/ReportsView.vue'
import BillingView from '@/views/subscriber/BillingView.vue'
import OrganizationView from '@/views/subscriber/OrganizationView.vue'

// Administrative Views
import AdminLoginView from '@/views/admin/AdminLoginView.vue'
import AdminDashboardView from '@/views/admin/AdminDashboardView.vue'
import AdminArticlesView from '@/views/admin/AdminArticlesView.vue'
import AdminClustersView from '@/views/admin/AdminClustersView.vue'
import AdminSourcesView from '@/views/admin/AdminSourcesView.vue'
import AdminAutomationView from '@/views/admin/AdminAutomationView.vue'
import AdminSocialIntelligenceView from '@/views/admin/AdminSocialIntelligenceView.vue'
import AIRouterDashboard from '@/views/admin/AIRouterDashboard.vue'
import AdminRagKnowledgeView from '@/views/admin/AdminRagKnowledgeView.vue'
import AdminUsersView from '@/views/admin/AdminUsersView.vue'
import PaymentVerificationView from '@/views/admin/PaymentVerificationView.vue'
import AdminAdvertisingView from '@/views/admin/AdminAdvertisingView.vue'
import AdminReportsView from '@/views/admin/AdminReportsView.vue'
import AdminSystemView from '@/views/admin/AdminSystemView.vue'
import AdminAuditLogsView from '@/views/admin/AdminAuditLogsView.vue'
import AdminTaxonomyView from '@/views/admin/AdminTaxonomyView.vue'
import AdminNotificationsView from '@/views/admin/AdminNotificationsView.vue'

const routes = [
  // 1. Public Intelligence Network
  {
    path: '/',
    name: 'home',
    component: PublicHomeView,
  },
  {
    path: '/feed',
    name: 'feed',
    component: PublicHomeView,
  },
  {
    path: '/news/:slug',
    name: 'story-detail',
    component: StoryDetailView,
  },
  {
    path: '/search',
    name: 'search',
    component: SearchResultsView,
  },
  {
    path: '/blog',
    name: 'blog',
    component: BlogView,
  },
  {
    path: '/blog/:slug',
    name: 'blog-detail',
    component: PublicArticleDetailView,
  },
  {
    path: '/landing',
    name: 'landing',
    component: LandingView,
  },
  {
    path: '/trends',
    name: 'trends',
    component: TrendsView,
  },
  {
    path: '/companies',
    name: 'companies',
    component: CompaniesView,
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
  },
  {
    path: '/register',
    name: 'register',
    component: RegisterView,
  },

  // 2. Subscriber & Workspace Routes
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true },
  },
  {
    path: '/saved',
    name: 'saved',
    component: PublicHomeView,
    meta: { requiresAuth: true },
  },
  {
    path: '/reports',
    name: 'reports',
    component: ReportsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/billing',
    name: 'billing',
    component: BillingView,
    meta: { requiresAuth: true },
  },
  {
    path: '/organization',
    name: 'organization',
    component: OrganizationView,
    meta: { requiresAuth: true },
  },

  // 3. Administrative Control Centre Routes
  {
    path: '/admin/login',
    name: 'admin-login',
    component: AdminLoginView,
  },
  {
    path: '/admin',
    redirect: '/admin/dashboard',
  },
  {
    path: '/admin/dashboard',
    name: 'admin-dashboard',
    component: AdminDashboardView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/articles',
    name: 'admin-articles',
    component: AdminArticlesView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/taxonomy',
    name: 'admin-taxonomy',
    component: AdminTaxonomyView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/clusters',
    name: 'admin-clusters',
    component: AdminClustersView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/sources',
    name: 'admin-sources',
    component: AdminSourcesView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/automation',
    name: 'admin-automation',
    component: AdminAutomationView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/social-intelligence',
    name: 'admin-social-intelligence',
    component: AdminSocialIntelligenceView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/ai-router',
    name: 'admin-ai-router',
    component: AIRouterDashboard,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/rag-knowledge',
    name: 'admin-rag-knowledge',
    component: AdminRagKnowledgeView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/users',
    name: 'admin-users',
    component: AdminUsersView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/payments',
    name: 'admin-payments',
    component: PaymentVerificationView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/notifications',
    name: 'admin-notifications',
    component: AdminNotificationsView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/advertising',
    name: 'admin-advertising',
    component: AdminAdvertisingView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/reports',
    name: 'admin-reports',
    component: AdminReportsView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/system',
    name: 'admin-system',
    component: AdminSystemView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/audit-logs',
    name: 'admin-audit-logs',
    component: AdminAuditLogsView,
    meta: { requiresAuth: true, requiresAdmin: true },
  },

  // Catch-all
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to, _from, next) => {
  const token = localStorage.getItem('nxv_auth_token')

  // Check admin portal protection
  if (to.meta.requiresAdmin) {
    if (!token) {
      return next({ name: 'admin-login', query: { redirect: to.fullPath } })
    }
  }

  // Check general subscriber protection
  if (to.meta.requiresAuth && !token) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // If visiting /admin/login while already authenticated with token, allow through or redirect
  next()
})

export default router
