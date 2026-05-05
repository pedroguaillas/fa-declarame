<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2 } from "lucide-vue-next";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

interface IdentificationType {
    id: number;
    description: string;
}

interface Contact {
    id: number;
    identification_type_id: number;
    identification: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    data_additional: { passport_type?: string } | null;
}

const passportTypes = [
    { value: "01", label: "Persona natural" },
    { value: "02", label: "Sociedad" },
    { value: "03", label: "Extranjero" },
];

const props = defineProps<{
    open: boolean;
    contact?: Contact | null;
    identificationTypes: IdentificationType[];
}>();

const emit = defineEmits<{
    "update:open": [value: boolean];
}>();

const isEditing = () => !!props.contact;

const form = useForm({
    identification_type_id: "",
    identification: "",
    name: "",
    phone: "",
    email: "",
    address: "",
    passport_type: "",
});

const resolving = ref(false);
const resolveMessage = ref<string | null>(null);
const alreadyExists = ref(false);

const selectedType = computed(() =>
    props.identificationTypes.find((t) => String(t.id) === form.identification_type_id),
);

const isPassport = computed(() => {
    if (!selectedType.value) return false;
    return selectedType.value.description.toUpperCase() === "PASAPORTE";
});

const maxLength = computed(() => {
    if (!selectedType.value) return 20;
    const desc = selectedType.value.description.toUpperCase();
    if (desc === "RUC") return 13;
    if (desc === "CEDULA" || desc === "CÉDULA") return 10;
    return 20;
});

const expectedLength = computed(() => {
    if (!selectedType.value) return 0;
    const desc = selectedType.value.description.toUpperCase();
    if (desc === "RUC") return 13;
    if (desc === "CEDULA" || desc === "CÉDULA") return 10;
    return 0;
});

async function resolveContact(identification: string) {
    if (!identification || isEditing()) return;
    resolveMessage.value = null;
    alreadyExists.value = false;
    resolving.value = true;

    try {
        const res = await fetch(route("tenant.contacts.resolve", { identification }), {
            headers: { Accept: "application/json" },
        });

        if (res.ok) {
            const data = await res.json();
            if (data.id) {
                form.name = data.name;
                resolveMessage.value = "Este contacto ya está registrado.";
                alreadyExists.value = true;
            } else if (data.name) {
                form.name = data.name;
                resolveMessage.value = "Nombre obtenido del SRI.";
            }
        }
    } catch {
        // Silently ignore resolve errors
    } finally {
        resolving.value = false;
    }
}

// Auto-resolve for RUC/Cédula when reaching expected length
watch(
    () => form.identification,
    (value) => {
        if (isEditing() || isPassport.value) return;
        resolveMessage.value = null;
        alreadyExists.value = false;

        if (expectedLength.value && value.length === expectedLength.value) {
            resolveContact(value);
        }
    },
);

// Resolve on blur for Pasaporte
function handleIdentificationBlur() {
    if (isPassport.value && form.identification.length > 0) {
        resolveContact(form.identification);
    }
}

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        resolveMessage.value = null;
        alreadyExists.value = false;
        if (props.contact) {
            form.identification_type_id = String(props.contact.identification_type_id);
            form.identification = props.contact.identification;
            form.name = props.contact.name;
            form.phone = props.contact.phone ?? "";
            form.email = props.contact.email ?? "";
            form.address = props.contact.address ?? "";
            form.passport_type = props.contact.data_additional?.passport_type ?? "";
        } else {
            form.reset();
        }
    },
);

function submit() {
    if (isEditing()) {
        form.put(route("tenant.contacts.update", props.contact!.id), {
            onSuccess: () => emit("update:open", false),
        });
    } else {
        form.post(route("tenant.contacts.store"), {
            onSuccess: () => {
                emit("update:open", false);
                form.reset();
            },
        });
    }
}

function close() {
    emit("update:open", false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ isEditing() ? "Editar contacto" : "Nuevo contacto" }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label
                            >Tipo de identificación
                            <span class="text-destructive ml-0.5">*</span>
                        </Label>
                        <Select v-model="form.identification_type_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Seleccionar..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in identificationTypes" :key="type.id" :value="String(type.id)">
                                    {{ type.description }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.identification_type_id" class="text-destructive text-xs">
                            {{ form.errors.identification_type_id }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label
                            >Identificación
                            <span class="text-destructive ml-0.5">*</span>
                        </Label>
                        <div class="relative">
                            <Input
                                v-model="form.identification"
                                :maxlength="maxLength"
                                @blur="handleIdentificationBlur"
                            />
                            <Loader2
                                v-if="resolving"
                                class="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin"
                            />
                        </div>
                        <p v-if="form.errors.identification" class="text-destructive text-xs">
                            {{ form.errors.identification }}
                        </p>
                        <p v-else-if="resolveMessage && alreadyExists" class="text-amber-600 text-xs font-medium">
                            {{ resolveMessage }}
                        </p>
                        <p v-else-if="resolveMessage" class="text-emerald-600 text-xs font-medium">
                            {{ resolveMessage }}
                        </p>
                    </div>
                </div>
                <div v-if="isPassport" class="space-y-2">
                    <Label
                        >Tipo de pasaporte
                        <span class="text-destructive ml-0.5">*</span>
                    </Label>
                    <Select v-model="form.passport_type">
                        <SelectTrigger>
                            <SelectValue placeholder="Seleccionar..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="pt in passportTypes" :key="pt.value" :value="pt.value">
                                {{ pt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.passport_type" class="text-destructive text-xs">
                        {{ form.errors.passport_type }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label
                        >Nombre / Razón social
                        <span class="text-destructive ml-0.5">*</span>
                    </Label>
                    <Input v-model="form.name" />
                    <p v-if="form.errors.name" class="text-destructive text-xs">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Teléfono</Label>
                        <Input v-model="form.phone" />
                    </div>
                    <div class="space-y-2">
                        <Label>Email</Label>
                        <Input v-model="form.email" type="email" />
                        <p v-if="form.errors.email" class="text-destructive text-xs">
                            {{ form.errors.email }}
                        </p>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label>Dirección</Label>
                    <Input v-model="form.address" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="close"> Cancelar </Button>
                    <Button type="submit" :disabled="form.processing || resolving || alreadyExists">
                        {{ isEditing() ? "Actualizar" : "Guardar" }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
