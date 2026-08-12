<script setup lang="ts">
import UsageChart from '@/components/charts/UsageChart.vue'
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeft,
    CircleCheck,
    CpuIcon,
    HardDrive,
    Info,
    Network,
    Server,
    Settings,
    SlidersHorizontal,
    Trash2,
    TriangleAlert,
    Wifi,
    WifiOff,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    node: any
    cells: any[]
    allocationSummary: {
        total: number
        available: number
        assigned: number
        reserved: number
    }
    cellSummary: {
        total: number
        installed: number
        installing: number
        failed: number
        sync_issues: number
        attention: number
    }
}>()

const labels = ref<string[]>([])
const cpuUsed = ref<number[]>([])
const cpuMax = ref<number[]>([])
const memoryUsed = ref<number[]>([])
const memoryMax = ref<number[]>([])
const diskUsed = ref<number[]>([])
const diskMax = ref<number[]>([])

let timer: number | undefined

const showDeleteModal = ref(false)
const deleting = ref(false)

const isOnline = computed(() => {
    if (!props.node.last_seen_at) return false

    const lastSeen = new Date(props.node.last_seen_at).getTime()
    const now = Date.now()

    return now - lastSeen < 45_000
})

const workerStatusLabel = computed(() => {
    if (!props.node.is_registered) return 'Unregistered'
    return isOnline.value ? 'Online' : 'Offline'
})

function pushPoint(target: number[], value: number) {
    target.push(Number(value || 0))

    if (target.length > 30) {
        target.shift()
    }
}

function pushStats(stats: any) {
    labels.value.push(new Date().toLocaleTimeString())

    if (labels.value.length > 30) {
        labels.value.shift()
    }

    pushPoint(cpuUsed.value, stats.cells?.cpu_used ?? 0)
    pushPoint(cpuMax.value, stats.limits?.cpu ?? stats.host?.cpu?.max ?? 0)

    pushPoint(memoryUsed.value, stats.cells?.memory_used_gb ?? 0)
    pushPoint(memoryMax.value, stats.limits?.memory_gb ?? stats.host?.memory?.max_gb ?? 0)

    pushPoint(diskUsed.value, stats.cells?.disk_used_gb ?? 0)
    pushPoint(diskMax.value, stats.limits?.disk_gb ?? stats.host?.disk?.max_gb ?? 0)
}

async function refreshNodeStats() {
    const response = await fetch(`/admin/nodes/${props.node.id}/stats-json`, {
        headers: { Accept: 'application/json' },
    })

    if (!response.ok) return

    pushStats(await response.json())
}

const latestCpu = computed(() => `${cpuUsed.value.at(-1) ?? 0} / ${cpuMax.value.at(-1) ?? 0} Threads`)
const latestMemory = computed(() => `${memoryUsed.value.at(-1) ?? 0} GiB / ${memoryMax.value.at(-1) ?? 0} GiB`)
const latestDisk = computed(() => `${diskUsed.value.at(-1) ?? 0} GiB / ${diskMax.value.at(-1) ?? 0} GiB`)

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}

function url(node: any) {
    return node.public_url ?? `${node.scheme}://${node.fqdn}:${node.port}`
}

function workerBadgeClass() {
    if (!props.node.is_registered) {
        return 'border-status-danger/30 bg-status-danger/10 text-status-danger'
    }

    return isOnline.value
        ? 'border-status-success/30 bg-status-success/10 text-status-success'
        : 'border-status-danger/30 bg-status-danger/10 text-status-danger'
}

function workerBadgeIcon() {
    return props.node.is_registered && isOnline.value
        ? CircleCheck
        : WifiOff
}

