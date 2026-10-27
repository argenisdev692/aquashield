<script setup lang="ts">
/**
 * The canvas shell for the shield's water moment.
 *
 * Kept separate from the particles so the camera and the renderer settings
 * are readable in one place. `alpha` + `clear-alpha=0` let the navy section
 * show through; `dpr` caps the drawing buffer at 1.5 because a 4k-point
 * cloud has no detail that a 3x buffer would resolve. `low-power` asks a
 * dual-GPU machine for the integrated chip: this is ambience, not a game.
 */
import { TresCanvas } from '@tresjs/core';
import BrandMarkParticles from '@/Components/BrandMarkParticles.vue';

const emit = defineEmits<{ ready: [] }>();
</script>

<template>
    <TresCanvas
        :alpha="true"
        :antialias="false"
        :clear-alpha="0"
        :dpr="[1, 1.5]"
        power-preference="low-power"
        render-mode="always"
        class="h-full w-full"
    >
        <!-- z = 3 / tan(22.5deg) puts a 6-unit view height behind a 4-unit
             mark, so the cloud covers exactly two thirds of the square —
             the same box the poster occupies. -->
        <TresPerspectiveCamera :position="[0, 0, 7.24]" :fov="45" :look-at="[0, 0, 0]" />
        <BrandMarkParticles @ready="emit('ready')" />
    </TresCanvas>
</template>
