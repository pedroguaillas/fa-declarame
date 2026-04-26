<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import { format, parseISO, differenceInDays } from "date-fns";
import { es } from "date-fns/locale";
import type { Subscription, Plan, User, Paginator } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import DatePicker from "@/components/DatePicker.vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    CreditCard,
    Plus,
    Pencil,
    Trash2,
    History,
    CalendarCheck,
    CalendarX,
    Clock,
} from "lucide-vue-next";

const props = defineProps<{
    subscriptions: Paginator<Subscription>;
    plans: Plan[];
    admins: User[];
}>();

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    user_id: "",
    plan_id: "",
    start_date: "",
    end_date: "",
    notes: "",
});

function openCreate() {
    createForm.reset();
    createDialog.value = true;
}

function store() {
    createForm.post(route("subscriptions.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<Subscription | null>(null);
const editForm = useForm({
    plan_id: "",
    start_date: "",
    end_date: "",
    notes: "",
    is_active: true,
});

function openEdit(sub: Subscription) {
    editing.value = sub;
    editForm.plan_id = String(sub.plan_id);
    editForm.start_date = sub.start_date;
    editForm.end_date = sub.end_date;
    editForm.notes = sub.notes ?? "";
    editForm.is_active = sub.is_active;
    editDialog.value = true;
}

function update() {
    editForm.put(route("subscriptions.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Toggle activo ──────────────────────────────────────
function toggleActive(sub: Subscription) {
    router.patch(route("subscriptions.toggle-active", sub.id));
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<Subscription | null>(null);

function confirmDelete(sub: Subscription) {
    toDelete.value = sub;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("subscriptions.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}

// ── Helpers ────────────────────────────────────────────
function formatDate(date: string) {
    return format(parseISO(date), "dd MMM yyyy", { locale: es });
}

function getStatusInfo(sub: Subscription) {
    if (!sub.is_active) {
        return {
            label: "Inactiva",
            variant: "secondary" as const,
            icon: CalendarX,
        };
    }
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

function goToHistory(userId: number) {
    router.visit(route("subscriptions.history", userId));
}

function goToPage(url: string | null) {
    if (url) router.visit(url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <CreditCard class="size-6" />
                        Suscripciones
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona las suscripciones de los administradores.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nueva suscripción
                </Button>
            </div>

            <!-- Tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Administrador</TableHead>
                            <TableHead>Plan</TableHead>
                            <TableHead class="text-center">Inicio</TableHead>
                            <TableHead class="text-center"
                                >Vencimiento</TableHead
                            >
                            <TableHead class="text-center">Estado</TableHead>
                            <TableHead class="text-center">Activa</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="sub in subscriptions.data"
                            :key="sub.id"
                        >
                            <TableCell>
                                <div class="font-medium">
                                    {{ sub.user?.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ sub.user?.email }}
                                </div>
                            </TableCell>
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
                            <TableCell class="text-center">
                                <Switch
                                    :model-value="sub.is_active"
                                    @update:model-value="toggleActive(sub)"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="goToHistory(sub.user_id)"
                                    >
                                        <History class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(sub)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(sub)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="subscriptions.data.length === 0">
                            <TableCell
                                colspan="7"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay suscripciones registradas.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Paginación -->
                <div
                    v-if="subscriptions.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Mostrando {{ subscriptions.from }} -
                        {{ subscriptions.to }} de {{ subscriptions.total }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            v-for="link in subscriptions.links"
                            :key="link.label"
                            variant="outline"
                            size="sm"
                            :disabled="!link.url"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    link.active,
                            }"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog crear -->
        <Dialog v-model:open="createDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nueva suscripción</DialogTitle>
                    <DialogDescription>
                        Asigna un plan a un administrador. La suscripción
                        anterior quedará inactiva.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="space-y-2">
                        <Label>Administrador</Label>
                        <Select v-model="createForm.user_id">
                            <SelectTrigger
                                class="w-full"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.user_id,
                                }"
                            >
                                <SelectValue
                                    placeholder="Seleccionar admin..."
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="admin in admins"
                                    :key="admin.id"
                                    :value="String(admin.id)"
                                >
                                    {{ admin.name }} — {{ admin.email }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="createForm.errors.user_id"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.user_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Plan</Label>
                        <Select v-model="createForm.plan_id">
                            <SelectTrigger
                                class="w-full"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.plan_id,
                                }"
                            >
                                <SelectValue
                                    placeholder="Seleccionar plan..."
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    :value="String(plan.id)"
                                >
                                    {{ plan.name }} — ${{ plan.price }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="createForm.errors.plan_id"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.plan_id }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Fecha de inicio</Label>
                            <DatePicker
                                v-model="createForm.start_date"
                                placeholder="Seleccionar inicio"
                            />
                            <p
                                v-if="createForm.errors.start_date"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.start_date }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Fecha de vencimiento</Label>
                            <DatePicker
                                v-model="createForm.end_date"
                                placeholder="Seleccionar vencimiento"
                            />
                            <p
                                v-if="createForm.errors.end_date"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.end_date }}
                            </p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label
                            >Notas
                            <span class="text-muted-foreground text-xs"
                                >(opcional)</span
                            ></Label
                        >
                        <Textarea
                            v-model="createForm.notes"
                            placeholder="Observaciones sobre esta suscripción..."
                            rows="2"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createDialog = false"
                            >Cancelar</Button
                        >
                        <Button type="submit" :disabled="createForm.processing"
                            >Crear suscripción</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar suscripción</DialogTitle>
                    <DialogDescription>
                        Modifica la suscripción de
                        <strong>{{ editing?.user?.name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="update" class="space-y-4">
                    <div class="space-y-2">
                        <Label>Plan</Label>
                        <Select v-model="editForm.plan_id">
                            <SelectTrigger
                                class="w-full"
                                :class="{
                                    'border-destructive':
                                        editForm.errors.plan_id,
                                }"
                            >
                                <SelectValue
                                    placeholder="Seleccionar plan..."
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    :value="String(plan.id)"
                                >
                                    {{ plan.name }} — ${{ plan.price }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Fecha de inicio</Label>
                            <DatePicker v-model="editForm.start_date" />
                            <p
                                v-if="editForm.errors.start_date"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.start_date }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Fecha de vencimiento</Label>
                            <DatePicker v-model="editForm.end_date" />
                            <p
                                v-if="editForm.errors.end_date"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.end_date }}
                            </p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label>Notas</Label>
                        <Textarea v-model="editForm.notes" rows="2" />
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch
                            :model-value="editForm.is_active"
                            @update:model-value="
                                (val) => (editForm.is_active = val)
                            "
                        />
                        <Label class="cursor-pointer">Suscripción activa</Label>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="editDialog = false"
                            >Cancelar</Button
                        >
                        <Button type="submit" :disabled="editForm.processing"
                            >Guardar cambios</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog eliminar -->
        <Dialog v-model:open="deleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Eliminar suscripción?</DialogTitle>
                    <DialogDescription>
                        Esta acción eliminará permanentemente la suscripción de
                        <strong>{{ toDelete?.user?.name }}</strong> al plan
                        <strong>{{ toDelete?.plan?.name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteDialog = false"
                        >Cancelar</Button
                    >
                    <Button variant="destructive" @click="handleDelete"
                        >Eliminar</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
