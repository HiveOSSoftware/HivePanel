<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowLeft,
    CircleAlert,
    CircleCheck,
    Database,
    ExternalLink,
    LoaderCircle,
    Play,
    RefreshCw,
    Server,
    TriangleAlert,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    migration: any
    servers: any[]
}>()

const migrationState = ref({
    ...props.migration,
})

const serverState = ref(
    props.servers.filter(
        (server) => server.selected !== false
    )
)

const starting = ref(false)
let pollTimer: number | undefined

const executionActive = computed(() =>
    ['running'].includes(
        migrationState.value.status
    )
)

const canStart = computed(() =>
    migrationState.value.status ===
    'execution_ready'
)

const completedCount = computed(() =>
    serverState.value.filter(
        (server) =>
            server.status === 'completed'
    ).length
)

const failedCount = computed(() =>
    serverState.value.filter(
        (server) =>
            server.status === 'failed'
    ).length
)

const databasePendingCount = computed(() =>
    serverState.value.filter(
        (server) =>
            server.status === 'database_pending'
    ).length
)

const activeCount = computed(() =>
    serverState.value.filter(
        (server) =>
            [
                'queued',
                'creating_cell',
                'transferring',
            ].includes(server.status)
    ).length
)

function startMigration() {
    starting.value = true

    router.post(
        `/admin/migrations/${migrationState.value.id}/execution/start`,
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                migrationState.value = {
                    ...migrationState.value,
                    status: 'running',
                    current_stage: 'Queueing server migrations',
                    progress: 0,
                    error: null,
                }

                startPolling()
            },

            onFinish: () => {
                starting.value = false
            },
        }
    )
}

async function pollStatus() {
    if (!executionActive.value) {
        stopPolling()
        return
    }

    try {
        const response = await fetch(
            `/admin/migrations/${migrationState.value.id}/status`,
            {
                headers: {
                    Accept: 'application/json',
                },
            }
        )

        if (!response.ok) {
            return
        }

        const payload = await response.json()

        migrationState.value =
            payload.migration

        serverState.value = (
            payload.servers ?? []
        ).filter(
            (server: any) =>
                server.selected !== false
        )

        if (
            migrationState.value.status
            !== 'running'
        ) {
            stopPolling()
        }
    } catch {
        // Keep the last known execution state
        // and try again on the next interval.
    }
}

function startPolling() {
    stopPolling()

    pollStatus()

    pollTimer = window.setInterval(
        pollStatus,
        2500
    )
}

function stopPolling() {
    if (!pollTimer) {
        return
    }

    window.clearInterval(pollTimer)
    pollTimer = undefined
}

function statusLabel(status: string) {
    switch (status) {
        case 'queued':
            return 'Queued'

        case 'creating_cell':
            return 'Creating Cell'

        case 'transferring':
            return 'Transferring Files'

        case 'database_pending':
            return 'Database Pending'

        case 'completed':
            return 'Completed'

        case 'failed':
            return 'Failed'

        case 'prepared':
            return 'Prepared'

        default:
            return status || 'Pending'
    }
}

function statusClass(status: string) {
    switch (status) {
        case 'completed':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        case 'database_pending':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'queued':
        case 'creating_cell':
        case 'transferring':
            return 'border-hive/30 bg-hive/10 text-hive'

        default:
            return 'border-zinc-700 bg-zinc-800 text-zinc-400'
    }
}

function statusIcon(status: string) {
    switch (status) {
        case 'completed':
            return CircleCheck

        case 'failed':
            return CircleAlert

        case 'database_pending':
            return Database

        case 'queued':
        case 'creating_cell':
        case 'transferring':
            return LoaderCircle

        default:
            return Server
    }
}

function selectedDatabaseCount(server: any) {
    return (
        server.database_plan ?? []
    ).filter(
        (database: any) =>
            database.selected
    ).length
}

onMounted(() => {
    if (executionActive.value) {
        startPolling()
    }
})

