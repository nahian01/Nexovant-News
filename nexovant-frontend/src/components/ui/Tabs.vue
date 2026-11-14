<script setup lang="ts">
defineProps<{
  tabs: { key: string; label: string; count?: number }[]
  activeTab: string
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', key: string): void
}>()
</script>

<template>
  <div class="border-b border-nexo-border flex space-x-6 overflow-x-auto text-xs font-sans">
    <button
      v-for="t in tabs"
      :key="t.key"
      @click="emit('update:activeTab', t.key)"
      :class="[
        'pb-3 border-b-2 font-medium transition-all whitespace-nowrap flex items-center space-x-2',
        activeTab === t.key
          ? 'border-nexo-accent-cyan text-nexo-text-primary font-semibold'
          : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary hover:border-nexo-border'
      ]"
    >
      <span>{{ t.label }}</span>
      <span
        v-if="t.count !== undefined"
        :class="[
          'px-2 py-0.5 rounded-full text-[10px] font-mono',
          activeTab === t.key ? 'bg-emerald-50 text-emerald-700 font-bold border border-emerald-200' : 'bg-nexo-bg text-nexo-text-muted'
        ]"
      >
        {{ t.count }}
      </span>
    </button>
  </div>
</template>
