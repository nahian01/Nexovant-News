<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Activity,
  Bell,
  Bot,
  BrainCircuit,
  Building2,
  CheckCheck,
  CircleDollarSign,
  CreditCard,
  FileText,
  FolderTree,
  Globe2,
  Layers3,
  LogOut,
  Megaphone,
  Menu,
  Network,
  Newspaper,
  Radio,
  Search,
  Settings2,
  ShieldCheck,
  Sliders,
  Sparkles,
  Users,
  Wrench,
  X,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const mobileNavOpen = ref(false)
const globalSearch = ref('')

const adminNavGroups = [
  {
    title: 'Operations & Telemetry',
    items: [
      { name: 'Dashboard Overview', path: '/admin/dashboard', icon: Activity },
      { name: 'Notifications & Dispatch', path: '/admin/notifications', icon: Bell },
      { name: 'Search Telemetry & Feedback', path: '/admin/search-analytics', icon: Search },
    ],
  },
  {
    title: 'Editorial & Taxonomy',
    items: [
      { name: 'Articles & Override Locks', path: '/admin/articles', icon: Newspaper },
      { name: 'Taxonomy & Knowledge DAG', path: '/admin/taxonomy', icon: FolderTree },
      { name: 'Story Clusters (Merge/Split)', path: '/admin/clusters', icon: Layers3 },
      { name: 'News Sources & RSS Feeds', path: '/admin/sources', icon: Radio },
    ],
  },
  {
    title: 'AI Intelligence Infrastructure',
    items: [
      { name: 'Intelligence Control Center', path: '/admin/intelligence', icon: Sparkles },
      { name: 'Deep Research & Graph', path: '/admin/research', icon: Network },
      { name: 'AI Gateway & Credentials', path: '/admin/ai-router', icon: Bot },
      { name: '14-Stage Pipeline Queue', path: '/admin/automation', icon: Wrench },
      { name: 'RAG Knowledge Center', path: '/admin/rag-knowledge', icon: BrainCircuit },
      { name: 'Social Intelligence', path: '/admin/social-intelligence', icon: Globe2 },
    ],
  },
  {
    title: 'Business & Access Control',
    items: [
      { name: 'Users & Permissions', path: '/admin/users', icon: Users },
      { name: 'Enterprise Organizations', path: '/admin/organizations', icon: Building2 },
      { name: 'Subscriber Intelligence', path: '/admin/subscriber-intelligence', icon: Sliders },
      { name: 'Billing & Gateways', path: '/admin/billing', icon: CreditCard },
      { name: 'Payment Review Queue', path: '/admin/payments', icon: CircleDollarSign },
      { name: 'Advertising Campaigns', path: '/admin/advertising', icon: Megaphone },
      { name: 'Reports & Publications', path: '/admin/reports', icon: FileText },
    ],
  },
  {
    title: 'Platform Governance',
    items: [
      { name: 'System Configuration', path: '/admin/system', icon: Settings2 },
      { name: 'Security & Audit Logs', path: '/admin/audit-logs', icon: ShieldCheck },
    ],
  },
]

