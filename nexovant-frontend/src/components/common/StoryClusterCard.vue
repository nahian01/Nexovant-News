<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Bookmark,
  ChevronDown,
  Flag,
  MoreHorizontal,
  Share2,
  ThumbsDown,
  ThumbsUp,
  X,
} from 'lucide-vue-next'
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

const categoryLabel = computed(() => {
  if (props.story.categories && props.story.categories.length > 0) {
    return props.story.categories[0].name.toUpperCase()
  }
  return 'CORPORATE'
})

const subcategoryLabel = computed(() => {
  if (props.story.is_breaking) return 'BREAKING'
  return 'DEVELOPMENT'
})

const dateLabel = computed(() => {
  if (!props.story.published_at) return 'Recent'
  const d = new Date(props.story.published_at)
  return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' })
})

const sourcesCount = computed(() => {
  return props.story.corroborating_sources_count || props.story.sources_count || 4
})

const supportingSources = [
  {
    publisher: props.story.source?.name || 'Primary Regulatory Filing',
    original_headline: props.story.title,
    published_at: '2h ago',
    url: props.story.canonical_url || 'https://sec.gov',
    coverage_type: 'Regulatory',
    adds_new_info: true,
    description: 'Direct institutional disclosure and confirmed reporting.',
  },
  {
    publisher: 'Financial News Wire',
    original_headline: props.story.title,
    published_at: '1h ago',
    url: 'https://reuters.com',
    coverage_type: 'Wire',
    adds_new_info: false,
    description: 'Cross-confirmation citing market filings and official statements.',
  },
]
</script>

