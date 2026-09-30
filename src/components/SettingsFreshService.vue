<!--
 - SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->


<script setup lang="ts">
import type { Service } from '../types/Service.ts'

import { translate as t } from '@nextcloud/l10n'
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CheckIcon from 'vue-material-design-icons/Check.vue'

const props = defineProps<{
	service: Service
	busy?: boolean
}>()

const emit = defineEmits<{
	(event: 'connect', service: Service): void
}>()

const configureManually = ref(false)
const editableService = ref<Service>({ ...props.service })

watch(() => props.service, (service) => {
	editableService.value = { ...service }
	configureManually.value = false
})

</script>

<template>
	<div class="jmapc-section__fresh">
		<h3 class="title">
			{{ t('integration_jmapc', 'Connection') }}
		</h3>
		<div class="description">
			{{ t('integration_jmapc', 'Enter your server and account information then press connect.') }}
		</div>
		<div class="parameter">
			<label for="jmapc-account-description">
				{{ t('integration_jmapc', 'Account description') }}
			</label>
			<NcTextField id="jmapc-account-description"
				v-model="editableService.label"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:label-outside="true"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Description for this account')" />
		</div>
		<div v-if="editableService.auth === 'BA' || editableService.auth === 'JB'" class="parameter">
			<label for="jmapc-account-bauth-id">
				{{ t('integration_jmapc', 'Account ID') }}
			</label>
			<NcTextField id="jmapc-account-bauth-id"
				v-model="editableService.bauth_id"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Authentication ID for your account')" />
		</div>
		<div v-if="editableService.auth === 'BA' || editableService.auth === 'JB'" class="parameter">
			<label for="jmapc-account-bauth-secret">
				{{ t('integration_jmapc', 'Account secret') }}
			</label>
			<NcPasswordField id="jmapc-account-bauth-secret"
				v-model="editableService.bauth_secret"
				type="password"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Authentication secret for your account')" />
		</div>
		<div v-if="editableService.auth === 'OA'" class="parameter">
			<label for="jmapc-account-oauth-id">
				{{ t('integration_jmapc', 'Account ID') }}
			</label>
			<NcTextField id="jmapc-account-oauth-id"
				v-model="editableService.oauth_id"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Authentication ID for your account')" />
		</div>
		<div v-if="editableService.auth === 'OA'" class="parameter">
			<label for="jmapc-account-oauth-token">
				{{ t('integration_jmapc', 'Account token') }}
			</label>
			<NcPasswordField id="jmapc-account-oauth-token"
				v-model="editableService.oauth_access_token"
				type="password"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Authentication secret for your account')" />
		</div>
		<div class="parameter">
			<label for="jmapc-service-authentication">
				{{ t('integration_jmapc', 'Authentication type') }}
			</label>
			<div class="radio-group">
				<NcCheckboxRadioSwitch v-model="editableService.auth"
					name="service_auth"
					type="radio"
					value="BA"
					button-variant-grouped="horizontal"
					:button-variant="true">
					{{ t('integration_jmapc', 'Basic') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="editableService.auth"
					name="service_auth"
					type="radio"
					value="OA"
					button-variant-grouped="horizontal"
					:button-variant="true">
					{{ t('integration_jmapc', 'OAuth') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="editableService.auth"
					name="service_auth"
					type="radio"
					value="JB"
					button-variant-grouped="horizontal"
					:button-variant="true">
					{{ t('integration_jmapc', 'Json Basic') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="jmapc-service-address">
				{{ t('integration_jmapc', 'Service address') }}
			</label>
			<NcTextField id="jmapc-service-address"
				v-model="editableService.location_host"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Domain or IP address')" />
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="jmapc-service-protocol">
				{{ t('integration_jmapc', 'Service protocol') }}
			</label>
			<div class="radio-group">
				<NcCheckboxRadioSwitch v-model="editableService.location_protocol"
					name="service_protocol"
					type="radio"
					value="http"
					button-variant-grouped="horizontal"
					:button-variant="true">
					{{ t('integration_jmapc', 'http') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="editableService.location_protocol"
					name="service_protocol"
					type="radio"
					value="https"
					button-variant-grouped="horizontal"
					:button-variant="true">
					{{ t('integration_jmapc', 'https') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<div v-if="configureManually" class="parameter">
			<NcCheckboxRadioSwitch v-model="editableService.location_security"
				type="switch">
				{{ t('integration_jmapc', 'Secure Transport Verification (SSL Certificate Verification). Should always be ON, unless connecting to a service over a secure internal network') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="jmapc-service-port">
				{{ t('integration_jmapc', 'Service port') }}
			</label>
			<NcTextField id="jmapc-service-port"
				v-model="editableService.location_port"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Leave empty for default. http (80) https (443)')" />
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="jmapc-service-path">
				{{ t('integration_jmapc', 'Service path') }}
			</label>
			<NcTextField id="jmapc-service-path"
				v-model="editableService.location_path"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_jmapc', 'Leave empty for default path (/.well-known/jmap)')" />
		</div>
		<div>
			<NcCheckboxRadioSwitch v-model="configureManually" type="switch">
				{{ t('integration_jmapc', 'Configure server manually') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div class="actions">
			<NcButton :disabled="busy" @click="emit('connect', { ...editableService })">
				<template #icon>
					<NcLoadingIcon v-if="busy" />
					<CheckIcon v-else />
				</template>
				{{ t('integration_jmapc', 'Connect') }}
			</NcButton>
		</div>
	</div>
</template>

<style scoped lang="scss">
.jmapc-section__fresh {
	.title {
		margin-bottom: 16px;
	}

	.description {
		margin-bottom: 20px;
	}

	.parameter {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 16px;

		label {
			min-width: 200px;
			font-weight: 500;
		}

		.radio-group {
			display: flex;
		}

	}

	.actions {
		display: flex;
		gap: 12px;
		margin-top: 24px;
		padding-top: 20px;
		border-top: 1px solid var(--color-border);
	}
}
</style>
