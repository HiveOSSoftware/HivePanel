<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ArrowLeft,
    Boxes,
    Check,
    ChevronDown,
    CircleAlert,
    RefreshCw,
    Server,
    ShieldAlert,
} from 'lucide-vue-next'
import {
    computed,
    ref,
    watch,
} from 'vue'

type Comb = {
    id: string
    external_id: string
    name: string
    game?: string | null
    source?: string | null
    data?: Record<string, any> | null
}

type Cell = {
    id: string
    name: string
    comb?: string | null
    status?: string | null
    running?: boolean
    variables?: Record<string, string> | null
    install_status?: string | null
    install_status_label?: string | null
    install_failure_reason?: string | null
}

const props = defineProps<{
    cell: Cell
    combs: Comb[]
}>()

const selectedCombId = ref('')
const variables = ref<Record<string, string>>({})
const confirmation = ref('')
const startOnCompletion = ref(false)

const submitting = ref(false)
const error = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const protectedVariableNames = new Set([
    'memory',
    'memory_mb',
    'overhead_memory',
    'overhead_memory_mb',
    'swap',
    'swap_mb',
    'cpu',
    'cpu_percent',
    'cpu_pinning',
    'disk',
    'disk_mb',
    'server_ip',
    'server_port',
    'allocation',
    'allocation_ip',
    'allocation_port',
    'node',
    'node_id',
    'daemon_id',
    'cell_id',
])

const selectedComb = computed(() => {
    return props.combs.find(
        comb => comb.id === selectedCombId.value,
    ) ?? null
})

const currentComb = computed(() => {
    return props.combs.find(
        comb => comb.external_id === props.cell.comb,
    ) ?? null
})

const isSameComb = computed(() => {
    return (
        selectedComb.value?.external_id ===
        props.cell.comb
    )
})

const isRunning = computed(() => {
    if (props.cell.running === true) {
        return true
    }

    return String(
        props.cell.status ?? '',
    ).toLowerCase() === 'running'
})

const confirmationMatches = computed(() => {
    return confirmation.value === props.cell.name
})

const canSubmit = computed(() => {
    return (
        !isRunning.value &&
        !!selectedCombId.value &&
        confirmationMatches.value &&
        !submitting.value
    )
})

const rawVariableSchema = computed<any[]>(() => {
    const data = selectedComb.value?.data ?? {}

    if (Array.isArray(data.variables)) {
        return data.variables
    }

    if (Array.isArray(data.variables_schema)) {
        return data.variables_schema
    }

    return []
})

const variableSchema = computed<any[]>(() => {
    return rawVariableSchema.value.filter(variable => {
        const name = variableName(variable)
            .trim()
            .toLowerCase()

        if (!name) {
            return false
        }

        if (protectedVariableNames.has(name)) {
            return false
        }

        if (variable?.user_editable === false) {
            return false
        }

        if (variable?.editable === false) {
            return false
        }

        if (variable?.hidden === true) {
            return false
        }

        if (variable?.internal === true) {
            return false
        }

        return true
    })
})

function variableName(variable: any) {
    return String(
        variable?.name ??
        variable?.key ??
        variable?.env_variable ??
        '',
    )
}

function variableLabel(variable: any) {
    return String(
        variable?.label ??
        variable?.display_name ??
        variable?.name ??
        variable?.key ??
        variable?.env_variable ??
        'Setting',
    )
}

function variableDescription(variable: any) {
    return String(
        variable?.description ??
        variable?.help ??
        variable?.hint ??
        '',
    )
}

function variableDefault(variable: any) {
    const value =
        variable?.default ??
        variable?.default_value ??
        ''

    return value === null || value === undefined
        ? ''
        : String(value)
}

function variableType(variable: any) {
    return String(
        variable?.type ??
        'text',
    ).toLowerCase()
}

function variableOptions(variable: any): any[] {
    const options =
        variable?.options ??
        variable?.values ??
        variable?.choices ??
        []

    return Array.isArray(options)
        ? options
        : []
}

function optionValue(option: any) {
    if (
        typeof option === 'string' ||
        typeof option === 'number'
    ) {
        return String(option)
    }

    return String(
        option?.value ??
        option?.id ??
        option?.name ??
        '',
    )
}

