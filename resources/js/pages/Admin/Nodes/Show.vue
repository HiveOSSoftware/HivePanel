<script setup lang="ts">
import UsageChart from '@/components/charts/UsageChart.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeft,
    CpuIcon,
    HardDrive,
    Info,
    Server,
    Settings,
    SlidersHorizontal,
    Wifi,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    node: any
    cells: any[]
}>()

const labels = ref<string[]>([])
const cpuUsed = ref<number[]>([])
const cpuMax = ref<number[]>([])
const memoryUsed = ref<number[]>([])
const memoryMax = ref<number[]>([])
const diskUsed = ref<number[]>([])
const diskMax = ref<number[]>([])

let timer: number | undefined

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

                    <section class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Worker</div>
                            <div class="mt-1 text-lg font-black" :class="isOnline ? 'text-status-success' : 'text-zinc-400'">
                                ● {{ workerStatusLabel }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">Last seen {{ formatDate(node.last_seen_at) }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Cells</div>
                            <div class="mt-1 text-2xl font-black">
                                {{ node.live_stat?.cells_running ?? 0 }} / {{ node.live_stat?.cells_total ?? cells.length }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">running / total</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Hostname</div>
                            <div class="mt-1 break-all text-sm font-black">{{ node.worker_hostname || 'Unknown' }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ node.worker_platform || 'No platform' }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Version</div>
                            <div class="mt-1 text-sm font-black">{{ node.worker_version || 'Unknown' }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ node.worker_ip || 'No IP' }}</div>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-[1fr_520px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Info class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Node Information</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="space-y-1">
                                        <div class="text-sm font-bold text-zinc-500">Node Name</div>
                                        <div class="font-black text-white">{{ node.name }}</div>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-sm font-bold text-zinc-500">Location</div>
                                        <div class="font-black text-white">{{ node.location || 'Unset' }}</div>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-sm font-bold text-zinc-500">Panel State</div>
                                        <div :class="node.is_active ? 'text-status-success' : 'text-zinc-400'" class="font-black">
                                            ● {{ node.is_active ? 'Active' : 'Inactive' }}
                                        </div>
                                    </div>

                                    <div class="space-y-1 md:col-span-3">
                                        <div class="text-sm font-bold text-zinc-500">Public URL</div>
                                        <div class="break-all font-mono font-black text-white">{{ url(node) }}</div>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-sm font-bold text-zinc-500">Created At</div>
                                        <div class="font-black text-white">{{ formatDate(node.created_at) }}</div>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-sm font-bold text-zinc-500">Last Updated</div>
                                        <div class="font-black text-white">{{ formatDate(node.updated_at) }}</div>
                                    </div>

                                    <div class="space-y-1">
                                        <div class="text-sm font-bold text-zinc-500">Live Stats Updated</div>
                                        <div class="font-black text-white">{{ formatDate(node.live_stat?.updated_at) }}</div>
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
                                        class="rounded-button border border-status-danger bg-status-danger px-4 py-2 text-sm font-black text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="cells.length > 0"
                                    >
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