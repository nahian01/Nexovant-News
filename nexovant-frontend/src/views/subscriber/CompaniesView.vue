<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import {
  Building2,
  Globe2,
  MapPin,
  Search,
  Sparkles,
  TrendingUp,
  Check,
  Plus,
  X,
  ExternalLink,
  Users,
  Calendar,
  Briefcase,
  SlidersHorizontal,
  RotateCcw,
  ChevronRight,
  ShieldCheck,
  Layers,
  Award,
} from 'lucide-vue-next'
import AuthenticatedLayout from '@/components/layout/AuthenticatedLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { companiesService, type Company, type CompanyFilters } from '@/services/api/companies.service'
import { userService } from '@/services/api/user.service'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// State
const companies = ref<Company[]>([])
const isLoading = ref(true)
const isTaxonomyLoading = ref(true)
const selectedCompany = ref<Company | null>(null)
const isDetailDrawerOpen = ref(false)

// Tabs
const activeTab = ref<'for_you' | 'all' | 'global_giants' | 'national_champions' | 'watchlist'>('for_you')

// Filters
const searchQuery = ref('')
const selectedContinent = ref('')
const selectedCountry = ref('')
const selectedDivision = ref('')
const selectedDistrict = ref('')
const selectedUpazila = ref('')
const selectedIndustry = ref('')
const selectedMarketCapTier = ref('')

// Pagination
const currentPage = ref(1)
const lastPage = ref(1)
const totalCompanies = ref(0)
const perPage = ref(24)

// Taxonomy dynamic options from backend
const taxonomyData = ref<{
  continents: string[]
  countries: Array<{ country: string; continent: string; headquarters_country: string; count: number }>
  divisions: Array<{ name: string; type: string; country: string; continent: string; count: number }>
  districts: Array<{ name: string; type: string; country: string; count: number }>
  upazilas: Array<{ name: string; type: string; country: string; count: number }>
  industries: Array<{ name: string; sector: string; count: number }>
  sectors: Array<{ name: string; count: number }>
  market_cap_tiers: Array<{ tier: string; count: number }>
  user?: {
    is_authenticated: boolean
    followed_company_ids: string[]
    preferred_industries: string[]
  }
}>({
  continents: [],
  countries: [],
  divisions: [],
  districts: [],
  upazilas: [],
  industries: [],
  sectors: [],
  market_cap_tiers: [],
})

// Quick focus industry tags for developer/tech personalization
const quickFocusTags = [
  'Accelerated Computing & AI Silicon',
  'Cloud Infrastructure & Enterprise AI',
  'Developer Collaboration & Agile Management',
  'Semiconductor Microprocessors & Foundry Services',
  'Artificial Intelligence & Search Infrastructure',
  'Pure-Play Pure Semiconductor Foundry',
  'Open Source AI & Social Platforms',
  'Digital Neobanking & FinTech Platforms',
  'Consumer Electronics & Devices',
  'Biotechnology & Healthcare',
]

const activePersonalizationTags = ref<string[]>([])

// Cascading country options based on continent
const availableCountries = computed(() => {
  if (!selectedContinent.value || selectedContinent.value === 'All Continents') {
    return taxonomyData.value.countries
  }
  return taxonomyData.value.countries.filter((c) => c.continent === selectedContinent.value)
})

// Cascading divisions based on country
const availableDivisions = computed(() => {
  if (!selectedCountry.value || selectedCountry.value === 'All Countries') {
    if (selectedContinent.value && selectedContinent.value !== 'All Continents') {
      return taxonomyData.value.divisions.filter((d) => d.continent === selectedContinent.value)
    }
    return taxonomyData.value.divisions
  }
  return taxonomyData.value.divisions.filter((d) => d.country === selectedCountry.value)
})

// Cascading districts based on country
const availableDistricts = computed(() => {
  if (!selectedCountry.value || selectedCountry.value === 'All Countries') {
    return taxonomyData.value.districts
  }
  return taxonomyData.value.districts.filter((d) => d.country === selectedCountry.value)
})

// Cascading upazilas / cities based on country
const availableUpazilas = computed(() => {
  if (!selectedCountry.value || selectedCountry.value === 'All Countries') {
    return taxonomyData.value.upazilas
  }
  return taxonomyData.value.upazilas.filter((u) => u.country === selectedCountry.value)
})

// Active filters count
const activeFiltersCount = computed(() => {
  let count = 0
  if (selectedContinent.value && selectedContinent.value !== 'All Continents') count++
  if (selectedCountry.value && selectedCountry.value !== 'All Countries') count++
  if (selectedDivision.value && selectedDivision.value !== 'All Divisions') count++
  if (selectedDistrict.value && selectedDistrict.value !== 'All Districts') count++
  if (selectedUpazila.value && selectedUpazila.value !== 'All Upazilas') count++
  if (selectedIndustry.value && selectedIndustry.value !== 'All Industries') count++
  if (selectedMarketCapTier.value && selectedMarketCapTier.value !== 'all') count++
  if (searchQuery.value.trim()) count++
  return count
})

