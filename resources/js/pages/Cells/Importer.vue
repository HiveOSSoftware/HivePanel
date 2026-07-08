<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    CheckCircle,
    DownloadCloud,
    FolderInput,
    Loader2,
    Server,
    ShieldCheck,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    cell: any
}>()

type Protocol = 'sftp' | 'ftp' | 'ftps'

const protocol = ref<Protocol>('sftp')
const host = ref('')
const port = ref('22')
const username = ref('')
const password = ref('')
const remotePath = ref('/')
const importing = ref(false)
const testing = ref(false)
const tested = ref(false)
const error = ref('')
const toastMessage = ref('')
const toastType = ref<'success' | 'error'>('success')

let statusTimer: number | undefined

const options = ref({
    importWorlds: true,
    importPlugins: true,
    importConfigs: true,
    importMods: true,
    importServerJar: false,
    wipeBeforeImport: false,
})

const progress = ref({
    running: false,
    stage: 'Waiting',
    percent: 0,
    message: 'Ready to import files.',
})

const cellId = computed(() => props.cell?.id)

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function showToast(message: string, type: 'success' | 'error' = 'success') {
    toastMessage.value = message
    toastType.value = type

    setTimeout(() => {
        toastMessage.value = ''
    }, 3000)
}

async function readError(response: Response) {
    const text = await response.text()

    try {
        const json = JSON.parse(text)
        return json.message || json.error || text
    } catch {
        return text
    }
}

function updateDefaultPort() {
    if (protocol.value === 'sftp') port.value = '22'
    if (protocol.value === 'ftp') port.value = '21'
    if (protocol.value === 'ftps') port.value = '990'
}