function optionLabel(option: any) {
    if (
        typeof option === 'string' ||
        typeof option === 'number'
    ) {
        return String(option)
    }

    return String(
        option?.label ??
        option?.name ??
        option?.value ??
        '',
    )
}

function isBooleanVariable(variable: any) {
    return [
        'boolean',
        'bool',
        'checkbox',
    ].includes(
        variableType(variable),
    )
}

function isSelectVariable(variable: any) {
    return (
        variableType(variable) === 'select' ||
        variableType(variable) === 'dropdown' ||
        variableType(variable) === 'choice' ||
        variableOptions(variable).length > 0
    )
}

function csrfToken() {
    return document
        .querySelector<HTMLMetaElement>(
            'meta[name="csrf-token"]',
        )
        ?.content ?? ''
}

function responseError(
    body: any,
    fallback: string,
) {
    return (
        body?.message ??
        body?.error ??
        fallback
    )
}

function initialiseSelectedComb() {
    const existing = props.combs.find(
        comb =>
            comb.external_id ===
            props.cell.comb,
    )

    selectedCombId.value =
        existing?.id ??
        props.combs[0]?.id ??
        ''
}

function initialiseVariables() {
    const result: Record<string, string> = {}

    for (const variable of variableSchema.value) {
        const name = variableName(variable)

        if (!name) {
            continue
        }

        const existing =
            props.cell.variables?.[name]

        if (
            isSameComb.value &&
            existing !== undefined
        ) {
            result[name] = String(existing)
            continue
        }

        result[name] = variableDefault(variable)
    }

    variables.value = result
}

async function reinstall() {
    if (!canSubmit.value) {
        return
    }

    submitting.value = true
    error.value = ''
    fieldErrors.value = {}

    try {
        const response = await fetch(
            `/cells/${props.cell.id}/reinstall`,
            {
                method: 'POST',
                credentials: 'same-origin',

                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },

                body: JSON.stringify({
                    comb_id: selectedCombId.value,
                    variables: variables.value,
                    start_on_completion:
                        startOnCompletion.value,
                    confirmation:
                        confirmation.value,
                }),
            },
        )

        const body = await response
            .json()
            .catch(() => ({}))

        if (!response.ok) {
            fieldErrors.value =
                body?.errors ?? {}

            error.value = responseError(
                body,
                'The cell could not be reinstalled.',
            )

            return
        }

        router.visit(
            `/cells/${props.cell.id}`,
        )
    } catch {
        error.value =
            'Unable to connect to the server.'
    } finally {
        submitting.value = false
    }
}

function goBack() {
    window.history.back()
}

watch(
    selectedCombId,
    () => {
        initialiseVariables()
    },
)

initialiseSelectedComb()
initialiseVariables()
</script>

