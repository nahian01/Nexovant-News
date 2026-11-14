<script setup lang="ts">
import { ref } from 'vue'
import Sidebar from '@/components/layout/Sidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import Card from '@/components/ui/Card.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'

const isGenerating = ref(false)

const reports = ref([
  {
    id: 'rep-01',
    title: 'Daily Global Executive Intelligence Briefing',
    type: 'Daily Publication',
    date: 'November 14, 2026',
    size: '1.2 MB',
    status: 'Ready',
  },
  {
    id: 'rep-02',
    title: 'Weekly Cross-Border M&A & Leadership Dossier',
    type: 'Weekly Dossier',
    date: 'November 10, 2026',
    size: '3.4 MB',
    status: 'Ready',
  },
  {
    id: 'rep-03',
    title: 'Special Brief: European Sovereign Semiconductor Mandates',
    type: 'Custom Report',
    date: 'November 08, 2026',
    size: '2.1 MB',
    status: 'Ready',
  },
])

const handleDownload = (report: any) => {
  // Simulates signed URL download
  window.open('#download', '_blank')
}
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex text-left">
    <Sidebar />

    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader />

      <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto max-w-7xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-nexo-border/60 pb-6">
          <div>
            <div class="flex items-center space-x-2 text-xs font-mono text-nexo-accent-cyan uppercase mb-1">
              <span>Executive Reporting</span>
              <span>/</span>
              <span>ReportLab PDF Publications</span>
            </div>
            <h1 class="editorial-title text-2xl sm:text-3xl font-medium text-nexo-text-primary">
              Publications & Intelligence Reports
            </h1>
            <p class="text-xs text-nexo-text-secondary mt-1">
              Print-ready executive publications synthesized directly from verified disclosures and RAG knowledge graphs.
            </p>
          </div>

          <Button variant="gold" size="sm" :loading="isGenerating">
            + Generate Custom On-Demand Report
          </Button>
        </div>

        <div class="space-y-4">
          <Card
            v-for="rep in reports"
            :key="rep.id"
            padding="md"
          >
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div class="space-y-1">
                <div class="flex items-center space-x-2">
                  <Badge variant="gold" size="sm">{{ rep.type }}</Badge>
                  <span class="text-xs font-mono text-nexo-text-muted">{{ rep.date }}</span>
                </div>
                <h3 class="font-serif text-lg font-bold text-nexo-text-primary">
                  {{ rep.title }}
                </h3>
                <span class="text-xs font-mono text-nexo-text-secondary">
                  PDF Document • {{ rep.size }} • Cloudflare R2 Signed Delivery
                </span>
              </div>

              <Button variant="secondary" size="sm" @click="handleDownload(rep)">
                Download Signed PDF
              </Button>
            </div>
          </Card>
        </div>
      </main>
    </div>
  </div>
</template>
