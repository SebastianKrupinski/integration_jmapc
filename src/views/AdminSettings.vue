<!--
 - SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref, reactive } from 'vue'
import axios, { type AxiosError } from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

import { showError, showSuccess } from '@nextcloud/dialogs'

import { NcButton, NcLoadingIcon, NcSelect } from '@nextcloud/vue'

import JmapIcon from '../icons/JmapIcon.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

// Types
interface AdminConfigurationState {
	harmonization_mode: 'P' | 'A'
	harmonization_thread_duration: string | number
	harmonization_thread_pause: string | number
}

interface SelectOption {
	label: string
	id: 'P' | 'A'
}

interface SaveRequest {
	values: {
		harmonization_mode: 'P' | 'A'
		harmonization_thread_duration: string | number
		harmonization_thread_pause: string | number
	}
}

// Reactive data
const saving = ref(false)
const state = reactive<AdminConfigurationState>(
	loadState('integration_jmapc', 'admin-configuration') as AdminConfigurationState,
)

// Select options for synchronization mode
const synchronizationModeOptions: SelectOption[] = [
	{ label: t('integration_jmapc', 'Passive'), id: 'P' },
	{ label: t('integration_jmapc', 'Active'), id: 'A' },
]

// Methods
const onSaveClick = async (): Promise<void> => {
	if (saving.value) {
		return
	}
	saving.value = true
	const req: SaveRequest = {
		values: {
			harmonization_mode: state.harmonization_mode,
			harmonization_thread_duration: state.harmonization_thread_duration,
			harmonization_thread_pause: state.harmonization_thread_pause,
		},
	}

	const url = generateUrl('/apps/integration_jmapc/admin-configuration')

	try {
		await axios.put(url, req)
		showSuccess(t('integration_jmapc', 'JMAP admin configuration saved'))
	} catch (error) {
		const axiosError = error as AxiosError
		const errorMessage = axiosError.response?.data
			? String(axiosError.response.data)
			: axiosError.message || 'Unknown error occurred'

		showError(
			t('integration_jmapc', 'Failed to save JMAP admin configuration')
			+ ': ' + errorMessage,
		)
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<div id="jmapc_settings" class="section">
		<div class="jmap-section-heading">
			<JmapIcon :size="32" /><h2> {{ t('integration_jmapc', 'JMAP Connector') }}</h2>
		</div>
		<p class="settings-hint">
			{{ t('integration_jmapc', 'Select the system settings for JMAP Integration') }}
		</p>
		<div class="fields">
			<div>
				<div class="line">
					<label>
						{{ t('integration_jmapc', 'Synchronization Mode') }}
					</label>
					<NcSelect v-model="state.harmonization_mode"
						:reduce="item => item.id"
						:options="synchronizationModeOptions" />
				</div>
				<div v-if="state.harmonization_mode === 'A'" class="line">
					<label>
						{{ t('integration_jmapc', 'Synchronization Thread Duration') }}
					</label>
					<input id="jmap-thread-duration"
						v-model="state.harmonization_thread_duration"
						type="number"
						:autocomplete="'off'"
						:autocorrect="'off'"
						:autocapitalize="'none'">
					<label>
						{{ t('integration_jmapc', 'Seconds') }}
					</label>
				</div>
				<div v-if="state.harmonization_mode === 'A'" class="line">
					<label>
						{{ t('integration_jmapc', 'Synchronization Thread Pause') }}
					</label>
					<input id="jmap-thread-pause"
						v-model="state.harmonization_thread_pause"
						type="number"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none">
					<label>
						{{ t('integration_jmapc', 'Seconds') }}
					</label>
				</div>
			</div>
			<br>
			<div class="jmap-actions">
				<NcButton :disabled="saving" @click="onSaveClick()">
					<template #icon>
						<NcLoadingIcon v-if="saving" />
						<CheckIcon v-else />
					</template>
					{{ t('integration_jmapc', 'Save') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
#jmapc_settings {
	.jmap-section-heading {
		display:inline-block;
		vertical-align:middle;
	}

	.jmap-connected {
		display: flex;
		align-items: center;

		label {
			padding-left: 1em;
			padding-right: 1em;
		}
	}

	.jmap-collectionlist-item {
		display: flex;
		align-items: center;

		label {
			padding-left: 1em;
			padding-right: 1em;
		}
	}

	.jmap-actions {
		display: flex;
		align-items: center;
	}

	.external-label {
		display: flex;
		//width: 100%;
		margin-top: 1rem;
	}

	.external-label label {
		padding-top: 7px;
		padding-right: 14px;
		white-space: nowrap;
	}
}
</style>
