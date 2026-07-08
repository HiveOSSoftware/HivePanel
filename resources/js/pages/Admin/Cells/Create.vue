<script setup lang="ts">
import AllocationSelector from './Sections/AllocationSelector.vue'
import DeploySummary from './Sections/DeploySummary.vue'
import NodeSelector from './Sections/NodeSelector.vue'
import ResourceLimits from './Sections/ResourceLimits.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    Boxes,
    Check,
    ChevronLeft,
    ChevronRight,
    Database,
    Container,
    Info,
    Search,
    Settings,
    Terminal,
    User,
} from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

type UserRecord = {
    id: number | string
    name: string
    email: string
}

type CombRecord = {
    id: number | string
    external_id: string
    name: string
    game: string
    source?: string
    data?: any
}

const props = defineProps<{
    nodes: any[]
    combs: CombRecord[]
    users: UserRecord[]
}>()

const currentStep = ref(1)
const allocations = ref<any[]>([])
const loadingAllocations = ref(false)
const ownerSearchFocused = ref(false)

const form = useForm({
    node_id: '',
    allocation_id: '',
    additional_allocation_ids: [] as any[],

    name: '',
    owner_email: '',
    description: '',
    start_on_completion: true,

    comb_id: '',
    version: '1.21.8',
    skip_install_script: false,

    memory_mb: 2048,
    overhead_memory_mb: 0,
    swap_mb: 0,
    disk_mb: 10240,
    cpu_percent: 100,
    cpu_pinning: '',
    io_weight: 500,
    oom_killer: true,
    exclude_from_resource_calculation: false,

    database_limit: null as number | null,
    allocation_limit: null as number | null,
    backup_limit: null as number | null,
    backup_storage_mb: null as number | null,

    docker_image: 'ghcr.io/pterodactyl/yolks:java_21',
    startup_command: 'java -Xms128M -XX:MaxRAMPercentage=95.0 -jar {{SERVER_JARFILE}}',

    variables: {
        SERVER_JARFILE: 'server.jar',
    },
})

const steps = [
    { id: 1, label: 'Core', icon: Info },
    { id: 2, label: 'Placement', icon: Settings },
    { id: 3, label: 'Comb', icon: Boxes },
    { id: 4, label: 'Resources', icon: Terminal },
    { id: 5, label: 'Advanced', icon: Database },
    { id: 6, label: 'Review', icon: Check },
]

const selectedNode = computed(() =>
    props.nodes.find((node) => String(node.id) === String(form.node_id))
)

const selectedAllocation = computed(() =>
    allocations.value.find((allocation) => String(allocation.id) === String(form.allocation_id))
)

const selectedAdditionalAllocations = computed(() =>
    allocations.value.filter((allocation) =>
        form.additional_allocation_ids.map(String).includes(String(allocation.id))
    )
)

const selectedComb = computed(() =>
    props.combs.find((comb) => String(comb.id) === String(form.comb_id))
)

const filteredUsers = computed(() => {
    const q = form.owner_email.toLowerCase().trim()

    if (!q) return props.users.slice(0, 8)

    return props.users
        .filter((user) =>
            user.name.toLowerCase().includes(q) ||
            user.email.toLowerCase().includes(q)
        )
        .slice(0, 8)
})

const groupedCombs = computed(() => {
    return props.combs.reduce<Record<string, CombRecord[]>>((groups, comb) => {
        const game = comb.game || 'Other'

        if (!groups[game]) groups[game] = []

        groups[game].push(comb)

        return groups
    }, {})
})

const canContinue = computed(() => {
    if (currentStep.value === 1) {
        return !!form.name
    }

    if (currentStep.value === 2) {
        return !!form.node_id && !!form.allocation_id
    }

    if (currentStep.value === 3) {
        return !!form.comb_id && !!form.version
    }

    return true
})

const canSubmit = computed(() =>
    !!form.name &&
    !!form.node_id &&
    !!form.allocation_id &&
    !!form.comb_id &&
    !!form.version
)

watch(
    () => form.node_id,
    async (nodeId) => {
        form.allocation_id = ''
        form.additional_allocation_ids = []
        allocations.value = []

        if (!nodeId) return

        loadingAllocations.value = true

        try {
            const response = await fetch(`/admin/nodes/${nodeId}/available-allocations`, {
                headers: { Accept: 'application/json' },
            })

            if (response.ok) {
                const data = await response.json()
                allocations.value = data.allocations ?? []
            }
        } finally {
            loadingAllocations.value = false
        }
    }
)

