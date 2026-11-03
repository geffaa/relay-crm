<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import RSelect from '@/Components/RSelect.vue'
import DateField from '@/Components/DateField.vue'
import Modal from '@/Components/Modal.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import InboxDetails from '@/Components/InboxDetails.vue'
import {
  AlertTriangle, Bot, Check, CheckCheck, ChevronLeft, MoreVertical, Phone, Clock, FileText, Flame, Inbox as InboxIcon, MessageCircle,
  Paperclip, PanelRight, RotateCw, Send, Sparkles, User, UserRound,
} from 'lucide-vue-next'
import { money, scoreColor, shortDate, waited } from '@/lib/format'

const props = defineProps({
  view: String, counts: Object, conversations: Array, channel: Object,
  conversation: { type: Object, default: null }, messages: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] }, contact: Object, scoreDetail: Object,
  openDeal: Object, summary: Object, users: { type: Array, default: () => [] }, roundRobin: { type: Boolean, default: true },
})

const active = computed(() => props.conversation?.id ?? null)
const threadEl = ref(null)
const tab = ref('reply')
const detailsOpen = ref(false)
const flashId = ref(null)

const reply = useForm({ body: '', template_id: null })
const note = useForm({ body: '' })
const task = useForm({ title: '', due_at: null })
const ai = ref({ drafting: false, summarizing: false, error: null, draftedFrom: null })
const liveSummary = ref(null)
const shownSummary = computed(() => liveSummary.value ?? props.summary)
const seqById = computed(() => Object.fromEntries(props.messages.map((m) => [m.id, m.seq])))
const seqOf = (id) => seqById.value[id] ?? '?'

const views = computed(() => [
  { key: 'unreplied', label: 'Unreplied', icon: Flame, n: props.counts.unreplied },
  { key: 'mine', label: 'Mine', icon: UserRound, n: props.counts.mine },
  { key: 'all', label: 'All open', icon: InboxIcon, n: props.counts.all },
])
const rowHref = (c) => `/inbox/${c.id}?view=${props.view}`

const assignOptions = computed(() => props.users.map((u) => ({ value: u.id, label: u.name, avatar: true, hint: `${u.open ?? 0} open` })))
const rrFooter = computed(() => ({ label: 'Round robin', hint: props.roundRobin ? 'auto' : 'off' }))
const fileInput = ref(null)
function attachFile(e) {
  const file = e.target.files[0]
  e.target.value = ''
  if (!file) return
  router.post(`/inbox/${props.conversation.id}/attach`, { file }, { forceFormData: true, preserveScroll: true, onError: (er) => (ai.value.error = er.file ?? 'Could not attach the file.') })
}
const canEditDraft = ref(false)
const templateOptions = computed(() => props.templates.map((t) => ({ value: t.id, label: t.name, hint: t.body.slice(0, 48) + (t.body.length > 48 ? '…' : '') })))
const windowLabel = computed(() => {
  const c = props.conversation
  if (!c?.window_closes_at) return null
  const diff = new Date(c.window_closes_at) - Date.now()
  if (diff <= 0) return null
  return `${Math.floor(diff / 3600000)} h ${String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0')} min`
})

