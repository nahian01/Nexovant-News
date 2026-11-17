<script setup lang="ts">
import { ref } from 'vue'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import {
  executiveService,
} from '@/services/api/executive.service'
import {
  Sparkles,
  Send,
  HelpCircle,
  ShieldCheck,
  ExternalLink,
  ChevronDown,
  ChevronUp,
  FileText,
  AlertTriangle,
  Compass,
  CheckCircle2,
  RefreshCw,
  Info,
} from 'lucide-vue-next'

interface AssistantInteraction {
  id: string
  question: string
  timestamp: string
  response: {
    query: string
    direct_answer: string
    executive_summary: string
    relevant_developments: Array<{ event: string; summary: string; source: string; date: string }>
    business_implications: string[]
    supporting_evidence: Array<{ citation_id: number; title: string; source_name: string; source_url?: string; published_at: string; excerpt: string }>
    contradictory_evidence?: any[]
    potential_scenarios: Array<{ scenario: string; triggers: string; implication: string }>
    recommended_monitoring_indicators: string[]
    suggested_follow_up_questions: string[]
    meta: { latency_ms: number; evidence_sufficiency: string; sources_retrieved: number }
  }
}

const inputQuestion = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const history = ref<AssistantInteraction[]>([])
const activeIndex = ref<number | null>(0)

const sampleQuestions = [
  'What is the strategic impact of recent semiconductor subsidies on global supply chain resiliency?',
  'What emerging regulatory risks and opportunities should technology leaders monitor regarding AI data sovereignty?',
  'Assess the medium-term impact of rising clean energy tariffs on industrial manufacturing in South Asia.',
  'What strategic options should executives consider in response to heightened cross-border export restrictions?',
]

const handleAsk = async (questionToAsk?: string) => {
  const query = (questionToAsk || inputQuestion.value).trim()
  if (!query) return
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await executiveService.askExecutiveAssistant(query)
    const interaction: AssistantInteraction = {
      id: Math.random().toString(36).substring(7),
      question: query,
      timestamp: new Date().toLocaleTimeString(),
      response: res,
    }
    history.value.unshift(interaction)
    activeIndex.value = 0
    if (!questionToAsk) inputQuestion.value = ''
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Executive assistant failed to analyze intelligence.'
  } finally {
    isLoading.value = false
  }
}

const toggleSection = (idx: number) => {
  activeIndex.value = activeIndex.value === idx ? null : idx
}
</script>

