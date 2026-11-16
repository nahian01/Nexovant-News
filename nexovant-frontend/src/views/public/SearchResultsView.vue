<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  Building2,
  CheckCircle2,
  Cpu,
  ExternalLink,
  FileText,
  Layers,
  Search,
  Sparkles,
  TrendingUp,
  Users,
  ShieldCheck,
  Bookmark,
  SlidersHorizontal,
  ThumbsUp,
  X,
  Clock,
  Globe,
  Filter,
  Check,
  ArrowRight,
  AlertTriangle,
} from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Button from '@/components/ui/Button.vue'
import SourceComparisonModal from '@/components/discovery/SourceComparisonModal.vue'
import SaveSearchModal from '@/components/discovery/SaveSearchModal.vue'
import RelevanceFeedbackModal from '@/components/discovery/RelevanceFeedbackModal.vue'
import {
  searchService,
  type SearchResultItem,
  type SearchFilters,
} from '@/services/api/search.service'
import { workspaceService } from '@/services/api/workspace.service'

const route = useRoute()
const router = useRouter()

const searchQuery = ref((route.query.q as string) || '')
const activeContentType = ref((route.query.type as string) || 'all')
const isHybrid = ref(true)
const sortBy = ref((route.query.sort as string) || 'relevance')
const currentPage = ref(Number(route.query.page) || 1)
const perPage = ref(15)

// Filters State
const showFilters = ref(false)
const filters = ref<SearchFilters>({
  date_preset: (route.query.date as string) || '',
  verification_status: (route.query.verification as string) || '',
  min_credibility: route.query.credibility ? Number(route.query.credibility) : undefined,
  region: (route.query.region as string) || '',
})

// Autocomplete State
const suggestions = ref<any[]>([])
const showSuggestions = ref(false)
let debounceTimeout: any = null

// Results State
const isLoading = ref(false)
const results = ref<SearchResultItem[]>([])
const totalResults = ref(0)
const totalPages = ref(1)
const searchMeta = ref<any>({})

// Modals State
const showCorroborationModal = ref(false)
const selectedItemId = ref<string>('')
const selectedItemTitle = ref<string>('')
const selectedItemType = ref<string>('article')

const showSaveSearchModal = ref(false)

const showFeedbackModal = ref(false)
const feedbackItemId = ref<string>('')
const feedbackItemTitle = ref<string>('')
const feedbackItemType = ref<string>('article')

const contentTypeTabs = [
  { key: 'all', label: 'All Intelligence' },
  { key: 'article', label: 'Articles' },
  { key: 'story', label: 'Story Clusters' },
  { key: 'report', label: 'Research Reports' },
  { key: 'publication', label: 'Publications' },
  { key: 'entity', label: 'Entities' },
  { key: 'event', label: 'Timeline Events' },
  { key: 'source', label: 'Publishers' },
  { key: 'collection', label: 'Collections' },
]

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.date_preset) count++
  if (filters.value.verification_status) count++
  if (filters.value.min_credibility) count++
  if (filters.value.region) count++
  if (sortBy.value !== 'relevance') count++
  return count
})

const executeSearch = async () => {
  if (!searchQuery.value.trim()) return

  isLoading.value = true
  showSuggestions.value = false

  try {
    const res = await searchService.search({
      q: searchQuery.value.trim(),
      content_type: activeContentType.value !== 'all' ? activeContentType.value : undefined,
      filters: filters.value,
      sort_by: sortBy.value,
      page: currentPage.value,
      per_page: perPage.value,
      hybrid: isHybrid.value,
    })

    if (res?.success) {
      results.value = res.results || []
      totalResults.value = res.total || 0
      totalPages.value = res.total_pages || 1
      searchMeta.value = res.meta || {}
    }
  } catch {
    results.value = []
    totalResults.value = 0
  } finally {
    isLoading.value = false
  }
}

const handleInput = () => {
  clearTimeout(debounceTimeout)
  if (searchQuery.value.trim().length >= 2) {
    debounceTimeout = setTimeout(async () => {
      try {
        suggestions.value = await searchService.getSuggestions(searchQuery.value)
        showSuggestions.value = suggestions.value.length > 0
      } catch {
        suggestions.value = []
      }
    }, 250)
  } else {
    suggestions.value = []
    showSuggestions.value = false
  }
}

