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
		<div class="jmapc-section-heading">
			<JmapIcon :size="32" /><h2> {{ t('integration_jmapc', 'JMAP Connector') }}</h2>
		</div>
		<div class="jmapc-section-services">
			<label>
				{{ t('integration_jmapc', 'Services') }}
			</label>
			<NcSelect :clearable="false"
				:searchable="false"
				:options="availableServicesCollection"
				:value="selectedService"
				@option:selected="selectService" />
		</div>
		<div class="jmapc-content" v-if="selectedService !== null">
			<h3>{{ t('integration_jmapc', 'Authentication') }}</h3>
			<div v-if="selectService.connected !== '1'">
				<div>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Enter your JMAP Server and account information then press connect.') }}
					</div>
					<div class="fields">
						<div class="line">
							<label for="jmapc-account-description">
								<JmapIcon />
								{{ t('integration_jmapc', 'Account Description') }}
							</label>
							<NcTextField id="jmapc-account-id"
								v-model="selectedService.label"
								type="text"
								:placeholder="t('integration_jmapc', 'Description for this Account')"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="none" />
						</div>
						<div class="line">
							<label for="jmapc-account-id">
								<JmapIcon />
								{{ t('integration_jmapc', 'Account ID') }}
							</label>
							<NcTextField id="jmapc-account-id"
								v-model="selectedService.bauth_id"
								type="text"
								:placeholder="t('integration_jmapc', 'Authentication ID for your Account')"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="none" />
						</div>
						<div class="line">
							<label for="jmapc-account-secret">
								<JmapIcon />
								{{ t('integration_jmapc', 'Account Secret') }}
							</label>
							<NcPasswordField id="jmapc-account-secret"
								v-model="selectedService.bauth_secret"
								type="password"
								:placeholder="t('integration_jmapc', 'Authentication secret for your Account')"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="none" />
						</div>
						<div class="line">
							<label for="jmapc-service-authentication">
								<JmapIcon />
								{{ t('integration_jmapc', 'Authentication Type') }}
							</label>
							<NcCheckboxRadioSwitch name="sharing_permission_radio"
								v-model="selectedService.auth"
								:button-variant="true"
								value="BA"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('integration_jmapc', 'Basic') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch name="sharing_permission_radio"
								v-model="selectedService.auth"
								:button-variant="true"
								value="OA"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('integration_jmapc', 'OAuth') }}
							</NcCheckboxRadioSwitch>
						</div>
						<div v-if="configureManually" class="line">
							<label for="jmapc-service-address">
								<JmapIcon />
								{{ t('integration_jmapc', 'Service Address') }}
							</label>
							<NcTextField id="jmapc-service-address"
								v-model="selectedService.location_host"
								type="text"
								:placeholder="t('integration_jmapc', 'Service Address')"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="none" />
						</div>
						<div v-if="configureManually" class="line">
							<label for="jmapc-service-port">
								<JmapIcon />
								{{ t('integration_jmapc', 'Service Port') }}
							</label>
							<NcTextField id="jmapc-service-port"
								v-model="selectedService.location_port"
								type="text"
								:placeholder="t('integration_jmapc', 'Service Port')"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="none" />
						</div>
						<div v-if="configureManually" class="line">
							<label for="jmapc-service-path">
								<JmapIcon />
								{{ t('integration_jmapc', 'Service Path') }}
							</label>
							<NcTextField id="jmapc-service-path"
								v-model="selectedService.location_path"
								type="text"
								:placeholder="t('integration_jmapc', 'Service Path')"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="none" />
						</div>
						<div>
							<NcCheckboxRadioSwitch :checked.sync="configureManually" type="switch">
								{{ t('integration_jmapc', 'Configure server manually') }}
							</NcCheckboxRadioSwitch>
						</div>
						<div class="line">
							<label class="jmapc-connect">
								&nbsp;
							</label>
							<NcButton @click="onConnectAlternateClick">
								<template #icon>
									<CheckIcon />
								</template>
								{{ t('integration_jmapc', 'Connect') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
			<div v-else>
				<div class="jmapc-connected">
					<JmapIcon />
					<label>
						{{ t('integration_jmapc', 'Connected as {0} to {1}', {0:selectedService.address_primary, 1:selectedService.location_host}) }}
					</label>
					<NcButton @click="onDisconnectClick">
						<template #icon>
							<CloseIcon />
						</template>
						{{ t('integration_jmapc', 'Disconnect') }}
					</NcButton>
				</div>
				<div>
					{{ t('integration_jmapc', 'Synchronization was last started on ') }} {{ formatDate(state.account_harmonization_start) }}
					{{ t('integration_jmapc', 'and finished on ') }} {{ formatDate(state.account_harmonization_end) }}
				</div>
				<br>
				<div class="jmapc-correlations-contacts">
					<h3>{{ t('integration_jmapc', 'Contacts') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the remote contacts folder(s) you wish to synchronize by pressing the link button next to the contact folder name and selecting the local contacts address book to synchronize to.') }}
					</div>
					<div v-if="state.system_contacts == 1">
						<ul v-if="availableContactCollections.length > 0">
							<li v-for="ritem in availableContactCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedContactCorrelation(ritem.id, ritem.name)"
									@update:checked="changeContactCorrelation(ritem.id, $event)" />
								<ContactIcon :inline="true" :style="{ color: establishedContactCorrelationColor(ritem.id) }" />
								<label>
									{{ ritem.name }} ({{ ritem.count }} Contacts)
								</label>
							</li>
						</ul>
						<div v-else-if="availableContactCollections.length == 0">
							{{ t('integration_jmapc', 'No contacts collections where found in the connected account.') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading contacts collections from the connected account.') }}
						</div>
						<br>
						<div>
							<label>
								{{ t('integration_jmapc', 'Synchronize ') }}
							</label>
							<NcSelect v-model="state.contacts_harmonize"
								:reduce="item => item.id"
								:options="[{label: 'Never', id: '-1'}, {label: 'Manually', id: '0'}, {label: 'Automatically', id: '5'}]" />
							<label>
								{{ t('integration_jmapc', 'and if there is a conflict') }}
							</label>
							<NcSelect v-model="state.contacts_prevalence"
								:reduce="item => item.id"
								:options="[{label: 'Remote', id: 'R'}, {label: 'Local', id: 'L'}, {label: 'Chronology', id: 'C'}]" />
							<label>
								{{ t('integration_jmapc', 'prevails') }}
							</label>
						</div>
					</div>
					<div v-else>
						{{ t('integration_jmapc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app.') }}
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-events">
					<h3>{{ t('integration_jmapc', 'Calendars') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the remote calendar(s) you wish to synchronize by pressing the link button next to the calendars name and selecting the local calendar to synchronize to.') }}
					</div>
					<div v-if="state.system_events == 1">
						<ul v-if="availableEventCollections.length > 0">
							<li v-for="ritem in availableEventCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedEventCorrelation(ritem.id, ritem.name)"
									@update:checked="changeEventCorrelation(ritem.id, $event)" />
								<NcColorPicker v-model="color" :advanced-fields="true">
									<CalendarIcon :inline="true" :style="{ color: establishedEventCorrelationColor(ritem.id) }" />
								</NcColorPicker>
								<label>
									{{ ritem.name }} ({{ ritem.count }} Events)
								</label>
							</li>
						</ul>
						<div v-else-if="availableEventCollections.length == 0">
							{{ t('integration_jmapc', 'No events collections where found in the connected account.') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading events collections from the connected account.') }}
						</div>
						<br>
						<div>
							<label>
								{{ t('integration_jmapc', 'Synchronize ') }}
							</label>
							<NcSelect v-model="state.events_harmonize"
								:reduce="item => item.id"
								:options="[{label: 'Never', id: '-1'}, {label: 'Manually', id: '0'}, {label: 'Automatically', id: '5'}]" />
							<label>
								{{ t('integration_jmapc', 'and if there is a conflict') }}
							</label>
							<NcSelect v-model="state.events_prevalence"
								:reduce="item => item.id"
								:options="[{label: 'Remote', id: 'R'}, {label: 'Local', id: 'L'}, {label: 'Chronology', id: 'C'}]" />
							<label>
								{{ t('integration_jmapc', 'prevails') }}
							</label>
						</div>
					</div>
					<div v-else>
						{{ t('integration_jmapc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app.') }}
					</div>
					<br>
				</div>
				<div class="jmapc-correlations-tasks">
					<h3>{{ t('integration_jmapc', 'Tasks') }}</h3>
					<div class="settings-hint">
						{{ t('integration_jmapc', 'Select the remote Task(s) folder you wish to synchronize by pressing the link button next to the folder name and selecting the local calendar to synchronize to.') }}
					</div>
					<div v-if="state.system_tasks == 1">
						<ul v-if="availableTaskCollections.length > 0">
							<li v-for="ritem in availableTaskCollections" :key="ritem.id" class="jmapc-collectionlist-item">
								<NcCheckboxRadioSwitch type="switch"
									:checked="establishedTaskCorrelation(ritem.id, ritem.name)"
									@update:checked="changeTaskCorrelation(ritem.id, $event)" />
								<NcColorPicker v-model="color" :advanced-fields="true">
									<CalendarIcon :inline="true" :style="{ color: establishedTaskCorrelationColor(ritem.id) }" />
								</NcColorPicker>
								<label>
									{{ ritem.name }} ({{ ritem.count }} Tasks)
								</label>
							</li>
						</ul>
						<div v-else-if="availableTaskCollections.length == 0">
							{{ t('integration_jmapc', 'No tasks collections where found in the connected account.') }}
						</div>
						<div v-else>
							{{ t('integration_jmapc', 'Loading tasks collections from the connected account.') }}
						</div>
						<br>
						<div>
							<label>
								{{ t('integration_jmapc', 'Synchronize ') }}
							</label>
							<NcSelect v-model="state.tasks_harmonize"
								:reduce="item => item.id"
								:options="[{label: 'Never', id: '-1'}, {label: 'Manually', id: '0'}, {label: 'Automatically', id: '5'}]" />
							<label>
								{{ t('integration_jmapc', 'and if there is a conflict') }}
							</label>
							<NcSelect v-model="state.tasks_prevalence"
								:reduce="item => item.id"
								:options="[{label: 'Remote', id: 'R'}, {label: 'Local', id: 'L'}, {label: 'Chronology', id: 'C'}]" />
							<label>
								{{ t('integration_jmapc', 'prevails') }}
							</label>
						</div>
					</div>
					<div v-else>
						{{ t('integration_jmapc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app.') }}
					</div>
					<br>
				</div>
				<div class="jmapc-actions">
					<NcButton @click="onSaveClick()">
						<template #icon>
							<CheckIcon />
						</template>
						{{ t('integration_jmapc', 'Save') }}
					</NcButton>
					<NcButton @click="onHarmonizeClick()">
						<template #icon>
							<LinkIcon />
						</template>
						{{ t('integration_jmapc', 'Sync') }}
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
			state: loadState('integration_jmapc', 'user-configuration'),
			// services
			availableServicesCollection: [],
			// contacts
			availableContactCollections: [],
			establishedContactCorrelations: [],
			// calendars
			availableEventCollections: [],
			establishedEventCorrelations: [],
			// tasks
			availableTaskCollections: [],
			establishedTaskCorrelations: [],

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
			this.listServices()
		},
		onConnectAlternateClick() {
			const uri = generateUrl('/apps/integration_jmapc/connect-alternate')
			const data = {
				params: {
					account_bauth_id: this.state.account_bauth_id,
					account_bauth_secret: this.state.account_bauth_secret,
					account_server: this.state.account_server,
					flag: this.configureMail,
				},
			}
			axios.get(uri, data)
				.then((response) => {
					if (response.data === 'success') {
						showSuccess(('Successfully connected to JMAP account'))
						this.state.account_connected = '1'
						this.fetchPreferences()
						this.loadData()
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to authenticate with JMAP server')
						+ ': ' + error.response?.request?.responseText
					)
				})
		},
		onConnectMS365Click() {
			const ssoWindow = window.open(
				this.state.system_ms365_authrization_uri,
				t('integration_jmapc', 'Sign in Nextcloud JMAP Connector'),
				' width=600, height=700'
			)
			ssoWindow.focus()
			window.addEventListener('message', (event) => {
				console.debug('Child window message received', event)
				this.state.account_connected = '1'
				this.fetchPreferences()
				this.loadData()
			})
		},
		onDisconnectClick() {
			const uri = generateUrl('/apps/integration_jmapc/disconnect')
			axios.get(uri)
				.then((response) => {
					showSuccess(('Successfully disconnected from JMAP account'))
					// state
					this.state.account_connected = '0'
					this.fetchPreferences()
					// contacts
					this.availableContactCollections = []
					this.availableLocalContactCollections = []
					this.establishedContactCorrelations = []
					// events
					this.availableEventCollections = []
					this.availableLocalEventCollections = []
					this.establishedEventCorrelations = []
					// tasks
					this.availableTaskCollections = []
					this.availableLocalTaskCollections = []
					this.establishedTaskCorrelations = []
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to disconnect from JMAP account')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		onSaveClick() {
			this.depositPreferences({
				contacts_prevalence: this.state.contacts_prevalence,
				contacts_harmonize: this.state.contacts_harmonize,
				contacts_actions_local: this.state.contacts_actions_local,
				contacts_actions_remote: this.state.contacts_actions_remote,
				events_prevalence: this.state.events_prevalence,
				events_harmonize: this.state.events_harmonize,
				events_actions_local: this.state.events_actions_local,
				events_actions_remote: this.state.events_actions_remote,
				tasks_prevalence: this.state.tasks_prevalence,
				tasks_harmonize: this.state.tasks_harmonize,
				tasks_actions_local: this.state.tasks_actions_local,
				tasks_actions_remote: this.state.tasks_actions_remote,
			})
			this.depositCorrelations()
		},
		onHarmonizeClick() {
			const uri = generateUrl('/apps/integration_jmapc/harmonize')
			axios.get(uri)
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
		listServices() {
			const uri = generateUrl('/apps/integration_jmapc/service-list')
			axios.get(uri)
				.then((response) => {
					if (response.data) {
						this.availableServicesCollection = response.data
						showSuccess(('Found ' + this.availableServicesCollection.length + ' Configured Services'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to load remote collections list')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		selectService(option) {
			if (!option) {
				return
			}

			this.selectedService = option
		},
		fetchCollections() {
			const uri = generateUrl('/apps/integration_jmapc/fetch-collections')
			axios.get(uri)
				.then((response) => {
					if (response.data.ContactCollections) {
						this.availableContactCollections = response.data.ContactCollections
						showSuccess(('Found ' + this.availableContactCollections.length + ' Remote Contacts Collections'))
					}
					if (response.data.EventCollections) {
						this.availableEventCollections = response.data.EventCollections
						showSuccess(('Found ' + this.availableEventCollections.length + ' Remote Events Collections'))
					}
					if (response.data.TaskCollections) {
						this.availableTaskCollections = response.data.TaskCollections
						showSuccess(('Found ' + this.availableTaskCollections.length + ' Remote Tasks Collections'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to load remote collections list')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		fetchCorrelations() {
			const uri = generateUrl('/apps/integration_jmapc/fetch-correlations')
			axios.get(uri)
				.then((response) => {
					if (response.data.ContactCorrelations) {
						this.establishedContactCorrelations = response.data.ContactCorrelations
						showSuccess(('Found ' + this.establishedContactCorrelations.length + ' Contact Collection Correlations'))
					}
					if (response.data.EventCorrelations) {
						this.establishedEventCorrelations = response.data.EventCorrelations
						showSuccess(('Found ' + this.establishedEventCorrelations.length + ' Event Collection Correlations'))
					}
					if (response.data.TaskCorrelations) {
						this.establishedTaskCorrelations = response.data.TaskCorrelations
						showSuccess(('Found ' + this.establishedTaskCorrelations.length + ' Task Collection Correlations'))
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to load collection correlations list')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})
		},
		depositCorrelations() {
			const uri = generateUrl('/apps/integration_jmapc/deposit-correlations')
			const data = {
				ContactCorrelations: this.establishedContactCorrelations,
				EventCorrelations: this.establishedEventCorrelations,
				TaskCorrelations: this.establishedTaskCorrelations,
			}
			axios.put(uri, data)
				.then((response) => {
					showSuccess('Saved correlations')
					if (response.data.ContactCorrelations) {
						this.establishedContactCorrelations = response.data.ContactCorrelations
						showSuccess('Found ' + this.establishedContactCorrelations.length + ' Contact Collection Correlations')
					}
					if (response.data.EventCorrelations) {
						this.establishedEventCorrelations = response.data.EventCorrelations
						showSuccess('Found ' + this.establishedEventCorrelations.length + ' Event Collection Correlations')
					}
					if (response.data.TaskCorrelations) {
						this.establishedTaskCorrelations = response.data.TaskCorrelations
						showSuccess('Found ' + this.establishedTaskCorrelations.length + ' Task Collection Correlations')
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to save correlations') + ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
				})

		},
		fetchPreferences() {
			const uri = generateUrl('/apps/integration_jmapc/fetch-preferences')
			axios.get(uri)
				.then((response) => {
					if (response.data) {
						this.state = response.data
					}
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to retrieve preferences')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		depositPreferences(values) {
			const data = {
				values,
			}
			const uri = generateUrl('/apps/integration_jmapc/deposit-preferences')
			axios.put(uri, data)
				.then((response) => {
					showSuccess(t('integration_jmapc', 'Saved preferences'))
				})
				.catch((error) => {
					showError(
						t('integration_jmapc', 'Failed to save preferences')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		changeContactCorrelation(roid, e) {
			const cid = this.establishedContactCorrelations.findIndex(i => String(i.roid) === String(roid))

			if (cid === -1) {
				this.establishedContactCorrelations.push({ id: null, roid, type: 'CC', enabled: e })
			} else {
				this.establishedContactCorrelations[cid].enabled = e
			}
		},
		changeEventCorrelation(roid, e) {
			const cid = this.establishedEventCorrelations.findIndex(i => String(i.roid) === String(roid))

			if (cid === -1) {
				this.establishedEventCorrelations.push({ id: null, roid, type: 'EC', enabled: e })
			} else {
				this.establishedEventCorrelations[cid].enabled = e
			}
		},
		changeTaskCorrelation(roid, e) {
			const cid = this.establishedTaskCorrelations.findIndex(i => String(i.roid) === String(roid))

			if (cid === -1) {
				this.establishedTaskCorrelations.push({ id: null, roid, type: 'TC', enabled: e })
			} else {
				this.establishedTaskCorrelations[cid].enabled = e
			}
		},
		establishedContactCorrelation(roid, label) {
			const citem = this.establishedContactCorrelations.find(i => String(i.roid) === String(roid))
			if (typeof citem !== 'undefined') {
				if (Boolean(citem.enabled) === true) {
					return true
				} else {
					return false
				}
			} else {
				this.establishedContactCorrelations.push({ id: null, roid, type: 'CC', label, color: this.randomColor(), enabled: false })
				return false
			}
		},
		establishedEventCorrelation(roid, label) {
			const citem = this.establishedEventCorrelations.find(i => String(i.roid) === String(roid))
			if (typeof citem !== 'undefined') {
				if (Boolean(citem.enabled) === true) {
					return true
				} else {
					return false
				}
			} else {
				this.establishedEventCorrelations.push({ id: null, roid, type: 'EC', label, color: this.randomColor(), enabled: false })
				return false
			}
		},
		establishedTaskCorrelation(roid, label) {
			const citem = this.establishedTaskCorrelations.find(i => String(i.roid) === String(roid))
			if (typeof citem !== 'undefined') {
				if (Boolean(citem.enabled) === true) {
					return true
				} else {
					return false
				}
			} else {
				this.establishedTaskCorrelations.push({ id: null, roid, type: 'TC', label, color: this.randomColor(), enabled: false })
				return false
			}
		},
		establishedContactCorrelationColor(roid) {
			const citem = this.establishedContactCorrelations.find(i => String(i.roid) === String(roid))
			if (typeof citem !== 'undefined') {
				return citem.color || this.randomColor()
			} else {
				return this.randomColor()
			}
		},
		establishedEventCorrelationColor(roid) {
			const citem = this.establishedEventCorrelations.find(i => String(i.roid) === String(roid))
			if (typeof citem !== 'undefined') {
				return citem.color || this.randomColor()
			} else {
				return this.randomColor()
			}
		},
		establishedTaskCorrelationColor(roid) {
			const citem = this.establishedTaskCorrelations.find(i => String(i.roid) === String(roid))
			if (typeof citem !== 'undefined') {
				return citem.color || this.randomColor()
			} else {
				return this.randomColor()
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
	.jmapc-section-heading {
		display:inline-block;
		vertical-align:middle;
	}

	.jmapc-connected {
		display: flex;
		align-items: center;

		label {
			padding-left: 1em;
			padding-right: 1em;
		}
	}

	.jmapc-collectionlist-item {
		display: flex;
		align-items: center;

		label {
			padding-left: 1em;
			padding-right: 1em;
		}
	}

	.jmapc-actions {
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
