<template>
  <Modal
    :show="show"
    @close-via-escape="handlePreventModalAbandonmentOnClose"
    role="dialog"
    size="2xl"
    modal-style="window"
    :use-focus-trap="show"
  >
    <form
      ref="theForm"
      autocomplete="off"
      @submit.prevent.stop="submit"
      class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden space-y-6"
    >
      <div class="space-y-6">
        <ModalHeader v-text="__('Create Passkey')" />

        <div class="px-8">
          <p class="mb-4">
            {{
              __(
                'To create a new passkey, please follow the instructions provided by your device.'
              )
            }}
          </p>
        </div>

        <div class="px-8 mb-6">
          <input
            v-model="form.name"
            ref="passkeyInput"
            class="form-control form-input form-control-bordered w-full"
            :class="{
              'form-control-bordered-error': form.errors.has('name'),
            }"
            :placeholder="__('Passkey Name')"
            type="text"
            name="name"
            :disabled="!show"
            required=""
          />

          <HelpText class="mt-2 text-red-500" v-if="form.errors.has('name')">
            {{ form.errors.first('name') }}
          </HelpText>

          <HelpText class="mt-2 text-red-500" v-if="error?.length > 0">
            {{ error }}
          </HelpText>
        </div>
      </div>

      <ModalFooter>
        <div class="flex items-center ml-auto">
          <Button
            variant="link"
            state="mellow"
            @click="handleClose"
            dusk="cancel-confirm-password-button"
            class="ml-auto mr-3"
          >
            {{ __('Cancel') }}
          </Button>

          <Button
            type="submit"
            :label="__('Add Passkey')"
            class="inline-flex items-center me-3"
          />
        </div>
      </ModalFooter>
    </form>
  </Modal>
</template>

<script>
import { Button } from 'laravel-nova-ui'
import { defineEmits } from 'vue'
import { PreventsModalAbandonment } from '@/mixins'
import { usePasskeyRegister } from '@laravel/passkeys/vue'

const browserName = () => {
  const userAgent = navigator.userAgent

  const browser = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'].find(
    browser => new RegExp(browser).test(userAgent)
  )

  const operatingSystem = ['iPhone', 'iPad', 'Android', 'Mac', 'Windows'].find(
    os => new RegExp(os).test(userAgent)
  )

  return [browser, operatingSystem].filter(Boolean).join(' on ') || ''
}

export default {
  components: {
    Button,
  },

  emits: ['created', 'close'],

  mixins: [PreventsModalAbandonment],

  props: {
    show: { type: Boolean, default: false },
  },

  data: () => ({
    form: Nova.form({
      name: browserName(),
    }),
  }),

  setup(props, { emit }) {
    const { register, isLoading, error } = usePasskeyRegister({
      routes: {
        options: Nova.url('/user-security/passkeys/register/options'),
        submit: Nova.url('/user-security/passkeys/register'),
      },
      onSuccess: () => {
        emit('created')
      },
    })

    return { register, isLoading, error }
  },

  methods: {
    async submit() {
      await this.register(this.form.name)
    },

    handleClose() {
      this.$emit('close')
      this.form.name = browserName()
    },

    handlePreventModalAbandonmentOnClose(event) {
      this.handlePreventModalAbandonment(
        () => {
          this.handleClose()
        },
        () => {
          event.stopPropagation()
        }
      )
    },
  },
}
</script>
