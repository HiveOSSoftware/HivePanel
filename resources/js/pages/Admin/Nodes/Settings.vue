<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeft,
    HardDrive,
    Save,
    Server,
    Settings,
    Shield,
    SlidersHorizontal,
} from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{
    node: any
}>()

const saving = ref(false)

const form = ref({
    name: props.node.name ?? '',
    description: props.node.description ?? '',
    location: props.node.location ?? '',

    public_fqdn: props.node.public_fqdn ?? props.node.fqdn ?? '',
    internal_fqdn: props.node.internal_fqdn ?? '',

    scheme: props.node.scheme ?? 'http',
    daemon_port: props.node.daemon_port ?? props.node.port ?? 8080,
    sftp_port: props.node.sftp_port ?? 2022,

    behind_proxy: props.node.behind_proxy ?? false,
    maintenance_mode: props.node.maintenance_mode ?? false,
    is_active: props.node.is_active ?? true,

    cpu_threads: props.node.cpu_threads ?? '',
    memory_mib: props.node.memory_mib ?? '',
    memory_overallocate: props.node.memory_overallocate ?? 0,
    disk_mib: props.node.disk_mib ?? '',
    disk_overallocate: props.node.disk_overallocate ?? 0,
    max_upload_mib: props.node.max_upload_mib ?? 100,
})

function save() {
    saving.value = true

    router.patch(`/admin/nodes/${props.node.id}/settings`, form.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
        },
    })
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Settings`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-zinc-500">
                            <Link href="/admin" class="hover:text-hive">Admin</Link>
                            <span>›</span>
                            <Link href="/admin/nodes" class="hover:text-hive">Nodes</Link>
                            <span>›</span>
                            <Link :href="`/admin/nodes/${node.id}`" class="hover:text-hive">
                                {{ node.name }}
                            </Link>
                            <span>›</span>
                            <span class="text-zinc-300">Settings</span>
                        </div>

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h1 class="text-3xl font-black tracking-tight">
                                    Node Settings
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    Configure connection details and usable resource limits.
                                </p>
                            </div>

                            <Link
                                :href="`/admin/nodes/${node.id}`"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Back to Node
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-1">
                        <div class="flex flex-wrap gap-1">
                            <Link
                                :href="`/admin/nodes/${node.id}`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Activity class="size-4" />
                                    Overview
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/settings`"
                                class="rounded-button bg-hive/10 px-4 py-3 text-sm font-black text-hive"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Settings class="size-4" />
                                    Settings
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/configuration`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <SlidersHorizontal class="size-4" />
                                    Configuration
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/allocations`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <HardDrive class="size-4" />
                                    Allocation
                                </span>
                            </Link>

                            <Link
                                :href="`/admin/nodes/${node.id}/servers`"
                                class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <Server class="size-4" />
                                    Servers
                                </span>
                            </Link>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">Connection</h2>

                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Name</span>
                                        <input v-model="form.name" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Location</span>
                                        <input v-model="form.location" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2 md:col-span-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Description</span>
                                        <textarea v-model="form.description" rows="3" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Public FQDN / IP</span>
                                        <input v-model="form.public_fqdn" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Internal FQDN / IP</span>
                                        <input v-model="form.internal_fqdn" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" placeholder="Leave blank to use public FQDN" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Scheme</span>
                                        <select v-model="form.scheme" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive">
                                            <option value="http">HTTP</option>
                                            <option value="https">HTTPS</option>
                                        </select>
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Daemon Port</span>
                                        <input v-model="form.daemon_port" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">SFTP Port</span>
                                        <input v-model="form.sftp_port" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Max Upload MiB</span>
                                        <input v-model="form.max_upload_mib" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">Allocation Limits</h2>

                                <div class="mt-5 grid gap-4 md:grid-cols-3">
                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">CPU Threads</span>
                                        <input v-model="form.cpu_threads" type="number" step="0.01" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Memory MiB</span>
                                        <input v-model="form.memory_mib" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Memory Overallocate %</span>
                                        <input v-model="form.memory_overallocate" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Disk MiB</span>
                                        <input v-model="form.disk_mib" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>

                                    <label class="space-y-2">
                                        <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Disk Overallocate %</span>
                                        <input v-model="form.disk_overallocate" type="number" class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive" />
                                    </label>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">Node State</h2>

                                <label class="mt-5 flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                                    <span>
                                        <span class="block text-sm font-bold text-zinc-300">Active</span>
                                        <span class="text-xs text-zinc-500">
                                            Allow new cells to be created on this node.
                                        </span>
                                    </span>

                                    <input v-model="form.is_active" type="checkbox" class="accent-hive" />
                                </label>
                                <label class="mt-5 flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                                    <span>
                                        <span class="block text-sm font-bold text-zinc-300">Active</span>
                                        <span class="text-xs text-zinc-500">Allow new cells to be created on this node.</span>
                                    </span>
                                    <input v-model="form.is_active" type="checkbox" class="accent-hive" />
                                </label>

                                <label class="mt-3 flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                                    <span>
                                        <span class="block text-sm font-bold text-zinc-300">Maintenance Mode</span>
                                        <span class="text-xs text-zinc-500">Prevent users accessing servers on this node.</span>
                                    </span>
                                    <input v-model="form.maintenance_mode" type="checkbox" class="accent-hive" />
                                </label>

                                <label class="mt-3 flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                                    <span>
                                        <span class="block text-sm font-bold text-zinc-300">Behind Proxy</span>
                                        <span class="text-xs text-zinc-500">Use when the worker sits behind Cloudflare or another proxy.</span>
                                    </span>
                                    <input v-model="form.behind_proxy" type="checkbox" class="accent-hive" />
                                </label>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">Save Changes</h2>

                                <p class="mt-2 text-sm leading-6 text-zinc-500">
                                    Changing the node URL affects how the panel communicates with the worker.
                                </p>

                                <button
                                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-3 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="saving"
                                    @click="save"
                                >
                                    <Save class="size-4" />
                                    {{ saving ? 'Saving...' : 'Save Node Settings' }}
                                </button>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>