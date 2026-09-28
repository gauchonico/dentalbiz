<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import Pagination from '@/Components/ui/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onUnmounted, watch, computed } from 'vue';
import { Plus, ArrowLeft, X, Upload, FileText } from 'lucide-vue-next';

interface Patient {
  id: number;
  name: string;
  email: string;
  phone: string;
  dob: string;
  dob_formatted: string;
  treatments: Treatment[];
  appointments: any[];
  invoices: any[];
  prescriptions: any[];
}

interface ProcedureEntry {
  name: string;
  cost: number | string;
}

interface Treatment {
  id: number;
  patient: { id: number; name: string; email?: string };
  procedure: string;
  cost: number;
  notes?: string;
  file_path?: string;
  appointment?: { id: number };
  invoice?: {
    id: number;
    amount?: number | string;
    paid_total?: number | string;
    balance?: number | string;
    payment_status?: 'pending' | 'partial' | 'paid';
  };
  prescriptions?: Array<{
    id?: number | null;
    medicine_id: number | null;
    dosage: string;
    quantity: number;
    prescription_amount: number;
    medicine?: { medicine_name: string };
  }>;
  procedures?: ProcedureEntry[];
  created_at?: string;
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface PaginatedData<T> {
  data: T[];
  meta: PaginationMeta;
  links?: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  patient: {
    id: number;
    name: string;
    email: string;
    phone: string;
    dob: string;
    dob_formatted: string;
  };
  patients: Array<{ id: number; name: string; email: string }>;
  treatments: PaginatedData<Treatment>;
  filters: {
    treatments_page: number;
    treatments_per_page: number;
  };
  medicines?: Array<{
    medicine_id: number;
    medicine_name: string;
    category: string;
    dosage_form: string;
    prescription_required: boolean;
  }>;
  appointmentTypes?: string[];
  procedureTemplates?: Array<{ name: string; cost: number }>;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const props = defineProps<Props>();

const isCreateTreatmentOpen = ref(false);
const filters = ref({
  treatments_page: props.filters?.treatments_page || 1,
  treatments_per_page: props.filters?.treatments_per_page || 10,
});

const perPageOptions = [5, 10, 25, 50];

const templateCostMap = computed<Record<string, number>>(() => {
  const entries = (props.procedureTemplates || []).map((template) => [template.name, Number(template.cost) || 0]);
  return Object.fromEntries(entries);
});

// Watch for changes in the treatments data
watch(() => props.treatments, () => {
  // This will trigger a re-render when treatments data changes
}, { deep: true });

// Update filters when props change
watch(() => props.filters, (newFilters) => {
  if (newFilters) {
    filters.value = {
      treatments_page: newFilters.treatments_page || 1,
      treatments_per_page: newFilters.treatments_per_page || 10,
    };
  }
}, { immediate: true });

// Handle per page change
const handlePerPageChange = (perPage: number) => {
  filters.value.treatments_per_page = perPage;
  filters.value.treatments_page = 1;
  router.get(route('patients.show', props.patient.id), {
    treatments_page: 1, // Reset to first page
    treatments_per_page: perPage,
  }, {
    preserveState: true,
    preserveScroll: true,
    only: ['treatments', 'filters']
  });
};

const paginationLinks = computed(() => {
  const directLinks = (props.treatments as any)?.links;
  if (Array.isArray(directLinks)) {
    return directLinks;
  }

  const metaLinks = (props.treatments as any)?.meta?.links;
  return Array.isArray(metaLinks) ? metaLinks : [];
});

const paginationSummary = computed(() => {
  const meta = props.treatments?.meta;
  if (meta) {
    return {
      from: meta.from ?? 0,
      to: meta.to ?? 0,
      total: meta.total ?? 0,
    };
  }

  return { from: 0, to: 0, total: 0 };
});

const goToPage = (link: PaginationLink) => {
  if (!link?.url || link.active) {
    return;
  }

  try {
    const url = new URL(link.url, window.location.origin);
    const pageParam = url.searchParams.get('treatments_page') ?? url.searchParams.get('page');

    if (pageParam) {
      router.get(route('patients.show', props.patient.id), {
        treatments_page: Number(pageParam),
        treatments_per_page: filters.value.treatments_per_page,
      }, {
        preserveState: true,
        preserveScroll: true,
        only: ['treatments', 'filters']
      });
    }
  } catch (error) {
    // Error handling for pagination
  }
};

const initialFormState = () => ({
  patient_id: props.patient.id,
  appointment_id: null as number | null,
  notes: '',
  file: null as File | null,
  procedures: [
    { name: '', cost: '' },
  ],
  prescriptions: [
    { medicine_id: null, dosage: '', quantity: 1, prescription_amount: 0 },
  ],
});

type TreatmentFormValues = ReturnType<typeof initialFormState>;

const formState = ref<TreatmentFormValues>(initialFormState());
const treatmentForm = useForm({ ...formState.value });

const syncTreatmentForm = (state: TreatmentFormValues) => {
  Object.assign(treatmentForm, state);
};

const updateProceduresState = (procedures: TreatmentFormValues['procedures']) => {
  formState.value.procedures = procedures;
  treatmentForm.procedures = procedures;
};

const updatePrescriptionsState = (prescriptions: TreatmentFormValues['prescriptions']) => {
  formState.value.prescriptions = prescriptions;
  treatmentForm.prescriptions = prescriptions;
};

const openCreateTreatment = () => {
  formState.value = initialFormState();
  syncTreatmentForm(formState.value);
  isCreateTreatmentOpen.value = true;
};

const filePreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// Computed property to calculate total cost of procedures
const proceduresTotal = computed(() => {
  return (treatmentForm.procedures || []).reduce((sum, entry) => {
    const value = Number(entry.cost);
    return sum + (Number.isFinite(value) ? value : 0);
  }, 0);
});

// Ensure at least one procedure row exists
const ensureCreateProcedureRow = () => {
  if (!treatmentForm.procedures || treatmentForm.procedures.length === 0) {
    updateProceduresState([{ name: '', cost: '' }]);
  }
};

// Update procedure field
const updateProcedureField = (index: number, field: string, value: any) => {
  const procedures = [...formState.value.procedures];
  const nextProcedure = { ...procedures[index], [field]: value };

  if (field === 'name') {
    const cost = templateCostMap.value[value];
    if (typeof cost === 'number' && !Number.isNaN(cost)) {
      nextProcedure.cost = cost;
    }
  }

  procedures[index] = nextProcedure;
  updateProceduresState(procedures);
};

// Add a new procedure row
const addProcedureRow = () => {
  const procedures = [...formState.value.procedures, { name: '', cost: '' }];
  updateProceduresState(procedures);
};

// Remove a procedure row
const removeProcedureRow = (index: number) => {
  if (formState.value.procedures.length > 1) {
    const procedures = [...formState.value.procedures];
    procedures.splice(index, 1);
    updateProceduresState(procedures);
  }
};

// Update prescription field
const updatePrescriptionField = (index: number, field: string, value: any) => {
  const prescriptions = [...formState.value.prescriptions];
  prescriptions[index] = { ...prescriptions[index], [field]: value };
  updatePrescriptionsState(prescriptions);
};

// Add a new prescription row
const addPrescriptionRow = () => {
  const prescriptions = [
    ...formState.value.prescriptions, 
    { medicine_id: null, dosage: '', quantity: 1, prescription_amount: 0 }
  ];
  updatePrescriptionsState(prescriptions);
};

// Remove a prescription row
const removePrescriptionRow = (index: number) => {
  const prescriptions = [...formState.value.prescriptions];
  prescriptions.splice(index, 1);
  updatePrescriptionsState(prescriptions);
};

// Handle file upload
const handleFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    const file = target.files[0];
    if (file.size > 5 * 1024 * 1024) { // 5MB
      alert('File size should be less than 5MB');
      return;
    }
    formState.value.file = file;
    treatmentForm.file = file;
    filePreview.value = URL.createObjectURL(file);
  }
};

