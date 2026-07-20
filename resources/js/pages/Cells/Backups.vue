<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    Archive,
    CalendarClock,
    Database,
    Download,
    FolderOpen,
    HardDrive,
    Lock,
    PackageOpen,
    Plus,
    RefreshCw,
    RotateCcw,
    Trash,
    Unlock,
} from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
    cell: any
}>()

type BackupStatus =
    | 'pending'
    | 'creating'
    | 'completed'
    | 'restoring'
    | 'deleting'
    | 'failed'

type BackupMountStatus =
    | 'mounting'
    | 'mounted'
    | 'unmounting'
    | 'failed'

type BackupMount = {
    id: string
    status: BackupMountStatus
    extracted_size?: number | null
    mounted_at?: string | null
    expires_at?: string | null
}

type Backup = {
    id: string
    name: string
    status: BackupStatus
    archive_name?: string | null
    size?: number
    checksum?: string | null
    checksum_algorithm?: string | null
    is_locked: boolean
    is_automatic: boolean
    ignored_files?: string[] | null
    failure_reason?: string | null
    started_at?: string | null
    completed_at?: string | null
    created_at?: string | null
    updated_at?: string | null
    active_mount?: BackupMount | null
}

type BackupAction = 'restore' | 'delete'

const backups = ref<Backup[]>([])
const loading = ref(false)
const actionLoading = ref('')
const error = ref('')
const toastMessage = ref('')

const confirmOpen = ref(false)
const selectedBackup = ref<Backup | null>(null)
const selectedAction = ref<BackupAction>('restore')

let refreshTimer: number | null = null
let toastTimer: number | null = null

const cellId = computed(() => props.cell?.id)

const totalStorage = computed(() =>
    backups.value.reduce((total, backup) => total + (backup.size ?? 0), 0),
)

const manualBackupCount = computed(() =>
    backups.value.filter((backup) => !backup.is_automatic).length,
)

const automaticBackupCount = computed(() =>
    backups.value.filter((backup) => backup.is_automatic).length,
)

const lastBackup = computed(() =>
    [...backups.value]
        .filter((backup) => backup.status === 'completed')
        .sort((a, b) => {
            const aTime = new Date(a.completed_at ?? a.created_at ?? 0).getTime()
            const bTime = new Date(b.completed_at ?? b.created_at ?? 0).getTime()

            return bTime - aTime
        })[0],
)

const hasProcessingBackups = computed(() =>
    backups.value.some((backup) => isProcessing(backup)),
)

const confirmTitle = computed(() =>
    selectedAction.value === 'restore' ? 'Restore Backup' : 'Delete Backup',
)

const confirmButton = computed(() =>
    selectedAction.value === 'restore' ? 'Restore' : 'Delete',
)

const confirmMessage = computed(() => {
    if (!selectedBackup.value) return ''

    if (selectedAction.value === 'restore') {
        return `Restore "${selectedBackup.value.name}"? This will overwrite matching files currently on the server.`
    }

    return `Delete "${selectedBackup.value.name}"? The archive will be removed and this cannot be undone.`
})

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function showToast(message: string) {
    toastMessage.value = message

    if (toastTimer !== null) {
        window.clearTimeout(toastTimer)
    }

    toastTimer = window.setTimeout(() => {
        toastMessage.value = ''
        toastTimer = null
    }, 3000)
}

async function responseError(response: Response, fallback: string) {
    try {
        const data = await response.json()

        return data.message
            ?? data.error
            ?? Object.values(data.errors ?? {}).flat().join(' ')
            ?? fallback
    } catch {
        const text = await response.text()
        return text || fallback
    }
}

function formatBytes(bytes?: number) {
    const value = bytes ?? 0

    if (value >= 1024 * 1024 * 1024) {
        return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
    }

    if (value >= 1024 * 1024) {
        return `${(value / 1024 / 1024).toFixed(2)} MB`
    }

    if (value >= 1024) {
        return `${(value / 1024).toFixed(2)} KB`
    }

    return `${value} B`
}

function formatDate(value?: string | null) {
    if (!value) return 'Unknown'

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return 'Unknown'
    }

    return date.toLocaleString()
}

function formatRelativeDate(value?: string | null) {
    if (!value) return 'Never'

    const timestamp = new Date(value).getTime()

    if (Number.isNaN(timestamp)) {
        return 'Never'
    }

    const diff = Math.max(0, Date.now() - timestamp)
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)

    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes} min${minutes === 1 ? '' : 's'} ago`
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`

    return `${days} day${days === 1 ? '' : 's'} ago`
}

