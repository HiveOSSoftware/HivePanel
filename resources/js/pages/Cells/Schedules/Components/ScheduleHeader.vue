<script setup lang="ts">
import { ArrowLeft, CalendarClock, Play, Save } from 'lucide-vue-next'

defineProps<{
    cell: any
    form: any
    saving: boolean
    cronSummary: string
}>()

const emit = defineEmits<{
    back: []
    run: []
    save: []
}>()
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
        <button
            class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-zinc-400 hover:text-hive"
            @click="emit('back')"
        >
            <ArrowLeft class="size-4" />
            Back to schedules
        </button>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <CalendarClock class="size-6 text-hive" />
                <div>
                    <h1 class="text-2xl font-black sm:text-3xl">
                        {{ form.name }}
                    </h1>
                    <p class="mt-1 text-sm text-zinc-400">
                        {{ cronSummary }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-black text-zinc-300 transition hover:border-hive hover:text-hive"
                    @click="emit('run')"
                >
                    <Play class="size-4" />
                    Run Now
                </button>

                <button
                    class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                    :disabled="saving"
                    @click="emit('save')"
                >
                    <Save class="size-4" />
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>
    </section>
</template>