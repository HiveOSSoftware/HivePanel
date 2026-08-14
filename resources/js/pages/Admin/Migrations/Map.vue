<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    ArrowRight,
    Boxes,
    Check,
    CircleAlert,
    Network,
    Server,
    Users,
} from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
    migration: any
    servers: any[]
    nodes: any[]
    users: any[]
    combs: any[]
    sourceOwners: any[]
    sourceNodes: any[]
    sourceEggs: any[]
}>()

const form = useForm({
    selected_server_ids: props.servers
        .filter((server) =>
            server.selected !== false
            && !server.migration_duplicate
            && !server.source_metadata?.migration_duplicate
        )
        .map((server) => server.id),

    owner_transfer: Object.fromEntries(
        props.sourceOwners.map((item) => [
            item.key,
            true,
        ])
    ),

    owner_map: Object.fromEntries(
        props.sourceOwners.map((item) => [
            item.key,
            item.destination_id
                ?? (item.matched ? '' : '__create__'),
        ])
    ),

    owner_create: Object.fromEntries(
        props.sourceOwners.map((item) => [
            item.key,
            {
                name: item.create_name ?? '',
                email: item.source,
                preserve_password: Boolean(
                    item.database?.preserve_by_default
                    && item.database?.password_compatible
                ),
            },
        ])
    ),

    node_map: Object.fromEntries(
        props.sourceNodes.map((item) => [
            item.key,
            item.destination_id ?? '',
        ])
    ),

    comb_map: Object.fromEntries(
        props.sourceEggs.map((item) => [
            item.key,
            item.destination_external_id
                ?? (item.matched ? '' : '__create__'),
        ])
    ),

    comb_create: Object.fromEntries(
        props.sourceEggs.map((item) => [
            item.key,
            {
                ...item.create,
            },
        ])
    ),

    database_selection: Object.fromEntries(
        props.servers.map((server) => [
            server.id,
            (server.database_plan?.length
                ? server.database_plan
                    .filter((database: any) => database.selected)
                    .map((database: any) => String(database.source?.id ?? database.source?.database ?? ''))
                : (server.source_metadata?.databases ?? [])
                    .map((database: any) => String(database.id ?? database.database ?? ''))
            ),
        ])
    ),

    allocation_strategy: props.servers.find((server) => server.selected)?.allocation_strategy ?? 'preserve',
})

function migrationDuplicate(server: any) {
    return server.migration_duplicate
        ?? server.source_metadata?.migration_duplicate
        ?? null
}

function serverSelectable(server: any) {
    return !migrationDuplicate(server)
}

const alreadyMigratedCount = computed(() =>
    props.servers.filter((server) =>
        !serverSelectable(server)
    ).length
)

const selectedCount = computed(() => form.selected_server_ids.length)

const selectedServers = computed(() =>
    props.servers.filter((server) =>
        form.selected_server_ids.includes(server.id)
    )
)

const requiredOwnerEmails = computed(() =>
    new Set(
        selectedServers.value
            .map((server) => server.owner_email)
            .filter(Boolean)
    )
)

const requiredNodeNames = computed(() =>
    new Set(
        selectedServers.value
            .map((server) => server.source_node_name)
            .filter(Boolean)
    )
)

const requiredEggNames = computed(() =>
    new Set(
        selectedServers.value
            .map((server) => server.source_egg_name)
            .filter(Boolean)
    )
)

const requiredOwners = computed(() =>
    props.sourceOwners.filter((item) =>
        requiredOwnerEmails.value.has(item.source)
    )
)

const requiredNodes = computed(() =>
    props.sourceNodes.filter((item) =>
        requiredNodeNames.value.has(item.source)
    )
)

const requiredEggs = computed(() =>
    props.sourceEggs.filter((item) =>
        requiredEggNames.value.has(item.source)
    )
)

const ownerMappedCount = computed(() =>
    requiredOwners.value.filter((item) =>
        Boolean(form.owner_map[item.key])
    ).length
)

const nodeMappedCount = computed(() =>
    requiredNodes.value.filter((item) =>
        Boolean(form.node_map[item.key])
    ).length
)

const eggMappedCount = computed(() =>
    requiredEggs.value.filter((item) =>
        Boolean(form.comb_map[item.key])
    ).length
)

