<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'

interface User { id: number; name: string }
interface Session {
  id: number
  opened_by: number
  closed_by?: number | null
  opening_amount: string | number
  closing_amount?: string | number | null
  expected_cash_at_close?: string | number | null
  variance?: string | number | null
  started_at: string
  ended_at?: string | null
  status: 'open' | 'closed' | 'auto_closed'
  notes?: string | null
  opened_by_user?: User
  closed_by_user?: User
}

const props = defineProps<{ filters: any; sessions: any; users: User[] }>()
const page = usePage<any>()

const submitFilters = () => {
  router.get(route('admin.cash-sessions.index'), props.filters, { preserveState: true, preserveScroll: true })
}
const exportCsv = () => {
  const params = new URLSearchParams({ ...props.filters, export: '1' }).toString()
  window.location.href = route('admin.cash-sessions.index') + '?' + params
}

const userName = (rel: any, fallbackId?: number|null) => {
  if (!rel) return fallbackId ?? '—'
  if (typeof rel === 'object' && rel.name) return rel.name
  return fallbackId ?? '—'
}

const userInitials = (rel: any): string => {
  const name = (rel && typeof rel === 'object' && rel.name) ? String(rel.name) : ''
  if (!name) return '—'
  const parts = name.trim().split(/\s+/)
  return (parts[0]?.[0] || '').toUpperCase() + (parts[1]?.[0] || '').toUpperCase()
}

const formatDate = (val?: string | null) => {
  if (!val) return '—'
  const d = new Date(val)
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleString()
}
</script>

<template>
  <AppLayout title="Cash Sessions">
    <Head title="Cash Sessions" />

    <div class="container mx-auto px-4 py-8 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Cash Sessions</h1>
          <p class="text-gray-500 dark:text-slate-400">Filter by date, user, or status. Export CSV.</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
          <CardDescription>Refine the sessions list</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid md:grid-cols-5 gap-3 items-end">
            <div>
              <Label>Status</Label>
              <select 
                v-model="props.filters.status"
                class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700"
              >
                <option :value="null">All</option>
                <option value="open">Open</option>
                <option value="closed">Closed</option>
                <option value="auto_closed">Auto-closed (needs reconciliation)</option>
              </select>
            </div>

            <div>
              <Label>User</Label>
              <select 
                v-model="props.filters.user_id"
                class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700"
              >
                <option :value="null">All</option>
                <option 
                  v-for="u in props.users" 
                  :key="u.id" 
                  :value="u.id"
                >
                  {{ u.name }}
                </option>
              </select>
            </div>
            <div>
              <Label>From</Label>
              <Input type="date" v-model="props.filters.from" class="bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" />
            </div>
            <div>
              <Label>To</Label>
              <Input type="date" v-model="props.filters.to" class="bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" />
            </div>
            <div class="flex gap-2">
              <Button @click="submitFilters" class="bg-blue-600 hover:bg-blue-700 text-white">Apply</Button>
              <Button variant="outline" @click="exportCsv">Export CSV</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Sessions</CardTitle>
        </CardHeader>
        <CardContent>
          <!-- Active filter chips -->
          <div class="flex flex-wrap gap-2 mb-3 text-xs">
            <span v-if="props.filters.status" class="px-2 py-1 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Status: {{ String(props.filters.status).toUpperCase() }}</span>
            <span v-if="props.filters.user_id" class="px-2 py-1 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">User: {{ (props.users.find(u=> String(u.id)===String(props.filters.user_id))?.name) || props.filters.user_id }}</span>
            <span v-if="props.filters.from" class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-300">From: {{ props.filters.from }}</span>
            <span v-if="props.filters.to" class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-300">To: {{ props.filters.to }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-gray-800 dark:text-slate-200">
              <thead class="bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
                <tr class="border-b border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300">
                  <th class="text-left py-2 px-3">#</th>
                  <th class="text-left py-2 px-3">Opened By</th>
                  <th class="text-left py-2 px-3">Closed By</th>
                  <th class="text-left py-2 px-3">Opening</th>
                  <th class="text-left py-2 px-3">Closing</th>
                  <th class="text-left py-2 px-3">Expected</th>
                  <th class="text-left py-2 px-3">Variance</th>
                  <th class="text-left py-2 px-3">Started</th>
                  <th class="text-left py-2 px-3">Ended</th>
                  <th class="text-left py-2 px-3">Status</th>
                  <th class="text-left py-2 px-3">Notes</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                <tr v-for="s in props.sessions.data" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/60">
                  <td class="py-2 px-3">{{ s.id }}</td>
                  <td class="py-2 px-3">
                    <span class="inline-flex items-center gap-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-slate-200 text-[10px] font-semibold">{{ userInitials(s.opened_by) }}</span>
                      <span>{{ userName(s.opened_by, s.opened_by_id || s.opened_by) }}</span>
                    </span>
                  </td>
                  <td class="py-2 px-3">
                    <span class="inline-flex items-center gap-2">
                      <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-slate-200 text-[10px] font-semibold">{{ userInitials(s.closed_by) }}</span>
                      <span>{{ userName(s.closed_by, s.closed_by_id || s.closed_by) }}</span>
                    </span>
                  </td>
                  <td class="py-2 px-3">{{ Number(s.opening_amount).toLocaleString() }}</td>
                  <td class="py-2 px-3">{{ s.closing_amount == null ? '—' : Number(s.closing_amount).toLocaleString() }}</td>
                  <td class="py-2 px-3">{{ s.expected_cash_at_close == null ? '—' : Number(s.expected_cash_at_close).toLocaleString() }}</td>
                  <td class="py-2 px-3"><span :class="{ 'text-red-600 dark:text-red-400': Number(s.variance) < 0, 'text-green-700 dark:text-green-400': Number(s.variance) > 0 }">{{ s.variance == null ? '—' : Number(s.variance).toLocaleString() }}</span></td>
                  <td class="py-2 px-3">{{ formatDate(s.started_at) }}</td>
                  <td class="py-2 px-3">{{ s.ended_at ? formatDate(s.ended_at) : '—' }}</td>
                  <td class="py-2 px-3">
                    <span v-if="s.status === 'open'" class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Open</span>
                    <span v-else-if="s.status === 'auto_closed'" class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300" title="Closed automatically at day end; reconcile it from the Cash Drawer page">Auto-closed</span>
                    <span v-else-if="s.status === 'closed'" class="px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-800 dark:bg-slate-700 dark:text-slate-200">Closed</span>
                    <span v-else class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-300">{{ s.status }}</span>
                  </td>
                  <td class="py-2 px-3">{{ s.notes || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-4" v-if="props.sessions.links">
            <div class="text-sm text-gray-600 dark:text-slate-300">Showing {{ props.sessions.from }} - {{ props.sessions.to }} of {{ props.sessions.total }}</div>
            <div class="flex gap-2 flex-wrap">
              <Button
                v-for="l in props.sessions.links"
                :key="(l.url || '') + l.label"
                :disabled="!l.url"
                :variant="l.active ? 'default' : 'outline'"
                size="sm"
                @click="l.url && router.get(l.url, {}, { preserveState: true, preserveScroll: true })"
                v-html="l.label"
              />
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
