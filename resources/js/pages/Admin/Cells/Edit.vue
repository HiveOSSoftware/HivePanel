<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    Cpu,
    HardDrive,
    MemoryStick,
    Save,
    Server,
    TriangleAlert,
} from 'lucide-vue-next'

const props = defineProps<{
    cell: any
    editState: {
        status: 'ready' | 'running' | 'unreachable' | 'missing' | 'error'
        editable: boolean
        message: string
    }
    allocations: {
        id: string
        ip: string
        port: number
        alias?: string | null
        label: string
        assigned_to_cell?: boolean
        primary?: boolean
    }[]
}>()

const form = useForm({
    name: props.cell.name ?? '',
    memory_mb: Number(props.cell.limits?.memory_mb ?? 1024),
    cpu_percent: Number(props.cell.limits?.cpu_percent ?? 100),
    disk_mb: Number(props.cell.limits?.disk_mb ?? 0),
    allocation_id: props.cell.allocation?.id ?? '',
    additional_allocation_ids: (props.cell.additional_allocations ?? []).map((allocation: any) => allocation.id),
})


function editStateTitle() {
    switch (props.editState.status) {
        case 'ready':
            return 'Ready to edit'

        case 'running':
            return 'Cell is running'

        case 'unreachable':
            return 'Worker unreachable'

        case 'missing':
            return 'Cell missing from Worker'

        default:
            return 'Editing unavailable'
    }
}

function editStateClass() {
    if (props.editState.status === 'ready') {
        return 'border-status-success/30 bg-status-success/10'
    }

    if (props.editState.status === 'running') {
        return 'border-status-warning/30 bg-status-warning/10'
    }

    return 'border-status-danger/30 bg-status-danger/10'
}

function editStateTextClass() {
    if (props.editState.status === 'ready') {
        return 'text-status-success'
    }

    if (props.editState.status === 'running') {
        return 'text-status-warning'
    }

    return 'text-status-danger'
}

function submit() {
    form.patch(`/admin/cells/${props.cell.id}`, {
        preserveScroll: true,
    })
}

function formatMb(value: number) {
    if (!value) return 'Unlimited'

    if (value >= 1024) {
        return `${(value / 1024).toFixed(value % 1024 === 0 ? 0 : 1)} GB`
    }

    return `${value} MB`
}

function isPrimaryAllocation(id: string) {
    return String(form.allocation_id) === String(id)
}

function toggleAdditionalAllocation(id: string) {
    const value = String(id)

    if (isPrimaryAllocation(value)) {
        form.additional_allocation_ids = form.additional_allocation_ids.filter((allocationId: string) => String(allocationId) !== value)
        return
    }

    if (form.additional_allocation_ids.some((allocationId: string) => String(allocationId) === value)) {
        form.additional_allocation_ids = form.additional_allocation_ids.filter((allocationId: string) => String(allocationId) !== value)
        return
    }

    form.additional_allocation_ids = [
        ...form.additional_allocation_ids,
        value,
    ]
}

function additionalAllocationSelected(id: string) {
    return form.additional_allocation_ids.some((allocationId: string) => String(allocationId) === String(id))
}

function primaryAllocationChanged() {
    const primaryId = String(form.allocation_id)

    form.additional_allocation_ids = form.additional_allocation_ids.filter(
        (allocationId: string) => String(allocationId) !== primaryId,
    )
}
</script>

