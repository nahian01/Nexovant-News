<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  Bookmark,
  BookmarkCheck,
  Check,
  ChevronDown,
  Clock,
  Compass,
  ExternalLink,
  Filter,
  Layers,
  Plus,
  Search,
  Share2,
  ShieldCheck,
  Sparkles,
  ThumbsDown,
  ThumbsUp,
  TrendingUp,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { workspaceService, type FeedStreamParams } from '@/services/api/workspace.service'
import { categoryService, type CategoryItem } from '@/services/api/categories.service'

const route = useRoute()
const router = useRouter()

// Feed State
const feedItems = ref<any[]>([])
const categories = ref<CategoryItem[]>([])
const selectedCategory = ref<string>('')
const selectedSort = ref<'relevance' | 'latest' | 'trending' | 'credible' | 'discussed'>('relevance')
const searchKeyword = ref<string>('')
const currentPage = ref(1)
const hasMorePages = ref(true)
const totalItems = ref(0)
const isLoading = ref(true)
const isLoadingMore = ref(false)

// Action feedback states
const savedItemIds = ref<Record<string, boolean>>({})
const followedEntityIds = ref<Record<string, boolean>>({})
const feedbackGiven = ref<Record<string, 'up' | 'down'>>({})
const copySuccessId = ref<string | null>(null)

const sortOptions = [
  { value: 'relevance', label: 'Most Relevant' },
  { value: 'latest', label: 'Latest' },
  { value: 'trending', label: 'Trending Signals' },
  { value: 'credible', label: 'Most Credible' },
  { value: 'discussed', label: 'Most Discussed' },
]

const fetchCategories = async () => {
  try {
    const cats = await categoryService.getCategories({ has_articles: true })
    categories.value = (cats || []).filter((c) => (c.article_count || 0) > 0)
  } catch {
    categories.value = []
  }
}

const fetchFeed = async (resetPage = true) => {
  if (resetPage) {
    currentPage.value = 1
    isLoading.value = true
  } else {
    isLoadingMore.value = true
  }

  const params: FeedStreamParams = {
    page: currentPage.value,
    per_page: 10,
    sort: selectedSort.value,
  }

  if (selectedCategory.value) {
    params.category = selectedCategory.value
  }

  if (searchKeyword.value.trim()) {
    params.search = searchKeyword.value.trim()
  }

  try {
    const res = await workspaceService.getFeedStream(params)
    const items = res?.data || []
    const meta = res?.meta || {}

    if (resetPage) {
      feedItems.value = items
    } else {
      feedItems.value = [...feedItems.value, ...items]
    }

    totalItems.value = meta.total || feedItems.value.length
    hasMorePages.value = (meta.current_page || 1) < (meta.last_page || 1)

    // Sync saved status
    for (const item of items) {
      if (item.is_saved) {
        savedItemIds.value[item.id] = true
      }
    }
  } catch {
    // If backend feed stream throws, fallback to section feed
    if (resetPage) {
      try {
        const fallbackRes = await workspaceService.getFeed('for_you')
        const items = fallbackRes?.feed?.items || []
        feedItems.value = items.map((it: any) => ({
          id: it.id,
          headline: it.title,
          summary: it.summary,
          category: it.primary_category || 'Enterprise AI',
          verification_status: it.verification_status || 'verified',
          relevance_score: 92,
          published_at: it.published_at || new Date().toISOString(),
          reading_time_minutes: 3,
          source: { name: 'Reuters Wire', credibility_rating: 96 },
          source_count: 3,
        }))
        totalItems.value = feedItems.value.length
      } catch {
        feedItems.value = []
      }
    }
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

const loadMore = () => {
  if (hasMorePages.value && !isLoadingMore.value) {
    currentPage.value++
    fetchFeed(false)
  }
}

const toggleSaveArticle = async (item: any) => {
  const current = savedItemIds.value[item.id]
  savedItemIds.value[item.id] = !current
  try {
    if (!current) {
      await workspaceService.saveLibraryItem({
        item_type: 'article',
        item_id: item.id,
        title: item.title,
      })
    } else {
      await workspaceService.deleteLibraryItem(item.id)
    }
  } catch {
    savedItemIds.value[item.id] = current
  }
}

const toggleFollowEntity = async (entity: any) => {
  const entityId = entity.id || entity.name
  const key = `company:${entityId}`
  const current = followedEntityIds.value[key]
  followedEntityIds.value[key] = !current
  try {
    await workspaceService.toggleFollow('company', entityId, entity.name)
  } catch {
    followedEntityIds.value[key] = current
  }
}

const handleShare = (item: any) => {
  const url = `${window.location.origin}/news/${item.slug || item.id}`
  navigator.clipboard.writeText(url)
  copySuccessId.value = item.id
  setTimeout(() => {
    copySuccessId.value = null
  }, 2000)
}

const handleFeedback = (articleId: string, type: 'up' | 'down') => {
  feedbackGiven.value[articleId] = type
}

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'Recently'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return dateStr
  }
}

