<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowUpRight, BookOpen, Globe2 } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
</script>

<template>
  <header class="sticky top-0 z-50 border-b border-nexo-border/80 bg-white/90 backdrop-blur-xl">
    <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
      <RouterLink to="/" class="group flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-nexo-accent-cyan text-lg font-semibold text-white shadow-cyan-glow transition-transform group-hover:-rotate-3">N</span>
        <span class="flex flex-col leading-none">
          <span class="font-serif text-[19px] font-semibold tracking-tight text-nexo-text-primary">Nexovant</span>
          <span class="mt-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-nexo-text-muted">Intelligence, in context</span>
        </span>
      </RouterLink>

      <nav class="hidden items-center gap-7 text-[13px] font-medium text-nexo-text-secondary lg:flex">
        <RouterLink to="/landing#features" class="transition hover:text-nexo-accent-cyan">The platform</RouterLink>
        <RouterLink to="/feed" class="inline-flex items-center gap-1.5 transition hover:text-nexo-accent-cyan"><Globe2 class="h-3.5 w-3.5" /> Live feed</RouterLink>
        <RouterLink to="/blog" class="inline-flex items-center gap-1.5 transition hover:text-nexo-accent-cyan"><BookOpen class="h-3.5 w-3.5" /> Analysis</RouterLink>
        <RouterLink to="/landing#pricing" class="transition hover:text-nexo-accent-cyan">Pricing</RouterLink>
      </nav>

      <div class="flex items-center gap-1 sm:gap-2">
        <RouterLink to="/login"><Button variant="ghost" size="sm">Sign in</Button></RouterLink>
        <RouterLink to="/register" class="hidden sm:block"><Button variant="primary" size="sm">Get started <ArrowUpRight class="ml-1.5 h-3.5 w-3.5" /></Button></RouterLink>
      </div>
    </div>
  </header>
</template>
