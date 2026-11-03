<script setup>
// Custom date picker (no native date input). Value is 'YYYY-MM-DD' or null.
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-vue-next'
import { place } from '@/lib/popover'

const props = defineProps({
  modelValue: { type: String, default: null },
  placeholder: { type: String, default: 'Pick a date' },
  label: { type: String, default: '' },
  clearable: { type: Boolean, default: true },
  block: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])

const root = ref(null)
const open = ref(false)
const view = ref(startOfMonth(props.modelValue ? parse(props.modelValue) : new Date()))
const focusDay = ref(null)
const pop = ref(null)
const pos = ref({})
const reposition = () => { const b = root.value?.querySelector('.df-btn'); if (b) pos.value = place(b, { height: 340, width: 276 }) }

function parse(s) { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d) }
function iso(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` }
function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1) }

const display = computed(() => props.modelValue
  ? parse(props.modelValue).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
  : '')
const title = computed(() => view.value.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }))
const todayIso = iso(new Date())

const days = computed(() => {
  const first = view.value
  const lead = (first.getDay() + 6) % 7 // Monday first
  const start = new Date(first.getFullYear(), first.getMonth(), 1 - lead)
  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i)
    return { d, iso: iso(d), n: d.getDate(), out: d.getMonth() !== first.getMonth() }
  })
})

function toggle() {
  open.value = !open.value
  if (open.value) {
    reposition()
    view.value = startOfMonth(props.modelValue ? parse(props.modelValue) : new Date())
    focusDay.value = props.modelValue ?? todayIso
    nextTick(focusCell)
  }
}
function shift(n) { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + n, 1) }
function pick(day) { emit('update:modelValue', day.iso); open.value = false }
function clear() { emit('update:modelValue', null); open.value = false }
function focusCell() { pop.value?.querySelector(`[data-day="${focusDay.value}"]`)?.focus() }

function onGridKey(e) {
  const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key]
  if (step === undefined) {
    if (e.key === 'Escape') { e.stopPropagation(); open.value = false; root.value?.querySelector('.df-btn')?.focus() }
    return
  }
  e.preventDefault()
  const cur = parse(focusDay.value)
  const next = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + step)
  focusDay.value = iso(next)
  if (next.getMonth() !== view.value.getMonth()) view.value = startOfMonth(next)
  nextTick(focusCell)
}
function outside(e) { if (root.value && !root.value.contains(e.target) && !pop.value?.contains(e.target)) open.value = false }
watch(open, (v) => {
  const m = v ? 'addEventListener' : 'removeEventListener'
  document[m]('pointerdown', outside)
  window[m]('resize', reposition)
  window[m]('scroll', reposition, true)
})
onBeforeUnmount(() => { document.removeEventListener('pointerdown', outside); window.removeEventListener('resize', reposition); window.removeEventListener('scroll', reposition, true) })
</script>

<template>
  <div ref="root" class="df" :class="{ block }">
    <button type="button" class="df-btn" :aria-label="label || placeholder" aria-haspopup="dialog" :aria-expanded="open" @click="toggle">
      <CalendarDays class="icon sm" aria-hidden="true" />
      <span :class="{ ph: !display }">{{ display || placeholder }}</span>
    </button>
    <Teleport to="body"><div v-if="open" ref="pop" class="df-pop pop" role="dialog" aria-label="Choose a date" :style="pos">
      <div class="df-h">
        <button type="button" class="ib" aria-label="Previous month" @click="shift(-1)"><ChevronLeft class="icon sm" /></button>
        <b aria-live="polite">{{ title }}</b>
        <button type="button" class="ib" aria-label="Next month" @click="shift(1)"><ChevronRight class="icon sm" /></button>
      </div>
      <div class="df-grid" role="grid" :aria-label="title" @keydown="onGridKey">
        <span v-for="w in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="w" class="df-w" aria-hidden="true">{{ w }}</span>
        <button
          v-for="day in days"
          :key="day.iso"
          type="button"
          role="gridcell"
          class="df-d"
          :class="{ out: day.out, today: day.iso === todayIso, on: day.iso === modelValue }"
          :data-day="day.iso"
          :tabindex="day.iso === (focusDay ?? modelValue ?? todayIso) ? 0 : -1"
          :aria-selected="day.iso === modelValue"
          :aria-label="day.d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"
          @click="pick(day)"
        >{{ day.n }}</button>
      </div>
      <div class="df-f">
        <button type="button" class="btn sm ghost" @click="pick({ iso: todayIso })">Today</button>
        <button v-if="clearable && modelValue" type="button" class="btn sm ghost" @click="clear"><X class="icon xs" />Clear</button>
      </div>
    </div></Teleport>
  </div>
</template>
