<template>
	<div id="osm_prefs" class="section">
		<h2>
			<OsmIcon class="icon" />
			{{ t('integration_openstreetmap', 'OpenStreetMap integration') }}
		</h2>
		<div id="osm-content">
			<NcTextField
				id="maptiler-api-key"
				v-model="state.maptiler_api_key"
				type="password"
				:label="t('integration_openstreetmap', 'Maptiler API key')"
				:show-trailing-button="!!state.maptiler_api_key"
				class="input"
				@update:model-value="onInput"
				@trailing-button-click="state.maptiler_api_key = '' ; onInput()">
				<template #icon>
					<KeyIcon :size="20" />
				</template>
			</NcTextField>
			<NcFormBox>
				<NcFormBoxSwitch
					:model-value="state.search_location_enabled"
					@update:model-value="onCheckboxChanged($event, 'search_location_enabled')">
					{{ t('integration_openstreetmap', 'Enable searching for locations') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch
					:model-value="state.proxy_osm"
					@update:model-value="onCheckboxChanged($event, 'proxy_osm')">
					{{ t('integration_openstreetmap', 'Proxy map tiles/vectors requests via Nextcloud') }}
				</NcFormBoxSwitch>
			</NcFormBox>
		</div>
	</div>
</template>

<script>
import KeyIcon from 'vue-material-design-icons/Key.vue'

import OsmIcon from './icons/OsmIcon.vue'

import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

import { delay } from '../utils.js'

export default {
	name: 'AdminSettings',

	components: {
		OsmIcon,
		KeyIcon,
		NcTextField,
		NcFormBox,
		NcFormBoxSwitch,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_openstreetmap', 'admin-config'),
			loading: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' }, false)
		},
		onInput() {
			this.loading = true
			delay(() => {
				if (this.state.maptiler_api_key !== 'dummyApiKey') {
					this.saveOptions({
						maptiler_api_key: this.state.maptiler_api_key,
					})
				}
			}, 2000)()
		},
		async saveOptions(values, sensitive = true) {
			if (sensitive) {
				await confirmPassword()
			}
			const req = {
				values,
			}
			const url = sensitive
				? generateUrl('/apps/integration_openstreetmap/sensitive-admin-config')
				: generateUrl('/apps/integration_openstreetmap/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_openstreetmap', 'OpenStreetMap options saved'))
			}).catch((error) => {
				showError(t('integration_openstreetmap', 'Failed to save OpenStreetMap options'))
				console.error(error)
			}).then(() => {
				this.loading = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
#osm_prefs {
	#osm-content {
		margin: 16px 0 0 40px;
		max-width: 800px;
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		.icon {
			margin-right: 8px;
		}
	}
}
</style>
