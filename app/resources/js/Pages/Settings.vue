<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import RSelect from '@/Components/RSelect.vue'
import TimeField from '@/Components/TimeField.vue'
import Modal from '@/Components/Modal.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { ArrowDown, ArrowUp, Clock, FileText, KanbanSquare, MessageCircle, Plus, Sparkles, Trash2, Users } from 'lucide-vue-next'
import { dateTime } from '@/lib/format'

const props = defineProps({
  company: Object, users: Array, pipelines: Array, templates: Array, ai: Object,
  whatsappNumbers: Array, workingHours: Array, aiRuns: Array,
})

const sections = [
  { key: 'team', label: 'Team', icon: Users }, { key: 'pipeline', label: 'Pipeline', icon: KanbanSquare },
  { key: 'templates', label: 'Message templates', icon: FileText }, { key: 'channels', label: 'WhatsApp and hours', icon: MessageCircle },
  { key: 'ai', label: 'AI', icon: Sparkles },
]
const section = ref('team')
const ROLES = [{ value: 'admin', label: 'Admin' }, { value: 'manager', label: 'Manager' }, { value: 'rep', label: 'Rep' }]

const setRole = (u, role) => router.post(`/settings/users/${u.id}/role`, { role }, { preserveScroll: true })
const setActive = (u) => router.post(`/settings/users/${u.id}/active`, { is_active: !u.is_active }, { preserveScroll: true })

// pipeline stages
const drafts = ref({})
const draft = (s) => (drafts.value[s.id] ??= { name: s.name, days: s.stale_after_days ?? '' })
function saveStage(s) {
  const d = draft(s)
  if (d.name === s.name && String(d.days) === String(s.stale_after_days ?? '')) return
  router.patch(`/settings/stages/${s.id}`, { name: d.name, stale_after_days: d.days === '' ? null : d.days }, { preserveScroll: true })
}
const moveStage = (s, direction) => router.post(`/settings/stages/${s.id}/move`, { direction }, { preserveScroll: true })
const removeStage = (s) => router.delete(`/settings/stages/${s.id}`, { preserveScroll: true })
const newStage = useForm({ name: '', stale_after_days: '' })
const addStage = (p) => newStage.post(`/settings/pipelines/${p.id}/stages`, { preserveScroll: true, onSuccess: () => newStage.reset() })

// templates
const editing = ref(null)
const tpl = useForm({ name: '', language: 'id', category: 'utility', body: '' })
const LANGS = [{ value: 'id', label: 'Indonesian' }, { value: 'en', label: 'English' }, { value: 'en_US', label: 'English (US)' }]
const CATS = [{ value: 'utility', label: 'Utility' }, { value: 'marketing', label: 'Marketing' }, { value: 'authentication', label: 'Authentication' }]
function editTemplate(t) {
  tpl.clearErrors()
  if (t) tpl.defaults({ name: t.name, language: t.language, category: t.category, body: t.body }).reset()
  else tpl.defaults({ name: '', language: 'id', category: 'utility', body: '' }).reset()
  editing.value = t ?? {}
}
function saveTemplate() {
  const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) }
  editing.value.id ? tpl.patch(`/settings/templates/${editing.value.id}`, opts) : tpl.post('/settings/templates', opts)
}
const removeTemplate = (t) => router.delete(`/settings/templates/${t.id}`, { preserveScroll: true })

