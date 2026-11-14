<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

onMounted(async () => {
  if (authStore.token && !authStore.user) {
    await authStore.fetchProfile()
  }
})
</script>

<template>
  <div class="min-h-screen bg-nexo-bg text-nexo-text-primary selection:bg-nexo-accent-cyan/30 selection:text-nexo-text-primary">
    <RouterView />
  </div>
</template>
