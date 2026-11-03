<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, required: true },
  size: { type: String, default: '' }, // '', 'lg', 'xs'
})

const initials = computed(() =>
  props.name
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase())

const variant = computed(() => {
  let h = 0
  for (const c of props.name) h = (h * 31 + c.charCodeAt(0)) % 7
  return `av-${h + 1}`
})
</script>

<template>
  <span class="av" :class="[variant, size]" :title="name">{{ initials }}</span>
</template>
