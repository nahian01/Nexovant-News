<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { apiClient } from '@/services/api/client'

const articles = ref<any[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const selectedCategory = ref('')
const selectedStatus = ref('')

const categories = [
  { name: 'All Categories', slug: '' },
  { name: 'Expansion & Capex', slug: 'expansion-capex' },
  { name: 'Leadership Transitions', slug: 'leadership-transitions' },
  { name: 'Financial Performance', slug: 'financial-performance' },
  { name: 'Emerging Risks', slug: 'emerging-risks' },
]

const verificationFilters = [
  { name: 'All Provenance', value: '' },
  { name: 'Confirmed', value: 'confirmed' },
  { name: 'Reported (Reliable)', value: 'reported_reliable' },
  { name: 'Developing', value: 'developing' },
  { name: 'Disputed', value: 'disputed' },
]

const fetchArticles = async () => {
  isLoading.value = true
  try {
    const params: any = {}
    if (searchQuery.value) params.q = searchQuery.value
    if (selectedCategory.value) params.category = selectedCategory.value
    if (selectedStatus.value) params.verification_status = selectedStatus.value

    const res = await apiClient.get('/articles', { params })
    articles.value = res.data.data
  } catch {
    // Graceful fallback
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchArticles()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <!-- Title & Search Bar -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              Global Intelligence Feed
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Real-time corporate developments filtered by verified provenance and business significance.
            </p>
          </div>

          <div class="w-full md:w-72">
            <Input
              v-model="searchQuery"
              placeholder="Search entities, events, keywords..."
              @input="fetchArticles"
            />
          </div>
        </div>

        <!-- Filter Pills Bar -->
        <div class="flex flex-wrap items-center gap-2 pb-2">
          <!-- Categories -->
          <button
            v-for="cat in categories"
            :key="cat.slug"
            :class="[
              'px-3 py-1.5 rounded-full text-xs font-mono transition-all',
              selectedCategory === cat.slug
                ? 'bg-nexo-accent-cyan text-nexo-bg font-semibold'
                : 'bg-nexo-surface text-nexo-text-secondary border border-nexo-border hover:text-nexo-text-primary'
            ]"
            @click="selectedCategory = cat.slug; fetchArticles()"
          >
            {{ cat.name }}
          </button>

          <span class="text-nexo-text-muted px-1">|</span>

          <!-- Verification Status -->
          <button
            v-for="st in verificationFilters"
            :key="st.value"
            :class="[
              'px-3 py-1.5 rounded-full text-xs font-mono transition-all',
              selectedStatus === st.value
                ? 'bg-nexo-elevated text-nexo-accent-cyan border border-nexo-accent-cyan/60 font-semibold'
                : 'bg-nexo-surface text-nexo-text-secondary border border-nexo-border hover:text-nexo-text-primary'
            ]"
            @click="selectedStatus = st.value; fetchArticles()"
          >
            {{ st.name }}
          </button>
        </div>

        <!-- Feed List -->
        <div class="space-y-4">
          <Card
            v-for="art in articles"
            :key="art.id"
            padding="md"
            class="hover:border-nexo-accent-cyan/40"
          >
            <div class="flex flex-col space-y-3">
              <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
                <div class="flex items-center space-x-2">
                  <Badge :variant="art.verification_status" size="sm" />
                  <span
                    v-for="cat in art.categories"
                    :key="cat.id"
                    class="font-mono text-nexo-text-secondary"
                  >
                    {{ cat.name }}
                  </span>
                </div>
                <span class="font-mono text-nexo-text-muted">
                  Score: {{ art.importance_score }} / 10
                </span>
              </div>

              <RouterLink :to="`/news/${art.slug}`">
                <h3 class="font-serif text-lg font-semibold text-nexo-text-primary leading-snug hover:text-nexo-accent-cyan transition-colors">
                  {{ art.title }}
                </h3>
              </RouterLink>

              <div class="flex items-center space-x-2 text-xs text-nexo-text-secondary">
                <span class="text-nexo-text-muted font-mono uppercase text-[10px]">Source:</span>
                <span>{{ art.source?.name }}</span>
                <span class="text-nexo-text-muted">|</span>
                <span class="text-nexo-accent-cyan font-mono text-[11px]">Audited Provenance</span>
              </div>
            </div>
          </Card>

          <div v-if="articles.length === 0 && !isLoading" class="p-12 text-center text-nexo-text-secondary text-sm">
            No intelligence developments matching your active filter criteria.
          </div>
        </div>
      </main>
    </div>
  </div>
</template>
