<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Box, Save } from 'lucide-vue-next'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'

const defaultJson = JSON.stringify({
    id: 'custom-comb',
    name: 'Custom Comb',
    game: 'minecraft',
    image: 'hivepanel/java:25',
    working_dir: '/home/container',
    entrypoint: [],
    environment: {
        TZ: 'UTC',
    },
    mounts: [
        {
            source: 'instance',
            target: '/home/container',
        },
    ],
    startup: 'java -Xms{{memory}}M -Xmx{{memory}}M -jar server.jar nogui',
    variables: {
        memory: '1024',
        version: '1.21.11',
    },
    install: [],
}, null, 2)

const form = useForm({
    external_id: 'custom-comb',
    name: 'Custom Comb',
    game: 'minecraft',
    manifest: defaultJson,
})

const editorOptions = {
    automaticLayout: true,
    minimap: {
        enabled: false,
    },
    fontSize: 13,
    fontFamily: 'JetBrains Mono, Consolas, monospace',
    scrollBeyondLastLine: false,
    tabSize: 2,
    wordWrap: 'on',
}

function submit() {
    form.post('/admin/combs')
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Create Comb" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Box class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Create Manual Comb
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Add a custom comb directly into this HivePanel instance.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/combs"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </Link>
                        </div>
                    </section>

                    <form class="space-y-5" @submit.prevent="submit">
                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="grid gap-4 lg:grid-cols-3">
                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Comb ID
                                    </label>

                                    <input
                                        v-model="form.external_id"
                                        type="text"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <p v-if="form.errors.external_id" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.external_id }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Name
                                    </label>

                                    <input
                                        v-model="form.name"
                                        type="text"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <p v-if="form.errors.name" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Game
                                    </label>

                                    <input
                                        v-model="form.game"
                                        type="text"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive/50"
                                    />

                                    <p v-if="form.errors.game" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.game }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <div class="mb-4">
                                <h2 class="text-lg font-black">
                                    Comb JSON
                                </h2>

                                <p class="mt-1 text-sm text-zinc-500">
                                    Paste or edit the full comb definition.
                                </p>
                            </div>

                            <div class="overflow-hidden rounded-button border border-zinc-800 bg-[#0d0f11]">
                                <VueMonacoEditor
                                    v-model:value="form.manifest"
                                    language="json"
                                    theme="vs-dark"
                                    height="560px"
                                    :options="editorOptions"
                                />
                            </div>

                            <p v-if="form.errors.manifest" class="mt-2 text-xs font-bold text-status-danger">
                                {{ form.errors.manifest }}
                            </p>
                        </section>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-white transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />
                                Create Comb
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>