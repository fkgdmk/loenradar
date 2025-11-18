<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="command-list"
    :class="cn('max-h-[300px] overflow-y-auto overflow-x-hidden p-1', props.class)"
  >
    <slot />
  </div>
</template>

