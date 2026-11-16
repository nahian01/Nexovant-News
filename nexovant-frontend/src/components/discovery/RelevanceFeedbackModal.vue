<script setup lang="ts">
import { ref } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import Alert from '@/components/ui/Alert.vue'
import { searchService } from '@/services/api/search.service'
import { ThumbsUp, ThumbsDown, AlertCircle, HelpCircle } from 'lucide-vue-next'

const props = defineProps<{
  modelValue: boolean
  query: string
  resultId: string
  resultTitle: string
  resultType: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'feedback-submitted'): void
}>()

const selectedRating = ref<'relevant' | 'not_relevant' | 'incorrect_result' | 'missing_information'>('relevant')
const comments = ref('')
const isSubmitting = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const handleSubmit = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await searchService.submitFeedback({
      query: props.query,
      result_id: props.resultId,
      result_type: props.resultType,
      feedback_rating: selectedRating.value,
      comments: comments.value ? comments.value.trim() : undefined,
    })

    successMessage.value = 'Thank you for your feedback. Our ranking models appreciate the signal.'
    emit('feedback-submitted')
    setTimeout(() => {
      emit('update:modelValue', false)
      comments.value = ''
      successMessage.value = ''
    }, 1200)
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to submit feedback.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Modal
    :is-open="modelValue"
    max-width="sm"
    @close="emit('update:modelValue', false)"
  >
    <template #header>
      <div>
        <h3 class="text-sm font-medium text-white font-serif">
          Search Relevance Feedback
        </h3>
        <p class="text-xs text-neutral-400 truncate max-w-sm">
          {{ resultTitle }}
        </p>
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
        <label class="block text-xs font-medium text-neutral-300 mb-2">
          How relevant was this intelligence record?
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            class="p-2 rounded-lg border text-xs flex items-center gap-2 transition-colors"
            :class="selectedRating === 'relevant' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="selectedRating = 'relevant'"
          >
            <ThumbsUp class="w-3.5 h-3.5" />
            <span>Highly Relevant</span>
          </button>

          <button
            type="button"
            class="p-2 rounded-lg border text-xs flex items-center gap-2 transition-colors"
            :class="selectedRating === 'not_relevant' ? 'border-neutral-500 bg-neutral-800 text-neutral-200' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="selectedRating = 'not_relevant'"
          >
            <ThumbsDown class="w-3.5 h-3.5" />
            <span>Not Relevant</span>
          </button>

          <button
            type="button"
            class="p-2 rounded-lg border text-xs flex items-center gap-2 transition-colors"
            :class="selectedRating === 'incorrect_result' ? 'border-rose-500 bg-rose-500/10 text-rose-400' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="selectedRating = 'incorrect_result'"
          >
            <AlertCircle class="w-3.5 h-3.5" />
            <span>Incorrect Fact</span>
          </button>

          <button
            type="button"
            class="p-2 rounded-lg border text-xs flex items-center gap-2 transition-colors"
            :class="selectedRating === 'missing_information' ? 'border-amber-500 bg-amber-500/10 text-amber-400' : 'border-neutral-800 bg-neutral-900 text-neutral-400 hover:border-neutral-700'"
            @click="selectedRating = 'missing_information'"
          >
            <HelpCircle class="w-3.5 h-3.5" />
            <span>Missing Context</span>
          </button>
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-1">
          Additional Context (Optional)
        </label>
        <textarea
          v-model="comments"
          rows="2"
          class="w-full rounded-md bg-neutral-900 border border-neutral-800 text-xs text-neutral-200 p-2 focus:border-primary-500 focus:outline-none"
          placeholder="Why was this result helpful or unhelpful?"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button variant="outline" size="sm" @click="emit('update:modelValue', false)">
          Cancel
        </Button>
        <Button size="sm" :loading="isSubmitting" @click="handleSubmit">
          Submit Feedback
        </Button>
      </div>
    </template>
  </Modal>
</template>
