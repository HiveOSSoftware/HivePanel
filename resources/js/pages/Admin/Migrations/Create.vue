<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    KeyRound,
    ServerCog,
    Database,
    ShieldCheck,
} from 'lucide-vue-next'

const form = useForm({
    source_type: 'pterodactyl',
    name: '',
    panel_url: '',
    api_key: '',

    database_enabled: false,
    database_host: '127.0.0.1',
    database_port: 3306,
    database_name: 'panel',
    database_username: '',
    database_password: '',
    preserve_passwords: true,
})

function submit() {
    form.post('/admin/migrations', {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="New Migration" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-4xl space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <ServerCog class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        New Platform Migration
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Connect HivePanel to the source hosting panel and discover its servers.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/migrations"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label class="text-sm font-bold text-zinc-400">
                                    Source Platform
                                </label>

                                <select
                                    v-model="form.source_type"
                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                >
                                    <option value="pterodactyl">
                                        Pterodactyl
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-zinc-400">
                                    Migration Name
                                </label>

                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Old production panel"
                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                />

                                <div v-if="form.errors.name" class="mt-1 text-xs font-bold text-status-danger">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-zinc-400">
                                    Panel URL
                                </label>

                                <input
                                    v-model="form.panel_url"
                                    type="url"
                                    placeholder="https://panel.example.com"
                                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 font-mono text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                />

                                <div v-if="form.errors.panel_url" class="mt-1 text-xs font-bold text-status-danger">
                                    {{ form.errors.panel_url }}
                                </div>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-zinc-400">
                                    API Key
                                </label>

                                <div class="relative mt-2">
                                    <KeyRound class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-600" />

                                    <input
                                        v-model="form.api_key"
                                        type="password"
                                        autocomplete="off"
                                        placeholder="ptla_..."
                                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 font-mono text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                    />
                                </div>

                                <div v-if="form.errors.api_key" class="mt-1 text-xs font-bold text-status-danger">
                                    {{ form.errors.api_key }}
                                </div>
                            </div>

                            <div class="rounded-panel border border-zinc-800 bg-[#0d0f11] p-4 sm:p-5">
                                <div class="flex items-start gap-3">
                                    <Database class="mt-0.5 size-5 shrink-0 text-hive" />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div class="text-sm font-black text-white">
                                                    Advanced Database Migration
                                                </div>

                                                <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                    Optional read-only access to the Pterodactyl panel database enables password-hash preservation and server database discovery.
                                                </p>
                                            </div>

                                            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-black text-zinc-300">
                                                <input
                                                    v-model="form.database_enabled"
                                                    type="checkbox"
                                                    class="size-4 accent-hive"
                                                />
                                                Enable
                                            </label>
                                        </div>

                                        <div
                                            v-if="form.database_enabled"
                                            class="mt-5 space-y-4"
                                        >
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label class="text-xs font-black text-zinc-500">
                                                        Database Host
                                                    </label>

                                                    <input
                                                        v-model="form.database_host"
                                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="text-xs font-black text-zinc-500">
                                                        Port
                                                    </label>

                                                    <input
                                                        v-model.number="form.database_port"
                                                        type="number"
                                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="text-xs font-black text-zinc-500">
                                                        Database Name
                                                    </label>

                                                    <input
                                                        v-model="form.database_name"
                                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div>
                                                    <label class="text-xs font-black text-zinc-500">
                                                        Username
                                                    </label>

                                                    <input
                                                        v-model="form.database_username"
                                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                                    />
                                                </div>

                                                <div class="sm:col-span-2">
                                                    <label class="text-xs font-black text-zinc-500">
                                                        Password
                                                    </label>

                                                    <input
                                                        v-model="form.database_password"
                                                        type="password"
                                                        autocomplete="off"
                                                        class="mt-1 w-full rounded-button border border-zinc-800 bg-black/30 px-3 py-2.5 text-sm font-bold text-white outline-none focus:border-hive"
                                                    />
                                                </div>
                                            </div>

                                            <label class="flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-black/20 p-3">
                                                <input
                                                    v-model="form.preserve_passwords"
                                                    type="checkbox"
                                                    class="mt-0.5 size-4 accent-hive"
                                                />

                                                <div>
                                                    <div class="text-xs font-black text-white">
                                                        Preserve compatible user password hashes
                                                    </div>

                                                    <p class="mt-1 text-xs leading-5 text-zinc-500">
                                                        Compatible source hashes can be copied directly when HivePanel creates missing users, allowing those users to keep their existing password.
                                                    </p>
                                                </div>
                                            </label>

                                            <p class="text-xs leading-5 text-zinc-600">
                                                Use a read-only MySQL/MariaDB account where possible. HivePanel only performs SELECT queries against the source panel database.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-button border border-hive/20 bg-hive/5 p-4">
                                <div class="flex items-start gap-3">
                                    <ShieldCheck class="mt-0.5 size-5 shrink-0 text-hive" />

                                    <div>
                                        <div class="text-sm font-black text-white">
                                            Source credentials are encrypted
                                        </div>

                                        <p class="mt-1 text-xs leading-5 text-zinc-500">
                                            HivePanel stores the API key using Laravel's encrypted cast. We will remove source credentials automatically when we add migration finalisation.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                <ServerCog
                                    class="size-4"
                                    :class="{ 'animate-pulse': form.processing }"
                                />
                                {{ form.processing ? 'Creating Migration...' : 'Create Migration' }}
                            </button>

                            <p
                                v-if="form.processing"
                                class="text-center text-xs font-bold text-zinc-500"
                            >
                                HivePanel will create the migration immediately and queue source discovery in the background.
                            </p>
                        </form>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