// hours
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const hoursForm = useForm({
  days: [1, 2, 3, 4, 5].map((weekday) => {
    const e = props.workingHours.find((h) => h.weekday === weekday)
    return { weekday, start_time: e?.start_time?.slice(0, 5) ?? '09:00', end_time: e?.end_time?.slice(0, 5) ?? '17:00' }
  }),
})
const saveHours = () => hoursForm.post('/settings/working-hours', { preserveScroll: true })
const pipeline = computed(() => props.pipelines[0])
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h"><h1>Settings</h1></div>
      <div class="views"><a v-for="s in sections" :key="s.key" href="#" :aria-current="section === s.key ? 'page' : undefined" @click.prevent="section = s.key"><component :is="s.icon" class="icon" />{{ s.label }}</a></div>
    </template>

    <Topbar title="Settings"><template #left><span class="muted small">{{ company.name }}</span></template></Topbar>
    <div class="content narrow">
      <div class="chips mchips" role="tablist"><button v-for="s in sections" :key="s.key" type="button" class="chip" :class="{ on: section === s.key }" @click="section = s.key">{{ s.label }}</button></div>

      <section v-if="section === 'team'" class="panel">
        <div class="panel-h"><h2>Team</h2><span class="muted small" style="margin-left:auto">Admins manage settings. Managers see every deal. Reps see their own.</span></div>
        <div class="tablewrap"><table class="t">
          <thead><tr><th>Person</th><th>Role</th><th>Active</th></tr></thead>
          <tbody><tr v-for="u in users" :key="u.id">
            <td><span class="who"><UserAvatar :name="u.name" /><span><b>{{ u.name }}</b><small class="muted">{{ u.email }}</small></span></span></td>
            <td style="width:150px"><RSelect :model-value="u.role" :options="ROLES" :label="`Role for ${u.name}`" size="sm" block @change="(v) => setRole(u, v)" /></td>
            <td><button type="button" class="swt" role="switch" :aria-checked="u.is_active" :aria-label="`${u.name} active`" @click="setActive(u)"><span class="track" /></button></td>
          </tr></tbody>
        </table></div>
      </section>

      <section v-else-if="section === 'pipeline' && pipeline" class="panel">
        <div class="panel-h"><h2>{{ pipeline.name }} stages</h2><span class="muted small" style="margin-left:auto">A deal is "stuck" after the days set here. Won and Lost are fixed.</span></div>
        <ul class="stages">
          <li v-for="(s, i) in pipeline.stages" :key="s.id" :class="{ fixed: s.kind !== 'open' }">
            <input v-model="draft(s).name" class="input" :aria-label="`Name of stage ${s.name}`" maxlength="60" :disabled="s.kind !== 'open'" @blur="saveStage(s)" @keydown.enter.prevent="$event.target.blur()">
            <label v-if="s.kind === 'open'" class="days"><input v-model="draft(s).days" class="input" inputmode="numeric" placeholder="—" :aria-label="`Stuck after days, ${s.name}`" @blur="saveStage(s)" @keydown.enter.prevent="$event.target.blur()"><span class="muted small">days</span></label>
            <span v-else class="badge" :class="s.kind === 'won' ? 'success' : 'danger'">{{ s.kind }}</span>
            <span class="muted small fig cnt">{{ s.deals }} deals</span>
            <span v-if="s.kind === 'open'" class="ctl">
              <button type="button" class="ib" :disabled="i === 0" aria-label="Move up" @click="moveStage(s, 'up')"><ArrowUp class="icon sm" /></button>
              <button type="button" class="ib" :disabled="pipeline.stages[i + 1]?.kind !== 'open'" aria-label="Move down" @click="moveStage(s, 'down')"><ArrowDown class="icon sm" /></button>
              <button type="button" class="ib" :disabled="s.deals > 0" :title="s.deals ? 'Move its deals out first' : 'Remove stage'" aria-label="Remove stage" @click="removeStage(s)"><Trash2 class="icon sm" /></button>
            </span>
          </li>
        </ul>
        <form class="addrow" @submit.prevent="addStage(pipeline)">
          <input v-model="newStage.name" class="input" placeholder="New stage name" aria-label="New stage name" maxlength="60" autocomplete="off">
          <input v-model="newStage.stale_after_days" class="input" style="width:90px" inputmode="numeric" placeholder="Days" aria-label="Stuck after days" autocomplete="off">
          <button class="btn primary" :disabled="!newStage.name || newStage.processing"><Plus class="icon sm" />Add stage</button>
          <span v-if="newStage.errors.name" class="ferr">{{ newStage.errors.name }}</span>
        </form>
        <p v-if="$page.props.errors?.stage" class="ferr" style="padding:0 16px 14px">{{ $page.props.errors.stage }}</p>
      </section>

      <section v-else-if="section === 'templates'" class="panel">
        <div class="panel-h"><h2>Message templates</h2><button type="button" class="btn sm primary" style="margin-left:auto" @click="editTemplate(null)"><Plus class="icon xs" />New template</button></div>
        <ul class="tpls">
          <li v-for="t in templates" :key="t.id">
            <div class="tm"><b>{{ t.name }}</b><span class="badge">{{ t.language }}</span><span class="badge">{{ t.category }}</span><span class="badge" :class="t.status === 'approved' ? 'success' : 'warning'">{{ t.status }}</span></div>
            <p class="muted">{{ t.body }}</p>
            <div class="tc"><button type="button" class="btn sm" @click="editTemplate(t)">Edit</button><button type="button" class="btn sm ghost" @click="removeTemplate(t)"><Trash2 class="icon xs" />Remove</button></div>
          </li>
          <li v-if="!templates.length" class="muted small">No templates yet. Outside the 24-hour window only approved templates can be sent.</li>
        </ul>
        <p class="muted small" style="padding:0 16px 14px">Use <code v-text="'{{1}}'" /> for the contact's first name. In live mode a template must also be approved by Meta before it can be used.</p>
      </section>

      <div v-else-if="section === 'channels'" class="two">
        <section class="panel">
          <div class="panel-h"><h2>WhatsApp channel</h2></div>
          <div class="panel-b">
            <div v-for="n in whatsappNumbers" :key="n.id" class="chan"><b class="fig">{{ n.display_phone }}</b><span class="badge" :class="n.mode === 'live' ? 'success' : ''">{{ n.mode }}</span></div>
            <p class="muted small" style="margin-top:10px">Live mode uses the WhatsApp Cloud API from Meta. Simulated mode produces messages locally so the product can be demoed without a number.</p>
          </div>
        </section>
        <section class="panel">
          <div class="panel-h"><h2><Clock class="icon sm" style="vertical-align:-3px" /> Working hours</h2></div>
          <form class="panel-b form" @submit.prevent="saveHours">
            <div v-for="d in hoursForm.days" :key="d.weekday" class="hrow"><span class="dn">{{ DAYS[d.weekday] }}</span><TimeField v-model="d.start_time" :label="`${DAYS[d.weekday]} start`" /><span class="muted">to</span><TimeField v-model="d.end_time" :label="`${DAYS[d.weekday]} end`" /></div>
            <span v-if="hoursForm.errors['days.0.end_time']" class="ferr">End time must be after start time.</span>
            <div class="actions"><button class="btn primary" :disabled="hoursForm.processing">Save hours</button></div>
          </form>
        </section>
      </div>

      <section v-else class="panel">
        <div class="panel-h"><h2>AI</h2><span class="badge" :class="ai.live ? 'success' : ''" style="margin-left:auto">{{ ai.live ? 'Live model' : 'Simulated' }}</span></div>
        <div class="panel-b"><p>Summaries, reply drafts and lead scores are written by <b>{{ ai.model }}</b>. Card and bank numbers are masked before any text is sent to the model.</p>
          <p class="muted small" style="margin-top:6px">Set <code>GROQ_API_KEY</code> or <code>ANTHROPIC_API_KEY</code> in the environment to use a live model. Without one, a deterministic simulated model is used.</p></div>
        <div class="tablewrap"><table class="t"><thead><tr><th>Task</th><th>Model</th><th class="n">Tokens in / out</th><th>Status</th><th>When</th></tr></thead>
          <tbody><tr v-for="(r, i) in aiRuns" :key="i"><td>{{ r.kind }}</td><td class="muted">{{ r.model }}</td><td class="n fig">{{ r.input_tokens }} / {{ r.output_tokens }}</td><td><span class="badge" :class="r.status === 'ok' ? 'success' : 'danger'">{{ r.status }}</span></td><td class="muted">{{ dateTime(r.created_at) }}</td></tr>
          <tr v-if="!aiRuns.length"><td colspan="5" class="muted" style="text-align:center;padding:22px 0">No AI runs yet.</td></tr></tbody></table></div>
      </section>
    </div>

    <Modal v-if="editing" :title="editing.id ? 'Edit template' : 'New template'" width="520px" @close="editing = null">
      <form class="form" @submit.prevent="saveTemplate">
        <div class="field"><label for="t-name">Name</label><input id="t-name" v-model="tpl.name" class="input" placeholder="follow_up_proposal" autocomplete="off"><span class="muted small">Lowercase letters, numbers and underscores.</span><span v-if="tpl.errors.name" class="ferr">{{ tpl.errors.name }}</span></div>
        <div class="row2"><div class="field"><label>Language</label><RSelect v-model="tpl.language" :options="LANGS" label="Language" block /></div><div class="field"><label>Category</label><RSelect v-model="tpl.category" :options="CATS" label="Category" block /></div></div>
        <div class="field"><label for="t-body">Message</label><textarea id="t-body" v-model="tpl.body" class="input" rows="4" maxlength="1024" /><span v-if="tpl.errors.body" class="ferr">{{ tpl.errors.body }}</span></div>
        <div class="actions"><button type="button" class="btn ghost" @click="editing = null">Cancel</button><button class="btn primary" :disabled="tpl.processing">Save template</button></div>
      </form>
    </Modal>
  </AppShell>
