<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  TrendingUp,
  Building2,
  Cpu,
  Hash,
  Sparkles,
  ArrowRight,
  Check,
  Plus,
  Compass,
  Search,
} from 'lucide-vue-next'
import { workspaceService } from '@/services/api/workspace.service'
import { apiClient } from '@/services/api/client'
import Badge from '@/components/ui/Badge.vue'

const router = useRouter()
const researchPrompt = ref('')
const isSubmittingFollow = ref<Record<string, boolean>>({})

// Trending Signals
const trendingSignals = ref<any[]>([])
const isLoadingTrends = ref(true)

// Followed state tracking
const followedEntities = ref<Record<string, boolean>>({})

// Suggested Entities
const suggestedCompanies = ref<any[]>([
  { id: 'nvidia-corp', name: 'NVIDIA Corporation', ticker: 'NVDA', type: 'company', followers: '18.4k' },
  { id: 'asml-corp', name: 'ASML Holding NV', ticker: 'ASML', type: 'company', followers: '12.1k' },
  { id: 'tsmc-corp', name: 'TSMC', ticker: 'TSM', type: 'company', followers: '15.6k' },
  { id: 'microsoft-corp', name: 'Microsoft Corporation', ticker: 'MSFT', type: 'company', followers: '22.3k' },
])

const emergingTechnologies = ref<any[]>([
  { slug: 'high-na-euv', name: 'High-NA EUV Lithography', type: 'technology', category: 'Semiconductors' },
  { slug: 'generative-agents', name: 'Autonomous AI Agents', type: 'technology', category: 'Artificial Intelligence' },
  { slug: 'quantum-annealing', name: 'Fault-Tolerant Quantum Computing', type: 'technology', category: 'Hardware' },
  { slug: 'radioligand-therapy', name: 'Radioligand Targeted Therapy', type: 'technology', category: 'Biotechnology' },
])

const suggestedTopics = ref<any[]>([
  { slug: 'silicon-sovereignty', tag: '#SiliconSovereignty', name: 'Silicon Sovereignty', count: '14.2k mentions' },
  { slug: 'eu-chips-act', tag: '#EUChipsAct', name: 'EU Chips Act', count: '9.8k mentions' },
  { slug: 'antitrust-regulatory', tag: '#AntitrustRegulatory', name: 'Antitrust Regulatory', count: '7.4k mentions' },
])

const loadPanelData = async () => {
  // 1. Fetch live trends
  try {
    const res = await apiClient.get('/trends?limit=3')
    if (res.data?.data && res.data.data.length > 0) {
      trendingSignals.value = res.data.data.slice(0, 3)
    }
  } catch {
    // Fallback default signals
    trendingSignals.value = [
      { id: 'tr-1', title: 'High-NA Lithography Supply Constraints', composite_trend_score: 94, cross_platform_count: 3, status: 'rapidly_accelerating' },
      { id: 'tr-2', title: 'Custom AI ASIC Enterprise Transition', composite_trend_score: 87, cross_platform_count: 2, status: 'trending' },
      { id: 'tr-3', title: 'European Sovereign Cloud Mandates', composite_trend_score: 81, cross_platform_count: 3, status: 'emerging' },
    ]
  } finally {
    isLoadingTrends.value = false
  }

  // 2. Fetch following list to mark followed items
  try {
    const followingRes = await workspaceService.getFollowing()
    if (followingRes?.data) {
      for (const item of followingRes.data) {
        followedEntities.value[`${item.type}:${item.id}`] = true
        followedEntities.value[`${item.type}:${item.name}`] = true
      }
    }
  } catch {
    //
  }

  // 3. Fetch following suggestions if available
  try {
    const suggestionsRes = await workspaceService.getFollowingSuggestions()
    if (suggestionsRes?.data) {
      if (suggestionsRes.data.companies?.length) {
        suggestedCompanies.value = suggestionsRes.data.companies.slice(0, 4)
      }
      if (suggestionsRes.data.technologies?.length) {
        emergingTechnologies.value = suggestionsRes.data.technologies.slice(0, 4)
      }
      if (suggestionsRes.data.topics?.length) {
        suggestedTopics.value = suggestionsRes.data.topics.slice(0, 3).map((t: any) => ({
          slug: t.slug || t.id,
          tag: `#${(t.name || '').replace(/\s+/g, '')}`,
          name: t.name,
          count: 'Trending'
        }))
      }
    }
  } catch {
    //
  }
}

