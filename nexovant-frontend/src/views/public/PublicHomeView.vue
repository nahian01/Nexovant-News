<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Activity, ArrowRight, CheckCheck, Globe2, Radio } from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import StoryClusterCard from '@/components/common/StoryClusterCard.vue'
import NewsletterSubscribe from '@/components/common/NewsletterSubscribe.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Button from '@/components/ui/Button.vue'
import { feedService } from '@/services/api/feed.service'

const stories = ref<any[]>([])
const breakingStories = ref<any[]>([])
const isLoading = ref(true)
const activeFilter = ref('')
const selectedCategory = ref('')
const confirmedCount = computed(() => stories.value.filter((story) => story.verification_status === 'confirmed').length)
const visibleStories = computed(() => {
  if (activeFilter.value === 'breaking') return stories.value.filter((story) => story.is_breaking)
  if (activeFilter.value === 'expansion-capex') {
    return stories.value.filter((story) => story.categories?.some((category: any) => category.slug === 'expansion-capex' || /expansion|capex/i.test(category.name)))
  }
  return stories.value
})

const quickFilters = [
  { label: 'All developments', key: '' },
  { label: 'Confirmed', key: 'confirmed' },
  { label: 'Breaking', key: 'breaking' },
  { label: 'Expansion & capex', key: 'expansion-capex' },
]

const fetchStories = async () => {
  isLoading.value = true
  try {
    const params: any = {}
    if (activeFilter.value === 'expansion-capex') params.category = activeFilter.value
    else if (selectedCategory.value) params.category = selectedCategory.value
    if (activeFilter.value === 'confirmed') params.verification_status = 'confirmed'

    const res = await feedService.getArticles(params)
    stories.value = res.data
    breakingStories.value = res.data.filter((story: any) => story.is_breaking)
  } catch {
    // The empty state is shown when the feed is unavailable.
  } finally {
    isLoading.value = false
  }
}

const handleCategorySelect = (slug: string) => {
  selectedCategory.value = slug
  fetchStories()
}

const handleStoryHidden = (id: string) => {
  stories.value = stories.value.filter((story) => story.id !== id)
}

onMounted(fetchStories)
</script>

<template>
  <ThreeColumnLayout @select-category="handleCategorySelect">
    <div class="mx-auto max-w-4xl space-y-5">
      <section class="rounded-[24px] border border-nexo-border bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
          <div>
            <div class="eyebrow flex items-center gap-2"><Globe2 class="h-3.5 w-3.5" /> Nexovant intelligence network</div>
            <h1 class="editorial-title mt-2 text-3xl font-medium tracking-tight sm:text-[38px]">The global business feed.</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-nexo-text-secondary">Important developments, connected to the sources, stakeholders and decisions behind them.</p>
          </div>
          <div class="flex items-center gap-2 self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-800 sm:self-auto"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-40"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"></span></span> Updating now</div>
        </div>
        <div class="mt-6 grid grid-cols-3 border-t border-nexo-border pt-4">
          <div class="pr-3"><div class="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-nexo-text-muted"><Activity class="h-3.5 w-3.5" /> Developments</div><p class="mt-1.5 font-serif text-2xl font-medium text-nexo-text-primary">{{ stories.length }}</p></div>
          <div class="border-x border-nexo-border px-4"><div class="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-nexo-text-muted"><CheckCheck class="h-3.5 w-3.5" /> Confirmed</div><p class="mt-1.5 font-serif text-2xl font-medium text-nexo-text-primary">{{ confirmedCount }}</p></div>
          <div class="pl-4"><div class="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-nexo-text-muted"><Radio class="h-3.5 w-3.5" /> Breaking</div><p class="mt-1.5 font-serif text-2xl font-medium text-nexo-text-primary">{{ breakingStories.length }}</p></div>
        </div>
      </section>

      <section v-if="breakingStories.length" class="space-y-2">
        <div v-for="story in breakingStories.slice(0, 1)" :key="story.id" class="flex flex-col items-start justify-between gap-3 rounded-2xl border border-rose-200 bg-gradient-to-r from-rose-50 via-white to-white p-4 shadow-sm sm:flex-row sm:items-center">
          <div class="space-y-1.5">
            <div class="flex items-center gap-2"><span class="h-2 w-2 animate-pulse rounded-full bg-rose-600"></span><span class="text-[10px] font-semibold uppercase tracking-[0.13em] text-rose-800">Breaking development</span><span class="text-[10px] text-nexo-text-muted">· Just in</span></div>
            <div class="font-serif text-sm font-semibold text-nexo-text-primary">{{ story.title }}</div>
          </div>
          <RouterLink :to="`/news/${story.slug}`">
            <Button variant="secondary" size="sm" class="whitespace-nowrap inline-flex items-center gap-1.5 font-medium">
              <span>Read the briefing</span>
              <ArrowRight class="h-3.5 w-3.5" />
            </Button>
          </RouterLink>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-nexo-border/80 pb-3">
        <div class="flex max-w-full items-center gap-2 overflow-x-auto text-xs">
          <button v-for="filter in quickFilters" :key="filter.key" type="button" @click="activeFilter = filter.key; fetchStories()" :class="['whitespace-nowrap rounded-full px-3.5 py-2 font-medium transition', activeFilter === filter.key ? 'bg-nexo-accent-cyan font-semibold text-white shadow-cyan-glow' : 'border border-nexo-border bg-white text-nexo-text-secondary hover:text-nexo-text-primary']">{{ filter.label }}</button>
        </div>
        <span class="hidden items-center gap-1.5 text-[11px] font-medium text-nexo-text-muted sm:inline-flex"><Radio class="h-3.5 w-3.5 text-nexo-accent-cyan" /> Live global coverage</span>
      </div>

      <section class="space-y-4">
        <div v-if="isLoading" class="space-y-4">
          <div v-for="i in 3" :key="i" class="space-y-3 rounded-2xl border border-nexo-border bg-white p-6 shadow-sm"><Skeleton class="h-4 w-1/4" /><Skeleton class="h-7 w-3/4" /><Skeleton class="h-16 w-full" /></div>
        </div>
        <div v-else-if="visibleStories.length === 0" class="rounded-2xl border border-nexo-border bg-white px-6 py-16 text-center shadow-sm">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-nexo-bg text-nexo-accent-cyan"><Globe2 class="h-5 w-5" /></div>
          <h2 class="mt-4 font-serif text-xl font-medium text-nexo-text-primary">No stories in this view</h2>
          <p class="mt-2 text-sm text-nexo-text-secondary">Try another filter or choose a different category.</p>
        </div>
        <div v-else class="space-y-4">
          <StoryClusterCard v-for="story in visibleStories" :key="story.id" :story="story" @hidden="handleStoryHidden" />
        </div>
      </section>

      <NewsletterSubscribe source="home_mid_feed" title="A more considered start to your morning." description="Get a concise briefing of the global business developments that shape your day, with useful context attached." />
    </div>
  </ThreeColumnLayout>
</template>
