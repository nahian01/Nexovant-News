<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import {
  Building2,
  Globe2,
  MapPin,
  Search,
  Plus,
  Filter,
  X,
  Edit2,
  Trash2,
  Eye,
  ChevronDown,
  Check,
  Sparkles,
  Layers,
  Tag,
  ExternalLink,
  Users,
  Calendar,
  ShieldCheck,
  RefreshCw,
} from 'lucide-vue-next'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import {
  companiesService,
  type Company,
  type CompanyFilters,
  type CompanyAggregates,
  type GeoNomenclatureRule,
} from '@/services/api/companies.service'
import { categoryService, type CategoryItem } from '@/services/api/categories.service'

// State
const companies = ref<Company[]>([])
const aggregates = ref<CompanyAggregates | null>(null)
const geoNomenclature = ref<Record<string, GeoNomenclatureRule>>({})
const availableContinents = ref<string[]>([])
const allCategories = ref<CategoryItem[]>([])

const isLoading = ref(true)
const isSubmitting = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const totalCompanies = ref(0)
const perPage = ref(20)

// Filters
const searchKeyword = ref('')
const selectedContinent = ref('')
const selectedCountry = ref('')
const selectedDivision = ref('')
const selectedDistrict = ref('')
const selectedUpazila = ref('')
const selectedState = ref('')
const selectedIndustry = ref('')
const selectedCategoryId = ref('')
const selectedMarketCap = ref('')

// Modal States
const isCreateModalOpen = ref(false)
const isEditMode = ref(false)
const isDetailModalOpen = ref(false)
const activeCompany = ref<Company | null>(null)

// Form State
const form = ref<Partial<Company>>({
  name: '',
  slug: '',
  ticker: '',
  isin: '',
  sector: '',
  industry: 'Strategic Technology',
  category_id: '',
  subcategory_id: '',
  continent: 'Asia',
  country: 'Bangladesh',
  headquarters_country: 'BD',
  division: 'Dhaka',
  district: 'Gazipur',
  upazila: 'Kaliakair',
  city: 'Chandra',
  state: '',
  province: '',
  county: '',
  headquarters_address: '',
  postal_code: '',
  website_url: '',
  logo_url: '',
  market_cap_tier: 'large',
  employee_count: 5000,
  founded_year: 2000,
  summary: '',
  is_active: true,
})

// Dynamic Nomenclature for current selected country in filter & form
const currentFilterCountryRule = computed<GeoNomenclatureRule>(() => {
  if (selectedCountry.value && geoNomenclature.value[selectedCountry.value]) {
    return geoNomenclature.value[selectedCountry.value]
  }
  return {
    continent: 'Global',
    code: 'XX',
    level_1: 'State / Province / Division',
    level_2: 'District / County',
    level_3: 'Upazila / City',
  }
})

const currentFormCountryRule = computed<GeoNomenclatureRule>(() => {
  const cName = form.value.country || ''
  if (cName && geoNomenclature.value[cName]) {
    return geoNomenclature.value[cName]
  }
  return {
    continent: 'Global',
    code: 'XX',
    level_1: 'State / Province / Division',
    level_2: 'District / County',
    level_3: 'Upazila / City',
  }
})

// Metric calculations
const totalContinents = computed(() => aggregates.value?.continents?.length || 0)
const totalCountries = computed(() => aggregates.value?.countries?.length || 0)
const totalIndustries = computed(() => aggregates.value?.industries?.length || 0)
const totalLocalHubs = computed(() => {
  const divCount = aggregates.value?.divisions?.length || 0
  const distCount = aggregates.value?.districts?.length || 0
  const upCount = aggregates.value?.upazilas?.length || 0
  return divCount + distCount + upCount
})

const hasActiveFilters = computed(() => {
  return (
    !!searchKeyword.value.trim() ||
    !!selectedContinent.value ||
    !!selectedCountry.value ||
    !!selectedDivision.value ||
    !!selectedDistrict.value ||
    !!selectedUpazila.value ||
    !!selectedState.value ||
    !!selectedIndustry.value ||
    !!selectedCategoryId.value ||
    !!selectedMarketCap.value
  )
})

