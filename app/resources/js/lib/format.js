// Display helpers shared by every page. Money is shown the way the market writes it:
// rupiah compact ("Rp 60 jt"), others with their symbol ("£8,000", "S$18,000").
const SYMBOL = { GBP: '£', SGD: 'S$', USD: '$', EUR: '€', AUD: 'A$' }

export function money(amount, currency = 'IDR', { compact = true } = {}) {
  if (amount === null || amount === undefined || amount === '') return '—'
  const n = Number(amount)
  if (currency === 'IDR') {
    if (!compact) return `Rp ${Math.round(n).toLocaleString('id-ID')}`
    if (Math.abs(n) >= 1e9) return `Rp ${trim(n / 1e9)} M`
    if (Math.abs(n) >= 1e6) return `Rp ${trim(n / 1e6)} jt`
    return `Rp ${Math.round(n).toLocaleString('id-ID')}`
  }
  return `${SYMBOL[currency] ?? currency + ' '}${Math.round(n).toLocaleString('en-GB')}`
}

function trim(x) {
  return (Math.round(x * 10) / 10).toString().replace('.', ',')
}

/** "1 h 05", "38 min", "2 d 3 h" — how long something has waited. */
export function waited(iso, now = Date.now()) {
  const mins = Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000))
  if (mins < 60) return `${mins} min`
  const h = Math.floor(mins / 60)
  if (h < 24) return `${h} h ${String(mins % 60).padStart(2, '0')}`
  return `${Math.floor(h / 24)} d ${h % 24} h`
}

export function dueLabel(iso, now = new Date()) {
  if (!iso) return { text: 'No due date', hot: false }
  const d = new Date(iso)
  const sameDay = d.toDateString() === now.toDateString()
  const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  if (d < now) return { text: sameDay ? `Overdue ${time}` : `Overdue ${shortDate(d)}`, hot: true }
  if (sameDay) return { text: `Due ${time}`, hot: false }
  return { text: `Due ${shortDate(d)}`, hot: false }
}

export function shortDate(d) {
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

export function longDate(d = new Date()) {
  return new Date(d).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' })
}

export function dateTime(d) {
  const x = new Date(d)
  return `${shortDate(x)}, ${x.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`
}

export function ago(iso, now = Date.now()) {
  const mins = Math.floor((now - new Date(iso).getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins} min ago`
  const h = Math.floor(mins / 60)
  if (h < 24) return `${h} h ago`
  const d = Math.floor(h / 24)
  return d < 7 ? `${d} d ago` : shortDate(iso)
}

export function scoreColor(score) {
  if (score == null) return '#A3A0AE'
  return score >= 70 ? '#16A34A' : score >= 40 ? '#D9930D' : '#A3A0AE'
}
