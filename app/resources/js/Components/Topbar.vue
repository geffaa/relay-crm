<script setup>
import { Search } from 'lucide-vue-next'

defineProps({ title: { type: String, required: true }, hint: { type: String, default: '' } })
const openPalette = () => window.dispatchEvent(new Event('relay:palette'))
</script>

<template>
  <header class="topbar">
    <h1>{{ title }}</h1>
    <span v-if="hint" class="badge">{{ hint }}</span>
    <slot name="left" />
    <div class="r">
      <button type="button" class="cmd" aria-label="Search or run a command" @click="openPalette">
        <Search class="icon sm" aria-hidden="true" />
        <span>Search or jump to…</span>
        <kbd>⌘K</kbd>
      </button>
      <slot />
    </div>
  </header>
</template>
