<script setup lang="ts">
interface Props {
  hoverable?: boolean
  padding?: 'none' | 'sm' | 'md' | 'lg'
}

withDefaults(defineProps<Props>(), {
  hoverable: false,
  padding: 'md',
})
</script>

<template>
  <div
    :class="[
      'executive-surface flex flex-col',
      hoverable ? 'executive-surface-hover cursor-pointer' : '',
    ]"
  >
    <!-- Card Header -->
    <div
      v-if="$slots.header"
      class="px-5 py-4 border-b border-nexo-border/80 flex items-center justify-between"
    >
      <slot name="header" />
    </div>

    <!-- Card Body -->
    <div
      :class="[
        padding === 'none' ? 'p-0' : '',
        padding === 'sm' ? 'p-3.5' : '',
        padding === 'md' ? 'p-5' : '',
        padding === 'lg' ? 'p-7' : '',
      ]"
    >
      <slot />
    </div>

    <!-- Card Footer -->
    <div
      v-if="$slots.footer"
      class="px-5 py-3.5 border-t border-nexo-border/80 bg-nexo-elevated/20 text-xs text-nexo-text-secondary flex items-center justify-between rounded-b-lg"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
