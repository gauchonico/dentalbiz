<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { route } from 'ziggy-js'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'

import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { MoreVertical } from 'lucide-vue-next'

interface PriceItem { id: number; name: string; cost: number; active: boolean }

const props = defineProps<{ filters: any; prices: any }>()

const qForm = useForm({ q: props.filters?.q || '' })
const applyFilters = () => {
  router.get(route('admin.treatment-procedure-prices.index'), qForm.data(), { preserveState: true, preserveScroll: true })
}

const createForm = useForm({ name: '', cost: 0 as number, active: true as boolean })
const createPrice = () => {
  if (!createForm.name.trim()) return alert('Name is required')
  createForm.post(route('admin.treatment-procedure-prices.store'), {
    preserveScroll: true,
    onSuccess: () => createForm.reset('name', 'cost')
  })
}

const editing = reactive<Record<number, boolean>>({})
const editForms = reactive<Record<number, any>>({})
const startEdit = (price: PriceItem) => {
  editing[price.id] = true
  editForms[price.id] = useForm({ name: price.name, cost: price.cost, active: price.active })
}
const cancelEdit = (id: number) => { editing[id] = false }
const saveEdit = (id: number) => {
  const f = editForms[id]
  if (!f) return
  f.post(route('admin.treatment-procedure-prices.update', id), { preserveScroll: true, onSuccess: () => { editing[id] = false } })
}

const isDeleteOpen = reactive<{ open: boolean }>({ open: false })
const deletingPrice = reactive<{ id: number|null; name: string }>({ id: null, name: '' })
const openDelete = (price: PriceItem) => { deletingPrice.id = price.id; deletingPrice.name = price.name; isDeleteOpen.open = true }
const confirmDelete = () => {
  if (!deletingPrice.id) return
  router.delete(route('admin.treatment-procedure-prices.destroy', deletingPrice.id), {
    preserveScroll: true,
    onSuccess: () => { isDeleteOpen.open = false; deletingPrice.id = null as any; deletingPrice.name = '' },
  })
}
const toggleActive = (id: number) => {
  router.post(route('admin.treatment-procedure-prices.toggle', id), {}, { preserveScroll: true })
}
</script>

<template>
  <AppLayout title="Treatment Procedure Prices">
    <Head title="Treatment Procedure Prices" />

    <div class="container mx-auto px-4 py-8 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Treatment Procedure Prices</h1>
          <p class="text-gray-500 dark:text-slate-400">Manage the prices used for treatments and invoices</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Search</CardTitle>
          <CardDescription>Find a procedure by name</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid md:grid-cols-3 gap-3 items-end">
            <div class="md:col-span-2">
              <Label>Search</Label>
              <input v-model="qForm.q" class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" placeholder="Search by procedure name" />
            </div>
            <div class="flex gap-2">
              <Button @click="applyFilters" class="bg-blue-600 hover:bg-blue-700 text-white">Apply</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Add Procedure Price</CardTitle>
          <CardDescription>Create a new procedure price entry</CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="createPrice" class="grid md:grid-cols-3 gap-3">
            <div>
              <Label>Name</Label>
              <input v-model="createForm.name" class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" placeholder="e.g. Root Canal" />
            </div>
            <div>
              <Label>Cost</Label>
              <input v-model.number="createForm.cost" type="number" min="0" step="1000" class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" />
            </div>
            <div class="flex items-end">
              <Button type="submit" :disabled="createForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white">Create</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Procedure Prices</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-gray-800 dark:text-slate-200">
              <thead class="bg-gray-50 dark:bg-slate-800">
                <tr class="border-b border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300">
                  <th class="text-left py-2 px-3">Name</th>
                  <th class="text-left py-2 px-3">Cost</th>
                  <th class="text-left py-2 px-3">Status</th>
                  <th class="text-left py-2 px-3">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                <tr v-for="price in props.prices.data" :key="price.id" class="hover:bg-gray-50 dark:hover:bg-slate-800/60">
                  <td class="py-2 px-3">
                    <div v-if="!editing[price.id]">{{ price.name }}</div>
                    <div v-else>
                      <input v-model="editForms[price.id].name" class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" />
                    </div>
                  </td>
                  <td class="py-2 px-3">
                    <div v-if="!editing[price.id]">{{ price.cost }}</div>
                    <div v-else>
                      <input v-model.number="editForms[price.id].cost" type="number" min="0" step="1000" class="w-full border rounded-md p-2 bg-white text-gray-800 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700" />
                    </div>
                  </td>
                  <td class="py-2 px-3">
                    <span v-if="price.active" class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Active</span>
                    <span v-else class="px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-800 dark:bg-slate-700 dark:text-slate-200">Inactive</span>
                  </td>
                  <td class="py-2 px-3">
                    <div v-if="!editing[price.id]" class="flex gap-2">
                      <Button size="sm" variant="outline" @click="startEdit(price)">Edit</Button>
                      <Button size="sm" variant="outline" @click="toggleActive(price.id)">{{ price.active ? 'Disable' : 'Enable' }}</Button>
                      <Button size="sm" variant="destructive" @click="openDelete(price)">Delete</Button>
                    </div>
                    <div v-else class="flex gap-2">
                      <Button size="sm" variant="outline" @click="cancelEdit(price.id)">Cancel</Button>
                      <Button size="sm" class="bg-blue-600 hover:bg-blue-700 text-white" @click="saveEdit(price.id)">Save</Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-4" v-if="props.prices.links">
            <div class="text-sm text-gray-600 dark:text-slate-300">Showing {{ props.prices.from }} - {{ props.prices.to }} of {{ props.prices.total }}</div>
            <div class="flex gap-2 flex-wrap">
              <Button
                v-for="l in props.prices.links"
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

      <Dialog :open="isDeleteOpen.open" @update:open="(v:boolean)=> isDeleteOpen.open = v">
        <DialogContent class="max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold text-red-600">Delete Procedure Price</DialogTitle>
            <DialogDescription class="text-gray-600 dark:text-gray-400">
              This action cannot be undone. This will permanently delete the procedure price entry.
            </DialogDescription>
          </DialogHeader>
          <div class="py-2 text-sm">
            Are you sure you want to delete <span class="font-medium">{{ deletingPrice.name }}</span>?
          </div>
          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" @click="isDeleteOpen.open = false">Cancel</Button>
            <Button type="button" class="bg-red-600 hover:bg-red-700" @click="confirmDelete">Delete</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
