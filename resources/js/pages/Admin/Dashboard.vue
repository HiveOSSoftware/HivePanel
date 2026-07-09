<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    Activity,
    BookOpen,
    Database,
    ExternalLink,
    Github,
    HeartHandshake,
    MessageCircle,
    Server,
    Shield,
    TriangleAlert,
    Users,
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
</script>

<template>
    <AppLayout
        :context="'admin'"
    >
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
                                    <span class="text-white">{{ versionStatus.latest }}</span>
                                    and you are currently running
                                    <span class="text-white">{{ versionStatus.current }}</span>.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-5">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <Server class="size-5 text-hive" />
                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">Nodes</div>
                            <div class="mt-1 text-2xl font-black">{{ stats.nodes }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ stats.active_nodes }} active</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <Database class="size-5 text-status-success" />
                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">Cells</div>
                            <div class="mt-1 text-2xl font-black">{{ stats.cells }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <Users class="size-5 text-status-warning" />
                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">Users</div>
                            <div class="mt-1 text-2xl font-black">{{ stats.users }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5 md:col-span-2">
                            <Activity class="size-5 text-purple-300" />
                            <div class="mt-3 text-xs font-black uppercase tracking-wide text-zinc-500">Audit Logs</div>
                            <div class="mt-1 text-2xl font-black">{{ stats.audit_logs }}</div>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-2">
                        <section class="grid gap-3 md:grid-cols-2">
                            <a
                                v-for="(link, index) in quickLinks"
                                :key="link.label"
                                :href="link.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group rounded-panel border border-zinc-800 bg-surface p-5 transition hover:-translate-y-0.5 hover:border-hive/50 hover:bg-surface-light"
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

                                    <ExternalLink class="size-4 text-zinc-600 transition group-hover:text-hive" />
                                </div>

                                <div class="mt-4 text-sm font-black text-white">
                                    {{ link.label }}
                                </div>

                                <p class="mt-1 text-xs font-bold text-zinc-500">
                                    {{ link.description }}
                                </p>
                            </a>
                        </section>

                        <section class="grid gap-5">
                            <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                                <h2 class="text-lg font-black">Recent Activity</h2>

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