const fetchCompanies = async (resetPage = false) => {
  if (resetPage) currentPage.value = 1
  isLoading.value = true

  try {
    const params: CompanyFilters = {
      page: currentPage.value,
      per_page: perPage.value,
    }

    if (searchKeyword.value.trim()) params.q = searchKeyword.value.trim()
    if (selectedContinent.value) params.continent = selectedContinent.value
    if (selectedCountry.value) params.country = selectedCountry.value
    if (selectedDivision.value) params.division = selectedDivision.value
    if (selectedDistrict.value) params.district = selectedDistrict.value
    if (selectedUpazila.value) params.upazila = selectedUpazila.value
    if (selectedState.value) params.state = selectedState.value
    if (selectedIndustry.value) params.industry = selectedIndustry.value
    if (selectedCategoryId.value) params.category_id = selectedCategoryId.value
    if (selectedMarketCap.value) params.market_cap_tier = selectedMarketCap.value

    const res = await companiesService.getAdminCompanies(params)
    companies.value = res.data || []
    currentPage.value = res.meta?.current_page || 1
    lastPage.value = res.meta?.last_page || 1
    totalCompanies.value = res.meta?.total || 0

    if (res.meta?.aggregates) {
      aggregates.value = res.meta.aggregates
    }
  } catch (err) {
    console.error('Failed to load companies registry', err)
    companies.value = []
  } finally {
    isLoading.value = false
  }
}

let searchTimer: any = null
const onSearchInput = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchCompanies(true)
  }, 350)
}

const clearFilters = () => {
  searchKeyword.value = ''
  selectedContinent.value = ''
  selectedCountry.value = ''
  selectedDivision.value = ''
  selectedDistrict.value = ''
  selectedUpazila.value = ''
  selectedState.value = ''
  selectedIndustry.value = ''
  selectedCategoryId.value = ''
  selectedMarketCap.value = ''
  fetchCompanies(true)
}

// Country change handler in form
const onFormCountryChange = () => {
  const cName = form.value.country || ''
  const rule = geoNomenclature.value[cName]
  if (rule) {
    form.value.continent = rule.continent
    form.value.headquarters_country = rule.code
    if (cName === 'Bangladesh') {
      form.value.division = form.value.division || 'Dhaka'
      form.value.district = form.value.district || 'Dhaka'
      form.value.upazila = form.value.upazila || 'Gulshan'
    } else if (cName === 'United States') {
      form.value.state = form.value.state || 'California'
      form.value.county = form.value.county || 'Santa Clara County'
      form.value.city = form.value.city || 'Santa Clara'
    }
  }
}

const openCreateModal = () => {
  isEditMode.value = false
  form.value = {
    name: '',
    slug: '',
    ticker: '',
    isin: '',
    sector: 'Information Technology',
    industry: 'Strategic Technology',
    category_id: allCategories.value[0]?.id || '',
    continent: 'Asia',
    country: 'Bangladesh',
    headquarters_country: 'BD',
    division: 'Dhaka',
    district: 'Gazipur',
    upazila: 'Kaliakair',
    city: 'Chandra',
    state: '',
    province: '',
    county: '',
    headquarters_address: '',
    postal_code: '1751',
    website_url: '',
    logo_url: '',
    market_cap_tier: 'large',
    employee_count: 5000,
    founded_year: 2000,
    summary: '',
    is_active: true,
  }
  isCreateModalOpen.value = true
}

const openEditModal = (co: Company) => {
  isEditMode.value = true
  activeCompany.value = co
  form.value = {
    ...co,
    category_id: co.category_id || co.category?.id || '',
    subcategory_id: co.subcategory_id || co.subcategory?.id || '',
  }
  isCreateModalOpen.value = true
}

const openDetailModal = (co: Company) => {
  activeCompany.value = co
  isDetailModalOpen.value = true
}

const saveCompany = async () => {
  if (!form.value.name?.trim()) return
  isSubmitting.value = true

  try {
    if (isEditMode.value && activeCompany.value?.id) {
      await companiesService.updateCompany(activeCompany.value.id, form.value)
    } else {
      await companiesService.createCompany(form.value)
    }
    isCreateModalOpen.value = false
    await fetchCompanies(false)
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to save corporate entity.')
  } finally {
    isSubmitting.value = false
  }
}

const deleteCompany = async (co: Company) => {
  if (!confirm(`Are you sure you want to delete corporate registry entity "${co.name}"?`)) {
    return
  }

  try {
    await companiesService.deleteCompany(co.id)
    await fetchCompanies(false)
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Failed to delete company.')
  }
}

