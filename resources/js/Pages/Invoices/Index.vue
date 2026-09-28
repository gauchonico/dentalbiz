<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { formatUGX } from '@/Composables/useCurrency';
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue';
import debounce from 'lodash.debounce';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import Pagination from '@/Components/ui/Pagination.vue';
import { Receipt, Plus, FileText, CreditCard, Calendar, Search, MoreVertical, Eye, Download, Pill, AlertCircle, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/ui/checkbox/Checkbox.vue';

interface Patient {
  id: number;
  name: string;
  email: string;
  treatments?: Treatment[];
  prescriptions?: Prescription[];
}

interface Prescription {
  id: number;
  medication?: string | null;
  dosage?: string | null;
  frequency?: string | null;
  duration?: string | null;
  amount?: number | null;
  medicine?: { medicine_name?: string | null };
}

interface Treatment {
  id: number;
  procedure: string;
  cost: number;
  prescription_amount?: number;
  prescriptions?: Prescription[];
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginationMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  from?: number;
  to?: number;
}

interface Invoice {
  id: number;
  patient?: { name: string; id: number };
  amount: number;
  due_date: string;
  status: 'paid' | 'pending' | 'overdue' | 'cancelled';
  pdf_path?: string;
  treatment?: { procedure: string; id: number };
  prescription?: Prescription;
  created_at?: string;
  paid_at?: string;
  paid_total?: number | string;
  balance?: number | string;
  payments?: Array<{
    id: number;
    amount: number | string;
    method?: string | null;
    received_at?: string | null;
    reference?: string | null;
  }>;
}

interface InvoiceItemOption {
  value: string;
  type: 'treatment' | 'prescription';
  title: string;
  subtitle?: string;
  treatmentCost?: number;
  prescriptionCost?: number;
}

interface Prefill {
  patient_id: number;
  treatment_id: number | null;
  prescription_id: number | null;
  amount: number;
  due_date: string;
}

interface InvoiceFilters {
  search?: string;
  status?: string | null;
  sort_by?: 'created_at' | 'amount' | 'patient' | 'status';
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

interface Props {
  invoices: {
    data: Invoice[];
    links: PaginationLink[];
    meta?: PaginationMeta;
  };
  patients: Patient[];
  stats?: {
    total_invoices: number;
    total_revenue: number;
    pending_amount: number;
    overdue_count: number;
  };
  prefill?: Prefill | null;
  filters?: InvoiceFilters;
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? 'all');
const selectedPatient = ref<Patient | null>(null);
const sortBy = ref<'created_at' | 'amount' | 'patient' | 'status'>(props.filters?.sort_by ?? 'created_at');
const sortOrder = ref<'asc' | 'desc'>(props.filters?.sort_order ?? 'desc');
const sortOrderToggle = () => {
  sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
};
const selectedInvoiceItem = ref<string | null>(null);
const selectedPrescriptionIds = ref<number[]>([]);
const selectedTreatmentPrescriptionIds = ref<number[]>([]);
const editSelectedPrescriptionIds = ref<number[]>([]);
const editSelectedTreatmentPrescriptionIds = ref<number[]>([]);
const editSelectedTreatmentIds = ref<number[]>([]);

// Permissions: admin or receptionist can take payments
const page = usePage<any>();
const canTakePayments = computed(() => {
  const roles = (page?.props?.auth?.user?.roles || []).map((r: any) => r?.name ?? r);
  return Array.isArray(roles) && (roles.includes('admin') || roles.includes('receptionist'));
});
const canDeleteInvoices = computed(() => {
  const roles = (page?.props?.auth?.user?.roles || []).map((r: any) => r?.name ?? r);
  return Array.isArray(roles) && roles.includes('admin');
});

// Filtered invoices
const paginationLinks = computed(() => props.invoices?.links || []);
const totalInvoices = computed(() => props.invoices?.meta?.total ?? props.filters?.total ?? props.invoices?.data.length ?? 0);

const listPerPageOptions = [10, 20, 30, 50];
const listViewPerPage = ref((props.filters?.per_page ?? props.invoices?.meta?.per_page ?? 10).toString());
const currentPage = ref(props.filters?.page ?? props.invoices?.meta?.current_page ?? 1);

const debouncedFetchInvoices = debounce((overrides: Partial<InvoiceFilters> = {}) => {
  fetchInvoices(overrides);
}, 300);

watch(listViewPerPage, (value, oldValue) => {
  if (value === oldValue) return;
  const perPage = Number(value);
  if (!Number.isFinite(perPage) || perPage <= 0) return;

  currentPage.value = 1;
  fetchInvoices({ per_page: perPage, page: 1 });
});

watch(searchQuery, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchInvoices({ search: value, page: 1 });
});

watch(statusFilter, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchInvoices({ status: value, page: 1 });
});

watch(sortBy, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchInvoices({ sort_by: value, page: 1 });
});

watch(sortOrder, (value, oldValue) => {
  if (value === oldValue) return;
  currentPage.value = 1;
  debouncedFetchInvoices({ sort_order: value, page: 1 });
});

watch(() => props.filters, (value) => {
  searchQuery.value = value?.search ?? '';
  statusFilter.value = value?.status ?? 'all';
  sortBy.value = value?.sort_by ?? 'created_at';
  sortOrder.value = value?.sort_order ?? 'desc';
  currentPage.value = value?.page ?? props.invoices?.meta?.current_page ?? 1;
  if (value?.per_page && value.per_page.toString() !== listViewPerPage.value) {
    listViewPerPage.value = value.per_page.toString();
  }
}, { deep: true });

watch(() => props.invoices?.meta?.per_page, (value) => {
  if (!value) return;
  const stringValue = value.toString();
  if (stringValue !== listViewPerPage.value) {
    listViewPerPage.value = stringValue;
  }
});

