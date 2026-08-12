<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeft,
    Boxes,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CpuIcon,
    Edit,
    Eye,
    HardDrive,
    Search,
    Server,
    Settings,
    SlidersHorizontal,
    TriangleAlert,
    User,
    WifiOff,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = defineProps<{
    node: any
    cells: any[]
}>()

const search = ref('')
const statusFilter = ref<'all' | 'healthy' | 'issues' | 'installing' | 'failed'>('all')

const totalCells = computed(() => props.cells.length)

const installedCount = computed(() =>
    props.cells.filter((cell) => cell.install_status === 'installed').length
)

const syncIssueCount = computed(() =>
    props.cells.filter((cell) =>
        ['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)
    ).length
)

const allocationCount = computed(() =>
    props.cells.reduce((total, cell) => {
        const primary = cell.allocation ? 1 : 0
        const additional = cell.additional_allocations?.length ?? 0

        return total + primary + additional
    }, 0)
)

const filteredCells = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.cells.filter((cell) => {
        const matchesSearch = !query || [
            cell.name,
            cell.daemon_id,
            cell.owner?.name,
            cell.owner?.email,
            cell.allocation?.ip,
            cell.allocation?.port,
            cell.comb,
        ].some((value) => String(value ?? '').toLowerCase().includes(query))

        if (!matchesSearch) return false

        switch (statusFilter.value) {
            case 'healthy':
                return cell.install_status === 'installed' &&
                    (!cell.worker_sync?.status || cell.worker_sync?.status === 'synced')

            case 'issues':
                return ['out_of_sync', 'missing', 'unreachable', 'error'].includes(cell.worker_sync?.status)

            case 'installing':
                return ['pending', 'installing'].includes(cell.install_status)

            case 'failed':
                return cell.install_status === 'failed'

            default:
                return true
        }
    })
})

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

function syncStatusClass(status?: string | null) {
    switch (status) {
        case 'synced':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'out_of_sync':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'missing':
        case 'unreachable':
        case 'error':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-zinc-700 bg-zinc-800 text-zinc-400'
    }
}

function syncStatusLabel(status?: string | null) {
    switch (status) {
        case 'synced':
            return 'Synced'

        case 'out_of_sync':
            return 'Out of Sync'

        case 'missing':
            return 'Missing'

        case 'unreachable':
            return 'Unavailable'

        case 'error':
            return 'Check Failed'

        default:
            return 'Not Checked'
    }
}

function syncStatusIcon(status?: string | null) {
    switch (status) {
        case 'synced':
            return CircleCheck

        case 'out_of_sync':
            return TriangleAlert

        case 'missing':
            return CircleAlert

        case 'unreachable':
            return WifiOff

        case 'error':
            return CircleAlert

        default:
            return CircleDashed
    }
}

