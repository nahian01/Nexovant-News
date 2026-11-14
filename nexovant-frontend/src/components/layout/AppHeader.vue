<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { RouterLink, useRouter } from 'vue-router'
import { Activity, LogOut, Search } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import GlobalSearchModal from '@/components/layout/GlobalSearchModal.vue'

const authStore = useAuthStore()
const router = useRouter()
const isSearchOpen = ref(false)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <header class="sticky top-0 z-30 flex h-[68px] items-center justify-between border-b border-nexo-border/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
    <div class="flex items-center gap-3 text-xs text-nexo-text-muted sm:gap-5">
      <RouterLink to="/" class="flex items-center gap-2 lg:hidden">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-nexo-accent-cyan text-sm font-semibold text-white">N</span>
        <span class="font-serif text-sm font-semibold tracking-tight text-nexo-text-primary sm:hidden">Nexovant</span>
      </RouterLink>
      <div class="hidden items-center gap-2 rounded-full border border-nexo-border bg-nexo-bg px-3 py-2 font-medium lg:flex">
        <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-40"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"></span></span>
        Intelligence feed active
      </div>
      <span class="hidden items-center gap-1.5 font-medium text-nexo-text-secondary sm:flex"><Activity class="h-4 w-4 text-nexo-accent-cyan" /> Verification engine <span class="text-nexo-text-muted">v1.0</span></span>
      <button class="flex items-center gap-2 rounded-lg border border-nexo-border bg-nexo-bg px-2.5 py-2 text-xs text-nexo-text-muted transition hover:border-nexo-accent-cyan/40 hover:text-nexo-text-secondary md:hidden" aria-label="Search" type="button" @click="isSearchOpen = true"><Search class="h-4 w-4" /><span>Search</span></button>
    </div>

    <div class="flex items-center gap-3 sm:gap-4">
      <div v-if="authStore.user" class="flex items-center gap-2.5">
        <div class="hidden text-right sm:block">
          <div class="text-xs font-semibold text-nexo-text-primary">{{ authStore.user.name }}</div>
          <div class="mt-0.5 text-[10px] font-medium capitalize text-nexo-text-muted">{{ authStore.user.account_type.replace('_', ' ') }}</div>
        </div>
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#e9f3ed] text-xs font-semibold text-nexo-accent-cyan">{{ authStore.user.name.charAt(0).toUpperCase() }}</span>
        <Badge variant="confirmed" size="sm" :dot="false">Active</Badge>
      </div>
      <Button variant="ghost" size="sm" @click="handleLogout"><LogOut class="mr-1.5 h-3.5 w-3.5" /> Sign out</Button>
    </div>
  </header>
  <GlobalSearchModal :is-open="isSearchOpen" @close="isSearchOpen = false" />
</template>
