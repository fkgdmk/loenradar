<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
  label?: string
}>()
</script>

<template>
  <div
    data-slot="command-group"
    :class="cn('overflow-hidden text-foreground', props.class)"
  >
    <p
      v-if="props.label"
      class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
    >
      {{ props.label }}
    </p>
    <div class="space-y-1">
      <slot />
    </div>
  </div>
</template>

