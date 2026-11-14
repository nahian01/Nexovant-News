<script setup lang="ts">
import { computed } from 'vue'

export type BadgeVariant =
  | 'confirmed'
  | 'reported_reliable'
  | 'developing'
  | 'disputed'
  | 'unverified'
  | 'neutral'
  | 'gold'

interface Props {
  variant?: BadgeVariant
  size?: 'sm' | 'md'
  dot?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'neutral',
  size: 'md',
  dot: true,
})

const badgeConfig = computed(() => {
  switch (props.variant) {
    case 'confirmed':
      return {
        label: 'Confirmed',
        bg: 'bg-emerald-50 text-emerald-400 border-emerald-200',
        dotBg: 'bg-emerald-600',
      }
    case 'reported_reliable':
      return {
        label: 'Reported (Reliable)',
        bg: 'bg-teal-50 text-nexo-accent-cyan border-teal-200',
        dotBg: 'bg-nexo-accent-cyan',
      }
    case 'developing':
      return {
        label: 'Developing Story',
        bg: 'bg-amber-50 text-amber-800 border-amber-200',
        dotBg: 'bg-amber-600',
      }
    case 'disputed':
      return {
        label: 'Disputed Claim',
        bg: 'bg-rose-50 text-red-400 border-rose-200',
        dotBg: 'bg-rose-600 animate-pulse',
      }
    case 'gold':
      return {
        label: 'High Significance',
        bg: 'bg-yellow-50 text-nexo-accent-gold border-yellow-200',
        dotBg: 'bg-nexo-accent-gold',
      }
    case 'unverified':
    case 'neutral':
    default:
      return {
        label: 'Unverified',
        bg: 'bg-slate-100 text-slate-700 border-slate-200',
        dotBg: 'bg-slate-500',
      }
  }
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-sans font-semibold rounded-full border transition-colors select-none tracking-wide uppercase',
      size === 'sm' ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-xs',
      badgeConfig.bg,
    ]"
  >
    <span
      v-if="dot"
      :class="['w-1.5 h-1.5 rounded-full mr-1.5 flex-shrink-0', badgeConfig.dotBg]"
      aria-hidden="true"
    />
    <slot>{{ badgeConfig.label }}</slot>
  </span>
</template>
