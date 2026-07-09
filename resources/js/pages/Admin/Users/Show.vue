<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, Edit, Mail, Server, Shield, User } from 'lucide-vue-next'

defineProps<{
    user: any
    cells: any[]
}>()

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="user.name" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex size-14 items-center justify-center rounded-full border border-hive/20 bg-hive/10 text-xl font-black text-hive">
                                    {{ user.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">{{ user.name }}</h1>
                                    <p class="mt-2 flex items-center gap-2 text-sm text-zinc-400">
                                        <Mail class="size-4" />
                                        {{ user.email }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Link href="/admin/users" class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 hover:text-hive">
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>

                                <Link :href="`/admin/users/${user.id}/edit`" class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black hover:opacity-90">
                                    <Edit class="size-4" />
                                    Edit
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase text-zinc-500">Role</div>
                            <div class="mt-2 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-black"
                                :class="user.is_admin ? 'border-hive/30 bg-hive/10 text-hive' : 'border-zinc-700 bg-zinc-800 text-zinc-400'">
                                <Shield v-if="user.is_admin" class="size-3" />
                                {{ user.is_admin ? 'Admin' : 'User' }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase text-zinc-500">Cells</div>
                            <div class="mt-1 text-2xl font-black text-hive">{{ user.cells_count ?? cells.length }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase text-zinc-500">Created</div>
                            <div class="mt-1 text-sm font-black text-white">{{ formatDate(user.created_at) }}</div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase text-zinc-500">Updated</div>
                            <div class="mt-1 text-sm font-black text-white">{{ formatDate(user.updated_at) }}</div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-lg font-black">Owned Cells</h2>
                            <p class="mt-1 text-sm text-zinc-500">Cells assigned to this user.</p>
                        </div>

                        <div v-if="cells.length === 0" class="p-8 text-center text-zinc-500">
                            No cells assigned.
                        </div>

                        <table v-else class="min-w-full divide-y divide-zinc-800">
                            <tbody class="divide-y divide-zinc-800">
                                <tr v-for="cell in cells" :key="cell.id" class="hover:bg-surface-light/40">
                                    <td class="px-5 py-4">
                                        <Link :href="`/admin/cells/${cell.id}`" class="font-black text-white hover:text-hive">
                                            {{ cell.name }}
                                        </Link>
                                        <div class="mt-1 text-xs text-zinc-500">{{ cell.comb }}</div>
                                    </td>

                                    <td class="px-5 py-4 text-sm text-zinc-400">
                                        <Server class="mr-2 inline size-4 text-zinc-500" />
                                        {{ cell.node?.name || 'Unknown node' }}
                                    </td>

                                    <td class="px-5 py-4 text-right text-sm font-mono text-zinc-400">
                                        <span v-if="cell.allocation">{{ cell.allocation.ip }}:{{ cell.allocation.port }}</span>
                                        <span v-else>No allocation</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>