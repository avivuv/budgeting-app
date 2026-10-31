<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { LogOut, Mail, Trash2, UserMinus } from '@lucide/vue';
import MemberController from '@/actions/App/Http/Controllers/Web/Family/MemberController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/members';

type Member = {
    id: number;
    name: string;
    email: string;
    role: App.Enums.FamilyRole;
    roleLabel: string;
    joined: string;
    isSelf: boolean;
};

type PendingInvitation = {
    id: number;
    email: string;
    roleLabel: string;
    expires: string;
    expired: boolean;
    inviter: string | null;
};

defineProps<{
    members: Member[];
    invitations: PendingInvitation[];
    roles: { value: App.Enums.FamilyRole; label: string }[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Anggota', href: index() }],
    },
});

function changeRole(member: Member, role: string) {
    router.patch(
        MemberController.updateRole.url(member.id),
        { role },
        { preserveScroll: true },
    );
}

function remove(member: Member) {
    if (
        confirm(
            `Keluarkan ${member.name} dari keluarga? Transaksinya tetap tersimpan.`,
        )
    ) {
        router.delete(MemberController.remove.url(member.id), {
            preserveScroll: true,
        });
    }
}

function revoke(invitation: PendingInvitation) {
    router.delete(MemberController.revokeInvitation.url(invitation.id), {
        preserveScroll: true,
    });
}

function leave() {
    if (confirm('Keluar dari keluarga ini?')) {
        router.post(MemberController.leave.url());
    }
}
</script>

<template>
    <Head title="Anggota" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Anggota keluarga"
            description="Semua anggota mencatat ke buku yang sama. Peran menentukan apa yang boleh dilakukan."
        />

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <Card>
                <CardHeader>
                    <CardTitle>{{ members.length }} anggota</CardTitle>
                </CardHeader>
                <CardContent class="divide-y">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="flex flex-wrap items-center gap-3 py-3"
                    >
                        <div
                            class="flex size-9 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary"
                        >
                            {{ member.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ member.name }}
                                <span
                                    v-if="member.isSelf"
                                    class="text-xs text-muted-foreground"
                                    >(kamu)</span
                                >
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ member.email }} · bergabung
                                {{ member.joined }}
                            </p>
                        </div>

                        <NativeSelect
                            v-if="
                                canManage &&
                                member.role !== 'owner' &&
                                !member.isSelf
                            "
                            class="w-36"
                            :model-value="member.role"
                            aria-label="Peran"
                            @update:model-value="
                                changeRole(member, String($event))
                            "
                        >
                            <option
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </NativeSelect>
                        <Badge v-else variant="secondary">
                            {{ member.roleLabel }}
                        </Badge>

                        <Button
                            v-if="
                                canManage &&
                                member.role !== 'owner' &&
                                !member.isSelf
                            "
                            variant="ghost"
                            size="icon"
                            class="size-8 text-muted-foreground hover:text-destructive"
                            aria-label="Keluarkan"
                            @click="remove(member)"
                        >
                            <UserMinus class="size-4" />
                        </Button>
                        <Button
                            v-if="member.isSelf && member.role !== 'owner'"
                            variant="outline"
                            size="sm"
                            @click="leave"
                        >
                            <LogOut class="size-4" /> Keluar
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div v-if="canManage" class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Undang anggota</CardTitle>
                        <CardDescription>
                            Link undangan dikirim lewat email dan berlaku 7
                            hari.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            v-bind="MemberController.invite.form()"
                            class="grid gap-4"
                            :reset-on-success="['email']"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <Label for="invite-email">Email</Label>
                                <Input
                                    id="invite-email"
                                    name="email"
                                    type="email"
                                    placeholder="pasangan@email.com"
                                    required
                                />
                                <InputError :message="errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="invite-role">Peran</Label>
                                <NativeSelect id="invite-role" name="role">
                                    <option
                                        v-for="role in roles"
                                        :key="role.value"
                                        :value="role.value"
                                        :selected="role.value === 'member'"
                                    >
                                        {{ role.label }}
                                    </option>
                                </NativeSelect>
                                <InputError
                                    :message="errors.role ?? errors.general"
                                />
                            </div>
                            <Button type="submit" :disabled="processing">
                                <Spinner v-if="processing" />
                                <Mail v-else class="size-4" />
                                Kirim undangan
                            </Button>
                        </Form>
                    </CardContent>
                </Card>

                <Card v-if="invitations.length">
                    <CardHeader>
                        <CardTitle>Menunggu konfirmasi</CardTitle>
                    </CardHeader>
                    <CardContent class="divide-y">
                        <div
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="flex items-center gap-2 py-2"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ invitation.email }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ invitation.roleLabel }} ·
                                    <span
                                        :class="{
                                            'text-expense': invitation.expired,
                                        }"
                                    >
                                        {{
                                            invitation.expired
                                                ? 'kedaluwarsa'
                                                : `s.d. ${invitation.expires}`
                                        }}
                                    </span>
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-destructive"
                                aria-label="Batalkan undangan"
                                @click="revoke(invitation)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div
                    class="rounded-lg border p-4 text-xs text-muted-foreground"
                >
                    <p class="mb-1 font-semibold text-foreground">Peran</p>
                    <p><b>Admin</b>: kelola dompet, kategori, anggota.</p>
                    <p>
                        <b>Anggota</b>: catat transaksi, ubah transaksi sendiri.
                    </p>
                    <p><b>Hanya lihat</b>: melihat saldo dan laporan.</p>
                </div>
            </div>
        </div>
    </div>
</template>
