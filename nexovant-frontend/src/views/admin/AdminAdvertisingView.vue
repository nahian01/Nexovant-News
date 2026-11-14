<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import Card from '@/components/ui/Card.vue'
import Table from '@/components/ui/Table.vue'
import Button from '@/components/ui/Button.vue'
import { adminService } from '@/services/api/admin.service'

const campaigns = ref<any[]>([])
const summary = ref<any>(null)
const isLoading = ref(true)
const statusMessage = ref('')

const headers = ['Campaign & Advertiser', 'Placement', 'Budget & Spend', 'Impressions', 'CTR', 'Status', 'Actions']

const fetchAds = async () => {
  isLoading.value = true
  try {
    const res = await adminService.getAds()
    campaigns.value = res.data
    summary.value = res.summary
  } catch {
    //
  } finally {
    isLoading.value = false
  }
}

const handleStatus = async (id: string, status: string) => {
  try {
    const res = await adminService.updateAdStatus(id, status)
    statusMessage.value = res.message
    setTimeout(() => (statusMessage.value = ''), 4000)
    fetchAds()
  } catch {
    //
  }
}

onMounted(() => {
  fetchAds()
})
</script>

<template>
  <AdminLayout>
    <div class="space-y-6 text-left">
      <div class="flex items-center justify-between border-b border-nexo-border/80 pb-4">
        <div>
          <h1 class="editorial-title text-2xl font-medium text-nexo-text-primary">
            Sponsored Intelligence & Advertising Control
          </h1>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Enterprise campaigns, sponsored brief creative approvals, clickthrough analytics, and strict editorial separation.
          </p>
        </div>

        <Button variant="secondary" size="sm" @click="fetchAds">
          Refresh Campaigns
        </Button>
      </div>

      <div
        v-if="statusMessage"
        class="p-3 bg-nexo-surface border border-nexo-status-confirmed text-nexo-status-confirmed text-xs font-mono rounded"
      >
        {{ statusMessage }}
      </div>

      <!-- Telemetry Stats -->
      <div v-if="summary" class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-mono">
        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Active Campaigns</span>
          <div class="text-2xl font-bold text-nexo-text-primary mt-1">{{ summary.active_campaigns }}</div>
          <span class="text-nexo-accent-gold text-[10px]">${{ summary.total_revenue_usd }} Revenue</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Total Impressions</span>
          <div class="text-2xl font-bold text-nexo-accent-cyan mt-1">{{ summary.total_impressions_30d }}</div>
          <span class="text-nexo-text-secondary text-[10px]">30-Day Window</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Average CTR</span>
          <div class="text-2xl font-bold text-nexo-status-confirmed mt-1">{{ summary.average_ctr }}</div>
          <span class="text-nexo-text-secondary text-[10px]">High Executive Engagement</span>
        </Card>

        <Card class="p-4 border border-nexo-border">
          <span class="text-[10px] text-nexo-text-muted uppercase">Editorial Guardrail</span>
          <div class="text-2xl font-bold text-nexo-text-primary mt-1">Enforced</div>
          <span class="text-nexo-status-confirmed text-[10px]">Zero Ranking Bias</span>
        </Card>
      </div>

      <!-- Campaigns Table -->
      <Table :headers="headers" :is-empty="campaigns.length === 0">
        <tr v-for="c in campaigns" :key="c.id" class="hover:bg-nexo-surface/40 transition-colors">
          <td class="py-3.5 px-4 font-semibold text-nexo-text-primary">
            <div>{{ c.campaign_name }}</div>
            <div class="text-[10px] font-mono text-nexo-accent-gold">{{ c.advertiser }}</div>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-secondary">
            {{ c.placement_type }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs">
            <span class="text-nexo-text-primary">${{ c.spend_usd }}</span>
            <span class="text-nexo-text-muted block text-[10px]">of ${{ c.budget_usd }}</span>
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-text-primary">
            {{ c.impressions }}
          </td>
          <td class="py-3.5 px-4 font-mono text-xs text-nexo-status-confirmed font-bold">
            {{ c.ctr_percentage }}%
          </td>
          <td class="py-3.5 px-4">
            <span
              :class="[
                'px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase',
                c.status === 'approved' ? 'bg-nexo-status-confirmed/20 text-nexo-status-confirmed' : 'bg-nexo-accent-gold/20 text-nexo-accent-gold'
              ]"
            >
              {{ c.status }}
            </span>
          </td>
          <td class="py-3.5 px-4 font-mono">
            <div class="flex items-center space-x-2">
              <button
                v-if="c.status === 'pending'"
                @click="handleStatus(c.id, 'approved')"
                class="px-2 py-1 rounded bg-nexo-status-confirmed/20 text-nexo-status-confirmed border border-nexo-status-confirmed/40 text-xs font-bold"
              >
                Approve
              </button>
              <button
                v-else
                @click="handleStatus(c.id, 'paused')"
                class="px-2 py-1 rounded bg-nexo-surface hover:bg-nexo-elevated border border-nexo-border text-xs text-nexo-text-secondary"
              >
                Pause
              </button>
            </div>
          </td>
        </tr>
      </Table>
    </div>
  </AdminLayout>
</template>
