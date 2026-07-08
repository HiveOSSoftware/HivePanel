<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { Ban, Crown, RefreshCw, ShieldOff, UserX, Users } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    cell: any
}>()

type Player = {
    name: string
    uuid?: string
    ping?: number
    world?: string
    online?: boolean
    source?: string
    last_seen_at?: string
}

type PlayerAction = 'kick' | 'ban' | 'op' | 'deop'

const players = ref<Player[]>([])
const loading = ref(false)
const error = ref('')
const actionLoading = ref('')
const toastMessage = ref('')

const actionOpen = ref(false)
const selectedPlayer = ref<Player | null>(null)
const selectedAction = ref<PlayerAction>('kick')
const reason = ref('')

let refreshTimer: number | undefined

const cellId = computed(() => props.cell?.id)

const onlinePlayers = computed(() => players.value.filter((player) => player.online))
const offlinePlayers = computed(() => players.value.filter((player) => !player.online))

const actionTitle = computed(() => {
    if (selectedAction.value === 'kick') return 'Kick Player'
    if (selectedAction.value === 'ban') return 'Ban Player'
    if (selectedAction.value === 'op') return 'OP Player'
    return 'De-OP Player'
})

const actionButton = computed(() => {
    if (selectedAction.value === 'kick') return 'Kick'
    if (selectedAction.value === 'ban') return 'Ban'
    if (selectedAction.value === 'op') return 'OP'
    return 'De-OP'
})

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function showToast(message: string) {
    toastMessage.value = message
    setTimeout(() => toastMessage.value = '', 3000)
}

function playerAvatar(player: Player) {
    return `https://mc-heads.net/avatar/${player.uuid || player.name}/64`
}

function formatDate(value?: string) {
    if (!value) return 'Unknown'

    return new Date(value).toLocaleString()
}

async function loadPlayers() {
    if (!cellId.value) return

    loading.value = true
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/players-json`, {
            headers: { Accept: 'application/json' },
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        const data = await response.json()
        players.value = data.players ?? []
    } finally {
        loading.value = false
    }
}

function openAction(action: PlayerAction, player: Player) {
    selectedAction.value = action
    selectedPlayer.value = player
    reason.value = ''
    actionOpen.value = true
}

function closeAction() {
    if (actionLoading.value) return

    actionOpen.value = false
    selectedPlayer.value = null
    reason.value = ''
}

async function runAction() {
    if (!cellId.value || !selectedPlayer.value) return

    actionLoading.value = selectedAction.value
    error.value = ''

    try {
        const response = await fetch(`/cells/${cellId.value}/players/${selectedAction.value}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: selectedPlayer.value.name,
                reason: reason.value || null,
            }),
        })

        if (!response.ok) {
            error.value = await response.text()
            return
        }

        showToast(`${actionButton.value} command sent.`)
        closeAction()
        await loadPlayers()
    } finally {
        actionLoading.value = ''
    }
}

