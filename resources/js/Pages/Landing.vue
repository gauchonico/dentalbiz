<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { ref, onMounted, computed } from 'vue';
import { useThemeStore } from '@/Stores/theme';

const themeStore = useThemeStore();
const isScrolled = ref(false);
const mobileMenuOpen = ref(false);
const activeTab = ref('all');

onMounted(() => {
  themeStore.initTheme();

  const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
  };
  window.addEventListener('scroll', handleScroll);
});

const features = [
  {
    icon: 'users',
    title: "Patient Management",
    description: "Complete patient records, medical history, treatment plans, and communication tools in one secure place.",
    highlights: ["Digital patient forms", "Medical history tracking", "Treatment plan templates"],
    color: "from-blue-500 to-cyan-500",
    category: 'clinical'
  },
  {
    icon: 'calendar',
    title: "Appointment Scheduling",
    description: "Intuitive calendar with automated reminders, waitlist management, and multi-location support.",
    highlights: ["Automated SMS/email reminders", "Online booking portal", "Resource scheduling"],
    color: "from-purple-500 to-pink-500",
    category: 'business'
  },
  {
    icon: 'bar-chart',
    title: "Billing & Analytics",
    description: "Streamlined billing, insurance claims, and comprehensive practice analytics to grow your business.",
    highlights: ["Insurance claim processing", "Revenue analytics", "Treatment case acceptance"],
    color: "from-orange-500 to-red-500",
    category: 'business'
  },
  {
    icon: 'sparkles',
    title: "Clinical Tools",
    description: "Digital charting, imaging integration, and clinical notes to enhance patient care.",
    highlights: ["Perio charting", "Image annotation", "Treatment documentation"],
    color: "from-green-500 to-emerald-500",
    category: 'clinical'
  },
  {
    icon: 'shield',
    title: "Security & Compliance",
    description: "HIPAA-compliant platform with robust security measures to protect patient data.",
    highlights: ["End-to-end encryption", "Access controls", "Audit trails"],
    color: "from-indigo-500 to-blue-500",
    category: 'clinical'
  },
  {
    icon: 'smartphone',
    title: "Mobile Access",
    description: "Full-featured mobile app for on-the-go access to patient information and practice management.",
    highlights: ["iOS and Android apps", "Offline capability", "Secure messaging"],
    color: "from-teal-500 to-cyan-500",
    category: 'business'
  }
];

const stats = [
  { value: "500+", label: "Clinics Trust Us", icon: 'zap' },
  { value: "50k+", label: "Patients Managed", icon: 'users' },
  { value: "98%", label: "Satisfaction Rate", icon: 'star' },
  { value: "24/7", label: "Support", icon: 'shield' }
];

const testimonials = [
  {
    quote: "Victoria Dental Lounge has transformed how we manage our practice. We've reduced administrative time by 40% and improved patient satisfaction significantly.",
    author: "Dr. Sarah Johnson",
    role: "Owner, Bright Smile Dental",
    rating: 5
  },
  {
    quote: "The scheduling system alone has been a game-changer. No more double bookings and our patients love the automated reminders.",
    author: "Dr. Michael Chen",
    role: "Practice Manager, City Dental",
    rating: 5
  },
  {
    quote: "Best investment we've made for our practice. The analytics help us make data-driven decisions every day.",
    author: "Dr. Emily Rodriguez",
    role: "Founder, SmileCare Clinic",
    rating: 5
  }
];

const filteredFeatures = computed(() => {
  if (activeTab.value === 'all') return features;
  return features.filter(f => f.category === activeTab.value);
});

const iconMap: Record<string, string> = {
  'users': 'fa-users',
  'calendar': 'fa-calendar-alt',
  'bar-chart': 'fa-chart-bar',
  'sparkles': 'fa-sparkles',
  'shield': 'fa-shield-alt',
  'smartphone': 'fa-mobile-alt',
  'zap': 'fa-bolt',
  'star': 'fa-star'
};
</script>

