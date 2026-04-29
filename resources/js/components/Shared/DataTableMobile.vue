<script setup lang="ts" generic="T extends object">
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Loader2, MoreHorizontal, SearchX } from "lucide-vue-next";
import type { Component } from "vue";
import { Avatar, AvatarFallback, AvatarImage } from "../ui/avatar";
import { ActionDef, ActionPayload, ColumnDef } from "@/types/shared";

const props = defineProps<{
    columns: ColumnDef<T>[];
    items: T[];
    loading?: boolean;
    actions?: ActionDef<T>[];
    emptyText?: string;
    emptyIcon?: Component;
}>();

const emit = defineEmits<{
    select: [item: T];
    action: [payload: ActionPayload<T>];
}>();

function cellValue(item: T, col: ColumnDef<T>): string {
    const v = item[col.key];
    if (col.format) return col.format(v, item);
    if (v == null) return "—";
    return String(v);
}

function badgeDisplayValue(item: T, col: ColumnDef<T>): string {
    const raw = item[col.key];
    if (col.format) return col.format(raw, item);
    return col.badge!.value(item);
}

function visibleActions(item: T): ActionDef<T>[] {
    return (props.actions ?? []).filter((a) => !a.show || a.show(item));
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div
            v-if="loading"
            class="flex flex-1 items-center justify-center py-10"
        >
            <Loader2 class="text-muted-foreground size-5 animate-spin" />
        </div>

        <template v-else>
            <!-- Empty -->
            <div
                v-if="!items.length"
                class="flex flex-1 flex-col items-center justify-center gap-2 py-10"
            >
                <component
                    :is="emptyIcon ?? SearchX"
                    class="text-muted-foreground/40 size-8"
                />
                <p class="text-muted-foreground text-sm">
                    {{ emptyText ?? "Sin resultados" }}
                </p>
            </div>

            <!-- Cards -->
            <div class="flex-1 overflow-y-auto">
                <div
                    v-for="(item, idx) in items"
                    :key="idx"
                    class="border-border hover:bg-muted/40 relative cursor-pointer p-3 transition-colors"
                    :class="{ 'border-t': idx > 0 }"
                    @click="emit('select', item)"
                >
                    <!-- Título + acciones -->
                    <div class="mb-1.5 flex items-start justify-between gap-2">
                        <!-- Si col[0] es avatar, renderiza imagen + col[1] como título -->
                        <template v-if="columns[0].avatar">
                            <Avatar class="h-7 w-7 shrink-0">
                                <AvatarImage
                                    v-if="columns[0].avatar.src(item)"
                                    :src="columns[0].avatar.src(item)!"
                                    class="object-contain"
                                />
                                <AvatarFallback
                                    class="text-[10px] font-medium uppercase"
                                >
                                    {{
                                        columns[0].avatar.fallback?.(item) ??
                                        "?"
                                    }}
                                </AvatarFallback>
                            </Avatar>
                            <p
                                class="text-foreground truncate text-sm leading-tight font-medium"
                            >
                                {{ cellValue(item, columns[1]) }}
                            </p>
                        </template>
                        <template v-else>
                            <Badge
                                v-if="columns[0].badge"
                                :variant="
                                    columns[0].badge.variant?.(item) ??
                                    'secondary'
                                "
                                :class="columns[0].badge.class?.(item)"
                            >
                                {{ columns[0].badge.value(item) }}
                            </Badge>
                            <p
                                v-else
                                class="text-foreground text-sm leading-tight font-medium"
                            >
                                {{ cellValue(item, columns[0]) }}
                            </p>
                        </template>

                        <div
                            v-if="actions?.length"
                            class="shrink-0"
                            @click.stop
                        >
                            <!-- Check inline -->
                            <template
                                v-if="
                                    visibleActions(item).every(
                                        (a) => a.type === 'check',
                                    )
                                "
                            >
                                <div class="flex items-center gap-1">
                                    <button
                                        v-for="action in visibleActions(item)"
                                        :key="action.event"
                                        type="button"
                                        :class="[
                                            'flex size-6 cursor-pointer items-center justify-center rounded transition-colors',
                                            action.checked?.(item)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80',
                                            action.class,
                                        ]"
                                        @click="
                                            emit('action', {
                                                event: action.event,
                                                item,
                                            })
                                        "
                                    >
                                        <component
                                            v-if="action.icon"
                                            :is="action.icon"
                                            class="size-3.5"
                                        />
                                    </button>
                                </div>
                            </template>

                            <!-- Dropdown normal -->
                            <DropdownMenu v-else>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground -mt-1 -mr-1 h-7 w-7"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <template
                                        v-for="action in visibleActions(item)"
                                        :key="action.event"
                                    >
                                        <DropdownMenuSeparator
                                            v-if="action.separator"
                                        />
                                        <DropdownMenuItem
                                            :class="[
                                                'cursor-pointer',
                                                action.class,
                                            ]"
                                            @click="
                                                emit('action', {
                                                    event: action.event,
                                                    item,
                                                })
                                            "
                                        >
                                            <component
                                                v-if="action.icon"
                                                :is="action.icon"
                                                class="mr-2 h-4 w-4 opacity-70"
                                            />
                                            <span>{{ action.label }}</span>
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <!-- Resto de columnas -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <div
                            v-for="col in columns.slice(1)"
                            :key="String(col.key)"
                            class="flex flex-col"
                        >
                            <span
                                class="text-muted-foreground text-[10px] font-medium tracking-wide uppercase"
                            >
                                {{ col.label }}
                            </span>
                            <!-- Con badge -->
                            <Badge
                                v-if="col.badge"
                                :variant="
                                    col.badge.variant?.(item) ?? 'secondary'
                                "
                                :class="[
                                    'mt-0.5 w-fit',
                                    col.badge.class?.(item),
                                ]"
                            >
                                {{ badgeDisplayValue(item, col) }}
                            </Badge>
                            <!-- Texto plano -->
                            <span
                                v-else
                                class="text-foreground truncate text-xs"
                            >
                                {{ cellValue(item, col) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
