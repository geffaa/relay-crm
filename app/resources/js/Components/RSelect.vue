<script setup>
// Custom single select (listbox pattern). Replaces the native <select> everywhere.
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import { place } from '@/lib/popover'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: Array, required: true }, // [{ value, label, hint? }]
  placeholder: { type: String, default: 'Select…' },
  label: { type: String, default: '' }, // accessible name
  size: { type: String, default: '' }, // '', 'sm'
  disabled: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
  footer: { type: Object, default: null }, // { label, hint } row shown under the options
})
const emit = defineEmits(['update:modelValue', 'change'])

const initials = (n) => String(n).split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()
const uid = `rs-${Math.random().toString(36).slice(2, 8)}`
const open = ref(false)
const active = ref(0)
const root = ref(null)
const list = ref(null)
const pos = ref({})
const reposition = () => { const b = root.value?.querySelector('button'); if (b) pos.value = place(b, { height: 260 }) }
let typed = ''
let typedTimer = null

const query = ref('')
const searchable = computed(() => props.options.length > 12)
const shown = computed(() => {
  const q = query.value.trim().toLowerCase()
  return q ? props.options.filter((o) => String(o.label).toLowerCase().includes(q)) : props.options
})
watch(query, () => { active.value = 0 })
const selected = computed(() => props.options.find((o) => o.value === props.modelValue))

function show() {
  if (props.disabled) return
  reposition()
  open.value = true
  query.value = ''
  active.value = Math.max(0, props.options.findIndex((o) => o.value === props.modelValue))
  nextTick(() => { list.value?.querySelector('.rsel-q')?.focus(); list.value?.querySelector('[data-active=true]')?.scrollIntoView({ block: 'nearest' }) })
}
function hide() { open.value = false }
function pick(o) {
  emit('update:modelValue', o.value)
  emit('change', o.value)
  hide()
  root.value?.querySelector('button')?.focus()
}
function move(d) {
  if (!shown.value.length) return
  active.value = (active.value + d + shown.value.length) % shown.value.length
  nextTick(() => list.value?.querySelector('[data-active=true]')?.scrollIntoView({ block: 'nearest' }))
}
function onKey(e) {
  if (!open.value) {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); show() }
    return
  }
  if (e.key === 'ArrowDown') { e.preventDefault(); move(1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1) }
  else if (e.key === 'Home') { e.preventDefault(); active.value = 0 }
  else if (e.key === 'End') { e.preventDefault(); active.value = shown.value.length - 1 }
  else if (e.key === 'Enter' || (e.key === ' ' && !searchable.value)) { e.preventDefault(); if (shown.value[active.value]) pick(shown.value[active.value]) }
  else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); hide() }
  else if (e.key === 'Tab') hide()
  else if (e.key.length === 1 && !searchable.value) {
    typed += e.key.toLowerCase()
    clearTimeout(typedTimer)
    typedTimer = setTimeout(() => (typed = ''), 600)
    const i = props.options.findIndex((o) => String(o.label).toLowerCase().startsWith(typed))
    if (i >= 0) active.value = i
  }
}
function outside(e) { if (root.value && !root.value.contains(e.target) && !list.value?.contains(e.target)) hide() }
watch(open, (v) => {
  const m = v ? 'addEventListener' : 'removeEventListener'
  document[m]('pointerdown', outside)
  window[m]('resize', reposition)
  window[m]('scroll', reposition, true)
})
onBeforeUnmount(() => { document.removeEventListener('pointerdown', outside); window.removeEventListener('resize', reposition); window.removeEventListener('scroll', reposition, true) })
</script>

<template>
  <div ref="root" class="rsel" :class="[size, { block, open }]">
    <button
      type="button"
      class="rsel-btn"
      role="combobox"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-controls="`${uid}-list`"
      :aria-label="label || undefined"
      :aria-activedescendant="open && shown.length ? `${uid}-${active}` : undefined"
      :disabled="disabled"
      @click="open ? hide() : show()"
      @keydown="onKey"
    >
      <span v-if="selected?.avatar" class="av xs rsel-av" :class="`av-${(String(selected.label).charCodeAt(0) % 7) + 1}`">{{ initials(selected.label) }}</span><span class="rsel-v" :class="{ ph: !selected }">{{ selected ? selected.label : placeholder }}</span>
      <ChevronDown class="icon xs" aria-hidden="true" />
    </button>
    <Teleport to="body"><ul v-if="open" :id="`${uid}-list`" ref="list" class="rsel-list pop" role="listbox" :style="pos" :aria-label="label || undefined">
      <li v-if="searchable" class="rsel-s" role="presentation"><input v-model="query" class="rsel-q" type="search" placeholder="Search…" aria-label="Filter options" autocomplete="off" @keydown="onKey"></li>
      <li
        v-for="(o, i) in shown"
        :id="`${uid}-${i}`"
        :key="String(o.value)"
        role="option"
        :aria-selected="o.value === modelValue"
        :data-active="i === active"
        @pointerenter="active = i"
        @pointerdown.prevent="pick(o)"
      >
        <span v-if="o.avatar" class="av xs" :class="`av-${(String(o.label).charCodeAt(0) % 7) + 1}`">{{ initials(o.label) }}</span>
        <span class="rsel-l">{{ o.label }}<small v-if="o.hint && !o.avatar">{{ o.hint }}</small></span>
        <small v-if="o.hint && o.avatar" class="rsel-h">{{ o.hint }}</small>
        <Check v-if="o.value === modelValue" class="icon xs" aria-hidden="true" />
      </li>
      <li v-if="!shown.length" class="rsel-none" role="presentation">No matches</li>
      <li v-if="footer" class="rsel-foot" role="presentation"><span>{{ footer.label }}</span><small>{{ footer.hint }}</small></li>
    </ul></Teleport>
  </div>
</template>
