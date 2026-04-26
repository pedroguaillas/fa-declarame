<script setup lang="ts">
import { Button } from "@/components/ui/button";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { useClipboard } from "@/composables/shared/useClipboard";
import { Check, Copy } from "lucide-vue-next";

const props = defineProps<{
    text: string | null | undefined;
}>();

const { copy, copied } = useClipboard();
</script>

<template>
    <div class="flex items-center gap-2">
        <p class="truncate font-mono text-sm" :title="text ?? ''">
            {{ text ?? "—" }}
        </p>

        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    v-if="text"
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    @click="copy(text)"
                >
                    <Check v-if="copied" class="size-3.5 text-green-600" />
                    <Copy v-else class="size-3.5" />
                </Button>
            </TooltipTrigger>

            <TooltipContent side="top">
                <p>
                    {{ copied ? "Copiado" : "Copiar" }}
                </p>
            </TooltipContent>
        </Tooltip>
    </div>
</template>
