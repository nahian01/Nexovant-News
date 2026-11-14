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
    <!-- Top Global Header -->
    <MainHeader />

    <!-- 3-Column Desktop Grid Container -->
    <div class="page-shell flex min-h-[calc(100vh-68px)] w-full">
      <!-- Left Column: Navigation -->
      <LeftNav />

      <!-- Center Column: Primary News Stream / Content -->
      <main class="min-w-0 flex-1 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <slot />
      </main>

      <!-- Right Column: Categories & Discovery Panel -->
      <div class="hidden shrink-0 lg:block">
        <RightDiscoveryPanel @select-category="(slug) => emit('selectCategory', slug)" />
      </div>
    </div>
  </div>
</template>