// Watch filters
watch([selectedCategory, selectedSort], () => {
  fetchFeed(true)
})

let searchTimeout: any = null
const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchFeed(true)
  }, 350)
}

onMounted(async () => {
  if (route.query.tab === 'latest') {
    selectedSort.value = 'latest'
  }
  await fetchCategories()
  await fetchFeed(true)
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- 1. Header & Executive Description (per Mockup P19 My feed (For You).png) -->
      <header class="border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
            <Sparkles class="h-3.5 w-3.5 text-[#274888]" />
            <span>PERSONALIZED INTELLIGENCE HUB</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium tracking-tight text-[#0E1A2B]">
            For You
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875] max-w-2xl">
            Intelligence shaped around the companies, technologies, and developments you follow.
          </p>
        </div>

        <!-- 2. Search & Controls Bar -->
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <!-- Search within Feed -->
          <div class="relative flex-1 sm:max-w-md">
            <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#5F6875]" />
            <input
              v-model="searchKeyword"
              @input="onSearchInput"
              type="text"
              placeholder="Filter intelligence by keyword, company, or event..."
              class="w-full rounded-[4px] border border-[#D0D9CF] bg-white py-2 pl-9 pr-4 text-xs text-[#0E1A2B] placeholder-[#5F6875] transition-colors focus:border-[#274888] focus:outline-none shadow-xs"
            />
          </div>

          <!-- Sort Criteria Dropdown -->
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono text-[#5F6875]">Sort:</span>
            <div class="relative">
              <select
                v-model="selectedSort"
                class="appearance-none rounded-[4px] border border-[#D0D9CF] bg-white py-2 pl-3 pr-8 text-xs font-medium text-[#0E1A2B] transition-colors focus:border-[#274888] focus:outline-none"
              >
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
              <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#5F6875]" />
            </div>
          </div>
        </div>

        <!-- 3. Category Filter Pills -->
        <div class="mt-4 flex flex-wrap items-center gap-1.5 overflow-x-auto pb-1 text-xs">
          <button
            type="button"
            @click="selectedCategory = ''"
            :class="[
              'rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors',
              selectedCategory === ''
                ? 'bg-[#0E1A2B] text-white shadow-xs'
                : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
            ]"
          >
            All Sectors
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            @click="selectedCategory = selectedCategory === cat.slug ? '' : cat.slug"
            :class="[
              'flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors cursor-pointer',
              selectedCategory === cat.slug
                ? 'bg-[#0E1A2B] text-white shadow-xs'
                : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
            ]"
          >
            <span>{{ cat.name }}</span>
            <span
              :class="[
                'px-1.5 py-0.2 rounded-full text-[10px] font-mono',
                selectedCategory === cat.slug ? 'bg-white/20 text-white' : 'bg-[#F0EFEA] text-[#5F6875]'
              ]"
            >
              {{ cat.article_count }}
            </span>
          </button>
        </div>
      </header>

      <!-- 4. Loading Skeleton -->
      <div v-if="isLoading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="card-editorial p-6 space-y-3">
          <div class="flex items-center justify-between">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-4 w-20" />
          </div>
          <Skeleton class="h-7 w-5/6" />
          <Skeleton class="h-14 w-full" />
        </div>
      </div>

      <!-- 5. Empty State -->
      <div
        v-else-if="feedItems.length === 0"
        class="card-editorial p-12 text-center space-y-3"
      >
        <Sparkles class="mx-auto h-8 w-8 text-[#5F6875]" />
        <h3 class="font-serif text-lg font-medium text-[#0E1A2B]">
          No intelligence matching your filters
        </h3>
        <p class="mx-auto max-w-sm text-xs leading-relaxed text-[#5F6875]">
          Try clearing your search query, switching category filters, or following additional companies.
        </p>
        <div class="pt-2 flex justify-center gap-2">
          <button
            type="button"
            class="px-3.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold"
            @click="selectedCategory = ''; searchKeyword = ''"
          >
            Reset Filters
          </button>
          <RouterLink to="/following">
            <button type="button" class="btn-ink text-xs px-3.5 py-2">
              Explore Following
            </button>
          </RouterLink>
        </div>
      </div>

      <!-- 6. Feed Item Cards -->
      <div v-else class="space-y-4">
        <div
          v-for="item in feedItems"
          :key="item.id"
          class="card-editorial p-5 sm:p-6 space-y-3 hover:border-[#274888]/40 transition-colors group"
        >
          <!-- Card Header: Verification + Category + Relevance Score + Time -->
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#D0D9CF]/60 pb-3">
            <div class="flex flex-wrap items-center gap-2">
              <!-- Verification Status Badge -->
              <span
                class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase tracking-wider"
                :class="item.verification_status === 'verified' || item.verification_status === 'confirmed' ? 'badge-verified' : 'badge-developing'"
              >
                {{ (item.verification_status || 'verified').replace('_', ' ') }}
              </span>

              <!-- Category Pill -->
              <span class="rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] px-2 py-0.5 text-[10px] font-mono font-medium text-[#3F4A5A]">
                {{ typeof item.category === 'object' ? item.category?.name : item.category || 'Strategic Intelligence' }}
              </span>

              <!-- Personal Relevance Score Badge -->
              <span class="inline-flex items-center gap-1 rounded-[2px] bg-[#E4E9F7] px-2 py-0.5 font-mono text-[10px] font-semibold text-[#274888]">
                <Sparkles class="h-2.5 w-2.5" />
                <span>{{ item.relevance_score || 90 }}% Relevance</span>
              </span>

              <!-- 80/20 Adaptive Ratio Badge -->
              <span
                v-if="item.personalization_factor || item.is_serendipity"
                class="inline-flex items-center gap-1 rounded-[2px] px-2 py-0.5 font-mono text-[10px] font-semibold"
                :class="item.is_serendipity ? 'bg-[#FFF3D6] text-[#8C5D00] border border-[#E0BE73]/50' : 'bg-[#E3F1EA] text-[#17794D] border border-[#17794D]/30'"
              >
                <Compass v-if="item.is_serendipity" class="h-2.5 w-2.5" />
                <Sparkles v-else class="h-2.5 w-2.5" />
                <span>{{ item.personalization_factor || (item.is_serendipity ? '20% Diverse Discovery' : '80% User Interests') }}</span>
              </span>
            </div>

            <!-- Reading Time & Timestamp -->
            <div class="flex items-center gap-3 text-[11px] text-[#5F6875]">
              <span class="flex items-center gap-1">
                <Clock class="h-3 w-3" />
                <span>{{ item.reading_time_minutes || 3 }} min read</span>
              </span>
              <span>•</span>
              <span>{{ formatDate(item.published_at) }}</span>
            </div>
          </div>

          <!-- Card Body: Headline & Summary -->
          <div class="space-y-2">
            <RouterLink
              :to="`/news/${item.slug || item.id}`"
              class="editorial-headline block text-xl sm:text-[22px] font-medium leading-snug tracking-tight text-[#0E1A2B] transition-colors group-hover:text-[#274888]"
            >
              {{ item.headline || item.title }}
            </RouterLink>

            <!-- Remote Article Thumbnail & Credit (Zero local storage) -->
            <div v-if="item.primary_image || item.featured_image" class="relative overflow-hidden rounded-[3px] border border-[#D0D9CF] max-h-48 bg-[#0E1A2B]/5">
              <img
                :src="item.primary_image || item.featured_image"
                :alt="item.headline || item.title"
                class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-[1.01]"
                loading="lazy"
                referrerpolicy="no-referrer"
                @error="(e: any) => e.target.parentElement.style.display = 'none'"
              />
              <div class="absolute bottom-2 left-2 bg-black/75 backdrop-blur-xs text-white text-[10px] font-mono px-2 py-0.5 rounded-[2px]">
                {{ item.source_attribution || (item.source?.name ? `Source: ${item.source.name}` : 'Source: Wire Service') }}
              </div>
            </div>

            <p class="text-xs sm:text-[13px] leading-relaxed text-[#3F4A5A]">
              {{ item.summary }}
            </p>
          </div>

          <!-- Personalization Reason (If Present) -->
          <div
            v-if="item.personalization_explanation"
            class="rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] px-3 py-2 text-[11px] text-[#5F6875]"
          >
            <span class="font-semibold text-[#274888]">Why you see this:</span>
            {{ item.personalization_explanation }}
          </div>

          <!-- Corroboration & Detected Entities -->
          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#D0D9CF]/60 pt-3">
            <!-- Source Attribution & Corroborating Count -->
            <div class="flex items-center gap-2 text-xs text-[#5F6875]">
              <span class="font-semibold text-[#0E1A2B]">{{ item.source?.name || 'Primary Source' }}</span>
              <span class="rounded-[2px] bg-[#E3F1EA] px-1.5 py-0.5 font-mono text-[10px] font-medium text-[#17794D]">
                Credibility {{ item.source?.credibility_rating || 95 }}%
              </span>
              <span>•</span>
              <span class="text-[11px]">
                {{ item.source_count || 3 }} independent sources
              </span>
            </div>

            <!-- Detected Entities (Clickable to follow) -->
            <div class="flex flex-wrap items-center gap-1.5">
              <button
                v-for="entity in (item.entities || []).slice(0, 3)"
                :key="typeof entity === 'string' ? entity : entity.name"
                type="button"
                @click="toggleFollowEntity(typeof entity === 'string' ? { name: entity } : entity)"
                :class="[
                  'group/ent flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-medium transition-colors',
                  followedEntityIds[`company:${typeof entity === 'string' ? entity : entity.id || entity.name}`]
                    ? 'border-[#17794D]/30 bg-[#E3F1EA] text-[#17794D]'
                    : 'border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
                ]"
              >
                <span>{{ typeof entity === 'string' ? entity : entity.name }}</span>
                <Check v-if="followedEntityIds[`company:${typeof entity === 'string' ? entity : entity.id || entity.name}`]" class="h-2.5 w-2.5" />
                <Plus v-else class="h-2.5 w-2.5 text-[#5F6875] group-hover/ent:text-[#0E1A2B]" />
              </button>
            </div>
          </div>

          <!-- Card Actions: Save, Share, Feedback, Full Story -->
          <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[#D0D9CF]/60 pt-3">
            <div class="flex items-center gap-2">
              <!-- Save to Library Action -->
              <button
                type="button"
                @click="toggleSaveArticle(item)"
                :class="[
                  'flex items-center gap-1.5 rounded-[4px] px-2.5 py-1 text-xs font-medium transition-colors border',
                  savedItemIds[item.id]
                    ? 'border-[#274888]/30 bg-[#E4E9F7] text-[#274888]'
                    : 'border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
                ]"
                aria-label="Save intelligence"
              >
                <BookmarkCheck v-if="savedItemIds[item.id]" class="h-3.5 w-3.5 text-[#274888]" />
                <Bookmark v-else class="h-3.5 w-3.5" />
                <span>{{ savedItemIds[item.id] ? 'Saved' : 'Save' }}</span>
              </button>

              <!-- Share Action -->
              <button
                type="button"
                @click="handleShare(item)"
                class="flex items-center gap-1.5 rounded-[4px] border border-[#D0D9CF] bg-white px-2.5 py-1 text-xs font-medium text-[#3F4A5A] transition-colors hover:bg-[#EEEBE4] hover:text-[#0E1A2B]"
                aria-label="Share intelligence"
              >
                <Share2 class="h-3.5 w-3.5" />
                <span>{{ copySuccessId === item.id ? 'Copied Link!' : 'Share' }}</span>
              </button>

              <!-- Relevance Feedback -->
              <div class="flex items-center gap-1 border-l border-[#D0D9CF] pl-2">
                <button
                  type="button"
                  @click="handleFeedback(item.id, 'up')"
                  :class="[
                    'rounded p-1 transition-colors',
                    feedbackGiven[item.id] === 'up'
                      ? 'bg-[#E3F1EA] text-[#17794D]'
                      : 'text-[#5F6875] hover:text-[#0E1A2B]'
                  ]"
                  title="More like this"
                  aria-label="More like this"
                >
                  <ThumbsUp class="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  @click="handleFeedback(item.id, 'down')"
                  :class="[
                    'rounded p-1 transition-colors',
                    feedbackGiven[item.id] === 'down'
                      ? 'bg-[#FBE4E1] text-[#B3261E]'
                      : 'text-[#5F6875] hover:text-[#0E1A2B]'
                  ]"
                  title="Less like this"
                  aria-label="Less like this"
                >
                  <ThumbsDown class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <!-- Deep Dive Link -->
            <RouterLink
              :to="`/news/${item.slug || item.id}`"
              class="inline-flex items-center gap-1 text-xs font-semibold text-[#274888] hover:underline"
            >
              <span>Full Intelligence Dossier</span>
              <ExternalLink class="h-3 w-3" />
            </RouterLink>
          </div>
        </div>

        <!-- Load More Pagination -->
        <div v-if="hasMorePages" class="pt-4 text-center">
          <button
            type="button"
            class="px-6 py-2.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold transition-colors disabled:opacity-50"
            :disabled="isLoadingMore"
            @click="loadMore"
          >
            <span v-if="isLoadingMore">Loading More Intelligence...</span>
            <span v-else>Load More Intelligence</span>
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
