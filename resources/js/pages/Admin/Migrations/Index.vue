<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowRight,
    CircleAlert,
    CircleCheck,
    Clock3,
    Database,
    LockKeyhole,
    Plus,
    RefreshCw,
    ServerCog,
    ShieldCheck,
} from 'lucide-vue-next'

defineProps<{
    migrations: any[]
}>()

const lifecycleSteps = [
    'Discovery',
    'Mapping',
    'Preflight',
    'Migration',
    'Verification',
    'Finalised',
]

function statusClass(status: string) {
    switch (status) {
        case 'ready':
        case 'preflight_ready':
        case 'execution_ready':
        case 'completed':
        case 'verified':
        case 'finalised':
            return 'border-status-success/30 bg-status-success/10 text-status-success'

        case 'queued':
        case 'discovering':
        case 'running':
        case 'database_transferring':
            return 'border-hive/30 bg-hive/10 text-hive'

        case 'failed':
        case 'database_failed':
        case 'verification_failed':
        case 'completed_with_errors':
        case 'preflight_blocked':
            return 'border-status-danger/30 bg-status-danger/10 text-status-danger'

        default:
            return 'border-status-warning/30 bg-status-warning/10 text-status-warning'
    }
}

function statusIcon(status: string) {
    switch (status) {
        case 'ready':
        case 'preflight_ready':
        case 'execution_ready':
        case 'completed':
        case 'verified':
            return CircleCheck

        case 'finalised':
            return LockKeyhole

        case 'failed':
        case 'database_failed':
        case 'verification_failed':
        case 'completed_with_errors':
        case 'preflight_blocked':
            return CircleAlert

        case 'queued':
        case 'discovering':
        case 'running':
        case 'database_transferring':
            return RefreshCw

        default:
            return Clock3
    }
}

function lifecycleStepClass(
    migration: any,
    index: number,
) {
    const current = Number(
        migration.lifecycle_step
        ?? 0
    )

    if (index < current) {
        return 'border-status-success/40 bg-status-success/15 text-status-success'
    }

    if (index === current) {
        if (
            [
                'failed',
                'database_failed',
                'verification_failed',
                'preflight_blocked',
                'completed_with_errors',
            ].includes(migration.status)
        ) {
            return 'border-status-danger/40 bg-status-danger/15 text-status-danger'
        }

        if (migration.status === 'finalised') {
            return 'border-status-success/40 bg-status-success/15 text-status-success'
        }

        return 'border-hive/40 bg-hive/15 text-hive'
    }

    return 'border-zinc-800 bg-[#0d0f11] text-zinc-700'
}

function lifecycleLineClass(
    migration: any,
    index: number,
) {
    return index < Number(
        migration.lifecycle_step
        ?? 0
    )
        ? 'bg-status-success/50'
        : 'bg-zinc-800'
}

function formatDate(value?: string) {
    if (!value) {
        return 'Never'
    }

    return new Date(
        value
    ).toLocaleString()
}

function serverSummary(migration: any) {
    const selected = Number(
        migration.selected_servers_count
        ?? migration.servers_count
        ?? 0
    )

    if (selected === 0) {
        return `${migration.servers_count ?? 0} discovered`
    }

    return `${migration.completed_servers_count ?? 0}/${selected} completed`
}

function databaseSummary(migration: any) {
    const count = Number(
        migration.database_count
        ?? 0
    )

    if (count === 0) {
        return 'No databases'
    }

    return `${migration.completed_database_count ?? 0}/${count} databases`
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
                                        Discover, map, transfer and verify servers from another hosting panel into HivePanel.
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
                                Finalised jobs retain their migration history and verification results, but source authentication credentials have been removed.
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
                                Connect a source panel to discover your first servers.
                            </p>
                        </div>

                        <div
                            v-else
                            class="divide-y divide-zinc-800"
                        >
                            <Link
                                v-for="migration in migrations"
                                :key="migration.id"
                                :href="`/admin/migrations/${migration.id}`"
                                class="group block p-5 transition hover:bg-surface-light/40 sm:p-6"
                            >
                                <div class="flex flex-col gap-5">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
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
                                                        :class="{
                                                            'animate-spin': [
                                                                'queued',
                                                                'discovering',
                                                                'running',
                                                                'database_transferring',
                                                            ].includes(migration.status),
                                                        }"
                                                    />

                                                    {{ migration.status_label ?? migration.status }}
                                                </span>

                                                <span
                                                    v-if="migration.finalisation?.credentials_removed"
                                                    class="inline-flex items-center gap-1 rounded-full border border-zinc-700 bg-zinc-800 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-zinc-400"
                                                >
                                                    <LockKeyhole class="size-3" />
                                                    Credentials purged
                                                </span>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs font-bold text-zinc-500">
                                                <span>{{ migration.source_label ?? migration.source_type }}</span>
                                                <span>{{ migration.servers_count }} discovered</span>
                                                <span>{{ serverSummary(migration) }}</span>

                                                <span
                                                    v-if="migration.failed_servers_count > 0"
                                                    class="text-status-danger"
                                                >
                                                    {{ migration.failed_servers_count }} failed
                                                </span>

                                                <span>Created {{ formatDate(migration.created_at) }}</span>
                                            </div>

                                            <p
                                                v-if="migration.current_stage"
                                                class="mt-2 text-sm text-zinc-500"
                                            >
                                                {{ migration.current_stage }}
                                            </p>
                                        </div>

                                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                                            <div
                                                v-if="migration.database_count > 0"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-zinc-800 bg-[#0d0f11] px-2.5 py-1 text-xs font-bold text-zinc-500"
                                            >
                                                <Database class="size-3.5" />
                                                {{ databaseSummary(migration) }}
                                            </div>

                                            <div
                                                v-if="migration.verification?.checked_at"
                                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold"
                                                :class="migration.verification.verified
                                                    ? 'border-status-success/20 bg-status-success/5 text-status-success'
                                                    : 'border-status-danger/20 bg-status-danger/5 text-status-danger'"
                                            >
                                                <ShieldCheck class="size-3.5" />
                                                {{ migration.verification.verified
                                                    ? 'Verified'
                                                    : `${migration.verification.failed ?? 0} verification issue(s)` }}
                                            </div>

                                            <div class="inline-flex items-center gap-2 text-sm font-black text-hive">
                                                Open
                                                <ArrowRight class="size-4" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="hidden items-center md:flex">
                                        <template
                                            v-for="(step, index) in lifecycleSteps"
                                            :key="step"
                                        >
                                            <div class="flex min-w-0 items-center gap-2">
                                                <div
                                                    class="flex size-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-black"
                                                    :class="lifecycleStepClass(migration, index)"
                                                >
                                                    <CircleCheck
                                                        v-if="index < Number(migration.lifecycle_step ?? 0) || (
                                                            migration.status === 'finalised'
                                                            && index === 5
                                                        )"
                                                        class="size-3.5"
                                                    />

                                                    <span v-else>
                                                        {{ index + 1 }}
                                                    </span>
                                                </div>

                                                <span
                                                    class="truncate text-[10px] font-black uppercase tracking-wide"
                                                    :class="index <= Number(migration.lifecycle_step ?? 0)
                                                        ? 'text-zinc-400'
                                                        : 'text-zinc-700'"
                                                >
                                                    {{ step }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="index < lifecycleSteps.length - 1"
                                                class="mx-3 h-px min-w-4 flex-1"
                                                :class="lifecycleLineClass(migration, index)"
                                            ></div>
                                        </template>
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
