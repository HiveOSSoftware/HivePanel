<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import {
    CalendarClock,
    CheckCircle,
    Clock,
    Pencil,
    Play,
    Plus,
    Trash,
    X,
} from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

type ScheduleAction = {
    type: string
    payload: Record<string, any>
}

type ScheduleRun = {
    id: number
    status: 'running' | 'success' | 'failed'
    started_at?: string
    finished_at?: string
    error?: string
}

type ServerSchedule = {
    id: number
    name: string
    cron_expression: string
    timezone: string
    enabled: boolean
    only_when_online: boolean
    continue_on_failure: boolean
    last_run_at?: string
    next_run_at?: string
    actions: ScheduleAction[]
    runs?: ScheduleRun[]
}

type ScheduleTemplate = {
    id: string
    name: string
    description: string
    cron_expression: string
    timezone: string
    enabled: boolean
    only_when_online: boolean
    continue_on_failure: boolean
    actions: ScheduleAction[]
}

const props = defineProps<{
    cell: any
    schedules: ServerSchedule[]
}>()

const { confirm } = useConfirm()

const scheduleList = ref<ServerSchedule[]>([...props.schedules])

const modalOpen = ref(false)
const saving = ref(false)
const actionLoading = ref<number | null>(null)
const error = ref('')
const toastMessage = ref('')
const toastType = ref<'success' | 'error'>('success')

const templates = ref<ScheduleTemplate[]>([])
const selectedTemplateId = ref('custom')
const templatesLoading = ref(false)
const detectedComb = ref<any>(null)

const cronPrompt = ref('')
const cronLoading = ref(false)
const generatedCronDescription = ref('')
const advancedCronOpen = ref(false)

const cron = ref({
    minute: '0',
    hour: '3',
    day: '*',
    month: '*',
    dayOfWeek: '*',
})

const form = ref({
    name: '',
    cron_expression: '0 3 * * *',
    timezone: 'Europe/London',
    enabled: true,
    only_when_online: false,
    continue_on_failure: false,
    actions: [
        {
            type: 'command',
            payload: {
                command: 'say Scheduled task running...',
            },
        },
    ] as ScheduleAction[],
})

const cellId = computed(() => props.cell?.id)

const cronSummary = computed(() => {
    const expression = form.value.cron_expression

    if (expression === '* * * * *') return `Every minute (${form.value.timezone})`
    if (expression.match(/^\*\/\d+ \* \* \* \*$/)) return `Every ${cron.value.minute.replace('*/', '')} minutes (${form.value.timezone})`

    if (
        cron.value.minute === '0' &&
        cron.value.hour !== '*' &&
        cron.value.day === '*' &&
        cron.value.month === '*' &&
        cron.value.dayOfWeek === '*'
    ) {
        return `Every day at ${cron.value.hour.padStart(2, '0')}:00 (${form.value.timezone})`
    }

    return `Cron: ${expression} (${form.value.timezone})`
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

function showToast(message: string, type: 'success' | 'error' = 'success') {
    toastMessage.value = message
    toastType.value = type
    setTimeout(() => toastMessage.value = '', 3000)
}

async function generateCron() {
    if (!cronPrompt.value.trim()) {
        showToast('Enter when this schedule should run.', 'error')
        return
    }

    cronLoading.value = true
    error.value = ''

    try {
        const response = await fetch('/cron/generate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                prompt: cronPrompt.value,
                timezone: form.value.timezone,
            }),
        })

        if (!response.ok) {
            const message = await readError(response)
            error.value = message
            showToast(message, 'error')
            return
        }

        const data = await response.json()

        form.value.cron_expression = data.cron
        generatedCronDescription.value = data.description ?? ''

        const parts = data.cron.trim().split(/\s+/)

        if (parts.length === 5) {
            cron.value = {
                minute: parts[0],
                hour: parts[1],
                day: parts[2],
                month: parts[3],
                dayOfWeek: parts[4],
            }
        }

        showToast('Schedule time generated.')
    } finally {
        cronLoading.value = false
    }
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

