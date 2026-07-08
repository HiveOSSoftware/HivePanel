<script setup lang="ts">
import type { CellStatus } from '@/composables/useActiveCell'
import { computed } from 'vue'

const props = defineProps<{
    cell?: any
    status?: CellStatus
}>()

const isLocked = computed(() => props.cell?.lock?.locked === true)

const lockLabel = computed(() => {
    return props.cell?.lock?.reason || 'Locked'
})

const lockMessage = computed(() => {
    return props.cell?.lock?.message || 'Server actions disabled'
})

function statusLabel(status?: CellStatus) {
    if (isLocked.value) return lockLabel.value

    switch (status) {
        case 'running':
            return 'Running'
        case 'starting':
            return 'Starting'
        case 'stopping':
            return 'Stopping'
        default:
            return 'Offline'
    }
}

function statusDotClass(status?: CellStatus) {
    if (isLocked.value) {
        return 'bg-status-warning shadow-hive-soft animate-pulse'
    }

    switch (status) {
        case 'running':
            return 'bg-status-success shadow-[0_0_12px_rgba(83,215,105,0.6)]'
        case 'starting':
        case 'stopping':
            return 'bg-status-warning shadow-hive-soft animate-pulse'
        default:
            return 'bg-status-danger shadow-[0_0_12px_rgba(239,68,68,0.55)]'
    }
}
</script>

<template>
    <div
        class="mx-2 mb-3 rounded-button border bg-surface p-4"
        :class="isLocked ? 'border-status-warning/40' : 'border-zinc-800'"
    >
        <h3 class="truncate text-sm font-black text-white">
            {{ cell?.name }}
        </h3>

        <div class="mt-2 flex items-center gap-2 text-xs font-bold text-zinc-300">
            <span
                class="h-3 w-3 rounded-full"
                :class="statusDotClass(status)"
            />

            <span :class="isLocked ? 'text-status-warning' : ''">
                {{ statusLabel(status) }}
            </span>
        </div>

        <div
            v-if="isLocked"
            class="mt-2 line-clamp-2 text-xs text-zinc-500"
        >
            {{ lockMessage }}
        </div>
    </div>
</template>