function formatDate(value?: string) {
    if (!value) return 'Never'

    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Cells`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ node.name }} - Cells
                                        </h1>

                                        <span
                                            v-if="node.location"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive"
                                        >
                                            {{ node.location }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Review every Cell deployed to this Worker node.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/nodes"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                            >
                                <ArrowLeft class="size-4" />
                                Back to Nodes
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-1">
                        <div class="flex flex-wrap gap-1">
                            <Link
                                :href="`/admin/nodes/${node.id}`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Activity class="size-4" />
                                    Overview
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/settings`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Settings class="size-4" />
                                    Settings
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/configuration`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <SlidersHorizontal class="size-4" />
                                    Configuration
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/allocations`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <HardDrive class="size-4" />
                                    Allocations
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/cells`"
                                class="rounded-button bg-hive/10 px-4 py-3 text-sm font-black text-hive"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Server class="size-4" />
                                    Cells
                                </span>
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Total Cells
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ totalCells }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                deployed to this node
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Installed
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-success">
                                {{ installedCount }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                completed installations
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Allocations
                            </div>

                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ allocationCount }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                primary + additional
                            </div>
                        </div>

                        <div
                            class="rounded-panel border bg-surface p-5"
                            :class="syncIssueCount > 0 ? 'border-status-danger/30' : 'border-zinc-800'"
                        >
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Sync Issues
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="syncIssueCount > 0 ? 'text-status-danger' : 'text-status-success'"
                            >
                                {{ syncIssueCount }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                requiring attention
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <h2 class="text-lg font-black">
                                        Node Cells
                                    </h2>

                                    <p class="mt-1 text-sm text-zinc-500">
                                        Installation, networking and Worker synchronization state for this node.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-600" />

                                        <input
                                            v-model="search"
                                            type="search"
                                            placeholder="Search Cells..."
                                            class="w-full min-w-[260px] rounded-button border border-zinc-800 bg-[#0d0f11] py-2.5 pl-10 pr-4 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive sm:w-auto"
                                        />
                                    </div>

                                    <select
                                        v-model="statusFilter"
                                        class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2.5 text-sm font-bold text-zinc-300 outline-none transition focus:border-hive"
                                    >
                                        <option value="all">All Statuses</option>
                                        <option value="healthy">Healthy</option>
                                        <option value="issues">Sync Issues</option>
                                        <option value="installing">Installing</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 text-xs font-bold text-zinc-600">
                                Showing {{ filteredCells.length }} of {{ cells.length }} Cells
                            </div>
                        </div>

                        <div
                            v-if="cells.length === 0"
                            class="p-10 text-center"
                        >
                            <Server class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No Cells on this node
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                This Worker does not currently have any Cells assigned.
                            </p>
                        </div>

                        <div
                            v-else-if="filteredCells.length === 0"
                            class="p-10 text-center"
                        >
                            <Search class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No matching Cells
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Adjust the search text or status filter.
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-800">
                                <thead class="bg-[#0d0f11]">
                                    <tr>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Cell</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Owner</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Allocation</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Comb</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Install</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Worker Sync</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Created</th>
                                        <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wide text-zinc-500">Actions</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-zinc-800">
                                    <tr
                                        v-for="cell in filteredCells"
                                        :key="cell.id"
                                        class="transition hover:bg-surface-light/40"
                                    >
                                        <td class="px-5 py-4">
                                            <Link
                                                :href="`/admin/cells/${cell.id}`"
                                                class="font-black text-white transition hover:text-hive"
                                            >
                                                {{ cell.name }}
                                            </Link>

                                            <div class="mt-1 font-mono text-xs text-zinc-500">
                                                {{ cell.daemon_id || 'No daemon ID' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2 text-sm font-bold text-zinc-300">
                                                <User class="size-4 text-zinc-500" />
                                                {{ cell.owner?.name || 'Unknown' }}
                                            </div>

                                            <div class="mt-1 text-xs text-zinc-500">
                                                {{ cell.owner?.email || 'No email' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div v-if="cell.allocation">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-mono text-sm font-black text-white">
                                                        {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                                                    </span>

                                                    <span class="inline-flex rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-hive">
                                                        Primary
                                                    </span>
                                                </div>

                                                <div class="mt-1 text-xs text-zinc-500">
                                                    {{ cell.additional_allocations?.length ?? 0 }} additional
                                                </div>
                                            </div>

                                            <div v-else class="text-sm font-bold text-status-warning">
                                                No primary allocation
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="inline-flex items-center gap-2 rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-300">
                                                <Boxes class="size-3" />
                                                {{ cell.comb }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <span
                                                class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                                :class="installStatusClass(cell.install_status)"
                                            >
                                                {{ cell.install_status_label || cell.install_status || 'Unknown' }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-4">
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black"
                                                :class="syncStatusClass(cell.worker_sync?.status)"
                                            >
                                                <component
                                                    :is="syncStatusIcon(cell.worker_sync?.status)"
                                                    class="size-3.5"
                                                />
                                                {{ syncStatusLabel(cell.worker_sync?.status) }}
                                            </span>
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

                                                <Link
                                                    :href="`/admin/cells/${cell.id}/edit`"
                                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                                >
                                                    <Edit class="size-4" />
                                                    Edit
                                                </Link>
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
    </AppLayout>
</template>
