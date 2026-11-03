<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import RSelect from '@/Components/RSelect.vue'
import Timeline from '@/Components/Timeline.vue'
import Topbar from '@/Components/Topbar.vue'
import { money } from '@/lib/format'
import UserAvatar from '@/Components/UserAvatar.vue'
import ScoreDot from '@/Components/ScoreDot.vue'
import { Phone, Mail, Building2, GitMerge, StickyNote, MessageCircle, GitBranch, CheckCheck, Info } from 'lucide-vue-next'

const props = defineProps({
  contact: { type: Object, required: true },
  deals: { type: Array, default: () => [] },
  scoreDetail: { type: Object, default: null },
  activities: { type: Array, default: () => [] },
  duplicates: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
})

const page = usePage()
const canOverride = computed(() => ['admin', 'manager'].includes(page.props.auth.user?.role))

const overrideForm = useForm({ to: props.contact.score ?? 50, reason: '' })

function submitOverride() {
  overrideForm.post(`/contacts/${props.contact.id}/score-override`, {
    preserveScroll: true,
    onSuccess: () => overrideForm.reset(),
  })
}

const editForm = useForm({
  name: props.contact.name,
  phone: props.contact.phone_e164 ?? '',
  email: props.contact.email ?? '',
  owner_id: props.contact.owner?.id ?? null,
})

const editing = ref(false)

function save() {
  editForm.patch(`/contacts/${props.contact.id}`, {
    onSuccess: () => { editing.value = false },
  })
}

const noteForm = useForm({ body: '' })

function addNote() {
  router.post(`/contacts/${props.contact.id}/notes`, noteForm.body ? { body: noteForm.body } : {}, {
    onSuccess: () => noteForm.reset(),
  })
}

const mergeForm = useForm({ primary_id: props.contact.id, duplicate_id: null })

function mergeWith(duplicateId) {
  mergeForm.duplicate_id = duplicateId
  mergeForm.post('/contacts/merge')
}

const typeIcon = {
  note: StickyNote,
  message: MessageCircle,
  stage_change: GitBranch,
  task: CheckCheck,
  contact_merged: GitMerge,
  assignment: UserAvatar,
}

function typeLabel(activity) {
  return ({
    note: 'Note',
    message: 'WhatsApp message',
    stage_change: 'Stage changed',
    task: 'Task',
    contact_merged: 'Contact merged',
    assignment: 'Assignment',
    score_override: 'Score override',
  })[activity.type] ?? activity.type
}

