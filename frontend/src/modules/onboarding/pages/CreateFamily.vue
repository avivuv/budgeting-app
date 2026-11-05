<script setup lang="ts">
import { useQueryClient } from '@tanstack/vue-query';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { useApiForm } from '@/core/api';
import InputError from '@/core/components/form/InputError.vue';
import { Button } from '@/core/components/ui/button';
import { Input } from '@/core/components/ui/input';
import { Label } from '@/core/components/ui/label';
import { Spinner } from '@/core/components/ui/spinner';
import { useSession } from '@/core/stores/session';
import { createFamily } from '../api';

const session = useSession();
const router = useRouter();
const queryClient = useQueryClient();

// Only a starting suggestion for the name field.
const lastName = session.user?.name.trim().split(/\s+/u).pop() ?? '';
const form = useApiForm({
    name: lastName ? `Keluarga ${lastName}` : '',
    period_start_day: 1,
});

const days = Array.from({ length: 28 }, (_, i) => i + 1);

async function submit(): Promise<void> {
    const response = await form.submit((values) => createFamily({ ...values }));

    if (response) {
        session.setUser(response.data);
        queryClient.clear();
        toast.success(response.message);
        await router.push({ name: 'home' });
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">Nama keluarga</Label>
            <Input
                id="name"
                v-model="form.values.name"
                required
                autofocus
                maxlength="100"
            />
            <InputError :message="form.errors.value.name" />
        </div>

        <div class="grid gap-2">
            <Label for="period_start_day">Tanggal awal periode bulanan</Label>
            <select
                id="period_start_day"
                v-model.number="form.values.period_start_day"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <option v-for="day in days" :key="day" :value="day">
                    Tanggal {{ day }}
                </option>
            </select>
            <p class="text-xs text-muted-foreground">
                Mis. tanggal gajian. Laporan bulanan dihitung mulai tanggal ini.
            </p>
            <InputError :message="form.errors.value.period_start_day" />
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing.value">
            <Spinner v-if="form.processing.value" />
            Buat keluarga
        </Button>
    </form>
</template>
