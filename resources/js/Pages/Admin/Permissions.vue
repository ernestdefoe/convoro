<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, computed, ref } from 'vue';
import { t as tr } from '@/lib/i18n';

type Group = { id: number; key: string | null; name: string; color: string; icon: string | null; is_staff: boolean; permissions: string[] | null };
type Perm = { key: string; label: string; category: string; baseline: boolean; scopable: boolean };

const props = defineProps<{
  groups: Group[];
  catalog: Perm[];
  baseline: string[];
  categories: { id: number; name: string; color: string; icon: string | null }[];
}>();

// Local grant state keyed by `${groupId}:${permKey}` — mutated optimistically so
// toggling never flickers while the POST round-trips (the server stays the
// source of truth on the next full page load).
const granted = reactive<Record<string, boolean>>({});
for (const g of props.groups) for (const p of g.permissions ?? []) granted[`${g.id}:${p}`] = true;

const has = (groupId: number, key: string) => !!granted[`${groupId}:${key}`];
function toggle(groupId: number, key: string, on: boolean) {
  granted[`${groupId}:${key}`] = on;
  router.post('/admin/permissions/toggle', { group_id: groupId, key, on }, { preserveScroll: true, preserveState: true });
}

// Global permissions grouped into their catalog categories, order preserved.
const globalByCategory = computed(() => {
  const order: string[] = [];
  const map = new Map<string, Perm[]>();
  for (const p of props.catalog) {
    if (!map.has(p.category)) { map.set(p.category, []); order.push(p.category); }
    map.get(p.category)!.push(p);
  }
  return order.map((category) => ({ category, perms: map.get(category)! }));
});

const scopablePerms = computed(() => props.catalog.filter((p) => p.scopable));
const activeCategory = ref<number | null>(props.categories[0]?.id ?? null);
</script>

<template>
  <Head :title="tr('Admin · Permissions')" />
  <AdminLayout>
    <template #title>{{ tr('Groups & permissions') }}</template>
    <template #subtitle>{{ tr('Grant capabilities to groups — globally or per category.') }}</template>

    <div class="space-y-6">
      <!-- Global matrix -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ tr('Global permissions') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ tr('Baseline abilities are granted to every member and can’t be revoked. Administrators bypass all checks.') }}</p>

        <div v-for="grp in globalByCategory" :key="grp.category" class="mb-5 last:mb-0">
          <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-ink-muted">{{ grp.category }}</div>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr>
                  <th class="w-64 py-2 pr-3 text-left font-semibold text-ink-2">{{ tr('Ability') }}</th>
                  <th v-for="g in groups" :key="g.id" class="px-3 py-2 text-center">
                    <span class="inline-flex items-center gap-1 whitespace-nowrap text-xs font-bold" :style="{ color: g.color }">
                      <i v-if="g.icon" :class="g.icon"></i>{{ g.name }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in grp.perms" :key="p.key" class="border-t border-line">
                  <td class="py-2 pr-3 text-ink">{{ p.label }}</td>
                  <template v-if="p.baseline">
                    <td :colspan="groups.length" class="px-3 py-2 text-center text-xs italic text-ink-muted">
                      {{ tr('Everyone (baseline)') }}
                    </td>
                  </template>
                  <template v-else>
                    <td v-for="g in groups" :key="g.id" class="px-3 py-2 text-center">
                      <input type="checkbox" class="rounded border-line text-indigo-500"
                        :checked="has(g.id, p.key)" @change="toggle(g.id, p.key, ($event.target as HTMLInputElement).checked)" />
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Per-category scoping -->
      <section v-if="categories.length && scopablePerms.length" class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ tr('Per-category permissions') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">
          {{ tr('Checking a box here restricts that ability in the chosen category to the ticked groups (plus admins) — overriding the global setting. Leave every box unchecked to keep the category open to whoever has the global permission.') }}
        </p>

        <div class="mb-4 flex items-center gap-2">
          <label class="text-xs font-semibold text-ink-2">{{ tr('Category') }}</label>
          <select v-model="activeCategory" class="rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500">
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div v-if="activeCategory" class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr>
                <th class="w-64 py-2 pr-3 text-left font-semibold text-ink-2">{{ tr('Ability') }}</th>
                <th v-for="g in groups" :key="g.id" class="px-3 py-2 text-center">
                  <span class="inline-flex items-center gap-1 whitespace-nowrap text-xs font-bold" :style="{ color: g.color }">
                    <i v-if="g.icon" :class="g.icon"></i>{{ g.name }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in scopablePerms" :key="p.key" class="border-t border-line">
                <td class="py-2 pr-3 text-ink">{{ p.label }}</td>
                <td v-for="g in groups" :key="g.id" class="px-3 py-2 text-center">
                  <input type="checkbox" class="rounded border-line text-indigo-500"
                    :checked="has(g.id, `category.${activeCategory}.${p.key}`)"
                    @change="toggle(g.id, `category.${activeCategory}.${p.key}`, ($event.target as HTMLInputElement).checked)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