<template>
    <AppLayout
        context="server"
        :active-cell="cell"
        :active-cell-status="cell.status as any"
    >
        <Head :title="`Reinstall ${cell.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] text-hive">
                                    <RefreshCw class="size-5" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Reinstall Server
                                    </h1>

                                    <p class="mt-2 text-sm leading-6 text-zinc-400">
                                        Install a fresh copy of
                                        <span class="font-bold text-zinc-200">
                                            {{ cell.name }}
                                        </span>
                                        using the current or another comb.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2.5 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                                @click="goBack"
                            >
                                <ArrowLeft class="size-4" />
                                Back
                            </button>
                        </div>
                    </section>

                    <section
                        v-if="isRunning"
                        class="rounded-panel border border-status-warning/30 bg-status-warning/10 p-5"
                    >
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="mt-0.5 size-5 shrink-0 text-status-warning" />

                            <div>
                                <div class="font-black text-status-warning">
                                    Server must be stopped
                                </div>

                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                    Stop the server before reinstalling it. HivePanel will not automatically stop a running server for this action.
                                </p>
                            </div>
                        </div>
                    </section>

                    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <Boxes class="size-5 text-hive" />

                                            <h2 class="text-lg font-black">
                                                Server Software
                                            </h2>
                                        </div>

                                        <p class="mt-2 text-sm leading-6 text-zinc-500">
                                            Choose the comb and installation options for the fresh installation.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5">
                                    <label class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Comb
                                    </label>

                                    <div class="relative mt-2">
                                        <select
                                            v-model="selectedCombId"
                                            class="w-full appearance-none rounded-button border border-zinc-800 bg-surface-light px-4 py-3 pr-10 text-sm font-bold text-zinc-200 outline-none transition focus:border-hive"
                                        >
                                            <option
                                                v-for="comb in combs"
                                                :key="comb.id"
                                                :value="comb.id"
                                            >
                                                {{ comb.name }}
                                                {{ comb.game ? `— ${comb.game}` : '' }}
                                            </option>
                                        </select>

                                        <ChevronDown class="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
                                    </div>

                                    <div
                                        v-if="selectedComb"
                                        class="mt-3 flex flex-wrap items-center gap-2 text-xs"
                                    >
                                        <span class="rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 font-bold text-zinc-400">
                                            {{ selectedComb.external_id }}
                                        </span>

                                        <span
                                            v-if="selectedComb.game"
                                            class="rounded-full border border-zinc-800 bg-[#0d0f11] px-3 py-1 font-bold capitalize text-zinc-400"
                                        >
                                            {{ selectedComb.game }}
                                        </span>

                                        <span
                                            v-if="isSameComb"
                                            class="rounded-full border border-hive/30 bg-hive/10 px-3 py-1 font-black text-hive"
                                        >
                                            Current Comb
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section
                                v-if="variableSchema.length > 0"
                                class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                            >
                                <div class="flex items-center gap-3">
                                    <Server class="size-5 text-hive" />

                                    <div>
                                        <h2 class="text-lg font-black">
                                            Comb Settings
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Configure the options exposed by this comb.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                    <div
                                        v-for="variable in variableSchema"
                                        :key="variableName(variable)"
                                    >
                                        <label class="text-sm font-bold text-zinc-400">
                                            {{ variableLabel(variable) }}
                                        </label>

                                        <select
                                            v-if="isSelectVariable(variable)"
                                            v-model="variables[variableName(variable)]"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none transition focus:border-hive"
                                        >
                                            <option
                                                v-for="option in variableOptions(variable)"
                                                :key="optionValue(option)"
                                                :value="optionValue(option)"
                                            >
                                                {{ optionLabel(option) }}
                                            </option>
                                        </select>

                                        <label
                                            v-else-if="isBooleanVariable(variable)"
                                            class="mt-2 flex cursor-pointer items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light p-4"
                                        >
                                            <div>
                                                <div class="text-sm font-black text-zinc-200">
                                                    {{ variables[variableName(variable)] === 'true' ? 'Enabled' : 'Disabled' }}
                                                </div>

                                                <div class="mt-1 text-xs text-zinc-500">
                                                    Toggle this comb option.
                                                </div>
                                            </div>

                                            <input
                                                type="checkbox"
                                                :checked="variables[variableName(variable)] === 'true'"
                                                class="size-4"
                                                @change="variables[variableName(variable)] = ($event.target as HTMLInputElement).checked ? 'true' : 'false'"
                                            />
                                        </label>

                                        <input
                                            v-else
                                            v-model="variables[variableName(variable)]"
                                            :type="variableType(variable) === 'number' ? 'number' : 'text'"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 text-sm text-zinc-200 outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                        />

                                        <p
                                            v-if="variableDescription(variable)"
                                            class="mt-2 text-xs leading-5 text-zinc-500"
                                        >
                                            {{ variableDescription(variable) }}
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section
                                v-else
                                class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6"
                            >
                                <div class="flex items-start gap-3">
                                    <Boxes class="mt-0.5 size-5 text-zinc-500" />

                                    <div>
                                        <h2 class="font-black text-zinc-300">
                                            No configurable comb settings
                                        </h2>

                                        <p class="mt-1 text-sm leading-6 text-zinc-500">
                                            This comb does not expose any user-configurable installation options.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-center gap-3">
                                    <RefreshCw class="size-5 text-hive" />

                                    <div>
                                        <h2 class="text-lg font-black">
                                            After Installation
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Choose what HivePanel should do after installation completes.
                                        </p>
                                    </div>
                                </div>

                                <label class="mt-5 flex cursor-pointer items-center justify-between gap-4 rounded-button border border-zinc-800 bg-surface-light p-4">
                                    <div>
                                        <div class="text-sm font-black text-zinc-200">
                                            Start server automatically
                                        </div>

                                        <div class="mt-1 text-xs leading-5 text-zinc-500">
                                            Start the server when the fresh installation completes successfully.
                                        </div>
                                    </div>

                                    <input
                                        v-model="startOnCompletion"
                                        type="checkbox"
                                        class="size-4"
                                    />
                                </label>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-status-danger/30 bg-surface p-5 sm:p-6">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-button border border-status-danger/30 bg-status-danger/10 text-status-danger">
                                        <ShieldAlert class="size-5" />
                                    </div>

                                    <div>
                                        <h2 class="text-lg font-black">
                                            Reinstall Warning
                                        </h2>

                                        <p class="mt-2 text-sm leading-6 text-zinc-400">
                                            Reinstalling permanently removes all files inside this server's working directory.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-2">
                                    <div class="flex items-center gap-2 text-xs font-bold text-zinc-400">
                                        <Check class="size-4 text-status-success" />
                                        Backups are retained
                                    </div>

                                    <div class="flex items-center gap-2 text-xs font-bold text-zinc-400">
                                        <Check class="size-4 text-status-success" />
                                        Allocations are retained
                                    </div>

                                    <div class="flex items-center gap-2 text-xs font-bold text-zinc-400">
                                        <Check class="size-4 text-status-success" />
                                        Subusers are retained
                                    </div>

                                    <div class="flex items-center gap-2 text-xs font-bold text-zinc-400">
                                        <Check class="size-4 text-status-success" />
                                        Audit history is retained
                                    </div>
                                </div>

                                <div class="mt-5 rounded-button border border-status-danger/20 bg-status-danger/5 p-4">
                                    <div class="flex items-start gap-2">
                                        <CircleAlert class="mt-0.5 size-4 shrink-0 text-status-danger" />

                                        <p class="text-xs leading-5 text-zinc-400">
                                            Server files cannot be recovered unless you have a backup.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black">
                                        Confirm Reinstall
                                    </h2>

                                    <p class="mt-2 text-sm leading-6 text-zinc-500">
                                        Enter the server name exactly to enable the reinstall action.
                                    </p>
                                </div>

                                <label class="mt-5 block">
                                    <span class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Type
                                        <span class="text-zinc-200">
                                            {{ cell.name }}
                                        </span>
                                    </span>

                                    <input
                                        v-model="confirmation"
                                        type="text"
                                        autocomplete="off"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-surface-light px-4 py-3 font-mono text-sm font-bold text-zinc-200 outline-none transition placeholder:text-zinc-700 focus:border-status-danger"
                                        :placeholder="cell.name"
                                    />
                                </label>

                                <p
                                    v-if="fieldErrors.confirmation?.[0]"
                                    class="mt-2 text-xs font-bold text-status-danger"
                                >
                                    {{ fieldErrors.confirmation[0] }}
                                </p>

                                <div
                                    v-if="error"
                                    class="mt-4 rounded-button border border-status-danger/30 bg-status-danger/10 p-4 text-sm font-bold text-status-danger"
                                >
                                    {{ error }}
                                </div>

                                <button
                                    type="button"
                                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-button border border-status-danger bg-status-danger px-4 py-3 text-sm font-black text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!canSubmit"
                                    @click="reinstall"
                                >
                                    <RefreshCw
                                        class="size-4"
                                        :class="{
                                            'animate-spin': submitting,
                                        }"
                                    />

                                    {{
                                        submitting
                                            ? 'Preparing Reinstall...'
                                            : 'Reinstall Server'
                                    }}
                                </button>

                                <p
                                    v-if="isRunning"
                                    class="mt-3 text-center text-xs font-bold text-status-warning"
                                >
                                    Stop the server to enable reinstall.
                                </p>

                                <p
                                    v-else-if="!confirmationMatches"
                                    class="mt-3 text-center text-xs text-zinc-600"
                                >
                                    Enter the server name to continue.
                                </p>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>