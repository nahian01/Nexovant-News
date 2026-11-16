<script setup lang="ts">
import { ref, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Alert from '@/components/ui/Alert.vue'
import { searchService, type SearchFilters } from '@/services/api/search.service'
import { Bell, Bookmark, CheckCircle2, Shield } from 'lucide-vue-next'

const props = defineProps<{
  modelValue: boolean
  query: string
  filters?: SearchFilters
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved', record: any): void
}>()

const name = ref('')
const description = ref('')
const alertFrequency = ref<'immediate' | 'daily_digest' | 'weekly_digest' | 'disabled'>('daily_digest')
const channelInApp = ref(true)
const channelEmail = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      name.value = `Monitor: ${props.query}`
      description.value = `Automated tracking for verified developments regarding "${props.query}".`
      errorMessage.value = ''
      successMessage.value = ''
    }
  }
)

const handleSave = async () => {
  if (!name.value.trim()) {
    errorMessage.value = 'Please provide a name for this saved search.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    const channels = []
    if (channelInApp.value) channels.push('in_app')
    if (channelEmail.value) channels.push('email')

    const saved = await searchService.createSavedSearch({
      name: name.value.trim(),
      description: description.value.trim(),
      query: props.query,
      filters: props.filters,
      alert_frequency: alertFrequency.value,
      delivery_channels: channels,
      is_active: true,
    })

    successMessage.value = 'Saved search created. Automated intelligence monitoring is now active.'
    emit('saved', saved)
    setTimeout(() => {
      emit('update:modelValue', false)
    }, 1200)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to save search monitor.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Modal
    :is-open="modelValue"
    max-width="md"
    @close="emit('update:modelValue', false)"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <div class="p-2 rounded-lg bg-primary-500/10 text-primary-400 border border-primary-500/20">
          <Bookmark class="w-5 h-5" />
        </div>
        <div>
          <h3 class="text-base font-medium text-white font-serif">
            Save Search & Configure Monitoring
          </h3>
          <p class="text-xs text-neutral-400">
            Receive automated alerts when newly verified intelligence matches your search criteria.
          </p>
        </div>
      </div>
    </template>

    <div class="space-y-4">
      <Alert v-if="successMessage" variant="success">
        {{ successMessage }}
      </Alert>
      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-1">
          Monitor Name
        </label>
        <Input v-model="name" placeholder="e.g. EUV High-NA Tool Deliveries" class="w-full text-xs" />
      </div>

      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-1">
          Description (Optional)
        </label>
        <textarea
          v-model="description"
          rows="2"
          class="w-full rounded-md bg-neutral-900 border border-neutral-800 text-xs text-neutral-200 p-2 focus:border-primary-500 focus:outline-none"
          placeholder="Brief description of the monitoring objective..."
        />
      </div>

      <div class="p-3 rounded-lg bg-neutral-900 border border-neutral-800 space-y-2">
        <div class="text-xs font-medium text-neutral-300">Active Search Criteria</div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-neutral-400">Query:</span>
          <span class="text-xs font-mono font-medium text-primary-400">"{{ query }}"</span>
        </div>
      </div>

      <!-- Alert Frequency -->
      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-2">
          Alert Frequency
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="p-2.5 rounded-lg border text-left text-xs transition-colors"
            :class="alertFrequency === 'daily_digest' ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="alertFrequency = 'daily_digest'"
          >
            <div class="font-medium">Daily Digest</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Summary of matching signals</div>
          </button>

          <button
            type="button"
            class="p-2.5 rounded-lg border text-left text-xs transition-colors"
            :class="alertFrequency === 'immediate' ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="alertFrequency = 'immediate'"
          >
            <div class="font-medium">Immediate Alert</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Real-time alert on match</div>
          </button>

          <button
            type="button"
            class="p-2.5 rounded-lg border text-left text-xs transition-colors"
            :class="alertFrequency === 'weekly_digest' ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="alertFrequency = 'weekly_digest'"
          >
            <div class="font-medium">Weekly Digest</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">Weekly curated brief</div>
          </button>

          <button
            type="button"
            class="p-2.5 rounded-lg border text-left text-xs transition-colors"
            :class="alertFrequency === 'disabled' ? 'border-primary-500 bg-primary-500/10 text-white' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="alertFrequency = 'disabled'"
          >
            <div class="font-medium">Saved Only</div>
            <div class="text-[11px] text-neutral-500 mt-0.5">No automated notifications</div>
          </button>
        </div>
      </div>

      <!-- Delivery Channels -->
      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-2">
          Notification Channels
        </label>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 cursor-pointer text-xs text-neutral-300">
            <input v-model="channelInApp" type="checkbox" class="rounded border-neutral-700 bg-neutral-800 text-primary-500 focus:ring-0" />
            <span>In-App Notification Drawer</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer text-xs text-neutral-300">
            <input v-model="channelEmail" type="checkbox" class="rounded border-neutral-700 bg-neutral-800 text-primary-500 focus:ring-0" />
            <span>Email Notification</span>
          </label>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button variant="outline" size="sm" @click="emit('update:modelValue', false)">
          Cancel
        </Button>
        <Button size="sm" :loading="isSubmitting" @click="handleSave">
          Activate Monitor
        </Button>
      </div>
    </template>
  </Modal>
</template>
