<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowLeft,
    CircleAlert,
    CircleCheck,
    Clipboard,
    Database,
    Eye,
    EyeOff,
    ExternalLink,
    LoaderCircle,
    LockKeyhole,
    Play,
    RefreshCw,
    Server,
    ShieldCheck,
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
const startingDatabases = ref(false)
const verifying = ref(false)
const revealedPasswords = ref<Record<string, boolean>>({})
const copiedCredential = ref<string | null>(null)
const retryingServers = ref<Record<string, boolean>>({})
const finalising = ref(false)
const showFinaliseModal = ref(false)
const finalisationError = ref<string | null>(null)
let pollTimer: number | undefined

const executionActive = computed(() =>
    [
        'running',
        'database_pending',
    ].includes(
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
            [
                'failed',
                'database_failed',
            ].includes(server.status)
    ).length
)

const databasePendingCount = computed(() =>
    serverState.value.filter(
        (server) =>
            server.status === 'database_pending'
    ).length
)

const lifecycleSteps = [
    'Discovery',
    'Mapping',
    'Preflight',
    'Migration',
    'Verification',
    'Finalised',
]

const lifecycleStep = computed(() => {
    switch (migrationState.value.status) {
        case 'finalised':
            return 5

        case 'verified':
        case 'verification_failed':
            return 4

        default:
            return 3
    }
})

const canFinalise = computed(() =>
    migrationState.value.status === 'verified'
    && verification.value?.verified === true
)

const finalisation = computed(() =>
    migrationState.value.finalisation
    ?? {}
)

const canVerify = computed(() =>
    [
        'completed',
        'completed_with_errors',
        'verified',
        'verification_failed',
    ].includes(
        migrationState.value.status
    )
)

const verification = computed(() =>
    migrationState.value.verification
    ?? {}
)

const canStartDatabases = computed(() =>
    migrationState.value.status === 'database_pending'
    && databasePendingCount.value > 0
)

const activeCount = computed(() =>
    serverState.value.filter(
        (server) =>
            [
                'queued',
                'creating_cell',
                'transferring',
                'database_transferring',
            ].includes(server.status)
    ).length
)

function lifecycleStepClass(index: number) {
    if (index < lifecycleStep.value) {
        return 'border-status-success/40 bg-status-success/15 text-status-success'
    }

    if (index === lifecycleStep.value) {
        if (
            [
                'failed',
                'database_failed',
                'verification_failed',
                'completed_with_errors',
            ].includes(migrationState.value.status)
        ) {
            return 'border-status-danger/40 bg-status-danger/15 text-status-danger'
        }

        return migrationState.value.status === 'finalised'
            ? 'border-status-success/40 bg-status-success/15 text-status-success'
            : 'border-hive/40 bg-hive/15 text-hive'
    }

    return 'border-zinc-800 bg-[#0d0f11] text-zinc-700'
}

function lifecycleLineClass(index: number) {
    return index < lifecycleStep.value
        ? 'bg-status-success/50'
        : 'bg-zinc-800'
}

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

function startDatabaseMigration() {
    startingDatabases.value = true

    router.post(
        `/admin/migrations/${migrationState.value.id}/execution/databases/start`,
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                startPolling()
            },

            onFinish: () => {
                startingDatabases.value = false
            },
        }
    )
}

async function finaliseMigration() {
    if (
        !canFinalise.value
        || finalising.value
    ) {
        return
    }

    finalising.value = true
    finalisationError.value = null

    try {
        const response = await fetch(
            `/admin/migrations/${migrationState.value.id}/execution/finalise`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({}),
            }
        )

        const payload = await response.json()

        if (!response.ok) {
            throw new Error(
                payload.message
                ?? 'Migration finalisation failed.'
            )
        }

        migrationState.value = payload.migration
        showFinaliseModal.value = false
    } catch (error: any) {
        finalisationError.value =
            error?.message
            ?? 'Migration finalisation failed.'
    } finally {
        finalising.value = false
    }
}

