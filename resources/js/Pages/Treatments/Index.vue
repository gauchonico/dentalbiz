<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { formatUGX } from '@/Composables/useCurrency';
import { route } from 'ziggy-js';
import { ref, computed, watch } from 'vue';
import debounce from 'lodash.debounce';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { AlertCircle } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Plus, Search, MoreVertical, Stethoscope, FileText, Calendar, Upload, Receipt, Pill } from 'lucide-vue-next';
import Pagination from '@/Components/ui/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// Interfaces
interface Patient {
  id: number;
  name: string;
  email: string;
}

interface ProcedureEntry {
  name: string;
  cost: number | '';
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
  procedures?: Array<{ id?: number; name: string; cost: number }>;
  prescriptions?: Array<{
    id?: number | null;
    medicine_id: number | null;
    dosage: string;
    quantity: number;
    prescription_amount: number;
    medication?: string | null;
    medicine?: { medicine_name: string };
  }>;
  created_at?: string;
}

interface InventoryItem {
  id: number;
  name: string;
  unit_price: number;
  unit?: string;
  category?: string;
}

interface PaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  from?: number;
  to?: number;
}

interface Filters {
  page?: number;
  per_page?: number;
  search?: string;
  patient?: string;
  invoice_status?: 'all' | 'invoiced' | 'not_invoiced';
  sort_by?: 'created_at' | 'procedure' | 'cost' | 'patient_id';
  sort_order?: 'asc' | 'desc';
}

interface Props {
  auth: { user: { id: number; name: string } | null };
  treatments: {
    data: Treatment[];
    meta: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
      from: number;
      to: number;
    };
  };
  patients: Patient[];
  stats?: {
    total_treatments: number;
    total_revenue: number;
    this_month_treatments: number;
  };
  appointmentTypes: string[];
  medicines: InventoryItem[];
  filters?: Filters;
  procedureTemplates?: Array<{ name: string; cost: number }>;
}

const props = defineProps<Props>();

// State
const searchQuery = ref('');
const filterPatient = ref('all');
const filterInvoiceStatus = ref('all');
const sortBy = ref('created_at');
const sortOrder = ref('desc');
const listViewPerPage = ref((props.treatments?.meta?.per_page ?? 10).toString());
const currentPage = ref(props.treatments?.meta?.current_page ?? 1);

// Initialize state from server-provided filters (if available)
if (props.filters) {
  searchQuery.value = props.filters.search ?? '';
  filterPatient.value = props.filters.patient ?? 'all';
  filterInvoiceStatus.value = props.filters.invoice_status ?? 'all';
  sortBy.value = props.filters.sort_by ?? 'created_at';
  sortOrder.value = props.filters.sort_order ?? 'desc';
  if (props.filters.per_page) listViewPerPage.value = props.filters.per_page.toString();
  if (props.filters.page) currentPage.value = props.filters.page;
}

// Computed properties
const totalTreatments = computed(() => props.treatments.meta?.total ?? 0);
const lastPage = computed(() => props.treatments.meta?.last_page ?? 1);

const templateCostMap = computed<Record<string, number>>(() => {
  const entries = (props.procedureTemplates || []).map((template) => [template.name, Number(template.cost) || 0]);
  return Object.fromEntries(entries);
});

const paginationLinks = computed(() => {
  const links: Array<{ url: string | null; label: string; active: boolean }> = [];
  const current = currentPage.value;
  const last = lastPage.value;

  // Previous link
  links.push({
    url: current > 1 ? '#' : null,
    label: '&laquo; Previous',
    active: current === 1,
  });
  // Page numbers (show current ± 2 pages)
  const startPage = Math.max(1, current - 2);
  const endPage = Math.min(last, current + 2);
  for (let i = startPage; i <= endPage; i++) {
    links.push({
      url: i === current ? null : '#',
      label: i.toString(),
      active: i === current,
    });
  }

  // Next link
  links.push({
    url: current < last ? '#' : null,
    label: 'Next &raquo;',
    active: current === last,
  });
  
  return links;
});

const patientSearchQuery = ref("")

const filteredPatientsForSelect = computed(() => {
  const query = patientSearchQuery.value.trim().toLowerCase()
  const patients = Array.isArray(props.patients) ? props.patients : []

  if (!query) {
    return patients
  }

  return patients.filter((patient) => {
    const parts = [
      patient.id,
      patient.name,
      patient.email,
      (patient as any).phone,
      `${patient.name ?? ""} ${(patient as any).phone ?? ""}`,
    ]

    return parts.some((part) => {
      if (part === undefined || part === null) {
        return false
      }
      return String(part).toLowerCase().includes(query)
    })
  })
})

const ensureCreateProcedureRow = () => {
  if (!Array.isArray(createForm.procedures) || createForm.procedures.length === 0) {
    createForm.procedures = [{ name: '', cost: 0 }]
  }
}

const ensureEditProcedureRow = () => {
  if (!Array.isArray(editForm.procedures) || editForm.procedures.length === 0) {
    editForm.procedures = [{ name: '', cost: 0 }]
  }
}

const addProcedureRow = () => {
  createForm.procedures.push({ name: '', cost: 0 })
}

const addEditProcedureRow = () => {
  editForm.procedures.push({ name: '', cost: 0 })
}

const updateCreateProcedureField = (index: number, field: keyof ProcedureEntry, value: any) => {
  const next = { ...createForm.procedures[index], [field]: value } as ProcedureEntry
  if (field === 'name') {
    const cost = templateCostMap.value[value]
    if (typeof cost === 'number' && !Number.isNaN(cost)) {
      next.cost = cost
    }
  }
  createForm.procedures.splice(index, 1, next)
}

const updateEditProcedureField = (index: number, field: keyof ProcedureEntry, value: any) => {
  const next = { ...editForm.procedures[index], [field]: value } as ProcedureEntry
  if (field === 'name') {
    const cost = templateCostMap.value[value]
    if (typeof cost === 'number' && !Number.isNaN(cost)) {
      next.cost = cost
    }
  }
  editForm.procedures.splice(index, 1, next)
}

const removeProcedureRow = (index: number) => {
  if (createForm.procedures.length === 1) {
    createForm.procedures[0] = { name: '', cost: 0 }
    return
  }
  createForm.procedures.splice(index, 1)
}

const removeEditProcedureRow = (index: number) => {
  if (editForm.procedures.length === 1) {
    editForm.procedures[0] = { name: '', cost: 0 }
    return
  }
  editForm.procedures.splice(index, 1)
}

const createProceduresTotal = computed(() => {
  return (createForm.procedures || []).reduce((sum, entry) => {
    const value = Number(entry.cost)
    return sum + (Number.isFinite(value) ? value : 0)
  }, 0)
})

const editProceduresTotal = computed(() => {
  return (editForm.procedures || []).reduce((sum, entry) => {
    const value = Number(entry.cost)
    return sum + (Number.isFinite(value) ? value : 0)
  }, 0)
})