function latestRun(schedule: ServerSchedule) {
    return schedule.runs?.[0] ?? null
}

function runStatusClass(status?: string) {
    if (status === 'success') return 'border-status-success/30 bg-status-success/10 text-status-success'
    if (status === 'failed') return 'border-status-danger/30 bg-status-danger/10 text-status-danger'
    if (status === 'running') return 'border-status-warning/30 bg-status-warning/10 text-status-warning'

    return 'border-zinc-700 bg-zinc-800 text-zinc-400'
}

function actionName(type: string) {
    return type
        .split('_')
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ')
}

function resetForm() {
    cron.value = {
        minute: '0',
        hour: '3',
        day: '*',
        month: '*',
        dayOfWeek: '*',
    }

    cronPrompt.value = ''
    generatedCronDescription.value = ''
    advancedCronOpen.value = false

    selectedTemplateId.value = 'custom'

    form.value = {
        name: '',
        cron_expression: '0 3 * * *',
        timezone: 'Europe/London',
        enabled: true,
        only_when_online: false,
        continue_on_failure: false,
        actions: [
            {
                type: 'command',
                payload: {
                    command: 'say Scheduled task running...',
                },
            },
        ],
    }
}

async function openModal() {
    resetForm()
    error.value = ''
    modalOpen.value = true

    await loadScheduleTemplates()
}

function closeModal() {
    if (saving.value) return

    modalOpen.value = false
    resetForm()
}

async function loadScheduleTemplates() {
    if (!cellId.value) return

    templatesLoading.value = true

    try {
        const response = await fetch(`/cells/${cellId.value}/schedule-templates`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            showToast(await readError(response), 'error')
            return
        }

        const data = await response.json()

        detectedComb.value = data.comb ?? null
        templates.value = data.templates ?? []

        if (templates.value.length > 0) {
            applyTemplate(templates.value[0])
        }
    } finally {
        templatesLoading.value = false
    }
}

function applyTemplate(template: ScheduleTemplate) {
    selectedTemplateId.value = template.id

    form.value = {
        name: template.name,
        cron_expression: template.cron_expression,
        timezone: template.timezone,
        enabled: template.enabled,
        only_when_online: template.only_when_online,
        continue_on_failure: template.continue_on_failure,
        actions: template.actions.map(action => ({
            type: action.type,
            payload: { ...(action.payload ?? {}) },
        })),
    }

    const parts = template.cron_expression.trim().split(/\s+/)

    if (parts.length === 5) {
        cron.value = {
            minute: parts[0],
            hour: parts[1],
            day: parts[2],
            month: parts[3],
            dayOfWeek: parts[4],
        }
    }
}

function validCron(expression: string) {
    return expression.trim().split(/\s+/).length === 5
}

