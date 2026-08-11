<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import {
    Activity,
    BookOpen,
    CheckCircle2,
    Database,
    ExternalLink,
    Github,
    HeartHandshake,
    MessageCircle,
    Server,
    Shield,
    TriangleAlert,
    Users,
    WifiOff,
} from 'lucide-vue-next'

defineProps<{
    stats: {
        nodes: number
        active_nodes: number
        cells: number
        users: number
        audit_logs: number
    }
    recentLogs: any[]
    versionStatus: {
        current: string
        latest?: string | null
        is_outdated: boolean
        checked: boolean
    }
    workerVersions: {
        id: string
        name: string
        version?: string | null
        reachable: boolean
        version_available: boolean
    }[]
    quickLinks: {
        label: string
        description: string
        url: string
        external: boolean
    }[]
}>()

function formatDate(value?: string) {
    if (!value) return 'Unknown'

    return new Date(value).toLocaleString()
}

function eventLabel(event: string) {
    return event
        .split('.')
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}

function versionLabel(version?: string | null) {
    if (!version) return 'Unknown'

    return version.startsWith('v') ? version : `v${version}`
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Admin" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <Shield class="size-6 text-hive" />

                            <div>
                                <h1 class="text-2xl font-black sm:text-3xl">
                                    Admin
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Manage admin activity, nodes, users, cells, and system health.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="versionStatus.checked && versionStatus.is_outdated"
                        class="rounded-panel border border-status-warning/30 bg-status-warning/10 p-5 sm:p-6"
                    >
                        <div class="flex items-start gap-3">
                            <TriangleAlert class="mt-0.5 size-6 text-status-warning" />

                            <div>
                                <h2 class="text-lg font-black text-status-warning">
                                    Your panel is not up-to-date!
                                </h2>

                                <p class="mt-2 text-sm font-bold text-zinc-300">
                                    The latest version is
                                    <span class="text-white">{{ versionLabel(versionStatus.latest) }}</span>
                                    and you are currently running
                                    <span class="text-white">{{ versionLabel(versionStatus.current) }}</span>.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-5">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <Server class="size-5 text-hive" />

                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">
                                Nodes
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ stats.nodes }}
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                {{ stats.active_nodes }} active
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <Database class="size-5 text-status-success" />

                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">
                                Cells
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ stats.cells }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <Users class="size-5 text-status-warning" />

                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">
                                Users
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ stats.users }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5 md:col-span-2">
                            <Activity class="size-5 text-purple-300" />

                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">
                                Audit Logs
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ stats.audit_logs }}
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-black">
                                    System Versions
                                </h2>

                                <p class="mt-1 text-sm text-zinc-500">
                                    Installed HivePanel and Worker versions.
                                </p>
                            </div>

                            <Server class="size-5 text-hive" />
                        </div>

                        <div class="mt-5 grid gap-3 lg:grid-cols-[300px_minmax(0,1fr)]">
                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            HivePanel
                                        </div>

                                        <div class="mt-2 text-xl font-black text-white">
                                            {{ versionLabel(versionStatus.current) }}
                                        </div>
                                    </div>

                                    <div
                                        v-if="versionStatus.checked && !versionStatus.is_outdated"
                                        class="flex size-9 items-center justify-center rounded-full border border-status-success/30 bg-status-success/10 text-status-success"
                                    >
                                        <CheckCircle2 class="size-4" />
                                    </div>

                                    <div
                                        v-else-if="versionStatus.is_outdated"
                                        class="flex size-9 items-center justify-center rounded-full border border-status-warning/30 bg-status-warning/10 text-status-warning"
                                    >
                                        <TriangleAlert class="size-4" />
                                    </div>
                                </div>

                                <div
                                    v-if="versionStatus.checked"
                                    class="mt-3 text-xs font-bold"
                                    :class="versionStatus.is_outdated ? 'text-status-warning' : 'text-status-success'"
                                >
                                    {{ versionStatus.is_outdated ? `Latest ${versionLabel(versionStatus.latest)}` : 'Up to date' }}
                                </div>

                                <div
                                    v-else
                                    class="mt-3 text-xs font-bold text-zinc-500"
                                >
                                    Latest version unavailable
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-button border border-zinc-800 bg-[#0d0f11]">
                                <div class="border-b border-zinc-800 px-4 py-3">
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Workers
                                    </div>
                                </div>

                                <div
                                    v-if="workerVersions.length > 0"
                                    class="divide-y divide-zinc-800"
                                >
                                    <div
                                        v-for="worker in workerVersions"
                                        :key="worker.id"
                                        class="flex items-center justify-between gap-4 px-4 py-3"
                                    >
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-black text-white">
                                                {{ worker.name }}
                                            </div>

                                            <div class="mt-1 text-xs text-zinc-500">
                                                Worker
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-3">
                                            <template v-if="!worker.reachable">
                                                <div class="text-right">
                                                    <div class="text-xs font-black text-status-danger">
                                                        Unreachable
                                                    </div>

                                                    <div class="mt-0.5 text-[11px] text-zinc-600">
                                                        Worker offline
                                                    </div>
                                                </div>

                                                <div class="flex size-8 items-center justify-center rounded-full border border-status-danger/30 bg-status-danger/10">
                                                    <WifiOff class="size-4 text-status-danger" />
                                                </div>
                                            </template>

                                            <template v-else-if="!worker.version_available">
                                                <div class="text-right">
                                                    <div class="text-xs font-black text-status-warning">
                                                        Version unavailable
                                                    </div>

                                                    <div class="mt-0.5 text-[11px] text-zinc-600">
                                                        Worker is reachable
                                                    </div>
                                                </div>

                                                <div class="flex size-8 items-center justify-center rounded-full border border-status-warning/30 bg-status-warning/10">
                                                    <TriangleAlert class="size-4 text-status-warning" />
                                                </div>
                                            </template>

                                            <template v-else>
                                                <div class="text-right">
                                                    <div class="font-mono text-sm font-black text-zinc-300">
                                                        {{ versionLabel(worker.version) }}
                                                    </div>

                                                    <div class="mt-0.5 text-[11px] font-bold text-status-success">
                                                        Online
                                                    </div>
                                                </div>

                                                <div class="flex size-8 items-center justify-center rounded-full border border-status-success/30 bg-status-success/10">
                                                    <CheckCircle2 class="size-4 text-status-success" />
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="p-4 text-sm font-bold text-zinc-500"
                                >
                                    No active Workers.
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="grid items-start gap-3 md:grid-cols-2">
                        <section class="grid content-start items-start gap-3 md:grid-cols-2">
                            <a
                                v-for="(link, index) in quickLinks"
                                :key="link.label"
                                :href="link.url"
                                :target="link.external ? '_blank' : undefined"
                                :rel="link.external ? 'noopener noreferrer' : undefined"
                                class="group self-start rounded-panel border border-zinc-800 bg-surface p-5 transition hover:-translate-y-0.5 hover:border-hive/50 hover:bg-surface-light"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div
                                        class="flex size-11 items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] text-hive transition group-hover:border-hive/50 group-hover:bg-hive group-hover:text-black"
                                    >
                                        <MessageCircle v-if="index === 0" class="size-5" />
                                        <BookOpen v-else-if="index === 1" class="size-5" />
                                        <Github v-else-if="index === 2" class="size-5" />
                                        <HeartHandshake v-else class="size-5" />
                                    </div>

                                    <ExternalLink
                                        v-if="link.external"
                                        class="size-4 text-zinc-600 transition group-hover:text-hive"
                                    />
                                </div>

                                <div class="mt-4 text-sm font-black text-white">
                                    {{ link.label }}
                                </div>

                                <p class="mt-1 text-xs font-bold text-zinc-500">
                                    {{ link.description }}
                                </p>
                            </a>
                        </section>

                        <section class="self-start">
                            <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="text-lg font-black">
                                        Recent Activity
                                    </h2>

                                    <div class="text-xs font-bold text-zinc-500">
                                        {{ recentLogs.length }} recent
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div
                                        v-for="log in recentLogs"
                                        :key="log.id"
                                        class="rounded-button border border-zinc-900 bg-[#0d0f11] p-4"
                                    >
                                        <div class="text-sm font-bold text-zinc-300">
                                            {{ log.description || eventLabel(log.event) }}
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-zinc-500">
                                            <span>{{ eventLabel(log.event) }}</span>
                                            <span>{{ log.user?.name || log.user?.email || 'System' }}</span>
                                            <span v-if="log.cell">{{ log.cell.name }}</span>
                                            <span>{{ formatDate(log.created_at) }}</span>
                                        </div>
                                    </div>

                                    <div
                                        v-if="recentLogs.length === 0"
                                        class="rounded-button border border-zinc-900 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500"
                                    >
                                        No activity yet.
                                    </div>
                                </div>
                            </div>
                        </section>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>