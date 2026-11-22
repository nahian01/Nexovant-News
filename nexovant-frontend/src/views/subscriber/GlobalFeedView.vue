<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Globe2,
  Search,
  ShieldCheck,
  Filter,
  X,
  Layers,
  Sparkles,
  Tag,
  Building,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { apiClient } from '@/services/api/client'
import {
  categoryService,
  type CategoryItem,
  type IndustryItem,
  type SubcategoryItem,
} from '@/services/api/categories.service'

const articles = ref<any[]>([])
const categories = ref<CategoryItem[]>([])
const industries = ref<IndustryItem[]>([])
const availableStatuses = ref<Record<string, number>>({})

const isLoading = ref(true)
const isLoadingMore = ref(false)
const searchQuery = ref('')
const selectedIndustry = ref('')
const selectedCategory = ref('')
const selectedSubcategory = ref('')
const selectedStatus = ref('')

const currentPage = ref(1)
const lastPage = ref(1)
const totalArticles = ref(0)

// Compute categories filtered by selected industry, showing only non-zero counts
const filteredCategories = computed(() => {
  if (!selectedIndustry.value) {
    return categories.value.filter((c) => (c.article_count || 0) > 0)
  }
  return categories.value.filter(
    (c) =>
      c.industry?.toLowerCase() === selectedIndustry.value.toLowerCase() &&
      (c.article_count || 0) > 0
  )
})

// Current selected category object
const currentCategoryObj = computed(() => {
  if (!selectedCategory.value) return null
  return categories.value.find((c) => c.slug === selectedCategory.value) || null
})

// Subcategories with non-zero counts under current category
const availableSubcategories = computed(() => {
  if (!currentCategoryObj.value) return []
  const subs = currentCategoryObj.value.subcategories as SubcategoryItem[]
  if (!Array.isArray(subs)) return []
  return subs.filter((s) => (s.article_count || 0) > 0)
})

// Active verification statuses with non-zero counts
const activeStatusesList = computed(() => {
  const list: Array<{ value: string; label: string; count: number }> = []
  for (const [key, count] of Object.entries(availableStatuses.value)) {
    if (typeof count === 'number' && count > 0) {
      const label = key
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ')
      list.push({ value: key, label, count })
    }
  }
  return list
})

// Total count across all industries
const totalIndustryArticles = computed(() => {
  return industries.value.reduce((acc, ind) => acc + (ind.article_count || 0), 0)
})

const hasActiveFilters = computed(() => {
  return (
    !!searchQuery.value.trim() ||
    !!selectedIndustry.value ||
    !!selectedCategory.value ||
    !!selectedSubcategory.value ||
    !!selectedStatus.value
  )
})

const fetchArticles = async (reset = true) => {
  if (reset) {
    currentPage.value = 1
    isLoading.value = true
  } else {
    isLoadingMore.value = true
  }

  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      per_page: 15,
    }

    if (searchQuery.value.trim()) {
      params.q = searchQuery.value.trim()
    }
    if (selectedIndustry.value) {
      params.industry = selectedIndustry.value
    }
    // Subcategory takes precedence if active, otherwise category
    if (selectedSubcategory.value) {
      params.category = selectedSubcategory.value
    } else if (selectedCategory.value) {
      params.category = selectedCategory.value
    }
    if (selectedStatus.value) {
      params.verification_status = selectedStatus.value
    }

    const res = await apiClient.get('/articles', { params })
    const items = res.data?.data || []
    const meta = res.data?.meta || {}

    if (reset) {
      articles.value = items
    } else {
      articles.value = [...articles.value, ...items]
    }

    totalArticles.value = meta.total || items.length
    currentPage.value = meta.current_page || 1
    lastPage.value = meta.last_page || 1

    if (meta.available_statuses) {
      availableStatuses.value = meta.available_statuses
      if (selectedStatus.value && !meta.available_statuses[selectedStatus.value]) {
        selectedStatus.value = ''
      }
    }
  } catch {
    if (reset) articles.value = []
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

let searchTimeout: any = null
const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchArticles(true)
  }, 300)
}

