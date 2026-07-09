<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Boxes,
    Eye,
    HardDrive,
    Plus,
    Server,
    Trash2,
    User,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = defineProps<{
    cells: any[]
}>()

const cellToDelete = ref<any | null>(null)
const deleting = ref(false)

const totalCells = computed(() => props.cells.length)

const assignedCount = computed(() =>
    props.cells.filter((cell) => cell.allocation).length
)

function confirmDelete(cell: any) {
    cellToDelete.value = cell
}

function cancelDelete() {
    if (deleting.value) return
    cellToDelete.value = null
}

function deleteCell() {
    if (!cellToDelete.value) return

    const routeId = cellToDelete.value.uuid ?? cellToDelete.value.id

    if (!routeId) {
        console.error('Missing cell route id', cellToDelete.value)
        return
    }

    deleting.value = true

    router.delete(`/admin/cells/${routeId}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            cellToDelete.value = null
        },
    })
}

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Cells" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Server class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Cells
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Manage deployed cells and their allocations on worker nodes.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/cells/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus class="size-4" />
                                New Cell
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Total Cells</div>
                            <div class="mt-1 text-2xl font-black">{{ totalCells }}</div>
                            <div class="mt-1 text-xs text-zinc-500">created cells</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Assigned Allocations</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ assignedCount }}</div>
                            <div class="mt-1 text-xs text-zinc-500">cells with ports</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Unassigned</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ totalCells - assignedCount }}</div>
                            <div class="mt-1 text-xs text-zinc-500">missing allocation</div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface">
                        <div class="flex flex-col gap-3 border-b border-zinc-800 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div>
                                <h2 class="text-lg font-black">All Cells</h2>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Cells are created from combs and deployed to worker nodes.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="cells.length === 0"
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Server class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No cells yet
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Add your first cell to get started.
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-800">
                                <thead class="bg-[#0d0f11]">
                                    <tr>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Cell</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Owner</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Node</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Allocation</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Comb</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Created</th>
                                        <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wide text-zinc-500">Actions</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-zinc-800">
                                    <tr
                                        v-for="cell in cells"
                                        :key="cell.id"
                                        class="transition hover:bg-surface-light/40"
                                    >
                                        <td class="px-5 py-4">
                                            <div class="font-black text-white">
                                                {{ cell.name }}
                                            </div>
                                            <div class="mt-1 font-mono text-xs text-zinc-500">
                                                {{ cell.daemon_id || 'No daemon ID' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2 text-sm font-bold text-zinc-300">
                                                <User class="size-4 text-zinc-500" />
                                                <span>{{ cell.owner?.name || 'Unknown' }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-zinc-500">
                                                {{ cell.owner?.email || 'No email' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2 text-sm font-bold text-zinc-300">
                                                <Server class="size-4 text-zinc-500" />
                                                <span>{{ cell.node?.name || 'Unknown' }}</span>
                                            </div>
                                            <div class="mt-1 text-xs text-zinc-500">
                                                {{ cell.node?.location || 'No location' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div v-if="cell.allocation" class="font-mono text-sm font-black text-white">
                                                {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                                            </div>
                                            <div v-else class="text-sm font-bold text-status-warning">
                                                No allocation
                                            </div>
                                            <div v-if="cell.allocation?.alias" class="mt-1 text-xs text-zinc-500">
                                                {{ cell.allocation.alias }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-300">
                                                <HardDrive class="size-3" />
                                                {{ cell.comb }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 text-sm font-bold text-zinc-500">
                                            {{ formatDate(cell.created_at) }}
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex justify-end gap-2">
                                                <Link
                                                    :href="`/admin/cells/${cell.id}`"
                                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                                >
                                                    <Eye class="size-4" />
                                                    View
                                                </Link>

                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-3 py-2 text-xs font-black text-status-danger transition hover:bg-status-danger/20"
                                                    @click="confirmDelete(cell)"
                                                >
                                                    <Trash2 class="size-4" />
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="!!cellToDelete"
            title="Delete Cell?"
            :description="`Are you sure you wish to delete '${cellToDelete?.name}'? This action cannot be undone.`"
            confirm-text="Delete Cell"
            cancel-text="Cancel"
            :danger="true"
            :loading="deleting"
            @cancel="cancelDelete"
            @confirm="deleteCell"
        />
    </AppLayout>
</template>