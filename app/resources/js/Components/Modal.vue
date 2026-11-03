<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { X } from 'lucide-vue-next'

defineProps({ title: { type: String, required: true }, width: { type: String, default: '440px' } })
const emit = defineEmits(['close'])
const box = ref(null)
let previous = null

function onKey(e) {
  if (e.key === 'Escape') emit('close')
  if (e.key !== 'Tab' || !box.value) return
  const f = [...box.value.querySelectorAll('button, [href], input, textarea, [tabindex]:not([tabindex="-1"])')].filter((el) => !el.disabled && el.offsetParent !== null)
  if (!f.length) return
  const first = f[0]; const last = f[f.length - 1]
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
}
onMounted(() => {
  previous = document.activeElement
  document.addEventListener('keydown', onKey)
  nextTick(() => box.value?.querySelector('input, textarea, button:not(.modal-x)')?.focus())
})
onUnmounted(() => { document.removeEventListener('keydown', onKey); previous?.focus?.() })
</script>

<template>
  <Teleport to="body">
    <div class="modal-bg" @pointerdown.self="emit('close')">
      <div ref="box" class="panel modal" role="dialog" aria-modal="true" :aria-label="title" :style="{ width }">
        <div class="panel-h"><h2>{{ title }}</h2><button type="button" class="ib modal-x" style="margin-left:auto" aria-label="Close" @click="emit('close')"><X class="icon sm" /></button></div>
        <div class="panel-b"><slot /></div>
      </div>
    </div>
  </Teleport>
</template>
