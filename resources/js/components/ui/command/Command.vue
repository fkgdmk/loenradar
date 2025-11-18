<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="command"
    :class="cn(
      'flex h-full w-full flex-col overflow-hidden rounded-md border bg-popover text-popover-foreground',
      props.class
    )"
  >
    <slot />
  </div>
</template>

