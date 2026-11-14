<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'info' | 'warning' | 'danger' | 'success'
  title?: string
  dismissible?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'info',
  dismissible: false,
})

const emit = defineEmits<{
  (e: 'dismiss'): void
}>()

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-emerald-50 text-emerald-900 border-emerald-200'
    case 'warning':
      return 'bg-amber-50 text-amber-900 border-amber-200'
    case 'danger':
      return 'bg-rose-50 text-rose-900 border-rose-200'
    case 'info':
    default:
      return 'bg-teal-50 text-teal-900 border-teal-200'
  }
})
</script>

<template>
  <div
    :class="[
      'border rounded-2xl p-4 text-sm flex items-start justify-between font-sans transition-all',
      variantClasses,
    ]"
    role="alert"
  >
    <div class="flex flex-col space-y-1">
      <h5 v-if="title" class="font-semibold text-sm leading-tight text-current">
        {{ title }}
      </h5>
      <div class="text-xs leading-relaxed opacity-95">
        <slot />
      </div>
    </div>

    <button
      v-if="dismissible"
      type="button"
      class="text-current opacity-70 hover:opacity-100 p-1 -mr-1 -mt-1 rounded transition-opacity"
      aria-label="Dismiss alert"
      @click="emit('dismiss')"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
