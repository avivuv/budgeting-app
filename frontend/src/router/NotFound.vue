<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { Button } from '@/core/components/ui/button';
</script>

<template>
    <div class="flex flex-col items-center gap-4 text-center">
        <p class="text-sm text-muted-foreground">
            Halaman yang kamu buka tidak ada atau sudah dipindah.
        </p>
        <Button as-child>
            <RouterLink :to="{ name: 'home' }">Ke beranda</RouterLink>
        </Button>
    </div>
</template>
