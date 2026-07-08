<script setup lang="ts">
import { Server } from 'lucide-vue-next'

defineProps<{
    nodes: any[]
    modelValue: string | number
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string | number]
}>()
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3">
            <Server class="size-5 text-hive" />
            <h2 class="text-lg font-black">Node</h2>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <button
                v-for="node in nodes"
                :key="node.id"
                type="button"
                class="rounded-panel border p-4 text-left transition"
                :class="String(modelValue) === String(node.id)
                    ? 'border-hive bg-hive/10'
                    : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-600'"
                :disabled="node.available_allocations_count <= 0"
                @click="emit('update:modelValue', node.id)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-base font-black text-white">
                            {{ node.name }}
                        </div>

                        <div class="mt-1 text-sm font-bold text-zinc-500">
                            {{ node.location || 'No location' }}
                        </div>
                    </div>

                    <span
                        class="rounded-full border px-3 py-1 text-xs font-black"
                        :class="node.available_allocations_count > 0
                            ? 'border-status-success/30 bg-status-success/10 text-status-success'
                            : 'border-status-danger/30 bg-status-danger/10 text-status-danger'"
                    >
                        {{ node.available_allocations_count }} free
                    </span>
                </div>

                <div class="mt-3 break-all font-mono text-xs text-zinc-500">
                    {{ node.public_fqdn }}
                </div>
            </button>
        </div>
    </section>
</template>