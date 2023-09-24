<template>
	<div id="osm_prefs" class="section">
		<h2>
			<OsmIcon class="icon" />
			{{ t('integration_openstreetmap', 'OpenStreetMap integration') }}
		</h2>
		<div id="osm-content">
			<div class="line">
				<label for="maptiler-api-key">
					<KeyIcon :size="20" class="icon" />
					{{ t('integration_openstreetmap', 'Maptiler API key') }}
				</label>
				<input id="maptiler-api-key"
					v-model="state.maptiler_api_key"
					type="password"
					:placeholder="t('integration_openstreetmap', '...')"
					@input="onInput">
			</div>
			<NcCheckboxRadioSwitch
				:checked="state.search_location_enabled"
				@update:checked="onCheckboxChanged($event, 'search_location_enabled')">
				{{ t('integration_openstreetmap', 'Enable searching for locations') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import KeyIcon from 'vue-material-design-icons/Key.vue'

import OsmIcon from './icons/OsmIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		OsmIcon,
		KeyIcon,
		NcCheckboxRadioSwitch,
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
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({
					maptiler_api_key: this.state.maptiler_api_key,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_openstreetmap/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_openstreetmap', 'OpenStreetMap options saved'))
			}).catch((error) => {
				showError(
					t('integration_openstreetmap', 'Failed to save OpenStreetMap options')
					+ ': ' + (error.response?.data?.error ?? ''),
				)
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
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
