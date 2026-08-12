<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    ArrowLeft,
    Boxes,
    CircleCheck,
    Network,
    Server,
    User,
} from 'lucide-vue-next'

defineProps<{
    migration: any
    servers: any[]
    summary: {
        selected: number
        preserve_allocations: number
        allocate_new: number
        source_nodes: number
        owners: number
        users_to_create: number
        combs_to_create: number
        users_to_transfer: number
        databases_to_transfer: number
    }
}>()

function primaryAllocation(server: any) {
    const allocations = server.source_allocations ?? []

    return allocations.find((allocation: any) => allocation.is_default)
        ?? allocations[0]
        ?? null
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${migration.name} - Review`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border border-status-success/30 bg-status-success/10 px-3 py-1 text-xs font-black text-status-success">
                                        Step 3 of 3
                                    </span>

                                    <span class="text-xs font-bold text-zinc-600">
                                        Discovery → Mapping → Review
                                    </span>
                                </div>

                                <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                    Review Migration
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Confirm the destination plan before we build the execution and transfer stage.
                                </p>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}/mapping`"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Edit Mapping
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-9">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Servers</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.selected }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Owners</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.owners }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Source Nodes</div>
                            <div class="mt-1 text-2xl font-black">{{ summary.source_nodes }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Preserve</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ summary.preserve_allocations }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Allocate New</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.allocate_new }}</div>
                        </div>

                        <div class="rounded-panel border border-status-warning/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Users to Create</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.users_to_create }}</div>
                        </div>

                        <div class="rounded-panel border border-status-warning/20 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Combs to Create</div>
                            <div class="mt-1 text-2xl font-black text-status-warning">{{ summary.combs_to_create }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Users to Transfer</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ summary.users_to_transfer }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Databases</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ summary.databases_to_transfer }}</div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-lg font-black">Migration Plan</h2>

                            <p class="mt-1 text-sm text-zinc-500">
                                These mappings are persisted. No destination Cell has been created yet.
                            </p>
                        </div>

                        <div class="divide-y divide-zinc-800">
                            <div
                                v-for="server in servers"
                                :key="server.id"
                                class="p-5 sm:p-6"
                            >
                                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_40px_minmax(0,1fr)] xl:items-center">
                                    <div>
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-600">
                                            Pterodactyl
                                        </div>

                                        <div class="mt-2 text-lg font-black text-white">
                                            {{ server.name }}
                                        </div>

                                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    <User class="size-3" />
                                                    Owner
                                                </div>
                                                <div class="mt-1 break-all text-xs font-black text-zinc-300">
                                                    {{ server.owner_email }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    <Server class="size-3" />
                                                    Node
                                                </div>
                                                <div class="mt-1 text-xs font-black text-zinc-300">
                                                    {{ server.source_node_name }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    <Boxes class="size-3" />
                                                    Egg
                                                </div>
                                                <div class="mt-1 text-xs font-black text-zinc-300">
                                                    {{ server.source_egg_name }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    <Network class="size-3" />
                                                    Primary
                                                </div>
                                                <div class="mt-1 font-mono text-xs font-black text-zinc-300">
                                                    <template v-if="primaryAllocation(server)">
                                                        {{ primaryAllocation(server).ip }}:{{ primaryAllocation(server).port }}
                                                    </template>
                                                    <template v-else>
                                                        None
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="hidden items-center justify-center text-2xl font-black text-hive xl:flex">
                                        →
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-status-success">
                                            <CircleCheck class="size-4" />
                                            HivePanel Destination
                                        </div>

                                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    Owner
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <div class="text-xs font-black text-white">
                                                        {{ server.destination_owner?.name }}
                                                    </div>

                                                    <span
                                                        v-if="server.destination_owner?.will_create"
                                                        class="rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-[10px] font-black text-status-warning"
                                                    >
                                                        Will create
                                                    </span>
                                                </div>

                                                <div class="mt-1 break-all text-[11px] text-zinc-500">
                                                    {{ server.destination_owner?.email }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    Node
                                                </div>
                                                <div class="mt-1 text-xs font-black text-white">
                                                    {{ server.destination_node?.name }}
                                                </div>
                                                <div class="mt-1 text-[11px] text-zinc-500">
                                                    {{ server.destination_node?.location || 'No location' }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    Comb
                                                </div>
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <div class="text-xs font-black text-white">
                                                        {{ server.destination_comb_record?.name }}
                                                    </div>

                                                    <span
                                                        v-if="server.destination_comb_record?.will_create"
                                                        class="rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-[10px] font-black text-status-warning"
                                                    >
                                                        Will create
                                                    </span>
                                                </div>

                                                <div class="mt-1 text-[11px] text-zinc-500">
                                                    {{ server.destination_comb_record?.external_id }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    Networking
                                                </div>
                                                <div class="mt-1 text-xs font-black text-white">
                                                    {{ server.allocation_strategy === 'preserve'
                                                        ? 'Preserve source allocations'
                                                        : 'Allocate new ports' }}
                                                </div>
                                            </div>

                                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3 sm:col-span-2">
                                                <div class="text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                                    Databases
                                                </div>

                                                <div class="mt-1 text-xs font-black text-white">
                                                    {{ (server.database_plan ?? []).filter(database => database.selected).length }} selected for transfer
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-hive/30 bg-hive/5 p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <CircleCheck class="mt-0.5 size-5 shrink-0 text-hive" />

                                <div>
                                    <h2 class="font-black text-white">
                                        Mapping is ready
                                    </h2>

                                    <p class="mt-1 text-sm leading-6 text-zinc-400">
                                        Run preflight to validate allocations and configure source-node transfer access.
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}/preflight`"
                                class="inline-flex items-center justify-center rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                Run Preflight →
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
