<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { workspaceService } from '@/services/api/workspace.service'
import {
  Sparkles,
  Search,
  CheckCircle2,
  AlertTriangle,
  Bookmark,
  ExternalLink,
  BookOpen,
  Layers,
  ArrowRight
} from 'lucide-vue-next'

interface WatchlistOption {
  id: string
  name: string
}

interface CitationItem {
  title: string
  source: string
  type: string
}

interface ResearchResponse {
  success: boolean
  query: string
  answer: string
  grounded_citations: CitationItem[]
  verified_facts?: string[]
  historical_milestones?: Array<{ date: string; title: string }>
  conflicting_evidence?: Array<{ claim: string; source: string; stance: string }>
  evidence_limitations: string
  generated_at: string
}

const queryText = ref('')
const selectedWatchlistId = ref('')
const watchlists = ref<WatchlistOption[]>([])
const isExecuting = ref(false)
const researchResult = ref<ResearchResponse | null>(null)
const statusMessage = ref('')
const errorMessage = ref('')

const samplePrompts = [
  'What are the confirmed capital allocation milestones for European semiconductor foundries?',
  'Summarize High-NA EUV lithography deployment schedules across pilot lines.',
  'Analyze supply chain localization risks affecting automotive microcontrollers.',
]

const loadWatchlists = async () => {
  try {
    const res = await workspaceService.getWatchlists()
    if (res.success) {
      watchlists.value = res.watchlists
    }
  } catch {
    //
  }
}