const isActive = (path: string) => route.path === path || route.path.startsWith(`${path}/`)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/admin/login')
}
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left lg:flex selection:bg-amber-100 selection:text-nexo-accent-gold">
    <!-- Desktop Left Sidebar -->
    <aside class="sticky top-0 hidden h-screen w-[270px] shrink-0 flex-col border-r border-nexo-border/80 bg-white lg:flex">
      <!-- Brand header -->
      <div class="flex h-[68px] items-center justify-between border-b border-nexo-border px-5">
        <RouterLink to="/admin/dashboard" class="flex items-center gap-3">
          <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#fff4dc] text-sm font-semibold text-nexo-accent-gold border border-amber-200 shadow-xs">N</span>
          <span class="flex flex-col leading-none">
            <span class="font-serif text-base font-semibold tracking-tight text-nexo-text-primary">Nexovant</span>
            <span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.15em] text-nexo-accent-gold">Control Room</span>
          </span>
        </RouterLink>
        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-nexo-accent-gold border border-amber-200">
          Admin
        </span>
      </div>

      <!-- Navigation groups -->
      <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
        <div v-for="group in adminNavGroups" :key="group.title" class="space-y-1">
          <p class="px-3 pb-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">{{ group.title }}</p>
          <RouterLink
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            :class="[
              'flex items-center gap-2.5 rounded-xl px-3 py-2 text-[12px] font-medium transition',
              isActive(item.path)
                ? 'bg-amber-50 text-nexo-accent-gold font-semibold shadow-xs border border-amber-200/50'
                : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
            ]"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ item.name }}</span>
          </RouterLink>
        </div>
      </nav>

      <!-- User footer & logout -->
      <div class="border-t border-nexo-border bg-nexo-bg/60 p-3.5 space-y-2.5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#fff4dc] text-xs font-semibold text-nexo-accent-gold border border-amber-200">
              {{ authStore.user?.name?.charAt(0).toUpperCase() || 'A' }}
            </span>
            <div class="min-w-0 flex-1">
              <div class="truncate text-xs font-semibold text-nexo-text-primary">{{ authStore.user?.name || 'Administrator' }}</div>
              <div class="truncate text-[10px] font-mono text-nexo-text-muted">{{ authStore.user?.email || 'admin@nexovant.si' }}</div>
            </div>
          </div>

          <button
            type="button"
            @click="handleLogout"
            class="p-1.5 rounded-lg text-nexo-text-muted hover:text-red-600 hover:bg-red-50 transition"
            title="Sign out of Admin Control Room"
          >
            <LogOut class="h-4 w-4" />
          </button>
        </div>

        <RouterLink
          to="/"
          class="flex items-center gap-1.5 text-[11px] font-medium text-nexo-accent-cyan hover:text-nexo-text-primary transition px-1"
        >
          <Globe2 class="h-3.5 w-3.5" /> Return to public network
        </RouterLink>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="min-w-0 flex-1">
      <header class="sticky top-0 z-30 flex h-[68px] items-center justify-between border-b border-nexo-border/80 bg-white/95 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-lg border border-nexo-border p-2 text-nexo-text-secondary lg:hidden"
            @click="mobileNavOpen = true"
            aria-label="Open admin navigation"
          >
            <Menu class="h-4 w-4" />
          </button>

          <div class="flex items-center gap-2 text-xs text-nexo-text-muted">
            <RouterLink to="/admin/dashboard" class="font-medium hover:text-nexo-text-primary transition">Control Room</RouterLink>
            <span>/</span>
            <span class="font-semibold capitalize text-nexo-text-primary">
              {{ route.path.split('/')[2]?.replace('-', ' ') || 'Dashboard' }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-medium text-emerald-800">
            <CheckCheck class="h-3.5 w-3.5" />
            <span class="hidden sm:inline">All cognitive subsystems operational</span>
            <span class="sm:hidden">Operational</span>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full max-w-[1520px] space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <slot />
      </main>
    </div>

    <!-- Mobile Drawer -->
    <div v-if="mobileNavOpen" class="fixed inset-0 z-50 flex lg:hidden" @click.self="mobileNavOpen = false">
      <div class="absolute inset-0 bg-[#10291f]/40 backdrop-blur-sm" @click="mobileNavOpen = false"></div>
      <aside class="relative flex h-full w-[min(86vw,320px)] flex-col bg-white shadow-2xl">
        <div class="flex h-[68px] items-center justify-between border-b border-nexo-border px-5">
          <span class="font-serif text-lg font-semibold text-nexo-text-primary">Control Room</span>
          <button type="button" class="rounded-lg p-2 text-nexo-text-secondary hover:bg-nexo-bg" aria-label="Close navigation" @click="mobileNavOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
          <div v-for="group in adminNavGroups" :key="group.title" class="space-y-1">
            <p class="px-3 pb-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">{{ group.title }}</p>
            <RouterLink
              v-for="item in group.items"
              :key="item.path"
              :to="item.path"
              @click="mobileNavOpen = false"
              :class="[
                'flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-medium transition',
                isActive(item.path) ? 'bg-amber-50 text-nexo-accent-gold font-semibold' : 'text-nexo-text-secondary hover:bg-nexo-bg'
              ]"
            >
              <component :is="item.icon" class="h-4 w-4" />
              <span>{{ item.name }}</span>
            </RouterLink>
          </div>
        </nav>

        <div class="border-t border-nexo-border p-4">
          <button
            type="button"
            @click="handleLogout"
            class="w-full flex items-center justify-center gap-2 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-semibold hover:bg-red-100 transition"
          >
            <LogOut class="h-4 w-4" /> Sign Out
          </button>
        </div>
      </aside>
    </div>
  </div>
</template>
