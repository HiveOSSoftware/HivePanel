<script setup lang="ts">
import {
    Cpu,
    HardDrive,
    MemoryStick,
} from 'lucide-vue-next'

const props = defineProps<{
    memoryMb: number
    overheadMemoryMb: number
    swapMb: number
    diskMb: number
    cpuPercent: number
    cpuPinning: string
    ioWeight: number
    oomKiller: boolean
    excludeFromResourceCalculation: boolean
}>()

const emit = defineEmits<{
    'update:memoryMb': [value: number]
    'update:overheadMemoryMb': [value: number]
    'update:swapMb': [value: number]
    'update:diskMb': [value: number]
    'update:cpuPercent': [value: number]
    'update:cpuPinning': [value: string]
    'update:ioWeight': [value: number]
    'update:oomKiller': [value: boolean]
    'update:excludeFromResourceCalculation': [value: boolean]
}>()

function numberValue(event: Event) {
    return Number((event.target as HTMLInputElement).value)
}

function stringValue(event: Event) {
    return (event.target as HTMLInputElement).value
}

function checkedValue(event: Event) {
    return (event.target as HTMLInputElement).checked
}
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
        <div class="mb-5 flex items-center gap-3">
            <Cpu class="size-5 text-hive" />
            <h2 class="text-lg font-black">Resource Management</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="flex items-center gap-2 text-sm font-bold text-zinc-400">
                    <MemoryStick class="size-4 text-zinc-500" />
                    Memory
                </label>

                <div class="relative mt-2">
                    <input
                        :value="memoryMb"
                        type="number"
                        min="0"
                        step="128"
                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-14 text-sm font-bold text-white outline-none transition focus:border-hive"
                        @input="emit('update:memoryMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-zinc-500">MiB</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-zinc-500">
                    Set to 0 for unlimited memory.
                </p>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm font-bold text-zinc-400">
                    <HardDrive class="size-4 text-zinc-500" />
                    Disk Space
                </label>

                <div class="relative mt-2">
                    <input
                        :value="diskMb"
                        type="number"
                        min="0"
                        step="1024"
                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-14 text-sm font-bold text-white outline-none transition focus:border-hive"
                        @input="emit('update:diskMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-zinc-500">MiB</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-zinc-500">
                    Set to 0 for unlimited disk usage.
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-zinc-400">CPU Limit</label>

                <div class="relative mt-2">
                    <input
                        :value="cpuPercent"
                        type="number"
                        min="0"
                        max="1000"
                        step="10"
                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-12 text-sm font-bold text-white outline-none transition focus:border-hive"
                        @input="emit('update:cpuPercent', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-zinc-500">%</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-zinc-500">
                    0 means unlimited. 100 equals one full thread.
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-zinc-400">CPU Pinning</label>

                <input
                    :value="cpuPinning"
                    type="text"
                    placeholder="0,1,3 or 0-3"
                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                    @input="emit('update:cpuPinning', stringValue($event))"
                />

                <p class="mt-1 text-xs leading-5 text-zinc-500">
                    Optional advanced CPU thread list.
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-zinc-400">Overhead Memory</label>

                <div class="relative mt-2">
                    <input
                        :value="overheadMemoryMb"
                        type="number"
                        min="0"
                        step="64"
                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-14 text-sm font-bold text-white outline-none transition focus:border-hive"
                        @input="emit('update:overheadMemoryMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-zinc-500">MiB</span>
                </div>
            </div>

            <div>
                <label class="text-sm font-bold text-zinc-400">Swap</label>

                <div class="relative mt-2">
                    <input
                        :value="swapMb"
                        type="number"
                        min="-1"
                        step="128"
                        class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-14 text-sm font-bold text-white outline-none transition focus:border-hive"
                        @input="emit('update:swapMb', numberValue($event))"
                    />
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-zinc-500">MiB</span>
                </div>

                <p class="mt-1 text-xs leading-5 text-zinc-500">
                    0 disables swap. -1 allows unlimited swap.
                </p>
            </div>

            <div>
                <label class="text-sm font-bold text-zinc-400">Block IO Weight</label>

                <input
                    :value="ioWeight"
                    type="number"
                    min="10"
                    max="1000"
                    step="10"
                    class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                    @input="emit('update:ioWeight', numberValue($event))"
                />

                <p class="mt-1 text-xs leading-5 text-zinc-500">
                    Relative disk IO priority between 10 and 1000.
                </p>
            </div>

            <div class="space-y-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <input
                        :checked="oomKiller"
                        type="checkbox"
                        class="mt-1"
                        @change="emit('update:oomKiller', checkedValue($event))"
                    />

                    <span>
                        <span class="block text-sm font-black text-white">Enable OOM Killer</span>
                        <span class="mt-1 block text-xs leading-5 text-zinc-500">
                            Terminates the process if it breaches memory limits.
                        </span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                    <input
                        :checked="excludeFromResourceCalculation"
                        type="checkbox"
                        class="mt-1"
                        @change="emit('update:excludeFromResourceCalculation', checkedValue($event))"
                    />

                    <span>
                        <span class="block text-sm font-black text-white">Exclude from Resource Calculation</span>
                        <span class="mt-1 block text-xs leading-5 text-zinc-500">
                            Useful for testing or development cells.
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </section>
</template>