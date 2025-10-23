<!--
 - SPDX-FileCopyrightText: 2023 Sebastian Krupinski <krupinski01@gmail.com>
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'

// Temporary replacement for @nextcloud/dialogs until Vue 3 compatibility
const showSuccess = (message: string) => {
	console.log('Success:', message)
	// Could use a simple notification or alert
}

const showError = (message: string) => {
	console.error('Error:', message)
	// Could use a simple notification or alert
}

import { 
	NcTextField, 
	NcPasswordField, 
	NcButton, 
	NcCheckboxRadioSwitch, 
	NcColorPicker, 
	NcSelect 
} from '@nextcloud/vue'

import JmapIcon from '../icons/JmapIcon.vue'
import AccountAddIcon from 'vue-material-design-icons/AccountPlus.vue'
import AccountRemoveIcon from 'vue-material-design-icons/AccountMinus.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import ContactIcon from 'vue-material-design-icons/ContactsOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'

// Types
interface SystemConfiguration {
	system_mail: boolean
	system_contacts: boolean
	system_events: boolean
	system_tasks: boolean
}

interface Service {
	id: string
	label: string
	connected: number
	auth: 'BA' | 'OA' | 'JB'
	bauth_id?: string
	bauth_secret?: string
	oauth_id?: string
	oauth_access_token?: string
	location_host?: string
	location_protocol?: string
	location_security?: boolean
	location_port?: string
	location_path?: string
	address_primary?: string
	harmonization_start?: number
	harmonization_end?: number
}

interface Collection {
	id: string | null
	ccid: string
	label: string
	enabled?: boolean
	color?: string
	hlockhb?: number
	count?: number
}

// Reactive data
const readonly = ref<boolean>(true)
const systemConfiguration = reactive<SystemConfiguration>(
	loadState('integration_jmapc', 'system-configuration') as SystemConfiguration
)

// Services
const configuredServices = ref<Service[]>([])
const selectedService = ref<Service | null>(null)

// Mail
const mailRemoteSupported = ref<boolean>(false)

// Contacts
const contactsRemoteSupported = ref<boolean>(false)
const contactsRemoteCollections = ref<Collection[]>([])
const contactsLocalCollections = ref<Collection[]>([])

// Events/Calendars
const eventsRemoteSupported = ref<boolean>(false)
const eventsRemoteCollections = ref<Collection[]>([])
const eventsLocalCollections = ref<Collection[]>([])

// Tasks
const tasksRemoteSupported = ref<boolean>(false)
const tasksRemoteCollections = ref<Collection[]>([])
const tasksLocalCollections = ref<Collection[]>([])

// UI State
const configureManually = ref<boolean>(false)
const configureMail = ref<boolean>(false)
const selectedcolor = ref<string>('')

// Computed
const color = computed({
	get() {
		return selectedcolor.value || randomColor()
	},
	set(value: string) {
		selectedcolor.value = value
	},
})

// Lifecycle
onMounted(() => {
	loadData()
})

// Methods
function randomColor(): string {
	return '#' + (Math.random() * 0xFFFFFF << 0).toString(16).padStart(6, '0')
}

function formatDate(dt: number | undefined): string {
	if (dt) {
		return (new Date(dt * 1000)).toLocaleString()
	} else {
		return 'never'
	}
}

function loadData(): void {
	serviceList()
}

function freshService(): void {
	selectedService.value = { label: 'New Connection' } as Service
}
async function connectService(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/connect')
	const data = {
		service: selectedService.value,
	}
	try {
		const response = await axios.post(uri, data)
		if (response.data === 'success') {
			showSuccess('Successfully connected to account')
			if (selectedService.value) {
				selectedService.value.connected = 1
			}
			serviceList()
			remoteCollectionsFetch()
			localCollectionsFetch()
		}
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Failed to authenticate with server')
			+ ': ' + error.response?.request?.responseText,
		)
	}
}

