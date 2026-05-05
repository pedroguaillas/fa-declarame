<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Head, Link } from "@inertiajs/vue3";
import { ChevronLeft, Plus, Search, Upload, X } from "lucide-vue-next";
import type { Component } from "vue";

interface Props {
    title: string;
    description?: string;
    showSearch?: boolean;
    searchPlaceholder?: string;
    modelValue?: string;
    linkLabel?: string;
    linkHref?: string;
    backHref?: string;
    linkIcon?: Component;
    showImport?: boolean;
    importLabel?: string;
    importHref?: string;
    importIcon?: Component;
}

const props = withDefaults(defineProps<Props>(), {
    description: "",
    showSearch: false,
    searchPlaceholder: "Buscar...",
    modelValue: "",
    linkLabel: "",
    linkHref: "",
    backHref: "",
    linkIcon: () => Plus,
    showImport: false,
    importLabel: "Importar",
    importHref: "",
    importIcon: () => Upload,
});

const emit = defineEmits<{
    "update:modelValue": [value: string];
    "click-link": [];
    "click-import": [];
}>();

const clearSearch = () => {
    emit("update:modelValue", "");
};

const handleButtonClick = () => {
    emit("click-link");
};

const handleImportClick = () => {
    emit("click-import");
};
</script>

<template>
    <Head :title="title" />
    <div class="flex flex-col gap-4 px-1 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <Button
                v-if="backHref"
                variant="ghost"
                size="icon"
                as-child
                class="h-9 w-9"
            >
                <Link :href="backHref">
                    <ChevronLeft class="size-5" />
                </Link>
            </Button>

            <div>
                <h2 class="text-2xl leading-tight font-black tracking-tight uppercase">
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="text-muted-foreground text-[10px] font-bold tracking-widest uppercase opacity-70"
                >
                    {{ description }}
                </p>
            </div>
        </div>

        <div class="flex flex-col items-center gap-3 sm:flex-row">
            <div v-if="showSearch" class="relative w-full sm:w-64">
                <Search
                    class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <Input
                    :model-value="modelValue"
                    @update:model-value="emit('update:modelValue', String($event))"
                    :placeholder="searchPlaceholder"
                    class="w-full pr-9 pl-10 focus-visible:ring-0 focus-visible:ring-offset-0 focus-visible:outline-none"
                />
                <button
                    v-if="modelValue"
                    type="button"
                    @click="clearSearch"
                    class="text-muted-foreground hover:text-foreground hover:bg-accent absolute top-1/2 right-1 -translate-y-1/2 cursor-pointer rounded-full p-2 transition-colors"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="flex w-full items-center gap-2 sm:w-auto">
                
                <slot name="extra-actions" />

                <template v-if="showImport">
                    <Button
                        variant="outline"
                        type="button"
                        @click="handleImportClick"
                        size="sm"
                        class="hidden cursor-pointer font-bold md:inline-flex sm:flex-none"
                    >
                        <component :is="importIcon" class="size-4" />
                        {{ importLabel }}
                    </Button>
                </template>

                <template v-if="linkLabel">
                    <Button
                        v-if="linkHref"
                        as-child
                        class=" font-bold sm:flex-none"
                        size="sm"
                    >
                        <Link :href="linkHref">
                            <component :is="linkIcon" class="size-4" />
                            {{ linkLabel }}
                        </Link>
                    </Button>

                    <Button
                        v-else
                        type="button"
                        @click="handleButtonClick"
                        class=" cursor-pointer font-bold sm:flex-none"
                        size="sm"
                    >
                        <component :is="linkIcon" class="size-4" />
                        {{ linkLabel }}
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>