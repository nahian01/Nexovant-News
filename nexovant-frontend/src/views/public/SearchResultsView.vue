<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  Building2,
  Check,
  CheckCircle2,
  Cpu,
  Download,
  ExternalLink,
  FileText,
  Layers,
  Plus,
  Search,
  Sparkles,
  TrendingUp,
  Users,
} from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import Input from '@/components/ui/Input.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Button from '@/components/ui/Button.vue'
import { searchService } from '@/services/api/search.service'
import { workspaceService } from '@/services/api/workspace.service'

const route = useRoute()
const searchQuery = ref((route.query.q as string) || '')
const activeTab = ref((route.query.tab as string) || 'all')
const isLoading = ref(false)
const isLoadingRag = ref(false)

const results = ref<any>({
  articles: [],
  companies: [],
  executives: [],
  technologies: [],
  reports: [],
  trends: [],
})

const ragResult = ref<{
  response: string
  source_citations: any[]
  cache_hit: boolean
  meta: any
} | null>(null)

const followedState = ref<Record<string, boolean>>({})

const tabs = [
  { key: 'all', label: 'All Results' },
  { key: 'rag', label: 'Executive AI Synthesis' },
  { key: 'articles', label: 'Articles & Stories' },
  { key: 'companies', label: 'Companies' },
  { key: 'technologies', label: 'Technologies' },
  { key: 'executives', label: 'Executives' },
  { key: 'reports', label: 'Publications' },
  { key: 'trends', label: 'Trend Signals' },
]

const executeSearch = async () => {
  if (!searchQuery.value.trim()) return
  isLoading.value = true
  try {
    const res = await searchService.search(searchQuery.value)
    results.value = {
      articles: res?.articles || [],
      companies: res?.companies || [],
      executives: res?.executives || [],
      technologies: res?.technologies || [],
      reports: res?.reports || [],
      trends: res?.trends || [],
    }

    // Trigger Grounded RAG alongside
    triggerRagSynthesis()
  } catch {
    results.value = {
      articles: [],
      companies: [],
      executives: [],
      technologies: [],
      reports: [],
      trends: [],
    }
  } finally {
    isLoading.value = false
  }
}

const triggerRagSynthesis = async () => {
  if (!searchQuery.value.trim() || searchQuery.value.trim().length < 3) return
  isLoadingRag.value = true
  try {
    const res = await searchService.queryRag(searchQuery.value)
    if (res?.success) {
      ragResult.value = {
        response: res.response,
        source_citations: res.source_citations || [],
        cache_hit: res.cache_hit,
        meta: res.meta || {},
      }
    }
  } catch {
    //
  } finally {
    isLoadingRag.value = false
  }
}

const toggleFollowEntity = async (type: string, id: string, name: string) => {
  const key = `${type}:${id}`
  followedState.value[key] = !followedState.value[key]
  try {
    await workspaceService.toggleFollow(type, id, name)
  } catch {
    followedState.value[key] = !followedState.value[key]
  }
}

watch(
  () => route.query.q,
  (newQ) => {
    if (newQ && newQ !== searchQuery.value) {
      searchQuery.value = newQ as string
      executeSearch()
    }
  }
)

onMounted(() => {
  if (searchQuery.value) {
    executeSearch()
  }
})
</script>

