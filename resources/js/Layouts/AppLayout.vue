<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { computed, ref, onMounted } from 'vue';
import { useThemeStore } from '@/Stores/theme';
import UnauthorizedModal from '@/Components/UnauthorizedModal.vue';
import { useUnauthorizedModal } from '@/Composables/useUnauthorizedModal';

const themeStore = useThemeStore();
const unauthorizedModal = useUnauthorizedModal();

const props = defineProps<{
  title: string;
}>();

interface PageProps {
  auth: {
    user: {
      name: string;
      email: string;
    } | null;
  };
  [key: string]: any;
}

const page = usePage<PageProps>();

usePage().props.jetstream ?? {}; // For Breeze dark mode compat

// Initialize theme from localStorage on component mount
onMounted(() => {
  themeStore.initTheme();
});

const navigationItems = [
  { value: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: 'fas fa-tachometer-alt' },
  { value: 'patients', label: 'Patients', href: '/patients', icon: 'fas fa-users' },
  { value: 'appointments', label: 'Appointments', href: '/appointments', icon: 'fas fa-calendar-check' },
  { value: 'treatments', label: 'Treatments', href: '/treatments', icon: 'fas fa-tooth' },
  { value: 'treatment-procedures', label: 'Treatment Procedures', href: '/admin/treatment-procedure-prices', icon: 'fas fa-folder' },
  { value: 'invoices', label: 'Billing', href: '/invoices', icon: 'fas fa-file-invoice-dollar' },
  { value: 'cash-drawer', label: 'Cash Drawer', href: '/cash-drawer', icon: 'fas fa-cash-register' },
  { value: 'admin-cash-sessions', label: 'Cash Sessions', href: '/admin/cash-sessions', icon: 'fas fa-coins' },
  { value: 'admin-treatment-procedure-prices', label: 'Treatment Prices', href: '/admin/treatment-procedure-prices', icon: 'fas fa-money-bill-wave' },
  { value: 'admin-clinical-note-templates', label: 'Clinical Note Templates', href: '/admin/clinical-note-templates', icon: 'fas fa-notes-medical' },
  { value: 'staff', label: 'Staff', href: '/staff', icon: 'fas fa-user-md' },
  { value: 'inventory', label: 'Inventory', href: '/inventory', icon: 'fas fa-boxes' },
  { value: 'expenses', label: 'Expenses', href: '/expenses', icon: 'fas fa-receipt' },
  { value: 'reports', label: 'Financial Reports', href: '/reports', icon: 'fas fa-chart-bar' },
  { value: 'consent-templates', label: 'Consent Templates', href: '/consent-templates', icon: 'fas fa-file-signature' },

];

const activeTab = computed(() => {
  // Get the base URL without query parameters
  const currentPath = page.url.split('?')[0];
  
  for (const item of navigationItems) {
    // Check if current path matches or starts with the item's href
    if (currentPath === item.href || 
        currentPath.startsWith(item.href + '/') ||
        // Special case for pagination - check if the base path matches
        (currentPath === '/patients' && item.href === '/patients')) {
      return item.value;
    }
  }
  return 'dashboard';
});

const visibleNavigationItems = computed(() => {
  const roles = ((page as any)?.props?.auth?.user?.roles || []).map((r: any) => r?.name ?? r);
  const isCashier = Array.isArray(roles) && (roles.includes('admin') || roles.includes('receptionist'));
  const isAdmin = Array.isArray(roles) && roles.includes('admin');
  return navigationItems.filter((item) => {
    if (item.value === 'cash-drawer') return isCashier;
    if (item.value === 'consent-templates') return isCashier;
    if (item.value.startsWith('admin-')) return isAdmin;
    return true;
  });
});

const visibleMainItems = computed(() => visibleNavigationItems.value.filter(i => !i.value.startsWith('admin-')))
const visibleAdminItems = computed(() => visibleNavigationItems.value.filter(i => i.value.startsWith('admin-')))

// Cash drawer banner (for cashier roles)
const showCashBanner = ref(false)
const isCashierRole = computed(() => {
  const roles = ((page as any)?.props?.auth?.user?.roles || []).map((r: any) => r?.name ?? r);
  return Array.isArray(roles) && (roles.includes('admin') || roles.includes('receptionist'))
})

