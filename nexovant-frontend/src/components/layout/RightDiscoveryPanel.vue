<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ChevronDown,
  ChevronUp,
} from 'lucide-vue-next'
import { categoryService, type CategoryItem } from '@/services/api/categories.service'
import { apiClient } from '@/services/api/client'

const emit = defineEmits<{
  (e: 'selectCategory', slug: string): void
}>()

const categories = ref<CategoryItem[]>([])
const expandedCategory = ref<string | null>('technology')
const followedEntities = ref<Record<string, boolean>>({})

const defaultCategories: CategoryItem[] = [
  { id: '1', name: 'Strategic Technology', slug: 'strategic-technology', industry: 'Technology', color: '#274888', article_count: 0, subcategories: [] },
  { id: '2', name: 'Leadership Transitions', slug: 'leadership-transitions', industry: 'Corporate & Governance', color: '#17794D', article_count: 0, subcategories: [] },
  { id: '3', name: 'Financial Performance', slug: 'financial-performance', industry: 'Corporate & Governance', color: '#8A5A08', article_count: 0, subcategories: [] },
  { id: '4', name: 'Regulatory & Legal', slug: 'regulatory-legal', industry: 'Corporate & Governance', color: '#B7791F', article_count: 0, subcategories: [] },
  { id: '5', name: 'Emerging Risks', slug: 'emerging-risks', industry: 'Corporate & Governance', color: '#58488A', article_count: 0, subcategories: [] },
]

const risingTopics = ref([
  { rank: 1, name: 'AI Silicon Packaging', velocity: '+148%' },
  { rank: 2, name: 'Autonomous Radars', velocity: '+96%' },
  { rank: 3, name: 'Cross-Border Foundries', velocity: '+71%' },
  { rank: 4, name: 'European Chips Act', velocity: '+58%' },
  { rank: 5, name: 'EDA Synthesis', velocity: '+44%' },
])

const popularCompanies = ref<any[]>([])
const trendingExecutives = ref<any[]>([])
const isLoadingEntities = ref(true)

const toggleExpand = (slug: string) => {
  if (expandedCategory.value === slug) {
    expandedCategory.value = null
  } else {
    expandedCategory.value = slug
    emit('selectCategory', slug)
  }
}

const toggleFollow = (slug: string) => {
  followedEntities.value[slug] = !followedEntities.value[slug]
}

const getMonogram = (name: string) => {
  return name.split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
}

onMounted(async () => {
  try {
    const [catRes, compRes] = await Promise.allSettled([
      categoryService.getCategories(),
      apiClient.get('/companies'),
    ])

    if (catRes.status === 'fulfilled' && catRes.value?.length > 0) {
      const activeCats = catRes.value.filter((c: CategoryItem) => (c.article_count || 0) > 0)
      categories.value = activeCats.length > 0 ? activeCats : defaultCategories
    } else {
      categories.value = defaultCategories
    }

    if (compRes.status === 'fulfilled' && compRes.value?.data?.data?.length > 0) {
      const comps = compRes.value.data.data
      popularCompanies.value = comps.map((c: any) => ({
        id: c.id,
        monogram: c.ticker || getMonogram(c.name),
        name: c.name,
        industry: c.industry || c.sector || 'Enterprise',
        slug: c.slug,
      }))

      // Extract real executives from companies
      const execs: any[] = []
      for (const c of comps) {
        if (c.executives && Array.isArray(c.executives)) {
          for (const ex of c.executives) {
            execs.push({
              id: ex.id,
              monogram: getMonogram(ex.full_name),
              name: ex.full_name,
              role: `${ex.current_title || 'Executive'}, ${c.name}`,
              slug: ex.slug || c.slug,
            })
          }
        }
      }
      if (execs.length > 0) {
        trendingExecutives.value = execs
      }
    }
  } catch {
    categories.value = defaultCategories as any
  } finally {
    isLoadingEntities.value = false
  }
})
</script>

