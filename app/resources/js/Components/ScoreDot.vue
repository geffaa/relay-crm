<script setup>
const props = defineProps({
  score: { type: Number, default: null },
})

function color(score) {
  if (score == null) return null
  if (score >= 70) return '#16A34A'
  if (score >= 40) return '#D9930D'
  return '#A3A0AE'
}
</script>

<template>
  <span v-if="props.score != null" class="score">
    <i :style="{ background: color(props.score) }" />
    {{ props.score }}
  </span>
</template>
