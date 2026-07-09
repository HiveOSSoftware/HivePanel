<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Box, CloudDownload, Plus, Search } from 'lucide-vue-next'
import { computed, ref, onMounted } from 'vue'

type CombRecord = {
    id: string | number
    external_id?: string
    name: string
    game: string
    source?: 'local' | 'registry' | 'manual'
    created_at?: string
    updated_at?: string
}

type RegistryCombRecord = {
    id: string
    name: string
    game: string
    tags?: string[]
}

const props = defineProps<{
    combs: CombRecord[]
    registryCombs: RegistryCombRecord[]
}>()

onMounted(() => {
    console.log('props.combs', props.combs);
    console.log('props.registryCombs', props.registryCombs);
})

const search = ref('')

const filteredLocalCombs = computed(() => {
    const q = search.value.toLowerCase()

    return props.combs.filter(comb =>
        comb.name.toLowerCase().includes(q) ||
        comb.game.toLowerCase().includes(q) ||
        String(comb.external_id ?? comb.id).toLowerCase().includes(q)
    )
})

const filteredRegistryCombs = computed(() => {
    const q = search.value.toLowerCase()

    return props.registryCombs.filter(comb =>
        comb.name.toLowerCase().includes(q) ||
        comb.game.toLowerCase().includes(q) ||
        comb.id.toLowerCase().includes(q)
    )
})

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}

function importComb(id: string) {
    router.post(`/admin/combs/registry/${id}/import`, {}, {
        preserveScroll: true,
    })
}

function isImported(id: string) {
    return props.combs.some(comb => comb.external_id === id)
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Admin Combs" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <Box class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Combs
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Manage local server templates and import official combs from HiveRegistry.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/combs/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus class="size-4" />
                                New Manual Comb
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />

                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search combs..."
                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 text-sm font-bold text-white outline-none transition placeholder:text-zinc-600 focus:border-hive/50"
                            />
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-black">Local Combs</h2>
                                <p class="mt-1 text-sm text-zinc-500">
                                    Combs already installed into this panel.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="filteredLocalCombs.length === 0"
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Box class="mx-auto size-10 text-zinc-700" />

                            <h3 class="mt-4 text-lg font-black text-zinc-300">
                                No local combs yet
                            </h3>

                            <p class="mt-2 text-sm text-zinc-500">
                                Import from HiveRegistry or create a manual comb.
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <Link
                                v-for="comb in filteredLocalCombs"
                                :key="comb.id"
                                :href="`/admin/combs/${comb.id}`"
                                class="block rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover active:translate-y-0"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-black text-white">
                                                {{ comb.name }}
                                            </h3>

                                            <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                                {{ comb.game }}
                                            </span>

                                            <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs font-bold text-zinc-400">
                                                {{ comb.source || 'local' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-4 text-xs text-zinc-500">
                                            <span>{{ comb.external_id || comb.id }}</span>
                                            <span>Updated {{ formatDate(comb.updated_at) }}</span>
                                        </div>
                                    </div>

                                    <div class="text-sm font-black text-hive">
                                        Open →
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="mb-4">
                            <h2 class="text-lg font-black">HiveRegistry</h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                Browse official and remote combs available to import - Wish to add your own comb to the registry? Submit it on GitHub
                            </p>
                        </div>

                        <div
                            v-if="filteredRegistryCombs.length === 0"
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <CloudDownload class="mx-auto size-10 text-zinc-700" />

                            <h3 class="mt-4 text-lg font-black text-zinc-300">
                                No registry combs found
                            </h3>

                            <p class="mt-2 text-sm text-zinc-500">
                                Check the registry URL or try another search.
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="comb in filteredRegistryCombs"
                                :key="comb.id"
                                class="rounded-button border border-zinc-900 bg-[#0d0f11] p-4"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-black text-white">
                                                {{ comb.name }}
                                            </h3>

                                            <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                                {{ comb.game }}
                                            </span>

                                            <span
                                                v-if="isImported(comb.id)"
                                                class="rounded-full border border-status-success/30 bg-status-success/10 px-2 py-0.5 text-xs font-bold text-status-success"
                                            >
                                                Imported
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-zinc-500">
                                            <span>{{ comb.id }}</span>

                                            <span
                                                v-for="tag in comb.tags || []"
                                                :key="tag"
                                                class="rounded-full bg-zinc-900 px-2 py-0.5"
                                            >
                                                {{ tag }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-button border px-4 py-2 text-sm font-black transition disabled:cursor-not-allowed disabled:opacity-50"
                                        :class="isImported(comb.id)
                                            ? 'border-zinc-700 bg-zinc-800 text-zinc-400'
                                            : 'border-hive bg-hive text-white hover:bg-hive-light'"
                                        :disabled="isImported(comb.id)"
                                        @click="importComb(comb.id)"
                                    >
                                        <CloudDownload class="size-4" />
                                        {{ isImported(comb.id) ? 'Imported' : 'Import' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>