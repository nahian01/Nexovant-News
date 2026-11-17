<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ChevronDown,
  ChevronUp,
} from 'lucide-vue-next'
import { categoryService, type CategoryItem } from '@/services/api/categories.service'

const emit = defineEmits<{
  (e: 'selectCategory', slug: string): void
}>()

const categories = ref<CategoryItem[]>([])
const expandedCategory = ref<string | null>('leadership')
const followedEntities = ref<Record<string, boolean>>({
  'halvorsen-energy': false,
  'arden-logistics': false,
  'orbis-health': false,
  'pelham-motors': false,
  'dana-whitcombe': false,
  'mara-okonkwo': false,
  'tomas-aldridge': false,
})

const defaultCategories = [
  { id: '1', name: 'Business', slug: 'business' },
  { id: '2', name: 'Finance', slug: 'finance' },
  { id: '3', name: 'Technology', slug: 'technology' },
  {
    id: '4',
    name: 'Leadership',
    slug: 'leadership',
    subcategories: ['All leadership', 'CEO changes', 'Boards', 'Succession']
  },
  { id: '5', name: 'Workforce', slug: 'workforce' },
  { id: '6', name: 'Healthcare', slug: 'healthcare' },
  { id: '7', name: 'Energy', slug: 'energy' },
  { id: '8', name: 'Markets', slug: 'markets' },
]

const risingTopics = [
  { rank: 1, name: 'Succession planning', velocity: '+148%' },
  { rank: 2, name: 'Grid storage', velocity: '+96%' },
  { rank: 3, name: 'Board independence', velocity: '+71%' },
  { rank: 4, name: 'Freight consolidation', velocity: '+58%' },
  { rank: 5, name: 'Device guidance', velocity: '+44%' },
]

const popularCompanies = [
  { id: 'he', monogram: 'HE', name: 'Halvorsen Energy', industry: 'Energy', slug: 'halvorsen-energy' },
  { id: 'al', monogram: 'AL', name: 'Arden Logistics', industry: 'Transport and logistics', slug: 'arden-logistics' },
  { id: 'oh', monogram: 'OH', name: 'Orbis Health', industry: 'Healthcare devices', slug: 'orbis-health' },
  { id: 'pm', monogram: 'PM', name: 'Pelham Motors', industry: 'Automotive', slug: 'pelham-motors' },
]

const trendingExecutives = [
  { id: 'dw', monogram: 'DW', name: 'Dana Whitcombe', role: 'CEO, Halvorsen Energy', slug: 'dana-whitcombe' },
  { id: 'mo', monogram: 'MO', name: 'Mara Okonkwo', role: 'CEO, Arden Logistics', slug: 'mara-okonkwo' },
  { id: 'ta', monogram: 'TA', name: 'Tomás Aldridge', role: 'Chair, Veldt Foods', slug: 'tomas-aldridge' },
]

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

onMounted(async () => {
  try {
    const res = await categoryService.getCategories()
    if (res && res.length > 0) {
      categories.value = res
    } else {
      categories.value = defaultCategories as any
    }
  } catch {
    categories.value = defaultCategories as any
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
              :key="sub"
              type="button"
              @click="emit('selectCategory', cat.slug)"
              class="rounded-[2px] border border-nexo-border bg-white px-2 py-0.5 text-[11px] text-nexo-text-2 hover:bg-nexo-paper-2 hover:text-nexo-ink"
            >
              {{ sub }}
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
      <p class="section-label">POPULAR COMPANIES</p>
      <div class="space-y-3">
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
      <p class="section-label">TRENDING EXECUTIVES</p>
      <div class="space-y-3">
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