function statusLabel(status: BackupStatus) {
    const labels: Record<BackupStatus, string> = {
        pending: 'Pending',
        creating: 'Creating',
        completed: 'Completed',
        restoring: 'Restoring',
        deleting: 'Deleting',
        failed: 'Failed',
    }

    return labels[status] ?? status
}

function statusClass(status: BackupStatus) {
    if (status === 'completed') {
        return 'border-status-success/30 bg-status-success/10 text-status-success'
    }

    if (status === 'failed') {
        return 'border-status-danger/30 bg-status-danger/10 text-status-danger'
    }

    if (status === 'restoring' || status === 'deleting') {
        return 'border-status-warning/30 bg-status-warning/10 text-status-warning'
    }

    return 'border-hive/30 bg-hive/10 text-hive'
}

function isProcessing(backup: Backup) {
    return ['pending', 'creating', 'restoring', 'deleting'].includes(backup.status)
}

function canDownload(backup: Backup) {
    return backup.status === 'completed'
}

function canRestore(backup: Backup) {
    return backup.status === 'completed'
}

function canDelete(backup: Backup) {
    return !backup.is_locked && !isProcessing(backup)
}

function isMountProcessing(backup: Backup) {
    return (
        backup.active_mount?.status === 'mounting' ||
        backup.active_mount?.status === 'unmounting'
    )
}

function isMounted(backup: Backup) {
    return backup.active_mount?.status === 'mounted'
}

function canMount(backup: Backup) {
    return (
        backup.status === 'completed' &&
        !backup.active_mount &&
        !isProcessing(backup)
    )
}

function canUnmount(backup: Backup) {
    return (
        backup.active_mount?.status === 'mounted' &&
        !isProcessing(backup)
    )
}

function backupLoadingKey(action: string, backup: Backup) {
    return `${action}:${backup.id}`
}

function isBackupActionLoading(action: string, backup: Backup) {
    return actionLoading.value === backupLoadingKey(action, backup)
}

function extractBackups(data: any): Backup[] {
    if (Array.isArray(data)) {
        return data
    }

    if (Array.isArray(data?.backups)) {
        return data.backups
    }

    if (Array.isArray(data?.data)) {
        return data.data
    }

    if (Array.isArray(data?.backups?.data)) {
        return data.backups.data
    }

    return []
}

async function loadBackups(silent = false) {
    if (!cellId.value) return

    if (!silent) {
        loading.value = true
        error.value = ''
    }

    try {
        const response = await fetch(`/cells/${cellId.value}/backups-json`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            if (!silent) {
                error.value = await responseError(response, 'Unable to load backups.')
            }
            return
        }

        backups.value = extractBackups(await response.json())
    } catch {
        if (!silent) {
            error.value = 'Unable to connect to the server.'
        }
    } finally {
        if (!silent) {
            loading.value = false
        }
    }
}

async function createBackup() {
    if (!cellId.value) return

    actionLoading.value = 'create'
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/backups`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({}),
        })

        if (!response.ok) {
            error.value = await responseError(response, 'Unable to create the backup.')
            return
        }

        showToast('Backup creation started.')
        await loadBackups(true)
    } catch {
        error.value = 'Unable to connect to the server.'
    } finally {
        actionLoading.value = ''
    }
}

async function mountBackup(backup: Backup) {
    if (!cellId.value || !canMount(backup)) return

    actionLoading.value = backupLoadingKey('mount', backup)
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/backups/${encodeURIComponent(backup.id)}/mount`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            error.value = await responseError(
                response,
                'Unable to mount the backup.',
            )

            return
        }

        showToast('Backup mounted successfully.')
        await loadBackups(true)
    } catch {
        error.value = 'Unable to connect to the server.'
    } finally {
        actionLoading.value = ''
    }
}

async function unmountBackup(backup: Backup) {
    const mount = backup.active_mount

    if (
        !cellId.value ||
        !mount ||
        !canUnmount(backup)
    ) {
        return
    }

    actionLoading.value = backupLoadingKey('unmount', backup)
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/backup-mounts/${encodeURIComponent(mount.id)}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            error.value = await responseError(
                response,
                'Unable to unmount the backup.',
            )

            return
        }

        showToast('Backup unmounted successfully.')
        await loadBackups(true)
    } catch {
        error.value = 'Unable to connect to the server.'
    } finally {
        actionLoading.value = ''
    }
}

function browseMountedBackup(backup: Backup) {
    const mount = backup.active_mount

    if (!cellId.value || !mount || mount.status !== 'mounted') {
        return
    }

    window.location.href =
        `/cells/${cellId.value}/backup-mounts/${encodeURIComponent(mount.id)}/files`
}