const selectSuggestion = (item: any) => {
  searchQuery.value = item.text || item.slug || ''
  showSuggestions.value = false
  executeSearch()
}

const clearFilter = (key: keyof SearchFilters) => {
  filters.value[key] = undefined
  executeSearch()
}

const clearAllFilters = () => {
  filters.value = {
    date_preset: '',
    verification_status: '',
    min_credibility: undefined,
    region: '',
  }
  sortBy.value = 'relevance'
  executeSearch()
}

const openCorroboration = (item: SearchResultItem) => {
  selectedItemId.value = item.id
  selectedItemTitle.value = item.title
  selectedItemType.value = item.content_type
  showCorroborationModal.value = true
}

const openFeedback = (item: SearchResultItem) => {
  feedbackItemId.value = item.id
  feedbackItemTitle.value = item.title
  feedbackItemType.value = item.content_type
  showFeedbackModal.value = true
}

const handleResultClick = (item: SearchResultItem) => {
  searchService.recordClick({
    query: searchQuery.value,
    result_id: item.id,
    result_type: item.content_type,
  }).catch(() => {})
}

onMounted(() => {
  if (searchQuery.value.trim()) {
    executeSearch()
  }
})

watch(
  () => [activeContentType.value, sortBy.value],
  () => {
    currentPage.value = 1
    executeSearch()
  }
)
</script>

