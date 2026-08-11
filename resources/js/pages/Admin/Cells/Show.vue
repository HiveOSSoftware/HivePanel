<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowLeft,
    Boxes,
    CheckCircle2,
    CircleAlert,
    Clock3,
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
import { computed, ref } from 'vue'

const props = defineProps<{
    cell: any
}>()

const showDeleteModal = ref(false)
const deleting = ref(false)

const isInstalled = computed(() => props.cell.install_status === 'installed')
const isInstalling = computed(() => props.cell.install_status === 'installing')
const isPending = computed(() => props.cell.install_status === 'pending')
const isFailed = computed(() => props.cell.install_status === 'failed')

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

function installStatusClass(status?: string) {
    switch (status) {
        case 'installed':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'installing':
            return 'border-hive/30 bg-hive/10 text-hive'

        case 'pending':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-zinc-700 bg-zinc-800 text-zinc-400'
    }
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
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex size-12 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] text-hive"
                                >
                                    <Server class="size-6" />
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ cell.name }}
                                        </h1>

                                        <span
                                            class="inline-flex items-center rounded-full border border-hive/30 bg-hive/10 px-3 py-1 text-xs font-black text-hive"
                                        >
                                            {{ cell.comb }}
                                        </span>

                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black"
                                            :class="installStatusClass(cell.install_status)"
                                        >
                                            <CheckCircle2
                                                v-if="isInstalled"
                                                class="size-3.5"
                                            />

                                            <Clock3
                                                v-else-if="isPending || isInstalling"
                                                class="size-3.5"
                                            />

                                            <CircleAlert
                                                v-else-if="isFailed"
                                                class="size-3.5"
                                            />

                                            {{ cell.install_status_label || cell.install_status || 'Unknown' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Manage this cell, review deployment information, resources, and installation state.
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-zinc-500">
                                        <span>
                                            ID:
                                            <span class="font-mono text-zinc-400">
                                                {{ cell.id }}
                                            </span>
                                        </span>

                                        <span v-if="cell.daemon_id">
                                            Daemon:
                                            <span class="font-mono text-zinc-400">
                                                {{ cell.daemon_id }}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/cells"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>

                                <Link
                                    :href="`/admin/cells/${cell.id}/edit`"
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                                >
                                    <Edit class="size-4" />
                                    Edit Cell
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="isFailed"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <CircleAlert class="mt-0.5 size-6 shrink-0 text-status-danger" />

                            <div class="min-w-0">
                                <h2 class="text-lg font-black text-status-danger">
                                    Installation Failed
                                </h2>

                                <p class="mt-1 text-sm text-zinc-400">
                                    The Worker was unable to complete this cell installation.
                                </p>

                                <div class="mt-4 rounded-button border border-status-danger/20 bg-black/20 p-4">
                                    <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-zinc-300">{{ cell.install_failure_reason || 'No failure reason was recorded.' }}</pre>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Server class="size-4 text-hive" />
                                Node
                            </div>

                            <div class="mt-3 text-lg font-black text-white">
                                {{ cell.node?.name || 'Unknown' }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                {{ cell.node?.location || 'No location' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Network class="size-4 text-hive" />
                                Allocation
                            </div>

                            <div
                                v-if="cell.allocation"
                                class="mt-3 font-mono text-lg font-black text-white"
                            >
                                {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                            </div>

                            <div
                                v-else
                                class="mt-3 text-lg font-black text-status-warning"
                            >
                                Missing
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                {{ cell.allocation?.alias || 'No alias' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Boxes class="size-4 text-hive" />
                                Comb
                            </div>

                            <div class="mt-3 text-lg font-black text-white">
                                {{ cell.comb }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                {{ cell.variables?.version ? `Version ${cell.variables.version}` : 'No version' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <User class="size-4 text-hive" />
                                Owner
                            </div>

                            <div class="mt-3 truncate text-lg font-black text-white">
                                {{ cell.owner?.name || 'Unknown' }}
                            </div>

                            <div class="mt-1 truncate text-xs text-zinc-500">
                                {{ cell.owner?.email || 'No email' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Database class="size-4 text-hive" />
                                Installation
                            </div>

                            <div class="mt-3">
                                <span
                                    class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-black"
                                    :class="installStatusClass(cell.install_status)"
                                >
                                    {{ cell.install_status_label || cell.install_status || 'Unknown' }}
                                </span>
                            </div>

                            <div
                                v-if="isInstalled"
                                class="mt-2 text-xs text-zinc-500"
                            >
                                {{ formatDate(cell.installed_at) }}
                            </div>

                            <div
                                v-else-if="isInstalling"
                                class="mt-2 text-xs text-hive"
                            >
                                Installation in progress
                            </div>

                            <div
                                v-else-if="isPending"
                                class="mt-2 text-xs text-status-warning"
                            >
                                Waiting for queue
                            </div>

                            <div
                                v-else-if="isFailed"
                                class="mt-2 text-xs text-status-danger"
                            >
                                Action required
                            </div>
                        </div>
                    </section>

                    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            Cell Information
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Core identifiers and deployment timestamps.
                                        </p>
                                    </div>

                                    <Database class="size-5 text-hive" />
                                </div>

                                <div class="mt-5 grid gap-3 md:grid-cols-2">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Cell ID
                                        </div>

                                        <div class="mt-2 break-all font-mono text-sm font-black text-white">
                                            {{ cell.id }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Daemon ID
                                        </div>

                                        <div class="mt-2 break-all font-mono text-sm font-black text-white">
                                            {{ cell.daemon_id || 'Not created on worker' }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Created
                                        </div>

                                        <div class="mt-2 text-sm font-black text-white">
                                            {{ formatDate(cell.created_at) }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Last Updated
                                        </div>

                                        <div class="mt-2 text-sm font-black text-white">
                                            {{ formatDate(cell.updated_at) }}
                                        </div>
                                    </div>

                                    <div
                                        v-if="cell.node?.public_fqdn"
                                        class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 md:col-span-2"
                                    >
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Node FQDN
                                        </div>

                                        <div class="mt-2 break-all font-mono text-sm font-black text-white">
                                            {{ cell.node.public_fqdn }}
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            Variables
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Runtime and comb variables stored for this cell.
                                        </p>
                                    </div>

                                    <Boxes class="size-5 text-hive" />
                                </div>

                                <div
                                    v-if="!cell.variables || Object.keys(cell.variables).length === 0"
                                    class="mt-5 rounded-button border border-zinc-800 bg-[#0d0f11] p-5 text-sm font-bold text-zinc-500"
                                >
                                    No variables stored for this cell.
                                </div>

                                <div
                                    v-else
                                    class="mt-5 overflow-hidden rounded-button border border-zinc-800"
                                >
                                    <table class="min-w-full divide-y divide-zinc-800">
                                        <thead class="bg-[#0d0f11]">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Variable
                                                </th>

                                                <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-zinc-500">
                                                    Value
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-zinc-800">
                                            <tr
                                                v-for="(value, key) in cell.variables"
                                                :key="key"
                                                class="transition hover:bg-surface-light/40"
                                            >
                                                <td class="px-4 py-3 font-mono text-sm font-black text-white">
                                                    {{ key }}
                                                </td>

                                                <td class="px-4 py-3 font-mono text-sm font-bold text-zinc-400">
                                                    {{ value }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            Resource Limits
                                        </h2>

                                        <p class="mt-1 text-xs text-zinc-500">
                                            Assigned runtime limits.
                                        </p>
                                    </div>

                                    <Cpu class="size-5 text-hive" />
                                </div>

                                <div class="mt-5 space-y-3">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <MemoryStick class="size-4" />
                                                Memory
                                            </div>

                                            <div class="text-lg font-black text-white">
                                                {{ formatMb(cell.limits?.memory_mb) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <HardDrive class="size-4" />
                                                Disk
                                            </div>

                                            <div class="text-lg font-black text-white">
                                                {{ formatMb(cell.limits?.disk_mb) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                                <Cpu class="size-4" />
                                                CPU
                                            </div>

                                            <div class="text-lg font-black text-white">
                                                {{ cell.limits?.cpu_percent ?? 0 }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-status-danger/30 bg-surface p-5 sm:p-6">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-button border border-status-danger/30 bg-status-danger/10 text-status-danger">
                                        <Trash2 class="size-5" />
                                    </div>

                                    <div>
                                        <h2 class="text-lg font-black text-white">
                                            Danger Zone
                                        </h2>

                                        <p class="mt-1 text-sm leading-6 text-zinc-400">
                                            Permanently delete this cell and release its assigned allocation.
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
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