<template>
    <AppLayout :context="'admin'">
        <Head :title="`Edit ${cell.name}`" />

        <div class="min-h-screen bg-surface-dark text-white">
            <main class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div class="mx-auto space-y-5">
                    <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-button border border-zinc-800 bg-[#0d0f11] text-hive">
                                    <Server class="size-6" />
                                </div>

                                <div>
                                    <h1 class="text-2xl font-black sm:text-3xl">
                                        Edit Cell
                                    </h1>

                                    <p class="mt-2 text-sm text-zinc-400">
                                        Update the Cell name and resource limits stored by HivePanel and the assigned Worker.
                                    </p>
                                </div>
                            </div>

                            <Link
                                :href="`/admin/cells/${cell.id}`"
                                class="inline-flex items-center justify-center gap-2 rounded-button border border-zinc-800 bg-surface-light px-4 py-2 text-sm font-bold text-zinc-300 transition hover:border-hive hover:text-hive"
                            >
                                <ArrowLeft class="size-4" />
                                Back to Cell
                            </Link>
                        </div>
                    </section>

                    <section
                        class="rounded-panel border p-5 sm:p-6"
                        :class="editStateClass()"
                    >
                        <div class="flex items-start gap-3">
                            <TriangleAlert
                                v-if="!editState.editable"
                                class="mt-0.5 size-5 shrink-0"
                                :class="editStateTextClass()"
                            />

                            <Server
                                v-else
                                class="mt-0.5 size-5 shrink-0"
                                :class="editStateTextClass()"
                            />

                            <div>
                                <h2
                                    class="text-sm font-black"
                                    :class="editStateTextClass()"
                                >
                                    {{ editStateTitle() }}
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-zinc-400">
                                    {{ editState.message }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <form class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
                        <fieldset
                            class="contents"
                            :disabled="!editState.editable || form.processing"
                        >
                        <div class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black">
                                        General
                                    </h2>

                                    <p class="mt-1 text-sm text-zinc-500">
                                        Basic identity for this Cell.
                                    </p>
                                </div>

                                <div class="mt-5">
                                    <label for="name" class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Cell Name
                                    </label>

                                    <input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        maxlength="255"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition placeholder:text-zinc-700 focus:border-hive"
                                        :class="{ 'border-status-danger': form.errors.name }"
                                    />

                                    <p v-if="form.errors.name" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.name }}
                                    </p>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div>
                                    <h2 class="text-lg font-black">
                                        Resource Limits
                                    </h2>

                                    <p class="mt-1 text-sm text-zinc-500">
                                        These values become the authoritative HivePanel definition and are pushed to the Worker when saved.
                                    </p>
                                </div>

                                <div class="mt-5 grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label for="memory_mb" class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                            <MemoryStick class="size-4 text-hive" />
                                            Memory
                                        </label>

                                        <div class="relative mt-2">
                                            <input
                                                id="memory_mb"
                                                v-model.number="form.memory_mb"
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-12 text-sm font-bold text-white outline-none transition focus:border-hive"
                                                :class="{ 'border-status-danger': form.errors.memory_mb }"
                                            />
                                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-zinc-600">
                                                MB
                                            </span>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500">
                                            {{ formatMb(Number(form.memory_mb)) }}
                                        </p>

                                        <p v-if="form.errors.memory_mb" class="mt-2 text-xs font-bold text-status-danger">
                                            {{ form.errors.memory_mb }}
                                        </p>
                                    </div>

                                    <div>
                                        <label for="cpu_percent" class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                            <Cpu class="size-4 text-hive" />
                                            CPU
                                        </label>

                                        <div class="relative mt-2">
                                            <input
                                                id="cpu_percent"
                                                v-model.number="form.cpu_percent"
                                                type="number"
                                                min="0"
                                                max="1000"
                                                step="1"
                                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-10 text-sm font-bold text-white outline-none transition focus:border-hive"
                                                :class="{ 'border-status-danger': form.errors.cpu_percent }"
                                            />
                                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-zinc-600">
                                                %
                                            </span>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500">
                                            {{ Number(form.cpu_percent) }}% CPU allocation
                                        </p>

                                        <p v-if="form.errors.cpu_percent" class="mt-2 text-xs font-bold text-status-danger">
                                            {{ form.errors.cpu_percent }}
                                        </p>
                                    </div>

                                    <div>
                                        <label for="disk_mb" class="flex items-center gap-2 text-xs font-black uppercase tracking-wide text-zinc-500">
                                            <HardDrive class="size-4 text-hive" />
                                            Disk
                                        </label>

                                        <div class="relative mt-2">
                                            <input
                                                id="disk_mb"
                                                v-model.number="form.disk_mb"
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 pr-12 text-sm font-bold text-white outline-none transition focus:border-hive"
                                                :class="{ 'border-status-danger': form.errors.disk_mb }"
                                            />
                                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-black text-zinc-600">
                                                MB
                                            </span>
                                        </div>

                                        <p class="mt-2 text-xs text-zinc-500">
                                            {{ formatMb(Number(form.disk_mb)) }}
                                        </p>

                                        <p v-if="form.errors.disk_mb" class="mt-2 text-xs font-bold text-status-danger">
                                            {{ form.errors.disk_mb }}
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            Primary Allocation
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Move this Cell to another available allocation on the same Worker node.
                                        </p>
                                    </div>

                                    <Server class="size-5 shrink-0 text-hive" />
                                </div>

                                <div class="mt-5">
                                    <label for="allocation_id" class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                        Allocation
                                    </label>

                                    <select
                                        id="allocation_id"
                                        v-model="form.allocation_id"
                                        @change="primaryAllocationChanged"
                                        class="mt-2 w-full rounded-button border border-zinc-800 bg-[#0d0f11] px-4 py-3 text-sm font-bold text-white outline-none transition focus:border-hive"
                                        :class="{ 'border-status-danger': form.errors.allocation_id }"
                                    >
                                        <option
                                            v-for="allocation in allocations"
                                            :key="allocation.id"
                                            :value="allocation.id"
                                        >
                                            {{ allocation.label }}
                                        </option>
                                    </select>

                                    <div class="mt-3 rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Current Allocation
                                        </div>

                                        <div class="mt-2 font-mono text-sm font-black text-white">
                                            <template v-if="cell.allocation">
                                                {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                                            </template>
                                            <template v-else>
                                                Missing
                                            </template>
                                        </div>
                                    </div>

                                    <p class="mt-3 text-xs leading-5 text-zinc-500">
                                        Only unreserved allocations on {{ cell.node?.name || 'this node' }} are shown. Changing the primary allocation also updates the stored server IP and server port variables before the Worker definition is reconciled.
                                    </p>

                                    <p v-if="form.errors.allocation_id" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.allocation_id }}
                                    </p>
                                </div>
                            </section>

                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-black">
                                            Additional Allocations
                                        </h2>

                                        <p class="mt-1 text-sm text-zinc-500">
                                            Assign extra IP and port combinations to this Cell. The primary allocation is excluded automatically.
                                        </p>
                                    </div>

                                    <Server class="size-5 shrink-0 text-hive" />
                                </div>

                                <div class="mt-5">
                                    <div
                                        v-if="allocations.length === 0"
                                        class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4 text-sm font-bold text-zinc-500"
                                    >
                                        No allocations are currently available on this node.
                                    </div>

                                    <div
                                        v-else
                                        class="grid gap-3 md:grid-cols-2"
                                    >
                                        <button
                                            v-for="allocation in allocations"
                                            :key="allocation.id"
                                            type="button"
                                            class="flex items-start gap-3 rounded-button border p-4 text-left transition"
                                            :class="[
                                                isPrimaryAllocation(allocation.id)
                                                    ? 'cursor-not-allowed border-hive/30 bg-hive/10 opacity-60'
                                                    : additionalAllocationSelected(allocation.id)
                                                        ? 'border-hive bg-hive/10'
                                                        : 'border-zinc-800 bg-[#0d0f11] hover:border-zinc-700',
                                            ]"
                                            :disabled="isPrimaryAllocation(allocation.id)"
                                            @click="toggleAdditionalAllocation(allocation.id)"
                                        >
                                            <span
                                                class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border text-[10px] font-black"
                                                :class="additionalAllocationSelected(allocation.id)
                                                    ? 'border-hive bg-hive text-black'
                                                    : 'border-zinc-700 bg-surface text-transparent'"
                                            >
                                                ✓
                                            </span>

                                            <span class="min-w-0 flex-1">
                                                <span class="block font-mono text-sm font-black text-white">
                                                    {{ allocation.ip }}:{{ allocation.port }}
                                                </span>

                                                <span
                                                    v-if="allocation.alias"
                                                    class="mt-1 block text-xs font-bold text-zinc-500"
                                                >
                                                    {{ allocation.alias }}
                                                </span>

                                                <span
                                                    v-if="isPrimaryAllocation(allocation.id)"
                                                    class="mt-2 inline-flex rounded-full border border-hive/30 bg-hive/10 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-hive"
                                                >
                                                    Primary
                                                </span>

                                                <span
                                                    v-else-if="allocation.assigned_to_cell"
                                                    class="mt-2 inline-flex rounded-full border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-zinc-400"
                                                >
                                                    Assigned
                                                </span>
                                            </span>
                                        </button>
                                    </div>

                                    <p class="mt-3 text-xs leading-5 text-zinc-500">
                                        Additional allocations may use different IP addresses and may reuse the same port number on different IPs. HivePanel will release any allocations you deselect when the Cell is saved.
                                    </p>

                                    <p v-if="form.errors.additional_allocation_ids" class="mt-2 text-xs font-bold text-status-danger">
                                        {{ form.errors.additional_allocation_ids }}
                                    </p>
                                </div>
                            </section>

                            <section
                                v-if="form.errors.worker"
                                class="rounded-panel border border-status-danger/30 bg-status-danger/10 p-5"
                            >
                                <div class="flex items-start gap-3">
                                    <TriangleAlert class="mt-0.5 size-5 shrink-0 text-status-danger" />

                                    <div>
                                        <h2 class="text-sm font-black text-status-danger">
                                            Worker update failed
                                        </h2>

                                        <p class="mt-1 text-sm leading-6 text-zinc-300">
                                            {{ form.errors.worker }}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-5">
                            <section class="rounded-panel border border-zinc-800 bg-surface p-5 sm:p-6">
                                <h2 class="text-lg font-black">
                                    Current Cell
                                </h2>

                                <div class="mt-5 space-y-3">
                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Node
                                        </div>
                                        <div class="mt-2 text-sm font-black text-white">
                                            {{ cell.node?.name || 'Unknown' }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Allocation
                                        </div>
                                        <div class="mt-2 font-mono text-sm font-black text-white">
                                            <template v-if="cell.allocation">
                                                {{ cell.allocation.ip }}:{{ cell.allocation.port }}
                                            </template>
                                            <template v-else>
                                                Missing
                                            </template>
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Additional Allocations
                                        </div>

                                        <div class="mt-2 text-sm font-black text-white">
                                            {{ form.additional_allocation_ids.length }}
                                        </div>
                                    </div>

                                    <div class="rounded-button border border-zinc-800 bg-[#0d0f11] p-4">
                                        <div class="text-xs font-black uppercase tracking-wide text-zinc-500">
                                            Comb
                                        </div>
                                        <div class="mt-2 text-sm font-black text-white">
                                            {{ cell.comb }}
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-button border border-hive bg-hive px-4 py-3 text-sm font-black text-black transition hover:bg-hive-light disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="form.processing || !editState.editable"
                            >
                                <Save class="size-4" />
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </aside>
                        </fieldset>
                    </form>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
