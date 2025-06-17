<template>
	<NcSelect
		:model-value="value"
		:options="profileList"
		:aria-label-combobox="t('integration_openstreetmap', 'Routing profile')"
		:placeholder="t('integration_openstreetmap', 'Routing profile')"
		@update:model-value="$emit('update:value', $event)">
		<template #option="option">
			<span class="profile-option">
				<component :is="option.icon" />
				<span>{{ option.label }}</span>
			</span>
		</template>
		<template #selected-option="option">
			<span class="profile-option">
				<component :is="option.icon" />
				<span>{{ option.label }}</span>
			</span>
		</template>
	</NcSelect>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { routingProfiles } from '../mapUtils.js'

export default {
	name: 'RoutingProfileSelect',

	components: {
		NcSelect,
	},

	props: {
		value: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		profileList() {
			return Object.values(routingProfiles)
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.profile-option {
	display: flex;
	align-items: center;
	gap: 4px;
}
</style>