const selectIndustry = (indName: string) => {
  if (selectedIndustry.value === indName) {
    selectedIndustry.value = ''
  } else {
    selectedIndustry.value = indName
  }
  selectedCategory.value = ''
  selectedSubcategory.value = ''
  fetchArticles(true)
}

const selectCategory = (catSlug: string) => {
  if (selectedCategory.value === catSlug) {
    selectedCategory.value = ''
  } else {
    selectedCategory.value = catSlug
  }
  selectedSubcategory.value = ''
  fetchArticles(true)
}

const selectSubcategory = (subSlug: string) => {
  if (selectedSubcategory.value === subSlug) {
    selectedSubcategory.value = ''
  } else {
    selectedSubcategory.value = subSlug
  }
  fetchArticles(true)
}

const selectStatus = (statusVal: string) => {
  if (selectedStatus.value === statusVal) {
    selectedStatus.value = ''
  } else {
    selectedStatus.value = statusVal
  }
  fetchArticles(true)
}

const clearAllFilters = () => {
  searchQuery.value = ''
  selectedIndustry.value = ''
  selectedCategory.value = ''
  selectedSubcategory.value = ''
  selectedStatus.value = ''
  fetchArticles(true)
}

const loadMore = () => {
  if (currentPage.value < lastPage.value && !isLoadingMore.value) {
    currentPage.value++
    fetchArticles(false)
  }
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return 'Recent Wire'
  try {
    const d = new Date(dateStr)
    return new Intl.DateTimeFormat('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'UTC',
    }).format(d) + ' UTC'
  } catch {
    return dateStr
  }
}