onMounted(() => {
    loadPlayers()
    refreshTimer = window.setInterval(loadPlayers, 5000)
})

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer)
})
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Players`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-2xl font-black sm:text-3xl">
                                    Players
                                </h1>

                                <p class="mt-2 text-sm text-zinc-400">
                                    {{ onlinePlayers.length }} online, {{ offlinePlayers.length }} recently seen.
                                </p>
                            </div>

                            <button
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                @click="loadPlayers"
                            >
                                <RefreshCw class="size-4" />
                                Refresh
                            </button>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 bg-surface-light px-5 py-3 text-xs font-black uppercase tracking-wide text-zinc-500">
                            Online Players
                        </div>

                        <div v-if="loading && players.length === 0" class="p-6 text-zinc-500">
                            Loading players...
                        </div>

                        <div v-else-if="error" class="p-6 font-bold text-status-danger">
                            {{ error }}
                        </div>

                        <div v-else-if="onlinePlayers.length === 0" class="p-10 text-center">
                            <Users class="mx-auto size-10 text-zinc-700" />
                            <h2 class="mt-4 text-lg font-black text-zinc-300">
                                No players online
                            </h2>
                        </div>

                        <div v-else class="divide-y divide-zinc-900">
                            <div
                                v-for="player in onlinePlayers"
                                :key="player.uuid ?? player.name"
                                class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_auto] lg:items-center"
                            >
                                <div class="flex min-w-0 items-center gap-4">
                                    <img
                                        :src="playerAvatar(player)"
                                        class="size-12 rounded-button border border-zinc-800 bg-surface-light"
                                        :alt="player.name"
                                    />

                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full bg-status-success shadow-[0_0_12px_rgba(83,215,105,0.6)]" />

                                            <div class="truncate text-base font-black text-white">
                                                {{ player.name }}
                                            </div>
                                        </div>

                                        <div class="mt-1 flex flex-wrap gap-3 text-xs text-zinc-500">
                                            <span class="font-mono">
                                                {{ player.uuid ?? 'UUID unavailable' }}
                                            </span>

                                            <span v-if="player.ping !== undefined">
                                                {{ player.ping }}ms
                                            </span>

                                            <span v-if="player.world">
                                                {{ player.world }}
                                            </span>

                                            <span>
                                                Source: {{ player.source ?? 'unknown' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-status-warning hover:text-status-warning"
                                        @click="openAction('kick', player)"
                                    >
                                        <UserX class="size-4" />
                                        Kick
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-bold text-status-danger transition hover:border-status-danger"
                                        @click="openAction('ban', player)"
                                    >
                                        <Ban class="size-4" />
                                        Ban
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="openAction('op', player)"
                                    >
                                        <Crown class="size-4" />
                                        OP
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="openAction('deop', player)"
                                    >
                                        <ShieldOff class="size-4" />
                                        De-OP
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                        <div class="border-b border-zinc-800 bg-surface-light px-5 py-3 text-xs font-black uppercase tracking-wide text-zinc-500">
                            Recently Seen / Offline
                        </div>

                        <div v-if="offlinePlayers.length === 0" class="p-6 text-zinc-500">
                            No offline players tracked yet.
                        </div>

                        <div v-else class="divide-y divide-zinc-900">
                            <div
                                v-for="player in offlinePlayers"
                                :key="player.uuid ?? player.name"
                                class="grid gap-4 px-5 py-4 opacity-80 lg:grid-cols-[1fr_auto] lg:items-center"
                            >
                                <div class="flex min-w-0 items-center gap-4">
                                    <img
                                        :src="playerAvatar(player)"
                                        class="size-12 rounded-button border border-zinc-800 bg-surface-light grayscale"
                                        :alt="player.name"
                                    />

                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full bg-zinc-600" />

                                            <div class="truncate text-base font-black text-zinc-300">
                                                {{ player.name }}
                                            </div>
                                        </div>

                                        <div class="mt-1 flex flex-wrap gap-3 text-xs text-zinc-500">
                                            <span class="font-mono">
                                                {{ player.uuid ?? 'UUID unavailable' }}
                                            </span>

                                            <span>
                                                Last seen: {{ formatDate(player.last_seen_at) }}
                                            </span>

                                            <span>
                                                Source: {{ player.source ?? 'unknown' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        class="inline-flex cursor-not-allowed items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-600"
                                        disabled
                                    >
                                        <UserX class="size-4" />
                                        Kick
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-bold text-status-danger transition hover:border-status-danger"
                                        @click="openAction('ban', player)"
                                    >
                                        <Ban class="size-4" />
                                        Ban
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="openAction('op', player)"
                                    >
                                        <Crown class="size-4" />
                                        OP
                                    </button>

                                    <button
                                        class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                        @click="openAction('deop', player)"
                                    >
                                        <ShieldOff class="size-4" />
                                        De-OP
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="actionOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-md rounded-panel border border-zinc-800 bg-surface p-6">
                <h2 class="text-xl font-black text-white">
                    {{ actionTitle }}
                </h2>

                <p class="mt-2 text-sm text-zinc-400">
                    Target:
                    <span class="font-mono text-hive">
                        {{ selectedPlayer?.name }}
                    </span>
                </p>

                <label
                    v-if="selectedAction === 'kick' || selectedAction === 'ban'"
                    class="mt-5 block space-y-2"
                >
                    <span class="text-sm font-bold text-zinc-400">
                        Reason
                    </span>

                    <input
                        v-model="reason"
                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                        placeholder="Optional reason"
                        @keydown.enter.prevent="runAction"
                    />
                </label>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:text-white"
                        :disabled="!!actionLoading"
                        @click="closeAction"
                    >
                        Cancel
                    </button>

                    <button
                        class="rounded-button border px-4 py-2 text-sm font-black text-white transition disabled:opacity-50"
                        :class="selectedAction === 'ban'
                            ? 'border-status-danger bg-status-danger'
                            : 'border-hive bg-hive hover:bg-hive-light'"
                        :disabled="!!actionLoading"
                        @click="runAction"
                    >
                        {{ actionLoading ? 'Sending...' : actionButton }}
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border border-status-success/40 bg-status-success/15 px-5 py-3 text-sm font-bold text-status-success shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>