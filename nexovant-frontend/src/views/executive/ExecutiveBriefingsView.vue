<script setup lang="ts">
import { ref, onMounted } from 'vue'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
  type ExecutiveBriefing,
} from '@/services/api/executive.service'
import {
  FileText,
  Download,
  CheckCircle,
  Plus,
  Clock,
  ExternalLink,
  ChevronRight,
  ShieldCheck,
  History,
  X,
} from 'lucide-vue-next'

const briefings = ref<ExecutiveBriefing[]>([])
const selectedBriefing = ref<ExecutiveBriefing | null>(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const statusMessage = ref('')

const isGenerateModalOpen = ref(false)
const generateForm = ref({
  title: '',
  period_type: 'daily',
  industry_scope: '',
  geographic_scope: 'Global',
})

const fetchBriefings = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await executiveService.getBriefings()
    briefings.value = res.data || []
    if (briefings.value.length > 0 && !selectedBriefing.value) {
      selectedBriefing.value = briefings.value[0]
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load executive briefings.'
  } finally {
    isLoading.value = false
  }
}

const handleGenerateBriefing = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const newBriefing = await executiveService.generateBriefing(generateForm.value)
    statusMessage.value = 'Briefing synthesized from verified intelligence.'
    isGenerateModalOpen.value = false
    await fetchBriefings()
    selectedBriefing.value = newBriefing
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to synthesize briefing.'
  } finally {
    isSubmitting.value = false
  }
}

