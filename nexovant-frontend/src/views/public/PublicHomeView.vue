<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Activity,
  ArrowRight,
  Bookmark,
  CheckCircle2,
  ChevronDown,
  Clock,
  ExternalLink,
  Flame,
  Globe2,
  Radio,
  Share2,
  ShieldCheck,
  TrendingUp,
} from 'lucide-vue-next'
import ThreeColumnLayout from '@/components/layout/ThreeColumnLayout.vue'
import StoryClusterCard from '@/components/common/StoryClusterCard.vue'
import NewsletterSubscribe from '@/components/common/NewsletterSubscribe.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { feedService } from '@/services/api/feed.service'

const stories = ref<any[]>([])
const breakingStories = ref<any[]>([])
const importantStories = ref<any[]>([])
const isLoading = ref(true)
const activeFilter = ref('')
const selectedCategory = ref('')

const filterTabs = [
  { label: 'All', key: '' },
  { label: 'Corporate', key: 'corporate' },
  { label: 'Financial', key: 'financial' },
  { label: 'Industry', key: 'industry' },
  { label: 'Social trends', key: 'social-trends' },
  { label: 'Regulatory', key: 'regulatory' },
]

const heroStory = computed(() => {
  return breakingStories.value[0] || stories.value[0] || null
})

const importantDevelopments = computed(() => {
  if (importantStories.value.length >= 3) return importantStories.value.slice(0, 3)
  // Fallback to stories 1, 2, 3
  return stories.value.slice(1, 4)
})

const feedStories = computed(() => {
  const list = stories.value.slice(4)
  if (!activeFilter.value) return list
  return list.filter((s: any) => {
    if (activeFilter.value === 'breaking') return s.is_breaking
    const catSlug = s.categories?.[0]?.slug || ''
    const catName = (s.categories?.[0]?.name || s.category || '').toLowerCase()
    return catSlug === activeFilter.value || catName.includes(activeFilter.value)
  })
})

