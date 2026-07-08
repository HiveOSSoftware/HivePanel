<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import {
    FileText,
    RefreshCw,
    Save,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next'

const props = defineProps<{
    cell: any
    configFiles: {
        id: string
        title: string
        path: string
        description: string
    }[]
}>()

type ConfigEntry = {
    number: number
    key: string
    value: string
    description: string
    raw: string
    hidden: boolean
    type: 'boolean' | 'number' | 'string'
}

const selectedPath = ref(props.configFiles?.[0]?.path ?? 'server.properties')
const content = ref('')
const originalContent = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const search = ref('')
const fileSearch = ref('')
const toastMessage = ref('')

const isDirty = computed(() => content.value !== originalContent.value)

const selectedFile = computed(() => props.configFiles.find((file) => file.path === selectedPath.value))

const filteredFiles = computed(() => {
    const value = fileSearch.value.toLowerCase()

    return props.configFiles.filter((file) =>
        file.title.toLowerCase().includes(value) ||
        file.path.toLowerCase().includes(value) ||
        file.description.toLowerCase().includes(value)
    )
})

const entries = computed<ConfigEntry[]>(() => {
    const query = search.value.trim().toLowerCase()
    const result: ConfigEntry[] = []
    const pendingComments: string[] = []

    content.value.split('\n').forEach((line, index) => {
        const trimmed = line.trim()

        if (!trimmed) return

        if (trimmed.startsWith('#')) {
            pendingComments.push(trimmed.replace(/^#\s?/, ''))
            return
        }

        const key = parseKey(line)
        const value = parseValue(line)

        if (!key) {
            pendingComments.length = 0
            return
        }

        const description = pendingComments.join(' ').trim()
        pendingComments.length = 0

        result.push({
            number: index + 1,
            key,
            value,
            description,
            raw: line,
            hidden: query
                ? !key.toLowerCase().includes(query) &&
                  !value.toLowerCase().includes(query) &&
                  !description.toLowerCase().includes(query)
                : false,
            type: detectType(value),
        })
    })

    return result
})

function detectType(value: string): ConfigEntry['type'] {
    const trimmed = value.trim().toLowerCase()

    if (trimmed === 'true' || trimmed === 'false') return 'boolean'
    if (/^-?\d+(\.\d+)?$/.test(trimmed)) return 'number'

    return 'string'
}

function sliderMin(entry: ConfigEntry) {
    const value = Number(entry.value)

    if (entry.key.toLowerCase().includes('port')) return 1
    if (entry.key.toLowerCase().includes('percent')) return 0
    if (value < 0) return Math.min(-100, value)

    return 0
}

function sliderMax(entry: ConfigEntry) {
    const key = entry.key.toLowerCase()
    const value = Number(entry.value)

    if (key.includes('port')) return 65535
    if (key.includes('percent')) return 100
    if (key.includes('distance') || key.includes('view')) return Math.max(32, value)
    if (key.includes('tick')) return Math.max(1200, value)
    if (key.includes('player')) return Math.max(100, value)
    if (value <= 1 && value >= 0) return 1

    return Math.max(100, value * 2 || 100)
}

function sliderStep(entry: ConfigEntry) {
    return entry.value.includes('.') ? 0.01 : 1
}

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function parseKey(line: string) {
    const trimmed = line.trim()

    if (!trimmed || trimmed.startsWith('#')) return ''

    if (trimmed.includes('=')) return trimmed.split('=')[0]?.trim() ?? ''
    if (trimmed.includes(':')) return trimmed.split(':')[0]?.trim() ?? ''

    return ''
}

function parseValue(line: string) {
    const trimmed = line.trim()

    if (!trimmed || trimmed.startsWith('#')) return ''

    if (trimmed.includes('=')) return trimmed.substring(trimmed.indexOf('=') + 1).trim()
    if (trimmed.includes(':')) return trimmed.substring(trimmed.indexOf(':') + 1).trim()

    return trimmed
}

function showToast(message: string) {
    toastMessage.value = message
    setTimeout(() => toastMessage.value = '', 3000)
}

async function loadConfig(path = selectedPath.value) {
    loading.value = true
    error.value = ''
    selectedPath.value = path

    try {
        const response = await fetch(`/cells/${props.cell.id}/config-json?path=${encodeURIComponent(path)}`, {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        const data = await response.json()

        content.value = data.content ?? ''
        originalContent.value = data.content ?? ''
    } finally {
        loading.value = false
    }
}

async function saveConfig() {
    if (!selectedPath.value) return

    saving.value = true
    error.value = ''

    try {
        const response = await fetch(`/cells/${props.cell.id}/config-json`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                path: selectedPath.value,
                content: content.value,
            }),
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        originalContent.value = content.value
        showToast('Configuration saved.')
    } finally {
        saving.value = false
    }
}

function openRawEditor() {
    router.visit(`/cells/${props.cell.id}/files/edit?path=${encodeURIComponent(selectedPath.value)}`)
}

function updateLine(lineNumber: number, nextValue: string | number | boolean) {
    const allLines = content.value.split('\n')
    const current = allLines[lineNumber - 1] ?? ''
    const value = String(nextValue)

    if (current.includes('=')) {
        const key = current.substring(0, current.indexOf('='))
        allLines[lineNumber - 1] = `${key}=${value}`
    } else if (current.includes(':')) {
        const key = current.substring(0, current.indexOf(':'))
        allLines[lineNumber - 1] = `${key}: ${value}`
    } else {
        allLines[lineNumber - 1] = value
    }

    content.value = allLines.join('\n')
}

onMounted(() => {
    loadConfig()
})
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Config`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto grid max-w-[1700px] gap-5 xl:grid-cols-[380px_1fr]">
                    <section class="rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 px-5 py-4">
                            <h2 class="text-sm font-black uppercase tracking-wide text-white">
                                Config Files
                            </h2>
                        </div>

                        <div class="p-4">
                            <div class="mb-3 flex items-center gap-3 rounded-button bg-surface-light px-4 py-3 text-zinc-500">
                                <Search class="size-4" />
                                <input
                                    v-model="fileSearch"
                                    class="w-full bg-transparent text-sm text-zinc-300 outline-none placeholder:text-zinc-600"
                                    placeholder="Search files..."
                                />
                            </div>

                            <div class="space-y-1">
                                <button
                                    v-for="file in filteredFiles"
                                    :key="file.path"
                                    class="flex w-full items-start gap-3 rounded-button px-3 py-3 text-left transition"
                                    :class="selectedPath === file.path ? 'bg-hive/25 text-white' : 'text-zinc-400 hover:bg-surface-light hover:text-white'"
                                    @click="loadConfig(file.path)"
                                >
                                    <FileText class="mt-0.5 size-4 shrink-0" />

                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-black">
                                            {{ file.title }}
                                        </span>

                                        <span class="mt-1 inline-block rounded bg-surface-light px-2 py-0.5 font-mono text-xs text-zinc-400">
                                            {{ file.path }}
                                        </span>

                                        <span class="mt-1 block text-xs text-zinc-500">
                                            {{ file.description }}
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-4 sm:p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h1 class="text-xl font-black uppercase tracking-wide text-white">
                                        {{ selectedFile?.title ?? 'Configuration' }}
                                    </h1>

                                    <p class="mt-1 font-mono text-sm text-zinc-500">
                                        {{ selectedPath }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="loadConfig()"
                                    >
                                        <RefreshCw class="size-4" />
                                        Reload
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="openRawEditor"
                                    >
                                        <SlidersHorizontal class="size-4" />
                                        Raw Editor
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                                        :disabled="saving || !isDirty"
                                        @click="saveConfig"
                                    >
                                        <Save class="size-4" />
                                        {{ saving ? 'Saving...' : 'Save' }}
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center gap-3 rounded-button bg-surface-light px-4 py-3 text-zinc-500">
                                <Search class="size-4" />
                                <input
                                    v-model="search"
                                    class="w-full bg-transparent text-sm text-zinc-300 outline-none placeholder:text-zinc-600"
                                    placeholder="Search properties..."
                                />
                            </div>
                        </div>

                        <div v-if="loading" class="p-6 text-zinc-500">
                            Loading configuration...
                        </div>

                        <div v-else-if="error" class="p-6 font-bold text-status-danger">
                            {{ error }}
                        </div>

                        <div v-else class="space-y-3 p-4 sm:p-5">
                            <div
                                v-for="line in entries"
                                :key="line.number"
                                v-show="!line.hidden"
                                class="rounded-button border border-zinc-900 bg-[#0d0f11] p-4"
                            >
                                <div class="grid gap-4 lg:grid-cols-[1fr_420px] lg:items-center">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-black text-white">
                                                {{ line.key }}
                                            </span>

                                            <span class="rounded bg-surface-light px-2 py-0.5 font-mono text-xs text-zinc-500">
                                                line {{ line.number }}
                                            </span>

                                            <span class="rounded bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                                {{ line.type }}
                                            </span>
                                        </div>

                                        <p
                                            v-if="line.description"
                                            class="mt-2 text-xs leading-5 text-zinc-500"
                                        >
                                            {{ line.description }}
                                        </p>

                                        <p class="mt-2 font-mono text-xs text-zinc-600">
                                            {{ line.raw }}
                                        </p>
                                    </div>

                                    <div>
                                        <button
                                            v-if="line.type === 'boolean'"
                                            class="ml-auto flex h-8 w-14 items-center rounded-full p-1 transition"
                                            :class="line.value.toLowerCase() === 'true' ? 'bg-hive' : 'bg-zinc-700'"
                                            @click="updateLine(line.number, line.value.toLowerCase() === 'true' ? 'false' : 'true')"
                                        >
                                            <span
                                                class="h-6 w-6 rounded-full bg-white transition"
                                                :class="line.value.toLowerCase() === 'true' ? 'translate-x-6' : 'translate-x-0'"
                                            />
                                        </button>

                                        <div v-else-if="line.type === 'number'" class="space-y-3">
                                            <div class="flex gap-3">
                                                <input
                                                    :value="line.value"
                                                    type="number"
                                                    class="w-32 rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none transition focus:border-hive"
                                                    @input="updateLine(line.number, ($event.target as HTMLInputElement).value)"
                                                />

                                                <input
                                                    :value="Number(line.value)"
                                                    type="range"
                                                    :min="sliderMin(line)"
                                                    :max="sliderMax(line)"
                                                    :step="sliderStep(line)"
                                                    class="w-full accent-hive"
                                                    @input="updateLine(line.number, ($event.target as HTMLInputElement).value)"
                                                />
                                            </div>

                                            <div class="flex justify-between font-mono text-[10px] text-zinc-600">
                                                <span>{{ sliderMin(line) }}</span>
                                                <span>{{ sliderMax(line) }}</span>
                                            </div>
                                        </div>

                                        <input
                                            v-else
                                            :value="line.value"
                                            class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm text-zinc-200 outline-none transition focus:border-hive"
                                            @input="updateLine(line.number, ($event.target as HTMLInputElement).value)"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="entries.length === 0"
                                class="rounded-button border border-zinc-900 bg-[#0d0f11] p-6 text-sm text-zinc-500"
                            >
                                No editable settings found. Use the raw editor for this file.
                            </div>
                        </div>
                    </section>
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