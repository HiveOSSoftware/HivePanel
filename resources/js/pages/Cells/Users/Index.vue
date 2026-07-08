<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Crown,
    Pencil,
    Shield,
    Trash2,
    UserPlus,
    Users,
} from 'lucide-vue-next'

type SubUser = {
    id: number
    permissions: string[]
    accepted_at?: string | null
    user: {
        id: number
        name?: string
        email: string
    }
}

const props = defineProps<{
    cell: any
    users: SubUser[]
}>()

function removeUser(user: SubUser) {
    if (!confirm(`Remove ${user.user.email} from this server?`)) return

    router.delete(`/cells/${props.cell.id}/users/${user.user.id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout
        context="server"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Sub Users`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="overflow-hidden rounded-panel border border-hive/10 bg-surface">
                        <div class="relative p-5 sm:p-6">
                            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,196,0,0.12),transparent_35%)]" />

                            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-xl border border-hive/20 bg-hive/10 p-3">
                                        <Users class="size-6 text-hive" />
                                    </div>

                                    <div>
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            Sub Users
                                        </h1>
                                        <p class="mt-1 text-sm text-zinc-400">
                                            Users with access to {{ cell.name }}.
                                        </p>
                                    </div>
                                </div>

                                <Link
                                    :href="`/cells/${cell.id}/users/create`"
                                    class="inline-flex items-center justify-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive/90"
                                >
                                    <UserPlus class="size-4" />
                                    Invite User
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <Shield class="size-5 text-hive" />
                                <div>
                                    <h2 class="font-black text-white">
                                        Server Access
                                    </h2>
                                    <p class="text-sm text-zinc-500">
                                        Manage users who can co-manage this server.
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-full border border-hive/20 bg-hive/10 px-3 py-1 text-xs font-black text-hive">
                                {{ users.length }} Sub Users
                            </div>
                        </div>

                        <div class="mb-4 rounded-button border border-hive/10 bg-[#0d0f11] p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full border border-hive/30 bg-hive/10">
                                    <Crown class="size-4 text-hive" />
                                </div>

                                <div class="min-w-0">
                                    <div class="font-black text-white">
                                        {{ cell.owner?.name ?? 'Server Owner' }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ cell.owner?.email ?? 'Full server access' }}
                                    </div>
                                </div>

                                <span class="ml-auto rounded-full border border-hive/20 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                    Owner
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="users.length === 0"
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Users class="mx-auto size-10 text-zinc-700" />
                            <h3 class="mt-4 text-lg font-black text-zinc-300">
                                No sub users yet
                            </h3>
                            <p class="mt-2 text-sm text-zinc-500">
                                Invite someone to help manage this server.
                            </p>
                        </div>

                        <div v-else class="overflow-hidden rounded-button border border-zinc-900">
                            <div class="divide-y divide-zinc-900">
                                <article
                                    v-for="user in users"
                                    :key="user.id"
                                    class="flex flex-col gap-4 bg-[#0d0f11] p-4 transition hover:bg-surface-light/40 lg:flex-row lg:items-center lg:justify-between"
                                >
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex size-10 items-center justify-center rounded-full border border-zinc-800 bg-surface-light">
                                            <Users class="size-4 text-zinc-400" />
                                        </div>

                                        <div class="min-w-0">
                                            <h3 class="truncate font-black text-white">
                                                {{ user.user.name || user.user.email }}
                                            </h3>
                                            <p class="truncate text-xs text-zinc-500">
                                                {{ user.user.email }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                        <span class="rounded-full border border-zinc-800 bg-surface-light px-2 py-0.5 text-xs font-bold text-zinc-400">
                                            {{ user.permissions.length }} Permissions
                                        </span>

                                        <Link
                                            :href="`/cells/${cell.id}/users/${user.user.id}/edit`"
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive/30 hover:text-hive"
                                        >
                                            <Pencil class="size-4" />
                                            Edit
                                        </Link>

                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-bold text-status-danger transition hover:bg-status-danger/15"
                                            @click="removeUser(user)"
                                        >
                                            <Trash2 class="size-4" />
                                            Remove
                                        </button>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>