<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import RSelect from '@/Components/RSelect.vue'
import DateField from '@/Components/DateField.vue'
import Modal from '@/Components/Modal.vue'
import Timeline from '@/Components/Timeline.vue'
import {
  ArrowRight, Building2, Check, ClipboardList, FileText, GitMerge, Hash, MessageCircle, Plus, Sparkles,
  StickyNote, Target, UserPlus, UserRound, X, ArrowRightLeft, ListChecks,
} from 'lucide-vue-next'
import { Phone, Ellipsis, UserCog } from 'lucide-vue-next'
import { ago, dateTime, dueLabel, money, scoreColor, shortDate } from '@/lib/format'

const props = defineProps({
  deal: Object, stages: Array, activities: Array, timeline_next: String, tasks: Array, doneTasks: Array,
  users: Array, stageDays: Object, probability: Number, lastContact: String, conversationId: Number,
  created_at: String, scoreDetail: Object,
})

const items = ref([...props.activities])
const next = ref(props.timeline_next)
const loading = ref(false)
async function more() {
  loading.value = true
  try {
    const res = await fetch(`/deals/${props.deal.id}/timeline?before=${encodeURIComponent(next.value)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    const json = await res.json()
    items.value.push(...json.items); next.value = json.next
  } finally { loading.value = false }
}
// Inertia refreshes props after note/task/stage changes; keep the local list in step.
import { watch } from 'vue'
watch(() => props.activities, (a) => { items.value = [...a]; next.value = props.timeline_next })

const open = computed(() => props.deal.stage.kind === 'open')
const wonStage = computed(() => props.stages.find((s) => s.kind === 'won'))
const lostStage = computed(() => props.stages.find((s) => s.kind === 'lost'))
const currentPos = computed(() => props.stages.find((s) => s.id === props.deal.stage.id)?.position ?? 0)
const closeDays = computed(() => {
  if (!props.deal.expected_close) return null
  return Math.round((new Date(props.deal.expected_close + 'T00:00:00') - new Date(new Date().toDateString())) / 864e5)
})

// stage stepper
function stepState(s) { return s.id === props.deal.stage.id ? 'now' : s.position < currentPos.value && s.kind === 'open' ? 'past' : 'future' }
function stepLabel(s) {
  if (s.id === props.deal.stage.id && open.value) return `${s.name} · day ${props.deal.days_in_stage}${props.deal.stale_after_days ? ' of ' + props.deal.stale_after_days : ''}`
  const d = props.stageDays?.[s.name]
  return d !== undefined && s.kind === 'open' && stepState(s) === 'past' ? `${s.name} · ${d}d` : s.name
}
const lostOpen = ref(false)
const lostForm = useForm({ stage_id: null, lost_reason: 'Price too high' })
const LOST = ['Price too high', 'Went with in-house', 'No budget this quarter', 'Chose a competitor', 'No response'].map((r) => ({ value: r, label: r }))
function moveTo(s) {
  if (s.id === props.deal.stage.id) return
  if (s.kind === 'lost') { lostForm.stage_id = s.id; lostForm.reset('lost_reason'); lostOpen.value = true; return }
  router.patch(`/deals/${props.deal.id}/stage`, { stage_id: s.id }, { preserveScroll: true })
}
function confirmLost() { lostForm.patch(`/deals/${props.deal.id}/stage`, { preserveScroll: true, onSuccess: () => (lostOpen.value = false) }) }

// composer
const tab = ref(['call', 'task'].includes(new URLSearchParams(window.location.search).get('tab')) ? new URLSearchParams(window.location.search).get('tab') : 'note')
const call = useForm({ text: '', minutes: '' })
function saveCall() { call.transform((d) => ({ ...d, minutes: d.minutes === '' ? null : d.minutes })).post(`/deals/${props.deal.id}/calls`, { preserveScroll: true, onSuccess: () => { call.reset(); tab.value = 'note' } }) }
const ownerOpen = ref(false)
const menuOpen = ref(false)
const ownerId = ref(props.deal.owner?.id ?? null)
const ownerOptions = computed(() => props.users.map((u) => ({ value: u.id, label: u.name, avatar: true })))
function saveOwner() { router.post(`/deals/${props.deal.id}/owner`, { owner_id: ownerId.value }, { preserveScroll: true, onSuccess: () => (ownerOpen.value = false) }) }
const note = useForm({ body: '' })
const taskForm = useForm({ title: '', assignee_id: null, due_at: null })
const taskOpen = ref(false)
const owners = computed(() => props.users.map((u) => ({ value: u.id, label: u.name })))
function saveNote() { note.post(`/deals/${props.deal.id}/notes`, { preserveScroll: true, onSuccess: () => note.reset() }) }
function saveTask() { taskForm.post(`/deals/${props.deal.id}/tasks`, { preserveScroll: true, onSuccess: () => { taskForm.reset(); taskOpen.value = false; tab.value = 'note' } }) }
const done = (t) => router.post(`/tasks/${t.id}/done`, {}, { preserveScroll: true })
function onNoteKey(e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey) && note.body) { e.preventDefault(); saveNote() } }

</script>

<template>
  <AppShell>
    <Topbar title="Deals"><template #left><span class="crumb">/ <b>{{ deal.title }}</b></span></template></Topbar>

    <div class="content">
      <header class="dhead">
        <div class="ttl">
          <h2>{{ deal.title }}</h2>
          <div class="meta">
            <span v-if="deal.account"><Building2 class="icon xs" /><Link :href="`/companies/${deal.account.id}`" class="rowlink">{{ deal.account.name }}</Link></span>
            <span v-if="deal.contact"><UserRound class="icon xs" /><Link :href="`/contacts/${deal.contact.id}`" class="rowlink">{{ deal.contact.name }}</Link></span>
            <span v-if="deal.owner"><UserRound class="icon xs" />{{ deal.owner.name }}</span>
            <span class="fig"><Hash class="icon xs" />{{ String(deal.id).padStart(4, '0') }} · {{ deal.pipeline.name }}</span>
          </div>
        </div>
        <div v-if="open" class="acts">
          <button v-if="lostStage" type="button" class="btn" @click="moveTo(lostStage)"><X class="icon sm" />Lost</button>
          <button v-if="wonStage" type="button" class="btn primary" @click="moveTo(wonStage)"><Check class="icon sm" />Won</button>
          <div v-if="$page.props.auth.user.role !== 'rep'" class="morewrap2">
            <button type="button" class="ib" aria-label="More actions" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen"><Ellipsis class="icon" /></button>
            <div v-if="menuOpen" class="pop menu" @mouseleave="menuOpen = false"><button type="button" @click="ownerOpen = true; menuOpen = false"><UserCog class="icon sm" />Change owner</button></div>
          </div>
        </div>
        <div v-else class="acts"><span class="badge" :class="deal.stage.kind === 'won' ? 'success' : 'danger'">{{ deal.stage.name }}<template v-if="deal.closed_at"> · {{ shortDate(deal.closed_at) }}</template></span>
          <button type="button" class="btn sm" @click="moveTo(stages.find((s) => s.kind === 'open' && s.position === Math.max(...stages.filter((x) => x.kind === 'open').map((x) => x.position))))">Reopen</button></div>
      </header>
      <p v-if="deal.lost_reason" class="lostnote"><X class="icon xs" />Lost: {{ deal.lost_reason }}</p>

      <div class="kpis">
        <div><span>Value</span><b class="fig">{{ deal.value ? money(deal.value, deal.currency, { compact: false }) : 'Not set' }}</b></div>
        <div><span>Expected close</span><b class="fig">{{ deal.expected_close ? shortDate(deal.expected_close) : 'Not set' }}<small v-if="closeDays !== null && open"> · {{ closeDays >= 0 ? `${closeDays} days` : `${-closeDays} days late` }}</small></b></div>
        <div><span>Win chance</span><b class="fig">{{ open ? Math.round(probability * 100) + '%' : deal.stage.kind === 'won' ? '100%' : '0%' }}</b></div>
        <div><span>Lead score</span><b><span v-if="deal.contact?.score != null" class="score"><i :style="{ background: scoreColor(deal.contact.score) }" />{{ deal.contact.score }}</span><template v-else>—</template></b></div>
        <div><span>Last contact</span><b>{{ lastContact ? ago(lastContact) : 'No messages yet' }}</b></div>
      </div>

      <ol class="steps" aria-label="Stage">
        <li v-for="s in stages" :key="s.id" :class="stepState(s)">
          <button type="button" :aria-current="s.id === deal.stage.id ? 'step' : undefined" :disabled="s.id === deal.stage.id" @click="moveTo(s)">{{ stepLabel(s) }}</button>
        </li>
      </ol>

      <div class="cols">
        <div class="main-col">
          <section class="panel composer" aria-label="Add to timeline">
            <div class="tabs" role="tablist">
              <button type="button" role="tab" :aria-selected="tab === 'note'" @click="tab = 'note'"><FileText class="icon xs" />Note</button>
              <Link v-if="conversationId" role="tab" :href="`/inbox/${conversationId}`" class="tablink"><MessageCircle class="icon xs" />WhatsApp</Link>
              <button type="button" role="tab" :aria-selected="tab === 'call'" @click="tab = 'call'"><Phone class="icon xs" />Call</button>
              <button type="button" role="tab" :aria-selected="tab === 'task'" @click="tab = 'task'"><ClipboardList class="icon xs" />Task</button>
            </div>
            <form v-if="tab === 'note'" @submit.prevent="saveNote">
              <textarea v-model="note.body" rows="2" class="ta" placeholder="Add a note · @name to notify a teammate" aria-label="Note" @keydown="onNoteKey" />
              <span v-if="note.errors.body" class="ferr">{{ note.errors.body }}</span>
              <div class="cf"><button class="btn sm primary" :disabled="note.processing || !note.body">Save note<kbd>⌘↵</kbd></button></div>
            </form>
            <form v-else-if="tab === 'call'" class="tform" @submit.prevent="saveCall">
              <textarea v-model="call.text" rows="2" class="input" placeholder="What was discussed?" aria-label="Call notes" />
              <div class="trow"><input v-model="call.minutes" class="input" inputmode="numeric" placeholder="Minutes (optional)" aria-label="Call length in minutes" autocomplete="off"><span /></div>
              <span v-if="call.errors.text" class="ferr">{{ call.errors.text }}</span>
              <div class="cf"><button class="btn sm primary" :disabled="call.processing || !call.text">Log call</button></div>
            </form>
            <form v-else @submit.prevent="saveTask" class="tform">
              <input v-model="taskForm.title" class="input" placeholder="What needs to happen?" aria-label="Task" autocomplete="off">
              <div class="trow"><DateField v-model="taskForm.due_at" placeholder="Due date" label="Due date" /><RSelect v-model="taskForm.assignee_id" :options="owners" placeholder="Assign to me" label="Assignee" block /></div>
              <span v-if="taskForm.errors.title" class="ferr">{{ taskForm.errors.title }}</span>
              <div class="cf"><button class="btn sm primary" :disabled="taskForm.processing || !taskForm.title">Add task</button></div>
            </form>
          </section>

          <Timeline :items="items" />
          <button v-if="next" type="button" class="btn" :disabled="loading" @click="more">{{ loading ? 'Loading…' : 'Show older activity' }}</button>
          <p v-else-if="!items.length" class="muted small">No activity yet.</p>
        </div>

        <aside class="side">
          <section class="panel">
            <div class="panel-h"><h2>Tasks</h2><button type="button" class="btn sm ghost" style="margin-left:auto" @click="tab = 'task'"><Plus class="icon xs" />Add</button></div>
            <ul class="tasks">
              <li v-for="t in tasks" :key="t.id">
                <button type="button" class="cb" role="checkbox" aria-checked="false" :aria-label="`Mark done: ${t.title}`" @click="done(t)" />
                <span class="tt">{{ t.title }}</span>
                <span class="due" :class="{ hot: dueLabel(t.due_at).hot }">{{ t.due_at ? dueLabel(t.due_at).text.replace('Due ', '') : '' }}</span>
              </li>
              <li v-for="t in doneTasks" :key="`d${t.id}`" class="isdone">
                <span class="cb on" role="checkbox" aria-checked="true" :aria-label="`Done: ${t.title}`"><Check class="icon xs" /></span>
                <span class="tt">{{ t.title }}</span><span class="due">{{ shortDate(t.done_at) }}</span>
              </li>
              <li v-if="!tasks.length && !doneTasks.length" class="muted small" style="padding:12px 16px">No tasks. Add one so nothing slips.</li>
            </ul>
          </section>

          <section class="panel">
            <div class="panel-h"><h2>Details</h2></div>
            <dl class="kv">
              <dt>Pipeline</dt><dd>{{ deal.pipeline.name }}</dd>
              <dt>Stage</dt><dd>{{ deal.stage.name }}</dd>
              <dt>Source</dt><dd>{{ deal.contact?.source ?? '—' }}</dd>
              <dt>Currency</dt><dd>{{ deal.currency }}</dd>
              <dt>Owner</dt><dd>{{ deal.owner?.name ?? '—' }}</dd>
              <dt>Created</dt><dd>{{ dateTime(created_at) }}</dd>
            </dl>
          </section>

          <section v-if="scoreDetail" class="panel">
            <div class="panel-h"><h2>Why score {{ scoreDetail.score }}</h2></div>
            <ul class="why">
              <li v-for="(r, i) in scoreDetail.reasons" :key="i"><b class="w fig" :class="{ neg: r.weight < 0 }">{{ r.weight > 0 ? '+' : '' }}{{ r.weight }}</b><span>{{ r.label }}</span></li>
            </ul>
          </section>
        </aside>
      </div>
      <p class="synthetic">Synthetic demo data.</p>
    </div>

    <Modal v-if="ownerOpen" title="Change owner" width="380px" @close="ownerOpen = false">
      <form class="form" @submit.prevent="saveOwner">
        <div class="field"><label>Owner</label><RSelect v-model="ownerId" :options="ownerOptions" label="Owner" block /></div>
        <div class="actions"><button type="button" class="btn ghost" @click="ownerOpen = false">Cancel</button><button type="submit" class="btn primary">Save owner</button></div>
      </form>
    </Modal>

    <Modal v-if="lostOpen" title="Mark deal lost" width="400px" @close="lostOpen = false">
      <form class="form" @submit.prevent="confirmLost">
        <p class="muted small">A reason is required. It feeds the lost-reasons chart in Insights.</p>
        <div class="field"><label>Lost reason</label><RSelect v-model="lostForm.lost_reason" :options="LOST" label="Lost reason" block /><span v-if="lostForm.errors.lost_reason" class="ferr">{{ lostForm.errors.lost_reason }}</span></div>
        <div class="actions"><button type="button" class="btn ghost" @click="lostOpen = false">Cancel</button><button type="submit" class="btn primary" :disabled="lostForm.processing">Mark lost</button></div>
      </form>
    </Modal>
  </AppShell>
</template>

<style scoped>
.dhead{display:flex;align-items:flex-start;gap:var(--s4);margin-bottom:var(--s4)}
.ttl h2{font-size:22px}
.meta{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:6px;color:var(--text-3)}
.meta span{display:inline-flex;align-items:center;gap:6px}
.acts{margin-left:auto;display:flex;gap:8px;align-items:center}
.morewrap2{position:relative}.menu{position:absolute;right:0;top:36px;z-index:30;min-width:170px;padding:4px}
.menu button{display:flex;align-items:center;gap:8px;width:100%;border:0;background:none;padding:8px 10px;border-radius:var(--r-sm);text-align:left}.menu button:hover{background:var(--hover)}
.lostnote{display:flex;align-items:center;gap:6px;color:var(--danger);margin:-6px 0 var(--s4);font-size:13px}
.kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);margin-bottom:var(--s4)}
.kpis>div{padding:14px 16px;border-right:1px solid var(--line);display:grid;gap:4px;min-width:0}
.kpis>div:last-child{border-right:0}
.kpis span{font-size:12px;color:var(--text-3)}
.kpis b{font-size:17px;font-weight:600;letter-spacing:-.01em}.kpis small{font-weight:400;font-size:13px;color:var(--text-3)}
.steps{list-style:none;padding:0;display:flex;gap:4px;margin-bottom:var(--s5)}
.steps li{flex:1;min-width:0}
.steps button{width:100%;height:30px;border:0;padding:0 18px 0 22px;font-size:12px;font-weight:500;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  clip-path:polygon(0 0,calc(100% - 12px) 0,100% 50%,calc(100% - 12px) 100%,0 100%,12px 50%);background:var(--hover);color:var(--text-3)}
.steps li:first-child button{clip-path:polygon(0 0,calc(100% - 12px) 0,100% 50%,calc(100% - 12px) 100%,0 100%);padding-left:12px}
.steps .past button{background:var(--brand-soft);color:var(--brand-strong)}
.steps .now button{background:var(--brand);color:#fff;font-weight:600;cursor:default}
.steps .future button:hover:not(:disabled){background:var(--line)}
.cols{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:var(--s5);align-items:start}
.main-col{display:grid;gap:var(--s4);min-width:0}
.side{display:grid;gap:var(--s4);min-width:0}
.composer .tabs{display:flex;gap:2px;padding:4px 8px 0;border-bottom:1px solid var(--line)}
.composer .tabs button,.tablink{display:inline-flex;align-items:center;gap:6px;border:0;background:none;padding:9px 10px;font-size:13px;font-weight:500;color:var(--text-3);border-bottom:2px solid transparent;margin-bottom:-1px}
.tablink:hover{text-decoration:none;color:var(--text)}
.composer .tabs button[aria-selected=true]{color:var(--text);border-bottom-color:var(--brand)}
.ta{width:100%;border:0;padding:12px 14px;font:inherit;resize:none;display:block;background:none}.ta:focus{outline:none}
.cf{display:flex;justify-content:flex-end;padding:6px 10px 10px}
.tform{padding:12px 14px 0;display:grid;gap:8px}.tform .cf{padding:0 0 12px}
.trow{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.tasks{list-style:none;padding:4px 0}
.tasks li{display:flex;align-items:flex-start;gap:10px;padding:9px 16px;border-bottom:1px solid var(--line)}
.tasks li:last-child{border-bottom:0}
.tt{flex:1;min-width:0}.due{font-size:12px;color:var(--text-3);white-space:nowrap}.due.hot{color:var(--hot);font-weight:600}
.isdone .tt{text-decoration:line-through;color:var(--text-4)}
.cb{width:18px;height:18px;border:1.5px solid var(--line-strong);border-radius:5px;background:var(--surface);flex:none;margin-top:2px;display:grid;place-items:center;padding:0}
.cb:hover{border-color:var(--brand)}.cb.on{background:var(--brand);border-color:var(--brand);color:#fff}
.kv{display:grid;grid-template-columns:90px minmax(0,1fr);gap:10px 12px;padding:14px 16px}.kv dt{color:var(--text-3)}.kv dd{margin:0}
.why{list-style:none;padding:12px 16px;display:grid;gap:8px}.why li{display:flex;gap:10px}
.why .w{color:var(--success);min-width:30px}.why .w.neg{color:var(--danger)}
@media (max-width:1180px){.cols{grid-template-columns:minmax(0,1fr)}.kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.kpis>div:nth-child(3){border-right:0}.kpis>div:nth-child(-n+3){border-bottom:1px solid var(--line)}}
@media (max-width:720px){.dhead{flex-wrap:wrap}.acts{margin-left:0}.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.kpis>div{border-right:0;border-bottom:1px solid var(--line)}.kpis>div:last-child{grid-column:1/-1;border-bottom:0}.steps{overflow-x:auto}.steps li{flex:0 0 130px}.trow{grid-template-columns:1fr}}
</style>
