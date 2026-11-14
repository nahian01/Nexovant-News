<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { Check, Plus } from 'lucide-vue-next'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
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
    companies.value = res.data.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const toggleFollow = async (company: any) => {
  try {
    const res = await apiClient.post(`/companies/${company.id}/follow`)
    company.is_followed = res.data.is_followed
  } catch {
    //
  }
}

onMounted(() => {
  fetchCompanies()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              Global Corporate Directory
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Monitored multinational corporations, executive leadership trees, and competitor matrices.
            </p>
          </div>

          <div class="w-full md:w-72">
            <Input
              v-model="searchQuery"
              placeholder="Search by company name or ticker..."
              @input="fetchCompanies"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <Card
            v-for="co in companies"
            :key="co.id"
            padding="md"
            class="hover:border-nexo-accent-cyan/40"
          >
            <div class="flex flex-col h-full justify-between space-y-4">
              <div>
                <div class="flex items-center justify-between text-xs">
                  <span class="font-mono text-nexo-accent-cyan font-semibold">{{ co.ticker }}</span>
                  <span class="px-2 py-0.5 rounded bg-nexo-elevated text-[10px] font-mono uppercase text-nexo-text-secondary">
                    {{ co.market_cap_tier }} Cap
                  </span>
                </div>

                <h3 class="font-serif text-lg font-bold text-nexo-text-primary mt-1">
                  {{ co.name }}
                </h3>

                <p class="text-xs text-nexo-text-secondary mt-1 line-clamp-2">
                  {{ co.summary }}
                </p>
              </div>

              <div class="pt-3 border-t border-nexo-border/60 flex items-center justify-between text-xs">
                <span class="text-nexo-text-muted font-mono">{{ co.sector }}</span>
                <Button
                  :variant="co.is_followed ? 'secondary' : 'outline'"
                  size="sm"
                  @click="toggleFollow(co)"
                >
                  <component :is="co.is_followed ? Check : Plus" class="h-3 w-3 mr-1 inline" />
                  <span>{{ co.is_followed ? 'Monitored' : 'Monitor' }}</span>
                </Button>
              </div>
            </div>
          </Card>
        </div>
      </main>
    </div>
  </div>
</template>
