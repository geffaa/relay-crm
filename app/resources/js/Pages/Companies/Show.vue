<script setup>
import { Link } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Globe, Factory, Users, UserRound } from 'lucide-vue-next'
import { money, scoreColor } from '@/lib/format'

defineProps({ account: Object, contacts: Array, deals: Array, currency: String })
</script>

<template>
  <AppShell>
    <Topbar title="Companies"><template #left><span class="crumb">/ <b>{{ account.name }}</b></span></template></Topbar>
    <div class="content">
      <header class="head">
        <UserAvatar :name="account.name" size="lg" />
        <div>
          <h2>{{ account.name }}</h2>
          <div class="meta">
            <span v-if="account.domain"><Globe class="icon xs" />{{ account.domain }}</span>
            <span v-if="account.industry"><Factory class="icon xs" />{{ account.industry }}</span>
            <span v-if="account.size_band"><Users class="icon xs" />{{ account.size_band }} people</span>
            <span v-if="account.owner"><UserRound class="icon xs" />{{ account.owner }}</span>
          </div>
        </div>
      </header>

      <div class="grid">
        <section class="panel">
          <div class="panel-h"><h2>People</h2><span class="badge">{{ contacts.length }}</span></div>
          <div class="tablewrap"><table class="t">
            <thead><tr><th>Name</th><th>Phone</th><th class="n">Score</th></tr></thead>
            <tbody>
              <tr v-for="c in contacts" :key="c.id"><td><Link :href="`/contacts/${c.id}`" class="rowlink"><b>{{ c.name }}</b></Link></td><td class="fig muted">{{ c.phone_e164 ?? '—' }}</td>
                <td class="n"><span v-if="c.score != null" class="score"><i :style="{ background: scoreColor(c.score) }" />{{ c.score }}</span></td></tr>
              <tr v-if="!contacts.length"><td colspan="3" class="muted" style="text-align:center;padding:22px 0">No contacts yet.</td></tr>
            </tbody>
          </table></div>
        </section>
        <section class="panel">
          <div class="panel-h"><h2>Deals</h2><span class="badge">{{ deals.length }}</span></div>
          <div class="tablewrap"><table class="t">
            <thead><tr><th>Deal</th><th>Stage</th><th class="n">Value</th></tr></thead>
            <tbody>
              <tr v-for="d in deals" :key="d.id"><td><Link :href="`/deals/${d.id}`" class="rowlink"><b>{{ d.title }}</b></Link></td>
                <td><span class="badge" :class="d.kind === 'won' ? 'success' : d.kind === 'lost' ? 'danger' : ''">{{ d.stage }}</span></td>
                <td class="n fig">{{ d.value ? money(d.value, d.currency) : '—' }}</td></tr>
              <tr v-if="!deals.length"><td colspan="3" class="muted" style="text-align:center;padding:22px 0">No deals yet.</td></tr>
            </tbody>
          </table></div>
        </section>
      </div>
      <p class="synthetic">Synthetic demo data.</p>
    </div>
  </AppShell>
</template>

<style scoped>
.head{display:flex;gap:16px;align-items:center;margin-bottom:var(--s5)}
.head h2{font-size:22px}
.meta{display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:4px;color:var(--text-3)}.meta span{display:inline-flex;align-items:center;gap:6px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--s5);align-items:start}
@media (max-width:1180px){.grid{grid-template-columns:minmax(0,1fr)}}
</style>
