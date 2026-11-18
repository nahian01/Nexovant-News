<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { authService } from '@/services/api/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import Alert from '@/components/ui/Alert.vue'

const email = ref('')
const isLoading = ref(false)
const localError = ref('')
const successMessage = ref('')
const isSubmitted = ref(false)

const handleForgotPassword = async () => {
  localError.value = ''
  successMessage.value = ''

  if (!email.value) {
    localError.value = 'Please enter your corporate or executive email address.'
    return
  }

  isLoading.value = true
  try {
    const res = await authService.forgotPassword({ email: email.value.trim() })
    successMessage.value = res.message || 'Password reset link has been dispatched to your email.'
    isSubmitted.value = true
  } catch (err: any) {
    localError.value =
      err.response?.data?.message ||
      err.response?.data?.error?.message ||
      'Unable to process password reset request. Please verify network connectivity.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 text-left">
    <!-- Brand Header -->
    <div class="mb-8 text-center flex flex-col items-center">
      <RouterLink to="/" class="flex items-center space-x-3 mb-2">
        <div class="w-9 h-9 rounded bg-nexo-surface border border-nexo-accent-cyan flex items-center justify-center text-nexo-accent-cyan font-serif font-bold text-xl shadow-cyan-glow">
          N
        </div>
        <span class="font-serif text-2xl font-bold tracking-tight text-nexo-text-primary">
          NEXOVANT
        </span>
      </RouterLink>
      <h2 class="text-sm font-mono tracking-widest text-nexo-accent-cyan uppercase">
        Executive Credential Recovery
      </h2>
    </div>

    <!-- Forgot Password Card -->
    <div class="w-full max-w-md">
      <Card padding="lg">
        <div class="mb-6">
          <h3 class="font-serif text-xl font-medium text-nexo-text-primary">
            Reset Password
          </h3>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Enter your corporate email. We'll send an authentication token to restore command center access.
          </p>
        </div>

        <Alert
          v-if="localError"
          variant="danger"
          class="mb-5"
          dismissible
          @dismiss="localError = ''"
        >
          {{ localError }}
        </Alert>

        <div
          v-if="isSubmitted"
          class="p-4 bg-[#0A2540]/60 border border-nexo-accent-cyan/40 rounded-[2px] space-y-3 mb-5"
        >
          <div class="flex items-start space-x-3">
            <div class="w-6 h-6 rounded-full bg-nexo-accent-cyan/20 border border-nexo-accent-cyan/60 flex items-center justify-center flex-shrink-0 text-nexo-accent-cyan text-xs">
              ✓
            </div>
            <div class="text-xs space-y-1">
              <p class="font-medium text-nexo-text-primary">
                Reset Link Dispatched
              </p>
              <p class="text-nexo-text-secondary leading-relaxed">
                If an executive account matches <span class="text-nexo-accent-cyan font-mono">{{ email }}</span>, an email has been sent from <span class="font-mono text-nexo-text-primary">noreply@nexvant.si</span> via Brevo Relay.
              </p>
              <p class="text-[11px] text-nexo-text-muted pt-1">
                Security notice: The cryptographic link expires in 60 minutes.
              </p>
            </div>
          </div>

          <div class="pt-2 border-t border-nexo-border/50 flex justify-between items-center text-xs">
            <button
              type="button"
              class="text-nexo-accent-cyan hover:underline"
              @click="isSubmitted = false"
            >
              Enter different email
            </button>
            <RouterLink to="/login" class="text-nexo-text-secondary hover:text-nexo-text-primary">
              Return to Sign In →
            </RouterLink>
          </div>
        </div>

        <form v-else class="space-y-4" @submit.prevent="handleForgotPassword">
          <Input
            id="forgot-email"
            v-model="email"
            type="email"
            label="Corporate / Executive Email"
            placeholder="alexander.vance@enterprise.com"
            required
            autocomplete="email"
          />

          <div class="pt-2">
            <Button
              type="submit"
              variant="primary"
              size="md"
              block
              :loading="isLoading"
            >
              Dispatch Reset Link
            </Button>
          </div>

          <div class="pt-2 text-center text-xs text-nexo-text-secondary">
            Remembered your credentials?
            <RouterLink to="/login" class="text-nexo-accent-cyan hover:underline ml-1">
              Sign In
            </RouterLink>
          </div>
        </form>
      </Card>
    </div>
  </div>
</template>
