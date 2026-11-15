<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Building2,
  Check,
  Clock,
  Compass,
  Cpu,
  ExternalLink,
  Hash,
  Layers,
  Plus,
  Search,
  Sparkles,
  Trash2,
  UserCheck,
  Users,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { workspaceService } from '@/services/api/workspace.service'

// View mode: 'entities' or 'stream'
const activeMainTab = ref<'entities' | 'stream'>('entities')
const selectedType = ref<string>('all')
const searchQuery = ref<string>('')

// Following state
const followingItems = ref<any[]>([])
const suggestions = ref<{
  companies: any[]
  executives: any[]
  technologies: any[]
  topics: any[]
}>({
  companies: [],
  executives: [],
  technologies: [],
  topics: [],
})

// Activity stream state
const streamItems = ref<any[]>([])
const isLoading = ref(true)
const isStreamLoading = ref(false)
const isToggling = ref<Record<string, boolean>>({})

const typeFilters = [
  { value: 'all', label: 'All Entities' },
  { value: 'company', label: 'Companies' },
  { value: 'executive', label: 'Executives' },
  { value: 'technology', label: 'Technologies' },
  { value: 'topic', label: 'Topics & Sectors' },
]

const loadFollowingData = async () => {
  isLoading.value = true
  try {
    const [followingRes, suggestionsRes] = await Promise.all([
      workspaceService.getFollowing(),
      workspaceService.getFollowingSuggestions(),
    ])

    followingItems.value = followingRes?.data || followingRes?.following || []
    suggestions.value = suggestionsRes?.data || suggestionsRes?.suggestions || {
      companies: [],
      executives: [],
      technologies: [],
      topics: [],
    }
  } catch {
    // Fallback if needed
    followingItems.value = []
  } finally {
    isLoading.value = false
  }
}

const loadActivityStream = async () => {
  isStreamLoading.value = true
  try {
    const res = await workspaceService.getFollowingFeed()
    streamItems.value = res?.data || []
  } catch {
    streamItems.value = []
  } finally {
    isStreamLoading.value = false
  }
}

const handleTabChange = (tab: 'entities' | 'stream') => {
  activeMainTab.value = tab
  if (tab === 'stream' && streamItems.value.length === 0) {
    loadActivityStream()
  }
}