const handleExport = async (format: 'markdown' | 'json') => {
  if (!selectedBriefing.value) return
  try {
    const exported = await executiveService.exportBriefing(selectedBriefing.value.id, format)
    if (format === 'markdown') {
      const blob = new Blob([exported as string], { type: 'text/markdown;charset=utf-8' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${selectedBriefing.value.slug}.md`
      a.click()
    } else {
      const blob = new Blob([JSON.stringify(exported, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${selectedBriefing.value.slug}.json`
      a.click()
    }
  } catch (err: any) {
    errorMessage.value = 'Failed to export briefing.'
  }
}

const handleReview = async (status: 'approved' | 'reviewed') => {
  if (!selectedBriefing.value) return
  try {
    const updated = await executiveService.reviewBriefing(selectedBriefing.value.id, status)
    selectedBriefing.value = updated
    statusMessage.value = `Briefing status updated to ${status}.`
    await fetchBriefings()
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to update review status.'
  }
}

onMounted(() => {
  fetchBriefings()
})
</script>

<template>
  <ThreeColumnLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col justify-between gap-4 border-b border-nexo-border pb-5 sm:flex-row sm:items-center">
        <div>
          <div class="flex items-center gap-2">
            <span class="badge-verified">STAGE 15</span>
            <span class="section-label">EXECUTIVE INTELLIGENCE BRIEFING ENGINE</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Executive Briefings
          </h1>
          <p class="mt-1 text-sm text-nexo-text-2">
            Structured 7-section executive reports synthesized from corroborated intelligence.
          </p>
        </div>
        <div>
          <button @click="isGenerateModalOpen = true" class="btn-ink flex items-center gap-2">
            <Plus class="h-4 w-4" />
            <span>Generate New Briefing</span>
          </button>
        </div>
      </div>

      <!-- Success / Error Alerts -->
      <div v-if="statusMessage" class="rounded-[2px] border border-nexo-verified/40 bg-nexo-verified-tint/40 p-3 text-xs text-nexo-verified font-medium flex items-center justify-between">
        <span>{{ statusMessage }}</span>
        <button @click="statusMessage = ''" class="hover:underline">Dismiss</button>
      </div>
      <div v-if="errorMessage" class="rounded-[2px] border border-nexo-breaking/30 bg-nexo-breaking-tint/30 p-3 text-xs text-nexo-breaking flex items-center justify-between">
        <span>{{ errorMessage }}</span>
        <button @click="errorMessage = ''" class="hover:underline">Dismiss</button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="h-96 shimmer-bg rounded-[2px]" />

      <!-- Empty State -->
      <div v-else-if="briefings.length === 0" class="card-editorial p-12 text-center">
        <FileText class="mx-auto h-12 w-12 text-nexo-muted/40" />
        <h3 class="mt-3 font-serif text-lg font-bold text-nexo-ink">No Briefings Synthesized</h3>
        <p class="mt-1 text-xs text-nexo-text-2 max-w-md mx-auto">
          Generate your first daily or weekly briefing to review multi-source intelligence, strategic implications, and scenario forecasts.
        </p>
        <button @click="isGenerateModalOpen = true" class="btn-ink mt-4">
          Generate Briefing
        </button>
      </div>

      <!-- Main Master-Detail Layout -->
      <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- Briefing Roster Sidebar (4 cols) -->
        <div class="lg:col-span-4 space-y-3">
          <p class="section-label">REPORT REPOSITORY</p>
          <div
            v-for="b in briefings"
            :key="b.id"
            @click="selectedBriefing = b"
            :class="[
              'card-editorial p-4 cursor-pointer transition-colors space-y-2',
              selectedBriefing?.id === b.id ? 'border-nexo-cobalt bg-nexo-paper-2' : 'hover:border-nexo-cobalt/60'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="badge-verified">{{ b.period_type.toUpperCase() }}</span>
              <span class="text-[10px] font-mono text-nexo-muted">v{{ b.version }}</span>
            </div>
            <h4 class="font-serif text-sm font-bold text-nexo-ink line-clamp-2">{{ b.title }}</h4>
            <div class="flex items-center justify-between text-[11px] text-nexo-muted">
              <span>{{ new Date(b.created_at).toLocaleDateString() }}</span>
              <span class="capitalize font-medium" :class="b.review_status === 'approved' ? 'text-nexo-verified' : 'text-nexo-brass-text'">
                {{ b.review_status }}
              </span>
            </div>
          </div>
        </div>

        <!-- Briefing Viewer (8 cols) -->
        <div v-if="selectedBriefing" class="lg:col-span-8 card-editorial p-6 space-y-6">
          <!-- Briefing Action Bar -->
          <div class="flex flex-col justify-between gap-3 border-b border-nexo-border pb-4 sm:flex-row sm:items-center">
            <div>
              <div class="flex items-center gap-2">
                <span class="badge-verified">{{ selectedBriefing.period_type.toUpperCase() }}</span>
                <span class="text-xs text-nexo-muted font-mono">Version {{ selectedBriefing.version }}</span>
                <span v-if="selectedBriefing.review_status === 'approved'" class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-verified">
                  <ShieldCheck class="h-3.5 w-3.5" /> Approved
                </span>
              </div>
              <h2 class="mt-1 font-serif text-2xl font-bold text-nexo-ink">{{ selectedBriefing.title }}</h2>
              <p class="text-xs text-nexo-muted">
                Coverage: {{ new Date(selectedBriefing.coverage_start).toLocaleDateString() }} – {{ new Date(selectedBriefing.coverage_end).toLocaleDateString() }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button
                v-if="selectedBriefing.review_status !== 'approved'"
                @click="handleReview('approved')"
                class="inline-flex items-center gap-1 rounded-[4px] border border-nexo-verified bg-white px-2.5 py-1.5 text-xs font-semibold text-nexo-verified hover:bg-nexo-verified-tint"
              >
                <CheckCircle class="h-3.5 w-3.5" />
                <span>Approve</span>
              </button>
              <button
                @click="handleExport('markdown')"
                class="inline-flex items-center gap-1 rounded-[4px] border border-nexo-border bg-white px-2.5 py-1.5 text-xs font-medium text-nexo-ink hover:bg-nexo-paper-2"
                title="Export Markdown"
              >
                <Download class="h-3.5 w-3.5" />
                <span>MD</span>
              </button>
              <button
                @click="handleExport('json')"
                class="inline-flex items-center gap-1 rounded-[4px] border border-nexo-border bg-white px-2.5 py-1.5 text-xs font-medium text-nexo-ink hover:bg-nexo-paper-2"
                title="Export JSON"
              >
                <Download class="h-3.5 w-3.5" />
                <span>JSON</span>
              </button>
            </div>
          </div>

          <!-- Section A: Executive Summary -->
          <div class="space-y-2">
            <h3 class="section-label">A. EXECUTIVE SUMMARY</h3>
            <div class="rounded-[2px] bg-nexo-surface-2 p-4 border border-nexo-border text-sm text-nexo-text leading-relaxed">
              {{ selectedBriefing.executive_summary }}
            </div>
          </div>

          <!-- Section B: What Happened -->
          <div class="space-y-3">
            <h3 class="section-label">B. WHAT HAPPENED</h3>
            <div class="space-y-2">
              <div
                v-for="(item, idx) in selectedBriefing.what_happened"
                :key="idx"
                class="rounded-[2px] border border-nexo-border p-3 space-y-1"
              >
                <div class="flex items-center justify-between text-xs text-nexo-muted font-mono">
                  <span>{{ item.geographic_scope }} · {{ new Date(item.date).toLocaleDateString() }}</span>
                  <span class="text-nexo-cobalt font-medium">{{ item.primary_source }}</span>
                </div>
                <h4 class="font-serif text-base font-bold text-nexo-ink">{{ item.headline }}</h4>
                <p class="text-xs text-nexo-text-2 leading-relaxed">{{ item.summary }}</p>
              </div>
            </div>
          </div>

          <!-- Section C: Why It Matters -->
          <div class="space-y-3">
            <h3 class="section-label">C. WHY IT MATTERS</h3>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <div
                v-for="(val, key) in selectedBriefing.why_it_matters"
                :key="key"
                class="rounded-[2px] border border-nexo-border bg-nexo-surface-2 p-3 space-y-1"
              >
                <p class="text-[10px] font-bold uppercase tracking-wider text-nexo-cobalt">
                  {{ key.replace(/_/g, ' ') }}
                </p>
                <p class="text-xs text-nexo-text-2 leading-relaxed">{{ val }}</p>
              </div>
            </div>
          </div>

          <!-- Section D: What Has Changed -->
          <div class="space-y-3">
            <h3 class="section-label">D. WHAT HAS CHANGED</h3>
            <div class="rounded-[2px] border border-nexo-border p-4 space-y-2 text-xs text-nexo-text-2">
              <div v-for="(val, key) in selectedBriefing.what_has_changed" :key="key" class="flex flex-col sm:flex-row sm:items-center justify-between py-1 border-b border-nexo-border last:border-none">
                <span class="font-semibold text-nexo-ink capitalize">{{ key.replace(/_/g, ' ') }}:</span>
                <span class="text-nexo-muted">{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
              </div>
            </div>
          </div>

          <!-- Section E: What Could Happen Next -->
          <div class="space-y-3">
            <h3 class="section-label">E. WHAT COULD HAPPEN NEXT (SCENARIOS)</h3>
            <div class="space-y-2">
              <div
                v-for="(sc, idx) in selectedBriefing.what_could_happen_next"
                :key="idx"
                class="rounded-[2px] border border-nexo-border p-3 space-y-1.5"
              >
                <h4 class="font-serif text-sm font-bold text-nexo-ink">{{ sc.scenario }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-nexo-muted">
                  <div><strong>Trigger:</strong> {{ sc.trigger_conditions }}</div>
                  <div><strong>Horizon:</strong> {{ sc.time_horizon }} ({{ sc.uncertainty }})</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section F: Executive Considerations -->
          <div class="space-y-3">
            <h3 class="section-label">F. EXECUTIVE CONSIDERATIONS</h3>
            <div class="rounded-[2px] border border-nexo-border p-4 space-y-3">
              <div>
                <p class="text-xs font-bold text-nexo-ink">Decisions Requiring Attention:</p>
                <ul class="mt-1 list-disc list-inside text-xs text-nexo-text-2 space-y-1">
                  <li v-for="(dec, idx) in selectedBriefing.executive_considerations.decisions_requiring_attention || []" :key="idx">
                    {{ dec }}
                  </li>
                </ul>
              </div>
              <div>
                <p class="text-xs font-bold text-nexo-ink">Potential Actions:</p>
                <ul class="mt-1 list-disc list-inside text-xs text-nexo-text-2 space-y-1">
                  <li v-for="(act, idx) in selectedBriefing.executive_considerations.potential_actions || []" :key="idx">
                    {{ act }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Section G: Evidence and Sources -->
          <div class="space-y-3">
            <h3 class="section-label">G. EVIDENCE AND SOURCES</h3>
            <div class="space-y-2">
              <div
                v-for="cit in selectedBriefing.citations || []"
                :key="cit.citation_id"
                class="rounded-[2px] border border-nexo-border p-3 flex items-start justify-between text-xs"
              >
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-nexo-cobalt">[{{ cit.citation_id }}]</span>
                    <strong class="text-nexo-ink">{{ cit.title }}</strong>
                    <span v-if="cit.is_verified" class="badge-verified text-[10px]">VERIFIED</span>
                  </div>
                  <p class="text-nexo-muted">{{ cit.source_name }} · {{ cit.published_at ? new Date(cit.published_at).toLocaleDateString() : 'Direct' }}</p>
                  <p class="text-nexo-text-2 italic">{{ cit.excerpt }}</p>
                </div>
                <a
                  v-if="cit.source_url"
                  :href="cit.source_url"
                  target="_blank"
                  class="text-nexo-cobalt hover:underline shrink-0 ml-4 inline-flex items-center gap-1"
                >
                  <span>Link</span>
                  <ExternalLink class="h-3 w-3" />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Synthesis Modal -->
      <div v-if="isGenerateModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-[2px] bg-white p-6 shadow-xl border border-nexo-border space-y-4">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <h3 class="font-serif text-lg font-bold text-nexo-ink">Synthesize Executive Briefing</h3>
            <button @click="isGenerateModalOpen = false" class="text-nexo-muted hover:text-nexo-ink">
              <X class="h-4 w-4" />
            </button>
          </div>

          <form @submit.prevent="handleGenerateBriefing" class="space-y-4 text-xs">
            <div>
              <label class="block font-medium text-nexo-ink mb-1">Briefing Title (Optional)</label>
              <input
                v-model="generateForm.title"
                type="text"
                placeholder="e.g. Semiconductor Autonomy Executive Briefing"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              />
            </div>

            <div>
              <label class="block font-medium text-nexo-ink mb-1">Cadence / Period Type</label>
              <select
                v-model="generateForm.period_type"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              >
                <option value="daily">Daily Briefing (Last 24 Hours)</option>
                <option value="weekly">Weekly Briefing (Last 7 Days)</option>
                <option value="monthly">Monthly Briefing (Last 30 Days)</option>
              </select>
            </div>

            <div>
              <label class="block font-medium text-nexo-ink mb-1">Industry Scope (Optional)</label>
              <input
                v-model="generateForm.industry_scope"
                type="text"
                placeholder="e.g. semiconductor-autonomy"
                class="w-full rounded-[4px] border border-nexo-border p-2 text-xs focus:border-nexo-cobalt focus:ring-1 focus:ring-nexo-cobalt"
              />
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-nexo-border">
              <button
                type="button"
                @click="isGenerateModalOpen = false"
                class="rounded-[4px] border border-nexo-border px-3 py-1.5 font-medium text-nexo-ink hover:bg-nexo-paper-2"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="btn-ink"
              >
                <span>{{ isSubmitting ? 'Synthesizing...' : 'Synthesize Report' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
