<script setup lang="ts">
import { GripVertical, Plus, Trash } from 'lucide-vue-next'

type ActionField = {
    name: string
    label: string
    type: 'text' | 'number' | 'select'
    required?: boolean
    default?: any
    placeholder?: string
    options?: { label: string; value: string }[]
}

type ActionDefinition = {
    type: string
    name: string
    description: string
    fields: ActionField[]
}

type ScheduleAction = {
    id?: number
    type: string
    payload: Record<string, any>
}

const props = defineProps<{
    form: {
        actions: ScheduleAction[]
    }
    actionDefinitions: ActionDefinition[]
}>()

const emit = defineEmits<{
    addAction: []
    removeAction: [index: number]
    moveAction: [index: number, direction: -1 | 1]
    actionTypeChange: [action: ScheduleAction]
}>()

function actionDefinition(type: string) {
    return props.actionDefinitions.find(action => action.type === type)
}
</script>

<template>
    <section class="rounded-panel border border-zinc-800 bg-surface p-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-black">Workflow</h2>
                <p class="mt-1 text-sm text-zinc-500">
                    Actions run from top to bottom.
                </p>
            </div>

            <button
                class="inline-flex items-center gap-2 rounded-button border border-hive bg-hive px-3 py-2 text-xs font-black text-white transition hover:bg-hive-light"
                @click="emit('addAction')"
            >
                <Plus class="size-4" />
                Add Action
            </button>
        </div>

        <div class="mt-5 space-y-4">
            <div
                v-for="(action, index) in form.actions"
                :key="index"
                class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4"
            >
                <div class="flex items-center gap-3">
                    <GripVertical class="size-4 text-zinc-600" />

                    <div class="flex h-8 w-8 items-center justify-center rounded-full border border-hive/30 bg-hive/10 text-sm font-black text-hive">
                        {{ index + 1 }}
                    </div>

                    <select
                        v-model="action.type"
                        class="min-w-0 flex-1 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                        @change="emit('actionTypeChange', action)"
                    >
                        <option
                            v-for="definition in actionDefinitions"
                            :key="definition.type"
                            :value="definition.type"
                        >
                            {{ definition.name }}
                        </option>
                    </select>

                    <button class="rounded-button border border-zinc-800 px-2 py-1 text-xs text-zinc-400 hover:text-hive" @click="emit('moveAction', index, -1)">↑</button>
                    <button class="rounded-button border border-zinc-800 px-2 py-1 text-xs text-zinc-400 hover:text-hive" @click="emit('moveAction', index, 1)">↓</button>

                    <button class="text-status-danger hover:text-red-300" @click="emit('removeAction', index)">
                        <Trash class="size-4" />
                    </button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <label
                        v-for="field in actionDefinition(action.type)?.fields ?? []"
                        :key="field.name"
                        class="space-y-2"
                    >
                        <span class="text-sm font-bold text-zinc-400">{{ field.label }}</span>

                        <select
                            v-if="field.type === 'select'"
                            v-model="action.payload[field.name]"
                            class="w-full rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-sm text-zinc-200 outline-none focus:border-hive"
                        >
                            <option
                                v-for="option in field.options ?? []"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <input
                            v-else
                            v-model="action.payload[field.name]"
                            :type="field.type === 'number' ? 'number' : 'text'"
                            class="w-full rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-sm text-zinc-200 outline-none focus:border-hive"
                            :placeholder="field.placeholder"
                        />
                    </label>
                </div>
            </div>
        </div>
    </section>
</template>