const filteredFollowing = computed(() => {
  let list = followingItems.value

  if (selectedType.value !== 'all') {
    list = list.filter((it) => it.type === selectedType.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(
      (it) =>
        (it.name || '').toLowerCase().includes(q) ||
        (it.ticker || '').toLowerCase().includes(q) ||
        (it.title || '').toLowerCase().includes(q) ||
        (it.category || '').toLowerCase().includes(q)
    )
  }

  return list
})

const handleToggleFollow = async (type: string, id: string, name: string) => {
  const key = `${type}:${id}`
  isToggling.value[key] = true
  try {
    const res = await workspaceService.toggleFollow(type, id, name)
    // Reload full list to sync
    await loadFollowingData()
    if (activeMainTab.value === 'stream') {
      await loadActivityStream()
    }
  } catch {
    //
  } finally {
    isToggling.value[key] = false
  }
}

const getEntityIcon = (type: string) => {
  switch (type) {
    case 'company':
      return Building2
    case 'executive':
      return Users
    case 'technology':
      return Cpu
    default:
      return Hash
  }
}

const getEntityBadgeColor = (type: string) => {
  switch (type) {
    case 'company':
      return 'bg-blue-50 text-blue-700 border-blue-200'
    case 'executive':
      return 'bg-purple-50 text-purple-700 border-purple-200'
    case 'technology':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    default:
      return 'bg-amber-50 text-amber-700 border-amber-200'
  }
}

onMounted(() => {
  loadFollowingData()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="mx-auto max-w-5xl space-y-7 text-left">
      <!-- 1. Header & Main Tabs -->
      <header class="border-b border-nexo-border/80 pb-5">
        <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-nexo-accent-cyan">
          <UserCheck class="h-3.5 w-3.5" />
          <span>Entity Surveillance Desk</span>
        </div>
        <h1 class="mt-2 font-serif text-3xl font-medium tracking-tight text-nexo-text-primary sm:text-4xl">
          Following & Tracked Entities
        </h1>
        <p class="mt-2 text-sm text-nexo-text-secondary">
          Monitor targeted companies, executives, technologies, and sectors across the global intelligence graph.
        </p>

        <!-- Main Tab Switcher: Entities vs Activity Stream -->
        <div class="mt-6 flex border-b border-nexo-border">
          <button
            type="button"
            @click="handleTabChange('entities')"
            :class="[
              'border-b-2 px-5 py-2.5 text-xs font-semibold transition',
              activeMainTab === 'entities'
                ? 'border-nexo-accent-cyan text-nexo-accent-cyan'
                : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
            ]"
          >
            Tracked Entities ({{ followingItems.length }})
          </button>
          <button
            type="button"
            @click="handleTabChange('stream')"
            :class="[
              'border-b-2 px-5 py-2.5 text-xs font-semibold transition',
              activeMainTab === 'stream'
                ? 'border-nexo-accent-cyan text-nexo-accent-cyan'
                : 'border-transparent text-nexo-text-secondary hover:text-nexo-text-primary'
            ]"
          >
            Followed Activity Stream
          </button>
        </div>
      </header>

      <!-- TAB 1: Tracked Entities Management -->
      <section v-if="activeMainTab === 'entities'" class="space-y-6">
        <!-- Controls: Search & Type Filter Pills -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <!-- Search -->
          <div class="relative flex-1 sm:max-w-xs">
            <Search class="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-nexo-text-muted" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search followed entities..."
              class="w-full rounded-xl border border-nexo-border bg-white py-1.5 pl-8 pr-3 text-xs text-nexo-text-primary placeholder-nexo-text-muted transition focus:border-nexo-accent-cyan focus:outline-none"
            />
          </div>

          <!-- Entity Type Pills -->
          <div class="flex flex-wrap items-center gap-1.5">
            <button
              v-for="flt in typeFilters"
              :key="flt.value"
              type="button"
              @click="selectedType = flt.value"
              :class="[
                'rounded-lg px-2.5 py-1 text-xs font-medium transition',
                selectedType === flt.value
                  ? 'bg-nexo-accent-cyan text-white shadow-2xs'
                  : 'border border-nexo-border bg-white text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
              ]"
            >
              {{ flt.label }}
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Card v-for="i in 6" :key="i" padding="md">
            <div class="space-y-2.5">
              <Skeleton class="h-4 w-24" />
              <Skeleton class="h-6 w-3/4" />
              <Skeleton class="h-4 w-1/2" />
            </div>
          </Card>
        </div>

        <!-- Empty Followed State -->
        <div
          v-else-if="filteredFollowing.length === 0"
          class="rounded-2xl border border-dashed border-nexo-border bg-white p-8 text-center"
        >
          <Users class="mx-auto h-8 w-8 text-nexo-text-muted" />
          <h3 class="mt-2 font-serif text-base font-medium text-nexo-text-primary">
            No entities followed in this view
          </h3>
          <p class="mx-auto mt-1 max-w-sm text-xs text-nexo-text-secondary">
            Follow suggested companies and emerging technologies below to personalize your intelligence feed.
          </p>
        </div>

        <!-- Followed Entities Grid -->
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="item in filteredFollowing"
            :key="`${item.type}:${item.id}`"
            padding="md"
            class="group border-nexo-border/80 transition hover:border-nexo-accent-cyan/35 hover:shadow-xs"
          >
            <div class="flex items-start justify-between gap-2">
              <span
                :class="[
                  'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold capitalize',
                  getEntityBadgeColor(item.type)
                ]"
              >
                <component :is="getEntityIcon(item.type)" class="h-3 w-3" />
                <span>{{ item.type }}</span>
              </span>

              <button
                type="button"
                @click="handleToggleFollow(item.type, item.id, item.name)"
                :disabled="isToggling[`${item.type}:${item.id}`]"
                class="rounded-lg p-1 text-nexo-text-muted transition hover:bg-rose-50 hover:text-rose-600"
                title="Unfollow entity"
                aria-label="Unfollow entity"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>

            <div class="mt-3">
              <div class="flex items-center gap-2">
                <h3 class="truncate font-serif text-base font-medium text-nexo-text-primary">
                  {{ item.name }}
                </h3>
                <span
                  v-if="item.ticker"
                  class="rounded bg-nexo-bg px-1.5 py-0.5 font-mono text-[10px] font-semibold text-nexo-text-secondary"
                >
                  {{ item.ticker }}
                </span>
              </div>
              <p v-if="item.title || item.category" class="mt-0.5 truncate text-xs text-nexo-text-muted">
                {{ item.title || item.category }}
              </p>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-nexo-border/60 pt-3 text-[11px] text-nexo-text-muted">
              <span>{{ item.stories_count || 0 }} intelligence stories</span>
              <RouterLink
                :to="`/search?q=${encodeURIComponent(item.name)}`"
                class="inline-flex items-center gap-1 text-nexo-accent-cyan transition hover:underline"
              >
                <span>Dossier</span>
                <ExternalLink class="h-3 w-3" />
              </RouterLink>
            </div>
          </Card>
        </div>

        <!-- 2. Recommended to Follow (Suggestions) Section -->
        <section class="mt-10 border-t border-nexo-border/80 pt-8">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-nexo-accent-gold">
                Recommended to Follow
              </p>
              <h2 class="mt-1 font-serif text-xl font-medium text-nexo-text-primary">
                Explore Suggested Intelligence Targets
              </h2>
            </div>
          </div>

          <!-- Suggested Categories Tabs/Sections -->
          <div class="mt-5 space-y-6">
            <!-- Suggested Companies -->
            <div v-if="suggestions.companies?.length">
              <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold text-nexo-text-secondary">
                <Building2 class="h-3.5 w-3.5 text-nexo-accent-gold" />
                <span>Companies in Global Focus</span>
              </h3>
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                  v-for="comp in suggestions.companies"
                  :key="comp.id || comp.slug"
                  class="flex items-center justify-between rounded-xl border border-nexo-border bg-white p-3 shadow-2xs"
                >
                  <div class="min-w-0 pr-2">
                    <p class="truncate text-xs font-semibold text-nexo-text-primary">{{ comp.name }}</p>
                    <p class="text-[10px] text-nexo-text-muted">{{ comp.ticker || 'Enterprise' }}</p>
                  </div>
                  <button
                    type="button"
                    @click="handleToggleFollow('company', comp.id || comp.slug, comp.name)"
                    class="flex shrink-0 items-center gap-1 rounded-lg border border-nexo-border bg-nexo-bg px-2 py-1 text-[11px] font-medium text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary"
                  >
                    <Plus class="h-3 w-3" />
                    <span>Follow</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Emerging Technologies -->
            <div v-if="suggestions.technologies?.length">
              <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold text-nexo-text-secondary">
                <Cpu class="h-3.5 w-3.5 text-nexo-accent-cyan" />
                <span>Emerging Technologies</span>
              </h3>
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                  v-for="tech in suggestions.technologies"
                  :key="tech.slug || tech.name"
                  class="flex items-center justify-between rounded-xl border border-nexo-border bg-white p-3 shadow-2xs"
                >
                  <div class="min-w-0 pr-2">
                    <p class="truncate text-xs font-semibold text-nexo-text-primary">{{ tech.name }}</p>
                    <p class="text-[10px] text-nexo-text-muted">{{ tech.category || 'Strategic Tech' }}</p>
                  </div>
                  <button
                    type="button"
                    @click="handleToggleFollow('technology', tech.slug, tech.name)"
                    class="flex shrink-0 items-center gap-1 rounded-lg border border-nexo-border bg-nexo-bg px-2 py-1 text-[11px] font-medium text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary"
                  >
                    <Plus class="h-3 w-3" />
                    <span>Follow</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Key Executives -->
            <div v-if="suggestions.executives?.length">
              <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold text-nexo-text-secondary">
                <Users class="h-3.5 w-3.5 text-purple-600" />
                <span>Executive Leaders</span>
              </h3>
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                  v-for="exec in suggestions.executives"
                  :key="exec.id || exec.name"
                  class="flex items-center justify-between rounded-xl border border-nexo-border bg-white p-3 shadow-2xs"
                >
                  <div class="min-w-0 pr-2">
                    <p class="truncate text-xs font-semibold text-nexo-text-primary">{{ exec.name }}</p>
                    <p class="truncate text-[10px] text-nexo-text-muted">{{ exec.company?.name || exec.title || 'Leader' }}</p>
                  </div>
                  <button
                    type="button"
                    @click="handleToggleFollow('executive', exec.id, exec.name)"
                    class="flex shrink-0 items-center gap-1 rounded-lg border border-nexo-border bg-nexo-bg px-2 py-1 text-[11px] font-medium text-nexo-text-secondary transition hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary"
                  >
                    <Plus class="h-3 w-3" />
                    <span>Follow</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </section>

      <!-- TAB 2: Followed Activity Stream -->
      <section v-else-if="activeMainTab === 'stream'" class="space-y-4">
        <!-- Stream Loading -->
        <div v-if="isStreamLoading" class="space-y-4">
          <Card v-for="i in 3" :key="i" padding="lg">
            <Skeleton class="h-6 w-3/4 mb-2" />
            <Skeleton class="h-12 w-full" />
          </Card>
        </div>

        <!-- Empty Stream State -->
        <div
          v-else-if="streamItems.length === 0"
          class="rounded-2xl border border-dashed border-nexo-border bg-white p-12 text-center"
        >
          <Sparkles class="mx-auto h-10 w-10 text-nexo-text-muted" />
          <h3 class="mt-3 font-serif text-lg font-medium text-nexo-text-primary">
            No recent activity for followed entities
          </h3>
          <p class="mx-auto mt-1.5 max-w-sm text-xs leading-relaxed text-nexo-text-secondary">
            Follow more companies or technologies to populate your dedicated activity stream.
          </p>
          <div class="mt-4">
            <Button variant="primary" size="sm" @click="activeMainTab = 'entities'">
              Browse Entities to Follow
            </Button>
          </div>
        </div>

        <!-- Stream Cards -->
        <div v-else class="space-y-4">
          <Card
            v-for="item in streamItems"
            :key="item.id"
            padding="lg"
            class="group border-nexo-border/80 transition hover:border-nexo-accent-cyan/35 hover:shadow-xs"
          >
            <div class="flex items-center justify-between gap-2 border-b border-nexo-border/60 pb-3">
              <div class="flex items-center gap-2">
                <Badge :variant="item.verification_status === 'verified' ? 'confirmed' : 'reported_reliable'" size="sm">
                  {{ item.verification_status || 'verified' }}
                </Badge>
                <span class="rounded bg-nexo-bg px-2 py-0.5 text-[11px] font-semibold text-nexo-text-secondary">
                  {{ typeof item.category === 'object' ? item.category?.name : item.category || 'Strategic Intelligence' }}
                </span>
              </div>
              <span class="text-[11px] text-nexo-text-muted">{{ item.published_at ? new Date(item.published_at).toLocaleDateString() : 'Recently' }}</span>
            </div>

            <div class="mt-3">
              <RouterLink
                :to="`/news/${item.slug || item.id}`"
                class="font-serif text-xl font-medium leading-snug text-nexo-text-primary transition group-hover:text-nexo-accent-cyan"
              >
                {{ item.headline || item.title }}
              </RouterLink>
              <p class="mt-1.5 text-xs leading-relaxed text-nexo-text-secondary sm:text-[13px]">
                {{ item.summary }}
              </p>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-nexo-border/60 pt-3">
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="ent in (item.entities || []).slice(0, 3)"
                  :key="typeof ent === 'string' ? ent : ent.name"
                  class="rounded-lg border border-nexo-border bg-nexo-bg px-2 py-0.5 text-[10px] font-medium text-nexo-text-secondary"
                >
                  {{ typeof ent === 'string' ? ent : ent.name }}
                </span>
              </div>
              <RouterLink
                :to="`/news/${item.slug || item.id}`"
                class="inline-flex items-center gap-1 text-xs font-semibold text-nexo-accent-cyan transition hover:underline"
              >
                <span>Read Dossier</span>
                <ExternalLink class="h-3 w-3" />
              </RouterLink>
            </div>
          </Card>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
