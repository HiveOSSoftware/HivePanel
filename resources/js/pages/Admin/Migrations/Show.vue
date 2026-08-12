<script setup lang="ts">
import ConfirmationModal from '@/components/ui/ConfirmationModal.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    Boxes,
    CircleAlert,
    CircleCheck,
    Database,
    HardDrive,
    KeyRound,
    RefreshCw,
    Server,
    ServerCog,
    Save,
    Trash2,
    User,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    migration: any
    servers: any[]
}>()

const migrationState = ref({ ...props.migration })
const serverState = ref([...props.servers])

const refreshing = ref(false)
const showDeleteModal = ref(false)
const deleting = ref(false)
const editingSource = ref(false)

const sourceForm = useForm({
    panel_url: props.migration.panel_url ?? '',
    api_key: '',
})

const editingDatabase = ref(false)

const databaseForm = useForm({
    enabled: Boolean(props.migration.database?.enabled),
    host: props.migration.database?.host ?? '127.0.0.1',
    port: props.migration.database?.port ?? 3306,
    database: props.migration.database?.database ?? 'panel',
    username: props.migration.database?.username ?? '',
    password: '',
    preserve_passwords: Boolean(props.migration.database?.preserve_passwords ?? true),
})

const discoveredCount = computed(() => serverState.value.length)

const ownerCount = computed(() =>
    new Set(
        serverState.value
            .map((server) => server.owner_email)
            .filter(Boolean)
    ).size
)

const sourceNodeCount = computed(() =>
    new Set(
        serverState.value
            .map((server) => server.source_node_name)
            .filter(Boolean)
    ).size
)

function migrationStatusClass(status: string) {
    switch (status) {
        case 'ready':
        case 'completed':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'queued':
        case 'discovering':
            return 'border-hive/30 bg-hive/10 text-hive'

        case 'failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'
    }
}

function migrationStatusIcon(status: string) {
    switch (status) {
        case 'ready':
        case 'completed':
            return CircleCheck

        case 'queued':
        case 'discovering':
            return RefreshCw

        case 'failed':
            return CircleAlert

        default:
            return ServerCog
    }
}

function updateSourceConnection() {
    sourceForm.patch(`/admin/migrations/${migrationState.value.id}/source`, {
        preserveScroll: true,
        onSuccess: () => {
            editingSource.value = false
            sourceForm.api_key = ''

            migrationState.value = {
                ...migrationState.value,
                panel_url: sourceForm.panel_url,
                status: 'queued',
                current_stage: 'Waiting for discovery worker',
                progress: 0,
                error: null,
            }

            startPolling()
        },
    })
}

function saveDatabaseSource() {
    databaseForm.patch(`/admin/migrations/${migrationState.value.id}/database-source`, {
        preserveScroll: true,
        onSuccess: () => {
            databaseForm.password = ''

            migrationState.value = {
                ...migrationState.value,
                database: {
                    ...migrationState.value.database,
                    enabled: databaseForm.enabled,
                    host: databaseForm.host,
                    port: databaseForm.port,
                    database: databaseForm.database,
                    username: databaseForm.username,
                    has_password: migrationState.value.database?.has_password || Boolean(databaseForm.password),
                    preserve_passwords: databaseForm.preserve_passwords,
                },
            }
        },
    })
}

function refreshDiscovery() {
    refreshing.value = true

    router.post(`/admin/migrations/${migrationState.value.id}/discover`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            migrationState.value = {
                ...migrationState.value,
                status: 'queued',
                current_stage: 'Waiting for discovery worker',
                progress: 0,
                error: null,
            }

            startPolling()
        },
        onFinish: () => {
            refreshing.value = false
        },
    })
}

function deleteMigration() {
    deleting.value = true

    router.delete(`/admin/migrations/${migrationState.value.id}`, {
        onFinish: () => {
            deleting.value = false
            showDeleteModal.value = false
        },
    })
}


