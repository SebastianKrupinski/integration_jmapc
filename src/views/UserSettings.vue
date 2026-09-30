<!--
 - SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Collection } from '../types/Collection.ts'
import type { SystemConfiguration } from '../types/SystemConfiguration.ts'
import type { Service } from '../types/Service.ts'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { onMounted, reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import AccountRemoveIcon from 'vue-material-design-icons/AccountMinus.vue'
import AccountAddIcon from 'vue-material-design-icons/AccountPlus.vue'
import SettingsConnectedService from '../components/SettingsConnectedService.vue'
import SettingsEmptyState from '../components/SettingsEmptyState.vue'
import SettingsFreshService from '../components/SettingsFreshService.vue'
import JmapIcon from '../icons/JmapIcon.vue'

// Reactive data
const systemConfiguration = reactive<SystemConfiguration>(loadState('integration_jmapc', 'system-configuration') as SystemConfiguration)

// Services
const configuredServices = ref<Service[]>([])
const selectedService = ref<Service | null>(null)

// Whether an action (save/harmonize/disconnect) is currently in progress
const busy = ref<boolean>(false)

// Runs an action while preventing any other action from starting until it completes
async function runExclusive(action: () => void | Promise<void>): Promise<void> {
	if (busy.value) {
		return
	}
	busy.value = true
	try {
		await action()
	} finally {
		busy.value = false
	}
}

const mailRemoteSupported = ref(false)
const tasksRemoteSupported = ref(false)
const tasksRemoteCollections = ref<Collection[]>([])
const tasksLocalCollections = ref<Collection[]>([])

// Contacts
const contactsRemoteSupported = ref<boolean>(false)
const contactsRemoteCollections = ref<Collection[]>([])
const contactsLocalCollections = ref<Collection[]>([])

// Events/Calendars
const eventsRemoteSupported = ref<boolean>(false)
const eventsRemoteCollections = ref<Collection[]>([])
const eventsLocalCollections = ref<Collection[]>([])

// Lifecycle
onMounted(() => runExclusive(async () => {
	await serviceList()
	if (selectedService.value === null && configuredServices.value.length === 1) {
		await serviceSelect(configuredServices.value[0])
	}
}))

// Methods
function formatDate(dt: number | undefined): string {
	if (dt) {
		return new Date(dt * 1000).toLocaleString()
	} else {
		return t('integration_jmapc', 'never')
	}
}

function getErrorResponseText(error: unknown): string {
	if (typeof error !== 'object' || error === null || !('response' in error)) {
		return error instanceof Error ? error.message : ''
	}

	const { response } = error as { response?: { request?: { responseText?: string } } }
	return response?.request?.responseText ?? ''
}

function freshService(): void {
	if (busy.value) {
		return
	}
	resetCollections()
	selectedService.value = { label: t('integration_jmapc', 'New connection'), auth: 'BA', location_protocol: 'https', location_security: true }
}
async function connectService(service: Service): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/connect')
	const data = {
		service,
	}
	try {
		const response = await axios.post(uri, data)
		if (response.data !== 'success') {
			throw new Error('Unexpected connection response')
		}
		const previousIds = new Set(configuredServices.value.map((item) => String(item.id)))
		if (!await serviceList()) {
			return
		}
		const addedServices = configuredServices.value.filter((item) => !previousIds.has(String(item.id)))
		if (addedServices.length === 0) {
			throw new Error(t('integration_jmapc', 'The connected account was not found in the service list'))
		}
		selectedService.value = null
		if (addedServices.length === 1) {
			await serviceSelect(addedServices[0])
		}
		showSuccess(t('integration_jmapc', 'Successfully connected to account'))
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Failed to authenticate with server')
			+ ': ' + getErrorResponseText(error))
	}
}

async function disconnectService(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/disconnect')
	const data = {
		sid: selectedService.value?.id,
	}
	try {
		await axios.post(uri, data)
		showSuccess(t('integration_jmapc', 'Disconnected from account'))
		// Reset state
		selectedService.value = null
		resetCollections()
		// refresh service list
		await serviceList()
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Failed to disconnect from account')
			+ ': ' + getErrorResponseText(error))
	}
}

