<script setup lang="ts">
interface Props {
  modelValue: string | number
  label?: string
  id?: string
  type?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  required: false,
  placeholder: '',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="w-full flex flex-col space-y-1.5 text-left font-sans">
    <label
      v-if="label"
      :for="id"
      class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary flex items-center justify-between"
    >
      <span>
        {{ label }}
        <span v-if="required" class="text-nexo-accent-cyan ml-0.5">*</span>
      </span>
      <span v-if="hint && !error" class="text-nexo-text-muted text-[11px] font-normal normal-case">
        {{ hint }}
      </span>
    </label>

    <div class="relative">
      <input
        :id="id"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :class="[
          'w-full bg-nexo-surface text-nexo-text-primary px-3.5 py-2.5 rounded-xl border text-sm transition-all duration-200 placeholder:text-nexo-text-muted/60 disabled:opacity-50 disabled:cursor-not-allowed',
          error
            ? 'border-nexo-status-danger focus:border-nexo-status-danger focus:ring-1 focus:ring-nexo-status-danger'
            : 'border-nexo-border focus:border-nexo-accent-cyan focus:ring-1 focus:ring-nexo-accent-cyan',
        ]"
        @input="handleInput"
      />
    </div>

    <p
      v-if="error"
      class="text-xs text-nexo-status-danger font-medium flex items-center mt-1"
      role="alert"
    >
      <svg
        class="w-3.5 h-3.5 mr-1 flex-shrink-0"
        fill="currentColor"
        viewBox="0 0 20 20"
      >
        <path
          fill-rule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
          clip-rule="evenodd"
        />
      </svg>
      {{ error }}
    </p>
  </div>
</template>
