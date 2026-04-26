<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";

defineProps<{
    canLogin?: boolean;
    //canRegister?: boolean;
}>();

const features = [
    {
        icon: "upload",
        title: "Importación SRI",
        description:
            "Importa tus documentos directamente desde el portal del SRI. Compatible con archivos .txt, .xml o .zip de compras, ventas y retenciones.",
    },
    {
        icon: "building",
        title: "Multi-empresa",
        description:
            "Administra todas tus empresas en un solo lugar. Cambia de empresa con un clic y mantén la información perfectamente separada.",
    },
    {
        icon: "calculator",
        title: "Distribución de IVA",
        description:
            "Distribución automática de bases imponibles por tarifa (0%, 5%, 8%, 12%, 15%). Sin cálculos manuales, sin errores.",
    },
    {
        icon: "receipt",
        title: "Retenciones",
        description:
            "Gestiona retenciones en compras y ventas con los códigos SRI actualizados. Genera reportes listos para declarar.",
    },
    {
        icon: "shield",
        title: "Validación de Clave de Acceso",
        description:
            "Validación automática del RUC en la clave de acceso. Solo se importan los documentos que corresponden a tu empresa.",
    },
    {
        icon: "users",
        title: "Multi-cliente",
        description:
            "Cada cliente tiene su propio espacio aislado. Perfecto para estudios contables que manejan múltiples clientes.",
    },
];

const steps = [
    {
        number: "01",
        title: "Contrata tu plan",
        description: "Crea tu cuenta en segundos.",
    },
    {
        number: "02",
        title: "Agrega tu empresa",
        description: "Ingresa el RUC y datos de tu empresa o la de tu cliente.",
    },
    {
        number: "03",
        title: "Importa desde el SRI",
        description:
            "Sube el archivo .txt, .xml o .zip exportado del portal SRI.",
    },
    {
        number: "04",
        title: "Declara con confianza",
        description:
            "Revisa, valida y exporta los datos listos para el formulario.",
    },
];
</script>