</template>

<style scoped>
.narrow{max-width:960px}
.mchips{display:none;margin-bottom:var(--s4);flex-wrap:wrap}
.who{display:flex;align-items:center;gap:10px}.who span{display:grid}.who small{font-size:12px}
.stages{list-style:none;padding:0}
.stages li{display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--line)}
.stages li>.input{max-width:260px}
.stages .days{display:flex;align-items:center;gap:6px}.stages .days .input{width:64px}
.stages .cnt{margin-left:auto;min-width:64px;text-align:right}.stages .ctl{display:flex;gap:2px}
.stages .fixed .input{background:var(--surface-2);color:var(--text-3)}
.addrow{display:flex;gap:8px;align-items:center;padding:14px 16px;flex-wrap:wrap}.addrow .input:first-child{max-width:260px}
.tpls{list-style:none;padding:0}.tpls li{padding:14px 16px;border-bottom:1px solid var(--line);display:grid;gap:6px}
.tm{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.tc{display:flex;gap:6px}
.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--s5);align-items:start}
.chan{display:flex;gap:10px;align-items:center;padding:4px 0}
.hrow{display:flex;align-items:center;gap:10px}.dn{width:36px;font-weight:500}
code{font-size:12px;background:var(--hover);padding:1px 5px;border-radius:4px}
@media (max-width:1180px){.mchips{display:flex}.two{grid-template-columns:minmax(0,1fr)}}
@media (max-width:720px){.stages li{flex-wrap:wrap}.stages .cnt{margin-left:0}}
</style>
