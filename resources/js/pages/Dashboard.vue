<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

type CellStatus = 'offline' | 'starting' | 'running' | 'stopping'

type Cell = {
  id: string
  name: string
  comb: string
  status?: CellStatus
  allocation?: {
    ip: string
    port: number
  }
  limits?: {
    memory_mb: number
    cpu_percent: number
    disk_mb?: number
  }
  stats?: {
    cpu?: number
    memory_mb?: number
    disk_bytes?: number
  }
}

type Comb = {
  id: string
  name: string
  game: string
  variables?: Record<string, string>
}

const props = defineProps<{
  cells: Cell[]
  combs: Comb[]
}>()

const search = ref('')
const statusFilter = ref<'all' | CellStatus>('all')
const sort = ref<'created' | 'name'>('created')

const form = useForm({
  name: '',
  comb: 'minecraft-paper',
  memory: 1024,
  version: '26.1.2',
})

const filteredCells = computed(() => {
  let results = [...props.cells]

  if (search.value.trim()) {
    const query = search.value.toLowerCase()

    results = results.filter((cell) => {
      return (
        cell.name.toLowerCase().includes(query) ||
        cell.id.toLowerCase().includes(query) ||
        cell.comb.toLowerCase().includes(query)
      )
    })
  }

  if (statusFilter.value !== 'all') {
    results = results.filter((cell) => normaliseStatus(cell.status) === statusFilter.value)
  }

  if (sort.value === 'name') {
    results.sort((a, b) => a.name.localeCompare(b.name))
  }

  return results
})

function normaliseStatus(status?: string): CellStatus {
  if (status === 'running' || status === 'starting' || status === 'stopping') {
    return status
  }

  return 'offline'
}

function statusDotClass(status?: string) {
  const current = normaliseStatus(status)

  if (current === 'running') {
    return 'bg-green-400 shadow-[0_0_16px_rgba(74,222,128,0.95)]'
  }

  if (current === 'starting' || current === 'stopping') {
    return 'bg-yellow-400 shadow-[0_0_16px_rgba(250,204,21,0.95)]'
  }

  return 'bg-red-400 shadow-[0_0_16px_rgba(248,113,113,0.95)]'
}

function statusBorderClass(status?: string) {
  const current = normaliseStatus(status)

  if (current === 'running') return 'border-l-green-500'
  if (current === 'starting' || current === 'stopping') return 'border-l-yellow-400'

  return 'border-l-red-500'
}

function statusLabel(status?: string) {
  const current = normaliseStatus(status)

  if (current === 'running') return 'Running'
  if (current === 'starting') return 'Starting'
  if (current === 'stopping') return 'Stopping'

  return 'Offline'
}

function formatMemory(cell: Cell) {
  const used = cell.stats?.memory_mb
  const limit = cell.limits?.memory_mb

  if (used && limit) {
    return `${used.toFixed(0)} MB / ${(limit / 1024).toFixed(1)} GB`
  }

  if (limit) {
    return `0 MB / ${(limit / 1024).toFixed(1)} GB`
  }

  return '0 MB'
}

function formatDisk(cell: Cell) {
  const usedBytes = cell.stats?.disk_bytes ?? 0
  const usedGb = usedBytes / 1024 / 1024 / 1024
  const limitGb = cell.limits?.disk_mb ? cell.limits.disk_mb / 1024 : null

  if (limitGb) {
    return `${usedGb.toFixed(2)} GB / ${limitGb.toFixed(0)} GB`
  }

  return `${usedGb.toFixed(2)} GB`
}

function createCell() {
  form.post(route('cells.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      form.comb = 'minecraft-paper'
      form.memory = 1024
      form.version = '26.1.2'
      showCreate.value = false
    },
  })
}

function startCell(id: string) {
  router.post(route('cells.start', id), {}, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['cells'], preserveScroll: true }),
  })
}

function stopCell(id: string) {
  router.post(route('cells.stop', id), {}, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['cells'], preserveScroll: true }),
  })
}
</script>

