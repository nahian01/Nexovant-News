<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { Search } from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import Input from '@/components/ui/Input.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { searchService } from '@/services/api/search.service'

const route = useRoute()
const searchQuery = ref((route.query.q as string) || '')
const activeTab = ref('all')
const isLoading = ref(false)
const results = ref<any>({
  articles: [],
  companies: [],
  executives: [],
  trends: [],
})

const tabs = [
  { key: 'all', label: 'All Results' },
  { key: 'articles', label: 'Intelligence Articles' },
  { key: 'companies', label: 'Companies' },
  { key: 'executives', label: 'Executives' },
  { key: 'trends', label: 'Trends' },
]

const executeSearch = async () => {
  if (!searchQuery.value.trim()) return
  isLoading.value = true
  try {
    const res = await searchService.search(searchQuery.value)
    results.value = res || { articles: [], companies: [], executives: [], trends: [] }
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

watch(
  () => route.query.q,
  (newQ) => {
    if (newQ) {
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
    <div class="max-w-4xl mx-auto space-y-6 text-left">
      <!-- Search Header & Input -->
      <div class="space-y-4">
        <div>
          <span class="text-[10px] font-mono uppercase text-nexo-text-muted tracking-wider">
            GLOBAL INTELLIGENCE RETRIEVAL
          </span>
          <h1 class="editorial-title text-3xl font-medium text-nexo-text-primary">
            Search Results for "{{ searchQuery }}"
          </h1>
        </div>

        <div class="flex gap-3">
          <Input
            v-model="searchQuery"
            placeholder="Search companies, leadership, filings, tickers..."
            @keyup.enter="executeSearch"
          />
          <button
            @click="executeSearch"
            class="px-5 py-2 rounded bg-nexo-accent-cyan text-nexo-bg text-xs font-mono font-bold hover:bg-nexo-accent-cyan/90 transition-colors whitespace-nowrap"
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
        <!-- Articles Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'articles') && results.articles.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase text-nexo-text-muted tracking-wider font-bold">
            Articles ({{ results.articles.length }})
          </h2>
          <Card
            v-for="a in results.articles"
            :key="a.id"
            hoverable
            class="p-5 border border-nexo-border space-y-2"
          >
            <div class="flex items-center space-x-2 text-xs font-mono">
              <Badge :variant="a.verification_status" size="sm" />
              <span class="text-nexo-text-muted">{{ a.source?.name }}</span>
            </div>
            <h3 class="editorial-title text-xl font-semibold text-nexo-text-primary">
              <RouterLink :to="`/news/${a.slug}`" class="hover:text-nexo-accent-cyan transition-colors">
                {{ a.title }}
              </RouterLink>
            </h3>
            <p class="text-xs text-nexo-text-secondary leading-relaxed">
              {{ a.content_normalized || a.content_raw }}
            </p>
          </Card>
        </div>

        <!-- Companies Tab / Section -->
        <div v-if="(activeTab === 'all' || activeTab === 'companies') && results.companies.length > 0" class="space-y-3">
          <h2 class="text-xs font-mono uppercase text-nexo-text-muted tracking-wider font-bold">
            Companies ({{ results.companies.length }})
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Card
              v-for="c in results.companies"
              :key="c.id"
              class="p-4 bg-nexo-surface border border-nexo-border"
            >
              <div class="flex items-center justify-between mb-1">
                <span class="font-semibold text-nexo-text-primary text-sm">{{ c.name }}</span>
                <span class="text-xs font-mono text-nexo-accent-cyan">{{ c.ticker }}</span>
              </div>
              <p class="text-[11px] text-nexo-text-secondary line-clamp-2">{{ c.summary }}</p>
            </Card>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="results.articles.length === 0 && results.companies.length === 0 && results.executives.length === 0 && results.trends.length === 0"
          class="text-center py-16 bg-white border border-nexo-border rounded-2xl p-8 shadow-sm"
        >
          <Search class="h-10 w-10 text-nexo-text-muted mx-auto mb-3" />
          <h3 class="text-base font-semibold text-nexo-text-primary">No Results Found for "{{ searchQuery }}"</h3>
          <p class="text-xs text-nexo-text-secondary mt-1">Check spelling or search for a specific corporate entity, ticker, or executive name.</p>
        </div>
      </div>
    </div>
  </ThreeColumnLayout>
</template>
