<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import { apiClient } from '@/services/api/client'

const trends = ref<any[]>([])
const isLoading = ref(true)

const fetchTrends = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get('/trends')
    trends.value = res.data.data
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchTrends()
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <div class="border-b border-nexo-border/60 pb-6">
          <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
            <span>Social Intelligence</span>
            <span>/</span>
            <span>Cross-Platform Listening Engine</span>
          </div>
          <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
            Social Trend Velocity & Narrative Analysis
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Permitted public discussion velocity across X, Reddit, and YouTube audited and separated from confirmed reporting.
          </p>
        </div>

        <!-- Trends Grid -->
        <div class="space-y-4">
          <Card
            v-for="trend in trends"
            :key="trend.id"
            padding="md"
            class="hover:border-nexo-accent-cyan/40"
          >
            <div class="flex flex-col space-y-4">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center space-x-2">
                  <span class="px-2 py-0.5 rounded bg-nexo-accent-cyan/15 text-nexo-accent-cyan font-mono text-xs font-bold border border-nexo-accent-cyan/30">
                    Velocity Score: {{ trend.composite_trend_score }}
                  </span>
                  <Badge variant="developing" size="sm">
                    {{ trend.status.replace('_', ' ') }}
                  </Badge>
                </div>
                <span class="text-xs font-mono text-nexo-text-muted">
                  Cross-Platform Reach: {{ trend.cross_platform_count }} Networks
                </span>
              </div>

              <div>
                <h3 class="font-serif text-xl font-semibold text-nexo-text-primary">
                  {{ trend.title }}
                </h3>
                <p v-if="trend.topic" class="text-xs text-nexo-text-secondary mt-1">
                  Topic: {{ trend.topic.title }}
                  <span v-if="trend.topic.company"> | Entity: {{ trend.topic.company.name }}</span>
                </p>
              </div>

              <!-- Metric Bars -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-nexo-border/60 text-xs">
                <div class="bg-nexo-elevated/30 p-2.5 rounded border border-nexo-border/40">
                  <span class="text-[10px] font-mono text-nexo-text-muted uppercase">Discussion Velocity</span>
                  <span class="text-sm font-mono font-bold text-nexo-text-primary block mt-0.5">
                    {{ trend.velocity_score }} pts
                  </span>
                </div>
                <div class="bg-nexo-elevated/30 p-2.5 rounded border border-nexo-border/40">
                  <span class="text-[10px] font-mono text-nexo-text-muted uppercase">Source Credibility Index</span>
                  <span class="text-sm font-mono font-bold text-nexo-accent-cyan block mt-0.5">
                    {{ trend.credibility_score }} pts
                  </span>
                </div>
                <div class="bg-nexo-elevated/30 p-2.5 rounded border border-nexo-border/40">
                  <span class="text-[10px] font-mono text-nexo-text-muted uppercase">Business Relevance</span>
                  <span class="text-sm font-mono font-bold text-nexo-accent-gold block mt-0.5">
                    {{ trend.business_relevance_score }} pts
                  </span>
                </div>
              </div>
            </div>
          </Card>
        </div>
      </main>
    </div>
  </div>
</template>
