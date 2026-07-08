<script setup lang="ts">
import { Bot } from 'lucide-vue-next'

defineProps<{
    modelValue: string
    loading: boolean
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
    generate: []
}>()
</script>

<template>
    <section class="rounded-panel border border-hive/30 bg-hive/10 p-5">
        <div class="flex items-start gap-3">
            <Bot class="mt-1 size-5 text-hive" />

            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-black text-hive">AI Workflow Generator</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-400">
                    Describe what you want this schedule to do. HivePanel will generate the workflow actions for you.
                </p>

                <textarea
                    :value="modelValue"
                    class="mt-4 min-h-28 w-full resize-none rounded-button border border-zinc-800 bg-surface px-4 py-3 text-sm text-zinc-200 outline-none placeholder:text-zinc-600 focus:border-hive"
                    placeholder="Warn players 5 minutes before restart, take a backup, restart the server, then say the server is online."
                    @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
                />

                <div class="mt-3 flex justify-end">
                    <button
                        class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                        :disabled="loading"
                        @click="emit('generate')"
                    >
                        <Bot class="size-4" />
                        {{ loading ? 'Generating...' : 'Generate Workflow' }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>