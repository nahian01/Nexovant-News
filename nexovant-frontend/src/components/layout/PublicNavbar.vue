<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowUpRight, BookOpen, Globe2 } from 'lucide-vue-next'

defineProps<{
  transparent?: boolean
}>()
</script>

<template>
  <header class="sticky top-0 z-50 border-b border-[#D0D9CF] bg-[#F6F4EF]/95 backdrop-blur-md">
    <div class="mx-auto flex h-[68px] max-w-7xl items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
      <!-- Brand Logo -->
      <RouterLink to="/" class="group flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-[4px] bg-[#0E1A2B] text-base font-serif font-bold text-white transition-transform group-hover:scale-105">
          N
        </span>
        <span class="flex flex-col leading-none text-left">
          <span class="font-serif text-[20px] font-semibold tracking-tight text-[#0E1A2B]">Nexovant</span>
          <span class="mt-0.5 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#5F6875]">Intelligence Beyond Headlines</span>
        </span>
      </RouterLink>

      <!-- Navigation Links -->
      <nav class="hidden items-center gap-7 text-xs font-medium text-[#3F4A5A] lg:flex">
        <RouterLink to="/#features" class="transition-colors hover:text-[#0E1A2B]">The Platform</RouterLink>
        <RouterLink to="/" class="inline-flex items-center gap-1.5 transition-colors hover:text-[#0E1A2B]">
          <Globe2 class="h-3.5 w-3.5 text-[#274888]" />
          <span>Live Feed</span>
        </RouterLink>
        <RouterLink to="/trends" class="transition-colors hover:text-[#0E1A2B]">Trending Signals</RouterLink>
        <RouterLink to="/billing" class="transition-colors hover:text-[#0E1A2B]">Plans & Pricing</RouterLink>
      </nav>

      <!-- Auth Action Buttons -->
      <div class="flex items-center gap-2">
        <RouterLink to="/login">
          <button type="button" class="px-3.5 py-2 text-xs font-medium text-[#3F4A5A] hover:text-[#0E1A2B] transition-colors rounded-[4px]">
            Sign in
          </button>
        </RouterLink>
        <RouterLink to="/register">
          <button type="button" class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5">
            <span>Join free</span>
            <ArrowUpRight class="h-3.5 w-3.5" />
          </button>
        </RouterLink>
      </div>
    </div>
  </header>
</template>
