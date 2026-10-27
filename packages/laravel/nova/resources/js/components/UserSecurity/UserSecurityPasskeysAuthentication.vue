<template>
  <div class="mt-10 sm:mt-0 mb-6">
    <div class="md:grid md:grid-cols-3 md:gap-6">
      <div class="md:col-span-1 flex justify-between">
        <div class="px-4 sm:px-0">
          <Heading :level="3" v-text="__('Passkeys')" />
          <p class="my-3 text-sm text-gray-600">
            {{ __('Manage your passkeys for passwordless sign-in.') }}
          </p>
        </div>
      </div>
      <Card class="md:col-span-2 p-6">
        <div class="grid grid-cols-6 gap-6">
          <div class="col-span-full sm:col-span-4">
            <Heading
              :level="4"
              class="text-medium font-bold"
              v-text="
                __(
                  hasPasskeys
                    ? 'You have enabled passkeys.'
                    : 'You have not enabled passkeys.'
                )
              "
            />
            <p class="mt-6 mb-4 text-sm text-gray-600">
              {{
                __(
                  hasPasskeys
                    ? 'Your account is now secured. You can bypass passwords and verification codes on this device by signing in with your face, fingerprint, or PIN'
                    : "When passkeys are enabled, you will be prompted to authenticate securely using your device's biometric authentication (such as fingerprint or facial recognition) or screen lock PIN."
                )
              }}
            </p>

            <div v-if="!isSupported" class="text-sm text-muted-foreground">
              {{ __('Passkeys are not supported in this browser.') }}
            </div>

            <template v-if="isSupported && !formModal">
              <ConfirmsPassword
                :required="requiresConfirmPassword"
                @confirmed="showModal"
              >
                <Button
                  :label="__('Add Passkey')"
                  class="inline-flex items-center me-3"
                />
              </ConfirmsPassword>
            </template>

            <CreatePasskeyModal
              :show="isSupported && formModal"
              @created="passkeyCreated"
              @close="hideModal"
            />
          </div>
        </div>

        <Separator v-if="hasPasskeys" />

        <PasskeyRow
          v-for="passkey in passkeys"
          :key="passkey.id"
          :passkey="passkey"
          @removed="passkeyDeleted"
        />
      </Card>
    </div>
  </div>
</template>

<script></script>

<script setup>
import { Button, Icon } from 'laravel-nova-ui'
import { computed, reactive, ref } from 'vue'
import { usePasskeyRegister } from '@laravel/passkeys/vue'
import { useLocalization } from '@/composables/useLocalization'

defineOptions({
  name: 'UserSecurityPasskeysAuthentication',
})

const { __ } = useLocalization()

const props = defineProps({
  options: { type: Object, required: true },
  user: { type: Object, required: true },
  passkeys: { type: Array, required: true },
})

const formModal = ref(false)

const { isSupported } = usePasskeyRegister()

const hasPasskeys = computed(() => props.passkeys.length > 0)
const requiresConfirmPassword = computed(
  () => props.options?.confirmsPassword ?? false
)

function showModal() {
  formModal.value = true
}

function hideModal() {
  formModal.value = false
}

function passkeyCreated() {
  hideModal()
  Nova.$router.reload({
    only: ['passkeys'],
  })
}

function passkeyDeleted(id, onError = null) {
  Nova.$router.delete(Nova.url(`/user-security/passkeys/delete/${id}`), {
    preserveScroll: true,
    onError,
  })
}
</script>