async function disconnectService(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/disconnect')
	const data = {
		sid: selectedService.value?.id,
	}
	try {
		await axios.post(uri, data)
		showSuccess('Successfully disconnected from account')
		// Reset state
		selectedService.value = null
		// mail
		mailRemoteSupported.value = false
		// contacts
		contactsRemoteSupported.value = false
		contactsRemoteCollections.value = []
		contactsLocalCollections.value = []
		// events
		eventsRemoteSupported.value = false
		eventsRemoteCollections.value = []
		eventsLocalCollections.value = []
		// tasks
		tasksRemoteSupported.value = false
		tasksRemoteCollections.value = []
		tasksLocalCollections.value = []
		// refresh service list
		serviceList()
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Failed to disconnect from account')
			+ ': ' + error.response?.request?.responseText,
		)
	}
}

function modifyService(): void {
	localCollectionsDeposit()
}

async function harmonizeService(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/harmonize')
	const data = {
		sid: selectedService.value?.id,
	}
	try {
		await axios.post(uri, data)
		showSuccess('Synchronization Successful')
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Synchronization Failed')
			+ ': ' + error.response?.request?.responseText,
		)
	}
}

async function serviceList(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/service/list')
	try {
		const response = await axios.get(uri)
		if (response.data) {
			configuredServices.value = Object.values(response.data)
			showSuccess('Found ' + configuredServices.value.length + ' Configured Services')
		}
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Failed to load service list')
			+ ': ' + error.response?.request?.responseText,
		)
	}
}

function serviceSelect(option: Service | null): void {
	if (!option) {
		return
	}
	selectedService.value = option
	remoteCollectionsFetch()
	localCollectionsFetch()
}
async function remoteCollectionsFetch(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/remote/collections/fetch')
	const params = {
		sid: selectedService.value?.id,
	}
	try {
		const response = await axios.get(uri, { params })
		if (response.data.MailSupported) {
			mailRemoteSupported.value = response.data.MailSupported
		}
		if (response.data.ContactsSupported) {
			contactsRemoteSupported.value = response.data.ContactsSupported
			contactsRemoteCollections.value = response.data.ContactsCollections
			showSuccess('Found ' + contactsRemoteCollections.value.length + ' Remote Contacts Collections')
		}
		if (response.data.EventsSupported) {
			eventsRemoteSupported.value = response.data.EventsSupported
			eventsRemoteCollections.value = response.data.EventsCollections
			showSuccess('Found ' + eventsRemoteCollections.value.length + ' Remote Events Collections')
		}
		if (response.data.TasksSupported) {
			tasksRemoteSupported.value = response.data.TasksSupported
			tasksRemoteCollections.value = response.data.TasksCollections
			showSuccess('Found ' + tasksRemoteCollections.value.length + ' Remote Tasks Collections')
		}
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Failed to load remote collections')
			+ ': ' + error.response?.request?.responseText,
		)
	}
}

async function localCollectionsFetch(): Promise<void> {
	const uri = generateUrl('/apps/integration_jmapc/local/collections/fetch')
	const params = {
		sid: selectedService.value?.id,
	}
	try {
		const response = await axios.get(uri, { params })
		if (response.data.ContactCollections) {
			contactsLocalCollections.value = response.data.ContactCollections
			showSuccess('Found ' + contactsLocalCollections.value.length + ' Local Contact Collections')
		}
		if (response.data.EventCollections) {
			eventsLocalCollections.value = response.data.EventCollections
			showSuccess('Found ' + eventsLocalCollections.value.length + ' Local Event Collections')
		}
		if (response.data.TaskCollections) {
			tasksLocalCollections.value = response.data.TaskCollections
			showSuccess('Found ' + tasksLocalCollections.value.length + ' Local Task Collections')
		}
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Failed to load remote collections')
			+ ': ' + error.response?.request?.responseText,
		)
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
		await axios.post(uri, data)
		showSuccess('Saved correlations')
		localCollectionsFetch()
	} catch (error: any) {
		showError(
			t('integration_jmapc', 'Failed to save correlations')
			+ ': ' + error.response?.request?.responseText,
		)
	}
}
function changeContactCorrelation(rcid: string | null, e: boolean): void {
	if (!rcid) return
	const lCollection = contactsLocalCollections.value.find(i => String(i.ccid) === String(rcid))

	if (lCollection === undefined) {
		const rCollection = contactsRemoteCollections.value.find(i => String(i.id) === String(rcid))
		if (rCollection && rCollection.id) {
			contactsLocalCollections.value.push({
				id: null,
				ccid: rCollection.id,
				label: rCollection.label,
				enabled: e,
			})
		}
	} else {
		lCollection.enabled = e
	}
}

