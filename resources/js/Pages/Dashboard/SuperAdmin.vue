<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { reactive } from "vue";
import { format, parseISO, differenceInDays } from "date-fns";
import { es } from "date-fns/locale";
import type { Subscription } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import DatePicker from "@/components/DatePicker.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Label } from "@/components/ui/label";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Users,
    CreditCard,
    TrendingUp,
    AlertTriangle,
    UserCheck,
    Building2,
    History,
    CalendarRange,
    PackageCheck,
    DollarSign,
} from "lucide-vue-next";

const props = defineProps<{
    stats: {
        total_admins: number;
        total_employees: number;
        active_subscriptions: number;
        expired_subscriptions: number;
    };
    expiring_soon: Subscription[];
    revenue_by_plan: {
        name: string;
        count: number;
        revenue: number;
        price: number;
    }[];
    sales_by_plan: {
        name: string;
        count: number;
        total: number;
        price: number;
    }[];
    sales_summary: {
        total_sold: number;
        total_revenue: number;
    };
    sales_filters: {
        start_date: string | null;
        end_date: string | null;
    };
    recent_subscriptions: Subscription[];
}>();

const salesFilters = reactive({
    start_date: props.sales_filters.start_date ?? "",
    end_date: props.sales_filters.end_date ?? "",
});

function formatDate(date: string) {
    return format(parseISO(date), "dd MMM yyyy", { locale: es });
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat("es-EC", {
        style: "currency",
        currency: "USD",
    }).format(amount);
}

function getDaysRemaining(endDate: string) {
    return differenceInDays(parseISO(endDate), new Date());
}

function totalRevenue(plans: { revenue: number }[]) {
    return plans.reduce((acc, p) => acc + p.revenue, 0);
}

function applySalesFilters() {
    const params: Record<string, string> = {};

    if (salesFilters.start_date)
        params.sales_start_date = salesFilters.start_date;
    if (salesFilters.end_date) params.sales_end_date = salesFilters.end_date;

    router.get(route("dashboard"), params, {
        preserveScroll: true,
        preserveState: true,
    });
}

