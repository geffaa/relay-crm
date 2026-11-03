<script setup>
import { ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import Topbar from '@/Components/Topbar.vue'
import Modal from '@/Components/Modal.vue'
import ScoreDot from '@/Components/ScoreDot.vue'
import { Search, Plus, Upload, Users, UserRound } from 'lucide-vue-next'

const props = defineProps({
  contacts: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const q = ref(props.filters.q ?? '')
const showNew = ref(false)

watch(q, (value) => {
  router.get('/contacts', { q: value || undefined, owner: props.filters.owner || undefined }, { preserveState: true, replace: true, preserveScroll: true })
})

const newForm = useForm({ name: '', phone: '', email: '' })

function createContact() {
  newForm.post('/contacts', {
    onSuccess: () => {
      showNew.value = false
      newForm.reset()
    },
  })
}

function sourceBadge(source) {
  return source === 'whatsapp' ? 'wa' : ''
}
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h">
        <h1>Contacts</h1>
        <div class="acts">
          <button class="ib" title="New contact" @click="showNew = true">
            <Plus class="icon" />
          </button>
        </div>
      </div>
      <div class="views">
        <Link href="/contacts" :aria-current="filters.owner !== 'mine' ? 'page' : undefined"><Users class="icon" />All contacts<span class="n">{{ contacts.total }}</span></Link>
        <Link href="/contacts?owner=mine" :aria-current="filters.owner === 'mine' ? 'page' : undefined"><UserRound class="icon" />Mine</Link>
        <Link href="/contacts/import"><Upload class="icon" />Import CSV</Link>
      </div>
    </template>

    <Topbar title="Contacts">
      <button type="button" class="btn primary" @click="showNew = true"><Plus class="icon sm" />New contact</button>
    </Topbar>

    <div class="toolbar2">
      <label class="search"><Search class="icon sm" aria-hidden="true" /><input v-model="q" type="search" placeholder="Search name, phone or email" aria-label="Search contacts" autocomplete="off"></label>
      <span class="muted small">{{ contacts.total }} contacts</span>
    </div>

    <div class="content">
      <div class="panel">
        <div class="tablewrap">
          <table class="t">
            <thead>
              <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Account</th>
                <th>Source</th>
                <th>Owner</th>
                <th class="n">Score</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="contact in contacts.data" :key="contact.id">
                <td>
                  <Link :href="`/contacts/${contact.id}`" class="cellp" style="color:var(--text)">
                    <UserAvatar :name="contact.name" size="xs" />
                    <b style="font-weight:500">{{ contact.name }}</b>
                  </Link>
                </td>
                <td class="n" style="text-align:left">{{ contact.phone_e164 ?? '—' }}</td>
                <td>{{ contact.email ?? '—' }}</td>
                <td>{{ contact.account?.name ?? '—' }}</td>
                <td>
                  <span class="badge" :class="sourceBadge(contact.source)">{{ contact.source }}</span>
                </td>
                <td>{{ contact.owner?.name ?? '—' }}</td>
                <td class="n">
                  <ScoreDot :score="contact.score" />
                  <span v-if="contact.score == null" class="muted">—</span>
                </td>
              </tr>
              <tr v-if="contacts.data.length === 0">
                <td colspan="7" class="muted" style="height:80px;text-align:center">
                  No contacts found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="contacts.last_page > 1" style="display:flex;gap:8px;margin-top:16px;align-items:center">
        <button
          class="btn sm"
          :disabled="!contacts.prev_page_url"
          @click="router.get(contacts.prev_page_url, {}, { preserveState: true })"
        >
          Previous
        </button>
        <span class="muted small">Page {{ contacts.current_page }} of {{ contacts.last_page }}</span>
        <button
          class="btn sm"
          :disabled="!contacts.next_page_url"
          @click="router.get(contacts.next_page_url, {}, { preserveState: true })"
        >
          Next
        </button>
      </div>

      <p class="synthetic">Deduplicated by E.164 phone and email per company.</p>
    </div>

    <Modal v-if="showNew" title="New contact" width="420px" @close="showNew = false">
      <form class="form" @submit.prevent="createContact">
        <div class="field"><label for="c-name">Name</label><input id="c-name" v-model="newForm.name" class="input" required autocomplete="off"><span v-if="newForm.errors.name" class="ferr">{{ newForm.errors.name }}</span></div>
        <div class="field"><label for="c-phone">Phone</label><input id="c-phone" v-model="newForm.phone" class="input" placeholder="+62 812 3456 789" autocomplete="off"><span v-if="newForm.errors.phone" class="ferr">{{ newForm.errors.phone }}</span></div>
        <div class="field"><label for="c-mail">Email</label><input id="c-mail" v-model="newForm.email" class="input" type="email" autocomplete="off"><span v-if="newForm.errors.email" class="ferr">{{ newForm.errors.email }}</span></div>
        <div class="actions"><button type="button" class="btn ghost" @click="showNew = false">Cancel</button><button type="submit" class="btn primary" :disabled="newForm.processing">Create contact</button></div>
      </form>
    </Modal>
  </AppShell>
</template>

<style scoped>
.toolbar2{display:flex;align-items:center;gap:var(--s3);padding:10px var(--s5);border-bottom:1px solid var(--line);background:var(--surface)}
.search{display:flex;align-items:center;gap:8px;height:32px;width:340px;max-width:100%;padding:0 10px;border:1px solid var(--line-strong);border-radius:var(--r-sm);background:var(--surface)}
.search:focus-within{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
.search input{border:0;outline:0;flex:1;min-width:0;font:inherit;background:none}
.cellp{display:flex;align-items:center;gap:8px}
</style>
