<script setup>
import { useForm } from '@inertiajs/vue3'
import Checkbox from '@/Components/Checkbox.vue'
import UserAvatar from '@/Components/UserAvatar.vue'

defineProps({ demo: { type: Array, default: () => [] } })

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post('/login')
}
</script>

<template>
  <div class="login-wrap">
    <div class="login-card">
      <div class="mk" style="margin-bottom:16px">R</div>
      <h1 style="font-size:22px">Sign in to Relay</h1>
      <p class="muted" style="margin:6px 0 20px;font-size:14px">
        The sales workspace where every WhatsApp conversation is part of its deal.
      </p>

      <form @submit.prevent="submit">
        <label class="f-label" for="email">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          class="f-input"
          required
          autofocus
          autocomplete="username"
        >
        <div v-if="form.errors.email" class="f-error">{{ form.errors.email }}</div>

        <label class="f-label" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          class="f-input"
          required
          autocomplete="current-password"
        >
        <div v-if="form.errors.password" class="f-error">{{ form.errors.password }}</div>

        <Checkbox v-model="form.remember" style="margin:14px 0 18px">Remember me</Checkbox>

        <button type="submit" class="btn primary" style="width:100%;justify-content:center" :disabled="form.processing">
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <div v-if="demo.length" class="demo">
        <p class="muted small">Demo workspace. Pick a role to fill in the sign-in form (password is <code>password</code>).</p>
        <button v-for="u in demo" :key="u.email" type="button" class="who" @click="form.email = u.email; form.password = 'password'">
          <UserAvatar :name="u.name" /><span><b>{{ u.name }}</b><small class="muted">{{ u.role }}</small></span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.demo{margin-top:20px;padding-top:16px;border-top:1px solid var(--line);display:grid;gap:6px}
.demo code{background:var(--hover);border-radius:4px;padding:0 4px}
.who{display:flex;align-items:center;gap:10px;border:0;background:none;padding:6px 8px;border-radius:var(--r-sm);text-align:left}
.who:hover{background:var(--hover)}
.who span{display:grid}.who small{font-size:12px;text-transform:capitalize}
.login-wrap {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: var(--ground);
}
.login-card {
  width: 360px;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--r-lg);
  padding: 28px;
}
.f-label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: var(--text-3);
  margin: 12px 0 4px;
}
.f-input {
  width: 100%;
  height: 34px;
  padding: 0 10px;
  border: 1px solid var(--line-strong);
  border-radius: var(--r-sm);
  font: inherit;
  font-size: 14px;
  background: var(--surface);
}
.f-input:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px var(--brand-soft);
}
.f-error {
  color: var(--danger);
  font-size: 12px;
  margin-top: 4px;
}
.mk {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: var(--brand);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
}
</style>