function downloadBackup(backup: Backup) {
    if (!cellId.value || !canDownload(backup)) return

    window.location.href =
        `/cells/${cellId.value}/backups/${encodeURIComponent(backup.id)}/download`
}

function openConfirm(action: BackupAction, backup: Backup) {
    if (action === 'restore' && !canRestore(backup)) return
    if (action === 'delete' && !canDelete(backup)) return

    selectedAction.value = action
    selectedBackup.value = backup
    confirmOpen.value = true
}

function closeConfirm() {
    if (actionLoading.value) return

    confirmOpen.value = false
    selectedBackup.value = null
}

async function runConfirmedAction() {
    if (!selectedBackup.value) return

    if (selectedAction.value === 'restore') {
        await restoreBackup(selectedBackup.value)
        return
    }

    await deleteBackup(selectedBackup.value)
}

async function restoreBackup(backup: Backup) {
    if (!cellId.value || !canRestore(backup)) return

    actionLoading.value = backupLoadingKey('restore', backup)
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/backups/${encodeURIComponent(backup.id)}/restore`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            error.value = await responseError(response, 'Unable to restore the backup.')
            return
        }

        showToast('Backup restore started.')
        confirmOpen.value = false
        selectedBackup.value = null
        await loadBackups(true)
    } catch {
        error.value = 'Unable to connect to the server.'
    } finally {
        actionLoading.value = ''
    }
}

async function deleteBackup(backup: Backup) {
    if (!cellId.value || !canDelete(backup)) return

    actionLoading.value = backupLoadingKey('delete', backup)
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/backups/${encodeURIComponent(backup.id)}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            error.value = await responseError(response, 'Unable to delete the backup.')
            return
        }

        showToast('Backup deleted.')
        confirmOpen.value = false
        selectedBackup.value = null
        await loadBackups(true)
    } catch {
        error.value = 'Unable to connect to the server.'
    } finally {
        actionLoading.value = ''
    }
}

async function setBackupLock(backup: Backup, locked: boolean) {
    if (!cellId.value || isProcessing(backup)) return

    const action = locked ? 'lock' : 'unlock'
    actionLoading.value = backupLoadingKey(action, backup)
    error.value = ''

    try {
        const response = await fetch(
            `/cells/${cellId.value}/backups/${encodeURIComponent(backup.id)}/lock`,
            {
                method: locked ? 'POST' : 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        )

        if (!response.ok) {
            error.value = await responseError(
                response,
                locked ? 'Unable to lock the backup.' : 'Unable to unlock the backup.',
            )
            return
        }

        backup.is_locked = locked
        showToast(locked ? 'Backup locked.' : 'Backup unlocked.')
    } catch {
        error.value = 'Unable to connect to the server.'
    } finally {
        actionLoading.value = ''
    }
}

function startRefreshTimer() {
    refreshTimer = window.setInterval(() => {
        if (hasProcessingBackups.value) {
            void loadBackups(true)
        }
    }, 3000)
}

onMounted(async () => {
    await loadBackups()
    startRefreshTimer()
})

onBeforeUnmount(() => {
    if (refreshTimer !== null) {
        window.clearInterval(refreshTimer)
    }

    if (toastTimer !== null) {
        window.clearTimeout(toastTimer)
    }
})
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Backups`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-2xl font-black sm:text-3xl">
                                    Backups
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Create, download, restore, lock, and delete server backups.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                    :disabled="loading"
                                    @click="loadBackups()"
                                >
                                    <RefreshCw
                                        class="size-4"
                                        :class="{ 'animate-spin': loading }"
                                    />
                                    Refresh
                                </button>

                                <button
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!!actionLoading"
                                    @click="createBackup"
                                >
                                    <Plus class="size-4" />
                                    {{ actionLoading === 'create' ? 'Creating...' : 'Create Backup' }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <div
                        v-if="error"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        {{ error }}
                    </div>

                    <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <div class="rounded-button bg-hive/10 p-3 text-hive">
                                    <Archive class="size-5" />
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Manual Backups
                                    </div>
                                    <div class="mt-1 text-2xl font-black text-white">
                                        {{ manualBackupCount }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <div class="rounded-button bg-hive/10 p-3 text-hive">
                                    <HardDrive class="size-5" />
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Storage Used
                                    </div>
                                    <div class="mt-1 text-2xl font-black text-white">
                                        {{ formatBytes(totalStorage) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <div class="rounded-button bg-hive/10 p-3 text-hive">
                                    <CalendarClock class="size-5" />
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Last Backup
                                    </div>
                                    <div class="mt-1 text-2xl font-black text-white">
                                        {{ formatRelativeDate(lastBackup?.completed_at ?? lastBackup?.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <div class="rounded-button bg-hive/10 p-3 text-hive">
                                    <Database class="size-5" />
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Automatic
                                    </div>
                                    <div class="mt-1 text-2xl font-black text-white">
                                        {{ automaticBackupCount }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-black text-white">
                                    Backup Archives
                                </h2>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Showing {{ backups.length }} backup{{ backups.length === 1 ? '' : 's' }}.
                                </p>
                            </div>

                            <div
                                v-if="hasProcessingBackups"
                                class="inline-flex items-center gap-2 text-xs font-bold text-hive"
                            >
                                <RefreshCw class="size-3.5 animate-spin" />
                                Updating backup statuses
                            </div>
                        </div>

                        <div
                            v-if="loading"
                            class="mt-5 rounded-button border border-zinc-900 bg-[#0d0f11] p-6 text-zinc-500"
                        >
                            Loading backups...
                        </div>

                        <div
                            v-else-if="backups.length === 0"
                            class="mt-5 rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Archive class="mx-auto size-10 text-zinc-700" />
                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No backups yet
                            </h2>
                            <p class="mt-2 text-sm text-zinc-500">
                                Create a manual backup to get started.
                            </p>
                        </div>

                        <div v-else class="mt-5 space-y-3">
                            <div
                                v-for="backup in backups"
                                :key="backup.id"
                                class="group rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:border-hive/40 hover:bg-surface-hover"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="flex min-w-0 items-start gap-4">
                                        <div class="rounded-button bg-surface-light p-3 text-zinc-400 transition group-hover:text-hive">
                                            <Archive class="size-5" />
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="truncate text-base font-black text-white">
                                                    {{ backup.name }}
                                                </h3>

                                                <span
                                                    class="rounded-full border px-2 py-0.5 text-xs font-bold"
                                                    :class="statusClass(backup.status)"
                                                >
                                                    {{ statusLabel(backup.status) }}
                                                </span>

                                                <span class="rounded-full border border-zinc-700 bg-zinc-800/70 px-2 py-0.5 text-xs font-bold text-zinc-300">
                                                    {{ backup.is_automatic ? 'Automatic' : 'Manual' }}
                                                </span>

                                                <span
                                                    v-if="backup.is_locked"
                                                    class="inline-flex items-center gap-1 rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-xs font-bold text-status-warning"
                                                >
                                                    <Lock class="size-3" />
                                                    Locked
                                                </span>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-4 text-xs text-zinc-500">
                                                <span>{{ formatBytes(backup.size) }}</span>
                                                <span>
                                                    Created {{ formatRelativeDate(backup.created_at) }}
                                                </span>
                                                <span>{{ formatDate(backup.created_at) }}</span>
                                                <span v-if="backup.archive_name" class="break-all font-mono">
                                                    {{ backup.archive_name }}
                                                </span>
                                            </div>

                                            <p
                                                v-if="backup.status === 'failed' && backup.failure_reason"
                                                class="mt-3 rounded-button border border-status-danger/20 bg-status-danger/10 px-3 py-2 text-xs font-semibold text-status-danger"
                                            >
                                                {{ backup.failure_reason }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 lg:justify-end">
                                        <button
                                            v-if="!backup.active_mount"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="
                                                !canMount(backup) ||
                                                isBackupActionLoading('mount', backup)
                                            "
                                            @click="mountBackup(backup)"
                                        >
                                            <PackageOpen
                                                class="size-4"
                                                :class="{
                                                    'animate-pulse': isBackupActionLoading('mount', backup),
                                                }"
                                            />

                                            {{
                                                isBackupActionLoading('mount', backup)
                                                    ? 'Mounting...'
                                                    : 'Mount'
                                            }}
                                        </button>

                                        <template v-else>
                                            <button
                                                v-if="isMounted(backup)"
                                                class="inline-flex items-center gap-2 rounded-button border border-hive/40 bg-hive/10 px-3 py-2 text-xs font-bold text-hive transition hover:border-hive"
                                                @click="browseMountedBackup(backup)"
                                            >
                                                <FolderOpen class="size-4" />
                                                Browse
                                            </button>

                                            <button
                                                class="inline-flex items-center gap-2 rounded-button border border-status-warning/30 bg-status-warning/10 px-3 py-2 text-xs font-bold text-status-warning transition hover:border-status-warning disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    !canUnmount(backup) ||
                                                    isBackupActionLoading('unmount', backup)
                                                "
                                                @click="unmountBackup(backup)"
                                            >
                                                <PackageOpen
                                                    class="size-4"
                                                    :class="{
                                                        'animate-pulse':
                                                            isBackupActionLoading('unmount', backup) ||
                                                            isMountProcessing(backup),
                                                    }"
                                                />

                                                {{
                                                    backup.active_mount?.status === 'mounting'
                                                        ? 'Mounting...'
                                                        : backup.active_mount?.status === 'unmounting'
                                                            ? 'Unmounting...'
                                                            : isBackupActionLoading('unmount', backup)
                                                                ? 'Unmounting...'
                                                                : 'Unmount'
                                                }}
                                            </button>
                                        </template>
                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="!canDownload(backup)"
                                            @click="downloadBackup(backup)"
                                        >
                                            <Download class="size-4" />
                                            Download
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-status-warning hover:text-status-warning disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="!canRestore(backup) || !!actionLoading"
                                            @click="openConfirm('restore', backup)"
                                        >
                                            <RefreshCw
                                                v-if="isBackupActionLoading('restore', backup)"
                                                class="size-4 animate-spin"
                                            />
                                            <RotateCcw v-else class="size-4" />
                                            Restore
                                        </button>

                                        <button
                                            v-if="backup.is_locked"
                                            class="inline-flex items-center gap-2 rounded-button border border-status-warning/30 bg-status-warning/10 px-3 py-2 text-xs font-bold text-status-warning transition hover:border-status-warning disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="isProcessing(backup) || !!actionLoading"
                                            @click="setBackupLock(backup, false)"
                                        >
                                            <RefreshCw
                                                v-if="isBackupActionLoading('unlock', backup)"
                                                class="size-4 animate-spin"
                                            />
                                            <Unlock v-else class="size-4" />
                                            Unlock
                                        </button>

                                        <button
                                            v-else
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-status-warning hover:text-status-warning disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="isProcessing(backup) || !!actionLoading"
                                            @click="setBackupLock(backup, true)"
                                        >
                                            <RefreshCw
                                                v-if="isBackupActionLoading('lock', backup)"
                                                class="size-4 animate-spin"
                                            />
                                            <Lock v-else class="size-4" />
                                            Lock
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-bold text-status-danger transition hover:border-status-danger disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="!canDelete(backup) || !!actionLoading"
                                            @click="openConfirm('delete', backup)"
                                        >
                                            <RefreshCw
                                                v-if="isBackupActionLoading('delete', backup)"
                                                class="size-4 animate-spin"
                                            />
                                            <Trash v-else class="size-4" />
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <h2 class="text-lg font-black text-white">
                            Automatic Backups
                        </h2>

                        <p class="mt-2 text-sm text-zinc-400">
                            Hourly, daily, and offsite backup policies will be controlled from the admin panel later.
                        </p>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                <div class="text-sm font-black text-zinc-300">Hourly</div>
                                <div class="mt-1 text-xs text-zinc-500">Disabled</div>
                            </div>

                            <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                <div class="text-sm font-black text-zinc-300">Daily</div>
                                <div class="mt-1 text-xs text-zinc-500">Disabled</div>
                            </div>

                            <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                <div class="text-sm font-black text-zinc-300">Offsite</div>
                                <div class="mt-1 text-xs text-zinc-500">Not configured</div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="confirmOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface p-6 shadow-[0_20px_80px_rgba(0,0,0,0.55)]">
                <h2 class="text-xl font-black text-white">
                    {{ confirmTitle }}
                </h2>

                <p class="mt-2 text-sm leading-6 text-zinc-400">
                    {{ confirmMessage }}
                </p>

                <p
                    v-if="selectedBackup"
                    class="mt-3 break-all rounded-button border border-zinc-800 bg-surface-light px-3 py-2 font-mono text-xs text-zinc-500"
                >
                    {{ selectedBackup.name }}
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:text-white disabled:opacity-50"
                        :disabled="!!actionLoading"
                        @click="closeConfirm"
                    >
                        Cancel
                    </button>

                    <button
                        class="rounded-button border px-4 py-2 text-sm font-black text-white transition disabled:opacity-50"
                        :class="selectedAction === 'delete'
                            ? 'border-status-danger bg-status-danger'
                            : 'border-hive bg-hive hover:bg-hive-light'"
                        :disabled="!!actionLoading"
                        @click="runConfirmedAction"
                    >
                        {{ actionLoading ? 'Working...' : confirmButton }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border border-status-success/40 bg-status-success/15 px-5 py-3 text-sm font-bold text-status-success shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>
