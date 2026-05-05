<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { format, parseISO, differenceInDays } from "date-fns";
import { es } from "date-fns/locale";
import type { Subscription, Plan, User } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    ArrowLeft,
    CreditCard,
    CalendarCheck,
    CalendarX,
    Clock,
    Users,
} from "lucide-vue-next";

const props = defineProps<{
    admin: User;
    subscriptions: Subscription[];
    plans: Plan[];
}>();

function formatDate(date: string) {
    return format(parseISO(date), "dd MMM yyyy", { locale: es });
}

function getStatusInfo(sub: Subscription) {
    if (!sub.is_active)
        return {
            label: "Inactiva",
            variant: "secondary" as const,
            icon: CalendarX,
        };
    const days = differenceInDays(parseISO(sub.end_date), new Date());
    if (days < 0)
        return {
            label: "Vencida",
            variant: "destructive" as const,
            icon: CalendarX,
        };
    if (days <= 7)
        return {
            label: `${days}d restantes`,
            variant: "outline" as const,
            icon: Clock,
        };
    return {
        label: "Activa",
        variant: "default" as const,
        icon: CalendarCheck,
    };
}

const activeSubscription = props.subscriptions.find(
    (s) =>
        s.is_active && differenceInDays(parseISO(s.end_date), new Date()) >= 0,
);
</script>

<template>
    <Head title="Historial de suscripciones" />
    <AppLayout>
        <div class="space-y-6 max-w-full md:max-w-2xl xl:max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    @click="router.visit(route('subscriptions.index'))"
                >
                    <ArrowLeft class="size-4" />
                </Button>
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <CreditCard class="size-6" />
                        Historial de suscripciones
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        {{ admin.name }} — {{ admin.email }}
                    </p>
                </div>
            </div>

            <!-- Resumen suscripción activa -->
            <div
                v-if="activeSubscription"
                class="grid grid-cols-1 lg:grid-cols-3 gap-4"
            >
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm text-muted-foreground"
                            >Plan actual</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ activeSubscription.plan?.name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            ${{ activeSubscription.plan?.price }}/período
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm text-muted-foreground"
                            >Vence el</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">
                            {{ formatDate(activeSubscription.end_date) }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                differenceInDays(
                                    parseISO(activeSubscription.end_date),
                                    new Date(),
                                )
                            }}
                            días restantes
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm text-muted-foreground"
                            >Max. empleados</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold flex items-center gap-2">
                            <Users class="size-5" />
                            {{ activeSubscription.plan?.max_employees }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            permitidos en el plan
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                v-else
                class="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive"
            >
                Este administrador no tiene ninguna suscripción activa y
                vigente.
            </div>

            <!-- Historial tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Plan</TableHead>
                            <TableHead class="text-center">Inicio</TableHead>
                            <TableHead class="text-center"
                                >Vencimiento</TableHead
                            >
                            <TableHead>Notas</TableHead>
                            <TableHead class="text-center">Estado</TableHead>
                            <TableHead>Creado por</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="sub in subscriptions" :key="sub.id">
                            <TableCell>
                                <Badge variant="outline">{{
                                    sub.plan?.name
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-center text-sm">
                                {{ formatDate(sub.start_date) }}
                            </TableCell>
                            <TableCell class="text-center text-sm">
                                {{ formatDate(sub.end_date) }}
                            </TableCell>
                            <TableCell
                                class="text-sm text-muted-foreground max-w-xs truncate"
                            >
                                {{ sub.notes ?? "—" }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge
                                    :variant="getStatusInfo(sub).variant"
                                    class="gap-1"
                                >
                                    <component
                                        :is="getStatusInfo(sub).icon"
                                        class="size-3"
                                    />
                                    {{ getStatusInfo(sub).label }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ sub.created_by?.name ?? "—" }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="subscriptions.length === 0">
                            <TableCell
                                colspan="6"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay historial de suscripciones.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