function modifyService(): Promise<void> {
	return localCollectionsDeposit()
}

async function harmonizeService(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/harmonize')
	const data = {
		sid: selectedService.value?.id,
	}
	try {
		await axios.post(uri, data)
		showSuccess(t('integration_jmapc', 'Synchronized'))
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Synchronization failed')
			+ ': ' + getErrorResponseText(error))
	}
}

async function serviceList(): Promise<boolean> {
	const uri = generateUrl('/apps/integration_jmapc/service/list')
	try {
		const response = await axios.get(uri)
		if (response.data) {
			configuredServices.value = Object.values(response.data)
			showSuccess(n('integration_jmapc', 'Found {count} configured service', 'Found {count} configured services', configuredServices.value.length, { count: configuredServices.value.length }))
			return true
		}
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Failed to load service list')
			+ ': ' + getErrorResponseText(error))
	}
	return false
}

async function serviceSelect(option: Service | null | undefined): Promise<void> {
	if (!option) {
		return
	}
	selectedService.value = option
	resetCollections()
	if (!option.connected || option.id === undefined) {
		return
	}

	await Promise.all([remoteCollectionsFetch(), localCollectionsFetch()])
}
async function remoteCollectionsFetch(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/remote/collections/fetch')
	const params = {
		sid: selectedService.value?.id,
	}
	try {
		const response = await axios.get(uri, { params })
		mailRemoteSupported.value = Boolean(response.data.MailSupported)
		tasksRemoteSupported.value = Boolean(response.data.TasksSupported)
		tasksRemoteCollections.value = response.data.TasksCollections ?? []
		if (response.data.ContactsSupported) {
			contactsRemoteSupported.value = response.data.ContactsSupported
			contactsRemoteCollections.value = response.data.ContactsCollections
			showSuccess(n('integration_jmapc', 'Found {count} remote contacts collection', 'Found {count} remote contacts collections', contactsRemoteCollections.value.length, { count: contactsRemoteCollections.value.length }))
		}
		if (response.data.EventsSupported) {
			eventsRemoteSupported.value = response.data.EventsSupported
			eventsRemoteCollections.value = response.data.EventsCollections
			showSuccess(n('integration_jmapc', 'Found {count} remote events collection', 'Found {count} remote events collections', eventsRemoteCollections.value.length, { count: eventsRemoteCollections.value.length }))
		}
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Failed to load remote collections')
			+ ': ' + getErrorResponseText(error))
	}
}

async function localCollectionsFetch(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/local/collections/fetch')
	const params = {
		sid: selectedService.value?.id,
	}
	try {
		const response = await axios.get(uri, { params })
		tasksLocalCollections.value = response.data.TaskCollections ?? []
		if (response.data.ContactCollections) {
			contactsLocalCollections.value = response.data.ContactCollections
			showSuccess(n('integration_jmapc', 'Found {count} local contact collection', 'Found {count} local contact collections', contactsLocalCollections.value.length, { count: contactsLocalCollections.value.length }))
		}
		if (response.data.EventCollections) {
			eventsLocalCollections.value = response.data.EventCollections
			showSuccess(n('integration_jmapc', 'Found {count} local event collection', 'Found {count} local event collections', eventsLocalCollections.value.length, { count: eventsLocalCollections.value.length }))
		}
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Failed to load local collections')
			+ ': ' + getErrorResponseText(error))
	}
}

async function localCollectionsDeposit(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/local/collections/deposit')
	const data = {
		sid: selectedService.value?.id,
		ContactCorrelations: contactsLocalCollections.value,
		EventCorrelations: eventsLocalCollections.value,
		TaskCorrelations: tasksLocalCollections.value,
	}
	try {
		const response = await axios.post(uri, data)
		tasksLocalCollections.value = response.data.TaskCollections ?? []
		if (response.data.ContactCollections) {
			contactsLocalCollections.value = response.data.ContactCollections
		}
		if (response.data.EventCollections) {
			eventsLocalCollections.value = response.data.EventCollections
		}
		showSuccess(t('integration_jmapc', 'Saved correlations'))
	} catch (error: unknown) {
		showError(t('integration_jmapc', 'Failed to save correlations')
			+ ': ' + getErrorResponseText(error))
	}
}
function changeCorrelation(local: Collection[], remote: Collection[], rcid: string | null, enabled: boolean): void {
	if (!rcid) {
		return
	}
	const collection = local.find((item) => String(item.ccid) === String(rcid))
	if (collection) {
		collection.enabled = enabled
		return
	}
	const source = remote.find((item) => String(item.id) === String(rcid))
	if (source?.id) {
		local.push({ id: null, ccid: source.id, label: source.label, enabled })
	}
}

