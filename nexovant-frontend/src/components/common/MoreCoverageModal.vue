<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'

const props = defineProps<{
  isOpen: boolean
  storyTitle: string
  sources: {
    publisher: string
    original_headline: string
    published_at: string
    url: string
    coverage_type: string
    adds_new_info: boolean
    description: string
  }[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <Modal
    :is-open="isOpen"
    title="Supporting Publisher Coverage & Event Provenance"
    max-width="3xl"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <div class="p-3 bg-nexo-surface border border-nexo-border rounded text-xs space-y-1">
        <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Consolidated Story Cluster</span>
        <div class="font-serif font-bold text-nexo-text-primary text-sm leading-snug">{{ storyTitle }}</div>
        <p class="text-[11px] text-nexo-text-secondary leading-relaxed">
          Nexovant clusters multiple publisher dispatches and regulatory filings into a single normalized intelligence record. Review the original sources below:
        </p>
      </div>

      <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
        <div
          v-for="(s, idx) in sources"
          :key="idx"
          class="p-4 bg-nexo-surface/60 border border-nexo-border rounded-lg space-y-2 text-xs hover:border-nexo-accent-cyan/40 transition-colors"
        >
          <div class="flex items-center justify-between flex-wrap gap-2 text-[10px] font-mono">
            <div class="flex items-center space-x-2">
              <span class="font-bold text-nexo-text-primary uppercase bg-nexo-card px-2 py-0.5 rounded border border-nexo-border">
                {{ s.publisher }}
              </span>
              <span v-if="s.adds_new_info" class="text-nexo-accent-cyan bg-nexo-accent-cyan/15 px-1.5 py-0.2 rounded font-bold">
                + Adds Strategic Details
              </span>
            </div>
            <span class="text-nexo-text-muted">{{ s.published_at }}</span>
          </div>

          <div class="font-serif font-semibold text-nexo-text-primary text-sm">
            {{ s.original_headline }}
          </div>

          <p class="text-[11px] text-nexo-text-secondary leading-relaxed">
            {{ s.description }}
          </p>

          <div class="pt-2 flex justify-end">
            <a
              :href="s.url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1.5 text-xs font-semibold text-nexo-accent-cyan hover:underline"
            >
              <span>Read original at {{ s.publisher }}</span>
              <ExternalLink class="h-3 w-3 shrink-0" />
            </a>
          </div>
        </div>
      </div>
    </div>
  </Modal>
</template>
