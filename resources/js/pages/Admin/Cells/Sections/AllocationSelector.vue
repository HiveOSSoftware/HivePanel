<script setup lang="ts">
import { Network } from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = defineProps<{
    allocations: any[]
    modelValue: string | number
    disabled?: boolean
    loading?: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string | number]
}>()

const search = ref('')

const filteredAllocations = computed(() => {
    const value = search.value.toLowerCase().trim()

    if (!value) return props.allocations

    return props.allocations.filter((allocation) =>
        `${allocation.ip}:${allocation.port} ${allocation.alias || ''}`
            .toLowerCase()
            .includes(value)
    )
})
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3">
            <Network class="size-5 text-hive" />
            <h2 class="text-lg font-black">Allocation</h2>
        </div>

        <div v-if="disabled" class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
            Select a node first.
        </div>

        <div v-else-if="loading" class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
            Loading allocations...
        </div>

        <div v-else-if="allocations.length === 0" class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger">
            This node has no available allocations.
        </div>

        <div v-else class="space-y-3">
            <input
                v-model="search"
                type="text"
                placeholder="Search allocations..."
                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
            />

            <select
                :value="modelValue"
                size="7"
                class="max-h-56 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-sm font-bold text-white outline-none transition focus:border-hive"
                @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
            >
                <option
                    v-for="allocation in filteredAllocations"
                    :key="allocation.id"
                    :value="allocation.id"
                >
                    {{ allocation.ip }}:{{ allocation.port }}{{ allocation.alias ? ` — ${allocation.alias}` : '' }}
                </option>
            </select>

            <div class="text-xs font-bold text-zinc-500">
                Showing {{ filteredAllocations.length }} of {{ allocations.length }} available allocations.
            </div>
        </div>
    </section>
</template>