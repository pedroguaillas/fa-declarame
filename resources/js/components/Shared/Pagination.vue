<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { LaravelPaginatorMeta } from "@/types";
import { ChevronLeft, ChevronRight } from "lucide-vue-next";

const props = withDefaults(
    defineProps<{
        paginator: LaravelPaginatorMeta | null;
        loading?: boolean;
    }>(),
    {
        loading: false,
    },
);

const emit = defineEmits<{
    changePage: [page: number];
}>();

function rangeLabel(): string {
    const p = props.paginator;
    if (!p || p.total === 0) return "Sin resultados";
    return `Mostrando ${p.from}–${p.to} de ${p.total}`;
}

function handlePrev() {
    if (props.paginator && props.paginator.current_page > 1 && !props.loading) {
        emit("changePage", props.paginator.current_page - 1);
    }
}

function handleNext() {
    if (
        props.paginator &&
        props.paginator.current_page < props.paginator.last_page &&
        !props.loading
    ) {
        emit("changePage", props.paginator.current_page + 1);
    }
}
</script>

<template>
    <div class="flex items-center justify-between">
        <!-- Rango de registros en la tabla -->
        <span class="text-muted-foreground text-xs font-medium">
            {{ rangeLabel() }}
        </span>

        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1">
                <!-- Botón atrás -->
                <Button
                    variant="ghost"
                    size="icon"
                    class="hover:bg-muted bg-muted h-8 w-8 cursor-pointer transition-colors"
                    :disabled="
                        !paginator || paginator.current_page <= 1 || loading
                    "
                    @click="handlePrev"
                >
                    <ChevronLeft class="size-4" />
                </Button>

                <!-- Números de páginas -->
                <div
                    class="flex items-center px-2 text-xs font-bold tabular-nums"
                >
                    <span class="text-foreground">{{
                        paginator?.current_page ?? 1
                    }}</span>
                    <span class="text-muted-foreground mx-1">/</span>
                    <span class="text-muted-foreground">{{
                        paginator?.last_page ?? 1
                    }}</span>
                </div>

                <!-- Botón siguiente -->
                <Button
                    variant="ghost"
                    size="icon"
                    class="hover:bg-muted bg-muted h-8 w-8 cursor-pointer transition-colors"
                    :disabled="
                        !paginator ||
                        paginator.current_page >= (paginator?.last_page ?? 1) ||
                        loading
                    "
                    @click="handleNext"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