function changeEventCorrelation(rcid: string | null, e: boolean): void {
	if (!rcid) return
	const lid = eventsLocalCollections.value.findIndex(i => String(i.ccid) === String(rcid))

	if (lid === -1) {
		const rCollection = eventsRemoteCollections.value.find(i => String(i.id) === String(rcid))
		if (rCollection && rCollection.id) {
			eventsLocalCollections.value.push({
				id: null,
				ccid: rCollection.id,
				label: rCollection.label,
				enabled: e,
			})
		}
	} else {
		eventsLocalCollections.value[lid].enabled = e
	}
}

function changeTaskCorrelation(rcid: string | null, e: boolean): void {
	if (!rcid) return
	const lCollection = tasksLocalCollections.value.find(i => String(i.ccid) === String(rcid))

	if (lCollection === undefined) {
		const rCollection = tasksRemoteCollections.value.find(i => String(i.id) === String(rcid))
		if (rCollection && rCollection.id) {
			tasksLocalCollections.value.push({
				id: null,
				ccid: rCollection.id,
				label: rCollection.label,
				enabled: e,
			})
		}
	} else {
		lCollection.enabled = e
	}
}

const establishedContactCorrelation = computed(() => {
	return (rcid: string | null): boolean => {
		if (!rcid) return false
		const lCollection = contactsLocalCollections.value.find(i => String(i.ccid) === String(rcid))
		if (typeof lCollection === 'undefined') {
			return false
		}
		if (typeof lCollection.enabled === 'undefined') {
			return true
		}
		return lCollection.enabled
	}
})

const establishedEventCorrelation = computed(() => {
	return (rcid: string | null): boolean => {
		if (!rcid) return false
		const lCollection = eventsLocalCollections.value.find(i => String(i.ccid) === String(rcid))
		if (typeof lCollection === 'undefined') {
			return false
		}
		if (typeof lCollection.enabled === 'undefined') {
			return true
		}
		return lCollection.enabled
	}
})

const establishedTaskCorrelation = computed(() => {
	return (rcid: string | null): boolean => {
		if (!rcid) return false
		const lCollection = tasksLocalCollections.value.find(i => String(i.ccid) === String(rcid))
		if (typeof lCollection === 'undefined') {
			return false
		}
		if (typeof lCollection.enabled === 'undefined') {
			return true
		}
		return lCollection.enabled
	}
})

function establishedContactCorrelationColor(ccid: string | null): string {
	if (!ccid) return randomColor()
	const collection = contactsLocalCollections.value.find(i => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.color || randomColor()
	} else {
		return randomColor()
	}
}

function establishedEventCorrelationColor(ccid: string | null): string {
	if (!ccid) return randomColor()
	const collection = eventsLocalCollections.value.find(i => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.color || randomColor()
	} else {
		return randomColor()
	}
}

function establishedTaskCorrelationColor(ccid: string | null): string {
	if (!ccid) return randomColor()
	const collection = tasksLocalCollections.value.find(i => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.color || randomColor()
	} else {
		return randomColor()
	}
}

