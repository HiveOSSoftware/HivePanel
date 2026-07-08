<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useConfirm } from '@/composables/useConfirm'

import ScheduleHeader from './Components/ScheduleHeader.vue'
import GeneralCard from './Components/GeneralCard.vue'
import AIWorkflowCard from './Components/AIWorkflowCard.vue'
import WorkflowCard from './Components/WorkflowCard.vue'
import ScheduleInfoCard from './Components/ScheduleInfoCard.vue'
import RecentRunsCard from './Components/RecentRunsCard.vue'

const { confirm } = useConfirm()

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
    cell: any
    schedule: any
    actionDefinitions: ActionDefinition[]
}>()

const saving = ref(false)
const error = ref('')
const toastMessage = ref('')
const workflowPrompt = ref('')
const workflowLoading = ref(false)

const cron = ref({
    minute: props.schedule.cron_expression?.split(/\s+/)[0] ?? '0',
    hour: props.schedule.cron_expression?.split(/\s+/)[1] ?? '3',
    day: props.schedule.cron_expression?.split(/\s+/)[2] ?? '*',
    month: props.schedule.cron_expression?.split(/\s+/)[3] ?? '*',
    dayOfWeek: props.schedule.cron_expression?.split(/\s+/)[4] ?? '*',
})

const form = ref({
    name: props.schedule.name,
    cron_expression: props.schedule.cron_expression,
    timezone: props.schedule.timezone ?? 'Europe/London',
    enabled: Boolean(props.schedule.enabled),
    only_when_online: Boolean(props.schedule.only_when_online),
    continue_on_failure: Boolean(props.schedule.continue_on_failure),
    actions: (props.schedule.actions ?? []).map((action: any) => ({
        id: action.id,
        type: action.type,
        payload: action.payload ?? {},
    })) as ScheduleAction[],
})

const cronSummary = computed(() => {
    return `${form.value.cron_expression} (${form.value.timezone})`
})

watch(cron, () => {
    form.value.cron_expression = [
        cron.value.minute || '*',
        cron.value.hour || '*',
        cron.value.day || '*',
        cron.value.month || '*',
        cron.value.dayOfWeek || '*',
    ].join(' ')
}, { deep: true })

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function showToast(message: string) {
    toastMessage.value = message
    setTimeout(() => toastMessage.value = '', 3000)
}

async function readError(response: Response) {
    const text = await response.text()

    try {
        const json = JSON.parse(text)
        return json.message || json.error || text
    } catch {
        return text
    }
}

function formatDate(value?: string) {
    if (!value) return 'Never'
    return new Date(value).toLocaleString()
}

function actionDefinition(type: string) {
    return props.actionDefinitions.find(action => action.type === type)
}

function actionName(type: string) {
    return actionDefinition(type)?.name ?? type
}

function statusClass(status?: string) {
    if (status === 'success') return 'border-status-success/30 bg-status-success/10 text-status-success'
    if (status === 'failed') return 'border-status-danger/30 bg-status-danger/10 text-status-danger'
    if (status === 'running') return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

    return 'border-zinc-700 bg-zinc-800 text-zinc-400'
}

function addAction(type = 'command') {
    const definition = actionDefinition(type)
    const payload: Record<string, any> = {}

    for (const field of definition?.fields ?? []) {
        payload[field.name] = field.default ?? ''
    }

    form.value.actions.push({ type, payload })
}

async function removeAction(index: number) {
    const accepted = await confirm({
        title: 'Remove Action',
        description: 'Remove this action from the workflow? This will not be saved until you click Save Changes.',
        confirmText: 'Remove Action',
        cancelText: 'Cancel',
        danger: true,
    })

    if (!accepted) return

    form.value.actions.splice(index, 1)
    showToast('Action removed.')
}

function moveAction(index: number, direction: -1 | 1) {
    const nextIndex = index + direction
    if (nextIndex < 0 || nextIndex >= form.value.actions.length) return

    const actions = [...form.value.actions]
    const item = actions[index]

    actions.splice(index, 1)
    actions.splice(nextIndex, 0, item)

    form.value.actions = actions
}

function onActionTypeChange(action: ScheduleAction) {
    const definition = actionDefinition(action.type)
    const payload: Record<string, any> = {}

    for (const field of definition?.fields ?? []) {
        payload[field.name] = field.default ?? ''
    }

    action.payload = payload
}

async function generateWorkflow() {
    if (!workflowPrompt.value.trim()) {
        showToast('Enter a workflow prompt first.')
        return
    }

    workflowLoading.value = true
    error.value = ''

    try {
        const response = await fetch('/workflows/generate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                prompt: workflowPrompt.value,
                actions: props.actionDefinitions,
            }),
        })

        if (!response.ok) {
            const message = await readError(response)
            error.value = message
            showToast(message)
            return
        }

        const data = await response.json()

        form.value.actions = (data.actions ?? []).map((action: any) => ({
            type: action.type,
            payload: action.payload ?? {},
        }))

        showToast('Workflow generated. Review it, then save changes.')
    } finally {
        workflowLoading.value = false
    }
}

function validCron(expression: string) {
    return expression.trim().split(/\s+/).length === 5
}

async function saveSchedule() {
    saving.value = true
    error.value = ''

    if (!validCron(form.value.cron_expression)) {
        showToast('Cron expression must have 5 parts.')
        return
    }

    try {
        const response = await fetch(`/cells/${props.cell.id}/schedules/${props.schedule.id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(form.value),
        })

        if (!response.ok) {
            const message = await readError(response)
            error.value = message
            showToast(message)
            return
        }

        showToast('Schedule saved.')
        router.reload({ only: ['schedule'] })
    } finally {
        saving.value = false
    }
}

function runSchedule() {
    router.post(`/cells/${props.cell.id}/schedules/${props.schedule.id}/run`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Schedule dispatched.')
            router.reload({ only: ['schedule'] })
        },
    })
}

function backToSchedules() {
    router.visit(`/cells/${props.cell.id}/schedules`)
}
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${schedule.name} Schedule`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-[1500px] space-y-5">
                    <ScheduleHeader
                        :cell="cell"
                        :form="form"
                        :saving="saving"
                        :cron-summary="cronSummary"
                        @back="backToSchedules"
                        @run="runSchedule"
                        @save="saveSchedule"
                    />

                    <div
                        v-if="error"
                        class="rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-sm font-bold text-status-danger"
                    >
                        {{ error }}
                    </div>

                    <section class="grid gap-5 lg:grid-cols-[1fr_380px]">
                        <div class="space-y-5">
                            <GeneralCard
                                :form="form"
                                :cron="cron"
                            />

                            <AIWorkflowCard
                                v-model="workflowPrompt"
                                :loading="workflowLoading"
                                @generate="generateWorkflow"
                            />

                            <WorkflowCard
                                :form="form"
                                :action-definitions="actionDefinitions"
                                @add-action="addAction"
                                @remove-action="removeAction"
                                @move-action="moveAction"
                                @action-type-change="onActionTypeChange"
                            />
                        </div>

                        <aside class="space-y-5">
                            <ScheduleInfoCard
                                :schedule="schedule"
                                :format-date="formatDate"
                            />

                            <RecentRunsCard
                                :schedule="schedule"
                                :format-date="formatDate"
                                :status-class="statusClass"
                                :action-name="actionName"
                            />
                        </aside>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border border-status-success/40 bg-status-success/15 px-5 py-3 text-sm font-bold text-status-success shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>