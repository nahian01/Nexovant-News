<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Building2,
  ChevronDown,
  ChevronUp,
  ExternalLink,
  Layers,
  TrendingUp,
  X,
} from 'lucide-vue-next'
import { categoryService, type CategoryItem } from '@/services/api/categories.service'

const emit = defineEmits<{
  (e: 'selectCategory', slug: string): void
}>()

const categories = ref<CategoryItem[]>([])
const expandedCategory = ref<string | null>(null)
const followedCompanies = ref<Record<string, boolean>>({
  'nvidia-corporation': true,
  'asml-holding': false,
  'novartis-ag': false,
})

const isAdDismissed = ref(false)

const popularCompanies = [
  { id: '01a1d9b5-424d-7169-a042-caf3d84c8d58', name: 'NVIDIA Corporation', ticker: 'NVDA', slug: 'nvidia-corporation' },
  { id: 'asml-corp', name: 'ASML Holding NV', ticker: 'ASML', slug: 'asml-holding' },
  { id: 'novartis-corp', name: 'Novartis AG', ticker: 'NOVN', slug: 'novartis-ag' },
]

const risingTopics = [
  { tag: '#SiliconSovereignty', count: '14.2k mentions', velocity: '+88%' },
  { tag: '#EUChipsAct', count: '8.9k mentions', velocity: '+62%' },
  { tag: '#HighNALithography', count: '5.1k mentions', velocity: '+45%' },
  { tag: '#RadioligandTherapy', count: '3.4k mentions', velocity: '+28%' },
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
  followedCompanies.value[slug] = !followedCompanies.value[slug]
}

onMounted(async () => {
  try {
    categories.value = await categoryService.getCategories()
  } catch {
    //
  }
})
</script>