function establishedContactCorrelationHarmonized(ccid: string | null): number {
	if (!ccid) return 0
	const collection = contactsLocalCollections.value.find(i => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.hlockhb || 0
	} else {
		return 0
	}
}

function establishedEventCorrelationHarmonized(ccid: string | null): number {
	if (!ccid) return 0
	const collection = eventsLocalCollections.value.find(i => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.hlockhb || 0
	} else {
		return 0
	}
}

function establishedTaskCorrelationHarmonized(ccid: string | null): number {
	if (!ccid) return 0
	const collection = tasksLocalCollections.value.find(i => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.hlockhb || 0
	} else {
		return 0
	}
}
</script>

<template>
	<div id="jmapc_settings" class="section">
		<div class="jmapc-page-title">
			<JmapIcon class="logo" :size="32" />
			<h2 class="label">
				{{ t('integration_jmapc', 'JMAP Connector') }}
			</h2>
		</div>
		<div class="jmapc-section-services">
			<label>
				{{ t('integration_jmapc', 'Services') }}
			</label>
			<NcSelect :clearable="false"
				:searchable="false"
				:options="configuredServices"
				:value="selectedService"
				@option:selected="serviceSelect" />
			<NcButton @click="disconnectService()">
				<template #icon>
					<AccountRemoveIcon :size="20" />
				</template>
			</NcButton>
			<NcButton @click="freshService()">
				<template #icon>
					<AccountAddIcon :size="20" />
				</template>
			</NcButton>
		</div>
		<div v-if="selectedService !== null" class="jmapc-section-content">
			<h3>{{ t('integration_jmapc', 'Connection') }}</h3>
			<div v-if="!Boolean(selectedService.connected)" class="jmapc-section-parameters">
				<div class="description">
					{{ t('integration_jmapc', 'Enter your server and account information then press connect.') }}
				</div>
				<div class="parameter">
					<label for="jmapc-account-description">
						{{ t('integration_jmapc', 'Account Description') }}
					</label>
					<NcTextField id="jmapc-account-description"
						type="text"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						v-model="selectedService.label"
						:label-outside="true"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Description for this Account')" />
				</div>
				<div v-if="selectedService.auth === 'BA' || selectedService.auth === 'JB'" class="parameter">
					<label for="jmapc-account-bauth-id">
						{{ t('integration_jmapc', 'Account ID') }}
					</label>
					<NcTextField id="jmapc-account-bauth-id"
						type="text"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						v-model="selectedService.bauth_id"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Authentication ID for your Account')" />
				</div>
				<div v-if="selectedService.auth === 'BA' || selectedService.auth === 'JB'" class="parameter">
					<label for="jmapc-account-bauth-secret">
						{{ t('integration_jmapc', 'Account Secret') }}
					</label>
					<NcPasswordField id="jmapc-account-bauth-secret"
						type="password"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						v-model="selectedService.bauth_secret"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Authentication secret for your Account')" />
				</div>
				<div v-if="selectedService.auth === 'OA'" class="parameter">
					<label for="jmapc-account-oauth-id">
						{{ t('integration_jmapc', 'Account ID') }}
					</label>
					<NcTextField id="jmapc-account-oauth-id"
						type="text"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						v-model="selectedService.oauth_id"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Authentication ID for your Account')" />
				</div>
				<div v-if="selectedService.auth === 'OA'" class="parameter">
					<label for="jmapc-account-oauth-token">
						{{ t('integration_jmapc', 'Account Token') }}
					</label>
					<NcPasswordField id="jmapc-account-oauth-token"
						type="password"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						v-model="selectedService.oauth_access_token"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Authentication secret for your Account')" />
				</div>
				<div class="parameter">
					<label for="jmapc-service-authentication">
						{{ t('integration_jmapc', 'Authentication Type') }}
					</label>
					<NcCheckboxRadioSwitch name="service_auth"
						type="radio"
						value="BA"
						button-variant-grouped="horizontal"
						:button-variant="true"
						v-model="selectedService.auth">
						{{ t('integration_jmapc', 'Basic') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch name="service_auth"
						type="radio"
						value="OA"
						button-variant-grouped="horizontal"
						:button-variant="true"
						v-model="selectedService.auth">
						{{ t('integration_jmapc', 'OAuth') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch name="service_auth"
						type="radio"
						value="JB"
						button-variant-grouped="horizontal"
						:button-variant="true"
						v-model="selectedService.auth">
						{{ t('integration_jmapc', 'Json Basic') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="configureManually" class="parameter">
					<label for="jmapc-service-address">
						{{ t('integration_jmapc', 'Service Address') }}
					</label>
					<NcTextField id="jmapc-service-address"
						type="text"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						v-model="selectedService.location_host"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Service Address')" />
				</div>
				<div v-if="configureManually" class="parameter">
					<label for="jmapc-service-protocol">
						{{ t('integration_jmapc', 'Service Protocol') }}
					</label>
					<NcCheckboxRadioSwitch name="service_protocol"
						type="radio"
						value="http"
						button-variant-grouped="horizontal"
						:button-variant="true"
						v-model="selectedService.location_protocol">
						{{ t('integration_jmapc', 'http') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch name="service_protocol"
						type="radio"
						value="https"
						button-variant-grouped="horizontal"
						:button-variant="true"
						v-model="selectedService.location_protocol">
						{{ t('integration_jmapc', 'https') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="configureManually" class="parameter">
					<NcCheckboxRadioSwitch :checked.sync="selectedService.location_security" type="switch">
						{{ t('integration_ews', 'Secure Transport Verification (SSL Certificate Verification). Should always be ON, unless connecting to a service over a secure internal network') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="configureManually" class="parameter">
					<label for="jmapc-service-port">
						{{ t('integration_jmapc', 'Service Port') }}
					</label>
					<NcTextField id="jmapc-service-port"
						type="text"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						:value.sync="selectedService.location_port"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Service Port')" />
				</div>
				<div v-if="configureManually" class="parameter">
					<label for="jmapc-service-path">
						{{ t('integration_jmapc', 'Service Path') }}
					</label>
					<NcTextField id="jmapc-service-path"
						type="text"
						autocomplete="off"
						autocorrect="off"
						autocapitalize="none"
						:value.sync="selectedService.location_path"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Service Path')" />
				</div>
				<div>
					<NcCheckboxRadioSwitch :checked.sync="configureManually" type="switch">
						{{ t('integration_jmapc', 'Configure server manually') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div class="actions">
					<label class="jmapc-connect">
						&nbsp;
					</label>
					<NcButton @click="connectService()">
						<template #icon>
							<CheckIcon />
						</template>
						{{ t('integration_jmapc', 'Connect') }}
					</NcButton>
				</div>
			</div>
			<div v-else>
				<div class="jmapc-connected">
					<JmapIcon />
					<label>
						{{ t('integration_jmapc', 'Connected as {0} to {1}', {0:selectedService.address_primary || '', 1:selectedService.location_host || ''}) }}
					</label>
				</div>
				<div>
					{{ t('integration_jmapc', 'Synchronization was last started on ') }} {{ formatDate(selectedService.harmonization_start) }}
					{{ t('integration_jmapc', 'and finished on ') }} {{ formatDate(selectedService.harmonization_end) }}
				</div>
				<br>
				<div class="jmapc-correlations-mail">
					<h3>{{ t('integration_jmapc', 'Mail') }}</h3>
					<div v-if="!systemConfiguration.system_mail">
						{{ t('integration_jmapc', 'The mail app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
					</div>
					<div v-if="!mailRemoteSupported">
						{{ t('integration_jmapc', 'The connected service does not support mail') }}
					</div>
					<div v-if="systemConfiguration.system_mail && mailRemoteSupported">
						<div>
							{{ t('integration_jmapc', 'The connected service supports mail, but mail integration is currently limited') }}
						</div>
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-contacts">
					<h3>{{ t('integration_jmapc', 'Contacts') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the contacts collection(s) you wish to synchronize by using the toggle') }}
					</div>
					<div v-if="!systemConfiguration.system_contacts">
						{{ t('integration_jmapc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
					</div>
					<div v-if="!contactsRemoteSupported">
						{{ t('integration_jmapc', 'The connected service does not support contacts') }}
					</div>
					<div v-if="systemConfiguration.system_contacts && contactsRemoteSupported">
						<template>
							<ul v-if="contactsRemoteCollections.length > 0">
								<li v-for="ritem in contactsRemoteCollections" :key="ritem.id" class="jmapc-collectionlist-item">
									<NcCheckboxRadioSwitch type="switch"
										:checked="establishedContactCorrelation(ritem.id)"
										@update:checked="changeContactCorrelation(ritem.id, $event)" />
									<ContactIcon :inline="true" :style="{ color: establishedContactCorrelationColor(ritem.id) }" />								<label>
									{{ ritem.label }}
								</label>
								<label v-if="ritem.count && ritem.count > 0">
									({{ ritem.count }} {{ t('integration_jmapc', 'Contacts') }})
								</label>
									<label v-if="establishedContactCorrelationHarmonized(ritem.id) > 0">
										{{ t('integration_jmapc', 'Last Harmonized') }} {{ formatDate(establishedContactCorrelationHarmonized(ritem.id)) }}
									</label>
									<label v-else>
										{{ t('integration_jmapc', 'Last Harmonized never') }}
									</label>
								</li>
							</ul>
							<div v-else-if="contactsRemoteCollections.length == 0">
								{{ t('integration_jmapc', 'No contacts collections where found in the connected account') }}
							</div>
							<div v-else>
								{{ t('integration_jmapc', 'Loading contacts collections from the connected account') }}
							</div>
						</template>
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-events">
					<h3>{{ t('integration_jmapc', 'Calendars') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the events collection(s) you wish to synchronize by using the toggle') }}
					</div>
					<div v-if="!systemConfiguration.system_events">
						{{ t('integration_jmapc', 'The calendar app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
					</div>
					<div v-if="!eventsRemoteSupported">
						{{ t('integration_jmapc', 'The connected service does not support events') }}
					</div>
					<div v-if="systemConfiguration.system_events && eventsRemoteSupported">
						<ul v-if="eventsRemoteCollections.length > 0">
							<li v-for="ritem in eventsRemoteCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedEventCorrelation(ritem.id)"
									@update:checked="changeEventCorrelation(ritem.id, $event)" />
								<NcColorPicker v-model="color" :advanced-fields="true">
									<CalendarIcon :inline="true" :style="{ color: establishedEventCorrelationColor(ritem.id) }" />
								</NcColorPicker>
								<label>
									{{ ritem.label }}
								</label>
								<label v-if="ritem.count && ritem.count > 0">
									({{ ritem.count }} {{ t('integration_jmapc', 'Events') }})
								</label>
								<label v-if="establishedEventCorrelationHarmonized(ritem.id) > 0">
									{{ t('integration_jmapc', 'Last Harmonized') }} {{ formatDate(establishedEventCorrelationHarmonized(ritem.id)) }}
								</label>
								<label v-else>
									{{ t('integration_jmapc', 'Last Harmonized never') }}
								</label>
							</li>
						</ul>
						<div v-else-if="eventsRemoteCollections.length == 0">
							{{ t('integration_jmapc', 'No events collections where found in the connected account') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading events collections from the connected account') }}
						</div>
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-tasks">
					<h3>{{ t('integration_jmapc', 'Tasks') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the task collection(s) you wish to synchronize by using the toggle') }}
					</div>
					<div v-if="!systemConfiguration.system_tasks">
						{{ t('integration_jmapc', 'The tasks app is either disabled or not installed. Please contact your administrator to install or enable the app.') }}
					</div>
					<div v-if="!tasksRemoteSupported">
						{{ t('integration_jmapc', 'The connected service does not support tasks.') }}
					</div>
					<div v-if="systemConfiguration.system_tasks && tasksRemoteSupported">
						<ul v-if="tasksRemoteCollections.length > 0">
							<li v-for="ritem in tasksRemoteCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedTaskCorrelation(ritem.id)"
									@update:checked="changeTaskCorrelation(ritem.id, $event)" />
								<NcColorPicker v-model="color" :advanced-fields="true">
									<CalendarIcon :inline="true" :style="{ color: establishedTaskCorrelationColor(ritem.id) }" />
								</NcColorPicker>
								<label>
									{{ ritem.label }}
								</label>
								<label v-if="ritem.count && ritem.count > 0">
									({{ ritem.count }} {{ t('integration_jmapc', 'Tasks') }})
								</label>
								<label v-if="establishedTaskCorrelationHarmonized(ritem.id) > 0">
									{{ t('integration_jmapc', 'Last Harmonized') }} {{ formatDate(establishedTaskCorrelationHarmonized(ritem.id)) }}
								</label>
								<label v-else>
									{{ t('integration_jmapc', 'Last Harmonized never') }}
								</label>
							</li>
						</ul>
						<div v-else-if="tasksRemoteCollections.length == 0">
							{{ t('integration_jmapc', 'No tasks collections where found in the connected account.') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading tasks collections from the connected account.') }}
						</div>
					</div>
					<br>
				</div>
				<div class="jmapc-actions">
					<NcButton @click="modifyService()">
						<template #icon>
							<CheckIcon />
						</template>
						{{ t('integration_jmapc', 'Save') }}
					</NcButton>
					<NcButton @click="harmonizeService()">
						<template #icon>
							<LinkIcon />
						</template>
						{{ t('integration_jmapc', 'Harmonize') }}
					</NcButton>
					<NcButton @click="disconnectService()">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('integration_jmapc', 'Disconnect') }}
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
#jmapc_settings {
	.jmapc-page-title {
		display: flex;
		align-items: center;
	}
	.jmapc-page-title h2 {
		padding-left: 1%;
		margin: 0;
	}
	.jmapc-page-title .logo {
		padding-left: 1%;
	}
	.jmapc-section-services {
		display: flex;
		padding-left: 1%;
	}
	.jmapc-section-services label {
		display: inline-block;
		width: 25ch;
		vertical-align: middle;
	}
	.jmapc-section-connect h3 {
		font-weight: bolder;
		font-size: larger;
	}
	.jmapc-section-parameters {
		padding-bottom: 1%;
	}
	.jmapc-section-parameters .description {
		padding-bottom: 1%;
	}
	.jmapc-section-parameters .parameter {
		display: flex;
		padding-bottom: 1%;
	}
	.jmapc-section-parameters .parameter label {
		display: inline-block;
		width: 25ch;
	}
	.jmapc-section-parameters .actions {
		padding-top: 1%;
	}
	.jmapc-section-connected h3 {
		font-weight: bolder;
		font-size: larger;
	}
	.jmapc-section-connected-status {
		display: flex;
		align-items: center;
	}
	.jmapc-section-connected-status label {
		padding-right: 1%;
	}
	.jmapc-section-connected .description {
		padding-bottom: 1%;
	}
	.jmapc-section-connected ul {
		padding-bottom: 1%;
	}
	.jmapc-section-connected .actions {
		display: flex;
		align-items: center;
	}
	.jmapc-collectionlist-item {
		display: flex;
		align-items: center;

		label {
			padding-left: 1%;
			padding-right: 1%;
		}
	}
}
</style>
