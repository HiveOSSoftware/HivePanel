<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    Archive,
    CalendarClock,
    Database,
    Download,
    HardDrive,
    Plus,
    RefreshCw,
    RotateCcw,
    Trash,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'

const props = defineProps<{
    cell: any
}>()

type Backup = {
    name: string
    size?: number
    created_at?: string
    type?: 'manual' | 'automatic' | 'hourly' | 'daily' | 'offsite'
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

const cellId = computed(() => props.cell?.id)

const totalStorage = computed(() => backups.value.reduce((total, backup) => total + (backup.size ?? 0), 0))
const lastBackup = computed(() => backups.value[0])

const confirmTitle = computed(() => selectedAction.value === 'restore' ? 'Restore Backup' : 'Delete Backup')
const confirmButton = computed(() => selectedAction.value === 'restore' ? 'Restore' : 'Delete')
const confirmMessage = computed(() => {
    if (!selectedBackup.value) return ''

    if (selectedAction.value === 'restore') {
        return `Restore "${selectedBackup.value.name}"? This may overwrite current server files.`
    }

    return `Delete "${selectedBackup.value.name}"? This cannot be undone.`
})

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function showToast(message: string) {
    toastMessage.value = message
    setTimeout(() => toastMessage.value = '', 3000)
}

function formatBytes(bytes?: number) {
    const value = bytes ?? 0

    if (value >= 1024 * 1024 * 1024) return `${(value / 1024 / 1024 / 1024).toFixed(2)} GB`
    if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(2)} MB`
    if (value >= 1024) return `${(value / 1024).toFixed(2)} KB`

    return `${value} B`
}

function formatDate(value?: string) {
    if (!value) return 'Unknown'

    return new Date(value).toLocaleString()
}

function formatRelativeDate(value?: string) {
    if (!value) return 'Never'

    const diff = Date.now() - new Date(value).getTime()
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)

    if (minutes < 1) return 'Just now'
    if (minutes < 60) return `${minutes} min${minutes === 1 ? '' : 's'} ago`
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`

    return `${days} day${days === 1 ? '' : 's'} ago`
}

function backupType(backup: Backup) {
    return backup.type ?? 'manual'
}

function backupTypeLabel(backup: Backup) {
    const type = backupType(backup)

    if (type === 'automatic') return 'Automatic'
    if (type === 'hourly') return 'Hourly'
    if (type === 'daily') return 'Daily'
    if (type === 'offsite') return 'Offsite'

    return 'Manual'
}

async function loadBackups() {
    if (!cellId.value) return

    loading.value = true
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/backups-json`, {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        const data = await response.json()
        backups.value = Array.isArray(data) ? data : data.backups ?? []
    } finally {
        loading.value = false
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
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        showToast('Backup created.')
        await loadBackups()
    } finally {
        actionLoading.value = ''
    }
}

function downloadBackup(backup: Backup) {
    if (!cellId.value) return

    window.location.href = `/cells/${cellId.value}/backups/${encodeURIComponent(backup.name)}/download`
}

function openConfirm(action: BackupAction, backup: Backup) {
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
    if (!cellId.value || !selectedBackup.value) return

    if (selectedAction.value === 'restore') {
        await restoreBackup(selectedBackup.value)
        return
    }

    await deleteBackup(selectedBackup.value)
}

async function restoreBackup(backup: Backup) {
    if (!cellId.value) return

    actionLoading.value = backup.name
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/backups/${encodeURIComponent(backup.name)}/restore`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        showToast('Backup restore started.')
        confirmOpen.value = false
        selectedBackup.value = null
    } finally {
        actionLoading.value = ''
    }
}

async function deleteBackup(backup: Backup) {
    if (!cellId.value) return

    actionLoading.value = backup.name
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/backups/${encodeURIComponent(backup.name)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        showToast('Backup deleted.')
        confirmOpen.value = false
        selectedBackup.value = null
        await loadBackups()
    } finally {
        actionLoading.value = ''
    }
}

onMounted(loadBackups)
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
                                    Create, download, restore, and delete server backups.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                    @click="loadBackups"
                                >
                                    <RefreshCw class="size-4" />
                                    Refresh
                                </button>

                                <button
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
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
                                        {{ backups.length }}
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
                                        {{ formatRelativeDate(lastBackup?.created_at) }}
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
                                        Offsite
                                    </div>
                                    <div class="mt-1 text-2xl font-black text-white">
                                        Disabled
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
                        </div>

                        <div v-if="loading" class="mt-5 rounded-button border border-zinc-900 bg-[#0d0f11] p-6 text-zinc-500">
                            Loading backups...
                        </div>

                        <div v-else-if="backups.length === 0" class="mt-5 rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center">
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
                                :key="backup.name"
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

                                                <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                                    {{ backupTypeLabel(backup) }}
                                                </span>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-4 text-xs text-zinc-500">
                                                <span>{{ formatBytes(backup.size) }}</span>
                                                <span>Created {{ formatRelativeDate(backup.created_at) }}</span>
                                                <span>{{ formatDate(backup.created_at) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2 lg:justify-end">
                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="downloadBackup(backup)"
                                        >
                                            <Download class="size-4" />
                                            Download
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-status-warning hover:text-status-warning disabled:opacity-50"
                                            :disabled="actionLoading === backup.name"
                                            @click="openConfirm('restore', backup)"
                                        >
                                            <RotateCcw class="size-4" />
                                            Restore
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-bold text-status-danger transition hover:border-status-danger disabled:opacity-50"
                                            :disabled="actionLoading === backup.name"
                                            @click="openConfirm('delete', backup)"
                                        >
                                            <Trash class="size-4" />
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
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:text-white"
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