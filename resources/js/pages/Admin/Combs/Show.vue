<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeft, Box, Trash2, PencilIcon } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
    comb: {
        id: number | string
        external_id: string
        name: string
        game: string
        source?: string
        data: any
        created_at?: string
        updated_at?: string
    }
}>()

const manifest = computed(() =>
    JSON.stringify(props.comb.data ?? {}, null, 2)
)

function destroyComb() {
    if (!confirm('Delete this local comb?')) return

    router.delete(`/admin/combs/${props.comb.id}`)
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="comb.name" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Box class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ comb.name }}
                                        </h1>

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                            {{ comb.game }}
                                        </span>

                                        <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs font-bold text-zinc-400">
                                            {{ comb.source || 'local' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-zinc-500">
                                        {{ comb.external_id }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/combs"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>

                                <Link
                                    :href="`/admin/combs/${comb.id}/edit`"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                                >
                                    <PencilIcon class="size-4" />
                                    Edit
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-status-danger/40 bg-status-danger/10 px-4 py-2 text-sm font-black text-status-danger transition hover:bg-status-danger/20"
                                    @click="destroyComb"
                                >
                                    <Trash2 class="size-4" />
                                    Delete
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-5 lg:grid-cols-3">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                            <h2 class="text-lg font-black">Details</h2>

                            <div class="mt-4 space-y-3 text-sm">
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Local ID
                                    </div>
                                    <div class="mt-1 font-mono text-zinc-300">
                                        {{ comb.id }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        External ID
                                    </div>
                                    <div class="mt-1 font-mono text-zinc-300">
                                        {{ comb.external_id }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Source
                                    </div>
                                    <div class="mt-1 text-zinc-300">
                                        {{ comb.source || 'local' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Updated
                                    </div>
                                    <div class="mt-1 text-zinc-300">
                                        {{ comb.updated_at || 'Never' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6 lg:col-span-2">
                            <h2 class="text-lg font-black">Manifest Preview</h2>

                            <pre class="mt-4 max-h-[650px] overflow-auto rounded-button border border-zinc-900 bg-[#0d0f11] p-4 text-xs leading-6 text-zinc-300"><code>{{ manifest }}</code></pre>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>