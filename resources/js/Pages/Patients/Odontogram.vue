<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ref, computed } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';

interface ToothState { tooth_code: string; status: string; note?: string }

interface Props {
  auth: any;
  patient: { id: number; name: string };
  odontogram: { id: number; scheme: 'FDI' | 'Universal' };
  teeth: ToothState[];
}

const props = defineProps<Props>();

const scheme = ref<'FDI' | 'Universal'>(props.odontogram.scheme || 'FDI');
const statusOrder = [
  'healthy','caries','restored','missing','crown','implant','root_canal','fracture','mobility','calculus'
];
const statusMeta: Record<string, { label: string; color: string; accent: string }> = {
  healthy: { label: 'Healthy', color: '#16a34a', accent: '#bbf7d0' },
  caries: { label: 'Caries', color: '#dc2626', accent: '#fecaca' },
  restored: { label: 'Restored', color: '#2563eb', accent: '#bfdbfe' },
  missing: { label: 'Missing', color: '#6b7280', accent: '#e5e7eb' },
  crown: { label: 'Crown', color: '#d97706', accent: '#fde68a' },
  implant: { label: 'Implant', color: '#0ea5e9', accent: '#bae6fd' },
  root_canal: { label: 'Root Canal', color: '#7c3aed', accent: '#ddd6fe' },
  fracture: { label: 'Fracture', color: '#ea580c', accent: '#fed7aa' },
  mobility: { label: 'Mobility', color: '#059669', accent: '#a7f3d0' },
  calculus: { label: 'Calculus', color: '#065f46', accent: '#a7f3d0' },
};

// Seed FDI adult tooth codes if none loaded
const fdiTeeth = [
  '18','17','16','15','14','13','12','11',
  '21','22','23','24','25','26','27','28',
  '48','47','46','45','44','43','42','41',
  '31','32','33','34','35','36','37','38'
];

const teeth = ref<ToothState[]>(props.teeth?.length ? props.teeth : fdiTeeth.map(c => ({ tooth_code: c, status: 'healthy' })));

const getTooth = (code: string) => teeth.value.find(t => t.tooth_code === code)!;
const setStatus = (t: ToothState, status: string) => { t.status = status; };
const cycleStatus = (t: ToothState) => {
  const idx = statusOrder.indexOf(t.status);
  const next = statusOrder[(idx + 1) % statusOrder.length];
  t.status = next;
};

const save = () => {
  router.post(`/patients/${props.patient.id}/odontogram`, {
    scheme: scheme.value,
    teeth: teeth.value,
  });
};

// Quadrant groupings (FDI)
const upperRight = ['18','17','16','15','14','13','12','11'];
const upperLeft  = ['21','22','23','24','25','26','27','28'];
const lowerRight = ['48','47','46','45','44','43','42','41'];
const lowerLeft  = ['31','32','33','34','35','36','37','38'];

// Universal numbering maps (adult 1-32)
const universalUpper = ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16'];
// Keep patient's right on the left for lower arch: 32 -> 17
const universalLower = ['32','31','30','29','28','27','26','25','24','23','22','21','20','19','18','17'];

const universalToFdi: Record<string, string> = {
  '1':'18','2':'17','3':'16','4':'15','5':'14','6':'13','7':'12','8':'11',
  '9':'21','10':'22','11':'23','12':'24','13':'25','14':'26','15':'27','16':'28',
  '17':'38','18':'37','19':'36','20':'35','21':'34','22':'33','23':'32','24':'31',
  '25':'41','26':'42','27':'43','28':'44','29':'45','30':'46','31':'47','32':'48',
};

const fdiToUniversal: Record<string, string> = Object.fromEntries(Object.entries(universalToFdi).map(([u,f]) => [f,u]));

// Scheme-aware display codes
const displayUpper = computed(() => scheme.value === 'FDI' ? [...upperRight, ...upperLeft] : universalUpper);
const displayLower = computed(() => scheme.value === 'FDI' ? [...lowerRight, ...lowerLeft] : universalLower);

// Resolve a display code to underlying FDI code for state lookup
const toFdi = (displayCode: string) => scheme.value === 'FDI' ? displayCode : universalToFdi[displayCode];
const getToothForDisplay = (displayCode: string) => getTooth(toFdi(displayCode));
</script>

