<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { computed, watch } from "vue";
import { Archive, Loader2, Trash2, TriangleAlert } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Company } from "@/types/tenant";

type DeleteMode = "logical" | "physical";

const props = defineProps<{
    open: boolean;
    company: Company | null;
}>();

const emit = defineEmits<{
    "update:open": [value: boolean];
}>();

const form = useForm<{ mode: DeleteMode; confirmation_ruc: string }>({
    mode: "logical",
    confirmation_ruc: "",
});

const modes: Array<{ value: DeleteMode; title: string; description: string; icon: typeof Archive }> = [
    {
        value: "logical",
        title: "Eliminación lógica",
        description:
            "Oculta el contribuyente y conserva toda su información. Se recupera al registrar nuevamente su RUC.",
        icon: Archive,
    },
    {
        value: "physical",
        title: "Eliminación física",
        description:
            "Borra definitivamente el contribuyente con sus compras, ventas, retenciones y descargas del SRI. No se puede deshacer.",
        icon: Trash2,
    },
];

const isPhysical = computed(() => form.mode === "physical");

const rucMatches = computed(
    () => !!props.company && form.confirmation_ruc === props.company.ruc,
);

const canSubmit = computed(
    () => !form.processing && (!isPhysical.value || rucMatches.value),
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
    },
);

watch(
    () => form.mode,
    () => {
        form.confirmation_ruc = "";
        form.clearErrors();
    },
);

/**
 * El RUC de confirmación solo puede escribirse a mano: se bloquea pegar,
 * soltar texto arrastrado y el autocompletado del navegador.
 */
function blockNonTypedInput(event: InputEvent) {
    const blockedInputTypes = ["insertFromPaste", "insertFromDrop", "insertReplacementText", "insertFromYank"];

    if (blockedInputTypes.includes(event.inputType)) {
        event.preventDefault();
    }
}

watch(
    () => form.confirmation_ruc,
    (value) => {
        const digitsOnly = value.replace(/\D/g, "").slice(0, 13);

        if (digitsOnly !== value) {
            form.confirmation_ruc = digitsOnly;
        }
    },
);

function submit() {
    if (!props.company || !canSubmit.value) return;

    form.delete(route("tenant.companies.destroy", props.company.id), {
        preserveScroll: true,
        onSuccess: () => emit("update:open", false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-black tracking-tight uppercase">
                    Eliminar contribuyente
                </DialogTitle>
                <DialogDescription>
                    {{ company?.name }} — RUC {{ company?.ruc }}
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2" role="radiogroup" aria-label="Tipo de eliminación">
                    <label
                        v-for="option in modes"
                        :key="option.value"
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                        :class="
                            form.mode === option.value
                                ? option.value === 'physical'
                                    ? 'border-destructive bg-destructive/5'
                                    : 'border-primary bg-primary/5'
                                : 'border-border hover:bg-muted/50'
                        "
                    >
                        <input
                            v-model="form.mode"
                            type="radio"
                            name="mode"
                            :value="option.value"
                            class="mt-1 accent-current"
                        />
                        <component
                            :is="option.icon"
                            class="mt-0.5 size-4 shrink-0"
                            :class="option.value === 'physical' ? 'text-destructive' : 'text-muted-foreground'"
                        />
                        <span class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold">{{ option.title }}</span>
                            <span class="text-muted-foreground text-xs">{{ option.description }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.mode" class="text-destructive text-xs">{{ form.errors.mode }}</p>

                <div v-if="isPhysical" class="flex flex-col gap-2">
                    <div class="border-destructive/40 bg-destructive/5 text-destructive flex items-start gap-2 rounded-md border p-2 text-xs">
                        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                        <span>
                            Para confirmar, escribe el RUC
                            <strong class="font-mono select-none">{{ company?.ruc }}</strong>.
                            No se permite copiar ni pegar.
                        </span>
                    </div>
                    <Label for="confirmation_ruc">RUC del contribuyente</Label>
                    <Input
                        id="confirmation_ruc"
                        v-model="form.confirmation_ruc"
                        inputmode="numeric"
                        maxlength="13"
                        autocomplete="off"
                        autocorrect="off"
                        spellcheck="false"
                        class="font-mono"
                        placeholder="Escribe el RUC"
                        @beforeinput="blockNonTypedInput"
                        @paste.prevent
                        @drop.prevent
                        @copy.prevent
                        @cut.prevent
                        @contextmenu.prevent
                    />
                    <p v-if="form.errors.confirmation_ruc" class="text-destructive text-xs">
                        {{ form.errors.confirmation_ruc }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="cursor-pointer"
                        :disabled="form.processing"
                        @click="emit('update:open', false)"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        class="cursor-pointer"
                        :variant="isPhysical ? 'destructive' : 'default'"
                        :disabled="!canSubmit"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        {{ isPhysical ? "Eliminar definitivamente" : "Eliminar" }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
