<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Check,
  Plus,
  Building2,
  Search,
  ArrowRight,
  Globe2,
  MapPin,
  Tag,
  Filter,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { apiClient } from '@/services/api/client'

const companies = ref<any[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const selectedContinent = ref('')
const selectedIndustry = ref('')

const continents = ['All Continents', 'Asia', 'North America', 'Europe']

const fetchCompanies = async () => {
  isLoading.value = true
  try {
    const params: any = {}
    if (searchQuery.value) params.q = searchQuery.value
    if (selectedContinent.value && selectedContinent.value !== 'All Continents') {
      params.continent = selectedContinent.value
    }
    if (selectedIndustry.value) params.industry = selectedIndustry.value

    const res = await apiClient.get('/companies', { params })
    companies.value = res.data?.data || []
  } catch {
    companies.value = []
  } finally {
    isLoading.value = false
  }
}

const toggleFollow = async (company: any) => {
  company.is_followed = !company.is_followed
  try {
    await apiClient.post(`/companies/${company.id}/follow`)
  } catch {
    // Optimistic fallback
  }
}

const getMonogram = (name: string) => {
  return name.split(' ').map((w: string) => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
}

onMounted(() => {
  fetchCompanies()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
            <Building2 class="h-3.5 w-3.5" />
            <span>CORPORATE INTELLIGENCE & GEO-TAXONOMY DIRECTORY</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Global Companies
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875] max-w-2xl">
            Monitored multinational corporations and regional enterprises categorized by Continent, Country, Administrative Hubs (Divisions, Districts, Upazilas), and Strategic Industry Sectors.
          </p>
        </div>

        <div class="relative w-full md:w-80">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#5F6875]" />
          <input
            v-model="searchQuery"
            placeholder="Search company, ticker, upazila, city..."
            @input="fetchCompanies"
            class="w-full pl-9 pr-3 py-2 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888]"
          />
        </div>
      </div>

      <!-- Continent Filter Pills -->
      <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button
          v-for="c in continents"
          :key="c"
          type="button"
          @click="selectedContinent = c === 'All Continents' ? '' : c; fetchCompanies()"
          :class="[
            'px-3.5 py-1.5 rounded-full text-xs font-medium transition-colors cursor-pointer',
            (c === 'All Continents' && !selectedContinent) || selectedContinent === c
              ? 'bg-[#0E1A2B] text-white shadow-xs'
              : 'border border-[#D0D9CF] bg-white text-[#3F4A5A] hover:bg-[#EEEBE4]'
          ]"
        >
          {{ c }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="card-editorial p-5 space-y-3">
          <Skeleton class="h-6 w-1/3" />
          <Skeleton class="h-5 w-3/4" />
          <Skeleton class="h-12 w-full" />
        </div>
      </div>

      <!-- Companies Grid -->
      <div v-else-if="companies.length === 0" class="card-editorial p-12 text-center text-[#5F6875] text-xs">
        No companies matching your search or geographic filter criteria.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="co in companies"
          :key="co.id"
          class="card-editorial p-5 flex flex-col justify-between space-y-4 hover:border-[#274888]/40 transition-colors"
        >
          <div class="space-y-3">
            <div class="flex items-start justify-between">
              <!-- Company Monogram & Ticker -->
              <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-[2px] bg-[#EEEBE4] border border-[#D0D9CF] text-[#0E1A2B] font-serif font-bold text-xs flex items-center justify-center">
                  {{ getMonogram(co.name) }}
                </span>
                <div>
                  <div class="flex items-center gap-1.5">
                    <span v-if="co.ticker" class="font-mono text-xs font-bold text-[#274888]">{{ co.ticker }}</span>
                    <span class="text-[10px] font-mono uppercase text-[#147A67] bg-[#EBF5EE] px-1.5 py-0.2 rounded-[2px]">
                      {{ co.industry || co.sector || 'Enterprise' }}
                    </span>
                  </div>
                  <div class="text-[10px] text-[#5F6875] flex items-center gap-1 mt-0.5">
                    <Globe2 class="w-3 h-3 text-[#5F6875]" />
                    <span>{{ co.country || co.headquarters_country }} ({{ co.continent }})</span>
                  </div>
                </div>
              </div>

              <span class="px-2 py-0.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] text-[10px] font-mono uppercase text-[#3F4A5A]">
                {{ co.market_cap_tier }} Cap
              </span>
            </div>

            <h3 class="editorial-headline text-lg font-medium text-[#0E1A2B] leading-snug">
              {{ co.name }}
            </h3>

            <!-- Geo Administrative Jurisdiction Tags -->
            <div class="flex flex-wrap items-center gap-1 text-[10px] font-mono text-[#5F6875]">
              <span v-if="co.admin_level_1 || co.division || co.state" class="px-1.5 py-0.5 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[2px]">
                {{ co.admin_labels?.level_1 || 'L1' }}: <strong class="text-[#274888]">{{ co.admin_level_1 || co.division || co.state }}</strong>
              </span>
              <span v-if="co.admin_level_2 || co.district || co.county" class="px-1.5 py-0.5 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[2px]">
                {{ co.admin_labels?.level_2 || 'L2' }}: <strong class="text-[#147A67]">{{ co.admin_level_2 || co.district || co.county }}</strong>
              </span>
              <span v-if="co.admin_level_3 || co.upazila || co.city" class="px-1.5 py-0.5 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[2px]">
                {{ co.admin_labels?.level_3 || 'L3' }}: <strong class="text-[#8A5A08]">{{ co.admin_level_3 || co.upazila || co.city }}</strong>
              </span>
            </div>

            <p class="text-xs text-[#5F6875] line-clamp-2 leading-relaxed">
              {{ co.summary }}
            </p>
          </div>

          <div class="pt-3 border-t border-[#D0D9CF]/60 flex items-center justify-between text-xs">
            <span class="text-[11px] font-mono text-[#5F6875]">
              {{ co.employee_count ? `${co.employee_count.toLocaleString()} employees` : 'Enterprise Entity' }}
            </span>

            <button
              type="button"
              :class="[
                'px-3 py-1.5 rounded-[4px] text-xs font-medium transition-colors border cursor-pointer',
                co.is_followed
                  ? 'border-[#17794D]/30 bg-[#E3F1EA] text-[#17794D]'
                  : 'border-[#D0D9CF] bg-white text-[#0E1A2B] hover:bg-[#EEEBE4]'
              ]"
              @click="toggleFollow(co)"
            >
              <component :is="co.is_followed ? Check : Plus" class="h-3 w-3 mr-1 inline" />
              <span>{{ co.is_followed ? 'Monitored' : '+ Follow' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
