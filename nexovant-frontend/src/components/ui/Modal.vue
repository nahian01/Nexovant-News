<script setup lang="ts">
import { watch, onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    isOpen: boolean
    title?: string
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl'
  }>(),
  {
    maxWidth: 'lg',
  }
)

const emit = defineEmits<{
  (e: 'close'): void
}>()

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.isOpen) {
    emit('close')
  }
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
  }
)

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})

const maxWidthClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl',
  '4xl': 'max-w-4xl',
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 text-left"
        @click.self="emit('close')"
      >
        <div
          :class="[
            'w-full bg-nexo-card border border-nexo-border rounded-3xl shadow-2xl p-6 sm:p-7 relative overflow-hidden',
            maxWidthClasses[maxWidth]
          ]"
        >
          <!-- Header -->
          <div class="flex items-center justify-between pb-4 border-b border-nexo-border/80 mb-4">
            <h3 v-if="title" class="editorial-title text-xl font-medium text-nexo-text-primary">
              {{ title }}
            </h3>
            <button
              @click="emit('close')"
              class="text-nexo-text-muted hover:text-nexo-text-primary transition-colors p-1.5 rounded-lg hover:bg-nexo-bg"
              aria-label="Close dialog"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div>
            <slot />
          </div>

          <!-- Footer slot if any -->
          <div v-if="$slots.footer" class="pt-4 border-t border-nexo-border/80 mt-6 flex justify-end space-x-3">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
