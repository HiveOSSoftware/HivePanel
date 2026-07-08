<script setup lang="ts">
defineProps<{
    schedule: any
    formatDate: (value?: string) => string
    statusClass: (status?: string) => string
    actionName: (type: string) => string
}>()
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5">
        <h2 class="text-lg font-black">Recent Runs</h2>

        <div class="mt-4 space-y-3">
            <div
                v-for="run in schedule.runs"
                :key="run.id"
                class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-bold text-zinc-300">
                        {{ formatDate(run.started_at) }}
                    </span>

                    <span
                        class="rounded-full border px-2 py-0.5 text-xs font-bold capitalize"
                        :class="statusClass(run.status)"
                    >
                        {{ run.status }}
                    </span>
                </div>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="(log, index) in run.action_logs ?? []"
                        :key="log.id"
                        class="rounded-button border border-zinc-800 bg-surface-light px-3 py-2"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-bold text-zinc-300">
                                {{ index + 1 }}. {{ actionName(log.type) }}
                            </span>

                            <span
                                class="rounded-full border px-2 py-0.5 text-[11px] font-bold capitalize"
                                :class="statusClass(log.status)"
                            >
                                {{ log.status }}
                            </span>
                        </div>

                        <div
                            v-if="log.error"
                            class="mt-2 text-xs font-bold text-status-danger"
                        >
                            {{ log.error }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="!schedule.runs?.length"
                class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-zinc-500"
            >
                This schedule has not run yet.
            </div>
        </div>
    </section>
</template>