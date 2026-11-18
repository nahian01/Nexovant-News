<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import {
  Activity,
  Banknote,
  Bell,
  Bookmark,
  Building2,
  ChartNoAxesCombined,
  Compass,
  CreditCard,
  FileText,
  Globe2,
  LayoutDashboard,
  Library,
  Radar,
  ShieldCheck,
  Sliders,
  Sparkles,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()

const workspaceItems = [
  { name: 'Overview', path: '/dashboard', icon: LayoutDashboard },
  { name: 'For You', path: '/feed', icon: Sparkles },
  { name: 'Following', path: '/following', icon: Users },
  { name: 'Trending Intelligence', path: '/trends', icon: ChartNoAxesCombined },
  { name: 'Global Companies', path: '/companies', icon: Building2 },
  { name: 'Watchlists', path: '/workspace/watchlists', icon: Bookmark },
  { name: 'Custom Monitors', path: '/workspace/monitors', icon: Radar },
  { name: 'Saved Searches', path: '/workspace/saved-searches', icon: Bookmark },
  { name: 'Saved Intelligence', path: '/saved', icon: Library },
  { name: 'Deep Research', path: '/workspace/research', icon: Compass },
  { name: 'Intelligence Reports', path: '/reports', icon: FileText },
  { name: 'Alerts & Updates', path: '/workspace/notifications', icon: Bell },
]

const accountItems = [
  { name: 'Team Workspace', path: '/organization', icon: Building2 },
  { name: 'Subscription & Billing', path: '/billing', icon: Banknote },
  { name: 'Settings & Preferences', path: '/workspace/preferences', icon: Sliders },
]

const adminItems = [
  { name: 'Admin Console', path: '/admin/dashboard', icon: ShieldCheck },
  { name: 'Intelligence Control', path: '/admin/intelligence', icon: Activity },
  { name: 'AI Gateway', path: '/admin/ai-router', icon: Activity },
  { name: 'Payment Review', path: '/admin/payments', icon: CreditCard },
  { name: 'Search Analytics', path: '/admin/search-analytics', icon: Activity },
]

const isActive = (path: string) => {
  if (path === '/dashboard') return route.path === '/dashboard'
  if (path === '/feed') return route.path === '/feed'
  if (path === '/saved') return route.path === '/saved' || route.path === '/workspace/library'
  if (path === '/following') return route.path === '/following'
  return route.path === path || route.path.startsWith(`${path}/`)
}
</script>

<template>
  <aside class="sticky top-0 hidden h-screen w-[260px] shrink-0 flex-col justify-between border-r border-nexo-border/80 bg-white px-3 py-5 text-left lg:flex">
    <div class="overflow-y-auto pr-1">
      <!-- Logo Header -->
      <RouterLink to="/" class="mb-6 flex items-center gap-3 px-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-nexo-accent-cyan text-lg font-semibold text-white shadow-xs">N</span>
        <span class="flex flex-col leading-none">
          <span class="font-serif text-[18px] font-semibold tracking-tight text-nexo-text-primary">Nexovant</span>
          <span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.15em] text-nexo-text-muted">Intelligence Beyond Headlines</span>
        </span>
      </RouterLink>

      <!-- Workspace Navigation -->
      <nav class="space-y-0.5">
        <p class="px-3 pb-2 pt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">Workspace</p>
        <RouterLink
          v-for="item in workspaceItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium transition',
            isActive(item.path)
              ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold shadow-2xs'
              : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
          ]"
        >
          <component :is="item.icon" class="h-[17px] w-[17px] shrink-0" />
          <span class="truncate">{{ item.name }}</span>
        </RouterLink>
      </nav>

      <!-- Account & Organization -->
      <nav class="mt-5 space-y-0.5 border-t border-nexo-border/70 pt-4">
        <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">Account & Team</p>
        <RouterLink
          v-for="item in accountItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium transition',
            isActive(item.path)
              ? 'bg-[#e9f3ed] text-nexo-accent-cyan font-semibold shadow-2xs'
              : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
          ]"
        >
          <component :is="item.icon" class="h-[17px] w-[17px] shrink-0" />
          <span class="truncate">{{ item.name }}</span>
        </RouterLink>
      </nav>

      <!-- Administrative Navigation (Admin Only) -->
      <nav v-if="authStore.user?.account_type === 'admin'" class="mt-5 space-y-0.5 border-t border-nexo-border/70 pt-4">
        <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-nexo-accent-gold">Administration</p>
        <RouterLink
          v-for="item in adminItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium transition',
            isActive(item.path)
              ? 'bg-amber-50 text-nexo-accent-gold font-semibold shadow-2xs'
              : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
          ]"
        >
          <component :is="item.icon" class="h-[17px] w-[17px] shrink-0" />
          <span class="truncate">{{ item.name }}</span>
        </RouterLink>
      </nav>
    </div>

    <!-- Active Plan Footer Card -->
    <div class="mt-4 rounded-2xl border border-nexo-border bg-nexo-bg p-3.5">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-nexo-text-primary">Executive Tier</span>
        <span class="rounded-full bg-[#e9f3ed] px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-nexo-accent-cyan">Active</span>
      </div>
      <p class="mt-1.5 text-[11px] leading-relaxed text-nexo-text-secondary">
        Verified intelligence, custom monitors & grounded deep research.
      </p>
    </div>
  </aside>
</template>