// Remove uploaded file
const removeFile = () => {
  if (filePreview.value) {
    URL.revokeObjectURL(filePreview.value);
    filePreview.value = null;
  }
  formState.value.file = null;
  treatmentForm.file = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

// Clean up object URLs when component is unmounted
onUnmounted(() => {
  if (filePreview.value) {
    URL.revokeObjectURL(filePreview.value);
  }
});

// Submit treatment form
const submitTreatment = () => {
  ensureCreateProcedureRow();
  
  const formData = new FormData();
  
  // Append basic fields
  formData.append('patient_id', formState.value.patient_id.toString());
  if (formState.value.appointment_id) {
    formData.append('appointment_id', formState.value.appointment_id.toString());
  }
  if (formState.value.notes) {
    formData.append('notes', formState.value.notes);
  }
  
  // Append procedures
  formState.value.procedures.forEach((proc, index) => {
    if (proc.name && proc.cost) {
      formData.append(`procedures[${index}][name]`, proc.name);
      formData.append(`procedures[${index}][cost]`, proc.cost.toString());
    }
  });
  
  // Append prescriptions
  formState.value.prescriptions?.forEach((presc, index) => {
    if (presc.medicine_id) {
      formData.append(`prescriptions[${index}][medicine_id]`, presc.medicine_id.toString());
      formData.append(`prescriptions[${index}][dosage]`, presc.dosage);
      formData.append(`prescriptions[${index}][quantity]`, presc.quantity.toString());
      formData.append(`prescriptions[${index}][prescription_amount]`, presc.prescription_amount.toString());
    }
  });
  
  // Append file if exists
  if (formState.value.file) {
    formData.append('file', formState.value.file);
  }
  
  // Submit the form
  treatmentForm.post(route('treatments.store'), {
    onSuccess: () => {
      isCreateTreatmentOpen.value = false;
      formState.value = initialFormState();
      treatmentForm.reset();
      if (filePreview.value) {
        URL.revokeObjectURL(filePreview.value);
        filePreview.value = null;
      }
    },
    preserveScroll: true,
    onError: (errors) => {
      console.error('Error creating treatment:', errors);
    }
  });
};

const formatUGX = (value: number | string) => {
  const n = Number(value || 0);
  return `UGX ${Math.round(n).toLocaleString('en-US')}`;
};

// Format currency values
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0,
  }).format(amount).replace('UGX', 'UGX');
};

