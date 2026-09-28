<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import {
  ArrowLeft, Upload, FileText, X, ZoomIn, ZoomOut, RotateCcw, Download, Trash2,
  ChevronLeft, ChevronRight, Contrast, Sun, ImageIcon,
} from 'lucide-vue-next'

interface PatientImage {
  id: number
  category: string
  teeth: string | null
  taken_at: string | null
  notes: string | null
  original_name: string
  mime_type: string
  size: number
  is_image: boolean
  treatment_id: number | null
  uploader: { id: number; name: string } | null
  created_at: string
  url: string
  can_delete: boolean
}

interface Props {
  patient: { id: number; name: string; email?: string; phone?: string; dob?: string }
  images: PatientImage[]
  categories: Record<string, string>
  treatments: { id: number; label: string }[]
  can_upload: boolean
}

const props = defineProps<Props>()
const page = usePage<any>()
const flashSuccess = computed(() => page.props?.flash?.success as string | undefined)

// ---- Filtering ----
const activeCategory = ref<string | null>(null)
const toothFilter = ref('')

const countFor = (cat: string) => props.images.filter(i => i.category === cat).length
const usedCategories = computed(() => Object.keys(props.categories).filter(c => countFor(c) > 0))

const filtered = computed(() => {
  const tooth = toothFilter.value.trim()
  return props.images.filter(i => {
    if (activeCategory.value && i.category !== activeCategory.value) return false
    if (tooth && !(i.teeth || '').split(',').includes(tooth)) return false
    return true
  })
})

const isXray = (cat: string) => !cat.endsWith('_photo') && cat !== 'other'

const formatDate = (val?: string | null) => {
  if (!val) return '—'
  const d = new Date(val)
  return isNaN(d.getTime()) ? '—' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}
const formatSize = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`

// ---- Upload ----
const showUpload = ref(false)
const dragOver = ref(false)
const MAX_FILE = 20 * 1024 * 1024
const MAX_FILES = 10
const clientError = ref<string | null>(null)

const uploadForm = useForm({
  files: [] as File[],
  category: 'periapical',
  teeth: '',
  taken_at: new Date().toISOString().slice(0, 10),
  treatment_id: '' as string | number,
  notes: '',
})

const previews = ref<string[]>([])
watch(() => uploadForm.files, (files) => {
  previews.value.forEach(URL.revokeObjectURL)
  previews.value = files.map(f => f.type.startsWith('image/') ? URL.createObjectURL(f) : '')
})

const addFiles = (list: FileList | null) => {
  if (!list) return
  clientError.value = null
  const accepted = Array.from(list).filter(f => {
    if (!/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(f.type)) {
      clientError.value = `${f.name}: only JPG, PNG, WEBP or PDF files are allowed.`
      return false
    }
    if (f.size > MAX_FILE) {
      clientError.value = `${f.name} is larger than 20 MB.`
      return false
    }
    return true
  })
  const combined = [...uploadForm.files, ...accepted]
  if (combined.length > MAX_FILES) clientError.value = `You can upload up to ${MAX_FILES} files at a time.`
  uploadForm.files = combined.slice(0, MAX_FILES)
}
const removeFile = (idx: number) => { uploadForm.files = uploadForm.files.filter((_, i) => i !== idx) }
const onDrop = (e: DragEvent) => { dragOver.value = false; addFiles(e.dataTransfer?.files ?? null) }

const fileErrors = computed(() => Object.entries(uploadForm.errors)
  .filter(([k]) => k.startsWith('files'))
  .map(([, v]) => v))

const openUpload = () => {
  uploadForm.reset()
  uploadForm.clearErrors()
  clientError.value = null
  showUpload.value = true
}

const submitUpload = () => {
  if (!uploadForm.files.length) { clientError.value = 'Choose at least one file.'; return }
  uploadForm
    .transform(data => ({ ...data, treatment_id: data.treatment_id || null, teeth: data.teeth || null }))
    .post(route('patients.images.store', props.patient.id), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => { showUpload.value = false; uploadForm.reset() },
    })
}

// ---- Viewer ----
const viewerIndex = ref<number | null>(null)
const current = computed(() => viewerIndex.value === null ? null : filtered.value[viewerIndex.value] ?? null)
const zoom = ref(1)
const brightness = ref(100)
const contrast = ref(100)
const invert = ref(false)

const resetAdjustments = () => { zoom.value = 1; brightness.value = 100; contrast.value = 100; invert.value = false }
const openViewer = (img: PatientImage) => {
  if (!img.is_image) { window.open(img.url, '_blank'); return }
  viewerIndex.value = filtered.value.indexOf(img)
  resetAdjustments()
}
const closeViewer = () => { viewerIndex.value = null }
const step = (dir: 1 | -1) => {
  if (viewerIndex.value === null) return
  const images = filtered.value
  let i = viewerIndex.value
  do { i = (i + dir + images.length) % images.length } while (!images[i].is_image && i !== viewerIndex.value)
  viewerIndex.value = i
  resetAdjustments()
}
const setZoom = (z: number) => { zoom.value = Math.min(5, Math.max(0.5, Math.round(z * 100) / 100)) }
const onWheel = (e: WheelEvent) => setZoom(zoom.value + (e.deltaY < 0 ? 0.2 : -0.2))

const imageStyle = computed(() => ({
  transform: `scale(${zoom.value})`,
  filter: `brightness(${brightness.value}%) contrast(${contrast.value}%)${invert.value ? ' invert(1)' : ''}`,
}))

const onKey = (e: KeyboardEvent) => {
  if (current.value) {
    if (e.key === 'Escape') closeViewer()
    else if (e.key === 'ArrowRight') step(1)
    else if (e.key === 'ArrowLeft') step(-1)
    else if (e.key === '+' || e.key === '=') setZoom(zoom.value + 0.25)
    else if (e.key === '-') setZoom(zoom.value - 0.25)
  } else if (showUpload.value && e.key === 'Escape') {
    showUpload.value = false
  }
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => {
  window.removeEventListener('keydown', onKey)
  previews.value.forEach(URL.revokeObjectURL)
})

const deleting = ref(false)
const deleteImage = (img: PatientImage) => {
  if (!confirm(`Remove "${img.original_name}" from this patient's record?`)) return
  deleting.value = true
  router.delete(route('patients.images.destroy', [props.patient.id, img.id]), {
    preserveScroll: true,
    onSuccess: () => closeViewer(),
    onFinish: () => { deleting.value = false },
  })
}