async function createSchedule() {
    if (!cellId.value) return

    saving.value = true
    error.value = ''

    if (!validCron(form.value.cron_expression)) {
        showToast('Cron expression must have 5 parts.', 'error')
        return
    }

    try {
        const response = await fetch(`/cells/${cellId.value}/schedules`, {
            method: 'POST',
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
            showToast(message, 'error')
            return
        }

        const schedule = await response.json()

        modalOpen.value = false
        resetForm()
        showToast('Schedule created.')

        router.visit(`/cells/${cellId.value}/schedules/${schedule.id}`)
    } finally {
        saving.value = false
    }
}

async function toggleSchedule(schedule: ServerSchedule) {
    if (!cellId.value) return

    actionLoading.value = schedule.id

    try {
        const response = await fetch(`/cells/${cellId.value}/schedules/${schedule.id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: schedule.name,
                cron_expression: schedule.cron_expression,
                timezone: schedule.timezone,
                enabled: !schedule.enabled,
                only_when_online: schedule.only_when_online,
                continue_on_failure: schedule.continue_on_failure,
                actions: schedule.actions,
            }),
        })

        if (!response.ok) {
            showToast(await readError(response), 'error')
            return
        }

        const saved = await response.json()
        scheduleList.value = scheduleList.value.map(item => item.id === saved.id ? saved : item)
        showToast(saved.enabled ? 'Schedule enabled.' : 'Schedule disabled.')
    } finally {
        actionLoading.value = null
    }
}

async function runSchedule(schedule: ServerSchedule) {
    if (!cellId.value) return

    actionLoading.value = schedule.id

    try {
        const response = await fetch(`/cells/${cellId.value}/schedules/${schedule.id}/run`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            showToast(await readError(response), 'error')
            return
        }

        showToast('Schedule dispatched.')
        await refreshSchedules()
    } finally {
        actionLoading.value = null
    }
}

async function deleteSchedule(schedule: ServerSchedule) {
    if (!cellId.value) return

    const accepted = await confirm({
        title: 'Delete Schedule',
        description: `Delete "${schedule.name}"? This cannot be undone.`,
        confirmText: 'Delete Schedule',
        cancelText: 'Cancel',
        danger: true,
    })

    if (!accepted) return

    actionLoading.value = schedule.id

    try {
        const response = await fetch(`/cells/${cellId.value}/schedules/${schedule.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })

        if (!response.ok) {
            showToast(await readError(response), 'error')
            return
        }

        scheduleList.value = scheduleList.value.filter(item => item.id !== schedule.id)
        showToast('Schedule deleted.')
    } finally {
        actionLoading.value = null
    }
}

async function refreshSchedules() {
    if (!cellId.value) return

    const response = await fetch(`/cells/${cellId.value}/schedules-json`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })

    if (!response.ok) {
        showToast(await readError(response), 'error')
        return
    }

    const data = await response.json()
    scheduleList.value = data.schedules ?? []
}
</script>