<template>
  <aside class="sticky top-[68px] h-[calc(100vh-68px)] w-80 shrink-0 space-y-6 overflow-y-auto border-l border-nexo-border/80 bg-white/70 px-4 py-5 text-left backdrop-blur-sm xl:w-84">
    <!-- 1. Categories & Dynamic Subcategories Section -->
    <section class="space-y-3">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-2">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <Layers class="h-3.5 w-3.5 text-nexo-accent-cyan" />
          Intelligence Categories
        </span>
        <button
          type="button"
          @click="emit('selectCategory', '')"
          class="text-[11px] font-medium text-nexo-accent-cyan transition hover:text-nexo-accent-cyan-hover hover:underline"
        >
          View all
        </button>
      </div>

      <div class="space-y-1">
        <div
          v-for="cat in categories"
          :key="cat.id"
          class="rounded-xl border border-transparent transition-all"
        >
          <button
            type="button"
            @click="toggleExpand(cat.slug)"
            class="group flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-xs transition-colors hover:bg-nexo-elevated/70"
          >
            <div class="flex items-center gap-2.5">
              <span class="h-2 w-2 rounded-full ring-2 ring-white" :style="{ backgroundColor: cat.color || '#147A67' }"></span>
              <span class="font-medium text-nexo-text-primary transition group-hover:text-nexo-accent-cyan">{{ cat.name }}</span>
            </div>

            <div class="flex items-center gap-2">
              <span v-if="cat.article_count > 0" class="rounded-md bg-nexo-bg px-1.5 py-0.5 font-mono text-[10px] text-nexo-text-muted">
                {{ cat.article_count }}
              </span>
              <component
                :is="expandedCategory === cat.slug ? ChevronUp : ChevronDown"
                class="h-3.5 w-3.5 text-nexo-text-muted transition-transform"
              />
            </div>
          </button>

          <!-- Expandable Subcategories -->
          <div
            v-if="expandedCategory === cat.slug"
            class="my-1 space-y-1 rounded-xl bg-nexo-bg/60 py-1.5 pl-6 pr-2"
          >
            <button
              v-for="(sub, i) in cat.subcategories"
              :key="i"
              type="button"
              @click="emit('selectCategory', cat.slug)"
              class="flex w-full items-center gap-2 py-1 text-left text-xs text-nexo-text-secondary transition hover:text-nexo-accent-cyan"
            >
              <span class="h-1 w-1 rounded-full bg-nexo-accent-cyan/60 shrink-0"></span>
              <span>{{ sub }}</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. Rising Topics Section -->
    <section class="space-y-3 border-t border-nexo-border/80 pt-4">
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <TrendingUp class="h-3.5 w-3.5 text-nexo-accent-cyan" />
          Rising Topics & Velocity
        </span>
        <RouterLink to="/trends" class="text-[11px] font-medium text-nexo-accent-cyan transition hover:underline">
          Radar →
        </RouterLink>
      </div>

      <div class="space-y-2">
        <RouterLink
          v-for="(t, idx) in risingTopics"
          :key="idx"
          to="/trends"
          class="flex items-center justify-between rounded-xl border border-nexo-border/70 bg-white p-2.5 text-xs transition hover:border-nexo-accent-cyan/40 hover:shadow-sm"
        >
          <div>
            <div class="font-medium text-nexo-text-primary">{{ t.tag }}</div>
            <div class="mt-0.5 text-[10px] text-nexo-text-muted">{{ t.count }}</div>
          </div>
          <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-700 border border-emerald-200">
            {{ t.velocity }}
          </span>
        </RouterLink>
      </div>
    </section>

    <!-- 3. Popular Companies Section -->
    <section class="space-y-3 border-t border-nexo-border/80 pt-4">
      <div class="flex items-center justify-between">
        <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-nexo-text-muted">
          <Building2 class="h-3.5 w-3.5 text-nexo-accent-cyan" />
          Monitored Companies
        </span>
        <RouterLink to="/companies" class="text-[11px] font-medium text-nexo-accent-cyan transition hover:underline">
          All (500+) →
        </RouterLink>
      </div>

      <div class="space-y-2">
        <div
          v-for="c in popularCompanies"
          :key="c.id"
          class="flex items-center justify-between rounded-xl border border-nexo-border/70 bg-white p-2.5 text-xs transition hover:border-nexo-accent-cyan/30"
        >
          <div class="min-w-0 pr-2">
            <div class="truncate font-medium text-nexo-text-primary">{{ c.name }}</div>
            <div class="font-mono text-[10px] text-nexo-text-muted">{{ c.ticker }}</div>
          </div>

          <button
            type="button"
            @click="toggleFollow(c.slug)"
            :class="[
              'shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold transition-all',
              followedCompanies[c.slug]
                ? 'border border-nexo-border bg-nexo-bg text-nexo-text-muted hover:border-rose-200 hover:text-rose-600'
                : 'bg-nexo-accent-cyan text-white hover:bg-nexo-accent-cyan-hover shadow-sm'
            ]"
          >
            {{ followedCompanies[c.slug] ? 'Following' : '+ Follow' }}
          </button>
        </div>
      </div>
    </section>

    <!-- 4. Clearly Labelled Sponsored Placement (PART 11 & 25) -->
    <section
      v-if="!isAdDismissed"
      class="relative overflow-hidden rounded-2xl border border-amber-300/80 bg-gradient-to-br from-amber-50/50 via-white to-amber-50/30 p-4 text-xs shadow-sm"
    >
      <div class="flex items-center justify-between">
        <span class="rounded-full border border-amber-200 bg-amber-100/60 px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest text-nexo-accent-gold">
          Sponsored Brief
        </span>
        <button
          type="button"
          @click="isAdDismissed = true"
          class="rounded p-1 text-nexo-text-muted transition hover:bg-white hover:text-nexo-text-primary"
          title="Dismiss sponsored content"
          aria-label="Dismiss"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </div>

      <div class="editorial-title mt-2.5 text-sm font-semibold leading-snug text-nexo-text-primary">
        Advancing Sovereign European Lithography Capacity
      </div>

      <p class="mt-1.5 text-[11px] leading-relaxed text-nexo-text-secondary">
        Download the official ASML technical roadmap on High-NA 0.55 numerical aperture scanner deployment for 2nm fabrication nodes.
      </p>

      <a
        href="https://asml.com"
        target="_blank"
        rel="noopener noreferrer"
        class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-nexo-accent-gold transition hover:text-amber-800 hover:underline"
      >
        <span>Access Technical Roadmap</span>
        <ExternalLink class="h-3 w-3" />
      </a>
    </section>
  </aside>
</template>