function fmt(iso) {
  return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h">
        <h1>Contacts</h1>
      </div>
      <div class="views">
        <Link href="/contacts">
          <svg class="icon" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6" /></svg>
          Back to all contacts
        </Link>
      </div>
    </template>

    <Topbar title="Contacts">
      <template #left><span class="crumb">/ <b>{{ contact.name }}</b></span><span v-if="contact.source === 'whatsapp'" class="badge wa">WhatsApp</span></template>
      <button type="button" class="btn" @click="editing = !editing">{{ editing ? 'Close' : 'Edit' }}</button>
      <button type="button" class="btn primary" @click="router.get('/pipeline', { new: contact.id })">Create deal</button>
    </Topbar>

    <div class="content cols2">
      <div style="display:grid;gap:20px;min-width:0">
        <form v-if="editing" class="panel" style="padding:16px;display:grid;gap:12px" @submit.prevent="save">
          <div>
            <label class="f-label">Name</label>
            <input v-model="editForm.name" class="f-input" required>
          </div>
          <div>
            <label class="f-label">Phone</label>
            <input v-model="editForm.phone" class="f-input">
            <div v-if="editForm.errors.phone" class="f-error">{{ editForm.errors.phone }}</div>
          </div>
          <div>
            <label class="f-label">Email</label>
            <input v-model="editForm.email" class="f-input" type="email">
            <div v-if="editForm.errors.email" class="f-error">{{ editForm.errors.email }}</div>
          </div>
          <div>
            <label class="f-label">Owner</label>
            <RSelect v-model="editForm.owner_id" :options="[{ value: null, label: 'Unassigned' }, ...users.map((u) => ({ value: u.id, label: u.name }))]" label="Owner" block />
          </div>
          <div style="display:flex;justify-content:flex-end;gap:8px">
            <button type="button" class="btn ghost" @click="editing = false">Cancel</button>
            <button type="submit" class="btn primary" :disabled="editForm.processing">Save</button>
          </div>
        </form>

        <div class="panel">
          <div class="panel-h">
            <h2>Timeline</h2>
          </div>
          <div class="panel-b" style="display:grid;gap:10px">
            <form style="display:flex;gap:8px" @submit.prevent="addNote">
              <input
                v-model="noteForm.body"
                class="f-input"
                style="flex:1"
                placeholder="Add a note… @mention to notify"
              >
              <button class="btn primary" :disabled="noteForm.processing || !noteForm.body">Add note</button>
            </form>

            <Timeline :items="activities" />
            <p v-if="activities.length === 0" class="muted small">No activity yet.</p>
          </div>
        </div>
      </div>

      <aside style="display:grid;gap:16px">
        <div class="panel">
          <div class="panel-b kv">
            <span class="k"><Phone class="icon xs" /> Phone</span>
            <span class="v num">{{ contact.phone_e164 ?? '—' }}</span>
            <span class="k"><Mail class="icon xs" /> Email</span>
            <span class="v">{{ contact.email ?? '—' }}</span>
            <span class="k"><Building2 class="icon xs" /> Account</span>
            <span class="v">{{ contact.account?.name ?? '—' }}</span>
            <span class="k">Owner</span>
            <span class="v">{{ contact.owner?.name ?? 'Unassigned' }}</span>
            <span class="k">Score</span>
            <span class="v"><ScoreDot :score="contact.score" /><span v-if="contact.score == null" class="muted">not scored</span></span>
          </div>
        </div>

        <div v-if="scoreDetail" class="panel">
          <div class="panel-h">
            <h2>Why this score</h2>
          </div>
          <div class="panel-b" style="display:grid;gap:8px">
            <div v-for="(reason, i) in scoreDetail.reasons" :key="i" class="note" style="padding:8px 10px">
              <Info class="icon xs" />
              <span>{{ reason.label }}</span>
            </div>
            <div class="muted small">
              Structured {{ scoreDetail.structured_part }} + AI adjustment {{ scoreDetail.ai_adjustment }}
            </div>

            <form v-if="canOverride" style="display:grid;gap:8px;border-top:1px solid var(--line);padding-top:10px" @submit.prevent="submitOverride">
              <label class="f-label">Manager override</label>
              <div style="display:flex;gap:8px">
                <input v-model.number="overrideForm.to" type="number" min="0" max="100" class="f-input" style="width:80px">
                <input v-model="overrideForm.reason" class="f-input" style="flex:1" placeholder="Why?" required>
                <button class="btn sm" :disabled="overrideForm.processing">Apply</button>
              </div>
              <div v-if="overrideForm.errors.reason" class="f-error">{{ overrideForm.errors.reason }}</div>
            </form>
          </div>
        </div>

        <div class="panel">
          <div class="panel-h">
            <h2>Deals</h2>
          </div>
          <div class="panel-b" style="display:grid;gap:8px">
            <div v-for="deal in deals" :key="deal.id" class="dealrow">
              <Link :href="`/deals/${deal.id}`" class="rowlink"><b>{{ deal.title }}</b></Link>
              <span class="badge" :class="deal.stage_kind === 'won' ? 'success' : deal.stage_kind === 'lost' ? 'danger' : ''">{{ deal.stage }}</span>
              <span class="muted small fig">{{ deal.value ? money(deal.value, deal.currency) : '—' }}</span>
            </div>
            <p v-if="deals.length === 0" class="muted small">No deals yet.</p>
          </div>
        </div>

        <div v-if="duplicates.length" class="panel">
          <div class="panel-h">
            <h2>Possible duplicates</h2>
          </div>
          <div class="panel-b" style="display:grid;gap:8px">
            <div v-for="dup in duplicates" :key="dup.id" style="display:flex;align-items:center;gap:8px">
              <UserAvatar :name="dup.name" size="xs" />
              <span style="font-size:13px">{{ dup.name }}</span>
              <button class="btn sm" style="margin-left:auto" @click="mergeWith(dup.id)">
                Merge
              </button>
            </div>
            <p class="muted small">Merging moves timeline, deals and conversations to this contact.</p>
          </div>
        </div>
      </aside>
    </div>
  </AppShell>
</template>

<style scoped>
.dealrow{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dealrow .fig{margin-left:auto}
.f-label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: var(--text-3);
  margin-bottom: 4px;
}
.f-input {
  width: 100%;
  height: 32px;
  padding: 0 10px;
  border: 1px solid var(--line-strong);
  border-radius: var(--r-sm);
  font: inherit;
  font-size: 13px;
  background: var(--surface);
}
.f-input:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px var(--brand-soft);
}
.f-error {
  color: var(--danger);
  font-size: 12px;
  margin-top: 4px;
}
.tl-item {
  display: flex;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid var(--line);
}
</style>