const fetchStories = async () => {
  isLoading.value = true
  try {
    const params: any = {}
    if (activeFilter.value) params.category = activeFilter.value
    else if (selectedCategory.value) params.category = selectedCategory.value

    const res = await feedService.getArticles(params)
    const all = res.data || []
    stories.value = all
    breakingStories.value = all.filter((story: any) => story.is_breaking)
    importantStories.value = all.filter((story: any) => !story.is_breaking && (story.importance_score >= 7 || story.verification_status === 'confirmed'))
  } catch {
    // Graceful fallback
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

const formatTimeAgo = (dateStr?: string) => {
  if (!dateStr) return 'Recently'
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${Math.max(1, mins)}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

onMounted(fetchStories)
</script>

<template>
  <ThreeColumnLayout @select-category="handleCategorySelect">
    <div class="space-y-8 text-left pb-16">

      <!-- SECTION 1: BREAKING DEVELOPMENTS HERO (Design Mockup: 1 News-first homepage — desktop.png) -->
      <section v-if="isLoading" class="space-y-3">
        <div class="card-editorial p-6 sm:p-7 space-y-4 animate-pulse">
          <div class="h-4 w-32 bg-nexo-border/40 rounded"></div>
          <div class="h-8 w-3/4 bg-nexo-border/40 rounded"></div>
          <div class="h-16 w-full bg-nexo-border/30 rounded"></div>
        </div>
      </section>

      <section v-else-if="heroStory" class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-[#B3261E] animate-pulse"></span>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#B3261E]">
              Breaking developments
            </span>
          </div>
          <span class="text-[11px] text-[#5F6875] flex items-center gap-1">
            <Radio class="h-3 w-3 text-[#B3261E]" />
            Live verified feed
          </span>
        </div>

        <div class="card-editorial overflow-hidden group">
          <div class="p-6 sm:p-7 space-y-4">
            <!-- Badges & Provenance Meta -->
            <div class="flex flex-wrap items-center gap-2.5 text-xs text-[#5F6875]">
              <span class="badge-breaking">BREAKING</span>
              <span class="font-medium text-[#3F4A5A]">{{ heroStory.source?.name || 'Verified Wire' }}</span>
              <span>·</span>
              <span>{{ formatTimeAgo(heroStory.published_at) }}</span>
              <span v-if="heroStory.sources_count">·</span>
              <span v-if="heroStory.sources_count" class="text-[#274888] font-medium">{{ heroStory.sources_count }} reports consolidated</span>
            </div>

            <!-- Headline -->
            <h2 class="editorial-headline text-2xl sm:text-3xl lg:text-[34px] font-medium text-[#0E1A2B] leading-[1.22]">
              <RouterLink :to="`/news/${heroStory.slug}`" class="hover:text-[#274888] transition-colors">
                {{ heroStory.title }}
              </RouterLink>
            </h2>

            <!-- Hero Crawled Source Image (Zero local storage) -->
            <div v-if="heroStory.featured_image || heroStory.primary_image || heroStory.hero_image" class="relative overflow-hidden rounded-[3px] border border-[#D0D9CF] max-h-72 bg-[#0E1A2B]/5">
              <img
                :src="heroStory.featured_image || heroStory.primary_image || heroStory.hero_image"
                :alt="heroStory.title"
                class="w-full h-64 object-cover"
                loading="lazy"
                referrerpolicy="no-referrer"
                @error="(e: any) => e.target.parentElement.style.display = 'none'"
              />
              <div class="absolute bottom-2 left-2 bg-black/80 backdrop-blur-xs text-white text-[10px] font-mono px-2 py-0.5 rounded-[2px]">
                {{ heroStory.featured_image_caption || (heroStory.source?.name ? `Source: ${heroStory.source.name}` : 'Source: Wire Service') }}
              </div>
            </div>

            <!-- Editorial Summary -->
            <p class="text-sm sm:text-[15px] text-[#3F4A5A] leading-relaxed max-w-3xl">
              {{ heroStory.summary?.executive_summary || heroStory.content_normalized?.slice(0, 260) || heroStory.content_raw?.slice(0, 260) || 'Primary corporate and regulatory disclosure consolidated from verified cross-border intelligence feeds.' }}
            </p>

            <!-- Entity Tags -->
            <div v-if="heroStory.tags?.length || heroStory.categories?.length" class="flex flex-wrap items-center gap-1.5 pt-1">
              <span
                v-for="tag in (heroStory.tags?.length ? heroStory.tags : heroStory.categories.map((c: any) => c.name))"
                :key="typeof tag === 'string' ? tag : tag.name"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-[#F8FAF7] border border-[#D0D9CF] text-[#0E1A2B]"
              >
                {{ typeof tag === 'string' ? tag : tag.name }}
              </span>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-[#D0D9CF]/60">
              <div class="flex items-center gap-2.5">
                <RouterLink :to="`/news/${heroStory.slug}`">
                  <button type="button" class="btn-ink text-xs px-4 py-2 flex items-center gap-1.5">
                    <span>Read story</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                  </button>
                </RouterLink>
                <RouterLink :to="`/news/${heroStory.slug}`">
                  <button
                    type="button"
                    class="text-xs px-3.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] font-medium transition-colors"
                  >
                    View Dossier
                  </button>
                </RouterLink>
              </div>

              <div class="flex items-center gap-2 text-xs text-[#17794D] bg-[#E3F1EA] px-2.5 py-1 rounded-[2px] font-medium">
                <ShieldCheck class="h-3.5 w-3.5" />
                <span>Verified · Provable facts</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION 2: IMPORTANT DEVELOPMENTS (3-card grid per Design Mockup) -->
      <section v-if="isLoading" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="i in 3" :key="i" class="card-editorial p-5 space-y-3 animate-pulse">
            <div class="h-3 w-20 bg-nexo-border/40 rounded"></div>
            <div class="h-5 w-4/5 bg-nexo-border/40 rounded"></div>
            <div class="h-10 w-full bg-nexo-border/30 rounded"></div>
          </div>
        </div>
      </section>

      <section v-else-if="importantDevelopments.length" class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="section-label">Important developments</h3>
          <RouterLink to="/feed" class="text-xs text-[#274888] hover:underline font-medium">
            View all ↗
          </RouterLink>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div
            v-for="(item, idx) in importantDevelopments"
            :key="item.id || idx"
            class="card-editorial p-5 flex flex-col justify-between space-y-3 hover:border-[#274888]/40 transition-colors"
          >
            <div class="space-y-2">
              <div class="flex items-center justify-between text-[10px] uppercase font-mono tracking-wider">
                <span class="font-bold text-[#8A5A08]">{{ item.category || item.categories?.[0]?.name || 'GLOBAL' }}</span>
                <span class="text-[#5F6875]">{{ formatTimeAgo(item.published_at) }}</span>
              </div>

              <h4 class="editorial-headline text-base font-medium text-[#0E1A2B] leading-snug line-clamp-3">
                <RouterLink :to="`/news/${item.slug}`" class="hover:text-[#274888] transition-colors">
                  {{ item.title }}
                </RouterLink>
              </h4>

              <!-- Remote Crawled Thumbnail (Zero local storage) -->
              <div v-if="item.featured_image || item.primary_image || item.hero_image" class="overflow-hidden rounded-[2px] border border-[#D0D9CF] h-28 bg-[#0E1A2B]/5">
                <img
                  :src="item.featured_image || item.primary_image || item.hero_image"
                  :alt="item.title"
                  class="w-full h-full object-cover"
                  loading="lazy"
                  referrerpolicy="no-referrer"
                  @error="(e: any) => e.target.parentElement.style.display = 'none'"
                />
              </div>

              <p class="text-xs text-[#5F6875] line-clamp-2 leading-relaxed">
                {{ item.summary?.executive_summary || item.content_normalized || item.content_raw?.slice(0, 140) || 'Consolidated intelligence from verified multi-publisher disclosures.' }}
              </p>
            </div>

            <div class="pt-3 border-t border-[#D0D9CF]/60 flex items-center justify-between text-[11px] text-[#5F6875]">
              <span class="badge-verified" v-if="item.verification_status === 'confirmed'">VERIFIED</span>
              <span class="badge-developing" v-else>DEVELOPING</span>
              <span v-if="item.sources_count">{{ item.sources_count }} sources</span>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION 3: GLOBAL INTELLIGENCE FEED -->
      <section class="space-y-4">
        <!-- Header & Category Filter Bar -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="section-label">Global intelligence feed</h3>
            <span class="text-xs text-[#5F6875]">{{ stories.length }} stories tracked</span>
          </div>

          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar text-xs">
            <button
              v-for="tab in filterTabs"
              :key="tab.key"
              type="button"
              @click="activeFilter = tab.key; fetchStories()"
              :class="[
                'px-3.5 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
                activeFilter === tab.key
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'bg-white border border-[#D0D9CF] text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
              ]"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <!-- Feed List -->
        <div v-if="isLoading" class="space-y-4">
          <div v-for="i in 3" :key="i" class="card-editorial p-6 space-y-3">
            <Skeleton class="h-4 w-1/4" />
            <Skeleton class="h-6 w-3/4" />
            <Skeleton class="h-16 w-full" />
          </div>
        </div>

        <div v-else-if="feedStories.length === 0" class="card-editorial p-12 text-center space-y-3">
          <div class="mx-auto w-10 h-10 rounded-full bg-[#EEEBE4] flex items-center justify-center text-[#5F6875]">
            <Globe2 class="w-5 h-5" />
          </div>
          <h4 class="font-serif text-lg font-medium text-[#0E1A2B]">No stories in this view</h4>
          <p class="text-xs text-[#5F6875] max-w-sm mx-auto">
            Try choosing a different topic filter or check back as the global intelligence collector updates.
          </p>
        </div>

        <div v-else class="space-y-4">
          <template v-for="(story, index) in feedStories" :key="story.id">
            <!-- In-feed Story Cluster Card -->
            <StoryClusterCard :story="story" @hidden="handleStoryHidden" />

            <!-- Mid-feed Trending Analysis Feature (after 2nd story) per Mockup P04 -->
            <div
              v-if="index === 1"
              class="card-editorial p-5 space-y-3 border-l-4 border-l-[#274888] bg-white"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="inline-flex items-center gap-1.5 font-mono text-[10px] font-bold text-[#274888] uppercase tracking-wider">
                  <Flame class="h-3.5 w-3.5 text-[#B7791F]" />
                  Trending discussion · Cross-platform velocity
                </span>
                <RouterLink to="/trends" class="text-[11px] text-[#274888] hover:underline font-medium">
                  Trend radar ↗
                </RouterLink>
              </div>

              <h4 class="editorial-headline text-lg font-medium text-[#0E1A2B]">
                High-NA EUV Commercial Packaging & Optics Heat Dissipation
              </h4>
              <p class="text-xs text-[#5F6875] leading-relaxed">
                Surging discussion regarding wafer throughput bottlenecks across leading foundries. Multi-platform signal velocity is accelerating (+64% in 24 hours).
              </p>

              <!-- Dual Bars: Popularity vs Verification (per P04 Trending.png) -->
              <div class="space-y-2 pt-2 border-t border-[#D0D9CF]/60 text-xs">
                <div>
                  <div class="flex justify-between text-[11px] text-[#3F4A5A] mb-1">
                    <span>Public Discussion Velocity</span>
                    <span class="font-mono font-semibold text-[#274888]">84% Velocity</span>
                  </div>
                  <div class="w-full bg-[#EEEBE4] h-2 rounded-full overflow-hidden">
                    <div class="bg-[#274888] h-full rounded-full" style="width: 84%"></div>
                  </div>
                </div>

                <div>
                  <div class="flex justify-between text-[11px] text-[#3F4A5A] mb-1">
                    <span>Evidence & Verification Score</span>
                    <span class="font-mono font-semibold text-[#17794D]">92% Verified</span>
                  </div>
                  <div class="w-full bg-[#EEEBE4] h-2 rounded-full overflow-hidden">
                    <div class="bg-[#17794D] h-full rounded-full" style="width: 92%"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mid-feed Sponsored Item (after 4th story) per Mockup Design System -->
            <div
              v-if="index === 3"
              class="card-editorial p-5 space-y-2.5 bg-[#FFFFFF] border-dashed border-[#D0D9CF]"
            >
              <div class="flex items-center justify-between text-[10px] font-mono">
                <span class="badge-sponsored">SPONSORED</span>
                <span class="text-[#5F6875]">Nordic Energy Infrastructure Council</span>
              </div>
              <h4 class="editorial-headline text-base font-medium text-[#0E1A2B]">
                Sustainable Grid Integration in Cross-Border European Free Markets
              </h4>
              <p class="text-xs text-[#5F6875] leading-relaxed">
                Whitepaper publication reviewing high-voltage direct-current transmission interconnectors and EU regulatory compliance timelines for grid operators.
              </p>
              <div class="pt-2 flex items-center justify-between text-[11px]">
                <a href="#" class="text-[#274888] hover:underline font-medium inline-flex items-center gap-1">
                  <span>Download Whitepaper (PDF)</span>
                  <ExternalLink class="h-3 w-3" />
                </a>
                <span class="text-[#5F6875] text-[10px]">Sponsored briefing</span>
              </div>
            </div>
          </template>
        </div>

        <!-- Load More Button per Mockup -->
        <div class="pt-4 text-center">
          <button
            type="button"
            class="px-6 py-2.5 rounded-[4px] border border-[#D0D9CF] bg-white hover:bg-[#EEEBE4] text-[#0E1A2B] text-xs font-semibold shadow-xs transition-colors"
          >
            Load more stories
          </button>
        </div>
      </section>

      <!-- Newsletter Callout per Design -->
      <NewsletterSubscribe
        source="home_mid_feed"
        title="A more considered start to your morning."
        description="Get a concise briefing of the global business developments that shape your day, with useful context attached."
      />

    </div>
  </ThreeColumnLayout>
</template>
