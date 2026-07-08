<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, Box, Save } from 'lucide-vue-next'
import { VueMonacoEditor } from '@guolao/vue-monaco-editor'

const props = defineProps<{
    comb: {
        id: number | string
        external_id: string
        name: string
        game: string
        source?: string
        data: any
    }
}>()

const form = useForm({
    external_id: props.comb.external_id,
    name: props.comb.name,
    game: props.comb.game,
    manifest: JSON.stringify(props.comb.data ?? {}, null, 2),
})

const editorOptions = {
    automaticLayout: true,
    minimap: { enabled: false },
    fontSize: 13,
    fontFamily: 'JetBrains Mono, Consolas, monospace',
    scrollBeyondLastLine: false,
    tabSize: 2,
    wordWrap: 'on',
}

function submit() {
    form.put(`/admin/combs/${props.comb.id}`)
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Edit ${comb.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Box class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Edit Comb
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Modify this local comb manifest.
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/combs/${comb.id}`"
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
                                <h2 class="text-lg font-black">Comb JSON</h2>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Editing an imported registry comb marks it as locally modified.
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
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <Save class="size-4" />
                                Save Comb
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>