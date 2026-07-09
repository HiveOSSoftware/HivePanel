<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Activity,
    ArrowLeft,
    Check,
    Clipboard,
    CpuIcon,
    Download,
    HardDrive,
    KeyRound,
    Server,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Terminal,
} from 'lucide-vue-next'
import { computed, ref } from 'vue'

const props = defineProps<{
    node: any
    registrationToken?: string | null
    workerYaml: string
    systemdService: string
    commands: string[]
    installScriptUrl: string
    oneClickCommand: string
}>()

const copied = ref<string | null>(null)
const generating = ref(false)
const setupMode = ref<'one-click' | 'manual'>('one-click')

const token = computed(() => props.registrationToken || null)

async function copy(text: string, key: string) {
    await navigator.clipboard.writeText(text)

    copied.value = key

    window.setTimeout(() => {
        if (copied.value === key) copied.value = null
    }, 1800)
}

function generateToken() {
    generating.value = true

    router.post(`/admin/nodes/${props.node.id}/registration-token`, {}, {
        preserveScroll: true,
        onFinish: () => {
            generating.value = false
        },
    })
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`${node.name} Configuration`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-center gap-3">
                                <CpuIcon class="size-6 text-hive" />

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-2xl font-black sm:text-3xl">
                                            {{ node.name }}
                                        </h1>

                                        <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-xs font-bold text-hive">
                                            {{ node.location }}
                                        </span>

                                        <span class="rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-xs font-bold text-zinc-400">
                                            {{ node.scheme }}
                                        </span>
                                    </div>

                                    <p class="mt-2 font-mono text-sm text-zinc-500">
                                        {{ node.fqdn }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Link
                                    href="/admin/nodes"
                                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive/40 hover:text-white"
                                >
                                    <ArrowLeft class="size-4" />
                                    Back
                                </Link>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-1">
                        <div class="flex flex-wrap gap-1">
                            <Link :href="`/admin/nodes/${node.id}`" class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Activity class="size-4" />
                                    Overview
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/settings`" class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Settings class="size-4" />
                                    Settings
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/configuration`" class="rounded-button bg-hive/10 px-4 py-3 text-sm font-black text-hive">
                                <span class="inline-flex items-center gap-2">
                                    <SlidersHorizontal class="size-4" />
                                    Configuration
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/allocations`" class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <HardDrive class="size-4" />
                                    Allocation
                                </span>
                            </Link>

                            <Link :href="`/admin/nodes/${node.id}/cells`" class="rounded-button px-4 py-3 text-sm font-bold text-zinc-400 transition hover:bg-surface-light hover:text-white">
                                <span class="inline-flex items-center gap-2">
                                    <Server class="size-4" />
                                    Cells
                                </span>
                            </Link>
                        </div>
                    </section>

                    <div class="grid gap-5 xl:grid-cols-[1fr_420px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="flex items-center gap-3">
                                        <KeyRound class="size-5 text-hive" />

                                        <div>
                                            <h2 class="text-lg font-black">Registration Token</h2>
                                            <p class="mt-1 text-sm text-zinc-500">
                                                Generate a one-time token used by the worker to register with the panel.
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90 disabled:opacity-50"
                                        :disabled="generating"
                                        @click="generateToken"
                                    >
                                        <KeyRound class="size-4" />
                                        {{ generating ? 'Generating...' : 'Generate Token' }}
                                    </button>
                                </div>

                                <div v-if="token" class="mt-5 rounded-button border border-status-success/30 bg-status-success/10 p-4">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <div class="text-xs font-black uppercase tracking-wide text-status-success">
                                                Token generated
                                            </div>

                                            <div class="mt-2 break-all font-mono text-sm font-bold text-zinc-200">
                                                {{ token }}
                                            </div>

                                            <p class="mt-2 text-xs font-bold text-zinc-500">
                                                Copy this now. It will only be shown once.
                                            </p>
                                        </div>

                                        <button
                                            class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="copy(token, 'token')"
                                        >
                                            <Check v-if="copied === 'token'" class="size-4" />
                                            <Clipboard v-else class="size-4" />
                                            {{ copied === 'token' ? 'Copied' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="mt-5 rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
                                    Generate a registration token before installing the worker.
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-1">
                                <div class="grid grid-cols-2 gap-1">
                                    <button
                                        type="button"
                                        class="rounded-button px-4 py-3 text-sm font-black transition"
                                        :class="setupMode === 'one-click'
                                            ? 'bg-hive/10 text-hive'
                                            : 'text-zinc-400 hover:bg-surface-light hover:text-white'"
                                        @click="setupMode = 'one-click'"
                                    >
                                        One-click Installer
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-button px-4 py-3 text-sm font-black transition"
                                        :class="setupMode === 'manual'
                                            ? 'bg-hive/10 text-hive'
                                            : 'text-zinc-400 hover:bg-surface-light hover:text-white'"
                                        @click="setupMode = 'manual'"
                                    >
                                        Manual Setup
                                    </button>
                                </div>
                            </section>

                            <template v-if="setupMode === 'one-click'">
                                <section class="rounded-panel border border-hive/30 bg-hive/10 p-5 sm:p-6">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="flex items-start gap-3">
                                            <Download class="mt-1 size-5 text-hive" />

                                            <div>
                                                <h2 class="text-lg font-black text-white">
                                                    One-command install
                                                </h2>

                                                <p class="mt-1 text-sm text-zinc-400">
                                                    Run this command on the target node as root or with sudo.
                                                </p>
                                            </div>
                                        </div>

                                        <button
                                            v-if="token"
                                            class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90"
                                            @click="copy(oneClickCommand, 'oneClick')"
                                        >
                                            <Check v-if="copied === 'oneClick'" class="size-4" />
                                            <Clipboard v-else class="size-4" />
                                            {{ copied === 'oneClick' ? 'Copied' : 'Copy Command' }}
                                        </button>
                                    </div>

                                    <div v-if="token" class="mt-5 rounded-button border border-zinc-900 bg-[#0d0f11] p-4">
                                        <code class="block break-all font-mono text-sm leading-6 text-zinc-300">
                                            {{ oneClickCommand }}
                                        </code>
                                    </div>

                                    <div v-else class="mt-5 rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
                                        Generate a registration token first.
                                    </div>
                                </section>

                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <div class="flex items-center gap-3">
                                        <ShieldCheck class="size-5 text-hive" />
                                        <h2 class="text-lg font-black">What the installer does</h2>
                                    </div>

                                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                                        <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-400">
                                            Creates <span class="font-mono text-zinc-200">/etc/hivepanel/worker.yml</span>
                                        </div>

                                        <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-400">
                                            Creates required data directories
                                        </div>

                                        <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-400">
                                            Installs the systemd service
                                        </div>

                                        <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-400">
                                            Starts HiveWorker automatically
                                        </div>
                                    </div>

                                    <p class="mt-5 text-xs font-bold text-zinc-500">
                                        Installer URL:
                                        <span class="font-mono text-zinc-400">{{ installScriptUrl }}</span>
                                    </p>
                                </section>
                            </template>

                            <template v-else>
                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <h2 class="text-lg font-black">worker.yml</h2>
                                            <p class="mt-1 text-sm text-zinc-500">
                                                Save this file at <span class="font-mono text-zinc-300">/etc/hivepanel/worker.yml</span>.
                                            </p>
                                        </div>

                                        <button
                                            class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="copy(workerYaml, 'workerYaml')"
                                        >
                                            <Check v-if="copied === 'workerYaml'" class="size-4" />
                                            <Clipboard v-else class="size-4" />
                                            {{ copied === 'workerYaml' ? 'Copied' : 'Copy YAML' }}
                                        </button>
                                    </div>

                                    <pre class="max-h-[420px] overflow-auto rounded-button border border-zinc-900 bg-[#0d0f11] p-4 text-sm leading-6 text-zinc-300"><code>{{ workerYaml }}</code></pre>
                                </section>

                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div>
                                            <h2 class="text-lg font-black">systemd Service</h2>
                                            <p class="mt-1 text-sm text-zinc-500">
                                                Save this file at <span class="font-mono text-zinc-300">/etc/systemd/system/hivepanel-worker.service</span>.
                                            </p>
                                        </div>

                                        <button
                                            class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                                            @click="copy(systemdService, 'systemd')"
                                        >
                                            <Check v-if="copied === 'systemd'" class="size-4" />
                                            <Clipboard v-else class="size-4" />
                                            {{ copied === 'systemd' ? 'Copied' : 'Copy Service' }}
                                        </button>
                                    </div>

                                    <pre class="max-h-[420px] overflow-auto rounded-button border border-zinc-900 bg-[#0d0f11] p-4 text-sm leading-6 text-zinc-300"><code>{{ systemdService }}</code></pre>
                                </section>
                            </template>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-center gap-3">
                                    <Terminal class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Manual Commands</h2>
                                </div>

                                <div class="mt-5 space-y-3">
                                    <div
                                        v-for="(command, index) in commands"
                                        :key="command"
                                        class="rounded-button border border-zinc-800 bg-[#0d0f11] p-3"
                                    >
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <span class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                Step {{ index + 1 }}
                                            </span>

                                            <button
                                                class="inline-flex items-center gap-1 text-xs font-black text-zinc-500 transition hover:text-hive"
                                                @click="copy(command, `command-${index}`)"
                                            >
                                                <Check v-if="copied === `command-${index}`" class="size-3" />
                                                <Clipboard v-else class="size-3" />
                                                {{ copied === `command-${index}` ? 'Copied' : 'Copy' }}
                                            </button>
                                        </div>

                                        <code class="block break-all font-mono text-sm text-zinc-300">
                                            {{ command }}
                                        </code>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">Node Details</h2>

                                <div class="mt-5 divide-y divide-zinc-800">
                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-zinc-500">Name</span>
                                        <span class="text-right font-black text-white">{{ node.name }}</span>
                                    </div>

                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-zinc-500">Public URL</span>
                                        <span class="break-all text-right font-mono text-xs font-black text-white">
                                            {{ node.public_url }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-zinc-500">Base URL</span>
                                        <span class="break-all text-right font-mono text-xs font-black text-white">
                                            {{ node.base_url }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between gap-4 py-3 text-sm">
                                        <span class="font-bold text-zinc-500">Registered</span>
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-xs font-black"
                                            :class="node.is_registered
                                                ? 'border-status-success/30 bg-status-success/10 text-status-success'
                                                : 'border-zinc-700 bg-zinc-800 text-zinc-400'"
                                        >
                                            {{ node.is_registered ? 'Yes' : 'No' }}
                                        </span>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>