<template>
  <AppLayout :title="`Odontogram - ${props.patient.name}`">
    <Head :title="`Odontogram - ${props.patient.name}`" />

    <div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Odontogram</h1>
          <p class="text-gray-500 dark:text-gray-400">{{ props.patient.name }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="outline" @click="$inertia.visit(route('patients.images.index', props.patient.id))">
            X-rays &amp; Images
          </Button>
          <Button variant="outline" @click="$inertia.visit(route('patients.show', props.patient.id))">
            Back to Patient
          </Button>
        </div>
      </div>
      <Card class="border-0 shadow-lg">
        <CardHeader>
          <CardTitle class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="hidden md:flex items-center gap-2">
                <div v-for="key in statusOrder" :key="key" class="flex items-center gap-1 text-xs">
                  <span class="inline-block w-3 h-3 rounded-full" :style="{ backgroundColor: statusMeta[key].color }"></span>
                  <span class="text-gray-600 dark:text-gray-300">{{ statusMeta[key].label }}</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <Label class="text-xs text-gray-600 dark:text-gray-300">Scheme</Label>
                <Select v-model="scheme">
                  <SelectTrigger class="w-36 h-9">
                    <SelectValue placeholder="Select scheme" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="FDI">FDI</SelectItem>
                    <SelectItem value="Universal">Universal</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <Button @click="save" class="bg-blue-600 hover:bg-blue-700 text-white">Save</Button>
            </div>
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-8 overflow-x-hidden">
          <!-- Upper arch -->
          <div class="space-y-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-300">Upper Arch</div>
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-8 gap-2">
              <div v-for="code in displayUpper" :key="code"
                class="tooth-tile"
                :style="{ borderColor: statusMeta[getToothForDisplay(code).status].color, backgroundColor: statusMeta[getToothForDisplay(code).status].accent }"
                @click="cycleStatus(getToothForDisplay(code))"
                :title="statusMeta[getToothForDisplay(code).status].label">
                <svg viewBox="0 0 40 50" class="w-10 h-12 mx-auto text-gray-700/70 dark:text-gray-200/80">
                  <!-- Base tooth silhouette -->
                  <path d="M20 4c6 0 10 5 10 11 0 3-1 6-2 9-1 3-1 6-1 9 0 4-3 7-7 7s-7-3-7-7c0-3 0-6-1-9-1-3-2-6-2-9C10 9 14 4 20 4z" fill="currentColor" fill-opacity="0.08" />
                  <!-- Gum line hint -->
                  <rect x="6" y="18" width="28" height="2" fill="currentColor" fill-opacity="0.06" />

                  <!-- Status overlays (image-like icons) -->
                  <!-- Healthy: small check mark -->
                  <g v-if="getToothForDisplay(code).status === 'healthy'" stroke="#16a34a" stroke-width="2" fill="none">
                    <path d="M12 34 l6 6 l10 -12" />
                  </g>

                  <!-- Caries: dark spot on occlusal -->
                  <g v-if="getToothForDisplay(code).status === 'caries'">
                    <circle cx="20" cy="20" r="3" fill="#dc2626" />
                    <circle cx="24" cy="24" r="2" fill="#b91c1c" />
                  </g>

                  <!-- Restored: silver filling rectangle -->
                  <g v-if="getToothForDisplay(code).status === 'restored'">
                    <rect x="15" y="18" width="10" height="6" rx="1" fill="#64748b" />
                  </g>

                  <!-- Missing: strike-through -->
                  <g v-if="getToothForDisplay(code).status === 'missing'" stroke="#6b7280" stroke-width="3">
                    <line x1="8" y1="10" x2="32" y2="38" />
                    <line x1="32" y1="10" x2="8" y2="38" />
                  </g>

                  <!-- Crown: golden cap -->
                  <g v-if="getToothForDisplay(code).status === 'crown'">
                    <path d="M13 12 h14 v6 c-2 1 -3 2 -7 2 s-5 -1 -7 -2 z" fill="#f59e0b" stroke="#d97706" stroke-width="1" />
                  </g>

                  <!-- Implant: screw post -->
                  <g v-if="getToothForDisplay(code).status === 'implant'" stroke="#0ea5e9" stroke-width="1.5" fill="none">
                    <line x1="20" y1="26" x2="20" y2="42" />
                    <path d="M16 28 h8 M16 32 h8 M16 36 h8" />
                  </g>

                  <!-- Root Canal: canal lines -->
                  <g v-if="getToothForDisplay(code).status === 'root_canal'" stroke="#7c3aed" stroke-width="1.5" fill="none">
                    <path d="M16 18 c0 10 2 18 4 24" />
                    <path d="M24 18 c0 10 -2 18 -4 24" />
                  </g>

                  <!-- Fracture: zigzag crack -->
                  <g v-if="getToothForDisplay(code).status === 'fracture'" stroke="#ea580c" stroke-width="2" fill="none">
                    <path d="M18 10 l2 4 l-2 4 l2 4 l-2 4" />
                  </g>

                  <!-- Mobility: arrows around tooth -->
                  <g v-if="getToothForDisplay(code).status === 'mobility'" stroke="#059669" stroke-width="1.5" fill="none">
                    <path d="M6 26 h6 m-2 -2 l2 2 l-2 2" />
                    <path d="M34 26 h-6 m2 -2 l-2 2 l2 2" />
                  </g>

                  <!-- Calculus: deposits near gum line -->
                  <g v-if="getToothForDisplay(code).status === 'calculus'">
                    <circle cx="12" cy="20" r="2" fill="#065f46" />
                    <circle cx="28" cy="20" r="1.8" fill="#065f46" />
                    <circle cx="20" cy="22" r="1.6" fill="#065f46" />
                  </g>
                </svg>
                <div class="tooth-code">{{ code }}</div>
                <div class="tooth-status" :style="{ color: statusMeta[getToothForDisplay(code).status].color }">{{ statusMeta[getToothForDisplay(code).status].label }}</div>
              </div>
            </div>
          </div>

          <!-- Lower arch -->
          <div class="space-y-3">
            <div class="text-sm font-medium text-gray-600 dark:text-gray-300">Lower Arch</div>
            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-8 gap-2">
              <div v-for="code in displayLower" :key="code"
                class="tooth-tile"
                :style="{ borderColor: statusMeta[getToothForDisplay(code).status].color, backgroundColor: statusMeta[getToothForDisplay(code).status].accent }"
                @click="cycleStatus(getToothForDisplay(code))"
                :title="statusMeta[getToothForDisplay(code).status].label">
                <svg viewBox="0 0 40 50" class="w-10 h-12 mx-auto text-gray-700/70 dark:text-gray-200/80">
                  <!-- Base tooth silhouette -->
                  <path d="M20 4c6 0 10 5 10 11 0 3-1 6-2 9-1 3-1 6-1 9 0 4-3 7-7 7s-7-3-7-7c0-3 0-6-1-9-1-3-2-6-2-9C10 9 14 4 20 4z" fill="currentColor" fill-opacity="0.08" />
                  <!-- Gum line hint -->
                  <rect x="6" y="18" width="28" height="2" fill="currentColor" fill-opacity="0.06" />

                  <!-- Status overlays (image-like icons) -->
                  <!-- Healthy: small check mark -->
                  <g v-if="getToothForDisplay(code).status === 'healthy'" stroke="#16a34a" stroke-width="2" fill="none">
                    <path d="M12 34 l6 6 l10 -12" />
                  </g>

                  <!-- Caries: dark spot on occlusal -->
                  <g v-if="getToothForDisplay(code).status === 'caries'">
                    <circle cx="20" cy="20" r="3" fill="#dc2626" />
                    <circle cx="24" cy="24" r="2" fill="#b91c1c" />
                  </g>

                  <!-- Restored: silver filling rectangle -->
                  <g v-if="getToothForDisplay(code).status === 'restored'">
                    <rect x="15" y="18" width="10" height="6" rx="1" fill="#64748b" />
                  </g>

                  <!-- Missing: strike-through -->
                  <g v-if="getToothForDisplay(code).status === 'missing'" stroke="#6b7280" stroke-width="3">
                    <line x1="8" y1="10" x2="32" y2="38" />
                    <line x1="32" y1="10" x2="8" y2="38" />
                  </g>

                  <!-- Crown: golden cap -->
                  <g v-if="getToothForDisplay(code).status === 'crown'">
                    <path d="M13 12 h14 v6 c-2 1 -3 2 -7 2 s-5 -1 -7 -2 z" fill="#f59e0b" stroke="#d97706" stroke-width="1" />
                  </g>

                  <!-- Implant: screw post -->
                  <g v-if="getToothForDisplay(code).status === 'implant'" stroke="#0ea5e9" stroke-width="1.5" fill="none">
                    <line x1="20" y1="26" x2="20" y2="42" />
                    <path d="M16 28 h8 M16 32 h8 M16 36 h8" />
                  </g>

                  <!-- Root Canal: canal lines -->
                  <g v-if="getToothForDisplay(code).status === 'root_canal'" stroke="#7c3aed" stroke-width="1.5" fill="none">
                    <path d="M16 18 c0 10 2 18 4 24" />
                    <path d="M24 18 c0 10 -2 18 -4 24" />
                  </g>

                  <!-- Fracture: zigzag crack -->
                  <g v-if="getToothForDisplay(code).status === 'fracture'" stroke="#ea580c" stroke-width="2" fill="none">
                    <path d="M18 10 l2 4 l-2 4 l2 4 l-2 4" />
                  </g>

                  <!-- Mobility: arrows around tooth -->
                  <g v-if="getToothForDisplay(code).status === 'mobility'" stroke="#059669" stroke-width="1.5" fill="none">
                    <path d="M6 26 h6 m-2 -2 l2 2 l-2 2" />
                    <path d="M34 26 h-6 m2 -2 l-2 2 l2 2" />
                  </g>

                  <!-- Calculus: deposits near gum line -->
                  <g v-if="getToothForDisplay(code).status === 'calculus'">
                    <circle cx="12" cy="20" r="2" fill="#065f46" />
                    <circle cx="28" cy="20" r="1.8" fill="#065f46" />
                    <circle cx="20" cy="22" r="1.6" fill="#065f46" />
                  </g>
                </svg>
                <div class="tooth-code">{{ code }}</div>
                <div class="tooth-status" :style="{ color: statusMeta[getToothForDisplay(code).status].color }">{{ statusMeta[getToothForDisplay(code).status].label }}</div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<style scoped>
.tooth-tile {
  @apply w-20 h-24 rounded-xl border bg-white/80 dark:bg-gray-900/40 backdrop-blur-sm shadow-sm flex flex-col items-center justify-start p-2 cursor-pointer transition-all duration-150 ease-out hover:shadow-md hover:-translate-y-0.5 active:scale-95;
}
.tooth-code {
  @apply text-[10px] text-gray-500 mt-1;
}
.tooth-status {
  @apply text-[10px] font-medium mt-1 truncate max-w-full;
}
</style>
