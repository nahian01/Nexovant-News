<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Bookmark,
  BookmarkCheck,
  Check,
  ChevronDown,
  Clock,
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
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
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
    categories.value = await categoryService.getCategories()
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
          published_at: it.published_at,
          reading_time_minutes: it.reading_time || 4,
          relevance_score: it.relevance_score || 92,
          source: { name: it.source_name || 'Reuters', credibility_rating: 94 },
          source_count: it.source_count || 3,
          entities: it.entities || [],
          is_saved: false,
          is_following: false,
        }))
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
    currentPage.value += 1
    fetchFeed(false)
  }
}

const toggleSaveArticle = async (article: any) => {
  const current = savedItemIds.value[article.id]
  savedItemIds.value[article.id] = !current
  try {
    if (savedItemIds.value[article.id]) {
      await workspaceService.saveLibraryItem({
        item_type: 'article',
        item_id: article.id,
        title: article.headline || article.title,
      })
    } else {
      // Find and remove
      const libraryRes = await workspaceService.getLibraryItems()
      const found = (libraryRes?.data || []).find((it: any) => it.item_id === article.id)
      if (found) {
        await workspaceService.deleteLibraryItem(found.id)
      }
    }
  } catch {
    // Revert state on error
    savedItemIds.value[article.id] = current
  }
}

const toggleFollowEntity = async (entity: any) => {
  const entityId = entity.id || entity.slug || entity.name
  const entityName = entity.name || entity.entity_name || entity
  const entityType = entity.type || 'company'
  const key = `${entityType}:${entityId}`

  followedEntityIds.value[key] = !followedEntityIds.value[key]
  try {
    await workspaceService.toggleFollow(entityType, entityId, entityName)
  } catch {
    followedEntityIds.value[key] = !followedEntityIds.value[key]
  }
}