watch(
    () => form.allocation_id,
    (allocationId) => {
        form.additional_allocation_ids = form.additional_allocation_ids.filter(
            (id) => String(id) !== String(allocationId)
        )
    }
)

watch(selectedComb, (comb) => {
    if (!comb?.data) return

    if (comb.data.image) {
        form.docker_image = comb.data.image
    }

    if (comb.data.startup) {
        form.startup_command = comb.data.startup
    }

    if (comb.data.variables) {
        form.variables = {
            ...form.variables,
            ...comb.data.variables,
        }
    }

    if (comb.data.variables?.version) {
        form.version = comb.data.variables.version
    }
})

function selectOwner(user: UserRecord) {
    form.owner_email = user.email
    ownerSearchFocused.value = false
}

function nextStep() {
    if (!canContinue.value) return
    currentStep.value = Math.min(currentStep.value + 1, steps.length)
}

function previousStep() {
    currentStep.value = Math.max(currentStep.value - 1, 1)
}

function goToStep(step: number) {
    currentStep.value = step
}

function submit() {
    router.post('/admin/cells', form.data(), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head title="Create Cell" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-zinc-500">
                            <Link href="/admin" class="hover:text-hive">Admin</Link>
                            <span>›</span>
                            <Link href="/admin/cells" class="hover:text-hive">Cells</Link>
                            <span>›</span>
                            <span class="text-zinc-300">Create</span>
                        </div>

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h1 class="text-3xl font-black tracking-tight">Create Cell</h1>
                                <p class="mt-2 text-sm text-zinc-400">
                                    Follow the steps to configure and deploy a new Cell.
                                </p>
                            </div>

                            <Link
                                href="/admin/cells"
                                class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Back to Cells
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-panel border border-zinc-800 bg-surface p-2">
                        <div class="grid gap-2 md:grid-cols-6">
                            <button
                                v-for="step in steps"
                                :key="step.id"
                                type="button"
                                class="flex items-center gap-3 rounded-button px-4 py-3 text-left transition"
                                :class="currentStep === step.id
                                    ? 'bg-hive/10 text-hive'
                                    : currentStep > step.id
                                        ? 'bg-status-success/10 text-status-success'
                                        : 'text-zinc-500 hover:bg-surface-light hover:text-white'"
                                @click="goToStep(step.id)"
                            >
                                <component :is="step.icon" class="size-4" />
                                <span class="text-sm font-black">{{ step.label }}</span>
                            </button>
                        </div>
                    </section>

                    <form class="grid gap-5 xl:grid-cols-[1fr_380px]" @submit.prevent="submit">
                        <div class="space-y-5">
                            <section v-if="currentStep === 1" class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Info class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Core Details</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">Cell Name</label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            placeholder="Survival Server"
                                            class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <div class="relative">
                                        <label class="text-sm font-bold text-zinc-400">Owner Email</label>

                                        <div class="relative mt-2">
                                            <User class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />

                                            <input
                                                v-model="form.owner_email"
                                                type="email"
                                                placeholder="Search name or email..."
                                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] py-3 pl-10 pr-4 text-sm font-bold text-white outline-none transition focus:border-hive"
                                                @focus="ownerSearchFocused = true"
                                                @blur="setTimeout(() => ownerSearchFocused = false, 150)"
                                            />
                                        </div>

                                        <div
                                            v-if="ownerSearchFocused && filteredUsers.length > 0"
                                            class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-button border border-zinc-800 bg-[#0d0f11] p-2 shadow-2xl"
                                        >
                                            <button
                                                v-for="user in filteredUsers"
                                                :key="user.id"
                                                type="button"
                                                class="block w-full rounded-button px-3 py-2 text-left transition hover:bg-surface-light"
                                                @mousedown.prevent="selectOwner(user)"
                                            >
                                                <span class="block text-sm font-black text-white">
                                                    {{ user.name }}
                                                </span>
                                                <span class="block text-xs font-bold text-zinc-500">
                                                    {{ user.email }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="text-sm font-bold text-zinc-400">Description</label>
                                        <textarea
                                            v-model="form.description"
                                            rows="4"
                                            placeholder="Optional internal description..."
                                            class="mt-2 w-full resize-none rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        />
                                    </div>

                                    <label class="md:col-span-2 flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <input v-model="form.start_on_completion" type="checkbox" class="mt-1" />
                                        <span>
                                            <span class="block text-sm font-black text-white">Start Cell when installed</span>
                                            <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                                Automatically start the Cell once installation completes successfully.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <template v-if="currentStep === 2">
                                <NodeSelector v-model="form.node_id" :nodes="nodes" />

                                <AllocationSelector
                                    v-model="form.allocation_id"
                                    :allocations="allocations"
                                    :disabled="!form.node_id"
                                    :loading="loadingAllocations"
                                />

                                <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                    <div class="mb-5 flex items-center gap-3">
                                        <Settings class="size-5 text-hive" />
                                        <h2 class="text-lg font-black">Additional Allocations</h2>
                                    </div>

                                    <div v-if="!form.node_id" class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
                                        Select a node first.
                                    </div>

                                    <div v-else-if="allocations.length === 0" class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500">
                                        No allocations available.
                                    </div>

                                    <div v-else class="max-h-64 space-y-2 overflow-y-auto rounded-button border border-zinc-800 bg-[#0d0f11] p-3">
                                        <label
                                            v-for="allocation in allocations.filter((item) => String(item.id) !== String(form.allocation_id))"
                                            :key="allocation.id"
                                            class="flex cursor-pointer items-center justify-between gap-3 rounded-button px-3 py-2 hover:bg-surface-light"
                                        >
                                            <span>
                                                <span class="block font-mono text-sm font-black text-white">
                                                    {{ allocation.ip }}:{{ allocation.port }}
                                                </span>
                                                <span class="block text-xs font-bold text-zinc-500">
                                                    {{ allocation.alias || 'No alias' }}
                                                </span>
                                            </span>

                                            <input
                                                v-model="form.additional_allocation_ids"
                                                type="checkbox"
                                                :value="allocation.id"
                                            />
                                        </label>
                                    </div>
                                </section>
                            </template>

                            <section v-if="currentStep === 3" class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Boxes class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Comb & Startup</h2>
                                </div>

                                <div v-if="combs.length === 0" class="rounded-button border border-zinc-800 bg-[#0d0f11] p-8 text-center">
                                    <Boxes class="mx-auto size-10 text-zinc-700" />
                                    <h3 class="mt-4 text-lg font-black text-zinc-300">No combs installed</h3>
                                    <p class="mt-2 text-sm text-zinc-500">
                                        Import a comb from HiveRegistry or create a manual comb first.
                                    </p>

                                    <Link
                                        href="/admin/combs"
                                        class="mt-5 inline-flex items-center justify-center rounded-button border border-hive bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90"
                                    >
                                        Manage Combs
                                    </Link>
                                </div>

                                <div v-else class="space-y-5">
                                    <div class="space-y-4">
                                        <div
                                            v-for="(items, game) in groupedCombs"
                                            :key="game"
                                            class="space-y-2"
                                        >
                                            <h3 class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                                {{ game }}
                                            </h3>

                                            <div class="grid gap-3 md:grid-cols-2">
                                                <button
                                                    v-for="comb in items"
                                                    :key="comb.id"
                                                    type="button"
                                                    class="rounded-button border p-4 text-left transition hover:-translate-y-0.5 hover:border-hive/40 hover:bg-surface-hover active:translate-y-0"
                                                    :class="String(form.comb_id) === String(comb.id)
                                                        ? 'border-hive bg-hive/10'
                                                        : 'border-zinc-900 bg-[#0d0f11]'"
                                                    @click="form.comb_id = String(comb.id)"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <h4 class="text-sm font-black text-white">
                                                                {{ comb.name }}
                                                            </h4>

                                                            <p class="mt-1 font-mono text-xs text-zinc-500">
                                                                {{ comb.external_id }}
                                                            </p>
                                                        </div>

                                                        <span
                                                            class="rounded-full border px-2 py-0.5 text-xs font-bold"
                                                            :class="comb.source === 'registry'
                                                                ? 'border-hive/30 bg-hive/10 text-hive'
                                                                : 'border-zinc-700 bg-zinc-800 text-zinc-400'"
                                                        >
                                                            {{ comb.source || 'local' }}
                                                        </span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-sm font-bold text-zinc-400">Server Version</label>
                                            <input
                                                v-model="form.version"
                                                type="text"
                                                placeholder="1.21.8"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <div>
                                            <label class="flex items-center gap-2 text-sm font-bold text-zinc-400">
                                                <Container class="size-4 text-zinc-500" />
                                                Docker Image
                                            </label>
                                            <input
                                                v-model="form.docker_image"
                                                type="text"
                                                placeholder="hivepanel/java:25"
                                                class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 font-mono text-sm font-bold text-white outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-sm font-bold text-zinc-400">Startup Command</label>
                                            <textarea
                                                v-model="form.startup_command"
                                                rows="4"
                                                class="mt-2 w-full resize-none rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 font-mono text-sm font-bold text-white outline-none transition focus:border-hive"
                                            />
                                        </div>

                                        <label class="md:col-span-2 flex cursor-pointer items-start gap-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                            <input v-model="form.skip_install_script" type="checkbox" class="mt-1" />
                                            <span>
                                                <span class="block text-sm font-black text-white">Skip install script</span>
                                                <span class="mt-1 block text-xs leading-5 text-zinc-500">
                                                    Skip the Comb install script during deployment.
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <ResourceLimits
                                v-if="currentStep === 4"
                                v-model:memory-mb="form.memory_mb"
                                v-model:overhead-memory-mb="form.overhead_memory_mb"
                                v-model:swap-mb="form.swap_mb"
                                v-model:disk-mb="form.disk_mb"
                                v-model:cpu-percent="form.cpu_percent"
                                v-model:cpu-pinning="form.cpu_pinning"
                                v-model:io-weight="form.io_weight"
                                v-model:oom-killer="form.oom_killer"
                                v-model:exclude-from-resource-calculation="form.exclude_from_resource_calculation"
                            />

                            <section v-if="currentStep === 5" class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="mb-5 flex items-center gap-3">
                                    <Database class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Feature Limits & Advanced</h2>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">Database Limit</label>
                                        <input v-model="form.database_limit" type="number" min="0" placeholder="Unlimited" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive" />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">Allocation Limit</label>
                                        <input v-model="form.allocation_limit" type="number" min="0" placeholder="Unlimited" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive" />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">Backup Limit</label>
                                        <input v-model="form.backup_limit" type="number" min="0" placeholder="Unlimited" class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive" />
                                    </div>

                                    <div>
                                        <label class="text-sm font-bold text-zinc-400">Backup Storage Limit</label>
                                        <div class="relative mt-2">
                                            <input v-model="form.backup_storage_mb" type="number" min="0" placeholder="Unlimited" class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-14 text-sm font-bold text-white outline-none transition focus:border-hive" />
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-zinc-500">MiB</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section v-if="currentStep === 6" class="rounded-panel border border-hive/30 bg-hive/5 p-5 sm:p-6">
                                <div class="mb-3 flex items-center gap-3">
                                    <Check class="size-5 text-hive" />
                                    <h2 class="text-lg font-black">Ready to Deploy</h2>
                                </div>

                                <p class="text-sm leading-6 text-zinc-400">
                                    Review the summary on the right. When everything looks correct, deploy the Cell.
                                </p>
                            </section>

                            <div class="flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="currentStep === 1"
                                    @click="previousStep"
                                >
                                    <ChevronLeft class="size-4" />
                                    Back
                                </button>

                                <button
                                    v-if="currentStep < steps.length"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-button bg-hive px-4 py-2 text-sm font-black text-black transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canContinue"
                                    @click="nextStep"
                                >
                                    Continue
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>
                        </div>

                        <DeploySummary
                            :node="selectedNode"
                            :allocation="selectedAllocation"
                            :additional-allocations="selectedAdditionalAllocations"
                            :name="form.name"
                            :comb="selectedComb ? `${selectedComb.game} / ${selectedComb.name}` : ''"
                            :version="form.version"
                            :owner-email="form.owner_email"
                            :memory-mb="form.memory_mb"
                            :disk-mb="form.disk_mb"
                            :cpu-percent="form.cpu_percent"
                            :docker-image="form.docker_image"
                            :startup-command="form.startup_command"
                            :processing="form.processing"
                            :can-submit="canSubmit"
                        />
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>