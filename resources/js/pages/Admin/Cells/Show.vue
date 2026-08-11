<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
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
    RefreshCw,
    Server,
    ShieldCheck,
    Trash2,
    User,
    WifiOff,
    Wrench,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'

type SyncDifference = {
    field: string
    panel: any
    worker: any
}

type SyncResult = {
    status: 'synced' | 'out_of_sync' | 'missing' | 'unreachable' | 'error'
    synced: boolean
    repairable: boolean
    message: string
    differences: SyncDifference[]
    expected?: Record<string, any>
    actual?: Record<string, any>
}

const props = defineProps<{
    cell: any
}>()

const showDeleteModal = ref(false)
const deleting = ref(false)

const syncResult = ref<SyncResult | null>(null)
const checkingSync = ref(false)
const repairingSync = ref(false)

const isInstalled = computed(() => props.cell.install_status === 'installed')
const isInstalling = computed(() => props.cell.install_status === 'installing')
const isPending = computed(() => props.cell.install_status === 'pending')
const isFailed = computed(() => props.cell.install_status === 'failed')

const syncStatusLabel = computed(() => {
    switch (syncResult.value?.status) {
        case 'synced':
            return 'Synced'

        case 'out_of_sync':
            return 'Out of Sync'

        case 'missing':
            return 'Worker Cell Missing'

        case 'unreachable':
            return 'Worker Unavailable'

        case 'error':
            return 'Sync Check Failed'

        default:
            return 'Checking Worker'
    }
})

const syncStatusDescription = computed(() => {
    switch (syncResult.value?.status) {
        case 'synced':
            return 'HivePanel and the Worker have matching definitions.'

        case 'out_of_sync':
            return 'The Worker definition differs from the definition stored by HivePanel.'

        case 'missing':
            return 'This Cell exists in HivePanel but not on the assigned Worker.'

        case 'unreachable':
            return 'The assigned node could not be contacted.'

        case 'error':
            return syncResult.value.message || 'HivePanel was unable to inspect this Cell on the Worker.'

        default:
            return 'Comparing HivePanel with the assigned Worker.'
    }
})

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

async function checkWorkerSync() {
    if (checkingSync.value || repairingSync.value) return

    checkingSync.value = true

    try {
        const response = await axios.get(`/admin/cells/${props.cell.id}/sync`)
        syncResult.value = response.data
    } catch (error: any) {
        syncResult.value = error.response?.data || {
            status: 'error',
            synced: false,
            repairable: false,
            message: 'Unable to inspect the Worker cell.',
            differences: [],
        }
    } finally {
        checkingSync.value = false
    }
}