function rowTime(iso) {
  const d = new Date(iso); const now = new Date()
  if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  if (now - d < 6 * 864e5) return d.toLocaleDateString('en-GB', { weekday: 'short' })
  return shortDate(d)
}
const clock = (iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
function dayLabel(iso) {
  const d = new Date(iso)
  if (d.toDateString() === new Date().toDateString()) return 'Today'
  if (d.toDateString() === new Date(Date.now() - 864e5).toDateString()) return 'Yesterday'
  return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' })
}
const newDay = (i) => i === 0 || new Date(props.messages[i].created_at).toDateString() !== new Date(props.messages[i - 1].created_at).toDateString()

function csrfFetch(url) {
  return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' } })
}
async function draft() {
  ai.value = { ...ai.value, drafting: true, error: null }
  try {
    const res = await csrfFetch(`/inbox/${props.conversation.id}/draft-reply`)
    const json = await res.json()
    if (!res.ok) throw new Error(json.errors?.draft ?? 'Could not draft a reply.')
    tab.value = 'reply'; reply.body = json.draft; reply.template_id = null
    ai.value.draftedFrom = json.sources ?? true
    nextTick(() => document.querySelector('.composer-input')?.focus())
  } catch (e) { ai.value.error = String(e.message ?? e) } finally { ai.value.drafting = false }
}
async function summarize() {
  ai.value = { ...ai.value, summarizing: true, error: null }
  try {
    const res = await csrfFetch(`/inbox/${props.conversation.id}/summarize`)
    const json = await res.json()
    if (!res.ok) throw new Error(json.errors?.summary ?? 'Could not summarize.')
    liveSummary.value = json
  } catch (e) { ai.value.error = String(e.message ?? e) } finally { ai.value.summarizing = false }
}
function send() {
  reply.post(`/inbox/${props.conversation.id}/send`, { preserveScroll: true, onSuccess: () => { reply.reset(); ai.value.draftedFrom = null } })
}
function saveNote() { note.post(`/contacts/${props.contact.id}/notes`, { preserveScroll: true, onSuccess: () => note.reset() }) }
function saveTask() { task.post(`/contacts/${props.contact.id}/tasks`, { preserveScroll: true, onSuccess: () => task.reset() }) }
const retry = (m) => router.post(`/inbox/${props.conversation.id}/messages/${m.id}/retry`, {}, { preserveScroll: true })
const assign = (id) => router.post(`/inbox/${props.conversation.id}/assign`, { user_id: id }, { preserveScroll: true })
const close = () => router.post(`/inbox/${props.conversation.id}/close`)
function pickTemplate(id) {
  const t = props.templates.find((x) => x.id === id)
  if (!t) return
  reply.template_id = id
  reply.body = t.body.replace(/\{\{?1\}?\}/g, props.contact.name.split(' ')[0])
}
function jump(id) {
  detailsOpen.value = false
  flashId.value = id
  nextTick(() => document.getElementById(`msg-${id}`)?.scrollIntoView({ block: 'center', behavior: 'smooth' }))
  setTimeout(() => (flashId.value = null), 1800)
}
function toBottom() { nextTick(() => { if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight }) }
watch(() => props.conversation?.id, () => { liveSummary.value = null; ai.value = { drafting: false, summarizing: false, error: null, draftedFrom: null }; reply.reset(); toBottom() })
watch(() => props.messages.length, toBottom)

function onKey(e) {
  if (e.target.matches('input, textarea')) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey) && tab.value === 'reply' && reply.body) { e.preventDefault(); send() } return }
  if (e.metaKey || e.ctrlKey || e.altKey) return
  const k = e.key.toLowerCase()
  if (k === 'r' && props.conversation) { tab.value = 'reply'; e.preventDefault(); nextTick(() => document.querySelector('.composer-input')?.focus()) }
  else if (k === 'e' && props.conversation) close()
  else if (k === 'j' || k === 'k') {
    const list = props.conversations; if (!list.length) return
    const i = list.findIndex((c) => c.id === active.value)
    const next = list[Math.max(0, Math.min(list.length - 1, i + (k === 'j' ? 1 : -1)))]
    if (next) router.get(rowHref(next), {}, { preserveScroll: true })
  }
}
onMounted(() => { window.addEventListener('keydown', onKey); toBottom() })
onUnmounted(() => window.removeEventListener('keydown', onKey))

