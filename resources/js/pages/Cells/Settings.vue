<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Bug, Clock, HardDrive, RefreshCw, Save, Server, Trash, Wrench } from 'lucide-vue-next'

const props = defineProps<{
    cell: any
}>()

const saving = ref(false)
const runningUtility = ref('')
const error = ref('')
const toastMessage = ref('')

const form = ref({
    name: props.cell.name ?? '',
    timezone: props.cell.timezone ?? 'UTC',
    jarfile: props.cell.jarfile ?? props.cell.variables?.jarfile ?? 'server.jar',
    world_name: props.cell.world_name ?? props.cell.variables?.world_name ?? 'world',
})

const cellId = computed(() => props.cell?.id)

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function showToast(message: string) {
    toastMessage.value = message
    setTimeout(() => toastMessage.value = '', 3000)
}

async function saveSettings() {
    if (!cellId.value) return

    saving.value = true
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/settings`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(form.value),
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        showToast('Settings saved.')
    } finally {
        saving.value = false
    }
}

async function runUtility(utility: string) {
    if (!cellId.value) return
    if (!confirm(`Run ${utility.replace('-', ' ')}?`)) return

    runningUtility.value = utility
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/utilities/${utility}`, {
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

        showToast('Utility started.')
    } finally {
        runningUtility.value = ''
    }
}
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Settings`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <h1 class="text-2xl font-black sm:text-3xl">
                            Server Settings
                        </h1>
                        <p class="mt-2 text-sm text-zinc-400">
                            Configure panel display settings, startup defaults, utilities, and debug information.
                        </p>
                    </section>

                    <div v-if="error" class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger">
                        {{ error }}
                    </div>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <Server class="size-5 text-hive" />
                            <h2 class="text-lg font-black">General</h2>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-2">
                            <label class="space-y-2">
                                <span class="text-sm font-bold text-zinc-400">Server Name</span>
                                <input v-model="form.name" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm font-bold text-zinc-400">Timezone</span>
                                <select v-model="form.timezone" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive">
                                    <option value="UTC">UTC</option>
                                    <option value="Europe/London">Europe/London</option>
                                    <option value="Europe/Paris">Europe/Paris</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="America/Chicago">America/Chicago</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles</option>
                                    <option value="Asia/Singapore">Asia/Singapore</option>
                                    <option value="Australia/Sydney">Australia/Sydney</option>
                                </select>
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm font-bold text-zinc-400">Jar File</span>
                                <input v-model="form.jarfile" placeholder="server.jar" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive" />
                            </label>

                            <label class="space-y-2">
                                <span class="text-sm font-bold text-zinc-400">World Name</span>
                                <input v-model="form.world_name" placeholder="world" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none focus:border-hive" />
                            </label>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                                :disabled="saving"
                                @click="saveSettings"
                            >
                                <Save class="size-4" />
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </button>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-4">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <Wrench class="size-5 text-hive" />
                                <h2 class="text-lg font-black">Tasks & Utilities</h2>
                            </div>

                            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <button class="rounded-button border border-zinc-800 bg-surface-light p-4 text-left transition hover:border-hive hover:text-hive" @click="runUtility('clear-logs')">
                                    <RefreshCw class="mb-3 size-5" />
                                    <div class="font-black">Clear Logs</div>
                                    <div class="mt-1 text-xs text-zinc-500">Remove old log files.</div>
                                </button>

                                <button class="rounded-button border border-zinc-800 bg-surface-light p-4 text-left transition hover:border-hive hover:text-hive" @click="runUtility('repair-permissions')">
                                    <Wrench class="mb-3 size-5" />
                                    <div class="font-black">Repair Permissions</div>
                                    <div class="mt-1 text-xs text-zinc-500">Fix file ownership issues.</div>
                                </button>

                                <button class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-left text-status-danger transition hover:border-status-danger" @click="runUtility('reset-world')">
                                    <HardDrive class="mb-3 size-5" />
                                    <div class="font-black">Reset World</div>
                                    <div class="mt-1 text-xs text-zinc-400">Delete and recreate world files.</div>
                                </button>

                                <button class="rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-left text-status-danger transition hover:border-status-danger" @click="runUtility('reset-files')">
                                    <Trash class="mb-3 size-5" />
                                    <div class="font-black">Reset All Files</div>
                                    <div class="mt-1 text-xs text-zinc-400">Wipe server files completely.</div>
                                </button>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <Bug class="size-5 text-hive" />
                                <h2 class="text-lg font-black">Debug Information</h2>
                            </div>

                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Server ID</div>
                                    <div class="mt-2 break-all font-mono text-sm text-zinc-200">{{ cell.uuid ?? cell.id }}</div>
                                </div>

                                <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Internal ID</div>
                                    <div class="mt-2 break-all font-mono text-sm text-zinc-200">{{ cell.id }}</div>
                                </div>

                                <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Node</div>
                                    <div class="mt-2 font-mono text-sm text-zinc-200">{{ cell.node?.name ?? 'worker-01' }}</div>
                                </div>

                                <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Allocation</div>
                                    <div class="mt-2 font-mono text-sm text-zinc-200">
                                        {{ cell.allocation?.ip ?? '127.0.0.1' }}:{{ cell.allocation?.port ?? '25565' }}
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border border-status-success/40 bg-status-success/15 px-5 py-3 text-sm font-bold text-status-success shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>