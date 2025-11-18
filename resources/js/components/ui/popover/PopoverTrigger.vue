<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { PopoverTrigger, type PopoverTriggerProps, useForwardProps } from 'reka-ui'

const props = defineProps<PopoverTriggerProps & {
  class?: HTMLAttributes['class']
}>()

const forwardedProps = useForwardProps(props)
</script>

<template>
  <PopoverTrigger
    data-slot="popover-trigger"
    v-bind="forwardedProps"
    :class="cn(props.class)"
  >
    <slot />
  </PopoverTrigger>
</template>

