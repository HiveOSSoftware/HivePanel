<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { CpuIcon, Plus, Server } from 'lucide-vue-next'

type NodeRecord = {
    id: string
    name: string
    location?: string
    scheme: 'http' | 'https'
    fqdn: string
    port: number
    is_active: boolean
    created_at?: string
    updated_at?: string
}

defineProps<{
    nodes: NodeRecord[]
}>()

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Admin Nodes" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Nodes
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Manage backend worker nodes connected to HivePanel.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/admin/nodes/create"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:bg-hive-light"
                            >
                                <Plus class="size-4" />
                                New Node
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div
                            v-if="nodes.length === 0"
                            class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center"
                        >
                            <Server class="mx-auto size-10 text-zinc-700" />

                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No nodes yet
                            </h2>

                            <p class="mt-2 text-sm text-zinc-500">
                                Add your first backend worker node.
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <Link
                                v-for="node in nodes"
                                :key="node.id"
                                :href="`/admin/nodes/${node.id}`"
                                class="block rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover active:translate-y-0"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-black text-white">
                                                {{ node.name }}
                                            </h3>

                                            <span
                                                class="rounded-full border px-2 py-0.5 text-xs font-bold"
                                                :class="node.is_active
                                                    ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                    : 'border-zinc-700 bg-zinc-800 text-zinc-400'"
                                            >
                                                {{ node.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-4 text-xs text-zinc-500">
                                            <span>{{ node.location || 'No location' }}</span>
                                            <span>{{ node.scheme }}://{{ node.fqdn }}:{{ node.port }}</span>
                                            <span>Updated {{ formatDate(node.updated_at) }}</span>
                                        </div>
                                    </div>

                                    <div class="text-sm font-black text-hive">
                                        Open →
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </AppLayout>
</template>