function clearSalesFilters() {
    salesFilters.start_date = "";
    salesFilters.end_date = "";

    router.get(
        route("dashboard"),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function salesRangeLabel() {
    if (props.sales_filters.start_date && props.sales_filters.end_date) {
        return `${formatDate(props.sales_filters.start_date)} - ${formatDate(props.sales_filters.end_date)}`;
    }

    if (props.sales_filters.start_date) {
        return `Desde ${formatDate(props.sales_filters.start_date)}`;
    }

    if (props.sales_filters.end_date) {
        return `Hasta ${formatDate(props.sales_filters.end_date)}`;
    }

    return "Todo el historial";
}
</script>

<template>
    <Head title="Panel de administración" />
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-foreground">Dashboard</h1>
                <p class="text-muted-foreground text-sm mt-1">
                    Resumen general del sistema.
                </p>
            </div>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Administradores</CardTitle
                        >
                        <UserCheck class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ stats.total_admins }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            registrados en el sistema
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Empleados</CardTitle
                        >
                        <Users class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ stats.total_employees }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            en todos los equipos
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Suscripciones activas</CardTitle
                        >
                        <CreditCard class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-3xl font-bold text-green-600 dark:text-green-400"
                        >
                            {{ stats.active_subscriptions }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            vigentes actualmente
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Suscripciones vencidas</CardTitle
                        >
                        <AlertTriangle class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-destructive">
                            {{ stats.expired_subscriptions }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            requieren atención
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Por vencer -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <AlertTriangle class="size-4 text-yellow-500" />
                            Por vencer en 7 días
                        </CardTitle>
                        <CardDescription
                            >Suscripciones que necesitan renovación
                            pronto.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="expiring_soon.length === 0"
                            class="text-sm text-muted-foreground text-center py-4"
                        >
                            No hay suscripciones por vencer.
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="sub in expiring_soon"
                                :key="sub.id"
                                class="flex items-center justify-between p-3 rounded-lg bg-muted/50 border border-border"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ sub.user?.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ sub.plan?.name }} · vence
                                        {{ formatDate(sub.end_date) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        class="text-yellow-600 border-yellow-500"
                                    >
                                        {{ getDaysRemaining(sub.end_date) }}d
                                    </Badge>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="
                                            router.visit(
                                                route(
                                                    'subscriptions.history',
                                                    sub.user_id,
                                                ),
                                            )
                                        "
                                    >
                                        <History class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ingresos por plan -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <TrendingUp class="size-4" />
                            Ingresos por plan
                        </CardTitle>
                        <CardDescription>
                            Total estimado:
                            {{ formatCurrency(totalRevenue(revenue_by_plan)) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                v-for="plan in revenue_by_plan"
                                :key="plan.name"
                                class="space-y-1"
                            >
                                <div
                                    class="flex items-center justify-between text-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <Building2
                                            class="size-3 text-muted-foreground"
                                        />
                                        <span class="font-medium">{{
                                            plan.name
                                        }}</span>
                                        <Badge variant="outline" class="text-xs"
                                            >{{ plan.count }} activos</Badge
                                        >
                                    </div>
                                    <span class="font-semibold">{{
                                        formatCurrency(plan.revenue)
                                    }}</span>
                                </div>
                                <!-- Barra de progreso -->
                                <div
                                    class="h-2 rounded-full bg-muted overflow-hidden"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{
                                            width:
                                                totalRevenue(revenue_by_plan) >
                                                0
                                                    ? `${(plan.revenue / totalRevenue(revenue_by_plan)) * 100}%`
                                                    : '0%',
                                        }"
                                    />
                                </div>
                            </div>
                            <div
                                v-if="revenue_by_plan.length === 0"
                                class="text-sm text-muted-foreground text-center py-4"
                            >
                                No hay datos de ingresos.
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!--  Planes vendidos por rango -->
            <Card>
                <CardHeader class="space-y-4">
                    <div
                        class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"
                    >
                    <div class="space-y-1 xl:max-w-sm">
                        <CardTitle class="flex items-center gap-2">
                            <CalendarRange class="size-4" />
                            Planes vendidos por rango
                        </CardTitle>
                        <CardDescription>
                            Consulta ventas por fecha de registro. 
                        </CardDescription>
                    </div>

                    <div
                        class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:max-w-3xl xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                    >
                        <div class="space-y-2">
                            <Label>Desde</Label>
                            <DatePicker
                                v-model="salesFilters.start_date"
                                placeholder="Fecha inicial"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label>Hasta</Label>
                            <DatePicker
                                v-model="salesFilters.end_date"
                                placeholder="Fecha final"
                            />
                        </div>
                        <div
                            class="flex flex-col gap-2 sm:col-span-2 sm:flex-row xl:col-span-1 xl:self-end"
                        >
                            <Button
                                class="xl:min-w-28"
                                @click="applySalesFilters"
                            >
                                Consultar
                            </Button>
                            <Button
                                variant="outline"
                                class="xl:min-w-24"
                                @click="clearSalesFilters"
                            >
                                Limpiar
                            </Button>
                        </div>
                    </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            class="rounded-lg border border-border bg-muted/30 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <PackageCheck class="size-4" />
                                Planes vendidos
                            </div>
                            <p class="text-3xl font-bold">
                                {{ sales_summary.total_sold }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-border bg-muted/30 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <DollarSign class="size-4" />
                                Total vendido
                            </div>
                            <p class="text-3xl font-bold">
                                {{
                                    formatCurrency(sales_summary.total_revenue)
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Plan</TableHead>
                                    <TableHead class="text-center">
                                        Vendidos
                                    </TableHead>
                                    <TableHead class="text-center">
                                        Precio
                                    </TableHead>
                                    <TableHead class="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="plan in sales_by_plan"
                                    :key="`sales-${plan.name}`"
                                >
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Building2
                                                class="size-4 text-muted-foreground"
                                            />
                                            <span class="font-medium">
                                                {{ plan.name }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge variant="outline">
                                            {{ plan.count }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        {{ formatCurrency(plan.price) }}
                                    </TableCell>
                                    <TableCell class="text-right font-semibold">
                                        {{ formatCurrency(plan.total) }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Suscripciones recientes -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Suscripciones recientes</CardTitle>
                        <CardDescription
                            >Últimas 5 suscripciones
                            registradas.</CardDescription
                        >
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="router.visit(route('subscriptions.index'))"
                    >
                        Ver todas
                    </Button>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Administrador</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead class="text-center"
                                    >Inicio</TableHead
                                >
                                <TableHead class="text-center"
                                    >Vencimiento</TableHead
                                >
                                <TableHead class="text-center"
                                    >Estado</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="sub in recent_subscriptions"
                                :key="sub.id"
                            >
                                <TableCell>
                                    <p class="font-medium text-sm">
                                        {{ sub.user?.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ sub.user?.email }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{
                                        sub.plan?.name
                                    }}</Badge>
                                </TableCell>
                                <TableCell class="text-center text-sm">{{
                                    formatDate(sub.start_date)
                                }}</TableCell>
                                <TableCell class="text-center text-sm">{{
                                    formatDate(sub.end_date)
                                }}</TableCell>
                                <TableCell class="text-center">
                                    <Badge
                                        :variant="
                                            sub.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            sub.is_active
                                                ? "Activa"
                                                : "Inactiva"
                                        }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="recent_subscriptions.length === 0">
                                <TableCell
                                    colspan="5"
                                    class="text-center text-muted-foreground py-6"
                                >
                                    No hay suscripciones recientes.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
