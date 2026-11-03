<script setup>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Building2, Search } from 'lucide-vue-next'
import { money } from '@/lib/format'

const props = defineProps({ accounts: Array, currency: String, filters: Object })
const q = ref(props.filters.q ?? '')
let t
watch(q, (v) => {
  clearTimeout(t)
  t = setTimeout(() => router.get('/companies', v ? { q: v } : {}, { preserveState: true, replace: true }), 200)
})
const HEALTH = { good: 'success', fair: 'warning', poor: 'danger' }
</script>

<template>
  <AppShell>
    <Topbar title="Companies"><template #left><span class="muted small">{{ accounts.length }} companies</span></template></Topbar>
    <div class="content">
      <div class="bar">
        <label class="search"><Search class="icon sm" aria-hidden="true" /><input v-model="q" type="search" placeholder="Search companies" aria-label="Search companies" autocomplete="off"></label>
      </div>
      <div class="panel">
        <div class="tablewrap">
          <table class="t">
            <thead><tr><th>Company</th><th>Industry</th><th>Health</th><th>Owner</th><th class="n">Contacts</th><th class="n">Open deals</th><th class="n">Open value</th></tr></thead>
            <tbody>
              <tr v-for="a in accounts" :key="a.id">
                <td><Link :href="`/companies/${a.id}`" class="co"><UserAvatar :name="a.name" /><span><b>{{ a.name }}</b><small v-if="a.domain" class="muted">{{ a.domain }}</small></span></Link></td>
                <td class="muted">{{ a.industry ?? '—' }}</td>
                <td><span v-if="a.health" class="badge" :class="HEALTH[a.health]">{{ a.health }}</span><span v-else class="muted">—</span></td>
                <td>{{ a.owner ?? '—' }}</td>
                <td class="n fig">{{ a.contacts }}</td>
                <td class="n fig">{{ a.open_deals }}</td>
                <td class="n fig">{{ a.open_deals ? money(a.open_value, currency) : '—' }}</td>
              </tr>
              <tr v-if="!accounts.length"><td colspan="7"><div class="empty"><Building2 class="icon" /><b>No companies found</b><span class="muted small">Companies are created from contacts and leads.</span></div></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <p class="synthetic">Synthetic demo data. Open value converts other currencies at estimated rates.</p>
    </div>
  </AppShell>
</template>

<style scoped>
.bar{display:flex;margin-bottom:var(--s4)}
.search{display:flex;align-items:center;gap:8px;height:32px;width:320px;max-width:100%;padding:0 10px;border:1px solid var(--line-strong);border-radius:var(--r-sm);background:var(--surface)}
.search:focus-within{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
.search input{border:0;outline:0;flex:1;min-width:0;font:inherit;background:none}
.co{display:flex;align-items:center;gap:10px;color:var(--text)}.co:hover{text-decoration:none}
.co span{display:grid}.co small{font-size:12px}
.badge{text-transform:capitalize}
</style>
