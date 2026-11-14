<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowRight,
  Bookmark,
  BookOpen,
  Flag,
  Link2,
  MoreHorizontal,
  Newspaper,
  ThumbsDown,
  ThumbsUp,
  X,
} from 'lucide-vue-next'
import Badge from '@/components/ui/Badge.vue'
import MoreCoverageModal from '@/components/common/MoreCoverageModal.vue'
import { feedService } from '@/services/api/feed.service'

const props = defineProps<{ story: any }>()

const emit = defineEmits<{ (e: 'hidden', id: string): void }>()

const isActionsOpen = ref(false)
const isCoverageOpen = ref(false)
const isSaved = ref(false)
const toastMessage = ref('')

const showToast = (message: string) => {
  toastMessage.value = message
  setTimeout(() => (toastMessage.value = ''), 3000)
}

const toggleSave = async () => {
  try {
    const action = isSaved.value ? 'unsave' : 'save'
    const res = await feedService.recordInteraction(props.story.id, action)
    isSaved.value = res.is_saved
    showToast(res.message)
  } catch {
    showToast('Please sign in to save stories.')
  }
  isActionsOpen.value = false
}

const handleShare = async () => {
  const url = `${window.location.origin}/news/${props.story.slug}`
  try {
    await navigator.clipboard.writeText(url)
    showToast('Story URL copied to clipboard.')
  } catch {
    showToast(url)
  }
  isActionsOpen.value = false
}

const handleFeedback = async (action: string) => {
  try {
    const res = await feedService.recordInteraction(props.story.id, action)
    showToast(res.message)
    if (action === 'hide') emit('hidden', props.story.id)
  } catch {
    showToast('Feedback noted.')
  }
  isActionsOpen.value = false
}

const supportingSources = [
  {
    publisher: props.story.source?.name || 'SEC EDGAR Disclosures',
    original_headline: 'Form 8-K Regulatory Filing for Capital Allocation',
    published_at: '2h ago',
    url: props.story.canonical_url || 'https://sec.gov',
    coverage_type: 'Regulatory',
    adds_new_info: true,
    description: 'Primary authorized filing with audited balance sheet allocation and state aid disclosures.',
  },
  {
    publisher: 'Reuters Technology Wire',
    original_headline: 'Nvidia approves long-term silicon packaging plan across Germany and France',
    published_at: '1h ago',
    url: 'https://reuters.com',
    coverage_type: 'Wire',
    adds_new_info: false,
    description: 'Independent cross-confirmation citing diplomatic and supply chain procurement sources.',
  },
]
</script>

<template>
  <article class="group relative rounded-2xl border border-nexo-border bg-white p-5 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-nexo-accent-cyan/35 hover:shadow-lg sm:p-6">
    <div v-if="toastMessage" class="absolute right-4 top-4 z-30 max-w-[85%] rounded-xl border border-nexo-accent-cyan/20 bg-white px-3 py-2 text-xs font-medium text-nexo-accent-cyan shadow-lg">{{ toastMessage }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <Badge :variant="story.verification_status" size="sm" />
        <span v-if="story.importance_score >= 8" class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-mono font-bold text-amber-800 border border-amber-200">
          Priority {{ story.importance_score }}/10
        </span>
        <button type="button" @click="isCoverageOpen = true" class="inline-flex items-center gap-1.5 rounded-full border border-nexo-border bg-nexo-bg px-2.5 py-1 text-[10px] font-semibold text-nexo-text-secondary transition hover:border-nexo-accent-cyan/30 hover:text-nexo-accent-cyan" title="Inspect clustered publisher sources">
          <Newspaper class="h-3.5 w-3.5 text-nexo-accent-cyan" /> 2 sources
        </button>
        <span v-if="story.source?.name" class="hidden text-xs text-nexo-text-muted sm:inline">• {{ story.source.name }}</span>
      </div>
      <div class="flex items-center gap-2 text-[11px] text-nexo-text-muted font-mono">
        <span>{{ new Date(story.published_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
        <div class="relative">
          <button type="button" @click="isActionsOpen = !isActionsOpen" class="rounded-lg p-1.5 text-nexo-text-muted transition hover:bg-nexo-bg hover:text-nexo-text-primary" aria-label="Story actions"><MoreHorizontal class="h-4 w-4" /></button>
          <div v-if="isActionsOpen" class="absolute right-0 top-9 z-30 w-52 rounded-2xl border border-nexo-border bg-white p-1.5 text-xs shadow-xl">
            <button type="button" @click="toggleSave" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"><Bookmark class="h-3.5 w-3.5" />{{ isSaved ? 'Remove saved story' : 'Save story' }}</button>
            <button type="button" @click="handleShare" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"><Link2 class="h-3.5 w-3.5" />Copy story link</button>
            <button type="button" @click="isCoverageOpen = true; isActionsOpen = false" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-text-secondary transition hover:bg-nexo-bg hover:text-nexo-text-primary"><BookOpen class="h-3.5 w-3.5" />View source coverage</button>
            <div class="my-1 border-t border-nexo-border"></div>
            <button type="button" @click="handleFeedback('more_like_this')" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-text-secondary transition hover:bg-nexo-bg"><ThumbsUp class="h-3.5 w-3.5" />More like this</button>
            <button type="button" @click="handleFeedback('less_like_this')" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-text-secondary transition hover:bg-nexo-bg"><ThumbsDown class="h-3.5 w-3.5" />Less like this</button>
            <button type="button" @click="handleFeedback('hide')" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-status-danger transition hover:bg-rose-50"><X class="h-3.5 w-3.5" />Hide story</button>
            <button type="button" @click="handleFeedback('report_issue')" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-nexo-text-muted transition hover:bg-nexo-bg"><Flag class="h-3.5 w-3.5" />Report an issue</button>
          </div>
        </div>
      </div>
    </div>

    <h2 class="editorial-title mt-4 text-[22px] font-medium leading-snug sm:text-[25px]">
      <RouterLink :to="`/news/${story.slug}`" class="transition group-hover:text-nexo-accent-cyan">{{ story.title }}</RouterLink>
    </h2>
    <p class="mt-3 text-sm leading-6 text-nexo-text-secondary">{{ story.content_normalized || story.content_raw }}</p>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-nexo-border pt-4">
      <div class="flex flex-wrap gap-1.5">
        <RouterLink v-for="company in story.companies" :key="company.id" to="/companies" class="rounded-lg border border-nexo-border bg-nexo-bg px-2.5 py-1 text-[10px] font-medium text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:text-nexo-accent-cyan">{{ company.name }} <span class="font-semibold">({{ company.ticker }})</span></RouterLink>
        <span v-for="category in story.categories" :key="category.id" class="rounded-lg bg-[#eaf3ed] px-2.5 py-1 text-[10px] font-semibold text-nexo-accent-cyan">{{ category.name }}</span>
      </div>
      <RouterLink :to="`/news/${story.slug}`" class="inline-flex items-center gap-1.5 text-xs font-semibold text-nexo-accent-cyan transition hover:gap-2.5">Explore the full analysis <ArrowRight class="h-3.5 w-3.5" /></RouterLink>
    </div>

    <MoreCoverageModal :is-open="isCoverageOpen" :story-title="story.title" :sources="supportingSources" @close="isCoverageOpen = false" />
  </article>
</template>
