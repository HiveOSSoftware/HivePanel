<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeft,
    HardDrive,
    Plus,
    Server,
    Settings,
    Shield,
    SlidersHorizontal,
    Trash2,
} from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
    node: any
    allocations: any[]
}>()

const form = useForm({
    ip: '0.0.0.0',
    alias: '',
    notes: '',
    mode: 'single',
    port: '',
    port_start: '',
    port_end: '',
})

const availableCount = computed(() =>
    props.allocations.filter((allocation) => !allocation.is_reserved && !allocation.is_assigned).length
)

const reservedCount = computed(() =>
    props.allocations.filter((allocation) => allocation.is_reserved).length
)

const assignedCount = computed(() =>
    props.allocations.filter((allocation) => allocation.is_assigned).length
)

function submit() {
    const payload: any = {
        ip: form.ip,
        alias: form.alias,
        notes: form.notes,
    }

    if (form.mode === 'single') {
        payload.port = form.port
    } else {
        payload.port_start = form.port_start
        payload.port_end = form.port_end
    }

    router.post(`/admin/nodes/${props.node.id}/allocations`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            form.port = ''
            form.port_start = ''
            form.port_end = ''
        },
    })
}

function toggleReserve(allocation: any) {
    router.patch(`/admin/nodes/${props.node.id}/allocations/${allocation.id}/reserve`, {}, {
        preserveScroll: true,
    })
}

function deleteAllocation(allocation: any) {
    if (allocation.is_assigned) return

    router.delete(`/admin/nodes/${props.node.id}/allocations/${allocation.id}`, {
        preserveScroll: true,
    })
}

function statusLabel(allocation: any) {
    if (allocation.is_assigned) return 'Assigned'
    if (allocation.is_reserved) return 'Reserved'
    return 'Available'
}

