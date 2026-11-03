<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Search, CornerDownLeft } from 'lucide-vue-next'

const open = ref(false)
const q = ref('')
const results = ref({ contacts: [], deals: [], leads: [], companies: [] })
const activeIdx = ref(0)
const inputEl = ref(null)

const flat = computed(() => {
  const r = results.value
  const jump = [
    ...r.deals.map((x) => ({ ...x, group: 'Jump to', kind: 'Deal' })),
    ...r.contacts.map((x) => ({ ...x, group: 'Jump to', kind: 'Contact' })),
    ...r.companies.map((x) => ({ ...x, group: 'Jump to', kind: 'Company' })),
    ...r.leads.map((x) => ({ ...x, group: 'Jump to', kind: 'Lead' })),
  ]
  const actions = []
  const c = r.contacts.find((x) => x.conversation_id)
  const d = r.deals[0]
  if (c) actions.push({ id: `a-reply`, group: 'Actions', title: `Reply to ${c.title} on WhatsApp`, kbd: 'R', href: `/inbox/${c.conversation_id}` })
  if (d) actions.push({ id: 'a-task', group: 'Actions', title: `Create task for ${d.deal_title ?? d.title}`, kbd: 'T', href: `/deals/${d.id}?tab=task` })
  if (d?.next_stage) actions.push({ id: 'a-move', group: 'Actions', title: `Move ${d.deal_title ?? d.title} to ${d.next_stage.name}`, kbd: 'M', move: { deal: d.id, stage: d.next_stage.id } })
  return [...jump, ...actions]
})

const quickLinks = [
  { title: 'Today', href: '/', kbd: 'G T' },
  { title: 'Inbox', href: '/inbox', kbd: 'G I' },
  { title: 'Deals', href: '/pipeline', kbd: 'G D' },
  { title: 'Contacts', href: '/contacts', kbd: 'G C' },
  { title: 'Companies', href: '/companies', kbd: '' },
  { title: 'Leads', href: '/leads', kbd: 'G L' },
  { title: 'Insights', href: '/insights', kbd: 'G N' },
]

let searchTimer = null

watch(q, (value) => {
  clearTimeout(searchTimer)
  if (value.trim().length < 2) {
    results.value = { contacts: [], deals: [], leads: [], companies: [] }
    return
  }
  searchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`/search?q=${encodeURIComponent(value)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
      results.value = await res.json()
      activeIdx.value = 0
    } catch {
      /* palette stays usable */
    }
  }, 180)
})

function show() {
  open.value = true
  q.value = ''
  nextTick(() => inputEl.value?.focus())
}

function hide() {
  open.value = false
}

function go(item) {
  hide()
  if (item.move) return router.patch(`/deals/${item.move.deal}/stage`, { stage_id: item.move.stage }, { preserveScroll: true })
  router.get(item.href)
}

function onKeydown(e) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    open.value ? hide() : show()
    return
  }

  // G-then-key navigation (I A: G T/I/D/C/L).
  if (e.key.toLowerCase() === 'g' && !e.metaKey && !e.ctrlKey && !e.target.matches('input, textarea, select')) {
    onceG = true
    setTimeout(() => { onceG = false }, 1200)
    return
  }

  if (onceG) {
    const map = { t: '/', i: '/inbox', d: '/pipeline', c: '/contacts', l: '/leads', n: '/insights' }
    const dest = map[e.key.toLowerCase()]
    if (dest) {
      e.preventDefault()
      onceG = false
      router.get(dest)
    }
    return
  }

  if (!open.value) return

  if (e.key === 'Escape') hide()
  else if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIdx.value = Math.min(activeIdx.value + 1, Math.max(0, flat.value.length - 1))
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIdx.value = Math.max(activeIdx.value - 1, 0)
  } else if (e.key === 'Enter' && flat.value[activeIdx.value]) {
    e.preventDefault()
    go(flat.value[activeIdx.value])
  }
}

let onceG = false

const openFromEvent = () => show()
onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('relay:palette', openFromEvent)
})
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('relay:palette', openFromEvent)
})

defineExpose({ show })
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="pal-bg" @click.self="hide">
      <div class="pal panel" role="dialog" aria-label="Command palette">
        <div class="pal-input">
          <Search class="icon sm" />
          <input
            ref="inputEl"
            v-model="q"
            placeholder="Search deals, contacts, companies…"
            @keydown="onKeydown"
          >
          <kbd>esc</kbd>
        </div>

        <div class="pal-body">
          <template v-if="flat.length">
            <template v-for="(item, i) in flat" :key="item.group + item.id">
              <div v-if="i === 0 || flat[i - 1].group !== item.group" class="grp">{{ item.group }}</div>
              <button
                class="pal-item"
                :class="{ on: i === activeIdx }"
                @mouseenter="activeIdx = i"
                @click="go(item)"
              >
                <span class="t">{{ item.title }}</span>
                <span v-if="item.subtitle && item.group === 'Jump to'" class="muted small sub">{{ item.subtitle }}</span>
                <CornerDownLeft v-if="i === activeIdx && !item.kbd" class="icon xs" style="margin-left:auto" />
                <span v-else-if="item.kind" class="muted small" style="margin-left:auto">{{ item.kind }}</span>
                <kbd v-else-if="item.kbd" style="margin-left:auto">{{ item.kbd }}</kbd>
              </button>
            </template>
          </template>

          <div v-else class="grp" style="padding-top:6px">Jump to</div>
          <button
            v-for="link in (flat.length ? [] : quickLinks)"
            :key="link.title"
            class="pal-item"
            @click="go(link)"
          >
            <span class="t">{{ link.title }}</span>
            <kbd style="margin-left:auto">{{ link.kbd }}</kbd>
          </button>

          <p v-if="q.length >= 2 && !flat.length" class="muted small" style="padding:6px 10px">
            No matches for “{{ q }}”.
          </p>
        </div>
        <div class="pal-foot"><span><kbd>↑</kbd><kbd>↓</kbd> navigate</span><span><kbd>↵</kbd> open</span><span><kbd>esc</kbd> close</span></div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.pal-foot{display:flex;gap:16px;padding:8px 12px;border-top:1px solid var(--line);font-size:12px;color:var(--text-3)}
.pal-foot kbd{margin-right:4px}
.sub{margin-left:8px}
.pal-bg {
  position: fixed;
  inset: 0;
  background: rgba(23, 22, 28, .35);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding-top: 12vh;
  z-index: 100;
}
.pal {
  width: 560px;
  max-height: 60vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--pop);
}
.pal-input {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
}
.pal-input input {
  flex: 1;
  border: 0;
  outline: none;
  font: inherit;
  font-size: 15px;
  background: none;
}
.pal-body { overflow-y: auto; padding: 6px; }
.grp {
  font-size: 11px;
  font-weight: 500;
  color: var(--text-4);
  text-transform: uppercase;
  letter-spacing: .04em;
  padding: 8px 10px 2px;
}
.pal-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border: 0;
  background: none;
  border-radius: var(--r-sm);
  padding: 8px 10px;
  font: inherit;
  font-size: 13px;
  text-align: left;
  color: var(--text);
}
.pal-item.on { background: var(--brand-soft); color: var(--brand-strong); }
.pal-item .t { font-weight: 500; flex: none; }
.pal-item .muted { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
