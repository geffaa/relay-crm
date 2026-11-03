<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import Modal from '@/Components/Modal.vue'
import { Plus, Rocket, Layers, Sparkles, MessageCircle, BadgeCheck, XCircle } from 'lucide-vue-next'

const props = defineProps({
  leads: { type: Object, required: true },
  status: { type: String, default: 'open' },
})

const statuses = [
  { key: 'open', label: 'Open', icon: Layers },
  { key: 'new', label: 'New', icon: Sparkles },
  { key: 'contacted', label: 'Contacted', icon: MessageCircle },
  { key: 'qualified', label: 'Qualified', icon: BadgeCheck },
  { key: 'converted', label: 'Converted', icon: Rocket },
  { key: 'lost', label: 'Lost', icon: XCircle },
]

function switchStatus(key) {
  router.get('/leads', { status: key }, { preserveState: true })
}

const showNew = ref(false)
const newForm = useForm({ name: '', phone: '', email: '', notes: '', source: 'manual' })

function createLead() {
  newForm.post('/leads', { onSuccess: () => { showNew.value = false; newForm.reset() } })
}

const converting = ref(null)
const convertForm = useForm({ deal_title: '', deal_value: '', account_name: '' })

function openConvert(lead) {
  converting.value = lead
  convertForm.deal_title = `New business — ${lead.name}`
  convertForm.deal_value = ''
  convertForm.account_name = ''
}

function confirmConvert() {
  convertForm.post(`/leads/${converting.value.id}/convert`, {
    preserveScroll: true,
    onSuccess: () => { converting.value = null },
  })
}

function setStatus(lead, status) {
  router.post(`/leads/${lead.id}/status`, { status }, { preserveScroll: true })
}
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h">
        <h1>Leads</h1>
        <div class="acts">
          <button class="ib" title="New lead" @click="showNew = true"><Plus class="icon" /></button>
        </div>
      </div>
      <div class="views">
        <a
          v-for="s in statuses"
          :key="s.key"
          href="#"
          :aria-current="status === s.key ? 'page' : undefined"
          @click.prevent="switchStatus(s.key)"
        >
          <component :is="s.icon" class="icon" />
          {{ s.label }}
        </a>
      </div>
    </template>

    <Topbar title="Leads" hint="qualify, then convert in one click">
      <button type="button" class="btn primary" @click="showNew = true"><Plus class="icon sm" />New lead</button>
    </Topbar>

    <div class="content">
      <div class="panel">
        <div class="tablewrap">
          <table class="t">
            <thead>
              <tr><th>Name</th><th>Phone</th><th>Email</th><th>Source</th><th>Status</th><th>Owner</th><th /></tr>
            </thead>
            <tbody>
              <tr v-for="lead in leads.data" :key="lead.id">
                <td><b style="font-weight:500">{{ lead.name }}</b></td>
                <td class="num">{{ lead.phone_e164 ?? '—' }}</td>
                <td class="muted">{{ lead.email ?? '—' }}</td>
                <td><span class="badge" :class="{ wa: lead.source === 'whatsapp' }">{{ lead.source }}</span></td>
                <td><span class="badge" :class="{ brand: lead.status === 'qualified', success: lead.status === 'converted' }">{{ lead.status }}</span></td>
                <td class="muted">{{ lead.owner?.name ?? '—' }}</td>
                <td>
                  <div style="display:flex;gap:6px;justify-content:flex-end">
                    <button v-if="lead.status !== 'converted'" class="btn sm primary" @click="openConvert(lead)">
                      <Rocket class="icon xs" /> Convert
                    </button>
                    <button v-if="lead.status === 'new'" class="btn sm" @click="setStatus(lead, 'contacted')">
                      Mark contacted
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="leads.data.length === 0">
                <td colspan="7" class="muted" style="height:80px;text-align:center">No leads here yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p class="synthetic">Converting deduplicates by E.164 phone / email — an existing contact is reused, never duplicated.</p>
    </div>

    <Modal v-if="showNew" title="New lead" width="440px" @close="showNew = false">
      <form class="form" @submit.prevent="createLead">
        <div class="field"><label for="l-name">Name</label><input id="l-name" v-model="newForm.name" class="input" required autocomplete="off"></div>
        <div class="field"><label for="l-phone">Phone</label><input id="l-phone" v-model="newForm.phone" class="input" placeholder="+62 812 3456 789" autocomplete="off"><span v-if="newForm.errors.phone" class="ferr">{{ newForm.errors.phone }}</span></div>
        <div class="field"><label for="l-mail">Email</label><input id="l-mail" v-model="newForm.email" class="input" type="email" autocomplete="off"></div>
        <div class="field"><label for="l-notes">Notes</label><textarea id="l-notes" v-model="newForm.notes" class="input" rows="2" placeholder="What do they want?" /></div>
        <div class="actions"><button type="button" class="btn ghost" @click="showNew = false">Cancel</button><button type="submit" class="btn primary" :disabled="newForm.processing">Create lead</button></div>
      </form>
    </Modal>

    <Modal v-if="converting" :title="`Convert ${converting.name}`" width="440px" @close="converting = null">
      <form class="form" @submit.prevent="confirmConvert">
        <p class="muted small">Creates a contact, or reuses the one that already has this phone or email<template v-if="converting.phone_e164"> ({{ converting.phone_e164 }})</template>.</p>
        <div class="field"><label for="v-acc">Company (optional)</label><input id="v-acc" v-model="convertForm.account_name" class="input" placeholder="Company name" autocomplete="off"></div>
        <div class="field"><label for="v-title">Deal name</label><input id="v-title" v-model="convertForm.deal_title" class="input" autocomplete="off"></div>
        <div class="field"><label for="v-val">Deal value</label><input id="v-val" v-model="convertForm.deal_value" class="input" inputmode="decimal" placeholder="Leave empty for none" autocomplete="off"></div>
        <div class="actions"><button type="button" class="btn ghost" @click="converting = null">Cancel</button><button type="submit" class="btn primary" :disabled="convertForm.processing">Convert</button></div>
      </form>
    </Modal>
  </AppShell>
</template>
