<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import { searchService, type ResearchBriefData } from '@/services/api/search.service'
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
  ArrowRight,
  Download,
  Share2,
  Clock,
  ShieldCheck,
  FileText,
} from 'lucide-vue-next'

interface WatchlistOption {
  id: string
  name: string
}

interface CollectionOption {
  id: string
  name: string
}

const queryText = ref('')
const selectedCollectionId = ref('')
const collections = ref<CollectionOption[]>([])
const isExecuting = ref(false)
const briefData = ref<ResearchBriefData | null>(null)
const statusMessage = ref('')
const errorMessage = ref('')
const isSharedWithOrg = ref(false)

const samplePrompts = [
  'What are the latest confirmed milestones in European semiconductor manufacturing?',
  'Which organizations are investing in AI infrastructure in Southeast Asia?',
  'What events have affected Bangladesh\'s energy sector during the last six months?',
  'Summarize High-NA EUV lithography deployment schedules across pilot lines.',
]

const loadCollections = async () => {
  try {
    const res = await workspaceService.getCollections()
    if (res?.success) {
      collections.value = res.collections || []
    }
  } catch {
    //
  }
}

const handleGenerate = async (preset?: string) => {
  const q = preset || queryText.value
  if (!q.trim()) return

  queryText.value = q
  isExecuting.value = true
  errorMessage.value = ''
  statusMessage.value = ''
  briefData.value = null

  try {
    const res = await searchService.generateResearchBrief({
      question: q.trim(),
      collection_id: selectedCollectionId.value || undefined,
      save: true,
      is_shared_with_org: isSharedWithOrg.value,
    })

    if (res?.success && res.brief) {
      briefData.value = res.brief
      statusMessage.value = 'Grounded 13-section research brief synthesized and saved.'
    }
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Brief generation failed. Please try again.'
  } finally {
    isExecuting.value = false
  }
}