// Fetch Taxonomy Data
const fetchTaxonomy = async () => {
  isTaxonomyLoading.value = true
  try {
    const res = await companiesService.getTaxonomy()
    if (res.success && res.data) {
      taxonomyData.value = res.data
      if (res.data.user?.preferred_industries?.length) {
        activePersonalizationTags.value = [...res.data.user.preferred_industries]
      } else {
        // Default developer profile
        activePersonalizationTags.value = [
          'Accelerated Computing & AI Silicon',
          'Cloud Infrastructure & Enterprise AI',
          'Developer Collaboration & Agile Management',
        ]
      }
    }
  } catch {
    //
  } finally {
    isTaxonomyLoading.value = false
  }
}

// Fetch Companies
let searchTimeout: any = null
const debounceFetchCompanies = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchCompanies()
  }, 300)
}

const fetchCompanies = async () => {
  isLoading.value = true
  try {
    const params: CompanyFilters = {
      tab: activeTab.value,
      page: currentPage.value,
      per_page: perPage.value,
    }

    if (searchQuery.value.trim()) params.q = searchQuery.value.trim()
    if (selectedContinent.value && selectedContinent.value !== 'All Continents') {
      params.continent = selectedContinent.value
    }
    if (selectedCountry.value && selectedCountry.value !== 'All Countries') {
      params.country = selectedCountry.value
    }
    if (selectedDivision.value && selectedDivision.value !== 'All Divisions') {
      params.division = selectedDivision.value
    }
    if (selectedDistrict.value && selectedDistrict.value !== 'All Districts') {
      params.district = selectedDistrict.value
    }
    if (selectedUpazila.value && selectedUpazila.value !== 'All Upazilas') {
      params.upazila = selectedUpazila.value
    }
    if (selectedIndustry.value && selectedIndustry.value !== 'All Industries') {
      params.industry = selectedIndustry.value
    }
    if (selectedMarketCapTier.value && selectedMarketCapTier.value !== 'all') {
      params.market_cap_tier = selectedMarketCapTier.value
    }

    const res = await companiesService.getSubscriberCompanies(params)
    if (res.success) {
      companies.value = res.data || []
      currentPage.value = res.meta?.current_page || 1
      lastPage.value = res.meta?.last_page || 1
      totalCompanies.value = res.meta?.total || 0
    }
  } catch {
    companies.value = []
  } finally {
    isLoading.value = false
  }
}

// Reset Filters
const resetFilters = () => {
  selectedContinent.value = ''
  selectedCountry.value = ''
  selectedDivision.value = ''
  selectedDistrict.value = ''
  selectedUpazila.value = ''
  selectedIndustry.value = ''
  selectedMarketCapTier.value = ''
  searchQuery.value = ''
  currentPage.value = 1
  fetchCompanies()
}

// Toggle Quick Personalization Focus Tag
const togglePersonalizationTag = async (tag: string) => {
  const index = activePersonalizationTags.value.indexOf(tag)
  if (index > -1) {
    activePersonalizationTags.value.splice(index, 1)
  } else {
    activePersonalizationTags.value.push(tag)
  }

  // Update in backend user preferences if logged in
  if (authStore.isAuthenticated) {
    try {
      await userService.updatePreferences({
        industries: activePersonalizationTags.value,
      })
    } catch {
      //
    }
  }

  if (activeTab.value === 'for_you') {
    fetchCompanies()
  }
}

// Toggle Watchlist Follow
const toggleFollow = async (company: Company) => {
  const previousState = company.is_followed
  company.is_followed = !previousState

  try {
    const res = await companiesService.toggleFollow(company.id)
    if (res && typeof res.is_followed === 'boolean') {
      company.is_followed = res.is_followed
    }
  } catch {
    company.is_followed = previousState
  }
}

// Open Detail Drawer
const openCompanyDetail = (company: Company) => {
  selectedCompany.value = company
  isDetailDrawerOpen.value = true
}

const closeCompanyDetail = () => {
  isDetailDrawerOpen.value = false
  selectedCompany.value = null
}

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isDetailDrawerOpen.value) {
    closeCompanyDetail()
  }
}