<template>
  <aside class="sticky top-[64px] h-[calc(100vh-64px)] w-[320px] shrink-0 space-y-6 overflow-y-auto border-l border-nexo-border bg-nexo-bg px-5 py-6 text-left">
    <!-- 1. CATEGORIES Accordion (matches 1 News-first homepage — desktop.png) -->
    <section class="space-y-3">
      <p class="section-label">CATEGORIES</p>
      <div class="space-y-1">
        <div
          v-for="cat in (categories.length ? categories : defaultCategories)"
          :key="cat.slug"
          class="border-b border-nexo-border/60 pb-1.5 pt-1"
        >
          <button
            type="button"
            @click="toggleExpand(cat.slug)"
            class="flex w-full items-center justify-between text-xs font-medium text-nexo-text-2 hover:text-nexo-ink"
          >
            <span>{{ cat.name }}</span>
            <component
              :is="expandedCategory === cat.slug ? ChevronUp : ChevronDown"
              class="h-3 w-3 text-nexo-muted"
            />
          </button>

          <!-- Subcategories pills if expanded -->
          <div
            v-if="expandedCategory === cat.slug && cat.subcategories?.length"
            class="mt-2 flex flex-wrap gap-1.5 pb-1"
          >
            <button
              v-for="sub in cat.subcategories"
              :key="typeof sub === 'string' ? sub : (sub as any).slug || (sub as any).name"
              type="button"
              @click="emit('selectCategory', typeof sub === 'string' ? cat.slug : ((sub as any).slug || cat.slug))"
              class="rounded-[2px] border border-nexo-border bg-white px-2 py-0.5 text-[11px] text-nexo-text-2 hover:bg-nexo-paper-2 hover:text-nexo-ink"
            >
              {{ typeof sub === 'string' ? sub : (sub as any).name }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. RISING TOPICS (Ranked with +% velocity) -->
    <section class="space-y-3 border-t border-nexo-border pt-5">
      <p class="section-label">RISING TOPICS</p>
      <div class="space-y-2.5">
        <RouterLink
          v-for="topic in risingTopics"
          :key="topic.rank"
          to="/trends"
          class="flex items-center justify-between text-xs text-nexo-text-2 hover:text-nexo-ink transition-colors"
        >
          <div class="flex items-center gap-2.5">
            <span class="w-3 font-mono text-xs text-nexo-muted">{{ topic.rank }}</span>
            <span class="font-medium text-nexo-ink">{{ topic.name }}</span>
          </div>
          <span class="font-mono text-[11px] font-semibold text-nexo-verified">
            {{ topic.velocity }}
          </span>
        </RouterLink>
      </div>
    </section>

    <!-- 3. POPULAR COMPANIES -->
    <section class="space-y-3 border-t border-nexo-border pt-5">
      <div class="flex items-center justify-between">
        <p class="section-label">POPULAR ENTITIES</p>
        <RouterLink to="/companies" class="text-[11px] text-[#274888] hover:underline font-medium">
          All entities ↗
        </RouterLink>
      </div>

      <div v-if="isLoadingEntities" class="space-y-2">
        <div v-for="i in 3" :key="i" class="h-8 rounded bg-nexo-border/20 animate-pulse"></div>
      </div>

      <div v-else-if="popularCompanies.length === 0" class="text-[11px] text-nexo-muted py-2">
        No monitored companies listed.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="co in popularCompanies"
          :key="co.id"
          class="flex items-center justify-between gap-2"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[2px] bg-nexo-paper-2 font-mono text-[10px] font-bold text-nexo-text-2">
              {{ co.monogram }}
            </span>
            <div class="min-w-0">
              <div class="truncate text-xs font-semibold text-nexo-ink">{{ co.name }}</div>
              <div class="truncate text-[11px] text-nexo-muted">{{ co.industry }}</div>
            </div>
          </div>
          <button
            type="button"
            @click="toggleFollow(co.slug)"
            class="shrink-0 rounded-[4px] border border-nexo-border bg-white px-2.5 py-1 text-[11px] font-semibold text-nexo-ink hover:border-nexo-ink transition"
          >
            {{ followedEntities[co.slug] ? 'Following' : 'Follow' }}
          </button>
        </div>
      </div>
    </section>

    <!-- 4. TRENDING EXECUTIVES -->
    <section class="space-y-3 border-t border-nexo-border pt-5">
      <div class="flex items-center justify-between">
        <p class="section-label">TRENDING EXECUTIVES</p>
        <RouterLink to="/companies" class="text-[11px] text-[#274888] hover:underline font-medium">
          All executives ↗
        </RouterLink>
      </div>

      <div v-if="isLoadingEntities" class="space-y-2">
        <div v-for="i in 2" :key="i" class="h-8 rounded bg-nexo-border/20 animate-pulse"></div>
      </div>

      <div v-else-if="trendingExecutives.length === 0" class="text-[11px] text-nexo-muted py-2">
        Profiles updating across intelligence disclosures.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="exec in trendingExecutives"
          :key="exec.id"
          class="flex items-center justify-between gap-2"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[2px] bg-nexo-paper-2 font-mono text-[10px] font-bold text-nexo-text-2">
              {{ exec.monogram }}
            </span>
            <div class="min-w-0">
              <div class="truncate text-xs font-semibold text-nexo-ink">{{ exec.name }}</div>
              <div class="truncate text-[11px] text-nexo-muted">{{ exec.role }}</div>
            </div>
          </div>
          <button
            type="button"
            @click="toggleFollow(exec.slug)"
            class="shrink-0 rounded-[4px] border border-nexo-border bg-white px-2.5 py-1 text-[11px] font-semibold text-nexo-ink hover:border-nexo-ink transition"
          >
            {{ followedEntities[exec.slug] ? 'Following' : 'Follow' }}
          </button>
        </div>
      </div>
    </section>

    <!-- 5. SPONSORED PLACEMENT (Purple pill token) -->
    <section class="rounded-[2px] border border-nexo-border bg-white p-4">
      <span class="badge-sponsored">SPONSORED</span>
      <h4 class="mt-2 text-xs font-bold leading-snug text-nexo-ink">
        Board-ready reporting for regulated industries
      </h4>
      <p class="mt-1 text-[11px] text-nexo-muted">
        Paid placement by Veridian Analytics.
      </p>
      <a href="#" class="mt-2.5 inline-block text-[11px] font-semibold text-nexo-cobalt hover:underline">
        Learn more
      </a>
    </section>

    <!-- 6. Footer Legal Links -->
    <div class="border-t border-nexo-border pt-4 text-[11px] text-nexo-muted space-x-2">
      <RouterLink to="/methodology" class="hover:underline">Methodology</RouterLink>
      <span>·</span>
      <RouterLink to="/corrections" class="hover:underline">Corrections</RouterLink>
      <span>·</span>
      <RouterLink to="/terms" class="hover:underline">Terms</RouterLink>
      <span>·</span>
      <RouterLink to="/privacy" class="hover:underline">Privacy</RouterLink>
    </div>
  </aside>
</template>