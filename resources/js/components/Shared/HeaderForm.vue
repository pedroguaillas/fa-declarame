<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Head, Link } from "@inertiajs/vue3";
import { ChevronLeft, Upload, type LucideIcon } from "lucide-vue-next";

interface Props {
    title: string;
    description?: string;

    linkLabel?: string;
    linkHref: string;

    icon?: LucideIcon;

    showImport?: boolean;
    importLabel?: string;
    importHref?: string;
    importIcon?: LucideIcon;
}

const props = withDefaults(defineProps<Props>(), {
    description: "",
    linkLabel: "Volver",
    icon: () => ChevronLeft,

    showImport: false,
    importLabel: "Importar",
    importHref: "",
    importIcon: () => Upload,
});

const emit = defineEmits<{
    "click-import": [];
}>();

const handleImportClick = () => {
    emit("click-import");
};
</script>

<template>
    <Head :title="title" />

    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div>
            <h2 class="text-2xl font-black tracking-tight uppercase">
                {{ title }}
            </h2>

            <p
                v-if="description"
                class="text-muted-foreground text-[10px] font-bold tracking-widest uppercase opacity-70"
            >
                {{ description }}
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <template v-if="showImport">
                <Button
                    v-if="importHref"
                    as-child
                    variant="outline"
                    size="sm"
                    class="w-full font-bold sm:w-auto"
                >
                    <Link :href="importHref">
                        <component :is="importIcon" class="mr-2 size-4" />
                        {{ importLabel }}
                    </Link>
                </Button>

                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    class="w-full font-bold sm:w-auto"
                    @click="handleImportClick"
                >
                    <component :is="importIcon" class="mr-2 size-4" />
                    {{ importLabel }}
                </Button>
            </template>

            <Button
                v-if="linkLabel && linkHref"
                variant="ghost"
                size="sm"
                as-child
                class="w-full font-bold sm:w-auto"
            >
                <Link :href="linkHref">
                    <component :is="icon" class="mr-2 size-4" />
                    {{ linkLabel }}
                </Link>
            </Button>
        </div>
    </div>
</template>
