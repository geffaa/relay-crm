<script setup>
// Custom checkbox: a real button with role="checkbox", so keyboard (Space) and screen readers work.
import { Check } from 'lucide-vue-next'

defineProps({ modelValue: { type: Boolean, default: false } })
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <button type="button" class="cbx" role="checkbox" :aria-checked="modelValue" @click="emit('update:modelValue', !modelValue)">
    <span class="box"><Check v-if="modelValue" class="icon" aria-hidden="true" /></span>
    <span><slot /></span>
  </button>
</template>

<style scoped>
.cbx{border:0;background:none;padding:0;text-align:left;color:var(--text)}
</style>
