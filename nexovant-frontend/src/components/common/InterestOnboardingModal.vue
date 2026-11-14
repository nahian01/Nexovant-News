<script setup lang="ts">
import { ref } from 'vue'
import { Check, Plus } from 'lucide-vue-next'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import { userService } from '@/services/api/user.service'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'completed'): void
}>()

const availableIndustries = ['Semiconductors', 'Generative AI', 'Cloud & Data Centers', 'Healthcare & Biotech', 'Clean Energy', 'Aerospace & Defense']
const availableCompanies = ['NVIDIA Corporation', 'ASML Holding', 'TSMC', 'Microsoft', 'Novartis AG', 'Alphabet']
const availableTopics = ['Foundry & Fabs', 'CEO Transitions', 'Sovereign Compute', 'M&A Antitrust', 'Earnings Disclosures']

const selectedIndustries = ref<string[]>(['Semiconductors', 'Generative AI'])
const selectedCompanies = ref<string[]>(['NVIDIA Corporation'])
const selectedTopics = ref<string[]>(['Foundry & Fabs'])
const isLoading = ref(false)

const toggleItem = (list: string[], item: string) => {
  const idx = list.indexOf(item)
  if (idx > -1) {
    list.splice(idx, 1)
  } else {
    list.push(item)
  }
}

const handleSave = async () => {
  isLoading.value = true
  try {
    await userService.updatePreferences({
      industries: selectedIndustries.value,
      companies: selectedCompanies.value,
      topics: selectedTopics.value,
    })
    emit('completed')
    emit('close')
  } catch {
    emit('close')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <Modal
    :is-open="isOpen"
    title="Tune Your Executive Intelligence Feed"
    max-width="2xl"
    @close="emit('close')"
  >
    <div class="space-y-6 text-left">
      <p class="text-xs text-nexo-text-secondary leading-relaxed">
        Select key sectors, corporations, and topics you actively monitor. Nexovant will weight your personal feed and morning briefs accordingly.
      </p>

      <!-- Industries -->
      <div class="space-y-2">
        <label class="text-[10px] font-mono uppercase text-nexo-text-muted tracking-wider font-bold">
          Key Sectors & Industries
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="ind in availableIndustries"
            :key="ind"
            type="button"
            @click="toggleItem(selectedIndustries, ind)"
            :class="[
              'inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium transition-all border',
              selectedIndustries.includes(ind)
                ? 'bg-emerald-50 border-emerald-300 text-emerald-800 font-semibold shadow-sm'
                : 'bg-white border-nexo-border text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            <Check v-if="selectedIndustries.includes(ind)" class="h-3 w-3 mr-1 text-emerald-700" />
            <Plus v-else class="h-3 w-3 mr-1 text-nexo-text-muted" />
            <span>{{ ind }}</span>
          </button>
        </div>
      </div>

      <!-- Companies -->
      <div class="space-y-2">
        <label class="text-[10px] uppercase text-nexo-text-muted tracking-wider font-bold">
          Tracked Corporations
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="c in availableCompanies"
            :key="c"
            type="button"
            @click="toggleItem(selectedCompanies, c)"
            :class="[
              'inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium transition-all border',
              selectedCompanies.includes(c)
                ? 'bg-emerald-50 border-emerald-300 text-emerald-800 font-semibold shadow-sm'
                : 'bg-white border-nexo-border text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            <Check v-if="selectedCompanies.includes(c)" class="h-3 w-3 mr-1 text-emerald-700" />
            <Plus v-else class="h-3 w-3 mr-1 text-nexo-text-muted" />
            <span>{{ c }}</span>
          </button>
        </div>
      </div>

      <!-- Topics -->
      <div class="space-y-2">
        <label class="text-[10px] uppercase text-nexo-text-muted tracking-wider font-bold">
          Strategic Topics
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="t in availableTopics"
            :key="t"
            type="button"
            @click="toggleItem(selectedTopics, t)"
            :class="[
              'inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium transition-all border',
              selectedTopics.includes(t)
                ? 'bg-emerald-50 border-emerald-300 text-emerald-800 font-semibold shadow-sm'
                : 'bg-white border-nexo-border text-nexo-text-secondary hover:border-nexo-accent-cyan/40 hover:text-nexo-text-primary'
            ]"
          >
            <Check v-if="selectedTopics.includes(t)" class="h-3 w-3 mr-1 text-emerald-700" />
            <Plus v-else class="h-3 w-3 mr-1 text-nexo-text-muted" />
            <span>{{ t }}</span>
          </button>
        </div>
      </div>
    </div>

    <template #footer>
      <Button variant="ghost" size="sm" @click="emit('close')">
        Skip & Explore
      </Button>
      <Button variant="primary" size="sm" :is-loading="isLoading" @click="handleSave">
        Save Intelligence Preferences
      </Button>
    </template>
  </Modal>
</template>