const handleExecuteQuery = async (preset?: string) => {
  const query = preset || queryText.value
  if (!query.trim()) return

  queryText.value = query
  isExecuting.value = true
  errorMessage.value = ''
  statusMessage.value = ''
  researchResult.value = null

  try {
    const res = await workspaceService.askResearchQuestion(query, selectedWatchlistId.value || undefined)
    if (res.success) {
      researchResult.value = res
      statusMessage.value = 'Grounded research synthesis complete.'
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.errors?.query?.[0] || 'Research synthesis failed.'
  } finally {
    isExecuting.value = false
  }
}

const handleGenerateWatchlistBrief = async (watchlistId: string) => {
  if (!watchlistId) return
  isExecuting.value = true
  errorMessage.value = ''
  statusMessage.value = ''
  researchResult.value = null

  try {
    const res = await workspaceService.generateWatchlistBrief(watchlistId)
    if (res.success) {
      researchResult.value = res
      statusMessage.value = 'Watchlist executive brief synthesized.'
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to synthesize watchlist brief.'
  } finally {
    isExecuting.value = false
  }
}

onMounted(() => {
  loadWatchlists()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Personal Workspace</span>
              <span>/</span>
              <span>Grounded Research & RAG Interface</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-serif font-bold text-nexo-text-primary">
              Personalized Intelligence Research
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Query your monitored entities and watchlists. Syntheses are strictly grounded in verified disclosures with full citations.
            </p>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="statusMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✓ {{ statusMessage }}</span>
          <button @click="statusMessage = ''" class="text-emerald-600 font-bold ml-2">✕</button>
        </div>

        <div v-if="errorMessage" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-mono rounded flex items-center justify-between">
          <span>✕ {{ errorMessage }}</span>
          <button @click="errorMessage = ''" class="text-rose-600 font-bold ml-2">✕</button>
        </div>

        <!-- Query Form Box -->
        <Card class="p-5 bg-white border border-nexo-border shadow-xs space-y-4">
          <div class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex-1 space-y-1">
              <label class="block text-xs font-medium text-nexo-text-primary">Research Objective / Question</label>
              <Input
                v-model="queryText"
                placeholder="Ask an analytical question about monitored technology, capital spending, or corporate moves..."
                @keyup.enter="handleExecuteQuery()"
              />
            </div>

            <div class="w-full sm:w-64 space-y-1">
              <label class="block text-xs font-medium text-nexo-text-primary">Scope to Watchlist (Optional)</label>
              <select
                v-model="selectedWatchlistId"
                class="w-full text-xs bg-white border border-nexo-border rounded px-3 py-2 text-nexo-text-primary focus:outline-hidden"
              >
                <option value="">Global Corporate Corpus</option>
                <option v-for="wl in watchlists" :key="wl.id" :value="wl.id">{{ wl.name }}</option>
              </select>
            </div>

            <div class="flex gap-2">
              <Button
                variant="primary"
                size="sm"
                :disabled="isExecuting || !queryText.trim()"
                @click="handleExecuteQuery()"
              >
                <Sparkles class="w-3.5 h-3.5 mr-1" />
                {{ isExecuting ? 'Synthesizing...' : 'Execute Research' }}
              </Button>
              <Button
                v-if="selectedWatchlistId"
                variant="outline"
                size="sm"
                title="Synthesize comprehensive brief for selected watchlist"
                :disabled="isExecuting"
                @click="handleGenerateWatchlistBrief(selectedWatchlistId)"
              >
                Watchlist Brief
              </Button>
            </div>
          </div>

          <!-- Preset Prompts -->
          <div class="flex flex-wrap items-center gap-1.5 pt-1">
            <span class="text-[10px] font-mono uppercase text-nexo-text-muted">Suggested:</span>
            <button
              v-for="(p, idx) in samplePrompts"
              :key="idx"
              @click="handleExecuteQuery(p)"
              class="px-2.5 py-1 rounded bg-slate-50 border border-slate-200 hover:border-amber-300 text-[11px] text-nexo-text-secondary hover:text-nexo-text-primary transition-colors text-left"
            >
              {{ p }}
            </button>
          </div>
        </Card>

        <!-- Loading Skeleton -->
        <div v-if="isExecuting" class="p-12 bg-white rounded-lg border border-nexo-border text-center space-y-3">
          <Sparkles class="w-8 h-8 text-amber-500 animate-spin mx-auto" />
          <h3 class="font-serif font-bold text-nexo-text-primary text-base">Retrieving & Grounding Evidence</h3>
          <p class="text-xs text-nexo-text-secondary max-w-md mx-auto">
            Cross-referencing RAG knowledge vectors, verified disclosure claims, and historical timelines...
          </p>
        </div>

        <!-- Research Result Display -->
        <div v-if="researchResult" class="space-y-5">
          <!-- Main Synthesis Card -->
          <Card class="p-6 bg-white border border-nexo-border shadow-xs space-y-4">
            <div class="flex items-start justify-between border-b border-nexo-border/80 pb-3">
              <div>
                <span class="text-[10px] font-mono text-emerald-800 uppercase font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  Grounded Analytical Synthesis
                </span>
                <h2 class="font-serif font-bold text-xl text-nexo-text-primary mt-1">
                  {{ researchResult.query }}
                </h2>
              </div>
              <span class="text-[11px] font-mono text-nexo-text-muted">
                {{ new Date(researchResult.generated_at).toLocaleTimeString() }}
              </span>
            </div>

            <!-- Answer Prose -->
            <div class="font-serif text-sm leading-relaxed text-nexo-text-primary whitespace-pre-wrap">
              {{ researchResult.answer }}
            </div>

            <!-- Grounded Citations Box -->
            <div class="pt-4 border-t border-slate-100 space-y-2">
              <h4 class="text-xs font-mono uppercase text-nexo-text-muted flex items-center gap-1.5">
                <BookOpen class="w-3.5 h-3.5 text-cyan-700" /> Corroborated Source Citations ({{ researchResult.grounded_citations.length }})
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                <div
                  v-for="(c, idx) in researchResult.grounded_citations"
                  :key="idx"
                  class="p-2.5 bg-slate-50 rounded border border-slate-200 text-xs space-y-0.5"
                >
                  <div class="font-bold text-nexo-text-primary truncate">{{ c.title }}</div>
                  <div class="text-[10px] font-mono text-nexo-text-muted truncate">Source: {{ c.source }}</div>
                </div>
              </div>
            </div>

            <!-- Evidence Limitations Banner -->
            <div class="p-3 bg-amber-50/70 rounded border border-amber-200 text-[11px] text-amber-900 flex items-start gap-2">
              <AlertTriangle class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
              <div>
                <span class="font-bold">Evidence Boundaries:</span>
                <span> {{ researchResult.evidence_limitations }}</span>
              </div>
            </div>
          </Card>
        </div>
      </main>
    </div>
  </div>
</template>