const handleExport = async (format: 'markdown' | 'json') => {
  if (!briefData.value?.id) return
  try {
    const content = await searchService.exportResearchBrief(briefData.value.id, format)
    const blob = new Blob([typeof content === 'string' ? content : JSON.stringify(content, null, 2)], {
      type: format === 'markdown' ? 'text/markdown' : 'application/json',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `research-brief-${briefData.value.id.substring(0, 8)}.${format === 'markdown' ? 'md' : 'json'}`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    errorMessage.value = 'Export failed.'
  }
}

onMounted(() => {
  loadCollections()
})
</script>

<template>
  <div class="flex h-screen bg-black text-neutral-100 overflow-hidden font-sans">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <AppHeader />

      <main class="flex-1 max-w-5xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-serif text-white tracking-tight flex items-center gap-2.5">
              <span>Grounded Research Intelligence</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-sans font-medium bg-primary-500/10 text-primary-400 border border-primary-500/20">
                13-Section Briefing
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1">
              Natural-language question answering synthesized strictly from verified disclosures, timelines, and knowledge chunks.
            </p>
          </div>
        </div>

        <Alert v-if="statusMessage" variant="success">
          {{ statusMessage }}
        </Alert>
        <Alert v-if="errorMessage" variant="danger">
          {{ errorMessage }}
        </Alert>

        <!-- Question Formulation Card -->
        <Card class="p-5 border-neutral-800 bg-neutral-900/60 space-y-4">
          <div class="space-y-2">
            <label class="block text-xs font-medium text-neutral-300">
              Enter Research Question
            </label>
            <div class="flex gap-2">
              <Input
                v-model="queryText"
                placeholder="e.g. What are the confirmed capital allocation milestones for European semiconductor foundries?"
                class="flex-1 text-xs"
                @keydown.enter="handleGenerate()"
              />
              <Button
                size="sm"
                class="gap-1.5 px-4"
                :loading="isExecuting"
                @click="handleGenerate()"
              >
                <Sparkles class="w-3.5 h-3.5 text-primary-300" />
                <span>Synthesize Brief</span>
              </Button>
            </div>
          </div>

          <!-- Configuration Controls -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 text-xs">
            <div>
              <label class="block text-neutral-400 mb-1 text-[11px]">Save to Research Collection</label>
              <select
                v-model="selectedCollectionId"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 text-xs text-white p-2 focus:outline-none"
              >
                <option value="">No Collection (Standalone Brief)</option>
                <option v-for="c in collections" :key="c.id" :value="c.id">
                  {{ c.name }}
                </option>
              </select>
            </div>

            <div class="flex items-end pb-2">
              <label class="flex items-center gap-2 cursor-pointer text-xs text-neutral-300">
                <input
                  v-model="isSharedWithOrg"
                  type="checkbox"
                  class="rounded border-neutral-700 bg-neutral-800 text-primary-500 focus:ring-0"
                />
                <span>Share with Organization Workspace</span>
              </label>
            </div>
          </div>

          <!-- Sample Query Chips -->
          <div class="space-y-1.5 pt-1">
            <div class="text-[11px] text-neutral-500">Sample Inquiries:</div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="(prompt, idx) in samplePrompts"
                :key="idx"
                type="button"
                class="px-2.5 py-1 rounded-full text-[11px] bg-neutral-800/80 border border-neutral-700 text-neutral-300 hover:text-white hover:border-primary-500/50 transition-colors text-left"
                @click="handleGenerate(prompt)"
              >
                {{ prompt }}
              </button>
            </div>
          </div>
        </Card>

        <!-- Brief Loading Skeleton -->
        <div v-if="isExecuting" class="space-y-4 py-8">
          <div class="text-center space-y-2">
            <Sparkles class="w-8 h-8 text-primary-400 animate-spin mx-auto" />
            <div class="text-sm font-serif text-white">Synthesizing Verified Intelligence Brief...</div>
            <p class="text-xs text-neutral-400 max-w-sm mx-auto">
              Querying grounded RAG vectors, validating corporate filings, and constructing 13-section brief.
            </p>
          </div>
          <Card class="p-6 border-neutral-800 bg-neutral-900/40 space-y-4">
            <Skeleton class="h-6 w-3/4" />
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-40 w-full" />
          </Card>
        </div>

        <!-- Synthesized 13-Section Brief Display -->
        <div v-else-if="briefData" class="space-y-6">
          <!-- Top Brief Action Bar -->
          <Card class="p-4 border-neutral-800 bg-neutral-900/80 flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono uppercase tracking-wider px-2 py-0.5 rounded bg-primary-500/10 text-primary-400 border border-primary-500/20">
                  Grounded Synthesis
                </span>
                <span class="text-xs text-neutral-400">
                  Confidence: <span class="font-mono text-emerald-400 font-semibold">{{ Math.round(briefData.confidence_score * 100) }}%</span>
                </span>
                <span class="text-xs text-neutral-400">
                  Uncertainty: <span class="font-mono text-neutral-200 uppercase">{{ briefData.uncertainty_rating }}</span>
                </span>
              </div>
              <h2 class="text-lg font-serif text-white font-medium">
                {{ briefData.title }}
              </h2>
            </div>

            <div class="flex items-center gap-2">
              <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="handleExport('markdown')">
                <Download class="w-3.5 h-3.5" />
                <span>Export Markdown</span>
              </Button>
              <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="handleExport('json')">
                <FileText class="w-3.5 h-3.5" />
                <span>Export JSON</span>
              </Button>
            </div>
          </Card>

          <!-- 13 Structured Sections -->
          <div class="space-y-4 text-xs">
            <!-- 1. Executive Summary -->
            <Card class="p-5 border-neutral-800 bg-neutral-900/60 space-y-2">
              <h3 class="text-sm font-serif font-medium text-primary-300 flex items-center gap-2">
                <BookOpen class="w-4 h-4 text-primary-400" />
                <span>1. Executive Summary</span>
              </h3>
              <p class="text-neutral-200 leading-relaxed text-sm">
                {{ briefData.executive_summary }}
              </p>
            </Card>

            <!-- 2. Research Question -->
            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-1">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400">
                2. Research Question
              </h4>
              <p class="text-white font-medium">
                {{ briefData.research_question }}
              </p>
            </Card>

            <!-- 3. Background & Context -->
            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-2">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400">
                3. Background and Context
              </h4>
              <p class="text-neutral-300 leading-relaxed">
                {{ briefData.structured_sections?.background_and_context }}
              </p>
            </Card>

            <!-- 4. Key Findings -->
            <Card class="p-5 border-neutral-800 bg-neutral-900/60 space-y-3">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400">
                4. Key Findings
              </h4>
              <div class="space-y-2">
                <div
                  v-for="(finding, idx) in briefData.structured_sections?.key_findings"
                  :key="idx"
                  class="p-3 rounded-lg bg-neutral-950/70 border border-neutral-800 flex items-start gap-3"
                >
                  <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                  <div class="space-y-1">
                    <div class="text-neutral-200 font-medium">
                      {{ finding.statement }}
                    </div>
                    <div class="text-[11px] text-neutral-500">
                      Source: {{ finding.source }} | Status: {{ finding.status }}
                    </div>
                  </div>
                </div>
              </div>
            </Card>

            <!-- 5. Relevant Developments -->
            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-2">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400">
                5. Relevant Developments
              </h4>
              <p class="text-neutral-300 leading-relaxed">
                {{ briefData.structured_sections?.relevant_developments }}
              </p>
            </Card>

            <!-- 6. Timeline -->
            <Card v-if="briefData.structured_sections?.timeline?.length" class="p-5 border-neutral-800 bg-neutral-900/60 space-y-3">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400 flex items-center gap-1.5">
                <Clock class="w-3.5 h-3.5 text-primary-400" />
                <span>7. Chronological Timeline</span>
              </h4>
              <div class="space-y-2 border-l border-neutral-800 pl-4 ml-2">
                <div
                  v-for="(item, idx) in briefData.structured_sections.timeline"
                  :key="idx"
                  class="relative space-y-0.5"
                >
                  <div class="w-2 h-2 rounded-full bg-primary-400 absolute -left-[21px] top-1" />
                  <div class="text-[11px] font-mono text-primary-400 font-semibold">{{ item.date }}</div>
                  <div class="text-neutral-200">{{ item.title }}</div>
                </div>
              </div>
            </Card>

            <!-- 8. Supporting Evidence & Citations -->
            <Card class="p-5 border-neutral-800 bg-neutral-900/60 space-y-3">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400 flex items-center gap-1.5">
                <ShieldCheck class="w-4 h-4 text-emerald-400" />
                <span>8 & 13. Traceable References and Citations</span>
              </h4>
              <div class="space-y-2">
                <div
                  v-for="cit in briefData.citations"
                  :key="cit.citation_id"
                  class="p-3 rounded-lg bg-neutral-950/70 border border-neutral-800/80 flex items-start justify-between gap-3"
                >
                  <div class="space-y-1">
                    <div class="flex items-center gap-2">
                      <span class="w-5 h-5 rounded-full bg-primary-500/20 text-primary-400 text-xs font-mono font-bold flex items-center justify-center shrink-0">
                        [{{ cit.citation_id }}]
                      </span>
                      <span class="text-neutral-200 font-medium">{{ cit.document_title }}</span>
                      <span class="text-[10px] text-neutral-500 uppercase font-mono">({{ cit.document_type }})</span>
                    </div>
                    <p v-if="cit.excerpt" class="text-neutral-400 text-[11px] pl-7">
                      "{{ cit.excerpt }}"
                    </p>
                  </div>
                  <a
                    v-if="cit.source_url"
                    :href="cit.source_url"
                    target="_blank"
                    rel="noreferrer"
                    class="text-neutral-400 hover:text-white p-1"
                  >
                    <ExternalLink class="w-3.5 h-3.5" />
                  </a>
                </div>
              </div>
            </Card>

            <!-- 9. Contradictory Evidence -->
            <Card v-if="briefData.conflicting_evidence?.length" class="p-5 border-neutral-800 bg-neutral-900/60 space-y-3">
              <h4 class="text-xs font-mono uppercase tracking-wider text-rose-400 flex items-center gap-1.5">
                <AlertTriangle class="w-4 h-4 text-rose-400" />
                <span>9. Contradictory Evidence & Divergent Claims</span>
              </h4>
              <div class="space-y-2">
                <div
                  v-for="(conf, idx) in briefData.conflicting_evidence"
                  :key="idx"
                  class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 space-y-1"
                >
                  <div class="text-rose-200 font-medium">
                    {{ conf.claim }}
                  </div>
                  <div class="text-[11px] text-rose-400/80">
                    Source: {{ conf.source_name }} (Stance: {{ conf.stance }})
                  </div>
                </div>
              </div>
            </Card>

            <!-- 10. Risk & Opportunity Analysis -->
            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-2">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400">
                10. Risk and Opportunity Analysis
              </h4>
              <p class="text-neutral-300 leading-relaxed">
                {{ briefData.structured_sections?.risk_and_opportunity_analysis }}
              </p>
            </Card>

            <!-- 11. Knowledge Gaps -->
            <Card class="p-4 border-neutral-800 bg-neutral-900/60 space-y-2">
              <h4 class="text-xs font-mono uppercase tracking-wider text-neutral-400">
                11. Knowledge Gaps
              </h4>
              <p class="text-neutral-300 leading-relaxed">
                {{ briefData.structured_sections?.knowledge_gaps }}
              </p>
            </Card>

            <!-- 12. Conclusion -->
            <Card class="p-5 border-neutral-800 bg-neutral-900/80 space-y-2">
              <h4 class="text-xs font-mono uppercase tracking-wider text-primary-400">
                12. Conclusion
              </h4>
              <p class="text-neutral-200 leading-relaxed font-medium">
                {{ briefData.structured_sections?.conclusion }}
              </p>
            </Card>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
