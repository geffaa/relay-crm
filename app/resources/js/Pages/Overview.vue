<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Clock, Flame, Hourglass, MessageCircle, PieChart, Target, Trophy, TrendingUp } from 'lucide-vue-next'
import { money, scoreColor } from '@/lib/format'

const props = defineProps({ period: String, kpi: Object, reps: Array, attention: Array, byStage: Array, lostReasons: Array })

const PERIODS = [{ k: 'week', l: 'Week' }, { k: 'month', l: 'Month' }, { k: 'quarter', l: 'Quarter' }]
const label = computed(() => ({ week: 'this week', month: 'this month', quarter: 'this quarter' })[props.period])
const setPeriod = (k) => router.get('/insights', k === 'quarter' ? {} : { period: k }, { preserveScroll: true, replace: true })
const COLORS = ['#6B6779', '#2F62CC', '#8A3FC0', '#9A5A06']
const maxRep = computed(() => Math.max(1, ...props.reps.map((r) => r.won)))
const maxStage = computed(() => Math.max(1, ...props.byStage.map((s) => s.value)))
const maxLost = computed(() => Math.max(1, ...props.lostReasons.map((r) => r.count)))
const sharePct = (r) => (props.kpi.won_total ? Math.round((100 * r.won) / props.kpi.won_total) : 0)
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h"><h1>Insights</h1></div>
      <div class="views">
        <Link href="/insights" aria-current="page"><PieChart class="icon" />Overview</Link>
        <span class="soonrow">Forecast<span class="n">Soon</span></span>
        <span class="soonrow">Goals and quota<span class="n">Soon</span></span>
      </div>
    </template>

    <Topbar title="Overview">
      <div class="seg" role="group" aria-label="Period">
        <button v-for="p in PERIODS" :key="p.k" type="button" :aria-pressed="period === p.k" @click="setPeriod(p.k)">{{ p.l }}</button>
      </div>
    </Topbar>

    <div class="content">
      <div class="kpis">
        <section class="kpi"><h3><Trophy class="icon xs" />Won {{ label }}</h3><b class="fig">{{ money(kpi.won_total, kpi.currency) }}</b><p>{{ kpi.won_count }} deals won</p></section>
        <section class="kpi"><h3><TrendingUp class="icon xs" />Open pipeline</h3><b class="fig">{{ money(kpi.pipeline_value, kpi.currency) }}</b><p>weighted {{ money(kpi.weighted_value, kpi.currency) }}</p></section>
        <section class="kpi"><h3><Target class="icon xs" />Win rate {{ label }}</h3><b class="fig">{{ kpi.win_rate !== null ? kpi.win_rate + '%' : '—' }}</b><p>{{ kpi.won_count }} won · {{ kpi.lost_count }} lost</p></section>
        <section class="kpi"><h3><Clock class="icon xs" />Median first reply</h3><b class="fig">{{ kpi.median_first_reply_minutes !== null ? kpi.median_first_reply_minutes + ' min' : '—' }}</b><p>{{ kpi.hot_leads }} hot leads (score 70+)</p></section>
      </div>

      <div class="grid"><div class="stack">
        <section class="panel">
          <div class="panel-h"><h2>Won by rep</h2><span class="muted small" style="margin-left:auto">{{ label }}</span></div>
          <ul class="rows">
            <li v-for="r in reps" :key="r.id"><UserAvatar :name="r.name" /><span class="nm">{{ r.name }}</span>
              <span class="bar"><i :style="{ width: (100 * r.won / maxRep) + '%' }" /></span><span class="v fig">{{ money(r.won, kpi.currency) }} · {{ sharePct(r) }}%</span></li>
            <li v-if="!reps.length" class="muted small">No deals won {{ label }} yet.</li>
          </ul>
        </section>

        <section class="panel">
          <div class="panel-h"><h2>Pipeline by stage</h2><Link class="btn sm ghost" style="margin-left:auto" href="/pipeline">Open board</Link></div>
          <ul class="rows">
            <li v-for="(s, i) in byStage" :key="s.name"><span class="nm stg">{{ s.name }}</span>
              <span class="bar big"><i :style="{ width: Math.max(4, 100 * s.value / maxStage) + '%', background: COLORS[i % COLORS.length] }">{{ s.count }}</i></span><span class="v fig">{{ money(s.value, kpi.currency) }}</span></li>
            <li v-if="!byStage.length" class="muted small">No open deals.</li>
          </ul>
        </section>
      </div>
      <div class="stack">
        <section class="panel">
          <div class="panel-h"><h2>Needs attention</h2><span v-if="attention.length" class="badge hot" style="margin-left:auto">{{ attention.length }}</span></div>
          <ul class="att">
            <li v-for="a in attention" :key="a.kind + a.id">
              <span class="ico" :class="a.kind"><MessageCircle v-if="a.kind === 'unreplied'" class="icon sm" /><Hourglass v-else class="icon sm" /></span>
              <span class="tx"><b>{{ a.name }}</b><small class="muted">{{ a.detail }}<template v-if="a.score != null"> · score <span class="score"><i :style="{ background: scoreColor(a.score) }" />{{ a.score }}</span></template></small></span>
              <Link class="btn sm" :href="a.href">Open</Link>
            </li>
            <li v-if="!attention.length" class="muted small">Nothing is waiting. Nice.</li>
          </ul>
        </section>

        <section class="panel">
          <div class="panel-h"><h2>Lost reasons</h2><span class="muted small" style="margin-left:auto">last 90 days</span></div>
          <ul class="rows">
            <li v-for="r in lostReasons" :key="r.reason"><span class="nm lr">{{ r.reason }}</span><span class="bar"><i class="lost" :style="{ width: (100 * r.count / maxLost) + '%' }" /></span><span class="v fig">{{ r.count }} · {{ money(r.value, kpi.currency) }}</span></li>
            <li v-if="!lostReasons.length" class="muted small">No lost deals in this window.</li>
          </ul>
        </section>
      </div>
      </div>
      <p class="synthetic">Synthetic demo data. Deals in other currencies are converted at estimated rates. Weighted pipeline uses a fixed win chance per stage.</p>
    </div>
  </AppShell>
