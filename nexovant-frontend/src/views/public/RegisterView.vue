<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { Sparkles } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Card from '@/components/ui/Card.vue'
import Alert from '@/components/ui/Alert.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const accountType = ref<'individual' | 'organization_member'>('individual')
const localError = ref('')
const appliedOffer = ref<string | null>(null)

onMounted(() => {
  if (route.query.offer) {
    appliedOffer.value = route.query.offer as string
  }
  if (route.query.email) {
    email.value = route.query.email as string
  }
})

const handleRegister = async () => {
  localError.value = ''

  if (password.value !== passwordConfirmation.value) {
    localError.value = 'Passwords do not match. Please ensure both fields are identical.'
    return
  }

  try {
    await authStore.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      account_type: accountType.value,
    })
    router.push('/dashboard')
  } catch (err: any) {
    localError.value =
      err.response?.data?.message ||
      err.response?.data?.error?.message ||
      'Registration could not be completed. Please review the inputs and retry.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-nexo-bg flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 py-12 text-left">
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
        Executive Account Provisioning
      </h2>
    </div>

    <!-- Register Card -->
    <div class="w-full max-w-lg">
      <Card padding="lg">
        <div class="mb-6">
          <h3 class="font-serif text-xl font-medium text-nexo-text-primary">Create Account</h3>
          <p class="text-xs text-nexo-text-secondary mt-1">
            Equip yourself or your leadership team with verified global business intelligence.
          </p>
        </div>

        <div v-if="appliedOffer" class="mb-4 bg-nexo-surface border border-nexo-accent-gold/60 rounded p-3 text-xs flex items-center justify-between">
          <div>
            <span class="font-mono font-bold text-nexo-accent-gold uppercase tracking-wider block">
              WELCOME OFFER ACTIVE (30% DISCOUNT)
            </span>
            <span class="text-nexo-text-primary text-[11px]">
              Promotional code <span class="font-mono text-nexo-accent-cyan font-bold">{{ appliedOffer }}</span> applied. 14-day free executive pass unlocked.
            </span>
          </div>
          <Sparkles class="h-5 w-5 text-nexo-accent-gold ml-2 shrink-0" />
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

        <form class="space-y-4" @submit.prevent="handleRegister">
          <!-- Account Type Switcher -->
          <div class="flex flex-col space-y-1.5">
            <label class="text-xs uppercase tracking-wider font-semibold text-nexo-text-secondary">
              Account Workspace Type
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                :class="[
                  'py-2 px-3 text-xs font-medium rounded border transition-all text-center',
                  accountType === 'individual'
                    ? 'bg-nexo-accent-cyan/15 border-nexo-accent-cyan text-nexo-accent-cyan'
                    : 'bg-nexo-surface border-nexo-border text-nexo-text-secondary hover:bg-nexo-elevated'
                ]"
                @click="accountType = 'individual'"
              >
                Individual Executive
              </button>
              <button
                type="button"
                :class="[
                  'py-2 px-3 text-xs font-medium rounded border transition-all text-center',
                  accountType === 'organization_member'
                    ? 'bg-nexo-accent-cyan/15 border-nexo-accent-cyan text-nexo-accent-cyan'
                    : 'bg-nexo-surface border-nexo-border text-nexo-text-secondary hover:bg-nexo-elevated'
                ]"
                @click="accountType = 'organization_member'"
              >
                Corporate / Org Workspace
              </button>
            </div>
          </div>

          <Input
            id="register-name"
            v-model="name"
            label="Full Name & Executive Title"
            placeholder="e.g. Alexander Vance, Managing Partner"
            required
          />

          <Input
            id="register-email"
            v-model="email"
            type="email"
            label="Corporate / Official Email"
            placeholder="alexander.vance@enterprise.com"
            required
          />

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Input
              id="register-password"
              v-model="password"
              type="password"
              label="Password (min 8 chars)"
              placeholder="••••••••••••"
              required
            />
            <Input
              id="register-password-conf"
              v-model="passwordConfirmation"
              type="password"
              label="Confirm Password"
              placeholder="••••••••••••"
              required
            />
          </div>

          <div class="pt-3">
            <Button
              type="submit"
              variant="primary"
              size="md"
              block
              :loading="authStore.isLoading"
            >
              Provision Executive Account
            </Button>
          </div>
        </form>

        <template #footer>
          <span class="text-nexo-text-secondary">Already have an executive account?</span>
          <RouterLink to="/login" class="text-nexo-accent-cyan font-medium hover:underline">
            Sign In
          </RouterLink>
        </template>
      </Card>
    </div>
  </div>
</template>