const errText = (m) => m.error?.message ?? 'Not delivered'
const topTitle = computed(() => (props.view === 'unreplied' ? 'Unreplied' : props.view === 'mine' ? 'Mine' : 'All open'))
const oldest = computed(() => { const w = props.conversations.filter((c) => c.waiting_minutes !== null).map((c) => c.waiting_minutes); return w.length ? Math.max(...w) : null })
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h"><h1>Inbox</h1></div>
      <div class="views">
        <Link v-for="v in views" :key="v.key" :href="`/inbox?view=${v.key}`" :aria-current="view === v.key ? 'page' : undefined"><component :is="v.icon" class="icon" />{{ v.label }}<span class="n">{{ v.n }}</span></Link>
        <div class="grp">Channels</div>
        <a href="#" @click.prevent><MessageCircle class="icon" />WhatsApp<span class="n">{{ counts.all }}</span></a>
        <span class="soonrow">Email<span class="n">Soon</span></span>
        <span class="soonrow">Instagram<span class="n">Soon</span></span>
      </div>
      <nav class="clist" aria-label="Conversations">
        <Link v-for="c in conversations" :key="c.id" :href="rowHref(c)" class="crow" :class="{ on: c.id === active }" :aria-current="c.id === active ? 'true' : undefined" preserve-scroll>
          <UserAvatar :name="c.contact_name" />
          <span class="mid"><span class="top"><b>{{ c.contact_name }}</b><time>{{ rowTime(c.last_message_at) }}</time></span><span class="pv">{{ c.preview_direction === 'out' ? 'You: ' : '' }}{{ c.preview }}</span></span>
          <span class="end"><span v-if="c.waiting_minutes !== null" class="wait">{{ waited(Date.now() - c.waiting_minutes * 60000) }}</span><span v-if="c.contact_score != null" class="score"><i :style="{ background: scoreColor(c.contact_score) }" />{{ c.contact_score }}</span></span>
        </Link>
        <p v-if="!conversations.length" class="muted small" style="padding:18px 14px">Nothing here. New WhatsApp messages land in this list.</p>
      </nav>
    </template>

    <Topbar :title="topTitle">
      <template #left>
        <span class="muted small cnt">{{ conversations.length }} conversations<template v-if="oldest !== null"> · oldest {{ waited(Date.now() - oldest * 60000) }}</template></span>
      </template>
      <template v-if="conversation">
        <button type="button" class="btn sm ghost detbtn" @click="detailsOpen = true"><PanelRight class="icon sm" />Details</button>
      </template>
    </Topbar>

    <!-- list (small screens, and the empty state on desktop) -->
    <div v-if="!conversation" class="mlist">
      <div class="panel emptyhint"><InboxIcon class="icon" /><b>Pick a conversation</b><span class="muted small">Use <kbd>J</kbd> <kbd>K</kbd> to move through the list and <kbd>R</kbd> to reply.</span></div>
      <div class="msmall items">
        <Link v-for="c in conversations" :key="c.id" :href="rowHref(c)" class="crow">
          <UserAvatar :name="c.contact_name" />
          <span class="mid"><span class="top"><b>{{ c.contact_name }}</b><time>{{ rowTime(c.last_message_at) }}</time></span><span class="pv">{{ c.preview }}</span></span>
          <span class="end"><span v-if="c.waiting_minutes !== null" class="wait">{{ waited(Date.now() - c.waiting_minutes * 60000) }}</span></span>
        </Link>
      </div>
    </div>

    <div v-else class="conv">
      <div class="thread-col">
        <header class="mhead">
          <Link href="/inbox" class="back2" aria-label="Back to the list"><ChevronLeft class="icon" /></Link>
          <UserAvatar :name="contact.name" size="lg" />
          <div class="who"><b>{{ contact.name }}</b><span class="muted small">WhatsApp<template v-if="contact.score != null"> · score {{ contact.score }}</template></span></div>
          <a v-if="contact.phone_e164" class="ib" :href="`tel:${contact.phone_e164}`" aria-label="Call"><Phone class="icon" /></a>
          <button type="button" class="ib" aria-label="Chat actions" @click="detailsOpen = true"><MoreVertical class="icon" /></button>
        </header>
        <div v-if="openDeal" class="mdeal"><span class="stage"><i style="background:#8A3FC0" />{{ openDeal.stage }}</span><b>{{ openDeal.title }}</b><span class="muted">{{ openDeal.value ? money(openDeal.value, openDeal.currency) : '' }}</span><Link :href="`/deals/${openDeal.id}`">Open deal</Link></div>

        <header class="chead">
          <Link href="/inbox" class="back" aria-label="Back to the list"><ChevronLeft class="icon sm" /></Link>
          <UserAvatar :name="contact.name" size="lg" />
          <div class="who"><b>{{ contact.name }}</b><span class="muted small"><template v-if="contact.account">{{ contact.account.name }} · </template>{{ contact.phone_e164 }}</span></div>
          <span v-if="channel.mode === 'simulated'" class="badge wa"><MessageCircle class="icon xs" />WhatsApp · simulated</span>
          <div class="hr">
            <RSelect :model-value="conversation.assignee?.id ?? null" :options="assignOptions" :footer="rrFooter" placeholder="Unassigned" label="Assignee" size="sm" @change="assign" />
            <button type="button" class="btn sm" @click="close"><Check class="icon xs" />Close<kbd>E</kbd></button>
          </div>
        </header>

        <div ref="threadEl" class="thread" tabindex="0" aria-label="Messages">
          <p class="sysrow"><Bot class="icon xs" />Chat started on WhatsApp · {{ conversation.assignee ? `assigned to ${conversation.assignee.name}${roundRobin ? ' (round robin)' : ''}` : 'not assigned yet' }}</p>
          <template v-for="(m, i) in messages" :key="m.id">
            <div v-if="newDay(i)" class="day"><span>{{ dayLabel(m.created_at) }}</span></div>
            <div :id="`msg-${m.id}`" class="brow" :class="{ out: m.direction === 'out' }">
              <div class="bubble" :class="{ failed: m.status === 'failed', flash: flashId === m.id }">
                <span v-if="m.type === 'template'" class="badge brand" style="margin-bottom:4px">Template</span>
                <template v-if="m.media"><a v-if="m.media.url && m.media.mime_type?.startsWith('image/')" :href="m.media.url" target="_blank" rel="noopener"><img :src="m.media.url" :alt="m.media.filename" class="mimg"></a><a v-else-if="m.media.url" :href="m.media.url" target="_blank" rel="noopener" class="badge"><FileText class="icon xs" />{{ m.media.filename }}</a><span v-else class="badge"><FileText class="icon xs" />{{ m.media.filename ?? 'attachment' }}</span></template>
                <p v-if="m.body">{{ m.body }}</p>
                <div class="bm fig">{{ clock(m.created_at) }} · #{{ m.seq }}<template v-if="m.sent_by"> · {{ m.sent_by }}</template>
                  <CheckCheck v-if="m.direction === 'out' && m.status === 'read'" class="icon xs" /><Check v-else-if="m.direction === 'out' && m.status !== 'failed'" class="icon xs" />
                </div>
                <div v-if="m.status === 'failed'" class="ferr-row"><AlertTriangle class="icon xs" /><span>{{ errText(m) }}</span>
                  <button v-if="m.type === 'text' && conversation.can_send_freeform" type="button" class="btn sm" @click="retry(m)"><RotateCw class="icon xs" />Retry</button></div>
              </div>
            </div>
          </template>
        </div>

        <div v-if="ai.draftedFrom && reply.body && !canEditDraft" class="msuggest">
          <b><Sparkles class="icon xs" />Suggested reply · edit before sending</b>
          <p>{{ reply.body }}</p>
          <div class="mrow"><button type="button" class="btn" @click="canEditDraft = true; nextTick(() => document.querySelector('.mcomposer textarea')?.focus())">Edit</button><button type="button" class="btn primary" :disabled="reply.processing" @click="send">Use reply</button></div>
        </div>
        <form class="mcomposer" @submit.prevent="send">
          <input ref="mfile" type="file" class="sr" tabindex="-1" accept=".jpg,.jpeg,.png,.pdf" aria-label="Attachment" @change="attachFile">
          <button type="button" class="ib" aria-label="Attach file" :disabled="!conversation.can_send_freeform" @click="$refs.mfile.click()"><Paperclip class="icon" /></button>
          <textarea v-model="reply.body" rows="1" class="mpill" :placeholder="conversation.can_send_freeform ? 'Message' : 'Window closed: use a template'" :disabled="!conversation.can_send_freeform && !reply.template_id" aria-label="Message" />
          <button v-if="!reply.body && conversation.can_send_freeform" type="button" class="ib" :disabled="ai.drafting" aria-label="Suggest a reply" @click="draft"><Sparkles class="icon" /></button>
          <button class="msend" :disabled="reply.processing || !reply.body" aria-label="Send"><Send class="icon" /></button>
        </form>
        <div class="composer-wrap">
          <p v-if="windowLabel" class="win"><Clock class="icon xs" />Free reply window open for {{ windowLabel }} · after that only approved templates</p>
          <p v-else class="win warn"><AlertTriangle class="icon xs" />The 24-hour window is closed. Send an approved template to reopen the chat.</p>
          <div class="composer">
            <div class="tabs" role="tablist">
              <button type="button" role="tab" :aria-selected="tab === 'reply'" @click="tab = 'reply'">WhatsApp</button>
              <button type="button" role="tab" :aria-selected="tab === 'note'" @click="tab = 'note'">Note</button>
              <button type="button" role="tab" :aria-selected="tab === 'task'" @click="tab = 'task'">Task</button>
              <div v-if="tab === 'reply' && templates.length" class="tpl"><RSelect :model-value="reply.template_id" :options="templateOptions" placeholder="Use a template" label="Template" size="sm" @change="pickTemplate" /></div>
            </div>

            <form v-if="tab === 'reply'" @submit.prevent="send">
              <textarea v-model="reply.body" class="composer-input" rows="3" :disabled="!conversation.can_send_freeform && !reply.template_id" :placeholder="conversation.can_send_freeform ? 'Write a reply…  (R to focus)' : 'Choose a template to reopen the chat'" aria-label="Reply" />
              <span v-if="reply.errors.body" class="ferr">{{ reply.errors.body }}</span><span v-if="ai.error" class="ferr">{{ ai.error }}</span>
              <div class="cf">
                <span v-if="ai.draftedFrom" class="ai-note"><Sparkles class="icon xs" />AI draft from #{{ Math.max(1, messages.length - 29) }}–#{{ messages.length }} · edit before sending</span>
                <span v-else class="muted small">Drafts are suggestions. Nothing is sent until you press Send.</span>
                <span class="sp" />
                <input ref="fileInput" type="file" class="sr" tabindex="-1" accept=".jpg,.jpeg,.png,.pdf" aria-label="Attachment" @change="attachFile">
                <button type="button" class="ib" title="Attach an image or PDF (up to 5 MB)" aria-label="Attach file" :disabled="!conversation.can_send_freeform" @click="fileInput.click()"><Paperclip class="icon sm" /></button>
                <button type="button" class="btn sm" :disabled="ai.drafting" @click="draft"><Sparkles class="icon xs" />{{ ai.drafting ? 'Drafting…' : ai.draftedFrom ? 'Redraft' : 'Draft with AI' }}</button>
                <button class="btn sm primary" :disabled="reply.processing || !reply.body"><Send class="icon xs" />Send<kbd>⌘↵</kbd></button>
              </div>
            </form>
            <form v-else-if="tab === 'note'" @submit.prevent="saveNote">
              <textarea v-model="note.body" class="composer-input" rows="3" placeholder="Internal note on the contact timeline. Only your team sees it." aria-label="Note" />
              <span v-if="note.errors.body" class="ferr">{{ note.errors.body }}</span>
              <div class="cf"><span class="sp" /><button class="btn sm primary" :disabled="note.processing || !note.body">Save note</button></div>
            </form>
            <form v-else @submit.prevent="saveTask">
              <div class="trow"><input v-model="task.title" class="input" placeholder="Follow-up, e.g. send the proposal" aria-label="Task" autocomplete="off"><DateField v-model="task.due_at" placeholder="Due date" label="Due date" :block="false" /></div>
              <span v-if="task.errors.title" class="ferr">{{ task.errors.title }}</span>
              <div class="cf"><span class="sp" /><button class="btn sm primary" :disabled="task.processing || !task.title">Add task</button></div>
            </form>
          </div>
        </div>
      </div>

      <aside class="rpanel" aria-label="Details">
        <InboxDetails :contact="contact" :open-deal="openDeal" :score-detail="scoreDetail" :summary="shownSummary" :summarizing="ai.summarizing" :seq-of="seqOf" @summarize="summarize" @jump="jump" />
      </aside>
    </div>

    <Modal v-if="detailsOpen" title="Details" width="420px" @close="detailsOpen = false">
      <div class="mact"><RSelect :model-value="conversation.assignee?.id ?? null" :options="assignOptions" :footer="rrFooter" placeholder="Unassigned" label="Assignee" block @change="assign" /><button type="button" class="btn" @click="close"><Check class="icon xs" />Close chat</button></div>
      <InboxDetails :contact="contact" :open-deal="openDeal" :score-detail="scoreDetail" :summary="shownSummary" :summarizing="ai.summarizing" :seq-of="seqOf" @summarize="summarize" @jump="jump" />
    </Modal>
  </AppShell>