const canContinue = computed(() =>
    selectedCount.value > 0
    && ownerMappedCount.value === requiredOwners.value.length
    && nodeMappedCount.value === requiredNodes.value.length
    && eggMappedCount.value === requiredEggs.value.length
)

function toggleServer(server: any) {
    if (!serverSelectable(server)) {
        return
    }

    if (form.selected_server_ids.includes(server.id)) {
        form.selected_server_ids = form.selected_server_ids.filter((value) => value !== server.id)
        return
    }

    form.selected_server_ids = [
        ...form.selected_server_ids,
        server.id,
    ]
}

function selectAll() {
    form.selected_server_ids = props.servers
        .filter((server) => serverSelectable(server))
        .map((server) => server.id)
}

function selectNone() {
    form.selected_server_ids = []
}

function toggleOwnerTransfer(owner: any) {
    const next = !form.owner_transfer[owner.key]
    form.owner_transfer[owner.key] = next

    if (!next && form.owner_map[owner.key] === '__create__') {
        form.owner_map[owner.key] = ''
    }
}

function databaseId(database: any) {
    return String(database.id ?? database.database ?? '')
}

function databaseSelected(serverId: string, database: any) {
    return (form.database_selection[serverId] ?? []).includes(
        databaseId(database)
    )
}

function toggleDatabase(serverId: string, database: any) {
    const id = databaseId(database)
    const current = form.database_selection[serverId] ?? []

    if (current.includes(id)) {
        form.database_selection[serverId] = current.filter(
            (value: string) => value !== id
        )
        return
    }

    form.database_selection[serverId] = [
        ...current,
        id,
    ]
}

