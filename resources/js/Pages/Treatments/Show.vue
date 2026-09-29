<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft } from 'lucide-vue-next';
import { formatUGX } from '@/Composables/useCurrency';
import { route } from 'ziggy-js';

interface Prescription {
  id: number;
  medicine?: { medicine_name?: string } | null;
  medication?: string | null;
  dosage?: string | null;
  frequency?: string | null;
  duration?: string | null;
  prescription_amount?: number | null;
  prescription_issue_date?: string | null;
  prescription_instructions?: string | null;
}

interface Treatment {
  id: number;
  procedure: string;
  cost: number;
  notes?: string;
  file_path?: string;
  patient: { name: string; email: string };
  appointment?: { id: number };
  prescriptions?: Prescription[];
  created_at: string;
  updated_at: string;
}

interface Props {
  treatment: Treatment;
}

const props = defineProps<Props>();
</script>

<template>
  <AppLayout :title="`Treatment: ${props.treatment.procedure}`">
    <Head :title="`Treatment: ${props.treatment.procedure}`" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <Button variant="ghost" @click="$inertia.visit(route('treatments.index'))">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Treatments
          </Button>
          <div>
            <h1 class="text-3xl font-bold">{{ props.treatment.procedure }}</h1>
            <p class="text-gray-600">Patient: {{ props.treatment.patient.name }}</p>
          </div>
        </div>
      </div>

      <!-- Treatment Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Treatment Information</CardTitle>
            <CardDescription>Procedure details and cost</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <Label class="text-sm font-medium text-gray-500">Procedure</Label>
              <p class="text-sm">{{ props.treatment.procedure }}</p>
            </div>
            <div>
              <Label class="text-sm font-medium text-gray-500">Cost</Label>
              <p class="text-sm">
                <Badge variant="secondary">{{ formatUGX(props.treatment.cost) }}</Badge>
              </p>
            </div>
            <div>
              <Label class="text-sm font-medium text-gray-500">Patient</Label>
              <p class="text-sm">{{ props.treatment.patient.name }} ({{ props.treatment.patient.email }})</p>
            </div>
            <div>
              <Label class="text-sm font-medium text-gray-500">Created</Label>
              <p class="text-sm">{{ new Date(props.treatment.created_at).toLocaleDateString() }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Additional Details</CardTitle>
            <CardDescription>Notes and attachments</CardDescription>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <Label class="text-sm font-medium text-gray-500">Notes</Label>
              <p class="text-sm">{{ props.treatment.notes || 'No notes provided' }}</p>
            </div>
            <div v-if="props.treatment.file_path">
              <Label class="text-sm font-medium text-gray-500">Attachment</Label>
              <p class="text-sm">
                <a :href="props.treatment.file_path" target="_blank" class="text-blue-600 hover:text-blue-800">
                  View attached file
                </a>
              </p>
            </div>
            <div v-if="props.treatment.appointment">
              <Label class="text-sm font-medium text-gray-500">Related Appointment</Label>
              <p class="text-sm">Appointment #{{ props.treatment.appointment.id }}</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Prescription Information -->
      <Card v-if="props.treatment?.prescriptions && props.treatment.prescriptions.length > 0" class="border-0 shadow-lg">
        <CardHeader class="pb-3">
          <CardTitle class="text-lg flex items-center">
            <i class="fas fa-pills mr-2 text-blue-600"></i>
            Prescriptions
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
          <div v-for="prescription in props.treatment.prescriptions" :key="prescription.id" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Medicine</Label>
                <p class="text-gray-900 dark:text-white font-medium">{{ prescription.medicine ? prescription.medicine.medicine_name : (prescription.medication || 'N/A') }}</p>
              </div>
              <div>
                <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Dosage</Label>
                <p class="text-gray-900 dark:text-white">{{ prescription.dosage || 'N/A' }}</p>
              </div>
              <div>
                <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Frequency</Label>
                <p class="text-gray-900 dark:text-white">{{ prescription.frequency || 'N/A' }}</p>
              </div>
              <div>
                <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</Label>
                <p class="text-gray-900 dark:text-white">{{ prescription.duration || 'N/A' }}</p>
              </div>
              <div>
                <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</Label>
                <p class="text-gray-900 dark:text-white font-medium">{{ Number(prescription.prescription_amount || 0) > 0 ? formatUGX(Number(prescription.prescription_amount || 0)) : 'N/A' }}</p>
              </div>
              <div>
                <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Issue Date</Label>
                <p class="text-gray-900 dark:text-white">{{ prescription.prescription_issue_date ? new Date(prescription.prescription_issue_date).toLocaleDateString() : 'N/A' }}</p>
              </div>
            </div>
            <div v-if="prescription.prescription_instructions" class="mt-4">
              <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Instructions</Label>
              <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ prescription.prescription_instructions }}</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