const filteredTreatments = computed(() => {
  let treatments = [...(props.treatments?.data || [])];

  // Apply search filter
  if (searchQuery.value) {
    treatments = treatments.filter(treatment => {
      const matchesProcedures = (treatment as any).procedures?.some((procedure: any) =>
        procedure?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
      )
      const matchesPatient = treatment.patient?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
      const matchesNotes = treatment.notes?.toLowerCase().includes(searchQuery.value.toLowerCase())
      const matchesPrescriptions = treatment.prescriptions?.some(pres => pres.medicine_id?.toString().includes(searchQuery.value.toLowerCase()))

      return matchesProcedures || matchesPatient || matchesNotes || matchesPrescriptions
    });
  }

  // Apply patient filter
  if (filterPatient.value !== 'all') {
    treatments = treatments.filter(treatment => treatment.patient?.id.toString() === filterPatient.value);
  }

  // Apply invoice status filter
  if (filterInvoiceStatus.value !== 'all') {
    const wantInvoiced = filterInvoiceStatus.value === 'invoiced';
    treatments = treatments.filter(t => (!!t.invoice) === wantInvoiced);
  }

  // Sort treatments
  treatments.sort((a, b) => {
    const key = sortBy.value as keyof Treatment;
    let aValue: any, bValue: any;

    switch (key) {
      case 'procedure':
        aValue = ((a as any).procedures?.[0]?.name || '').toLowerCase();
        bValue = ((b as any).procedures?.[0]?.name || '').toLowerCase();
        break;
      case 'cost':
        aValue = a.cost;
        bValue = b.cost;
        break;
      case 'patient_id':
        aValue = a.patient?.name.toLowerCase() || '';
        bValue = b.patient?.name.toLowerCase() || '';
        break;
      case 'created_at':
      default:
        aValue = new Date(a.created_at || 0).getTime();
        bValue = new Date(b.created_at || 0).getTime();
        break;
    }

    return sortOrder.value === 'asc' ? aValue < bValue ? -1 : 1 : aValue < bValue ? 1 : -1;
  });

  return treatments;
});

const paginationSummary = computed(() => {
  const meta = props.treatments?.meta;
  if (meta) {
    return { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total ?? totalTreatments.value };
  }
  const count = filteredTreatments.value.length;
  return { from: count ? 1 : 0, to: count, total: totalTreatments.value };
});

const buildFiltersPayload = () => ({
  page: currentPage.value,
  per_page: Number(listViewPerPage.value),
  search: searchQuery.value,
  patient: filterPatient.value,
  invoice_status: filterInvoiceStatus.value,
  sort_by: sortBy.value,
  sort_order: sortOrder.value,
});

const fetchTreatments = (overrides: Partial<Filters> = {}, { replace = true } = {}) => {
  const payload = {
    ...buildFiltersPayload(),
    ...overrides,
  };

  const pageNumber = Number(payload.page);
  if (Number.isFinite(pageNumber) && pageNumber > 0) {
    currentPage.value = pageNumber;
    payload.page = pageNumber;
  } else {
    payload.page = currentPage.value;
  }

  payload.per_page = Number(payload.per_page ?? Number(listViewPerPage.value));

  router.get(route('treatments.index'), payload, {
    preserveState: true,
    preserveScroll: true,
    replace,
    only: ['treatments', 'filters'],
  });
};

const debouncedFetchTreatments = debounce((overrides: Partial<Filters> = {}) => {
  fetchTreatments(overrides);
}, 300);

watch(listViewPerPage, (value, oldValue) => {
  if (value === oldValue) return;
  const perPage = Number(value);
  if (!Number.isFinite(perPage) || perPage <= 0) return;

  currentPage.value = 1;
  fetchTreatments({ per_page: perPage, page: 1 });
});

watch(searchQuery, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchTreatments({ search: value, page: 1 });
});

watch(filterPatient, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchTreatments({ patient: value, page: 1 });
});

watch(filterInvoiceStatus, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchTreatments({ invoice_status: value, page: 1 });
});

watch(sortBy, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  fetchTreatments({ sort_by: value, page: 1 });
});

watch(sortOrder, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  fetchTreatments({ sort_order: value, page: 1 });
});

watch(() => props.treatments?.meta?.per_page, (value) => {
  if (value && value.toString() !== listViewPerPage.value) {
    listViewPerPage.value = value.toString();
  }
});

watch(() => props.treatments?.meta?.current_page, (value) => {
  if (value && value !== currentPage.value) {
    currentPage.value = value;
  }
});

// Pagination navigation
const goToPage = (link: { url: string | null; label: string; active: boolean }) => {
  if (link.active || !link.url) return;

  let newPage = currentPage.value;
  if (link.label.includes('Previous')) {
    newPage = currentPage.value - 1;
  } else if (link.label.includes('Next')) {
    newPage = currentPage.value + 1;
  } else if (!isNaN(parseInt(link.label, 10))) {
    newPage = parseInt(link.label, 10);
  } else {
    return; // Don't proceed if it's not a valid page number
  }

  if (newPage >= 1 && newPage <= lastPage.value) {
    fetchTreatments({ page: newPage }, { replace: true });
  }
};

// Rest of the script (form handling, modals, etc.)
const createForm = useForm({
  patient_id: null as number | null,
  appointment_id: null as number | null,
  cost: 0,
  notes: '',
  file: null as File | null,
  procedures: [
    { name: '', cost: 0 } as ProcedureEntry,
  ],
  prescriptions: [] as Array<{ id?: number | null; inventory_item_id: number | null; dosage: string; quantity: number; prescription_amount: number; medication?: string }>,
});

const editForm = useForm({
  patient_id: null as number | null,
  appointment_id: null as number | null,
  cost: 0,
  notes: '',
  file: null as File | null,
  procedures: [] as ProcedureEntry[],
  prescriptions: [] as Array<{ id?: number | null; inventory_item_id: number | null; dosage: string; quantity: number; prescription_amount: number; medication?: string }>,
});

const isCreateOpen = ref(false);
const isEditOpen = ref(false);
const isDeleteOpen = ref(false);
const isViewOpen = ref(false);
const isPaymentOpen = ref(false);
const showCashSessionDialog = ref(false);
const showErrorDialog = ref(false);
const editingTreatment = ref<Treatment | null>(null);
const viewingTreatment = ref<Treatment | null>(null);
const payingTreatment = ref<Treatment | null>(null);

// Invoice creation preview/confirmation
const isInvoicePreviewOpen = ref(false);
const invoicingTreatment = ref<Treatment | null>(null);
const isCreatingInvoice = ref(false);

watch([isCreateOpen, isEditOpen], ([createOpen, editOpen]) => {
  if (!createOpen && !editOpen) {
    patientSearchQuery.value = ""
  }
})

watch(patientSearchQuery, (newQuery) => {
  if (!newQuery.trim()) {
    if (isCreateOpen.value) {
      createForm.patient_id = null
    }
    if (isEditOpen.value) {
      editForm.patient_id = null
    }
  }
})

// Permissions: admin or receptionist can take payments
const page = usePage<any>();
const canTakePayments = computed(() => {
  const roles = (page?.props?.auth?.user?.roles || []).map((r: any) => r?.name ?? r);
  return Array.isArray(roles) && (roles.includes('admin') || roles.includes('receptionist'));
});

const openCreate = () => {
  isCreateOpen.value = true;
  patientSearchQuery.value = ''
  createForm.reset()
  createForm.procedures = [{ name: '', cost: 0 }]
  createForm.prescriptions = []
  createForm.cost = 0
};

const getInventoryItemById = (id: number | null) => {
  if (!id) return null;
  return (props.medicines || []).find((item) => item.id === id) || null;
};

