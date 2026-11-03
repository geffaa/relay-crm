<script setup>
import { Link } from '@inertiajs/vue3'
import { ArrowRight, Building2, Mail, Sparkles, Target, Zap, KanbanSquare } from 'lucide-vue-next'
import { money, scoreColor, shortDate } from '@/lib/format'

defineProps({
  contact: Object, openDeal: Object, scoreDetail: Object, summary: Object,
  summarizing: Boolean, seqOf: Function,
})
const emit = defineEmits(['summarize', 'jump'])
</script>

<template>
  <div class="details">
    <section v-if="openDeal">
      <h3 class="dh"><KanbanSquare class="icon xs" />Deal</h3>
      <div class="dealcard">
        <div class="stage"><i style="background:#8A3FC0" /><span>{{ openDeal.stage }}</span><span class="muted small" style="margin-left:auto">day {{ openDeal.days_in_stage }}<template v-if="openDeal.stale_after_days"> of {{ openDeal.stale_after_days }}</template></span></div>
        <b class="dt">{{ openDeal.title }}</b>
        <div class="dv fig">{{ openDeal.value ? money(openDeal.value, openDeal.currency, { compact: false }) : 'No value yet' }}</div>
        <p v-if="openDeal.expected_close" class="muted small">Close {{ shortDate(openDeal.expected_close) }}</p>
        <Link class="btn sm" :href="`/deals/${openDeal.id}`">Open deal<ArrowRight class="icon xs" /></Link>
      </div>
    </section>
    <section v-else>
      <h3 class="dh"><KanbanSquare class="icon xs" />Deal</h3>
      <p class="muted small">No open deal for this contact yet.</p>
      <Link class="btn sm" style="margin-top:8px" :href="`/contacts/${contact.id}`">Open contact</Link>
    </section>

    <section v-if="scoreDetail">
      <h3 class="dh"><Target class="icon xs" />Lead score <span class="score"><i :style="{ background: scoreColor(scoreDetail.score) }" />{{ scoreDetail.score }}</span></h3>
      <ul class="reasons">
        <li v-for="(r, i) in scoreDetail.reasons" :key="i"><b class="w fig" :class="{ neg: r.weight < 0 }">{{ r.weight > 0 ? '+' : '' }}{{ r.weight }}</b><span>{{ r.label }}</span></li>
      </ul>
    </section>

    <section>
      <h3 class="dh"><Sparkles class="icon xs" />AI summary</h3>
      <template v-if="summary">
        <ul class="pts">
          <li v-for="(p, i) in summary.points" :key="i">
            <span>{{ p.text }}</span>
            <span class="srcs"><button v-for="mid in p.message_ids" :key="mid" type="button" class="src" :title="`Jump to message #${seqOf(mid)}`" @click="emit('jump', mid)">#{{ seqOf(mid) }}</button></span>
          </li>
        </ul>
        <div v-if="summary.next_step" class="next"><Zap class="icon sm" /><span><b>Next step:</b> {{ summary.next_step }}</span></div>
        <p class="muted small" style="margin-top:8px">Written by {{ summary.model }}. Check the linked messages before acting on it.</p>
      </template>
      <p v-else class="muted small">No summary yet.</p>
      <button type="button" class="btn sm" style="margin-top:8px" :disabled="summarizing" @click="emit('summarize')"><Sparkles class="icon xs" />{{ summarizing ? 'Summarizing…' : summary ? 'Refresh summary' : 'Summarize conversation' }}</button>
    </section>

    <section>
      <h3 class="dh"><Building2 class="icon xs" />Contact</h3>
      <dl class="kv">
        <template v-if="contact.account"><dt>Company</dt><dd><Link :href="`/companies/${contact.account.id}`">{{ contact.account.name }}</Link></dd></template>
        <dt>Phone</dt><dd class="fig">{{ contact.phone_e164 }}</dd>
        <dt>Source</dt><dd>{{ contact.source }}</dd>
        <template v-if="contact.email"><dt>Email</dt><dd><Mail class="icon xs" /> {{ contact.email }}</dd></template>
      </dl>
    </section>
  </div>
</template>

<style scoped>
.details{display:grid;gap:var(--s5)}
.dh{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:8px}
.dh .score{margin-left:4px}
.dealcard{border:1px solid var(--line);border-radius:var(--r-lg);padding:12px;display:grid;gap:6px;background:var(--surface)}
.dealcard .dt{font-size:15px}.dealcard .dv{font-size:20px;font-weight:600;letter-spacing:-.02em}
.dealcard .btn{justify-self:start;margin-top:4px}
.reasons,.pts{list-style:none;padding:0;display:grid;gap:8px}
.reasons li{display:flex;gap:10px;align-items:baseline}
.reasons .w{color:var(--success);min-width:30px}.reasons .w.neg{color:var(--danger)}
.pts li{display:flex;gap:10px;justify-content:space-between;align-items:flex-start}
.srcs{display:flex;gap:4px;flex:none}
.src{border:0;background:none;color:var(--brand);font-weight:600;font-size:12px;padding:0 2px}
.src:hover{text-decoration:underline}
.next{display:flex;gap:10px;margin-top:10px;padding:10px 12px;border-radius:var(--r);background:var(--brand-tint);border:1px solid #E6DEFC}
.next .icon{color:var(--brand);margin-top:2px}
.kv{display:grid;grid-template-columns:84px minmax(0,1fr);gap:8px 12px}
.kv dt{color:var(--text-3)}.kv dd{margin:0;overflow-wrap:anywhere}
</style>