function deleteNode() {
    deleting.value = true

    router.delete(`/admin/nodes/${props.node.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false
            showDeleteModal.value = false
        },
    })
}

onMounted(() => {
    refreshNodeStats()
    timer = window.setInterval(refreshNodeStats, 15000)
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
})
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Node ${node.name}`" />

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
                                            {{ node.name }}
                                        </h1>

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                            {{ node.location }}
                                        </span>

                                        <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs font-bold text-zinc-400">
                                            {{ node.scheme }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-zinc-500">
                                        {{ node.fqdn }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/nodes"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-1">
                        <div class="flex flex-wrap gap-1">
                            <Link :href="`/admin/nodes/${node.id}`" class="rounded-button bg-hive/10 px-4 pt-3 pb-2 text-sm font-black text-hive">
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

                            <Link :href="`/admin/nodes/${node.id}/allocations`" class="rounded-button px-4 pt-3 pb-2 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <HardDrive class="size-4" />
                                    Allocations
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/cells`" class="rounded-button px-4 pt-3 pb-2 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Server class="size-4" />
                                    Cells
                                </span>
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Worker</div>

                            <div class="mt-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black"
                                    :class="workerBadgeClass()"
                                >
                                    <component :is="workerBadgeIcon()" class="size-3.5" />
                                    {{ workerStatusLabel }}
                                </span>
                            </div>

                            <div class="mt-2 text-xs text-zinc-500">
                                Last seen {{ formatDate(node.last_seen_at) }}
                            </div>
                        </div>

                        <Link
                            :href="`/admin/nodes/${node.id}/cells`"
                            class="rounded-panel border border-zinc-800 bg-surface p-5 transition hover:border-hive/40 hover:bg-surface-light/40"
                        >
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Server class="size-4 text-hive" />
                                Cells
                            </div>

                            <div class="mt-2 text-2xl font-black text-white">
                                {{ node.live_stat?.cells_running ?? 0 }} / {{ cellSummary.total }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">running / total</div>
                        </Link>

                        <Link
                            :href="`/admin/nodes/${node.id}/allocations`"
                            class="rounded-panel border border-zinc-800 bg-surface p-5 transition hover:border-hive/40 hover:bg-surface-light/40"
                        >
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <Network class="size-4 text-hive" />
                                Allocations
                            </div>

                            <div class="mt-2 text-2xl font-black text-white">
                                {{ allocationSummary.available }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                available of {{ allocationSummary.total }}
                            </div>
                        </Link>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Installed</div>
                            <div class="mt-2 text-2xl font-black text-status-success">{{ cellSummary.installed }}</div>
                            <div class="mt-1 text-xs text-zinc-500">completed installs</div>
                        </div>

                        <div
                            class="rounded-panel border bg-surface p-5"
                            :class="cellSummary.sync_issues > 0 ? 'border-status-warning/30' : 'border-zinc-800'"
                        >
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Sync Issues</div>

                            <div
                                class="mt-2 text-2xl font-black"
                                :class="cellSummary.sync_issues > 0 ? 'text-status-warning' : 'text-status-success'"
                            >
                                {{ cellSummary.sync_issues }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">Cells out of sync</div>
                        </div>

                        <div
                            class="rounded-panel border bg-surface p-5"
                            :class="cellSummary.attention > 0 ? 'border-status-danger/30' : 'border-zinc-800'"
                        >
                            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                <TriangleAlert
                                    v-if="cellSummary.attention > 0"
                                    class="size-4 text-status-danger"
                                />
                                <CircleCheck
                                    v-else
                                    class="size-4 text-status-success"
                                />
                                Attention
                            </div>

                            <div
                                class="mt-2 text-2xl font-black"
                                :class="cellSummary.attention > 0 ? 'text-status-danger' : 'text-status-success'"
                            >
                                {{ cellSummary.attention }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">failed installs + sync issues</div>
                        </div>
                    </section>

                    <section
                        v-if="cellSummary.attention > 0 || !node.is_registered || !isOnline"
                        class="rounded-panel border border-status-warning/30 bg-status-warning/10 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <TriangleAlert class="mt-0.5 size-5 shrink-0 text-status-warning" />

                            <div class="min-w-0 flex-1">
                                <h2 class="text-lg font-black text-status-warning">Needs Attention</h2>

                                <div class="mt-2 space-y-1 text-sm leading-6 text-zinc-300">
                                    <p v-if="!node.is_registered">This Worker has not completed registration.</p>
                                    <p v-else-if="!isOnline">The Worker has not sent a recent heartbeat.</p>

                                    <p v-if="cellSummary.failed > 0">
                                        {{ cellSummary.failed }} {{ cellSummary.failed === 1 ? 'Cell has' : 'Cells have' }} failed installation state.
                                    </p>

                                    <p v-if="cellSummary.sync_issues > 0">
                                        {{ cellSummary.sync_issues }} {{ cellSummary.sync_issues === 1 ? 'Cell requires' : 'Cells require' }} Worker reconciliation.
                                    </p>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <Link
                                        v-if="cellSummary.attention > 0"
                                        :href="`/admin/nodes/${node.id}/cells`"
                                        class="inline-flex items-center rounded-button border border-status-warning/40 bg-status-warning/10 px-4 py-2 text-sm font-black text-status-warning transition hover:bg-status-warning/20"
                                    >
                                        Review Cells
                                    </Link>

                                    <Link
                                        v-if="!node.is_registered"
                                        :href="`/admin/nodes/${node.id}/configuration`"
                                        class="inline-flex items-center rounded-button border border-hive/40 bg-hive/10 px-4 py-2 text-sm font-black text-hive transition hover:bg-hive/20"
                                    >
                                        Open Configuration
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-[1fr_520px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Info class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Worker Information</h2>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Hostname</div>
                                        <div class="mt-2 break-all text-sm font-black text-white">{{ node.worker_hostname || 'Unknown' }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">{{ node.worker_platform || 'No platform' }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Worker Version</div>
                                        <div class="mt-2 text-sm font-black text-white">{{ node.worker_version || 'Unknown' }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">{{ node.worker_ip || 'No Worker IP' }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Registration</div>
                                        <div
                                            class="mt-2 text-sm font-black"
                                            :class="node.is_registered ? 'text-status-success' : 'text-status-danger'"
                                        >
                                            {{ node.is_registered ? 'Registered' : 'Not Registered' }}
                                        </div>
                                        <div class="mt-1 text-xs text-zinc-500">
                                            {{ node.registered_at ? `Since ${formatDate(node.registered_at)}` : 'No registration timestamp' }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Panel State</div>
                                        <div
                                            class="mt-2 text-sm font-black"
                                            :class="node.is_active ? 'text-status-success' : 'text-zinc-400'"
                                        >
                                            {{ node.is_active ? 'Active' : 'Inactive' }}
                                        </div>
                                        <div class="mt-1 text-xs text-zinc-500">
                                            {{ node.maintenance_mode ? 'Maintenance mode enabled' : 'Normal operation' }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Location</div>
                                        <div class="mt-2 text-sm font-black text-white">{{ node.location || 'Unset' }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">{{ node.name }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Live Stats</div>
                                        <div class="mt-2 text-sm font-black text-white">{{ formatDate(node.live_stat?.updated_at) }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">Latest reported sample</div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <Network class="size-5 text-hive" />
                                            <h2 class="text-lg font-black">Allocation Capacity</h2>
                                        </div>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Exact IP and port entries currently configured for this Worker.
                                        </p>
                                    </div>

                                    <Link
                                        :href="`/admin/nodes/${node.id}/allocations`"
                                        class="inline-flex items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                    >
                                        Manage Allocations
                                    </Link>
                                </div>

                                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Total</div>
                                        <div class="mt-1 text-2xl font-black text-white">{{ allocationSummary.total }}</div>
                                    </div>

                                    <div class="rounded-button border border-status-success/20 bg-status-success/5 p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Available</div>
                                        <div class="mt-1 text-2xl font-black text-status-success">{{ allocationSummary.available }}</div>
                                    </div>

                                    <div class="rounded-button border border-hive/20 bg-hive/5 p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Assigned</div>
                                        <div class="mt-1 text-2xl font-black text-hive">{{ allocationSummary.assigned }}</div>
                                    </div>

                                    <div class="rounded-button border border-status-warning/20 bg-status-warning/5 p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Reserved</div>
                                        <div class="mt-1 text-2xl font-black text-status-warning">{{ allocationSummary.reserved }}</div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Wifi class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Connection Details</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Scheme</div>
                                        <div class="mt-1 font-black text-white">{{ node.scheme }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Public FQDN</div>
                                        <div class="mt-1 break-all font-black text-white">{{ node.public_fqdn || node.fqdn }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Internal FQDN</div>
                                        <div class="mt-1 break-all font-black text-white">{{ node.internal_fqdn || 'Uses public FQDN' }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Daemon Port</div>
                                        <div class="mt-1 font-black text-white">{{ node.daemon_port }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">SFTP Port</div>
                                        <div class="mt-1 font-black text-white">{{ node.sftp_port }}</div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-sm font-bold text-zinc-500">Behind Proxy</div>
                                        <div class="mt-1 font-black text-white">{{ node.behind_proxy ? 'Yes' : 'No' }}</div>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-status-danger/40 bg-surface p-5 sm:p-6">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <h2 class="text-lg font-black text-white">Delete Node</h2>
                                        <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-400">
                                            Deleting a node is irreversible. There must be no servers associated with this node before it can be removed.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="rounded-button border border-status-danger bg-status-danger px-4 py-2 text-sm font-black text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="cells.length > 0"
                                        :title="cells.length > 0 ? 'Move or delete all Cells before deleting this node.' : undefined"
                                        @click="showDeleteModal = true"
                                    >
                                        <Trash2 class="mr-2 inline size-4" />
                                        Delete This Node
                                    </button>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <Activity class="size-5 text-hive" />
                                        <h2 class="text-lg font-black">Resource Usage</h2>
                                    </div>

                                    <span class="rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-400">
                                        Live
                                    </span>
                                </div>

                                <div class="space-y-8">
                                    <UsageChart title="CPU Usage" :value="latestCpu" :labels="labels" :used="cpuUsed" :max="cpuMax" />
                                    <UsageChart title="Memory Usage" :value="latestMemory" unit="GiB" :labels="labels" :used="memoryUsed" :max="memoryMax" />
                                    <UsageChart title="Disk Usage" :value="latestDisk" unit="GiB" :labels="labels" :used="diskUsed" :max="diskMax" />
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>