</template>

<style scoped>
.soonrow{display:flex;align-items:center;gap:10px;padding:6px 8px 6px 36px;color:var(--text-4)}.soonrow .n{margin-left:auto;font-size:12px}
.kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--s4);margin-bottom:var(--s4)}
.kpi{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:16px;display:grid;gap:6px;align-content:start;min-width:0}
.kpi h3{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:var(--text-3)}
.kpi b{font-size:26px;font-weight:600;letter-spacing:-.02em}
.kpi p{color:var(--text-3);font-size:13px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--s4);align-items:start}
.stack{display:grid;gap:var(--s4);min-width:0}
.rows,.att{list-style:none;padding:6px 16px 10px}
.rows li{display:grid;grid-template-columns:auto 130px minmax(0,1fr) auto;gap:12px;align-items:center;padding:9px 0;border-bottom:1px solid var(--line)}
.rows li:last-child,.att li:last-child{border-bottom:0}
.rows li:has(.stg),.rows li:has(.lr){grid-template-columns:130px minmax(0,1fr) auto}
.nm{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bar{height:8px;border-radius:999px;background:var(--hover);overflow:hidden}
.bar i{display:block;height:100%;border-radius:999px;background:var(--brand)}
.bar i.lost{background:#E07A66}
.bar.big{height:24px;border-radius:6px;background:none}.bar.big i{border-radius:6px;color:#fff;font-size:12px;font-weight:600;font-style:normal;padding:0 8px;display:flex;align-items:center}
.v{color:var(--text-2);white-space:nowrap;font-size:13px}
.att li{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)}
.att .ico{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:var(--hot-soft);color:var(--hot);flex:none}
.att .ico.stale{background:var(--warning-soft);color:var(--warning)}
.att .tx{display:grid;min-width:0;flex:1}.att .tx small{font-size:12px}
@media (max-width:1180px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:minmax(0,1fr)}}
@media (max-width:720px){.kpis{grid-template-columns:minmax(0,1fr)}.rows li,.rows li:has(.stg),.rows li:has(.lr){grid-template-columns:minmax(0,1fr) auto}.rows li .bar{grid-column:1/-1;order:3}.rows li>.av{display:none}}
</style>