onMounted(async () => {
  themeStore.initTheme();
  try {
    if (isCashierRole.value) {
      const res = await fetch(route('cash-drawer.active'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      const data = await res.json()
      showCashBanner.value = !data?.active
    }
  } catch {}
});
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Fixed Header -->
    <nav class="fixed top-0 left-0 right-0 bg-gradient-to-r from-blue-600 to-cyan-600 text-primary-foreground shadow-lg z-50 border-b border-primary-foreground/10 dark:bg-slate-900 dark:text-slate-100 dark:border-slate-800">
      <div class="flex items-center justify-between px-4 py-3">
        <!-- Logo and Title Section -->
        <div class="flex items-center space-x-3">
          <!-- Logo/Icon -->
          <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-white/1 rounded-lg backdrop-blur-sm">
            <img 
              src="/images/tooth.png" 
              alt="Tooth Logo" 
              class="h-6 w-6" 
              style="filter: invert(40%) sepia(73%) saturate(2000%) hue-rotate(200deg) brightness(90%) contrast(90%);"
            />
          </div>
          <div>
            <Link href="/dashboard" class="text-2xl font-bold hover:text-white/90 dark:hover:text-slate-200 transition-colors">
              Victoria Dental Lounge
            </Link>
            <p class="text-xs text-primary-foreground/70 dark:text-slate-300 hidden sm:block">You Smile We Smile</p>
          </div>
        </div>

        <!-- Right Section -->
        <div class="flex items-center space-x-3">
          <!-- Dark Mode Toggle -->
          <Button
            variant="ghost"
            @click="themeStore.toggleDarkMode"
            size="sm"
            class="hover:bg-white/10 dark:hover:bg-slate-800/50 transition-all duration-200"
          >
            <i :class="[
              'fas transition-all duration-200',
              themeStore.isDark
                ? 'fa-sun text-yellow-300 rotate-180'
                : 'fa-moon hover:text-yellow-300 dark:text-slate-300 dark:hover:text-yellow-300'
            ]"></i>
          </Button>

          <!-- User Menu Dropdown -->
          <div class="relative">
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button
                  variant="ghost"
                  class="hover:bg-white/10 dark:hover:bg-slate-800/50 transition-all duration-200 px-3"
                >
                  <i class="fas fa-user-md mr-2 dark:text-slate-300"></i>
                  <span class="hidden sm:inline dark:text-slate-200">{{ page.props.auth?.user?.name || 'User' }}</span>
                  <i class="fas fa-chevron-down ml-2 text-xs dark:text-slate-400"></i>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent class="w-56" align="end">
                <DropdownMenuLabel class="font-normal">
                  <div class="flex flex-col space-y-1">
                    <p class="text-sm font-medium leading-none">{{ page.props.auth?.user?.name || 'User' }}</p>
                    <p class="text-xs leading-none text-muted-foreground">
                      {{ page.props.auth?.user?.email || 'user@example.com' }}
                    </p>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                  <Link href="/profile" class="cursor-pointer">
                    <i class="fas fa-user mr-2 h-4 w-4"></i>
                    <span>Profile</span>
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                  @click="router.post(route('logout'))"
                  class="cursor-pointer text-red-600 dark:text-red-400 focus:text-red-600 dark:focus:text-red-400"
                >
                  <i class="fas fa-sign-out-alt mr-2 h-4 w-4"></i>
                  <span>Log out</span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        <!-- Mobile Menu Button -->
        <Button
          variant="ghost"
          size="sm"
          class="md:hidden hover:bg-white/10 dark:hover:bg-slate-800/50"
        >
          <i class="fas fa-bars dark:text-slate-300"></i>
        </Button>
      </div>
    </nav>

    <div class="flex pt-16">
      <!-- Fixed Sidebar -->
      <aside class="hidden md:block fixed left-0 top-16 w-64 bg-muted p-4 h-screen overflow-y-auto z-40">
        <nav class="space-y-1">
          <Link
            v-for="item in visibleMainItems"
            :key="item.value"
            :href="item.href"
            :class="[
              'flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors',
              activeTab === item.value
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground hover:bg-accent'
            ]"
          >
            <i :class="[item.icon, 'mr-2 w-4 h-4']"></i>
            {{ item.label }}
          </Link>

          <div v-if="visibleAdminItems.length" class="mt-4 mb-1 px-3 text-xs uppercase tracking-wide text-muted-foreground">Admin</div>
          <Link
            v-for="item in visibleAdminItems"
            :key="item.value"
            :href="item.href"
            :class="[
              'flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors',
              activeTab === item.value
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground hover:bg-accent'
            ]"
          >
            <i :class="[item.icon, 'mr-2 w-4 h-4']"></i>
            {{ item.label }}
          </Link>
        </nav>
      </aside>

      <!-- Main Content Area (offset for fixed sidebar) -->
      <main class="flex-1 md:ml-64 p-6">
        <slot />
      </main>
    </div>

    <!-- Global Unauthorized Modal -->
    <UnauthorizedModal
      :show="unauthorizedModal.isModalOpen.value"
      :title="unauthorizedModal.errorDetails.value.title"
      :message="unauthorizedModal.errorDetails.value.message"
      :required-role="unauthorizedModal.errorDetails.value.requiredRole"
      :resource="unauthorizedModal.errorDetails.value.resource"
      @close="unauthorizedModal.hideUnauthorizedModal"
    />
  </div>
</template>