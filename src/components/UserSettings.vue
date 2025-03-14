<!--
*
* @copyright Copyright (c) 2024 Sebastian Krupinski <krupinski01@gmail.com>
*
* @author Sebastian Krupinski <krupinski01@gmail.com>
*
* @license AGPL-3.0-or-later
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
-->

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
						:value.sync="selectedService.label"
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
						:value.sync="selectedService.bauth_id"
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
						:value.sync="selectedService.bauth_secret"
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
						:value.sync="selectedService.oauth_id"
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
						:value.sync="selectedService.oauth_access_token"
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
						:checked.sync="selectedService.auth"
						@update:checked="(value) => selectedService.auth = value">
						{{ t('integration_jmapc', 'Basic') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch name="service_auth"
						type="radio"
						value="OA"
						button-variant-grouped="horizontal"
						:button-variant="true"
						:checked.sync="selectedService.auth"
						@update:checked="(value) => selectedService.auth = value">
						{{ t('integration_jmapc', 'OAuth') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch name="service_auth"
						type="radio"
						value="JB"
						button-variant-grouped="horizontal"
						:button-variant="true"
						:checked.sync="selectedService.auth"
						@update:checked="(value) => selectedService.auth = value">
						{{ t('integration_jmapc', 'Json Basic') }}
					</NcCheckboxRadioSwitch>
				</div>
				<div v-if="configureManually" class="parameter">
					<NcCheckboxRadioSwitch :checked.sync="selectedService.location_security" type="switch">
						{{ t('integration_ews', 'Secure Transport Verification (SSL Certificate Verification). Should always be ON, unless connecting to a Exchange system over an internal LAN.') }}
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
						:value.sync="selectedService.location_host"
						:style="{ width: '48ch' }"
						:placeholder="t('integration_jmapc', 'Service Address')" />
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
						{{ t('integration_jmapc', 'Connected as {0} to {1}', {0:selectedService.address_primary, 1:selectedService.location_host}) }}
					</label>
				</div>
				<div>
					{{ t('integration_jmapc', 'Synchronization was last started on ') }} {{ formatDate(selectedService.harmonization_start) }}
					{{ t('integration_jmapc', 'and finished on ') }} {{ formatDate(selectedService.harmonization_end) }}
				</div>
				<br>
				<div class="jmapc-correlations-contacts">
					<h3>{{ t('integration_jmapc', 'Contacts') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the contacts collection(s) you wish to synchronize by using the toggle') }}
					</div>
					<div v-if="systemConfiguration.system_contacts">
						<ul v-if="remoteContactCollections.length > 0">
							<li v-for="ritem in remoteContactCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedContactCorrelation(ritem.id)"
									@update:checked="changeContactCorrelation(ritem.id, $event)" />
								<ContactIcon :inline="true" :style="{ color: establishedContactCorrelationColor(ritem.id) }" />
								<label>
									{{ ritem.label }}
								</label>
								<label v-if="ritem.count > 0">
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
						<div v-else-if="remoteContactCollections.length == 0">
							{{ t('integration_jmapc', 'No contacts collections where found in the connected account') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading contacts collections from the connected account') }}
						</div>
					</div>
					<div v-else>
						{{ t('integration_jmapc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-events">
					<h3>{{ t('integration_jmapc', 'Calendars') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the events collection(s) you wish to synchronize by using the toggle') }}
					</div>
					<div v-if="systemConfiguration.system_events">
						<ul v-if="remoteEventCollections.length > 0">
							<li v-for="ritem in remoteEventCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedEventCorrelation(ritem.id)"
									@update:checked="changeEventCorrelation(ritem.id, $event)" />
								<NcColorPicker v-model="color" :advanced-fields="true">
									<CalendarIcon :inline="true" :style="{ color: establishedEventCorrelationColor(ritem.id) }" />
								</NcColorPicker>
								<label>
									{{ ritem.label }}
								</label>
								<label v-if="ritem.count > 0">
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
						<div v-else-if="remoteEventCollections.length == 0">
							{{ t('integration_jmapc', 'No events collections where found in the connected account') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading events collections from the connected account') }}
						</div>
					</div>
					<div v-else>
						{{ t('integration_jmapc', 'The calendar app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-tasks">
					<h3>{{ t('integration_jmapc', 'Tasks') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the task collection(s) you wish to synchronize by using the toggle') }}
					</div>
					<div v-if="systemConfiguration.system_tasks">
						<ul v-if="remoteTaskCollections.length > 0">
							<li v-for="ritem in remoteTaskCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedTaskCorrelation(ritem.id)"
									@update:checked="changeTaskCorrelation(ritem.id, $event)" />
								<NcColorPicker v-model="color" :advanced-fields="true">
									<CalendarIcon :inline="true" :style="{ color: establishedTaskCorrelationColor(ritem.id) }" />
								</NcColorPicker>
								<label>
									{{ ritem.label }}
								</label>
								<label v-if="ritem.count > 0">
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
						<div v-else-if="remoteTaskCollections.length == 0">
							{{ t('integration_jmapc', 'No tasks collections where found in the connected account.') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading tasks collections from the connected account.') }}
						</div>
					</div>
					<div v-else>
						{{ t('integration_jmapc', 'The tasks app is either disabled or not installed. Please contact your administrator to install or enable the app.') }}
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

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcColorPicker from '@nextcloud/vue/dist/Components/NcColorPicker.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import JmapIcon from './icons/JmapIcon.vue'
import AccountAddIcon from 'vue-material-design-icons/AccountPlus.vue'
import AccountRemoveIcon from 'vue-material-design-icons/AccountMinus.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import ContactIcon from 'vue-material-design-icons/ContactsOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'

export default {
	name: 'UserSettings',

	components: {
		NcTextField,
		NcPasswordField,
		NcButton,
		NcCheckboxRadioSwitch,
		NcColorPicker,
		NcSelect,
		JmapIcon,
		AccountAddIcon,
		AccountRemoveIcon,
		CheckIcon,
		CloseIcon,
		CalendarIcon,
		ContactIcon,
		LinkIcon,
	},

	props: [],

	data() {
		return {
			readonly: true,
			systemConfiguration: loadState('integration_jmapc', 'system-configuration'),
			// services
			configuredServices: [],
			// contacts
			remoteContactCollections: [],
			localContactCollections: [],
			// calendars
			remoteEventCollections: [],
			localEventCollections: [],
			// tasks
			remoteTaskCollections: [],
			localTaskCollections: [],

			configureManually: false,
			configureMail: false,
			selectedcolor: '',
			selectedService: null,
		}
	},

	computed: {
		color: {
			get() {
				return this.selectedcolor || this.randomColor()
			},
			set(value) {
				this.selectedcolor = value
			},
		},
	},

	watch: {
	},

	mounted() {
		this.loadData()
	},

	methods: {
		loadData() {
			this.serviceList()
		},
		freshService() {
			this.selectedService = { label: 'New Connection' }
		},
		connectService() {
			const uri = generateUrl('/apps/integration_jmapc/service/connect')
			const data = {
				service: this.selectedService,
			}
			axios.post(uri, data)
				.then((response) => {
					if (response.data === 'success') {
						showSuccess(('Successfully connected to account'))
						this.selectedService.connected = 1
						this.loadData()
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to authenticate with server')
						+ ': ' + error.response?.request?.responseText
					)
				})
		},
		disconnectService() {
			const uri = generateUrl('/apps/integration_jmapc/service/disconnect')
			const data = {
				sid: this.selectedService.id,
			}
			axios.post(uri, data)
				.then((response) => {
					showSuccess(('Successfully disconnected from account'))
					// state
					this.selectedService = null
					// contacts
					this.remoteContactCollections = []
					this.availableLocalContactCollections = []
					this.localContactCollections = []
					// events
					this.remoteEventCollections = []
					this.availableLocalEventCollections = []
					this.localEventCollections = []
					// tasks
					this.remoteTaskCollections = []
					this.availableLocalTaskCollections = []
					this.localTaskCollections = []
					// refresh service list
					this.serviceList()
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to disconnect from account')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		modifyService() {
			this.localCollectionsDeposit()
		},
		harmonizeService() {
			const uri = generateUrl('/apps/integration_jmapc/service/harmonize')
			const data = {
				sid: this.selectedService.id,
			}
			axios.post(uri, data)
				.then((response) => {
					showSuccess('Synchronization Successful')
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Synchronization Failed')
						+ ': ' + error.response?.request?.responseText
					)
				})
		},
		serviceList() {
			const uri = generateUrl('/apps/integration_jmapc/service/list')
			axios.get(uri)
				.then((response) => {
					if (response.data) {
						this.configuredServices = response.data
						showSuccess(('Found ' + this.configuredServices.length + ' Configured Services'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to load service list')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		serviceSelect(option) {
			if (!option) {
				return
			}
			this.selectedService = option
			this.remoteCollectionsFetch()
			this.localCollectionsFetch()
		},
		remoteCollectionsFetch() {
			const uri = generateUrl('/apps/integration_jmapc/remote/collections/fetch')
			const params = {
				sid: this.selectedService.id,
			}
			axios.get(uri, { params })
				.then((response) => {
					if (response.data.ContactCollections) {
						this.remoteContactCollections = response.data.ContactCollections
						showSuccess(('Found ' + this.remoteContactCollections.length + ' Remote Contacts Collections'))
					}
					if (response.data.EventCollections) {
						this.remoteEventCollections = response.data.EventCollections
						showSuccess(('Found ' + this.remoteEventCollections.length + ' Remote Events Collections'))
					}
					if (response.data.TaskCollections) {
						this.remoteTaskCollections = response.data.TaskCollections
						showSuccess(('Found ' + this.remoteTaskCollections.length + ' Remote Tasks Collections'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to load remote collections')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		localCollectionsFetch() {
			const uri = generateUrl('/apps/integration_jmapc/local/collections/fetch')
			const params = {
				sid: this.selectedService.id,
			}
			axios.get(uri, { params })
				.then((response) => {
					if (response.data.ContactCollections) {
						this.localContactCollections = response.data.ContactCollections
						showSuccess(('Found ' + this.localContactCollections.length + ' Local Contact Collections'))
					}
					if (response.data.EventCollections) {
						this.localEventCollections = response.data.EventCollections
						showSuccess(('Found ' + this.localEventCollections.length + ' Local Event Collections'))
					}
					if (response.data.TaskCollections) {
						this.localTaskCollections = response.data.TaskCollections
						showSuccess(('Found ' + this.localTaskCollections.length + ' Local Task Collections'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to load remote collections')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		localCollectionsDeposit() {
			const uri = generateUrl('/apps/integration_jmapc/local/collections/deposit')
			const data = {
				sid: this.selectedService.id,
				ContactCorrelations: this.localContactCollections,
				EventCorrelations: this.localEventCollections,
				TaskCorrelations: this.localTaskCollections,
			}
			axios.post(uri, data)
				.then((response) => {
					showSuccess('Saved correlations')
					this.serviceSelect()
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to save correlations') + ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})

		},
		changeContactCorrelation(rcid, e) {
			const lCollection = this.localContactCollections.find(i => String(i.ccid) === String(rcid))

			if (lCollection === undefined) {
				const rCollection = this.remoteContactCollections.find(i => String(i.id) === String(rcid))
				this.localContactCollections.push({
					id: null,
					ccid: rCollection.id,
					label: rCollection.label,
					enabled: e,
				})
			} else {
				lCollection.enabled = e
			}
		},
		changeEventCorrelation(rcid, e) {
			const lCollection = this.localEventCollections.find(i => String(i.ccid) === String(rcid))

			if (lCollection === undefined) {
				const rCollection = this.remoteEventCollections.find(i => String(i.id) === String(rcid))
				this.localEventCollections.push({
					id: null,
					ccid: rCollection.id,
					label: rCollection.label,
					enabled: e,
				})
			} else {
				lCollection.enabled = e
			}
		},
		changeTaskCorrelation(rcid, e) {
			const lCollection = this.localTaskCollections.find(i => String(i.ccid) === String(rcid))

			if (lCollection === undefined) {
				const rCollection = this.remoteTaskCollections.find(i => String(i.id) === String(rcid))
				this.localTaskCollections.push({
					id: null,
					ccid: rCollection.id,
					label: rCollection.label,
					enabled: e,
				})
			} else {
				lCollection.enabled = e
			}
		},
		establishedContactCorrelation(rcid) {
			const collection = this.localContactCollections.find(i => String(i.ccid) === String(rcid))
			if (typeof collection !== 'undefined') {
				return true
			} else {
				return false
			}
		},
		establishedEventCorrelation(rcid) {
			const collection = this.localEventCollections.find(i => String(i.ccid) === String(rcid))
			if (typeof collection !== 'undefined') {
				return true
			} else {
				return false
			}
		},
		establishedTaskCorrelation(rcid) {
			const collection = this.localTaskCollections.find(i => String(i.ccid) === String(rcid))
			if (typeof collection !== 'undefined') {
				return true
			} else {
				return false
			}
		},
		establishedContactCorrelationColor(ccid) {
			const collection = this.localContactCollections.find(i => String(i.ccid) === String(ccid))
			if (typeof collection !== 'undefined') {
				return collection.color || this.randomColor()
			} else {
				return this.randomColor()
			}
		},
		establishedEventCorrelationColor(ccid) {
			const collection = this.localEventCollections.find(i => String(i.ccid) === String(ccid))
			if (typeof collection !== 'undefined') {
				return collection.color || this.randomColor()
			} else {
				return this.randomColor()
			}
		},
		establishedTaskCorrelationColor(ccid) {
			const collection = this.localTaskCollections.find(i => String(i.ccid) === String(ccid))
			if (typeof collection !== 'undefined') {
				return collection.color || this.randomColor()
			} else {
				return this.randomColor()
			}
		},
		establishedContactCorrelationHarmonized(ccid) {
			const collection = this.localContactCollections.find(i => String(i.ccid) === String(ccid))
			if (typeof collection !== 'undefined') {
				return collection.hlockhb || 0
			} else {
				return 0
			}
		},
		establishedEventCorrelationHarmonized(ccid) {
			const collection = this.localEventCollections.find(i => String(i.ccid) === String(ccid))
			if (typeof collection !== 'undefined') {
				return collection.hlockhb || 0
			} else {
				return 0
			}
		},
		establishedTaskCorrelationHarmonized(ccid) {
			const collection = this.localTaskCollections.find(i => String(i.ccid) === String(ccid))
			if (typeof collection !== 'undefined') {
				return collection.hlockhb || 0
			} else {
				return 0
			}
		},
		formatDate(dt) {
			if (dt) {
				return (new Date(dt * 1000)).toLocaleString()
			} else {
				return 'never'
			}
		},
		randomColor() {
			return '#' + (Math.random() * 0xFFFFFF << 0).toString(16).padStart(6, '0')
		},
	},
}
</script>

<style scoped lang="scss">
#jmapc_settings {
	.jmapc-page-title {
		display: ruby;
	}
	.jmapc-page-title h2 {
		padding-left: 1%;
	}
	.jmapc-page-title .logo {
		vertical-align: sub;
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
