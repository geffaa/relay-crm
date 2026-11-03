<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import Checkbox from '@/Components/Checkbox.vue'
import { Upload, Check, AlertTriangle, X } from 'lucide-vue-next'

const page = usePage()

const picker = ref(null)
const dragging = ref(false)
const form = useForm({ file: null, skip_duplicates: true })
const rows = ref(page.props.importRows ?? [])
const importKey = ref(page.props.importKey ?? null)

function onFileChange(e) {
  form.file = e.target.files[0] ?? null
}
function onDrop(e) {
  dragging.value = false
  const f = e.dataTransfer.files[0]
  if (f) form.file = f
}

function preview() {
  form.post('/contacts/import', {
    preserveScroll: true,
    forceFormData: true,
    onSuccess: () => {
      rows.value = page.props.importRows ?? []
      importKey.value = page.props.importKey ?? null
    },
  })
}

function confirmImport() {
  form.post('/contacts/import/confirm', {
    data: () => ({ key: importKey.value, skip_duplicates: form.skip_duplicates }),
    preserveScroll: true,
  })
}

function dupLabel(dups) {
  return dups.map((d) => ({
    phone: 'phone exists',
    email: 'email exists',
    invalid_phone: 'invalid phone',
  })[d] ?? d)
}
</script>

<template>
  <AppShell has-context>
    <template #context>
      <div class="ctx-h">
        <h1>Contacts</h1>
      </div>
      <div class="views">
        <a href="/contacts">← Back to contacts</a>
      </div>
    </template>

    <div class="topbar">
      <h1>Import contacts</h1>
    </div>

    <div class="content" style="max-width:820px;display:grid;gap:20px">
      <div class="panel">
        <div class="panel-b" style="display:grid;gap:12px">
          <p class="muted small">
            Upload a CSV with <b>name</b>, <b>phone</b> and optional <b>email</b> columns.
            Phones are normalized to E.164; duplicates (against existing contacts
            <em>and</em> inside the file) are flagged before anything is saved.
          </p>
          <div class="drop" :class="{ over: dragging }" @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="onDrop">
            <Upload class="icon" />
            <span v-if="form.file"><b>{{ form.file.name }}</b> · {{ Math.max(1, Math.round(form.file.size / 1024)) }} KB</span>
            <span v-else class="muted">Drop a CSV here, or</span>
            <button type="button" class="btn sm" @click="picker.click()">{{ form.file ? 'Choose another file' : 'Choose a CSV file' }}</button>
            <input ref="picker" type="file" accept=".csv,text/csv" class="sr" tabindex="-1" aria-label="CSV file" @change="onFileChange">
          </div>
          <div v-if="form.errors.file" class="f-error">{{ form.errors.file }}</div>
          <Checkbox v-model="form.skip_duplicates">Skip flagged duplicates on import</Checkbox>
          <div style="display:flex;justify-content:flex-end">
            <button class="btn primary" :disabled="!form.file || form.processing" @click="preview">
              <Upload class="icon xs" />
              {{ form.processing ? 'Parsing…' : 'Preview import' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="rows.length" class="panel">
        <div class="panel-h">
          <h2>Preview · {{ rows.length }} rows</h2>
          <div class="acts">
            <button class="btn primary sm" :disabled="form.processing" @click="confirmImport">
              Import now
            </button>
          </div>
        </div>
        <div class="tablewrap">
          <table class="t">
            <thead>
              <tr><th>#</th><th>Name</th><th>Phone (E.164)</th><th>Email</th><th>Flags</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.line">
                <td class="muted num">{{ row.line }}</td>
                <td>{{ row.name }}</td>
                <td class="num">{{ row.phone ?? '—' }}</td>
                <td class="muted">{{ row.email ?? '—' }}</td>
                <td>
                  <span v-if="row.duplicates.length" class="badge danger">
                    <AlertTriangle class="icon xs" />
                    {{ dupLabel(row.duplicates).join(', ') }}
                  </span>
                  <span v-else class="badge success"><Check class="icon xs" /> ok</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="panel-b muted small">
          Flagged rows are skipped when “Skip flagged duplicates” is on; uncheck it to import them anyway
          (dedup at save time still prevents true duplicates).
        </div>
      </div>
    </div>
  </AppShell>
</template>

<style scoped>
.f-input {
  width: 100%;
  padding: 0 10px;
  border: 1px solid var(--line-strong);
  border-radius: var(--r-sm);
  font: inherit;
  font-size: 13px;
  background: var(--surface);
}
.f-error { color: var(--danger); font-size: 12px; }
</style>

<style scoped>
.drop{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:22px;border:1.5px dashed var(--line-strong);border-radius:var(--r-lg);background:var(--surface-2)}
.drop.over{border-color:var(--brand);background:var(--brand-tint)}
.drop .icon{color:var(--text-3)}
</style>