onUnmounted(() => {
    stopPolling()
})
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${migrationState.name} - Execution`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-black"
                                        :class="migrationState.status === 'execution_ready'
                                            ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                            : migrationState.status === 'running'
                                                ? 'border-hive/30 bg-hive/10 text-hive'
                                                : migrationState.status === 'completed'
                                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                    : migrationState.status === 'completed_with_errors' || migrationState.status === 'failed'
                                                        ? 'border-status-danger/30 bg-status-danger/10 text-status-danger'
                                                        : 'border-status-warning/30 bg-status-warning/10 text-status-warning'"
                                    >
                                        {{ migrationState.status }}
                                    </span>

                                    <span class="text-xs font-bold text-zinc-600">
                                        {{ migrationState.current_stage }}
                                    </span>
                                </div>

                                <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                    Execute Migration
                                </h1>

                                <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-400">
                                    HivePanel creates each destination Cell independently and imports the existing Pterodactyl files without running the destination Comb installation script.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    :href="`/admin/migrations/${migrationState.id}/preflight`"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <ArrowLeft class="size-4" />
                                    Preflight
                                </Link>

                                <button
                                    v-if="canStart"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-status-success bg-status-success px-5 py-2.5 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50"
                                    :disabled="starting"
                                    @click="startMigration"
                                >
                                    <Play class="size-4" />
                                    {{ starting ? 'Queueing...' : 'Start Migration' }}
                                </button>

                                <button
                                    v-if="executionActive"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                    @click="pollStatus"
                                >
                                    <RefreshCw class="size-4" />
                                    Refresh
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="executionActive"
                            class="mt-5"
                        >
                            <div class="flex items-center justify-between gap-4 text-xs font-black">
                                <span class="text-zinc-500">
                                    Overall Progress
                                </span>

                                <span class="text-white">
                                    {{ migrationState.progress }}%
                                </span>
                            </div>

                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-900">
                                <div
                                    class="h-full rounded-full bg-hive transition-all duration-500"
                                    :style="{ width: `${migrationState.progress}%` }"
                                ></div>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Servers
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ serverState.length }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-hive/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Active
                            </div>

                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ activeCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-status-success/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Completed
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-success">
                                {{ completedCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-status-warning/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                DB Pending
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-warning">
                                {{ databasePendingCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-status-danger/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Failed
                            </div>

                            <div class="mt-1 text-2xl font-black text-status-danger">
                                {{ failedCount }}
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="migrationState.error"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-danger" />

                            <div class="text-sm font-bold leading-6 text-status-danger">
                                {{ migrationState.error }}
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-lg font-black">
                                Server Migration Progress
                            </h2>

                            <p class="mt-1 text-sm text-zinc-500">
                                Every server runs independently. A failure on one server does not stop the others.
                            </p>
                        </div>

                        <div class="divide-y divide-zinc-800">
                            <div
                                v-for="server in serverState"
                                :key="server.id"
                                class="p-5 sm:p-6"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-black text-white">
                                                {{ server.name }}
                                            </h3>

                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black"
                                                :class="statusClass(server.status)"
                                            >
                                                <component
                                                    :is="statusIcon(server.status)"
                                                    class="size-3.5"
                                                    :class="{ 'animate-spin': ['queued', 'creating_cell', 'transferring'].includes(server.status) }"
                                                />

                                                {{ statusLabel(server.status) }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                                            <span>
                                                {{ server.source_node_name }}
                                            </span>

                                            <span>
                                                {{ server.destination_node?.name || 'Destination node' }}
                                            </span>

                                            <span>
                                                {{ server.destination_owner?.email || server.owner_email }}
                                            </span>

                                            <span v-if="selectedDatabaseCount(server) > 0">
                                                {{ selectedDatabaseCount(server) }} database{{ selectedDatabaseCount(server) === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                    </div>

                                    <Link
                                        v-if="server.destination_cell_id"
                                        :href="`/admin/cells/${server.destination_cell_id}`"
                                        class="inline-flex shrink-0 items-center gap-2 text-xs font-black text-hive transition hover:text-hive-light"
                                    >
                                        Open Cell
                                        <ExternalLink class="size-3.5" />
                                    </Link>
                                </div>

                                <div class="mt-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-xs font-bold text-zinc-500">
                                            {{ server.current_stage || 'Waiting' }}
                                        </span>

                                        <span class="text-xs font-black text-white">
                                            {{ server.progress ?? 0 }}%
                                        </span>
                                    </div>

                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-900">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="server.status === 'failed'
                                                ? 'bg-status-danger'
                                                : server.status === 'database_pending'
                                                    ? 'bg-status-warning'
                                                    : server.status === 'completed'
                                                        ? 'bg-status-success'
                                                        : 'bg-hive'"
                                            :style="{ width: `${server.progress ?? 0}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <div
                                    v-if="server.error"
                                    class="mt-4 rounded-button border border-status-danger/30 bg-status-danger/10 p-3"
                                >
                                    <div class="flex items-start gap-2 text-xs font-bold leading-5 text-status-danger">
                                        <CircleAlert class="mt-0.5 size-3.5 shrink-0" />
                                        {{ server.error }}
                                    </div>
                                </div>

                                <div
                                    v-if="server.status === 'database_pending'"
                                    class="mt-4 rounded-button border border-status-warning/30 bg-status-warning/10 p-3"
                                >
                                    <div class="flex items-start gap-2">
                                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-status-warning" />

                                        <div>
                                            <div class="text-xs font-black text-status-warning">
                                                File migration completed
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                {{ selectedDatabaseCount(server) }} selected database{{ selectedDatabaseCount(server) === 1 ? '' : 's' }} still need to be transferred by the database migration worker.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="migrationState.status === 'database_pending'"
                        class="rounded-panel border border-status-warning/30 bg-status-warning/5 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <Database class="mt-0.5 size-5 shrink-0 text-status-warning" />

                            <div>
                                <h2 class="font-black text-white">
                                    File migration phase complete
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                    One or more servers have selected MySQL/MariaDB databases. Their Cells and files are now in HivePanel; the database-content transfer worker is the remaining execution phase.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="migrationState.status === 'completed'"
                        class="rounded-panel border border-status-success/30 bg-status-success/5 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <CircleCheck class="mt-0.5 size-5 shrink-0 text-status-success" />

                            <div>
                                <h2 class="font-black text-white">
                                    Migration complete
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                    All selected servers have been created and their files imported successfully.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
