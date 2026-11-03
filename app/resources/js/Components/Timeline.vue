<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowRight, GitMerge, ListChecks, MessageCircle, Sparkles, StickyNote, Target, UserPlus, ArrowRightLeft } from 'lucide-vue-next'

const props = defineProps({ items: { type: Array, required: true } })

const ICON = { note: StickyNote, message: MessageCircle, stage_change: ArrowRightLeft, task: ListChecks, ai_summary: Sparkles, assignment: UserPlus, score_override: Target, lead_conversion: UserPlus, contact_merged: GitMerge }
const TONE = { ai_summary: 'ai', message: 'wa' }
function head(a) {
  const who = a.actor ?? 'System'
  const b = a.body ?? {}
  switch (a.type) {
    case 'stage_change': return { t: `Moved to ${b.to}`, by: a.actor, sub: b.kind === 'lost' && b.lost_reason ? `Reason: ${b.lost_reason}` : b.from ? `From ${b.from}` : null }
    case 'message': return { t: b.direction === 'in' ? 'Customer wrote' : 'Reply sent', by: b.direction === 'out' ? a.actor : null, quote: b.text, link: b.conversation_id ? `/inbox/${b.conversation_id}` : null }
    case 'ai_summary': return { t: 'AI summary updated', sub: `${b.points ?? 0} key points`, link: b.conversation_id ? `/inbox/${b.conversation_id}` : null }
    case 'assignment': return { t: `Chat assigned to ${b.to ?? 'someone'}`, by: a.actor, sub: b.from ? `Was ${b.from}` : null }
    case 'score_override': return { t: `Lead score changed to ${b.to ?? ''}`, by: a.actor, sub: b.reason }
    case 'lead_conversion': return { t: 'Converted from lead', by: a.actor }
    case 'contact_merged': return { t: 'Contacts merged', by: a.actor }
    case 'task': return { t: `Task added: ${b.title ?? ''}`, by: a.actor }
    default:
      if (b.event === 'deal_created') return { t: 'Deal created', by: a.actor }
      return { t: `${who} left a note`, note: b.text }
  }
}
function mentions(text) {
  return String(text ?? '').split(/(@[\w.]+)/g).map((p) => ({ p, m: /^@[\w.]/.test(p) }))
}
function dayKey(iso) { return new Date(iso).toDateString() }
function dayHead(iso) {
  const d = new Date(iso)
  if (d.toDateString() === new Date().toDateString()) return 'Today'
  if (d.toDateString() === new Date(Date.now() - 864e5).toDateString()) return 'Yesterday'
  return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' })
}
const grouped = computed(() => {
  const out = []
  for (const a of props.items) {
    const k = dayKey(a.occurred_at)
    if (!out.length || out[out.length - 1].k !== k) out.push({ k, label: dayHead(a.occurred_at), rows: [] })
    out[out.length - 1].rows.push(a)
  }
  return out
})
const clock = (iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
</script>

<template>
  <div>
    <div v-for="g in grouped" :key="g.k" class="day">
      <h3 class="dh">{{ g.label }}</h3>
      <ol class="tl">
        <li v-for="a in g.rows" :key="a.id" :class="TONE[a.type]">
          <span class="ic"><component :is="ICON[a.type] ?? StickyNote" class="icon sm" /></span>
          <div class="body">
            <div class="line"><b>{{ head(a).t }}</b><span v-if="head(a).by" class="muted"> by {{ head(a).by }}</span><time class="fig">{{ clock(a.occurred_at) }}</time></div>
            <p v-if="head(a).sub" class="muted small">{{ head(a).sub }}</p>
            <p v-if="head(a).note" class="notebody"><template v-for="(part, i) in mentions(head(a).note)" :key="i"><span v-if="part.m" class="mention">{{ part.p }}</span><template v-else>{{ part.p }}</template></template></p>
            <blockquote v-if="head(a).quote" class="quote">{{ head(a).quote }}</blockquote>
            <Link v-if="head(a).link" :href="head(a).link" class="small">Open conversation <ArrowRight class="icon xs" style="vertical-align:-2px" /></Link>
          </div>
        </li>
      </ol>
    </div>
  </div>
</template>

<style scoped>
.dh{font-size:12px;font-weight:600;color:var(--text-3);margin:6px 0 6px}
.tl{list-style:none;padding:0;display:grid}
.tl li{display:grid;grid-template-columns:28px minmax(0,1fr);gap:12px;padding:10px 0;border-bottom:1px solid var(--line)}
.tl li:last-child{border-bottom:0}
.ic{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:var(--hover);color:var(--text-3)}
.tl .ai .ic{background:var(--brand-soft);color:var(--brand)}.tl .wa .ic{background:var(--wa-soft);color:var(--wa)}
.line{display:flex;gap:4px;align-items:baseline}.line time{margin-left:auto;color:var(--text-3);font-size:12px}
.notebody{white-space:pre-wrap;overflow-wrap:anywhere;margin-top:2px}
.mention{color:var(--brand-strong);background:var(--brand-soft);border-radius:4px;padding:0 3px;font-weight:500}
.quote{margin:6px 0;padding:8px 12px;border:1px solid var(--line);border-radius:var(--r);background:var(--surface);color:var(--text-2)}
</style>
