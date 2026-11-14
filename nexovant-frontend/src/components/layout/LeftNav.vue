<script setup lang="ts">
import { computed } from 'vue'
import { ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  Bookmark,
  Building2,
  ChartNoAxesCombined,
  CreditCard,
  FileText,
  Globe2,
  House,
  MoreHorizontal,
  Newspaper,
  ShieldCheck,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const mobileMenuOpen = ref(false)

const mainNavItems = [
  { name: 'Overview', path: '/', icon: House },
  { name: 'Global feed', path: '/feed', icon: Globe2 },
  { name: 'Trending signals', path: '/trends', icon: ChartNoAxesCombined },
  { name: 'Companies', path: '/companies', icon: Building2 },
  { name: 'Executive coverage', path: '/blog', icon: Newspaper },
  { name: 'Saved stories', path: '/saved', icon: Bookmark, requiresAuth: true },
  { name: 'Briefings & reports', path: '/reports', icon: FileText, requiresAuth: true },
]

const workspaceItems = [
  { name: 'Billing', path: '/billing', icon: CreditCard, requiresAuth: true },
  { name: 'Team workspace', path: '/organization', icon: Users, requiresAuth: true },
]

const isActive = (path: string) => {
  if (path === '/') return route.path === '/'
  if (path === '/feed') return route.path === '/feed' || route.path === '/news' || route.path === '/saved'
  return route.path === path || route.path.startsWith(`${path}/`)
}

const mobileItems = computed(() => mainNavItems.slice(0, 4))
</script>

<template>
  <aside class="sticky top-[68px] hidden h-[calc(100vh-68px)] w-[244px] shrink-0 flex-col justify-between overflow-y-auto border-r border-nexo-border/80 bg-white/80 px-3 py-6 text-left md:flex">
    <div class="space-y-8">
      <nav class="space-y-1">
        <p class="eyebrow px-3 pb-2">Explore</p>
        <RouterLink
          v-for="item in mainNavItems"
          :key="item.name"
          :to="(!authStore.isAuthenticated && item.requiresAuth) ? '/register?prompt=save' : item.path"
          :class="[
            'group flex items-center justify-between rounded-xl px-3 py-2.5 text-[13px] font-medium transition-all',
            isActive(item.path)
              ? 'bg-[#e9f3ed] text-nexo-accent-cyan'
              : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
          ]"
        >
          <span class="flex items-center gap-3">
            <component :is="item.icon" class="h-[17px] w-[17px] transition-transform group-hover:scale-105" />
            {{ item.name }}
          </span>
          <span v-if="!authStore.isAuthenticated && item.requiresAuth" class="rounded-full bg-white px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-nexo-text-muted">Free</span>
        </RouterLink>
      </nav>

      <nav class="space-y-1 border-t border-nexo-border/80 pt-5">
        <p class="eyebrow px-3 pb-2">Workspace</p>
        <RouterLink
          v-for="item in workspaceItems"
          :key="item.name"
          :to="(!authStore.isAuthenticated && item.requiresAuth) ? '/register' : item.path"
          :class="[
            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition-all',
            isActive(item.path) ? 'bg-[#e9f3ed] text-nexo-accent-cyan' : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
          ]"
        >
          <component :is="item.icon" class="h-[17px] w-[17px]" />
          {{ item.name }}
        </RouterLink>
      </nav>

      <nav v-if="authStore.user?.account_type === 'admin'" class="space-y-1 border-t border-nexo-border/80 pt-5">
        <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-nexo-accent-gold">Administration</p>
        <RouterLink to="/admin/dashboard" class="flex items-center gap-3 rounded-xl border border-amber-200/70 bg-amber-50/60 px-3 py-2.5 text-[13px] font-medium text-nexo-accent-gold transition hover:bg-amber-50">
          <ShieldCheck class="h-[17px] w-[17px]" /> Admin console
        </RouterLink>
      </nav>
    </div>

    <div class="mt-8 rounded-2xl bg-[#0d2a23] p-4 text-white shadow-executive border border-emerald-900/40">
      <div class="flex items-center justify-between text-[10px] font-mono uppercase tracking-wider text-[#93d2af]">
        <span class="flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
          Live Wire Active
        </span>
        <span class="text-white/60">24/7</span>
      </div>
      <div class="mt-2.5 font-serif text-sm font-medium text-white leading-snug">
        Executive Edition
      </div>
      <p class="mt-1 text-[11px] leading-relaxed text-[#b7d8ca]/80">
        Dispatches verified across regulatory registries, filings, and wire feeds.
      </p>
      <RouterLink
        v-if="!authStore.isAuthenticated"
        to="/register"
        class="mt-3.5 inline-flex w-full items-center justify-center rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/30 px-3 py-2 text-xs font-semibold text-emerald-200 transition"
      >
        Sign up for full access
      </RouterLink>
    </div>
  </aside>

  <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-nexo-border bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] pt-2 shadow-[0_-8px_30px_rgba(20,48,35,0.08)] backdrop-blur-xl md:hidden" aria-label="Primary navigation">
    <RouterLink v-for="item in mobileItems" :key="item.path" :to="(!authStore.isAuthenticated && item.requiresAuth) ? '/register?prompt=save' : item.path" :class="['flex flex-col items-center gap-1 px-1 py-1.5 text-[10px] font-medium transition', isActive(item.path) ? 'text-nexo-accent-cyan' : 'text-nexo-text-muted']">
      <component :is="item.icon" class="h-[18px] w-[18px]" />
      <span>{{ item.name === 'Trending signals' ? 'Trending' : item.name === 'Global feed' ? 'Feed' : item.name }}</span>
    </RouterLink>
    <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" :class="['flex flex-col items-center gap-1 px-1 py-1.5 text-[10px] font-medium transition', mobileMenuOpen ? 'text-nexo-accent-cyan' : 'text-nexo-text-muted']" aria-label="More navigation"><MoreHorizontal class="h-[18px] w-[18px]" /><span>More</span></button>
  </nav>
  <div v-if="mobileMenuOpen" class="fixed bottom-[4.75rem] right-3 z-50 w-60 rounded-2xl border border-nexo-border bg-white p-2 shadow-xl md:hidden">
    <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-nexo-text-muted">Explore Nexovant</p>
    <RouterLink v-for="item in mainNavItems.slice(4)" :key="item.path" :to="(!authStore.isAuthenticated && item.requiresAuth) ? '/register?prompt=save' : item.path" @click="mobileMenuOpen = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"><component :is="item.icon" class="h-4 w-4" />{{ item.name }}</RouterLink>
    <RouterLink v-for="item in workspaceItems" :key="item.path" :to="(!authStore.isAuthenticated && item.requiresAuth) ? '/register' : item.path" @click="mobileMenuOpen = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"><component :is="item.icon" class="h-4 w-4" />{{ item.name }}</RouterLink>
  </div>
</template>