function statusClass(allocation: any) {
    if (allocation.is_assigned) {
        return 'border-hive/30 bg-hive/10 text-hive'
    }

    if (allocation.is_reserved) {
        return 'border-status-warning/30 bg-status-warning/10 text-status-warning'
    }

    return 'border-status-success/30 bg-status-success/10 text-status-success'
}

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Node ${node.name} Allocations`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-zinc-500">
                            <Link href="/admin" class="hover:text-hive">Admin</Link>
                            <span>›</span>
                            <Link href="/admin/nodes" class="hover:text-hive">Nodes</Link>
                            <span>›</span>
                            <Link :href="`/admin/nodes/${node.id}`" class="hover:text-hive">{{ node.name }}</Link>
                            <span>›</span>
                            <span class="text-zinc-300">Allocations</span>
                        </div>

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h1 class="text-3xl font-black tracking-tight">
                                    Allocations
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Manage IP and port combinations available to cells on this node.
                                </p>
                            </div>

                            <Link
                                href="/admin/nodes"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Back to Nodes
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-1">
                        <div class="flex flex-wrap gap-1">
                            <Link :href="`/admin/nodes/${node.id}`" class="rounded-button px-4 pt-3 pb-2 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Activity class="size-4" />
                                    Overview
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/settings`" class="rounded-button px-4 pt-3 pb-2 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Settings class="size-4" />
                                    Settings
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/configuration`" class="rounded-button px-4 pt-3 pb-2 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <SlidersHorizontal class="size-4" />
                                    Configuration
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/allocations`" class="rounded-button bg-hive/10 px-4 pt-3 pb-2 text-sm font-black text-hive">
                                <span class="inline-flex items-center gap-2">
                                    <HardDrive class="size-4" />
                                    Allocations
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/servers`" class="rounded-button px-4 pt-3 pb-2 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Server class="size-4" />
                                    Servers
                                </span>
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Total</div>
                            <div class="mt-1 text-2xl font-black">{{ allocations.length }}</div>
                            <div class="mt-1 text-xs text-zinc-500">allocations</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Available</div>
                            <div class="mt-1 text-2xl font-black text-status-success">{{ availableCount }}</div>
                            <div class="mt-1 text-xs text-zinc-500">ready to assign</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Assigned</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ assignedCount }}</div>
                            <div class="mt-1 text-xs text-zinc-500">used by cells</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Reserved</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ reservedCount }}</div>
                            <div class="mt-1 text-xs text-zinc-500">blocked from use</div>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-[420px_1fr]">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="mb-5 flex items-center gap-3">
                                <Plus class="size-5 text-hive" />
                                <h2 class="text-lg font-black">Add Allocations</h2>
                            </div>

                            <form class="space-y-4" @submit.prevent="submit">
                                <div>
                                    <label class="text-sm font-bold text-zinc-400">IP Address</label>
                                    <input
                                        v-model="form.ip"
                                        type="text"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    />
                                    <div v-if="form.errors.ip" class="mt-1 text-xs font-bold text-status-danger">
                                        {{ form.errors.ip }}
                                    </div>
                                </div>

                                <div>
                                    <label class="text-sm font-bold text-zinc-400">Alias</label>
                                    <input
                                        v-model="form.alias"
                                        type="text"
                                        placeholder="Optional display alias"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    />
                                </div>

                                <div>
                                    <label class="text-sm font-bold text-zinc-400">Mode</label>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            class="rounded-button border px-4 py-3 text-sm font-black transition"
                                            :class="form.mode === 'single'
                                                ? 'border-hive bg-hive/10 text-hive'
                                                : 'border-zinc-800 bg-[#0d0f11] text-zinc-400 hover:text-white'"
                                            @click="form.mode = 'single'"
                                        >
                                            Single Port
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-button border px-4 py-3 text-sm font-black transition"
                                            :class="form.mode === 'range'
                                                ? 'border-hive bg-hive/10 text-hive'
                                                : 'border-zinc-800 bg-[#0d0f11] text-zinc-400 hover:text-white'"
                                            @click="form.mode = 'range'"
                                        >
                                            Port Range
                                        </button>
                                    </div>
                                </div>

                                <div v-if="form.mode === 'single'">
                                    <label class="text-sm font-bold text-zinc-400">Port</label>
                                    <input
                                        v-model="form.port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        placeholder="25565"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    />
                                    <div v-if="form.errors.port" class="mt-1 text-xs font-bold text-status-danger">
                                        {{ form.errors.port }}
                                    </div>
                                </div>

                                <div v-else class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">Start Port</label>
                                        <input
                                            v-model="form.port_start"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            placeholder="25565"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">End Port</label>
                                        <input
                                            v-model="form.port_end"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            placeholder="25600"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div v-if="form.errors.port_start || form.errors.port_end" class="col-span-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.port_start || form.errors.port_end }}
                                    </div>
                                </div>

                                <div>
                                    <label class="text-sm font-bold text-zinc-400">Notes</label>
                                    <textarea
                                        v-model="form.notes"
                                        rows="3"
                                        placeholder="Optional internal notes"
                                        class="mt-2 w-full resize-none rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="form.processing"
                                >
                                    <Plus class="size-4" />
                                    Add Allocation
                                </button>
                            </form>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface">
                            <div class="flex flex-col gap-3 border-b border-zinc-800 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black">Node Allocations</h2>
                                    <p class="mt-1 text-sm text-zinc-500">
                                        Ports can only be deleted when they are not assigned to a cell.
                                    </p>
                                </div>
                            </div>

                            <div v-if="allocations.length === 0" class="p-8 text-center">
                                <HardDrive class="mx-auto size-10 text-zinc-700" />
                                <div class="mt-3 text-lg font-black text-white">No allocations yet</div>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Add a single port or port range to make this node usable for cells.
                                </p>
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-zinc-800">
                                    <thead class="bg-[#0d0f11]">
                                        <tr>
                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Address</th>
                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Alias</th>
                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Status</th>
                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Cell</th>
                                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Created</th>
                                            <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wide text-zinc-500">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-zinc-800">
                                        <tr
                                            v-for="allocation in allocations"
                                            :key="allocation.id"
                                            class="transition hover:bg-surface-light/40"
                                        >
                                            <td class="px-5 py-4">
                                                <div class="font-mono text-sm font-black text-white">
                                                    {{ allocation.ip }}:{{ allocation.port }}
                                                </div>
                                                <div v-if="allocation.notes" class="mt-1 max-w-md truncate text-xs text-zinc-500">
                                                    {{ allocation.notes }}
                                                </div>
                                            </td>

                                            <td class="px-5 py-4 text-sm font-bold text-zinc-300">
                                                {{ allocation.alias || '—' }}
                                            </td>

                                            <td class="px-5 py-4">
                                                <span
                                                    class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                                    :class="statusClass(allocation)"
                                                >
                                                    {{ statusLabel(allocation) }}
                                                </span>
                                            </td>

                                            <td class="px-5 py-4 text-sm font-bold text-zinc-300">
                                                <span v-if="allocation.cell">
                                                    {{ allocation.cell.name }}
                                                </span>
                                                <span v-else class="text-zinc-600">None</span>
                                            </td>

                                            <td class="px-5 py-4 text-sm font-bold text-zinc-500">
                                                {{ formatDate(allocation.created_at) }}
                                            </td>

                                            <td class="px-5 py-4">
                                                <div class="flex justify-end gap-2">
                                                    <button
                                                        class="inline-flex items-center gap-2 rounded-button border px-3 py-2 text-xs font-black transition disabled:cursor-not-allowed disabled:opacity-40"
                                                        :class="allocation.is_reserved
                                                            ? 'border-status-warning/40 bg-status-warning/10 text-status-warning hover:bg-status-warning/20'
                                                            : 'border-zinc-800 bg-[#0d0f11] text-zinc-400 hover:border-status-warning hover:text-status-warning'"
                                                        :disabled="allocation.is_assigned"
                                                        @click="toggleReserve(allocation)"
                                                    >
                                                        <Shield class="size-4" />
                                                        {{ allocation.is_reserved ? 'Unreserve' : 'Reserve' }}
                                                    </button>

                                                    <button
                                                        class="inline-flex items-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-3 py-2 text-xs font-black text-status-danger transition hover:bg-status-danger/20 disabled:cursor-not-allowed disabled:opacity-40"
                                                        :disabled="allocation.is_assigned"
                                                        @click="deleteAllocation(allocation)"
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
                </div>
            </main>
        </div>
    </AppLayout>
</template>