<template>
    <AppLayout
        :context="'server'"
        :active-cell="cell"
        :active-cell-status="cell.status ?? 'offline'"
    >
        <Head :title="`${cell.name} Schedules`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto max-w-[1500px] space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-2xl font-black sm:text-3xl">Schedules</h1>
                                <p class="mt-2 text-sm text-zinc-400">
                                    Create schedules, then open one to build its workflow.
                                </p>
                            </div>

                            <button
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light"
                                @click="openModal"
                            >
                                <Plus class="size-4" />
                                New Schedule
                            </button>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <CalendarClock class="size-5 text-hive" />
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Schedules</div>
                                    <div class="mt-1 text-2xl font-black text-white">{{ scheduleList.length }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <CheckCircle class="size-5 text-status-success" />
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Enabled</div>
                                    <div class="mt-1 text-2xl font-black text-white">
                                        {{ scheduleList.filter(schedule => schedule.enabled).length }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-panel border border-zinc-800 bg-surface p-5">
                            <div class="flex items-center gap-3">
                                <Clock class="size-5 text-status-warning" />
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wide text-zinc-500">Next Run</div>
                                    <div class="mt-1 text-sm font-black text-white">
                                        {{ formatDate(scheduleList.find(schedule => schedule.next_run_at)?.next_run_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div v-if="scheduleList.length === 0" class="rounded-button border border-zinc-900 bg-[#0d0f11] p-10 text-center">
                            <CalendarClock class="mx-auto size-10 text-zinc-700" />
                            <h2 class="mt-4 text-lg font-black text-zinc-300">No schedules yet</h2>
                            <p class="mt-2 text-sm text-zinc-500">
                                Create your first schedule, then add workflow actions to it.
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="schedule in scheduleList"
                                :key="schedule.id"
                                class="rounded-button border border-zinc-900 bg-[#0d0f11] p-4 transition hover:border-hive/40 hover:bg-surface-hover"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 text-left"
                                        @click="router.visit(`/cells/${cellId}/schedules/${schedule.id}`)"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate text-base font-black text-white">{{ schedule.name }}</h3>

                                            <span class="rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 font-mono text-xs font-bold text-hive">
                                                {{ schedule.cron_expression }}
                                            </span>

                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-bold"
                                                :class="schedule.enabled
                                                    ? 'border border-status-success/30 bg-status-success/10 text-status-success'
                                                    : 'border border-zinc-700 bg-zinc-800 text-zinc-400'"
                                            >
                                                {{ schedule.enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-4 text-xs text-zinc-500">
                                            <span>{{ schedule.timezone }}</span>
                                            <span>Last: {{ formatDate(schedule.last_run_at) }}</span>
                                            <span>Next: {{ formatDate(schedule.next_run_at) }}</span>
                                            <span>{{ schedule.actions?.length ?? 0 }} action(s)</span>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                v-for="(action, index) in schedule.actions"
                                                :key="index"
                                                class="rounded-full border border-zinc-800 bg-surface-light px-2 py-1 text-xs font-bold text-zinc-400"
                                            >
                                                {{ index + 1 }}. {{ actionName(action.type) }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="latestRun(schedule)"
                                            class="mt-3 rounded-button border border-zinc-800 bg-surface-light px-3 py-2"
                                        >
                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="font-bold text-zinc-500">Latest run:</span>

                                                <span
                                                    class="rounded-full border px-2 py-0.5 font-bold capitalize"
                                                    :class="runStatusClass(latestRun(schedule)?.status)"
                                                >
                                                    {{ latestRun(schedule)?.status }}
                                                </span>

                                                <span class="text-zinc-500">
                                                    Started: {{ formatDate(latestRun(schedule)?.started_at) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-3 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-500"
                                        >
                                            This schedule has not run yet.
                                        </div>
                                    </button>

                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                            :disabled="actionLoading === schedule.id"
                                            @click="router.visit(`/cells/${cellId}/schedules/${schedule.id}`)"
                                        >
                                            <Pencil class="size-4" />
                                            Open
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                            :disabled="actionLoading === schedule.id"
                                            @click="runSchedule(schedule)"
                                        >
                                            <Play class="size-4" />
                                            Run
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-3 py-2 text-xs font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:opacity-50"
                                            :disabled="actionLoading === schedule.id"
                                            @click="toggleSchedule(schedule)"
                                        >
                                            {{ schedule.enabled ? 'Disable' : 'Enable' }}
                                        </button>

                                        <button
                                            class="inline-flex items-center gap-2 rounded-button border border-status-danger/30 bg-status-danger/10 px-3 py-2 text-xs font-bold text-status-danger transition hover:border-status-danger disabled:opacity-50"
                                            :disabled="actionLoading === schedule.id"
                                            @click="deleteSchedule(schedule)"
                                        >
                                            <Trash class="size-4" />
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </div>

        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
        >
            <div class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-panel border border-zinc-800 bg-surface">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-800 px-6 py-5">
                    <div>
                        <h2 class="text-xl font-black text-white">Create Schedule</h2>
                        <p class="mt-1 text-sm text-zinc-500">
                            Pick a template, choose when it should run, then customise the workflow.
                        </p>
                    </div>

                    <button class="text-zinc-500 transition hover:text-white" @click="closeModal">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <div
                        v-if="error"
                        class="mb-5 rounded-button border border-status-danger/30 bg-status-danger/10 p-3 text-sm font-bold text-status-danger"
                    >
                        {{ error }}
                    </div>

                    <div class="space-y-5">
                        <section class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black uppercase tracking-wide text-zinc-400">
                                        Choose Template
                                    </h3>

                                    <p class="mt-1 text-xs text-zinc-500">
                                        Start with a common workflow, then customise it after creation.
                                    </p>
                                </div>

                                <span
                                    v-if="detectedComb"
                                    class="rounded-full border border-hive/30 bg-hive/10 px-2 py-1 text-xs font-bold text-hive"
                                >
                                    {{ detectedComb.name ?? detectedComb.slug ?? 'Detected game' }}
                                </span>
                            </div>

                            <div
                                v-if="templatesLoading"
                                class="rounded-button border border-zinc-800 bg-surface-light p-4 text-sm font-bold text-zinc-500"
                            >
                                Loading templates...
                            </div>

                            <div v-else class="grid gap-3 md:grid-cols-2">
                                <button
                                    v-for="template in templates"
                                    :key="template.id"
                                    type="button"
                                    class="rounded-button border p-4 text-left transition"
                                    :class="selectedTemplateId === template.id
                                        ? 'border-hive bg-hive/10'
                                        : 'border-zinc-800 bg-surface-light hover:border-hive/50'"
                                    @click="applyTemplate(template)"
                                >
                                    <div class="font-black text-white">
                                        {{ template.name }}
                                    </div>

                                    <p class="mt-1 text-sm leading-5 text-zinc-500">
                                        {{ template.description }}
                                    </p>
                                </button>
                            </div>
                        </section>

                        <label class="space-y-2">
                            <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Schedule Name</span>
                            <input
                                v-model="form.name"
                                class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="Daily restart"
                            />
                        </label>

                        <section class="space-y-4">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wide text-zinc-400">
                                    When should this run?
                                </h3>

                                <p class="mt-1 text-xs text-zinc-500">
                                    Describe the schedule in plain English or edit the cron fields below. The AI simply fills the fields for you—you can always adjust them afterwards.
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <input
                                    v-model="cronPrompt"
                                    class="min-w-0 flex-1 rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                    placeholder="Every day at 3am"
                                />

                                <button
                                    class="rounded-button border border-hive bg-hive px-5 py-3 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                                    :disabled="cronLoading"
                                    @click="generateCron"
                                >
                                    {{ cronLoading ? 'Generating…' : 'Ask AI' }}
                                </button>
                            </div>

                            <div class="rounded-button border border-zinc-800 bg-surface-light p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Schedule Preview
                                    </span>

                                    <span class="font-mono text-xs font-bold text-hive">
                                        {{ form.cron_expression }}
                                    </span>
                                </div>

                                <div class="mt-2 text-sm font-semibold text-zinc-300">
                                    {{ generatedCronDescription || cronSummary }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                                <label class="space-y-2">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-400">
                                        Minute
                                    </span>

                                    <input
                                        v-model="cron.minute"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-400">
                                        Hour
                                    </span>

                                    <input
                                        v-model="cron.hour"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-400">
                                        Day
                                    </span>

                                    <input
                                        v-model="cron.day"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-400">
                                        Month
                                    </span>

                                    <input
                                        v-model="cron.month"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-400">
                                        Day of Week
                                    </span>

                                    <input
                                        v-model="cron.dayOfWeek"
                                        class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none focus:border-hive"
                                    />
                                </label>
                            </div>

                            <div class="rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3">
                                <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                    Cron Expression
                                </div>

                                <div class="mt-2 font-mono text-sm font-bold text-zinc-300">
                                    {{ form.cron_expression }}
                                </div>
                            </div>
                        </section>

                        <label class="space-y-2">
                            <span class="text-xs font-black uppercase tracking-wide text-zinc-400">Timezone</span>
                            <input
                                v-model="form.timezone"
                                class="w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none focus:border-hive"
                                placeholder="Europe/London"
                            />
                        </label>

                        <label class="flex items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light px-4 py-3">
                            <span>
                                <span class="block text-sm font-bold text-zinc-300">Enabled</span>
                                <span class="text-xs text-zinc-500">Allow this schedule to run automatically.</span>
                            </span>
                            <input v-model="form.enabled" type="checkbox" class="accent-hive" />
                        </label>
                    </div>
                </div>

                <div class="border-t border-zinc-800 bg-surface px-6 py-4">
                    <div class="flex justify-end gap-3">
                        <button
                            class="rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:text-white"
                            @click="closeModal"
                        >
                            Cancel
                        </button>

                        <button
                            class="rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-white transition hover:bg-hive-light disabled:opacity-50"
                            :disabled="saving"
                            @click="createSchedule"
                        >
                            {{ saving ? 'Creating...' : 'Create & Open' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="toastMessage"
            class="fixed bottom-5 right-5 z-[60] rounded-button border px-5 py-3 text-sm font-bold shadow-[0_20px_70px_rgba(0,0,0,0.45)]"
            :class="toastType === 'success'
                ? 'border-status-success/40 bg-status-success/15 text-status-success'
                : 'border-status-danger/40 bg-status-danger/15 text-status-danger'"
        >
            {{ toastMessage }}
        </div>
    </AppLayout>
</template>