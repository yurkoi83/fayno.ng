<template>
  <div class="flex items-center justify-between border-b p-4 last:border-b-0">
    <div class="flex items-center gap-4">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-900"
      >
        <Icon name="key" class="h-5 w-5 text-muted-foreground" />
      </div>

      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <p class="font-medium tracking-tight">{{ passkey.name }}</p>

          <span
            v-if="passkey.authenticator"
            class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset"
          >
            {{ passkey.authenticator }}
          </span>
        </div>

        <p class="text-sm text-muted-foreground">
          Added {{ passkey.created_at_diff }}
          <template v-if="passkey.last_used_at_diff">
            <span class="mx-1 text-muted-foreground/50">/</span>
            Last used {{ passkey.last_used_at_diff }}
          </template>
        </p>
      </div>
    </div>

    <div class="inline-flex justify-end">
      <ConfirmsPassword
        mode="always"
        @confirmed="deletePasskey"
        button-state="danger"
      >
        <Button variant="ghost" state="danger">
          <Icon name="trash" type="micro" class="h-4 w-4" />
          <span class="sr-only">Remove</span>
        </Button>
      </ConfirmsPassword>
    </div>
  </div>
</template>

<script setup>
import { Button, Icon } from 'laravel-nova-ui'
import { ref } from 'vue'
import { useLocalization } from '@/composables/useLocalization'

const props = defineProps({
  passkey: { type: Object, required: true },
})

const emit = defineEmits(['removed'])

const isDeleting = ref(false)

const { __ } = useLocalization()

function deletePasskey() {
  isDeleting.value = true

  emit('removed', props.passkey.id, () => {
    isDeleting.value = false
  })
}
</script>
