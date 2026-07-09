<script setup lang="ts">
import DataTable from '@/components/ui/DataTable.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { Eye, Mail, Pencil, Plus, Server, Shield, User } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
    users: any[]
}>()

const columns = [
    { key: 'user', label: 'User', sortable: true },
    { key: 'role', label: 'Role', sortable: true },
    { key: 'cells_count', label: 'Cells', sortable: true, align: 'center' },
    { key: 'created_at', label: 'Created', sortable: true },
    { key: 'actions', label: 'Actions', align: 'right' },
]

const totalUsers = computed(() => props.users.length)
const adminCount = computed(() => props.users.filter((user) => user.is_admin).length)
const cellCount = computed(() => props.users.reduce((total, user) => total + Number(user.cells_count ?? 0), 0))

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleDateString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Admin Users" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <User class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Users
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Manage HivePanel users, administrators and owned cells.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/users/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90"
                            >
                                <Plus class="size-4" />
                                New User
                            </Link>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Total Users
                            </div>
                            <div class="mt-1 text-2xl font-black">
                                {{ totalUsers }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Administrators
                            </div>
                            <div class="mt-1 text-2xl font-black text-hive">
                                {{ adminCount }}
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                Owned Cells
                            </div>
                            <div class="mt-1 text-2xl font-black">
                                {{ cellCount }}
                            </div>
                        </div>
                    </section>

                    <DataTable
                        :columns="columns"
                        :rows="users"
                        search-placeholder="Search users by name, email or role..."
                        empty-title="No users found"
                        empty-description="No users match your search."
                        :per-page="20"
                    >
                        <template #cell-user="{ row }">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-full border border-hive/20 bg-hive/10 text-sm font-black text-hive">
                                    {{ row.name?.charAt(0)?.toUpperCase() ?? '?' }}
                                </div>

                                <div>
                                    <Link
                                        :href="`/admin/users/${row.id}`"
                                        class="font-black text-white transition hover:text-hive"
                                    >
                                        {{ row.name }}
                                    </Link>

                                    <div class="mt-1 flex items-center gap-2 text-xs text-zinc-500">
                                        <Mail class="size-3" />
                                        {{ row.email }}
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #cell-role="{ row }">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-black"
                                :class="row.is_admin
                                    ? 'border-hive/30 bg-hive/10 text-hive'
                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400'"
                            >
                                <Shield v-if="row.is_admin" class="size-3" />
                                {{ row.is_admin ? 'Admin' : 'User' }}
                            </span>
                        </template>

                        <template #cell-cells_count="{ row }">
                            <span class="inline-flex items-center justify-center gap-2 rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 text-xs font-black text-zinc-300">
                                <Server class="size-3" />
                                {{ row.cells_count ?? 0 }}
                            </span>
                        </template>

                        <template #cell-created_at="{ row }">
                            <span class="text-sm font-bold text-zinc-500">
                                {{ formatDate(row.created_at) }}
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="`/admin/users/${row.id}`"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <Eye class="size-4" />
                                    View
                                </Link>

                                <Link
                                    :href="`/admin/users/${row.id}/edit`"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                </Link>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </main>
        </div>
    </AppLayout>
</template>