const selectClass = 'w-full border rounded-md p-2 bg-white text-gray-800 border-gray-200 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700'
</script>

<template>
  <AppLayout :title="`Imaging - ${props.patient.name}`">
    <Head :title="`Imaging - ${props.patient.name}`" />

    <div class="container mx-auto px-4 py-8 space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <Button variant="ghost" @click="router.visit(route('patients.show', props.patient.id))">
            <ArrowLeft class="mr-2 h-4 w-4" /> Back to Patient
          </Button>
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">X-rays &amp; Images</h1>
            <p class="text-gray-500 dark:text-slate-400">{{ props.patient.name }} · {{ props.images.length }} file{{ props.images.length === 1 ? '' : 's' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" @click="router.visit(route('patients.odontogram.show', props.patient.id))">Odontogram</Button>
          <Button v-if="props.can_upload" class="bg-blue-600 hover:bg-blue-700 text-white" @click="openUpload">
            <Upload class="mr-2 h-4 w-4" /> Upload
          </Button>
        </div>
      </div>

      <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300">
        {{ flashSuccess }}
      </div>

      <!-- Filters -->
      <div v-if="props.images.length" class="flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="px-3 py-1.5 rounded-full text-sm border transition-colors"
            :class="activeCategory === null ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-800'"
            @click="activeCategory = null"
          >All ({{ props.images.length }})</button>
          <button
            v-for="cat in usedCategories"
            :key="cat"
            type="button"
            class="px-3 py-1.5 rounded-full text-sm border transition-colors"
            :class="activeCategory === cat ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-800'"
            @click="activeCategory = cat"
          >{{ props.categories[cat] }} ({{ countFor(cat) }})</button>
        </div>
        <div class="ml-auto w-40">
          <Label class="text-xs">Tooth (FDI)</Label>
          <Input v-model="toothFilter" placeholder="e.g. 36" class="h-9" />
        </div>
      </div>

      <!-- Empty state -->
      <Card v-if="!props.images.length">
        <CardContent class="py-16 text-center">
          <ImageIcon class="mx-auto h-12 w-12 text-gray-300 dark:text-slate-600" />
          <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No X-rays or images yet</h2>
          <p class="mt-1 text-gray-500 dark:text-slate-400">Upload periapicals, bitewings, OPGs or clinical photos to keep them with this patient's record.</p>
          <Button v-if="props.can_upload" class="mt-6 bg-blue-600 hover:bg-blue-700 text-white" @click="openUpload">
            <Upload class="mr-2 h-4 w-4" /> Upload first image
          </Button>
        </CardContent>
      </Card>

      <p v-else-if="!filtered.length" class="text-center text-gray-500 dark:text-slate-400 py-10">No images match these filters.</p>

      <!-- Grid -->
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        <button
          v-for="img in filtered"
          :key="img.id"
          type="button"
          class="group text-left rounded-lg overflow-hidden border border-gray-200 bg-white hover:shadow-md hover:border-blue-300 transition dark:border-slate-700 dark:bg-slate-900 dark:hover:border-blue-700"
          @click="openViewer(img)"
        >
          <div class="aspect-[4/3] flex items-center justify-center overflow-hidden" :class="isXray(img.category) ? 'bg-black' : 'bg-gray-100 dark:bg-slate-800'">
            <img v-if="img.is_image" :src="img.url" :alt="img.original_name" loading="lazy" class="h-full w-full object-contain group-hover:scale-105 transition-transform" />
            <FileText v-else class="h-12 w-12 text-gray-400" />
          </div>
          <div class="p-3 space-y-1">
            <div class="flex items-center justify-between gap-2">
              <Badge class="bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 truncate">{{ props.categories[img.category] || img.category }}</Badge>
              <span class="text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap">{{ formatDate(img.taken_at) }}</span>
            </div>
            <div v-if="img.teeth" class="text-xs text-gray-600 dark:text-slate-300">Teeth: {{ img.teeth.split(',').join(', ') }}</div>
            <div v-if="img.notes" class="text-xs text-gray-500 dark:text-slate-400 line-clamp-2">{{ img.notes }}</div>
          </div>
        </button>
      </div>
    </div>

    <!-- Upload modal -->
    <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showUpload = false">
      <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-xl dark:bg-slate-900">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload X-rays / Images</h2>
          <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200" @click="showUpload = false"><X class="h-5 w-5" /></button>
        </div>

        <form class="space-y-4" @submit.prevent="submitUpload">
          <label
            class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 cursor-pointer transition-colors"
            :class="dragOver ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 hover:border-blue-400 dark:border-slate-600'"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
          >
            <Upload class="h-8 w-8 text-gray-400" />
            <span class="mt-2 text-sm text-gray-700 dark:text-slate-300">Drag files here or <span class="text-blue-600 dark:text-blue-400 underline">browse</span></span>
            <span class="text-xs text-gray-500 dark:text-slate-400">JPG, PNG, WEBP or PDF · up to 20 MB each · max {{ MAX_FILES }} files</span>
            <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="addFiles(($event.target as HTMLInputElement).files); ($event.target as HTMLInputElement).value = ''" />
          </label>

          <div v-if="uploadForm.files.length" class="grid grid-cols-3 sm:grid-cols-5 gap-2">
            <div v-for="(f, i) in uploadForm.files" :key="i" class="relative rounded border border-gray-200 dark:border-slate-700 overflow-hidden">
              <img v-if="previews[i]" :src="previews[i]" class="h-20 w-full object-cover" />
              <div v-else class="h-20 flex items-center justify-center bg-gray-100 dark:bg-slate-800"><FileText class="h-8 w-8 text-gray-400" /></div>
              <div class="px-1 py-0.5 text-[10px] truncate text-gray-600 dark:text-slate-400">{{ f.name }} · {{ formatSize(f.size) }}</div>
              <button type="button" class="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white hover:bg-black/80" @click="removeFile(i)"><X class="h-3 w-3" /></button>
            </div>
          </div>
          <div v-if="clientError" class="text-red-600 text-xs">{{ clientError }}</div>
          <div v-for="(e, i) in fileErrors" :key="i" class="text-red-600 text-xs">{{ e }}</div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <Label>Type</Label>
              <select v-model="uploadForm.category" :class="selectClass">
                <option v-for="(label, key) in props.categories" :key="key" :value="key">{{ label }}</option>
              </select>
              <div v-if="uploadForm.errors.category" class="text-red-600 text-xs mt-1">{{ uploadForm.errors.category }}</div>
            </div>
            <div>
              <Label>Date Taken</Label>
              <Input v-model="uploadForm.taken_at" type="date" :max="new Date().toISOString().slice(0, 10)" />
              <div v-if="uploadForm.errors.taken_at" class="text-red-600 text-xs mt-1">{{ uploadForm.errors.taken_at }}</div>
            </div>
            <div>
              <Label>Teeth (FDI, optional)</Label>
              <Input v-model="uploadForm.teeth" placeholder="e.g. 36, 37" />
              <div v-if="uploadForm.errors.teeth" class="text-red-600 text-xs mt-1">{{ uploadForm.errors.teeth }}</div>
            </div>
            <div>
              <Label>Link to Treatment (optional)</Label>
              <select v-model="uploadForm.treatment_id" :class="selectClass">
                <option value="">None</option>
                <option v-for="t in props.treatments" :key="t.id" :value="t.id">{{ t.label }}</option>
              </select>
              <div v-if="uploadForm.errors.treatment_id" class="text-red-600 text-xs mt-1">{{ uploadForm.errors.treatment_id }}</div>
            </div>
          </div>
          <div>
            <Label>Findings / Notes</Label>
            <textarea v-model="uploadForm.notes" rows="3" :class="selectClass" placeholder="e.g. Periapical radiolucency on 36, pre-RCT"></textarea>
            <div v-if="uploadForm.errors.notes" class="text-red-600 text-xs mt-1">{{ uploadForm.errors.notes }}</div>
          </div>

          <div v-if="uploadForm.progress" class="h-2 w-full rounded bg-gray-200 dark:bg-slate-700 overflow-hidden">
            <div class="h-full bg-blue-600 transition-all" :style="{ width: `${uploadForm.progress.percentage}%` }"></div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" @click="showUpload = false">Cancel</Button>
            <Button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white" :disabled="uploadForm.processing || !uploadForm.files.length">
              {{ uploadForm.processing ? 'Uploading...' : `Upload ${uploadForm.files.length || ''} file${uploadForm.files.length === 1 ? '' : 's'}` }}
            </Button>
          </div>
        </form>
      </div>
    </div>

    <!-- Viewer -->
    <div v-if="current" class="fixed inset-0 z-50 flex flex-col bg-black/95 text-white">
      <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-white/10">
        <div class="min-w-0">
          <div class="font-medium truncate">{{ props.categories[current.category] || current.category }}<span v-if="current.teeth"> · Teeth {{ current.teeth.split(',').join(', ') }}</span></div>
          <div class="text-xs text-white/60 truncate">
            {{ formatDate(current.taken_at) }} · {{ current.original_name }} · {{ formatSize(current.size) }}<span v-if="current.uploader"> · by {{ current.uploader.name }}</span>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button type="button" class="p-2 rounded hover:bg-white/10" title="Zoom out (-)" @click="setZoom(zoom - 0.25)"><ZoomOut class="h-5 w-5" /></button>
          <span class="w-12 text-center text-sm tabular-nums">{{ Math.round(zoom * 100) }}%</span>
          <button type="button" class="p-2 rounded hover:bg-white/10" title="Zoom in (+)" @click="setZoom(zoom + 0.25)"><ZoomIn class="h-5 w-5" /></button>
          <button type="button" class="p-2 rounded hover:bg-white/10" :class="invert ? 'bg-white/20' : ''" title="Invert" @click="invert = !invert"><Contrast class="h-5 w-5" /></button>
          <button type="button" class="p-2 rounded hover:bg-white/10" title="Reset" @click="resetAdjustments"><RotateCcw class="h-5 w-5" /></button>
          <a :href="`${current.url}?download=1`" class="p-2 rounded hover:bg-white/10" title="Download"><Download class="h-5 w-5" /></a>
          <button v-if="current.can_delete" type="button" class="p-2 rounded hover:bg-red-500/30 text-red-300" title="Remove" :disabled="deleting" @click="deleteImage(current)"><Trash2 class="h-5 w-5" /></button>
          <button type="button" class="p-2 rounded hover:bg-white/10 ml-2" title="Close (Esc)" @click="closeViewer"><X class="h-5 w-5" /></button>
        </div>
      </div>

      <div class="relative flex-1 overflow-auto flex items-center justify-center" @wheel.prevent="onWheel">
        <img :src="current.url" :alt="current.original_name" class="max-h-full max-w-full object-contain transition-transform origin-center select-none" :style="imageStyle" draggable="false" />
        <button v-if="filtered.length > 1" type="button" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 hover:bg-white/20" title="Previous (←)" @click="step(-1)"><ChevronLeft class="h-6 w-6" /></button>
        <button v-if="filtered.length > 1" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 hover:bg-white/20" title="Next (→)" @click="step(1)"><ChevronRight class="h-6 w-6" /></button>
      </div>

      <div class="flex flex-wrap items-center gap-6 px-4 py-3 border-t border-white/10 text-sm">
        <label class="flex items-center gap-2"><Sun class="h-4 w-4" /> Brightness
          <input v-model.number="brightness" type="range" min="30" max="200" class="w-32 accent-blue-500" />
        </label>
        <label class="flex items-center gap-2"><Contrast class="h-4 w-4" /> Contrast
          <input v-model.number="contrast" type="range" min="30" max="250" class="w-32 accent-blue-500" />
        </label>
        <p v-if="current.notes" class="flex-1 min-w-[200px] text-white/80">{{ current.notes }}</p>
      </div>
    </div>
  </AppLayout>
</template>
