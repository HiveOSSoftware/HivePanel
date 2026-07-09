<script setup lang="ts">
import { ChevronDown, ChevronUp, Search } from 'lucide-vue-next'
import { computed, ref } from 'vue'

type Column = {
    key: string
    label: string
    sortable?: boolean
    align?: 'left' | 'center' | 'right'
}

const props = withDefaults(defineProps<{
    columns: Column[]
    rows: any[]
    searchPlaceholder?: string
    emptyTitle?: string
    emptyDescription?: string
    perPage?: number
}>(), {
    searchPlaceholder: 'Search...',
    emptyTitle: 'No records found',
    emptyDescription: 'There is nothing to show yet.',
    perPage: 15,
})

const search = ref('')
const sortKey = ref('')
const sortDirection = ref<'asc' | 'desc'>('asc')
const page = ref(1)

const filteredRows = computed(() => {
    const value = search.value.trim().toLowerCase()

    if (!value) return props.rows

    return props.rows.filter((row) =>
        JSON.stringify(row).toLowerCase().includes(value)
    )
})

const sortedRows = computed(() => {
    if (!sortKey.value) return filteredRows.value

    return [...filteredRows.value].sort((a, b) => {
        const aValue = a[sortKey.value] ?? ''
        const bValue = b[sortKey.value] ?? ''

        if (aValue < bValue) return sortDirection.value === 'asc' ? -1 : 1
        if (aValue > bValue) return sortDirection.value === 'asc' ? 1 : -1

        return 0
    })
})

const totalPages = computed(() => Math.max(1, Math.ceil(sortedRows.value.length / props.perPage)))

const paginatedRows = computed(() => {
    const start = (page.value - 1) * props.perPage
    return sortedRows.value.slice(start, start + props.perPage)
})

function sort(column: Column) {
    if (!column.sortable) return

    if (sortKey.value === column.key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
        return
    }

    sortKey.value = column.key
    sortDirection.value = 'asc'
}

function alignClass(align?: string) {
    if (align === 'right') return 'text-right'
    if (align === 'center') return 'text-center'
    return 'text-left'
}
</script>

<template>
    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
        <div class="border-b border-zinc-800 p-4 sm:p-5">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />

                <input
                    v-model="search"
                    :placeholder="searchPlaceholder"
                    class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                    @input="page = 1"
                />
            </div>
        </div>

        <div v-if="paginatedRows.length === 0" class="p-10 text-center">
            <div class="text-lg font-black text-zinc-300">
                {{ emptyTitle }}
            </div>

            <p class="mt-2 text-sm text-zinc-500">
                {{ emptyDescription }}
            </p>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800">
                <thead class="bg-[#0d0f11]">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-5 py-4 text-xs font-black uppercase tracking-wide text-zinc-500"
                            :class="[alignClass(column.align), column.sortable ? 'cursor-pointer select-none hover:text-hive' : '']"
                            @click="sort(column)"
                        >
                            <span class="inline-flex items-center gap-1" :class="column.align === 'right' ? 'justify-end' : ''">
                                {{ column.label }}

                                <template v-if="sortKey === column.key">
                                    <ChevronUp v-if="sortDirection === 'asc'" class="size-3" />
                                    <ChevronDown v-else class="size-3" />
                                </template>
                            </span>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-800">
                    <tr
                        v-for="row in paginatedRows"
                        :key="row.id"
                        class="transition hover:bg-surface-light/40"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-5 py-4 text-sm"
                            :class="alignClass(column.align)"
                        >
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="sortedRows.length > perPage"
            class="flex items-center justify-between border-t border-zinc-800 px-5 py-4 text-sm text-zinc-500"
        >
            <div>
                Page {{ page }} of {{ totalPages }}
            </div>

            <div class="flex gap-2">
                <button
                    class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 font-bold text-zinc-300 disabled:opacity-40"
                    :disabled="page <= 1"
                    @click="page--"
                >
                    Previous
                </button>

                <button
                    class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 font-bold text-zinc-300 disabled:opacity-40"
                    :disabled="page >= totalPages"
                    @click="page++"
                >
                    Next
                </button>
            </div>
        </div>
    </section>
</template>