<template>
  <ThreeColumnLayout>
    <div class="mx-auto max-w-4xl space-y-6 text-left">
      <!-- Search Header & Input -->
      <div class="space-y-4">
        <div>
          <span class="text-[10px] font-mono uppercase tracking-wider text-nexo-text-muted">
            GLOBAL INTELLIGENCE & GROUNDED RAG RETRIEVAL
          </span>
          <h1 class="font-serif text-3xl font-medium tracking-tight text-nexo-text-primary">
            Search Results for "{{ searchQuery }}"
          </h1>
        </div>

        <div class="flex gap-2">
          <Input
            v-model="searchQuery"
            placeholder="Search companies, leadership, technologies, reports, or ask an executive question..."
            @keyup.enter="executeSearch"
          />
          <button
            @click="executeSearch"
            class="whitespace-nowrap rounded-xl bg-nexo-accent-cyan px-5 py-2 text-xs font-semibold text-white transition hover:bg-nexo-accent-cyan/90"
          >
            Search
          </button>
        </div>

        <Tabs :tabs="tabs" :active-tab="activeTab" @update:active-tab="(k) => (activeTab = k)" />
      </div>

      <!-- Results Display -->
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-20 w-full" />
        <Skeleton class="h-20 w-full" />
      </div>

      <div v-else class="space-y-6">
        <!-- Grounded AI Synthesis Section (Shown in 'all' and 'rag' tabs) -->
        <div v-if="(activeTab === 'all' || activeTab === 'rag')" class="space-y-3">
          <div v-if="isLoadingRag" class="space-y-3 rounded-2xl border border-nexo-border bg-white p-6 shadow-sm">
            <div class="flex items-center gap-2 text-xs font-mono text-nexo-accent-cyan animate-pulse">
              <Sparkles class="h-4 w-4" />
              <span>Retrieving grounded vector knowledge & synthesizing executive brief...</span>
            </div>
            <Skeleton class="h-16 w-full" />
          </div>

          <Card
            v-else-if="ragResult"
            padding="lg"
            class="relative space-y-4 overflow-hidden border-nexo-accent-cyan/40 bg-gradient-to-br from-[#f0f7f3] via-white to-[#f4f8f5]"
          >
            <!-- Header with Metadata Pill -->
            <div class="flex flex-col gap-2 border-b border-nexo-border/60 pb-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-center gap-2">
                <div class="rounded-lg bg-nexo-accent-cyan/10 p-1.5 text-nexo-accent-cyan">
                  <Cpu class="h-4 w-4" />
                </div>
                <h2 class="font-serif text-base font-semibold text-nexo-text-primary">
                  Executive AI Intelligence Synthesis (Grounded RAG)
                </h2>
              </div>

              <div class="flex items-center gap-2 text-[10px] font-mono">
                <span
                  v-if="ragResult.cache_hit"
                  class="flex items-center gap-1 rounded bg-emerald-900/30 px-2 py-0.5 text-emerald-700 border border-emerald-500/30"
                >
                  <CheckCircle2 class="h-3 w-3" />
                  Semantic Cache Hit
                </span>
                <span
                  v-else
                  class="rounded bg-cyan-900/20 px-2 py-0.5 text-cyan-800 border border-cyan-500/30"
                >
                  Live Vector Retrieval ({{ ragResult.meta?.latency_ms || 35 }}ms)
                </span>
              </div>
            </div>

            <!-- Grounded Response Text -->
            <p class="text-xs sm:text-sm text-nexo-text-primary leading-relaxed font-sans">
              {{ ragResult.response }}
            </p>

            <!-- Citations Footnotes -->
            <div v-if="ragResult.source_citations.length > 0" class="space-y-2 border-t border-nexo-border/50 pt-3">
              <span class="flex items-center gap-1.5 text-[10px] font-mono uppercase tracking-wider text-nexo-text-muted font-bold">
                <Layers class="h-3 w-3 text-nexo-accent-cyan" />
                Verified Citations & Grounding Excerpts ({{ ragResult.source_citations.length }})
              </span>

              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs">
                <div
                  v-for="(cite, idx) in ragResult.source_citations"
                  :key="idx"
                  class="space-y-1 rounded-xl border border-nexo-border/70 bg-white/70 p-2.5"
                >
                  <div class="flex items-center justify-between text-[11px] font-mono">
                    <span class="max-w-[200px] truncate font-bold text-nexo-text-primary">
                      [{{ idx + 1 }}] {{ cite.document_title }}
                    </span>
                    <span class="text-[10px] uppercase text-nexo-accent-cyan">
                      {{ (cite.document_type || 'Record').replace('_', ' ') }}
                    </span>
                  </div>
                  <p class="line-clamp-2 text-[11px] leading-normal text-nexo-text-secondary">
                    {{ cite.excerpt }}
                  </p>
                </div>
              </div>
            </div>
          </Card>
        </div>

        <!-- Articles Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'articles') && results.articles?.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted font-bold">
            Intelligence Articles ({{ results.articles.length }})
          </h2>
          <Card
            v-for="a in results.articles"
            :key="a.id"
            padding="md"
            class="space-y-2 border-nexo-border/80 transition hover:border-nexo-accent-cyan/35"
          >
            <div class="flex items-center space-x-2 text-xs font-mono">
              <Badge :variant="a.verification_status || 'verified'" size="sm" />
              <span class="text-nexo-text-muted">{{ a.source?.name || 'Corroborated Source' }}</span>
            </div>
            <h3 class="font-serif text-xl font-semibold text-nexo-text-primary">
              <RouterLink :to="`/news/${a.slug || a.id}`" class="transition hover:text-nexo-accent-cyan">
                {{ a.title }}
              </RouterLink>
            </h3>
            <p class="text-xs leading-relaxed text-nexo-text-secondary line-clamp-2">
              {{ a.content_normalized || a.content_raw || a.summary }}
            </p>
          </Card>
        </div>

        <!-- Companies Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'companies') && results.companies?.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted font-bold">
            Companies ({{ results.companies.length }})
          </h2>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Card
              v-for="c in results.companies"
              :key="c.id"
              padding="md"
              class="border-nexo-border/80"
            >
              <div class="flex items-center justify-between mb-1.5">
                <span class="font-serif text-base font-semibold text-nexo-text-primary">{{ c.name }}</span>
                <span v-if="c.ticker" class="font-mono text-xs text-nexo-accent-cyan">{{ c.ticker }}</span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary line-clamp-2">{{ c.summary }}</p>
              <div class="mt-3 flex items-center justify-between border-t border-nexo-border/60 pt-2.5">
                <span class="text-[10px] text-nexo-text-muted">{{ c.industry || 'Enterprise' }}</span>
                <button
                  type="button"
                  @click="toggleFollowEntity('company', c.id || c.slug, c.name)"
                  :class="[
                    'flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[11px] font-medium transition',
                    followedState[`company:${c.id || c.slug}`]
                      ? 'border-nexo-accent-cyan bg-[#e9f3ed] text-nexo-accent-cyan'
                      : 'border-nexo-border text-nexo-text-secondary hover:border-nexo-accent-cyan/40'
                  ]"
                >
                  <Check v-if="followedState[`company:${c.id || c.slug}`]" class="h-3 w-3" />
                  <Plus v-else class="h-3 w-3" />
                  <span>{{ followedState[`company:${c.id || c.slug}`] ? 'Following' : 'Follow' }}</span>
                </button>
              </div>
            </Card>
          </div>
        </div>

        <!-- Technologies Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'technologies') && results.technologies?.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted font-bold">
            Technologies ({{ results.technologies.length }})
          </h2>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Card
              v-for="t in results.technologies"
              :key="t.id"
              padding="md"
              class="border-nexo-border/80"
            >
              <div class="flex items-center justify-between mb-1.5">
                <span class="font-serif text-base font-semibold text-nexo-text-primary">{{ t.name }}</span>
                <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-mono text-[10px] text-emerald-800">{{ t.category || 'Strategic Tech' }}</span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary line-clamp-2">{{ t.description || t.summary }}</p>
              <div class="mt-3 flex items-center justify-end border-t border-nexo-border/60 pt-2.5">
                <button
                  type="button"
                  @click="toggleFollowEntity('technology', t.slug || t.id, t.name)"
                  :class="[
                    'flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[11px] font-medium transition',
                    followedState[`technology:${t.slug || t.id}`]
                      ? 'border-nexo-accent-cyan bg-[#e9f3ed] text-nexo-accent-cyan'
                      : 'border-nexo-border text-nexo-text-secondary hover:border-nexo-accent-cyan/40'
                  ]"
                >
                  <Check v-if="followedState[`technology:${t.slug || t.id}`]" class="h-3 w-3" />
                  <Plus v-else class="h-3 w-3" />
                  <span>{{ followedState[`technology:${t.slug || t.id}`] ? 'Following' : 'Follow' }}</span>
                </button>
              </div>
            </Card>
          </div>
        </div>

        <!-- Executives Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'executives') && results.executives?.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted font-bold">
            Executives ({{ results.executives.length }})
          </h2>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Card
              v-for="e in results.executives"
              :key="e.id"
              padding="md"
              class="border-nexo-border/80"
            >
              <div class="flex items-center justify-between mb-1.5">
                <span class="font-serif text-base font-semibold text-nexo-text-primary">{{ e.full_name || e.name }}</span>
                <span class="font-mono text-xs text-nexo-accent-cyan">{{ e.current_title || e.title }}</span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary line-clamp-2">{{ e.bio }}</p>
              <div class="mt-3 flex items-center justify-end border-t border-nexo-border/60 pt-2.5">
                <button
                  type="button"
                  @click="toggleFollowEntity('executive', e.id, e.full_name || e.name)"
                  :class="[
                    'flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[11px] font-medium transition',
                    followedState[`executive:${e.id}`]
                      ? 'border-nexo-accent-cyan bg-[#e9f3ed] text-nexo-accent-cyan'
                      : 'border-nexo-border text-nexo-text-secondary hover:border-nexo-accent-cyan/40'
                  ]"
                >
                  <Check v-if="followedState[`executive:${e.id}`]" class="h-3 w-3" />
                  <Plus v-else class="h-3 w-3" />
                  <span>{{ followedState[`executive:${e.id}`] ? 'Following' : 'Follow' }}</span>
                </button>
              </div>
            </Card>
          </div>
        </div>

        <!-- Reports / Publications Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'reports') && results.reports?.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase tracking-wider text-nexo-text-muted font-bold">
            Executive Publications & Dossiers ({{ results.reports.length }})
          </h2>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Card
              v-for="rep in results.reports"
              :key="rep.id"
              padding="md"
              class="border-nexo-border/80"
            >
              <div class="flex items-center justify-between mb-1.5">
                <span class="font-serif text-base font-semibold text-nexo-text-primary">{{ rep.title }}</span>
                <span class="rounded bg-nexo-bg px-2 py-0.5 text-[10px] font-medium uppercase text-nexo-text-secondary">
                  {{ (rep.publication_type || 'report').replace('_', ' ') }}
                </span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary line-clamp-2">{{ rep.executive_summary || rep.description }}</p>
              <div class="mt-3 flex items-center justify-between border-t border-nexo-border/60 pt-2.5">
                <span class="text-[10px] text-nexo-text-muted">{{ rep.published_at ? new Date(rep.published_at).toLocaleDateString() : 'Published' }}</span>
                <RouterLink
                  to="/reports"
                  class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-accent-cyan hover:underline"
                >
                  <span>Access PDF</span>
                  <ExternalLink class="h-3 w-3" />
                </RouterLink>
              </div>
            </Card>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="results.articles?.length === 0 && results.companies?.length === 0 && results.executives?.length === 0 && results.technologies?.length === 0 && results.reports?.length === 0 && !ragResult"
          class="rounded-2xl border border-nexo-border bg-white p-12 text-center"
        >
          <Search class="mx-auto mb-3 h-10 w-10 text-nexo-text-muted" />
          <h3 class="font-serif text-base font-semibold text-nexo-text-primary">No Results Found for "{{ searchQuery }}"</h3>
          <p class="mx-auto mt-1 max-w-sm text-xs text-nexo-text-secondary">
            Check spelling or search for a specific company, executive, ticker, or technology.
          </p>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