function submit() {
    form.patch(`/admin/migrations/${props.migration.id}/mapping`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${migration.name} - Map`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full border border-hive/30 bg-hive/10 px-3 py-1 text-xs font-black text-hive">
                                        Step 2 of 3
                                    </span>

                                    <span class="text-xs font-bold text-zinc-600">
                                        Discovery → Mapping → Review
                                    </span>
                                </div>

                                <h1 class="mt-3 text-2xl font-black sm:text-3xl">
                                    Map Migration
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Map the discovered source inventory to HivePanel before anything is created or transferred.
                                </p>
                            </div>

                            <Link
                                :href="`/admin/migrations/${migration.id}`"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Back to Discovery
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Selected Servers
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ selectedCount }}/{{ servers.length - alreadyMigratedCount }}
                            </div>

                            <div
                                v-if="alreadyMigratedCount > 0"
                                class="mt-1 text-xs font-bold text-status-warning"
                            >
                                {{ alreadyMigratedCount }} already migrated
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Owners
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="ownerMappedCount === requiredOwners.length ? 'text-status-success' : 'text-status-warning'"
                            >
                                {{ ownerMappedCount }}/{{ requiredOwners.length }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Nodes
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="nodeMappedCount === requiredNodes.length ? 'text-status-success' : 'text-status-warning'"
                            >
                                {{ nodeMappedCount }}/{{ requiredNodes.length }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Combs
                            </div>

                            <div
                                class="mt-1 text-2xl font-black"
                                :class="eggMappedCount === requiredEggs.length ? 'text-status-success' : 'text-status-warning'"
                            >
                                {{ eggMappedCount }}/{{ requiredEggs.length }}
                            </div>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-3">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-2">
                                <Users class="size-5 text-hive" />
                                <h2 class="text-lg font-black">Owner Mapping</h2>
                            </div>

                            <p class="mt-1 text-sm text-zinc-500">
                                Exact email matches are selected automatically.
                            </p>

                            <div class="mt-5 space-y-3">
                                <div
                                    v-for="owner in sourceOwners"
                                    :key="owner.key"
                                    class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
                                >
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div class="break-all text-xs font-black text-zinc-400">
                                            {{ owner.source }}
                                        </div>

                                        <button
                                            type="button"
                                            class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide transition"
                                            :class="form.owner_transfer[owner.key]
                                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                : 'border-zinc-700 bg-zinc-800 text-zinc-400'"
                                            @click="toggleOwnerTransfer(owner)"
                                        >
                                            {{ form.owner_transfer[owner.key] ? 'Transfer User' : 'Do Not Transfer' }}
                                        </button>
                                    </div>

                                    <p
                                        v-if="!form.owner_transfer[owner.key]"
                                        class="mb-3 text-xs leading-5 text-status-warning"
                                    >
                                        Select an existing HivePanel user below as the fallback owner for any selected servers belonging to this source account.
                                    </p>

                                    <select
                                        v-model="form.owner_map[owner.key]"
                                        class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    >
                                        <option value="">Select HivePanel user</option>

                                        <option
                                            v-if="form.owner_transfer[owner.key]"
                                            value="__create__"
                                        >
                                            + Create new HivePanel user
                                        </option>

                                        <option
                                            v-for="user in users"
                                            :key="user.id"
                                            :value="user.id"
                                        >
                                            {{ user.name }} — {{ user.email }}
                                        </option>
                                    </select>

                                    <div
                                        v-if="form.owner_transfer[owner.key] && form.owner_map[owner.key] === '__create__'"
                                        class="mt-3 grid gap-2"
                                    >
                                        <div class="rounded-button border border-status-warning/20 bg-status-warning/5 p-3">
                                            <div class="text-[10px] font-black uppercase tracking-wide text-status-warning">
                                                Will create during execution
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                No account is created while mapping or reviewing.
                                            </p>
                                        </div>

                                        <input
                                            v-model="form.owner_create[owner.key].name"
                                            type="text"
                                            placeholder="User name"
                                            class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />

                                        <input
                                            v-model="form.owner_create[owner.key].email"
                                            type="email"
                                            placeholder="Email"
                                            class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />

                                        <label
                                            v-if="owner.database?.password_available"
                                            class="flex cursor-pointer items-start gap-2 rounded-button border border-zinc-800 bg-black/20 p-3"
                                        >
                                            <input
                                                v-model="form.owner_create[owner.key].preserve_password"
                                                type="checkbox"
                                                class="mt-0.5 size-4 accent-hive"
                                                :disabled="!owner.database?.password_compatible"
                                            />

                                            <div>
                                                <div class="text-xs font-black text-white">
                                                    Preserve existing password
                                                </div>

                                                <p
                                                    class="mt-1 text-xs leading-5"
                                                    :class="owner.database?.password_compatible
                                                        ? 'text-zinc-500'
                                                        : 'text-status-warning'"
                                                >
                                                    <template v-if="owner.database?.password_compatible">
                                                        {{ owner.database?.password_hash_type }} hash is compatible with HivePanel.
                                                    </template>

                                                    <template v-else>
                                                        {{ owner.database?.password_hash_type || 'Source' }} hash is not compatible with HivePanel's configured hasher; this user will need a password reset.
                                                    </template>
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-2">
                                <Server class="size-5 text-hive" />
                                <h2 class="text-lg font-black">Node Mapping</h2>
                            </div>

                            <p class="mt-1 text-sm text-zinc-500">
                                Source and destination nodes with the same name are selected automatically.
                            </p>

                            <div class="mt-5 space-y-3">
                                <div
                                    v-for="sourceNode in sourceNodes"
                                    :key="sourceNode.key"
                                    class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
                                >
                                    <div class="mb-2 text-xs font-black text-zinc-400">
                                        {{ sourceNode.source }}
                                    </div>

                                    <select
                                        v-model="form.node_map[sourceNode.key]"
                                        class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    >
                                        <option value="">Select HivePanel node</option>

                                        <option
                                            v-for="node in nodes"
                                            :key="node.id"
                                            :value="node.id"
                                        >
                                            {{ node.name }}{{ node.location ? ` — ${node.location}` : '' }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="flex items-center gap-2">
                                <Boxes class="size-5 text-hive" />
                                <h2 class="text-lg font-black">Egg → Comb Mapping</h2>
                            </div>

                            <p class="mt-1 text-sm text-zinc-500">
                                HivePanel suggests a Comb from the Egg name where possible.
                            </p>

                            <div class="mt-5 space-y-3">
                                <div
                                    v-for="egg in sourceEggs"
                                    :key="egg.key"
                                    class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
                                >
                                    <div class="mb-2 text-xs font-black text-zinc-400">
                                        {{ egg.source }}
                                    </div>

                                    <select
                                        v-model="form.comb_map[egg.key]"
                                        class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                    >
                                        <option value="">Select HivePanel Comb</option>

                                        <option value="__create__">
                                            + Create draft Comb from Pterodactyl Egg
                                        </option>

                                        <option
                                            v-for="comb in combs"
                                            :key="comb.id"
                                            :value="comb.external_id"
                                        >
                                            {{ comb.game }} — {{ comb.name }}
                                        </option>
                                    </select>

                                    <div
                                        v-if="form.comb_map[egg.key] === '__create__'"
                                        class="mt-3 space-y-2"
                                    >
                                        <div class="rounded-button border border-status-warning/20 bg-status-warning/5 p-3">
                                            <div class="text-[10px] font-black uppercase tracking-wide text-status-warning">
                                                Draft Comb will be created
                                            </div>

                                            <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                Review the generated definition before execution.
                                            </p>
                                        </div>

                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <input
                                                v-model="form.comb_create[egg.key].name"
                                                type="text"
                                                placeholder="Comb name"
                                                class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                            />

                                            <input
                                                v-model="form.comb_create[egg.key].game"
                                                type="text"
                                                placeholder="Game"
                                                class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <input
                                            v-model="form.comb_create[egg.key].external_id"
                                            type="text"
                                            placeholder="External ID"
                                            class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 font-mono text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />

                                        <input
                                            v-model="form.comb_create[egg.key].docker_image"
                                            type="text"
                                            placeholder="Docker image"
                                            class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 font-mono text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />

                                        <textarea
                                            v-model="form.comb_create[egg.key].startup"
                                            rows="3"
                                            placeholder="Startup command"
                                            class="w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 font-mono text-xs font-bold text-white outline-none transition focus:border-hive"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Network class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Allocation Strategy</h2>
                                </div>

                                <p class="mt-1 max-w-3xl text-sm text-zinc-500">
                                    Preserve keeps the source IP/port intent for the execution planner. Allocate New will choose free HivePanel allocations instead.
                                </p>
                            </div>

                            <div class="grid min-w-[320px] grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="rounded-button border px-4 py-3 text-sm font-black transition"
                                    :class="form.allocation_strategy === 'preserve'
                                        ? 'border-hive bg-hive/10 text-hive'
                                        : 'border-zinc-800 bg-[#0d0f11] text-zinc-400'"
                                    @click="form.allocation_strategy = 'preserve'"
                                >
                                    Preserve
                                </button>

                                <button
                                    type="button"
                                    class="rounded-button border px-4 py-3 text-sm font-black transition"
                                    :class="form.allocation_strategy === 'allocate_new'
                                        ? 'border-hive bg-hive/10 text-hive'
                                        : 'border-zinc-800 bg-[#0d0f11] text-zinc-400'"
                                    @click="form.allocation_strategy = 'allocate_new'"
                                >
                                    Allocate New
                                </button>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="alreadyMigratedCount > 0"
                        class="rounded-panel border border-status-warning/30 bg-status-warning/10 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <CircleAlert class="mt-0.5 size-5 shrink-0 text-status-warning" />

                            <div>
                                <div class="text-sm font-black text-status-warning">
                                    {{ alreadyMigratedCount }} server{{ alreadyMigratedCount === 1 ? '' : 's' }} already migrated
                                </div>

                                <p class="mt-1 text-xs leading-5 text-zinc-400">
                                    HivePanel found an existing destination Cell for the same source panel and server UUID. These servers are excluded from selection to prevent duplicate Cells.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="flex flex-col gap-3 border-b border-zinc-800 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div>
                                <h2 class="text-lg font-black">Servers to Migrate</h2>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Deselect test or retired servers you do not want included. Servers already migrated to HivePanel are locked.
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-xs font-black text-zinc-300"
                                    @click="selectAll"
                                >
                                    Select All
                                </button>

                                <button
                                    type="button"
                                    class="rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-xs font-black text-zinc-300"
                                    @click="selectNone"
                                >
                                    Select None
                                </button>
                            </div>
                        </div>

                        <div class="divide-y divide-zinc-800">
                            <div
                                v-for="server in servers"
                                :key="server.id"
                                class="p-4 transition sm:px-6"
                                :class="serverSelectable(server)
                                    ? 'hover:bg-surface-light/40'
                                    : 'bg-status-warning/[0.03]'"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-4 text-left"
                                    :class="serverSelectable(server)
                                        ? ''
                                        : 'cursor-not-allowed opacity-75'"
                                    :disabled="!serverSelectable(server)"
                                    @click="toggleServer(server)"
                                >
                                    <div
                                        class="flex size-5 shrink-0 items-center justify-center rounded border"
                                        :class="!serverSelectable(server)
                                            ? 'border-status-warning/40 bg-status-warning/10 text-status-warning'
                                            : (
                                                form.selected_server_ids.includes(server.id)
                                                    ? 'border-hive bg-hive text-black'
                                                    : 'border-zinc-700 bg-[#0d0f11]'
                                            )"
                                    >
                                        <Check
                                            v-if="form.selected_server_ids.includes(server.id)"
                                            class="size-3.5"
                                        />

                                        <CircleAlert
                                            v-else-if="!serverSelectable(server)"
                                            class="size-3.5"
                                        />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="font-black text-white">
                                                {{ server.name }}
                                            </div>

                                            <a
                                                v-if="migrationDuplicate(server)"
                                                :href="`/admin/cells/${migrationDuplicate(server).cell_id}`"
                                                class="inline-flex items-center gap-1 rounded-full border border-status-warning/30 bg-status-warning/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-status-warning transition hover:bg-status-warning/20"
                                                @click.stop
                                            >
                                                Already Migrated · {{ migrationDuplicate(server).cell_name }}
                                            </a>
                                        </div>

                                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                                            <span>{{ server.owner_email }}</span>
                                            <span>{{ server.source_node_name }}</span>
                                            <span>{{ server.source_egg_name }}</span>

                                            <span v-if="(server.source_metadata?.databases ?? []).length > 0">
                                                {{ (server.source_metadata?.databases ?? []).length }} database{{ (server.source_metadata?.databases ?? []).length === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                    </div>
                                </button>

                                <div
                                    v-if="form.selected_server_ids.includes(server.id) && (server.source_metadata?.databases ?? []).length > 0"
                                    class="ml-9 mt-4 rounded-button border border-zinc-800 bg-black/20 p-3"
                                >
                                    <div class="mb-2 text-[10px] font-black uppercase tracking-wide text-zinc-600">
                                        Databases to Transfer
                                    </div>

                                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                        <button
                                            v-for="database in server.source_metadata.databases"
                                            :key="databaseId(database)"
                                            type="button"
                                            class="flex items-start gap-2 rounded-button border p-3 text-left transition"
                                            :class="databaseSelected(server.id, database)
                                                ? 'border-hive/40 bg-hive/10'
                                                : 'border-zinc-800 bg-[#0d0f11]'"
                                            @click="toggleDatabase(server.id, database)"
                                        >
                                            <div
                                                class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border"
                                                :class="databaseSelected(server.id, database)
                                                    ? 'border-hive bg-hive text-black'
                                                    : 'border-zinc-700'"
                                            >
                                                <Check
                                                    v-if="databaseSelected(server.id, database)"
                                                    class="size-3"
                                                />
                                            </div>

                                            <div class="min-w-0">
                                                <div class="truncate font-mono text-xs font-black text-white">
                                                    {{ database.database || `Database #${database.id}` }}
                                                </div>

                                                <div class="mt-1 truncate text-[11px] text-zinc-500">
                                                    {{ database.host?.name || database.host?.host || 'Unknown host' }}
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="form.errors.mapping"
                        class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                    >
                        <div class="flex items-start gap-2">
                            <CircleAlert class="mt-0.5 size-4 shrink-0" />
                            {{ form.errors.mapping }}
                        </div>
                    </section>

                    <section class="flex flex-col gap-3 rounded-panel border border-zinc-800 bg-surface p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div>
                            <div class="font-black text-white">
                                {{ selectedCount }} servers selected
                            </div>

                            <div class="mt-1 text-xs text-zinc-500">
                                No Cells will be created until the execution stage.
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="form.processing || !canContinue"
                            @click="submit"
                        >
                            {{ form.processing ? 'Saving...' : 'Save & Review' }}
                            <ArrowRight class="size-4" />
                        </button>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
