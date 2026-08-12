<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowRight,
    CircleAlert,
    CircleCheck,
    Clock3,
    Plus,
    RefreshCw,
    ServerCog,
} from 'lucide-vue-next'

defineProps<{
    migrations: any[]
}>()

function statusClass(status: string) {
    switch (status) {
        case 'ready':
        case 'completed':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'discovering':
        case 'running':
            return 'border-hive/30 bg-hive/10 text-hive'

        case 'failed':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'
    }
}

function statusIcon(status: string) {
    switch (status) {
        case 'ready':
        case 'completed':
            return CircleCheck

        case 'failed':
            return CircleAlert

        case 'discovering':
        case 'running':
            return RefreshCw

        default:
            return Clock3
    }
}

function formatDate(value?: string) {
    if (!value) return 'Never'

    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Migrations" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <ServerCog class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Platform Migrations
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Discover and migrate servers from another hosting panel into HivePanel.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/migrations/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus class="size-4" />
                                New Migration
                            </Link>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-lg font-black">
                                Migration Jobs
                            </h2>

                            <p class="mt-1 text-sm text-zinc-500">
                                Source credentials are encrypted and only retained for the lifetime of the migration job.
                            </p>
                        </div>

                        <div
                            v-if="migrations.length === 0"
                            class="p-10 text-center"
                        >
                            <ServerCog class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No migrations yet
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Connect a Pterodactyl panel to discover your first source servers.
                            </p>
                        </div>

                        <div v-else class="divide-y divide-zinc-800">
                            <Link
                                v-for="migration in migrations"
                                :key="migration.id"
                                :href="`/admin/migrations/${migration.id}`"
                                class="group block p-5 transition hover:bg-surface-light/40 sm:p-6"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-black text-white transition group-hover:text-hive">
                                                {{ migration.name }}
                                            </h3>

                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black"
                                                :class="statusClass(migration.status)"
                                            >
                                                <component
                                                    :is="statusIcon(migration.status)"
                                                    class="size-3.5"
                                                    :class="{ 'animate-spin': ['discovering', 'running'].includes(migration.status) }"
                                                />
                                                {{ migration.status }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-zinc-500">
                                            <span>{{ migration.source_type }}</span>
                                            <span>{{ migration.servers_count }} servers</span>
                                            <span>Created {{ formatDate(migration.created_at) }}</span>
                                        </div>

                                        <p
                                            v-if="migration.current_stage"
                                            class="mt-2 text-sm text-zinc-500"
                                        >
                                            {{ migration.current_stage }}
                                        </p>
                                    </div>

                                    <div class="inline-flex items-center gap-2 text-sm font-black text-hive">
                                        Open
                                        <ArrowRight class="size-4" />
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