onMounted(async () => {
  try {
    const payload = await categoryService.getCategoriesWithIndustries({ has_articles: true })
    categories.value = (payload.categories || []).filter((c) => (c.article_count || 0) > 0)
    industries.value = (payload.industries || []).filter((i) => (i.article_count || 0) > 0)
  } catch (err) {
    console.error('Failed to load categories/industries', err)
  }

  await fetchArticles(true)
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- 1. Title & Search Header -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
            <Globe2 class="h-3.5 w-3.5" />
            <span>GLOBAL INTELLIGENCE NETWORK</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Global Intelligence Feed
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875] max-w-2xl">
            Real-time developments dynamically classified by verified provenance, corporate events, and industry sectors.
          </p>
        </div>

        <div class="relative w-full md:w-80">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#5F6875]" />
          <input
            v-model="searchQuery"
            placeholder="Search entities, filings, technologies..."
            @input="onSearchInput"
            class="w-full pl-9 pr-3 py-2 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888] shadow-xs"
          />
        </div>
      </div>

      <!-- 2. Dynamic Hierarchical Filter Controls Card -->
      <div class="card-editorial p-4 sm:p-5 space-y-4 bg-white border border-[#D0D9CF]">
        <!-- 2.1 Industry Filter Bar -->
        <div class="space-y-2">
          <div class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wider text-[#5F6875]">
            <Building class="w-3.5 h-3.5 text-[#274888]" />
            <span>Industry Sectors</span>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button
              type="button"
              @click="selectIndustry('')"
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer',
                selectedIndustry === ''
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-[#F8FAF7] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              All Industries <span class="opacity-80">({{ totalIndustryArticles }})</span>
            </button>

            <!-- Only display industries that actually have published articles -->
            <button
              v-for="ind in industries"
              :key="ind.slug"
              type="button"
              @click="selectIndustry(ind.name)"
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer flex items-center gap-1.5',
                selectedIndustry === ind.name
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              <span>{{ ind.name }}</span>
              <span
                :class="[
                  'px-1.5 py-0.2 rounded-full text-[10px] font-mono',
                  selectedIndustry === ind.name ? 'bg-white/20 text-white' : 'bg-[#F0EFEA] text-[#5F6875]'
                ]"
              >
                {{ ind.article_count }}
              </span>
            </button>
          </div>
        </div>

        <!-- 2.2 Category / Event Filter Bar -->
        <div v-if="filteredCategories.length > 0" class="space-y-2 border-t border-[#D0D9CF]/60 pt-3">
          <div class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wider text-[#5F6875]">
            <Layers class="w-3.5 h-3.5 text-[#147A67]" />
            <span>Categories & Strategic Events</span>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button
              type="button"
              @click="selectCategory('')"
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer',
                selectedCategory === ''
                  ? 'bg-[#147A67] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-[#F8FAF7] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              All Categories
            </button>

            <!-- Only display categories that actually have published articles -->
            <button
              v-for="cat in filteredCategories"
              :key="cat.slug"
              type="button"
              @click="selectCategory(cat.slug)"
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer flex items-center gap-1.5',
                selectedCategory === cat.slug
                  ? 'bg-[#147A67] text-white shadow-xs'
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
        </div>

        <!-- 2.3 Subcategory Filter Bar (Visible when category selected and subcategories have articles) -->
        <div v-if="availableSubcategories.length > 0" class="space-y-2 border-t border-[#D0D9CF]/60 pt-3">
          <div class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wider text-[#5F6875]">
            <Tag class="w-3.5 h-3.5 text-[#274888]" />
            <span>Sub-topics in {{ currentCategoryObj?.name }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button
              type="button"
              @click="selectSubcategory('')"
              :class="[
                'px-2.5 py-0.5 rounded-[4px] text-xs font-medium transition-colors cursor-pointer',
                selectedSubcategory === ''
                  ? 'bg-[#274888] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-[#F8FAF7] text-[#3F4A5A] hover:bg-[#EEEBE4]'
              ]"
            >
              All {{ currentCategoryObj?.name }}
            </button>

            <button
              v-for="sub in availableSubcategories"
              :key="sub.slug"
              type="button"
              @click="selectSubcategory(sub.slug)"
              :class="[
                'px-2.5 py-0.5 rounded-[4px] text-xs font-medium transition-colors cursor-pointer flex items-center gap-1.5',
                selectedSubcategory === sub.slug
                  ? 'bg-[#274888] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4]'
              ]"
            >
              <span>{{ sub.name }}</span>
              <span class="text-[10px] font-mono opacity-75">({{ sub.article_count }})</span>
            </button>
          </div>
        </div>

        <!-- 2.4 Provenance Audit Filter Bar (Only show statuses with non-zero articles) -->
        <div v-if="activeStatusesList.length > 0" class="space-y-2 border-t border-[#D0D9CF]/60 pt-3">
          <div class="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wider text-[#5F6875]">
            <ShieldCheck class="w-3.5 h-3.5 text-[#17794D]" />
            <span>Verified Provenance Audit</span>
          </div>
          <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <button
              type="button"
              @click="selectStatus('')"
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer',
                selectedStatus === ''
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-[#F8FAF7] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              All Provenance
            </button>

            <button
              v-for="st in activeStatusesList"
              :key="st.value"
              type="button"
              @click="selectStatus(st.value)"
              :class="[
                'px-3 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer flex items-center gap-1.5',
                selectedStatus === st.value
                  ? st.value === 'confirmed' ? 'bg-[#17794D] text-white shadow-xs' : 'bg-[#274888] text-white shadow-xs'
                  : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              <span>{{ st.label }}</span>
              <span
                :class="[
                  'px-1.5 py-0.2 rounded-full text-[10px] font-mono',
                  selectedStatus === st.value ? 'bg-white/20 text-white' : 'bg-[#F0EFEA] text-[#5F6875]'
                ]"
              >
                {{ st.count }}
              </span>
            </button>
          </div>
        </div>

        <!-- 2.5 Active Filter Reset Strip -->
        <div v-if="hasActiveFilters" class="flex flex-wrap items-center justify-between gap-2 border-t border-[#D0D9CF]/60 pt-3 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-[11px] font-mono text-[#5F6875]">Active Filters:</span>
            <span v-if="selectedIndustry" class="inline-flex items-center gap-1 bg-[#0E1A2B] text-white px-2 py-0.5 rounded-[2px] text-[11px]">
              Industry: {{ selectedIndustry }}
              <button @click="selectIndustry('')" class="hover:text-red-300 cursor-pointer"><X class="w-3 h-3" /></button>
            </span>
            <span v-if="selectedCategory" class="inline-flex items-center gap-1 bg-[#147A67] text-white px-2 py-0.5 rounded-[2px] text-[11px]">
              Category: {{ currentCategoryObj?.name || selectedCategory }}
              <button @click="selectCategory('')" class="hover:text-red-300 cursor-pointer"><X class="w-3 h-3" /></button>
            </span>
            <span v-if="selectedSubcategory" class="inline-flex items-center gap-1 bg-[#274888] text-white px-2 py-0.5 rounded-[2px] text-[11px]">
              Sub-topic: {{ selectedSubcategory }}
              <button @click="selectSubcategory('')" class="hover:text-red-300 cursor-pointer"><X class="w-3 h-3" /></button>
            </span>
            <span v-if="selectedStatus" class="inline-flex items-center gap-1 bg-[#8A5A08] text-white px-2 py-0.5 rounded-[2px] text-[11px]">
              Status: {{ selectedStatus.replace('_', ' ') }}
              <button @click="selectStatus('')" class="hover:text-red-300 cursor-pointer"><X class="w-3 h-3" /></button>
            </span>
            <span v-if="searchQuery" class="inline-flex items-center gap-1 bg-gray-700 text-white px-2 py-0.5 rounded-[2px] text-[11px]">
              Keyword: "{{ searchQuery }}"
              <button @click="searchQuery = ''; fetchArticles(true)" class="hover:text-red-300 cursor-pointer"><X class="w-3 h-3" /></button>
            </span>
          </div>

          <button
            type="button"
            @click="clearAllFilters"
            class="text-[#274888] hover:text-[#0E1A2B] hover:underline font-semibold cursor-pointer"
          >
            Clear all filters
          </button>
        </div>
      </div>

      <!-- 3. Results Header & Count -->
      <div class="flex items-center justify-between text-xs text-[#5F6875] border-b border-[#D0D9CF]/60 pb-2">
        <span class="font-mono uppercase tracking-wider text-[11px]">
          Showing {{ articles.length }} of {{ totalArticles }} verified developments
        </span>
        <span v-if="selectedIndustry" class="font-semibold text-[#0E1A2B]">
          Scope: {{ selectedIndustry }}
        </span>
      </div>

      <!-- 4. Loading State -->
      <div v-if="isLoading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="card-editorial p-6 space-y-3">
          <Skeleton class="h-4 w-32" />
          <Skeleton class="h-6 w-3/4" />
          <Skeleton class="h-12 w-full" />
        </div>
      </div>

      <!-- 5. Empty State -->
      <div v-else-if="articles.length === 0" class="card-editorial p-12 text-center space-y-3">
        <Globe2 class="w-8 h-8 text-[#5F6875] mx-auto opacity-50" />
        <h3 class="editorial-headline text-lg font-medium text-[#0E1A2B]">No Developments Found</h3>
        <p class="text-[#5F6875] text-xs max-w-md mx-auto">
          No intelligence developments match your active filter criteria. Try selecting another industry sector or clearing filters.
        </p>
        <button
          type="button"
          @click="clearAllFilters"
          class="mt-2 px-4 py-2 bg-[#0E1A2B] text-white text-xs font-semibold rounded-[4px] hover:bg-[#274888] transition-colors"
        >
          Reset All Filters
        </button>
      </div>

      <!-- 6. Feed List -->
      <div v-else class="space-y-4">
        <article
          v-for="art in articles"
          :key="art.id"
          class="card-editorial p-5 sm:p-6 space-y-3 hover:border-[#274888]/40 transition-colors group"
        >
          <!-- Metadata Badges -->
          <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
            <div class="flex items-center flex-wrap gap-1.5">
              <!-- Verification status badge -->
              <span
                class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase tracking-wider"
                :class="art.verification_status === 'confirmed' || art.verification_status === 'verified' ? 'badge-verified' : 'badge-developing'"
              >
                {{ (art.verification_status || 'verified').replace('_', ' ') }}
              </span>

              <!-- Categories -->
              <span
                v-for="cat in art.categories"
                :key="cat.id"
                class="font-mono text-[10px] uppercase text-[#3F4A5A] bg-[#F8FAF7] border border-[#D0D9CF] px-2 py-0.5 rounded-[2px]"
              >
                {{ cat.name }}
              </span>

              <!-- Subcategories -->
              <span
                v-for="sub in art.subcategories"
                :key="sub.id"
                class="font-mono text-[10px] uppercase text-[#147A67] bg-[#EBF5EE] border border-[#C5E3CE] px-2 py-0.5 rounded-[2px]"
              >
                {{ sub.name }}
              </span>
            </div>

            <div class="flex items-center gap-3 text-xs">
              <span class="font-mono text-[11px] text-[#274888] font-semibold">
                Importance: {{ art.importance_score || '7.5' }} / 10
              </span>
              <span class="font-mono text-[10px] text-[#5F6875] flex items-center gap-1">
                <Clock class="w-3 h-3 text-[#5F6875]" />
                {{ formatDate(art.published_at) }}
              </span>
            </div>
          </div>

          <!-- Headline -->
          <RouterLink :to="`/news/${art.slug}`">
            <h3 class="editorial-headline text-xl sm:text-2xl font-medium text-[#0E1A2B] leading-snug group-hover:text-[#274888] transition-colors">
              {{ art.title }}
            </h3>
          </RouterLink>

          <!-- Remote Thumbnail & Credit (Zero local storage per rules) -->
          <div v-if="art.featured_image || art.primary_image" class="relative overflow-hidden rounded-[3px] border border-[#D0D9CF] max-h-56 bg-[#0E1A2B]/5">
            <img
              :src="art.featured_image || art.primary_image"
              :alt="art.title"
              class="w-full h-56 object-cover transition-transform duration-300 group-hover:scale-[1.01]"
              loading="lazy"
              referrerpolicy="no-referrer"
              @error="(e: any) => e.target.parentElement.style.display = 'none'"
            />
            <div class="absolute bottom-2 left-2 bg-black/80 backdrop-blur-xs text-white text-[10px] font-mono px-2 py-0.5 rounded-[2px]">
              {{ art.featured_image_caption || (art.source?.name ? `Source: ${art.source.name}` : 'Source: Wire Service') }}
            </div>
          </div>

          <!-- Executive Summary -->
          <p v-if="art.summary || art.content_normalized" class="text-xs sm:text-[13px] text-[#3F4A5A] leading-relaxed">
            {{ typeof art.summary === 'object' ? (art.summary?.executive_summary || art.content_normalized) : (art.summary || art.content_normalized) }}
          </p>

          <!-- Source & Dossier Footer -->
          <div class="flex items-center justify-between text-xs text-[#5F6875] pt-2 border-t border-[#D0D9CF]/60">
            <div class="flex items-center space-x-2">
              <span class="font-mono uppercase text-[10px] text-[#5F6875]">Source:</span>
              <span class="font-semibold text-[#0E1A2B]">{{ art.source?.name || 'Primary Wire' }}</span>
              <span>•</span>
              <span class="text-[#17794D] font-medium flex items-center gap-1">
                <ShieldCheck class="w-3.5 h-3.5" />
                Audited Provenance
              </span>
            </div>

            <RouterLink :to="`/news/${art.slug}`" class="text-[#274888] font-semibold hover:underline flex items-center gap-1">
              Read intelligence dossier &rarr;
            </RouterLink>
          </div>
        </article>

        <!-- 7. Load More Pagination Button -->
        <div v-if="currentPage < lastPage" class="text-center pt-4">
          <button
            type="button"
            @click="loadMore"
            :disabled="isLoadingMore"
            class="px-5 py-2.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold transition-colors shadow-xs cursor-pointer disabled:opacity-50"
          >
            <span v-if="isLoadingMore">Loading additional developments...</span>
            <span v-else>Load More Developments ({{ totalArticles - articles.length }} remaining)</span>
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