<template>
  <ThreeColumnLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col justify-between gap-4 border-b border-nexo-border pb-5 sm:flex-row sm:items-center">
        <div>
          <div class="flex items-center gap-2">
            <span class="badge-verified">STAGE 15</span>
            <span class="section-label">EXECUTIVE INTELLIGENCE ASSISTANT</span>
          </div>
          <h1 class="font-serif text-3xl font-bold tracking-tight text-nexo-ink mt-1">
            Grounded Executive Assistant
          </h1>
          <p class="text-xs text-nexo-text-2 mt-1">
            Rigorous structured intelligence analysis answering the essential executive questions with verifiable evidence citations.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1.5 rounded-[4px] border border-nexo-border bg-white px-2.5 py-1 text-xs text-nexo-text-2">
            <ShieldCheck class="h-3.5 w-3.5 text-nexo-cobalt" />
            Prompt-Injection Guard Active
          </span>
        </div>
      </div>

      <!-- Query Box -->
      <div class="rounded-[4px] border border-nexo-border bg-white p-4 shadow-sm space-y-3">
        <div class="relative">
          <textarea
            v-model="inputQuestion"
            @keydown.enter.prevent="handleAsk()"
            rows="3"
            placeholder="Ask any executive intelligence question (e.g., 'What are the strategic supply chain implications of new regional subsidies?')..."
            class="w-full resize-none rounded-[2px] border border-nexo-border bg-nexo-bg p-3 text-xs text-nexo-ink placeholder-nexo-muted focus:border-nexo-cobalt focus:outline-none"
          ></textarea>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <!-- Sample prompts -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[11px] font-semibold text-nexo-muted">Suggested:</span>
            <button
              v-for="(sq, i) in sampleQuestions.slice(0, 2)"
              :key="i"
              @click="handleAsk(sq)"
              class="rounded-[2px] border border-nexo-border bg-nexo-paper-2 px-2 py-0.5 text-[11px] text-nexo-ink hover:bg-nexo-paper-2/80 transition truncate max-w-[280px]"
            >
              {{ sq }}
            </button>
          </div>

          <button
            @click="handleAsk()"
            :disabled="isLoading || !inputQuestion.trim()"
            class="inline-flex items-center justify-center gap-1.5 rounded-[2px] bg-nexo-ink px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#1A2C42] disabled:opacity-50"
          >
            <Sparkles v-if="!isLoading" class="h-3.5 w-3.5 text-nexo-brass" />
            <RefreshCw v-else class="h-3.5 w-3.5 animate-spin" />
            {{ isLoading ? 'Synthesizing...' : 'Synthesize Analysis' }}
          </button>
        </div>
      </div>

      <!-- Error Message -->
      <div v-if="errorMessage" class="rounded-[2px] border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
        {{ errorMessage }}
      </div>

      <!-- Assistant Responses Feed -->
      <div class="space-y-6">
        <div v-if="history.length === 0 && !isLoading" class="rounded-[4px] border border-dashed border-nexo-border bg-white p-12 text-center space-y-3">
          <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-nexo-paper-2 text-nexo-cobalt">
            <Compass class="h-5 w-5" />
          </div>
          <h3 class="font-serif text-base font-bold text-nexo-ink">Executive Query Ready</h3>
          <p class="text-xs text-nexo-muted max-w-md mx-auto">
            Pose strategic questions to synthesize evidence-backed answers broken down across the 9 Executive Decision Support dimensions.
          </p>
        </div>

        <!-- Interactions List -->
        <div
          v-for="(item, idx) in history"
          :key="item.id"
          class="rounded-[4px] border border-nexo-border bg-white shadow-sm overflow-hidden"
        >
          <!-- Interaction Header -->
          <div
            @click="toggleSection(idx)"
            class="flex items-center justify-between cursor-pointer border-b border-nexo-border bg-nexo-paper-2/50 px-4 py-3 hover:bg-nexo-paper-2 transition"
          >
            <div class="flex items-center gap-2">
              <span class="flex h-5 w-5 items-center justify-center rounded-full bg-nexo-ink text-[10px] font-bold text-white">
                Q
              </span>
              <h3 class="font-serif text-sm font-bold text-nexo-ink">{{ item.question }}</h3>
            </div>
            <div class="flex items-center gap-3 text-xs text-nexo-muted">
              <span>{{ item.timestamp }}</span>
              <span class="rounded-[2px] border border-nexo-border bg-white px-1.5 py-0.5 text-[10px] font-semibold text-nexo-cobalt">
                {{ item.response.meta?.sources_retrieved || 0 }} sources ({{ item.response.meta?.latency_ms || 0 }}ms)
              </span>
              <ChevronUp v-if="activeIndex === idx" class="h-4 w-4" />
              <ChevronDown v-else class="h-4 w-4" />
            </div>
          </div>

          <!-- Interaction Body: Executive Dimensions -->
          <div v-if="activeIndex === idx" class="p-6 space-y-6 text-xs">
            <!-- Confidence & Uncertainty Disclosure -->
            <div class="rounded-[2px] border border-nexo-border bg-nexo-bg/60 p-3 flex items-start gap-2 text-nexo-text-2">
              <Info class="h-4 w-4 text-nexo-cobalt shrink-0 mt-0.5" />
              <div>
                <span class="font-semibold text-nexo-ink">Direct Executive Summary: </span>
                {{ item.response.direct_answer }}
                <div class="mt-1 text-nexo-muted">
                  Evidence sufficiency: <strong class="capitalize text-nexo-ink">{{ item.response.meta?.evidence_sufficiency }}</strong>.
                  Data Origin: Public Intelligence Disclosures & Verified News Network.
                </div>
              </div>
            </div>

            <!-- Structured Sections -->
            <div class="space-y-4 divide-y divide-nexo-border/60">
              <!-- 1. Executive Summary -->
              <div class="pt-3 first:pt-0">
                <h4 class="section-label mb-1">1. EXECUTIVE SUMMARY</h4>
                <p class="text-nexo-ink leading-relaxed">
                  {{ item.response.executive_summary }}
                </p>
              </div>

              <!-- 2. Relevant Developments (What Happened) -->
              <div class="pt-3">
                <h4 class="section-label mb-2">2. RELEVANT DEVELOPMENTS & TIMELINE</h4>
                <div v-if="item.response.relevant_developments && item.response.relevant_developments.length > 0" class="space-y-2">
                  <div
                    v-for="(dev, i) in item.response.relevant_developments"
                    :key="i"
                    class="rounded-[2px] border border-nexo-border bg-nexo-bg/30 p-2.5"
                  >
                    <div class="flex items-center justify-between text-[11px] text-nexo-muted mb-1">
                      <span class="font-semibold text-nexo-cobalt">{{ dev.source }}</span>
                      <span>{{ new Date(dev.date).toLocaleDateString() }}</span>
                    </div>
                    <h5 class="font-serif font-bold text-nexo-ink">{{ dev.event }}</h5>
                    <p class="text-nexo-text-2 mt-0.5 leading-relaxed">{{ dev.summary }}</p>
                  </div>
                </div>
                <p v-else class="text-xs text-nexo-muted italic">No specific event timeline recorded.</p>
              </div>

              <!-- 3. Business Implications (Why It Matters) -->
              <div class="pt-3">
                <h4 class="section-label mb-1">3. BUSINESS & OPERATIONAL IMPLICATIONS</h4>
                <ul class="list-disc list-inside space-y-1 text-nexo-ink leading-relaxed">
                  <li v-for="(imp, i) in item.response.business_implications" :key="i">
                    {{ imp }}
                  </li>
                </ul>
              </div>

              <!-- 4. Potential Scenarios (What Could Happen Next) -->
              <div class="pt-3">
                <h4 class="section-label mb-2">4. POTENTIAL SCENARIOS & FORWARD TRAJECTORIES</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div
                    v-for="(sc, i) in item.response.potential_scenarios"
                    :key="i"
                    class="rounded-[2px] border border-nexo-border bg-white p-3 space-y-1.5"
                  >
                    <h5 class="font-serif font-bold text-nexo-ink">{{ sc.scenario }}</h5>
                    <p class="text-[11px] text-nexo-text-2"><strong>Trigger:</strong> {{ sc.triggers }}</p>
                    <p class="text-[11px] text-nexo-cobalt"><strong>Implication:</strong> {{ sc.implication }}</p>
                  </div>
                </div>
              </div>

              <!-- 5. Supporting Evidence & Citations -->
              <div class="pt-3">
                <h4 class="section-label mb-2">5. SUPPORTING EVIDENCE & VERIFIED CITATIONS</h4>
                <div v-if="item.response.supporting_evidence && item.response.supporting_evidence.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div
                    v-for="(ev, i) in item.response.supporting_evidence"
                    :key="i"
                    class="rounded-[2px] border border-nexo-border bg-white p-2.5"
                  >
                    <div class="flex items-center justify-between text-[11px]">
                      <span class="font-semibold text-nexo-cobalt">[{{ ev.citation_id }}] {{ ev.source_name }}</span>
                      <a v-if="ev.source_url" :href="ev.source_url" target="_blank" class="text-nexo-muted hover:text-nexo-cobalt">
                        <ExternalLink class="h-3 w-3" />
                      </a>
                    </div>
                    <p class="font-medium text-nexo-ink mt-0.5 text-[11px]">{{ ev.title }}</p>
                    <p class="text-nexo-muted text-[10px] mt-1 line-clamp-2">{{ ev.excerpt }}</p>
                  </div>
                </div>
                <p v-else class="text-xs text-nexo-muted italic">No citations retrieved.</p>
              </div>

              <!-- 6. Recommended Monitoring Indicators -->
              <div class="pt-3">
                <h4 class="section-label mb-1.5">6. RECOMMENDED MONITORING & OBSERVABILITY INDICATORS</h4>
                <div class="flex flex-wrap gap-1.5">
                  <span
                    v-for="(mon, i) in item.response.recommended_monitoring_indicators"
                    :key="i"
                    class="rounded-[2px] border border-nexo-border bg-nexo-paper-2 px-2.5 py-1 text-[11px] text-nexo-ink"
                  >
                    {{ mon }}
                  </span>
                </div>
              </div>

              <!-- 7. Suggested Follow-Up Questions -->
              <div v-if="item.response.suggested_follow_up_questions && item.response.suggested_follow_up_questions.length > 0" class="pt-3">
                <h4 class="section-label mb-1.5">7. STRATEGIC FOLLOW-UP QUESTIONS</h4>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="(q, i) in item.response.suggested_follow_up_questions"
                    :key="i"
                    @click="handleAsk(q)"
                    class="rounded-[2px] border border-nexo-border bg-nexo-bg px-2.5 py-1 text-[11px] text-nexo-ink hover:bg-nexo-paper-2 hover:border-nexo-cobalt transition text-left"
                  >
                    {{ q }} →
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
