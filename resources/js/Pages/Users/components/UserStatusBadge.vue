<script setup lang="ts">
import { computed } from 'vue';
import Tag from '@/volt/Tag.vue';

/**
 * One status badge for a managed account. Deleted wins over unverified: the
 * account state is what the restore action operates on, and an unverified flag
 * on a deleted row would be noise.
 */

type TagSeverity = 'success' | 'warn' | 'danger';

interface Status {
    label: string;
    severity: TagSeverity;
}

const props = defineProps<{
    deletedAt: string | null;
    emailVerified: boolean;
}>();

const status = computed<Status>(() => {
    if (props.deletedAt !== null) {
        return { label: 'Deleted', severity: 'danger' };
    }

    if (!props.emailVerified) {
        return { label: 'Unverified', severity: 'warn' };
    }

    return { label: 'Active', severity: 'success' };
});
</script>

<template>
    <Tag :severity="status.severity">{{ status.label }}</Tag>
</template>