async function runVerification() {
    if (!canVerify.value || verifying.value) {
        return
    }

    verifying.value = true

    try {
        const response = await fetch(
            `/admin/migrations/${migrationState.value.id}/execution/verify`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({}),
            }
        )

        const payload = await response.json()

        if (!response.ok) {
            throw new Error(
                payload.message
                ?? 'Post-migration verification failed to run.'
            )
        }

        migrationState.value = payload.migration
    } catch (error: any) {
        migrationState.value = {
            ...migrationState.value,
            verification: {
                ...(verification.value ?? {}),
                status: 'verification_failed',
                verified: false,
                checked_at: new Date().toISOString(),
                error: error?.message
                    ?? 'Post-migration verification failed to run.',
            },
        }
    } finally {
        verifying.value = false
    }
}

function verificationCheckClass(status: string) {
    switch (status) {
        case 'passed':
            return 'border-status-success/20 bg-status-success/5'

        case 'warning':
            return 'border-status-warning/20 bg-status-warning/5'

        case 'failed':
            return 'border-status-danger/20 bg-status-danger/5'

        default:
            return 'border-zinc-800 bg-black/20'
    }
}

function verificationCheckTextClass(status: string) {
    switch (status) {
        case 'passed':
            return 'text-status-success'

        case 'warning':
            return 'text-status-warning'

        case 'failed':
            return 'text-status-danger'

        default:
            return 'text-zinc-500'
    }
}

function credentialKey(server: any, database: any) {
    return `${server.id}:${database.destination?.credential_key ?? database.source?.id ?? database.source?.database ?? 'database'}`
}

function credentialPassword(server: any, database: any) {
    return server.database_credentials?.[database.destination?.credential_key]?.password
        ?? ''
}

function toggleCredential(server: any, database: any) {
    const key = credentialKey(server, database)

    revealedPasswords.value[key] =
        !revealedPasswords.value[key]
}

async function copyCredential(server: any, database: any) {
    const password = credentialPassword(server, database)

    if (!password) {
        return
    }

    await navigator.clipboard.writeText(
        password
    )

    const key = credentialKey(server, database)
    copiedCredential.value = key

    window.setTimeout(() => {
        if (copiedCredential.value === key) {
            copiedCredential.value = null
        }
    }, 1800)
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

        if (!executionActive.value) {
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

        case 'database_transferring':
            return 'Transferring Database'

        case 'database_failed':
            return 'Database Failed'

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
        case 'database_failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        case 'database_pending':
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

        case 'queued':
        case 'creating_cell':
        case 'transferring':
        case 'database_transferring':
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
        case 'database_failed':
            return CircleAlert

        case 'database_pending':
            return Database

        case 'database_transferring':
            return LoaderCircle

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

function databaseLabel(database: any) {
    return (
        database?.source?.database
        ?? database?.source?.username
        ?? 'Database'
    )
}

function databaseStatusClass(status: string) {
    switch (status) {
        case 'completed':
            return 'text-status-success'

        case 'failed':
            return 'text-status-danger'

        case 'transferring':
            return 'text-hive'

        default:
            return 'text-zinc-500'
    }
}

function retryServer(server: any) {
    if (
        server.status !== 'failed'
        || retryingServers.value[server.id]
    ) {
        return
    }

    retryingServers.value[server.id] = true

    router.post(
        `/admin/migrations/${migrationState.value.id}/execution/servers/${server.id}/retry`,
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                server.status = 'queued'
                server.current_stage =
                    server.destination_cell_id
                        ? 'Retry queued; destination Cell will be reused'
                        : 'Retry queued; destination Cell will be created'
                server.error = null

                migrationState.value = {
                    ...migrationState.value,
                    status: 'running',
                    current_stage: `Retrying ${server.name}`,
                    error: null,
                    verification: {},
                }

                startPolling()
            },

            onFinish: () => {
                retryingServers.value[server.id] = false
            },
        }
    )
}

function retryDatabases(server: any) {
    router.post(
        `/admin/migrations/${migrationState.value.id}/execution/servers/${server.id}/databases/retry`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                startPolling()
            },
        }
    )
}

