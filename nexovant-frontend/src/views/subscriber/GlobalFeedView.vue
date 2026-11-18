<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Globe2, Search, ShieldCheck } from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
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
  { name: 'Reported Reliable', value: 'reported_reliable' },
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
    articles.value = res.data?.data || []
  } catch {
    articles.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchArticles()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Title & Search Bar -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
            <Globe2 class="h-3.5 w-3.5" />
            <span>GLOBAL INTELLIGENCE NETWORK</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Global Intelligence Feed
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Real-time developments filtered by verified provenance, entity relationships, and strategic impact.
          </p>
        </div>

        <div class="relative w-full md:w-72">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#5F6875]" />
          <input
            v-model="searchQuery"
            placeholder="Search entities, events..."
            @input="fetchArticles"
            class="w-full pl-9 pr-3 py-2 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888]"
          />
        </div>
      </div>

      <!-- Filter Pills Bar -->
      <div class="flex flex-wrap items-center gap-1.5 pb-1 text-xs">
        <button
          v-for="cat in categories"
          :key="cat.slug"
          :class="[
            'px-3.5 py-1.5 rounded-full text-xs font-medium transition-colors',
            selectedCategory === cat.slug
              ? 'bg-[#0E1A2B] text-white shadow-xs'
              : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
          ]"
          @click="selectedCategory = cat.slug; fetchArticles()"
        >
          {{ cat.name }}
        </button>

        <span class="text-[#D0D9CF] px-1">|</span>

        <button
          v-for="st in verificationFilters"
          :key="st.value"
          :class="[
            'px-3.5 py-1.5 rounded-full text-xs font-medium transition-colors',
            selectedStatus === st.value
              ? 'bg-[#274888] text-white shadow-xs'
              : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4] hover:text-[#0E1A2B]'
          ]"
          @click="selectedStatus = st.value; fetchArticles()"
        >
          {{ st.name }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="card-editorial p-6 space-y-3">
          <Skeleton class="h-4 w-32" />
          <Skeleton class="h-6 w-3/4" />
          <Skeleton class="h-12 w-full" />
        </div>
      </div>

      <!-- Feed List -->
      <div v-else-if="articles.length === 0" class="card-editorial p-12 text-center text-[#5F6875] text-xs">
        No intelligence developments matching your active filter criteria.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="art in articles"
          :key="art.id"
          class="card-editorial p-5 sm:p-6 space-y-3 hover:border-[#274888]/40 transition-colors group"
        >
          <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
            <div class="flex items-center space-x-2">
              <span
                class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase tracking-wider"
                :class="art.verification_status === 'confirmed' || art.verification_status === 'verified' ? 'badge-verified' : 'badge-developing'"
              >
                {{ (art.verification_status || 'verified').replace('_', ' ') }}
              </span>
              <span
                v-for="cat in art.categories"
                :key="cat.id"
                class="font-mono text-[10px] uppercase text-[#5F6875] bg-[#F8FAF7] border border-[#D0D9CF] px-2 py-0.5 rounded-[2px]"
              >
                {{ cat.name }}
              </span>
            </div>
            <span class="font-mono text-[11px] text-[#274888] font-semibold">
              Importance: {{ art.importance_score || 8 }} / 10
            </span>
          </div>

          <RouterLink :to="`/news/${art.slug}`">
            <h3 class="editorial-headline text-xl font-medium text-[#0E1A2B] leading-snug group-hover:text-[#274888] transition-colors">
              {{ art.title }}
            </h3>
          </RouterLink>

          <!-- Remote Thumbnail & Credit (Zero local storage) -->
          <div v-if="art.featured_image || art.primary_image" class="relative overflow-hidden rounded-[3px] border border-[#D0D9CF] max-h-48 bg-[#0E1A2B]/5">
            <img
              :src="art.featured_image || art.primary_image"
              :alt="art.title"
              class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-[1.01]"
              loading="lazy"
              @error="(e: any) => e.target.parentElement.style.display = 'none'"
            />
            <div class="absolute bottom-2 left-2 bg-black/75 backdrop-blur-xs text-white text-[10px] font-mono px-2 py-0.5 rounded-[2px]">
              {{ art.featured_image_caption || (art.source?.name ? `Source: ${art.source.name}` : 'Source: Wire Service') }}
            </div>
          </div>

          <p v-if="art.summary" class="text-xs sm:text-[13px] text-[#3F4A5A] leading-relaxed">
            {{ typeof art.summary === 'object' ? art.summary.executive_summary : art.summary }}
          </p>

          <div class="flex items-center justify-between text-xs text-[#5F6875] pt-2 border-t border-[#D0D9CF]/60">
            <div class="flex items-center space-x-2">
              <span class="font-mono uppercase text-[10px] text-[#5F6875]">Source:</span>
              <span class="font-semibold text-[#0E1A2B]">{{ art.source?.name || 'Primary Wire' }}</span>
              <span>•</span>
              <span class="text-[#17794D] font-medium flex items-center gap-1">
                <ShieldCheck class="w-3.5 h-3.5" />
                Audited Provenance
              </span>
            </div>

            <RouterLink :to="`/news/${art.slug}`" class="text-[#274888] font-semibold hover:underline">
              Read story &rarr;
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
