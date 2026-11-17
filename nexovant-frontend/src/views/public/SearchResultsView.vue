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
import Skeleton from '@/components/ui/Skeleton.vue'
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
  if (debounceTimeout) clearTimeout(debounceTimeout)
  if (!searchQuery.value.trim() || searchQuery.value.length < 2) {
    suggestions.value = []
    showSuggestions.value = false
    return
  }

  debounceTimeout = setTimeout(async () => {
    try {
      const res = await searchService.getSuggestions(searchQuery.value.trim())
      suggestions.value = res || []
      showSuggestions.value = suggestions.value.length > 0
    } catch {
      suggestions.value = []
      showSuggestions.value = false
    }
  }, 250)
}

const selectSuggestion = (s: any) => {
  searchQuery.value = s.text || s.title || s
  showSuggestions.value = false
  executeSearch()
}

const clearFilter = (key: keyof SearchFilters) => {
  delete filters.value[key]
  executeSearch()
}

const clearAllFilters = () => {
  filters.value = {}
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

const handleResultClick = async (item: SearchResultItem) => {
  try {
    await searchService.recordClick({
      query: searchQuery.value,
      result_id: item.id,
      result_type: item.content_type,
    })
  } catch {
    // Non-blocking telemetry
  }
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
    <div class="space-y-6 pb-16 text-left">
      <!-- Search Header & Omnibar (per Mockup Search results.png) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="editorial-headline text-2xl sm:text-3xl font-medium text-[#0E1A2B] tracking-tight flex items-center gap-2.5">
              <span>Intelligence Discovery</span>
              <span class="text-[11px] font-mono px-2 py-0.5 rounded-[2px] bg-[#E4E9F7] text-[#274888] font-bold">
                HYBRID VECTOR
              </span>
            </h1>
            <p class="text-xs text-[#5F6875] mt-1">
              Cross-entity verified intelligence, story clusters, research briefs, and historical events.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button
              v-if="searchQuery.trim()"
              type="button"
              class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
              @click="showSaveSearchModal = true"
            >
              <Bookmark class="w-3.5 h-3.5 text-[#274888]" />
              <span>Save & Monitor</span>
            </button>
          </div>
        </div>

        <!-- Search Omnibar with Suggestions Dropdown -->
        <div class="relative">
          <div class="relative flex items-center">
            <Search class="absolute left-3.5 w-4 h-4 text-[#5F6875] pointer-events-none" />
            <input
              v-model="searchQuery"
              type="text"
              class="w-full pl-10 pr-28 py-2.5 rounded-[4px] bg-white border border-[#D0D9CF] text-xs sm:text-sm text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888] transition-colors shadow-xs"
              placeholder="Search companies, executives, technologies, reports, or research topics..."
              @input="handleInput"
              @keydown.enter="executeSearch"
              @focus="showSuggestions = suggestions.length > 0"
            />
            <div class="absolute right-2 flex items-center gap-1.5">
              <button
                type="button"
                class="px-2 py-1 rounded-[2px] text-xs transition-colors"
                :class="isHybrid ? 'bg-[#E4E9F7] text-[#274888] font-semibold' : 'bg-[#EEEBE4] text-[#5F6875]'"
                :title="isHybrid ? 'Semantic vector + keyword hybrid retrieval enabled' : 'Keyword only'"
                @click="isHybrid = !isHybrid; executeSearch()"
              >
                <span class="flex items-center gap-1 font-mono text-[10px]">
                  <Sparkles class="w-3 h-3 text-[#274888]" />
                  {{ isHybrid ? 'Hybrid' : 'Keyword' }}
                </span>
              </button>
              <button
                type="button"
                class="btn-ink text-xs px-3 py-1.5"
                :disabled="isLoading"
                @click="executeSearch"
              >
                {{ isLoading ? 'Searching...' : 'Search' }}
              </button>
            </div>
          </div>

          <!-- Live Autocomplete Suggestions Dropdown -->
          <div
            v-if="showSuggestions && suggestions.length"
            class="absolute top-full left-0 right-0 mt-1.5 bg-white border border-[#D0D9CF] rounded-[2px] shadow-lg z-50 overflow-hidden divide-y divide-[#D0D9CF]/60"
          >
            <div class="p-2 text-[10px] font-mono uppercase tracking-wider text-[#5F6875] bg-[#F8FAF7]">
              Verified Suggestions
            </div>
            <button
              v-for="(item, idx) in suggestions"
              :key="idx"
              type="button"
              class="w-full px-3.5 py-2 text-left text-xs flex items-center justify-between hover:bg-[#EEEBE4]/60 transition-colors"
              @click="selectSuggestion(item)"
            >
              <div class="flex items-center gap-2.5 text-[#0E1A2B]">
                <Building2 v-if="item.type === 'company'" class="w-3.5 h-3.5 text-[#274888]" />
                <Users v-else-if="item.type === 'executive'" class="w-3.5 h-3.5 text-[#8A5A08]" />
                <FileText v-else-if="item.type === 'article'" class="w-3.5 h-3.5 text-[#5F6875]" />
                <Globe v-else-if="item.type === 'source'" class="w-3.5 h-3.5 text-[#17794D]" />
                <TrendingUp v-else class="w-3.5 h-3.5 text-[#B7791F]" />
                <span>{{ item.text }}</span>
              </div>
              <span class="text-[10px] uppercase font-mono text-[#5F6875] bg-[#EEEBE4] px-1.5 py-0.5 rounded-[2px]">
                {{ item.type }}
              </span>
            </button>
          </div>
        </div>

        <!-- Content Type Tabs -->
        <div class="flex items-center justify-between border-b border-[#D0D9CF] pt-1">
          <div class="flex overflow-x-auto gap-4 no-scrollbar">
            <button
              v-for="tab in contentTypeTabs"
              :key="tab.key"
              type="button"
              class="pb-2.5 text-xs font-medium border-b-2 whitespace-nowrap transition-colors"
              :class="activeContentType === tab.key ? 'border-[#0E1A2B] text-[#0E1A2B] font-semibold' : 'border-transparent text-[#5F6875] hover:text-[#0E1A2B]'"
              @click="activeContentType = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <button
            type="button"
            class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-[4px] border transition-colors mb-2"
            :class="showFilters || activeFilterCount > 0 ? 'bg-[#E4E9F7] border-[#274888]/40 text-[#274888]' : 'bg-white border-[#D0D9CF] text-[#5F6875] hover:text-[#0E1A2B]'"
            @click="showFilters = !showFilters"
          >
            <SlidersHorizontal class="w-3.5 h-3.5" />
            <span>Filters</span>
            <span v-if="activeFilterCount > 0" class="w-4 h-4 rounded-full bg-[#0E1A2B] text-white text-[10px] font-bold flex items-center justify-center">
              {{ activeFilterCount }}
            </span>
          </button>
        </div>

        <!-- Advanced Filters Collapsible Drawer -->
        <div v-if="showFilters" class="card-editorial p-4 bg-white border border-[#D0D9CF] space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <!-- Date Preset -->
            <div>
              <label class="block text-[#5F6875] mb-1 text-[11px] font-mono uppercase">Time Horizon</label>
              <select
                v-model="filters.date_preset"
                class="w-full rounded-[4px] bg-white border border-[#D0D9CF] text-xs text-[#0E1A2B] p-2 focus:outline-none"
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
              <label class="block text-[#5F6875] mb-1 text-[11px] font-mono uppercase">Verification Status</label>
              <select
                v-model="filters.verification_status"
                class="w-full rounded-[4px] bg-white border border-[#D0D9CF] text-xs text-[#0E1A2B] p-2 focus:outline-none"
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
              <label class="block text-[#5F6875] mb-1 text-[11px] font-mono uppercase">Source Credibility</label>
              <select
                v-model="filters.min_credibility"
                class="w-full rounded-[4px] bg-white border border-[#D0D9CF] text-xs text-[#0E1A2B] p-2 focus:outline-none"
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
              <label class="block text-[#5F6875] mb-1 text-[11px] font-mono uppercase">Rank Strategy</label>
              <select
                v-model="sortBy"
                class="w-full rounded-[4px] bg-white border border-[#D0D9CF] text-xs text-[#0E1A2B] p-2 focus:outline-none"
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
          <span class="text-[#5F6875] text-[11px]">Active filters:</span>

          <span v-if="filters.date_preset" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#EEEBE4] text-[#0E1A2B] border border-[#D0D9CF] text-[11px]">
            <span>Time: {{ filters.date_preset }}</span>
            <X class="w-3 h-3 cursor-pointer hover:text-[#B3261E]" @click="clearFilter('date_preset')" />
          </span>

          <span v-if="filters.verification_status" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#EEEBE4] text-[#0E1A2B] border border-[#D0D9CF] text-[11px]">
            <span>Status: {{ filters.verification_status }}</span>
            <X class="w-3 h-3 cursor-pointer hover:text-[#B3261E]" @click="clearFilter('verification_status')" />
          </span>

          <span v-if="filters.min_credibility" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#EEEBE4] text-[#0E1A2B] border border-[#D0D9CF] text-[11px]">
            <span>Credibility: {{ filters.min_credibility }}%+</span>
            <X class="w-3 h-3 cursor-pointer hover:text-[#B3261E]" @click="clearFilter('min_credibility')" />
          </span>

          <button
            type="button"
            class="text-[11px] text-[#274888] hover:underline ml-1 font-medium"
            @click="clearAllFilters"
          >
            Clear all
          </button>
        </div>
      </div>

      <!-- Telemetry Meta Bar -->
      <div v-if="!isLoading && searchQuery.trim()" class="flex items-center justify-between text-xs text-[#5F6875] px-1">
        <div>
          Found <span class="text-[#0E1A2B] font-semibold">{{ totalResults }}</span> records
          <span v-if="searchMeta.latency_ms"> ({{ searchMeta.latency_ms }}ms)</span>
        </div>
        <div class="flex items-center gap-2 font-mono text-[11px]">
          <span class="px-2 py-0.5 rounded-[2px] bg-white border border-[#D0D9CF]">
            Engine: {{ searchMeta.retrieval_method || 'hybrid_vector' }}
          </span>
        </div>
      </div>

      <!-- Loading Skeletons -->
      <div v-if="isLoading" class="space-y-4">
        <Skeleton class="h-28 w-full rounded-[2px]" />
        <Skeleton class="h-28 w-full rounded-[2px]" />
        <Skeleton class="h-28 w-full rounded-[2px]" />
      </div>

      <!-- Empty State -->
      <div
        v-else-if="results.length === 0 && searchQuery.trim()"
        class="card-editorial p-12 text-center space-y-3"
      >
        <Search class="w-8 h-8 text-[#5F6875] mx-auto" />
        <h3 class="font-serif text-lg font-medium text-[#0E1A2B]">No intelligence matches found</h3>
        <p class="text-xs text-[#5F6875] max-w-md mx-auto">
          No verified documents, entities, or reports matched "<span class="text-[#274888] font-semibold">{{ searchQuery }}</span>". Try broadening your search or clearing active filters.
        </p>
        <div class="pt-2 flex justify-center gap-2">
          <button
            type="button"
            class="px-3.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="clearAllFilters"
          >
            Clear Filters
          </button>
          <button
            type="button"
            class="btn-ink text-xs px-3.5 py-2 flex items-center gap-1.5"
            @click="showSaveSearchModal = true"
          >
            <Bookmark class="w-3.5 h-3.5" />
            <span>Create Automated Monitor</span>
          </button>
        </div>
      </div>

      <!-- Results List -->
      <div v-else class="space-y-3">
        <div
          v-for="item in results"
          :key="item.id"
          class="card-editorial p-5 space-y-3 hover:border-[#274888]/40 transition-colors group"
        >
          <!-- Top Row: Type Badge, Verification Status, Score, Date -->
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono uppercase tracking-wider font-semibold bg-[#EEEBE4] text-[#0E1A2B]">
                {{ item.content_type }}
              </span>

              <span
                class="px-2 py-0.5 rounded-[2px] text-[10px] uppercase font-mono font-bold flex items-center gap-1"
                :class="item.verification_status === 'verified' || item.verification_status === 'confirmed' ? 'badge-verified' : 'badge-developing'"
              >
                <CheckCircle2 v-if="item.verification_status === 'verified' || item.verification_status === 'confirmed'" class="w-3 h-3" />
                <AlertTriangle v-else class="w-3 h-3" />
                <span>{{ item.verification_status.replace('_', ' ') }}</span>
              </span>

              <!-- Machine Translation Label -->
              <span
                v-if="item.original_language && item.original_language !== 'en'"
                class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-medium bg-[#E4E9F7] text-[#274888] border border-[#274888]/30 flex items-center gap-1"
                title="Machine translated from original source text"
              >
                <span>Translated ({{ item.original_language.toUpperCase() }} &rarr; EN)</span>
              </span>

              <span v-if="item.source" class="text-[11px] text-[#5F6875]">
                {{ item.source.name }}
                <span v-if="item.source.credibility_score" class="text-[#274888] font-mono">
                  ({{ Math.round(item.source.credibility_score * 100) }}%)
                </span>
              </span>
            </div>

            <div class="flex items-center gap-3 text-[#5F6875] text-[11px]">
              <span v-if="item.corroboration_count > 1" class="text-[#17794D] font-medium">
                {{ item.corroboration_count }} corroborating sources
              </span>
              <span v-if="item.published_at">{{ new Date(item.published_at).toLocaleDateString() }}</span>
            </div>
          </div>

          <!-- Middle Row: Title & Link -->
          <div>
            <RouterLink
              :to="item.url"
              class="editorial-headline text-lg font-medium text-[#0E1A2B] group-hover:text-[#274888] transition-colors flex items-center justify-between"
              @click="handleResultClick(item)"
            >
              <span>{{ item.title }}</span>
              <ArrowRight class="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity text-[#274888] shrink-0" />
            </RouterLink>
            <p class="text-xs text-[#3F4A5A] leading-relaxed mt-1">
              {{ item.excerpt }}
            </p>
          </div>

          <!-- Relevance Explanation Pill -->
          <div v-if="item.relevance_explanation" class="p-2.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] text-[11px] text-[#5F6875] flex items-center gap-2">
            <Sparkles class="w-3.5 h-3.5 text-[#274888] shrink-0" />
            <span>{{ item.relevance_explanation }}</span>
          </div>

          <!-- Bottom Row: Matched Entity & Topic Chips + Actions -->
          <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-[#D0D9CF]/60">
            <div class="flex flex-wrap items-center gap-1.5">
              <span
                v-for="ent in item.matched_entities"
                :key="ent.id"
                class="px-2 py-0.5 rounded-full text-[10px] bg-[#F8FAF7] text-[#0E1A2B] border border-[#D0D9CF]"
              >
                {{ ent.name }}
              </span>
              <span
                v-for="topic in item.matched_topics"
                :key="topic"
                class="px-2 py-0.5 rounded-full text-[10px] bg-[#EEEBE4] text-[#3F4A5A]"
              >
                {{ topic }}
              </span>
            </div>

            <div class="flex items-center gap-2 text-xs">
              <button
                type="button"
                class="px-2.5 py-1 rounded-[4px] border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-[#EEEBE4] transition-colors flex items-center gap-1 text-[11px]"
                @click="openCorroboration(item)"
              >
                <ShieldCheck class="w-3.5 h-3.5 text-[#17794D]" />
                <span>Corroboration</span>
              </button>

              <button
                type="button"
                class="px-2.5 py-1 rounded-[4px] border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-[#EEEBE4] transition-colors flex items-center gap-1 text-[11px]"
                @click="openFeedback(item)"
              >
                <ThumbsUp class="w-3 h-3" />
                <span>Feedback</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between pt-4 border-t border-[#D0D9CF] text-xs text-[#5F6875]">
          <div>
            Page {{ currentPage }} of {{ totalPages }}
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs disabled:opacity-50"
              :disabled="currentPage <= 1"
              @click="currentPage--; executeSearch()"
            >
              Previous
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs disabled:opacity-50"
              :disabled="currentPage >= totalPages"
              @click="currentPage++; executeSearch()"
            >
              Next
            </button>
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