function resetCollections(): void {
	mailRemoteSupported.value = false
	contactsRemoteSupported.value = false
	contactsRemoteCollections.value = []
	contactsLocalCollections.value = []
	eventsRemoteSupported.value = false
	eventsRemoteCollections.value = []
	eventsLocalCollections.value = []
	tasksRemoteSupported.value = false
	tasksRemoteCollections.value = []
	tasksLocalCollections.value = []
}
</script>

<template>
	<div class="jmapc-settings">
		<div class="jmapc-section__title">
			<JmapIcon class="logo" />
			<span class="label">
				{{ t('integration_jmapc', 'JMAP Connector') }}
			</span>
		</div>
		<div class="jmapc-section__selector">
			<label>
				{{ t('integration_jmapc', 'Services') }}
			</label>
			<NcSelect :model-value="selectedService"
				:clearable="false"
				:searchable="false"
				:options="configuredServices"
				:disabled="busy"
				@option:selected="runExclusive(() => serviceSelect($event))" />
			<NcButton :disabled="busy || !selectedService?.connected"
				:aria-label="t('integration_jmapc', 'Disconnect')"
				@click="runExclusive(disconnectService)">
				<template #icon>
					<AccountRemoveIcon :size="20" />
				</template>
			</NcButton>
			<NcButton :disabled="busy"
				:aria-label="t('integration_jmapc', 'Add service')"
				@click="freshService()">
				<template #icon>
					<AccountAddIcon :size="20" />
				</template>
			</NcButton>
		</div>

		<SettingsEmptyState v-if="selectedService === null" :busy="busy" @add-service="freshService()" />

		<SettingsFreshService v-if="selectedService !== null && !Boolean(selectedService.connected)"
			:service="selectedService"
			:busy="busy"
			@connect="runExclusive(() => connectService($event))" />

		<SettingsConnectedService v-if="selectedService !== null && Boolean(selectedService.connected)"
			:service="selectedService"
			:busy="busy"
			:system-configuration="systemConfiguration"
			:contacts-remote-supported="contactsRemoteSupported"
			:contacts-remote-collections="contactsRemoteCollections"
			:contacts-local-collections="contactsLocalCollections"
			:events-remote-supported="eventsRemoteSupported"
			:events-remote-collections="eventsRemoteCollections"
			:events-local-collections="eventsLocalCollections"
			:mail-remote-supported="mailRemoteSupported"
			:tasks-remote-supported="tasksRemoteSupported"
			:tasks-remote-collections="tasksRemoteCollections"
			:tasks-local-collections="tasksLocalCollections"
			:change-task-correlation="(id, enabled) => changeCorrelation(tasksLocalCollections, tasksRemoteCollections, id, enabled)"
			:format-date="formatDate"
			:change-contact-correlation="(id, enabled) => changeCorrelation(contactsLocalCollections, contactsRemoteCollections, id, enabled)"
			:change-event-correlation="(id, enabled) => changeCorrelation(eventsLocalCollections, eventsRemoteCollections, id, enabled)"
			@save="runExclusive(modifyService)"
			@harmonize="runExclusive(harmonizeService)"
			@disconnect="runExclusive(disconnectService)" />
	</div>
</template>

<style scoped lang="scss">
.jmapc-settings {
	padding: 30px;
	max-width: 100%;
	width: 100%;
}

.jmapc-section__title {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 20px;

	.logo {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.label {
		font-size: 24px;
		font-weight: bold;
		line-height: 1;
		display: flex;
		align-items: center;
	}
}

.jmapc-section__selector {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 20px;

	label {
		font-weight: bold;
		white-space: nowrap;
	}
}

</style>