<template>
    <Head title="Declárame — Declaraciones tributarias sin complicaciones" />

    <div
        class="min-h-screen bg-white text-gray-900 transition-colors duration-300 dark:bg-gray-950 dark:text-gray-100"
    >
        <!-- Navbar -->
        <header
            class="sticky top-0 z-50 border-b border-gray-200/80 bg-white/90 backdrop-blur-md dark:border-gray-800/80 dark:bg-gray-950/90"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
            >
                <!-- Logo -->
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600"
                    >
                        <svg
                            class="h-5 w-5 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
                            />
                        </svg>
                    </div>
                    <span
                        class="text-xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        Declárame
                    </span>
                </div>

                <!-- Nav actions -->
                <div class="flex items-center gap-3">
                    <!-- Dark mode toggle -->

                    <template v-if="canLogin">
                        <template v-if="$page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                            >
                                Ir al panel
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                            >
                                Ingresar
                            </Link>
                            <!-- <Link
                                :href="route('register')"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                            >
                                Empezar gratis
                            </Link> -->
                        </template>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden px-6 py-24 sm:py-32">
            <!-- Background gradient -->
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div
                    class="absolute -top-40 left-1/2 h-150 w-150 -translate-x-1/2 rounded-full bg-emerald-500/10 blur-3xl dark:bg-emerald-400/5"
                ></div>
                <div
                    class="absolute right-0 -bottom-20 h-100 w-100 rounded-full bg-teal-500/10 blur-3xl dark:bg-teal-400/5"
                ></div>
            </div>

            <div class="mx-auto max-w-4xl text-center">
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-sm font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-400"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                    ></span>
                    Hecho para contadores ecuatorianos
                </div>

                <h1
                    class="mb-6 text-5xl leading-tight font-extrabold tracking-tight text-gray-900 sm:text-6xl dark:text-white"
                >
                    Declaraciones tributarias
                    <span class="text-emerald-600 dark:text-emerald-400"
                        >sin complicaciones</span
                    >
                </h1>

                <p
                    class="mx-auto mb-10 max-w-2xl text-xl leading-relaxed text-gray-600 dark:text-gray-400"
                >
                    Importa, organiza y valida tus documentos del SRI en
                    segundos. Compras, ventas y retenciones en un solo lugar,
                    listas para declarar.
                </p>

                <div
                    class="flex flex-col items-center justify-center gap-4 sm:flex-row"
                >
                    <!-- <Link
                        v-if="canLogin && !$page.props.auth?.user"
                        :href="route('register')"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700 hover:shadow-emerald-500/40"
                    >
                        Comenzar ahora — es gratis
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"
                            />
                        </svg>
                    </Link> -->
                    <Link
                        v-if="canLogin && $page.props.auth?.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700"
                    >
                        Ir a mi panel
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"
                            />
                        </svg>
                    </Link>
                    <a
                        href="#caracteristicas"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-8 py-4 text-base font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:text-white"
                    >
                        Ver características
                    </a>
                </div>

                <!-- Stats -->
                <div
                    class="mt-16 grid grid-cols-3 gap-8 border-t border-gray-200 pt-10 dark:border-gray-800"
                >
                    <div>
                        <div
                            class="text-3xl font-bold text-gray-900 dark:text-white"
                        >
                            100%
                        </div>
                        <div
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            Compatible SRI
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-3xl font-bold text-gray-900 dark:text-white"
                        >
                            4
                        </div>
                        <div
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            Tarifas de IVA
                        </div>
                    </div>
                    <div>
                        <div
                            class="text-3xl font-bold text-gray-900 dark:text-white"
                        >
                            ∞
                        </div>
                        <div
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            Empresas por cuenta
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section
            id="caracteristicas"
            class="bg-gray-50 px-6 py-24 dark:bg-gray-900/50"
        >
            <div class="mx-auto max-w-7xl">
                <div class="mb-16 text-center">
                    <h2
                        class="mb-4 text-4xl font-bold text-gray-900 dark:text-white"
                    >
                        Todo lo que necesitas para declarar
                    </h2>
                    <p
                        class="mx-auto max-w-2xl text-lg text-gray-600 dark:text-gray-400"
                    >
                        Diseñado específicamente para el flujo de trabajo del
                        contador ecuatoriano con el SRI.
                    </p>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="rounded-2xl border border-gray-200 bg-white p-6 transition hover:border-emerald-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800"
                    >
                        <div
                            class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40"
                        >
                            <!-- Upload icon -->
                            <svg
                                v-if="feature.icon === 'upload'"
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                                />
                            </svg>
                            <!-- Building icon -->
                            <svg
                                v-else-if="feature.icon === 'building'"
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"
                                />
                            </svg>
                            <!-- Calculator icon -->
                            <svg
                                v-else-if="feature.icon === 'calculator'"
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 3h.008v.008H8.25v-.008zm0 3h.008v.008H8.25v-.008zm3-6h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm3-6h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm-9-9h.008v.008H8.25v-.008zM12 9h.008v.008H12V9zm3.75 0h.008v.008h-.008V9zM7.5 4.5a3 3 0 10-3 3 3 3 0 003-3zM19.5 6.75a.75.75 0 00-1.5 0v.75h-.75a.75.75 0 000 1.5h.75v.75a.75.75 0 001.5 0v-.75h.75a.75.75 0 000-1.5h-.75v-.75zM3.75 18a3 3 0 116 0 3 3 0 01-6 0z"
                                />
                            </svg>
                            <!-- Receipt icon -->
                            <svg
                                v-else-if="feature.icon === 'receipt'"
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"
                                />
                            </svg>
                            <!-- Shield icon -->
                            <svg
                                v-else-if="feature.icon === 'shield'"
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
                                />
                            </svg>
                            <!-- Users icon -->
                            <svg
                                v-else-if="feature.icon === 'users'"
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-2 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ feature.title }}
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            {{ feature.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="px-6 py-24">
            <div class="mx-auto max-w-7xl">
                <div class="mb-16 text-center">
                    <h2
                        class="mb-4 text-4xl font-bold text-gray-900 dark:text-white"
                    >
                        Así de simple
                    </h2>
                    <p class="text-lg text-gray-600 dark:text-gray-400">
                        De la exportación del SRI a tu declaración en 4 pasos.
                    </p>
                </div>

                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="step in steps"
                        :key="step.number"
                        class="relative"
                    >
                        <div
                            class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600 text-lg font-bold text-white"
                        >
                            {{ step.number }}
                        </div>
                        <h3
                            class="mb-2 text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ step.title }}
                        </h3>
                        <p
                            class="text-sm leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            {{ step.description }}
                        </p>
                        <!-- Connector line -->
                        <div
                            v-if="step.number !== '04'"
                            class="absolute top-6 left-12 hidden h-0.5 w-full bg-emerald-100 lg:block dark:bg-emerald-900/40"
                        ></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Document types -->
        <section class="bg-gray-50 px-6 py-24 dark:bg-gray-900/50">
            <div class="mx-auto max-w-7xl">
                <div class="grid items-center gap-16 lg:grid-cols-2">
                    <div>
                        <h2
                            class="mb-6 text-4xl font-bold text-gray-900 dark:text-white"
                        >
                            Todos los documentos tributarios en un solo sistema
                        </h2>
                        <p
                            class="mb-8 text-lg text-gray-600 dark:text-gray-400"
                        >
                            Declárame soporta todos los tipos de comprobantes
                            reconocidos por el SRI, con validación automática de
                            la clave de acceso.
                        </p>
                        <ul class="space-y-4">
                            <li
                                v-for="item in [
                                    {
                                        label: 'Facturas de compra y venta',
                                        code: '01',
                                    },
                                    { label: 'Notas de venta', code: '02' },
                                    {
                                        label: 'Liquidaciones de compra',
                                        code: '03',
                                    },
                                    { label: 'Notas de crédito', code: '04' },
                                    { label: 'Notas de débito', code: '05' },
                                    {
                                        label: 'Comprobantes de retención',
                                        code: '07',
                                    },
                                ]"
                                :key="item.code"
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400"
                                >
                                    {{ item.code }}
                                </div>
                                <span
                                    class="text-gray-700 dark:text-gray-300"
                                    >{{ item.label }}</span
                                >
                            </li>
                        </ul>
                    </div>

                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <div class="h-3 w-3 rounded-full bg-red-400"></div>
                            <div
                                class="h-3 w-3 rounded-full bg-yellow-400"
                            ></div>
                            <div
                                class="h-3 w-3 rounded-full bg-green-400"
                            ></div>
                            <span class="ml-2 text-xs text-gray-400"
                                >compras_abril_2026.txt</span
                            >
                        </div>
                        <div class="space-y-3 font-mono text-xs">
                            <div
                                class="flex items-center gap-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20"
                            >
                                <span
                                    class="text-emerald-600 dark:text-emerald-400"
                                    >✓</span
                                >
                                <span class="text-gray-600 dark:text-gray-400"
                                    >Factura #001-001-0001234</span
                                >
                                <span
                                    class="ml-auto text-emerald-600 dark:text-emerald-400"
                                    >$1,240.00</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20"
                            >
                                <span
                                    class="text-emerald-600 dark:text-emerald-400"
                                    >✓</span
                                >
                                <span class="text-gray-600 dark:text-gray-400"
                                    >Factura #001-001-0001235</span
                                >
                                <span
                                    class="ml-auto text-emerald-600 dark:text-emerald-400"
                                    >$850.00</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-3 rounded-lg bg-yellow-50 p-3 dark:bg-yellow-900/20"
                            >
                                <span
                                    class="text-yellow-600 dark:text-yellow-400"
                                    >→</span
                                >
                                <span class="text-gray-600 dark:text-gray-400"
                                    >Factura #001-001-0001236</span
                                >
                                <span
                                    class="ml-auto text-yellow-600 dark:text-yellow-400"
                                    >retener</span
                                >
                            </div>
                            <div
                                class="flex items-center gap-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20"
                            >
                                <span
                                    class="text-emerald-600 dark:text-emerald-400"
                                    >✓</span
                                >
                                <span class="text-gray-600 dark:text-gray-400"
                                    >Factura #001-001-0001237</span
                                >
                                <span
                                    class="ml-auto text-emerald-600 dark:text-emerald-400"
                                    >$3,100.00</span
                                >
                            </div>
                            <div
                                class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700"
                            >
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-500"
                                        >3 documentos importados · 1
                                        omitido</span
                                    >
                                    <span
                                        class="font-semibold text-emerald-600 dark:text-emerald-400"
                                        >$5,190.00</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="px-6 py-24">
            <div class="mx-auto max-w-3xl text-center">
                <h2
                    class="mb-6 text-4xl font-bold text-gray-900 dark:text-white"
                >
                    Listo para simplificar tus declaraciones?
                </h2>
                <p class="mb-10 text-xl text-gray-600 dark:text-gray-400">
                    Únete a los contadores que ya usan Declárame para gestionar
                    sus obligaciones tributarias con el SRI.
                </p>
                <div
                    class="flex flex-col items-center justify-center gap-4 sm:flex-row"
                >
                    <!-- <Link
                        v-if="canLogin && !$page.props.auth?.user"
                        :href="route('register')"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700"
                    >
                        Crear cuenta gratuita
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"
                            />
                        </svg>
                    </Link> -->
                    <Link
                        v-if="canLogin && $page.props.auth?.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-500/25 transition hover:bg-emerald-700"
                    >
                        Ir a mi panel
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"
                            />
                        </svg>
                    </Link>
                    <Link
                        v-if="canLogin && !$page.props.auth?.user"
                        :href="route('login')"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-8 py-4 text-base font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:text-white"
                    >
                        Ya tengo cuenta
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-200 px-6 py-8 dark:border-gray-800">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 sm:flex-row"
            >
                <div
                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <div
                        class="flex h-6 w-6 items-center justify-center rounded bg-emerald-600"
                    >
                        <svg
                            class="h-3.5 w-3.5 text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"
                            />
                        </svg>
                    </div>
                    <span class="font-semibold text-gray-900 dark:text-white"
                        >Declárame</span
                    >
                    <span>&mdash; Sistema tributario para Ecuador</span>
                </div>
                <p class="text-sm text-gray-400 dark:text-gray-500">
                    &copy; {{ new Date().getFullYear() }} Declárame. Todos los
                    derechos reservados.
                </p>
            </div>
        </footer>
    </div>
</template>