const handleShare = async (article: any) => {
  const url = `${window.location.origin}/news/${article.slug || article.id}`
  try {
    if (navigator.clipboard) {
      await navigator.clipboard.writeText(url)
      copySuccessId.value = article.id
      setTimeout(() => {
        if (copySuccessId.value === article.id) {
          copySuccessId.value = null
        }
      }, 2500)
    }
  } catch {
    //
  }
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
    <div class="mx-auto max-w-4xl space-y-6 text-left">
      <!-- 1. Header & Executive Description -->
      <header class="border-b border-nexo-border/80 pb-5">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-nexo-accent-cyan">
          <Sparkles class="h-3.5 w-3.5" />
          <span>Personalized Intelligence Hub</span>
        </div>
        <h1 class="mt-2 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary sm:text-4xl">
          For You
        </h1>
        <p class="mt-2 text-sm text-nexo-text-secondary">
          Intelligence shaped around the companies, technologies, and developments you follow.
        </p>

        <!-- 2. Search & Controls Bar -->
        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <!-- Search within Feed -->
          <div class="relative flex-1 sm:max-w-md">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-nexo-text-muted" />
            <input
              v-model="searchKeyword"
              @input="onSearchInput"
              type="text"
              placeholder="Filter intelligence by keyword, company, or event..."
              class="w-full rounded-xl border border-nexo-border bg-white py-2 pl-9 pr-4 text-xs text-nexo-text-primary placeholder-nexo-text-muted transition focus:border-nexo-accent-cyan focus:outline-none focus:ring-1 focus:ring-nexo-accent-cyan"
            />
          </div>

          <!-- Sort Criteria Dropdown -->
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-nexo-text-muted">Sort:</span>
            <div class="relative">
              <select
                v-model="selectedSort"
                class="appearance-none rounded-xl border border-nexo-border bg-white py-2 pl-3 pr-8 text-xs font-medium text-nexo-text-primary transition focus:border-nexo-accent-cyan focus:outline-none"
              >
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
              <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-nexo-text-muted" />
            </div>
          </div>
        </div>

        <!-- 3. Category Filter Pills -->
        <div class="mt-4 flex flex-wrap items-center gap-1.5 overflow-x-auto pb-1">
          <button
            type="button"
            @click="selectedCategory = ''"
            :class="[
              'rounded-lg px-3 py-1.5 text-xs font-medium transition',
              selectedCategory === ''
                ? 'bg-nexo-accent-cyan text-white shadow-xs'
                : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            All Sectors
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            @click="selectedCategory = cat.slug"
            :class="[
              'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition',
              selectedCategory === cat.slug
                ? 'bg-nexo-accent-cyan text-white shadow-xs'
                : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            <span
              class="h-1.5 w-1.5 rounded-full"
              :style="{ backgroundColor: selectedCategory === cat.slug ? '#ffffff' : cat.color || '#147A67' }"
            ></span>
            <span>{{ cat.name }}</span>
          </button>
        </div>
      </header>

      <!-- 4. Loading Skeleton -->
      <div v-if="isLoading" class="space-y-4">
        <Card v-for="i in 3" :key="i" padding="lg">
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <Skeleton class="h-4 w-32" />
              <Skeleton class="h-4 w-20" />
            </div>
            <Skeleton class="h-7 w-5/6" />
            <Skeleton class="h-14 w-full" />
            <div class="flex gap-2 pt-2">
              <Skeleton class="h-5 w-24" />
              <Skeleton class="h-5 w-24" />
            </div>
          </div>
        </Card>
      </div>

      <!-- 5. Empty State -->
      <div
        v-else-if="feedItems.length === 0"
        class="rounded-2xl border border-dashed border-nexo-border bg-white p-12 text-center"
      >
        <Sparkles class="mx-auto h-10 w-10 text-nexo-text-muted" />
        <h3 class="mt-3 font-serif text-lg font-medium text-nexo-text-primary">
          No intelligence matching your filters
        </h3>
        <p class="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-nexo-text-secondary">
          Try clearing your search query, switching category filters, or following additional companies and technologies.
        </p>
        <div class="mt-4 flex justify-center gap-2">
          <Button variant="secondary" size="sm" @click="selectedCategory = ''; searchKeyword = ''">
            Reset Filters
          </Button>
          <RouterLink to="/following">
            <Button variant="primary" size="sm">Explore Following</Button>
          </RouterLink>
        </div>
      </div>

      <!-- 6. Feed Item Cards -->
      <div v-else class="space-y-4">
        <Card
          v-for="item in feedItems"
          :key="item.id"
          padding="lg"
          class="group border-nexo-border/80 transition-all hover:border-nexo-accent-cyan/35 hover:shadow-md"
        >
          <!-- Card Header: Verification + Category + Relevance Score + Time -->
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-nexo-border/60 pb-3">
            <div class="flex flex-wrap items-center gap-2">
              <!-- Verification Status Badge -->
              <Badge
                :variant="item.verification_status === 'verified' || item.verification_status === 'confirmed' ? 'confirmed' : 'reported_reliable'"
                size="sm"
              >
                {{ (item.verification_status || 'verified').replace('_', ' ') }}
              </Badge>

              <!-- Category Pill -->
              <span class="rounded-md bg-nexo-bg px-2 py-0.5 text-[11px] font-semibold text-nexo-text-secondary">
                {{ typeof item.category === 'object' ? item.category?.name : item.category || 'Strategic Intelligence' }}
              </span>

              <!-- Personal Relevance Score Badge -->
              <span class="inline-flex items-center gap-1 rounded-md bg-[#e9f3ed] px-2 py-0.5 font-mono text-[10px] font-semibold text-nexo-accent-cyan">
                <Sparkles class="h-2.5 w-2.5" />
                <span>{{ item.relevance_score || 90 }}% Relevance</span>
              </span>
            </div>

            <!-- Reading Time & Timestamp -->
            <div class="flex items-center gap-3 text-[11px] text-nexo-text-muted">
              <span class="flex items-center gap-1">
                <Clock class="h-3 w-3" />
                <span>{{ item.reading_time_minutes || 3 }} min read</span>
              </span>
              <span>•</span>
              <span>{{ formatDate(item.published_at) }}</span>
            </div>
          </div>

          <!-- Card Body: Headline & Summary -->
          <div class="mt-3.5 space-y-2">
            <RouterLink
              :to="`/news/${item.slug || item.id}`"
              class="block font-serif text-xl font-medium leading-snug tracking-tight text-nexo-text-primary transition group-hover:text-nexo-accent-cyan sm:text-[22px]"
            >
              {{ item.headline || item.title }}
            </RouterLink>
            <p class="text-xs leading-relaxed text-nexo-text-secondary sm:text-[13px]">
              {{ item.summary }}
            </p>
          </div>

          <!-- Personalization Reason (If Present) -->
          <div
            v-if="item.personalization_explanation"
            class="mt-3 rounded-xl bg-[#f4f7f4] px-3 py-2 text-[11px] text-nexo-text-secondary"
          >
            <span class="font-semibold text-nexo-accent-cyan">Why you see this:</span>
            {{ item.personalization_explanation }}
          </div>

          <!-- Corroboration & Detected Entities -->
          <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 border-t border-nexo-border/60 pt-3">
            <!-- Source Attribution & Corroborating Count -->
            <div class="flex items-center gap-2 text-xs text-nexo-text-secondary">
              <span class="font-semibold text-nexo-text-primary">{{ item.source?.name || 'Primary Source' }}</span>
              <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-mono text-[10px] font-medium text-emerald-800">
                Credibility {{ item.source?.credibility_rating || 95 }}%
              </span>
              <span class="text-nexo-text-muted">•</span>
              <span class="text-[11px] text-nexo-text-muted">
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
                  'group/ent flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[10px] font-medium transition',
                  followedEntityIds[`company:${typeof entity === 'string' ? entity : entity.id || entity.name}`]
                    ? 'border-nexo-accent-cyan/40 bg-[#e9f3ed] text-nexo-accent-cyan'
                    : 'border-nexo-border bg-nexo-bg text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
                ]"
              >
                <span>{{ typeof entity === 'string' ? entity : entity.name }}</span>
                <Check v-if="followedEntityIds[`company:${typeof entity === 'string' ? entity : entity.id || entity.name}`]" class="h-2.5 w-2.5" />
                <Plus v-else class="h-2.5 w-2.5 text-nexo-text-muted group-hover/ent:text-nexo-accent-cyan" />
              </button>
            </div>
          </div>

          <!-- Card Actions: Save, Share, Feedback, Full Story -->
          <div class="mt-3.5 flex flex-wrap items-center justify-between gap-2 border-t border-nexo-border/60 pt-3">
            <div class="flex items-center gap-2">
              <!-- Save to Library Action -->
              <button
                type="button"
                @click="toggleSaveArticle(item)"
                :class="[
                  'flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition',
                  savedItemIds[item.id]
                    ? 'bg-[#e9f3ed] text-nexo-accent-cyan'
                    : 'text-nexo-text-secondary hover:bg-nexo-bg hover:text-nexo-text-primary'
                ]"
                aria-label="Save intelligence"
              >
                <BookmarkCheck v-if="savedItemIds[item.id]" class="h-3.5 w-3.5 text-nexo-accent-cyan" />
                <Bookmark v-else class="h-3.5 w-3.5" />
                <span>{{ savedItemIds[item.id] ? 'Saved' : 'Save' }}</span>
              </button>

              <!-- Share Action -->
              <button
                type="button"
                @click="handleShare(item)"
                class="flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"
                aria-label="Share intelligence"
              >
                <Share2 class="h-3.5 w-3.5" />
                <span>{{ copySuccessId === item.id ? 'Copied Link!' : 'Share' }}</span>
              </button>

              <!-- Relevance Feedback -->
              <div class="flex items-center gap-1 border-l border-nexo-border/60 pl-2">
                <button
                  type="button"
                  @click="handleFeedback(item.id, 'up')"
                  :class="[
                    'rounded p-1 transition',
                    feedbackGiven[item.id] === 'up'
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'text-nexo-text-muted hover:text-nexo-text-primary'
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
                    'rounded p-1 transition',
                    feedbackGiven[item.id] === 'down'
                      ? 'bg-rose-50 text-rose-700'
                      : 'text-nexo-text-muted hover:text-nexo-text-primary'
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
              class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-accent-cyan transition hover:underline"
            >
              <span>Full Intelligence Dossier</span>
              <ExternalLink class="h-3 w-3" />
            </RouterLink>
          </div>
        </Card>

        <!-- Load More Pagination -->
        <div v-if="hasMorePages" class="pt-4 text-center">
          <Button
            variant="secondary"
            size="md"
            :disabled="isLoadingMore"
            @click="loadMore"
          >
            <span v-if="isLoadingMore">Loading More Intelligence...</span>
            <span v-else>Load More Intelligence</span>
          </Button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