const updatePrescriptionAmount = (prescription: { inventory_item_id: number | null; quantity: number; prescription_amount: number; medication?: string }, index: number, formType: 'create' | 'edit') => {
  const item = getInventoryItemById(prescription.inventory_item_id);
  const quantity = Number(prescription.quantity || 1);
  const unitPrice = Number(item?.unit_price || 0);
  const amount = unitPrice * quantity;

  if (formType === 'create') {
    createForm.prescriptions[index].prescription_amount = amount;
    createForm.prescriptions[index].medication = item?.name || createForm.prescriptions[index].medication;
  } else {
    editForm.prescriptions[index].prescription_amount = amount;
    editForm.prescriptions[index].medication = item?.name || editForm.prescriptions[index].medication;
  }
};

const openEdit = (treatment: Treatment) => {
  if (treatment.invoice) {
    alert('This treatment has an invoice and cannot be edited.');
    return;
  }
  
  editingTreatment.value = treatment;
  
  // Reset form and set values directly
  editForm.reset();
  editForm.patient_id = treatment.patient?.id || null;
  editForm.procedures = (treatment as any).procedures?.length
    ? (treatment as any).procedures.map((procedure: any) => ({
        id: procedure.id,
        name: procedure.name,
        cost: Number(procedure.cost ?? 0),
      }))
    : [{ name: treatment.procedure || '', cost: Number(treatment.cost ?? 0) }];
  ensureEditProcedureRow()
  editForm.cost = editProceduresTotal.value;
  editForm.notes = treatment.notes || '';
  editForm.prescriptions = treatment.prescriptions?.map(pres => ({
    id: pres.id,
    inventory_item_id: pres.medicine_id ?? (pres as any).inventory_item_id ?? null,
    dosage: pres.dosage,
    quantity: pres.quantity,
    prescription_amount: pres.prescription_amount,
    medication: (pres as any).medication || undefined,
  })) || [];
  
  isEditOpen.value = true;
};

const openDelete = (treatment: Treatment) => {
  editingTreatment.value = treatment;
  isDeleteOpen.value = true;
};

const openView = (treatment: Treatment) => {
  viewingTreatment.value = treatment;
  isViewOpen.value = true;
};

// --- Invoice creation: preview all procedures + prescriptions for this treatment,
// then confirm before combining them into a single invoice ---

const invoicePreviewProcedures = computed(() => {
  const treatment = invoicingTreatment.value as any;
  if (!treatment) return [];
  if (treatment.procedures?.length) return treatment.procedures;
  return [{ name: treatment.procedure, cost: treatment.cost }];
});

const invoicePreviewPrescriptions = computed(() => invoicingTreatment.value?.prescriptions || []);

const invoicePreviewProceduresTotal = computed(() =>
  invoicePreviewProcedures.value.reduce((sum: number, p: any) => sum + (Number(p.cost) || 0), 0)
);

const invoicePreviewPrescriptionsTotal = computed(() =>
  invoicePreviewPrescriptions.value.reduce((sum, p) => sum + (Number(p.prescription_amount) || 0), 0)
);

const invoicePreviewTotal = computed(() => invoicePreviewProceduresTotal.value + invoicePreviewPrescriptionsTotal.value);

const openInvoicePreview = (treatment: Treatment) => {
  if (treatment.invoice) {
    alert('An invoice already exists for this treatment.');
    return;
  }
  invoicingTreatment.value = treatment;
  isInvoicePreviewOpen.value = true;
};

const confirmCreateInvoice = () => {
  if (!invoicingTreatment.value?.id) return;
  isCreatingInvoice.value = true;
  router.post(route('treatments.createInvoice', invoicingTreatment.value.id), {}, {
    onSuccess: () => {
      isInvoicePreviewOpen.value = false;
      invoicingTreatment.value = null;
      router.reload();
    },
    onError: (errors) => {
      alert('Failed to create invoice. Please try again.');
      console.error(errors);
    },
    onFinish: () => {
      isCreatingInvoice.value = false;
    },
  });
};

const paymentForm = useForm({
  amount: 0,
  method: '',
  received_at: new Date().toISOString().slice(0, 10),
  reference: '',
  notes: '',
});

const openPayment = (treatment: Treatment) => {
  if (!treatment.invoice) return;
  payingTreatment.value = treatment;
  paymentForm.reset();
  paymentForm.amount = Number(treatment.invoice.balance || 0);
  paymentForm.received_at = new Date().toISOString().slice(0, 10);
  isPaymentOpen.value = true;
};

