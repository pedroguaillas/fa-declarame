<script setup lang="ts">
  import { Input } from '@/components/ui/input';
  import { Search, X } from 'lucide-vue-next';

  const search = defineModel<string>({ default: '' });
</script>

<template>
  <div class="group relative w-full">
    <Search
      class="text-muted-foreground/60 group-focus-within:text-primary pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 transition-colors"
    />

    <Input
      v-model="search"
      placeholder="Buscar..."
      class="focus-visible:ring-ring/30 focus-visible:border-primary/50 pl-9 transition-all duration-200 focus-visible:ring-1"
      :class="search ? 'pr-9' : ''"
    />

    <button
      v-if="search"
      type="button"
      @click="search = ''"
      class="text-muted-foreground/60 hover:text-foreground hover:bg-muted absolute top-1/2 right-1.5 -translate-y-1/2 cursor-pointer rounded-full p-1.5 transition-all active:scale-95"
      aria-label="Limpiar búsqueda"
    >
      <X class="size-3.5" />
    </button>
  </div>
</template>