<template>
  <ThreeColumnLayout>
    <div class="max-w-5xl mx-auto space-y-6 pb-16">
      <!-- Search Header & Omnibar -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-serif text-white tracking-tight flex items-center gap-2.5">
              <span>Intelligence Discovery</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-sans font-medium bg-primary-500/10 text-primary-400 border border-primary-500/20">
                Hybrid Retrieval
              </span>
            </h1>
            <p class="text-xs text-neutral-400 mt-1">
              Cross-entity verified intelligence, story clusters, research reports, and historical events.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <Button
              v-if="searchQuery.trim()"
              variant="outline"
              size="sm"
              class="gap-1.5 text-xs border-primary-500/30 text-primary-300 hover:bg-primary-500/10"
              @click="showSaveSearchModal = true"
            >
              <Bookmark class="w-3.5 h-3.5" />
              <span>Save & Monitor</span>
            </Button>
          </div>
        </div>

        <!-- Search Bar with Suggestions Dropdown -->
        <div class="relative">
          <div class="relative flex items-center">
            <Search class="absolute left-3.5 w-4 h-4 text-neutral-400 pointer-events-none" />
            <input
              v-model="searchQuery"
              type="text"
              class="w-full pl-10 pr-24 py-3 rounded-xl bg-neutral-900 border border-neutral-800 text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-all shadow-lg"
              placeholder="Search companies, executives, technologies, reports, or research topics..."
              @input="handleInput"
              @keydown.enter="executeSearch"
              @focus="showSuggestions = suggestions.length > 0"
            />
            <div class="absolute right-3 flex items-center gap-2">
              <button
                type="button"
                class="px-2 py-1 rounded text-xs transition-colors"
                :class="isHybrid ? 'bg-primary-500/15 text-primary-300 border border-primary-500/30' : 'bg-neutral-800 text-neutral-400'"
                :title="isHybrid ? 'Semantic vector + keyword hybrid retrieval enabled' : 'Keyword only'"
                @click="isHybrid = !isHybrid; executeSearch()"
              >
                <span class="flex items-center gap-1 font-mono text-[11px]">
                  <Sparkles class="w-3 h-3 text-primary-400" />
                  {{ isHybrid ? 'Hybrid' : 'Keyword' }}
                </span>
              </button>
              <Button size="sm" class="h-8 px-3 text-xs" :loading="isLoading" @click="executeSearch">
                Search
              </Button>
            </div>
          </div>

          <!-- Live Autocomplete Suggestions Dropdown -->
          <div
            v-if="showSuggestions && suggestions.length"
            class="absolute top-full left-0 right-0 mt-1.5 bg-neutral-900 border border-neutral-800 rounded-xl shadow-2xl z-50 overflow-hidden divide-y divide-neutral-800/60"
          >
            <div class="p-2 text-[11px] font-mono uppercase tracking-wider text-neutral-500 bg-neutral-950/60">
              Verified Suggestions
            </div>
            <button
              v-for="(item, idx) in suggestions"
              :key="idx"
              type="button"
              class="w-full px-3.5 py-2.5 text-left text-xs flex items-center justify-between hover:bg-neutral-800/70 transition-colors"
              @click="selectSuggestion(item)"
            >
              <div class="flex items-center gap-2.5 text-neutral-200">
                <Building2 v-if="item.type === 'company'" class="w-3.5 h-3.5 text-primary-400" />
                <Users v-else-if="item.type === 'executive'" class="w-3.5 h-3.5 text-blue-400" />
                <FileText v-else-if="item.type === 'article'" class="w-3.5 h-3.5 text-neutral-400" />
                <Globe v-else-if="item.type === 'source'" class="w-3.5 h-3.5 text-emerald-400" />
                <TrendingUp v-else class="w-3.5 h-3.5 text-amber-400" />
                <span>{{ item.text }}</span>
              </div>
              <span class="text-[10px] uppercase font-mono text-neutral-500 bg-neutral-800 px-1.5 py-0.5 rounded">
                {{ item.type }}
              </span>
            </button>
          </div>
        </div>

        <!-- Content Type Tabs -->
        <div class="flex items-center justify-between border-b border-neutral-800 pt-1">
          <div class="flex overflow-x-auto gap-4 no-scrollbar">
            <button
              v-for="tab in contentTypeTabs"
              :key="tab.key"
              type="button"
              class="pb-2.5 text-xs font-medium border-b-2 whitespace-nowrap transition-colors"
              :class="activeContentType === tab.key ? 'border-primary-500 text-primary-400' : 'border-transparent text-neutral-400 hover:text-white'"
              @click="activeContentType = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <button
            type="button"
            class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-md border transition-colors mb-2"
            :class="showFilters || activeFilterCount > 0 ? 'bg-primary-500/10 border-primary-500/30 text-primary-300' : 'bg-neutral-900 border-neutral-800 text-neutral-400 hover:text-white'"
            @click="showFilters = !showFilters"
          >
            <SlidersHorizontal class="w-3.5 h-3.5" />
            <span>Filters</span>
            <span v-if="activeFilterCount > 0" class="w-4 h-4 rounded-full bg-primary-500 text-black text-[10px] font-bold flex items-center justify-center">
              {{ activeFilterCount }}
            </span>
          </button>
        </div>

        <!-- Advanced Filters Collapsible Drawer -->
        <div v-if="showFilters" class="p-4 rounded-xl bg-neutral-900/80 border border-neutral-800 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <!-- Date Preset -->
            <div>
              <label class="block text-neutral-400 mb-1 text-[11px]">Time Horizon</label>
              <select
                v-model="filters.date_preset"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 text-xs text-white p-2 focus:outline-none"
                @change="executeSearch"
              >
                <option value="">All Time</option>
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="1y">Last Year</option>
              </select>
            </div>

            <!-- Verification Status -->
            <div>
              <label class="block text-neutral-400 mb-1 text-[11px]">Verification Status</label>
              <select
                v-model="filters.verification_status"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 text-xs text-white p-2 focus:outline-none"
                @change="executeSearch"
              >
                <option value="">All Statuses</option>
                <option value="verified">Verified Facts</option>
                <option value="reported_reliable">Reported Reliable</option>
                <option value="disputed">Disputed Claims</option>
                <option value="unverified">Unverified Reports</option>
              </select>
            </div>

            <!-- Min Credibility -->
            <div>
              <label class="block text-neutral-400 mb-1 text-[11px]">Source Credibility</label>
              <select
                v-model="filters.min_credibility"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 text-xs text-white p-2 focus:outline-none"
                @change="executeSearch"
              >
                <option :value="undefined">Any Reliability</option>
                <option :value="70">70%+ Trust Score</option>
                <option :value="80">80%+ Reputable Outlets</option>
                <option :value="90">90%+ Established Primary</option>
              </select>
            </div>

            <!-- Sort By -->
            <div>
              <label class="block text-neutral-400 mb-1 text-[11px]">Rank Strategy</label>
              <select
                v-model="sortBy"
                class="w-full rounded-lg bg-neutral-800 border border-neutral-700 text-xs text-white p-2 focus:outline-none"
                @change="executeSearch"
              >
                <option value="relevance">Most Relevant (Hybrid)</option>
                <option value="recent">Most Recent First</option>
                <option value="oldest">Oldest First</option>
                <option value="highest_credibility">Highest Credibility</option>
                <option value="highest_corroboration">Highest Corroboration</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Active Filter Chips -->
        <div v-if="activeFilterCount > 0" class="flex flex-wrap items-center gap-2 pt-1 text-xs">
          <span class="text-neutral-500 text-[11px]">Active filters:</span>

          <span v-if="filters.date_preset" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-neutral-800 text-neutral-200 border border-neutral-700 text-[11px]">
            <span>Time: {{ filters.date_preset }}</span>
            <X class="w-3 h-3 cursor-pointer hover:text-white" @click="clearFilter('date_preset')" />
          </span>

          <span v-if="filters.verification_status" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-neutral-800 text-neutral-200 border border-neutral-700 text-[11px]">
            <span>Status: {{ filters.verification_status }}</span>
            <X class="w-3 h-3 cursor-pointer hover:text-white" @click="clearFilter('verification_status')" />
          </span>

          <span v-if="filters.min_credibility" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-neutral-800 text-neutral-200 border border-neutral-700 text-[11px]">
            <span>Credibility: {{ filters.min_credibility }}%+</span>
            <X class="w-3 h-3 cursor-pointer hover:text-white" @click="clearFilter('min_credibility')" />
          </span>

          <button
            type="button"
            class="text-[11px] text-primary-400 hover:underline ml-1"
            @click="clearAllFilters"
          >
            Clear all
          </button>
        </div>
      </div>

      <!-- Telemetry Meta Bar -->
      <div v-if="!isLoading && searchQuery.trim()" class="flex items-center justify-between text-xs text-neutral-500 px-1">
        <div>
          Found <span class="text-white font-medium">{{ totalResults }}</span> records
          <span v-if="searchMeta.latency_ms"> ({{ searchMeta.latency_ms }}ms)</span>
        </div>
        <div class="flex items-center gap-2 font-mono text-[11px]">
          <span class="px-1.5 py-0.5 rounded bg-neutral-900 border border-neutral-800">
            Engine: {{ searchMeta.retrieval_method || 'hybrid_vector' }}
          </span>
        </div>
      </div>

      <!-- Loading Skeletons -->
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-28 w-full rounded-xl" />
        <Skeleton class="h-28 w-full rounded-xl" />
        <Skeleton class="h-28 w-full rounded-xl" />
      </div>

      <!-- Empty State -->
      <div
        v-else-if="results.length === 0 && searchQuery.trim()"
        class="py-16 text-center space-y-3 rounded-xl bg-neutral-900/40 border border-neutral-800/80 p-6"
      >
        <Search class="w-10 h-10 text-neutral-600 mx-auto" />
        <h3 class="text-base font-serif text-white">No intelligence matches found</h3>
        <p class="text-xs text-neutral-400 max-w-md mx-auto">
          No verified documents, entities, or reports matched "<span class="text-primary-400">{{ searchQuery }}</span>". Try broadening your search or clearing active filters.
        </p>
        <div class="pt-2 flex justify-center gap-2">
          <Button variant="outline" size="sm" @click="clearAllFilters">
            Clear Filters
          </Button>
          <Button size="sm" class="gap-1.5" @click="showSaveSearchModal = true">
            <Bookmark class="w-3.5 h-3.5" />
            <span>Create Automated Monitor</span>
          </Button>
        </div>
      </div>

      <!-- Results List -->
      <div v-else class="space-y-3">
        <div
          v-for="item in results"
          :key="item.id"
          class="p-4 rounded-xl bg-neutral-900/70 border border-neutral-800 hover:border-neutral-700 transition-all space-y-3 group"
        >
          <!-- Top Row: Type Badge, Verification Status, Score, Date -->
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase tracking-wider font-semibold bg-neutral-800 text-neutral-300 border border-neutral-700">
                {{ item.content_type }}
              </span>

              <span
                class="px-2 py-0.5 rounded text-[10px] uppercase font-mono font-medium flex items-center gap-1"
                :class="item.verification_status === 'verified' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
              >
                <CheckCircle2 v-if="item.verification_status === 'verified'" class="w-3 h-3" />
                <AlertTriangle v-else class="w-3 h-3" />
                <span>{{ item.verification_status.replace('_', ' ') }}</span>
              </span>

              <!-- Machine Translation Label (Stage 14.1 Requirement 7 & 16) -->
              <span
                v-if="item.original_language && item.original_language !== 'en'"
                class="px-2 py-0.5 rounded text-[10px] font-mono font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center gap-1"
                title="Machine translated from original source text"
              >
                <span>Translated ({{ item.original_language.toUpperCase() }} &rarr; EN)</span>
              </span>

              <span v-if="item.source" class="text-[11px] text-neutral-400">
                {{ item.source.name }}
                <span v-if="item.source.credibility_score" class="text-primary-400 font-mono">
                  ({{ Math.round(item.source.credibility_score) }}%)
                </span>
              </span>
            </div>

            <div class="flex items-center gap-3 text-neutral-500 text-[11px]">
              <span v-if="item.corroboration_count > 1" class="text-emerald-400 font-medium">
                {{ item.corroboration_count }} corroborators
              </span>
              <span v-if="item.published_at">{{ new Date(item.published_at).toLocaleDateString() }}</span>
            </div>
          </div>

          <!-- Middle Row: Title & Link -->
          <div>
            <RouterLink
              :to="item.url"
              class="text-base font-serif font-medium text-white group-hover:text-primary-300 transition-colors flex items-center justify-between"
              @click="handleResultClick(item)"
            >
              <span>{{ item.title }}</span>
              <ArrowRight class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity text-primary-400 shrink-0" />
            </RouterLink>
            <p class="text-xs text-neutral-300 leading-relaxed mt-1">
              {{ item.excerpt }}
            </p>
          </div>

          <!-- Relevance Explanation Pill -->
          <div v-if="item.relevance_explanation" class="p-2 rounded-lg bg-neutral-950/70 border border-neutral-800/60 text-[11px] text-neutral-400 flex items-center gap-2">
            <Sparkles class="w-3.5 h-3.5 text-primary-400 shrink-0" />
            <span>{{ item.relevance_explanation }}</span>
          </div>

          <!-- Bottom Row: Matched Entity & Topic Chips + Actions -->
          <div class="flex flex-wrap items-center justify-between gap-3 pt-1 border-t border-neutral-800/50">
            <div class="flex flex-wrap items-center gap-1.5">
              <span
                v-for="ent in item.matched_entities"
                :key="ent.id"
                class="px-2 py-0.5 rounded text-[10px] bg-primary-500/10 text-primary-300 border border-primary-500/20"
              >
                {{ ent.name }}
              </span>
              <span
                v-for="topic in item.matched_topics"
                :key="topic"
                class="px-2 py-0.5 rounded text-[10px] bg-neutral-800 text-neutral-400"
              >
                {{ topic }}
              </span>
            </div>

            <div class="flex items-center gap-2 text-xs">
              <button
                type="button"
                class="px-2 py-1 rounded text-[11px] text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors flex items-center gap-1"
                @click="openCorroboration(item)"
              >
                <ShieldCheck class="w-3.5 h-3.5 text-emerald-400" />
                <span>Corroboration</span>
              </button>

              <button
                type="button"
                class="px-2 py-1 rounded text-[11px] text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors flex items-center gap-1"
                @click="openFeedback(item)"
              >
                <ThumbsUp class="w-3 h-3" />
                <span>Feedback</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between pt-4 border-t border-neutral-800 text-xs text-neutral-400">
          <div>
            Page {{ currentPage }} of {{ totalPages }}
          </div>
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              :disabled="currentPage <= 1"
              @click="currentPage--; executeSearch()"
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              :disabled="currentPage >= totalPages"
              @click="currentPage++; executeSearch()"
            >
              Next
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <SourceComparisonModal
      v-model="showCorroborationModal"
      :item-id="selectedItemId"
      :item-type="selectedItemType"
      :item-title="selectedItemTitle"
    />

    <SaveSearchModal
      v-model="showSaveSearchModal"
      :query="searchQuery"
      :filters="filters"
    />

    <RelevanceFeedbackModal
      v-model="showFeedbackModal"
      :query="searchQuery"
      :result-id="feedbackItemId"
      :result-title="feedbackItemTitle"
      :result-type="feedbackItemType"
    />
  </ThreeColumnLayout>
</template>
