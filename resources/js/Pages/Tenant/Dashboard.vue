<script setup lang="ts">
import { format, parseISO } from "date-fns";
import { es } from "date-fns/locale";
import { ref } from "vue";
import type { Subscription } from "@/types";
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Users, CreditCard, CalendarCheck, Clock, Download } from "lucide-vue-next";

interface TenantUser {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
}

defineProps<{
    tenant: { id: string; name: string };
    subscription: Subscription | null;
    stats: {
        employee_count: number;
        max_employees: number;
        slots_used_pct: number;
        days_remaining: number;
    };
    recent_employees: TenantUser[];
}>();

function formatDate(date: string) {
    return format(parseISO(date), "dd MMM yyyy", { locale: es });
}

function getDaysVariant(days: number) {
    if (days <= 0) return "destructive" as const;
    if (days <= 7) return "outline" as const;
    return "default" as const;
}

// ─── ATS Export ──────────────────────────────────────────────────────────────

const atsDialogOpen = ref(false);
const currentYear = new Date().getFullYear();
const atsYear = ref(String(currentYear));
const atsMonth = ref(String(new Date().getMonth() + 1));

const yearOptions = Array.from({ length: 10 }, (_, i) => currentYear - i);
const monthOptions = [
    { value: "1", label: "Enero" },
    { value: "2", label: "Febrero" },
    { value: "3", label: "Marzo" },
    { value: "4", label: "Abril" },
    { value: "5", label: "Mayo" },
    { value: "6", label: "Junio" },
    { value: "7", label: "Julio" },
    { value: "8", label: "Agosto" },
    { value: "9", label: "Septiembre" },
    { value: "10", label: "Octubre" },
    { value: "11", label: "Noviembre" },
    { value: "12", label: "Diciembre" },
];

function downloadAts() {
    window.location.href = route("tenant.orders.export-ats", {
        year: atsYear.value,
        month: atsMonth.value,
    });
    atsDialogOpen.value = false;
}
</script>

<template>
    <TenantLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Dashboard</h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Bienvenido al panel de {{ tenant.name }}.
                    </p>
                </div>
                <Button variant="outline" size="sm" class="font-bold" @click="atsDialogOpen = true">
                    <Download class="size-4" />
                    <span class="hidden sm:inline">Exportar ATS</span>
                </Button>
            </div>

            <!-- Alerta sin suscripción -->
            <div
                v-if="!subscription"
                class="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive"
            >
                No hay una suscripción activa. Contacta al administrador del
                sistema.
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Empleados</CardTitle
                        >
                        <Users class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ stats.employee_count }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            de {{ stats.max_employees }} permitidos
                        </p>
                        <div
                            class="mt-2 h-1.5 rounded-full bg-muted overflow-hidden"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: `${stats.slots_used_pct}%` }"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Plan</CardTitle
                        >
                        <CreditCard class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{ subscription?.plan?.name ?? "—" }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            plan activo
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Vencimiento</CardTitle
                        >
                        <CalendarCheck class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">
                            {{
                                subscription
                                    ? formatDate(subscription.end_date)
                                    : "—"
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-1">
                            fecha de vencimiento
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-2"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                            >Días restantes</CardTitle
                        >
                        <Clock class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <p class="text-3xl font-bold">
                                {{ stats.days_remaining }}
                            </p>
                            <Badge
                                :variant="getDaysVariant(stats.days_remaining)"
                                class="text-xs"
                            >
                                {{
                                    stats.days_remaining <= 0
                                        ? "Vencida"
                                        : stats.days_remaining <= 7
                                          ? "Por vencer"
                                          : "Al día"
                                }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            días restantes
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Empleados recientes -->
            <Card>
                <CardHeader>
                    <CardTitle>Empleados recientes</CardTitle>
                    <CardDescription
                        >Últimos empleados registrados en el
                        sistema.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Correo</TableHead>
                                <TableHead class="text-center"
                                    >Estado</TableHead
                                >
                                <TableHead class="text-center"
                                    >Registrado</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="employee in recent_employees"
                                :key="employee.id"
                            >
                                <TableCell class="font-medium">{{
                                    employee.name
                                }}</TableCell>
                                <TableCell
                                    class="text-muted-foreground text-sm"
                                    >{{ employee.email }}</TableCell
                                >
                                <TableCell class="text-center">
                                    <Badge
                                        :variant="
                                            employee.is_active
                                                ? 'default'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            employee.is_active
                                                ? "Activo"
                                                : "Inactivo"
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell
                                    class="text-center text-sm text-muted-foreground"
                                >
                                    {{ formatDate(employee.created_at) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="recent_employees.length === 0">
                                <TableCell
                                    colspan="4"
                                    class="text-center text-muted-foreground py-6"
                                >
                                    No hay empleados registrados.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- ATS Export dialog -->
        <Dialog v-model:open="atsDialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>Exportar ATS XML</DialogTitle>
                </DialogHeader>
                <div class="grid grid-cols-2 gap-4 py-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium">Año</label>
                        <Select v-model="atsYear">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="y in yearOptions"
                                    :key="y"
                                    :value="String(y)"
                                >
                                    {{ y }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium">Mes</label>
                        <Select v-model="atsMonth">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in monthOptions"
                                    :key="m.value"
                                    :value="m.value"
                                >
                                    {{ m.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="atsDialogOpen = false">
                        Cancelar
                    </Button>
                    <Button @click="downloadAts">
                        <Download class="size-4" />
                        Descargar XML
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