</template>

<style scoped>
.mhead,.mdeal,.mcomposer,.msuggest{display:none}
.sysrow{display:flex;align-items:center;gap:6px;justify-content:center;font-size:12px;color:var(--text-3);margin:2px 0 6px}
.mimg{display:block;max-width:240px;max-height:200px;border-radius:8px;margin-bottom:6px}
.clist{overflow-y:auto;flex:1;min-height:0}
.crow{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:10px;align-items:start;padding:11px 12px;border-bottom:1px solid var(--line);color:var(--text)}
.crow:hover{background:var(--surface-2);text-decoration:none}
.crow.on{background:var(--brand-tint);box-shadow:inset 2px 0 0 var(--brand)}
.crow .mid{min-width:0;display:grid;gap:1px}
.crow .top{display:flex;justify-content:space-between;gap:8px}
.crow .top b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.crow time{font-size:12px;color:var(--text-3);flex:none}
.crow .pv{font-size:13px;color:var(--text-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.crow .end{display:grid;justify-items:end;gap:2px;padding-top:18px;font-size:12px}
.crow .wait{color:var(--hot);font-weight:600}
.soonrow{display:flex;align-items:center;gap:10px;padding:6px 8px;color:var(--text-4);padding-left:36px}
.soonrow .n{margin-left:auto;font-size:12px}
.back{display:none}
.cnt{white-space:nowrap}
.conv{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 340px}
.thread-col{display:flex;flex-direction:column;min-height:0;min-width:0;border-right:1px solid var(--line)}
.chead{display:flex;align-items:center;gap:12px;padding:10px var(--s5);background:var(--surface);border-bottom:1px solid var(--line);flex-wrap:wrap}
.chead .who{display:grid;min-width:0}.chead .who b{font-size:15px}
.chead .hr{margin-left:auto;display:flex;gap:8px;align-items:center}
.thread{flex:1;min-height:0;overflow-y:auto;padding:16px var(--s5);display:flex;flex-direction:column;gap:8px;background:var(--ground)}
.day{display:flex;justify-content:center;margin:10px 0 4px}
.day span{font-size:11px;font-weight:500;color:var(--text-3);background:var(--surface);border:1px solid var(--line);border-radius:999px;padding:1px 10px}
.brow{display:flex}.brow.out{justify-content:flex-end}
.bubble{max-width:min(620px,78%);background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:9px 12px;scroll-margin:80px}
.brow.out .bubble{background:var(--brand-soft);border-color:#DED4FB}
.bubble p{white-space:pre-wrap;overflow-wrap:anywhere}
.brow .bubble.failed,.brow.out .bubble.failed{border-color:#E9A7A7;background:var(--danger-soft)}
.bubble.flash{box-shadow:0 0 0 2px var(--brand);transition:box-shadow .2s}
.bm{display:flex;align-items:center;gap:4px;font-size:11px;color:var(--text-4);margin-top:4px;justify-content:flex-end}
.ferr-row{display:flex;align-items:center;gap:8px;margin-top:8px;padding-top:8px;border-top:1px dashed #E9A7A7;color:var(--danger);font-size:12px}
.ferr-row .btn{margin-left:auto}
.composer-wrap{padding:8px var(--s5) var(--s4);background:var(--surface);border-top:1px solid var(--line)}
.win{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-3);margin:2px 0 8px}
.win.warn{color:var(--warning)}
.composer{border:1px solid var(--line-strong);border-radius:var(--r-lg);overflow:visible;background:var(--surface)}
.tabs{display:flex;align-items:center;gap:2px;padding:4px 6px 0;border-bottom:1px solid var(--line)}
.tabs button[role=tab]{border:0;background:none;padding:7px 10px;font-size:13px;font-weight:500;color:var(--text-3);border-bottom:2px solid transparent;margin-bottom:-1px}
.tabs button[aria-selected=true]{color:var(--text);border-bottom-color:var(--brand)}
.tpl{margin-left:auto;padding-bottom:4px}
.composer-input{width:100%;border:0;padding:10px 12px;font:inherit;resize:none;background:none;display:block}
.composer-input:focus{outline:none}.composer:focus-within{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
.composer-input:disabled{color:var(--text-4);background:var(--surface-2)}
.cf{display:flex;align-items:center;gap:8px;padding:6px 8px 8px}
.cf .sp{flex:1}
.ai-note{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--brand-strong)}
.trow{display:flex;gap:8px;padding:10px 12px 0}.trow .input{flex:1}
.ferr{display:block;padding:0 12px}
.rpanel{overflow-y:auto;padding:var(--s4) var(--s5);background:var(--surface)}
.mlist{display:none;padding:var(--s4)}
.emptyhint{display:none}
.detbtn{display:none}
@media (min-width:1181px){
  .mlist{display:grid;flex:1;place-items:center;padding:0}
  .mlist .msmall{display:none}
  .mlist .emptyhint{display:grid;justify-items:center;gap:6px;padding:32px 40px;border:0;background:none;text-align:center}
  .mlist .emptyhint .icon{width:28px;height:28px;color:var(--text-3)}
}
@media (max-width:1180px){
  .conv{grid-template-columns:minmax(0,1fr)}
  .rpanel{display:none}
  .mlist{display:grid;gap:var(--s3)}
  .msmall{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);overflow:hidden}
  .msmall .crow:last-child{border-bottom:0}
  .back{display:grid;place-items:center;width:32px;height:32px;border-radius:var(--r-sm);color:var(--text-2)}
  .detbtn{display:inline-flex}
}
@media (max-width:720px){
  .cnt{display:none}
  .chead,.composer-wrap{display:none}
  .mhead{display:flex;align-items:center;gap:10px;padding:8px var(--s3);background:var(--surface);border-bottom:1px solid var(--line)}
  .mhead .who{display:grid;flex:1;min-width:0}.mhead .who b{font-size:16px}
  .mhead :deep(.av.lg){width:36px;height:36px;font-size:12px}
  .back2{display:grid;place-items:center;width:32px;height:32px;color:var(--text-2)}
  .mdeal{display:flex;align-items:center;gap:8px;padding:8px var(--s4);background:var(--brand-tint);border-bottom:1px solid var(--line);font-size:13px;white-space:nowrap;overflow:hidden}
  .mdeal b{overflow:hidden;text-overflow:ellipsis;font-weight:600}.mdeal a{margin-left:auto;font-weight:500;flex:none}
  .msuggest{display:block;margin:var(--s3);padding:12px;border:1px solid #DED4FB;border-radius:var(--r-lg);background:var(--surface)}
  .msuggest b{display:flex;gap:6px;align-items:center;color:var(--brand-strong);font-size:13px}.msuggest p{margin:6px 0 10px}
  .mrow{display:grid;grid-template-columns:1fr 1.4fr;gap:8px}.mrow .btn{height:38px;justify-content:center}
  .mcomposer{display:flex;align-items:flex-end;gap:6px;padding:8px var(--s3);background:var(--surface);border-top:1px solid var(--line)}
  .mpill{flex:1;min-height:40px;max-height:120px;border:1px solid var(--line-strong);border-radius:20px;padding:9px 14px;font:inherit;resize:none;background:var(--surface-2)}
  .mpill:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
  .msend{width:40px;height:40px;border-radius:50%;border:0;background:var(--brand);color:#fff;display:grid;place-items:center;flex:none}.msend:disabled{opacity:.45}
  .mcomposer .ib{width:40px;height:40px}
  .chead{padding:10px var(--s4)}.thread{padding:12px var(--s4)}.composer-wrap{padding:8px var(--s4) var(--s4)}
  .bubble{max-width:90%}.cf kbd{display:none}
  .conv{min-height:calc(100vh - 52px - 60px)}
}
</style>

<style>
@media (max-width:720px){ body:has(.mhead) .topbar{display:none} body:has(.mhead) .app{padding-bottom:0;height:100dvh;overflow:hidden} body:has(.mhead) .main{overflow:hidden;height:calc(100dvh - 60px)} body:has(.mhead) .conv{height:100%;min-height:0} body:has(.mhead) .thread-col{height:100%;border-right:0} .mact{display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:16px} }
.mact{display:none}
@media (max-width:720px){ .modal .mact{display:grid} }
</style>
