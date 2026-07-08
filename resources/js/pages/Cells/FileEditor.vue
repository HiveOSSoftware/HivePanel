<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'
import { ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps<{
    cell: any
    path: string
}>()

const content = ref('')
const originalContent = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const savedMessage = ref('')

const isDirty = computed(() => content.value !== originalContent.value)

const language = computed(() => {
    const file = props.path.toLowerCase()

    if (file.endsWith('.yml') || file.endsWith('.yaml')) return 'yaml'
    if (file.endsWith('.json')) return 'json'
    if (file.endsWith('.properties')) return 'ini'
    if (file.endsWith('.toml')) return 'toml'
    if (file.endsWith('.xml')) return 'xml'
    if (file.endsWith('.js')) return 'javascript'
    if (file.endsWith('.ts')) return 'typescript'
    if (file.endsWith('.php')) return 'php'
    if (file.endsWith('.java')) return 'java'
    if (file.endsWith('.html')) return 'html'
    if (file.endsWith('.css')) return 'css'
    if (file.endsWith('.md')) return 'markdown'

    return 'plaintext'
})

function handleKeyboardSave(event: KeyboardEvent) {
    const isSaveShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's'

    if (!isSaveShortcut) return

    event.preventDefault()

    if (!saving.value && isDirty.value) {
        saveFile()
    }
}

async function loadFile() {
    loading.value = true
    error.value = ''

    try {
        const response = await fetch(`/cells/${props.cell.id}/files/read?path=${encodeURIComponent(props.path)}`, {
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

async function saveFile() {
    saving.value = true
    savedMessage.value = ''
    error.value = ''

    try {
        const response = await fetch(`/cells/${props.cell.id}/files/write`, {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                path: props.path,
                content: content.value,
            }),
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        originalContent.value = content.value
        savedMessage.value = 'Saved'
    } finally {
        saving.value = false
    }
}

function goBack() {
    router.visit(`/cells/${props.cell.id}/files?path=${encodeURIComponent(parentPath.value)}`)
}

const parentPath = computed(() => {
    const parts = props.path.split('/').filter(Boolean)
    parts.pop()
    return parts.join('/')
})

onMounted(() => {
    loadFile()
    window.addEventListener('keydown', handleKeyboardSave)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyboardSave)
})
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} - ${path}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-4">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-2xl font-black">File Editor</h1>
                                <p class="mt-1 break-all font-mono text-sm text-zinc-400">{{ path }}</p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                    @click="goBack"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </button>

                                <button
                                    class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="saving || !isDirty"
                                    @click="saveFile"
                                >
                                    <Save class="size-4" />
                                    {{ saving ? 'Saving...' : 'Save' }}
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="flex items-center justify-between border-b border-zinc-800 bg-surface-light px-4 py-3">
                            <div class="font-mono text-sm text-zinc-400">
                                {{ language }}
                            </div>

                            <div class="text-sm">
                                <span v-if="savedMessage" class="font-bold text-status-success">{{ savedMessage }}</span>
                                <span v-else-if="isDirty" class="font-bold text-status-warning">Unsaved changes</span>
                                <span v-else class="text-zinc-500">No changes</span>
                            </div>
                        </div>

                        <div v-if="loading" class="p-6 text-zinc-500">
                            Loading file...
                        </div>

                        <div v-else-if="error" class="p-6 font-bold text-status-danger">
                            {{ error }}
                        </div>

                        <VueMonacoEditor
                            v-else
                            v-model:value="content"
                            :language="language"
                            theme="vs-dark"
                            height="calc(100vh - 260px)"
                            :options="{
                                fontSize: 14,
                                minimap: { enabled: true },
                                wordWrap: 'on',
                                automaticLayout: true,
                                scrollBeyondLastLine: false,
                                tabSize: 4,
                                insertSpaces: true,
                                formatOnPaste: true,
                                formatOnType: true,
                            }"
                        />
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>