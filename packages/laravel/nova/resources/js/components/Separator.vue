<template>
  <div class="relative my-6">
    <div class="absolute inset-0 flex items-center">
      <div
        :data-orientation="orientation"
        role="none"
        data-slot="separator"
        class="bg-gray-200 dark:bg-gray-600 shrink-0 data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px w-full"
      >
        &nbsp;
      </div>
    </div>

    <div class="relative flex justify-center">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  orientation: {
    type: String,
    default: 'horizontal',
    validator(value) {
      return ['horizontal', 'vertical'].includes(value)
    },
  },
})
</script>
