<script setup lang="ts">
import { ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { searchService, type CorroborationReport } from '@/services/api/search.service'
import {
  CheckCircle2,
  AlertTriangle,
  Copy,
  ExternalLink,
  ShieldCheck,
  Building2,
  Clock,
  Layers,
  FileText,
} from 'lucide-vue-next'

const props = defineProps<{
  modelValue: boolean
  itemId?: string
  itemType?: string
  itemTitle?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const isLoading = ref(false)
const report = ref<CorroborationReport | null>(null)
const activeTab = ref<'corroboration' | 'claims' | 'syndication'>('corroboration')

const fetchCorroboration = async () => {
  if (!props.itemId) return
  isLoading.value = true
  try {
    report.value = await searchService.getCorroboration(props.itemId, props.itemType || 'article')
  } catch {
    report.value = null
  } finally {
    isLoading.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open && props.itemId) {
      fetchCorroboration()
    }
  }
)
</script>

<template>
  <Modal
    :is-open="modelValue"
    max-width="xl"
    @close="emit('update:modelValue', false)"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <div class="p-2 rounded-lg bg-primary-500/10 text-primary-400 border border-primary-500/20">
          <ShieldCheck class="w-5 h-5" />
        </div>
        <div>
          <h3 class="text-base font-medium text-white font-serif">
            Source Transparency & Corroboration Audit
          </h3>
          <p class="text-xs text-neutral-400 truncate max-w-lg">
            {{ itemTitle || report?.title || 'Intelligence Verification Analysis' }}
          </p>
        </div>
      </div>
    </template>

    <div v-if="isLoading" class="space-y-4 py-4">
      <Skeleton class="h-16 w-full" />
      <Skeleton class="h-28 w-full" />
      <Skeleton class="h-28 w-full" />
    </div>

    <div v-else-if="report" class="space-y-6">
      <!-- Top Metrics Banner -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
          <div class="text-xs text-neutral-400">Primary Source</div>
          <div class="text-sm font-medium text-white truncate mt-1">
            {{ report.primary_source?.name || 'Primary Publisher' }}
          </div>
          <div class="text-[11px] text-primary-400 mt-0.5">
            {{ report.primary_source?.trust_tier ? report.primary_source.trust_tier.replace('_', ' ') : 'Monitored' }}
          </div>
        </div>

        <div class="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
          <div class="text-xs text-neutral-400">Independent Outlets</div>
          <div class="text-lg font-semibold text-emerald-400 mt-0.5">
            {{ report.independent_sources_count }}
          </div>
          <div class="text-[11px] text-neutral-500 mt-0.5">Non-syndicated</div>
        </div>

        <div class="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
          <div class="text-xs text-neutral-400">Syndicated Reprints</div>
          <div class="text-lg font-semibold text-amber-400 mt-0.5">
            {{ report.syndicated_count }}
          </div>
          <div class="text-[11px] text-neutral-500 mt-0.5">Excluded from score</div>
        </div>

        <div class="p-3 rounded-lg bg-neutral-900 border border-neutral-800">
          <div class="text-xs text-neutral-400">Contradictions</div>
          <div class="text-lg font-semibold text-rose-400 mt-0.5">
            {{ report.contradiction_count }}
          </div>
          <div class="text-[11px] text-neutral-500 mt-0.5">Divergent claims</div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-neutral-800 gap-6">
        <button
          type="button"
          class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'corroboration' ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
          @click="activeTab = 'corroboration'"
        >
          Independent Corroborators ({{ report.independent_corroborators.length }})
        </button>
        <button
          type="button"
          class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'syndication' ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
          @click="activeTab = 'syndication'"
        >
          Syndicated Reprints ({{ report.syndicated_count }})
        </button>
        <button
          type="button"
          class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
          :class="activeTab === 'claims' ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
          @click="activeTab = 'claims'"
        >
          Extracted Claims ({{ report.extracted_claims.length }})
        </button>
      </div>

      <!-- Tab Content: Independent Corroborators -->
      <div v-if="activeTab === 'corroboration'" class="space-y-3">
        <div v-if="report.independent_corroborators.length === 0" class="text-center py-6 text-neutral-500 text-xs">
          No secondary independent publications recorded yet. This development is currently classified as single-source.
        </div>
        <div
          v-for="(source, idx) in report.independent_corroborators"
          :key="idx"
          class="p-3 rounded-lg bg-neutral-900/60 border border-neutral-800 flex items-start justify-between gap-3"
        >
          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-white">{{ source.source_name }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                Independent Corroboration
              </span>
              <span class="text-[10px] text-neutral-500">{{ source.domain }}</span>
            </div>
            <div v-if="source.title" class="text-xs text-neutral-300 truncate">
              {{ source.title }}
            </div>
            <div v-if="source.published_at" class="text-[11px] text-neutral-500 flex items-center gap-1">
              <Clock class="w-3 h-3" />
              Published {{ new Date(source.published_at).toLocaleString() }}
            </div>
          </div>
          <div class="text-right shrink-0">
            <div class="text-xs font-medium text-neutral-200">
              {{ source.credibility_score ? `${source.credibility_score}%` : '85%' }}
            </div>
            <div class="text-[10px] text-neutral-500">Credibility</div>
          </div>
        </div>
      </div>

      <!-- Tab Content: Syndicated Reprints -->
      <div v-else-if="activeTab === 'syndication'" class="space-y-3">
        <div class="p-2.5 rounded bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300 flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 shrink-0 text-amber-400" />
          <span>Syndicated wire reprints (>85% verbatim text) are automatically excluded from corroboration scoring to prevent artificial inflation.</span>
        </div>

        <div v-if="report.syndicated_reprints.length === 0" class="text-center py-6 text-neutral-500 text-xs">
          No duplicate syndicated reprints detected.
        </div>
        <div
          v-for="(source, idx) in report.syndicated_reprints"
          :key="idx"
          class="p-3 rounded-lg bg-neutral-900/60 border border-neutral-800 flex items-start justify-between gap-3 opacity-80"
        >
          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium text-neutral-300">{{ source.source_name }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-400 border border-neutral-700">
                Syndicated Wire Copy
              </span>
            </div>
            <div v-if="source.title" class="text-xs text-neutral-400 truncate">
              {{ source.title }}
            </div>
          </div>
          <span class="text-[11px] text-neutral-500 shrink-0">Overlapped Text</span>
        </div>
      </div>

      <!-- Tab Content: Extracted Claims -->
      <div v-else-if="activeTab === 'claims'" class="space-y-3">
        <div v-if="report.extracted_claims.length === 0" class="text-center py-6 text-neutral-500 text-xs">
          No individual atomic claims extracted for this record.
        </div>
        <div
          v-for="(claim, idx) in report.extracted_claims"
          :key="idx"
          class="p-3 rounded-lg bg-neutral-900/60 border border-neutral-800 space-y-1.5"
        >
          <div class="text-xs font-medium text-neutral-200">
            {{ claim.claim }}
          </div>
          <div class="flex items-center gap-3 text-[11px]">
            <span
              class="px-1.5 py-0.5 rounded text-[10px] uppercase font-mono font-medium"
              :class="claim.verification_status === 'verified' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400'"
            >
              {{ claim.verification_status }}
            </span>
            <span class="text-neutral-500">
              Confidence: {{ Math.round(claim.confidence_score * 100) }}%
            </span>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button variant="outline" size="sm" @click="emit('update:modelValue', false)">
          Close
        </Button>
      </div>
    </template>
  </Modal>
</template>
