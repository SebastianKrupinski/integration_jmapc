<!--
 - SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import AccountAddIcon from 'vue-material-design-icons/AccountPlus.vue'

defineProps<{ busy?: boolean }>()

const emit = defineEmits<{
	(event: 'add-service'): void
}>()
</script>

<template>
	<div class="jmapc-section__empty">
		<NcEmptyContent :description="t('integration_jmapc', 'Please select a configured service or add a new service.')">
			<template #icon>
				<AccountAddIcon />
			</template>
			<template #name>
				<h2 class="empty-content__name">
					{{ t('integration_jmapc', 'No service selected') }}
				</h2>
			</template>
			<template #action>
				<NcButton :disabled="busy" variant="primary" @click="emit('add-service')">
					<template #icon>
						<AccountAddIcon :size="20" />
					</template>
					{{ t('integration_jmapc', 'Add service') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</div>
</template>

<style scoped lang="scss">
.jmapc-section__empty {
	margin-top: 40px;
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 400px;
	width: 100%;

	.empty-content__name {
		margin: 0;
		font-size: 20px;
		font-weight: bold;
	}
}
</style>
