<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const remember = ref(false)
const localError = ref('')

const handleLogin = async () => {
  localError.value = ''
  if (!email.value || !password.value) {
    localError.value = 'Please provide both your executive email and password.'
    return
  }

  try {
    await authStore.login({
      email: email.value,
      password: password.value,
      remember: remember.value,
    })
    router.push('/dashboard')
  } catch (err: any) {
    localError.value =
      err.response?.data?.message ||
      err.response?.data?.error?.message ||
      'Invalid executive credentials. Please verify and retry.'
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
        Executive Intelligence Authentication
      </h2>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-md">
      <Card padding="lg">
        <div class="mb-6">
          <h3 class="font-serif text-xl font-medium text-nexo-text-primary">Sign In</h3>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Access your verified briefings, company watchlists, and RAG search.
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

        <form class="space-y-4" @submit.prevent="handleLogin">
          <Input
            id="login-email"
            v-model="email"
            type="email"
            label="Corporate / Executive Email"
            placeholder="alexander.vance@enterprise.com"
            required
          />

          <Input
            id="login-password"
            v-model="password"
            type="password"
            label="Password"
            placeholder="••••••••••••"
            required
          />

          <div class="flex items-center justify-between text-xs pt-1">
            <label class="flex items-center space-x-2 text-nexo-text-secondary cursor-pointer">
              <input
                v-model="remember"
                type="checkbox"
                class="rounded bg-nexo-surface border-nexo-border text-nexo-accent-cyan focus:ring-nexo-accent-cyan"
              />
              <span>Remember session</span>
            </label>

            <RouterLink to="/forgot-password" class="text-nexo-accent-cyan hover:underline">
              Forgot password?
            </RouterLink>
          </div>

          <div class="pt-2">
            <Button
              type="submit"
              variant="primary"
              size="md"
              block
              :loading="authStore.isLoading"
            >
              Sign In to Command Center
            </Button>
          </div>
        </form>

        <template #footer>
          <span class="text-nexo-text-secondary">Don't have an executive account?</span>
          <RouterLink to="/register" class="text-nexo-accent-cyan font-medium hover:underline">
            Register Account
          </RouterLink>
        </template>
      </Card>
    </div>
  </div>
</template>
