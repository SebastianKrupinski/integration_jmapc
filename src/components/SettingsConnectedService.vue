<!--
 - SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Collection } from '../types/Collection.ts'
import type { SystemConfiguration } from '../types/SystemConfiguration.ts'
import type { Service } from '../types/Service.ts'

import { translate as t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContactIcon from 'vue-material-design-icons/ContactsOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import JmapIcon from '../icons/JmapIcon.vue'

defineProps<{
	service: Service
	busy: boolean
	systemConfiguration: SystemConfiguration
	contactsRemoteSupported: boolean
	contactsRemoteCollections: Collection[]
	contactsLocalCollections: Collection[]
	eventsRemoteSupported: boolean
	eventsRemoteCollections: Collection[]
	eventsLocalCollections: Collection[]
	mailRemoteSupported: boolean
	tasksRemoteSupported: boolean
	tasksRemoteCollections: Collection[]
	tasksLocalCollections: Collection[]
	formatDate: (dt: number | undefined) => string
	changeContactCorrelation: (rcid: string | null, enabled: boolean) => void
	changeTaskCorrelation: (rcid: string | null, enabled: boolean) => void
	changeEventCorrelation: (rcid: string | null, enabled: boolean) => void
}>()

const emit = defineEmits<{
	(event: 'save'): void
	(event: 'harmonize'): void
	(event: 'disconnect'): void
}>()

function randomColor(): string {
	return '#' + (Math.random() * 0xFFFFFF << 0).toString(16).padStart(6, '0')
}

const selectedcolor = ref('')
const color = computed({
	get() {
		return selectedcolor.value || randomColor()
	},
	set(value: string) {
		selectedcolor.value = value
	},
})

function establishedCorrelation(collections: Collection[], rcid: string | null): boolean {
	const collection = collections.find((item) => String(item.ccid) === String(rcid))
	return collection ? collection.enabled ?? true : false
}

function correlationColor(collections: Collection[], rcid: string | null): string {
	return collections.find((item) => String(item.ccid) === String(rcid))?.color || randomColor()
}

function correlationHarmonized(collections: Collection[], rcid: string | null): number {
	return collections.find((item) => String(item.ccid) === String(rcid))?.hlockhb || 0
}
</script>

<template>
	<div class="jmapc-section__connected">
		<div class="connection-status">
			<h3 class="connection-status__title">
				{{ t('integration_jmapc', 'Connection') }}
			</h3>
			<div class="connection-status__overview">
				<JmapIcon />
				<span>{{ t('integration_jmapc', 'Connected as {0} to {1}', {0: service.address_primary || '', 1: service.location_host || ''}) }}</span>
			</div>
			<div class="connection-status__harmonization">
				{{ t('integration_jmapc', 'Synchronization was last started on {0} and finished on {1}', {0: formatDate(service.harmonization_start), 1: formatDate(service.harmonization_end)}) }}
			</div>
		</div>
		<div class="connection-correlations-mail">
			<h3>{{ t('integration_jmapc', 'Mail') }}</h3>
			<div v-if="!systemConfiguration.system_mail" class="warning-message">
				{{ t('integration_jmapc', 'The mail app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
			</div>
			<div v-if="!mailRemoteSupported" class="warning-message">
				{{ t('integration_jmapc', 'The connected service does not support mail') }}
			</div>
			<div v-if="systemConfiguration.system_mail && mailRemoteSupported" class="info-message">
				<div>
					{{ t('integration_jmapc', 'The connected service supports mail, but mail integration is currently limited') }}
				</div>
			</div>
		</div>
		<div class="connection-correlations-contacts">
			<h3>{{ t('integration_jmapc', 'Contacts') }}</h3>
			<div v-if="systemConfiguration.system_contacts && contactsRemoteSupported" class="instruction-message">
				{{ t('integration_jmapc', 'Select the contacts collection(s) you wish to synchronize by using the toggle') }}
			</div>
			<div v-if="!systemConfiguration.system_contacts" class="warning-message">
				{{ t('integration_jmapc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
			</div>
			<div v-if="!contactsRemoteSupported" class="warning-message">
				{{ t('integration_jmapc', 'The connected service does not support contacts') }}
			</div>
			<div v-if="systemConfiguration.system_contacts && contactsRemoteSupported" class="collections-list">
				<ul v-if="contactsRemoteCollections.length > 0">
					<li v-for="ritem in contactsRemoteCollections" :key="ritem.id ?? ritem.ccid" class="collections-list-item">
						<NcCheckboxRadioSwitch type="switch"
							:disabled="busy"
							:model-value="establishedCorrelation(contactsLocalCollections, ritem.id)"
							@update:modelValue="changeContactCorrelation(ritem.id, $event)" />
						<ContactIcon :inline="true" :style="{ color: correlationColor(contactsLocalCollections, ritem.id) }" />
						<label>
							{{ ritem.label }}
						</label>
						<label v-if="ritem.count && ritem.count > 0">
							({{ ritem.count }} {{ t('integration_jmapc', 'Contacts') }})
						</label>
						<label v-if="correlationHarmonized(contactsLocalCollections, ritem.id) > 0">
							{{ t('integration_jmapc', 'Last Harmonized {0}', {0: formatDate(correlationHarmonized(contactsLocalCollections, ritem.id))}) }}
						</label>
						<label v-else>
							{{ t('integration_jmapc', 'Never harmonized') }}
						</label>
					</li>
				</ul>
				<div v-else-if="contactsRemoteCollections.length === 0" class="empty-message">
					{{ t('integration_jmapc', 'No contacts collections were found in the connected account') }}
				</div>
				<div v-else class="loading-message">
					{{ t('integration_jmapc', 'Loading contacts collections from the connected account') }}
				</div>
			</div>
		</div>
		<div class="connection-correlations-events">
			<h3>{{ t('integration_jmapc', 'Calendars') }}</h3>
			<div v-if="systemConfiguration.system_events && eventsRemoteSupported" class="instruction-message">
				{{ t('integration_jmapc', 'Select the events collection(s) you wish to synchronize by using the toggle') }}
			</div>
			<div v-if="!systemConfiguration.system_events" class="warning-message">
				{{ t('integration_jmapc', 'The calendar app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
			</div>
			<div v-if="!eventsRemoteSupported" class="warning-message">
				{{ t('integration_jmapc', 'The connected service does not support events') }}
			</div>
			<div v-if="systemConfiguration.system_events && eventsRemoteSupported" class="collections-list">
				<ul v-if="eventsRemoteCollections.length > 0">
					<li v-for="ritem in eventsRemoteCollections" :key="ritem.id ?? ritem.ccid" class="collections-list-item">
						<NcCheckboxRadioSwitch type="switch"
							:disabled="busy"
							:model-value="establishedCorrelation(eventsLocalCollections, ritem.id)"
							@update:modelValue="changeEventCorrelation(ritem.id, $event)" />
						<NcColorPicker v-model="color" :advanced-fields="true">
							<CalendarIcon :inline="true" :style="{ color: correlationColor(eventsLocalCollections, ritem.id) }" />
						</NcColorPicker>
						<label>
							{{ ritem.label }}
						</label>
						<label v-if="ritem.count && ritem.count > 0">
							({{ ritem.count }} {{ t('integration_jmapc', 'Events') }})
						</label>
						<label v-if="correlationHarmonized(eventsLocalCollections, ritem.id) > 0">
							{{ t('integration_jmapc', 'Last Harmonized {0}', {0: formatDate(correlationHarmonized(eventsLocalCollections, ritem.id))}) }}
						</label>
						<label v-else>
							{{ t('integration_jmapc', 'Never harmonized') }}
						</label>
					</li>
				</ul>
				<div v-else-if="eventsRemoteCollections.length === 0" class="empty-message">
					{{ t('integration_jmapc', 'No events collections were found in the connected account') }}
				</div>
				<div v-else class="loading-message">
					{{ t('integration_jmapc', 'Loading events collections from the connected account') }}
				</div>
			</div>
		</div>
		<div class="connection-correlations-tasks">
			<h3>{{ t('integration_jmapc', 'Tasks') }}</h3>
			<div v-if="systemConfiguration.system_tasks && tasksRemoteSupported" class="instruction-message">
				{{ t('integration_jmapc', 'Select the tasks collection(s) you wish to synchronize by using the toggle') }}
			</div>
			<div v-if="!systemConfiguration.system_tasks" class="warning-message">
				{{ t('integration_jmapc', 'The tasks app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
			</div>
			<div v-if="!tasksRemoteSupported" class="warning-message">
				{{ t('integration_jmapc', 'The connected service does not support tasks') }}
			</div>
			<div v-if="systemConfiguration.system_tasks && tasksRemoteSupported" class="collections-list">
				<ul v-if="tasksRemoteCollections.length > 0">
					<li v-for="ritem in tasksRemoteCollections" :key="ritem.id ?? ritem.ccid" class="collections-list-item">
						<NcCheckboxRadioSwitch type="switch"
							:disabled="busy"
							:model-value="establishedCorrelation(tasksLocalCollections, ritem.id)"
							@update:modelValue="changeTaskCorrelation(ritem.id, $event)" />
						<NcColorPicker v-model="color" :advanced-fields="true">
							<CalendarIcon :inline="true" :style="{ color: correlationColor(tasksLocalCollections, ritem.id) }" />
						</NcColorPicker>
						<label>
							{{ ritem.label }}
						</label>
						<label v-if="ritem.count && ritem.count > 0">
							({{ ritem.count }} {{ t('integration_jmapc', 'Tasks') }})
						</label>
						<label v-if="correlationHarmonized(tasksLocalCollections, ritem.id) > 0">
							{{ t('integration_jmapc', 'Last Harmonized {0}', {0: formatDate(correlationHarmonized(tasksLocalCollections, ritem.id))}) }}
						</label>
						<label v-else>
							{{ t('integration_jmapc', 'Never harmonized') }}
						</label>
					</li>
				</ul>
				<div v-else-if="tasksRemoteCollections.length === 0" class="empty-message">
					{{ t('integration_jmapc', 'No tasks collections were found in the connected account') }}
				</div>
				<div v-else class="loading-message">
					{{ t('integration_jmapc', 'Loading tasks collections from the connected account') }}
				</div>
			</div>
		</div>
		<div class="actions">
			<NcButton :disabled="busy" @click="emit('save')">
				<template #icon>
					<CheckIcon />
				</template>
				{{ t('integration_jmapc', 'Save') }}
			</NcButton>
			<NcButton :disabled="busy" @click="emit('harmonize')">
				<template #icon>
					<LinkIcon />
				</template>
				{{ t('integration_jmapc', 'Harmonize') }}
			</NcButton>
			<NcButton :disabled="busy" @click="emit('disconnect')">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('integration_jmapc', 'Disconnect') }}
			</NcButton>
		</div>
	</div>
</template>

<style scoped lang="scss">
.jmapc-section__connected {
	margin-top: 20px;
	border-top: 1px solid var(--color-border);
	border-bottom: 1px solid var(--color-border);

	.connection-status {
		margin-bottom: 30px;

		.connection-status__title {
			margin-bottom: 16px;
			font-size: 18px;
			font-weight: bold;
		}

		.connection-status__overview {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 12px;
			padding: 12px;

			span {
				font-weight: 500;
			}
		}

		.connection-status__harmonization {
			font-size: 14px;
			color: var(--color-text-maxcontrast);
			margin-inline-start: 12px;
		}
	}

	.connection-correlations-mail,
	.connection-correlations-tasks,
	.connection-correlations-contacts,
	.connection-correlations-events {
		margin-bottom: 24px;

		h3 {
			margin-bottom: 12px;
			font-size: 18px;
			font-weight: bold;
		}

		ul {
			list-style: none;
			padding: 0;
			margin: 0;

			.collections-list-item {
				display: flex;
				align-items: center;
				padding: 12px;

				label {
					flex: 1;
					font-weight: 500;

					&:last-child {
						font-size: 12px;
						font-weight: normal;
					}
				}
			}
		}
	}

	.actions {
		display: flex;
		gap: 12px;
		margin-top: 24px;
		padding-top: 20px;
	}
}
</style>
