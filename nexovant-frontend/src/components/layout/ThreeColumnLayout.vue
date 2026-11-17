<script setup lang="ts">
import MainHeader from '@/components/layout/MainHeader.vue'
import LeftNav from '@/components/layout/LeftNav.vue'
import RightDiscoveryPanel from '@/components/layout/RightDiscoveryPanel.vue'

const emit = defineEmits<{
  (e: 'selectCategory', slug: string): void
}>()
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-left">
    <!-- Top Global Header with Environment Disclaimer -->
    <MainHeader />

    <!-- 3-Column Desktop Grid Container (Design System: max 1440px, margin 24px, gap 32px) -->
    <div class="mx-auto flex min-h-[calc(100vh-64px)] max-w-[1440px] px-6 gap-8">
      <!-- Left Column: Navigation (224px) -->
      <LeftNav />

      <!-- Center Column: Primary News Stream / Content (fluid 560 to 784px) -->
      <main class="min-w-0 flex-1 space-y-8 py-6">
        <slot />
      </main>

      <!-- Right Column: Categories & Discovery Panel (320px) -->
      <div class="hidden shrink-0 lg:block">
        <RightDiscoveryPanel @select-category="(slug) => emit('selectCategory', slug)" />
      </div>
    </div>
  </div>
</template>
