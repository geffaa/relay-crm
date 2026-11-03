<script setup>
import InboxWorkspace from '@/Components/InboxWorkspace.vue'
defineOptions({ inheritAttrs: false })
</script>

<template>
  <InboxWorkspace v-bind="$attrs" />
</template>
