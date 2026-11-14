<script setup lang="ts">
defineProps<{
  headers: string[]
  isEmpty?: boolean
  emptyMessage?: string
}>()
</script>

<template>
  <div class="w-full overflow-x-auto border border-nexo-border rounded-2xl bg-nexo-card shadow-sm">
    <table class="w-full text-left border-collapse text-[13px]">
      <thead>
        <tr class="border-b border-nexo-border bg-nexo-bg text-nexo-text-muted font-sans uppercase text-[10px] tracking-[0.12em]">
          <th
            v-for="(header, i) in headers"
            :key="i"
            class="py-3.5 px-4 font-semibold"
          >
            {{ header }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-nexo-border/70 text-nexo-text-primary [&>tr]:transition-colors [&>tr:hover]:bg-nexo-bg/70 [&>tr>td]:py-3.5 [&>tr>td]:px-4">
        <slot />
        <tr v-if="isEmpty">
          <td :colspan="headers.length" class="py-12 text-center text-nexo-text-muted font-mono">
            {{ emptyMessage || 'No records found matching criteria.' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
