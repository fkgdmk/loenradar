<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <!-- Radar-ringe og arm -->
  <g fill="none" stroke="#000000" stroke-width="4" stroke-linecap="round">
    <circle cx="50" cy="50" r="40" />
    <circle cx="50" cy="50" r="27" />
    <circle cx="50" cy="50" r="14" />
    <line x1="50" y1="50" x2="82" y2="32" />
  </g>

  <!-- Radar-prik -->
  <circle cx="74" cy="37" r="6" fill="#000000"  />
</svg>
</template>
