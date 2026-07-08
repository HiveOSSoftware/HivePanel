<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Save, Server } from 'lucide-vue-next'

const form = useForm({
    name: '',
    description: '',
    location: '',

    public_fqdn: '',
    internal_fqdn: '',

    scheme: 'http',
    daemon_port: 8080,
    sftp_port: 2022,

    behind_proxy: false,
    maintenance_mode: false,
    is_active: true,

    cpu_threads: null as number | null,
    memory_mib: null as number | null,
    memory_overallocate: 0,
    disk_mib: null as number | null,
    disk_overallocate: 0,
    max_upload_mib: 100,
})

function submit() {
    form.post('/admin/nodes')
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Create Node" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <Server class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        New Node
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Add a worker host. Registration and API token setup happens on the configuration screen.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/nodes"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-white">
                                Node Details
                            </h2>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Name
                                    </label>

                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder="EU01"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Location
                                    </label>

                                    <input
                                        v-model="form.location"
                                        type="text"
                                        placeholder="London, UK"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.location" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Description
                                    </label>

                                    <textarea
                                        v-model="form.description"
                                        rows="3"
                                        placeholder="Optional notes about this worker node..."
                                        class="mt-2 w-full resize-none rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.description" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-white">
                                Network
                            </h2>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Public FQDN / IP
                                    </label>

                                    <input
                                        v-model="form.public_fqdn"
                                        type="text"
                                        placeholder="worker.example.com"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.public_fqdn" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Internal FQDN / IP
                                    </label>

                                    <input
                                        v-model="form.internal_fqdn"
                                        type="text"
                                        placeholder="10.0.0.12"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.internal_fqdn" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Scheme
                                    </label>

                                    <select
                                        v-model="form.scheme"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    >
                                        <option value="http">HTTP</option>
                                        <option value="https">HTTPS</option>
                                    </select>

                                    <InputError class="mt-2" :message="form.errors.scheme" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Daemon Port
                                    </label>

                                    <input
                                        v-model="form.daemon_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.daemon_port" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        SFTP Port
                                    </label>

                                    <input
                                        v-model="form.sftp_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.sftp_port" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-white">
                                Resource Limits
                            </h2>

                            <p class="mt-1 text-sm text-zinc-500">
                                Leave CPU, memory, or disk empty to use live worker-reported capacity after registration.
                            </p>

                            <div class="mt-5 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        CPU Threads
                                    </label>

                                    <input
                                        v-model="form.cpu_threads"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Auto"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.cpu_threads" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Memory MiB
                                    </label>

                                    <input
                                        v-model="form.memory_mib"
                                        type="number"
                                        min="0"
                                        placeholder="Auto"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.memory_mib" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Memory Overallocation %
                                    </label>

                                    <input
                                        v-model="form.memory_overallocate"
                                        type="number"
                                        min="0"
                                        max="1000"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.memory_overallocate" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Disk MiB
                                    </label>

                                    <input
                                        v-model="form.disk_mib"
                                        type="number"
                                        min="0"
                                        placeholder="Auto"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.disk_mib" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Disk Overallocation %
                                    </label>

                                    <input
                                        v-model="form.disk_overallocate"
                                        type="number"
                                        min="0"
                                        max="1000"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.disk_overallocate" />
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Max Upload MiB
                                    </label>

                                    <input
                                        v-model="form.max_upload_mib"
                                        type="number"
                                        min="1"
                                        max="1024"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <InputError class="mt-2" :message="form.errors.max_upload_mib" />
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black text-white">
                                Options
                            </h2>

                            <div class="mt-5 grid gap-3 md:grid-cols-3">
                                <label class="flex cursor-pointer items-center gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                    <input
                                        v-model="form.behind_proxy"
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                    />
                                    <span class="text-sm font-bold text-zinc-300">Behind proxy</span>
                                </label>

                                <label class="flex cursor-pointer items-center gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                    <input
                                        v-model="form.maintenance_mode"
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                    />
                                    <span class="text-sm font-bold text-zinc-300">Maintenance</span>
                                </label>

                                <label class="flex cursor-pointer items-center gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="size-4 rounded border-zinc-700 bg-[#0d0f11] text-hive focus:ring-hive"
                                    />
                                    <span class="text-sm font-bold text-zinc-300">Active</span>
                                </label>
                            </div>
                        </section>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />
                                {{ form.processing ? 'Creating...' : 'Create Node' }}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>