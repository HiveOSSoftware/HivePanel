<script setup lang="ts">
import {
    Boxes,
    Cpu,
    HardDrive,
    MemoryStick,
    Network,
    Plus,
    Server,
} from 'lucide-vue-next'

defineProps<{
    node: any
    allocation: any
    additionalAllocations: any[]
    name: string
    comb: string
    version: string
    ownerEmail: string
    memoryMb: number
    diskMb: number
    cpuPercent: number
    dockerImage: string
    startupCommand: string
    processing: boolean
    canSubmit: boolean
}>()

function formatMb(value: number) {
    if (!value) return 'Unlimited'

    if (value >= 1024) {
        return `${(value / 1024).toFixed(value % 1024 === 0 ? 0 : 1)} GB`
    }

    return `${value} MiB`
}
</script>

<template>
    <aside class="space-y-5 xl:sticky xl:top-6 xl:self-start">
        <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
            <h2 class="text-lg font-black">Deploy Summary</h2>

            <div class="mt-5 space-y-4">
                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                        <Server class="size-4" />
                        Node
                    </div>
                    <div class="mt-1 font-black text-white">
                        {{ node?.name || 'Not selected' }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-zinc-500">
                        {{ node?.location || 'No location' }}
                    </div>
                </div>

                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                        <Network class="size-4" />
                        Primary Allocation
                    </div>
                    <div class="mt-1 font-mono font-black text-white">
                        <template v-if="allocation">
                            {{ allocation.ip }}:{{ allocation.port }}
                        </template>
                        <template v-else>
                            Not selected
                        </template>
                    </div>
                    <div class="mt-1 text-xs font-bold text-zinc-500">
                        {{ additionalAllocations.length }} additional allocation(s)
                    </div>
                </div>

                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                        <Boxes class="size-4" />
                        Cell
                    </div>
                    <div class="mt-1 font-black text-white">
                        {{ name || 'Unnamed Cell' }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-zinc-500">
                        {{ ownerEmail || 'No owner email set' }}
                    </div>
                </div>

                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                        Comb
                    </div>
                    <div class="mt-1 font-black text-white">
                        {{ comb }}
                    </div>
                    <div class="mt-1 text-xs font-bold text-zinc-500">
                        Version {{ version }}
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <MemoryStick class="size-4 text-hive" />
                        <div class="mt-2 text-xs font-black uppercase tracking-wide text-zinc-500">RAM</div>
                        <div class="mt-1 font-black text-white">{{ formatMb(memoryMb) }}</div>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <Cpu class="size-4 text-hive" />
                        <div class="mt-2 text-xs font-black uppercase tracking-wide text-zinc-500">CPU</div>
                        <div class="mt-1 font-black text-white">{{ cpuPercent }}%</div>
                    </div>

                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                        <HardDrive class="size-4 text-hive" />
                        <div class="mt-2 text-xs font-black uppercase tracking-wide text-zinc-500">Disk</div>
                        <div class="mt-1 font-black text-white">{{ formatMb(diskMb) }}</div>
                    </div>
                </div>

                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                        Docker Image
                    </div>
                    <div class="mt-1 break-all font-mono text-xs font-bold text-white">
                        {{ dockerImage || 'Default image' }}
                    </div>
                </div>

                <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                        Startup
                    </div>
                    <div class="mt-1 line-clamp-3 break-all font-mono text-xs font-bold text-white">
                        {{ startupCommand || 'Default startup command' }}
                    </div>
                </div>
            </div>

            <button
                type="submit"
                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-button bg-hive px-4 py-3 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="processing || !canSubmit"
            >
                <Plus class="size-4" />
                Deploy Cell
            </button>
        </section>
    </aside>
</template>