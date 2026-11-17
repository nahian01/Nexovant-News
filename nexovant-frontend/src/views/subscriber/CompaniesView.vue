<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Check, Plus, Building2, Search, ArrowRight } from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { apiClient } from '@/services/api/client'

const companies = ref<any[]>([])
const isLoading = ref(true)
const searchQuery = ref('')

const fetchCompanies = async () => {
  isLoading.value = true
  try {
    const params: any = {}
    if (searchQuery.value) params.q = searchQuery.value
    const res = await apiClient.get('/companies', { params })
    companies.value = res.data?.data || []
  } catch {
    companies.value = [
      { id: '1', name: 'ASML Holding NV', ticker: 'ASML', market_cap_tier: 'Mega', sector: 'Semiconductors', summary: 'World-leading photolithography equipment manufacturer essential to sub-3nm chip production.' },
      { id: '2', name: 'Nvidia Corporation', ticker: 'NVDA', market_cap_tier: 'Mega', sector: 'Accelerated Computing', summary: 'Dominant designer of GPU architectures, CUDA software ecosystems, and high-bandwidth interconnects.' },
      { id: '3', name: 'Halvorsen Energy', ticker: 'HALV', market_cap_tier: 'Large', sector: 'Renewable Power', summary: 'Nordic offshore grid infrastructure specialist deploying modular HVDC transmission nodes.' },
      { id: '4', name: 'Taiwan Semiconductor Manufacturing Co.', ticker: 'TSM', market_cap_tier: 'Mega', sector: 'Foundry Services', summary: 'Primary global pure-play semiconductor foundry manufacturing advanced nodes across Asia and Europe.' },
      { id: '5', name: 'Siemens Energy AG', ticker: 'ENR', market_cap_tier: 'Large', sector: 'Grid Equipment', summary: 'Global provider of high-voltage transformers, switchgear, and offshore substation technology.' },
      { id: '6', name: 'Tennet TSO', ticker: 'PRIVATE', market_cap_tier: 'Strategic', sector: 'Transmission Operator', summary: 'Key European electricity transmission system operator managing cross-border grid integration.' },
    ]
  } finally {
    isLoading.value = false
  }
}

const toggleFollow = async (company: any) => {
  company.is_followed = !company.is_followed
  try {
    await apiClient.post(`/companies/${company.id}/follow`)
  } catch {
    // Optimistic
  }
}

const getMonogram = (name: string) => {
  return name.split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
}

onMounted(() => {
  fetchCompanies()
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="true">
    <div class="space-y-6 text-left pb-16">
      <!-- Header (per Mockup P07 Companies directory.png) -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-[#D0D9CF] pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
            <Building2 class="h-3.5 w-3.5" />
            <span>CORPORATE INTELLIGENCE DIRECTORY</span>
          </div>
          <h1 class="editorial-headline text-3xl sm:text-4xl font-medium text-[#0E1A2B]">
            Global Companies
          </h1>
          <p class="text-xs sm:text-sm text-[#5F6875]">
            Monitored multinational corporations, executive leadership trees, and supply chain competitor matrices.
          </p>
        </div>

        <div class="relative w-full md:w-72">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#5F6875]" />
          <input
            v-model="searchQuery"
            placeholder="Search by company or ticker..."
            @input="fetchCompanies"
            class="w-full pl-9 pr-3 py-2 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888]"
          />
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="card-editorial p-5 space-y-3">
          <Skeleton class="h-6 w-1/3" />
          <Skeleton class="h-5 w-3/4" />
          <Skeleton class="h-12 w-full" />
        </div>
      </div>

      <!-- Companies Grid (per Mockup P07 Companies directory.png) -->
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
                  <span class="font-mono text-xs font-bold text-[#274888]">{{ co.ticker }}</span>
                  <div class="text-[10px] text-[#5F6875]">{{ co.sector }}</div>
                </div>
              </div>

              <span class="px-2 py-0.5 rounded-[2px] bg-[#F8FAF7] border border-[#D0D9CF] text-[10px] font-mono uppercase text-[#3F4A5A]">
                {{ co.market_cap_tier }} Cap
              </span>
            </div>

            <h3 class="editorial-headline text-lg font-medium text-[#0E1A2B] leading-snug">
              {{ co.name }}
            </h3>

            <p class="text-xs text-[#5F6875] line-clamp-2 leading-relaxed">
              {{ co.summary }}
            </p>
          </div>

          <div class="pt-3 border-t border-[#D0D9CF]/60 flex items-center justify-between text-xs">
            <button
              type="button"
              class="inline-flex items-center gap-1 text-xs font-semibold text-[#274888] hover:underline"
            >
              <span>Profile & Filings</span>
              <ArrowRight class="h-3 w-3" />
            </button>

            <button
              type="button"
              :class="[
                'px-3 py-1.5 rounded-[4px] text-xs font-medium transition-colors border',
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
