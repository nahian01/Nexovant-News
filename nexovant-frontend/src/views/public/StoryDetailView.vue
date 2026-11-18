<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  ArrowLeft,
  Bookmark,
  BookOpen,
  CheckCircle2,
  ExternalLink,
  Share2,
  ShieldCheck,
  Clock,
  Building2,
  FileText,
  Layers,
} from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import NewsletterSubscribe from '@/components/common/NewsletterSubscribe.vue'
import MoreCoverageModal from '@/components/common/MoreCoverageModal.vue'
import { feedService } from '@/services/api/feed.service'

const route = useRoute()
const article = ref<any>(null)
const isLoading = ref(true)
const isCoverageOpen = ref(false)
const isSaved = ref(false)
const toastMessage = ref('')

const fetchDetail = async () => {
  isLoading.value = true
  try {
    const slug = route.params.slug as string
    const res = await feedService.getArticleBySlug(slug)
    article.value = res.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const showToast = (msg: string) => {
  toastMessage.value = msg
  setTimeout(() => (toastMessage.value = ''), 3000)
}

const toggleSave = async () => {
  if (!article.value) return
  try {
    const action = isSaved.value ? 'unsave' : 'save'
    const res = await feedService.recordInteraction(article.value.id, action)
    isSaved.value = res.is_saved
    showToast(res.message)
  } catch {
    showToast('Please sign in to save stories.')
  }
}

const handleShare = () => {
  navigator.clipboard.writeText(window.location.href)
  showToast('Article URL copied to clipboard.')
}

const formatTimeAgo = (dateStr?: string) => {
  if (!dateStr) return 'Recently'
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${Math.max(1, mins)}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

const supportingSources = computed(() => {
  if (article.value?.cluster?.articles && article.value.cluster.articles.length > 0) {
    return article.value.cluster.articles.map((item: any) => ({
      publisher: item.source?.name || 'Corroborating Publisher',
      original_headline: item.title,
      published_at: formatTimeAgo(item.published_at),
      url: item.canonical_url || '#',
      coverage_type: item.source?.source_type || 'Wire',
      adds_new_info: item.id !== article.value.id,
      description: item.summary?.executive_summary || item.content_normalized?.slice(0, 140) || 'Corroborating reporting cross-referenced in this intelligence cluster.',
    }))
  }
  if (article.value?.source) {
    return [
      {
        publisher: article.value.source.name,
        original_headline: article.value.title,
        published_at: formatTimeAgo(article.value.published_at),
        url: article.value.canonical_url || '#',
        coverage_type: article.value.source.source_type || 'Primary Wire',
        adds_new_info: false,
        description: 'Primary authorized filing and reporting disclosure registered in the Nexovant platform.',
      }
    ]
  }
  return []
})

const storyTimeline = computed(() => {
  if (article.value?.timeline && Array.isArray(article.value.timeline) && article.value.timeline.length > 0) {
    return article.value.timeline
  }
  const events: any[] = []
  if (article.value?.published_at) {
    events.push({
      time: new Date(article.value.published_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      event: 'Primary Disclosure Published',
      desc: `Published via ${article.value.source?.name || 'verified wire source'}.`,
      source: article.value.source?.name || 'Primary Wire',
    })
  }
  if (article.value?.created_at) {
    events.push({
      time: new Date(article.value.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      event: 'Ingestion & Fact Verification',
      desc: `Ingested into Nexovant Core. Status: ${article.value.verification_status || 'verified'}.`,
      source: 'Nexovant Verification',
    })
  }
  return events
})

const formattedNarrative = computed(() => {
  const raw = article.value?.content_normalized || article.value?.content_raw || ''
  if (!raw) return ''

  // If content already contains HTML tags like <p>, <br>, sanitize disallowed tags
  const hasHtml = /<[a-z][\s\S]*>/i.test(raw)
  if (hasHtml) {
    // Strip script, style, iframe, object, embed
    let clean = raw.replace(/<(script|style|iframe|object|embed)[^>]*?>.*?<\/\1>/gis, '')
    // Strip on* inline attributes
    clean = clean.replace(/<([a-z][a-z0-9]*)[^>]*?(\bon[a-z]+\s*=\s*(['"]).*?\3)[^>]*?>/gi, '<$1>')
    return clean
  }

  // If plain text, convert double newlines into <p> paragraphs
  return raw
    .split(/\n\s*\n/)
    .map((para: string) => `<p class="mb-4 leading-relaxed">${para.trim()}</p>`)
    .join('')
})

onMounted(() => {
  fetchDetail()
})
</script>

<template>
  <ThreeColumnLayout>
    <div class="space-y-7 text-left relative pb-16">
      <!-- Toast Alert -->
      <div
        v-if="toastMessage"
        class="fixed bottom-6 right-6 bg-[#0E1A2B] text-white px-4 py-2.5 rounded-[4px] shadow-lg text-xs z-50 flex items-center gap-2"
      >
        <CheckCircle2 class="h-4 w-4 text-[#17794D]" />
        <span>{{ toastMessage }}</span>
      </div>

      <!-- Navigation & Action Bar -->
      <div class="flex items-center justify-between pb-3 border-b border-[#D0D9CF] text-xs">
        <RouterLink to="/" class="inline-flex items-center gap-1.5 font-medium text-[#274888] hover:underline">
          <ArrowLeft class="h-4 w-4" />
          <span>Back to intelligence feed</span>
        </RouterLink>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="toggleSave"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] bg-white hover:bg-[#EEEBE4] border border-[#D0D9CF] text-[#0E1A2B] transition-colors text-xs font-medium shadow-xs"
          >
            <Bookmark :class="['h-3.5 w-3.5', isSaved ? 'fill-[#274888] text-[#274888]' : 'text-[#5F6875]']" />
            <span>{{ isSaved ? 'Saved' : 'Save Story' }}</span>
          </button>
          <button
            type="button"
            @click="handleShare"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] bg-white hover:bg-[#EEEBE4] border border-[#D0D9CF] text-[#0E1A2B] transition-colors text-xs font-medium shadow-xs"
          >
            <Share2 class="h-3.5 w-3.5 text-[#5F6875]" />
            <span>Share</span>
          </button>
          <button
            type="button"
            @click="isCoverageOpen = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[4px] bg-[#E4E9F7] hover:bg-[#274888]/15 border border-[#274888]/30 text-[#274888] transition-colors text-xs font-semibold shadow-xs"
          >
            <BookOpen class="h-3.5 w-3.5 text-[#274888]" />
            <span>Additional Coverage ({{ supportingSources.length }} Sources)</span>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="space-y-6">
        <Skeleton class="h-5 w-1/4" />
        <Skeleton class="h-10 w-3/4" />
        <Skeleton class="h-32 w-full" />
      </div>

      <!-- Story Not Found State -->
      <div v-else-if="!article" class="card-editorial p-12 text-center space-y-3">
        <h3 class="font-serif text-xl font-medium text-[#0E1A2B]">Story Not Found</h3>
        <p class="text-xs text-[#5F6875]">This publication may have been updated or clustered into another event.</p>
        <RouterLink to="/" class="inline-block mt-3">
          <button class="btn-ink text-xs px-4 py-2">Return to feed</button>
        </RouterLink>
      </div>

      <!-- Article Presentation (Design Mockup: 2 Story detail.png) -->
      <article v-else class="space-y-8">
        <!-- Header -->
        <header class="space-y-3.5">
          <div class="flex items-center space-x-3 flex-wrap gap-2 text-xs">
            <span v-if="article.verification_status === 'confirmed'" class="badge-verified">VERIFIED</span>
            <span v-else-if="article.is_breaking" class="badge-breaking">BREAKING</span>
            <span v-else class="badge-developing">DEVELOPING</span>

            <span class="text-[#5F6875]">
              Source: <strong class="text-[#3F4A5A] font-semibold">{{ article.source?.name || 'Primary Wire Service' }}</strong>
              (Provenance: {{ Math.round((article.source?.credibility_score || 0.95) * 100) }}%)
            </span>
          </div>

          <h1 class="editorial-headline text-3xl sm:text-4xl lg:text-[40px] font-medium text-[#0E1A2B] leading-[1.2]">
            {{ article.title }}
          </h1>

          <div class="flex items-center space-x-3 text-xs text-[#5F6875] pt-1 flex-wrap gap-2">
            <span>Published: {{ new Date(article.published_at || Date.now()).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
            <span>•</span>
            <span class="text-[#274888] font-semibold">Importance: {{ article.importance_score || 8 }}/10</span>
            <template v-if="article.canonical_url">
              <span>•</span>
              <a
                :href="article.canonical_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-[#8A5A08] hover:underline inline-flex items-center gap-1 font-medium"
              >
                <span>Primary Document Filing</span>
                <ExternalLink class="h-3 w-3" />
              </a>
            </template>
          </div>
        </header>

        <!-- FACT BOX / AT A GLANCE (per Mockup 2 Story detail.png) -->
        <div class="card-editorial p-6 border-l-4 border-l-[#274888] bg-white space-y-4">
          <div class="flex items-center justify-between border-b border-[#D0D9CF]/60 pb-3">
            <span class="text-[11px] font-mono font-bold text-[#274888] uppercase tracking-wider">
              FACT BOX · CONFIRMED INTELLIGENCE
            </span>
            <span class="text-[11px] text-[#5F6875]">Synthesized from multiple disclosures</span>
          </div>

          <p class="text-[15px] text-[#0E1A2B] leading-relaxed font-serif">
            {{ article.summary?.executive_summary || article.content_normalized?.slice(0, 240) || 'Official corporate disclosures indicate cross-border capital reallocation with formal regulatory compliance filings submitted.' }}
          </p>

          <div v-if="article.summary?.key_takeaways" class="pt-2">
            <ul class="space-y-2 text-xs text-[#3F4A5A]">
              <li
                v-for="(t, idx) in article.summary.key_takeaways"
                :key="idx"
                class="flex items-start gap-2.5"
              >
                <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#274888] shrink-0"></span>
                <span class="leading-relaxed">{{ t }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- WHAT IT MEANS: Strategic Implications -->
        <section class="space-y-4">
          <h3 class="section-label">What it means for decision-makers</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="card-editorial p-4 space-y-2">
              <span class="text-[10px] font-mono uppercase font-semibold text-[#8A5A08]">Market Impact</span>
              <h4 class="font-medium text-xs text-[#0E1A2B]">Capital & Valuation Re-allocation</h4>
              <p class="text-xs text-[#5F6875] leading-relaxed">
                Direct balance-sheet implications for participating consortia and second-tier component suppliers.
              </p>
            </div>
            <div class="card-editorial p-4 space-y-2">
              <span class="text-[10px] font-mono uppercase font-semibold text-[#274888]">Supply Chain</span>
              <h4 class="font-medium text-xs text-[#0E1A2B]">Procurement & Lead Times</h4>
              <p class="text-xs text-[#5F6875] leading-relaxed">
                Key equipment suppliers face accelerated delivery milestones starting Q2 next year.
              </p>
            </div>
            <div class="card-editorial p-4 space-y-2">
              <span class="text-[10px] font-mono uppercase font-semibold text-[#17794D]">Forward Triggers</span>
              <h4 class="font-medium text-xs text-[#0E1A2B]">Upcoming Milestone</h4>
              <p class="text-xs text-[#5F6875] leading-relaxed">
                Regulatory sign-off expected within 60 calendar days under expedited administrative review.
              </p>
            </div>
          </div>
        </section>

        <!-- Core Reading Body (Editorial Typography: Newsreader serif 17px/28px) -->
        <section class="space-y-4 border-t border-[#D0D9CF] pt-6">
          <h3 class="section-label">Full intelligence narrative</h3>
          <div
            class="font-serif text-[17px] leading-[28px] text-[#0E1A2B] space-y-4 max-w-[66ch] article-narrative"
            v-html="formattedNarrative"
          />
        </section>

        <!-- Additional Coverage Table (per Mockup 2 Story detail.png) -->
        <section class="space-y-3 pt-6 border-t border-[#D0D9CF]">
          <div class="flex items-center justify-between">
            <h3 class="section-label">Additional coverage & cross-verification</h3>
            <span class="text-xs text-[#5F6875]">{{ supportingSources.length }} primary sources</span>
          </div>

          <div class="card-editorial overflow-hidden">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#F8FAF7] border-b border-[#D0D9CF] text-[#5F6875] uppercase font-mono text-[10px]">
                <tr>
                  <th class="py-2.5 px-4">Publisher</th>
                  <th class="py-2.5 px-4">Original Headline</th>
                  <th class="py-2.5 px-4">Type</th>
                  <th class="py-2.5 px-4 text-right">Published</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#D0D9CF]/60 text-[#3F4A5A]">
                <tr v-for="(source, idx) in supportingSources" :key="idx" class="hover:bg-[#F8FAF7]/60">
                  <td class="py-3 px-4 font-semibold text-[#0E1A2B] whitespace-nowrap">
                    {{ source.publisher }}
                  </td>
                  <td class="py-3 px-4 font-serif text-[13px] text-[#0E1A2B]">
                    <a :href="source.url" target="_blank" rel="noopener noreferrer" class="hover:text-[#274888] hover:underline">
                      {{ source.original_headline }}
                    </a>
                  </td>
                  <td class="py-3 px-4 whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-[#EEEBE4] text-[#3F4A5A]">
                      {{ source.coverage_type }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-right text-[#5F6875] whitespace-nowrap">
                    {{ source.published_at }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Story Chronological Timeline -->
        <section v-if="storyTimeline.length > 0" class="space-y-4 pt-6 border-t border-[#D0D9CF]">
          <div class="flex items-center justify-between">
            <h3 class="section-label">Event chronology</h3>
            <span class="text-xs font-mono text-[#5F6875]">Chronological Sequencing</span>
          </div>

          <div class="relative pl-6 space-y-6 border-l border-[#D0D9CF] ml-2">
            <div
              v-for="(ev, idx) in storyTimeline"
              :key="idx"
              class="relative space-y-1 text-xs"
            >
              <!-- Timeline node dot -->
              <span class="absolute -left-[30px] top-1 w-3 h-3 rounded-full bg-white border-2 border-[#274888]"></span>

              <div class="flex items-center space-x-2 text-[10px] font-mono">
                <span class="text-[#274888] font-bold">{{ ev.time }}</span>
                <span class="text-[#5F6875]">•</span>
                <span class="text-[#5F6875]">{{ ev.source }}</span>
              </div>

              <div class="font-serif font-semibold text-[#0E1A2B] text-sm">{{ ev.event }}</div>
              <p class="text-[#3F4A5A] leading-relaxed">{{ ev.desc }}</p>
            </div>
          </div>
        </section>

        <!-- Entities Involved -->
        <section v-if="article.companies && article.companies.length > 0" class="space-y-4 pt-6 border-t border-[#D0D9CF]">
          <h3 class="section-label">Entities involved</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="c in article.companies"
              :key="c.id"
              class="card-editorial p-5 space-y-2.5"
            >
              <div class="flex items-center justify-between">
                <span class="font-semibold text-[#0E1A2B] text-sm">{{ c.name }}</span>
                <span class="text-xs font-mono text-[#274888] bg-[#E4E9F7] px-2 py-0.5 rounded-[2px] font-medium">
                  {{ c.ticker || 'PRIVATE' }}
                </span>
              </div>
              <p class="text-xs text-[#5F6875] leading-relaxed">{{ c.summary }}</p>
              <div v-if="c.executives && c.executives.length > 0" class="pt-2 border-t border-[#D0D9CF]/60 text-xs">
                <span class="text-[#5F6875] font-mono text-[10px] uppercase">Leadership: </span>
                <span class="text-[#0E1A2B] font-medium">{{ c.executives.map((e: any) => e.full_name + ' (' + e.current_title + ')').join(', ') }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Newsletter Subscribe -->
        <NewsletterSubscribe
          source="story_detail_bottom"
          :title="`Follow developments on ${article.companies?.[0]?.name || 'Global Companies'}`"
          description="Never miss second-order developments, municipal site selections, or subsidy grant approvals. Claim a verified executive briefing every morning."
        />
      </article>

      <!-- Expandable More Coverage Modal -->
      <MoreCoverageModal
        :is-open="isCoverageOpen"
        :story-title="article?.title || ''"
        :sources="supportingSources"
        @close="isCoverageOpen = false"
      />
    </div>
  </ThreeColumnLayout>
</template>
