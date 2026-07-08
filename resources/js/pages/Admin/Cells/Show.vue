<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import { ref } from 'vue'
import {
    ArrowLeft,
    Boxes,
    Cpu,
    Database,
    Edit,
    HardDrive,
    MemoryStick,
    Network,
    Server,
    Trash2,
    User,
} from 'lucide-vue-next'

const props = defineProps<{
    cell: any
}>()

const showDeleteModal = ref(false)
const deleting = ref(false)

function deleteCell() {
    deleting.value = true

    router.delete(`/admin/cells/${props.cell.id}`, {
        preserveScroll: true,

        onFinish: () => {
            deleting.value = false
            showDeleteModal.value = false
        },
    })
}

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}

function formatMb(value?: number) {
    if (!value) return 'Unlimited'

    if (value >= 1024) {
        return `${(value / 1024).toFixed(value % 1024 === 0 ? 0 : 1)} GB`
    }

    return `${value} MB`
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Cell ${cell.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-5">
                    <section class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-zinc-500">
                            <Link href="/admin" class="hover:text-hive">Admin</Link>
                            <span>›</span>
                            <Link href="/admin/cells" class="hover:text-hive">Cells</Link>
                            <span>›</span>
                            <span class="text-zinc-300">{{ cell.name }}</span>
                        </div>

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h1 class="text-3xl font-black tracking-tight">
                                        {{ cell.name }}
                                    </h1>

                                    <span class="rounded-full border border-hive/30 bg-hive/10 px-3 py-1 text-xs font-black text-hive">
                                        {{ cell.comb }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Admin view for this deployed cell.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/cells"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back to Cells
                                </Link>

                                <Link
                                    :href="`/admin/cells/${cell.id}/edit`"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <Edit class="size-4" />
                                    Edit
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Server class="size-4" />
                                Node
                            </div>
                            <div class="mt-2 text-lg font-black text-white">
                                {{ cell.node?.name || 'Unknown' }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ cell.node?.location || 'No location' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Network class="size-4" />
                                Allocation
                            </div>
                            <div v-if="cell.allocation" class="mt-2 font-mono text-lg font-black text-white">
                                {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                            </div>
                            <div v-else class="mt-2 text-lg font-black text-status-warning">
                                Missing
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ cell.allocation?.alias || 'No alias' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Boxes class="size-4" />
                                Comb
                            </div>
                            <div class="mt-2 text-lg font-black text-white">
                                {{ cell.comb }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ cell.variables?.version ? `Version ${cell.variables.version}` : 'No version' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <User class="size-4" />
                                Owner
                            </div>
                            <div class="mt-2 text-lg font-black text-white">
                                {{ cell.owner?.name || 'Unknown' }}
                            </div>
                            <div class="mt-1 truncate text-xs text-zinc-500">
                                {{ cell.owner?.email || 'No email' }}
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-5 lg:grid-cols-[1fr_420px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Database class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Cell Information</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Cell ID</div>
                                        <div class="mt-1 font-mono text-sm font-black text-white">{{ cell.id }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Daemon ID</div>
                                        <div class="mt-1 break-all font-mono text-sm font-black text-white">
                                            {{ cell.daemon_id || 'Not created on worker' }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Created</div>
                                        <div class="mt-1 font-black text-white">{{ formatDate(cell.created_at) }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Updated</div>
                                        <div class="mt-1 font-black text-white">{{ formatDate(cell.updated_at) }}</div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Boxes class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Variables</h2>
                                </div>

                                <div v-if="!cell.variables || Object.keys(cell.variables).length === 0" class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
                                    No variables stored for this cell.
                                </div>

                                <div v-else class="overflow-x-auto rounded-button border border-zinc-800">
                                    <table class="min-w-full divide-y divide-zinc-800">
                                        <thead class="bg-[#0d0f11]">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Key</th>
                                                <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Value</th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-zinc-800">
                                            <tr v-for="(value, key) in cell.variables" :key="key">
                                                <td class="px-4 py-3 font-mono text-sm font-black text-white">{{ key }}</td>
                                                <td class="px-4 py-3 font-mono text-sm font-bold text-zinc-400">{{ value }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Cpu class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Limits</h2>
                                </div>

                                <div class="space-y-3">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                            <MemoryStick class="size-4" />
                                            Memory
                                        </div>
                                        <div class="mt-2 text-xl font-black text-white">
                                            {{ formatMb(cell.limits?.memory_mb) }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                            <HardDrive class="size-4" />
                                            Disk
                                        </div>
                                        <div class="mt-2 text-xl font-black text-white">
                                            {{ formatMb(cell.limits?.disk_mb) }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                            <Cpu class="size-4" />
                                            CPU
                                        </div>
                                        <div class="mt-2 text-xl font-black text-white">
                                            {{ cell.limits?.cpu_percent ?? 0 }}%
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-status-danger/40 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black text-white">Danger Zone</h2>
                                <p class="mt-2 text-sm leading-6 text-zinc-400">
                                    Deleting this cell will remove it from the panel and release its assigned allocation.
                                </p>

                                <button
                                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-button border border-status-danger bg-status-danger px-4 py-3 text-sm font-black text-white transition hover:opacity-90"
                                    @click="showDeleteModal = true"
                                >
                                    <Trash2 class="size-4" />
                                    Delete Cell
                                </button>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
        <ConfirmationModal
            :open="showDeleteModal"
            title="Delete Cell?"
            :description="`Are you sure you wish to delete '${cell.name}'? This action cannot be undone and will remove the Cell from HivePanel, release all allocations and delete it from the worker.`"
            confirm-text="Delete Cell"
            cancel-text="Cancel"
            :danger="true"
            :loading="deleting"
            @cancel="showDeleteModal = false"
            @confirm="deleteCell"
        />
    </AppLayout>
</template>