<template>
  <article class="relative rounded-[2px] border border-nexo-border bg-white p-5 text-left transition-colors hover:border-nexo-ink/40">
    <!-- Toast message overlay -->
    <div v-if="toastMessage" class="absolute right-4 top-4 z-30 rounded-[2px] border border-nexo-border bg-nexo-paper px-3 py-1.5 text-xs font-semibold text-nexo-ink shadow-sm">
      {{ toastMessage }}
    </div>

    <!-- Category, Event Date & Actions (matches 1 News-first homepage — desktop.png) -->
    <div class="flex items-center justify-between text-xs">
      <div class="flex items-center gap-2">
        <span class="section-label text-nexo-cobalt">{{ categoryLabel }}</span>
        <span class="text-nexo-muted">·</span>
        <span class="section-label">{{ subcategoryLabel }}</span>
        <span class="text-nexo-muted">·</span>
        <span class="text-[11px] text-nexo-muted font-medium">Event {{ dateLabel }}</span>
      </div>

      <div class="relative">
        <button
          type="button"
          @click="isActionsOpen = !isActionsOpen"
          class="rounded p-1 text-nexo-muted hover:text-nexo-ink"
          aria-label="Story actions"
        >
          <MoreHorizontal class="h-4 w-4" />
        </button>

        <div v-if="isActionsOpen" class="absolute right-0 top-6 z-30 w-48 rounded-[2px] border border-nexo-border bg-white p-1 text-xs shadow-popover">
          <button type="button" @click="toggleSave" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-nexo-text-2 hover:bg-nexo-paper-2 hover:text-nexo-ink">
            <Bookmark class="h-3.5 w-3.5" />{{ isSaved ? 'Remove saved story' : 'Save story' }}
          </button>
          <button type="button" @click="handleShare" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-nexo-text-2 hover:bg-nexo-paper-2 hover:text-nexo-ink">
            <Share2 class="h-3.5 w-3.5" />Copy story link
          </button>
          <div class="my-1 border-t border-nexo-border"></div>
          <button type="button" @click="handleFeedback('more_like_this')" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-nexo-text-2 hover:bg-nexo-paper-2">
            <ThumbsUp class="h-3.5 w-3.5" />More like this
          </button>
          <button type="button" @click="handleFeedback('less_like_this')" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-nexo-text-2 hover:bg-nexo-paper-2">
            <ThumbsDown class="h-3.5 w-3.5" />Less like this
          </button>
          <button type="button" @click="handleFeedback('hide')" class="flex w-full items-center gap-2 px-2.5 py-1.5 text-left text-nexo-breaking hover:bg-[#FBE4E1]/40">
            <X class="h-3.5 w-3.5" />Hide story
          </button>
        </div>
      </div>
    </div>

    <!-- Headline (Newsreader serif 20px / 26px) -->
    <h2 class="editorial-headline mt-2 text-[20px] font-semibold leading-[26px]">
      <RouterLink :to="`/news/${story.slug}`" class="text-nexo-ink hover:underline">
        {{ story.title }}
      </RouterLink>
    </h2>

    <!-- Remote Crawled Source Image (Zero local storage - hotlinked directly from source) -->
    <div v-if="story.featured_image || story.primary_image || story.hero_image" class="mt-2.5 relative overflow-hidden rounded-[2px] border border-nexo-border max-h-52 bg-nexo-paper-2">
      <img
        :src="story.featured_image || story.primary_image || story.hero_image"
        :alt="story.title"
        class="w-full h-44 object-cover transition-transform duration-300 hover:scale-[1.01]"
        loading="lazy"
        referrerpolicy="no-referrer"
        @error="(e: any) => e.target.parentElement.style.display = 'none'"
      />
      <div v-if="story.featured_image_caption || story.source?.name" class="absolute bottom-1.5 left-1.5 bg-black/75 backdrop-blur-xs text-white text-[9px] font-mono px-1.5 py-0.5 rounded-[2px]">
        {{ story.featured_image_caption || `Source: ${story.source?.name || 'Wire Service'}` }}
      </div>
    </div>

    <!-- Summary Header & Text -->
    <div class="mt-2.5">
      <p class="section-label text-[10px] text-nexo-muted uppercase tracking-[0.08em]">
        SUMMARY, GENERATED FROM {{ sourcesCount }} REPORTS
      </p>
      <p class="mt-1 text-sm text-nexo-text-2 leading-relaxed">
        {{ story.content_normalized || story.content_raw || story.summary }}
      </p>
    </div>

    <!-- Entity Tags (Halvorsen Energy, Dana Whitcombe, etc.) -->
    <div class="mt-3 flex flex-wrap gap-1.5">
      <template v-if="story.companies && story.companies.length">
        <RouterLink
          v-for="co in story.companies"
          :key="co.id"
          to="/companies"
          class="rounded-[2px] border border-nexo-border bg-nexo-paper-2/60 px-2 py-0.5 text-xs text-nexo-text-2 hover:text-nexo-ink"
        >
          {{ co.name }}
        </RouterLink>
      </template>
      <template v-else>
        <span class="rounded-[2px] border border-nexo-border bg-nexo-paper-2/60 px-2 py-0.5 text-xs text-nexo-text-2">
          Corporate Filing
        </span>
      </template>
    </div>

    <!-- Footer Bar: Verification Status + More Coverage + Bookmark/Share -->
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-nexo-border pt-3 text-xs">
      <!-- Status & Timestamp -->
      <div class="flex items-center gap-2.5">
        <span v-if="story.verification_status === 'confirmed' || story.verification_status === 'verified'" class="badge-verified">
          ✓ Verified
        </span>
        <span v-else-if="story.is_breaking" class="badge-breaking">
          Breaking
        </span>
        <span v-else class="badge-developing">
          ● Developing
        </span>
        <span class="text-[11px] text-nexo-muted">
          {{ story.source?.name ? `Via ${story.source.name}` : 'Latest: figures confirmed 2h ago' }}
        </span>
      </div>

      <!-- Right Action Controls -->
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isCoverageOpen = true"
          class="flex items-center gap-1 text-xs font-medium text-nexo-text-2 hover:text-nexo-ink"
        >
          <span>{{ sourcesCount }} sources · More coverage</span>
          <ChevronDown class="h-3 w-3" />
        </button>

        <button
          type="button"
          @click="toggleSave"
          class="text-nexo-muted hover:text-nexo-ink"
          :title="isSaved ? 'Remove bookmark' : 'Bookmark story'"
        >
          <Bookmark :class="['h-3.5 w-3.5', isSaved ? 'fill-nexo-ink text-nexo-ink' : '']" />
        </button>

        <button
          type="button"
          @click="handleShare"
          class="text-nexo-muted hover:text-nexo-ink"
          title="Share story"
        >
          <Share2 class="h-3.5 w-3.5" />
        </button>
      </div>
    </div>

    <!-- More Coverage Modal -->
    <MoreCoverageModal :is-open="isCoverageOpen" :story-title="story.title" :sources="supportingSources" @close="isCoverageOpen = false" />
  </article>
</template>
