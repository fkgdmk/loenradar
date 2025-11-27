<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
  disabled?: boolean
}>()

const emits = defineEmits<{
  select: []
}>()

const handleSelect = () => {
  if (props.disabled) return
  emits('select')
}
</script>

<template>
  <button
    type="button"
    data-slot="command-item"
    :disabled="props.disabled"
    :class="cn(
      'relative flex w-full cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-left text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-50',
      props.class
    )"
    @click="handleSelect"
  >
    <slot />
  </button>
</template>

