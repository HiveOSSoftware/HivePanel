<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    RefreshCw,
    Server,
} from 'lucide-vue-next'
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
} from 'vue'

type InstallStatus =
    | 'pending'
    | 'installing'
    | 'installed'
    | 'failed'

type Cell = {
    id: string
    daemon_id?: string | null
    node_id?: string | null
    name: string
    comb?: string | null
    install_status: InstallStatus
    install_status_label: string
    install_failure_reason?: string | null
    installed_at?: string | null

    node?: {
        id?: string | null
        name?: string | null
        location?: string | null
    } | null
}

const props = defineProps<{
    cell: Cell
}>()

const refreshing = ref(false)

let refreshTimer: number | null = null

const isPending = computed(() => {
    return props.cell.install_status === 'pending'
})

const isInstalling = computed(() => {
    return props.cell.install_status === 'installing'
})

const isFailed = computed(() => {
    return props.cell.install_status === 'failed'
})

const pageTitle = computed(() => {
    if (isFailed.value) {
        return `${props.cell.name} Installation Failed`
    }

    return `${props.cell.name} Installing`
})

const heading = computed(() => {
    if (isPending.value) {
        return 'Waiting to install'
    }

    if (isInstalling.value) {
        return 'Installing your cell'
    }

    if (isFailed.value) {
        return 'Installation failed'
    }

    return 'Installation complete'
})

const description = computed(() => {
    if (isPending.value) {
        return 'This cell is queued and waiting for a Worker to begin the installation.'
    }

    if (isInstalling.value) {
        return 'HivePanel is preparing the server files and runtime environment. This page will update automatically.'
    }

    if (isFailed.value) {
        return 'HivePanel was unable to complete the installation. Review the error below before retrying.'
    }

    return 'The cell has finished installing and is ready to use.'
})

const statusClass = computed(() => {
    if (isFailed.value) {
        return [
            'border-status-danger/30',
            'bg-status-danger/10',
            'text-status-danger',
        ]
    }

    if (isPending.value) {
        return [
            'border-status-warning/30',
            'bg-status-warning/10',
            'text-status-warning',
        ]
    }

    return [
        'border-hive/30',
        'bg-hive/10',
        'text-hive',
    ]
})

function refreshPage() {
    if (refreshing.value) {
        return
    }

    refreshing.value = true

    router.reload({
        only: ['cell'],
        preserveScroll: true,

        onFinish: () => {
            refreshing.value = false
        },
    })
}

onMounted(() => {
    if (!isFailed.value) {
        refreshTimer = window.setInterval(() => {
            refreshPage()
        }, 5000)
    }
})

onUnmounted(() => {
    if (refreshTimer !== null) {
        window.clearInterval(refreshTimer)
    }
})
</script>

<template>
    <AppLayout
        context="server"
        :active-cell="cell"
        active-cell-status="installing"
    >
        <Head :title="pageTitle" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="flex min-h-[calc(100vh-5rem)] items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="w-full max-w-3xl">
                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                        <div class="border-b border-zinc-800 bg-surface-light px-6 py-5 sm:px-8">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-xs font-black uppercase tracking-[0.2em] text-zinc-500">
                                        Cell installation
                                    </div>

                                    <h1 class="mt-2 text-2xl font-black text-white sm:text-3xl">
                                        {{ cell.name }}
                                    </h1>
                                </div>

                                <div
                                    class="inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-black"
                                    :class="statusClass"
                                >
                                    <AlertTriangle
                                        v-if="isFailed"
                                        class="size-4"
                                    />

                                    <Clock3
                                        v-else-if="isPending"
                                        class="size-4"
                                    />

                                    <LoaderCircle
                                        v-else
                                        class="size-4 animate-spin"
                                    />

                                    {{ cell.install_status_label }}
                                </div>
                            </div>
                        </div>

                        <div class="px-6 py-8 sm:px-8 sm:py-10">
                            <div class="flex flex-col items-center text-center">
                                <div
                                    class="flex size-20 items-center justify-center rounded-full border"
                                    :class="statusClass"
                                >
                                    <AlertTriangle
                                        v-if="isFailed"
                                        class="size-9"
                                    />

                                    <Clock3
                                        v-else-if="isPending"
                                        class="size-9"
                                    />

                                    <LoaderCircle
                                        v-else
                                        class="size-9 animate-spin"
                                    />
                                </div>

                                <h2 class="mt-6 text-2xl font-black text-white">
                                    {{ heading }}
                                </h2>

                                <p class="mt-3 max-w-xl text-sm leading-7 text-zinc-400">
                                    {{ description }}
                                </p>
                            </div>

                            <div
                                v-if="!isFailed"
                                class="mt-8 overflow-hidden rounded-full bg-zinc-900"
                            >
                                <div
                                    class="h-2 w-1/3 rounded-full bg-hive"
                                    :class="{
                                        'animate-pulse': isPending,
                                        'installation-progress': isInstalling,
                                    }"
                                />
                            </div>

                            <div
                                v-if="isFailed"
                                class="mt-8 rounded-button border border-status-danger/30 bg-status-danger/10 p-5"
                            >
                                <div class="flex items-start gap-3">
                                    <AlertTriangle class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                    <div class="min-w-0">
                                        <div class="text-sm font-black text-status-danger">
                                            Worker error
                                        </div>

                                        <pre class="mt-3 whitespace-pre-wrap break-words font-mono text-xs leading-6 text-zinc-300">{{ cell.install_failure_reason || 'No installation error was provided.' }}</pre>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                        <Server class="size-4" />
                                        Node
                                    </div>

                                    <div class="mt-2 text-sm font-bold text-white">
                                        {{ cell.node?.name || 'Unknown node' }}
                                    </div>

                                    <div
                                        v-if="cell.node?.location"
                                        class="mt-1 text-xs text-zinc-500"
                                    >
                                        {{ cell.node.location }}
                                    </div>
                                </div>

                                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Cell ID
                                    </div>

                                    <div class="mt-2 break-all font-mono text-xs text-zinc-300">
                                        {{ cell.id }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                                <Link
                                    href="/cells"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2.5 text-sm font-bold text-zinc-300 transition hover:border-zinc-700 hover:text-white"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back to Cells
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2.5 text-sm font-black text-black transition hover:bg-hive/90 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="refreshing"
                                    @click="refreshPage"
                                >
                                    <RefreshCw
                                        class="size-4"
                                        :class="{
                                            'animate-spin': refreshing,
                                        }"
                                    />

                                    {{
                                        refreshing
                                            ? 'Checking...'
                                            : 'Check Status'
                                    }}
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>

<style scoped>
.installation-progress {
    animation: installation-progress 1.8s ease-in-out infinite;
}

@keyframes installation-progress {
    0% {
        transform: translateX(-110%);
    }

    100% {
        transform: translateX(310%);
    }
}
</style>