function fetchInvoices(overrides: Partial<InvoiceFilters> = {}) {
  const payload: Record<string, any> = {
    search: searchQuery.value,
    status: statusFilter.value,
    sort_by: sortBy.value,
    sort_order: sortOrder.value,
    per_page: Number(listViewPerPage.value),
    page: currentPage.value,
  };

  Object.assign(payload, overrides);

  const pageNumber = Number(payload.page);
  if (!Number.isFinite(pageNumber) || pageNumber <= 0) {
    payload.page = currentPage.value;
  } else {
    payload.page = pageNumber;
    currentPage.value = pageNumber;
  }

  router.get(route('invoices.index'), payload, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

const filteredInvoices = computed(() => {
  return props.invoices?.data || [];
});

// Forms
const createForm = useForm({
  patient_id: null as number | null,
  treatment_id: null as number | null,
  prescription_ids: [] as number[],
  amount: 0,
  due_date: '',
  notes: '',
});

const editForm = useForm({
  patient_id: null as number | null,
  treatment_ids: [] as number[],
  prescription_ids: [] as number[],
  amount: '',
  due_date: '',
  status: '',
  notes: '',
});

// Modal states
const isCreateOpen = ref(false);
const isEditOpen = ref(false);
const isDeleteOpen = ref(false);
const editingInvoice = ref<Invoice | null>(null);
const isPaymentOpen = ref(false);
const showCashSessionDialog = ref(false);
const showErrorDialog = ref(false);
const payingInvoice = ref<Invoice | null>(null);

const paymentForm = useForm({
  amount: 0,
  method: '',
  received_at: new Date().toISOString().slice(0, 10),
  reference: '',
  notes: '',
});

const paymentAmountInput = ref<{ focus: () => void } | null>(null);

const isAnyModalOpen = computed(() =>
  isCreateOpen.value ||
  isEditOpen.value ||
  isDeleteOpen.value ||
  isPaymentOpen.value
);

watch(isAnyModalOpen, (open) => {
  document.body.classList.toggle('modal-open', open);
});

const openPayment = (invoice: Invoice) => {
  payingInvoice.value = invoice;
  paymentForm.reset();
  paymentForm.amount = Number(invoice.balance || 0) || Math.max(invoice.amount, 0);
  paymentForm.received_at = new Date().toISOString().slice(0, 10);
  isPaymentOpen.value = true;
  nextTick(() => {
    paymentAmountInput.value?.focus();
  });
};

const navigateToCashDrawer = () => {
  window.location.href = route('cash-drawer.index');
};

const submitPayment = async () => {
  if (!payingInvoice.value) return;
  if (paymentForm.amount <= 0) {
    alert('Amount must be greater than zero.');
    return;
  }
  const maxBalance = Number(payingInvoice.value.balance || 0);
  if (Number(paymentForm.amount) > maxBalance + 0.0001) {
    alert(`Amount cannot exceed current balance of ${formatUGX(maxBalance)}`);
    return;
  }
  
  // If method is cash, ensure an active cash session
  if (paymentForm.method === 'cash') {
    try {
      const res = await fetch(route('cash-drawer.active'), { 
        headers: { 
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        } 
      });
      
      if (!res.ok) throw new Error('Failed to verify cash drawer status');
      
      const data = await res.json();
      if (!data?.active) {
        showCashSessionDialog.value = true;
        // Wait for the dialog to be closed
        await new Promise<void>((resolve) => {
          const unwatch = watch(showCashSessionDialog, (newVal) => {
            if (!newVal) {
              unwatch();
              resolve();
            }
          });
        });
        return; // Stop execution if dialog was shown
      }
    } catch (e) {
      console.error('Error checking cash drawer status:', e);
      showErrorDialog.value = true;
      return;
    }
  }
  
  // Proceed with payment submission
  paymentForm.post(route('invoices.payments.store', payingInvoice.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      isPaymentOpen.value = false;
      payingInvoice.value = null;
      router.reload();
    },
  });
};

// Sum all payment amounts for an invoice (used to infer refunds in list view)
const sumPayments = (inv: Invoice) => {
  return (inv.payments || []).reduce((sum, p) => sum + Number(p.amount || 0), 0);
};

// Computed properties
const availableTreatments = computed(() => {
  return selectedPatient.value?.treatments || [];
});

const availablePrescriptions = computed(() => {
  return selectedPatient.value?.prescriptions || [];
});

const availableTreatmentPrescriptions = computed(() => {
  if (!createForm.treatment_id || !selectedPatient.value) return [];
  const treatment = selectedPatient.value.treatments?.find(t => t.id === createForm.treatment_id);
  return treatment?.prescriptions || [];
});

const editAvailableTreatments = computed(() => {
  return selectedPatient.value?.treatments || [];
});

const editAvailableTreatmentPrescriptions = computed(() => {
  if (!editSelectedTreatmentIds.value.length || !selectedPatient.value) return [];
  const treatments = selectedPatient.value.treatments?.filter(t => editSelectedTreatmentIds.value.includes(t.id)) || [];
  return treatments.flatMap(t => t.prescriptions || []);
});

const editAvailablePrescriptions = computed(() => {
  return selectedPatient.value?.prescriptions || [];
});

const invoiceItems = computed<InvoiceItemOption[]>(() => {
  if (!selectedPatient.value) return [];

  const items: InvoiceItemOption[] = [];

  (selectedPatient.value.treatments || []).forEach((treatment) => {
    items.push({
      value: `treatment-${treatment.id}`,
      type: 'treatment',
      title: treatment.procedure,
      treatmentCost: Number(treatment.cost) || 0,
      prescriptionCost: Number(treatment.prescription_amount) || 0,
    });
  });

  (selectedPatient.value.prescriptions || []).forEach((prescription) => {
    const prescriptionTitle = prescription.medication
      || prescription.medicine?.medicine_name
      || prescription.medicine_name
      || `Prescription #${prescription.id}`;

    const subtitleParts = [prescription.dosage, prescription.frequency].filter((part): part is string => !!part);
    items.push({
      value: `prescription-${prescription.id}`,
      type: 'prescription',
      title: prescriptionTitle,
      subtitle: subtitleParts.join(' • '),
      prescriptionCost: Number(prescription.amount) || 0,
    });
  });

  return items;
});

const selectedTreatmentOption = computed(() => {
  if (!selectedInvoiceItem.value || !selectedPatient.value) return null;
  if (!selectedInvoiceItem.value.startsWith('treatment-')) return null;
  const id = Number(selectedInvoiceItem.value.split('-')[1]);
  return selectedPatient.value.treatments?.find(t => t.id === id) || null;
});

const selectedPrescriptionOption = computed(() => {
  if (!selectedInvoiceItem.value || !selectedPatient.value) return null;
  if (!selectedInvoiceItem.value.startsWith('prescription-')) return null;
  const id = Number(selectedInvoiceItem.value.split('-')[1]);
  return selectedPatient.value.prescriptions?.find(p => p.id === id) || null;
});

const suggestedPrescriptions = computed(() => {
  if (!selectedPatient.value) return [];
  const treatmentPrescriptions = availableTreatmentPrescriptions.value;
  if (treatmentPrescriptions.length > 0) {
    return treatmentPrescriptions;
  }
  return selectedPatient.value.prescriptions || [];
});

const calculatedAmount = computed(() => {
  let total = 0;

  if (createForm.treatment_id && selectedPatient.value) {
    const treatment = selectedPatient.value.treatments?.find(t => t.id === createForm.treatment_id);
    if (treatment) {
      total += Number(treatment.cost) || 0;
      const treatmentPrescriptionTotal = availableTreatmentPrescriptions.value
        .filter(p => selectedTreatmentPrescriptionIds.value.includes(p.id))
        .reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
      total += treatmentPrescriptionTotal;
    }
  } else if (selectedPatient.value) {
    const prescriptionTotal = selectedPrescriptionIds.value.reduce((sum, id) => {
      const prescription = selectedPatient.value?.prescriptions?.find(p => p.id === id);
      return sum + (Number(prescription?.amount) || 0);
    }, 0);
    total += prescriptionTotal;
  }

  return Math.round(total);
});

const editCalculatedAmount = computed(() => {
  let total = 0;

  if (editSelectedTreatmentIds.value.length && selectedPatient.value) {
    const treatments = selectedPatient.value.treatments?.filter(t => editSelectedTreatmentIds.value.includes(t.id));
    if (treatments) {
      const treatmentTotal = treatments.reduce((sum, t) => sum + (Number(t.cost) || 0), 0);
      total += treatmentTotal;

      const treatmentPrescriptionTotal = editAvailableTreatmentPrescriptions.value
        .filter(p => editSelectedTreatmentPrescriptionIds.value.includes(p.id))
        .reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
      total += treatmentPrescriptionTotal;
    }
  }

  if (!editSelectedTreatmentIds.value.length && selectedPatient.value) {
    const prescriptionTotal = editSelectedPrescriptionIds.value.reduce((sum, id) => {
      const prescription = selectedPatient.value?.prescriptions?.find(p => p.id === id);
      return sum + (Number(prescription?.amount) || 0);
    }, 0);
    total += prescriptionTotal;
  }

  return Math.round(total);
});

const formattedAmountDisplay = computed(() => {
  return calculatedAmount.value.toLocaleString('en-US', { minimumFractionDigits: 0 });
});

const totalRevenue = computed(() => {
  return (props.invoices?.data || [])
    .filter(invoice => invoice.status === 'paid')
    .reduce((sum, invoice) => sum + invoice.amount, 0);
});

const pendingAmount = computed(() => {
  return (props.invoices?.data || [])
    .filter(invoice => invoice.status === 'pending')
    .reduce((sum, invoice) => sum + invoice.amount, 0);
});

const paginationSummary = computed(() => {
  const meta = props.invoices?.meta;
  if (meta) {
    return {
      from: meta.from ?? 0,
      to: meta.to ?? 0,
      total: meta.total ?? totalInvoices.value,
    };
  }

  if (props.filters) {
    return {
      from: props.filters.from ?? 0,
      to: props.filters.to ?? 0,
      total: props.filters.total ?? totalInvoices.value,
    };
  }

  const count = filteredInvoices.value.length;
  return {
    from: count ? 1 : 0,
    to: count,
    total: totalInvoices.value,
  };
});

const goToPage = (link: PaginationLink) => {
  if (!link.url || link.active) return;

  let page = currentPage.value;
  try {
    const url = new URL(link.url, window.location.origin);
    const pageParam = url.searchParams.get('page');
    const parsed = pageParam ? Number(pageParam) : NaN;
    if (Number.isFinite(parsed) && parsed > 0) {
      page = parsed;
    }
  } catch (error) {
    const label = formatPaginationLabel(link.label);
    const fallback = Number(label);
    if (Number.isFinite(fallback) && fallback > 0) {
      page = fallback;
    }
  }

  currentPage.value = page;
  fetchInvoices({ page });
};

// Helper functions
const getStatusColor = (status: string) => {
  switch (status) {
    case 'paid': return '#10B981';
    case 'pending': return '#F59E0B';
    case 'overdue': return '#EF4444';
    case 'cancelled': return '#6B7280';
    default: return '#6B7280';
  }
};

const getStatusBadgeVariant = (status: string) => {
  switch (status) {
    case 'paid': return 'default';
    case 'pending': return 'secondary';
    case 'overdue': return 'destructive';
    case 'cancelled': return 'outline';
    default: return 'outline';
  }
};

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const isOverdue = (dueDate: string, status: string) => {
  if (status === 'paid' || status === 'cancelled') return false;
  return new Date(dueDate) < new Date();
};

const formatCurrency = (amount: number) => {
  const whole = Math.round(amount);
  return `UGX ${whole.toLocaleString('en-US')}`;
};

// Event handlers
const openCreate = () => {
  createForm.reset();
  selectedPatient.value = null;
  selectedInvoiceItem.value = null;
  selectedPrescriptionIds.value = [];
  selectedTreatmentPrescriptionIds.value = [];
  createForm.treatment_id = null;
  (createForm as any).prescription_id = null;
  isCreateOpen.value = true;
};

const openView = (invoice: Invoice) => {
  router.visit(route('invoices.show', invoice.id));
};

const openEdit = (invoice: Invoice) => {
  editingInvoice.value = invoice;
  selectedPatient.value = props.patients.find(p => p.id === (invoice.patient?.id || 0)) || null;

  editForm.patient_id = invoice.patient?.id || null;
  editForm.treatment_ids = invoice.treatment ? [invoice.treatment.id] : [];
  editForm.prescription_ids = (invoice as any).prescription ? [(invoice as any).prescription.id] : [];
  editForm.amount = invoice.amount.toString();
  editForm.due_date = invoice.due_date.split('T')[0];
  editForm.status = invoice.status;
  editForm.notes = '';

  if (invoice.treatment && selectedPatient.value) {
    editSelectedTreatmentIds.value = [invoice.treatment.id];
    const treatmentPrescriptions = invoice.treatment.prescriptions || [];
    editSelectedTreatmentPrescriptionIds.value = treatmentPrescriptions.map(p => p.id);
  } else if (invoice.prescription && selectedPatient.value) {
    editSelectedPrescriptionIds.value = [invoice.prescription.id];
  } else {
    editSelectedPrescriptionIds.value = [];
    editSelectedTreatmentPrescriptionIds.value = [];
    editSelectedTreatmentIds.value = [];
  }

  nextTick(() => {
    isEditOpen.value = true;
    editForm.amount = editCalculatedAmount.value.toString();
  });
};

const openDelete = (invoice: Invoice) => {
  editingInvoice.value = invoice;
  isDeleteOpen.value = true;
};

const submitCreate = () => {
  createForm.amount = calculatedAmount.value;

  if (!createForm.patient_id || !createForm.amount || !createForm.due_date) {
    alert('Please fill in all required fields');
    return;
  }

  if (!selectedInvoiceItem.value && !selectedPrescriptionIds.value.length && !selectedTreatmentPrescriptionIds.value.length) {
    alert('Please select a treatment or prescription to invoice');
    return;
  }

  createForm.post(route('invoices.store'), {
    data: {
      ...createForm.data(),
      prescription_ids: createForm.treatment_id
        ? selectedTreatmentPrescriptionIds.value
        : selectedPrescriptionIds.value,
    },
    onSuccess: () => {
      createForm.reset();
      selectedPrescriptionIds.value = [];
      selectedTreatmentPrescriptionIds.value = [];
      selectedInvoiceItem.value = null;
      selectedPatient.value = null;
      isCreateOpen.value = false;
      router.visit(route('invoices.index'));
    },
  });
};

const submitEdit = () => {
  if (!editForm.patient_id || !editForm.amount || !editForm.due_date) {
    alert('Please fill in all required fields');
    return;
  }

  if (editingInvoice.value) {
    editForm.put(route('invoices.update', editingInvoice.value.id), {
      data: {
        ...editForm,
        treatment_ids: editSelectedTreatmentIds.value,
        prescription_ids: editSelectedTreatmentIds.value.length
          ? editSelectedTreatmentPrescriptionIds.value
          : editSelectedPrescriptionIds.value,
      },
      onSuccess: () => {
        editForm.reset();
        editSelectedPrescriptionIds.value = [];
        editSelectedTreatmentPrescriptionIds.value = [];
        editSelectedTreatmentIds.value = [];
        isEditOpen.value = false;
        editingInvoice.value = null;
      },
    });
  }
};

const confirmDelete = () => {
  if (editingInvoice.value) {
    router.delete(route('invoices.destroy', editingInvoice.value.id), {
      onSuccess: () => {
        isDeleteOpen.value = false;
        editingInvoice.value = null;
      },
    });
  }
};

const downloadPDF = (invoice: Invoice) => {
  if (!invoice.id) {
    console.error('Invalid invoice ID');
    return;
  }
  
  // Show loading state
  const originalText = 'Download PDF';
  const button = event?.target?.closest('button');
  if (button) {
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating...';
  }
  
  // Create a hidden iframe to handle the download
  const iframe = document.createElement('iframe');
  iframe.style.display = 'none';
  document.body.appendChild(iframe);
  
  // Set the iframe's src to trigger the download
  iframe.src = route('invoices.download', { invoice: invoice.id });
  
  // Clean up after a short delay
  setTimeout(() => {
    document.body.removeChild(iframe);
    if (button) {
      button.disabled = false;
      button.innerHTML = '<Download class="w-4 h-4 mr-2" />' + originalText;
    }
  }, 5000);
};

onMounted(() => {
  if (props.prefill) {
    selectedPatient.value = props.patients.find(p => p.id === props.prefill!.patient_id) || null;
    createForm.patient_id = props.prefill.patient_id;
    createForm.treatment_id = props.prefill.treatment_id;

    const prescriptionPrefill = (props.prefill as any).prescription_id;
    if (Array.isArray(prescriptionPrefill) && prescriptionPrefill.length > 0) {
      selectedPrescriptionIds.value = prescriptionPrefill;
    } else if (prescriptionPrefill) {
      selectedPrescriptionIds.value = [prescriptionPrefill];
    }

    createForm.due_date = props.prefill.due_date;

    nextTick(() => {
      if (props.prefill?.treatment_id) {
        selectedInvoiceItem.value = `treatment-${props.prefill.treatment_id}`;
      } else if (selectedPrescriptionIds.value.length > 0) {
        selectedInvoiceItem.value = `prescription-${selectedPrescriptionIds.value[0]}`;
      }
      createForm.amount = calculatedAmount.value;
    });

    isCreateOpen.value = true;
  }
});

watch(() => createForm.treatment_id, (newValue) => {
  if (newValue) {
    selectedPrescriptionIds.value = [];
    selectedTreatmentPrescriptionIds.value = [];
    (createForm as any).prescription_id = null;
  }
  createForm.amount = calculatedAmount.value;
});

watch(selectedTreatmentPrescriptionIds, () => {
  createForm.amount = calculatedAmount.value;
});

watch(selectedPrescriptionIds, () => {
  if (selectedPrescriptionIds.value.length > 0) {
    createForm.treatment_id = null;
  }
  createForm.amount = calculatedAmount.value;
});

watch(selectedInvoiceItem, (value) => {
  if (!value) {
    createForm.treatment_id = null;
    selectedPrescriptionIds.value = [];
    selectedTreatmentPrescriptionIds.value = [];
    (createForm as any).prescription_id = null;
    createForm.amount = calculatedAmount.value;
    return;
  }

  const [type, id] = value.split('-');
  if (type === 'treatment') {
    createForm.treatment_id = Number(id);
    selectedTreatmentPrescriptionIds.value = [];
  } else if (type === 'prescription') {
    selectedPrescriptionIds.value = [Number(id)];
  }
  createForm.amount = calculatedAmount.value;
});

watch(() => createForm.patient_id, () => {
  createForm.treatment_id = null;
  selectedPrescriptionIds.value = [];
  selectedTreatmentPrescriptionIds.value = [];
  selectedInvoiceItem.value = null;
  (createForm as any).prescription_id = null;
  createForm.amount = calculatedAmount.value;
});

watch([editSelectedTreatmentIds, editSelectedTreatmentPrescriptionIds, editSelectedPrescriptionIds], () => {
  editForm.amount = editCalculatedAmount.value.toString();
}, { immediate: true });
</script>

<template>
  <AppLayout title="Invoices">
    <Head title="Invoices" />

    <div
      class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-cyan-50 dark:from-gray-900 dark:via-gray-800 dark:to-blue-900"
      :inert="isAnyModalOpen"
    >
      <div class="container mx-auto px-4 py-8">
        <!-- Header Section -->
        <div class="mb-8">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent mb-2">
                Invoice Management
              </h1>
              <p class="text-gray-600 dark:text-gray-400 text-lg">
                Create, manage, and track patient invoices and payments
              </p>
            </div>

            <div class="flex items-center gap-3">
              <Badge variant="secondary" class="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 py-2 px-4 font-medium rounded-md shadow-sm">
                <Receipt class="w-4 h-4 mr-2" />
                {{ totalInvoices }} Total Invoices
              </Badge>

              <Button as-child variant="outline" class="shadow-sm">
                <a :href="route('invoices.payments.export')" target="_blank">
                  <i class="fas fa-file-csv w-4 h-4 mr-2"></i>
                  Export Payments
                </a>
              </Button>
              <Button
                v-if="!searchQuery && statusFilter === 'all'"
                @click.stop="openCreate"
                class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700"
              >
                <Plus class="w-4 h-4 mr-2" />
                Invoice Client
              </Button>
              

              <!-- <Button @click="openCreate" class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white shadow-lg hover:shadow-xl transition-all duration-300">
                <Plus class="w-4 h-4 mr-2" />
                Create Invoice
              </Button> -->
            </div>
          </div>
        </div>

        <!-- Stats Overview -->
        <div v-if="props.stats" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <Card class="border-0 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-green-700 dark:text-green-300 mb-1">Payments Today</p>
                  <p class="text-3xl font-bold text-green-900 dark:text-green-100 mb-1">{{ formatUGX(props.stats.payments_today || 0) }}</p>
                  <p class="text-xs text-green-600 dark:text-green-400">Collected today</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-lg">
                  <Receipt class="w-6 h-6 text-white" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-1">Payments This Month</p>
                  <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mb-1">{{ formatUGX(props.stats.payments_this_month || 0) }}</p>
                  <p class="text-xs text-blue-600 dark:text-blue-400">Month to date</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-lg">
                  <FileText class="w-6 h-6 text-white" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/20 dark:to-amber-800/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-amber-700 dark:text-amber-300 mb-1">Outstanding Total</p>
                  <p class="text-3xl font-bold text-amber-900 dark:text-amber-100 mb-1">{{ formatUGX(props.stats.outstanding_total || 0) }}</p>
                  <p class="text-xs text-amber-600 dark:text-amber-400">Awaiting payment</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg">
                  <CreditCard class="w-6 h-6 text-white" />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="border-0 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20">
            <CardContent class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-1">Overdue Invoices</p>
                  <p class="text-3xl font-bold text-red-900 dark:text-red-100 mb-1">{{ props.stats.overdue_invoices || 0 }}</p>
                  <p class="text-xs text-red-600 dark:text-red-400">Past due date</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-lg">
                  <Calendar class="w-6 h-6 text-white" />
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Main Content -->
        <Card class="border-0 shadow-xl bg-white dark:bg-gray-900">
          <CardHeader class="pb-4">
            <div>
              <CardTitle class="text-2xl text-gray-900 dark:text-white">Invoice Management</CardTitle>
              <CardDescription class="text-gray-600 dark:text-gray-400">
                View and manage all patient invoices
              </CardDescription>
            </div>
          </CardHeader>

          <CardContent>
            <div class="space-y-6">
              <div class="flex flex-col xl:flex-row gap-4 xl:items-center">
                <div class="relative flex-1 w-full">
                  <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <Input
                    v-model="searchQuery"
                    placeholder="Search invoices by patient name, invoice ID, or treatment..."
                    class="pl-10 h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700"
                  />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                  <div class="flex items-center gap-2">
                    <Label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</Label>
                    <Select v-model="statusFilter">
                      <SelectTrigger class="w-48 h-12">
                        <SelectValue placeholder="Filter by status" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem value="paid">Paid</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="overdue">Overdue</SelectItem>
                        <SelectItem value="cancelled">Cancelled</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div class="flex items-center gap-2">
                    <Label class="text-sm font-medium text-gray-700 dark:text-gray-300">Sort by:</Label>
                    <Select v-model="sortBy">
                      <SelectTrigger class="w-36 h-12">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="created_at">Created</SelectItem>
                        <SelectItem value="amount">Amount</SelectItem>
                        <SelectItem value="patient">Patient</SelectItem>
                        <SelectItem value="status">Status</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <Button
                    @click="sortOrderToggle"
                    variant="outline"
                    size="sm"
                    class="px-3"
                  >
                    <i :class="['fas', sortOrder === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down', 'text-sm']"></i>
                  </Button>

                  <div class="flex items-center gap-2">
                    <Label class="text-sm font-medium text-gray-700 dark:text-gray-300">Per page:</Label>
                    <Select v-model="listViewPerPage">
                      <SelectTrigger class="w-24 h-12">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem v-for="option in listPerPageOptions" :key="`invoice-per-page-${option}`" :value="option.toString()">
                          {{ option }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <div class="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm">
                <table class="w-full">
                  <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">INVOICE</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">PATIENT</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">TREATMENT</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">DATE</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">AMOUNT</th>
                      <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">STATUS</th>
                      <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">ACTIONS</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr
                      v-for="invoice in filteredInvoices"
                      :key="invoice.id"
                      class="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors cursor-pointer"
                      @click="openView(invoice)">
                      <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                          <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center">
                            <FileText class="text-white text-sm h-4 w-4" />
                          </div>
                          <div>
                            <p class="font-semibold text-gray-900 dark:text-white">#{{ invoice.id }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(invoice.created_at) }}</p>
                          </div>
                        </div>
                      </td>
                      <td class="px-4 py-3">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ invoice.patient?.name || 'N/A' }}</p>
                        <p v-if="invoice.patient?.email" class="text-xs text-gray-500 dark:text-gray-400">{{ invoice.patient.email }}</p>
                      </td>
                      <td class="px-4 py-3">
                        <div class="space-y-1">
                          <span v-if="invoice.treatment" class="flex items-center gap-2">
                            <i class="fas fa-tooth text-blue-400"></i>
                            {{ invoice.treatment.procedure }}
                          </span>
                          <span
                            v-if="invoice.treatment && invoice.treatment.prescriptions && invoice.treatment.prescriptions.length"
                            class="flex items-start gap-2">
                            <i class="fas fa-pills text-blue-400 mt-1"></i>
                            <span>
                              <span
                                v-for="prescription in invoice.treatment.prescriptions"
                                :key="prescription.id"
                                class="block"
                              >
                                {{ prescription.medicine?.medicine_name || prescription.medication || `Prescription #${prescription.id}` }}
                              </span>
                            </span>
                          </span>
                          <span v-if="invoice.prescription" class="flex items-center gap-2">
                            <i class="fas fa-pills text-blue-400"></i>
                            {{ invoice.prescription.medication || 'Prescription' }}
                          </span>
                          <span v-if="!invoice.treatment && !invoice.prescription" class="text-xs text-gray-500">N/A</span>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                        <div class="flex flex-col gap-1">
                          <div class="flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-gray-400" />
                            {{ invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : '—' }}
                          </div>
                          <div v-if="isOverdue(invoice.due_date, invoice.status) && invoice.status !== 'paid'" class="text-xs text-red-500 dark:text-red-400">
                            Overdue
                          </div>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-right">
                        <span class="font-semibold text-red-600 dark:text-red-400">{{ formatUGX(invoice.amount) }}</span>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex flex-col gap-1">
                          <Badge v-if="!invoice.paid_at" class="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            {{ invoice.status }}
                          </Badge>
                          <Badge v-else :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': invoice.status === 'paid',
                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': invoice.status === 'partial',
                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': invoice.status === 'overdue' || invoice.status === 'pending'
                          }">
                            {{ invoice.status === 'paid' ? 'Paid' : invoice.status === 'partial' ? 'Partially Paid' : 'Pending' }}
                            <template v-if="invoice.paid_total">
                              : {{ formatCurrency(invoice.paid_total) }}
                            </template>
                          </Badge>
                          <div v-if="invoice.paid_at" class="text-xs text-gray-500 dark:text-gray-400">
                            {{ new Date(invoice.paid_at).toLocaleDateString() }}
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
                              <DropdownMenuItem @click="openView(invoice)">
                                <Eye class="w-4 h-4 mr-2" />
                                View
                              </DropdownMenuItem>
                              <DropdownMenuItem @click="downloadPDF(invoice)">
                                <Download class="w-4 h-4 mr-2" />
                                Download PDF
                              </DropdownMenuItem>
                              <template v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'">
                                <!-- <DropdownMenuItem @click="openEdit(invoice)">
                                  <FileText class="w-4 h-4 mr-2" />
                                  Edit
                                </DropdownMenuItem> -->
                                <DropdownMenuItem @click="openPayment(invoice)" class="text-green-600 dark:text-green-400">
                                  <CreditCard class="w-4 h-4 mr-2" />
                                  Record Payment
                                </DropdownMenuItem>
                              </template>
                              <DropdownMenuItem
                                v-if="invoice.status !== 'cancelled'"
                                @click="openDelete(invoice)"
                                class="text-red-600 dark:text-red-400">
                                <Trash2 class="w-4 h-4 mr-2" />
                                Cancel
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="filteredInvoices.length === 0">
                      <td colspan="7" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center gap-4 text-gray-600 dark:text-gray-400">
                          <Receipt class="w-10 h-10 text-gray-400" />
                          <div>
                            <p class="text-xl font-semibold text-gray-900 dark:text-white">
                              {{ searchQuery || statusFilter !== 'all' ? 'No invoices found' : 'No invoices yet' }}
                            </p>
                            <p class="text-sm">
                              {{ searchQuery || statusFilter !== 'all' ? 'Try adjusting your search criteria' : 'Get started by creating your first invoice.' }}
                            </p>
                          </div>
                          <div class="flex gap-2">
                            <Button
                              v-if="!searchQuery && statusFilter === 'all'"
                              @click.stop="openCreate"
                              class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700"
                            >
                              <Plus class="w-4 h-4 mr-2" />
                              Create First Invoice
                            </Button>
                            <Button v-else @click.stop="searchQuery = ''; statusFilter = 'all'" variant="outline">
                              Clear Filters
                            </Button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-if="paginationLinks.length > 0" class="flex flex-col md:flex-row md:items-center md:justify-between items-start gap-4">
                <div class="flex-1">
                  <Pagination
                    v-if="paginationLinks.length > 1"
                    :links="paginationLinks"
                    :from="paginationSummary.from"
                    :to="paginationSummary.to"
                    :total="paginationSummary.total"
                    item-name="invoices"
                    @page-change="goToPage"
                    class="mt-2 sm:mt-0"
                  />
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                  <Label for="per-page" class="text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">Per page:</Label>
                  <Select v-model="listViewPerPage" @update:model-value="() => fetchInvoices()">
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
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Create Invoice Modal -->
    <Dialog :open="isCreateOpen" @update:open="(value) => isCreateOpen = value">
      <DialogContent class="max-w-3xl">
        <DialogHeader>
          <DialogTitle class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">
            Create New Invoice
          </DialogTitle>
          <DialogDescription class="text-gray-600 dark:text-gray-400">
            Generate an invoice for a patient's treatment or service.
          </DialogDescription>
        </DialogHeader>

        <form @submit.prevent="submitCreate" class="space-y-6">
          <div class="space-y-2">
            <Label for="patient" class="text-gray-700 dark:text-gray-300">Patient</Label>
            <Select v-model="createForm.patient_id" @update:model-value="selectedPatient = props.patients.find(p => p.id === $event) || null">
              <SelectTrigger class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700">
                <SelectValue placeholder="Select a patient" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="patient in props.patients" :key="patient.id" :value="patient.id">
                  {{ patient.name }} ({{ patient.email }})
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="space-y-2">
            <Label for="invoice-item" class="text-gray-700 dark:text-gray-300">Invoice Item</Label>
            <Select v-model="selectedInvoiceItem">
              <SelectTrigger class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700">
                <SelectValue placeholder="Select a treatment or prescription" />
              </SelectTrigger>
              <SelectContent>
                <template v-if="invoiceItems.length > 0">
                  <SelectItem v-for="item in invoiceItems" :key="item.value" :value="item.value">
                    <div class="flex flex-col text-left space-y-1">
                      <span class="font-medium">{{ item.title }}</span>
                      <span v-if="item.subtitle" class="text-xs text-gray-500 dark:text-gray-400">{{ item.subtitle }}</span>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        <template v-if="item.type === 'treatment'">
                          <div>Procedure: {{ formatCurrency(item.treatmentCost || 0) }}</div>
                          <div v-if="(item.prescriptionCost || 0) > 0">Prescription: {{ formatCurrency(item.prescriptionCost || 0) }}</div>
                        </template>
                        <template v-else>
                          <div>Prescription: {{ formatCurrency(item.prescriptionCost || 0) }}</div>
                        </template>
                      </div>
                    </div>
                  </SelectItem>
                </template>
                <SelectItem v-else disabled value="none">No billable items found</SelectItem>
              </SelectContent>
            </Select>
            <div v-if="!selectedPatient" class="text-sm text-gray-500 dark:text-gray-400 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              Select a patient to view treatments and prescriptions.
            </div>
            <div v-else-if="invoiceItems.length === 0" class="text-sm text-gray-500 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              No treatments or prescriptions available for this patient.
            </div>
            <div v-else class="space-y-4">
              <div v-if="createForm.treatment_id && availableTreatmentPrescriptions.length > 0" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-2 mb-3">
                  <Pill class="w-4 h-4 text-blue-500" />
                  <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Add prescriptions for this treatment</span>
                </div>
                <div class="space-y-3">
                  <label v-for="prescription in availableTreatmentPrescriptions" :key="prescription.id" class="flex items-start space-x-3 text-sm">
                    <Checkbox
                      :checked="selectedTreatmentPrescriptionIds.includes(prescription.id)"
                      @update:checked="(checked: boolean) => {
                        if (checked) {
                          selectedTreatmentPrescriptionIds.value = [...selectedTreatmentPrescriptionIds.value, prescription.id];
                        } else {
                          selectedTreatmentPrescriptionIds.value = selectedTreatmentPrescriptionIds.value.filter(id => id !== prescription.id);
                        }
                        createForm.amount = calculatedAmount.value;
                      }"
                    />
                    <div class="flex-1">
                      <div class="font-medium text-gray-800 dark:text-gray-100">
                        {{ prescription.medicine?.medicine_name || prescription.medication || `Prescription #${prescription.id}` }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="prescription.dosage">{{ prescription.dosage }}</span>
                        <span v-if="prescription.frequency"> • {{ prescription.frequency }}</span>
                        <span v-if="prescription.duration"> • {{ prescription.duration }}</span>
                        <span class="block">Amount: {{ formatCurrency(Number(prescription.amount || 0)) }}</span>
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              <div v-if="!createForm.treatment_id" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-2 mb-3">
                  <Pill class="w-4 h-4 text-blue-500" />
                  <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Select prescriptions to invoice</span>
                </div>
                <div v-if="availablePrescriptions.length > 0" class="space-y-3">
                  <label v-for="prescription in availablePrescriptions" :key="prescription.id" class="flex items-start space-x-3 text-sm">
                    <Checkbox
                      :checked="selectedPrescriptionIds.includes(prescription.id)"
                      @update:checked="(checked: boolean) => {
                        if (checked) {
                          selectedPrescriptionIds.value = [...selectedPrescriptionIds.value, prescription.id];
                        } else {
                          selectedPrescriptionIds.value = selectedPrescriptionIds.value.filter(id => id !== prescription.id);
                        }
                        createForm.amount = calculatedAmount.value;
                      }"
                    />
                    <div class="flex-1">
                      <div class="font-medium text-gray-800 dark:text-gray-100">
                        {{ prescription.medicine?.medicine_name || prescription.medication || `Prescription #${prescription.id}` }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="prescription.dosage">{{ prescription.dosage }}</span>
                        <span v-if="prescription.frequency"> • {{ prescription.frequency }}</span>
                        <span v-if="prescription.duration"> • {{ prescription.duration }}</span>
                        <span class="block">Amount: {{ formatCurrency(Number(prescription.amount || 0)) }}</span>
                      </div>
                    </div>
                  </label>
                </div>
                <div v-else class="text-sm text-gray-500 dark:text-gray-400">
                  No prescriptions available for this patient.
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="amount" class="text-gray-700 dark:text-gray-300">Amount (UGX) <span class="text-sm text-gray-500">(Auto-calculated)</span></Label>
              <div class="flex items-center h-12 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-700 dark:text-gray-200">
                UGX {{ formattedAmountDisplay }}
              </div>
              <input type="hidden" :value="createForm.amount" name="amount" />
              <div v-if="calculatedAmount > 0" class="text-xs text-gray-500 mt-1 space-y-1">
                <div v-if="selectedTreatmentOption">
                  <div>Treatment: {{ formatCurrency(selectedTreatmentOption.cost) }}</div>
                  <div v-if="selectedTreatmentPrescriptionIds.length > 0" class="mt-1 space-y-1">
                    <div class="text-xs text-gray-500" v-for="id in selectedTreatmentPrescriptionIds" :key="`selected-treatment-${id}`">
                      + Prescription #{{ id }}: {{ formatCurrency(Number(availableTreatmentPrescriptions.find(p => p.id === id)?.amount || 0)) }}
                    </div>
                  </div>
                </div>
                <div v-else-if="selectedPrescriptionIds.length > 0">
                  <div v-for="id in selectedPrescriptionIds" :key="`selected-${id}`" class="text-xs text-gray-500">
                    Prescription #{{ id }}: {{ formatCurrency(Number(availablePrescriptions.find(p => p.id === id)?.amount || 0)) }}
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="due_date" class="text-gray-700 dark:text-gray-300">Due Date</Label>
              <Input
                id="due_date"
                type="date"
                v-model="createForm.due_date"
                class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700"
                required
              />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="notes" class="text-gray-700 dark:text-gray-300">Notes (Optional)</Label>
            <Input
              id="notes"
              v-model="createForm.notes"
              placeholder="Additional invoice details"
              class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700"
            />
          </div>

          <DialogFooter class="gap-2">
            <Button type="button" variant="outline" @click="isCreateOpen = false">
              Cancel
            </Button>
            <Button
              type="submit"
              :disabled="createForm.processing"
              class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700"
            >
              <i v-if="createForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-plus mr-2"></i>
              {{ createForm.processing ? 'Creating...' : 'Create Invoice' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Edit Invoice Modal -->
    <Dialog :open="isEditOpen" @update:open="(value) => isEditOpen = value">
      <DialogContent class="max-w-5xl max-h-[90vh] flex flex-col">
        <DialogHeader class="flex-shrink-0">
          <DialogTitle class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">
            Edit Invoice
          </DialogTitle>
          <DialogDescription class="text-gray-600 dark:text-gray-400">
            Update the invoice information below.
          </DialogDescription>
        </DialogHeader>

        <div class="flex-1 overflow-y-auto py-4">
          <form @submit.prevent="submitEdit" class="space-y-6">
            <div class="space-y-2">
              <Label for="edit-patient" class="text-gray-700 dark:text-gray-300">Patient</Label>
              <Select v-model="editForm.patient_id" @update:model-value="selectedPatient = props.patients.find(p => p.id === $event) || null">
                <SelectTrigger class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700">
                  <SelectValue placeholder="Select a patient" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="patient in props.patients" :key="patient.id" :value="patient.id">
                    {{ patient.name }} ({{ patient.email }})
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Procedure Selection for Edit -->
            <div v-if="selectedPatient && editAvailableTreatments && editAvailableTreatments.length > 0" class="space-y-4">
              <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-2 mb-3">
                  <i class="fas fa-tooth w-4 h-4 text-blue-500"></i>
                  <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Edit procedures to invoice</span>
                </div>
                <div class="space-y-3">
                  <label v-for="treatment in editAvailableTreatments" :key="treatment.id" class="flex items-start space-x-3 text-sm">
                    <Checkbox
                      :checked="editSelectedTreatmentIds.includes(treatment.id)"
                      @update:checked="(checked: boolean) => {
                        if (checked) {
                          editSelectedTreatmentIds.value = [...editSelectedTreatmentIds.value, treatment.id];
                        } else {
                          editSelectedTreatmentIds.value = editSelectedTreatmentIds.value.filter(id => id !== treatment.id);
                        }
                      }"
                    />
                    <div class="flex-1">
                      <div class="font-medium text-gray-800 dark:text-gray-100">
                        {{ treatment.procedure }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span>Amount: {{ formatCurrency(Number(treatment.cost || 0)) }}</span>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Prescription Selection for Edit -->
            <div v-if="selectedPatient && !editSelectedTreatmentIds.length && editAvailablePrescriptions && editAvailablePrescriptions.length > 0" class="space-y-4">
              <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-2 mb-3">
                  <Pill class="w-4 h-4 text-blue-500" />
                  <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Edit prescriptions to invoice</span>
                </div>
                <div class="space-y-3">
                  <label v-for="prescription in editAvailablePrescriptions" :key="prescription.id" class="flex items-start space-x-3 text-sm">
                    <Checkbox
                      :checked="editSelectedPrescriptionIds.includes(prescription.id)"
                      @update:checked="(checked: boolean) => {
                        if (checked) {
                          editSelectedPrescriptionIds.value = [...editSelectedPrescriptionIds.value, prescription.id];
                        } else {
                          editSelectedPrescriptionIds.value = editSelectedPrescriptionIds.value.filter(id => id !== prescription.id);
                        }
                      }"
                    />
                    <div class="flex-1">
                      <div class="font-medium text-gray-800 dark:text-gray-100">
                        {{ prescription.medicine?.medicine_name || prescription.medication || `Prescription #${prescription.id}` }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="prescription.dosage">{{ prescription.dosage }}</span>
                        <span v-if="prescription.frequency"> • {{ prescription.frequency }}</span>
                        <span v-if="prescription.duration"> • {{ prescription.duration }}</span>
                        <span class="block">Amount: {{ formatCurrency(Number(prescription.amount || 0)) }}</span>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Treatment Prescriptions for Edit -->
            <div v-if="editSelectedTreatmentIds.length && editAvailableTreatmentPrescriptions && editAvailableTreatmentPrescriptions.length > 0" class="space-y-4">
              <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-2 mb-3">
                  <Pill class="w-4 h-4 text-blue-500" />
                  <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Edit prescriptions for selected procedures</span>
                </div>
                <div class="space-y-3">
                  <label v-for="prescription in editAvailableTreatmentPrescriptions" :key="prescription.id" class="flex items-start space-x-3 text-sm">
                    <Checkbox
                      :checked="editSelectedTreatmentPrescriptionIds.includes(prescription.id)"
                      @update:checked="(checked: boolean) => {
                        if (checked) {
                          editSelectedTreatmentPrescriptionIds.value = [...editSelectedTreatmentPrescriptionIds.value, prescription.id];
                        } else {
                          editSelectedTreatmentPrescriptionIds.value = editSelectedTreatmentPrescriptionIds.value.filter(id => id !== prescription.id);
                        }
                      }"
                    />
                    <div class="flex-1">
                      <div class="font-medium text-gray-800 dark:text-gray-100">
                        {{ prescription.medicine?.medicine_name || prescription.medication || `Prescription #${prescription.id}` }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="prescription.dosage">{{ prescription.dosage }}</span>
                        <span v-if="prescription.frequency"> • {{ prescription.frequency }}</span>
                        <span v-if="prescription.duration"> • {{ prescription.duration }}</span>
                        <span class="block">Amount: {{ formatCurrency(Number(prescription.amount || 0)) }}</span>
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="edit-amount" class="text-gray-700 dark:text-gray-300">Amount (UGX) <span class="text-sm text-gray-500">(Auto-calculated)</span></Label>
                <div class="flex items-center h-12 px-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md text-gray-700 dark:text-gray-200">
                  UGX {{ editCalculatedAmount.toLocaleString('en-US', { minimumFractionDigits: 0 }) }}
                </div>
                <input type="hidden" :value="editForm.amount" name="amount" />
                <div v-if="parseFloat(editForm.amount) > 0" class="text-xs text-gray-500 mt-1 space-y-1">
                  <div v-if="editSelectedTreatmentIds.length > 0">
                    <div v-for="id in editSelectedTreatmentIds" :key="`edit-treatment-${id}`">
                      Treatment: {{ formatCurrency(Number(editAvailableTreatments.find(t => t.id === id)?.cost || 0)) }}
                    </div>
                    <div v-if="editSelectedTreatmentPrescriptionIds.length > 0" class="mt-1 space-y-1">
                      <div v-for="id in editSelectedTreatmentPrescriptionIds" :key="`edit-treatment-prescription-${id}`" class="text-xs text-gray-500">
                        + Prescription #{{ id }}: {{ formatCurrency(Number(editAvailableTreatmentPrescriptions.find(p => p.id === id)?.amount || 0)) }}
                      </div>
                    </div>
                  </div>
                  <div v-else-if="editSelectedPrescriptionIds.length > 0">
                    <div v-for="id in editSelectedPrescriptionIds" :key="`edit-prescription-${id}`" class="text-xs text-gray-500">
                      Prescription #{{ id }}: {{ formatCurrency(Number(editAvailablePrescriptions.find(p => p.id === id)?.amount || 0)) }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-2">
                <Label for="edit-due_date" class="text-gray-700 dark:text-gray-300">Due Date</Label>
                <Input
                  id="edit-due_date"
                  type="date"
                  v-model="editForm.due_date"
                  class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700"
                  required
                />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="edit-status" class="text-gray-700 dark:text-gray-300">Status</Label>
              <Select v-model="editForm.status">
                <SelectTrigger class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700">
                  <SelectValue placeholder="Invoice status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="paid">Paid</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="overdue">Overdue</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="edit-notes" class="text-gray-700 dark:text-gray-300">Notes (Optional)</Label>
              <Input
                id="edit-notes"
                v-model="editForm.notes"
                placeholder="Additional invoice details"
                class="h-12 bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700"
              />
            </div>

            <DialogFooter class="gap-2 flex-shrink-0">
              <Button type="button" variant="outline" @click="isEditOpen = false">
                Cancel
              </Button>
              <Button
                type="submit"
                :disabled="editForm.processing"
                class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700"
              >
                <i v-if="editForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
                <i v-else class="fas fa-save mr-2"></i>
                {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
              </Button>
            </DialogFooter>
          </form>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Cash Session Required Dialog -->
    <Dialog :open="showCashSessionDialog" @update:open="(val) => {
      showCashSessionDialog = val;
      if (!val) isPaymentOpen = false;
    }">
      <DialogContent class="sm:max-w-md z-[1000] transition-all duration-300 ease-in-out" :class="{ 'scale-105': showCashSessionDialog }">
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

    <!-- Record Payment Modal -->
    <Dialog :open="isPaymentOpen" @update:open="(value) => isPaymentOpen = value">
      <DialogContent class="max-w-md transition-opacity duration-300" :class="{ 'opacity-50': showCashSessionDialog }">
        <DialogHeader>
          <DialogTitle class="text-2xl font-bold text-gray-900 dark:text-white">Record Payment</DialogTitle>
          <DialogDescription>Add a payment to this invoice</DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitPayment" class="space-y-4">
          <div>
            <Label>Amount (UGX)</Label>
            <Input
              ref="paymentAmountInput"
              v-model.number="paymentForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              required
            />
            <p v-if="payingInvoice?.balance" class="text-xs text-gray-500 mt-1">Balance: {{ formatCurrency(Number(payingInvoice?.balance || 0)) }}</p>
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
            <Button type="button" variant="outline" @click="isPaymentOpen = false">Cancel</Button>
            <Button type="submit" :disabled="paymentForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white">
              <i v-if="paymentForm.processing" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-check mr-2"></i>
              {{ paymentForm.processing ? 'Saving...' : 'Save Payment' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete Modal -->
    <Dialog :open="isDeleteOpen" @update:open="(value) => isDeleteOpen = value">
      <DialogContent class="max-w-md">
        <DialogHeader>
          <DialogTitle class="text-xl font-bold text-red-600">Delete Invoice</DialogTitle>
          <DialogDescription class="text-gray-600 dark:text-gray-400">
            This action cannot be undone. This will permanently delete the invoice record.
          </DialogDescription>
        </DialogHeader>

        <div class="py-4">
          <div class="flex items-center space-x-3 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            <div>
              <p class="font-medium text-red-800 dark:text-red-200">Delete Invoice #{{ editingInvoice?.id }}?</p>
              <p class="text-sm text-red-600 dark:text-red-400">This will remove the invoice permanently.</p>
            </div>
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="isDeleteOpen = false">
            Cancel
          </Button>
          <Button
            @click="confirmDelete"
            variant="destructive"
            class="bg-red-600 hover:bg-red-700"
          >
            <i class="fas fa-trash mr-2"></i>
            Delete Invoice
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
</style>