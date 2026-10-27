<template>
  <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
    <Head :title="__('User Security')" />

    <div class="mb-10">
      <Heading :level="1" v-text="__('User Security')" />
    </div>

    <div>
      <UserSecurityUpdatePasswords
        v-if="features.includes('update-passwords')"
        :user="user"
      />

      <DividerLine
        v-if="
          features.includes('update-passwords') &&
          features.includes('two-factor-authentication')
        "
      />

      <UserSecurityTwoFactorAuthentication
        v-if="features.includes('two-factor-authentication')"
        :options="options['two-factor-authentication'] ?? {}"
        :user="user"
      />

      <DividerLine
        v-if="
          (features.includes('update-passwords') ||
            features.includes('two-factor-authentication')) &&
          features.includes('passkeys')
        "
      />

      <UserSecurityPasskeysAuthentication
        v-if="features.includes('passkeys')"
        :options="options['passkeys'] ?? {}"
        :user="user"
        :passkeys="passkeys"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'UserSecurity',
})

defineProps({
  options: { type: Object, required: true },
  user: { type: Object, required: true },
  passkeys: { type: Array, required: true },
})

const features = computed(() => Nova.config('fortifyFeatures'))
</script>