const toggleFollow = async (type: string, id: string, name: string) => {
  const key = `${type}:${id}`
  isSubmittingFollow.value[key] = true
  try {
    const res = await workspaceService.toggleFollow(type, id, name)
    if (res?.is_following !== undefined) {
      followedEntities.value[key] = res.is_following
      followedEntities.value[`${type}:${name}`] = res.is_following
    } else {
      followedEntities.value[key] = !followedEntities.value[key]
      followedEntities.value[`${type}:${name}`] = followedEntities.value[key]
    }
  } catch {
    // Toggle optimistically on error fallback
    followedEntities.value[key] = !followedEntities.value[key]
  } finally {
    isSubmittingFollow.value[key] = false
  }
}

const handleQuickResearch = () => {
  if (!researchPrompt.value.trim()) return
  router.push({ path: '/search', query: { q: researchPrompt.value.trim(), mode: 'rag' } })
}

onMounted(() => {
  loadPanelData()
})
</script>

<template>
  <aside class="sticky top-[68px] hidden h-[calc(100vh-68px)] w-[320px] shrink-0 space-y-5 overflow-y-auto border-l border-nexo-border/80 bg-white/70 px-4 py-5 text-left backdrop-blur-sm xl:block 2xl:w-[360px]">
    <!-- 1. Executive AI Research Box -->
    <div class="rounded-2xl border border-nexo-accent-cyan/20 bg-gradient-to-br from-[#f0f7f3] via-white to-[#f4f8f5] p-4 shadow-sm">
      <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-nexo-accent-cyan">
        <Sparkles class="h-4 w-4" />
        <span>Grounded RAG Research</span>
      </div>
      <p class="mt-1.5 text-[11px] leading-relaxed text-nexo-text-secondary">
        Synthesize cross-source verified intelligence directly from the knowledge base.
      </p>
      <form @submit.prevent="handleQuickResearch" class="mt-3">
        <div class="relative">
          <input
            v-model="researchPrompt"
            type="text"
            placeholder="Ask research engine..."
            class="w-full rounded-xl border border-nexo-border bg-white py-2 pl-3 pr-8 text-xs text-nexo-text-primary placeholder-nexo-text-muted transition focus:border-nexo-accent-cyan focus:outline-none focus:ring-1 focus:ring-nexo-accent-cyan"
          />
          <button
            type="submit"
            class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg p-1 text-nexo-accent-cyan transition hover:bg-[#e9f3ed]"
            aria-label="Execute search"
          >
            <ArrowRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </form>
    </div>

    <!-- 2. Trending Now Signals -->
    <section class="space-y-3">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-2">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <TrendingUp class="h-3.5 w-3.5 text-nexo-accent-cyan" />
          Trending Signals
        </span>
        <RouterLink
          to="/trends"
          class="text-[11px] font-medium text-nexo-accent-cyan transition hover:underline"
        >
          Radar
        </RouterLink>
      </div>

      <div class="space-y-2">
        <div
          v-for="trend in trendingSignals"
          :key="trend.id"
          class="group rounded-xl border border-nexo-border/70 bg-white/90 p-3 transition-all hover:border-nexo-accent-cyan/30 hover:shadow-xs"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center rounded-md bg-nexo-accent-cyan/10 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-nexo-accent-cyan">
              Velocity: {{ trend.composite_trend_score || 85 }}
            </span>
            <span class="text-[10px] text-nexo-text-muted">
              {{ trend.cross_platform_count || 3 }} platforms
            </span>
          </div>
          <RouterLink
            :to="`/trends`"
            class="mt-1.5 block font-serif text-[13px] font-medium leading-snug text-nexo-text-primary transition group-hover:text-nexo-accent-cyan"
          >
            {{ trend.title }}
          </RouterLink>
        </div>
      </div>
    </section>

    <!-- 3. Most Followed Companies -->
    <section class="space-y-3">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-2">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <Building2 class="h-3.5 w-3.5 text-nexo-accent-gold" />
          Tracked Companies
        </span>
        <RouterLink
          to="/following"
          class="text-[11px] font-medium text-nexo-accent-cyan transition hover:underline"
        >
          View all
        </RouterLink>
      </div>

      <div class="space-y-1.5">
        <div
          v-for="company in suggestedCompanies"
          :key="company.id"
          class="flex items-center justify-between rounded-xl p-2 transition hover:bg-nexo-bg"
        >
          <div class="min-w-0 pr-2">
            <div class="flex items-center gap-1.5">
              <span class="truncate text-xs font-semibold text-nexo-text-primary">{{ company.name }}</span>
            </div>
            <div class="text-[10px] text-nexo-text-muted">
              <span v-if="company.ticker" class="font-mono font-medium text-nexo-text-secondary">{{ company.ticker }} · </span>
              <span>{{ company.followers || 'Tracked' }}</span>
            </div>
          </div>
          <button
            type="button"
            @click="toggleFollow('company', company.id || company.slug, company.name)"
            :disabled="isSubmittingFollow[`company:${company.id || company.slug}`]"
            :class="[
              'flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-medium transition',
              followedEntities[`company:${company.id || company.slug}`] || followedEntities[`company:${company.name}`]
                ? 'bg-[#e9f3ed] text-nexo-accent-cyan border border-nexo-accent-cyan/30'
                : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            <Check v-if="followedEntities[`company:${company.id || company.slug}`] || followedEntities[`company:${company.name}`]" class="h-3 w-3" />
            <Plus v-else class="h-3 w-3" />
            <span>{{ followedEntities[`company:${company.id || company.slug}`] || followedEntities[`company:${company.name}`] ? 'Following' : 'Follow' }}</span>
          </button>
        </div>
      </div>
    </section>

    <!-- 4. Emerging Technologies -->
    <section class="space-y-3">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-2">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <Cpu class="h-3.5 w-3.5 text-nexo-accent-cyan" />
          Emerging Technologies
        </span>
        <RouterLink
          to="/following"
          class="text-[11px] font-medium text-nexo-accent-cyan transition hover:underline"
        >
          Explore
        </RouterLink>
      </div>

      <div class="space-y-1.5">
        <div
          v-for="tech in emergingTechnologies"
          :key="tech.slug"
          class="flex items-center justify-between rounded-xl p-2 transition hover:bg-nexo-bg"
        >
          <div class="min-w-0 pr-2">
            <p class="truncate text-xs font-semibold text-nexo-text-primary">{{ tech.name }}</p>
            <p class="text-[10px] text-nexo-text-muted">{{ tech.category || 'Strategic Tech' }}</p>
          </div>
          <button
            type="button"
            @click="toggleFollow('technology', tech.slug, tech.name)"
            :disabled="isSubmittingFollow[`technology:${tech.slug}`]"
            :class="[
              'flex shrink-0 items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-medium transition',
              followedEntities[`technology:${tech.slug}`] || followedEntities[`technology:${tech.name}`]
                ? 'bg-[#e9f3ed] text-nexo-accent-cyan border border-nexo-accent-cyan/30'
                : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            <Check v-if="followedEntities[`technology:${tech.slug}`] || followedEntities[`technology:${tech.name}`]" class="h-3 w-3" />
            <Plus v-else class="h-3 w-3" />
            <span>{{ followedEntities[`technology:${tech.slug}`] || followedEntities[`technology:${tech.name}`] ? 'Following' : 'Follow' }}</span>
          </button>
        </div>
      </div>
    </section>

    <!-- 5. Suggested Topics -->
    <section class="space-y-3">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-2">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <Hash class="h-3.5 w-3.5 text-nexo-accent-gold" />
          Suggested Topics
        </span>
      </div>

      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="topic in suggestedTopics"
          :key="topic.slug"
          type="button"
          @click="toggleFollow('topic', topic.slug, topic.name)"
          :class="[
            'group flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-medium transition',
            followedEntities[`topic:${topic.slug}`] || followedEntities[`topic:${topic.name}`]
              ? 'border-nexo-accent-cyan/40 bg-[#e9f3ed] text-nexo-accent-cyan'
              : 'border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
          ]"
        >
          <span>{{ topic.tag }}</span>
          <Check v-if="followedEntities[`topic:${topic.slug}`] || followedEntities[`topic:${topic.name}`]" class="h-3 w-3" />
          <Plus v-else class="h-3 w-3 text-nexo-text-muted group-hover:text-nexo-accent-cyan" />
        </button>
      </div>
    </section>
  </aside>
</template>