const getMonogram = (name: string) => {
  return name
    .split(' ')
    .map((w: string) => w[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

// Change Tab
const setTab = (tab: 'for_you' | 'all' | 'global_giants' | 'national_champions' | 'watchlist') => {
  activeTab.value = tab
  currentPage.value = 1
  fetchCompanies()
}

// Watch Continent selection to reset cascading children
watch(selectedContinent, () => {
  selectedCountry.value = ''
  selectedDivision.value = ''
  selectedDistrict.value = ''
  selectedUpazila.value = ''
  currentPage.value = 1
  fetchCompanies()
})

// Watch Country selection to reset cascading children
watch(selectedCountry, () => {
  selectedDivision.value = ''
  selectedDistrict.value = ''
  selectedUpazila.value = ''
  currentPage.value = 1
  fetchCompanies()
})

onMounted(async () => {
  window.addEventListener('keydown', handleKeydown)
  await fetchTaxonomy()
  await fetchCompanies()
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <AuthenticatedLayout :show-right-panel="false">
    <div class="space-y-6 text-left pb-24 max-w-[1600px] mx-auto w-full">
      <!-- Top Intelligence Header -->
      <div class="border-b border-[#D0D9CF] pb-5 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-1.5 min-w-0">
            <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
              <Building2 class="h-4 w-4 shrink-0" />
              <span class="truncate">GLOBAL CORPORATE DIRECTORY & GEO-TAXONOMY RADAR</span>
            </div>
            <h1 class="editorial-headline text-2xl sm:text-3xl lg:text-4xl font-medium text-[#0E1A2B]">
              Enterprise Intelligence Directory
            </h1>
            <p class="text-xs sm:text-sm text-[#5F6875] max-w-3xl leading-relaxed">
              Monitored multinational market leaders and national enterprise champions across all 6 continents. Filter by Continents, Countries, Administrative Jurisdictions (Divisions/States, Districts, Upazilas/Cities), and Strategic Industry Sectors.
            </p>
          </div>

          <!-- Live Search Bar -->
          <div class="relative w-full lg:w-96 shrink-0">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#5F6875]" />
            <input
              v-model="searchQuery"
              placeholder="Search by company, ticker, city, upazila, industry..."
              @input="debounceFetchCompanies"
              class="w-full pl-10 pr-9 py-2.5 text-xs rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] placeholder-[#5F6875] focus:outline-none focus:border-[#274888] shadow-xs"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''; fetchCompanies()"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5F6875] hover:text-[#0E1A2B] cursor-pointer"
            >
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Mode & Tab Navigation: Scrollable strip on mobile, wraps cleanly on desktop -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
          <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar border border-[#D0D9CF] bg-[#F8FAF7] p-1 rounded-[6px] max-w-full">
            <button
              type="button"
              @click="setTab('for_you')"
              :class="[
                'px-3 sm:px-3.5 py-1.5 rounded-[4px] text-xs font-medium transition-all flex items-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer',
                activeTab === 'for_you'
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-white'
              ]"
            >
              <Sparkles class="w-3.5 h-3.5 text-amber-400" />
              <span>For You (Personalized)</span>
            </button>

            <button
              type="button"
              @click="setTab('all')"
              :class="[
                'px-3 sm:px-3.5 py-1.5 rounded-[4px] text-xs font-medium transition-all flex items-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer',
                activeTab === 'all'
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-white'
              ]"
            >
              <Globe2 class="w-3.5 h-3.5" />
              <span>All Companies</span>
            </button>

            <button
              type="button"
              @click="setTab('global_giants')"
              :class="[
                'px-3 sm:px-3.5 py-1.5 rounded-[4px] text-xs font-medium transition-all flex items-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer',
                activeTab === 'global_giants'
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-white'
              ]"
            >
              <Award class="w-3.5 h-3.5 text-indigo-600" />
              <span>Global Giants (Mega Cap)</span>
            </button>

            <button
              type="button"
              @click="setTab('national_champions')"
              :class="[
                'px-3 sm:px-3.5 py-1.5 rounded-[4px] text-xs font-medium transition-all flex items-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer',
                activeTab === 'national_champions'
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-white'
              ]"
            >
              <MapPin class="w-3.5 h-3.5 text-emerald-600" />
              <span>National Champions</span>
            </button>

            <button
              type="button"
              @click="setTab('watchlist')"
              :class="[
                'px-3 sm:px-3.5 py-1.5 rounded-[4px] text-xs font-medium transition-all flex items-center gap-1.5 shrink-0 whitespace-nowrap cursor-pointer',
                activeTab === 'watchlist'
                  ? 'bg-[#0E1A2B] text-white shadow-xs'
                  : 'text-[#3F4A5A] hover:text-[#0E1A2B] hover:bg-white'
              ]"
            >
              <ShieldCheck class="w-3.5 h-3.5 text-[#274888]" />
              <span>Monitored Watchlist</span>
            </button>
          </div>

          <!-- Total Count Indicator -->
          <div class="flex items-center gap-2 text-xs font-mono text-[#5F6875] shrink-0">
            <span class="font-bold text-[#0E1A2B]">{{ totalCompanies }}</span>
            <span>entities indexed</span>
            <span v-if="activeFiltersCount > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[11px]">
              {{ activeFiltersCount }} active
            </span>
          </div>
        </div>

        <!-- Quick Focus Specialties Personalization Bar (For You Tab) -->
        <div v-if="activeTab === 'for_you'" class="bg-gradient-to-r from-[#F0F4F8] to-[#F8FAF7] border border-[#D0D9CF] p-3 sm:p-4 rounded-[6px] space-y-2.5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <span class="text-[11px] font-mono uppercase tracking-wider text-[#274888] flex items-center gap-1.5 font-bold">
              <Sparkles class="w-3.5 h-3.5 text-amber-500 shrink-0" />
              <span>YOUR FOCUS SPECIALTIES & PERSONALIZATION TAGS:</span>
            </span>
            <span class="text-[10px] text-[#5F6875] font-mono">Click tags to tailor your focus</span>
          </div>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="tag in quickFocusTags"
              :key="tag"
              type="button"
              @click="togglePersonalizationTag(tag)"
              :class="[
                'text-[11px] px-2.5 py-1 rounded-[3px] border transition-colors cursor-pointer flex items-center gap-1 font-medium',
                activePersonalizationTags.includes(tag)
                  ? 'bg-[#274888] text-white border-[#274888]'
                  : 'bg-white text-[#3F4A5A] border-[#D0D9CF] hover:border-[#274888]'
              ]"
            >
              <span>{{ tag }}</span>
              <Check v-if="activePersonalizationTags.includes(tag)" class="w-3 h-3 shrink-0" />
            </button>
          </div>
        </div>
      </div>

      <!-- Multi-Level Geographic & Industry Cascading Filter Bar -->
      <div class="bg-white border border-[#D0D9CF] p-4 sm:p-5 rounded-[6px] shadow-xs space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-[#D0D9CF]/60 text-xs">
          <div class="flex items-center gap-1.5 font-mono text-[#0E1A2B] uppercase tracking-wider text-[11px] font-bold">
            <SlidersHorizontal class="w-3.5 h-3.5 text-[#274888]" />
            <span>Multi-Level Geographic & Industry Taxonomy Filters</span>
          </div>
          <button
            v-if="activeFiltersCount > 0"
            type="button"
            @click="resetFilters"
            class="text-[11px] font-medium text-[#274888] hover:underline flex items-center gap-1 cursor-pointer"
          >
            <RotateCcw class="w-3 h-3" />
            <span>Reset All Filters</span>
          </button>
        </div>

        <!-- 6 Dropdowns: 1 col on mobile, 2 on tablet, 3 on laptop, 6 on wide desktop -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 text-xs">
          <!-- 1. Continent Filter -->
          <div class="min-w-0">
            <label class="block text-[10px] font-mono uppercase text-[#5F6875] mb-1 font-semibold truncate">
              Continent
            </label>
            <select
              v-model="selectedContinent"
              class="w-full truncate px-2.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] text-xs focus:outline-none focus:border-[#274888] shadow-2xs"
            >
              <option value="">All Continents (6)</option>
              <option v-for="c in taxonomyData.continents" :key="c" :value="c">
                {{ c }}
              </option>
            </select>
          </div>

          <!-- 2. Country Filter (Cascading) -->
          <div class="min-w-0">
            <label class="block text-[10px] font-mono uppercase text-[#5F6875] mb-1 font-semibold truncate">
              Country
            </label>
            <select
              v-model="selectedCountry"
              class="w-full truncate px-2.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] text-xs focus:outline-none focus:border-[#274888] shadow-2xs"
            >
              <option value="">All Countries</option>
              <option v-for="country in availableCountries" :key="country.country" :value="country.country">
                {{ country.country }} ({{ country.count }})
              </option>
            </select>
          </div>

          <!-- 3. Division / State / Province (Cascading) -->
          <div class="min-w-0">
            <label class="block text-[10px] font-mono uppercase text-[#5F6875] mb-1 font-semibold truncate">
              State / Division
            </label>
            <select
              v-model="selectedDivision"
              @change="currentPage = 1; fetchCompanies()"
              class="w-full truncate px-2.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] text-xs focus:outline-none focus:border-[#274888] shadow-2xs"
            >
              <option value="">All Divisions/States</option>
              <option v-for="div in availableDivisions" :key="div.name" :value="div.name">
                {{ div.name }} ({{ div.country }})
              </option>
            </select>
          </div>

          <!-- 4. District / County (Cascading) -->
          <div class="min-w-0">
            <label class="block text-[10px] font-mono uppercase text-[#5F6875] mb-1 font-semibold truncate">
              District / County
            </label>
            <select
              v-model="selectedDistrict"
              @change="currentPage = 1; fetchCompanies()"
              class="w-full truncate px-2.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] text-xs focus:outline-none focus:border-[#274888] shadow-2xs"
            >
              <option value="">All Districts/Counties</option>
              <option v-for="dist in availableDistricts" :key="dist.name" :value="dist.name">
                {{ dist.name }}
              </option>
            </select>
          </div>

          <!-- 5. Industry / Specialty -->
          <div class="min-w-0">
            <label class="block text-[10px] font-mono uppercase text-[#5F6875] mb-1 font-semibold truncate">
              Industry Sector
            </label>
            <select
              v-model="selectedIndustry"
              @change="currentPage = 1; fetchCompanies()"
              class="w-full truncate px-2.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] text-xs focus:outline-none focus:border-[#274888] shadow-2xs"
            >
              <option value="">All Industries</option>
              <option v-for="ind in taxonomyData.industries" :key="ind.name" :value="ind.name">
                {{ ind.name }} ({{ ind.count }})
              </option>
            </select>
          </div>

          <!-- 6. Market Cap Tier -->
          <div class="min-w-0">
            <label class="block text-[10px] font-mono uppercase text-[#5F6875] mb-1 font-semibold truncate">
              Market Cap Tier
            </label>
            <select
              v-model="selectedMarketCapTier"
              @change="currentPage = 1; fetchCompanies()"
              class="w-full truncate px-2.5 py-2 rounded-[4px] border border-[#D0D9CF] bg-white text-[#0E1A2B] text-xs focus:outline-none focus:border-[#274888] shadow-2xs"
            >
              <option value="">All Tiers</option>
              <option value="mega">Mega Cap ($200B+)</option>
              <option value="large">Large Cap ($10B-$200B)</option>
              <option value="mid">Mid Cap ($2B-$10B)</option>
            </select>
          </div>
        </div>

        <!-- Active Filter Badges Bar -->
        <div v-if="activeFiltersCount > 0" class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-[#D0D9CF]/40 text-xs">
          <span class="text-[11px] font-mono text-[#5F6875] font-semibold">Active:</span>

          <span v-if="selectedContinent" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-900 text-[11px] border border-blue-200">
            Continent: {{ selectedContinent }}
            <button @click="selectedContinent = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <span v-if="selectedCountry" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-900 text-[11px] border border-blue-200">
            Country: {{ selectedCountry }}
            <button @click="selectedCountry = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <span v-if="selectedDivision" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-900 text-[11px] border border-blue-200">
            State/Division: {{ selectedDivision }}
            <button @click="selectedDivision = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <span v-if="selectedDistrict" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-900 text-[11px] border border-blue-200">
            District: {{ selectedDistrict }}
            <button @click="selectedDistrict = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <span v-if="selectedIndustry" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-900 text-[11px] border border-emerald-200">
            Industry: {{ selectedIndustry }}
            <button @click="selectedIndustry = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <span v-if="selectedMarketCapTier" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-900 text-[11px] border border-amber-200">
            Tier: {{ selectedMarketCapTier }}
            <button @click="selectedMarketCapTier = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <span v-if="searchQuery" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-[11px] border border-gray-300">
            "{{ searchQuery }}"
            <button @click="searchQuery = ''; fetchCompanies()" class="hover:text-black cursor-pointer"><X class="w-3 h-3" /></button>
          </span>

          <button @click="resetFilters" class="text-[11px] text-[#274888] hover:underline font-medium ml-1 cursor-pointer">
            Clear all
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
        <div v-for="i in 8" :key="i" class="card-editorial p-5 space-y-4 rounded-[6px] border border-[#D0D9CF] bg-white">
          <Skeleton class="h-6 w-1/3" />
          <Skeleton class="h-6 w-3/4" />
          <Skeleton class="h-14 w-full" />
          <div class="flex justify-between pt-2">
            <Skeleton class="h-4 w-1/4" />
            <Skeleton class="h-8 w-24" />
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="companies.length === 0" class="card-editorial p-12 text-center text-[#5F6875] space-y-3 rounded-[6px] border border-[#D0D9CF] bg-white">
        <Building2 class="w-10 h-10 mx-auto text-[#5F6875]/40" />
        <h3 class="text-base font-semibold text-[#0E1A2B]">No companies matching your criteria</h3>
        <p class="text-xs max-w-md mx-auto leading-relaxed">
          No corporate entities were found with the current combination of location, industry, or search parameters. Try clearing some filters.
        </p>
        <button
          type="button"
          @click="resetFilters"
          class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0E1A2B] text-white text-xs font-medium rounded-[4px] hover:bg-[#274888] cursor-pointer"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          <span>Reset All Filters</span>
        </button>
      </div>

      <!-- Companies Responsive Grid: 1 col on mobile, 2 on tablet, 3 on laptop, 4 on wide desktop -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4 gap-4 sm:gap-5">
        <div
          v-for="co in companies"
          :key="co.id"
          class="card-editorial p-4 sm:p-5 flex flex-col justify-between space-y-3.5 hover:border-[#274888]/60 transition-all hover:shadow-md group bg-white rounded-[6px] border border-[#D0D9CF] min-w-0"
        >
          <div class="space-y-3 min-w-0">
            <!-- Header Row: Monogram, Ticker, Industry, Market Cap -->
            <div class="flex items-start justify-between gap-2.5">
              <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <span class="w-10 h-10 rounded-[3px] bg-[#EEEBE4] border border-[#D0D9CF] text-[#0E1A2B] font-serif font-bold text-sm flex items-center justify-center shrink-0 group-hover:border-[#274888] group-hover:bg-[#EBF5EE] transition-colors">
                  {{ getMonogram(co.name) }}
                </span>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span v-if="co.ticker" class="font-mono text-xs font-bold text-[#274888] bg-[#F0F4F8] px-1.5 py-0.5 rounded-[2px] border border-[#D0D9CF]/60 shrink-0">
                      {{ co.ticker }}
                    </span>
                    <span class="text-[10px] font-mono uppercase font-semibold text-[#147A67] bg-[#EBF5EE] px-1.5 py-0.5 rounded-[2px] truncate max-w-[140px] sm:max-w-[170px]" :title="co.industry">
                      {{ co.industry || co.sector || 'Enterprise' }}
                    </span>
                  </div>
                  <div class="text-[11px] text-[#5F6875] flex items-center gap-1 mt-1 font-medium truncate">
                    <Globe2 class="w-3 h-3 text-[#5F6875] shrink-0" />
                    <span class="truncate">{{ co.country || co.headquarters_country }} ({{ co.continent }})</span>
                  </div>
                </div>
              </div>

              <!-- Market Cap Tier Badge -->
              <span
                :class="[
                  'px-2 py-0.5 rounded-[2px] text-[10px] font-mono uppercase font-bold shrink-0 border whitespace-nowrap',
                  co.market_cap_tier === 'mega'
                    ? 'bg-amber-50 text-amber-900 border-amber-200'
                    : co.market_cap_tier === 'large'
                    ? 'bg-blue-50 text-blue-900 border-blue-200'
                    : 'bg-[#F8FAF7] text-[#3F4A5A] border-[#D0D9CF]'
                ]"
              >
                {{ co.market_cap_tier || 'Corporate' }} Cap
              </span>
            </div>

            <!-- Company Name -->
            <h3
              @click="openCompanyDetail(co)"
              class="editorial-headline text-base sm:text-lg font-medium text-[#0E1A2B] leading-snug cursor-pointer group-hover:text-[#274888] transition-colors line-clamp-1 break-words"
              :title="co.name"
            >
              {{ co.name }}
            </h3>

            <!-- Geo Administrative Jurisdiction Hierarchy Breadcrumbs -->
            <div class="flex flex-wrap items-center gap-1 text-[10px] font-mono">
              <span v-if="co.admin_level_1 || co.division || co.state" class="px-1.5 py-0.5 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[2px] text-[#274888] max-w-full truncate" :title="`${co.admin_labels?.level_1 || 'L1'}: ${co.admin_level_1 || co.division || co.state}`">
                <strong class="font-semibold">{{ co.admin_labels?.level_1 || 'State' }}:</strong> {{ co.admin_level_1 || co.division || co.state }}
              </span>
              <span v-if="co.admin_level_2 || co.district || co.county" class="px-1.5 py-0.5 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[2px] text-[#147A67] max-w-full truncate" :title="`${co.admin_labels?.level_2 || 'L2'}: ${co.admin_level_2 || co.district || co.county}`">
                <strong class="font-semibold">{{ co.admin_labels?.level_2 || 'District' }}:</strong> {{ co.admin_level_2 || co.district || co.county }}
              </span>
              <span v-if="co.admin_level_3 || co.upazila || co.city" class="px-1.5 py-0.5 bg-[#F8FAF7] border border-[#D0D9CF] rounded-[2px] text-[#8A5A08] max-w-full truncate" :title="`${co.admin_labels?.level_3 || 'L3'}: ${co.admin_level_3 || co.upazila || co.city}`">
                <strong class="font-semibold">{{ co.admin_labels?.level_3 || 'City' }}:</strong> {{ co.admin_level_3 || co.upazila || co.city }}
              </span>
            </div>

            <!-- Summary -->
            <p class="text-xs text-[#5F6875] line-clamp-2 leading-relaxed min-h-[34px]">
              {{ co.summary || 'Strategic corporate entity tracked by Nexovant multi-continental intelligence scanners.' }}
            </p>
          </div>

          <!-- Footer: Metrics & Follow Action -->
          <div class="pt-3 border-t border-[#D0D9CF]/60 flex items-center justify-between text-xs gap-2">
            <div class="flex items-center gap-1.5 text-[11px] font-mono text-[#5F6875] truncate">
              <span class="flex items-center gap-1 truncate">
                <Users class="w-3 h-3 text-[#5F6875] shrink-0" />
                <span class="truncate">{{ co.employee_count ? co.employee_count.toLocaleString() : 'Corporate' }}</span>
              </span>
              <span>•</span>
              <span class="shrink-0">{{ co.founded_year ? `Est. ${co.founded_year}` : 'Indexed' }}</span>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
              <button
                type="button"
                @click="openCompanyDetail(co)"
                class="px-2 py-1 text-xs text-[#274888] hover:underline font-medium cursor-pointer"
              >
                Profile →
              </button>

              <button
                type="button"
                :class="[
                  'px-2.5 py-1.5 rounded-[4px] text-xs font-medium transition-all border cursor-pointer flex items-center gap-1',
                  co.is_followed
                    ? 'border-[#17794D]/40 bg-[#E3F1EA] text-[#17794D] font-semibold'
                    : 'border-[#D0D9CF] bg-white text-[#0E1A2B] hover:bg-[#EEEBE4]'
                ]"
                @click="toggleFollow(co)"
              >
                <component :is="co.is_followed ? Check : Plus" class="h-3 w-3 shrink-0" />
                <span>{{ co.is_followed ? 'Monitored' : '+ Watch' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination Footer -->
      <div v-if="lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-[#D0D9CF] pt-5 text-xs">
        <span class="text-[#5F6875] font-mono">
          Page {{ currentPage }} of {{ lastPage }} ({{ totalCompanies }} companies)
        </span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="currentPage--; fetchCompanies()"
            :class="[
              'px-3.5 py-1.5 rounded-[4px] border text-xs font-medium transition-colors',
              currentPage <= 1
                ? 'border-[#D0D9CF] text-[#5F6875]/50 cursor-not-allowed bg-[#F8FAF7]'
                : 'border-[#D0D9CF] bg-white text-[#0E1A2B] hover:bg-[#EEEBE4] cursor-pointer'
            ]"
          >
            ← Previous
          </button>
          <button
            type="button"
            :disabled="currentPage >= lastPage"
            @click="currentPage++; fetchCompanies()"
            :class="[
              'px-3.5 py-1.5 rounded-[4px] border text-xs font-medium transition-colors',
              currentPage >= lastPage
                ? 'border-[#D0D9CF] text-[#5F6875]/50 cursor-not-allowed bg-[#F8FAF7]'
                : 'border-[#D0D9CF] bg-white text-[#0E1A2B] hover:bg-[#EEEBE4] cursor-pointer'
            ]"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Company Detail Slide-Over Modal / Drawer -->
    <div
      v-if="isDetailDrawerOpen && selectedCompany"
      class="fixed inset-0 z-50 overflow-hidden bg-black/50 backdrop-blur-xs flex justify-end animate-in fade-in duration-200"
      @click.self="closeCompanyDetail"
    >
      <div class="w-full sm:max-w-xl bg-white h-full shadow-2xl flex flex-col justify-between text-left border-l border-[#D0D9CF] overflow-y-auto">
        <!-- Header -->
        <div class="p-5 sm:p-6 border-b border-[#D0D9CF] bg-[#F8FAF7] flex items-start justify-between gap-3">
          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 font-mono text-xs text-[#274888] flex-wrap">
              <span v-if="selectedCompany.ticker" class="font-bold bg-[#EBF5EE] text-[#147A67] px-2 py-0.5 rounded shrink-0">
                {{ selectedCompany.ticker }}
              </span>
              <span class="truncate">{{ selectedCompany.sector || 'Technology' }}</span>
              <span>•</span>
              <span class="capitalize shrink-0">{{ selectedCompany.market_cap_tier }} Cap</span>
            </div>
            <h2 class="editorial-headline text-xl sm:text-2xl font-bold text-[#0E1A2B] break-words">
              {{ selectedCompany.name }}
            </h2>
            <div class="text-xs text-[#5F6875] flex items-center gap-1 font-medium truncate">
              <Globe2 class="w-3.5 h-3.5 text-[#5F6875] shrink-0" />
              <span class="truncate">{{ selectedCompany.country }} • {{ selectedCompany.continent }}</span>
            </div>
          </div>

          <button
            type="button"
            @click="closeCompanyDetail"
            class="text-[#5F6875] hover:text-[#0E1A2B] p-1.5 rounded-full hover:bg-white border border-transparent hover:border-[#D0D9CF] cursor-pointer shrink-0"
            aria-label="Close dialog"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Body Details -->
        <div class="p-5 sm:p-6 space-y-6 flex-1 text-xs overflow-y-auto">
          <!-- Overview -->
          <div class="space-y-2">
            <h4 class="font-mono uppercase text-[11px] font-bold text-[#0E1A2B]">Corporate Intelligence Brief</h4>
            <p class="text-sm text-[#3F4A5A] leading-relaxed">
              {{ selectedCompany.summary }}
            </p>
          </div>

          <!-- Multi-Level Geographic Administrative Jurisdiction Breakdown -->
          <div class="space-y-2.5 bg-[#F8FAF7] border border-[#D0D9CF] p-4 rounded-[6px]">
            <h4 class="font-mono uppercase text-[11px] font-bold text-[#274888] flex items-center gap-1.5">
              <MapPin class="w-3.5 h-3.5 text-[#274888] shrink-0" />
              <span>Geographic Administrative Jurisdiction:</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div>
                <span class="text-[#5F6875] block text-[10px] font-mono uppercase">Continent:</span>
                <span class="font-medium text-[#0E1A2B]">{{ selectedCompany.continent }}</span>
              </div>
              <div>
                <span class="text-[#5F6875] block text-[10px] font-mono uppercase">Country:</span>
                <span class="font-medium text-[#0E1A2B]">{{ selectedCompany.country }} ({{ selectedCompany.headquarters_country }})</span>
              </div>
              <div>
                <span class="text-[#5F6875] block text-[10px] font-mono uppercase">{{ selectedCompany.admin_labels?.level_1 || 'State / Division' }}:</span>
                <span class="font-medium text-[#0E1A2B]">{{ selectedCompany.admin_level_1 || selectedCompany.division || selectedCompany.state || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-[#5F6875] block text-[10px] font-mono uppercase">{{ selectedCompany.admin_labels?.level_2 || 'District / County' }}:</span>
                <span class="font-medium text-[#0E1A2B]">{{ selectedCompany.admin_level_2 || selectedCompany.district || selectedCompany.county || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-[#5F6875] block text-[10px] font-mono uppercase">{{ selectedCompany.admin_labels?.level_3 || 'City / Upazila' }}:</span>
                <span class="font-medium text-[#0E1A2B]">{{ selectedCompany.admin_level_3 || selectedCompany.upazila || selectedCompany.city || 'N/A' }}</span>
              </div>
              <div>
                <span class="text-[#5F6875] block text-[10px] font-mono uppercase">Industry:</span>
                <span class="font-medium text-[#0E1A2B]">{{ selectedCompany.industry }}</span>
              </div>
            </div>
          </div>

          <!-- Key Metrics -->
          <div class="grid grid-cols-3 gap-3 border-y border-[#D0D9CF] py-4 text-center">
            <div>
              <span class="block text-[10px] font-mono uppercase text-[#5F6875]">Employees</span>
              <span class="font-bold text-sm text-[#0E1A2B]">
                {{ selectedCompany.employee_count ? selectedCompany.employee_count.toLocaleString() : 'N/A' }}
              </span>
            </div>
            <div>
              <span class="block text-[10px] font-mono uppercase text-[#5F6875]">Founded</span>
              <span class="font-bold text-sm text-[#0E1A2B]">
                {{ selectedCompany.founded_year || 'N/A' }}
              </span>
            </div>
            <div>
              <span class="block text-[10px] font-mono uppercase text-[#5F6875]">Market Cap Tier</span>
              <span class="font-bold text-sm text-[#147A67] uppercase">
                {{ selectedCompany.market_cap_tier }}
              </span>
            </div>
          </div>

          <!-- Official Website -->
          <div v-if="selectedCompany.website_url" class="space-y-1">
            <span class="text-[10px] font-mono uppercase text-[#5F6875]">Corporate Portal:</span>
            <a
              :href="selectedCompany.website_url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-xs text-[#274888] hover:underline flex items-center gap-1 font-mono break-all"
            >
              <span>{{ selectedCompany.website_url }}</span>
              <ExternalLink class="w-3 h-3 shrink-0" />
            </a>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="p-5 sm:p-6 border-t border-[#D0D9CF] bg-[#F8FAF7] flex items-center justify-between gap-3">
          <button
            type="button"
            @click="toggleFollow(selectedCompany)"
            :class="[
              'px-4 py-2.5 rounded-[4px] text-xs font-semibold transition-all border cursor-pointer flex items-center gap-2',
              selectedCompany.is_followed
                ? 'border-[#17794D] bg-[#E3F1EA] text-[#17794D]'
                : 'border-[#274888] bg-[#274888] text-white hover:bg-[#1C3668]'
            ]"
          >
            <component :is="selectedCompany.is_followed ? Check : Plus" class="w-4 h-4 shrink-0" />
            <span>{{ selectedCompany.is_followed ? 'Monitored on Watchlist' : '+ Add to Monitored Watchlist' }}</span>
          </button>

          <button
            type="button"
            @click="closeCompanyDetail"
            class="px-4 py-2 text-xs font-medium text-[#5F6875] hover:text-[#0E1A2B] cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
