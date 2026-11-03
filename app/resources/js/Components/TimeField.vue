<script setup>
// Custom time picker: two listboxes (hour, minute). Value is 'HH:MM'.
import { computed } from 'vue'
import RSelect from '@/Components/RSelect.vue'

const props = defineProps({ modelValue: { type: String, default: '09:00' }, label: { type: String, default: 'Time' } })
const emit = defineEmits(['update:modelValue'])

const hour = computed(() => (props.modelValue || '09:00').split(':')[0])
const minute = computed(() => (props.modelValue || '09:00').split(':')[1])
const hours = Array.from({ length: 24 }, (_, i) => ({ value: String(i).padStart(2, '0'), label: String(i).padStart(2, '0') }))
const minutes = computed(() => {
  const base = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'))
  if (!base.includes(minute.value)) base.push(minute.value)
  return base.sort().map((v) => ({ value: v, label: v }))
})
</script>

<template>
  <span class="tf" role="group" :aria-label="label">
    <RSelect :model-value="hour" :options="hours" :label="`${label}, hour`" size="sm" @change="(v) => emit('update:modelValue', `${v}:${minute}`)" />
    <span class="muted">:</span>
    <RSelect :model-value="minute" :options="minutes" :label="`${label}, minute`" size="sm" @change="(v) => emit('update:modelValue', `${hour}:${v}`)" />
  </span>
</template>

<style scoped>.tf{display:inline-flex;align-items:center;gap:4px}</style>