<template>
  <div :class="['min-h-screen transition-colors duration-300', themeStore.isDark ? 'dark bg-gray-950' : 'bg-gradient-to-br from-blue-50 via-white to-cyan-50']">
    <!-- Floating Header -->
    <header :class="['fixed top-0 left-0 right-0 z-50 transition-all duration-300', isScrolled ? 'bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg shadow-lg' : 'bg-transparent']">
      <div class="container mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center space-x-3">
            <!-- Logo/Icon -->
            <Link href="/" class="flex items-center space-x-3">
              <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-white/5 rounded-lg backdrop-blur-sm">
                <img 
                  src="/images/tooth.png" 
                  alt="Tooth Logo" 
                  class="h-6 w-6" 
                  style="filter: invert(40%) sepia(73%) saturate(2000%) hue-rotate(200deg) brightness(90%) contrast(90%);"
                />
              </div>
              <div>
                <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">
                  Victoria Dental Lounge
                </span>
                <p class="text-xs text-gray-600 dark:text-slate-300 hidden sm:block">You Smile We Smile</p>
              </div>
            </Link>
          </div>

          <!-- Desktop Navigation -->
          <nav class="hidden md:flex items-center space-x-8">
            <Link href="#features" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">Features</Link>
            <!-- <Link href="#pricing" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">Pricing</Link> -->
            <Link href="#testimonials" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-cyan-400 transition-colors">Testimonials</Link>
          </nav>

          <div class="flex items-center space-x-3">
            <Button variant="ghost" size="icon" @click="themeStore.toggleDarkMode" class="rounded-full">
              <i :class="['fas', themeStore.isDark ? 'fa-sun text-yellow-300' : 'fa-moon text-gray-700']"></i>
            </Button>

            <Button variant="ghost" class="hidden md:inline-flex text-gray-700 dark:text-gray-300" as-child>
              <Link href="/login">Login</Link>
            </Button>

            <!-- Signup button (hidden) -->
            <Button v-if="false" class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white shadow-lg hidden md:inline-flex" as-child>
              <Link href="/register" class="flex items-center">
                Get Started
                <i class="fas fa-arrow-right ml-2"></i>
              </Link>
            </Button>

            <Button variant="ghost" size="icon" class="md:hidden text-gray-700 dark:text-gray-300" @click="mobileMenuOpen = !mobileMenuOpen">
              <i :class="['fas', mobileMenuOpen ? 'fa-times' : 'fa-bars']"></i>
            </Button>
          </div>
        </div>

        <!-- Mobile Menu -->
        <div v-if="mobileMenuOpen" class="md:hidden mt-4 py-4 border-t border-gray-200 dark:border-gray-800">
          <nav class="flex flex-col space-y-4">
            <Link href="#features" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">Features</Link>
            <!-- <Link href="#pricing" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">Pricing</Link> -->
            <Link href="#testimonials" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">Testimonials</Link>
            <Button variant="outline" class="w-full" as-child>
              <Link href="/login">Login</Link>
            </Button>
            <!-- Mobile signup button (hidden) -->
            <Button v-if="false" class="w-full bg-gradient-to-r from-blue-600 to-cyan-600" as-child>
              <Link href="/register">Get Started</Link>
            </Button>
          </nav>
        </div>
      </div>
    </header>

    <div class="container mx-auto px-4 pt-32 pb-16">
      <!-- Hero Section -->
      <section class="text-center mb-24">
        <Badge class="mb-6 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-0 px-4 py-2">
          <i class="fas fa-sparkles mr-2"></i>
          Trusted by 500+ dental practices
        </Badge>
        
        <h1 class="text-5xl md:text-7xl font-bold mb-6 leading-tight">
          <span class="bg-gradient-to-r from-blue-600 via-cyan-600 to-teal-600 bg-clip-text text-transparent">
            Transform Your
          </span>
          <br />
          <span class="text-gray-900 dark:text-white">Dental Practice</span>
        </h1>
        
        <p class="text-xl text-gray-600 dark:text-gray-400 mb-10 max-w-3xl mx-auto leading-relaxed">
          A comprehensive practice management solution designed to help dental professionals 
          deliver exceptional patient care with efficiency and precision.
        </p>

        <div class="flex flex-col sm:flex-row justify-center gap-4 mb-16">
          <!-- Hero signup CTA (hidden) -->
          <Button v-if="false" size="lg" class="bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white shadow-xl hover:shadow-2xl transition-all duration-300 group text-lg px-8 py-6" as-child>
            <Link href="/register" class="flex items-center">
              Start Free Trial
              <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
            </Link>
          </Button>
          <Button size="lg" variant="outline" class="border-2 text-lg px-8 py-6 hover:bg-gray-50 dark:hover:bg-gray-900" as-child>
            <Link href="/demo" class="flex items-center">
              <i class="fas fa-play-circle mr-2"></i>
              Watch Demo
            </Link>
          </Button>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl mx-auto">
          <Card v-for="(stat, index) in stats" :key="index" class="border-0 shadow-xl bg-white/50 dark:bg-gray-900/50 backdrop-blur-sm hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
            <CardContent class="p-6 text-center">
              <i :class="['fas', iconMap[stat.icon], 'text-3xl mx-auto mb-3 text-blue-600 dark:text-cyan-400']"></i>
              <div class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent mb-1">
                {{ stat.value }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">{{ stat.label }}</div>
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="mb-24">
        <div class="text-center mb-16">
          <Badge class="mb-4 bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400 border-0">
            Features
          </Badge>
          <h2 class="text-4xl md:text-5xl font-bold mb-4 text-gray-900 dark:text-white">
            Everything You Need in One Platform
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Powerful tools designed specifically for modern dental practices
          </p>
        </div>

        <Tabs v-model="activeTab" class="w-full">
          <TabsList class="grid w-full max-w-md mx-auto grid-cols-3 mb-12">
            <TabsTrigger value="all">All Features</TabsTrigger>
            <TabsTrigger value="clinical">Clinical</TabsTrigger>
            <TabsTrigger value="business">Business</TabsTrigger>
          </TabsList>

          <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <Card v-for="(feature, index) in filteredFeatures" :key="index" class="border-0 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 group bg-white dark:bg-gray-900">
              <CardHeader>
                <div :class="['w-14 h-14 rounded-2xl bg-gradient-to-br', feature.color, 'flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300']">
                  <i :class="['fas', iconMap[feature.icon], 'text-2xl text-white']"></i>
                </div>
                <CardTitle class="text-2xl mb-2 text-gray-900 dark:text-white">{{ feature.title }}</CardTitle>
                <CardDescription class="text-base text-gray-600 dark:text-gray-400">
                  {{ feature.description }}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <ul class="space-y-3">
                  <li v-for="(highlight, i) in feature.highlights" :key="i" class="flex items-start text-sm">
                    <i class="fas fa-check text-green-500 mr-2 flex-shrink-0 mt-0.5"></i>
                    <span class="text-gray-700 dark:text-gray-300">{{ highlight }}</span>
                  </li>
                </ul>
              </CardContent>
            </Card>
          </div>
        </Tabs>
      </section>

      <!-- Testimonials -->
      <section id="testimonials" class="mb-24">
        <div class="text-center mb-16">
          <Badge class="mb-4 bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border-0">
            Testimonials
          </Badge>
          <h2 class="text-4xl md:text-5xl font-bold mb-4 text-gray-900 dark:text-white">
            Loved by Dental Professionals
          </h2>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          <Card v-for="(testimonial, index) in testimonials" :key="index" class="border-0 shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 bg-gradient-to-br from-white to-blue-50 dark:from-gray-900 dark:to-blue-950">
            <CardContent class="p-8">
              <div class="flex mb-4">
                <i v-for="i in testimonial.rating" :key="i" class="fas fa-star text-yellow-400"></i>
              </div>
              <p class="text-gray-700 dark:text-gray-300 mb-6 text-lg leading-relaxed">
                "{{ testimonial.quote }}"
              </p>
              <div>
                <div class="font-semibold text-gray-900 dark:text-white">{{ testimonial.author }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ testimonial.role }}</div>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="mb-16">
        <Card class="border-0 shadow-2xl bg-gradient-to-r from-blue-600 via-cyan-600 to-teal-600 text-white overflow-hidden relative">
          <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: repeating-linear-gradient(0deg, transparent, transparent 19px, white 19px, white 20px), repeating-linear-gradient(90deg, transparent, transparent 19px, white 19px, white 20px);"></div>
          </div>
          <CardContent class="p-12 md:p-16 text-center relative z-10">
            <h2 class="text-3xl md:text-5xl font-bold mb-4">
              Ready to Transform Your Practice?
            </h2>
            <p class="text-xl mb-8 max-w-2xl mx-auto opacity-90">
              Join thousands of dental professionals who trust our platform to streamline operations and focus on patient care.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
              <!-- Bottom CTA signup (hidden) -->
              <Button v-if="false" size="lg" class="bg-white text-blue-600 hover:bg-gray-100 shadow-xl text-lg px-8 py-6" as-child>
                <Link href="/register" class="flex items-center">
                  Start Free Trial
                  <i class="fas fa-arrow-right ml-2"></i>
                </Link>
              </Button>
              <Button size="lg" class="bg-white/20 border-2 border-white text-white hover:bg-white/30 hover:border-white text-lg px-8 py-6 backdrop-blur-sm" as-child>
                <Link href="/demo" class="flex items-center">
                  <i class="fas fa-calendar-check mr-2"></i>
                  Schedule a Demo
                </Link>
              </Button>
            </div>
          </CardContent>
        </Card>
      </section>

      <!-- Footer -->
      <footer class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
          <div class="grid md:grid-cols-4 gap-8 mb-8">
            <div>
              <Link href="/" class="inline-block">
                <div class="flex items-center space-x-3 mb-4">
                  <!-- Logo/Icon -->
                  <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-white/5 rounded-lg backdrop-blur-sm">
                    <i class="fa-solid fa-tooth text-xl text-blue-600 dark:text-slate-200"></i>
                  </div>
                  <div>
                    <span class="text-xl font-bold bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">
                      Victoria Dental Lounge
                    </span>
                    <p class="text-xs text-gray-600 dark:text-slate-300">You Smile We Smile</p>
                  </div>
                </div>
              </Link>
              <p class="text-gray-600 dark:text-gray-400">
                Streamlining dental practices with cutting-edge technology.
              </p>
            </div>

            <div>
              <h3 class="font-semibold mb-4 text-gray-900 dark:text-white">Product</h3>
              <ul class="space-y-2">
                <li><Link href="#features" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Features</Link></li>
                <!-- <li><Link href="#pricing" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Pricing</Link></li> -->
                <li><Link href="/demo" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Demo</Link></li>
              </ul>
            </div>

            <div>
              <h3 class="font-semibold mb-4 text-gray-900 dark:text-white">Company</h3>
              <ul class="space-y-2">
                <li><Link href="/about" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">About</Link></li>
                <li><Link href="/contact" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Contact</Link></li>
                <!-- <li><Link href="/careers" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Careers</Link></li> -->
              </ul>
            </div>

            <div>
              <h3 class="font-semibold mb-4 text-gray-900 dark:text-white">Legal</h3>
              <ul class="space-y-2">
                <!-- <li><Link href="/privacy" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Privacy</Link></li> -->
                <li><Link href="/terms" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Terms</Link></li>
                <li><Link href="/security" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-cyan-400">Security</Link></li>
              </ul>
            </div>
          </div>

          <div class="pt-8 border-t border-gray-200 dark:border-gray-800 text-center text-gray-600 dark:text-gray-400">
            <p>&copy; {{ new Date().getFullYear() }} Victoria Dental Lounge. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.bg-clip-text {
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}
</style>