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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Loader2, MoreHorizontal, SearchX } from "lucide-vue-next";
import type { Component } from "vue";

import { Avatar, AvatarFallback, AvatarImage } from "../ui/avatar";
import { ActionDef, ActionPayload, ColumnDef } from "@/types/shared";

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";

const props = defineProps<{
    columns: ColumnDef<T>[];
    items: T[];
    loading?: boolean;
    actions?: ActionDef<T>[];
    emptyText?: string;
    emptyIcon?: Component;
    actionsMode?: "menu" | "icons" | "auto";
}>();

const emit = defineEmits<{
    select: [item: T];
    action: [payload: ActionPayload<T>];
}>();

const alignClass: Record<"left" | "center" | "right", string> = {
    left: "text-left",
    center: "text-center",
    right: "text-right",
};

function colAlign(col: ColumnDef<T>): string {
    return alignClass[col.align ?? "left"];
}

function badgeDisplayValue(item: T, col: ColumnDef<T>): string {
    const raw = item[col.key];
    if (col.format) return col.format(raw, item);
    return col.badge!.value(item);
}

function cellValue(item: T, col: ColumnDef<T>): string {
    const v = item[col.key];
    if (col.format) return col.format(v, item);
    if (v == null) return "—";
    return String(v);
}

function visibleActions(item: T): ActionDef<T>[] {
    return (props.actions ?? []).filter((a) => !a.show || a.show(item));
}
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden">
        <div v-if="loading" class="flex flex-1 items-center justify-center">
            <Loader2 class="text-muted-foreground size-5 animate-spin" />
        </div>

        <template v-else>
            <div class="flex-1 overflow-auto">
                <Table>
                    <TableHeader class="bg-muted">
                        <TableRow class="hover:bg-transparent">
                            <TableHead
                                v-for="col in columns"
                                :key="String(col.key)"
                                :class="colAlign(col)"
                                :style="
                                    col.width
                                        ? {
                                              width: col.width,
                                              minWidth: col.width,
                                          }
                                        : {}
                                "
                            >
                                {{ col.label }}
                            </TableHead>
                            <TableHead
                                v-if="actions?.length"
                                class="bg-muted sticky right-0 z-10 w-10 text-center"
                            >
                                Acciones
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <!-- Empty -->
                        <TableRow
                            v-if="!items.length"
                            class="hover:bg-transparent"
                        >
                            <TableCell
                                :colspan="
                                    actions?.length
                                        ? columns.length + 1
                                        : columns.length
                                "
                                class="py-10 text-center"
                            >
                                <div class="flex flex-col items-center gap-2">
                                    <component
                                        :is="emptyIcon ?? SearchX"
                                        class="text-muted-foreground/40 size-8"
                                    />
                                    <p class="text-muted-foreground text-sm">
                                        {{ emptyText ?? "Sin resultados" }}
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>

                        <!-- Rows -->
                        <TableRow
                            v-for="(item, idx) in items"
                            :key="idx"
                            class="cursor-pointer"
                            @click="emit('select', item)"
                        >
                            <TableCell
                                v-for="col in columns"
                                :key="String(col.key)"
                                :class="colAlign(col)"
                                :style="
                                    col.width
                                        ? {
                                              width: col.width,
                                              minWidth: col.width,
                                          }
                                        : {}
                                "
                            >
                                <!-- Avatar -->

                                <div
                                    v-if="col.avatar"
                                    class="flex items-center justify-center"
                                >
                                    <Avatar class="h-8 w-8">
                                        <AvatarImage
                                            v-if="col.avatar.src(item)"
                                            :src="col.avatar.src(item)!"
                                            class="object-contain"
                                        />
                                        <AvatarFallback
                                            class="text-[10px] font-medium uppercase"
                                        >
                                            {{
                                                col.avatar.fallback?.(item) ??
                                                "?"
                                            }}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>

                                <!-- Con badge -->
                                <Badge
                                    v-else-if="col.badge"
                                    :variant="
                                        col.badge.variant?.(item) ?? 'secondary'
                                    "
                                    :class="col.badge.class?.(item)"
                                >
                                    {{ badgeDisplayValue(item, col) }}
                                </Badge>
                                <!-- Texto plano -->
                                <template v-else>
                                    {{ cellValue(item, col) }}
                                </template>
                            </TableCell>

                            <!-- Acciones -->
                            <TableCell
                                v-if="actions?.length"
                                class="bg-card sticky right-0 z-10 w-10 text-center"
                                @click.stop
                            >
                                <!-- Si todas las acciones visibles son tipo check, mostrarlas inline -->
                                <template
                                    v-if="
                                        props.actionsMode === 'icons' ||
                                        (props.actionsMode === 'auto' &&
                                            visibleActions(item).every(
                                                (a) => a.icon,
                                            ))
                                    "
                                >
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <TooltipProvider>
                                            <div
                                                class="flex items-center justify-center gap-1"
                                            >
                                                <Tooltip
                                                    v-for="action in visibleActions(
                                                        item,
                                                    )"
                                                    :key="action.event"
                                                >
                                                    <TooltipTrigger as-child>
                                                        <button
                                                            type="button"
                                                            :class="[
                                                                'flex size-6 cursor-pointer items-center justify-center rounded transition-colors',

                                                                action.checked?.(
                                                                    item,
                                                                )
                                                                    ? 'bg-primary text-primary-foreground'
                                                                    : '',

                                                                action.event ===
                                                                'delete'
                                                                    ? 'text-destructive hover:bg-destructive/10'
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
                                                                v-if="
                                                                    action.icon
                                                                "
                                                                :is="
                                                                    action.icon
                                                                "
                                                                class="size-3.5"
                                                            />
                                                        </button>
                                                    </TooltipTrigger>

                                                    <TooltipContent>
                                                        <p>
                                                            {{
                                                                action.tooltip ??
                                                                action.label
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </TooltipProvider>
                                    </div>
                                </template>

                                <!-- Comportamiento normal con dropdown -->
                                <DropdownMenu v-else>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="text-muted-foreground h-7 w-7 cursor-pointer rounded-full"
                                        >
                                            <MoreHorizontal class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <template
                                            v-for="action in visibleActions(
                                                item,
                                            )"
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
                                                    class="mr-2 size-4 opacity-70"
                                                />
                                                <span>{{ action.label }}</span>
                                            </DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </template>
    </div>
</template>
