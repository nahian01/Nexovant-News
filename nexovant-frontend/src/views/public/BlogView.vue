<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Newspaper } from 'lucide-vue-next'
import PublicNavbar from '@/components/layout/PublicNavbar.vue'
import NewsletterSubscribe from '@/components/common/NewsletterSubscribe.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
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
  <div class="min-h-screen bg-nexo-bg flex flex-col text-left">
    <PublicNavbar />

    <!-- Main Public Editorial Feed Container -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full space-y-10">
      <!-- Editorial Hero Header -->
      <header class="border-b border-nexo-border/80 pb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-6">
        <div>
          <div class="inline-flex items-center space-x-2 bg-nexo-surface border border-nexo-border px-3 py-1 rounded-full mb-3">
            <span class="w-2 h-2 rounded-full bg-nexo-accent-cyan"></span>
            <span class="text-[10px] font-mono tracking-wider text-nexo-text-secondary uppercase">
              PUBLIC EXECUTIVE INTELLIGENCE & BLOG
            </span>
          </div>
          <h1 class="editorial-title text-3xl sm:text-4xl lg:text-5xl font-medium tracking-tight text-nexo-text-primary leading-tight">
            Global Business & Strategic News
          </h1>
          <p class="mt-2 text-sm sm:text-base text-nexo-text-secondary max-w-2xl leading-relaxed">
            Discover verified corporate disclosures, executive leadership transitions, and capital market movements analyzed across 10 strategic dimensions.
          </p>
        </div>

        <!-- Quick Access CTA -->
        <div class="flex items-center space-x-3">
          <RouterLink to="/register?offer=NEXO-EXECUTIVE-30">
            <Button variant="primary" size="md">
              Claim 30% Executive Offer
            </Button>
          </RouterLink>
          <RouterLink to="/login">
            <Button variant="secondary" size="md">
              Subscriber Sign In
            </Button>
          </RouterLink>
        </div>
      </header>

      <!-- Stay in Tune / Newsletter Capture Section -->
      <NewsletterSubscribe
        source="public_blog_top"
        title="Stay in Tune — Executive Briefings in Your Mailbox"
        description="Subscribe to receive verified intelligence briefs before market open. Subscribers receive an instant 30% discount voucher on annual executive access."
      />

      <!-- Search & Provenance Filters -->
      <section class="bg-nexo-card border border-nexo-border rounded-lg p-5 space-y-4">
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
          <div class="w-full md:w-96">
            <Input
              v-model="searchQuery"
              placeholder="Filter by company, ticker, or keyword..."
              @keyup.enter="fetchArticles"
            />
          </div>

          <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <select
              v-model="selectedCategory"
              @change="fetchArticles"
              class="bg-nexo-surface border border-nexo-border rounded px-3 py-2 text-xs text-nexo-text-primary focus:outline-none focus:border-nexo-accent-cyan"
            >
              <option v-for="cat in categories" :key="cat.slug" :value="cat.slug">
                {{ cat.name }}
              </option>
            </select>

            <select
              v-model="selectedStatus"
              @change="fetchArticles"
              class="bg-nexo-surface border border-nexo-border rounded px-3 py-2 text-xs text-nexo-text-primary focus:outline-none focus:border-nexo-accent-cyan"
            >
              <option v-for="stat in verificationFilters" :key="stat.value" :value="stat.value">
                {{ stat.name }}
              </option>
            </select>

            <Button variant="secondary" size="sm" @click="fetchArticles">
              Refresh Feed
            </Button>
          </div>
        </div>
      </section>

      <!-- Article Stream -->
      <section class="space-y-4">
        <div v-if="isLoading" class="space-y-4">
          <Card v-for="i in 3" :key="i" class="p-6">
            <div class="space-y-3">
              <Skeleton class="h-4 w-1/4" />
              <Skeleton class="h-6 w-3/4" />
              <Skeleton class="h-16 w-full" />
            </div>
          </Card>
        </div>

        <div v-else-if="articles.length === 0" class="text-center py-16 bg-white border border-nexo-border rounded-2xl p-8 shadow-sm">
          <Newspaper class="h-10 w-10 text-nexo-text-muted mx-auto mb-3" />
          <h3 class="text-base font-semibold text-nexo-text-primary">No Public Intelligence Dispatches Found</h3>
          <p class="text-xs text-nexo-text-secondary mt-1">Try clearing your search query or selecting a different category.</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-6">
          <Card
            v-for="item in articles"
            :key="item.id"
            hoverable
            class="p-6 transition-all border border-nexo-border hover:border-nexo-accent-cyan/40"
          >
            <div class="flex flex-col space-y-4">
              <!-- Meta Provenance Row -->
              <div class="flex items-center justify-between flex-wrap gap-2 text-xs font-mono">
                <div class="flex items-center space-x-2">
                  <Badge :variant="item.verification_status" size="sm" />
                  <span class="text-nexo-text-muted">
                    Source: {{ item.source?.name }} (Score: {{ item.source?.credibility_score * 100 }}%)
                  </span>
                </div>
                <span class="text-nexo-text-muted">
                  {{ new Date(item.published_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                </span>
              </div>

              <!-- Title & Excerpt -->
              <div>
                <RouterLink
                  :to="`/blog/${item.slug}`"
                  class="editorial-title text-xl sm:text-2xl font-semibold text-nexo-text-primary hover:text-nexo-accent-cyan transition-colors"
                >
                  {{ item.title }}
                </RouterLink>
                <p class="mt-2 text-sm text-nexo-text-secondary leading-relaxed">
                  {{ item.content_normalized || item.content_raw }}
                </p>
              </div>

              <!-- Entities & Category Footer -->
              <div class="flex items-center justify-between flex-wrap gap-3 pt-2 border-t border-nexo-border/40">
                <div class="flex items-center space-x-2 flex-wrap gap-1.5">
                  <span
                    v-for="comp in item.companies"
                    :key="comp.id"
                    class="bg-nexo-surface border border-nexo-border text-nexo-text-primary text-[11px] px-2.5 py-1 rounded font-mono"
                  >
                    {{ comp.name }} ({{ comp.ticker }})
                  </span>
                  <span
                    v-for="cat in item.categories"
                    :key="cat.id"
                    class="bg-nexo-accent-cyan/10 text-nexo-accent-cyan text-[11px] px-2.5 py-1 rounded font-mono"
                  >
                    {{ cat.name }}
                  </span>
                </div>

                <RouterLink :to="`/blog/${item.slug}`">
                  <Button variant="ghost" size="sm" class="text-nexo-accent-cyan hover:text-nexo-text-primary">
                    Read 10-Dimension Analysis →
                  </Button>
                </RouterLink>
              </div>
            </div>
          </Card>
        </div>
      </section>

      <!-- Bottom Executive Offer Callout -->
      <section class="bg-nexo-surface border border-nexo-accent-gold/40 rounded-lg p-6 lg:p-8 flex flex-col md:flex-row items-center justify-between gap-6 text-left">
        <div class="space-y-1">
          <div class="flex items-center space-x-2">
            <span class="text-xs font-mono font-bold text-nexo-accent-gold uppercase tracking-wider">
              LIMITED TIME EXECUTIVE WELCOME OFFER
            </span>
            <span class="bg-nexo-accent-gold/20 text-nexo-accent-gold px-2 py-0.5 rounded text-[10px] font-mono font-bold">
              30% OFF ANNUAL ACCESS
            </span>
          </div>
          <h3 class="editorial-title text-xl sm:text-2xl font-semibold text-nexo-text-primary">
            Upgrade to Full 10-Dimension Executive Dossiers & Live PDF Briefings
          </h3>
          <p class="text-xs text-nexo-text-secondary max-w-2xl leading-relaxed">
            Gain unlimited access to competitor disruption matrices, cognitive RAG search, social trend radar, and board-ready PDF publications.
          </p>
        </div>

        <RouterLink to="/register?offer=NEXO-EXECUTIVE-30">
          <Button variant="primary" size="lg" class="whitespace-nowrap">
            Claim Offer & Sign Up
          </Button>
        </RouterLink>
      </section>
    </main>

    <!-- Public Footer -->
    <footer class="border-t border-nexo-border/60 bg-nexo-card py-10 px-4 sm:px-6 lg:px-8 text-center sm:text-left">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs font-mono text-nexo-text-muted">
        <div>
          <span class="text-nexo-text-primary font-serif font-bold">NEXOVANT</span> — Global Executive Intelligence SaaS.
        </div>
        <div class="flex items-center space-x-6">
          <RouterLink to="/blog" class="hover:text-nexo-accent-cyan transition-colors">Public Blog & News</RouterLink>
          <RouterLink to="/#pricing" class="hover:text-nexo-accent-cyan transition-colors">Pricing</RouterLink>
          <RouterLink to="/login" class="hover:text-nexo-accent-cyan transition-colors">Sign In</RouterLink>
          <RouterLink to="/register" class="hover:text-nexo-accent-cyan transition-colors">Sign Up</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>
