<script setup lang="ts" generic="T extends object">
  import { PackageSearch, X } from 'lucide-vue-next';
  import type { Component } from 'vue';
  import { onMounted, onUnmounted, ref, watch } from 'vue';
  import Searchbar from '../Searchbar.vue';
  import DataTableDesktop from '../Shared/DataTableDesktop.vue';
  import DataTableMobile from '../Shared/DataTableMobile.vue';
  import Pagination from '../Shared/Pagination.vue';
import { ActionDef, ActionPayload, ColumnDef } from '@/types/shared';


  // ── Tipos ──────────────────────────────────────────────────────────────────
  export interface LaravelPaginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  }

  // ── Props ──────────────────────────────────────────────────────────────────
  const props = withDefaults(
    defineProps<{
      open: boolean;
      title?: string;
      icon?: Component;
      description?: string;
      columns: ColumnDef<T>[];
      paginator: LaravelPaginator<T> | null;
      loading?: boolean;
      searchPlaceholder?: string;
      actions?: ActionDef<T>[];
      emptyText?: string;
      /** Icono para el estado vacío. Default: SearchX */
      emptyIcon?: Component;
    }>(),
    {
      loading: false,
      searchPlaceholder: 'Buscar...',
      icon: PackageSearch,
    }
  );

  // ── Emits ──────────────────────────────────────────────────────────────────
  const emit = defineEmits<{
    close: [];
    select: [item: T];
    action: [payload: ActionPayload<T>];
    fetch: [params: { search: string; page: number }];
  }>();

  // ── Estado ─────────────────────────────────────────────────────────────────
  const search = ref('');
  const currentPage = ref(1);
  let debounceTimer: ReturnType<typeof setTimeout>;

  watch(search, (q) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      currentPage.value = 1;
      emit('fetch', { search: q, page: 1 });
    }, 300);
  });

  watch(
    () => props.open,
    (val) => {
      if (val) {
        search.value = '';
        currentPage.value = 1;
        emit('fetch', { search: '', page: 1 });
      }
    }
  );

  function goTo(page: number) {
    currentPage.value = page;
    emit('fetch', { search: search.value, page });
  }

  // ── Keyboard / click-outside ───────────────────────────────────────────────
  const modalRef = ref<HTMLElement | null>(null);

  function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.open) emit('close');
  }
  function onOverlayClick(e: MouseEvent) {
    if (modalRef.value && !modalRef.value.contains(e.target as Node)) emit('close');
  }

  onMounted(() => window.addEventListener('keydown', onKeydown));
  onUnmounted(() => window.removeEventListener('keydown', onKeydown));

  // ── Helpers ────────────────────────────────────────────────────────────────
  function rangeLabel(): string {
    const p = props.paginator;
    if (!p || p.total === 0) return 'Sin resultados';
    return `Mostrando ${p.from}–${p.to} de ${p.total}`;
  }
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        @mousedown="onOverlayClick"
      >
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="open"
            ref="modalRef"
            class="bg-background border-border flex h-[70vh] w-full max-w-2xl flex-col rounded-xl border p-5 shadow-2xl"
          >
            <!-- Header -->

            <div class="flex shrink-0 flex-col gap-3 pb-3">
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <component :is="icon ?? PackageSearch" class="size-6" />
                  <div>
                    <h2 v-if="title" class="text-foreground text-base leading-tight font-semibold">
                      {{ title }}
                    </h2>
                    <p v-if="description" class="text-muted-foreground mt-0.5 text-sm">
                      {{ description }}
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  @click="emit('close')"
                  class="text-muted-foreground hover:text-foreground hover:bg-muted mt-0.5 ml-4 shrink-0 cursor-pointer rounded-full p-2 transition-colors"
                  aria-label="Cerrar"
                >
                  <X class="size-4" />
                </button>
              </div>
              <Searchbar v-model="search" :placeholder="searchPlaceholder" />
            </div>

            <!-- Tabla -->
            <div class="border-border relative min-h-0 flex-1 overflow-hidden rounded-lg border">
              <!-- Desktop (md+) -->
              <DataTableDesktop
                class="hidden h-full md:flex"
                :columns="columns"
                :items="paginator?.data ?? []"
                :loading="loading"
                :actions="actions"
                :empty-text="emptyText"
                :empty-icon="emptyIcon"
                @select="emit('select', $event)"
                @action="emit('action', $event)"
              />

              <!-- Móvil (< md) -->
              <DataTableMobile
                class="flex h-full md:hidden"
                :columns="columns"
                :items="paginator?.data ?? []"
                :loading="loading"
                :actions="actions"
                :empty-text="emptyText"
                :empty-icon="emptyIcon"
                @select="emit('select', $event)"
                @action="emit('action', $event)"
              />
            </div>

            <!-- Pagination -->
            <div class="py-3">
              <Pagination :paginator="paginator" :loading="loading" @change-page="goTo" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