async function repairWorkerSync() {
    if (repairingSync.value || checkingSync.value || !syncResult.value?.repairable) return

    repairingSync.value = true

    try {
        const response = await axios.post(`/admin/cells/${props.cell.id}/sync`)
        syncResult.value = response.data
    } catch (error: any) {
        syncResult.value = error.response?.data || {
            status: 'error',
            synced: false,
            repairable: false,
            message: 'Unable to repair the Worker cell.',
            differences: [],
        }
    } finally {
        repairingSync.value = false
    }
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

function syncStatusClass(status?: string) {
    switch (status) {
        case 'synced':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'out_of_sync':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'missing':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        case 'unreachable':
        case 'error':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-zinc-700 bg-zinc-800 text-zinc-400'
    }
}

function syncDifferenceLabel(field: string) {
    switch (field) {
        case 'worker_cell':
            return 'Worker Cell'

        case 'comb':
            return 'Comb'

        case 'comb_data':
            return 'Comb Data'

        case 'variables':
            return 'Variables'

        case 'allocation':
            return 'Allocation'

        case 'limits':
            return 'Resource Limits'

        case 'name':
            return 'Name'

        default:
            return field
                .replaceAll('_', ' ')
                .replace(/\b\w/g, character => character.toUpperCase())
    }
}

function formatSyncValue(value: any) {
    if (value === null || value === undefined) return 'Missing'

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false'
    }

    if (typeof value === 'object') {
        if (Array.isArray(value) && value.length === 0) return '{}'
        if (!Array.isArray(value) && Object.keys(value).length === 0) return '{}'

        return JSON.stringify(value, null, 2)
    }

    if (value === '') return 'Empty'

    return String(value)
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

onMounted(() => {
    checkWorkerSync()
})
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
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex size-10 shrink-0 items-center justify-center rounded-button border"
                                            :class="syncStatusClass(syncResult?.status)"
                                        >
                                            <RefreshCw
                                                v-if="checkingSync"
                                                class="size-5 animate-spin"
                                            />

                                            <CheckCircle2
                                                v-else-if="syncResult?.status === 'synced'"
                                                class="size-5"
                                            />

                                            <Wrench
                                                v-else-if="syncResult?.status === 'out_of_sync'"
                                                class="size-5"
                                            />

                                            <Server
                                                v-else-if="syncResult?.status === 'missing'"
                                                class="size-5"
                                            />

                                            <WifiOff
                                                v-else-if="syncResult?.status === 'unreachable'"
                                                class="size-5"
                                            />

                                            <CircleAlert
                                                v-else-if="syncResult?.status === 'error'"
                                                class="size-5"
                                            />

                                            <ShieldCheck
                                                v-else
                                                class="size-5"
                                            />
                                        </div>

                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h2 class="text-lg font-black">
                                                    Worker Sync
                                                </h2>

                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black"
                                                    :class="syncStatusClass(syncResult?.status)"
                                                >
                                                    <span
                                                        class="size-1.5 rounded-full bg-current"
                                                        :class="{ 'animate-pulse': checkingSync }"
                                                    />

                                                    {{ syncStatusLabel }}
                                                </span>
                                            </div>

                                            <p class="mt-1 text-sm text-zinc-500">
                                                {{ syncStatusDescription }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="checkingSync || repairingSync"
                                        @click="checkWorkerSync"
                                    >
                                        <RefreshCw
                                            class="size-4"
                                            :class="{ 'animate-spin': checkingSync }"
                                        />
                                        {{ checkingSync ? 'Checking...' : 'Check Again' }}
                                    </button>
                                </div>

                                <div
                                    v-if="checkingSync && !syncResult"
                                    class="mt-5 rounded-button border border-zinc-800 bg-[#0d0f11] p-5"
                                >
                                    <div class="flex items-center gap-3">
                                        <RefreshCw class="size-5 animate-spin text-hive" />

                                        <div>
                                            <div class="text-sm font-black text-white">
                                                Checking Worker definition
                                            </div>

                                            <div class="mt-1 text-xs text-zinc-500">
                                                Contacting {{ cell.node?.name || 'the assigned node' }} and comparing definitions.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <template v-if="syncResult">
                                    <div
                                        v-if="syncResult.status === 'synced'"
                                        class="mt-5 rounded-button border border-status-success/20 bg-status-success/5 p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-status-success" />

                                            <div>
                                                <div class="text-sm font-black text-status-success">
                                                    Definitions match
                                                </div>

                                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                                    The Cell name, Comb, Comb data, variables, allocation and resource limits stored by the Worker match HivePanel.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="syncResult.status === 'out_of_sync'"
                                        class="mt-5"
                                    >
                                        <div class="rounded-button border border-status-warning/20 bg-status-warning/5 p-4">
                                            <div class="flex items-start gap-3">
                                                <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-warning" />

                                                <div>
                                                    <div class="text-sm font-black text-status-warning">
                                                        Worker definition is stale
                                                    </div>

                                                    <p class="mt-1 text-sm leading-6 text-zinc-400">
                                                        {{ syncResult.differences.length }} {{ syncResult.differences.length === 1 ? 'field differs' : 'fields differ' }} from HivePanel. Repairing will update the Worker's stored definition to match HivePanel.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <div
                                                v-for="difference in syncResult.differences"
                                                :key="difference.field"
                                                class="overflow-hidden rounded-button border border-zinc-800 bg-[#0d0f11]"
                                            >
                                                <div class="border-b border-zinc-800 px-4 py-3">
                                                    <div class="text-xs font-black uppercase tracking-wide text-status-warning">
                                                        {{ syncDifferenceLabel(difference.field) }}
                                                    </div>
                                                </div>

                                                <div class="grid divide-y divide-zinc-800 md:grid-cols-2 md:divide-x md:divide-y-0">
                                                    <div class="min-w-0 p-4">
                                                        <div class="text-[11px] font-black uppercase tracking-wide text-zinc-500">
                                                            HivePanel
                                                        </div>

                                                        <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words font-mono text-xs leading-6 text-zinc-300">{{ formatSyncValue(difference.panel) }}</pre>
                                                    </div>

                                                    <div class="min-w-0 p-4">
                                                        <div class="text-[11px] font-black uppercase tracking-wide text-zinc-500">
                                                            Worker
                                                        </div>

                                                        <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words font-mono text-xs leading-6 text-zinc-300">{{ formatSyncValue(difference.worker) }}</pre>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <button
                                            v-if="syncResult.repairable"
                                            type="button"
                                            class="mt-4 inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="repairingSync || checkingSync"
                                            @click="repairWorkerSync"
                                        >
                                            <RefreshCw
                                                v-if="repairingSync"
                                                class="size-4 animate-spin"
                                            />

                                            <Wrench
                                                v-else
                                                class="size-4"
                                            />

                                            {{ repairingSync ? 'Repairing...' : 'Repair Cell' }}
                                        </button>
                                    </div>

                                    <div
                                        v-else-if="syncResult.status === 'missing'"
                                        class="mt-5 rounded-button border border-status-danger/20 bg-status-danger/5 p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <Server class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                            <div>
                                                <div class="text-sm font-black text-status-danger">
                                                    Worker Cell Missing
                                                </div>

                                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                                    This Cell exists in HivePanel but the Worker has no Cell matching daemon ID
                                                    <span class="font-mono font-bold text-zinc-300">{{ cell.daemon_id || 'unknown' }}</span>.
                                                </p>

                                                <p class="mt-2 text-xs leading-5 text-zinc-500">
                                                    Automatic recreation is currently disabled to prevent HivePanel and the Worker from ending up with different daemon IDs.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="syncResult.status === 'unreachable'"
                                        class="mt-5 rounded-button border border-status-danger/20 bg-status-danger/5 p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <WifiOff class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                            <div>
                                                <div class="text-sm font-black text-status-danger">
                                                    Worker Unavailable
                                                </div>

                                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                                    HivePanel could not contact {{ cell.node?.name || 'the assigned node' }}. No definition comparison or repair can be performed until the Worker is reachable.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-else-if="syncResult.status === 'error'"
                                        class="mt-5 rounded-button border border-status-danger/20 bg-status-danger/5 p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                            <div>
                                                <div class="text-sm font-black text-status-danger">
                                                    Sync Check Failed
                                                </div>

                                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                                    {{ syncResult.message }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
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