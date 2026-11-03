<script setup>
import { computed, onMounted, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import RSelect from '@/Components/RSelect.vue'
import DateField from '@/Components/DateField.vue'
import Modal from '@/Components/Modal.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Flame, Hourglass, KanbanSquare, MessageCircle, Plus, Target, UserRound, CalendarClock, Layers } from 'lucide-vue-next'
import { money, scoreColor, shortDate } from '@/lib/format'

const props = defineProps({
  pipelines: Array, pipeline: Object, stages: Array, filters: Object, views: Object,
  owners: Array, contacts: Array, currency: String, newContact: { type: Number, default: null },
})

const COLORS = { New: '#6B6779', Qualified: '#2F62CC', Proposal: '#8A3FC0', Negotiation: '#9A5A06', Won: '#146C35', Lost: '#B91C1C' }
const color = (s) => COLORS[s.name] ?? (s.kind === 'won' ? '#146C35' : s.kind === 'lost' ? '#B91C1C' : '#2F62CC')

const view = computed(() => props.filters.view)
const ownerOptions = computed(() => [{ value: 0, label: 'Owner: everyone' }, ...props.owners.map((o) => ({ value: o.id, label: o.name }))])
const owner = computed(() => props.filters.owner ?? 0)

const open = computed(() => props.stages.filter((s) => s.kind === 'open'))
const totals = computed(() => ({
  value: open.value.reduce((a, s) => a + s.value, 0),
  weighted: open.value.reduce((a, s) => a + s.weighted, 0),
  deals: props.stages.reduce((a, s) => a + s.count, 0),
}))
const rows = computed(() => props.stages.flatMap((s) => s.deals.map((d) => ({ ...d, stage: s }))))

function go(extra = {}) {
  const q = { pipeline: props.pipeline.id, ...props.filters, ...extra }
  for (const k of Object.keys(q)) if (!q[k] || (k === 'view' && q[k] === 'board')) delete q[k]
  router.get('/pipeline', q, { preserveScroll: true, preserveState: true, replace: true })
}
const savedView = (key) => go({ stuck: 0, hot: 0, closing: 0, mine: 0, owner: 0, ...(key ? { [key]: 1 } : {}) })
const isSaved = (key) => (key ? props.filters[key] : !['stuck', 'hot', 'closing', 'mine'].some((k) => props.filters[k]))

// drag and drop
const drag = ref(null)
const over = ref(null)
const lost = ref(null)
const lostForm = useForm({ stage_id: null, lost_reason: 'Price too high' })
const LOST_REASONS = ['Price too high', 'Went with in-house', 'No budget this quarter', 'Chose a competitor', 'No response'].map((r) => ({ value: r, label: r }))

function onDrop(stage) {
  over.value = null
  const id = drag.value
  drag.value = null
  const deal = rows.value.find((d) => d.id === id)
  if (!deal || deal.stage.id === stage.id) return
  if (stage.kind === 'lost') { lostForm.stage_id = stage.id; lostForm.lost_reason = 'Price too high'; lost.value = id; return }
  router.patch(`/deals/${id}/stage`, { stage_id: stage.id }, { preserveScroll: true })
}
function confirmLost() {
  lostForm.patch(`/deals/${lost.value}/stage`, { preserveScroll: true, onSuccess: () => (lost.value = null) })
}

// new deal
const creating = ref(false)
const CURRENCIES = ['IDR', 'USD', 'GBP', 'SGD', 'EUR', 'AUD'].map((c) => ({ value: c, label: c }))
const stageOptions = computed(() => open.value.map((s) => ({ value: s.id, label: s.name })))
const form = useForm({ title: '', contact_id: null, pipeline_id: props.pipeline.id, stage_id: null, value: '', currency: props.currency, expected_close: null })
function newDeal(stageId = null) {
  form.reset(); form.clearErrors()
  form.pipeline_id = props.pipeline.id; form.currency = props.currency
  form.stage_id = stageId ?? open.value[0]?.id
  creating.value = true
}
function createDeal() {
  form.transform((d) => ({ ...d, value: d.value === '' ? null : d.value })).post('/deals', { onSuccess: () => (creating.value = false) })
}

onMounted(() => {
  if (props.newContact) { newDeal(); form.contact_id = props.newContact }
})

const unrepliedText = (m) => (m < 60 ? `${m} min` : `${Math.floor(m / 60)} h`)
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h"><h1>Deals</h1><div class="acts"><button type="button" class="ib" title="New deal" aria-label="New deal" @click="newDeal()"><Plus class="icon" /></button></div></div>
      <div class="views">
        <div class="grp">Pipelines</div>
        <Link v-for="p in pipelines" :key="p.id" :href="`/pipeline?pipeline=${p.id}`" :aria-current="p.id === pipeline.id ? 'page' : undefined">
          <KanbanSquare class="icon" />{{ p.name }}<span v-if="p.id === pipeline.id" class="n">{{ views.all }}</span>
        </Link>
        <div class="grp">Saved views</div>
        <a href="#" :aria-current="isSaved(null) ? 'page' : undefined" @click.prevent="savedView(null)"><Layers class="icon" />All deals<span class="n">{{ views.all }}</span></a>
        <a href="#" :aria-current="isSaved('mine') ? 'page' : undefined" @click.prevent="savedView('mine')"><UserRound class="icon" />My open deals<span class="n">{{ views.mine }}</span></a>
        <a href="#" :aria-current="isSaved('stuck') ? 'page' : undefined" @click.prevent="savedView('stuck')"><Hourglass class="icon" />Stuck<span class="n">{{ views.stuck }}</span></a>
        <a href="#" :aria-current="isSaved('hot') ? 'page' : undefined" @click.prevent="savedView('hot')"><Flame class="icon" />Hot (score 70+)<span class="n">{{ views.hot }}</span></a>
        <a href="#" :aria-current="isSaved('closing') ? 'page' : undefined" @click.prevent="savedView('closing')"><CalendarClock class="icon" />Closing this month<span class="n">{{ views.closing }}</span></a>
      </div>
    </template>

    <Topbar title="Deals">
      <template #left><span class="crumb">/ <b>{{ pipeline.name }}</b></span></template>
      <button type="button" class="btn primary" @click="newDeal()"><Plus class="icon sm" />New deal</button>
    </Topbar>

    <div class="toolbar">
      <div class="seg" role="group" aria-label="View">
        <button type="button" :aria-pressed="view === 'board'" @click="go({ view: 'board' })">Board</button>
        <button type="button" :aria-pressed="view === 'table'" @click="go({ view: 'table' })">Table</button>
        <button type="button" :aria-pressed="view === 'forecast'" @click="go({ view: 'forecast' })">Forecast</button>
      </div>
      <RSelect :model-value="owner" :options="ownerOptions" label="Owner" @change="(v) => go({ owner: v, mine: 0 })" />
      <span class="sum"><b class="fig">{{ money(totals.value, currency) }}</b> open · weighted <b class="fig">{{ money(totals.weighted, currency) }}</b> · {{ totals.deals }} deals</span>
    </div>

    <!-- board -->
    <div v-if="view === 'board'" class="content board-scroll">
      <div class="board">
        <section
          v-for="stage in stages" :key="stage.id" class="col" :class="{ over: over === stage.id }"
          :aria-label="`${stage.name}, ${stage.count} deals`"
          @dragover.prevent="over = stage.id" @dragleave="over === stage.id && (over = null)" @drop.prevent="onDrop(stage)"
        >
          <header class="col-h">
            <span class="stage"><i :style="{ background: color(stage) }" /><b>{{ stage.name }}</b></span>
            <span class="muted small fig">{{ stage.count }}</span>
            <span class="muted small fig tot">{{ money(stage.value, currency) }}</span>
            <span class="bar" :style="{ '--c': color(stage) }" />
          </header>
          <div class="cards">
            <Link v-for="deal in stage.deals" :key="deal.id" :href="`/deals/${deal.id}`" class="dcard" draggable="true" @dragstart="drag = deal.id" @dragend="drag = null">
              <div class="row1"><span class="muted small fig">#{{ String(deal.id).padStart(4, '0') }}</span><span class="muted small fig" :class="{ late: deal.stale }">{{ deal.days_in_stage }}d</span></div>
              <div class="t">{{ deal.title }}</div>
              <div class="muted small acct">{{ deal.account }}</div>
              <div class="row2">
                <b class="fig">{{ deal.value ? money(deal.value, deal.currency) : '—' }}</b>
                <span v-if="deal.score != null" class="score"><i :style="{ background: scoreColor(deal.score) }" />{{ deal.score }}</span>
                <UserAvatar :name="deal.owner ?? '?'" size="xs" style="margin-left:auto" />
              </div>
              <div v-if="deal.unreplied_minutes !== null && stage.kind === 'open'" class="flag hot"><MessageCircle class="icon xs" />Unreplied {{ unrepliedText(deal.unreplied_minutes) }}</div>
              <div v-else-if="deal.stale" class="flag warn"><Hourglass class="icon xs" />Stuck · limit {{ deal.stale_after_days }} days</div>
            </Link>
            <button v-if="stage.kind === 'open'" type="button" class="add" @click="newDeal(stage.id)"><Plus class="icon xs" />Add deal</button>
            <p v-else-if="!stage.deals.length" class="muted small drop">{{ stage.kind === 'lost' ? 'Drop here to mark lost' : 'Drop here to mark won' }}</p>
          </div>
        </section>
      </div>
      <p class="synthetic">Synthetic demo data. Mixed currencies are shown as entered; column totals use estimated IDR rates.</p>
    </div>

    <!-- table -->
    <div v-else-if="view === 'table'" class="content">
      <div class="panel"><div class="tablewrap">
        <table class="t">
          <thead><tr><th>Deal</th><th>Company</th><th>Stage</th><th class="n">Value</th><th class="n">Score</th><th>Owner</th><th>Close</th><th class="n">In stage</th></tr></thead>
          <tbody>
            <tr v-for="d in rows" :key="d.id">
              <td><Link :href="`/deals/${d.id}`" class="rowlink"><b>{{ d.title }}</b></Link></td>
              <td class="muted">{{ d.account }}</td>
              <td><span class="stage"><i :style="{ background: color(d.stage) }" />{{ d.stage.name }}</span></td>
              <td class="n fig">{{ d.value ? money(d.value, d.currency) : '—' }}</td>
              <td class="n"><span v-if="d.score != null" class="score"><i :style="{ background: scoreColor(d.score) }" />{{ d.score }}</span></td>
              <td>{{ d.owner }}</td>
              <td class="muted">{{ d.expected_close ? shortDate(d.expected_close) : '—' }}</td>
              <td class="n fig" :class="{ late: d.stale }">{{ d.days_in_stage }}d</td>
            </tr>
            <tr v-if="!rows.length"><td colspan="8" class="muted" style="text-align:center;padding:28px 0">No deals match these filters.</td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- forecast -->
    <div v-else class="content">
      <div class="panel"><div class="tablewrap">
        <table class="t">
          <thead><tr><th>Stage</th><th class="n">Deals</th><th class="n">Value</th><th class="n">Win chance</th><th class="n">Weighted</th></tr></thead>
          <tbody>
            <tr v-for="s in open" :key="s.id">
              <td><span class="stage"><i :style="{ background: color(s) }" />{{ s.name }}</span></td>
              <td class="n fig">{{ s.count }}</td><td class="n fig">{{ money(s.value, currency) }}</td>
              <td class="n fig">{{ Math.round(s.probability * 100) }}%</td><td class="n fig"><b>{{ money(s.weighted, currency) }}</b></td>
            </tr>
            <tr class="tot"><td><b>Open pipeline</b></td><td class="n fig">{{ open.reduce((a, s) => a + s.count, 0) }}</td><td class="n fig"><b>{{ money(totals.value, currency) }}</b></td><td /><td class="n fig"><b>{{ money(totals.weighted, currency) }}</b></td></tr>
          </tbody>
        </table>
      </div></div>
      <p class="synthetic">Weighted value = stage value × a fixed win chance per stage (10 / 25 / 45 / 65%). Rates for non-IDR deals are estimates.</p>
    </div>

    <Modal v-if="creating" title="New deal" @close="creating = false">
      <form class="form" @submit.prevent="createDeal">
        <div class="field"><label for="nd-title">Deal name</label><input id="nd-title" v-model="form.title" class="input" maxlength="160" placeholder="e.g. Website redesign retainer" autocomplete="off"><span v-if="form.errors.title" class="ferr">{{ form.errors.title }}</span></div>
        <div class="field"><label>Contact</label><RSelect v-model="form.contact_id" :options="contacts" placeholder="Choose a contact" label="Contact" block /><span v-if="form.errors.contact_id" class="ferr">{{ form.errors.contact_id }}</span></div>
        <div class="row2">
          <div class="field"><label>Stage</label><RSelect v-model="form.stage_id" :options="stageOptions" label="Stage" block /></div>
          <div class="field"><label>Expected close</label><DateField v-model="form.expected_close" label="Expected close" /></div>
        </div>
        <div class="row2">
          <div class="field"><label for="nd-val">Value</label><input id="nd-val" v-model="form.value" class="input" inputmode="decimal" placeholder="0" autocomplete="off"><span v-if="form.errors.value" class="ferr">{{ form.errors.value }}</span></div>
          <div class="field"><label>Currency</label><RSelect v-model="form.currency" :options="CURRENCIES" label="Currency" block /></div>
        </div>
        <div class="actions"><button type="button" class="btn ghost" @click="creating = false">Cancel</button><button type="submit" class="btn primary" :disabled="form.processing"><Target class="icon sm" />Create deal</button></div>
      </form>
    </Modal>

    <Modal v-if="lost" title="Mark deal lost" width="400px" @close="lost = null">
      <form class="form" @submit.prevent="confirmLost">
        <p class="muted small">A reason is required. The deal moves to Lost and closes.</p>
        <div class="field"><label>Lost reason</label><RSelect v-model="lostForm.lost_reason" :options="LOST_REASONS" label="Lost reason" block /><span v-if="lostForm.errors.lost_reason" class="ferr">{{ lostForm.errors.lost_reason }}</span></div>
        <div class="actions"><button type="button" class="btn ghost" @click="lost = null">Cancel</button><button type="submit" class="btn primary" :disabled="lostForm.processing">Mark lost</button></div>
      </form>
    </Modal>
  </AppShell>
</template>

<style scoped>
.toolbar{display:flex;align-items:center;gap:var(--s3);padding:10px var(--s5);border-bottom:1px solid var(--line);background:var(--surface);flex-wrap:wrap}
.sum{margin-left:auto;color:var(--text-3);font-size:13px}.sum b{color:var(--text)}
.board-scroll{overflow-x:auto}
.board{display:flex;gap:14px;align-items:flex-start;padding-bottom:8px}
.col{flex:0 0 250px;min-height:140px}
.col.over .cards{outline:2px dashed var(--brand);outline-offset:-2px;border-radius:var(--r)}
.col-h{position:relative;display:flex;align-items:center;gap:8px;padding:4px 2px 12px}
.col-h .tot{margin-left:auto}
.col-h .bar{position:absolute;left:0;right:0;bottom:4px;height:3px;border-radius:3px;background:var(--c)}
.cards{display:grid;gap:10px;padding:2px}
.dcard{display:block;background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:10px 12px;color:var(--text);transition:box-shadow .12s ease,border-color .12s ease}
.dcard:hover{text-decoration:none;border-color:var(--line-strong);box-shadow:var(--pop)}
.dcard .row1{display:flex;justify-content:space-between;margin-bottom:4px}
.dcard .t{font-weight:600;font-size:13px}
.dcard .acct{margin-top:1px}
.dcard .row2{display:flex;align-items:center;gap:8px;margin-top:8px}
.late{color:var(--hot);font-weight:600}
.flag{display:flex;align-items:center;gap:6px;margin-top:8px;padding-top:8px;border-top:1px dashed var(--line);font-size:12px}
.flag.hot{color:var(--hot)}.flag.warn{color:var(--warning)}
.add{display:flex;align-items:center;justify-content:center;gap:6px;height:30px;border:1px dashed var(--line-strong);border-radius:var(--r);background:none;color:var(--text-3);font-size:12px}
.add:hover{background:var(--hover);color:var(--text)}
.drop{border:1px dashed var(--line-strong);border-radius:var(--r);padding:16px;text-align:center}
tr.tot td{background:var(--surface-2)}
@media (max-width:720px){.sum{margin-left:0;width:100%}.col{flex-basis:84vw}}
</style>
