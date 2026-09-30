/*
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { test } from 'node:test'
import { compileScript, parse } from '@vue/compiler-sfc'
import ts from 'typescript'
import * as vue from 'vue'
import type { Service } from '../../../src/types/Service.js'

interface RequestOptions {
	params?: { sid?: string | number }
}

interface Request extends RequestOptions {
	method: 'get' | 'post' | 'put'
	url: string
	data?: Record<string, any>
}

interface Configuration {
	services?: Service[]
	state?: Record<string, unknown>
	get?: (url: string, options?: RequestOptions) => Promise<{ data: unknown }>
	post?: (url: string, data: Record<string, any>) => Promise<{ data: unknown }>
	put?: (url: string, data: Record<string, any>) => Promise<{ data: unknown }>
}

interface TestNode {
	type: string
	text: string
	props: Record<string, any>
	children: TestNode[]
	parent: TestNode | null
}

// Render compiled Vue components without a browser; replace only external UI controls.
function mountSettings(view = 'views/UserSettings.vue', props: Record<string, any> = {}, configuration: Configuration = {}) {
	const requests: Request[] = []
	const errors: string[] = []
	const successes: string[] = []
	const services = configuration.services ?? []
	const api = {
		async get(url: string, options?: RequestOptions) {
			requests.push({ method: 'get', url, ...options })
			if (configuration.get) return configuration.get(url, options)
			return { data: url.endsWith('/service/list') ? services : {} }
		},
		async post(url: string, data: Record<string, any>) {
			requests.push({ method: 'post', url, data })
			return configuration.post ? configuration.post(url, data) : { data: {} }
		},
		async put(url: string, data: Record<string, any>) {
			requests.push({ method: 'put', url, data })
			return configuration.put ? configuration.put(url, data) : { data: {} }
		},
	}
	const translate = (_app: string, message: string, parameters: Record<string, string | number> = {}) => message.replace(/\{(\w+)\}/g, (_: string, key: string) => String(parameters[key] ?? key))
	const controls = new Map<string, vue.Component>()
	function control(name: string): vue.Component {
		if (!controls.has(name)) {
			controls.set(name, vue.defineComponent({ name, props: ['modelValue'], setup: (props, { attrs, slots }) => () => vue.h(name, { ...attrs, ...props }, Object.values(slots).flatMap(slot => slot?.() ?? [])) }))
		}
		return controls.get(name)!
	}
	const cache = new Map<string, vue.Component>()
	function load(filename: string): vue.Component {
		if (cache.has(filename)) return cache.get(filename)!
		const { descriptor } = parse(readFileSync(filename, 'utf8'), { filename })
		const script = compileScript(descriptor, { id: filename, inlineTemplate: true })
		const { outputText } = ts.transpileModule(script.content, { compilerOptions: { esModuleInterop: true, module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } })
		const module = { exports: {} as { default: vue.Component } }
		const mockedRequire = (name: string): unknown => {
			if (name === 'vue') return vue
			if (name === '@nextcloud/axios') return api
			if (name === '@nextcloud/dialogs') return { showError: (message: string) => errors.push(message), showSuccess: (message: string) => successes.push(message) }
			if (name === '@nextcloud/initial-state') return { loadState: () => configuration.state ?? { system_mail: true, system_contacts: true, system_events: true, system_tasks: true } }
			if (name === '@nextcloud/router') return { generateUrl: (url: string) => url }
			if (name === '@nextcloud/l10n') return { translate, translatePlural: (app: string, single: string, plural: string, count: number, params: Record<string, string | number>) => translate(app, count === 1 ? single : plural, params) }
			if (name.startsWith('@nextcloud/vue/components/')) return control(name.split('/').at(-1)!)
			if (name === '@nextcloud/vue') return Object.fromEntries(['NcButton', 'NcLoadingIcon', 'NcSelect'].map(name => [name, control(name)]))
			if (name.includes('icons/')) return control('Icon')
			if (name.endsWith('.vue')) return load(resolve(filename, '..', name))
			throw new Error(`Unexpected import: ${name}`)
		}
		new Function('require', 'module', 'exports', outputText)(mockedRequire, module, module.exports)
		cache.set(filename, module.exports.default)
		return module.exports.default
	}
	const node = (type: string, text = ''): TestNode => ({ type, text, props: {}, children: [], parent: null })
	const renderer = vue.createRenderer<TestNode, TestNode>({
		createElement: node,
		createText: text => node('text', text),
		createComment: () => node('comment'),
		setText: (node, text) => { node.text = text },
		setElementText: (node, text) => { node.text = text; node.children = [] },
		patchProp: (node, key, _old, value) => { node.props[key] = value },
		insert(child, parent, anchor) {
			if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1)
			child.parent = parent
			const index = anchor ? parent.children.indexOf(anchor) : -1
			parent.children.splice(index < 0 ? parent.children.length : index, 0, child)
		},
		remove(child) { child.parent?.children.splice(child.parent.children.indexOf(child), 1) },
		parentNode: node => node.parent,
		nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1] ?? null,
	})
	const root = node('root')
	const state = vue.reactive(props)
	const component = load(resolve('src', view))
	const app = renderer.createApp({ render: () => vue.h(component, state) })
	app.mount(root)
	const all = (node = root): TestNode[] => [node, ...node.children.flatMap(child => all(child))]
	const text = (node = root): string => node.text + node.children.map(text).join(' ')
	const find = (type: string, predicate: (node: TestNode) => boolean = () => true): TestNode => {
		const result = all().find(node => node.type === type && predicate(node))
		assert.ok(result, `Missing ${type} control`)
		return result
	}
	const button = (label: string) => find('NcButton', node => text(node).trim() === label || node.props['aria-label'] === label)
	return { requests, errors, successes, state, all, text, find, button, unmount: () => app.unmount() }
}

const flush = async () => { await new Promise<void>(resolve => setImmediate(resolve)); await vue.nextTick() }
const savedService: Service = { id: 12, label: 'Personal', auth: 'BA', connected: true, address_primary: 'me@example.org', location_host: 'example.org' }
const connectedProps = () => ({
	service: savedService, busy: false,
	systemConfiguration: { system_mail: true, system_contacts: true, system_events: true, system_tasks: true },
	mailRemoteSupported: true,
	contactsRemoteSupported: true, contactsRemoteCollections: [{ id: 'c', label: 'People' }], contactsLocalCollections: [{ ccid: 'c', hlockhb: 0 }],
	eventsRemoteSupported: true, eventsRemoteCollections: [{ id: 'e', label: 'Calendar' }], eventsLocalCollections: [],
	tasksRemoteSupported: true, tasksRemoteCollections: [{ id: 't', label: 'Todos' }], tasksLocalCollections: [],
	formatDate: (value?: number) => String(value ?? 'never'),
	changeContactCorrelation: () => {}, changeEventCorrelation: () => {}, changeTaskCorrelation: () => {},
})

test('empty state starts a new connection with basic authentication and secure defaults', async () => {
	const ui = mountSettings()
	await flush()
	assert.match(ui.text(), /No service selected/)
	ui.button('Add service').props.onClick()
	await flush()
	assert.equal(ui.find('NcTextField', node => node.props.id === 'jmapc-account-description').props.modelValue, 'New connection')
	assert.equal(ui.find('NcCheckboxRadioSwitch', node => node.props.value === 'BA').props.modelValue, 'BA')
	assert.equal(ui.requests.length, 1)
	ui.unmount()
})

test('setup preserves JSON Basic and OAuth, emits a copy, and resets when the selected service changes', async () => {
	const submissions: Service[] = []
	const service: Service = { label: 'New', auth: 'JB', bauth_id: 'me', bauth_secret: 'secret', location_security: true, location_protocol: 'https' }
	const ui = mountSettings('components/SettingsFreshService.vue', { service, onConnect: (value: Service) => submissions.push(value) })
	assert.ok(ui.find('NcPasswordField', node => node.props.id === 'jmapc-account-bauth-secret'))
	ui.find('NcTextField', node => node.props.id === 'jmapc-account-description').props['onUpdate:modelValue']('Edited')
	ui.button('Connect').props.onClick()
	assert.equal(submissions[0].label, 'Edited')
	assert.equal(submissions[0].auth, 'JB')
	assert.equal(service.label, 'New')
	assert.notEqual(submissions[0], service)
	ui.state.service = { label: 'OAuth account', auth: 'OA', oauth_id: 'me', oauth_access_token: 'token' }
	await flush()
	assert.ok(ui.find('NcPasswordField', node => node.props.id === 'jmapc-account-oauth-token'))
	assert.equal(ui.find('NcTextField', node => node.props.id === 'jmapc-account-description').props.modelValue, 'OAuth account')
	ui.unmount()
})

test('connect reloads the saved account before requesting collections with its ID', async () => {
	let services: Service[] = []
	let finish!: () => void
	const ui = mountSettings('views/UserSettings.vue', {}, {
		get: async url => ({ data: url.endsWith('/service/list') ? services : {} }),
		post: async () => { await new Promise<void>(resolve => { finish = resolve }); services = [savedService]; return { data: 'success' } },
	})
	await flush()
	ui.button('Add service').props.onClick()
	await flush()
	ui.button('Connect').props.onClick()
	ui.button('Connect').props.onClick()
	await flush()
	assert.equal(ui.requests.filter(request => request.method === 'post').length, 1)
	assert.equal(ui.button('Connect').props.disabled, true)
	assert.equal(ui.button('Add service').props.disabled, true)
	finish()
	await flush()
	assert.match(ui.text(), /Connected as me@example.org to example.org/)
	const fetches = ui.requests.filter(request => request.url.includes('/collections/fetch'))
	assert.equal(fetches.length, 2)
	assert.ok(fetches.every(request => request.params?.sid === 12))
	assert.equal(ui.button('Save').props.disabled, false)
	ui.unmount()
})

test('a single account is selected automatically; switches save contact, event, and task correlations', async () => {
	const ui = mountSettings('views/UserSettings.vue', {}, {
		get: async url => ({ data: url.endsWith('/service/list') ? [savedService] : url.includes('/remote/') ? {
			MailSupported: true, ContactsSupported: true, EventsSupported: true, TasksSupported: true,
			ContactsCollections: [{ id: 'c', label: 'People' }], EventsCollections: [{ id: 'e', label: 'Calendar' }], TasksCollections: [{ id: 't', label: 'Todos' }],
		} : {} }),
		post: async (_url, data) => ({ data: { ContactCollections: data.ContactCorrelations, EventCollections: data.EventCorrelations, TaskCollections: data.TaskCorrelations } }),
	})
	await flush()
	assert.match(ui.text(), /mail integration is currently limited/)
	const toggles = ui.all().filter(node => node.type === 'NcCheckboxRadioSwitch')
	assert.equal(toggles.length, 3)
	for (const toggle of toggles) toggle.props['onUpdate:modelValue'](true)
	await flush()
	ui.button('Save').props.onClick()
	await flush()
	const saved = ui.requests.find(request => request.url.endsWith('/deposit'))!.data!
	for (const [key, id] of [['ContactCorrelations', 'c'], ['EventCorrelations', 'e'], ['TaskCorrelations', 't']]) {
		assert.equal(saved[key][0].ccid, id)
		assert.equal(saved[key][0].enabled, true)
	}
	toggles[2].props['onUpdate:modelValue'](false)
	await flush()
	ui.button('Save').props.onClick()
	await flush()
	assert.equal(ui.requests.filter(request => request.url.endsWith('/deposit')).at(-1)!.data!.TaskCorrelations.length, 1)
	assert.equal(ui.requests.filter(request => request.url.endsWith('/deposit')).at(-1)!.data!.TaskCorrelations[0].enabled, false)
	ui.button('Harmonize').props.onClick()
	await flush()
	assert.equal(ui.requests.at(-1)!.data!.sid, 12)
	ui.button('Disconnect').props.onClick()
	await flush()
	assert.match(ui.text(), /No service selected/)
	assert.equal(ui.button('Disconnect').props.disabled, true)
	ui.unmount()
})

test('switching accounts clears unsupported capabilities and previous collections', async () => {
	const other = { ...savedService, id: 13, label: 'Other' }
	const ui = mountSettings('views/UserSettings.vue', {}, {
		get: async (url, options) => ({ data: url.endsWith('/service/list') ? [savedService, other] : options?.params?.sid === 12 && url.includes('/remote/') ? { MailSupported: true, TasksSupported: true, TasksCollections: [{ id: 't', label: 'Old tasks' }] } : {} }),
	})
	await flush()
	assert.match(ui.text(), /No service selected/)
	ui.find('NcSelect').props['onOption:selected'](savedService)
	await flush()
	assert.match(ui.text(), /Old tasks/)
	ui.find('NcSelect').props['onOption:selected'](other)
	await flush()
	assert.doesNotMatch(ui.text(), /Old tasks/)
	assert.match(ui.text(), /does not support mail/)
	assert.match(ui.text(), /does not support tasks/)
	ui.unmount()
})

test('connected view reports disabled apps, correlation state, dates, and emits actions', async () => {
	const events: string[] = []
	const props = connectedProps()
	props.contactsLocalCollections[0].hlockhb = 123
	const ui = mountSettings('components/SettingsConnectedService.vue', { ...props, onSave: () => events.push('save'), onHarmonize: () => events.push('harmonize'), onDisconnect: () => events.push('disconnect') })
	assert.equal(ui.find('NcCheckboxRadioSwitch').props.modelValue, true)
	assert.match(ui.text(), /Last Harmonized 123/)
	for (const label of ['Save', 'Harmonize', 'Disconnect']) ui.button(label).props.onClick()
	assert.deepEqual(events, ['save', 'harmonize', 'disconnect'])
	ui.state.busy = true
	ui.state.systemConfiguration.system_tasks = false
	await flush()
	assert.equal(ui.button('Save').props.disabled, true)
	assert.ok(ui.all().filter(node => node.type === 'NcCheckboxRadioSwitch').every(node => node.props.disabled))
	assert.match(ui.text(), /tasks app is either disabled/)
	ui.unmount()
})

test('failed connect releases the busy state and leaves the editable form available', async () => {
	const ui = mountSettings('views/UserSettings.vue', {}, { post: async () => { throw new Error('Offline') } })
	await flush()
	ui.button('Add service').props.onClick()
	await flush()
	ui.button('Connect').props.onClick()
	await flush()
	assert.match(ui.errors[0], /Offline/)
	assert.equal(ui.button('Connect').props.disabled, false)
	ui.unmount()
})

test('admin saves JMAP synchronization settings once and restores the button after errors', async () => {
	let finish!: () => void
	const state = { harmonization_mode: 'P', harmonization_thread_duration: 30, harmonization_thread_pause: 5 }
	const ui = mountSettings('views/AdminSettings.vue', {}, { state, put: async () => { await new Promise<void>(resolve => { finish = resolve }); throw new Error('Offline') } })
	ui.button('Save').props.onClick()
	ui.button('Save').props.onClick()
	await flush()
	assert.equal(ui.requests.length, 1)
	assert.deepEqual(ui.requests[0].data!.values, state)
	assert.equal(ui.requests[0].method, 'put')
	assert.equal(ui.button('Save').props.disabled, true)
	finish()
	await flush()
	assert.equal(ui.button('Save').props.disabled, false)
	assert.match(ui.errors[0], /Offline/)
	ui.unmount()
})

test('a successful response without a saved account keeps setup open and reports the problem', async () => {
	const ui = mountSettings('views/UserSettings.vue', {}, { post: async () => ({ data: 'success' }) })
	await flush()
	ui.button('Add service').props.onClick()
	await flush()
	ui.button('Connect').props.onClick()
	await flush()
	assert.match(ui.errors[0], /not found in the service list/)
	assert.equal(ui.button('Connect').props.disabled, false)
	assert.equal(ui.requests.filter(request => request.url.includes('/collections/fetch')).length, 0)
	ui.unmount()
})

test('manual setup retains JMAP discovery path and transport values in the connect payload', async () => {
	let submitted!: Service
	const ui = mountSettings('components/SettingsFreshService.vue', {
		service: { label: 'Manual', auth: 'BA', location_protocol: 'https', location_security: true },
		onConnect: (value: Service) => { submitted = value },
	})
	ui.find('NcCheckboxRadioSwitch', node => ui.text(node).includes('Configure server manually')).props['onUpdate:modelValue'](true)
	await flush()
	const path = ui.find('NcTextField', node => node.props.id === 'jmapc-service-path')
	assert.match(path.props.placeholder, /\.well-known\/jmap/)
	path.props['onUpdate:modelValue']('/jmap/session')
	ui.find('NcTextField', node => node.props.id === 'jmapc-service-address').props['onUpdate:modelValue']('jmap.example.org')
	ui.button('Connect').props.onClick()
	assert.equal(submitted.location_path, '/jmap/session')
	assert.equal(submitted.location_host, 'jmap.example.org')
	assert.equal(submitted.location_protocol, 'https')
	assert.equal(submitted.location_security, true)
	ui.unmount()
})

test('admin success uses a visible notification', async () => {
	const ui = mountSettings('views/AdminSettings.vue', {}, { state: { harmonization_mode: 'P', harmonization_thread_duration: 30, harmonization_thread_pause: 5 } })
	ui.button('Save').props.onClick()
	await flush()
	assert.deepEqual(ui.successes, ['JMAP admin configuration saved'])
	assert.equal(ui.button('Save').props.disabled, false)
	ui.unmount()
})
