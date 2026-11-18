<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { authService } from '@/services/api/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import Alert from '@/components/ui/Alert.vue'

const route = useRoute()
const router = useRouter()

const email = ref('')
const token = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const isLoading = ref(false)
const localError = ref('')
const successMessage = ref('')
const isSuccess = ref(false)

onMounted(() => {
  token.value = (route.query.token as string) || ''
  email.value = (route.query.email as string) || ''

  if (!token.value || !email.value) {
    localError.value =
      'Invalid password reset link. Please request a new verification link from the password recovery page.'
  }
})

const handleResetPassword = async () => {
  localError.value = ''
  successMessage.value = ''

  if (!token.value || !email.value) {
    localError.value = 'Missing authentication reset token or email address.'
    return
  }

  if (password.value.length < 8) {
    localError.value = 'Password must be at least 8 characters in length.'
    return
  }

  if (password.value !== passwordConfirmation.value) {
    localError.value = 'Password confirmation does not match the entered password.'
    return
  }

  isLoading.value = true
  try {
    const res = await authService.resetPassword({
      token: token.value,
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    successMessage.value =
      res.message || 'Credentials updated successfully. You may now sign in with your new password.'
    isSuccess.value = true
  } catch (err: any) {
    localError.value =
      err.response?.data?.message ||
      err.response?.data?.error?.message ||
      'Failed to reset password. The security token may be invalid or expired.'
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
        Secure Credential Reset
      </h2>
    </div>

    <!-- Reset Password Card -->
    <div class="w-full max-w-md">
      <Card padding="lg">
        <div class="mb-6">
          <h3 class="font-serif text-xl font-medium text-nexo-text-primary">
            Set New Password
          </h3>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Establish new executive credentials for account <span class="text-nexo-accent-cyan font-mono">{{ email || 'verified subscriber' }}</span>.
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

        <!-- Success State -->
        <div
          v-if="isSuccess"
          class="p-5 bg-[#0A2540]/60 border border-nexo-accent-cyan/40 rounded-[2px] space-y-4"
        >
          <div class="flex items-start space-x-3">
            <div class="w-7 h-7 rounded-full bg-nexo-accent-cyan/20 border border-nexo-accent-cyan/60 flex items-center justify-center flex-shrink-0 text-nexo-accent-cyan text-sm">
              ✓
            </div>
            <div class="text-xs space-y-1">
              <p class="font-medium text-nexo-text-primary text-sm">
                Password Reset Successfully
              </p>
              <p class="text-nexo-text-secondary leading-relaxed">
                {{ successMessage }}
              </p>
            </div>
          </div>

          <div class="pt-3 border-t border-nexo-border/50">
            <Button
              variant="primary"
              size="md"
              block
              @click="router.push('/login')"
            >
              Sign In with New Password
            </Button>
          </div>
        </div>

        <!-- Form State -->
        <form v-else class="space-y-4" @submit.prevent="handleResetPassword">
          <Input
            id="reset-email"
            v-model="email"
            type="email"
            label="Corporate / Executive Email"
            readonly
            disabled
          />

          <Input
            id="new-password"
            v-model="password"
            type="password"
            label="New Executive Password"
            placeholder="Minimum 8 characters"
            required
            autocomplete="new-password"
          />

          <Input
            id="confirm-new-password"
            v-model="passwordConfirmation"
            type="password"
            label="Confirm New Password"
            placeholder="Re-type new password"
            required
            autocomplete="new-password"
          />

          <div class="pt-2">
            <Button
              type="submit"
              variant="primary"
              size="md"
              block
              :loading="isLoading"
              :disabled="!token || !email"
            >
              Update Password
            </Button>
          </div>

          <div class="pt-2 text-center text-xs text-nexo-text-secondary">
            Need a fresh link?
            <RouterLink to="/forgot-password" class="text-nexo-accent-cyan hover:underline ml-1">
              Request new link
            </RouterLink>
          </div>
        </form>
      </Card>
    </div>
  </div>
</template>