onMounted(async () => {
  try {
    const [geoRes, catRes] = await Promise.allSettled([
      companiesService.getGeoOptions(),
      categoryService.getCategories(),
    ])

    if (geoRes.status === 'fulfilled') {
      availableContinents.value = geoRes.value.continents || []
      geoNomenclature.value = geoRes.value.nomenclature || {}
    }

    if (catRes.status === 'fulfilled') {
      allCategories.value = catRes.value || []
    }
  } catch (err) {
    console.error('Initialization error', err)
  }

  await fetchCompanies(true)
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left pb-20">
      <!-- 1. Header & Quick Actions -->
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-nexo-border pb-5">
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-widest text-[#274888]">
            <Building2 class="h-3.5 w-3.5" />
            <span>GLOBAL TAXONOMY & GEO-ADMINISTRATIVE DIRECTORY</span>
          </div>
          <h1 class="editorial-headline text-3xl font-medium tracking-tight text-nexo-ink">
            Companies Registry & Geo-Taxonomy
          </h1>
          <p class="text-xs sm:text-sm text-nexo-muted max-w-3xl">
            Directory of corporate entities organized by Industry, Category, Continent, Country, and localized Administrative Hierarchy (Division, District, Upazila / State, County, City).
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            @click="fetchCompanies(true)"
            class="px-3 py-2 rounded-[4px] border border-nexo-border bg-white hover:bg-nexo-paper-2 text-nexo-text-2 text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs"
            title="Refresh Registry"
          >
            <RefreshCw class="h-3.5 w-3.5 text-nexo-muted" />
            <span>Refresh</span>
          </button>
          <button
            type="button"
            @click="openCreateModal"
            class="px-3.5 py-2 rounded-[4px] bg-[#0E1A2B] hover:bg-[#274888] text-white text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs cursor-pointer"
          >
            <Plus class="h-3.5 w-3.5" />
            <span>Add New Company</span>
          </button>
        </div>
      </div>

      <!-- 2. Metrics Strip (Continents, Countries, Local Jurisdictions, Industries) -->
      <section class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <!-- Metric 1: Total Companies -->
        <div class="card-editorial p-4 border border-nexo-border bg-white">
          <div class="flex items-center justify-between text-[11px] font-mono uppercase text-nexo-muted">
            <span>Total Entities</span>
            <Building2 class="h-4 w-4 text-[#274888]" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-ink">{{ totalCompanies }}</p>
          <p class="text-[10px] text-nexo-muted">Active in database</p>
        </div>

        <!-- Metric 2: Continents -->
        <div class="card-editorial p-4 border border-nexo-border bg-white">
          <div class="flex items-center justify-between text-[11px] font-mono uppercase text-nexo-muted">
            <span>Continents</span>
            <Globe2 class="h-4 w-4 text-[#17794D]" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-ink">{{ totalContinents }}</p>
          <p class="text-[10px] text-nexo-muted">Asia, N. America, Europe</p>
        </div>

        <!-- Metric 3: Sovereign Countries -->
        <div class="card-editorial p-4 border border-nexo-border bg-white">
          <div class="flex items-center justify-between text-[11px] font-mono uppercase text-nexo-muted">
            <span>Countries</span>
            <MapPin class="h-4 w-4 text-[#8A5A08]" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-ink">{{ totalCountries }}</p>
          <p class="text-[10px] text-nexo-muted">Bangladesh, US, UK, DE, JP, etc.</p>
        </div>

        <!-- Metric 4: Local Jurisdictions -->
        <div class="card-editorial p-4 border border-nexo-border bg-white">
          <div class="flex items-center justify-between text-[11px] font-mono uppercase text-nexo-muted">
            <span>Local Hubs</span>
            <Layers class="h-4 w-4 text-[#58488A]" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-ink">{{ totalLocalHubs }}</p>
          <p class="text-[10px] text-nexo-muted">Divisions, Districts, Upazilas</p>
        </div>

        <!-- Metric 5: Industries -->
        <div class="card-editorial p-4 border border-nexo-border bg-white">
          <div class="flex items-center justify-between text-[11px] font-mono uppercase text-nexo-muted">
            <span>Industries</span>
            <Tag class="h-4 w-4 text-[#B7791F]" />
          </div>
          <p class="mt-2 font-serif text-2xl font-semibold text-nexo-ink">{{ totalIndustries }}</p>
          <p class="text-[10px] text-nexo-muted">Tech, Pharma, Telecom, Auto</p>
        </div>
      </section>

      <!-- 3. Multi-Dimensional Hierarchical Filter Card -->
      <div class="card-editorial p-4 sm:p-5 bg-white border border-nexo-border space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5 text-xs font-mono uppercase tracking-wider text-nexo-ink font-semibold">
            <Filter class="h-3.5 w-3.5 text-[#274888]" />
            <span>Filter Corporate Registry By Geo-Hierarchy & Industry</span>
          </div>

          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            type="button"
            class="text-xs text-[#274888] hover:underline font-semibold flex items-center gap-1 cursor-pointer"
          >
            <X class="w-3.5 h-3.5" />
            <span>Clear all filters</span>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 text-xs">
          <!-- 3.1 Search Keyword -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-nexo-muted">Keyword Search</label>
            <div class="relative">
              <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-nexo-muted" />
              <input
                v-model="searchKeyword"
                @input="onSearchInput"
                placeholder="Name, ticker, upazila, city..."
                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
              />
            </div>
          </div>

          <!-- 3.2 Continent -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-nexo-muted">Continent</label>
            <select
              v-model="selectedContinent"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All Continents</option>
              <option v-for="c in aggregates?.continents || []" :key="c.continent" :value="c.continent">
                {{ c.continent }} ({{ c.count }})
              </option>
            </select>
          </div>

          <!-- 3.3 Country -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-nexo-muted">Country</label>
            <select
              v-model="selectedCountry"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All Countries</option>
              <option v-for="co in aggregates?.countries || []" :key="co.country" :value="co.country">
                {{ co.country }} ({{ co.count }})
              </option>
            </select>
          </div>

          <!-- 3.4 Dynamic Administrative Level 1 (Division / State / Province) -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-[#274888] font-semibold">
              {{ currentFilterCountryRule.level_1 }}
            </label>
            <select
              v-if="selectedCountry === 'Bangladesh' || !selectedCountry"
              v-model="selectedDivision"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All {{ currentFilterCountryRule.level_1 }}s</option>
              <option v-for="d in aggregates?.divisions || []" :key="d.division" :value="d.division">
                {{ d.division }} ({{ d.count }})
              </option>
            </select>
            <input
              v-else
              v-model="selectedState"
              @input="onSearchInput"
              :placeholder="`Filter by ${currentFilterCountryRule.level_1}...`"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            />
          </div>

          <!-- 3.5 Dynamic Administrative Level 2 (District / County) -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-[#147A67] font-semibold">
              {{ currentFilterCountryRule.level_2 }}
            </label>
            <select
              v-model="selectedDistrict"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All {{ currentFilterCountryRule.level_2 }}s</option>
              <option v-for="dst in aggregates?.districts || []" :key="dst.district" :value="dst.district">
                {{ dst.district }} ({{ dst.count }})
              </option>
            </select>
          </div>

          <!-- 3.6 Dynamic Administrative Level 3 (Upazila / City) -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-[#8A5A08] font-semibold">
              {{ currentFilterCountryRule.level_3 }}
            </label>
            <select
              v-if="aggregates?.upazilas && aggregates.upazilas.length > 0"
              v-model="selectedUpazila"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All {{ currentFilterCountryRule.level_3 }}s</option>
              <option v-for="upz in aggregates?.upazilas || []" :key="upz.upazila" :value="upz.upazila">
                {{ upz.upazila }} ({{ upz.count }})
              </option>
            </select>
            <input
              v-else
              v-model="selectedUpazila"
              @input="onSearchInput"
              :placeholder="`Filter by ${currentFilterCountryRule.level_3}...`"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            />
          </div>

          <!-- 3.7 Industry Sector -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-nexo-muted">Industry</label>
            <select
              v-model="selectedIndustry"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All Industries</option>
              <option v-for="ind in aggregates?.industries || []" :key="ind.industry" :value="ind.industry">
                {{ ind.industry }} ({{ ind.count }})
              </option>
            </select>
          </div>

          <!-- 3.8 Category -->
          <div class="space-y-1">
            <label class="font-mono text-[10px] uppercase text-nexo-muted">Category</label>
            <select
              v-model="selectedCategoryId"
              @change="fetchCompanies(true)"
              class="w-full px-2.5 py-1.5 text-xs rounded-[3px] border border-nexo-border bg-white text-nexo-ink focus:outline-none focus:border-[#274888]"
            >
              <option value="">All Categories</option>
              <option v-for="cat in aggregates?.categories || []" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- 4. Table Header & Count -->
      <div class="flex items-center justify-between text-xs text-nexo-muted border-b border-nexo-border pb-2">
        <span class="font-mono uppercase tracking-wider text-[11px]">
          Showing {{ companies.length }} of {{ totalCompanies }} Registered Corporate Entities
        </span>
        <span class="text-[11px] font-mono text-[#274888]">
          Page {{ currentPage }} of {{ lastPage }}
        </span>
      </div>

      <!-- 5. Loading Skeleton -->
      <div v-if="isLoading" class="space-y-3">
        <div v-for="i in 5" :key="i" class="card-editorial p-4 flex items-center justify-between">
          <Skeleton class="h-5 w-48" />
          <Skeleton class="h-5 w-32" />
          <Skeleton class="h-5 w-40" />
        </div>
      </div>

      <!-- 6. Empty State -->
      <div v-else-if="companies.length === 0" class="card-editorial p-12 text-center space-y-3 bg-white border border-nexo-border">
        <Building2 class="w-8 h-8 text-nexo-muted mx-auto opacity-50" />
        <h3 class="editorial-headline text-lg font-medium text-nexo-ink">No Corporate Entities Found</h3>
        <p class="text-xs text-nexo-muted max-w-md mx-auto">
          No companies match your active geographic or industry filters. Try resetting the filters or add a new company.
        </p>
        <button
          type="button"
          @click="clearFilters"
          class="px-4 py-2 bg-[#0E1A2B] text-white text-xs font-semibold rounded-[4px] hover:bg-[#274888] transition-colors"
        >
          Reset All Filters
        </button>
      </div>

      <!-- 7. Corporate Registry Data Table -->
      <div v-else class="card-editorial bg-white border border-nexo-border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-nexo-border bg-[#F8FAF7] text-[11px] font-mono uppercase text-nexo-muted">
                <th class="py-3 px-4">Company & Ticker</th>
                <th class="py-3 px-4">Industry & Category</th>
                <th class="py-3 px-4">Continent / Country</th>
                <th class="py-3 px-4">Level 1 (Div / State)</th>
                <th class="py-3 px-4">Level 2 (District / County)</th>
                <th class="py-3 px-4">Level 3 (Upazila / City)</th>
                <th class="py-3 px-4">Market Cap</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-nexo-border/60">
              <tr
                v-for="co in companies"
                :key="co.id"
                class="hover:bg-nexo-paper-2/60 transition-colors group"
              >
                <!-- Company Name & Ticker -->
                <td class="py-3.5 px-4 font-medium text-nexo-ink">
                  <div class="flex items-center gap-2">
                    <span class="font-semibold text-sm group-hover:text-[#274888] transition-colors">{{ co.name }}</span>
                    <span v-if="co.ticker" class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded-[2px] bg-[#EEEBE4] border border-nexo-border text-[#274888]">
                      {{ co.ticker }}
                    </span>
                  </div>
                  <div class="text-[10px] text-nexo-muted mt-0.5 line-clamp-1 max-w-xs">
                    {{ co.summary || co.sector }}
                  </div>
                </td>

                <!-- Industry & Category -->
                <td class="py-3.5 px-4">
                  <div class="space-y-1">
                    <span class="inline-block px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-medium bg-[#EBF5EE] text-[#147A67] border border-[#C5E3CE]">
                      {{ co.industry || 'General Technology' }}
                    </span>
                    <div v-if="co.category" class="text-[10px] text-nexo-muted flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: co.category.color || '#274888' }"></span>
                      <span>{{ co.category.name }}</span>
                    </div>
                  </div>
                </td>

                <!-- Continent / Country -->
                <td class="py-3.5 px-4">
                  <div class="font-medium text-nexo-ink flex items-center gap-1.5">
                    <Globe2 class="w-3.5 h-3.5 text-[#274888]" />
                    <span>{{ co.country || co.headquarters_country || 'Global' }}</span>
                  </div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase tracking-wider">
                    {{ co.continent || 'Global' }} ({{ co.headquarters_country }})
                  </span>
                </td>

                <!-- Admin Level 1 (Division / State / Province) -->
                <td class="py-3.5 px-4">
                  <div v-if="co.admin_level_1 || co.division || co.state || co.province" class="space-y-0.5">
                    <span class="font-semibold text-nexo-ink">
                      {{ co.admin_level_1 || co.division || co.state || co.province }}
                    </span>
                    <div class="text-[9px] font-mono uppercase text-[#274888]">
                      {{ co.admin_labels?.level_1 || co.admin_level_1_type || 'Region' }}
                    </div>
                  </div>
                  <span v-else class="text-nexo-muted text-[11px]">—</span>
                </td>

                <!-- Admin Level 2 (District / County) -->
                <td class="py-3.5 px-4">
                  <div v-if="co.admin_level_2 || co.district || co.county" class="space-y-0.5">
                    <span class="font-semibold text-nexo-ink">
                      {{ co.admin_level_2 || co.district || co.county }}
                    </span>
                    <div class="text-[9px] font-mono uppercase text-[#147A67]">
                      {{ co.admin_labels?.level_2 || co.admin_level_2_type || 'District' }}
                    </div>
                  </div>
                  <span v-else class="text-nexo-muted text-[11px]">—</span>
                </td>

                <!-- Admin Level 3 (Upazila / City) -->
                <td class="py-3.5 px-4">
                  <div v-if="co.admin_level_3 || co.upazila || co.city" class="space-y-0.5">
                    <span class="font-semibold text-nexo-ink">
                      {{ co.admin_level_3 || co.upazila || co.city }}
                    </span>
                    <div class="text-[9px] font-mono uppercase text-[#8A5A08]">
                      {{ co.admin_labels?.level_3 || co.admin_level_3_type || 'Upazila / City' }}
                    </div>
                  </div>
                  <span v-else class="text-nexo-muted text-[11px]">—</span>
                </td>

                <!-- Market Cap Tier -->
                <td class="py-3.5 px-4">
                  <span
                    class="px-2 py-0.5 rounded-[2px] text-[10px] font-mono font-bold uppercase tracking-wider"
                    :class="[
                      co.market_cap_tier === 'mega' ? 'bg-amber-100 text-amber-900 border border-amber-300' :
                      co.market_cap_tier === 'large' ? 'bg-blue-50 text-blue-900 border border-blue-200' :
                      'bg-gray-100 text-gray-800 border border-gray-200'
                    ]"
                  >
                    {{ co.market_cap_tier || 'mid' }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                  <button
                    type="button"
                    @click="openDetailModal(co)"
                    class="p-1 rounded-[2px] border border-nexo-border hover:bg-[#EEEBE4] text-[#274888] cursor-pointer"
                    title="View Comprehensive Dossier"
                  >
                    <Eye class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="openEditModal(co)"
                    class="p-1 rounded-[2px] border border-nexo-border hover:bg-[#EEEBE4] text-nexo-ink cursor-pointer"
                    title="Edit Entity"
                  >
                    <Edit2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    @click="deleteCompany(co)"
                    class="p-1 rounded-[2px] border border-nexo-border hover:bg-red-50 text-red-600 cursor-pointer"
                    title="Delete Entity"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 8. Pagination Strip -->
        <div class="flex items-center justify-between px-4 py-3 border-t border-nexo-border text-xs bg-[#F8FAF7]">
          <span class="text-nexo-muted">
            Page {{ currentPage }} of {{ lastPage }} ({{ totalCompanies }} total items)
          </span>

          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="currentPage <= 1"
              @click="currentPage--; fetchCompanies(false)"
              class="px-2.5 py-1 rounded-[3px] border border-nexo-border bg-white text-nexo-ink disabled:opacity-40 cursor-pointer"
            >
              Previous
            </button>
            <button
              type="button"
              :disabled="currentPage >= lastPage"
              @click="currentPage++; fetchCompanies(false)"
              class="px-2.5 py-1 rounded-[3px] border border-nexo-border bg-white text-nexo-ink disabled:opacity-40 cursor-pointer"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- 9. Create / Edit Corporate Entity Modal -->
      <div
        v-if="isCreateModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4 overflow-y-auto"
      >
        <div class="card-editorial bg-white max-w-3xl w-full p-6 space-y-6 my-8 max-h-[90vh] overflow-y-auto border border-nexo-border shadow-xl">
          <div class="flex items-center justify-between border-b border-nexo-border pb-3">
            <div class="space-y-0.5">
              <span class="text-[10px] font-mono uppercase text-[#274888] font-bold">
                {{ isEditMode ? 'EDIT CORPORATE RECORD' : 'NEW CORPORATE RECORD' }}
              </span>
              <h3 class="editorial-headline text-xl font-medium text-nexo-ink">
                {{ isEditMode ? `Edit ${form.name}` : 'Register New Corporate Entity' }}
              </h3>
            </div>
            <button @click="isCreateModalOpen = false" class="text-nexo-muted hover:text-nexo-ink cursor-pointer">
              <X class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="saveCompany" class="space-y-6 text-xs">
            <!-- Section 1: Basic Identity & Taxonomy -->
            <div class="space-y-3">
              <h4 class="font-mono text-xs uppercase font-bold text-nexo-ink border-b border-nexo-border pb-1">
                1. Corporate Identity & Market Taxonomy
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted font-bold">Company Name *</label>
                  <input
                    v-model="form.name"
                    required
                    placeholder="e.g. Walton Hi-Tech Industries PLC"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Stock Ticker</label>
                  <input
                    v-model="form.ticker"
                    placeholder="e.g. WALTONHIL, NVDA"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">ISIN Code</label>
                  <input
                    v-model="form.isin"
                    placeholder="e.g. BD0440WALTON"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Industry Sector</label>
                  <input
                    v-model="form.industry"
                    placeholder="e.g. Strategic Technology"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Category Association</label>
                  <select
                    v-model="form.category_id"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  >
                    <option value="">Select Category</option>
                    <option v-for="cat in allCategories" :key="cat.id" :value="cat.id">
                      {{ cat.name }} ({{ cat.industry }})
                    </option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Market Cap Tier</label>
                  <select
                    v-model="form.market_cap_tier"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  >
                    <option value="mega">Mega Cap (>$200B)</option>
                    <option value="large">Large Cap ($10B-$200B)</option>
                    <option value="mid">Mid Cap ($2B-$10B)</option>
                    <option value="small">Small Cap ($300M-$2B)</option>
                    <option value="micro">Micro Cap</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Section 2: Multi-Level Geographic & Geo-Administrative Taxonomy -->
            <div class="space-y-3">
              <div class="flex items-center justify-between border-b border-nexo-border pb-1">
                <h4 class="font-mono text-xs uppercase font-bold text-nexo-ink">
                  2. Geo-Administrative Hierarchy (Continent, Country, Division/State, District, Upazila)
                </h4>
                <span class="text-[10px] font-mono text-[#274888]">
                  Adaptive labels: {{ currentFormCountryRule.level_1 }} / {{ currentFormCountryRule.level_2 }} / {{ currentFormCountryRule.level_3 }}
                </span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <!-- Continent -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted font-bold">Continent *</label>
                  <select
                    v-model="form.continent"
                    required
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  >
                    <option value="Asia">Asia</option>
                    <option value="Europe">Europe</option>
                    <option value="North America">North America</option>
                    <option value="South America">South America</option>
                    <option value="Africa">Africa</option>
                    <option value="Oceania">Oceania</option>
                  </select>
                </div>

                <!-- Country -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted font-bold">Country *</label>
                  <select
                    v-model="form.country"
                    @change="onFormCountryChange"
                    required
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  >
                    <option value="Bangladesh">Bangladesh (Asia)</option>
                    <option value="United States">United States (North America)</option>
                    <option value="Canada">Canada (North America)</option>
                    <option value="United Kingdom">United Kingdom (Europe)</option>
                    <option value="Germany">Germany (Europe)</option>
                    <option value="Switzerland">Switzerland (Europe)</option>
                    <option value="Japan">Japan (Asia)</option>
                    <option value="India">India (Asia)</option>
                  </select>
                </div>

                <!-- Country ISO-2 Code -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Country Code</label>
                  <input
                    v-model="form.headquarters_country"
                    placeholder="e.g. BD, US, DE"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none uppercase font-mono"
                  />
                </div>

                <!-- Level 1: Division / State / Province -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-[#274888] font-bold">
                    {{ currentFormCountryRule.level_1 }} *
                  </label>
                  <input
                    v-if="form.country === 'Bangladesh'"
                    v-model="form.division"
                    placeholder="e.g. Dhaka, Chattogram, Sylhet"
                    required
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                  <input
                    v-else
                    v-model="form.state"
                    :placeholder="`e.g. California, Ontario, Bavaria`"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <!-- Level 2: District / County -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-[#147A67] font-bold">
                    {{ currentFormCountryRule.level_2 }} *
                  </label>
                  <input
                    v-if="form.country === 'Bangladesh'"
                    v-model="form.district"
                    placeholder="e.g. Gazipur, Dhaka, Narsingdi"
                    required
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                  <input
                    v-else
                    v-model="form.county"
                    placeholder="e.g. Santa Clara County, Cambridgeshire"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <!-- Level 3: Upazila / City -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-[#8A5A08] font-bold">
                    {{ currentFormCountryRule.level_3 }} *
                  </label>
                  <input
                    v-if="form.country === 'Bangladesh'"
                    v-model="form.upazila"
                    placeholder="e.g. Kaliakair, Vatara, Palash"
                    required
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                  <input
                    v-else
                    v-model="form.city"
                    placeholder="e.g. Cupertino, Mountain View, Austin"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <!-- City / Area Locality -->
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">City / Locality</label>
                  <input
                    v-model="form.city"
                    placeholder="e.g. Chandra, Dhaka, Silicon Valley"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <!-- Headquarters Address -->
                <div class="space-y-1 sm:col-span-2">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Headquarters Street Address</label>
                  <input
                    v-model="form.headquarters_address"
                    placeholder="e.g. Walton Hi-Tech Park, Chandra, Kaliakair"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>
              </div>
            </div>

            <!-- Section 3: Extended Metadata -->
            <div class="space-y-3">
              <h4 class="font-mono text-xs uppercase font-bold text-nexo-ink border-b border-nexo-border pb-1">
                3. Operational Profile & Executive Summary
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Website URL</label>
                  <input
                    v-model="form.website_url"
                    placeholder="https://..."
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Employee Count</label>
                  <input
                    v-model.number="form.employee_count"
                    type="number"
                    placeholder="30000"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>

                <div class="space-y-1">
                  <label class="font-mono text-[10px] uppercase text-nexo-muted">Founded Year</label>
                  <input
                    v-model.number="form.founded_year"
                    type="number"
                    placeholder="1977"
                    class="w-full px-3 py-1.5 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                  />
                </div>
              </div>

              <div class="space-y-1">
                <label class="font-mono text-[10px] uppercase text-nexo-muted">Executive Intelligence Summary</label>
                <textarea
                  v-model="form.summary"
                  rows="3"
                  placeholder="Primary strategic footprint, manufacturing hubs, semiconductor capabilities..."
                  class="w-full px-3 py-2 text-xs rounded-[3px] border border-nexo-border focus:border-[#274888] outline-none"
                ></textarea>
              </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-nexo-border">
              <button
                type="button"
                @click="isCreateModalOpen = false"
                class="px-4 py-2 rounded-[3px] border border-nexo-border bg-white text-nexo-ink hover:bg-nexo-paper-2 font-medium cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 rounded-[3px] bg-[#0E1A2B] hover:bg-[#274888] text-white font-semibold transition-colors shadow-xs cursor-pointer disabled:opacity-50"
              >
                <span v-if="isSubmitting">Saving Corporate Record...</span>
                <span v-else>{{ isEditMode ? 'Update Corporate Entity' : 'Save Corporate Record' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- 10. View Details Modal / Dossier Drawer -->
      <div
        v-if="isDetailModalOpen && activeCompany"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
      >
        <div class="card-editorial bg-white max-w-2xl w-full p-6 space-y-5 border border-nexo-border shadow-2xl">
          <div class="flex items-start justify-between border-b border-nexo-border pb-3">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="font-mono text-xs font-bold text-[#274888] bg-[#EEEBE4] px-2 py-0.5 rounded-[2px]">
                  {{ activeCompany.ticker || 'UNQUOTED' }}
                </span>
                <span class="text-[10px] font-mono uppercase text-[#147A67] bg-[#EBF5EE] px-2 py-0.5 rounded-[2px]">
                  {{ activeCompany.industry }}
                </span>
              </div>
              <h3 class="editorial-headline text-2xl font-medium text-nexo-ink">
                {{ activeCompany.name }}
              </h3>
            </div>
            <button @click="isDetailModalOpen = false" class="text-nexo-muted hover:text-nexo-ink cursor-pointer">
              <X class="w-5 h-5" />
            </button>
          </div>

          <div class="space-y-4 text-xs">
            <!-- Full Geo Hierarchy Breakdown -->
            <div class="p-3.5 rounded-[4px] bg-[#F8FAF7] border border-nexo-border space-y-2">
              <span class="font-mono text-[10px] uppercase font-bold text-[#274888] flex items-center gap-1.5">
                <MapPin class="w-3.5 h-3.5" />
                Geo-Administrative Nomenclature & Location Tree
              </span>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-1">
                <div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase">Continent:</span>
                  <div class="font-semibold text-nexo-ink">{{ activeCompany.continent }}</div>
                </div>
                <div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase">Country:</span>
                  <div class="font-semibold text-nexo-ink">{{ activeCompany.country }} ({{ activeCompany.headquarters_country }})</div>
                </div>
                <div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase">
                    {{ activeCompany.admin_labels?.level_1 || 'Level 1' }}:
                  </span>
                  <div class="font-semibold text-[#274888]">{{ activeCompany.admin_level_1 || activeCompany.division || activeCompany.state }}</div>
                </div>
                <div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase">
                    {{ activeCompany.admin_labels?.level_2 || 'Level 2' }}:
                  </span>
                  <div class="font-semibold text-[#147A67]">{{ activeCompany.admin_level_2 || activeCompany.district || activeCompany.county }}</div>
                </div>
                <div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase">
                    {{ activeCompany.admin_labels?.level_3 || 'Level 3' }}:
                  </span>
                  <div class="font-semibold text-[#8A5A08]">{{ activeCompany.admin_level_3 || activeCompany.upazila || activeCompany.city }}</div>
                </div>
                <div>
                  <span class="text-[10px] font-mono text-nexo-muted uppercase">Locality / City:</span>
                  <div class="font-semibold text-nexo-ink">{{ activeCompany.city || 'Primary Hub' }}</div>
                </div>
              </div>

              <div v-if="activeCompany.headquarters_address" class="pt-2 border-t border-nexo-border/60 text-[11px] text-nexo-text-2">
                <span class="font-mono text-[10px] text-nexo-muted uppercase">Address: </span>
                <span>{{ activeCompany.headquarters_address }}</span>
                <span v-if="activeCompany.postal_code"> (Postal: {{ activeCompany.postal_code }})</span>
              </div>
            </div>

            <!-- Summary & Operational Details -->
            <div class="space-y-2">
              <span class="font-mono text-[10px] uppercase font-bold text-nexo-muted">Executive Summary</span>
              <p class="text-nexo-text-2 leading-relaxed">
                {{ activeCompany.summary || 'Corporate intelligence profile registered under sovereign jurisdiction.' }}
              </p>
            </div>

            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-nexo-border text-[11px]">
              <div>
                <span class="font-mono text-nexo-muted uppercase text-[10px]">Market Cap:</span>
                <div class="font-bold text-nexo-ink uppercase">{{ activeCompany.market_cap_tier }}</div>
              </div>
              <div>
                <span class="font-mono text-nexo-muted uppercase text-[10px]">Employees:</span>
                <div class="font-bold text-nexo-ink">{{ activeCompany.employee_count?.toLocaleString() || 'N/A' }}</div>
              </div>
              <div>
                <span class="font-mono text-nexo-muted uppercase text-[10px]">Founded:</span>
                <div class="font-bold text-nexo-ink">{{ activeCompany.founded_year || 'N/A' }}</div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end pt-3 border-t border-nexo-border">
            <button
              type="button"
              @click="isDetailModalOpen = false"
              class="px-4 py-2 rounded-[3px] bg-[#0E1A2B] text-white text-xs font-semibold hover:bg-[#274888] cursor-pointer"
            >
              Close Dossier
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
