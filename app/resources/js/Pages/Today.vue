<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Topbar from '@/Components/Topbar.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import {
  CalendarCheck, Check, CheckCheck, Flame, Hourglass, ListTodo, MessageCircle, Phone,
  Reply, SquareKanban, UserRound, Users,
} from 'lucide-vue-next'
import { dueLabel, longDate, money, scoreColor, waited } from '@/lib/format'

const props = defineProps({
  scope: String, canTeam: Boolean, replies: Array, tasks: Array, stuck: Array,
  doneToday: Object, pace: Object, pipeline: Array, replyTime: Object, currency: String,
})

const total = computed(() => props.replies.length + props.tasks.length + props.stuck.length)
const oldest = computed(() => (props.replies.length ? waited(props.replies[0].since) : null))
const sel = ref(0)

function setScope(scope) {
  router.get('/', scope === 'team' ? { scope } : {}, { preserveScroll: true, replace: true })
}
function done(task) {
  router.post(`/tasks/${task.id}/done`, {}, { preserveScroll: true })
}
function onKey(e) {
  if (e.target.matches('input, textarea') || e.metaKey || e.ctrlKey) return
  const k = e.key.toLowerCase()
  if (!props.replies.length) return
  if (k === 'j') sel.value = Math.min(sel.value + 1, props.replies.length - 1)
  else if (k === 'k') sel.value = Math.max(sel.value - 1, 0)
  else if (k === 'r') router.get(props.replies[sel.value].href)
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

const taskIcon = { call: Phone, manual: ListTodo }
const paceUp = computed(() => props.pace.won >= props.pace.previous)
const maxSeries = computed(() => Math.max(1, ...(props.replyTime.series ?? [1])))
const spark = computed(() => {
  const s = props.replyTime.series ?? []
  if (s.length < 2) return ''
  return s.map((v, i) => `${((i / (s.length - 1)) * 200).toFixed(1)},${(32 - (v / maxSeries.value) * 28).toFixed(1)}`).join(' ')
})
</script>

<template>
  <AppShell>
    <Topbar title="Today" hint="G then T" />
    <div class="content">
      <div class="wrap2">
        <div>
          <div class="hello">
            <div>
              <h2>{{ longDate() }}</h2>
              <p v-if="total">{{ total }} {{ total === 1 ? 'thing' : 'things' }} in the queue<template v-if="oldest"> · oldest chat waiting {{ oldest }}</template></p>
              <p v-else>Nothing in the queue.</p>
            </div>
            <div v-if="canTeam" class="chips" role="group" aria-label="Whose queue">
              <button type="button" class="chip" :class="{ on: scope === 'mine' }" :aria-pressed="scope === 'mine'" @click="setScope('mine')"><UserRound class="icon xs" />Mine</button>
              <button type="button" class="chip" :class="{ on: scope === 'team' }" :aria-pressed="scope === 'team'" @click="setScope('team')"><Users class="icon xs" />Team</button>
            </div>
          </div>

          <div v-if="!total" class="items empty">
            <CalendarCheck class="icon" />
            <b>You're clear</b>
            <span class="muted small">Unanswered chats, follow-ups and stuck deals appear here as they come up.</span>
          </div>

          <section v-if="replies.length" class="q" aria-labelledby="q1">
            <div class="q-h">
              <span style="color:var(--hot)"><Flame class="icon sm" /></span><h3 id="q1">Reply now</h3><span class="n">{{ replies.length }}</span>
              <span class="hint"><kbd>J</kbd><kbd>K</kbd> move · <kbd>R</kbd> reply</span>
            </div>
            <div class="items">
              <div v-for="(r, i) in replies" :key="r.id" class="item" :class="{ sel: i === sel }" @click="sel = i">
                <span class="ty wa"><MessageCircle class="icon sm" /></span>
                <UserAvatar :name="r.name" />
                <div style="min-width:0">
                  <div class="nm"><Link :href="r.href" class="rowlink"><b>{{ r.name }}</b></Link> <span v-if="r.company" class="muted small">· {{ r.company }}</span><span v-if="scope === 'team' && r.owner" class="muted small"> · {{ r.owner }}</span></div>
                  <div class="msg">{{ r.text }}</div>
                </div>
                <div class="meta">
                  <span class="wait">{{ waited(r.since) }}</span>
                  <span v-if="r.score != null" class="score" :title="`Lead score ${r.score}`"><i :style="{ background: scoreColor(r.score) }" />{{ r.score }}</span>
                </div>
                <div class="acts"><Link class="btn sm primary" :href="r.href"><Reply class="icon xs" />Reply<kbd v-if="i === sel">R</kbd></Link></div>
              </div>
            </div>
          </section>

          <section v-if="tasks.length" class="q" aria-labelledby="q2">
            <div class="q-h"><span class="muted"><ListTodo class="icon sm" /></span><h3 id="q2">Follow up</h3><span class="n">{{ tasks.length }}</span></div>
            <div class="items">
              <div v-for="t in tasks" :key="t.id" class="item">
                <span class="ty"><component :is="taskIcon[t.kind] ?? ListTodo" class="icon sm" /></span>
                <UserAvatar :name="t.subject ?? t.title" />
                <div style="min-width:0">
                  <div class="nm"><component :is="t.href ? Link : 'b'" :href="t.href" class="rowlink"><b>{{ t.subject ?? 'Task' }}</b></component></div>
                  <div class="msg">{{ t.title }}</div>
                </div>
                <div class="meta"><span :class="{ wait: dueLabel(t.due_at).hot }">{{ dueLabel(t.due_at).text }}</span></div>
                <div class="acts">
                  <Link v-if="t.href" class="btn sm" :href="t.href">Open</Link>
                  <button type="button" class="btn sm ghost" aria-label="Mark done" @click="done(t)"><Check class="icon xs" /></button>
                </div>
              </div>
            </div>
          </section>

          <section v-if="stuck.length" class="q" aria-labelledby="q3">
            <div class="q-h"><span style="color:var(--warning)"><Hourglass class="icon sm" /></span><h3 id="q3">Stuck deals</h3><span class="n">{{ stuck.length }}</span></div>
            <div class="items">
              <div v-for="d in stuck" :key="d.id" class="item">
                <span class="ty"><SquareKanban class="icon sm" /></span>
                <UserAvatar :name="d.name" />
                <div style="min-width:0">
                  <div class="nm"><Link :href="d.href" class="rowlink"><b>{{ d.name }}</b></Link> <span class="muted small">· {{ d.title }}<template v-if="d.value"> · {{ money(d.value, d.currency) }}</template></span></div>
                  <div class="msg">{{ d.days }} days in {{ d.stage }} (limit {{ d.limit }})</div>
                </div>
                <div class="meta"><span class="stage">{{ d.stage }}</span></div>
                <div class="acts"><Link class="btn sm" :href="d.href">Open deal</Link></div>
              </div>
            </div>
          </section>

          <div v-if="doneToday.count" class="done"><CheckCheck class="icon sm" />{{ doneToday.count }} done today<template v-if="doneToday.last"> · last: {{ doneToday.last.title }}</template></div>
        </div>

        <aside class="side">
          <section class="panel">
            <div class="panel-h"><h2>Won this quarter</h2><span class="badge brand" style="margin-left:auto">Day {{ pace.day }} of {{ pace.days }}</span></div>
            <div class="panel-b pace">
              <div><span class="big fig">{{ money(pace.won, currency) }}</span></div>
              <div class="row"><span>{{ money(pace.previous, currency) }} at this point last quarter</span><span v-if="pace.won || pace.previous" :style="{ color: paceUp ? 'var(--success)' : 'var(--danger)' }">{{ paceUp ? 'Ahead' : 'Behind' }}</span></div>
            </div>
          </section>

          <section class="panel mini">
            <div class="panel-h"><h2>{{ scope === 'team' ? 'Team pipeline' : 'My pipeline' }}</h2><Link class="btn sm ghost" style="margin-left:auto" href="/pipeline">Open</Link></div>
            <div class="panel-b" style="padding-top:6px;padding-bottom:6px">
              <p v-if="!pipeline.length" class="muted small" style="padding:8px 0">No open deals.</p>
              <ul v-else><li v-for="s in pipeline" :key="s.name"><span class="stage">{{ s.name }}</span><span class="v">{{ s.count }} · {{ money(s.value, currency) }}</span></li></ul>
            </div>
          </section>

          <section class="panel">
            <div class="panel-h"><h2>{{ scope === 'team' ? 'Team reply time' : 'Your reply time' }}</h2></div>
            <div class="panel-b">
              <div v-if="replyTime.mine !== null" class="big fig">{{ replyTime.mine }} min <span v-if="scope === 'mine' && replyTime.team !== null" class="badge success" style="vertical-align:middle">team {{ replyTime.team }} min</span></div>
              <p v-else class="muted small">No replies in the last 14 days.</p>
              <div v-if="spark" style="color:var(--brand)"><svg class="spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true" style="width:100%;height:36px"><polyline :points="spark" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" /></svg></div>
              <p class="small muted">Median first reply, last 14 days</p>
            </div>
          </section>
        </aside>
      </div>
      <p class="synthetic">Synthetic demo data.</p>
    </div>
  </AppShell>
</template>