// Calculate total cost for treatment (procedure + prescriptions)
const calculateTotalCost = (treatment: Treatment) => {
  const procedureCost = Number(treatment.cost) || 0;
  const prescriptionCost = treatment.prescriptions?.reduce((total: number, prescription: any) => {
    return total + (Number(prescription.prescription_amount) || 0);
  }, 0) || 0;

  return procedureCost + prescriptionCost;
};

</script>

<template>
  <AppLayout :title="`Patient: ${props.patient.name}`">
    <Head :title="`Patient: ${props.patient.name}`" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Button variant="ghost" @click="$inertia.visit(route('patients.index'))">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Patients
          </Button>
          <div>
            <h1 class="text-3xl font-bold">{{ props.patient.name }}</h1>
            <p class="text-gray-600">{{ props.patient.email }} • {{ props.patient.phone }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <template v-if="!$page.url.includes('/patients/')">
            <Button @click="openCreateTreatment" class="bg-dental-blue hover:bg-dental-dark flex items-center space-x-2">
              <Plus class="mr-2 h-4 w-4" />
              <span>Add Treatment</span>
            </Button>
          </template>
          <Button variant="outline" @click="$inertia.visit(route('patients.medical-history.show', props.patient.id))">
            Medical History
          </Button>
          <Button variant="outline" @click="$inertia.visit(route('patients.notes.index', props.patient.id))">
            Clinical Notes
          </Button>
          <Button variant="outline" @click="$inertia.visit(route('patients.odontogram.show', props.patient.id))">
            Odontogram
          </Button>
          <Button variant="outline" @click="$inertia.visit(route('patients.images.index', props.patient.id))">
            X-rays &amp; Images
          </Button>
          <Button variant="outline" @click="$inertia.visit(route('patients.consents.index', props.patient.id))">
            Consents
          </Button>
        </div>
      </div>

      <!-- Patient Details -->
      <Card>
        <CardHeader>
          <CardTitle>Patient Information</CardTitle>
          <CardDescription>Basic patient details</CardDescription>
        </CardHeader>
        <CardContent class="grid grid-cols-2 gap-4">
          <div>
            <Label class="text-sm font-medium text-gray-500">Name</Label>
            <p class="text-sm">{{ props.patient.name }}</p>
          </div>
          <div>
            <Label class="text-sm font-medium text-gray-500">Email</Label>
            <p class="text-sm">{{ props.patient.email }}</p>
          </div>
          <div>
            <Label class="text-sm font-medium text-gray-500">Phone</Label>
            <p class="text-sm">{{ props.patient.phone }}</p>
          </div>
          <div>
            <Label class="text-sm font-medium text-gray-500">Date of Birth</Label>
            <p class="text-sm">{{ props.patient.dob_formatted }}</p>
          </div>
        </CardContent>
      </Card>

      <!-- Treatments -->
      <Card>
        <CardHeader>
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <CardTitle>Treatments</CardTitle>
              <CardDescription>Medical procedures performed</CardDescription>
            </div>
            <div class="flex items-center gap-2">
              <Button @click="isCreateTreatmentOpen = true" size="sm" class="bg-blue-600 hover:bg-blue-700 text-white">
                <Plus class="h-4 w-4 mr-2" />
                Add Treatment
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="!props.treatments || props.treatments.data.length === 0" class="text-center py-8 text-gray-500">
            No treatments recorded yet.
          </div>
          <div v-else class="space-y-4">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-2 px-3 font-medium text-gray-700 dark:text-gray-300">Procedure</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-700 dark:text-gray-300">Prescriptions</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-700 dark:text-gray-300">Date</th>
                    <th class="text-left py-2 px-3 font-medium text-gray-700 dark:text-gray-300">Total Cost</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                  <tr 
                    v-for="treatment in props.treatments.data" 
                    :key="treatment.id" 
                    class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                  >
                    <td class="py-3 px-3">
                      <div class="space-y-1">
                        <div
                          v-if="treatment.procedures?.length"
                          class="space-y-1"
                        >
                          <div
                            v-for="(procedure, idx) in treatment.procedures"
                            :key="`patient-treatment-${treatment.id}-procedure-${idx}`"
                            class="flex items-center justify-between gap-2"
                          >
                            <div class="flex items-center gap-2">
                              <i class="fas fa-tooth text-blue-500"></i>
                              <span class="font-medium text-gray-900 dark:text-white">{{ procedure.name }}</span>
                            </div>
                            <span class="text-xs text-green-600 dark:text-green-400">{{ formatCurrency(Number(procedure.cost || 0)) }}</span>
                          </div>
                        </div>
                        <div v-else class="flex items-center space-x-2">
                          <i class="fas fa-tooth text-blue-500"></i>
                          <span class="font-medium text-gray-900 dark:text-white">{{ treatment.procedure }}</span>
                          <span class="text-green-600 dark:text-green-400">({{ formatCurrency(treatment.cost || 0) }})</span>
                        </div>
                      </div>
                    </td>
                    <td class="py-3 px-3">
                      <div v-if="treatment.prescriptions && treatment.prescriptions.length > 0" class="space-y-1">
                        <div v-for="prescription in treatment.prescriptions" :key="prescription.id" class="text-xs">
                          <span class="text-gray-600 dark:text-gray-400">
                            {{ prescription.medicine?.medicine_name || prescription.medication }}
                            <span v-if="prescription.prescription_amount" class="text-green-600 dark:text-green-400">
                              ({{ formatCurrency(prescription.prescription_amount) }})
                            </span>
                          </span>
                        </div>
                      </div>
                      <span v-else class="text-gray-500 dark:text-gray-500 italic">No prescriptions</span>
                    </td>
                    <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                      {{ treatment.created_at ? new Date(treatment.created_at).toLocaleDateString() : 'Not specified' }}
                    </td>
                    <td class="py-3 px-3">
                      <span class="font-medium text-red-600 dark:text-red-400">
                        {{ formatCurrency(calculateTotalCost(treatment)) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Pagination -->
            <div v-if="paginationLinks.length > 0" class="flex flex-col md:flex-row md:items-center md:justify-between items-start gap-4 mt-4">
              <div class="flex-1 w-full">
                <Pagination
                  v-if="paginationLinks.length > 1"
                  :links="paginationLinks"
                  :from="paginationSummary.from"
                  :to="paginationSummary.to"
                  :total="paginationSummary.total"
                  :item-name="paginationSummary.total === 1 ? 'treatment' : 'treatments'"
                  @page-change="goToPage"
                />
              </div>
              <div class="flex items-center gap-2">
                <Label class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">Per page:</Label>
                <Select
                  :model-value="filters.treatments_per_page.toString()"
                  @update:modelValue="(value) => handlePerPageChange(Number(value))"
                >
                  <SelectTrigger class="h-8 w-20">
                    <SelectValue :placeholder="filters.treatments_per_page.toString()" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="option in perPageOptions"
                      :key="`patients-show-per-page-${option}`"
                      :value="option.toString()"
                    >
                      {{ option }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Add Treatment Modal -->
      <Dialog v-model:open="isCreateTreatmentOpen">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Add New Treatment</DialogTitle>
            <DialogDescription>Record a new dental procedure</DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submitTreatment" class="space-y-4">
            <input type="hidden" v-model="treatmentForm.patient_id" />
            
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <div>
                  <Label class="text-lg font-medium">Procedures</Label>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Add one or more procedures for this treatment.</p>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addProcedureRow">
                  <Plus class="w-4 h-4 mr-2" />Add Procedure
                </Button>
              </div>

              <div class="space-y-3">
                <div
                  v-for="(procedure, index) in formState.procedures"
                  :key="`patient-create-procedure-${index}`"
                  class="grid grid-cols-12 gap-3 items-end border rounded-lg p-3 bg-gray-50 dark:bg-gray-800/40"
                >
                  <div class="col-span-7">
                    <Label class="text-sm text-gray-600 dark:text-gray-300">Procedure</Label>
                    <Select 
                      v-model="formState.procedures[index].name"
                      @update:modelValue="(val) => updateProcedureField(index, 'name', val)"
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select or enter procedure" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="type in props.appointmentTypes || []" :key="type" :value="type">
                          {{ type }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="col-span-4">
                    <Label class="text-sm text-gray-600 dark:text-gray-300">Cost (UGX)</Label>
                    <Input 
                      :model-value="procedure.cost"
                      @update:modelValue="(val) => updateProcedureField(index, 'cost', val)"
                      type="number" 
                      placeholder="0.00" 
                      step="0.01" 
                      min="0" 
                    />
                  </div>
                  <div class="col-span-1 flex justify-end">
                    <Button type="button" variant="ghost" size="icon" @click="removeProcedureRow(index)">
                      <X class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-800 rounded-md px-3 py-2 text-sm">
              <span class="font-medium text-gray-700 dark:text-gray-300">Total Cost</span>
              <span class="font-semibold text-blue-600 dark:text-blue-400">{{ formatUGX(proceduresTotal) }}</span>
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label class="text-lg font-medium">Prescriptions</Label>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  @click="addPrescriptionRow"
                >
                  <Plus class="w-4 h-4 mr-2" />Add Prescription
                </Button>
              </div>

              <div v-for="(prescription, index) in formState.prescriptions" :key="index" class="grid grid-cols-12 gap-2 items-end border rounded-lg p-3 bg-gray-50 dark:bg-gray-800/40 mt-2">
                <div class="col-span-4">
                  <Label class="text-sm text-gray-600 dark:text-gray-300">Medicine</Label>
                  <Select 
                    :model-value="prescription.medicine_id"
                    @update:modelValue="(val) => updatePrescriptionField(index, 'medicine_id', val)"
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select a medicine" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem 
                        v-for="medicine in (props.medicines || [])" 
                        :key="medicine.medicine_id" 
                        :value="medicine.medicine_id"
                      >
                        {{ medicine.medicine_name }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="col-span-3">
                  <Label class="text-sm text-gray-600 dark:text-gray-300">Dosage</Label>
                  <Input 
                    :model-value="prescription.dosage"
                    @update:modelValue="(val) => updatePrescriptionField(index, 'dosage', val)" 
                    type="text" 
                    placeholder="e.g., 500mg x2/day"
                  />
                </div>
                <div class="col-span-2">
                  <Label class="text-sm text-gray-600 dark:text-gray-300">Quantity</Label>
                  <Input 
                    :model-value="prescription.quantity"
                    @update:modelValue="(val) => updatePrescriptionField(index, 'quantity', Number(val))"
                    type="number" 
                    min="1" 
                    placeholder="Qty" 
                  />
                </div>
                <div class="col-span-2">
                  <Label class="text-sm text-gray-600 dark:text-gray-300">Amount (UGX)</Label>
                  <Input 
                    :model-value="prescription.prescription_amount"
                    @update:modelValue="(val) => updatePrescriptionField(index, 'prescription_amount', Number(val))"
                    type="number" 
                    placeholder="0.00" 
                    step="0.01" 
                    min="0" 
                  />
                </div>
                <div class="col-span-1 flex items-end">
                  <Button 
                    type="button" 
                    variant="ghost" 
                    size="icon" 
                    class="h-10 w-10"
                    @click="removePrescriptionRow(index)"
                  >
                    <X class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <Label>Notes (Optional)</Label>
                <textarea 
                  v-model="formState.notes" 
                  @input="(e) => { 
                    formState.notes = e.target.value; 
                    treatmentForm.notes = e.target.value; 
                  }"
                  placeholder="Additional details" 
                  class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                ></textarea>
              </div>

              <div>
                <Label>Upload File (Optional)</Label>
                <div class="mt-1">
                  <Input 
                    type="file" 
                    @change="handleFileChange" 
                    ref="fileInput" 
                    accept="image/*,.pdf" 
                    class="cursor-pointer"
                  />
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">JPEG, PNG, JPG, PDF (max 5MB)</p>
                  
                  <div v-if="filePreview" class="mt-2 p-3 border rounded-md bg-gray-50 dark:bg-gray-800/40">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <FileText class="h-5 w-5 text-gray-500" />
                        <span class="text-sm font-medium">{{ formState.file?.name }}</span>
                      </div>
                      <Button 
                        type="button" 
                        variant="ghost" 
                        size="sm" 
                        class="h-8 w-8 p-0"
                        @click="removeFile"
                      >
                        <X class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            
            <DialogFooter>
              <Button 
                type="submit" 
                :disabled="treatmentForm.processing"
                class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white"
              >
                <span v-if="treatmentForm.processing" class="flex items-center">
                  <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Saving...
                </span>
                <span v-else>Save Treatment</span>
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>
