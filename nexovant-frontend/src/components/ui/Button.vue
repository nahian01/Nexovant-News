<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  variant?: 'primary' | 'secondary' | 'gold' | 'outline' | 'ghost' | 'danger'
  size?: 'xs' | 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
  type?: 'button' | 'submit' | 'reset'
  block?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  type: 'button',
  block: false,
})

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void
}>()

const handleClick = (event: MouseEvent) => {
  if (!props.disabled && !props.loading) {
    emit('click', event)
  }
}

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-nexo-accent-cyan text-white font-semibold hover:bg-nexo-accent-cyan-hover focus-visible:ring-nexo-accent-cyan active:bg-nexo-accent-cyan/90 shadow-cyan-glow'
    case 'secondary':
      return 'bg-nexo-surface text-nexo-text-primary border border-nexo-border font-medium hover:border-nexo-accent-cyan/50 hover:bg-nexo-elevated focus-visible:ring-nexo-accent-cyan'
    case 'gold':
      return 'bg-nexo-accent-gold text-white font-semibold hover:bg-nexo-accent-gold-hover focus-visible:ring-nexo-accent-gold shadow-gold-glow'
    case 'outline':
      return 'border border-nexo-accent-cyan text-nexo-accent-cyan bg-transparent hover:bg-nexo-accent-cyan/10 focus-visible:ring-nexo-accent-cyan'
    case 'ghost':
      return 'text-nexo-text-secondary hover:text-nexo-text-primary hover:bg-nexo-elevated/50 focus-visible:ring-nexo-accent-cyan'
    case 'danger':
      return 'bg-nexo-status-danger text-white font-medium hover:bg-red-600 focus-visible:ring-red-500'
    default:
      return ''
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'px-2 py-0.5 text-[11px]'
    case 'sm':
      return 'px-3 py-1.5 text-xs'
    case 'lg':
      return 'px-6 py-3.5 text-base tracking-wide'
    case 'md':
    default:
      return 'px-4 py-2 text-sm'
  }
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center font-sans rounded-xl transition-all duration-200 relative select-none disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none hover:-translate-y-px active:translate-y-0',
      variantClasses,
      sizeClasses,
      block ? 'w-full' : '',
    ]"
    @click="handleClick"
  >
    <!-- Loading Spinner Icon -->
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>

    <slot></slot>
  </button>
</template>