const submitPayment = async () => {
  if (!payingTreatment.value?.invoice?.id) return;
  if (paymentForm.amount <= 0) {
    alert('Amount must be greater than zero.');
    return;
  }
  const maxBalance = Number(payingTreatment.value.invoice.balance || 0);
  if (Number(paymentForm.amount) > maxBalance + 0.0001) {
    alert(`Amount cannot exceed current balance of UGX ${formatUGX(maxBalance)}`);
    return;
  }
  // If method is cash, ensure an active cash session
  if (paymentForm.method === 'cash') {
    try {
      const res = await fetch(route('cash-drawer.active'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();
      if (!data?.active) {
        showCashSessionDialog.value = true;
        return new Promise((resolve) => {
          const unwatch = watch(showCashSessionDialog, (newVal) => {
            if (!newVal) {
              unwatch();
              resolve(false);
            }
          });
        });
      }
    } catch (e) {
      showErrorDialog.value = true;
      return;
    }
  }
  paymentForm.post(route('invoices.payments.store', payingTreatment.value.invoice.id), {
    preserveScroll: true,
    onSuccess: () => {
      isPaymentOpen.value = false;
      payingTreatment.value = null;
      router.reload();
    },
  });
};

const submitCreate = () => {
  if (!createForm.patient_id) {
    alert('Please select a patient.');
    return;
  }
  if (!createForm.procedures.length || createProceduresTotal.value <= 0) {
    alert('Add at least one procedure with a cost greater than zero.');
    return;
  }
  createForm.cost = createProceduresTotal.value;
  createForm.post(route('treatments.store'), {
    forceFormData: true,
    onSuccess: () => {
      createForm.reset();
      isCreateOpen.value = false;
    },
  });
};

const submitEdit = () => {
  if (!editForm.patient_id) {
    alert('Please select a patient.');
    return;
  }
  
  if (!editForm.procedures.length || editProceduresTotal.value <= 0) {
    alert('Add at least one procedure with a cost greater than zero.');
    return;
  }
  
  // Create a new form data object
  const formData = new FormData();
  
  // Handle patient_id - ensure it's a single value
  const patientId = Array.isArray(editForm.patient_id) 
    ? editForm.patient_id[0] 
    : editForm.patient_id;
  
  // Add all form data
  formData.append('_method', 'PUT');
  formData.append('patient_id', patientId);
  formData.append('cost', editProceduresTotal.value.toString());
  formData.append('notes', editForm.notes || '');
  
  // Add procedures
  editForm.procedures.forEach((proc, index) => {
    formData.append(`procedures[${index}][name]`, proc.name);
    formData.append(`procedures[${index}][cost]`, proc.cost.toString());
    if (proc.id) {
      formData.append(`procedures[${index}][id]`, proc.id.toString());
    }
  });
  
  // Add prescriptions if any
  if (editForm.prescriptions?.length) {
    editForm.prescriptions.forEach((presc, index) => {
      if (presc.inventory_item_id) {
        formData.append(`prescriptions[${index}][inventory_item_id]`, presc.inventory_item_id.toString());
        formData.append(`prescriptions[${index}][dosage]`, presc.dosage || '');
        formData.append(`prescriptions[${index}][quantity]`, (presc.quantity || 0).toString());
        formData.append(`prescriptions[${index}][prescription_amount]`, (presc.prescription_amount || 0).toString());
        if (presc.medication) {
          formData.append(`prescriptions[${index}][medication]`, presc.medication);
        }
        if (presc.id) {
          formData.append(`prescriptions[${index}][id]`, presc.id.toString());
        }
      }
    });
  }
  
  if (editingTreatment.value) {
    // Use axios directly to have more control over the request
    axios.post(route('treatments.update', editingTreatment.value.id), formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    }).then(() => {
      // Refresh the page to show updated data
      window.location.reload();
    }).catch(error => {
      console.error('Error updating treatment:', error);
      
      // Handle validation errors
      if (error.response?.status === 422) {
        const errors = error.response.data.errors || {};
        
        // Convert errors to a user-friendly message
        const errorMessages: string[] = [];
        
        for (const [, messages] of Object.entries(errors)) {
          if (Array.isArray(messages)) {
            errorMessages.push(...messages);
          } else if (typeof messages === 'string') {
            errorMessages.push(messages);
          } else if (typeof messages === 'object') {
            errorMessages.push(...Object.values(messages as Record<string, string>).flat());
          }
        }
        
        // Show error message to the user
        if (errorMessages.length > 0) {
          alert('Please fix the following errors:\n\n' + [...new Set(errorMessages)].join('\n'));
        } else {
          alert('An error occurred while updating the treatment. Please try again.');
        }
      } else {
        // Handle other types of errors
        const errorMessage = error.response?.data?.message || 
                           error.message || 
                           'An unknown error occurred while updating the treatment.';
        alert(errorMessage);
      }
    });
  }
};

const confirmDelete = () => {
  if (editingTreatment.value) {
    router.delete(route('treatments.destroy', editingTreatment.value.id), {
      onSuccess: () => {
        isDeleteOpen.value = false;
        editingTreatment.value = null;
      },
    });
  }
};

const getTreatmentIcon = (procedure: string) => {
  const lower = (procedure || '').toLowerCase();
  if (lower.includes('cleaning') || lower.includes('hygiene')) return 'fas fa-toothbrush';
  if (lower.includes('filling') || lower.includes('cavity')) return 'fas fa-fill';
  if (lower.includes('extraction') || lower.includes('removal')) return 'fas fa-tooth';
  if (lower.includes('crown') || lower.includes('bridge')) return 'fas fa-crown';
  if (lower.includes('implant')) return 'fas fa-bolt';
  if (lower.includes('x-ray') || lower.includes('radiograph')) return 'fas fa-x-ray';
  return 'fas fa-stethoscope';
};


const navigateToCashDrawer = () => {
  window.location.href = route('cash-drawer.index');
};

const calculateTotalCost = (treatment: Treatment) => {
  const procedureCost = Number(treatment.cost) || 0;
  const prescriptionCost = treatment.prescriptions?.reduce((total: number, prescription: any) => {
    return total + (Number(prescription.prescription_amount) || 0);
  }, 0) || 0;

  return procedureCost + prescriptionCost;
};
</script>

<template>
  <AppLayout title="Treatments">
    <Head title="Treatments" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
      <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Treatment Management</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage dental procedures and patient records</p>
          </div>
          <div class="flex items-center gap-3">
            <Badge class="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 px-3 py-1">
              <Stethoscope class="w-4 h-4 mr-2" />
              {{ totalTreatments }} Treatments
            </Badge>
            <Button @click="openCreate" class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white shadow-lg hover:shadow-xl transition-all duration-300">
              <Plus class="w-4 h-4 mr-2" />
              Add Treatment
            </Button>
          </div>
        </div>

        <!-- Stats -->
        <div v-if="props.stats" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <Card class="shadow-md hover:shadow-lg transition-shadow">
            <CardContent class="p-4 flex justify-between items-center">
              <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Treatments</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.total_treatments }}</p>
              </div>
              <Stethoscope class="w-8 h-8 text-blue-500" />
            </CardContent>
          </Card>
          <Card class="shadow-md hover:shadow-lg transition-shadow">
            <CardContent class="p-4 flex justify-between items-center">
              <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Revenue</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatUGX(props.stats.total_revenue) }}</p>
              </div>
              <Receipt class="w-8 h-8 text-green-500" />
            </CardContent>
          </Card>
          <Card class="shadow-md hover:shadow-lg transition-shadow">
            <CardContent class="p-4 flex justify-between items-center">
              <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">This Month</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.this_month_treatments }}</p>
              </div>
              <Calendar class="w-8 h-8 text-purple-500" />
            </CardContent>
          </Card>
        </div>

        <!-- Main Content -->
        <Card class="shadow-md">
          <CardHeader>
            <CardTitle>Treatment Records</CardTitle>
            <CardDescription>View and manage all treatments</CardDescription>
          </CardHeader>
          <CardContent>
            <!-- Filters -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
              <div class="relative flex-1">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                <Input v-model="searchQuery" placeholder="Search treatments..." class="pl-10" />
              </div>
              <Select v-model="filterPatient">
                <SelectTrigger class="w-48">
                  <SelectValue placeholder="All patients" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All patients</SelectItem>
                  <SelectItem v-for="patient in props.patients" :key="patient.id" :value="patient.id.toString()">
                    {{ patient.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <Select v-model="filterInvoiceStatus">
                <SelectTrigger class="w-48">
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  <SelectItem value="not_invoiced">Not invoiced</SelectItem>
                  <SelectItem value="invoiced">Invoiced</SelectItem>
                </SelectContent>
              </Select>
              <Select v-model="sortBy">
                <SelectTrigger class="w-32">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="created_at">Date</SelectItem>
                  <SelectItem value="procedure">Procedure</SelectItem>
                  <SelectItem value="cost">Cost</SelectItem>
                  <SelectItem value="patient_id">Patient</SelectItem>
                </SelectContent>
              </Select>
              <Button @click="sortOrder = sortOrder === 'asc' ? 'desc' : 'asc'" variant="outline" size="sm">
                <i :class="['fas', sortOrder === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
              </Button>
              <Select v-model="listViewPerPage">
                <SelectTrigger class="w-24">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in [10, 20, 30, 50]" :key="option" :value="option.toString()">
                    {{ option }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto border rounded-lg">
              <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-800">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">PATIENT</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">PROCEDURE & PRESCRIPTIONS</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">STATUS</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">TOTAL COST</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">DATE</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">ACTIONS</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="treatment in filteredTreatments" :key="treatment.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer" @click="openView(treatment)">
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center">
                          <i :class="[getTreatmentIcon(treatment.procedure), 'text-white text-sm']"></i>
                        </div>
                        <div>
                          <p class="font-semibold text-gray-900 dark:text-white">{{ treatment.patient?.name || 'N/A' }}</p>
                          <p class="text-xs text-gray-500 dark:text-gray-400">ID: {{ treatment.id }}</p>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <div class="space-y-2">
                        <!-- Procedure -->
                        <div class="flex items-center gap-2">
                          <span class="w-6 h-6 rounded-md bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center flex-shrink-0">
                            <i :class="[getTreatmentIcon(treatment.procedure), 'text-blue-500 dark:text-blue-300 text-xs']"></i>
                          </span>
                          <div class="font-medium text-gray-900 dark:text-white flex-1">{{ treatment.procedure || 'N/A' }}</div>
                          <span class="text-sm font-medium text-blue-600 dark:text-blue-400">{{ formatUGX(treatment.cost) }}</span>
                        </div>
                        
                        <!-- Prescriptions -->
                        <div v-if="treatment.prescriptions?.length" class="pl-8 space-y-2">
                          <div v-for="prescription in treatment.prescriptions" :key="prescription.id" class="flex items-center gap-2 text-sm">
                            <span class="w-5 h-5 rounded-md bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                              <i class="fas fa-pills text-green-500 dark:text-green-400 text-xs"></i>
                            </span>
                            <span class="text-gray-700 dark:text-gray-300 flex-1">
                              {{ prescription.medication || prescription.medicine?.medicine_name || 'Prescription' }}
                              <span v-if="prescription.dosage" class="text-xs text-gray-500 dark:text-gray-400">({{ prescription.dosage }})</span>
                            </span>
                            <span class="text-blue-600 dark:text-blue-400 font-medium">
                              {{ formatUGX(Number(prescription.prescription_amount || 0)) }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </td>
                    
                    <!-- Status -->
                    <td class="px-4 py-3">
                      <Badge v-if="!treatment.invoice" class="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Not Invoiced</Badge>
                      <Badge v-else :class="{
                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': treatment.invoice.payment_status === 'pending',
                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': treatment.invoice.payment_status === 'partial',
                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': treatment.invoice.payment_status === 'paid'
                      }">
                        {{ treatment.invoice.payment_status === 'paid' ? 'Paid' : treatment.invoice.payment_status === 'partial' ? 'Partially Paid' : 'Pending' }}
                        <template v-if="treatment.invoice.payment_status === 'paid' && treatment.invoice.amount_paid">
                          : {{ formatUGX(treatment.invoice.amount_paid) }}
                        </template>
                        <template v-else-if="treatment.invoice.payment_status === 'partial' && treatment.invoice.amount_paid">
                          : {{ formatUGX(treatment.invoice.amount_paid) }}
                        </template>
                      </Badge>
                    </td>
                    
                    <!-- Total Cost -->
                    <td class="px-4 py-3">
                      <div class="text-right">
                        <div class="text-base font-semibold text-blue-600 dark:text-blue-400">
                          {{ formatUGX(calculateTotalCost(treatment)) }}
                        </div>
                        <div v-if="treatment.prescriptions?.length" class="text-xs text-gray-500 dark:text-gray-400">
                          ({{ formatUGX(treatment.cost) }} + {{ formatUGX(treatment.prescriptions.reduce((sum, p) => sum + Number(p.prescription_amount || 0), 0)) }})
                        </div>
                      </div>
                    </td>
                    
                    <!-- Date -->
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                      <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                          <Calendar class="w-4 h-4 text-gray-400" />
                          {{ treatment.created_at ? new Date(treatment.created_at).toLocaleDateString() : '—' }}
                        </div>
                        <div v-if="treatment.invoice" class="text-xs text-gray-500 dark:text-gray-400">
                          <span>Paid: {{ formatUGX(Number(treatment.invoice.paid_total || 0)) }}</span>
                          <span class="mx-1">•</span>
                          <span>Due: {{ formatUGX(Number(treatment.invoice.balance || 0)) }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-right" @click.stop>
                      <div class="flex justify-end">
                        <DropdownMenu>
                          <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                              <MoreVertical class="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuItem @click="openView(treatment)">
                              <i class="fas fa-eye mr-2 w-4 h-4 text-center"></i>View Details
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="!treatment.invoice" @click="openEdit(treatment)">
                              <i class="fas fa-edit mr-2 w-4 h-4 text-center"></i>Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="!treatment.invoice" @click="openInvoicePreview(treatment)" class="text-green-600">
                              <FileText class="w-4 h-4 mr-2" />Create Invoice
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              v-if="canTakePayments && treatment.invoice && treatment.invoice.payment_status !== 'paid'"
                              @click="openPayment(treatment)"
                              class="text-amber-600"
                            >
                              <i class="fas fa-credit-card mr-2 w-4 h-4 text-center"></i>Record Payment
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="openDelete(treatment)" class="text-red-600">
                              <i class="fas fa-trash mr-2 w-4 h-4 text-center"></i>Delete
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!filteredTreatments.length">
                    <td colspan="7" class="px-6 py-12 text-center text-gray-600 dark:text-gray-400">
                      <Stethoscope class="w-10 h-10 mx-auto mb-4 text-gray-400" />
                      <p class="text-lg font-semibold">
                        {{ searchQuery || filterPatient !== 'all' ? 'No treatments found' : 'No treatments yet' }}
                      </p>
                      <p class="text-sm">
                        {{ searchQuery || filterPatient !== 'all' ? 'Try adjusting your filters' : 'Add your first treatment to get started.' }}
                      </p>
                      <Button
                        v-if="!searchQuery && filterPatient === 'all'"
                        @click="openCreate"
                        class="mt-4 bg-blue-600 hover:bg-blue-700 text-white"
                      >
                        <Plus class="w-4 h-4 mr-2" />Add Treatment
                      </Button>
                      <Button v-else @click="searchQuery = ''; filterPatient = 'all'" variant="outline" class="mt-4">
                        Clear Filters
                      </Button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div v-if="paginationLinks.length > 0" class="flex flex-col md:flex-row md:items-center md:justify-between items-start gap-4 mt-6">
              <div class="flex-1">
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
                <Label for="per-page" class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">Per page:</Label>
                <Select v-model="listViewPerPage" @update:model-value="() => fetchTreatments()">
                  <SelectTrigger class="h-8 w-20">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in [10, 20, 30, 50]" :key="option" :value="option.toString()">
                      {{ option }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Modals -->
      <Dialog :open="isCreateOpen" @update:open="val => isCreateOpen = val">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Add New Treatment</DialogTitle>
            <DialogDescription>Record a new dental procedure</DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
              <Label>Patient</Label>
              <Select v-model="createForm.patient_id">
                <SelectTrigger><SelectValue placeholder="Select a patient" /></SelectTrigger>
                <SelectContent>
                  <div class="p-2">
                    <Input
                      v-model="patientSearchQuery"
                      type="text"
                      placeholder="Search patients..."
                      class="h-9"
                      autocomplete="off"
                      @keydown.stop
                      @keyup.stop
                      @keypress.stop
                    />
                  </div>
                  <template v-if="filteredPatientsForSelect.length">
                    <SelectItem v-for="patient in filteredPatientsForSelect" :key="patient.id" :value="patient.id">
                      {{ patient.name }} ({{ patient.email }})
                    </SelectItem>
                  </template>
                  <div v-else class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    No patients found.
                  </div>
                </SelectContent>
              </Select>
            </div>
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
                  v-for="(procedure, index) in createForm.procedures"
                  :key="`create-procedure-${index}`"
                  class="grid grid-cols-12 gap-3 items-end border rounded-lg p-3 bg-gray-50 dark:bg-gray-800/40"
                >
                  <div class="col-span-7">
                    <Label class="text-sm text-gray-600 dark:text-gray-300">Procedure</Label>
                    <Select
                      :model-value="procedure.name"
                      @update:modelValue="(value) => updateCreateProcedureField(index, 'name', value)"
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select or enter procedure" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="type in props.appointmentTypes" :key="type" :value="type">{{ type }}</SelectItem>
                      </SelectContent>
                    </Select>
                    
                  </div>
                  <div class="col-span-4">
                    <Label class="text-sm text-gray-600 dark:text-gray-300">Cost (UGX)</Label>
                    <Input
                      :model-value="procedure.cost"
                      type="number"
                      placeholder="0.00"
                      step="0.01"
                      min="0"
                      @update:modelValue="(value) => updateCreateProcedureField(index, 'cost', value === '' ? '' : Number(value))"
                    />
                  </div>
                  <div class="col-span-1 flex items-center justify-end">
                    <Button type="button" variant="ghost" size="icon" class="text-red-500" @click="removeProcedureRow(index)">
                      <i class="fas fa-trash"></i>
                    </Button>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-800 rounded-md px-3 py-2 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Total Cost</span>
                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ formatUGX(createProceduresTotal) }}</span>
              </div>
            </div>
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label class="text-lg font-medium">Prescriptions</Label>
                <Button type="button" variant="outline" size="sm" @click="createForm.prescriptions.push({ id: null, inventory_item_id: null, dosage: '', quantity: 1, prescription_amount: 0, medication: '' })">
                  <Plus class="w-4 h-4 mr-2" />Add Prescription
                </Button>
              </div>
              <div v-for="(prescription, index) in createForm.prescriptions" :key="index" class="grid grid-cols-12 gap-2 items-end">
                <div class="col-span-4">
                  <Select
                    :model-value="prescription.inventory_item_id"
                    @update:modelValue="(value) => { prescription.inventory_item_id = value; updatePrescriptionAmount(prescription, index, 'create'); }"
                  >
                    <SelectTrigger><SelectValue placeholder="Select an inventory item" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="item in props.medicines" :key="item.id" :value="item.id">
                        {{ item.name }} ({{ item.unit_price ? formatUGX(item.unit_price) : '0' }} / {{ item.unit || 'unit' }})
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="col-span-3">
                  <Input v-model="prescription.dosage" type="text" placeholder="Dosage (e.g., 500mg x2/day)" />
                </div>
                <div class="col-span-2">
                  <Input
                    v-model.number="prescription.quantity"
                    type="number"
                    min="1"
                    placeholder="Qty"
                    @update:modelValue="(value) => { prescription.quantity = Number(value || 1); updatePrescriptionAmount(prescription, index, 'create'); }"
                  />
                </div>
                <div class="col-span-2">
                  <Input v-model="prescription.prescription_amount" type="number" placeholder="0.00" step="0.01" min="0" />
                </div>
                <div class="col-span-1">
                  <Button variant="destructive" size="sm" @click="createForm.prescriptions.splice(index, 1)">
                    <i class="fas fa-trash"></i>
                  </Button>
                </div>
              </div>
            </div>
            <div>
              <Label>Notes (Optional)</Label>
              <Input v-model="createForm.notes" placeholder="Additional details" />
            </div>
            <div>
              <Label>Upload File (Optional)</Label>
              <Input type="file" @change="(e) => (createForm.file = (e.target as HTMLInputElement).files?.[0] || null)" accept="image/*,.pdf" />
              <p class="text-xs text-gray-500 dark:text-gray-400">JPEG, PNG, JPG, PDF (max 10MB)</p>
            </div>
            <DialogFooter>
              <Button variant="outline" @click="isCreateOpen = false">Cancel</Button>
              <Button type="submit" :disabled="createForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white">
                <i v-if="createForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-plus mr-2"></i>
                {{ createForm.processing ? 'Creating...' : 'Create' }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog :open="isPaymentOpen" @update:open="val => isPaymentOpen = val">
        <DialogContent class="max-w-md">
          <DialogHeader>
            <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Record Payment</DialogTitle>
            <DialogDescription>Add a payment to this invoice</DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submitPayment" class="space-y-4">
            <div>
              <Label>Amount (UGX)</Label>
              <Input v-model.number="paymentForm.amount" type="number" step="0.01" min="0.01" required />
              <p v-if="payingTreatment?.invoice?.balance" class="text-xs text-gray-500 mt-1">Balance: {{ formatUGX(Number(payingTreatment.invoice.balance || 0)) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <Label>Method</Label>
                <Select v-model="paymentForm.method">
                  <SelectTrigger class="h-10">
                    <SelectValue placeholder="Select method" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cash">Cash</SelectItem>
                    <SelectItem value="card">Card</SelectItem>
                    <SelectItem value="mobile_money">Mobile Money</SelectItem>
                    <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Date</Label>
                <Input v-model="paymentForm.received_at" type="date" />
              </div>
            </div>
            <div>
              <Label>Reference</Label>
              <Input v-model="paymentForm.reference" placeholder="Txn / receipt no." />
            </div>
            <div>
              <Label>Notes</Label>
              <Input v-model="paymentForm.notes" placeholder="Optional notes" />
            </div>
            <DialogFooter>
              <Button variant="outline" @click="isPaymentOpen = false">Cancel</Button>
              <Button type="submit" :disabled="paymentForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white">
                <i v-if="paymentForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-check mr-2"></i>
                {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog :open="isEditOpen" @update:open="val => isEditOpen = val">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Edit Treatment</DialogTitle>
            <DialogDescription>Update treatment details</DialogDescription>
          </DialogHeader>
          <form @submit.prevent="submitEdit" class="space-y-4">
            <div>
              <Label>Patient</Label>
              <Select v-model="editForm.patient_id">
                <SelectTrigger><SelectValue placeholder="Select a patient" /></SelectTrigger>
                <SelectContent>
                  <div class="p-2">
                    <Input
                      v-model="patientSearchQuery"
                      type="text"
                      placeholder="Search patients..."
                      class="h-9"
                      autocomplete="off"
                      @keydown.stop
                      @keyup.stop
                      @keypress.stop
                    />
                  </div>
                  <template v-if="filteredPatientsForSelect.length">
                    <SelectItem v-for="patient in filteredPatientsForSelect" :key="patient.id" :value="patient.id">
                      {{ patient.name }} ({{ patient.email }})
                    </SelectItem>
                  </template>
                  <div v-else class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    No patients found.
                  </div>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <div>
                  <Label class="text-lg font-medium">Procedures</Label>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Update the procedures for this treatment.</p>
                </div>
                <Button type="button" variant="outline" size="sm" @click="addEditProcedureRow">
                  <Plus class="w-4 h-4 mr-2" />Add Procedure
                </Button>
              </div>

              <div class="space-y-3">
                <div
                  v-for="(procedure, index) in editForm.procedures"
                  :key="`edit-procedure-${index}`"
                  class="grid grid-cols-12 gap-3 items-end border rounded-lg p-3 bg-gray-50 dark:bg-gray-800/40"
                >
                  <div class="col-span-7">
                    <Label class="text-sm text-gray-600 dark:text-gray-300">Procedure</Label>
                    <Select
                      :model-value="procedure.name"
                      @update:modelValue="(value) => updateEditProcedureField(index, 'name', value)"
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select or enter procedure" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="type in props.appointmentTypes" :key="type" :value="type">{{ type }}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="col-span-4">
                    <Label class="text-sm text-gray-600 dark:text-gray-300">Cost (UGX)</Label>
                    <Input
                      :model-value="procedure.cost"
                      type="number"
                      placeholder="0.00"
                      step="0.01"
                      min="0"
                      @update:modelValue="(value) => updateEditProcedureField(index, 'cost', value === '' ? '' : Number(value))"
                    />
                  </div>
                  <div class="col-span-1 flex items-center justify-end">
                    <Button type="button" variant="ghost" size="icon" class="text-red-500" @click="removeEditProcedureRow(index)">
                      <i class="fas fa-trash"></i>
                    </Button>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-800 rounded-md px-3 py-2 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Total Cost</span>
                <span class="font-semibold text-blue-600 dark:text-blue-400">{{ formatUGX(editProceduresTotal) }}</span>
              </div>
            </div>
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label class="text-lg font-medium">Prescriptions</Label>
                <Button type="button" variant="outline" size="sm" @click="editForm.prescriptions.push({ id: null, inventory_item_id: null, dosage: '', quantity: 1, prescription_amount: 0, medication: '' })">
                  <Plus class="w-4 h-4 mr-2" />Add Prescription
                </Button>
              </div>
              <div v-for="(prescription, index) in editForm.prescriptions" :key="index" class="grid grid-cols-12 gap-2 items-end">
                <input type="hidden" v-model="prescription.id" />
                <div class="col-span-4">
                  <Select
                    :model-value="prescription.inventory_item_id"
                    @update:modelValue="(value) => { prescription.inventory_item_id = value; updatePrescriptionAmount(prescription, index, 'edit'); }"
                  >
                    <SelectTrigger><SelectValue placeholder="Select an inventory item" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="item in props.medicines" :key="item.id" :value="item.id">
                        {{ item.name }} ({{ item.unit_price ? formatUGX(item.unit_price) : '0' }} / {{ item.unit || 'unit' }})
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="col-span-3">
                  <Input v-model="prescription.dosage" type="text" placeholder="Dosage" />
                </div>
                <div class="col-span-2">
                  <Input
                    v-model.number="prescription.quantity"
                    type="number"
                    min="1"
                    placeholder="Qty"
                    @update:modelValue="(value) => { prescription.quantity = Number(value || 1); updatePrescriptionAmount(prescription, index, 'edit'); }"
                  />
                </div>
                <div class="col-span-2">
                  <Input v-model="prescription.prescription_amount" type="number" placeholder="0.00" step="0.01" min="0" />
                </div>
                <div class="col-span-1">
                  <Button variant="destructive" size="sm" @click="editForm.prescriptions.splice(index, 1)">
                    <i class="fas fa-trash"></i>
                  </Button>
                </div>
              </div>
            </div>
            <div>
              <Label>Notes (Optional)</Label>
              <Input v-model="editForm.notes" placeholder="Additional details" />
            </div>
            <div>
              <Label>Upload File (Optional)</Label>
              <Input type="file" @change="(e) => (editForm.file = (e.target as HTMLInputElement).files?.[0] || null)" accept="image/*,.pdf" />
              <p class="text-xs text-gray-500 dark:text-gray-400">JPEG, PNG, JPG, PDF (max 10MB)</p>
            </div>
            <DialogFooter>
              <Button variant="outline" @click="isEditOpen = false">Cancel</Button>
              <Button type="submit" :disabled="editForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white">
                <i v-if="editForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-save mr-2"></i>
                {{ editForm.processing ? 'Saving...' : 'Save' }}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog :open="isDeleteOpen" @update:open="val => isDeleteOpen = val">
        <DialogContent class="max-w-md">
          <DialogHeader>
            <DialogTitle class="text-xl font-bold text-red-600">Delete Treatment</DialogTitle>
            <DialogDescription>This action cannot be undone.</DialogDescription>
          </DialogHeader>
          <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
            <p class="font-medium text-red-800 dark:text-red-200">Delete "{{ editingTreatment?.procedure }}"?</p>
            <p class="text-sm text-red-600 dark:text-red-400">This will permanently remove the treatment record.</p>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="isDeleteOpen = false">Cancel</Button>
            <Button variant="destructive" @click="confirmDelete">Delete</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog :open="isViewOpen" @update:open="val => isViewOpen = val">
        <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Treatment Details</DialogTitle>
            <DialogDescription>View complete treatment information</DialogDescription>
          </DialogHeader>
          <div v-if="viewingTreatment" class="space-y-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg space-y-3">
              <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-lg bg-blue-500 flex items-center justify-center">
                  <i :class="[getTreatmentIcon(((viewingTreatment as any).procedures?.[0]?.name || viewingTreatment.procedure || '')), 'text-white text-lg']"></i>
                </div>
                <div class="space-y-1">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Treatment Procedures</h3>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    Recorded on: {{ viewingTreatment.created_at ? new Date(viewingTreatment.created_at).toLocaleString() : 'N/A' }}
                  </div>
                </div>
              </div>

              <div class="space-y-2">
                <div
                  v-if="(viewingTreatment as any).procedures?.length"
                  class="space-y-2"
                >
                  <div
                    v-for="(procedure, index) in (viewingTreatment as any).procedures"
                    :key="`viewing-procedure-${index}`"
                    class="flex items-center justify-between bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 rounded-md px-3 py-2"
                  >
                    <div class="flex items-center gap-3">
                      <span class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                        <i :class="[getTreatmentIcon(procedure.name || ''), 'text-blue-500 dark:text-blue-300']"></i>
                      </span>
                      <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ procedure.name }}</p>
                      </div>
                    </div>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ formatUGX(Number(procedure.cost || 0)) }}</span>
                  </div>
                </div>
                <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                  {{ viewingTreatment.procedure || 'No procedures recorded.' }}
                </div>
                <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-700/60 rounded-md px-3 py-2 text-sm font-semibold">
                  <span>Total Cost</span>
                  <span class="text-blue-600 dark:text-blue-300">{{ formatUGX(Number(viewingTreatment.cost || 0)) }}</span>
                </div>
              </div>
            </div>
            <div>
              <h4 class="text-lg font-medium text-gray-900 dark:text-white">Cost Breakdown</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                  <div class="flex justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Procedure</span>
                    <span class="font-medium text-green-600">{{ formatUGX(viewingTreatment.cost) }}</span>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ viewingTreatment.procedure }}</p>
                </div>
                <div v-if="viewingTreatment.prescriptions?.length" class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                  <div class="flex justify-between">
                    <span class="text-gray-700 dark:text-gray-300">Prescriptions</span>
                    <span class="font-medium text-green-600">{{ formatUGX(viewingTreatment.prescriptions.reduce((acc, p) => acc + Number(p.prescription_amount || 0), 0)) }}</span>
                  </div>
                  <div v-for="prescription in viewingTreatment.prescriptions" :key="prescription.id || prescription.medicine_id" class="mt-2">
                    <div class="flex justify-between">
                      <span class="text-gray-600 dark:text-gray-400">{{ prescription.medication || prescription.medicine?.medicine_name || prescription.medicine_id || 'Prescription' }}</span>
                      <span class="text-sm">{{ formatUGX(prescription.prescription_amount) }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-span-full pt-4 border-t">
                  <div class="flex justify-between font-bold text-lg text-red-600">
                    <span>Total Cost</span>
                    <span>{{ formatUGX(calculateTotalCost(viewingTreatment)) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <Card v-if="viewingTreatment.notes">
              <CardHeader>
                <CardTitle class="text-lg flex items-center">
                  <FileText class="w-5 h-5 mr-2 text-purple-600" />Notes
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p class="text-gray-700 dark:text-gray-300">{{ viewingTreatment.notes }}</p>
              </CardContent>
            </Card>
            <Card v-if="viewingTreatment.file_path">
              <CardHeader>
                <CardTitle class="text-lg flex items-center">
                  <Upload class="w-5 h-5 mr-2 text-orange-600" />Attachment
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div class="flex items-center gap-3 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                  <i class="fas fa-file text-orange-600"></i>
                  <div>
                    <p class="font-medium text-gray-900 dark:text-white">Attachment Available</p>
                    <Button size="sm" variant="outline" class="mt-2">
                      <i class="fas fa-download mr-2"></i>Download
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card v-if="viewingTreatment.invoice" class="mt-2">
              <CardHeader>
                <CardTitle class="text-lg flex items-center">Payment Summary</CardTitle>
                <CardDescription>
                  Status: 
                  <Badge :class="{
                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': viewingTreatment.invoice.payment_status === 'paid',
                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': viewingTreatment.invoice.payment_status === 'partial',
                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': viewingTreatment.invoice.payment_status === 'pending',
                  }">
                    {{ viewingTreatment.invoice.payment_status === 'paid' ? 'Paid' : viewingTreatment.invoice.payment_status === 'partial' ? 'Partially Paid' : 'Pending' }}
                  </Badge>
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                  <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xs text-gray-500">Amount</div>
                    <div class="font-semibold text-red-600">{{ formatUGX(Number(viewingTreatment.invoice.amount || 0)) }}</div>
                  </div>
                  <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xs text-gray-500">Paid</div>
                    <div class="font-semibold text-green-600">{{ formatUGX(Number(viewingTreatment.invoice.paid_total || 0)) }}</div>
                  </div>
                  <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                    <div class="text-xs text-gray-500">Balance</div>
                    <div :class="['font-semibold', Number(viewingTreatment.invoice.balance || 0) > 0 ? 'text-amber-600' : 'text-green-600']">
                      {{ formatUGX(Number(viewingTreatment.invoice.balance || 0)) }}
                    </div>
                  </div>
                </div>

                <div v-if="(viewingTreatment.invoice as any).payments?.length" class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                    <thead>
                      <tr>
                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-300">Date</th>
                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-300">Method</th>
                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-300">Reference</th>
                        <th class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">Amount</th>
                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-300">Receipt</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                      <tr v-for="p in (viewingTreatment.invoice as any).payments" :key="p.id">
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ p.received_at ? new Date(p.received_at).toLocaleDateString() : '—' }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ p.method || '—' }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ p.reference || '—' }}</td>
                        <td class="px-3 py-2 text-green-600 text-right">{{ formatUGX(Number(p.amount || 0)) }}</td>
                        <td class="px-3 py-2"><a :href="route('invoices.payments.receipt', [viewingTreatment.invoice.id, p.id])" class="text-blue-600 hover:underline">Receipt</a></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-sm text-gray-600 dark:text-gray-400">No payments recorded yet.</div>
              </CardContent>
            </Card>
          </div>
          <DialogFooter>
            <Button variant="outline" @click="isViewOpen = false">Close</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Create Invoice Preview / Confirmation Dialog -->
      <Dialog :open="isInvoicePreviewOpen" @update:open="val => isInvoicePreviewOpen = val">
        <DialogContent class="max-w-lg">
          <DialogHeader>
            <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Create Invoice</DialogTitle>
            <DialogDescription>
              This combines every procedure and prescription recorded on this treatment into a single invoice.
            </DialogDescription>
          </DialogHeader>

          <div v-if="invoicingTreatment" class="space-y-4">
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Patient</p>
              <p class="text-base font-semibold text-gray-900 dark:text-white">{{ invoicingTreatment.patient?.name || 'N/A' }}</p>
            </div>

            <div>
              <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                <Stethoscope class="w-4 h-4 text-blue-500" />Procedures
              </p>
              <div class="space-y-1 border rounded-lg divide-y divide-gray-100 dark:divide-gray-800 dark:border-gray-700">
                <div
                  v-for="(procedure, index) in invoicePreviewProcedures"
                  :key="`invoice-preview-procedure-${index}`"
                  class="flex justify-between text-sm px-3 py-2"
                >
                  <span class="text-gray-700 dark:text-gray-200">{{ procedure.name }}</span>
                  <span class="font-medium text-gray-900 dark:text-white">{{ formatUGX(Number(procedure.cost || 0)) }}</span>
                </div>
              </div>
              <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1 px-1">
                <span>Procedures subtotal</span>
                <span>{{ formatUGX(invoicePreviewProceduresTotal) }}</span>
              </div>
            </div>

            <div v-if="invoicePreviewPrescriptions.length">
              <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                <Pill class="w-4 h-4 text-green-500" />Prescriptions
              </p>
              <div class="space-y-1 border rounded-lg divide-y divide-gray-100 dark:divide-gray-800 dark:border-gray-700">
                <div
                  v-for="prescription in invoicePreviewPrescriptions"
                  :key="prescription.id"
                  class="flex justify-between text-sm px-3 py-2"
                >
                  <span class="text-gray-700 dark:text-gray-200">
                    {{ prescription.medication || prescription.medicine?.medicine_name || 'Prescription' }}
                    <span v-if="prescription.dosage" class="text-xs text-gray-500 dark:text-gray-400">({{ prescription.dosage }})</span>
                  </span>
                  <span class="font-medium text-gray-900 dark:text-white">{{ formatUGX(Number(prescription.prescription_amount || 0)) }}</span>
                </div>
              </div>
              <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-1 px-1">
                <span>Prescriptions subtotal</span>
                <span>{{ formatUGX(invoicePreviewPrescriptionsTotal) }}</span>
              </div>
            </div>

            <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 rounded-lg px-4 py-3">
              <span class="font-semibold text-gray-800 dark:text-gray-100">Invoice Total</span>
              <span class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ formatUGX(invoicePreviewTotal) }}</span>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" @click="isInvoicePreviewOpen = false">Cancel</Button>
            <Button
              :disabled="isCreatingInvoice"
              class="bg-green-600 hover:bg-green-700 text-white"
              @click="confirmCreateInvoice"
            >
              <i v-if="isCreatingInvoice" class="fas fa-spinner fa-spin mr-2"></i>
              <FileText v-else class="w-4 h-4 mr-2" />
              {{ isCreatingInvoice ? 'Creating...' : 'Confirm & Create Invoice' }}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Cash Session Required Dialog -->
      <Dialog :open="showCashSessionDialog" @update:open="(val) => {
        showCashSessionDialog = val;
        if (!val) isPaymentOpen = false;
      }">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-lg font-semibold flex items-center gap-2">
              <AlertCircle class="h-5 w-5 text-amber-500" />
              Cash Session Required
            </DialogTitle>
            <DialogDescription class="pt-2">
              You need to open a cash drawer session before processing cash payments.
            </DialogDescription>
          </DialogHeader>
          <div class="flex justify-end gap-2 pt-4">
            <Button variant="outline" @click="showCashSessionDialog = false">
              Cancel
            </Button>
            <Button 
              @click="navigateToCashDrawer"
              class="bg-amber-600 hover:bg-amber-700 text-white"
            >
              Open Cash Drawer
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      <!-- Error Dialog -->
      <Dialog :open="showErrorDialog" @update:open="showErrorDialog = $event">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="text-lg font-semibold flex items-center gap-2">
              <AlertCircle class="h-5 w-5 text-red-500" />
              Unable to Verify Cash Drawer
            </DialogTitle>
            <DialogDescription class="pt-2">
              There was an error verifying the cash drawer status. Please open a cash session first.
            </DialogDescription>
          </DialogHeader>
          <div class="flex justify-end gap-2 pt-4">
            <Button @click="showErrorDialog = false">
              Close
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Minimal Tailwind-based styles */
</style>