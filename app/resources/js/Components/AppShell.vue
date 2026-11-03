<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import CommandPalette from '@/Components/CommandPalette.vue'
import { ago } from '@/lib/format'
import {
  BarChart3, Bell, Building2, CalendarCheck, Ellipsis, Inbox, LogOut, Settings2,
  SquareKanban, Target, Users, Workflow,
} from 'lucide-vue-next'

defineProps({ hasContext: { type: Boolean, default: false } })

const page = usePage()
const badges = computed(() => page.props.badges ?? {})
const notes = computed(() => page.props.notifications ?? { unread: 0, items: [] })
const isAdmin = computed(() => page.props.auth.user?.role === 'admin')

const items = computed(() => [
  { name: 'Today', href: '/', icon: CalendarCheck, badge: badges.value.today },
  { name: 'Inbox', href: '/inbox', icon: Inbox, badge: badges.value.inbox },
  { name: 'Deals', href: '/pipeline', icon: SquareKanban },
  { name: 'Contacts', href: '/contacts', icon: Users },
  { name: 'Companies', href: '/companies', icon: Building2 },
  { name: 'Leads', href: '/leads', icon: Target },
  { name: 'Automation', icon: Workflow, soon: true },
  { name: 'Insights', href: '/insights', icon: BarChart3 },
])
const tabs = computed(() => items.value.filter((i) => ['Today', 'Inbox', 'Deals', 'Contacts'].includes(i.name)))
const more = computed(() => items.value.filter((i) => ['Companies', 'Leads', 'Insights'].includes(i.name)))

const bellOpen = ref(false)
const moreOpen = ref(false)
const bellRoot = ref(null)
const moreRoot = ref(null)

function isActive(item) {
  return item.href === '/' ? page.url === '/' : page.url.startsWith(item.href)
}
function initials(name) {
  return (name || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()
}
function openNote(n) {
  bellOpen.value = false
  router.get(`/notifications/${n.id}`)
}
function readAll() {
  router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true })
}
function logout() {
  router.post('/logout')
}
function outside(e) {
  if (bellRoot.value && !bellRoot.value.contains(e.target)) bellOpen.value = false
  if (moreRoot.value && !moreRoot.value.contains(e.target)) moreOpen.value = false
}
function esc(e) {
  if (e.key === 'Escape') { bellOpen.value = false; moreOpen.value = false }
}
onMounted(() => { document.addEventListener('pointerdown', outside); document.addEventListener('keydown', esc) })
onUnmounted(() => { document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', esc) })
</script>

<template>
  <div class="app" :class="{ noctx: !hasContext }">
    <nav class="rail" aria-label="Main">
      <Link class="mk" href="/" aria-label="Relay home">R</Link>

      <template v-for="item in items" :key="item.name">
        <Link
          v-if="!item.soon"
          :href="item.href"
          :aria-current="isActive(item) ? 'page' : undefined"
          :aria-label="item.name"
          :title="item.name"
        >
          <component :is="item.icon" class="icon" />
          <span v-if="item.badge" class="b">{{ item.badge }}</span>
        </Link>
        <span v-else class="soon" role="img" :aria-label="`${item.name}, coming soon`" :title="`${item.name} (coming soon)`">
          <component :is="item.icon" class="icon" />
        </span>
      </template>

      <div class="sp" />

      <div ref="bellRoot" class="bellwrap">
        <button type="button" class="railbtn" aria-label="Notifications" :aria-expanded="bellOpen" title="Notifications" @click="bellOpen = !bellOpen">
          <Bell class="icon" />
          <span v-if="notes.unread" class="b">{{ notes.unread }}</span>
        </button>
        <div v-if="bellOpen" class="pop bellpop" role="dialog" aria-label="Notifications">
          <div class="bellpop-h"><b>Notifications</b><button v-if="notes.unread" type="button" class="btn sm ghost" @click="readAll">Mark all read</button></div>
          <p v-if="!notes.items.length" class="muted small" style="padding:14px 10px">Nothing yet. Mentions and new assignments show up here.</p>
          <button v-for="n in notes.items" :key="n.id" type="button" class="bellitem" :class="{ unread: !n.read }" @click="openNote(n)">
            <i aria-hidden="true" />
            <span><span class="t">{{ n.title }}</span><span class="muted small">{{ ago(n.at) }}</span></span>
          </button>
        </div>
      </div>

      <Link
        v-if="isAdmin"
        href="/settings"
        :aria-current="page.url.startsWith('/settings') ? 'page' : undefined"
        aria-label="Settings"
        title="Settings"
      >
        <Settings2 class="icon" />
      </Link>

      <CommandPalette />

      <button type="button" class="me av av-5" :title="`${page.props.auth.user?.name} · sign out`" :aria-label="`Sign out ${page.props.auth.user?.name}`" @click="logout">{{ initials(page.props.auth.user?.name) }}</button>
    </nav>

    <aside v-if="hasContext" class="ctx">
      <slot name="context" />
    </aside>

    <main class="main">
      <slot />
    </main>

    <nav class="tabbar" aria-label="Main">
      <Link v-for="item in tabs" :key="item.name" :href="item.href" :aria-current="isActive(item) ? 'page' : undefined">
        <span class="ico"><component :is="item.icon" class="icon" /><span v-if="item.badge" class="b">{{ item.badge }}</span></span>
        <span>{{ item.name }}</span>
      </Link>
      <div ref="moreRoot" class="morewrap">
        <button type="button" :aria-expanded="moreOpen" @click="moreOpen = !moreOpen">
          <span class="ico"><Ellipsis class="icon" /></span><span>More</span>
        </button>
        <div v-if="moreOpen" class="pop morepop">
          <Link v-for="item in more" :key="item.name" :href="item.href"><component :is="item.icon" class="icon sm" />{{ item.name }}</Link>
          <Link v-if="isAdmin" href="/settings"><Settings2 class="icon sm" />Settings</Link>
          <button type="button" @click="logout"><LogOut class="icon sm" />Sign out</button>
        </div>
      </div>
    </nav>
  </div>
</template>
