<template>
  <div v-if="isSupported && fortifyFeatures.includes('passkeys')">
    <div class="grid gap-2">
      <Button
        :loading="isLoading"
        variant="outline"
        icon="key"
        size="small"
        class="!h-9 !font-extrabold"
        @click="verify"
      >
        <slot name="default" :loading="isLoading">
          {{
            isLoading ? __('Authenticating...') : __('Sign in with a passkey')
          }}
        </slot>
      </Button>
    </div>

    <Separator>
      <slot name="separator" />
    </Separator>
  </div>
</template>

<script setup>
import { Button } from 'laravel-nova-ui'
import { computed } from 'vue'
import { usePasskeyVerify } from '@laravel/passkeys/vue'
import { useLocalization } from '@/composables/useLocalization'

const { __ } = useLocalization()

const props = defineProps({
  type: {
    type: String,
    validator(value) {
      return ['login', 'confirm'].includes(value)
    },
  },
})

const emitter = defineEmits(['confirmed'])

const optionsUrl = computed(() => {
  if (props.type === 'login') {
    return Nova.url('/user-security/passkeys/login/options')
  }

  return Nova.url('/user-security/passkeys/confirm/options')
})

const submitUrl = computed(() => {
  if (props.type === 'login') {
    return Nova.url('/user-security/passkeys/login')
  }

  return Nova.url('/user-security/passkeys/confirm')
})

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
  routes: {
    options: optionsUrl.value,
    submit: submitUrl.value,
  },
  onSuccess: response => {
    if (props.type === 'login') {
      Nova.visit({ url: response.redirect ?? Nova.url('/'), remote: true })
    } else if (props.type == 'confirm') {
      emitter('confirmed')
    }
  },
  onError: response => {
    Nova.$toasted.show(response.message, {
      duration: null,
      type: 'error',
    })
  },
})

const fortifyFeatures = computed(() => Nova.config('fortifyFeatures'))
</script>