function historyLabel(item: any) {
    switch (item.type) {
        case 'server_retry':
            return `Retried ${item.server_name ?? 'server'}`

        case 'database_retry':
            return `Retried databases for ${item.server_name ?? 'server'}`

        case 'verification':
            return item.status === 'verified'
                ? 'Migration verification passed'
                : 'Migration verification found issues'

        case 'finalisation':
            return 'Migration finalised'

        default:
            return item.type ?? 'Migration event'
    }
}

function historyDescription(item: any) {
    switch (item.type) {
        case 'server_retry':
            return item.reused_destination_cell
                ? 'Reused the existing destination Cell and restarted file import.'
                : 'Queued Cell creation and file import again.'

        case 'database_retry':
            return `${item.database_count ?? 0} database retry/retries queued.`

        case 'verification':
            return `${item.passed ?? 0} passed · ${item.warnings ?? 0} warnings · ${item.failed ?? 0} failed`

        case 'finalisation':
            return item.message
                ?? 'Stored source credentials were removed.'

        default:
            return ''
    }
}

function formatHistoryDate(value: string) {
    if (!value) {
        return ''
    }

    return new Intl.DateTimeFormat(
        undefined,
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        }
    ).format(new Date(value))
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
                                                : ['completed', 'verified', 'finalised'].includes(migrationState.status)
                                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                    : ['completed_with_errors', 'failed', 'verification_failed'].includes(migrationState.status)
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
                                    v-if="[
                                        'execution_ready',
                                        'running',
                                        'database_pending',
                                        'database_transferring',
                                        'database_failed',
                                        'failed',
                                    ].includes(migrationState.status)"
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
                                    v-if="canStartDatabases"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-status-warning bg-status-warning px-5 py-2.5 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50"
                                    :disabled="startingDatabases"
                                    @click="startDatabaseMigration"
                                >
                                    <Database class="size-4" />
                                    {{ startingDatabases ? 'Queueing Databases...' : 'Start Database Transfer' }}
                                </button>

                                <button
                                    v-if="canVerify"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-2.5 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="verifying"
                                    @click="runVerification"
                                >
                                    <ShieldCheck
                                        class="size-4"
                                        :class="{ 'animate-pulse': verifying }"
                                    />
                                    {{ verifying
                                        ? 'Verifying...'
                                        : verification.checked_at
                                            ? 'Run Verification Again'
                                            : 'Verify Migration' }}
                                </button>

                                <button
                                    v-if="canFinalise"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-status-success bg-status-success px-5 py-2.5 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50"
                                    :disabled="finalising"
                                    @click="showFinaliseModal = true"
                                >
                                    <LockKeyhole
                                        class="size-4"
                                        :class="{ 'animate-pulse': finalising }"
                                    />
                                    {{ finalising
                                        ? 'Finalising...'
                                        : 'Finalise Migration' }}
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

                        <div class="mt-5 border-t border-zinc-800 pt-5">
                            <div class="flex items-center">
                                <template
                                    v-for="(step, index) in lifecycleSteps"
                                    :key="step"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <div
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full border text-[10px] font-black"
                                            :class="lifecycleStepClass(index)"
                                        >
                                            <CircleCheck
                                                v-if="index < lifecycleStep || (
                                                    migrationState.status === 'finalised'
                                                    && index === 5
                                                )"
                                                class="size-3.5"
                                            />

                                            <span v-else>
                                                {{ index + 1 }}
                                            </span>
                                        </div>

                                        <span
                                            class="hidden text-[10px] font-black uppercase tracking-wide sm:block"
                                            :class="index <= lifecycleStep
                                                ? 'text-zinc-400'
                                                : 'text-zinc-700'"
                                        >
                                            {{ step }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="index < lifecycleSteps.length - 1"
                                        class="mx-2 h-px min-w-2 flex-1 sm:mx-3"
                                        :class="lifecycleLineClass(index)"
                                    ></div>
                                </template>
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
                        v-if="finalisationError"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-danger" />

                            <div>
                                <div class="text-sm font-black text-status-danger">
                                    Migration finalisation failed
                                </div>

                                <p class="mt-1 text-sm leading-6 text-zinc-300">
                                    {{ finalisationError }}
                                </p>
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
                                                    :class="{ 'animate-spin': ['queued', 'creating_cell', 'transferring', 'database_transferring'].includes(server.status) }"
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
                                            :class="['failed', 'database_failed'].includes(server.status)
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
                                    v-if="server.status === 'failed'"
                                    class="mt-4 rounded-button border border-status-warning/20 bg-status-warning/5 p-3"
                                >
                                    <div class="flex items-start gap-2">
                                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-status-warning" />

                                        <div>
                                            <div class="text-xs font-black text-status-warning">
                                                Retry available
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                {{ server.destination_cell_id
                                                    ? 'The existing destination Cell will be reused. HivePanel will restart only the file-import phase and will not create a duplicate Cell.'
                                                    : 'The failure happened before a destination Cell was retained. HivePanel will retry the normal Cell creation and file-import path.' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="selectedDatabaseCount(server) > 0"
                                    class="mt-4 overflow-hidden rounded-button border border-zinc-800 bg-black/20"
                                >
                                    <div class="border-b border-zinc-800 px-4 py-3">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Databases
                                        </div>
                                    </div>

                                    <div class="divide-y divide-zinc-800">
                                        <div
                                            v-for="(database, databaseIndex) in (server.database_plan ?? []).filter((item: any) => item.selected)"
                                            :key="database.source?.id ?? database.source?.database ?? databaseIndex"
                                            class="p-4"
                                        >
                                            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                                <div>
                                                    <div class="text-sm font-black text-white">
                                                        {{ databaseLabel(database) }}
                                                    </div>

                                                    <div class="mt-1 text-xs font-bold" :class="databaseStatusClass(database.status)">
                                                        {{ database.status || 'pending' }}
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="database.status === 'completed' && database.destination"
                                                    class="grid gap-x-5 gap-y-1 text-xs text-zinc-500 sm:grid-cols-2"
                                                >
                                                    <span>
                                                        Host:
                                                        <strong class="text-zinc-300">
                                                            {{ database.destination.host }}:{{ database.destination.port }}
                                                        </strong>
                                                    </span>

                                                    <span>
                                                        Database:
                                                        <strong class="text-zinc-300">
                                                            {{ database.destination.database }}
                                                        </strong>
                                                    </span>

                                                    <span>
                                                        Username:
                                                        <strong class="text-zinc-300">
                                                            {{ database.destination.username }}
                                                        </strong>
                                                    </span>

                                                    <span>
                                                        Password:
                                                        <span class="ml-1 inline-flex items-center gap-1.5">
                                                            <strong class="font-mono text-zinc-300">
                                                                {{ credentialPassword(server, database)
                                                                    ? (
                                                                        revealedPasswords[credentialKey(server, database)]
                                                                            ? credentialPassword(server, database)
                                                                            : '••••••••••••••••'
                                                                    )
                                                                    : 'Unavailable' }}
                                                            </strong>

                                                            <button
                                                                v-if="credentialPassword(server, database)"
                                                                type="button"
                                                                class="text-zinc-500 transition hover:text-hive"
                                                                :title="revealedPasswords[credentialKey(server, database)] ? 'Hide password' : 'Reveal password'"
                                                                @click="toggleCredential(server, database)"
                                                            >
                                                                <EyeOff
                                                                    v-if="revealedPasswords[credentialKey(server, database)]"
                                                                    class="size-3.5"
                                                                />
                                                                <Eye
                                                                    v-else
                                                                    class="size-3.5"
                                                                />
                                                            </button>

                                                            <button
                                                                v-if="credentialPassword(server, database)"
                                                                type="button"
                                                                class="text-zinc-500 transition hover:text-hive"
                                                                title="Copy password"
                                                                @click="copyCredential(server, database)"
                                                            >
                                                                <CircleCheck
                                                                    v-if="copiedCredential === credentialKey(server, database)"
                                                                    class="size-3.5 text-status-success"
                                                                />
                                                                <Clipboard
                                                                    v-else
                                                                    class="size-3.5"
                                                                />
                                                            </button>
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>

                                            <div
                                                v-if="database.error"
                                                class="mt-2 text-xs font-bold leading-5 text-status-danger"
                                            >
                                                {{ database.error }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    v-if="server.status === 'failed'"
                                    type="button"
                                    class="mt-4 inline-flex items-center gap-2 rounded-button border border-status-warning bg-status-warning px-4 py-2 text-xs font-black text-black transition hover:opacity-90 disabled:opacity-50"
                                    :disabled="retryingServers[server.id]"
                                    @click="retryServer(server)"
                                >
                                    <RefreshCw
                                        class="size-3.5"
                                        :class="{ 'animate-spin': retryingServers[server.id] }"
                                    />
                                    {{ retryingServers[server.id]
                                        ? 'Queueing Retry...'
                                        : server.destination_cell_id
                                            ? 'Retry File Migration'
                                            : 'Retry Server Migration' }}
                                </button>

                                <button
                                    v-if="server.status === 'database_failed'"
                                    type="button"
                                    class="mt-4 inline-flex items-center gap-2 rounded-button border border-status-warning bg-status-warning px-4 py-2 text-xs font-black text-black transition hover:opacity-90"
                                    @click="retryDatabases(server)"
                                >
                                    <RefreshCw class="size-3.5" />
                                    Retry Failed Databases
                                </button>

                                <div
                                    v-if="['database_pending', 'database_transferring'].includes(server.status)"
                                    class="mt-4 rounded-button border border-status-warning/30 bg-status-warning/10 p-3"
                                >
                                    <div class="flex items-start gap-2">
                                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-status-warning" />

                                        <div>
                                            <div class="text-xs font-black text-status-warning">
                                                {{ server.status === 'database_transferring' ? 'Database migration running' : 'File migration completed' }}
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                {{ server.status === 'database_transferring'
                                                    ? 'HivePanel is exporting and importing the selected database content now.'
                                                    : `${selectedDatabaseCount(server)} selected database${selectedDatabaseCount(server) === 1 ? '' : 's'} waiting for the database migration worker.` }}
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
                        v-if="['completed', 'verified', 'verification_failed'].includes(migrationState.status)"
                        class="rounded-panel border border-status-success/30 bg-status-success/5 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <CircleCheck class="mt-0.5 size-5 shrink-0 text-status-success" />

                            <div>
                                <h2 class="font-black text-white">
                                    Migration transfer complete
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                    All selected server transfer phases have finished. Source data has not been automatically deleted.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="verification.checked_at"
                        class="overflow-hidden rounded-panel border bg-surface"
                        :class="verification.verified
                            ? 'border-status-success/30'
                            : 'border-status-danger/30'"
                    >
                        <div
                            class="border-b p-5 sm:p-6"
                            :class="verification.verified
                                ? 'border-status-success/20 bg-status-success/5'
                                : 'border-status-danger/20 bg-status-danger/5'"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex items-start gap-3">
                                    <ShieldCheck
                                        class="mt-0.5 size-5 shrink-0"
                                        :class="verification.verified
                                            ? 'text-status-success'
                                            : 'text-status-danger'"
                                    />

                                    <div>
                                        <h2 class="font-black text-white">
                                            {{ verification.verified
                                                ? 'Migration verified'
                                                : 'Verification found issues' }}
                                        </h2>

                                        <p class="mt-1 text-sm leading-6 text-zinc-400">
                                            {{ verification.verified
                                                ? 'HivePanel verified the destination Cells, files, install state, Worker definitions, allocations and selected databases.'
                                                : `${verification.summary?.failed ?? 0} verification check(s) require attention. The completed transfer has not been rolled back or deleted.` }}
                                        </p>

                                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-bold text-zinc-500">
                                            <span>{{ verification.summary?.passed ?? 0 }} passed</span>
                                            <span>{{ verification.summary?.warnings ?? 0 }} warnings</span>
                                            <span>{{ verification.summary?.failed ?? 0 }} failed</span>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-button border border-zinc-700 bg-[#0d0f11] px-4 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                    :disabled="verifying"
                                    @click="runVerification"
                                >
                                    <RefreshCw class="size-3.5" />
                                    {{ verifying ? 'Verifying...' : 'Run Again' }}
                                </button>
                            </div>
                        </div>

                        <div class="divide-y divide-zinc-800">
                            <div
                                v-for="serverReport in verification.servers ?? []"
                                :key="serverReport.server_id"
                                class="p-5 sm:p-6"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div class="font-black text-white">
                                            {{ serverReport.name }}
                                        </div>

                                        <Link
                                            v-if="serverReport.cell_id"
                                            :href="`/admin/cells/${serverReport.cell_id}`"
                                            class="mt-1 inline-flex items-center gap-1 text-xs font-black text-hive hover:text-hive-light"
                                        >
                                            Open Cell
                                            <ExternalLink class="size-3" />
                                        </Link>
                                    </div>

                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide"
                                        :class="serverReport.status === 'passed'
                                            ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                            : serverReport.status === 'warning'
                                                ? 'border-status-warning/30 bg-status-warning/10 text-status-warning'
                                                : 'border-status-danger/30 bg-status-danger/10 text-status-danger'"
                                    >
                                        {{ serverReport.status }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                    <div
                                        v-for="check in serverReport.checks ?? []"
                                        :key="check.key"
                                        class="rounded-button border p-3"
                                        :class="verificationCheckClass(check.status)"
                                    >
                                        <div
                                            class="text-xs font-black"
                                            :class="verificationCheckTextClass(check.status)"
                                        >
                                            {{ check.label }}
                                        </div>

                                        <p class="mt-1 text-xs leading-5 text-zinc-500">
                                            {{ check.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section
                        v-if="migrationState.status === 'finalised'"
                        class="rounded-panel border border-status-success/30 bg-status-success/5 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <LockKeyhole class="mt-0.5 size-5 shrink-0 text-status-success" />

                            <div>
                                <h2 class="font-black text-white">
                                    Migration finalised
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                    Stored source API, SFTP/SSH and migration database credentials have been removed from HivePanel. Source server files and source databases were not deleted.
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                                    <span class="rounded-full border border-status-success/20 bg-status-success/10 px-2.5 py-1 text-status-success">
                                        Credentials removed
                                    </span>

                                    <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-zinc-400">
                                        Source files retained
                                    </span>

                                    <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-zinc-400">
                                        Source databases retained
                                    </span>
                                </div>

                                <p class="mt-3 text-xs leading-5 text-zinc-600">
                                    This migration is now read-only for historical reference. Retrying source transfers or discovery would require new source credentials in a new migration.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="(migrationState.execution_history ?? []).length > 0"
                        class="overflow-hidden rounded-panel border border-zinc-800 bg-surface"
                    >
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-lg font-black">
                                Migration History
                            </h2>

                            <p class="mt-1 text-sm text-zinc-500">
                                Recovery actions and verification runs are retained with this migration.
                            </p>
                        </div>

                        <div class="divide-y divide-zinc-800">
                            <div
                                v-for="(item, index) in [...(migrationState.execution_history ?? [])].reverse()"
                                :key="`${item.at ?? index}:${index}`"
                                class="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:justify-between sm:px-6"
                            >
                                <div>
                                    <div class="text-sm font-black text-white">
                                        {{ historyLabel(item) }}
                                    </div>

                                    <p
                                        v-if="historyDescription(item)"
                                        class="mt-1 text-xs leading-5 text-zinc-500"
                                    >
                                        {{ historyDescription(item) }}
                                    </p>

                                    <p
                                        v-if="item.previous_error"
                                        class="mt-1 text-xs leading-5 text-status-danger"
                                    >
                                        Previous error: {{ item.previous_error }}
                                    </p>
                                </div>

                                <div class="shrink-0 text-xs font-bold text-zinc-600">
                                    {{ formatHistoryDate(item.at) }}
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="showFinaliseModal"
            title="Finalise Migration?"
            description="This permanently removes the stored source API key, SFTP/SSH credentials, private keys, source database credentials and other source authentication data from this migration. Source server files and databases will not be deleted. Once finalised, this migration cannot be retried without creating a new migration with new source credentials."
            confirm-text="Finalise Migration"
            cancel-text="Cancel"
            :danger="true"
            :loading="finalising"
            @cancel="showFinaliseModal = false"
            @confirm="finaliseMigration"
        />
    </AppLayout>
</template>