let pollTimer: number | undefined

const discoveryActive = computed(() =>
    ['queued', 'discovering'].includes(migrationState.value.status)
)

async function pollStatus() {
    if (!discoveryActive.value) {
        stopPolling()
        return
    }

    try {
        const response = await fetch(`/admin/migrations/${migrationState.value.id}/status`, {
            headers: {
                Accept: 'application/json',
            },
        })

        if (!response.ok) {
            return
        }

        const payload = await response.json()

        migrationState.value = payload.migration
        serverState.value = payload.servers ?? []

        if (!['queued', 'discovering'].includes(migrationState.value.status)) {
            stopPolling()
        }
    } catch {
        // Keep the current UI state and try again on the next polling interval.
    }
}

function startPolling() {
    stopPolling()
    pollStatus()

    pollTimer = window.setInterval(pollStatus, 2000)
}

function stopPolling() {
    if (!pollTimer) return

    window.clearInterval(pollTimer)
    pollTimer = undefined
}

onMounted(() => {
    if (discoveryActive.value) {
        startPolling()
    }

    if (migrationState.value.status === 'failed') {
        editingSource.value = true
    }
})

onUnmounted(() => {
    stopPolling()
})

function primaryAllocation(server: any) {
    const allocations = server.source_allocations ?? []

    return allocations.find((allocation: any) => allocation.is_default)
        ?? allocations[0]
        ?? null
}

function additionalAllocationCount(server: any) {
    const allocations = server.source_allocations ?? []

    return Math.max(allocations.length - (primaryAllocation(server) ? 1 : 0), 0)
}

function formatLimit(value: any, suffix: string) {
    const number = Number(value ?? 0)

    if (!number) return 'Unlimited'

    return `${number} ${suffix}`
}