async function testConnection() {
    if (!cellId.value) return

    testing.value = true
    tested.value = false
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/importer/test`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                protocol: protocol.value,
                host: host.value,
                port: Number(port.value),
                username: username.value,
                password: password.value,
                remote_path: remotePath.value,
            }),
        })

        if (!response.ok) {
            const message = await readError(response)
            error.value = message
            showToast(`Connection failed: ${message}`, 'error')
            return
        }

        const data = await response.json()

        tested.value = true
        showToast(data.message ?? 'Connection successful.')
    } finally {
        testing.value = false
    }
}

async function startImport() {
    if (!cellId.value) return

    importing.value = true
    error.value = ''

    progress.value = {
        running: true,
        stage: 'Starting',
        percent: 5,
        message: 'Preparing import job...',
    }

    try {
        const response = await fetch(`/cells/${cellId.value}/importer/start`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                protocol: protocol.value,
                host: host.value,
                port: Number(port.value),
                username: username.value,
                password: password.value,
                remote_path: remotePath.value,
                options: options.value,
            }),
        })

        if (!response.ok) {
            const message = await readError(response)
            error.value = message
            progress.value.running = false
            progress.value.stage = 'Failed'
            progress.value.percent = 100
            progress.value.message = message
            showToast(`Import failed: ${message}`, 'error')
            return
        }

        const data = await response.json()

        showToast(data.message ?? 'Import started.')
        startStatusPolling()
    } finally {
        importing.value = false
    }
}

function startStatusPolling() {
    if (statusTimer) {
        clearInterval(statusTimer)
    }

    loadImportStatus()

    statusTimer = window.setInterval(loadImportStatus, 1500)
}

async function loadImportStatus() {
    if (!cellId.value) return

    try {
        const response = await fetch(`/cells/${cellId.value}/importer/status`, {
            headers: {
                Accept: 'application/json',
            },
        })

        if (!response.ok) return

        const data = await response.json()

        progress.value = {
            running: data.running ?? false,
            stage: data.stage ?? 'Waiting',
            percent: data.percent ?? 0,
            message: data.error || data.message || 'Waiting...',
        }

        if (data.error) {
            error.value = data.error
            showToast(`Import failed: ${data.error}`, 'error')
        }

        if (!data.running && data.percent >= 100) {
            if (statusTimer) clearInterval(statusTimer)
            statusTimer = undefined
        }
    } catch {
        // ignore temporary polling errors
    }
}

onMounted(async () => {
    await loadImportStatus()

    if (progress.value.running) {
        startStatusPolling()
    }
})

onUnmounted(() => {
    if (statusTimer) {
        clearInterval(statusTimer)
    }
})
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Importer`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-2xl font-black sm:text-3xl">
                                    Server Importer
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Import files from another host using FTP, FTPS, or SFTP.
                                </p>
                            </div>

                            <div class="rounded-button border border-hive/30 bg-hive/10 px-4 py-2 text-sm font-bold text-hive">
                                Beta
                            </div>
                        </div>
                    </section>

                    <div
                        v-if="error"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        {{ error }}
                    </div>

                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <Server class="size-5 text-hive" />
                                <h2 class="text-lg font-black text-white">
                                    Source Server Details
                                </h2>
                            </div>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <label class="space-y-2">
                                    <span class="text-sm font-bold text-zinc-400">Protocol</span>
                                    <select
                                        v-model="protocol"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                        @change="updateDefaultPort"
                                    >
                                        <option value="sftp">SFTP</option>
                                        <option value="ftp">FTP</option>
                                        <option value="ftps">FTPS</option>
                                    </select>
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-bold text-zinc-400">Host</span>
                                    <input
                                        v-model="host"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                        placeholder="example.host.com"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-bold text-zinc-400">Port</span>
                                    <input
                                        v-model="port"
                                        type="number"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-bold text-zinc-400">Remote Directory</span>
                                    <input
                                        v-model="remotePath"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive"
                                        placeholder="/"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-bold text-zinc-400">Username</span>
                                    <input
                                        v-model="username"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-bold text-zinc-400">Password</span>
                                    <input
                                        v-model="password"
                                        type="password"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>
                            </div>

                            <div class="mt-6 flex flex-wrap gap-3">
                                <button
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                    :disabled="testing || !host || !username || !password"
                                    @click="testConnection"
                                >
                                    <Loader2 v-if="testing" class="size-4 animate-spin" />
                                    <ShieldCheck v-else class="size-4" />
                                    {{ testing ? 'Testing...' : tested ? 'Test Again' : 'Test Connection' }}
                                </button>

                                <button
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="importing || progress.running || !host || !username || !password"
                                    @click="startImport"
                                >
                                    <DownloadCloud class="size-4" />
                                    {{ importing ? 'Starting...' : progress.running ? 'Import Running...' : 'Start Import' }}
                                </button>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <FolderInput class="size-5 text-hive" />
                                <h2 class="text-lg font-black text-white">
                                    Import Options
                                </h2>
                            </div>

                            <div class="mt-5 space-y-3">
                                <label
                                    v-for="(enabled, key) in options"
                                    :key="key"
                                    class="flex cursor-pointer items-center justify-between rounded-button border border-zinc-800 bg-surface-light px-4 py-3"
                                >
                                    <span class="text-sm font-bold text-zinc-300">
                                        {{
                                            String(key)
                                                .replace('import', 'Import ')
                                                .replace('wipe', 'Wipe ')
                                                .replace(/([A-Z])/g, ' $1')
                                        }}
                                    </span>

                                    <input
                                        v-model="options[key]"
                                        type="checkbox"
                                        class="accent-hive"
                                    />
                                </label>
                            </div>
                        </section>
                    </div>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <CheckCircle class="size-5 text-hive" />
                            <h2 class="text-lg font-black text-white">
                                Import Progress
                            </h2>
                        </div>

                        <div class="mt-5 rounded-button border border-zinc-800 bg-surface-light p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="font-black text-white">
                                        {{ progress.stage }}
                                    </div>

                                    <div class="mt-1 text-sm text-zinc-500">
                                        {{ progress.message }}
                                    </div>
                                </div>

                                <div class="font-mono text-sm font-black text-hive">
                                    {{ progress.percent }}%
                                </div>
                            </div>

                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-zinc-800">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="error ? 'bg-status-danger' : 'bg-hive'"
                                    :style="{ width: `${progress.percent}%` }"
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border px-5 py-3 text-sm font-bold shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
            :class="toastType === 'success'
                ? 'border-status-success/40 bg-status-success/15 text-status-success'
                : 'border-status-danger/40 bg-status-danger/15 text-status-danger'"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>