<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import RSelect from '@/Components/RSelect.vue'
import { ChartPie as PieChart, Clock, CircleDollarSign, Hourglass, MessageCircle, Target, TrendingUp } from 'lucide-vue-next'
import { money, scoreColor } from '@/lib/format'

const props = defineProps({ period: String, kpi: Object, reps: Array, attention: Array, byStage: Array, lostReasons: Array, owners: Array, owner: Number, target: Object, series: Object })
const setOwner = (v) => router.get('/insights', { ...(props.period !== 'quarter' ? { period: props.period } : {}), ...(v ? { owner: v } : {}) }, { preserveScroll: true, replace: true })
function spark(vals) {
  const v = vals.map((x) => (x === null ? null : Number(x)))
  const pts = v.map((x, i) => [i, x]).filter(([, x]) => x !== null)
  if (pts.length < 2) return null
  const max = Math.max(...pts.map(([, x]) => x)); const min = Math.min(...pts.map(([, x]) => x)); const span = max - min || 1
  const xy = pts.map(([i, x]) => `${((i / (v.length - 1)) * 200).toFixed(1)},${(32 - ((x - min) / span) * 28).toFixed(1)}`)
  return { line: xy.join(' '), area: `${xy[0].split(',')[0]},36 ${xy.join(' ')} ${xy[xy.length - 1].split(',')[0]},36` }
}
const sp = computed(() => ({ pipeline: spark(props.series.pipeline), win: spark(props.series.win_rate), reply: spark(props.series.reply) }))
const ahead = computed(() => props.target && props.target.pct >= props.target.expected_pct)

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
      <RSelect :model-value="owner" :options="owners" label="Team filter" size="sm" @change="setOwner" />
      <div class="seg" role="group" aria-label="Period">
        <button v-for="p in PERIODS" :key="p.k" type="button" :aria-pressed="period === p.k" @click="setPeriod(p.k)">{{ p.l }}</button>
      </div>
    </Topbar>

    <div class="content">
      <div class="kpis">
        <section class="kpi"><h3><CircleDollarSign class="icon xs" />Won {{ label }}</h3><b class="fig">{{ money(kpi.won_total, kpi.currency) }}</b>
          <template v-if="target"><p>{{ target.pct }}% of {{ money(target.amount, kpi.currency) }} · expected {{ target.expected_pct }}% by day {{ target.day }}</p>
            <div class="mbar" role="img" :aria-label="`${target.pct}% of target, expected ${target.expected_pct}%`"><i :style="{ width: Math.min(100, target.pct) + '%' }" /><b :style="{ left: target.expected_pct + '%' }" /></div></template>
          <p v-else>{{ kpi.won_count }} deals won</p></section>
        <section class="kpi"><h3><TrendingUp class="icon xs" />Open pipeline</h3><b class="fig">{{ money(kpi.pipeline_value, kpi.currency) }}</b><p>weighted {{ money(kpi.weighted_value, kpi.currency) }}</p>
          <svg v-if="sp.pipeline" class="spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true"><polygon :points="sp.pipeline.area" fill="currentColor" opacity=".12" /><polyline :points="sp.pipeline.line" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" /></svg></section>
        <section class="kpi"><h3><Target class="icon xs" />Win rate {{ label }}</h3><b class="fig">{{ kpi.win_rate !== null ? kpi.win_rate + '%' : '—' }}</b>
          <p><span v-if="kpi.win_rate_delta !== null" :class="kpi.win_rate_delta >= 0 ? 'up' : 'down'">{{ kpi.win_rate_delta >= 0 ? '+' : '' }}{{ kpi.win_rate_delta }} pts</span><template v-if="kpi.win_rate_delta !== null"> vs previous period · </template>{{ kpi.won_count }} won, {{ kpi.lost_count }} lost</p>
          <svg v-if="sp.win" class="spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true"><polygon :points="sp.win.area" fill="currentColor" opacity=".12" /><polyline :points="sp.win.line" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" /></svg></section>
        <section class="kpi"><h3><Clock class="icon xs" />Median first reply</h3><b class="fig">{{ kpi.median_first_reply_minutes !== null ? kpi.median_first_reply_minutes + ' min' : '—' }}</b>
          <p><span v-if="kpi.reply_delta !== null && kpi.reply_delta !== 0" :class="kpi.reply_delta > 0 ? 'up' : 'down'">{{ Math.abs(kpi.reply_delta) }} min {{ kpi.reply_delta > 0 ? 'faster' : 'slower' }}</span><template v-if="kpi.reply_delta"> · </template>{{ kpi.hot_leads }} hot leads</p>
          <svg v-if="sp.reply" class="spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true"><polygon :points="sp.reply.area" fill="currentColor" opacity=".12" /><polyline :points="sp.reply.line" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" /></svg></section>
      </div>

      <div class="grid"><div class="stack">
        <section class="panel">
          <div class="panel-h"><h2>Won vs target</h2><span class="muted small" style="margin-left:auto"><template v-if="target">Marker = expected by today ({{ target.expected_pct }}%)</template><template v-else>{{ label }}</template></span></div>
          <ul class="rows">
            <li v-for="r in reps" :key="r.id"><UserAvatar :name="r.name" /><span class="nm">{{ r.name }}</span>
              <span class="bar mk"><i :style="{ width: r.target ? Math.min(100, (100 * r.won) / r.target) + '%' : (100 * r.won / maxRep) + '%' }" /><b v-if="target" :style="{ left: target.expected_pct + '%' }" /></span><span class="v fig">{{ money(r.won, kpi.currency) }} · {{ r.pct !== null ? r.pct : sharePct(r) }}%</span></li>
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
              <UserAvatar :name="a.name" />
              <span class="tx"><b>{{ a.name }}</b><small class="muted">{{ a.detail }}<template v-if="a.score != null"> · score <span class="score"><i :style="{ background: scoreColor(a.score) }" />{{ a.score }}</span></template></small></span>
              <Link class="btn sm" :href="a.href">{{ a.kind === 'unreplied' ? 'Reply' : 'Open' }}</Link>
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
.kpi .up{color:var(--success);font-weight:600}.kpi .down{color:var(--danger);font-weight:600}
.kpi .spark{width:100%;height:36px;color:var(--brand);margin-top:4px}
.mbar,.bar.mk{position:relative;height:8px;border-radius:999px;background:var(--hover);margin-top:6px}
.mbar i{position:absolute;inset:0 auto 0 0;border-radius:999px;background:var(--brand)}
.mbar b,.bar.mk b{position:absolute;top:-4px;width:2px;height:16px;background:var(--text);border-radius:1px}
.bar.mk{overflow:visible;margin:0}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--s4);align-items:stretch}
.stack{display:contents}
.grid>.stack:first-child>.panel:nth-child(1){order:1}.grid>.stack:last-child>.panel:nth-child(1){order:2}.grid>.stack:first-child>.panel:nth-child(2){order:3}.grid>.stack:last-child>.panel:nth-child(2){order:4}
.grid .panel{height:100%}
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