function formatDate(value?: string) {
    if (!value) return 'Never'

    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="migrationState.name" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-start gap-3">
                                <ServerCog class="mt-1 size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ migrationState.name }}
                                        </h1>

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2.5 py-1 text-xs font-black text-hive">
                                            {{ migrationState.source_type }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-zinc-500">
                                        {{ migrationState.panel_url }}
                                    </p>

                                    <p class="mt-1 text-xs text-zinc-600">
                                        Last discovered {{ formatDate(migrationState.discovered_at) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/migrations"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>

                                <Link
                                    v-if="['ready', 'mapped'].includes(migrationState.status)"
                                    :href="`/admin/migrations/${migrationState.id}/mapping`"
                                    class="inline-flex items-center gap-2 rounded-button border border-status-success bg-status-success px-4 py-2 text-sm font-black text-black transition hover:opacity-90"
                                >
                                    <Boxes class="size-4" />
                                    {{ migrationState.status === 'mapped' ? 'Edit Mapping' : 'Configure Mapping' }}
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="refreshing || discoveryActive"
                                    @click="refreshDiscovery"
                                >
                                    <RefreshCw
                                        class="size-4"
                                        :class="{ 'animate-spin': refreshing }"
                                    />
                                    {{ discoveryActive ? 'Discovery Running' : refreshing ? 'Refreshing...' : 'Refresh Discovery' }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="discoveryActive"
                        class="rounded-panel border border-hive/30 bg-hive/5 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-4">
                            <RefreshCw class="mt-0.5 size-5 shrink-0 animate-spin text-hive" />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-sm font-black text-hive">
                                            Discovering Pterodactyl
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-400">
                                            {{ migrationState.current_stage || 'Waiting for discovery worker' }}
                                        </p>
                                    </div>

                                    <div class="text-lg font-black text-white">
                                        {{ migrationState.progress }}%
                                    </div>
                                </div>

                                <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-900">
                                    <div
                                        class="h-full rounded-full bg-hive transition-all duration-500"
                                        :style="{ width: `${migrationState.progress}%` }"
                                    ></div>
                                </div>

                                <p class="mt-3 text-xs font-bold text-zinc-600">
                                    This page updates automatically. You can leave and return without interrupting discovery.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="migrationState.error"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-5 sm:p-6"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                <div>
                                    <div class="text-sm font-black text-status-danger">
                                        Migration source error
                                    </div>

                                    <p class="mt-1 max-w-3xl text-sm leading-6 text-zinc-300">
                                        {{ migrationState.error }}
                                    </p>

                                    <p class="mt-2 text-xs font-bold text-zinc-500">
                                        Correct the source connection below and retry without recreating this migration.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-4 py-2 text-sm font-black text-status-danger transition hover:bg-status-danger/20"
                                @click="editingSource = !editingSource"
                            >
                                <KeyRound class="size-4" />
                                {{ editingSource ? 'Hide Connection' : 'Edit Source Connection' }}
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="editingSource"
                        class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                    >
                        <div class="mb-5">
                            <div class="flex items-center gap-2">
                                <KeyRound class="size-5 text-hive" />

                                <h2 class="text-lg font-black">
                                    Source Connection
                                </h2>
                            </div>

                            <p class="mt-1 text-sm text-zinc-500">
                                Update the Pterodactyl panel URL or replace the Application API key. Leave the key blank to keep the currently stored encrypted key.
                            </p>
                        </div>

                        <form
                            class="space-y-4"
                            @submit.prevent="updateSourceConnection"
                        >
                            <div>
                                <label class="text-sm font-bold text-zinc-400">
                                    Panel URL
                                </label>

                                <input
                                    v-model="sourceForm.panel_url"
                                    type="url"
                                    placeholder="https://panel.example.com"
                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 font-mono text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                />

                                <div
                                    v-if="sourceForm.errors.panel_url"
                                    class="mt-1 text-xs font-bold text-status-danger"
                                >
                                    {{ sourceForm.errors.panel_url }}
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-zinc-400">
                                    New Application API Key
                                </label>

                                <div class="relative mt-2">
                                    <KeyRound class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-600" />

                                    <input
                                        v-model="sourceForm.api_key"
                                        type="password"
                                        autocomplete="off"
                                        placeholder="Leave blank to keep existing key"
                                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 font-mono text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                    />
                                </div>

                                <div
                                    v-if="sourceForm.errors.api_key"
                                    class="mt-1 text-xs font-bold text-status-danger"
                                >
                                    {{ sourceForm.errors.api_key }}
                                </div>
                            </div>

                            <div
                                v-if="sourceForm.errors.source"
                                class="rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-xs font-bold text-status-danger"
                            >
                                {{ sourceForm.errors.source }}
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="sourceForm.processing || discoveryActive"
                            >
                                <Save class="size-4" />
                                {{ sourceForm.processing ? 'Saving & Queuing...' : 'Save & Retry Discovery' }}
                            </button>
                        </form>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Servers
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ discoveredCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Owners
                            </div>

                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ ownerCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Source Nodes
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ sourceNodeCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Status
                            </div>

                            <div class="mt-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-black"
                                    :class="migrationStatusClass(migrationState.status)"
                                >
                                    <component
                                        :is="migrationStatusIcon(migrationState.status)"
                                        class="size-3.5"
                                        :class="{ 'animate-spin': ['queued', 'discovering'].includes(migrationState.status) }"
                                    />
                                    {{ migrationState.status }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <Database class="mt-0.5 size-5 shrink-0 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-black">
                                            Pterodactyl Database Enhancement
                                        </h2>

                                        <span
                                            class="rounded-full border px-2.5 py-1 text-xs font-black"
                                            :class="migrationState.database?.enabled
                                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                : 'border-zinc-700 bg-zinc-800 text-zinc-400'"
                                        >
                                            {{ migrationState.database?.enabled ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </div>

                                    <p class="mt-1 max-w-3xl text-sm leading-6 text-zinc-500">
                                        Optional read-only access to the source panel database enables password-hash preservation and discovery of server database definitions.
                                    </p>

                                    <div
                                        v-if="migrationState.database?.enabled"
                                        class="mt-3 flex flex-wrap gap-2 text-xs font-bold text-zinc-500"
                                    >
                                        <span>
                                            {{ migrationState.database?.discovered_users ?? 0 }} database users discovered
                                        </span>
                                        <span>·</span>
                                        <span>
                                            {{ migrationState.database?.server_database_count ?? 0 }} server database definitions
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                @click="editingDatabase = !editingDatabase"
                            >
                                <Database class="size-4" />
                                {{ editingDatabase ? 'Hide Database Settings' : 'Configure Database' }}
                            </button>
                        </div>

                        <form
                            v-if="editingDatabase"
                            class="mt-5 space-y-4 border-t border-zinc-800 pt-5"
                            @submit.prevent="saveDatabaseSource"
                        >
                            <label class="flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                <input
                                    v-model="databaseForm.enabled"
                                    type="checkbox"
                                    class="mt-0.5 size-4 accent-hive"
                                />

                                <div>
                                    <div class="text-sm font-black text-white">
                                        Enable panel database migration enhancements
                                    </div>

                                    <p class="mt-1 text-xs leading-5 text-zinc-500">
                                        Use a read-only MySQL/MariaDB account where possible.
                                    </p>
                                </div>
                            </label>

                            <div
                                v-if="databaseForm.enabled"
                                class="grid gap-3 sm:grid-cols-2"
                            >
                                <div>
                                    <label class="text-xs font-black text-zinc-500">Host</label>

                                    <input
                                        v-model="databaseForm.host"
                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                    />
                                </div>

                                <div>
                                    <label class="text-xs font-black text-zinc-500">Port</label>

                                    <input
                                        v-model.number="databaseForm.port"
                                        type="number"
                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                    />
                                </div>

                                <div>
                                    <label class="text-xs font-black text-zinc-500">Database</label>

                                    <input
                                        v-model="databaseForm.database"
                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                    />
                                </div>

                                <div>
                                    <label class="text-xs font-black text-zinc-500">Username</label>

                                    <input
                                        v-model="databaseForm.username"
                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                    />
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="text-xs font-black text-zinc-500">Password</label>

                                    <input
                                        v-model="databaseForm.password"
                                        type="password"
                                        autocomplete="off"
                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                        :placeholder="migrationState.database?.has_password ? 'Leave blank to keep saved password' : 'Required'"
                                    />
                                </div>
                            </div>

                            <label
                                v-if="databaseForm.enabled"
                                class="flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
                            >
                                <input
                                    v-model="databaseForm.preserve_passwords"
                                    type="checkbox"
                                    class="mt-0.5 size-4 accent-hive"
                                />

                                <div>
                                    <div class="text-sm font-black text-white">
                                        Preserve compatible user passwords
                                    </div>

                                    <p class="mt-1 text-xs leading-5 text-zinc-500">
                                        Compatible source hashes are copied directly only when a missing HivePanel user is created.
                                    </p>
                                </div>
                            </label>

                            <div
                                v-if="databaseForm.errors.database"
                                class="rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-xs font-bold text-status-danger"
                            >
                                {{ databaseForm.errors.database }}
                            </div>

                            <div
                                v-if="databaseForm.errors.password"
                                class="rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-xs font-bold text-status-danger"
                            >
                                {{ databaseForm.errors.password }}
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="databaseForm.processing || discoveryActive"
                                >
                                    <Save class="size-4" />
                                    {{ databaseForm.processing ? 'Testing & Saving...' : 'Test & Save Database' }}
                                </button>

                                <p class="text-xs font-bold text-zinc-600">
                                    Refresh Discovery after saving to import password metadata and server database definitions.
                                </p>
                            </div>
                        </form>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-lg font-black">
                                Discovered Servers
                            </h2>

                            <p class="mt-1 text-sm text-zinc-500">
                                This is the source inventory. The next phase will map these servers to HivePanel owners, Nodes, Combs and allocations.
                            </p>
                        </div>

                        <div
                            v-if="serverState.length === 0"
                            class="p-10 text-center"
                        >
                            <Server class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No source servers discovered
                            </h2>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-800">
                                <thead class="bg-[#0d0f11]">
                                    <tr>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Server</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Owner</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Source Node</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Egg</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Allocation</th>
                                        <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide text-zinc-500">Limits</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-zinc-800">
                                    <tr
                                        v-for="server in serverState"
                                        :key="server.id"
                                        class="transition hover:bg-surface-light/40"
                                    >
                                        <td class="px-5 py-4">
                                            <div class="font-black text-white">
                                                {{ server.name }}
                                            </div>

                                            <div class="mt-1 font-mono text-xs text-zinc-500">
                                                {{ server.source_uuid || server.source_server_id }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2 text-sm font-bold text-zinc-300">
                                                <User class="size-4 text-zinc-500" />
                                                {{ server.owner_email || 'Unknown owner' }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 text-sm font-bold text-zinc-300">
                                            {{ server.source_node_name || 'Unknown node' }}
                                        </td>

                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-2 rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-300">
                                                <Boxes class="size-3" />
                                                {{ server.source_egg_name || 'Unknown egg' }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div v-if="primaryAllocation(server)">
                                                <div class="font-mono text-sm font-black text-white">
                                                    {{ primaryAllocation(server).ip }}:{{ primaryAllocation(server).port }}
                                                </div>

                                                <div class="mt-1 text-xs text-zinc-500">
                                                    +{{ additionalAllocationCount(server) }} additional
                                                </div>
                                            </div>

                                            <span v-else class="text-sm font-bold text-status-warning">
                                                No allocation discovered
                                            </span>
                                        </td>

                                        <td class="px-5 py-4">
                                            <div class="grid min-w-[230px] grid-cols-3 gap-2">
                                                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2">
                                                    <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                        Memory
                                                    </div>

                                                    <div class="mt-1 whitespace-nowrap text-xs font-black text-zinc-300">
                                                        {{ formatLimit(server.source_metadata?.limits?.memory, 'MB') }}
                                                    </div>
                                                </div>

                                                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2">
                                                    <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                        CPU
                                                    </div>

                                                    <div class="mt-1 whitespace-nowrap text-xs font-black text-zinc-300">
                                                        {{ formatLimit(server.source_metadata?.limits?.cpu, '%') }}
                                                    </div>
                                                </div>

                                                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2">
                                                    <div class="flex items-center gap-1 text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                        <HardDrive class="size-3" />
                                                        Disk
                                                    </div>

                                                    <div class="mt-1 whitespace-nowrap text-xs font-black text-zinc-300">
                                                        {{ formatLimit(server.source_metadata?.limits?.disk, 'MB') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-panel border border-status-danger/30 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-black">
                                    Remove Migration
                                </h2>

                                <p class="mt-1 text-sm text-zinc-500">
                                    Removes the migration job, discovered inventory and encrypted source credentials.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-status-danger bg-status-danger px-4 py-2 text-sm font-black text-white transition hover:opacity-90"
                                @click="showDeleteModal = true"
                            >
                                <Trash2 class="size-4" />
                                Delete Migration
                            </button>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <ConfirmationModal
            :open="showDeleteModal"
            title="Delete Migration?"
            :description="`Delete '${migrationState.name}' and all discovered migration inventory? Source credentials stored with this job will also be removed.`"
            confirm-text="Delete Migration"
            cancel-text="Cancel"
            :danger="true"
            :loading="deleting"
            @cancel="showDeleteModal = false"
            @confirm="deleteMigration"
        />
    </AppLayout>
</template>