<template>
  <AppLayout>
    <Head title="Cells" />

    <div class="min-h-screen bg-[#080a0d] px-6 py-8 text-white lg:px-10">
      <div class="mx-auto space-y-8">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-4xl font-black tracking-tight">All Cells</h1>
            <p class="mt-2 text-sm text-zinc-400">
              Manage your game server instances.
            </p>
          </div>
        </div>

        <div class="rounded-2xl border border-zinc-800 bg-[#0d1015]/90 p-4 shadow-2xl">
          <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-sm">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500">⌕</span>
              <input
                v-model="search"
                class="w-full rounded-lg border border-zinc-800 bg-black/30 py-3 pl-10 pr-3 text-sm text-white outline-none transition placeholder:text-zinc-500 focus:border-green-500"
                placeholder="Search cells..."
              />
            </div>

            <div class="flex gap-3">
              <select
                v-model="statusFilter"
                class="rounded-lg border border-zinc-800 bg-black/30 px-4 py-3 text-sm text-zinc-300 outline-none focus:border-green-500"
              >
                <option value="all">All Status</option>
                <option value="running">Running</option>
                <option value="offline">Offline</option>
                <option value="starting">Starting</option>
                <option value="stopping">Stopping</option>
              </select>

              <select
                v-model="sort"
                class="rounded-lg border border-zinc-800 bg-black/30 px-4 py-3 text-sm text-zinc-300 outline-none focus:border-green-500"
              >
                <option value="created">Sort: Created</option>
                <option value="name">Sort: Name</option>
              </select>
            </div>
          </div>

          <div class="space-y-3">
            <div
              v-for="cell in filteredCells"
              :key="cell.id"
              class="group grid gap-5 rounded-xl border border-zinc-800 border-l-4 bg-gradient-to-r from-[#15191f] to-[#101318] p-5 shadow-lg transition hover:border-zinc-700 hover:from-[#1a1f26] md:grid-cols-[1.5fr_0.5fr_0.7fr_0.7fr_0.9fr_1fr] md:items-center"
              :class="statusBorderClass(cell.status)"
            >
              <div class="flex items-center gap-4">
                <div class="hidden rounded-xl bg-black/25 p-3 text-zinc-300 md:block">
                  ▬<br />▬
                </div>

                <div>
                  <div class="flex items-center gap-2">
                    <Link
                      :href="route('cells.show', cell.id)"
                      class="text-lg font-bold text-white transition group-hover:text-yellow-300"
                    >
                      {{ cell.name }}
                    </Link>

                    <span
                      class="h-2.5 w-2.5 rounded-full"
                      :class="statusDotClass(cell.status)"
                    />
                  </div>

                  <div class="mt-1 text-xs text-zinc-500">
                    {{ cell.id }}
                  </div>

                  <div class="mt-1 text-xs text-zinc-400">
                    {{ cell.comb }}
                  </div>
                </div>
              </div>

              <div>
                <div class="text-xs uppercase text-zinc-500">CPU</div>
                <div
                  class="mt-1 font-bold"
                  :class="(cell.stats?.cpu ?? 0) > 10 ? 'text-yellow-300' : 'text-green-400'"
                >
                  {{ (cell.stats?.cpu ?? 0).toFixed(2) }}%
                </div>
              </div>

              <div>
                <div class="text-xs uppercase text-zinc-500">RAM</div>
                <div class="mt-1 font-bold text-blue-400">
                  {{ formatMemory(cell) }}
                </div>
              </div>

              <div>
                <div class="text-xs uppercase text-zinc-500">Disk</div>
                <div class="mt-1 font-bold text-white">
                  {{ formatDisk(cell) }}
                </div>
              </div>

              <div>
                <div class="inline-flex items-center rounded-lg border border-zinc-800 bg-black/30 px-4 py-3 text-sm text-zinc-300">
                  🌐
                  <span class="ml-2">
                    {{ cell.allocation?.ip ?? '0.0.0.0' }}:{{ cell.allocation?.port ?? '—' }}
                  </span>
                </div>
              </div>

              <div class="flex justify-end gap-2">
                <button
                  class="rounded-lg bg-green-500 px-4 py-2 font-medium text-black transition hover:bg-green-400 disabled:cursor-not-allowed disabled:bg-zinc-800 disabled:text-zinc-500"
                  :disabled="normaliseStatus(cell.status) === 'running' || normaliseStatus(cell.status) === 'starting'"
                  @click="startCell(cell.id)"
                >
                  Start
                </button>

                <button
                  class="rounded-lg bg-red-500 px-4 py-2 font-medium text-white transition hover:bg-red-400 disabled:cursor-not-allowed disabled:bg-zinc-900 disabled:text-zinc-600"
                  :disabled="normaliseStatus(cell.status) === 'offline' || normaliseStatus(cell.status) === 'stopping'"
                  @click="stopCell(cell.id)"
                >
                  Stop
                </button>

                <Link
                  :href="route('cells.show', cell.id)"
                  class="rounded-lg border border-zinc-800 bg-black/30 px-4 py-2 text-zinc-300 transition hover:border-zinc-600 hover:text-white"
                >
                  ⋮
                </Link>
              </div>
            </div>

            <div v-if="filteredCells.length === 0" class="rounded-xl border border-dashed border-zinc-800 py-16 text-center text-zinc-500">
              No cells found.
            </div>
          </div>

          <div class="mt-5 text-sm text-zinc-500">
            Showing {{ filteredCells.length }} of {{ cells.length }} cells
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>