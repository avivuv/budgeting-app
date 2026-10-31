<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import OnboardingController from '@/actions/App/Http/Controllers/Web/Family/OnboardingController';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Buat buku keuangan keluarga',
        description:
            'Satu keluarga, satu buku. Anggota lain bisa diundang setelah ini.',
    },
});

defineProps<{
    suggestedName: string;
}>();

const days = Array.from({ length: 28 }, (_, index) => index + 1);
</script>

<template>
    <Head title="Buat keluarga" />

    <Form
        v-bind="OnboardingController.store.form()"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">Nama keluarga</Label>
            <Input
                id="name"
                name="name"
                :default-value="suggestedName"
                required
                autofocus
                maxlength="100"
                placeholder="Keluarga Budi"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="period_start_day"
                >Periode bulanan dimulai tanggal</Label
            >
            <NativeSelect id="period_start_day" name="period_start_day">
                <option
                    v-for="day in days"
                    :key="day"
                    :value="day"
                    :selected="day === 1"
                >
                    {{ day === 1 ? '1 (awal bulan)' : day }}
                </option>
            </NativeSelect>
            <p class="text-xs text-muted-foreground">
                Pilih tanggal gajian jika anggaran keluarga dihitung dari gajian
                ke gajian, misalnya 25.
            </p>
            <InputError :message="errors.period_start_day" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Mulai
        </Button>

        <p class="text-center text-xs text-muted-foreground">
            Dompet "Tunai" dan kategori umum (makan, transportasi, tagihan,
            dll.) dibuat otomatis dan bisa diubah kapan saja.
        </p>
    </Form>
</template>
